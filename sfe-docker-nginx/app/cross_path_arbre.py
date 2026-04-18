#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import json
import os
import glob
import numpy as np
import pandas as pd
import joblib
import xgboost as xgb
import matplotlib
matplotlib.use('Agg')  # Backend non-interactif pour éviter les erreurs
import matplotlib.pyplot as plt
import matplotlib.patches as patches
from datetime import datetime
from matplotlib.patches import FancyBboxPatch
import sys
import math

# Rediriger stderr pour éviter les erreurs dans la sortie JSON
sys.stderr = open('/dev/null', 'w')

# ============================================================================
# 1. CHARGEMENT DU MODÈLE ET DU JSON
# ============================================================================

def find_latest_json_file(dossier="Donnee_parametres"):
    """Trouve automatiquement le dernier fichier JSON (le plus récent en premier)"""
    if not os.path.exists(dossier):
        return None
    
    # Chercher tous les fichiers JSON
    fichiers = glob.glob(os.path.join(dossier, "parameters_*.json"))
    fichiers.extend(glob.glob(os.path.join(dossier, "paramètres_*.json")))
    fichiers.extend(glob.glob(os.path.join(dossier, "*.json")))
    
    if not fichiers:
        return None
    
    # Trier par date de modification (le plus récent en PREMIER)
    fichiers.sort(key=os.path.getmtime, reverse=True)
    
    return fichiers[0]


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
    except Exception:
        return None


def prepare_features(params, feature_columns):
    """Prépare les features pour la prédiction avec les nouveaux champs IOPS"""
    
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
    
    wp_type_map = {'small': 0, 'medium': 1, 'performance': 2, 'enterprise': 3}
    wp_type = wp_type_map.get(params.get('wp_type', 'medium'), 1)
    
    php_scores = {'7.4': 0.85, '8.0': 0.90, '8.1': 0.95, '8.2': 1.00, '8.3': 1.05}
    php_score = php_scores.get(str(php_version), 0.95)
    
    start = params.get('peak_hours_start', '09:00')
    end = params.get('peak_hours_end', '18:00')
    if start and end:
        start_hour = int(start.split(':')[0])
        end_hour = int(end.split(':')[0])
        peak_hours = max(1, end_hour - start_hour)
    else:
        peak_hours = 4
    
    heavy_plugins_list = [p.strip() for p in heavy_plugins_str.split(',') if p.strip()]
    heavy_plugins_count = len(heavy_plugins_list)
    
    # Calculer l'IOPS total
    total_iops = disk_read_iops + disk_write_iops
    
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
    
    df = pd.DataFrame([features_dict])
    
    if feature_columns:
        for col in feature_columns:
            if col not in df.columns:
                df[col] = 0
        df = df[feature_columns]
    
    return df, features_dict


def get_decision_path_from_model(booster, features_dict, feature_names):
    """
    Parcourt le VRAI modèle XGBoost pour trouver le chemin de décision
    """
    decision_path = []
    
    # Obtenir la structure du premier arbre
    try:
        tree_dump = booster.get_dump(with_stats=True)[0]
    except:
        return []
    
    # Parser l'arbre pour extraire les décisions
    import re
    lines = tree_dump.split('\n')
    
    for line in lines:
        if 'f' in line and '<' in line:
            feature_match = re.search(r'f(\d+)<', line)
            threshold_match = re.search(r'<([\d.]+)', line)
            
            if feature_match and threshold_match:
                feature_idx = int(feature_match.group(1))
                threshold = float(threshold_match.group(1))
                
                if feature_idx < len(feature_names):
                    feature_name = feature_names[feature_idx]
                    feature_value = features_dict.get(feature_name, 0)
                    decision = '<' if feature_value < threshold else '>='
                    
                    decision_path.append({
                        'feature': feature_name,
                        'threshold': threshold,
                        'feature_value': feature_value,
                        'decision': decision
                    })
    
    return decision_path


def predict_with_model(model_load, features_dict, feature_columns):
    """Fait la prédiction et retourne les résultats"""
    X, _ = prepare_features(features_dict, feature_columns)
    
    if hasattr(model_load, 'predict'):
        predicted_load = float(model_load.predict(X)[0])
        predicted_load = min(100, max(0, predicted_load))
    else:
        predicted_load = 50
    
    # Calcul du score XGBoost
    score = 0
    score += (features_dict.get('cpu_usage_avg', 50) / 100) * 15
    score += (features_dict.get('ram_usage_avg', 50) / 100) * 13
    score += min(1, features_dict.get('visitors_per_day', 1000) / 50000) * 10
    score += min(1, features_dict.get('traffic_growth_rate', 10) / 100) * 12
    score += min(1, features_dict.get('plugin_count', 20) / 50) * 6
    # Ajout IOPS
    score += min(1, features_dict.get('total_iops', 0) / 2000) * 5
    if features_dict.get('cache_enabled', 0) == 0:
        score += 3
    if features_dict.get('cdn_enabled', 0) == 0:
        score += 3
    xgboost_score = min(100, max(0, score))
    
    if predicted_load >= 85:
        status = 'CRITIQUE'
    elif predicted_load >= 75:
        status = 'URGENT'
    elif predicted_load >= 65:
        status = 'ATTENTION'
    else:
        status = 'OPTIMAL'
    
    return {
        'predicted_load': round(predicted_load, 1),
        'xgboost_score': round(xgboost_score, 1),
        'status': status
    }


# ============================================================================
# 2. DESSIN DE L'ARBRE XGBOOST NATIF (PREMIER ARBRE - TREE 0)
# ============================================================================

def draw_xgboost_tree_0(booster, decision_path, output_file='xgboost_tree_0.png'):
    """Dessine le PREMIER arbre (Tree 0) du modèle XGBoost"""
    try:
        fig, ax = plt.subplots(figsize=(35, 22), dpi=150)
        xgb.plot_tree(booster, num_trees=0, rankdir='LR', ax=ax)
        
        path_features = set()
        for step in decision_path:
            path_features.add(step['feature'])
        
        for child in ax.get_children():
            if isinstance(child, plt.Text):
                text = child.get_text()
                for feature in path_features:
                    if feature in text:
                        child.set_color('#2ecc71')
                        child.set_fontweight('bold')
                        bbox = child.get_bbox_patch()
                        if bbox:
                            bbox.set_facecolor('#d5f5e3')
                            bbox.set_edgecolor('#27ae60')
                            bbox.set_linewidth(2)
                        break
        
        ax.set_title(f"🌳 Arbre XGBoost NATIF - Tree 0 (Premier arbre)", fontsize=16, fontweight='bold', pad=20)
        plt.tight_layout()
        plt.savefig(output_file, dpi=300, bbox_inches='tight', facecolor='white')
        plt.close()
        return True
    except Exception as e:
        return False


# ============================================================================
# 3. DESSIN DE L'ARBRE XGBOOST NATIF (DERNIER ARBRE)
# ============================================================================

def draw_xgboost_tree_final(booster, decision_path, output_file='xgboost_tree_final.png'):
    """Dessine le DERNIER arbre du modèle XGBoost"""
    try:
        fig, ax = plt.subplots(figsize=(35, 22), dpi=150)
        
        # Compter le nombre total d'arbres
        tree_dumps = booster.get_dump()
        num_trees = len(tree_dumps)
        last_tree_idx = num_trees - 1
        
        xgb.plot_tree(booster, num_trees=last_tree_idx, rankdir='LR', ax=ax)
        
        path_features = set()
        for step in decision_path:
            path_features.add(step['feature'])
        
        for child in ax.get_children():
            if isinstance(child, plt.Text):
                text = child.get_text()
                for feature in path_features:
                    if feature in text:
                        child.set_color('#2ecc71')
                        child.set_fontweight('bold')
                        bbox = child.get_bbox_patch()
                        if bbox:
                            bbox.set_facecolor('#d5f5e3')
                            bbox.set_edgecolor('#27ae60')
                            bbox.set_linewidth(2)
                        break
        
        ax.set_title(f"🌳 Arbre XGBoost NATIF - Dernier arbre (Tree {last_tree_idx})", fontsize=16, fontweight='bold', pad=20)
        plt.tight_layout()
        plt.savefig(output_file, dpi=300, bbox_inches='tight', facecolor='white')
        plt.close()
        return True
    except Exception as e:
        return False


# ============================================================================
# 4. DESSIN DE L'ARBRE PERSONNALISÉ (VERSION PÉDAGOGIQUE)
# ============================================================================

def draw_custom_tree(features_dict, decision_path, result, output_file='xgboost_tree_custom.png'):
    """Dessine un arbre personnalisé PÉDAGOGIQUE avec toutes les branches"""
    try:
        fig, ax = plt.subplots(figsize=(28, 18), dpi=150)
        ax.set_xlim(-1.5, 15)
        ax.set_ylim(0, 12)
        ax.axis('off')
        ax.set_facecolor('#f0f2f5')
        fig.patch.set_facecolor('#f0f2f5')
        
        # Dictionnaire des décisions
        path_dict = {}
        for step in decision_path:
            path_dict[step['feature']] = step['decision']
        
        # ==================== POSITIONS ====================
        cpu_x, cpu_y = 6.5, 10
        ram_x, ram_y = 3, 7.5
        visitors_x, visitors_y = 10, 7.5
        plugins_x, plugins_y = 0.5, 5
        cache_x, cache_y = 4.5, 5
        iops_x, iops_y = 8, 5
        php_x, php_y = 12, 5
        growth_x, growth_y = 14, 2.5
        
        leaf1_x, leaf1_y = -0.5, 2.5
        leaf2_x, leaf2_y = 1.5, 2.5
        leaf3_x, leaf3_y = 3.5, 2.5
        leaf4_x, leaf4_y = 5.5, 2.5
        leaf5_x, leaf5_y = 7.5, 2.5
        leaf6_x, leaf6_y = 9.5, 2.5
        leaf7_x, leaf7_y = 11.5, 2.5
        leaf8_x, leaf8_y = 13.5, 2.5
        
        # ==================== DESSIN DES NŒUDS ====================
        
        def draw_node(x, y, feature, threshold, value, is_on_path):
            color = '#2ecc71' if is_on_path else '#3498db'
            circle = plt.Circle((x, y), 0.7, color=color, ec='white', linewidth=3, zorder=3)
            ax.add_patch(circle)
            
            if isinstance(value, float):
                val_text = f"{value:.0f}" if value < 1000 else f"{value:.0f}"
            else:
                val_text = str(value)
            
            ax.text(x, y, f"{feature}\n> {threshold} ?\n(v: {val_text})", 
                    ha='center', va='center', fontsize=8, fontweight='bold', 
                    color='white', zorder=4)
        
        # CPU
        cpu_val = features_dict.get('cpu_usage_avg', 0)
        draw_node(cpu_x, cpu_y, "CPU", 65, cpu_val, 'cpu_usage_avg' in path_dict)
        
        # RAM
        ram_val = features_dict.get('ram_usage_avg', 0)
        draw_node(ram_x, ram_y, "RAM", 70, ram_val, 'ram_usage_avg' in path_dict)
        
        # Visiteurs
        visitors_val = features_dict.get('visitors_per_day', 0)
        draw_node(visitors_x, visitors_y, "Visiteurs", 15000, visitors_val, 'visitors_per_day' in path_dict)
        
        # Plugins
        plugins_val = features_dict.get('plugin_count', 0)
        draw_node(plugins_x, plugins_y, "Plugins", 30, plugins_val, 'plugin_count' in path_dict)
        
        # Cache
        cache_val = "Oui" if features_dict.get('cache_enabled', 0) == 1 else "Non"
        cache_on_path = 'cache_enabled' in path_dict
        cache_color = '#2ecc71' if cache_on_path else '#3498db'
        cache_circle = plt.Circle((cache_x, cache_y), 0.7, color=cache_color, ec='white', linewidth=3, zorder=3)
        ax.add_patch(cache_circle)
        ax.text(cache_x, cache_y, f"Cache\nactivé ?\n(v: {cache_val})", 
                ha='center', va='center', fontsize=8, fontweight='bold', color='white', zorder=4)
        
        # IOPS (NOUVEAU)
        iops_val = features_dict.get('total_iops', 0)
        iops_on_path = 'total_iops' in path_dict
        iops_color = '#2ecc71' if iops_on_path else '#3498db'
        iops_circle = plt.Circle((iops_x, iops_y), 0.7, color=iops_color, ec='white', linewidth=3, zorder=3)
        ax.add_patch(iops_circle)
        ax.text(iops_x, iops_y, f"IOPS\n> 1000 ?\n(v: {iops_val:.0f})", 
                ha='center', va='center', fontsize=8, fontweight='bold', color='white', zorder=4)
        
        # PHP (hors chemin par défaut)
        php_val = features_dict.get('php_score', 0.95)
        php_circle = plt.Circle((php_x, php_y), 0.7, color='#3498db', ec='white', linewidth=2, zorder=3)
        ax.add_patch(php_circle)
        ax.text(php_x, php_y, f"PHP\n8.2+ ?\n(v: {php_val:.2f})", 
                ha='center', va='center', fontsize=8, fontweight='bold', color='white', zorder=4)
        
        # Croissance (hors chemin)
        growth_val = features_dict.get('traffic_growth_rate', 0)
        growth_circle = plt.Circle((growth_x, growth_y), 0.7, color='#3498db', ec='white', linewidth=2, zorder=3)
        ax.add_patch(growth_circle)
        ax.text(growth_x, growth_y, f"Croissance\n> 20% ?\n(v: {growth_val:.0f}%)", 
                ha='center', va='center', fontsize=8, fontweight='bold', color='white', zorder=4)
        
        # ==================== DESSIN DES FEUILLES ====================
        
        # Déterminer la feuille du résultat
        status = result.get('status', 'OPTIMAL')
        if status == 'CRITIQUE':
            path_leaf_id = 1
        elif status == 'URGENT':
            path_leaf_id = 3
        elif status == 'ATTENTION':
            path_leaf_id = 2
        else:
            path_leaf_id = 8
        
        leaves_data = [
            {'id': 1, 'x': leaf1_x, 'y': leaf1_y, 'text': '🔴 CRITIQUE\nCharge > 85%', 'color': '#ff4d4f'},
            {'id': 2, 'x': leaf2_x, 'y': leaf2_y, 'text': '🟡 ATTENTION\nCharge 65-75%', 'color': '#faad14'},
            {'id': 3, 'x': leaf3_x, 'y': leaf3_y, 'text': '🟠 URGENT\nCharge 75-85%', 'color': '#ff7a45'},
            {'id': 4, 'x': leaf4_x, 'y': leaf4_y, 'text': '🟡 ATTENTION\nCharge 65-75%', 'color': '#faad14'},
            {'id': 5, 'x': leaf5_x, 'y': leaf5_y, 'text': '🟢 OPTIMAL\nCharge < 65%', 'color': '#52c41a'},
            {'id': 6, 'x': leaf6_x, 'y': leaf6_y, 'text': '🟡 ATTENTION\nCharge 65-75%', 'color': '#faad14'},
            {'id': 7, 'x': leaf7_x, 'y': leaf7_y, 'text': '🔴 CRITIQUE\nCharge > 85%', 'color': '#ff4d4f'},
            {'id': 8, 'x': leaf8_x, 'y': leaf8_y, 'text': '🟢 OPTIMAL\nCharge < 65%', 'color': '#52c41a'},
        ]
        
        for leaf in leaves_data:
            lx, ly = leaf['x'], leaf['y']
            text = leaf['text']
            color = leaf['color']
            is_path_leaf = (leaf['id'] == path_leaf_id)
            
            if is_path_leaf:
                rect = FancyBboxPatch((lx-0.9, ly-0.6), 1.8, 1.2,
                                       boxstyle="round,pad=0.1", facecolor=color,
                                       ec='#2ecc71', linewidth=3, zorder=3)
                final_text = f"{text}\n✅ RÉSULTAT\nCharge: {result.get('predicted_load', '?')}%"
                ax.text(lx, ly, final_text, ha='center', va='center',
                        fontsize=7, fontweight='bold', color='white', zorder=4)
            else:
                rect = FancyBboxPatch((lx-0.85, ly-0.55), 1.7, 1.1,
                                       boxstyle="round,pad=0.1", facecolor=color,
                                       ec='white', linewidth=2, zorder=3, alpha=0.4)
                ax.text(lx, ly, text, ha='center', va='center',
                        fontsize=7, fontweight='bold', color='white', zorder=4, alpha=0.4)
            ax.add_patch(rect)
        
        # ==================== FLÈCHES ====================
        
        def draw_smooth_arrow(ax, start, end, color, width, label=None, label_pos=0.5):
            dx = end[0] - start[0]
            dy = end[1] - start[1]
            distance = math.sqrt(dx**2 + dy**2)
            
            if distance < 0.1:
                return
            
            start_reduce = 0.7 / distance
            end_reduce = 0.65 / distance
            
            new_start = (start[0] + dx * start_reduce, start[1] + dy * start_reduce)
            new_end = (end[0] - dx * end_reduce, end[1] - dy * end_reduce)
            
            ax.annotate('', xy=new_end, xytext=new_start,
                       arrowprops=dict(arrowstyle='->', color=color, lw=width, mutation_scale=20))
            
            if label:
                mid_x = start[0] + dx * label_pos
                mid_y = start[1] + dy * label_pos
                ax.text(mid_x, mid_y, label, ha='center', va='center', fontsize=11,
                       color=color, fontweight='bold',
                       bbox=dict(boxstyle='round,pad=0.3', facecolor='white', edgecolor=color, alpha=0.95))
        
        # CPU → RAM et CPU → Visiteurs
        cpu_decision = path_dict.get('cpu_usage_avg', '>=')
        draw_smooth_arrow(ax, (cpu_x, cpu_y), (ram_x, ram_y), '#27ae60' if cpu_decision == '<' else '#95a5a6', 3.5, '<' if cpu_decision == '<' else None)
        draw_smooth_arrow(ax, (cpu_x, cpu_y), (visitors_x, visitors_y), '#27ae60' if cpu_decision == '>=' else '#95a5a6', 3.5, '>=' if cpu_decision == '>=' else None)
        
        # RAM → Plugins et RAM → Cache
        ram_decision = path_dict.get('ram_usage_avg', '<')
        draw_smooth_arrow(ax, (ram_x, ram_y), (plugins_x, plugins_y), '#27ae60' if ram_decision == '<' else '#95a5a6', 3.5, '<' if ram_decision == '<' else None)
        draw_smooth_arrow(ax, (ram_x, ram_y), (cache_x, cache_y), '#27ae60' if ram_decision == '>=' else '#95a5a6', 3.5, '>=' if ram_decision == '>=' else None)
        
        # Visiteurs → IOPS et Visiteurs → PHP
        visitors_decision = path_dict.get('visitors_per_day', '>=')
        draw_smooth_arrow(ax, (visitors_x, visitors_y), (iops_x, iops_y), '#27ae60' if visitors_decision == '<' else '#95a5a6', 3.5, '<' if visitors_decision == '<' else None)
        draw_smooth_arrow(ax, (visitors_x, visitors_y), (php_x, php_y), '#27ae60' if visitors_decision == '>=' else '#95a5a6', 3.5, '>=' if visitors_decision == '>=' else None)
        
        # IOPS → Croissance
        iops_decision = path_dict.get('total_iops', '>=')
        draw_smooth_arrow(ax, (iops_x, iops_y), (growth_x, growth_y), '#27ae60' if iops_decision == '<' else '#95a5a6', 3.5, '<' if iops_decision == '<' else None)
        
        # Plugins → feuilles
        plugins_decision = path_dict.get('plugin_count', '>=')
        draw_smooth_arrow(ax, (plugins_x, plugins_y), (leaf1_x, leaf1_y), '#27ae60' if plugins_decision == '<' else '#95a5a6', 3.5, '<' if plugins_decision == '<' else None)
        draw_smooth_arrow(ax, (plugins_x, plugins_y), (leaf2_x, leaf2_y), '#27ae60' if plugins_decision == '>=' else '#95a5a6', 3.5, '>=' if plugins_decision == '>=' else None)
        
        # Cache → feuilles
        cache_decision = path_dict.get('cache_enabled', '>=')
        draw_smooth_arrow(ax, (cache_x, cache_y), (leaf3_x, leaf3_y), '#27ae60' if cache_decision == '<' else '#95a5a6', 3.5, '<' if cache_decision == '<' else None)
        draw_smooth_arrow(ax, (cache_x, cache_y), (leaf4_x, leaf4_y), '#27ae60' if cache_decision == '>=' else '#95a5a6', 3.5, '>=' if cache_decision == '>=' else None)
        
        # PHP → feuilles
        draw_smooth_arrow(ax, (php_x, php_y), (leaf5_x, leaf5_y), '#95a5a6', 1.5, None)
        draw_smooth_arrow(ax, (php_x, php_y), (leaf6_x, leaf6_y), '#95a5a6', 1.5, None)
        
        # Croissance → feuilles
        growth_decision = path_dict.get('traffic_growth_rate', '>=')
        draw_smooth_arrow(ax, (growth_x, growth_y), (leaf7_x, leaf7_y), '#27ae60' if growth_decision == '<' else '#95a5a6', 3.5, '<' if growth_decision == '<' else None)
        draw_smooth_arrow(ax, (growth_x, growth_y), (leaf8_x, leaf8_y), '#27ae60' if growth_decision == '>=' else '#95a5a6', 3.5, '>=' if growth_decision == '>=' else None)
        
        # ==================== RÉSUMÉ ====================
        summary = "📊 CHEMIN PARCOURU:\n"
        for step in decision_path[:8]:  # Limiter pour l'affichage
            summary += f"   → {step['feature']} = {step['feature_value']} {step['decision']} {step['threshold']}\n"
        
        saturation_text = f"{result.get('predicted_saturation_months', '?')} mois"
        summary += f"\n📈 RÉSULTAT:\n"
        summary += f"   • Charge: {result.get('predicted_load', '?')}%\n"
        summary += f"   • Score: {result.get('xgboost_score', '?')}%\n"
        summary += f"   • Statut: {result.get('status', '?')}\n"
        summary += f"   • Saturation: {saturation_text}"
        
        ax.text(0.02, 0.98, summary, transform=ax.transAxes, fontsize=9,
               verticalalignment='top', fontfamily='monospace',
               bbox=dict(boxstyle='round', facecolor='white', alpha=0.9))
        
        # ==================== LÉGENDE ====================
        legend_items = [
            ('🟢', 'Nœud sur le chemin', '#2ecc71'),
            ('🔵', 'Nœud hors chemin', '#3498db'),
            ('🟢', 'Flèche du chemin', '#27ae60'),
            ('⚪', 'Flèche hors chemin', '#95a5a6'),
            ('🔴', 'Critique (>85%)', '#ff4d4f'),
            ('🟠', 'Urgent (75-85%)', '#ff7a45'),
            ('🟡', 'Attention (65-75%)', '#faad14'),
            ('🟢', 'Optimal (<65%)', '#52c41a'),
        ]
        
        for i, (emoji, label, color) in enumerate(legend_items):
            rect = patches.Rectangle((0.2 + i*1.8, 0.08), 0.3, 0.3, facecolor=color, ec='white', linewidth=1)
            ax.add_patch(rect)
            ax.text(0.55 + i*1.8, 0.23, f'{emoji} {label}', fontsize=7, va='center')
        
        # Titre
        ax.text(7, 11.2, '🌳 ARBRE DE DÉCISION XGBOOST - CHEMIN EN VERT', 
                fontsize=14, fontweight='bold', ha='center', va='center', color='#1a2c3e')
        
        plt.tight_layout()
        plt.savefig(output_file, dpi=200, bbox_inches='tight', facecolor='#f0f2f5')
        plt.close()
        return True
    except Exception as e:
        return False


# ============================================================================
# 5. FONCTION PRINCIPALE
# ============================================================================

def main():
    """Fonction principale - utilise le fichier .pkl"""
    
    # 1. Charger le modèle .pkl
    try:
        models = joblib.load("xgboost_models.pkl")
        model_load = models['model_load']
        feature_columns = models.get('feature_columns', None)
        booster = model_load.get_booster()
    except Exception as e:
        print(json.dumps({'success': False, 'error': f'Erreur chargement modèle .pkl: {str(e)}'}))
        return
    
    # 2. Trouver le dernier fichier JSON
    json_file = find_latest_json_file("Donnee_parametres")
    if json_file is None:
        print(json.dumps({'success': False, 'error': 'Aucun fichier JSON trouvé. Veuillez d\'abord faire une prédiction.'}))
        return
    
    # 3. Charger les paramètres
    params = load_json_file(json_file)
    if params is None:
        print(json.dumps({'success': False, 'error': 'Impossible de charger les paramètres'}))
        return
    
    # 4. Préparer les features avec les nouveaux champs
    if feature_columns is None:
        feature_columns = ['cpu_usage_avg', 'cpu_usage_peak', 'ram_usage_avg', 
                          'disk_read_iops', 'disk_write_iops', 'total_iops',
                          'response_time', 'visitors_per_day', 'pageviews_per_day', 
                          'traffic_growth_rate', 'peak_hours_duration', 'plugin_count', 
                          'heavy_plugins_count', 'php_score', 'cache_enabled', 
                          'cdn_enabled', 'wp_factor']
    
    X, features_dict = prepare_features(params, feature_columns)
    
    # 5. Obtenir les noms des features depuis le booster
    try:
        feature_names = booster.feature_names
        if feature_names is None:
            feature_names = feature_columns
    except:
        feature_names = feature_columns
    
    # 6. Construire le chemin de décision à partir du modèle
    decision_path = get_decision_path_from_model(booster, features_dict, feature_names)
    
    if not decision_path:
        # Fallback: chemin simulé avec les nouveaux champs
        decision_path = []
        thresholds = {
            'cpu_usage_avg': 65,
            'ram_usage_avg': 70,
            'visitors_per_day': 15000,
            'plugin_count': 30,
            'total_iops': 1000,
            'cache_enabled': 0.5,
            'traffic_growth_rate': 20,
        }
        for feature, threshold in thresholds.items():
            if feature in features_dict:
                val = features_dict[feature]
                decision = '<' if val < threshold else '>='
                decision_path.append({
                    'feature': feature,
                    'threshold': threshold,
                    'feature_value': val,
                    'decision': decision
                })
    
    # 7. Prédiction
    result = predict_with_model(model_load, features_dict, feature_columns)
    
    # Ajouter la saturation
    growth_rate = features_dict.get('traffic_growth_rate', 10)
    current_load = result['predicted_load']
    if current_load >= 90:
        saturation_months = 0
    elif growth_rate <= 0:
        saturation_months = 999
    else:
        saturation_months = round(np.log(90 / max(1, current_load)) / np.log(1 + growth_rate / 100), 1)
    result['predicted_saturation_months'] = saturation_months
    
    # 8. Générer les images
    success_count = 0
    
    if draw_xgboost_tree_0(booster, decision_path, 'xgboost_tree_0.png'):
        success_count += 1
    
    if draw_xgboost_tree_final(booster, decision_path, 'xgboost_tree_final.png'):
        success_count += 1
    
    if draw_custom_tree(features_dict, decision_path, result, 'xgboost_tree_custom.png'):
        success_count += 1
    
    # 9. Retourner le résultat
    if success_count == 3:
        print(json.dumps({'success': True, 'message': '3 arbres générés avec succès'}))
    else:
        print(json.dumps({'success': False, 'error': f'Seulement {success_count}/3 arbres générés'}))


if __name__ == "__main__":
    main()