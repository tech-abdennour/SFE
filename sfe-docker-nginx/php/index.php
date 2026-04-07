<?php
session_start();

// --- 1. Simulation d'un écran de connexion ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    if ($_POST['username'] === 'sfe_user' && $_POST['password'] === 'sfe_pass123') {
        $_SESSION['logged_in'] = true;
    } else {
        $error = "Identifiants incorrects.";
    }
}

// Déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Redirection vers l'écran de connexion si non connecté
if (!isset($_SESSION['logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>SFE - Connexion</title>
        <style>
            body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f0f2f5; }
            .login-box { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); text-align: center; }
            input { display: block; width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
            button { background-color: #4CAF50; color: white; border: none; padding: 12px 20px; border-radius: 4px; cursor: pointer; width: 100%; font-size: 16px; }
            button:hover { background-color: #45a049; }
            .error { color: red; margin-bottom: 10px; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2>SFE - Portail de Prédiction</h2>
            <p>Connectez-vous pour accéder au dashboard.</p>
            <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
            <form method="POST">
                <input type="text" name="username" placeholder="Nom d'utilisateur (sfe_user)" required>
                <input type="password" name="password" placeholder="Mot de passe (sfe_pass123)" required>
                <button type="submit" name="login_submit">Se connecter</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit(); // Stop here
}

// --- 2. Dashboard : Tableau Dynamique (L'Éditeur) ---
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>SFE - Dashboard de Prédiction de Prix</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f9f9f9; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; table-layout: fixed; }
        th, td { border: 1px solid #ddd; padding: 15px; text-align: left; }
        th { background-color: #f2f2f2; color: #333; font-weight: 600; }
        
        /* Style inspiré de l'image : Cadre rouge pour la ligne d'édition */
        .editor-row { border: 2px solid #e74c3c; background-color: #fdf2f2; }
        .editor-row td { border: 2px solid #e74c3c; border-top: none; }
        .editor-row td:first-child { border-left: 2px solid #e74c3c; }
        .editor-row td:last-child { border-right: 2px solid #e74c3c; }

        .editor-input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { padding: 8px 15px; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 14px; border: none; }
        .btn-add { background-color: #e74c3c; color: white; font-weight: bold; width: 100%; }
        .btn-add:hover { background-color: #c0392b; }
        .btn-logout { background-color: #555; color: white; }
        .btn-predict { background-color: #3498db; color: white; margin-top: 10px; width: 100%; }

        .trend-up { color: #e74c3c; font-weight: bold; }
        .trend-down { color: #27ae60; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>Analyse et Prédiction de Prix de Domaines (SFE)</h1>
        <a href="?logout=1" class="btn btn-logout">Déconnexion</a>
    </header>

    <div class="description">
        <p>Ce tableau présente les données d'historique et vous permet d'ajouter de nouveaux clients. Le modèle de Machine Learning supervise la prédiction du prix pour l'année prochaine.</p>
    </div>

    <table id="domainTable">
        <thead>
            <tr>
                <th style="width: 15%;">ID Client</th>
                <th style="width: 30%;">Nom du Domaine</th>
                <th style="width: 15%;">Extension</th>
                <th style="width: 20%;">Prix Achat 2025 (DH)</th>
                <th style="width: 20%;">Prédiction 2026 (DH)</th>
            </tr>
        </thead>
        <tbody id="tableBody">
            <tr>
                <td>101</td>
                <td>ecole-est.ma</td>
                <td>.ma</td>
                <td>120.00</td>
                <td><span class="trend-up">145.50 (📈)</span></td>
            </tr>
            <tr>
                <td>102</td>
                <td>boutique.shop</td>
                <td>.shop</td>
                <td>250.00</td>
                <td><span class="trend-down">210.15 (📉)</span></td>
            </tr>
        </tbody>
        <tfoot>
            <tr class="editor-row">
                <td><input type="number" id="new_client_id" class="editor-input" placeholder="Ex: 103"></td>
                <td><input type="text" id="new_domain_name" class="editor-input" placeholder="Ex: monsite.ma"></td>
                <td>
                    <select id="new_extension" class="editor-input">
                        <option value=".ma">.ma</option>
                        <option value=".net">.net</option>
                        <option value=".shop">.shop</option>
                        <option value=".org">.org</option>
                    </select>
                </td>
                <td><input type="number" id="new_price" class="editor-input" placeholder="Ex: 150"></td>
                <td>
                    <button id="addClientBtn" class="btn btn-add">+ AJOUTER CE CLIENT</button>
                    <button id="predictBtn" class="btn btn-predict">Lancer Prédiction</button>
                </td>
            </tr>
        </tfoot>
    </table>
</div>

<script>
    document.getElementById('addClientBtn').addEventListener('click', function() {
        // 1. Récupérer les données de l'éditeur (le cadre rouge)
        const clientId = document.getElementById('new_client_id').value;
        const domainName = document.getElementById('new_domain_name').value;
        const extension = document.getElementById('new_extension').value;
        const price = document.getElementById('new_price').value;

        // Validation simple
        if (!clientId || !domainName || !price) {
            alert("Veuillez remplir tous les champs avant d'ajouter.");
            return;
        }

        // --- 2. Simuler la prédiction de Machine Learning ---
        // Dans ton SFE réel, cela enverrait les données à ton script Python.
        // On utilise ici une logique simple pour l'exemple.
        let mlFactor = 1.0;
        if (extension === '.ma') mlFactor = 1.3;  // Très populaire
        if (extension === '.net') mlFactor = 0.9; // Baisse de popularité
        if (extension === '.shop') mlFactor = 1.1;

        const predictedPrice = (parseFloat(price) * mlFactor).toFixed(2);
        const trendIcon = (predictedPrice > parseFloat(price)) ? "📈" : "📉";
        const trendClass = (predictedPrice > parseFloat(price)) ? "trend-up" : "trend-down";

        // 3. Créer la nouvelle ligne et l'ajouter dynamiquement
        const tableBody = document.getElementById('tableBody');
        const newRow = tableBody.insertRow();

        newRow.innerHTML = `
            <td>${clientId}</td>
            <td>${domainName}</td>
            <td>${extension}</td>
            <td>${parseFloat(price).toFixed(2)}</td>
            <td><span class="${trendClass}">${predictedPrice} DH (${trendIcon})</span></td>
        `;

        // 4. Effacer les champs de l'éditeur pour la prochaine saisie
        document.getElementById('new_client_id').value = '';
        document.getElementById('new_domain_name').value = '';
        document.getElementById('new_price').value = '';
    });
</script>

</body>
</html>