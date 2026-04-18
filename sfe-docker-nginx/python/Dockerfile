FROM python:3.11-slim

# Installation des dépendances système (Graphviz pour les arbres)
RUN apt-get update && apt-get install -y \
    graphviz \
    && rm -rf /var/lib/apt/lists/*

# Définir le dossier de travail
WORKDIR /var/www/html

# Installation des bibliothèques Python
RUN pip install --no-cache-dir \
    pandas \
    numpy \
    xgboost \
    joblib \
    scikit-learn \
    matplotlib \
    graphviz \
    Pillow

# Commande par défaut (garder le conteneur actif)
CMD ["tail", "-f", "/dev/null"]