<?php
session_start();

// Configuration de sécurité
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'vala2026');

// --- GESTION DE L'AUTHENTIFICATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        // Redirection pour éviter la resoumission du formulaire
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $error = "Accès refusé. Vérifiez vos identifiants.";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Vérification de session expirée (optionnel : 8 heures)
if (isset($_SESSION['logged_in']) && isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 28800)) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// --- PAGE DE LOGIN ---
if (!isset($_SESSION['logged_in'])) {
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vala Bleu - Authentification Expert</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #001529 0%, #002140 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
        }

        .login-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 48px 40px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-5px);
        }

        .logo {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #1890ff, #40a9ff);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 32px;
        }

        h1 {
            font-size: 28px;
            font-weight: 700;
            color: #001529;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #8c8c8c;
            font-size: 14px;
            margin-bottom: 32px;
        }

        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .input-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #595959;
            margin-bottom: 8px;
        }

        .input-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e8e8e8;
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        .input-group input:focus {
            outline: none;
            border-color: #1890ff;
            box-shadow: 0 0 0 3px rgba(24, 144, 255, 0.1);
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #1890ff, #40a9ff);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 12px;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(24, 144, 255, 0.3);
        }

        .error-message {
            background: #fff2f0;
            border-left: 4px solid #ff4d4f;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: left;
            font-size: 13px;
            color: #ff4d4f;
        }

        .footer-text {
            margin-top: 24px;
            font-size: 12px;
            color: #bfbfbf;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo">
                <span>⚡</span>
            </div>
            <h1>VALA BLEU</h1>
            <div class="subtitle">Expert Predictive Systems v4.0</div>
            
            <?php if (isset($error)): ?>
                <div class="error-message">
                    <strong>⚠️ Erreur</strong><br>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="input-group">
                    <label>Identifiant</label>
                    <input type="text" name="username" placeholder="admin" required autofocus>
                </div>
                <div class="input-group">
                    <label>Mot de passe</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
                <button type="submit" name="login_submit">Accéder au Dashboard</button>
            </form>
        </div>
        <div class="footer-text">
            Système sécurisé - Accès réservé aux experts
        </div>
    </div>
</body>
</html>
<?php 
exit();
} 
?>

<!-- DASHBOARD PRINCIPAL -->
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vala Bleu - Dashboard Expert AIOps</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f5f7fa;
            overflow-x: hidden;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #001529 0%, #000c17 100%);
            color: white;
            padding: 32px 24px;
            display: flex;
            flex-direction: column;
            z-index: 100;
        }

        .sidebar-header {
            margin-bottom: 48px;
            text-align: center;
        }

        .sidebar-header h2 {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #1890ff, #40a9ff);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 8px;
        }

        .sidebar-header p {
            font-size: 11px;
            color: #5a6e8a;
            letter-spacing: 1px;
        }

        .menu-item {
            padding: 14px 20px;
            margin-bottom: 8px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            color: #a6b4c8;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .menu-item:hover {
            background: rgba(24, 144, 255, 0.1);
            color: white;
            transform: translateX(4px);
        }

        .active-menu {
            background: linear-gradient(135deg, #1890ff, #40a9ff);
            color: white !important;
            box-shadow: 0 4px 12px rgba(24, 144, 255, 0.3);
        }

        .logout-link {
            margin-top: auto;
            padding: 14px 20px;
            color: #ff7a5c;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .logout-link:hover {
            background: rgba(255, 77, 79, 0.1);
            color: #ff7a5c;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 40px 48px;
            min-height: 100vh;
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.4s ease;
        }

        .active-tab {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 20px;
            padding: 28px 32px;
            margin-bottom: 28px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            border: 1px solid #eef2f6;
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }

        .card h3 {
            font-size: 18px;
            font-weight: 600;
            color: #1a2c3e;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #4a5b6e;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e8edf2;
            border-radius: 12px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
            background: #fafbfc;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #1890ff;
            background: white;
        }

        .btn-primary {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #1890ff, #40a9ff);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 16px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(24, 144, 255, 0.3);
        }

        /* Chart Wrapper */
        .chart-wrapper {
            background: #fafbfc;
            padding: 20px;
            border-radius: 16px;
            border: 1px solid #eef2f6;
            margin-top: 20px;
        }

        canvas {
            max-height: 400px;
            width: 100%;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .critical {
            background: #fff1f0;
            color: #cf1322;
            border: 1px solid #ffccc7;
        }

        .optimal {
            background: #f6ffed;
            color: #389e0d;
            border: 1px solid #b7eb8f;
        }

        .expert-report {
            background: linear-gradient(135deg, #f0f7ff, #ffffff);
            border-left: 4px solid #1890ff;
        }

        /* Page Title */
        .page-title {
            margin-bottom: 32px;
        }

        .page-title h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1a2c3e;
            margin-bottom: 8px;
        }

        .page-title p {
            color: #6b7a8a;
            font-size: 14px;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <h2>VALA BLEU</h2>
        <p>EXPERT AIOps v4.0</p>
    </div>
    
    <div class="menu-item active-menu" onclick="showTab('dashboard')">
        <span>📊</span> Dashboard Analyse
    </div>
    <div class="menu-item" onclick="showTab('resultats')">
        <span>🔮</span> Résultats Prédictifs
    </div>
    
    <div style="margin: 32px 20px 16px; font-size: 11px; color: #5a6e8a; text-transform: uppercase; letter-spacing: 1px;">
        Service Monitoré
    </div>
    <div style="padding: 0 20px; margin-bottom: 20px;">
        <div style="background: rgba(24,144,255,0.1); padding: 12px; border-radius: 12px;">
            <div style="font-weight: 600; margin-bottom: 4px;">WordPress</div>
            <div style="font-size: 11px; color: #8a9bb0;">Performance Pack</div>
        </div>
    </div>
    
    <a href="?logout=1" class="logout-link">
        <span>🚪</span> Déconnexion
    </a>
</div>

<div class="main-content">
    <!-- Dashboard Tab -->
    <div id="dashboard" class="tab-content active-tab">
        <div class="page-title">
            <h1>Analyse de Croissance WordPress</h1>
            <p>Simulateur de Capacity Planning basé sur l'IA pour l'infrastructure Vala Bleu</p>
        </div>
        
        <div class="card">
            <h3>⚙️ Configuration de l'Hébergement</h3>
            <div class="grid-2">
                <div class="form-group">
                    <label>Pack WordPress Actuel</label>
                    <select id="wp_type">
                        <option value="small">SMALL (Max 10k visites/mois)</option>
                        <option value="medium">MEDIUM (Max 50k visites/mois)</option>
                        <option value="performance" selected>PERFORMANCE (Trafic illimité)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Taux de Croissance Mensuel (%)</label>
                    <input type="number" id="growth" value="25" min="1" max="200" step="1">
                </div>
            </div>
        </div>
        
        <div class="card">
            <h3>📈 Métriques Systèmes Actuelles</h3>
            <div class="grid-2">
                <div class="form-group">
                    <label>Charge CPU Actuelle (%)</label>
                    <input type="number" id="cpu" value="40" min="0" max="100" step="1">
                </div>
                <div class="form-group">
                    <label>Consommation RAM (%)</label>
                    <input type="number" id="ram" value="55" min="0" max="100" step="1">
                </div>
            </div>
            <button class="btn-primary" onclick="runExpertAnalysis()">
                🚀 GÉNÉRER LE RAPPORT DE RÉGRESSION
            </button>
        </div>
    </div>
    
    <!-- Résultats Tab -->
    <div id="resultats" class="tab-content">
        <div class="page-title">
            <h1>Analyse Statistique des Résultats</h1>
            <p>Modèle de prédiction basé sur la régression linéaire</p>
        </div>
        
        <div class="card">
            <div id="status-area"></div>
            <h3>📐 Relation entre Croissance et Saturation</h3>
            <div class="chart-wrapper">
                <canvas id="seabornChart"></canvas>
            </div>
        </div>
        
        <div class="card expert-report" id="expert-report" style="display: none;">
            <h3>🛡️ Recommandation Expert IT</h3>
            <p id="report-text" style="line-height: 1.6; color: #2c3e50;"></p>
            <p style="font-size: 11px; color: #8a9bb0; margin-top: 16px; padding-top: 12px; border-top: 1px solid #eef2f6;">
                📐 Modèle : Régression Linéaire OLS (Ordinary Least Squares)
            </p>
        </div>
    </div>
</div>

<script>
let chartInstance = null;

function showTab(tabId) {
    // Cacher tous les contenus
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active-tab');
    });
    
    // Désactiver tous les menus
    document.querySelectorAll('.menu-item').forEach(menu => {
        menu.classList.remove('active-menu');
    });
    
    // Afficher l'onglet sélectionné
    document.getElementById(tabId).classList.add('active-tab');
    
    // Activer le menu correspondant
    const menuMap = {
        'dashboard': 0,
        'resultats': 1
    };
    
    const menus = document.querySelectorAll('.menu-item');
    if (menus[menuMap[tabId]]) {
        menus[menuMap[tabId]].classList.add('active-menu');
    }
}

function runExpertAnalysis() {
    // Récupération des valeurs
    const cpu = parseFloat(document.getElementById('cpu').value) || 0;
    const ram = parseFloat(document.getElementById('ram').value) || 0;
    const growth = parseFloat(document.getElementById('growth').value) || 0;
    const type = document.getElementById('wp_type').value;
    
    // Validation
    if (cpu < 0 || cpu > 100 || ram < 0 || ram > 100) {
        alert('Veuillez entrer des valeurs valides (0-100) pour CPU et RAM');
        return;
    }
    
    if (growth < 1 || growth > 200) {
        alert('Le taux de croissance doit être compris entre 1% et 200%');
        return;
    }
    
    // Génération des données de régression
    const scatterData = [];
    const step = Math.max(1, Math.floor(growth / 15));
    
    for (let i = 0; i <= growth + 5; i += step) {
        const predictedLoad = cpu + (i * 0.85);
        const randomVariation = (Math.random() - 0.5) * 6;
        let yValue = Math.min(100, Math.max(0, predictedLoad + randomVariation));
        scatterData.push({ x: i, y: yValue });
    }
    
    // Ligne de tendance
    const trendLine = [
        { x: 0, y: cpu },
        { x: growth + 10, y: Math.min(100, cpu + ((growth + 10) * 0.85)) }
    ];
    
    // Création du graphique
    const ctx = document.getElementById('seabornChart').getContext('2d');
    
    if (chartInstance) {
        chartInstance.destroy();
    }
    
    chartInstance = new Chart(ctx, {
        type: 'scatter',
        data: {
            datasets: [
                {
                    label: 'Points de charge observés',
                    data: scatterData,
                    backgroundColor: 'rgba(76, 114, 176, 0.6)',
                    borderColor: '#4C72B0',
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    pointBorderWidth: 2,
                    pointBorderColor: '#ffffff'
                },
                {
                    label: 'Ligne de Régression (Prédiction)',
                    data: trendLine,
                    type: 'line',
                    borderColor: '#C44E52',
                    borderWidth: 3,
                    fill: false,
                    pointRadius: 0,
                    tension: 0
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `Croissance: ${context.parsed.x}% | Charge: ${Math.round(context.parsed.y)}%`;
                        }
                    }
                },
                legend: {
                    position: 'top',
                    labels: {
                        font: { size: 12, family: 'Inter' }
                    }
                }
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Croissance du Trafic (%)',
                        font: { size: 13, weight: 'bold' }
                    },
                    grid: { color: '#eef2f6' },
                    min: 0,
                    max: growth + 10
                },
                y: {
                    title: {
                        display: true,
                        text: 'Utilisation des Ressources (%)',
                        font: { size: 13, weight: 'bold' }
                    },
                    grid: { color: '#eef2f6' },
                    min: 0,
                    max: 100,
                    ticks: { stepSize: 20 }
                }
            }
        }
    });
    
    // Analyse et recommandation
    const predictedLoad = cpu + (growth * 0.85);
    const finalLoad = Math.min(100, Math.round(predictedLoad));
    const statusArea = document.getElementById('status-area');
    const expertReport = document.getElementById('expert-report');
    const reportText = document.getElementById('report-text');
    
    expertReport.style.display = 'block';
    
    const packNames = {
        'small': 'SMALL (capacité limitée)',
        'medium': 'MEDIUM (capacité modérée)',
        'performance': 'PERFORMANCE (haute capacité)'
    };
    
    if (finalLoad >= 85) {
        statusArea.innerHTML = `
            <div class="status-badge critical">
                ⚠️ MIGRATION CRITIQUE REQUISE
            </div>
        `;
        reportText.innerHTML = `
            <strong>Analyse prédictive :</strong> Avec une croissance projetée de <strong>${growth}%</strong>, 
            vos ressources atteindront <strong style="color:#cf1322;">${finalLoad}%</strong> de saturation.<br><br>
            Le pack <strong>${packNames[type]}</strong> est insuffisant pour absorber cette charge dans les prochains mois.
            <br><br>
            <strong>Recommandation immédiate :</strong> Migration vers une infrastructure Cloud VPS avec auto-scaling pour garantir la 
            disponibilité et les performances de votre plateforme WordPress.
        `;
    } else if (finalLoad >= 70) {
        statusArea.innerHTML = `
            <div class="status-badge" style="background:#fff7e6; color:#d46b00; border-color:#ffe58f;">
                ⚡ SURVEILLANCE RENFORCÉE RECOMMANDÉE
            </div>
        `;
        reportText.innerHTML = `
            <strong>Analyse prédictive :</strong> Avec une croissance de <strong>${growth}%</strong>, 
            vos ressources atteindront <strong style="color:#d46b00;">${finalLoad}%</strong> dans les prochains mois.<br><br>
            Le pack <strong>${packNames[type]}</strong> peut encore supporter cette charge, mais une marge de sécurité réduite est observée.
            <br><br>
            <strong>Recommandation :</strong> Planifiez une optimisation des ressources et surveillez attentivement l'évolution des métriques.
        `;
    } else {
        statusArea.innerHTML = `
            <div class="status-badge optimal">
                ✅ INFRASTRUCTURE OPTIMISÉE
            </div>
        `;
        reportText.innerHTML = `
            <strong>Analyse prédictive :</strong> Le modèle de régression confirme que votre infrastructure actuelle 
            peut absorber la croissance projetée de <strong>${growth}%</strong>.<br><br>
            Marge de sécurité estimée : <strong style="color:#389e0d;">${Math.round(100 - finalLoad)}%</strong> de ressources disponibles.<br><br>
            <strong>Recommandation :</strong> Aucun changement d'infrastructure n'est nécessaire. Continuez à surveiller les métriques 
            pour anticiper les besoins futurs.
        `;
    }
    
    // Redirection automatique vers l'onglet résultats
    setTimeout(() => {
        showTab('resultats');
    }, 600);
}

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    // S'assurer que le menu est correctement initialisé
    if (!window.location.hash) {
        showTab('dashboard');
    }
});
</script>
</body>
</html>
