FROM php:8.2-fpm

# Installer les extensions PHP
RUN docker-php-ext-install pdo_mysql mysqli

# Installer Docker CLI
RUN apt-get update && apt-get install -y \
    curl \
    wget \
    gnupg \
    lsb-release \
    && rm -rf /var/lib/apt/lists/*

# Ajouter le dépôt Docker
RUN curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg \
    && echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/debian $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null

# Installer Docker CLI
RUN apt-get update && apt-get install -y docker-ce-cli

# Installer bash
RUN apt-get install -y bash

WORKDIR /var/www/html

CMD ["php-fpm"]
