import sys

from fastapi import FastAPI
from fastapi.staticfiles import StaticFiles
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import FileResponse
from script.cleanup import cleanup_json_files, cleanup_images
import subprocess
import os
import json
import glob
# Ajouter le dossier script au path pour importer cleanup
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'script'))


app = FastAPI()

# =========================
# CORS
# =========================
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# =========================
# PATHS (DOCKER SAFE)
# =========================
BASE_DIR = "/app/service"
PRED_SCRIPT = os.path.join(BASE_DIR, "predict_from_file.py")
EXPORT_DIR = os.path.join(BASE_DIR, "analysis_exports")

# Créer le dossier s'il n'existe pas
os.makedirs(EXPORT_DIR, exist_ok=True)

# =========================
# STATIC FILES
# =========================
app.mount("/static", StaticFiles(directory=EXPORT_DIR), name="static")

# =========================
# IMAGE TYPES
# =========================
IMAGE_TYPES = {
    "tree": "arbre_",
    "correlation": "correlation_",
    "dashboard": "dashboard_",
    "feature_importance": "feature_importance_"
}

# =========================
# GET LATEST IMAGES
# =========================
def get_latest_images():
    if not os.path.exists(EXPORT_DIR):
        return []

    files = os.listdir(EXPORT_DIR)
    result = []

    for img_type, prefix in IMAGE_TYPES.items():
        candidates = [
            f for f in files
            if f.startswith(prefix) and f.endswith(".png")
        ]

        if not candidates:
            continue

        candidates.sort(reverse=True)
        latest = candidates[0]

        result.append({
            "type": img_type,
            "url": f"http://localhost:8000/static/{latest}",
            "filename": latest
        })

    return result

# =========================
# GET TREE IMAGES
# =========================
def get_tree_images():
    """Récupère les images des arbres XGBoost"""
    if not os.path.exists(EXPORT_DIR):
        return []

    tree_images = []
    
    # Chercher tous les fichiers tree_
    all_tree_files = glob.glob(os.path.join(EXPORT_DIR, "tree_*.png"))
    
    for filepath in all_tree_files:
        filename = os.path.basename(filepath)
        tree_images.append({
            "type": "tree",
            "url": f"http://localhost:8000/static/{filename}",
            "filename": filename
        })
    
    return tree_images

# =========================
# SIMPLE PREDICTION
# =========================
@app.get("/predict/simple")
def predict_simple(cpu: float, ram: float, visitors: int, plugins: int, growth: float):
    score = max(0, min(100, 100 - (cpu + ram) / 2 + growth))

    return {
        "predicted_load": round(score, 2),
        "xgboost_score": round(score - 5, 2),
        "saturation_months": max(1, int(36 - growth)),
        "status": "CRITIQUE" if score > 80 else "SURVEILLANCE" if score > 60 else "OPTIMAL"
    }

# =========================
# PREDICT FROM FILE
# =========================
@app.get("/predict/from-file")
def predict_from_file():
    try:
        if not os.path.exists(PRED_SCRIPT):
            return {"status": "error", "message": f"Script introuvable: {PRED_SCRIPT}"}

        result = subprocess.run(
            ["python", PRED_SCRIPT],
            capture_output=True,
            text=True,
            timeout=60
        )

        print("STDOUT:", result.stdout)
        print("STDERR:", result.stderr)

        try:
            output_json = json.loads(result.stdout.strip())
        except json.JSONDecodeError as e:
            return {
                "status": "error",
                "message": f"Erreur parsing JSON: {str(e)}",
                "raw_output": result.stdout,
                "stderr": result.stderr
            }

        # Récupérer les résultats
        prediction_result = output_json.get("result", {})
        
        # Récupérer les images
        latest_images = get_latest_images()
        tree_images = get_tree_images()

        return {
            "status": "success",
            "output": {
                "result": prediction_result,
                "images": latest_images,
                "trees": tree_images,
                "source": output_json.get("source", "")
            }
        }

    except subprocess.TimeoutExpired:
        return {"status": "error", "message": "Le script a dépassé le temps limite (60s)"}
    except Exception as e:
        return {"status": "error", "message": str(e)}

# =========================
# DOWNLOAD TREE 0
# =========================
@app.get("/download/tree0")
def download_tree0():
    # Chercher le fichier tree_0 le plus récent
    tree_files = glob.glob(os.path.join(EXPORT_DIR, "tree_0*.png"))
    
    if not tree_files:
        # Essayer aussi xgboost_tree_0
        tree_files = glob.glob(os.path.join(EXPORT_DIR, "xgboost_tree_0*.png"))
    
    if not tree_files:
        return {"status": "error", "message": "Tree 0 non trouvé. Lancez une analyse d'abord."}
    
    tree_files.sort(reverse=True)
    file_path = tree_files[0]
    
    return FileResponse(
        file_path,
        media_type="image/png",
        filename=os.path.basename(file_path),
        headers={"Content-Disposition": f"attachment; filename={os.path.basename(file_path)}"}
    )

# =========================
# DOWNLOAD TREE FINAL
# =========================
@app.get("/download/tree-final")
def download_tree_final():
    # Chercher le fichier tree_final le plus récent
    tree_files = glob.glob(os.path.join(EXPORT_DIR, "tree_final*.png"))
    
    if not tree_files:
        # Essayer aussi xgboost_tree_final
        tree_files = glob.glob(os.path.join(EXPORT_DIR, "xgboost_tree_final*.png"))
    
    if not tree_files:
        return {"status": "error", "message": "Tree Final non trouvé. Lancez une analyse d'abord."}
    
    tree_files.sort(reverse=True)
    file_path = tree_files[0]
    
    return FileResponse(
        file_path,
        media_type="image/png",
        filename=os.path.basename(file_path),
        headers={"Content-Disposition": f"attachment; filename={os.path.basename(file_path)}"}
    )

# =========================
# HEALTH CHECK
# =========================
@app.get("/")
def root():
    return {
        "status": "API running",
        "export_dir": EXPORT_DIR,
        "export_dir_exists": os.path.exists(EXPORT_DIR),
        "endpoints": [
            "/predict/simple",
            "/predict/from-file",
            "/download/tree0",
            "/download/tree-final",
            "/static/{filename}"
        ]
    }
