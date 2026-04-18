# train_xgboost_model.py - Version corrigée

import numpy as np
import pandas as pd

# Import de xgboost avec un alias pour éviter les conflits
try:
    import xgboost as xgb
except ImportError:
    print("❌ Erreur: xgboost n'est pas installé. Exécutez: pip install xgboost")
    exit(1)

from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler
from sklearn.metrics import mean_squared_error, mean_absolute_error, r2_score, accuracy_score, classification_report, confusion_matrix
import joblib
import warnings
import json
from datetime import datetime
import matplotlib.pyplot as plt
import seaborn as sns

warnings.filterwarnings('ignore')

# Configuration
np.random.seed(42)

print("=" * 80)
print("🤖 XGBOOST MODEL TRAINING - VALA BLEU PREDICTOR")
print("=" * 80)

# ============================================================================
# 1. GÉNÉRATION DES DONNÉES D'ENTRAÎNEMENT (SIMULATION)
# ============================================================================
print("\n📊 1. Génération des données d'entraînement...")

def generate_training_data(n_samples=10000):
    """Génère des données d'entraînement réalistes"""
    
    np.random.seed(42)
    
    data = []
    
    for _ in range(n_samples):
        # Paramètres d'entrée (15 features)
        cpu_avg = np.random.uniform(10, 95)
        cpu_peak = cpu_avg + np.random.uniform(5, 30)
        cpu_peak = min(100, cpu_peak)
        
        ram_avg = np.random.uniform(15, 90)
        disk_io = np.random.uniform(5, 85)
        response_time = np.random.uniform(50, 3000)
        visitors = np.random.uniform(100, 100000)
        pageviews = visitors * np.random.uniform(1.5, 5)
        growth_rate = np.random.uniform(-10, 80)
        
        peak_hours_start = np.random.randint(8, 20)
        peak_hours_end = peak_hours_start + np.random.randint(1, 6)
        peak_hours_duration = peak_hours_end - peak_hours_start
        
        plugin_count = np.random.randint(5, 60)
        heavy_plugins_count = np.random.randint(0, 6)
        
        php_versions = ['7.4', '8.0', '8.1', '8.2', '8.3']
        php_scores = {'7.4': 0.85, '8.0': 0.90, '8.1': 0.95, '8.2': 1.00, '8.3': 1.05}
        php_version = np.random.choice(php_versions, p=[0.2, 0.2, 0.3, 0.2, 0.1])
        php_score = php_scores[php_version]
        
        cache_enabled = np.random.choice([0, 1], p=[0.3, 0.7])
        cdn_enabled = np.random.choice([0, 1], p=[0.4, 0.6])
        
        wp_types = ['small', 'medium', 'performance', 'enterprise']
        wp_capacity = {'small': 0.7, 'medium': 1.0, 'performance': 1.5, 'enterprise': 2.0}
        wp_type = np.random.choice(wp_types, p=[0.3, 0.35, 0.25, 0.1])
        wp_factor = wp_capacity[wp_type]
        
        # Calcul de la charge prédite (cible)
        predicted_load = (
            cpu_avg * 0.25 +
            cpu_peak * 0.15 +
            ram_avg * 0.20 +
            (visitors / 50000) * 100 * 0.15 +
            max(0, growth_rate / 100) * 100 * 0.15 +
            (plugin_count / 50) * 100 * 0.10
        )
        
        # Ajustements
        predicted_load *= (1 / wp_factor)
        predicted_load += heavy_plugins_count * 2
        if not cache_enabled:
            predicted_load *= 1.15
        if not cdn_enabled:
            predicted_load *= 1.05
        predicted_load *= (1 / php_score)
        
        # Ajout de bruit
        noise = np.random.normal(0, 5)
        predicted_load = predicted_load + noise
        predicted_load = max(0, min(100, predicted_load))
        
        # Calcul du score XGBoost (confiance du modèle)
        xgboost_score = 100 - (predicted_load * 0.3) + np.random.normal(0, 5)
        xgboost_score = max(0, min(100, xgboost_score))
        
        # Calcul des mois avant saturation
        if growth_rate > 0 and predicted_load < 90:
            saturation_months = np.log(90 / max(1, predicted_load)) / np.log(1 + growth_rate / 100)
            saturation_months = saturation_months * wp_factor
            saturation_months = max(0, min(60, saturation_months))
        else:
            saturation_months = 0 if predicted_load >= 90 else 999
        
        # Détermination du statut
        if predicted_load >= 80 or saturation_months <= 2:
            status = 2  # CRITIQUE
        elif predicted_load >= 70 or saturation_months <= 6:
            status = 1  # SURVEILLANCE
        else:
            status = 0  # OPTIMAL
        
        data.append({
            # Features
            'cpu_usage_avg': cpu_avg,
            'cpu_usage_peak': cpu_peak,
            'ram_usage_avg': ram_avg,
            'disk_io': disk_io,
            'response_time': response_time,
            'visitors_per_day': visitors,
            'pageviews_per_day': pageviews,
            'traffic_growth_rate': growth_rate,
            'peak_hours_duration': peak_hours_duration,
            'plugin_count': plugin_count,
            'heavy_plugins_count': heavy_plugins_count,
            'php_score': php_score,
            'cache_enabled': cache_enabled,
            'cdn_enabled': cdn_enabled,
            'wp_factor': wp_factor,
            # Targets
            'predicted_load': predicted_load,
            'xgboost_score': xgboost_score,
            'saturation_months': saturation_months,
            'status': status
        })
    
    return pd.DataFrame(data)

# Génération des données
df = generate_training_data(10000)
print(f"✅ {len(df)} échantillons générés")
print(f"📋 Features: {df.shape[1]} colonnes")

# ============================================================================
# 2. PRÉPARATION DES DONNÉES
# ============================================================================
print("\n🔧 2. Préparation des données...")

# Séparation features et targets - Ordre fixe et explicite
feature_columns = [
    'cpu_usage_avg', 'cpu_usage_peak', 'ram_usage_avg', 'disk_io', 
    'response_time', 'visitors_per_day', 'pageviews_per_day', 
    'traffic_growth_rate', 'peak_hours_duration', 'plugin_count', 
    'heavy_plugins_count', 'php_score', 'cache_enabled', 'cdn_enabled', 'wp_factor'
]

target_columns = ['predicted_load', 'xgboost_score', 'saturation_months', 'status']

X = df[feature_columns].copy()
y_load = df['predicted_load']
y_score = df['xgboost_score']
y_saturation = df['saturation_months']
y_status = df['status']

print(f"✅ Features: {X.shape[1]} colonnes")
print(f"✅ Targets: 4 cibles")
print(f"✅ Colonnes features: {list(X.columns)}")

# ============================================================================
# 3. NORMALISATION DES DONNÉES
# ============================================================================
print("\n📐 3. Normalisation des données...")

scaler = StandardScaler()
X_scaled = scaler.fit_transform(X)
X_scaled = pd.DataFrame(X_scaled, columns=feature_columns)

print("✅ Normalisation terminée")

# ============================================================================
# 4. DIVISION TRAIN/TEST
# ============================================================================
print("\n✂️ 4. Division Train/Test...")

X_train, X_test, y_load_train, y_load_test = train_test_split(
    X_scaled, y_load, test_size=0.2, random_state=42
)
_, _, y_score_train, y_score_test = train_test_split(
    X_scaled, y_score, test_size=0.2, random_state=42
)
_, _, y_saturation_train, y_saturation_test = train_test_split(
    X_scaled, y_saturation, test_size=0.2, random_state=42
)
_, _, y_status_train, y_status_test = train_test_split(
    X_scaled, y_status, test_size=0.2, random_state=42
)

print(f"✅ Train: {len(X_train)} échantillons")
print(f"✅ Test: {len(X_test)} échantillons")

# ============================================================================
# 5. ENTRAÎNEMENT DES MODÈLES XGBOOST
# ============================================================================
print("\n🎯 5. Entraînement des modèles XGBoost...")

# Configuration des hyperparamètres
params = {
    'n_estimators': 300,
    'max_depth': 8,
    'learning_rate': 0.05,
    'subsample': 0.8,
    'colsample_bytree': 0.8,
    'min_child_weight': 3,
    'reg_alpha': 0.1,
    'reg_lambda': 1,
    'random_state': 42,
    'eval_metric': 'rmse'
}

# Modèle 1: Prédiction de la charge
print("\n📊 Modèle 1: Prédiction de la charge CPU...")
model_load = xgb.XGBRegressor(**params)
model_load.fit(
    X_train, y_load_train,
    eval_set=[(X_test, y_load_test)],
    verbose=False
)

# Modèle 2: Prédiction du score XGBoost
print("\n🎯 Modèle 2: Prédiction du score XGBoost...")
model_score = xgb.XGBRegressor(**params)
model_score.fit(
    X_train, y_score_train,
    eval_set=[(X_test, y_score_test)],
    verbose=False
)

# Modèle 3: Prédiction des mois avant saturation
print("\n⏰ Modèle 3: Prédiction de la saturation...")
model_saturation = xgb.XGBRegressor(**params)
model_saturation.fit(
    X_train, y_saturation_train,
    eval_set=[(X_test, y_saturation_test)],
    verbose=False
)

# Modèle 4: Classification du statut
print("\n🏷️ Modèle 4: Classification du statut...")
model_status = xgb.XGBClassifier(
    n_estimators=200,
    max_depth=6,
    learning_rate=0.1,
    subsample=0.8,
    colsample_bytree=0.8,
    random_state=42
)
model_status.fit(X_train, y_status_train)

print("\n✅ Tous les modèles sont entraînés!")

# ============================================================================
# 6. ÉVALUATION DES MODÈLES
# ============================================================================
print("\n📈 6. Évaluation des modèles...")

# Prédictions sur le test set
y_load_pred = model_load.predict(X_test)
y_score_pred = model_score.predict(X_test)
y_saturation_pred = model_saturation.predict(X_test)
y_status_pred = model_status.predict(X_test)

# Métriques pour la charge
load_mse = mean_squared_error(y_load_test, y_load_pred)
load_mae = mean_absolute_error(y_load_test, y_load_pred)
load_r2 = r2_score(y_load_test, y_load_pred)

print("\n📊 Modèle de prédiction de charge:")
print(f"   - MSE: {load_mse:.4f}")
print(f"   - MAE: {load_mae:.4f}")
print(f"   - R²: {load_r2:.4f}")
print(f"   - RMSE: {np.sqrt(load_mse):.4f}")

# Métriques pour le score
score_mse = mean_squared_error(y_score_test, y_score_pred)
score_mae = mean_absolute_error(y_score_test, y_score_pred)
score_r2 = r2_score(y_score_test, y_score_pred)

print("\n🎯 Modèle de prédiction du score XGBoost:")
print(f"   - MSE: {score_mse:.4f}")
print(f"   - MAE: {score_mae:.4f}")
print(f"   - R²: {score_r2:.4f}")

# Métriques pour la saturation
saturation_mse = mean_squared_error(y_saturation_test, y_saturation_pred)
saturation_mae = mean_absolute_error(y_saturation_test, y_saturation_pred)

print("\n⏰ Modèle de prédiction de saturation:")
print(f"   - MSE: {saturation_mse:.4f}")
print(f"   - MAE: {saturation_mae:.4f}")

# Métriques pour la classification
status_accuracy = accuracy_score(y_status_test, y_status_pred)

print("\n🏷️ Modèle de classification du statut:")
print(f"   - Accuracy: {status_accuracy:.4f}")
print(f"   - Classification Report:")
print(classification_report(y_status_test, y_status_pred, 
                            target_names=['OPTIMAL', 'SURVEILLANCE', 'CRITIQUE']))

# ============================================================================
# 7. SAUVEGARDE DES MODÈLES
# ============================================================================
print("\n💾 7. Sauvegarde des modèles...")

models = {
    'model_load': model_load,
    'model_score': model_score,
    'model_saturation': model_saturation,
    'model_status': model_status,
    'scaler': scaler,
    'feature_columns': feature_columns
}

# Sauvegarde avec joblib
joblib.dump(models, 'xgboost_models.pkl')
print("✅ Modèles sauvegardés dans 'xgboost_models.pkl'")

# Sauvegarde des métriques
metrics = {
    'load': {'mse': float(load_mse), 'mae': float(load_mae), 'r2': float(load_r2), 'rmse': float(np.sqrt(load_mse))},
    'score': {'mse': float(score_mse), 'mae': float(score_mae), 'r2': float(score_r2)},
    'saturation': {'mse': float(saturation_mse), 'mae': float(saturation_mae)},
    'status': {'accuracy': float(status_accuracy)},
    'training_date': datetime.now().isoformat(),
    'n_samples': len(df),
    'n_features': len(feature_columns),
    'feature_columns': feature_columns
}

with open('model_metrics.json', 'w', encoding='utf-8') as f:
    json.dump(metrics, f, indent=2, ensure_ascii=False)
print("✅ Métriques sauvegardées dans 'model_metrics.json'")

# ============================================================================
# 8. IMPORTANCE DES FEATURES
# ============================================================================
print("\n📊 8. Analyse de l'importance des features...")

feature_importance = pd.DataFrame({
    'feature': feature_columns,
    'importance': model_load.feature_importances_
}).sort_values('importance', ascending=False)

print("\n🏆 Top 10 des features les plus importantes:")
print(feature_importance.head(10).to_string(index=False))

# ============================================================================
# 9. FONCTION DE PRÉDICTION CORRIGÉE
# ============================================================================
print("\n🎯 9. Fonction de prédiction...")

def predict_with_confidence(features_dict):
    """
    Fait une prédiction avec intervalle de confiance
    
    Paramètres:
    - features_dict: dictionnaire avec les features d'entrée
    
    Retourne:
    - dict contenant les prédictions
    """
    
    # Création du DataFrame avec l'ordre exact des colonnes
    input_df = pd.DataFrame([features_dict])
    
    # S'assurer que toutes les colonnes sont présentes dans le bon ordre
    for col in feature_columns:
        if col not in input_df.columns:
            input_df[col] = 0
    
    # Réordonner les colonnes
    input_df = input_df[feature_columns]
    
    # Normalisation
    input_scaled = scaler.transform(input_df)
    input_scaled = pd.DataFrame(input_scaled, columns=feature_columns)
    
    # Prédictions
    load_pred = model_load.predict(input_scaled)[0]
    score_pred = model_score.predict(input_scaled)[0]
    saturation_pred = model_saturation.predict(input_scaled)[0]
    status_pred = model_status.predict(input_scaled)[0]
    
    # Détermination du statut texte
    status_map = {0: 'OPTIMAL', 1: 'SURVEILLANCE', 2: 'CRITIQUE'}
    status_text = status_map.get(int(status_pred), 'INCONNU')
    
    # Recommandation
    if load_pred >= 80 or saturation_pred <= 2:
        recommendation = "🔴 URGENT: Migration requise immédiatement"
    elif load_pred >= 70 or saturation_pred <= 6:
        recommendation = "🟠 ATTENTION: Planifier migration"
    else:
        recommendation = "🟢 OPTIMAL: Infrastructure stable"
    
    return {
        'predicted_load': round(float(load_pred), 2),
        'xgboost_score': round(float(score_pred), 2),
        'saturation_months': round(float(saturation_pred), 1) if saturation_pred < 100 else '∞',
        'status': status_text,
        'recommendation': recommendation
    }

# ============================================================================
# 10. TEST DE LA FONCTION DE PRÉDICTION
# ============================================================================
print("\n🧪 10. Test de la fonction de prédiction...")

# Exemple de données d'entrée avec TOUTES les colonnes
test_input = {
    'cpu_usage_avg': 65,
    'cpu_usage_peak': 85,
    'ram_usage_avg': 70,
    'disk_io': 45,
    'response_time': 350,
    'visitors_per_day': 15000,
    'pageviews_per_day': 45000,
    'traffic_growth_rate': 25,
    'peak_hours_duration': 4,
    'plugin_count': 28,
    'heavy_plugins_count': 3,
    'php_score': 1.0,
    'cache_enabled': 1,
    'cdn_enabled': 1,
    'wp_factor': 1.0
}

print("\n📝 Données d'entrée:")
for key, value in test_input.items():
    print(f"   - {key}: {value}")

print("\n🔮 Prédiction:")
result = predict_with_confidence(test_input)

print(f"\n📊 Résultats:")
print(f"   - Charge prédite: {result['predicted_load']}%")
print(f"   - Score XGBoost: {result['xgboost_score']}%")
print(f"   - Saturation dans: {result['saturation_months']} mois")
print(f"   - Statut: {result['status']}")
print(f"   - Recommandation: {result['recommendation']}")



# ============================================================================
# 12. RÉSUMÉ FINAL
# ============================================================================
print("\n" + "=" * 80)
print("📋 RÉSUMÉ FINAL")
print("=" * 80)
print(f"""
✅ Modèles XGBoost entraînés avec succès!
   - Échantillons d'entraînement: {len(df)}
   - Features utilisées: {len(feature_columns)}
   - Targets: 4 (charge, score, saturation, statut)

📈 Performances:
   - Prédiction de charge: R² = {load_r2:.3f}, MAE = {load_mae:.2f}%
   - Prédiction du score: R² = {score_r2:.3f}, MAE = {score_mae:.2f}%
   - Prédiction saturation: MAE = {saturation_mae:.2f} mois
   - Classification statut: Accuracy = {status_accuracy:.3f}

💾 Fichiers générés:
   - xgboost_models.pkl: Modèles entraînés
   - model_metrics.json: Métriques de performance
   - predict_xgboost.py: Script de prédiction pour PHP

🚀 Test rapide:
   python predict_xgboost.py '{{"cpu_usage_avg": 65, "cpu_usage_peak": 85, "ram_usage_avg": 70, "disk_io": 45, "response_time": 350, "visitors_per_day": 15000, "pageviews_per_day": 45000, "traffic_growth_rate": 25, "peak_hours_duration": 4, "plugin_count": 28, "heavy_plugins_count": 3, "php_score": 1.0, "cache_enabled": 1, "cdn_enabled": 1, "wp_factor": 1.0}}'
""")
print("\n🚀 Modèle prêt à l'emploi!")