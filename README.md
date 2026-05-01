au debut il faut installer docker
Pour executer ce projet il faut le cloner en uilisant la commande :git clone https://github.com/tech-abdennour/SFE.git
Puis:cd SFE/sfe-docker-nginx
ensuite :docker compose up -d
et pour verifiée si tous les service sont activée:docker ps
et pour front:http://localhost
et pour le backend:http://localhost:8080
et dans dossier script à intérieur de SFE on peut executer deux script
un pour le backups et autre pour healthcheck ainsi:./backups.sh ou ./healthcheck.sh
