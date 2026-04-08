-- Création de la base de données
CREATE DATABASE IF NOT EXISTS vala_bleu_db;
USE vala_bleu_db;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des prédictions (historique complet)
CREATE TABLE IF NOT EXISTS predictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    cpu_usage INT NOT NULL,
    ram_usage INT NOT NULL,
    growth_rate INT NOT NULL,
    wp_type VARCHAR(50) NOT NULL,
    predicted_load DECIMAL(5,2) NOT NULL,
    status VARCHAR(50) NOT NULL,
    recommendation TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insertion de l'utilisateur admin (mot de passe: vala2026)
-- Hash du mot de passe: vala2026 = MD5('vala2026') -> 6c6c4d3c7e5c9d4e8f2a1b3c5d7e9f0a
INSERT INTO users (username, password_hash) 
VALUES ('admin', MD5('vala2026'))
ON DUPLICATE KEY UPDATE username = username;

-- Index pour les recherches rapides
CREATE INDEX idx_user_date ON predictions(user_id, created_at);
CREATE INDEX idx_predicted_load ON predictions(predicted_load);
