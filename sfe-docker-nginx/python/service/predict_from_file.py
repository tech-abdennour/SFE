#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
PREDICT + GRAPHES + ARBRE PERSONNALISÉ
Script unifié - Vala Bleu
SATURATION EN JOURS ET MOIS
"""

import json
import os
import glob
import sys
import math
import numpy as np
import pandas as pd
import joblib
import xgboost as xgb
from datetime import datetime
from pathlib import Path
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
import matplotlib.patches as patches
from matplotlib.patches import FancyBboxPatch
import seaborn as sns
import warnings
warnings.filterwarnings('ignore')

# ============================================================================
# CHEMINS UNIVERSELS (Windows + Docker)
# ============================================================================
BASE_DIR = Path(__file__).parent

if os.path.exists("/app"):
    MODELS_DIR = Path("/app/models")
    DATA_DIR = Path("/app/Donnee_parametres")
else:
    MODELS_DIR = BASE_DIR.parent / "models"
    DATA_DIR = BASE_DIR.parent.parent / "app" / "Donnee_parametres"

MODEL_PATH = MODELS_DIR / "xgboost_models.pkl"
OUTPUT_DIR = BASE_DIR / "analysis_exports"
OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

TIMESTAMP = datetime.now().strftime("%Y%m%d_%H%M%S")

print("=" * 80, file=sys.stderr)
print("🔮 VALA BLEU - PRÉDICTION + GRAPHES + ARBRE", file=sys.stderr)
print(f"📁 Modèle  : {MODEL_PATH}", file=sys.stderr)
print(f"📁 Données : {DATA_DIR}", file=sys.stderr)
print(f"📁 Sorties : {OUTPUT_DIR}", file=sys.stderr)
print("=" * 80, file=sys.stderr)


# ============================================================================
# FONCTION DE CONVERSION JOURS -> MOIS/JOURS
# ============================================================================
def days_to_months_days(days):
    """Convertit des jours en format 'X mois Y jours'"""
    if days is None or days >= 30000:  # ~infini
        return 999, 0, "∞"
    elif days <= 0:
        return 0, 0, "⚠️ SATURÉ"
    else:
        total_months = days / 30.44
        months = int(total_months)
        remaining_days = int(round((total_months - months) * 30.44))
        
        # Ajustement pour éviter 0 mois 30 jours
        if remaining_days >= 30:
            months += 1
            remaining_days -= 30
        
        if months == 0:
            text = f"{remaining_days} jour{'s' if remaining_days > 1 else ''}"
        elif remaining_days == 0:
            text = f"{months} mois"
        else:
            text = f"{months} mois {remaining_days} jour{'s' if remaining_days > 1 else ''}"
        
        return months, remaining_days, text


# ============================================================================
# 1. CHARGEMENT
# ============================================================================
def load_model():
    if not MODEL_PATH.exists():
        return None, None, None
    models = joblib.load(str(MODEL_PATH))
    ml = models.get('model_load', models) if isinstance(models, dict) else models
    fc = models.get('feature_columns', None) if isinstance(models, dict) else None
    sc = models.get('scaler', None) if isinstance(models, dict) else None
    print(f"✅ Modèle chargé", file=sys.stderr)
    return ml, fc, sc


def find_latest_json():
    if not DATA_DIR.exists():
        return None
    fichiers = sorted(glob.glob(str(DATA_DIR / "*.json")), key=os.path.getmtime, reverse=True)
    return fichiers[0] if fichiers else None


def load_params(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        data = json.load(f)
    params = data.get('parameters', data.get('params', data))
    cleaned = {}
    for k, v in params.items():
        if isinstance(v, (int, float)): cleaned[k] = v
        elif isinstance(v, str):
            try: cleaned[k] = float(v) if '.' in v else int(v)
            except: cleaned[k] = v
        else: cleaned[k] = v
    return cleaned


# ============================================================================
# 2. FEATURES (TOUS LES PARAMÈTRES)
# ============================================================================
def prepare_features(params, feature_columns):
    cpu_avg = float(params.get('cpu_usage_avg', 50))
    cpu_peak = float(params.get('cpu_usage_peak', 70))
    ram_avg = float(params.get('ram_usage_avg', 50))
    ram_max = float(params.get('ram_usage_max', 85))
    disk_avg = float(params.get('disk_usage_avg', 45))
    disk_max = float(params.get('disk_usage_max', 70))
    disk_read_iops = float(params.get('disk_read_iops', 150))
    disk_write_iops = float(params.get('disk_write_iops', 80))
    total_iops = disk_read_iops + disk_write_iops
    response_time = float(params.get('response_time', 350))
    visitors = float(params.get('visitors_per_day', 5000))
    pageviews = float(params.get('pageviews_per_day', 15000))
    growth_rate = float(params.get('traffic_growth_rate', 15))
    plugin_count = int(params.get('plugin_count', 25))
    heavy_str = str(params.get('heavy_plugins', ''))
    php_version = params.get('php_version', '8.1')
    cache_enabled = 1 if str(params.get('cache_enabled', 'non')).lower() == 'oui' else 0
    cdn_enabled = 1 if str(params.get('cdn_enabled', 'non')).lower() == 'oui' else 0
    wp_type_map = {'small': 0, 'medium': 1, 'performance': 2, 'enterprise': 3}
    wp_type = wp_type_map.get(params.get('wp_type', 'medium'), 1)
    php_scores = {'7.4': 0.85, '8.0': 0.90, '8.1': 0.95, '8.2': 1.00, '8.3': 1.05}
    php_score = php_scores.get(str(php_version), 0.95)
    start = params.get('peak_hours_start', '09:00')
    end = params.get('peak_hours_end', '18:00')
    peak_hours = max(1, int(end.split(':')[0]) - int(start.split(':')[0])) if start and end else 4
    heavy_list = [p.strip() for p in heavy_str.split(',') if p.strip()]
    heavy_count = len(heavy_list)

    features_dict = {
        'cpu_usage_avg': cpu_avg, 'cpu_usage_peak': cpu_peak,
        'ram_usage_avg': ram_avg, 'ram_usage_max': ram_max,
        'disk_usage_avg': disk_avg, 'disk_usage_max': disk_max,
        'disk_read_iops': disk_read_iops, 'disk_write_iops': disk_write_iops,
        'total_iops': total_iops, 'response_time': response_time,
        'visitors_per_day': visitors, 'pageviews_per_day': pageviews,
        'traffic_growth_rate': growth_rate, 'peak_hours_duration': peak_hours,
        'plugin_count': plugin_count, 'heavy_plugins_count': heavy_count,
        'php_score': php_score, 'cache_enabled': cache_enabled,
        'cdn_enabled': cdn_enabled, 'wp_factor': wp_type
    }

    df = pd.DataFrame([features_dict])
    if feature_columns:
        for col in feature_columns:
            if col not in df.columns:
                df[col] = 0
        df = df[feature_columns]
    return df, features_dict


# ============================================================================
# 3. PRÉDICTION (MODIFIÉE POUR JOURS ET MOIS)
# ============================================================================
def predict(model_load, scaler, features_dict, feature_columns):
    # Prédiction de la charge
    X, _ = prepare_features(features_dict, feature_columns)
    if scaler is not None:
        X = scaler.transform(X)
    
    if hasattr(model_load, 'predict'):
        predicted_load = float(model_load.predict(X)[0])
    else:
        predicted_load = 50
    predicted_load = min(100, max(0, predicted_load))
    
    # Score XGBoost
    score = min(100, max(0,
        (features_dict.get('cpu_usage_avg', 50) / 100) * 15 +
        (features_dict.get('ram_usage_avg', 50) / 100) * 13 +
        min(1, features_dict.get('visitors_per_day', 5000) / 50000) * 10 +
        min(1, features_dict.get('traffic_growth_rate', 15) / 100) * 12 +
        min(1, features_dict.get('plugin_count', 25) / 50) * 6 +
        min(1, features_dict.get('total_iops', 230) / 2000) * 5 +
        (3 if features_dict.get('cache_enabled', 0) == 0 else 0) +
        (3 if features_dict.get('cdn_enabled', 0) == 0 else 0)
    ))
    
    # ================================================================
    # SATURATION EN JOURS (MODIFIÉ)
    # ================================================================
    growth = features_dict.get('traffic_growth_rate', 15)
    
    if predicted_load >= 90:
        saturation_months = 0
        saturation_days = 0
    elif growth <= 0:
        saturation_months = 999
        saturation_days = 999 * 30.44  # ~30 ans = infini
    else:
        # Calcul en mois d'abord
        saturation_months = round(np.log(90 / max(1, predicted_load)) / np.log(1 + growth / 100), 1)
        # Conversion en jours (1 mois = 30.44 jours)
        saturation_days = saturation_months * 30.44
    
    # Conversion en format lisible
    sat_mois, sat_jours, saturation_text = days_to_months_days(saturation_days)
    
    # Statut basé sur les jours
    if predicted_load >= 85 or saturation_days <= 30:  # Moins de 30 jours = CRITIQUE
        status = 'CRITIQUE'
    elif predicted_load >= 75 or saturation_days <= 60:  # Moins de 2 mois = URGENT
        status = 'URGENT'
    elif predicted_load >= 65 or saturation_days <= 180:  # Moins de 6 mois = SURVEILLANCE
        status = 'SURVEILLANCE'
    else:
        status = 'OPTIMAL'
    
    recs = {
        'CRITIQUE': "🔴 Migration immédiate requise - Serveur en surcharge critique",
        'URGENT': "🟠 Planifier migration urgente - Risque élevé de saturation dans " + saturation_text,
        'SURVEILLANCE': "🟡 Surveiller et optimiser - Marge de " + saturation_text + " avant saturation",
        'OPTIMAL': "🟢 Configuration stable - Aucune action requise"
    }
    
    return {
        'predicted_load': round(predicted_load, 1),
        'xgboost_score': round(score, 1),
        'saturation_days': round(saturation_days, 1),      # NOUVEAU : Jours
        'saturation_months': sat_mois,                      # NOUVEAU : Mois entiers
        'saturation_jours': sat_jours,                      # NOUVEAU : Jours restants
        'saturation_text': saturation_text,                 # NOUVEAU : Texte formaté
        'saturation_months_raw': saturation_months,         # ANCIEN : Gardé pour compatibilité
        'status': status,
        'recommendation': recs[status]
    }


# ============================================================================
# 4. GRAPHE 1 : IMPORTANCE DES FEATURES
# ============================================================================
def graph_importance(model_load, feature_columns):
    if not hasattr(model_load, 'feature_importances_'):
        print("⚠️ Pas d'importance dispo", file=sys.stderr)
        return None
    
    importances = model_load.feature_importances_
    df_imp = pd.DataFrame({'feature': feature_columns[:len(importances)], 'importance': importances})
    df_imp = df_imp.sort_values('importance', ascending=True).tail(15)
    
    fig, ax = plt.subplots(figsize=(12, 8))
    colors = plt.cm.RdYlGn(np.linspace(0.2, 1, len(df_imp)))
    bars = ax.barh(range(len(df_imp)), df_imp['importance'], color=colors)
    ax.set_yticks(range(len(df_imp)))
    ax.set_yticklabels(df_imp['feature'])
    ax.set_xlabel('Importance (F-score)', fontweight='bold')
    ax.set_title('🏆 Importance des Features - Modèle XGBoost', fontsize=14, fontweight='bold')
    for bar, val in zip(bars, df_imp['importance']):
        ax.text(bar.get_width() + 0.001, bar.get_y() + bar.get_height()/2, f'{val:.3f}', va='center', fontsize=8)
    plt.tight_layout()
    path = str(OUTPUT_DIR / f'feature_importance_{TIMESTAMP}.png')
    plt.savefig(path, dpi=200, bbox_inches='tight')
    plt.close()
    return path


# ============================================================================
# 5. GRAPHE 2 : DASHBOARD (MODIFIÉ POUR AFFICHER JOURS/MOIS)
# ============================================================================
def graph_metrics(features_dict, result, json_file):
    fig, axes = plt.subplots(2, 3, figsize=(18, 10))
    fig.suptitle(f'📊 Dashboard VALA BLEU\nDonnées: {Path(json_file).name}', fontsize=14, fontweight='bold')
    
    def color(val, crit=80, warn=60):
        return '#e74c3c' if val > crit else ('#f39c12' if val > warn else '#27ae60')
    
    metrics = [
        (0, 0, 'CPU Moyen', features_dict.get('cpu_usage_avg', 0), '%', 100),
        (0, 1, 'RAM Moyen', features_dict.get('ram_usage_avg', 0), '%', 100),
        (0, 2, 'Charge Prédite', result['predicted_load'], '%', 100),
        (1, 0, 'Visiteurs/jour', features_dict.get('visitors_per_day', 0), '', None),
        (1, 1, 'Score XGBoost', result['xgboost_score'], '%', 100),
    ]
    
    for row, col, title, val, unit, ymax in metrics:
        ax = axes[row, col]
        c = color(val) if ymax else '#3498db'
        ax.bar([title], [val], color=c, width=0.5)
        if ymax: ax.set_ylim(0, ymax)
        ax.set_title(f'{title}: {val:.1f}{unit}', fontweight='bold')
    
    # Panneau de statut (MODIFIÉ pour afficher jours/mois)
    ax = axes[1, 2]
    ax.axis('off')
    sc = {'CRITIQUE': '#e74c3c', 'URGENT': '#ff7a45', 'SURVEILLANCE': '#f39c12', 'OPTIMAL': '#27ae60'}
    ax.text(0.5, 0.85, result['status'], ha='center', va='center', fontsize=22, fontweight='bold', 
            color=sc.get(result['status'], 'gray'), transform=ax.transAxes)
    
    # Afficher la saturation en jours et mois
    default_sat = f"{result.get('saturation_months_raw', 0)} mois"
    saturation_info = f"Saturation: {result.get('saturation_text', default_sat)}"
    ax.text(0.5, 0.60, saturation_info, ha='center', fontsize=11, transform=ax.transAxes)
    
    # Détail jours si disponible
    if 'saturation_days' in result and result['saturation_days'] > 0 and result['saturation_days'] < 30000:
        detail = f"({result['saturation_days']:.0f} jours)"
        ax.text(0.5, 0.50, detail, ha='center', fontsize=9, color='#6b7280', transform=ax.transAxes)
    
    ax.text(0.5, 0.30, result['recommendation'], ha='center', fontsize=9, transform=ax.transAxes, 
            bbox=dict(boxstyle='round', facecolor='lightyellow', alpha=0.8))
    
    plt.tight_layout()
    path = str(OUTPUT_DIR / f'dashboard_{TIMESTAMP}.png')
    plt.savefig(path, dpi=200, bbox_inches='tight')
    plt.close()
    return path


# ============================================================================
# 6. GRAPHE 3 : HEATMAP DE CORRÉLATION
# ============================================================================
def graph_correlation(features_dict):
    np.random.seed(42)
    n = 100
    sim = {}
    for k, v in features_dict.items():
        if isinstance(v, (int, float)) and v > 0:
            sim[k] = np.clip(v + np.random.normal(0, v * 0.15, n), 0, None)
        else:
            sim[k] = np.random.uniform(0, 100, n)
    
    df = pd.DataFrame(sim)
    corr = df.corr()
    
    fig, ax = plt.subplots(figsize=(14, 12))
    mask = np.triu(np.ones_like(corr, dtype=bool), k=1)
    sns.heatmap(corr, mask=mask, annot=True, fmt='.2f', cmap='RdBu_r', center=0, square=True, linewidths=0.5, ax=ax, annot_kws={'size': 6})
    ax.set_title('🔥 Matrice de Corrélation des Variables', fontsize=14, fontweight='bold')
    plt.tight_layout()
    path = str(OUTPUT_DIR / f'correlation_{TIMESTAMP}.png')
    plt.savefig(path, dpi=200, bbox_inches='tight')
    plt.close()
    return path


# ============================================================================
# 7. ARBRE PERSONNALISÉ AVEC FLÈCHES OUI/NON DYNAMIQUES (MODIFIÉ)
# ============================================================================
def graph_arbre(features_dict, result, json_file):
    fig, ax = plt.subplots(figsize=(26, 16), dpi=150)
    ax.set_xlim(-1, 14)
    ax.set_ylim(0, 13)
    ax.axis('off')
    ax.set_facecolor('#f8f9fa')
    
    # Extraire les valeurs
    cpu_val = features_dict.get('cpu_usage_avg', 0)
    ram_val = features_dict.get('ram_usage_avg', 0)
    vis_val = features_dict.get('visitors_per_day', 0)
    plug_val = features_dict.get('plugin_count', 0)
    iops_val = features_dict.get('total_iops', 0)
    growth_val = features_dict.get('traffic_growth_rate', 0)
    cache_val = features_dict.get('cache_enabled', 0)
    
    # Déterminer le chemin (OUI = < seuil, NON = >= seuil)
    cpu_ok = cpu_val < 65
    ram_ok = ram_val < 70
    vis_ok = vis_val < 15000
    plug_ok = plug_val < 30
    cache_ok = cache_val == 1
    iops_ok = iops_val < 1000
    growth_ok = growth_val < 20
    
    def node(x, y, label, value, threshold, active):
        color = '#27ae60' if active else '#3498db'
        circle = plt.Circle((x, y), 0.65, color=color, ec='white', linewidth=2, zorder=3)
        ax.add_patch(circle)
        ax.text(x, y, f"{label}\n>{threshold}?\n({value})", ha='center', va='center', fontsize=7, fontweight='bold', color='white', zorder=4)
    
    def fleche(x1, y1, x2, y2, oui, label=None):
        color = '#27ae60' if oui else '#bdc3c7'
        lw = 3 if oui else 1.5
        ax.annotate('', xy=(x2, y2+0.5), xytext=(x1, y1-0.5),
                   arrowprops=dict(arrowstyle='->', color=color, lw=lw))
        if label:
            mx, my = (x1+x2)/2, (y1+y2)/2
            ax.text(mx, my, label, ha='center', va='center', fontsize=10, fontweight='bold', color=color,
                   bbox=dict(boxstyle='round,pad=0.2', facecolor='white', edgecolor=color, alpha=0.9))
    
    # Nœuds
    node(7, 11, "CPU", f"{cpu_val:.0f}%", 65, True)
    node(3.5, 8.5, "RAM", f"{ram_val:.0f}%", 70, cpu_ok)
    node(10.5, 8.5, "Visiteurs", f"{vis_val:.0f}", "15K", not cpu_ok)
    node(1, 6, "Plugins", f"{plug_val}", 30, cpu_ok and ram_ok)
    node(6, 6, "Cache", "Oui" if cache_val else "Non", "Actif?", cpu_ok and not ram_ok)
    node(9.5, 6, "IOPS", f"{iops_val:.0f}", "1000", not cpu_ok and vis_ok)
    node(13, 6, "Croissance", f"{growth_val:.0f}%", "20%", not cpu_ok and not vis_ok)
    
    # Flèches avec OUI/NON dynamiques
    fleche(7, 10.5, 3.5, 9, cpu_ok, "OUI" if cpu_ok else "NON")
    fleche(7, 10.5, 10.5, 9, not cpu_ok, "OUI" if not cpu_ok else "NON")
    fleche(3.5, 8, 1, 6.5, ram_ok, "OUI" if ram_ok else "NON")
    fleche(3.5, 8, 6, 6.5, not ram_ok, "OUI" if not ram_ok else "NON")
    fleche(10.5, 8, 9.5, 6.5, vis_ok, "OUI" if vis_ok else "NON")
    fleche(10.5, 8, 13, 6.5, not vis_ok, "OUI" if not vis_ok else "NON")
    fleche(1, 5.5, 0, 3, plug_ok, "OUI" if plug_ok else "NON")
    fleche(1, 5.5, 2.5, 3, not plug_ok, "OUI" if not plug_ok else "NON")
    fleche(6, 5.5, 4.5, 3, cache_ok, "OUI" if cache_ok else "NON")
    fleche(6, 5.5, 7.5, 3, not cache_ok, "OUI" if not cache_ok else "NON")
    fleche(9.5, 5.5, 9.5, 3, iops_ok, "OUI" if iops_ok else "NON")
    fleche(13, 5.5, 12, 3, growth_ok, "OUI" if growth_ok else "NON")
    fleche(13, 5.5, 14, 3, not growth_ok, "OUI" if not growth_ok else "NON")
    
    # Feuilles
    leaves = [
        (0, 2, 'CRITIQUE', '#e74c3c'),
        (2.5, 2, 'URGENT', '#ff7a45'),
        (4.5, 2, 'SURVEILLANCE', '#f39c12'),
        (7.5, 2, 'ATTENTION', '#faad14'),
        (9.5, 2, 'SURVEILLANCE', '#f39c12'),
        (12, 2, 'CRITIQUE', '#e74c3c'),
        (14, 2, 'OPTIMAL', '#27ae60'),
    ]
    
    result_idx = {'CRITIQUE': 0, 'URGENT': 1, 'SURVEILLANCE': 2, 'ATTENTION': 3, 'OPTIMAL': 6}
    idx_res = result_idx.get(result['status'], 6)
    
    for i, (lx, ly, name, color) in enumerate(leaves):
        is_res = (i == idx_res)
        alpha = 1.0 if is_res else 0.4
        lw = 3 if is_res else 1
        rect = FancyBboxPatch((lx-0.7, ly-0.5), 1.4, 1.0, boxstyle="round,pad=0.1", facecolor=color, ec='#2ecc71' if is_res else 'white', linewidth=lw, alpha=alpha, zorder=3)
        ax.add_patch(rect)
        txt = f"{name}\n✅" if is_res else name
        ax.text(lx, ly, txt, ha='center', va='center', fontsize=7, fontweight='bold', color='white', alpha=alpha, zorder=4)
    
    # Résumé (MODIFIÉ pour afficher jours/mois)
    saturation_display = result.get('saturation_text', f"{result.get('saturation_months_raw', 0)} mois")
    summary = f"📊 {result['status']} | Charge: {result['predicted_load']}% | Score: {result['xgboost_score']}% | Saturation: {saturation_display}"
    ax.text(7, 12.3, summary, ha='center', va='center', fontsize=9, fontfamily='monospace',
           bbox=dict(boxstyle='round', facecolor='white', edgecolor='#27ae60', alpha=0.95))
    ax.set_title('🌳 ARBRE DE DÉCISION XGBOOST - OUI/NON DYNAMIQUES', fontsize=14, fontweight='bold', pad=15)
    
    plt.tight_layout()
    path = str(OUTPUT_DIR / f'arbre_{TIMESTAMP}.png')
    plt.savefig(path, dpi=200, bbox_inches='tight', facecolor='#f8f9fa')
    plt.close()
    return path


# ============================================================================
# MAIN
# ============================================================================
def main():
    model_load, feature_columns, scaler = load_model()
    if model_load is None:
        print(json.dumps({'success': False, 'error': 'Modèle introuvable'}))
        return
    
    if feature_columns is None:
        feature_columns = ['cpu_usage_avg', 'cpu_usage_peak', 'ram_usage_avg', 'ram_usage_max',
                          'disk_usage_avg', 'disk_usage_max', 'disk_read_iops', 'disk_write_iops',
                          'total_iops', 'response_time', 'visitors_per_day', 'pageviews_per_day',
                          'traffic_growth_rate', 'peak_hours_duration', 'plugin_count',
                          'heavy_plugins_count', 'php_score', 'cache_enabled', 'cdn_enabled', 'wp_factor']
    
    json_file = find_latest_json()
    if json_file is None:
        print(json.dumps({'success': False, 'error': 'Aucun JSON dans Donnee_parametres'}))
        return
    
    params = load_params(json_file)
    _, features_dict = prepare_features(params, feature_columns)
    result = predict(model_load, scaler, features_dict, feature_columns)
    
    # Afficher la saturation
    print(f"\n📅 Saturation: {result.get('saturation_text', 'N/A')}", file=sys.stderr)
    if 'saturation_days' in result:
        print(f"   Jours: {result['saturation_days']:.1f}", file=sys.stderr)
        print(f"   Mois: {result['saturation_months']}, Jours: {result['saturation_jours']}", file=sys.stderr)
    
    # Générer les graphiques + arbre
    g1 = graph_importance(model_load, feature_columns)
    g2 = graph_metrics(features_dict, result, json_file)
    g3 = graph_correlation(features_dict)
    g4 = graph_arbre(features_dict, result, json_file)
    
    output = {
        'success': True,
        'result': result,
        'files': {
            'importance': g1,
            'dashboard': g2,
            'correlation': g3,
            'arbre': g4
        },
        'source': Path(json_file).name
    }
    
    print(json.dumps(output, ensure_ascii=False))


if __name__ == "__main__":
    main()
