<?php
session_start();

// Configuration de sécurité
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'vala2026');

// --- BASE DE DONNÉES SQLITE ---
$db_file = 'vala_bleu.db';
$pdo = null;

try {
    $pdo = new PDO("sqlite:" . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Création de la table des prédictions si elle n'existe pas
    $pdo->exec("CREATE TABLE IF NOT EXISTS predictions (
        id TEXT PRIMARY KEY,
        created_at TEXT,
        cpu_usage TEXT,
        ram_usage TEXT,
        growth_rate TEXT,
        clients_initiaux TEXT,
        clients_actuels TEXT,
        wp_type TEXT,
        predicted_load TEXT,
        months_until_saturation TEXT,
        status TEXT,
        recommendation TEXT,
        is_deleted INTEGER DEFAULT 0
    )");
    
    // Création de la table des sauvegardes supprimées (corbeille)
    $pdo->exec("CREATE TABLE IF NOT EXISTS deleted_sauvegardes (
        id TEXT PRIMARY KEY,
        created_at TEXT,
        cpu_usage TEXT,
        ram_usage TEXT,
        growth_rate TEXT,
        clients_initiaux TEXT,
        clients_actuels TEXT,
        wp_type TEXT,
        predicted_load TEXT,
        months_until_saturation TEXT,
        status TEXT,
        recommendation TEXT,
        deleted_at TEXT
    )");
} catch (Exception $e) {
    error_log("SQLite error: " . $e->getMessage());
}

// --- GESTION DE L'AUTHENTIFICATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
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

if (isset($_SESSION['logged_in']) && isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 28800)) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// --- FONCTIONS DE PERSISTANCE DES DONNÉES ---
function getPredictions($pdo) {
    if ($pdo === null) {
        return isset($_SESSION['predictions']) ? $_SESSION['predictions'] : [];
    }
    try {
        $stmt = $pdo->query("SELECT * FROM predictions WHERE is_deleted = 0 ORDER BY created_at DESC");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $results;
    } catch (Exception $e) {
        return isset($_SESSION['predictions']) ? $_SESSION['predictions'] : [];
    }
}

function getDeletedSauvegardes($pdo) {
    if ($pdo === null) {
        return isset($_SESSION['deleted_sauvegardes']) ? $_SESSION['deleted_sauvegardes'] : [];
    }
    try {
        $stmt = $pdo->query("SELECT * FROM deleted_sauvegardes ORDER BY deleted_at DESC");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $results;
    } catch (Exception $e) {
        return isset($_SESSION['deleted_sauvegardes']) ? $_SESSION['deleted_sauvegardes'] : [];
    }
}

function savePrediction($pdo, $data) {
    if ($pdo === null) {
        if (!isset($_SESSION['predictions'])) $_SESSION['predictions'] = [];
        array_unshift($_SESSION['predictions'], $data);
        $_SESSION['predictions'] = array_slice($_SESSION['predictions'], 0, 50);
        return true;
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO predictions (id, created_at, cpu_usage, ram_usage, growth_rate, clients_initiaux, clients_actuels, wp_type, predicted_load, months_until_saturation, status, recommendation, is_deleted) 
            VALUES (:id, :created_at, :cpu_usage, :ram_usage, :growth_rate, :clients_initiaux, :clients_actuels, :wp_type, :predicted_load, :months_until_saturation, :status, :recommendation, 0)");
        $stmt->execute([
            ':id' => $data['id'],
            ':created_at' => $data['created_at'],
            ':cpu_usage' => $data['cpu_usage'],
            ':ram_usage' => $data['ram_usage'],
            ':growth_rate' => $data['growth_rate'],
            ':clients_initiaux' => $data['clients_initiaux'],
            ':clients_actuels' => $data['clients_actuels'],
            ':wp_type' => $data['wp_type'],
            ':predicted_load' => $data['predicted_load'],
            ':months_until_saturation' => $data['months_until_saturation'],
            ':status' => $data['status'],
            ':recommendation' => $data['recommendation']
        ]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function archivePrediction($pdo, $id) {
    if ($pdo === null) {
        foreach ($_SESSION['predictions'] as $key => $item) {
            if ($item['id'] === $id) {
                array_unshift($_SESSION['deleted_sauvegardes'], $item);
                array_splice($_SESSION['predictions'], $key, 1);
                return true;
            }
        }
        return false;
    }
    try {
        $stmt = $pdo->prepare("SELECT * FROM predictions WHERE id = :id AND is_deleted = 0");
        $stmt->execute([':id' => $id]);
        $pred = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pred) {
            $stmt2 = $pdo->prepare("INSERT INTO deleted_sauvegardes (id, created_at, cpu_usage, ram_usage, growth_rate, clients_initiaux, clients_actuels, wp_type, predicted_load, months_until_saturation, status, recommendation, deleted_at) 
                VALUES (:id, :created_at, :cpu_usage, :ram_usage, :growth_rate, :clients_initiaux, :clients_actuels, :wp_type, :predicted_load, :months_until_saturation, :status, :recommendation, :deleted_at)");
            $stmt2->execute([
                ':id' => $pred['id'],
                ':created_at' => $pred['created_at'],
                ':cpu_usage' => $pred['cpu_usage'],
                ':ram_usage' => $pred['ram_usage'],
                ':growth_rate' => $pred['growth_rate'],
                ':clients_initiaux' => $pred['clients_initiaux'],
                ':clients_actuels' => $pred['clients_actuels'],
                ':wp_type' => $pred['wp_type'],
                ':predicted_load' => $pred['predicted_load'],
                ':months_until_saturation' => $pred['months_until_saturation'],
                ':status' => $pred['status'],
                ':recommendation' => $pred['recommendation'],
                ':deleted_at' => date('Y-m-d H:i:s')
            ]);
            
            $stmt3 = $pdo->prepare("DELETE FROM predictions WHERE id = :id");
            $stmt3->execute([':id' => $id]);
            return true;
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}

function deletePermanently($pdo, $id) {
    if ($pdo === null) {
        foreach ($_SESSION['deleted_sauvegardes'] as $key => $item) {
            if ($item['id'] === $id) {
                array_splice($_SESSION['deleted_sauvegardes'], $key, 1);
                return true;
            }
        }
        return false;
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM deleted_sauvegardes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// --- SAUVEGARDE D'UNE PRÉDICTION (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($data && isset($_SESSION['logged_in'])) {
        if (isset($data['action']) && $data['action'] === 'delete' && isset($data['delete_id'])) {
            $success = deletePermanently($pdo, $data['delete_id']);
            echo json_encode(['success' => $success]);
            exit();
        }
        
        if (isset($data['action']) && $data['action'] === 'archive' && isset($data['archive_id'])) {
            $success = archivePrediction($pdo, $data['archive_id']);
            echo json_encode(['success' => $success]);
            exit();
        }
        
        $prediction = [
            'id' => uniqid(),
            'created_at' => date('Y-m-d H:i:s'),
            'cpu_usage' => $data['cpu'],
            'ram_usage' => $data['ram'],
            'growth_rate' => $data['growth'],
            'clients_initiaux' => $data['clients_initiaux'],
            'clients_actuels' => $data['clients_actuels'],
            'wp_type' => $data['wp_type'],
            'predicted_load' => $data['predicted_load'],
            'months_until_saturation' => $data['months_until_saturation'],
            'status' => $data['status'],
            'recommendation' => $data['recommendation']
        ];
        
        $success = savePrediction($pdo, $prediction);
        echo json_encode(['success' => $success]);
        exit();
    }
    echo json_encode(['success' => false]);
    exit();
}

$history_predictions = getPredictions($pdo);
$deleted_sauvegardes = getDeletedSauvegardes($pdo);
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : (isset($_SESSION['last_tab']) ? $_SESSION['last_tab'] : 'dashboard');
$_SESSION['last_tab'] = $active_tab;

// --- PAGE DE LOGIN ---
if (!isset($_SESSION['logged_in'])) {
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vala Bleu - Authentification Expert</title>
	<link rel="icon" type="image/x-icon" href="vala-svgrepo-com.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #001529 0%, #002140 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container { width: 100%; max-width: 420px; }
        .login-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 48px 40px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
            transition: transform 0.3s ease;
        }
        .login-card:hover { transform: translateY(-5px); }
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
        h1 { font-size: 28px; font-weight: 700; color: #001529; margin-bottom: 8px; }
        .subtitle { color: #8c8c8c; font-size: 14px; margin-bottom: 32px; }
        .input-group { margin-bottom: 20px; text-align: left; }
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
        .footer-text { margin-top: 24px; font-size: 12px; color: #bfbfbf; }
        .info-persist {
            margin-top: 16px;
            font-size: 11px;
            color: #52c41a;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo"><span>⚡</span></div>
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
                    <input type="text" name="username">
                </div>
                <div class="input-group">
                    <label>Mot de passe</label>
                    <input type="password" name="password">
                </div>
                <button type="submit" name="login_submit">Accéder au Dashboard</button>
            </form>
            <div class="info-persist">
                💾 Les données sont sauvegardées et persistent après déconnexion
            </div>
        </div>
        <div class="footer-text">Système sécurisé - Stockage permanent SQLite</div>
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f7fa;
            overflow-x: hidden;
        }
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #001529 0%, #000c17 100%);
            color: white;
            padding: 32px 20px;
            display: flex;
            flex-direction: column;
            z-index: 100;
        }
        .sidebar-header { margin-bottom: 32px; text-align: center; }
        .sidebar-header h2 {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #1890ff, #40a9ff);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 8px;
        }
        .sidebar-header p { font-size: 11px; color: #5a6e8a; letter-spacing: 1px; }
        
        .menu-container {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 32px;
        }
        .menu-item {
            padding: 12px 16px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 14px;
            color: #a6b4c8;
            background: rgba(255,255,255,0.05);
        }
        .menu-item:hover {
            background: rgba(24, 144, 255, 0.2);
            color: white;
        }
        .active-menu {
            background: linear-gradient(135deg, #1890ff, #40a9ff);
            color: white !important;
            box-shadow: 0 4px 12px rgba(24, 144, 255, 0.3);
        }
        .logout-link {
            margin-top: auto;
            padding: 12px 16px;
            color: #ff7a5c;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            font-size: 14px;
            background: rgba(255,77,79,0.1);
        }
        .logout-link:hover {
            background: rgba(255, 77, 79, 0.2);
            color: #ff7a5c;
        }
        .main-content {
            margin-left: 280px;
            padding: 40px 48px;
            min-height: 100vh;
            width: calc(100% - 280px);
        }
        .tab-content { 
            display: none; 
            animation: fadeIn 0.4s ease;
            width: 100%;
        }
        .active-tab { 
            display: block;
            width: 100%;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .card {
            background: white;
            border-radius: 20px;
            padding: 28px 32px;
            margin-bottom: 28px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            border: 1px solid #eef2f6;
            transition: all 0.3s ease;
            width: 100%;
        }
        .card:hover { box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); }
        .card h3 {
            font-size: 18px;
            font-weight: 600;
            color: #1a2c3e;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #4a5b6e;
            margin-bottom: 8px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e8edf2;
            border-radius: 12px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
            background: #fafbfc;
        }
        .form-group input:focus, .form-group select:focus {
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
        .btn-save {
            background: linear-gradient(135deg, #52c41a, #73d13d);
            margin-top: 0;
        }
        .btn-save:hover {
            box-shadow: 0 8px 20px rgba(82, 196, 26, 0.3);
        }
        .btn-delete-red {
            background: #e84118;
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            transition: 0.2s;
            font-size: 0.8rem;
        }
        .btn-delete-red:hover {
            background: #bf2e0b;
            transform: scale(1.02);
        }
        .btn-archive {
            background: #6c757d;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.8rem;
            transition: 0.2s;
        }
        .btn-archive:hover {
            background: #5a6268;
        }
        .chart-wrapper {
            background: #fafbfc;
            padding: 20px;
            border-radius: 16px;
            border: 1px solid #eef2f6;
            margin-top: 20px;
        }
        canvas { max-height: 450px; width: 100%; }
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
        .warning {
            background: #fff7e6;
            color: #d46b00;
            border: 1px solid #ffd591;
        }
        .expert-report {
            background: linear-gradient(135deg, #f0f7ff, #ffffff);
            border-left: 4px solid #1890ff;
        }
        .page-title { margin-bottom: 32px; }
        .page-title h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1a2c3e;
            margin-bottom: 8px;
        }
        .page-title p { color: #6b7a8a; font-size: 14px; }
        .threshold-legend {
            display: flex;
            align-items: center;
            gap: 24px;
            margin-top: 16px;
            padding: 12px 16px;
            background: #f8f9fa;
            border-radius: 12px;
            font-size: 12px;
            flex-wrap: wrap;
        }
        .threshold-line { display: inline-flex; align-items: center; gap: 8px; }
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        .history-table th, .history-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eef2f6;
        }
        .history-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #1a2c3e;
        }
        .history-table tr:hover { background: #fafbfc; }
        .badge-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-critical { background: #fff1f0; color: #cf1322; }
        .badge-warning { background: #fff7e6; color: #d46b00; }
        .badge-optimal { background: #f6ffed; color: #389e0d; }
        
        .toast-notification {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #52c41a;
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            z-index: 1000;
            animation: slideIn 0.3s ease;
            display: none;
        }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .error-message-box {
            background: #fff2f0;
            border-left: 4px solid #ff4d4f;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            color: #cf1322;
            font-weight: 500;
        }
        
        .growth-result {
            background: #e6f7ff;
            border: 1px solid #91d5ff;
            padding: 16px;
            border-radius: 12px;
            margin-top: 16px;
        }
        .growth-result strong {
            color: #1890ff;
            font-size: 20px;
        }
        
        .action-col {
            text-align: center;
            white-space: nowrap;
        }
        
        .warning-text {
            color: #d46b00;
            font-size: 11px;
            margin-top: 4px;
        }
        
        .value-error {
            border-color: #ff4d4f !important;
            background: #fff2f0 !important;
        }
        
        .service-info {
            margin: 20px 0 16px;
            padding: 12px;
            background: rgba(24,144,255,0.1);
            border-radius: 12px;
        }
        .service-info div:first-child {
            font-weight: 600;
            margin-bottom: 4px;
        }
        .service-info div:last-child {
            font-size: 11px;
            color: #8a9bb0;
        }
        
        .required-field {
            color: #ff4d4f;
            margin-left: 4px;
        }
        
        .prediction-message {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #1890ff;
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            z-index: 1001;
            animation: slideDown 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        @keyframes slideDown {
            from { transform: translateY(-100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .gauge-container {
            background: #f0f0f0;
            border-radius: 20px;
            height: 12px;
            margin: 15px 0;
            overflow: hidden;
        }
        .gauge-fill {
            height: 100%;
            border-radius: 20px;
            transition: width 0.5s ease;
        }
        .gauge-fill.critical { background: linear-gradient(90deg, #ff4d4f, #cf1322); }
        .gauge-fill.warning { background: linear-gradient(90deg, #faad14, #d46b00); }
        .gauge-fill.optimal { background: linear-gradient(90deg, #52c41a, #389e0d); }
        
        .saturation-card {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 20px;
            margin-top: 20px;
            border-left: 4px solid;
        }
        .saturation-card.urgent { border-left-color: #cf1322; background: #fff1f0; }
        .saturation-card.warning { border-left-color: #d46b00; background: #fff7e6; }
        .saturation-card.safe { border-left-color: #389e0d; background: #f6ffed; }
        
        .months-counter {
            font-size: 32px;
            font-weight: 700;
            display: inline-block;
            margin-right: 10px;
        }
        .months-label {
            font-size: 14px;
            color: #6b7a8a;
        }
        
        .persist-info {
            background: #e6f7ff;
            border: 1px solid #91d5ff;
            border-radius: 12px;
            padding: 10px 16px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #1890ff;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <h2>VALA BLEU</h2>
        <p>EXPERT AIOps v4.0</p>
    </div>
    
    <div class="menu-container">
        <div class="menu-item <?php echo $active_tab == 'dashboard' ? 'active-menu' : ''; ?>" onclick="showTab('dashboard')">📊 Saisie des paramètres</div>
        <div class="menu-item <?php echo $active_tab == 'resultats' ? 'active-menu' : ''; ?>" onclick="showTab('resultats')">🔮 Résultats Prédictifs</div>
        <div class="menu-item <?php echo $active_tab == 'sauvegarde' ? 'active-menu' : ''; ?>" onclick="showTab('sauvegarde')">💾 Sauvegarde</div>
        <div class="menu-item <?php echo $active_tab == 'historique' ? 'active-menu' : ''; ?>" onclick="showTab('historique')">📜 Historique</div>
        <div class="menu-item <?php echo $active_tab == 'supprimee' ? 'active-menu' : ''; ?>" onclick="showTab('supprimee')">🗑️ Corbeille</div>
    </div>
    
    <div class="service-info">
        <div>🔍 SERVICE MONITORÉ</div>
        <div>WordPress Performance Pack</div>
    </div>
    
    <a href="?logout=1" class="logout-link">
        <span>🚪</span> Déconnexion
    </a>
</div>

<div class="main-content">
    <!-- Dashboard Tab -->
    <div id="dashboard" class="tab-content <?php echo $active_tab == 'dashboard' ? 'active-tab' : ''; ?>">
        <div class="page-title">
            <h1>Analyse de Croissance WordPress</h1>
            <p>Simulateur de Capacity Planning basé sur l'IA pour l'infrastructure Vala Bleu</p>
        </div>
        
        <div class="persist-info">
            💾 Toutes vos analyses sont sauvegardées automatiquement et persistent après déconnexion (base SQLite)
        </div>
        
        <div class="card">
            <h3>⚙️ Configuration de l'Hébergement</h3>
            <div class="grid-2">
                <div class="form-group">
                    <label>Pack WordPress Actuel <span class="required-field">*</span></label>
                    <select id="wp_type" onchange="updateClientLimit()">
                        <option value="" selected disabled>-- Choisissez un pack --</option>
                        <option value="small">SMALL (Max 10k visites/mois)</option>
                        <option value="medium">MEDIUM (Max 50k visites/mois)</option>
                        <option value="performance">PERFORMANCE (Trafic illimité)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Taux de Croissance Mensuel (%) <span class="required-field">*</span></label>
                    <input type="number" id="growth" step="1" placeholder="Ex: 20">
                    <div class="warning-text">Exemple: +70% = croissance rapide</div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h3>📊 Calcul du taux de croissance client (optionnel)</h3>
            <div class="grid-3">
                <div class="form-group">
                    <label>Nombre de clients initiaux</label>
                    <input type="number" id="clients_initiaux" step="100" placeholder="Optionnel" oninput="calculerTauxCroissance()">
					<div id="error-message" style="color: #ff4d4f; font-size: 12px; margin-top: 5px; display: none;">
						⚠️ Le nombre de clients ne peut pas être négatif.
					</div>
                </div>
                <div class="form-group">
                    <label>Nombre de clients actuels</label>
                    <input type="number" id="clients_actuels" step="100" placeholder="Optionnel" oninput="calculerTauxCroissance(); checkClientLimit()">
                    <div class="warning-text" id="clientLimitWarning"></div>
                </div>
                <div class="form-group">
                    <label>Taux de croissance calculé (%)</label>
                    <input type="text" id="taux_calcule" readonly style="background: #f0f0f0; font-weight: bold; color: #1890ff;">
                </div>
            </div>
            <div id="growth-info" class="growth-result" style="display: none;">
                📈 <strong>Taux de croissance calculé : <span id="display_taux">0</span>%</strong><br>
                <small>Formule : ((Clients actuels - Clients initiaux) / Clients initiaux) × 100</small>
            </div>
        </div>
        
        <div class="card">
            <h3>📈 Métriques Systèmes Actuelles</h3>
            <div class="grid-2">
                <div class="form-group">
                    <label>Charge CPU Actuelle (%) <span class="required-field">*</span></label>
                    <input type="number" id="cpu" placeholder="Ex: 70" min="0" max="100" step="1" oninput="validateCpuRam(this)">
                    <div class="warning-text">ne dépasser pas 100%</div>
                </div>
                <div class="form-group">
                    <label>Consommation RAM (%) <span class="required-field">*</span></label>
                    <input type="number" id="ram" placeholder="Ex: 65" min="0" max="100" step="1" oninput="validateCpuRam(this)">
                    <div class="warning-text">ne dépasser pas 100%</div>
                </div>
            </div>
            <button class="btn-primary" onclick="runExpertAnalysis()">
                🚀 GÉNÉRER LE RAPPORT DE RÉGRESSION
            </button>
        </div>
    </div>
    
    <!-- Résultats Tab -->
    <div id="resultats" class="tab-content <?php echo $active_tab == 'resultats' ? 'active-tab' : ''; ?>">
        <div class="page-title">
            <h1>Analyse Statistique des Résultats</h1>
            <p>Modèle de prédiction basé sur la régression linéaire</p>
        </div>
        
        <div id="no-data-message" class="error-message-box" style="display: none;">
            ⚠️ Aucune analyse disponible. Veuillez d'abord saisir les données dans l'onglet "Dashboard Analyse" et cliquer sur "GÉNÉRER LE RAPPORT".
        </div>
        
        <div id="results-content" style="display: none;">
            <div class="card">
                <div id="status-area"></div>
                <h3>📐 Relation entre Croissance et Saturation</h3>
                <div class="chart-wrapper">
                    <canvas id="seabornChart"></canvas>
                </div>
                <div class="threshold-legend">
                    <div class="threshold-line">
                        <div style="width: 30px; height: 3px; background: none; border-bottom: 3px dashed #ff4d4f;"></div>
                        <span><strong>Seuil critique (80%)</strong> - Zone d'alerte</span>
                    </div>
                    <div class="threshold-line">
                        <div style="width: 30px; height: 3px; background: #C44E52; border-radius: 2px;"></div>
                        <span><strong>Ligne de régression</strong> - Prédiction linéaire</span>
                    </div>
                    <div class="threshold-line">
                        <div style="width: 12px; height: 12px; background: #4C72B0; border-radius: 50%;"></div>
                        <span><strong>Points observés</strong> - Données simulées</span>
                    </div>
                </div>
            </div>
            
            <div class="card" id="temporel-card">
                <h3>⏰ Prédiction Temporelle</h3>
                <div id="temporel-content">
                    <!-- Rempli par JS -->
                </div>
            </div>
            
            <div class="card expert-report" id="expert-report">
                <h3>🛡️ Recommandation Expert IT</h3>
                <p id="report-text" style="line-height: 1.6; color: #2c3e50;"></p>
                <p style="font-size: 11px; color: #8a9bb0; margin-top: 16px; padding-top: 12px; border-top: 1px solid #eef2f6;">
                    📐 Modèle : Régression Linéaire OLS | Seuil critique : 80% | Saturation : 90%
                </p>
            </div>
        </div>
    </div>
    
    <!-- Sauvegarde Tab -->
    <div id="sauvegarde" class="tab-content <?php echo $active_tab == 'sauvegarde' ? 'active-tab' : ''; ?>">
        <div class="page-title">
            <h1>💾 Sauvegarde des Analyses</h1>
            <p>Enregistrez vos prédictions pour les retrouver dans l'historique</p>
        </div>
        
        <div class="card">
            <h3>📋 Dernière analyse effectuée</h3>
            <div id="last-analysis-info" style="background: #f8f9fa; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
                <p style="color: #8a9bb0;">Aucune analyse générée. Lancez d'abord une prédiction dans l'onglet Dashboard.</p>
            </div>
            <button class="btn-primary btn-save" id="saveAnalysisBtn" onclick="saveCurrentAnalysis()" disabled>
                💾 Sauvegarder cette analyse
            </button>
        </div>
        
        <div class="card">
            <h3>ℹ️ Informations</h3>
            <p style="color: #6b7a8a; line-height: 1.6;">
                <strong>📌 Comment ça marche ?</strong><br><br>
                1. Allez dans l'onglet <strong>Dashboard Analyse</strong><br>
                2. Configurez vos paramètres (croissance, CPU, RAM)<br>
                3. Cliquez sur <strong>"GÉNÉRER LE RAPPORT"</strong><br>
                4. Revenez ici et cliquez sur <strong>"Sauvegarder"</strong><br><br>
                ✅ Toutes vos analyses sauvegardées seront disponibles dans l'onglet <strong>Historique</strong>.<br>
                🗑️ Pour supprimer une analyse, allez dans l'onglet <strong>Corbeille</strong>.<br><br>
                💾 <strong>Persistance :</strong> Les données sont stockées dans une base SQLite et persistent après déconnexion.
            </p>
        </div>
    </div>
    
    <!-- Historique Tab -->
    <div id="historique" class="tab-content <?php echo $active_tab == 'historique' ? 'active-tab' : ''; ?>">
        <div class="page-title">
            <h1>📜 Historique des Analyses</h1>
            <p>Consultez toutes vos prédictions précédentes sauvegardées</p>
        </div>
        
        <div class="card">
            <h3>🗂️ Dernières analyses effectuées</h3>
            <div id="historique-container">
                <?php if (count($history_predictions) > 0): ?>
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Clients (init/act)</th>
                                <th>Taux (%)</th>
                                <th>CPU/RAM</th>
                                <th>Pack</th>
                                <th>Charge prédite</th>
                                <th>Saturation (mois)</th>
                                <th>Statut</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="historique-tbody">
                            <?php foreach ($history_predictions as $pred): ?>
                                <tr id="row-<?php echo htmlspecialchars($pred['id'] ?? ''); ?>">
                                    <td><?php echo date('d/m/Y H:i', strtotime($pred['created_at'] ?? 'now')); ?></td>
                                    <td>
                                        <?php 
                                        $clients_init = isset($pred['clients_initiaux']) ? $pred['clients_initiaux'] : 'N/A';
                                        $clients_act = isset($pred['clients_actuels']) ? $pred['clients_actuels'] : 'N/A';
                                        echo htmlspecialchars($clients_init) . ' → ' . htmlspecialchars($clients_act);
                                        ?>
                                    </td>
                                    <td><strong><?php echo isset($pred['growth_rate']) ? htmlspecialchars($pred['growth_rate']) : 'N/A'; ?>%</strong></td>
                                    <td>
                                        <?php 
                                        $cpu = isset($pred['cpu_usage']) ? $pred['cpu_usage'] : 'N/A';
                                        $ram = isset($pred['ram_usage']) ? $pred['ram_usage'] : 'N/A';
                                        echo htmlspecialchars($cpu) . '/' . htmlspecialchars($ram) . '%';
                                        ?>
                                    </td>
                                    <td><?php echo isset($pred['wp_type']) ? htmlspecialchars(strtoupper($pred['wp_type'])) : 'N/A'; ?></td>
                                    <td><strong><?php echo isset($pred['predicted_load']) ? htmlspecialchars($pred['predicted_load']) : 'N/A'; ?>%</strong></td>
                                    <td>
                                        <?php 
                                        $months = isset($pred['months_until_saturation']) ? $pred['months_until_saturation'] : 'N/A';
                                        if ($months !== 'N/A' && $months > 0 && $months < 100) {
                                            echo '<span style="color: #d46b00;">⏰ ' . $months . ' mois</span>';
                                        } elseif ($months === 0) {
                                            echo '<span style="color: #cf1322;">⚠️ Saturée</span>';
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $load = isset($pred['predicted_load']) ? $pred['predicted_load'] : 0;
                                        $status_class = 'badge-optimal';
                                        if ($load >= 80) $status_class = 'badge-critical';
                                        elseif ($load >= 70) $status_class = 'badge-warning';
                                        $status_text = isset($pred['status']) ? $pred['status'] : ($load >= 80 ? 'CRITIQUE' : ($load >= 70 ? 'SURVEILLANCE' : 'OPTIMAL'));
                                        ?>
                                        <span class="badge-status <?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($status_text); ?>
                                        </span>
                                    </td>
                                    <td class="action-col">
                                        <button class="btn-archive" onclick="archiverAnalyse('<?php echo htmlspecialchars($pred['id'] ?? ''); ?>')">📦 Archiver</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; color: #8a9bb0; padding: 40px;">
                        📭 Aucune analyse enregistrée. Utilisez l'onglet Sauvegarde pour enregistrer vos analyses.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Corbeille Tab -->
    <div id="supprimee" class="tab-content <?php echo $active_tab == 'supprimee' ? 'active-tab' : ''; ?>">
        <div class="page-title">
            <h1>🗑️ Sauvegardes Supprimées</h1>
            <p>Analyses archivées - Suppression définitive possible</p>
        </div>
        
        <div class="card">
            <h3>🗂️ Analyses dans la corbeille</h3>
            <div id="corbeille-container">
                <?php if (count($deleted_sauvegardes) > 0): ?>
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Clients (init/act)</th>
                                <th>Taux (%)</th>
                                <th>CPU/RAM</th>
                                <th>Pack</th>
                                <th>Charge prédite</th>
                                <th>Saturation (mois)</th>
                                <th>Statut</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="corbeille-tbody">
                            <?php foreach ($deleted_sauvegardes as $del): ?>
                                <tr id="deleted-<?php echo htmlspecialchars($del['id'] ?? ''); ?>">
                                    <td><?php echo date('d/m/Y H:i', strtotime($del['created_at'] ?? 'now')); ?></td>
                                    <td>
                                        <?php 
                                        $clients_init = isset($del['clients_initiaux']) ? $del['clients_initiaux'] : 'N/A';
                                        $clients_act = isset($del['clients_actuels']) ? $del['clients_actuels'] : 'N/A';
                                        echo htmlspecialchars($clients_init) . ' → ' . htmlspecialchars($clients_act);
                                        ?>
                                    </td>
                                    <td><strong><?php echo isset($del['growth_rate']) ? htmlspecialchars($del['growth_rate']) : 'N/A'; ?>%</strong></td>
                                    <td>
                                        <?php 
                                        $cpu = isset($del['cpu_usage']) ? $del['cpu_usage'] : 'N/A';
                                        $ram = isset($del['ram_usage']) ? $del['ram_usage'] : 'N/A';
                                        echo htmlspecialchars($cpu) . '/' . htmlspecialchars($ram) . '%';
                                        ?>
                                    </td>
                                    <td><?php echo isset($del['wp_type']) ? htmlspecialchars(strtoupper($del['wp_type'])) : 'N/A'; ?></td>
                                    <td><strong><?php echo isset($del['predicted_load']) ? htmlspecialchars($del['predicted_load']) : 'N/A'; ?>%</strong></td>
                                    <td>
                                        <?php 
                                        $months = isset($del['months_until_saturation']) ? $del['months_until_saturation'] : 'N/A';
                                        if ($months !== 'N/A' && $months > 0 && $months < 100) {
                                            echo '<span style="color: #d46b00;">⏰ ' . $months . ' mois</span>';
                                        } elseif ($months === 0) {
                                            echo '<span style="color: #cf1322;">⚠️ Saturée</span>';
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $load = isset($del['predicted_load']) ? $del['predicted_load'] : 0;
                                        $status_class = 'badge-optimal';
                                        if ($load >= 80) $status_class = 'badge-critical';
                                        elseif ($load >= 70) $status_class = 'badge-warning';
                                        $status_text = isset($del['status']) ? $del['status'] : ($load >= 80 ? 'CRITIQUE' : ($load >= 70 ? 'SURVEILLANCE' : 'OPTIMAL'));
                                        ?>
                                        <span class="badge-status <?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($status_text); ?>
                                        </span>
                                    </td>
                                    <td class="action-col">
                                        <button class="btn-delete-red" onclick="supprimerDefinitivement('<?php echo htmlspecialchars($del['id'] ?? ''); ?>')">🗑️ Supprimer</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; color: #8a9bb0; padding: 40px;">
                        🗑️ Aucune sauvegarde dans la corbeille. Utilisez "Archiver" dans l'historique pour déplacer des analyses ici.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="toast" class="toast-notification">✅ Action réussie !</div>

<script>
let chartInstance = null;
let lastAnalysis = null;
let analysisGenerated = false;

function showPredictionMessage() {
    const msgDiv = document.createElement('div');
    msgDiv.className = 'prediction-message';
    msgDiv.innerHTML = '✅ Prédiction terminée ! Consultez les résultats dans l\'onglet "Résultats Prédictifs"';
    document.body.appendChild(msgDiv);
    setTimeout(() => {
        msgDiv.remove();
    }, 4000);
}

function validateCpuRam(input) {
    let value = parseFloat(input.value);
    if (isNaN(value) || input.value === '') {
        input.classList.remove('value-error');
        return;
    }
    if (value < 0) {
        input.value = 0;
        showToast('❌ La charge CPU/RAM ne peut pas être négative !', true);
        input.classList.add('value-error');
        setTimeout(() => input.classList.remove('value-error'), 1500);
    } else if (value > 100) {
        input.value = 100;
        showToast('⚠️ La charge CPU/RAM ne peut pas dépasser 100%', true);
        input.classList.add('value-error');
        setTimeout(() => input.classList.remove('value-error'), 1500);
    } else {
        input.classList.remove('value-error');
    }
}

function updateClientLimit() {
    const pack = document.getElementById('wp_type').value;
    const clientsActuels = document.getElementById('clients_actuels');
    const warningSpan = document.getElementById('clientLimitWarning');
    
    if (!pack) {
        warningSpan.innerHTML = '';
        return;
    }
    
    let maxLimit = null;
    if (pack === 'small') {
        maxLimit = 10000;
        warningSpan.innerHTML = '<span style="color: #ff4d4f;">⚠️ Pack SMALL: maximum 10 000 clients</span>';
    } else if (pack === 'medium') {
        maxLimit = 50000;
        warningSpan.innerHTML = '<span style="color: #ff4d4f;">⚠️ Pack MEDIUM: maximum 50 000 clients</span>';
    } else if (pack === 'performance') {
        warningSpan.innerHTML = '<span style="color: #52c41a;">✅ Pack PERFORMANCE: capacité illimitée</span>';
        clientsActuels.classList.remove('value-error');
        return;
    }
    
    let currentValue = parseFloat(clientsActuels.value);
    if (!isNaN(currentValue) && maxLimit !== null && currentValue > maxLimit) {
        clientsActuels.classList.add('value-error');
        showToast(`⚠️ Le pack ${pack.toUpperCase()} ne permet pas plus de ${maxLimit.toLocaleString()} clients actuels !`, true);
    } else {
        clientsActuels.classList.remove('value-error');
    }
}

function checkClientLimit() {
    const pack = document.getElementById('wp_type').value;
    const clientsActuels = document.getElementById('clients_actuels');
    let value = parseFloat(clientsActuels.value);
    
    if (isNaN(value) || clientsActuels.value === '') return true;
    if (!pack) return true;
    
    let maxLimit = null;
    if (pack === 'small') maxLimit = 10000;
    else if (pack === 'medium') maxLimit = 50000;
    
    if (maxLimit !== null && value > maxLimit) {
        clientsActuels.classList.add('value-error');
        showToast(`⚠️ Le pack ${pack.toUpperCase()} ne permet pas plus de ${maxLimit.toLocaleString()} clients actuels !`, true);
        return false;
    } else {
        clientsActuels.classList.remove('value-error');
        return true;
    }
}

function calculerTauxCroissance() {
    const clients_initiaux = parseFloat(document.getElementById('clients_initiaux').value) || 0;
    const clients_actuels = parseFloat(document.getElementById('clients_actuels').value) || 0;
    
    if (clients_initiaux !== 0 && document.getElementById('clients_initiaux').value !== '' && document.getElementById('clients_actuels').value !== '') {
        const taux = ((clients_actuels - clients_initiaux) / clients_initiaux) * 100;
        const taux_final = Math.round(taux * 100) / 100;
        document.getElementById('taux_calcule').value = taux_final + '%';
        document.getElementById('display_taux').textContent = taux_final;
        document.getElementById('growth-info').style.display = 'block';
        document.getElementById('growth').value = taux_final;
        return taux_final;
    } else {
        document.getElementById('taux_calcule').value = '';
        document.getElementById('growth-info').style.display = 'none';
        return null;
    }
}

function showTab(tabId) {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tabId);
    window.history.pushState({}, '', url);
    
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active-tab');
    });
    
    document.querySelectorAll('.menu-item').forEach(menu => {
        menu.classList.remove('active-menu');
    });
    
    document.getElementById(tabId).classList.add('active-tab');
    
    const menuItems = document.querySelectorAll('.menu-item');
    const tabNames = ['dashboard', 'resultats', 'sauvegarde', 'historique', 'supprimee'];
    const index = tabNames.indexOf(tabId);
    if (index >= 0 && menuItems[index]) {
        menuItems[index].classList.add('active-menu');
    }
    
    sessionStorage.setItem('activeTab', tabId);
}

function showToast(message, isError = false) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.style.background = isError ? '#ff4d4f' : '#52c41a';
    toast.style.display = 'block';
    setTimeout(() => {
        toast.style.display = 'none';
        toast.style.background = '#52c41a';
    }, 3000);
}

async function saveCurrentAnalysis() {
    if (!lastAnalysis) {
        showToast('⚠️ Aucune analyse à sauvegarder. Générez d\'abord une prédiction !', true);
        return;
    }
    
    const saveBtn = document.getElementById('saveAnalysisBtn');
    saveBtn.disabled = true;
    saveBtn.textContent = '💾 Sauvegarde en cours...';
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(lastAnalysis)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('✅ Analyse sauvegardée avec succès !');
            saveBtn.textContent = '✅ Sauvegardé !';
            setTimeout(() => {
                saveBtn.textContent = '💾 Sauvegarder cette analyse';
                saveBtn.disabled = false;
            }, 2000);
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast('❌ Erreur lors de la sauvegarde', true);
            saveBtn.textContent = '💾 Sauvegarder cette analyse';
            saveBtn.disabled = false;
        }
    } catch (error) {
        console.error('Erreur:', error);
        showToast('❌ Erreur de connexion', true);
        saveBtn.textContent = '💾 Sauvegarder cette analyse';
        saveBtn.disabled = false;
    }
}

async function archiverAnalyse(id) {
    if (!id) {
        showToast('❌ Identifiant d\'analyse invalide', true);
        return;
    }
    if (!confirm('Déplacer cette analyse vers la corbeille ?')) return;
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ action: 'archive', archive_id: id })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('📦 Analyse déplacée vers la corbeille');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast('❌ Erreur lors de l\'archivage', true);
        }
    } catch (error) {
        console.error('Erreur:', error);
        showToast('❌ Erreur de connexion', true);
    }
}

async function supprimerDefinitivement(id) {
    if (!id) {
        showToast('❌ Identifiant de sauvegarde invalide', true);
        return;
    }
    if (!confirm('⚠️ SUPPRESSION DÉFINITIVE ! Êtes-vous sûr de vouloir supprimer cette sauvegarde ? Cette action est irréversible.')) return;
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ action: 'delete', delete_id: id })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('🗑️ Sauvegarde supprimée définitivement');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast('❌ Erreur lors de la suppression', true);
        }
    } catch (error) {
        console.error('Erreur:', error);
        showToast('❌ Erreur de connexion', true);
    }
}

function updateLastAnalysisDisplay(analysis) {
    const container = document.getElementById('last-analysis-info');
    const saveBtn = document.getElementById('saveAnalysisBtn');
    
    const statusText = analysis.status === 'CRITIQUE' ? '🔴 Critique' : (analysis.status === 'SURVEILLANCE' ? '🟠 Surveillance' : '🟢 Optimal');
    const statusColor = analysis.status === 'CRITIQUE' ? '#cf1322' : (analysis.status === 'SURVEILLANCE' ? '#d46b00' : '#389e0d');
    
    container.innerHTML = `
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <div>
                <strong style="color: #1a2c3e;">📊 Paramètres :</strong><br>
                • Clients: ${analysis.clients_initiaux} → ${analysis.clients_actuels}<br>
                • Croissance: ${analysis.growth}%<br>
                • CPU: ${analysis.cpu}% | RAM: ${analysis.ram}%<br>
                • Pack: ${analysis.wp_type.toUpperCase()}
            </div>
            <div>
                <strong style="color: #1a2c3e;">📈 Résultat :</strong><br>
                • Charge prédite: <strong>${analysis.predicted_load}%</strong><br>
                • Saturation dans: <strong>${analysis.months_until_saturation} mois</strong><br>
                • Statut: <span style="color: ${statusColor}; font-weight: bold;">${statusText}</span>
            </div>
        </div>
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eef2f6;">
            <strong style="color: #1a2c3e;">💡 Recommandation :</strong><br>
            ${analysis.recommendation}
        </div>
    `;
    saveBtn.disabled = false;
}

function calculerMoisAvantSaturation(cpu, ram, croissance) {
    const chargeMax = Math.max(cpu, ram);
    const seuilSaturation = 90;
    
    if (chargeMax >= seuilSaturation) {
        return 0;
    }
    
    if (croissance <= 0) {
        return 999;
    }
    
    const mois = Math.log(seuilSaturation / chargeMax) / Math.log(1 + croissance / 100);
    return Math.ceil(mois);
}

function getRecommendationByMonths(months, pack, charge, croissance) {
    if (months === 0) {
        return {
            text: `⚠️ Votre infrastructure est DÉJÀ SATURÉE (${charge}% ≥ 90%). Migration IMMÉDIATE requise vers un pack supérieur !`,
            urgency: 'urgent'
        };
    } else if (months <= 2) {
        return {
            text: `🔴 URGENT : Saturation prévue dans ${months} mois. Avec une croissance de ${croissance}%, votre pack ${pack.toUpperCase()} sera saturé très rapidement. Migration recommandée dans les semaines à venir.`,
            urgency: 'urgent'
        };
    } else if (months <= 6) {
        return {
            text: `🟠 ATTENTION : Saturation prévue dans ${months} mois. Avec une croissance de ${croissance}%, planifiez une migration vers un pack supérieur d'ici ${months} mois.`,
            urgency: 'warning'
        };
    } else if (months <= 12) {
        return {
            text: `🟡 SURVEILLANCE : Saturation prévue dans ${months} mois (environ ${Math.round(months/12)} an). Avec une croissance de ${croissance}%, l'infrastructure actuelle est suffisante pour le moment. Revoyez dans 6 mois.`,
            urgency: 'warning'
        };
    } else {
        return {
            text: `🟢 OPTIMAL : Saturation prévue dans plus d'un an (${months} mois). Avec une croissance de ${croissance}%, votre pack ${pack.toUpperCase()} est parfaitement adapté. Aucune action immédiate requise.`,
            urgency: 'safe'
        };
    }
}

function updateTemporelDisplay(months, charge, croissance) {
    const container = document.getElementById('temporel-content');
    if (!container) return;
    
    let gaugeClass = 'optimal';
    let cardClass = 'safe';
    let emoji = '🟢';
    let message = '';
    
    if (months === 0) {
        gaugeClass = 'critical';
        cardClass = 'urgent';
        emoji = '🔴';
        message = `⚠️ Votre infrastructure est DÉJÀ SATURÉE (${charge}% ≥ 90%) !`;
    } else if (months <= 2) {
        gaugeClass = 'critical';
        cardClass = 'urgent';
        emoji = '🔴';
        message = `🔴 Saturation imminente dans ${months} mois !`;
    } else if (months <= 6) {
        gaugeClass = 'warning';
        cardClass = 'warning';
        emoji = '🟠';
        message = `🟠 Attention : saturation dans ${months} mois`;
    } else if (months <= 12) {
        gaugeClass = 'warning';
        cardClass = 'warning';
        emoji = '🟡';
        message = `🟡 Saturation prévue dans ${months} mois`;
    } else {
        gaugeClass = 'optimal';
        cardClass = 'safe';
        emoji = '🟢';
        message = `🟢 Infrastructure stable pour plus d'un an`;
    }
    
    let gaugePercent = 0;
    if (months === 0) {
        gaugePercent = 100;
    } else if (months <= 24) {
        gaugePercent = Math.min(100, Math.max(0, 100 - (months / 24 * 100)));
    } else {
        gaugePercent = 0;
    }
    
    container.innerHTML = `
        <div class="saturation-card ${cardClass}" style="padding: 20px; border-radius: 16px;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; margin-bottom: 15px;">
                <div>
                    <span class="months-counter">${months === 999 ? '∞' : months}</span>
                    <span class="months-label">mois avant saturation (seuil 90%)</span>
                </div>
                <div style="font-size: 32px;">${emoji}</div>
            </div>
            
            <div class="gauge-container">
                <div class="gauge-fill ${gaugeClass}" style="width: ${gaugePercent}%;"></div>
            </div>
            
            <div style="margin-top: 15px; font-size: 14px;">
                <strong>📊 Analyse :</strong><br>
                • Croissance mensuelle : <strong>${croissance}%</strong><br>
                • Charge actuelle max : <strong>${charge}%</strong><br>
                • Seuil de saturation : <strong>90%</strong><br><br>
                <strong>${message}</strong>
            </div>
        </div>
    `;
}

function runExpertAnalysis() {
    const packSelect = document.getElementById('wp_type');
    const cpuInput = document.getElementById('cpu');
    const ramInput = document.getElementById('ram');
    const growthInput = document.getElementById('growth');
    const clientsInit = document.getElementById('clients_initiaux');
    const clientsAct = document.getElementById('clients_actuels');
    
    let hasError = false;
    
    if (parseFloat(cpuInput.value) < 0) {
        showToast('❌ La charge CPU ne peut pas être négative !', true);
        hasError = true;
    }
    if (parseFloat(ramInput.value) < 0) {
        showToast('❌ La charge RAM ne peut pas être négative !', true);
        hasError = true;
    }
    if (parseFloat(clientsInit.value) < 0) {
        showToast('❌ Le nombre de clients initiaux ne peut pas être négatif !', true);
        hasError = true;
    }
    if (parseFloat(clientsAct.value) < 0) {
        showToast('❌ Le nombre de clients actuels ne peut pas être négatif !', true);
        hasError = true;
    }
    
    if (hasError) return;
    
    if (!packSelect.value) {
        showToast('❌ Veuillez choisir un pack WordPress !', true);
        packSelect.classList.add('value-error');
        setTimeout(() => packSelect.classList.remove('value-error'), 2000);
        return;
    }
    
    let growth = null;
    const clients_initiaux = clientsInit.value;
    const clients_actuels = clientsAct.value;
    
    if (clients_initiaux !== '' && clients_actuels !== '' && parseFloat(clients_initiaux) !== 0 && parseFloat(clients_initiaux) > 0) {
        const init = parseFloat(clients_initiaux);
        const act = parseFloat(clients_actuels);
        growth = ((act - init) / init) * 100;
        document.getElementById('growth').value = Math.round(growth * 100) / 100;
    } else if (growthInput.value !== '' && growthInput.value !== null) {
        growth = parseFloat(growthInput.value);
    } else {
        growth = 0;
    }
    
    let cpu = parseFloat(cpuInput.value) || 0;
    let ram = parseFloat(ramInput.value) || 0;
    const type = packSelect.value;
    let clients_initiaux_val = parseFloat(clients_initiaux) || 0;
    let clients_actuels_val = parseFloat(clients_actuels) || 0;
    
    const monthsUntilSaturation = calculerMoisAvantSaturation(cpu, ram, growth);
    const chargeMax = Math.max(cpu, ram);
    
    updateTemporelDisplay(monthsUntilSaturation, chargeMax, growth);
    
    const slope = 0.85;
    const scatterData = [];
    for (let i = 0; i <= 100; i += 5) {
        const theoreticalValue = cpu + (i * slope * (Math.max(-100, Math.min(200, growth)) / 100));
        const randomVariation = (Math.random() - 0.5) * 8;
        scatterData.push({ x: i, y: theoreticalValue + randomVariation });
    }
    
    const yAt0 = cpu;
    const yAt100 = cpu + (100 * slope * (Math.max(-100, Math.min(200, growth)) / 100));
    const regressionLine = [{ x: 0, y: yAt0 }, { x: 100, y: yAt100 }];
    
    const ctx = document.getElementById('seabornChart').getContext('2d');
    if (chartInstance) chartInstance.destroy();
    
    chartInstance = new Chart(ctx, {
        type: 'scatter',
        data: {
            datasets: [
                {
                    label: 'Points de charge observés',
                    data: scatterData,
                    backgroundColor: 'rgba(76, 114, 176, 0.7)',
                    borderColor: '#4C72B0',
                    pointRadius: 5,
                    pointHoverRadius: 8,
                    pointBorderWidth: 2,
                    pointBorderColor: '#ffffff'
                },
                {
                    label: '📈 Ligne de Régression',
                    data: regressionLine,
                    type: 'line',
                    borderColor: '#C44E52',
                    borderWidth: 3,
                    fill: false,
                    pointRadius: 0,
                    tension: 0,
                    borderDash: []
                },
                {
                    label: '⚠️ Seuil Critique (80%)',
                    data: [{ x: 0, y: 80 }, { x: 100, y: 80 }],
                    type: 'line',
                    borderColor: '#ff4d4f',
                    borderWidth: 2.5,
                    borderDash: [8, 6],
                    fill: false,
                    pointRadius: 0
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
                            if (context.dataset.label === '⚠️ Seuil Critique (80%)') {
                                return 'Seuil d\'alerte : 80% de saturation';
                            }
                            if (context.dataset.label === '📈 Ligne de Régression') {
                                return `Prédiction à ${context.parsed.x}% de croissance : ${Math.round(context.parsed.y)}% de charge`;
                            }
                            return `Croissance: ${context.parsed.x}% | Charge: ${Math.round(context.parsed.y)}%`;
                        }
                    }
                },
                legend: { display: false }
            },
            scales: {
                x: {
                    title: { display: true, text: 'Croissance du Trafic (%)', font: { size: 13, weight: 'bold' } },
                    grid: { color: '#eef2f6' },
                    min: 0, max: 100,
                    ticks: { stepSize: 10, callback: (v) => v + '%' }
                },
                y: {
                    title: { display: true, text: 'Utilisation des Ressources (%)', font: { size: 13, weight: 'bold' } },
                    grid: { color: '#eef2f6' },
                    min: 0, max: 100,
                    ticks: { stepSize: 10, callback: (v) => v + '%' }
                }
            }
        }
    });
    
    const predictedLoadAtGrowth = cpu + (growth * slope);
    const finalLoad = Math.min(100, Math.max(0, Math.round(predictedLoadAtGrowth)));
    const statusArea = document.getElementById('status-area');
    const reportText = document.getElementById('report-text');
    
    const rec = getRecommendationByMonths(monthsUntilSaturation, type, chargeMax, growth);
    let recommendation = rec.text;
    let status = '';
    
    if (finalLoad >= 80 || monthsUntilSaturation <= 2) {
        status = 'CRITIQUE';
        statusArea.innerHTML = `<div class="status-badge critical">⚠️ CRITIQUE - Saturation dans ${monthsUntilSaturation} mois (${finalLoad}% charge)</div>`;
    } else if (finalLoad >= 70 || monthsUntilSaturation <= 6) {
        status = 'SURVEILLANCE';
        statusArea.innerHTML = `<div class="status-badge warning">⚡ SURVEILLANCE - Saturation dans ${monthsUntilSaturation} mois</div>`;
    } else {
        status = 'OPTIMAL';
        statusArea.innerHTML = `<div class="status-badge optimal">✅ OPTIMAL - Infrastructure stable (${monthsUntilSaturation} mois avant saturation)</div>`;
    }
    
    reportText.innerHTML = `
        <strong>Analyse prédictive :</strong><br>
        • Taux de croissance : <strong>${Math.round(growth)}%</strong> par mois<br>
        • Charge CPU/RAM actuelle : ${cpu}% / ${ram}%<br>
        • Charge max actuelle : <strong>${chargeMax}%</strong><br><br>
        <strong>⏰ Prédiction temporelle :</strong><br>
        • Saturation (90%) prévue dans <strong style="font-size: 18px;">${monthsUntilSaturation === 999 ? '∞' : monthsUntilSaturation}</strong> mois<br><br>
        <strong>💡 Recommandation :</strong><br>
        ${recommendation}
    `;
    
    lastAnalysis = {
        cpu: cpu,
        ram: ram,
        growth: Math.round(growth),
        clients_initiaux: clients_initiaux_val,
        clients_actuels: clients_actuels_val,
        wp_type: type,
        predicted_load: finalLoad,
        months_until_saturation: monthsUntilSaturation === 999 ? '∞' : monthsUntilSaturation,
        status: status,
        recommendation: recommendation
    };
    
    updateLastAnalysisDisplay(lastAnalysis);
    analysisGenerated = true;
    
    showPredictionMessage();
    document.getElementById('no-data-message').style.display = 'none';
    document.getElementById('results-content').style.display = 'block';
    
    // Changer d'onglet vers Résultats
    showTab('resultats');
    
    // SCROLL EN HAUT DE PAGE (CORRECTION)
    setTimeout(() => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }, 100);
}

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    let tabToShow = urlParams.get('tab');
    
    if (!tabToShow) {
        tabToShow = sessionStorage.getItem('activeTab');
    }
    
    if (!tabToShow || !['dashboard', 'resultats', 'sauvegarde', 'historique', 'supprimee'].includes(tabToShow)) {
        tabToShow = 'dashboard';
    }
    
    showTab(tabToShow);
});
</script>
</body>
</html>
