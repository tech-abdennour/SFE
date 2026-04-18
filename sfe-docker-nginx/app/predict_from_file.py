#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import json
import sys
import os
import glob
import numpy as np
import pandas as pd
from datetime import datetime

# Tentative d'import de xgboost
try:
    import xgboost as xgb
    XGBOOST_AVAILABLE = True
except ImportError:
    XGBOOST_AVAILABLE = False
    print("⚠️ XGBoost non installé. Installation: pip install xgboost", file=sys.stderr)

# Tentative d'import joblib pour charger le modèle .pkl
try:
    import joblib
    JOBLIB_AVAILABLE = True
except ImportError:
    JOBLIB_AVAILABLE = False
    print("⚠️ joblib non installé. Installation: pip install joblib", file=sys.stderr)


class XGBoostPredictorPython:
    """Version Python du modèle XGBoost utilisant le fichier .pkl entraîné"""
    
    def __init__(self, model_path='xgboost_models.pkl'):
        self.model_load = None      # Modèle pour prédire la charge
        self.model_score = None     # Modèle pour prédire le score
        self.model_saturation = None # Modèle pour prédire la saturation
        self.model_status = None    # Modèle pour prédire le statut
        self.scaler = None
        self.feature_columns = None
        self.model_loaded = False
        
        # Charger le modèle .pkl
        self.load_model(model_path)
        
        # Configuration par défaut (fallback si modèle non chargé)
        self.pack_capacity = {
            'small': {'max_visitors': 10000, 'base_load': 40, 'recommended_cpu': 70},
            'medium': {'max_visitors': 50000, 'base_load': 30, 'recommended_cpu': 65},
            'performance': {'max_visitors': 1000000, 'base_load': 20, 'recommended_cpu': 50},
            'enterprise': {'max_visitors': 9999999, 'base_load': 15, 'recommended_cpu': 40}
        }
        
        self.php_scores = {
            '7.4': 0.85, '8.0': 0.90, '8.1': 0.95, '8.2': 1.00, '8.3': 1.05
        }
    
    def load_model(self, model_path):
        """Charge le modèle XGBoost depuis un fichier .pkl"""
        if not JOBLIB_AVAILABLE:
            print("⚠️ joblib non disponible", file=sys.stderr)
            return False
        
        if not os.path.exists(model_path):
            print(f"⚠️ Modèle non trouvé: {model_path}", file=sys.stderr)
            print("   Utilisation du mode simulation...", file=sys.stderr)
            return False
        
        try:
            models = joblib.load(model_path)
            
            if isinstance(models, dict):
                self.model_load = models.get('model_load', models.get('model'))
                self.model_score = models.get('model_score', None)
                self.model_saturation = models.get('model_saturation', None)
                self.model_status = models.get('model_status', None)
                self.scaler = models.get('scaler', None)
                self.feature_columns = models.get('feature_columns', None)
            else:
                self.model_load = models
                self.model_score = None
                self.model_saturation = None
                self.model_status = None
                self.scaler = None
                self.feature_columns = None
            
            self.model_loaded = True
            print(f"✅ Modèle chargé avec succès depuis {model_path}", file=sys.stderr)
            if self.feature_columns:
                print(f"📊 Features attendues: {self.feature_columns}", file=sys.stderr)
            return True
            
        except Exception as e:
            print(f"❌ Erreur chargement modèle: {e}", file=sys.stderr)
            return False
    
    def format_saturation(self, months_float):
        """Formate la saturation en mois et jours"""
        if months_float >= 999:
            return "∞ (jamais)"
        
        if months_float <= 0:
            return "0 (déjà saturé)"
        
        months_int = int(months_float)
        days_float = (months_float - months_int) * 30.44
        
        if months_int == 0:
            days_int = int(days_float)
            if days_int <= 0:
                return "moins d'un jour"
            elif days_int == 1:
                return f"{days_int} jour"
            else:
                return f"{days_int} jours"
        elif days_float < 1:
            if months_int == 1:
                return f"{months_int} mois"
            else:
                return f"{months_int} mois"
        else:
            days_int = int(days_float)
            if months_int == 1:
                if days_int == 1:
                    return f"{months_int} mois et {days_int} jour"
                else:
                    return f"{months_int} mois et {days_int} jours"
            else:
                if days_int == 1:
                    return f"{months_int} mois et {days_int} jour"
                else:
                    return f"{months_int} mois et {days_int} jours"
    
    def prepare_features(self, params):
        """Prépare le vecteur de features à partir des paramètres JSON"""
        
        # Extraire les valeurs
        cpu_avg = float(params.get('cpu_usage_avg', 50))
        cpu_peak = float(params.get('cpu_usage_peak', 70))
        ram_avg = float(params.get('ram_usage_avg', 50))
        
        # NOUVEAU: Read/Write IOPS
        disk_read_iops = float(params.get('disk_read_iops', 150))
        disk_write_iops = float(params.get('disk_write_iops', 80))
        
        response_time = float(params.get('response_time', 500))
        visitors = float(params.get('visitors_per_day', 1000))
        pageviews = float(params.get('pageviews_per_day', 3000))
        growth_rate = float(params.get('traffic_growth_rate', 10))
        plugin_count = int(params.get('plugin_count', 20))
        heavy_plugins_str = params.get('heavy_plugins', '')
        php_version = params.get('php_version', '8.1')
        cache_enabled = 1 if params.get('cache_enabled', 'non') == 'oui' else 0
        cdn_enabled = 1 if params.get('cdn_enabled', 'non') == 'oui' else 0
        
        # Mapping wp_type
        wp_type_map = {'small': 0, 'medium': 1, 'performance': 2, 'enterprise': 3}
        wp_type = wp_type_map.get(params.get('wp_type', 'medium'), 1)
        
        # Score PHP
        php_score = self.php_scores.get(str(php_version), 0.95)
        
        # Heures de pointe
        start = params.get('peak_hours_start', '09:00')
        end = params.get('peak_hours_end', '18:00')
        if start and end:
            start_hour = int(start.split(':')[0])
            end_hour = int(end.split(':')[0])
            peak_hours = max(1, end_hour - start_hour)
        else:
            peak_hours = 4
        
        # Compter les plugins lourds
        heavy_plugins_list = [p.strip() for p in heavy_plugins_str.split(',') if p.strip()]
        heavy_plugins_count = len(heavy_plugins_list)
        
        # Calculer l'IOPS total (Read + Write) pour la feature
        total_iops = disk_read_iops + disk_write_iops
        
        # Créer le dictionnaire de features
        features_dict = {
            'cpu_usage_avg': cpu_avg,
            'cpu_usage_peak': cpu_peak,
            'ram_usage_avg': ram_avg,
            'disk_read_iops': disk_read_iops,
            'disk_write_iops': disk_write_iops,
            'total_iops': total_iops,
            'response_time': response_time,
            'visitors_per_day': visitors,
            'pageviews_per_day': pageviews,
            'traffic_growth_rate': growth_rate,
            'peak_hours_duration': peak_hours,
            'plugin_count': plugin_count,
            'heavy_plugins_count': heavy_plugins_count,
            'php_score': php_score,
            'cache_enabled': cache_enabled,
            'cdn_enabled': cdn_enabled,
            'wp_factor': wp_type
        }
        
        # Créer DataFrame
        df = pd.DataFrame([features_dict])
        
        # Réordonner les colonnes si feature_columns est fourni
        if self.feature_columns:
            for col in self.feature_columns:
                if col not in df.columns:
                    df[col] = 0
            df = df[self.feature_columns]
        
        return df, features_dict
    
    def calculate_saturation_months_float(self, current_load, growth_rate, wp_type):
        """Calcule le nombre de mois avant saturation - CORRIGÉ"""
        threshold = 90
        
        # Si déjà saturé ou dépassé
        if current_load >= threshold:
            return 0.0
        
        if growth_rate <= 0:
            return 999.0
        
        months = np.log(threshold / max(1, current_load)) / np.log(1 + growth_rate / 100)
        
        # Ajustement selon le type de pack
        if wp_type == 'small':
            months = months * 0.7
        elif wp_type == 'medium':
            months = months * 0.9
        elif wp_type == 'performance':
            months = months * 1.2
        elif wp_type == 'enterprise':
            months = months * 1.5
        
        return max(0.0, round(months, 2))
    
    def get_corrected_saturation(self, predicted_load, saturation_from_model):
        """Corrige la saturation en fonction de la charge actuelle"""
        # Si la charge est déjà saturée, la saturation est 0
        if predicted_load >= 90:
            return 0.0
        # Si la charge est très élevée, réduire la saturation
        elif predicted_load >= 80:
            return min(saturation_from_model, 3.0)
        elif predicted_load >= 70:
            return min(saturation_from_model, 6.0)
        else:
            return saturation_from_model
    
    def get_status(self, predicted_load, saturation_months_float):
        """Détermine le statut - PRIORITÉ ABSOLUE à la charge actuelle"""
        # Priorité 1: Charge actuelle
        if predicted_load >= 95:
            return 'CRITIQUE'
        elif predicted_load >= 85:
            return 'URGENT'
        elif predicted_load >= 75:
            return 'ATTENTION'
        
        # Priorité 2: Saturation future
        if saturation_months_float <= 1:
            return 'CRITIQUE'
        elif saturation_months_float <= 3:
            return 'URGENT'
        elif saturation_months_float <= 6:
            return 'ATTENTION'
        else:
            return 'OPTIMAL'
    
    def get_wp_type_name(self, wp_type_int):
        mapping = {0: 'small', 1: 'medium', 2: 'performance', 3: 'enterprise'}
        return mapping.get(wp_type_int, 'medium')
    
    def get_next_pack(self, current):
        packs = ['small', 'medium', 'performance', 'enterprise']
        pack_names = {
            'small': 'MEDIUM', 
            'medium': 'PERFORMANCE', 
            'performance': 'ENTERPRISE', 
            'enterprise': 'DÉDIÉ HAUTE PERFORMANCE'
        }
        if current in packs:
            idx = packs.index(current)
            if idx < len(packs) - 1:
                return pack_names.get(packs[idx + 1], packs[idx + 1].upper())
        return "PACK SUPÉRIEUR"
    
    def get_recommendation(self, predicted_load, saturation_months_float, wp_type, features):
        """Génère une recommandation - PRIORITÉ à la charge actuelle"""
        saturation_text = self.format_saturation(saturation_months_float)
        recommendations = []
        
        # ===== PRIORITÉ 1: CHARGE ACTUELLE =====
        if predicted_load >= 95:
            recommendations.append("🔴🔴 CRITIQUE EXTREME : Serveur SATURE IMMEDIATEMENT !")
            recommendations.append(f"👉 ACTION URGENTE : Migrer vers {self.get_next_pack(wp_type)} DANS LES 24H")
        elif predicted_load >= 85:
            recommendations.append(f"🔴 URGENCE : Charge critique ({predicted_load:.0f}%). Migration REQUISE.")
            recommendations.append(f"👉 Migration recommandée vers {self.get_next_pack(wp_type)}")
        elif predicted_load >= 75:
            recommendations.append(f"🟠 ATTENTION : Charge élevée ({predicted_load:.0f}%). Surveillance requise.")
            recommendations.append(f"👉 Envisagez migration vers {self.get_next_pack(wp_type)}")
        
        # ===== PRIORITÉ 2: SATURATION FUTURE =====
        elif saturation_months_float <= 1:
            recommendations.append(f"🔴 CRITIQUE : Saturation dans {saturation_text}. Migration immédiate.")
            recommendations.append(f"👉 Migration recommandée vers {self.get_next_pack(wp_type)}")
        elif saturation_months_float <= 3:
            recommendations.append(f"🟠 URGENT : Saturation dans {saturation_text}. Planifiez la migration.")
            recommendations.append(f"👉 Migration recommandée vers {self.get_next_pack(wp_type)}")
        elif saturation_months_float <= 6:
            recommendations.append(f"🟡 ATTENTION : Saturation dans {saturation_text}. Préparez la migration.")
        else:
            recommendations.append(f"🟢 OPTIMAL : Infrastructure stable pour {saturation_text}.")
        
        # ===== RECOMMANDATIONS SPÉCIFIQUES =====
        
        # CPU
        if features['cpu_usage_avg'] > 90:
            recommendations.append(f"⚠️ CPU CRITIQUE ({features['cpu_usage_avg']:.0f}%) - Action immédiate")
        elif features['cpu_usage_avg'] > 80:
            recommendations.append(f"⚠️ CPU très élevé ({features['cpu_usage_avg']:.0f}%) - Optimisation urgente")
        elif features['cpu_usage_avg'] > 70:
            recommendations.append(f"⚠️ CPU élevé ({features['cpu_usage_avg']:.0f}%) - Surveillance")
        
        # RAM
        if features['ram_usage_avg'] > 90:
            recommendations.append(f"⚠️ RAM CRITIQUE ({features['ram_usage_avg']:.0f}%) - Augmentez mémoire")
        elif features['ram_usage_avg'] > 80:
            recommendations.append(f"⚠️ RAM très élevée ({features['ram_usage_avg']:.0f}%) - Optimisation")
        elif features['ram_usage_avg'] > 70:
            recommendations.append(f"⚠️ RAM élevée ({features['ram_usage_avg']:.0f}%) - Surveillance")
        
        # IOPS (NOUVEAU)
        if features['disk_read_iops'] > 1000 or features['disk_write_iops'] > 1000:
            recommendations.append(f"💾 IOPS TRÈS ÉLEVÉ (R:{features['disk_read_iops']:.0f}/W:{features['disk_write_iops']:.0f}) - Disque NVMe recommandé")
        elif features['disk_read_iops'] > 500 or features['disk_write_iops'] > 500:
            recommendations.append(f"💾 IOPS ÉLEVÉ (R:{features['disk_read_iops']:.0f}/W:{features['disk_write_iops']:.0f}) - Surveillez les performances disque")
        
        # Plugins
        if features['plugin_count'] > 100:
            recommendations.append(f"🔴 PLUGINS EXCESSIF ({features['plugin_count']}) - Nettoyage URGENT")
        elif features['plugin_count'] > 50:
            recommendations.append(f"🔌 Trop de plugins ({features['plugin_count']}) - Nettoyez les inutilisés")
        elif features['plugin_count'] > 30:
            recommendations.append(f"📦 {features['plugin_count']} plugins - Surveillez les performances")
        
        # Trafic
        if features['visitors_per_day'] > 100000:
            recommendations.append(f"📊 Trafic très élevé ({features['visitors_per_day']:.0f} visites/jour) - Pack ENTERPRISE nécessaire")
        elif features['visitors_per_day'] > 50000:
            recommendations.append(f"📊 Trafic élevé ({features['visitors_per_day']:.0f} visites/jour) - Envisagez PERFORMANCE")
        
        # Cache
        if features['cache_enabled'] == 0:
            recommendations.append("💡 Activez un cache (Redis/LiteSpeed) - Gain potentiel 30-50%")
        
        # CDN
        if features['cdn_enabled'] == 0:
            recommendations.append("🌍 Activez un CDN - Réduction charge ressources statiques")
        
        # PHP
        php_version_val = features.get('php_score', 0.95)
        if php_version_val < 0.95:
            recommendations.append("🐘 Mettez à jour PHP vers 8.2+ (gain 20-30%)")
        
        return " | ".join(recommendations)
    
    def calculate_simulated_score(self, features):
        """Calcule un score simulé (fallback si pas de modèle)"""
        score = 0
        score += (features['cpu_usage_avg'] / 100) * 15
        score += (features['cpu_usage_peak'] / 100) * 10
        score += (features['ram_usage_avg'] / 100) * 13
        score += min(1, features['visitors_per_day'] / 50000) * 10
        score += min(1, features['traffic_growth_rate'] / 100) * 12
        score += min(1, features['plugin_count'] / 50) * 6
        # Ajout IOPS
        score += min(1, features.get('total_iops', 0) / 2000) * 5
        if features['cache_enabled'] == 0:
            score += 3
        if features['cdn_enabled'] == 0:
            score += 3
        return min(100, max(0, score))
    
    def predict(self, params):
        """Fonction principale de prédiction utilisant le modèle .pkl"""
        
        # Préparer les features
        X, features_dict = self.prepare_features(params)
        wp_type_name = self.get_wp_type_name(features_dict['wp_factor'])
        
        # Appliquer le scaler si disponible
        if self.scaler:
            X_scaled = self.scaler.transform(X)
        else:
            X_scaled = X.values
        
        # === PRÉDICTION DE LA CHARGE ===
        if self.model_loaded and self.model_load:
            predicted_load = float(self.model_load.predict(X_scaled)[0])
            predicted_load = min(100, max(0, predicted_load))
            print(f"✅ Prédiction charge (modèle .pkl): {predicted_load:.1f}%", file=sys.stderr)
        else:
            print("⚠️ Utilisation du mode simulation (fallback)", file=sys.stderr)
            simulated_score = self.calculate_simulated_score(features_dict)
            predicted_load = min(100, max(0, 
                features_dict['cpu_usage_avg'] * 0.3 + 
                features_dict['cpu_usage_peak'] * 0.2 + 
                features_dict['ram_usage_avg'] * 0.2 + 
                simulated_score * 0.3
            ))
        
        # === PRÉDICTION DU SCORE XGBOOST ===
        if self.model_loaded and self.model_score:
            predicted_score = float(self.model_score.predict(X_scaled)[0])
            predicted_score = min(100, max(0, predicted_score))
        else:
            predicted_score = self.calculate_simulated_score(features_dict)
        
        # === PRÉDICTION DE LA SATURATION (BRUTE) ===
        if self.model_loaded and self.model_saturation:
            saturation_raw = float(self.model_saturation.predict(X_scaled)[0])
            saturation_raw = max(0, saturation_raw)
        else:
            saturation_raw = self.calculate_saturation_months_float(
                predicted_load, features_dict['traffic_growth_rate'], wp_type_name
            )
        
        # === CORRECTION DE LA SATURATION (basée sur la charge actuelle) ===
        saturation_months_float = self.get_corrected_saturation(predicted_load, saturation_raw)
        
        # === STATUT (basé sur la charge corrigée) ===
        status = self.get_status(predicted_load, saturation_months_float)
        
        # Formater la saturation pour l'affichage
        saturation_formatted = self.format_saturation(saturation_months_float)
        
        # Génération de la recommandation
        recommendation = self.get_recommendation(predicted_load, saturation_months_float, 
                                                   wp_type_name, features_dict)
        
        return {
            'xgboost_score': round(predicted_score, 1),
            'predicted_load': round(predicted_load, 1),
            'predicted_saturation_months': round(saturation_months_float, 2),
            'predicted_saturation_text': saturation_formatted,
            'status': status,
            'recommendation': recommendation,
            'confidence': round(85 - (predicted_score * 0.3), 1),
            'model_used': 'XGBoost .pkl' if self.model_loaded else 'Simulation',
            'details': {
                'cpu_avg': features_dict['cpu_usage_avg'],
                'cpu_peak': features_dict['cpu_usage_peak'],
                'ram_avg': features_dict['ram_usage_avg'],
                'disk_read_iops': features_dict['disk_read_iops'],
                'disk_write_iops': features_dict['disk_write_iops'],
                'visitors': features_dict['visitors_per_day'],
                'growth_rate': features_dict['traffic_growth_rate'],
                'plugin_count': features_dict['plugin_count'],
                'wp_type': wp_type_name,
                'cache_enabled': 'oui' if features_dict['cache_enabled'] == 1 else 'non',
                'cdn_enabled': 'oui' if features_dict['cdn_enabled'] == 1 else 'non'
            }
        }


def find_latest_json_file(dossier="Donnee_parametres"):
    """Trouve automatiquement le dernier fichier JSON dans le dossier"""
    if not os.path.exists(dossier):
        print(f"❌ Dossier '{dossier}' non trouvé !", file=sys.stderr)
        return None
    
    fichiers = glob.glob(os.path.join(dossier, "parameters_*.json"))
    fichiers.extend(glob.glob(os.path.join(dossier, "paramètres_*.json")))
    fichiers.extend(glob.glob(os.path.join(dossier, "*.json")))
    
    if not fichiers:
        print(f"❌ Aucun fichier JSON trouvé", file=sys.stderr)
        return None
    
    fichiers.sort(key=os.path.getmtime, reverse=True)
    latest_file = fichiers[0]
    print(f"📂 Dernier fichier: {latest_file}", file=sys.stderr)
    return latest_file


def load_json_file(filepath):
    """Charge un fichier JSON et extrait les paramètres"""
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            data = json.load(f)
        
        if 'parameters' in data:
            params = data['parameters']
        elif 'params' in data:
            params = data['params']
        else:
            params = data
        
        cleaned_params = {}
        for key, value in params.items():
            if isinstance(value, (int, float)):
                cleaned_params[key] = value
            elif isinstance(value, str):
                if value.isdigit():
                    cleaned_params[key] = int(value)
                elif value.replace('.', '').replace('-', '').isdigit():
                    cleaned_params[key] = float(value)
                else:
                    cleaned_params[key] = value
            else:
                cleaned_params[key] = value
        
        return cleaned_params
    except Exception as e:
        print(f"❌ Erreur chargement JSON: {e}", file=sys.stderr)
        return None


def main():
    """Fonction principale"""
    
    # Désactiver les buffers pour éviter les problèmes
    sys.stdout.reconfigure(line_buffering=True) if hasattr(sys.stdout, 'reconfigure') else None
    
    # Trouver le fichier JSON
    if len(sys.argv) >= 2:
        json_file = sys.argv[1]
        print(f"📂 Utilisation du fichier spécifié: {json_file}", file=sys.stderr)
    else:
        json_file = find_latest_json_file("Donnee_parametres")
        if json_file is None:
            json_file = find_latest_json_file("./Donnee_parametres")
        if json_file is None:
            # Retourner une réponse JSON d'erreur
            error_response = json.dumps({
                'success': False,
                'error': 'Aucun fichier JSON trouvé dans le dossier Donnee_parametres'
            }, ensure_ascii=False)
            print(error_response)
            sys.exit(1)
    
    # Charger les paramètres
    params = load_json_file(json_file)
    if params is None:
        error_response = json.dumps({
            'success': False,
            'error': f'Impossible de charger le fichier: {json_file}'
        }, ensure_ascii=False)
        print(error_response)
        sys.exit(1)
    
    # Afficher les paramètres chargés
    print(f"\n📊 PARAMÈTRES CHARGÉS:", file=sys.stderr)
    for key, value in params.items():
        print(f"   {key}: {value}", file=sys.stderr)
    
    # Effectuer la prédiction
    print(f"\n🤖 PRÉDICTION XGBOOST AVEC MODÈLE .pkl...", file=sys.stderr)
    predictor = XGBoostPredictorPython('xgboost_models.pkl')
    result = predictor.predict(params)
    
    # Ajouter des métadonnées
    result['success'] = True
    result['timestamp'] = datetime.now().isoformat()
    result['input_file'] = os.path.basename(json_file)
    
    # Afficher les résultats formatés
    print(f"\n{'='*50}", file=sys.stderr)
    print(f"📊 RÉSULTATS DE LA PRÉDICTION", file=sys.stderr)
    print(f"{'='*50}", file=sys.stderr)
    print(f"🤖 Modèle utilisé: {result['model_used']}", file=sys.stderr)
    print(f"🎯 Score XGBoost: {result['xgboost_score']}%", file=sys.stderr)
    print(f"⚡ Charge prédite: {result['predicted_load']}%", file=sys.stderr)
    print(f"⏰ Saturation: {result['predicted_saturation_text']}", file=sys.stderr)
    
    # Icône de statut
    status_icons = {
        'CRITIQUE': '🔴🔴',
        'URGENT': '🔴',
        'ATTENTION': '🟠',
        'OPTIMAL': '🟢'
    }
    print(f"📊 Statut: {status_icons.get(result['status'], '⚪')} {result['status']}", file=sys.stderr)
    print(f"{'='*50}\n", file=sys.stderr)
    
    # Afficher la recommandation
    if result['recommendation']:
        print(f"💡 RECOMMANDATION:", file=sys.stderr)
        for rec in result['recommendation'].split(' | '):
            print(f"   {rec}", file=sys.stderr)
        print(file=sys.stderr)
    
    # Retourner le résultat en JSON (un seul objet JSON)
    json_output = json.dumps(result, ensure_ascii=False)
    print(json_output)


if __name__ == "__main__":
    main()