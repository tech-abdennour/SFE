# Utilise l'image officielle Debian
FROM debian:latest

# Met à jour les paquets (optionnel mais recommandé pour la sécurité)
RUN apt-get update && apt-get upgrade -y

# Commande pour que le conteneur reste allumé (sinon il s'arrête de suite)
CMD ["tail", "-f", "/dev/null"]