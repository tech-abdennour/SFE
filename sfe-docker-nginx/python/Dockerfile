FROM python:3.10-slim

WORKDIR /app

# Installer graphviz pour les arbres XGBoost
RUN apt-get update && apt-get install -y --no-install-recommends \
    graphviz \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

EXPOSE 8000
CMD ["uvicorn", "api:app", "--host", "0.0.0.0", "--port", "8000"]
# =============================================
# 🔥 SCRIPT DE DÉMARRAGE
# =============================================
# Créer un script qui lance le nettoyage PUIS l'API
RUN echo '#!/bin/bash\n\
echo "🧹 Nettoyage au démarrage..."\n\
python /app/script/cleanup.py\n\
echo "🚀 Démarrage de l API..."\n\
exec uvicorn api:app --host 0.0.0.0 --port 8000\n\
' > /app/start.sh && chmod +x /app/start.sh

# Utiliser le script de démarrage
CMD ["/app/start.sh"]
# 🔥 Rendre le script exécutable
RUN chmod +x /app/entrypoint.sh

# 🔥 Utiliser entrypoint.sh au lieu de CMD direct
ENTRYPOINT ["/app/entrypoint.sh"]