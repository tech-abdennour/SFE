FROM php:8.2-fpm

# Installer extensions PHP nécessaires
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Installer outils utiles
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
 && rm -rf /var/lib/apt/lists/*

# Définir le dossier de travail
WORKDIR /var/www/html
