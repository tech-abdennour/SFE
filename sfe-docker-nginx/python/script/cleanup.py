#!/usr/bin/env python3
"""
Script de nettoyage automatique
"""

import os
import glob
from datetime import datetime

# =============================================
# CONFIGURATION - CHEMINS DANS LE CONTENEUR
# =============================================
# Dans le conteneur :
#   /app = racine (monté depuis ./python)
#   /app/Donnee_parametres (monté depuis ./app/Donnee_parametres)
#   /app/service/analysis_exports (monté depuis ./python/service/analysis_exports)

PARAMS_DIR = "/app/Donnee_parametres"
EXPORTS_DIR = "/app/service/analysis_exports"

# Types d'images à vérifier
IMAGE_TYPES = ["dashboard_", "correlation_", "feature_importance_", "arbre_"]

# Fichiers protégés (jamais supprimés)
PROTECTED_PREFIXES = ["tree_0", "tree_final"]

def log(message):
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{timestamp}] {message}")

def cleanup_json_files():
    """
    Si > 1 JSON → supprime les anciens, garde le plus récent
    """
    log(f"📁 Donnee_parametres/ → {PARAMS_DIR}")
    
    if not os.path.exists(PARAMS_DIR):
        log(f"   ⚠️  Dossier introuvable : {PARAMS_DIR}")
        return
    
    json_files = glob.glob(os.path.join(PARAMS_DIR, "*.json"))
    
    if len(json_files) <= 1:
        log(f"   ✅ {len(json_files)} fichier(s) → rien à supprimer")
        return
    
    json_files.sort(key=os.path.getmtime, reverse=True)
    
    latest = json_files[0]
    to_delete = json_files[1:]
    
    log(f"   🗑️  Suppression de {len(to_delete)} ancien(s) JSON...")
    for f in to_delete:
        os.remove(f)
        log(f"      ❌ {os.path.basename(f)}")
    
    log(f"   ✅ Gardé : {os.path.basename(latest)}")

def cleanup_images():
    """
    Vérifie CHAQUE type d'image.
    Si TOUS les types ont > 1 fichier → supprime les anciens
    """
    log(f"🖼️  analysis_exports/ → {EXPORTS_DIR}")
    
    if not os.path.exists(EXPORTS_DIR):
        log(f"   ⚠️  Dossier introuvable : {EXPORTS_DIR}")
        return
    
    counts = {}
    for img_type in IMAGE_TYPES:
        files = glob.glob(os.path.join(EXPORTS_DIR, f"{img_type}*.png"))
        counts[img_type] = len(files)
        log(f"   📊 {img_type}* = {len(files)} fichier(s)")
    
    all_above_threshold = all(count > 1 for count in counts.values())
    
    if not all_above_threshold:
        log(f"   ⏸️  Tous les types n'ont pas > 1 fichier → aucune suppression")
        return
    
    log(f"   🗑️  Tous les types > 1 → nettoyage...")
    
    for img_type in IMAGE_TYPES:
        files = glob.glob(os.path.join(EXPORTS_DIR, f"{img_type}*.png"))
        files.sort(key=os.path.getmtime, reverse=True)
        
        latest = files[0]
        to_delete = files[1:]
        
        for f in to_delete:
            os.remove(f)
        
        log(f"      {img_type}* → gardé {os.path.basename(latest)}, supprimé {len(to_delete)}")

def main():
    log("🚀 Début du nettoyage automatique")
    print("=" * 60)
    cleanup_json_files()
    print("=" * 60)
    cleanup_images()
    print("=" * 60)
    log("🏁 Nettoyage terminé")

if __name__ == "__main__":
    main()