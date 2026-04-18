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
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS predictions (
        id TEXT PRIMARY KEY,
        created_at TEXT,
        cpu_usage_avg TEXT,
        cpu_usage_peak TEXT,
        ram_usage_avg TEXT,
        disk_read_iops TEXT,
        disk_write_iops TEXT,
        response_time TEXT,
        visitors_per_day TEXT,
        pageviews_per_day TEXT,
        traffic_growth_rate TEXT,
        peak_hours_start TEXT,
        peak_hours_end TEXT,
        peak_hours TEXT,
        plugin_count TEXT,
        heavy_plugins TEXT,
        php_version TEXT,
        cache_enabled TEXT,
        cdn_enabled TEXT,
        wp_type TEXT,
        predicted_load TEXT,
        predicted_saturation_months TEXT,
        xgboost_score TEXT,
        status TEXT,
        recommendation TEXT,
        tree_image TEXT,
        is_deleted INTEGER DEFAULT 0
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS deleted_sauvegardes (
        id TEXT PRIMARY KEY,
        created_at TEXT,
        cpu_usage_avg TEXT,
        cpu_usage_peak TEXT,
        ram_usage_avg TEXT,
        disk_read_iops TEXT,
        disk_write_iops TEXT,
        response_time TEXT,
        visitors_per_day TEXT,
        pageviews_per_day TEXT,
        traffic_growth_rate TEXT,
        peak_hours_start TEXT,
        peak_hours_end TEXT,
        peak_hours TEXT,
        plugin_count TEXT,
        heavy_plugins TEXT,
        php_version TEXT,
        cache_enabled TEXT,
        cdn_enabled TEXT,
        wp_type TEXT,
        predicted_load TEXT,
        predicted_saturation_months TEXT,
        xgboost_score TEXT,
        status TEXT,
        recommendation TEXT,
        tree_image TEXT,
        deleted_at TEXT
    )");
} catch (Exception $e) {
    error_log("SQLite error: " . $e->getMessage());
}

// --- FONCTION POUR EXÉCUTER LE SCRIPT PYTHON DE PRÉDICTION ---
function executePythonPrediction() {
    $isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
    $pythonBin = $isWindows ? 'python' : 'python3';
    $scriptPath = __DIR__ . '/predict_from_file.py';
    
    if (!file_exists($scriptPath)) {
        return ['success' => false, 'error' => 'Script Python non trouvé: ' . $scriptPath];
    }
    
    // Tester Python
    $testCommand = $pythonBin . ' --version 2>&1';
    exec($testCommand, $testOutput, $testReturn);
    if ($testReturn !== 0) {
        return ['success' => false, 'error' => 'Python n\'est pas accessible'];
    }
    
    // Exécuter le script
    if ($isWindows) {
        $command = "\"$pythonBin\" \"" . $scriptPath . "\" 2>&1";
    } else {
        $command = $pythonBin . ' ' . escapeshellarg($scriptPath) . ' 2>&1';
    }
    
    exec($command, $output, $returnCode);
    $outputString = implode("\n", $output);
    
    // Trouver le JSON dans la sortie
    $jsonStart = strpos($outputString, '{');
    $jsonEnd = strrpos($outputString, '}');
    
    if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd > $jsonStart) {
        $jsonString = substr($outputString, $jsonStart, $jsonEnd - $jsonStart + 1);
        $result = json_decode($jsonString, true);
        
        if ($result !== null) {
            if (!isset($result['success'])) {
                $result['success'] = true;
            }
            return $result;
        }
    }
    
    return [
        'success' => false,
        'error' => 'Erreur d\'exécution Python',
        'return_code' => $returnCode,
        'output_preview' => substr($outputString, 0, 200)
    ];
}

// --- FONCTION POUR GÉNÉRER L'ARBRE DE DÉCISION ---
function generateDecisionTree() {
    $isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
    $pythonBin = $isWindows ? 'python' : 'python3';
    $scriptPath = __DIR__ . '/cross_path_arbre.py';
    
    if (!file_exists($scriptPath)) {
        return ['success' => false, 'error' => 'Script cross_path_arbre.py non trouvé'];
    }
    
    $jsonFolder = __DIR__ . '/Donnee_parametres';
    if (!file_exists($jsonFolder)) {
        return ['success' => false, 'error' => 'Dossier Donnee_parametres non trouvé'];
    }
    
    $jsonFiles = glob($jsonFolder . "/*.json");
    if (empty($jsonFiles)) {
        return ['success' => false, 'error' => 'Aucun fichier JSON trouvé'];
    }
    
    if ($isWindows) {
        $command = "\"$pythonBin\" \"" . $scriptPath . "\" 2>&1";
    } else {
        $command = $pythonBin . ' ' . escapeshellarg($scriptPath) . ' 2>&1';
    }
    
    exec($command, $output, $returnCode);
    $outputString = implode("\n", $output);
    
    // Chercher le JSON dans la sortie
    $jsonStart = strpos($outputString, '{');
    $jsonEnd = strrpos($outputString, '}');
    
    if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd > $jsonStart) {
        $jsonString = substr($outputString, $jsonStart, $jsonEnd - $jsonStart + 1);
        $result = json_decode($jsonString, true);
        if ($result !== null) {
            return $result;
        }
    }
    
    // Vérifier si les images ont été générées
    $images = ['xgboost_tree_0.png', 'xgboost_tree_final.png', 'xgboost_tree_custom.png'];
    $allExist = true;
    foreach ($images as $img) {
        if (!file_exists(__DIR__ . '/' . $img)) {
            $allExist = false;
        }
    }
    
    if ($allExist) {
        return ['success' => true, 'message' => 'Arbres générés avec succès'];
    } else {
        return ['success' => false, 'error' => 'Erreur lors de la génération des arbres'];
    }
}

// --- FONCTIONS DE PERSISTANCE ---
function getPredictions($pdo) {
    if ($pdo === null) return [];
    try {
        $stmt = $pdo->query("SELECT * FROM predictions WHERE is_deleted = 0 ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function getDeletedSauvegardes($pdo) {
    if ($pdo === null) return [];
    try {
        $stmt = $pdo->query("SELECT * FROM deleted_sauvegardes ORDER BY deleted_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function savePrediction($pdo, $data) {
    if ($pdo === null) return false;
    try {
        $stmt = $pdo->prepare("INSERT INTO predictions (
            id, created_at, cpu_usage_avg, cpu_usage_peak, ram_usage_avg, disk_read_iops, disk_write_iops, response_time,
            visitors_per_day, pageviews_per_day, traffic_growth_rate, peak_hours_start, peak_hours_end, peak_hours,
            plugin_count, heavy_plugins, php_version, cache_enabled, cdn_enabled,
            wp_type, predicted_load, predicted_saturation_months, xgboost_score,
            status, recommendation, tree_image, is_deleted
        ) VALUES (
            :id, :created_at, :cpu_usage_avg, :cpu_usage_peak, :ram_usage_avg, :disk_read_iops, :disk_write_iops, :response_time,
            :visitors_per_day, :pageviews_per_day, :traffic_growth_rate, :peak_hours_start, :peak_hours_end, :peak_hours,
            :plugin_count, :heavy_plugins, :php_version, :cache_enabled, :cdn_enabled,
            :wp_type, :predicted_load, :predicted_saturation_months, :xgboost_score,
            :status, :recommendation, :tree_image, 0
        )");
        
        $stmt->execute([
            ':id' => $data['id'],
            ':created_at' => $data['created_at'],
            ':cpu_usage_avg' => $data['cpu_usage_avg'] ?? '',
            ':cpu_usage_peak' => $data['cpu_usage_peak'] ?? '',
            ':ram_usage_avg' => $data['ram_usage_avg'] ?? '',
            ':disk_read_iops' => $data['disk_read_iops'] ?? '',
            ':disk_write_iops' => $data['disk_write_iops'] ?? '',
            ':response_time' => $data['response_time'] ?? '',
            ':visitors_per_day' => $data['visitors_per_day'] ?? '',
            ':pageviews_per_day' => $data['pageviews_per_day'] ?? '',
            ':traffic_growth_rate' => $data['traffic_growth_rate'] ?? '',
            ':peak_hours_start' => $data['peak_hours_start'] ?? '',
            ':peak_hours_end' => $data['peak_hours_end'] ?? '',
            ':peak_hours' => $data['peak_hours'] ?? '',
            ':plugin_count' => $data['plugin_count'] ?? '',
            ':heavy_plugins' => $data['heavy_plugins'] ?? '',
            ':php_version' => $data['php_version'] ?? '',
            ':cache_enabled' => $data['cache_enabled'] ?? '',
            ':cdn_enabled' => $data['cdn_enabled'] ?? '',
            ':wp_type' => $data['wp_type'] ?? '',
            ':predicted_load' => $data['predicted_load'] ?? '',
            ':predicted_saturation_months' => $data['predicted_saturation_months'] ?? '',
            ':xgboost_score' => $data['xgboost_score'] ?? '',
            ':status' => $data['status'] ?? '',
            ':recommendation' => $data['recommendation'] ?? '',
            ':tree_image' => $data['tree_image'] ?? ''
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Save error: " . $e->getMessage());
        return false;
    }
}

function archivePrediction($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM predictions WHERE id = :id AND is_deleted = 0");
        $stmt->execute([':id' => $id]);
        $pred = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pred) {
            $stmt2 = $pdo->prepare("INSERT INTO deleted_sauvegardes (
                id, created_at, cpu_usage_avg, cpu_usage_peak, ram_usage_avg, disk_read_iops, disk_write_iops, response_time,
                visitors_per_day, pageviews_per_day, traffic_growth_rate, peak_hours_start, peak_hours_end, peak_hours,
                plugin_count, heavy_plugins, php_version, cache_enabled, cdn_enabled,
                wp_type, predicted_load, predicted_saturation_months, xgboost_score,
                status, recommendation, tree_image, deleted_at
            ) VALUES (
                :id, :created_at, :cpu_usage_avg, :cpu_usage_peak, :ram_usage_avg, :disk_read_iops, :disk_write_iops, :response_time,
                :visitors_per_day, :pageviews_per_day, :traffic_growth_rate, :peak_hours_start, :peak_hours_end, :peak_hours,
                :plugin_count, :heavy_plugins, :php_version, :cache_enabled, :cdn_enabled,
                :wp_type, :predicted_load, :predicted_saturation_months, :xgboost_score,
                :status, :recommendation, :tree_image, :deleted_at
            )");
            
            $stmt2->execute([
                ':id' => $pred['id'],
                ':created_at' => $pred['created_at'],
                ':cpu_usage_avg' => $pred['cpu_usage_avg'],
                ':cpu_usage_peak' => $pred['cpu_usage_peak'],
                ':ram_usage_avg' => $pred['ram_usage_avg'],
                ':disk_read_iops' => $pred['disk_read_iops'],
                ':disk_write_iops' => $pred['disk_write_iops'],
                ':response_time' => $pred['response_time'],
                ':visitors_per_day' => $pred['visitors_per_day'],
                ':pageviews_per_day' => $pred['pageviews_per_day'],
                ':traffic_growth_rate' => $pred['traffic_growth_rate'],
                ':peak_hours_start' => $pred['peak_hours_start'],
                ':peak_hours_end' => $pred['peak_hours_end'],
                ':peak_hours' => $pred['peak_hours'],
                ':plugin_count' => $pred['plugin_count'],
                ':heavy_plugins' => $pred['heavy_plugins'],
                ':php_version' => $pred['php_version'],
                ':cache_enabled' => $pred['cache_enabled'],
                ':cdn_enabled' => $pred['cdn_enabled'],
                ':wp_type' => $pred['wp_type'],
                ':predicted_load' => $pred['predicted_load'],
                ':predicted_saturation_months' => $pred['predicted_saturation_months'],
                ':xgboost_score' => $pred['xgboost_score'],
                ':status' => $pred['status'],
                ':recommendation' => $pred['recommendation'],
                ':tree_image' => $pred['tree_image'],
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
    try {
        $stmt = $pdo->prepare("DELETE FROM deleted_sauvegardes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// --- ROUTES AJAX ---
if (isset($_GET['export_full_csv']) && isset($_SESSION['logged_in'])) {
    $predictions = getPredictions($pdo);
    
    $headers = ['Date', 'CPU Moyen', 'CPU Peak', 'RAM', 'Read IOPS', 'Write IOPS', 'Visiteurs/jour', 'Croissance', 
                'Plugins', 'Pack', 'Charge prédite', 'Score XGBoost', 'Saturation', 'Statut'];
    
    $csvContent = implode(',', $headers) . "\n";
    
    foreach ($predictions as $pred) {
        $row = [
            $pred['created_at'] ?? '',
            $pred['cpu_usage_avg'] ?? '',
            $pred['cpu_usage_peak'] ?? '',
            $pred['ram_usage_avg'] ?? '',
            $pred['disk_read_iops'] ?? '',
            $pred['disk_write_iops'] ?? '',
            $pred['visitors_per_day'] ?? '',
            $pred['traffic_growth_rate'] ?? '',
            $pred['plugin_count'] ?? '',
            $pred['wp_type'] ?? '',
            $pred['predicted_load'] ?? '',
            $pred['xgboost_score'] ?? '',
            $pred['predicted_saturation_months'] ?? '',
            $pred['status'] ?? ''
        ];
        $csvContent .= implode(',', $row) . "\n";
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="export_xgboost_' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF" . $csvContent;
    exit();
}

if (isset($_GET['run_python']) && isset($_SESSION['logged_in'])) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache');
    
    $result = executePythonPrediction();
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit();
}

if (isset($_GET['generate_tree']) && isset($_SESSION['logged_in'])) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache');
    
    $result = generateDecisionTree();
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit();
}

if (isset($_GET['test_python']) && isset($_SESSION['logged_in'])) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    $isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
    $pythonBin = $isWindows ? 'python' : 'python3';
    
    $testCommand = $pythonBin . ' --version 2>&1';
    exec($testCommand, $testOutput, $testReturn);
    
    echo json_encode([
        'success' => $testReturn === 0,
        'python_version' => implode(' ', $testOutput),
        'python_path' => $pythonBin,
        'os' => PHP_OS
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// --- SAUVEGARDE DES PARAMÈTRES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if ($data && isset($_SESSION['logged_in'])) {
        if (isset($data['action'])) {
            if ($data['action'] === 'delete' && isset($data['delete_id'])) {
                echo json_encode(['success' => deletePermanently($pdo, $data['delete_id'])]);
                exit();
            }
            if ($data['action'] === 'archive' && isset($data['archive_id'])) {
                echo json_encode(['success' => archivePrediction($pdo, $data['archive_id'])]);
                exit();
            }
        }
        
        if (isset($data['predicted_load'])) {
            $prediction = [
                'id' => uniqid(),
                'created_at' => date('Y-m-d H:i:s'),
                'cpu_usage_avg' => $data['cpu_usage_avg'] ?? '',
                'cpu_usage_peak' => $data['cpu_usage_peak'] ?? '',
                'ram_usage_avg' => $data['ram_usage_avg'] ?? '',
                'disk_read_iops' => $data['disk_read_iops'] ?? '',
                'disk_write_iops' => $data['disk_write_iops'] ?? '',
                'response_time' => $data['response_time'] ?? '',
                'visitors_per_day' => $data['visitors_per_day'] ?? '',
                'pageviews_per_day' => $data['pageviews_per_day'] ?? '',
                'traffic_growth_rate' => $data['traffic_growth_rate'] ?? '',
                'peak_hours_start' => $data['peak_hours_start'] ?? '',
                'peak_hours_end' => $data['peak_hours_end'] ?? '',
                'peak_hours' => $data['peak_hours'] ?? '',
                'plugin_count' => $data['plugin_count'] ?? '',
                'heavy_plugins' => $data['heavy_plugins'] ?? '',
                'php_version' => $data['php_version'] ?? '',
                'cache_enabled' => $data['cache_enabled'] ?? '',
                'cdn_enabled' => $data['cdn_enabled'] ?? '',
                'wp_type' => $data['wp_type'] ?? '',
                'predicted_load' => $data['predicted_load'] ?? '',
                'predicted_saturation_months' => $data['predicted_saturation_months'] ?? '',
                'xgboost_score' => $data['xgboost_score'] ?? '',
                'status' => $data['status'] ?? '',
                'recommendation' => $data['recommendation'] ?? '',
                'tree_image' => $data['tree_image'] ?? ''
            ];
            echo json_encode(['success' => savePrediction($pdo, $prediction)]);
            exit();
        }
        
        $jsonFolder = './Donnee_parametres';
        if (!file_exists($jsonFolder)) mkdir($jsonFolder, 0777, true);
        
        $filename = $jsonFolder . '/parameters_' . date('Y-m-d_H-i-s') . '.json';
        $dataToSave = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user' => ADMIN_USERNAME,
            'parameters' => $data
        ];
        
        if (file_put_contents($filename, json_encode($dataToSave, JSON_PRETTY_PRINT))) {
            echo json_encode(['status' => 'success', 'message' => 'Paramètres sauvegardés']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Erreur sauvegarde']);
        }
        exit();
    }
    echo json_encode(['success' => false]);
    exit();
}

// --- GESTION AUTHENTIFICATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $error = "Accès refusé.";
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
    <title>Vala Bleu - XGBoost Predictor</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .login-container { width: 100%; max-width: 440px; }
        .login-card { background: rgba(255, 255, 255, 0.98); border-radius: 32px; padding: 48px 40px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); transition: transform 0.3s ease; }
        .login-card:hover { transform: translateY(-5px); }
        .logo-wrapper { text-align: center; margin-bottom: 32px; }
        .logo-icon { width: 80px; height: 80px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 24px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px; box-shadow: 0 10px 25px -5px rgba(102, 126, 234, 0.3); }
        .logo-icon i { font-size: 40px; color: white; }
        h1 { font-size: 32px; font-weight: 800; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; background-clip: text; color: transparent; margin-bottom: 8px; }
        .subtitle { color: #6b7280; font-size: 14px; }
        .input-group { margin-bottom: 24px; }
        .input-group label { display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 8px; }
        .input-group input { width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 16px; font-size: 15px; transition: all 0.3s ease; font-family: 'Inter', sans-serif; }
        .input-group input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .login-btn { width: 100%; padding: 14px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 16px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; font-family: 'Inter', sans-serif; }
        .login-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(102, 126, 234, 0.4); }
        .error-message { background: #fef2f2; border-left: 4px solid #ef4444; padding: 12px 16px; border-radius: 12px; margin-bottom: 24px; color: #dc2626; font-size: 14px; display: flex; align-items: center; gap: 10px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .login-card { animation: fadeIn 0.5s ease; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo-wrapper">
                <div class="logo-icon"><i class="fas fa-brain"></i></div>
                <h1>VALA BLEU</h1>
                <div class="subtitle">XGBoost Predictive Engine</div>
            </div>
            <?php if (isset($error)): ?>
                <div class="error-message"><i class="fas fa-exclamation-circle"></i><span><?php echo htmlspecialchars($error); ?></span></div>
            <?php endif; ?>
            <form method="POST">
                <div class="input-group"><label><i class="fas fa-user"></i> Identifiant</label><input type="text" name="username" placeholder="admin" autocomplete="off"></div>
                <div class="input-group"><label><i class="fas fa-lock"></i> Mot de passe</label><input type="password" name="password" placeholder="••••••"></div>
                <button type="submit" name="login_submit" class="login-btn"><i class="fas fa-arrow-right"></i> Accéder au dashboard</button>
            </form>
        </div>
    </div>
</body>
</html>
<?php exit(); } ?>

<!-- DASHBOARD PRINCIPAL -->
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vala Bleu - Dashboard XGBoost</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; color: #1f2937; }
        .sidebar { position: fixed; left: 0; top: 0; width: 280px; height: 100vh; background: linear-gradient(180deg, #1f2937 0%, #111827 100%); color: white; padding: 32px 24px; display: flex; flex-direction: column; box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); z-index: 100; }
        .sidebar-header { text-align: center; margin-bottom: 40px; padding-bottom: 24px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .sidebar-header h2 { font-size: 28px; font-weight: 800; background: linear-gradient(135deg, #60a5fa, #a78bfa); -webkit-background-clip: text; background-clip: text; color: transparent; margin-bottom: 8px; }
        .sidebar-header p { font-size: 11px; color: #9ca3af; letter-spacing: 1px; }
        .menu-item { padding: 12px 16px; border-radius: 12px; cursor: pointer; margin-bottom: 8px; color: #d1d5db; transition: all 0.3s ease; display: flex; align-items: center; gap: 12px; font-weight: 500; }
        .menu-item i { width: 24px; font-size: 18px; }
        .menu-item:hover { background: rgba(255, 255, 255, 0.1); color: white; transform: translateX(5px); }
        .active-menu { background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: white; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
        .logout-link { margin-top: auto; padding: 12px 16px; color: #f87171; text-decoration: none; border-radius: 12px; display: flex; align-items: center; gap: 12px; transition: all 0.3s ease; background: rgba(248, 113, 113, 0.1); font-weight: 500; }
        .logout-link:hover { background: rgba(248, 113, 113, 0.2); transform: translateX(5px); }
        .main-content { margin-left: 280px; padding: 32px 48px; min-height: 100vh; }
        .tab-content { display: none; animation: fadeInUp 0.4s ease; }
        .active-tab { display: block; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .card { background: white; border-radius: 24px; padding: 32px; margin-bottom: 28px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); border: 1px solid #e5e7eb; transition: box-shadow 0.3s ease; }
        .card:hover { box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1); }
        .card h3 { font-size: 20px; font-weight: 700; color: #1f2937; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
        .param-section { background: #f9fafb; border-radius: 20px; padding: 24px; margin-bottom: 24px; border: 1px solid #e5e7eb; }
        .param-section h4 { font-size: 18px; font-weight: 600; color: #1f2937; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .grid-4 { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .form-group { margin-bottom: 0; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #4b5563; margin-bottom: 8px; }
        .form-group input, .form-group select { width: 100%; padding: 10px 14px; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 14px; font-family: 'Inter', sans-serif; transition: all 0.3s ease; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .required-field { color: #ef4444; margin-left: 4px; }
        .checkbox-group { display: flex; flex-direction: column; gap: 8px; padding: 10px; border: 2px solid #e5e7eb; border-radius: 12px; background: white; max-height: 150px; overflow-y: auto; }
        .checkbox-item { display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 4px 8px; border-radius: 8px; transition: background 0.2s ease; }
        .checkbox-item:hover { background: #f3f4f6; }
        .checkbox-item input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; margin: 0; }
        .checkbox-item label { margin: 0; cursor: pointer; font-weight: normal; font-size: 13px; color: #374151; }
        .double-input { display: flex; gap: 12px; }
        .double-input .input-half { flex: 1; }
        .input-half label { font-size: 11px; margin-bottom: 4px; }
        .btn-primary { width: 100%; padding: 14px; background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: white; border: none; border-radius: 16px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; font-family: 'Inter', sans-serif; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.4); }
        .btn-save { background: linear-gradient(135deg, #10b981, #34d399); }
        .btn-green { background: linear-gradient(135deg, #10b981, #34d399); color: white; border: none; border-radius: 12px; padding: 10px 20px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .btn-green:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3); }
        .btn-archive { background: #6b7280; color: white; border: none; padding: 6px 12px; border-radius: 20px; cursor: pointer; font-size: 12px; font-weight: 500; transition: all 0.3s ease; }
        .btn-archive:hover { background: #4b5563; transform: scale(1.05); }
        .btn-delete-red { background: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 20px; cursor: pointer; font-size: 12px; font-weight: 500; transition: all 0.3s ease; }
        .btn-delete-red:hover { background: #dc2626; transform: scale(1.05); }
        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th, .history-table td { padding: 12px 8px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .history-table th { background: #f9fafb; font-weight: 600; color: #374151; font-size: 13px; }
        .history-table tr:hover { background: #f9fafb; }
        .badge-status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-critical { background: #fef2f2; color: #dc2626; }
        .badge-warning { background: #fffbeb; color: #d97706; }
        .badge-optimal { background: #f0fdf4; color: #10b981; }
        .score-large { font-size: 48px; font-weight: 800; }
        .gauge-container { background: #e5e7eb; border-radius: 20px; height: 10px; margin: 15px 0; overflow: hidden; }
        .gauge-fill { height: 100%; border-radius: 20px; transition: width 0.5s ease; }
        .gauge-fill.critical { background: linear-gradient(90deg, #ef4444, #dc2626); }
        .gauge-fill.warning { background: linear-gradient(90deg, #f59e0b, #d97706); }
        .gauge-fill.optimal { background: linear-gradient(90deg, #10b981, #059669); }
        .toast-notification { position: fixed; bottom: 30px; right: 30px; background: #10b981; color: white; padding: 14px 24px; border-radius: 16px; display: none; z-index: 1000; animation: slideInRight 0.3s ease; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2); font-weight: 500; }
        @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .loading-spinner { text-align: center; padding: 60px; }
        .loading-spinner i { font-size: 48px; color: #3b82f6; animation: spin 1s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .tree-image { max-width: 100%; border-radius: 12px; border: 1px solid #e5e7eb; margin-top: 15px; cursor: pointer; transition: transform 0.2s; }
        .tree-image:hover { transform: scale(1.02); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .tree-container { margin-top: 20px; padding: 20px; background: #f9fafb; border-radius: 16px; border: 1px solid #e5e7eb; }
        .tree-title { font-size: 14px; font-weight: 600; color: #1f2937; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.95); cursor: pointer; }
        .modal-content { margin: auto; display: block; max-width: 95%; max-height: 95%; margin-top: 2%; }
        .close-modal { position: absolute; top: 20px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; cursor: pointer; z-index: 1001; }
        .close-modal:hover { color: #bbb; }
        .zoom-hint { position: absolute; bottom: 20px; left: 20px; color: white; background: rgba(0, 0, 0, 0.5); padding: 5px 10px; border-radius: 20px; font-size: 12px; }
        .page-title { margin-bottom: 32px; }
        .page-title h1 { font-size: 32px; font-weight: 800; color: #1f2937; margin-bottom: 8px; }
        .page-title p { color: #6b7280; }
        .flex-between { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        @media (max-width: 768px) {
            .sidebar { width: 80px; padding: 20px 12px; }
            .sidebar-header h2, .sidebar-header p, .menu-item span, .logout-link span { display: none; }
            .menu-item i, .logout-link i { margin: 0; }
            .main-content { margin-left: 80px; padding: 20px; }
            .grid-4 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header"><h2>VALA BLEU</h2><p>XGBOOST PREDICTOR</p></div>
    <div class="menu-item <?php echo $active_tab == 'dashboard' ? 'active-menu' : ''; ?>" onclick="showTab('dashboard')"><i class="fas fa-sliders-h"></i><span>Paramètres</span></div>
    <div class="menu-item <?php echo $active_tab == 'resultats' ? 'active-menu' : ''; ?>" onclick="showTab('resultats')"><i class="fas fa-chart-line"></i><span>Résultats</span></div>
    <div class="menu-item <?php echo $active_tab == 'historique' ? 'active-menu' : ''; ?>" onclick="showTab('historique')"><i class="fas fa-history"></i><span>Historique</span></div>
    <div class="menu-item <?php echo $active_tab == 'corbeille' ? 'active-menu' : ''; ?>" onclick="showTab('corbeille')"><i class="fas fa-trash-alt"></i><span>Corbeille</span></div>
    <a href="?logout=1" class="logout-link"><i class="fas fa-sign-out-alt"></i><span>Déconnexion</span></a>
</div>

<div class="main-content">
    <div id="dashboard" class="tab-content <?php echo $active_tab == 'dashboard' ? 'active-tab' : ''; ?>">
        <div class="page-title"><h1>Analyse Prédictive XGBoost</h1><p>Modèle XGBoost pour la prédiction de charge WordPress</p></div>
        
        <div class="param-section">
            <h4><i class="fas fa-chart-bar"></i> Trafic</h4>
            <div class="grid-4">
                <div class="form-group"><label>Visiteurs/jour <span class="required-field">*</span></label><input type="number" id="visitors_per_day" value="5000" placeholder="Ex: 5000"></div>
                <div class="form-group"><label>Pages vues/jour</label><input type="number" id="pageviews_per_day" value="15000" placeholder="Ex: 15000"></div>
                <div class="form-group"><label>Croissance (%) <span class="required-field">*</span></label><input type="number" id="traffic_growth_rate" value="15" placeholder="Ex: 15"></div>
                <div class="form-group"><label>Pics horaires</label><div style="display: flex; gap: 10px;"><input type="time" id="peak_hours_start" value="09:00" style="flex: 1;"><span style="align-self: center;">à</span><input type="time" id="peak_hours_end" value="18:00" style="flex: 1;"></div></div>
            </div>
        </div>
        
        <div class="param-section">
            <h4><i class="fas fa-server"></i> Ressources Serveur</h4>
            <div class="grid-4">
                <div class="form-group"><label>CPU moyen (%) <span class="required-field">*</span></label><input type="number" id="cpu_usage_avg" value="45" placeholder="Ex: 45"></div>
                <div class="form-group"><label>CPU max (%) <span class="required-field">*</span></label><input type="number" id="cpu_usage_peak" value="75" placeholder="Ex: 75"></div>
                <div class="form-group"><label>RAM moyenne (%) <span class="required-field">*</span></label><input type="number" id="ram_usage_avg" value="60" placeholder="Ex: 60"></div>
                <div class="form-group"><label>Temps réponse (ms)</label><input type="number" id="response_time" value="350" placeholder="Ex: 350"></div>
                <div class="form-group"><label>I/O Disque (IOPS)</label><div class="double-input"><div class="input-half"><label>Read IOPS</label><input type="number" id="disk_read_iops" value="150" placeholder="Read"></div><div class="input-half"><label>Write IOPS</label><input type="number" id="disk_write_iops" value="80" placeholder="Write"></div></div></div>
            </div>
        </div>
        
        <div class="param-section">
            <h4><i class="fab fa-wordpress"></i> WordPress</h4>
            <div class="grid-4">
                <div class="form-group"><label>Nombre de plugins <span class="required-field">*</span></label><input type="number" id="plugin_count" value="25" placeholder="Ex: 25"></div>
                <div class="form-group"><label>Plugins lourds</label>
                    <div class="checkbox-group" id="heavy_plugins_group">
                        <div class="checkbox-item"><input type="checkbox" value="woocommerce" id="plugin_woocommerce"><label for="plugin_woocommerce">WooCommerce</label></div>
                        <div class="checkbox-item"><input type="checkbox" value="elementor" id="plugin_elementor"><label for="plugin_elementor">Elementor</label></div>
                        <div class="checkbox-item"><input type="checkbox" value="wpml" id="plugin_wpml"><label for="plugin_wpml">WPML</label></div>
                        <div class="checkbox-item"><input type="checkbox" value="yoast" id="plugin_yoast"><label for="plugin_yoast">Yoast SEO</label></div>
                        <div class="checkbox-item"><input type="checkbox" value="revslider" id="plugin_revslider"><label for="plugin_revslider">RevSlider</label></div>
                        <div class="checkbox-item"><input type="checkbox" value="gravityforms" id="plugin_gravityforms"><label for="plugin_gravityforms">Gravity Forms</label></div>
                    </div>
                </div>
                <div class="form-group"><label>Version PHP</label><select id="php_version"><option value="7.4">PHP 7.4</option><option value="8.0">PHP 8.0</option><option value="8.1" selected>PHP 8.1</option><option value="8.2">PHP 8.2</option><option value="8.3">PHP 8.3</option></select></div>
                <div class="form-group"><label>Cache activé</label><select id="cache_enabled"><option value="oui">Oui</option><option value="non" selected>Non</option></select></div>
                <div class="form-group"><label>CDN activé</label><select id="cdn_enabled"><option value="oui">Oui</option><option value="non" selected>Non</option></select></div>
                <div class="form-group"><label>Pack WordPress <span class="required-field">*</span></label><select id="wp_type"><option value="small">SMALL</option><option value="medium" selected>MEDIUM</option><option value="performance">PERFORMANCE</option><option value="enterprise">ENTERPRISE</option></select></div>
            </div>
        </div>
        
        <div class="card">
            <button class="btn-primary" onclick="runXGBoostPrediction()"><i class="fas fa-play"></i> LANCER LA PRÉDICTION XGBOOST</button>
        </div>
        <div class="card" style="margin-top: 10px;">
            <button class="btn-primary" style="background: #6c5ce7;" onclick="testPythonConnection()"><i class="fas fa-stethoscope"></i> DIAGNOSTIC - Tester Python</button>
        </div>
    </div>
    
    <div id="resultats" class="tab-content <?php echo $active_tab == 'resultats' ? 'active-tab' : ''; ?>">
        <div class="page-title"><h1>Résultats XGBoost</h1><p>Prédiction basée sur le modèle XGBoost avec arbre de décision</p></div>
        <div id="resultsContainer" style="display: none;">
            <div class="card"><h3><i class="fas fa-chart-pie"></i> Scores de Performance</h3><div id="scoresDisplay"></div></div>
            <div class="card"><h3><i class="fas fa-lightbulb"></i> Recommandation</h3><div id="recommendationDisplay"></div></div>
            <div class="card"><div class="flex-between"><h3><i class="fas fa-tree"></i> Arbre de Décision XGBoost</h3><button class="btn-green" onclick="generateAndShowTree()"><i class="fas fa-eye"></i> VISUALISER L'ARBRE</button></div><div id="treeDisplay"></div></div>
            <div class="card"><button class="btn-primary btn-save" id="saveResultBtn" onclick="saveCurrentResult()"><i class="fas fa-save"></i> Sauvegarder cette analyse</button></div>
        </div>
        <div id="noResults" class="card" style="text-align: center;"><i class="fas fa-inbox" style="font-size: 48px; color: #9ca3af; margin-bottom: 16px; display: block;"></i><p style="color: #6b7280;">Aucune prédiction générée.</p><p style="color: #9ca3af; font-size: 14px; margin-top: 8px;">Remplissez les paramètres et cliquez sur "LANCER LA PRÉDICTION XGBOOST".</p></div>
        <div id="loadingResults" class="card" style="text-align: center; display: none;"><div class="loading-spinner"><i class="fas fa-spinner"></i></div><p style="margin-top: 20px; color: #6b7280;">Calcul en cours...</p></div>
    </div>
    
    <div id="historique" class="tab-content <?php echo $active_tab == 'historique' ? 'active-tab' : ''; ?>">
        <div class="flex-between"><h1 style="font-size: 32px; font-weight: 800;">Historique des analyses</h1><button class="btn-primary" style="width: auto; padding: 10px 20px;" onclick="exportToCSV()"><i class="fas fa-download"></i> Exporter CSV</button></div>
        <div class="card">
            <?php if (count($history_predictions) > 0): ?>
                <table class="history-table"><thead><tr><th>Date</th><th>CPU/RAM</th><th>Read/Write IOPS</th><th>Visiteurs</th><th>Score</th><th>Charge</th><th>Statut</th><th>Action</th></tr></thead>
                <tbody><?php foreach ($history_predictions as $pred): ?><tr><td><?php echo date('d/m/Y H:i', strtotime($pred['created_at'])); ?></td><td><?php echo $pred['cpu_usage_avg']; ?>/<?php echo $pred['ram_usage_avg']; ?>%</td><td><?php echo $pred['disk_read_iops']; ?>/<?php echo $pred['disk_write_iops']; ?></td><td><?php echo number_format($pred['visitors_per_day']); ?></td><td><span class="badge-status badge-optimal"><?php echo $pred['xgboost_score']; ?>%</span></td><td><?php echo $pred['predicted_load']; ?>%</td><td><span class="badge-status <?php echo $pred['status'] == 'CRITIQUE' ? 'badge-critical' : ($pred['status'] == 'ATTENTION' ? 'badge-warning' : 'badge-optimal'); ?>"><?php echo $pred['status']; ?></span></td><td><button class="btn-archive" onclick="archiverAnalyse('<?php echo $pred['id']; ?>')"><i class="fas fa-archive"></i> Archiver</button></td></tr><?php endforeach; ?></tbody>
                </table>
            <?php else: ?><p style="text-align: center; color: #9ca3af; padding: 60px;"><i class="fas fa-folder-open" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>Aucune analyse sauvegardée.</p><?php endif; ?>
        </div>
    </div>
    
    <div id="corbeille" class="tab-content <?php echo $active_tab == 'corbeille' ? 'active-tab' : ''; ?>">
        <h1 style="font-size: 32px; font-weight: 800; margin-bottom: 24px;">Corbeille</h1>
        <div class="card">
            <?php if (count($deleted_sauvegardes) > 0): ?>
                <table class="history-table"><thead><tr><th>Date</th><th>CPU/RAM</th><th>Score</th><th>Statut</th><th>Action</th></tr></thead>
                <tbody><?php foreach ($deleted_sauvegardes as $del): ?><tr><td><?php echo date('d/m/Y H:i', strtotime($del['created_at'])); ?></td><td><?php echo $del['cpu_usage_avg']; ?>/<?php echo $del['ram_usage_avg']; ?>%</td><td><?php echo $del['xgboost_score']; ?>%</td><td><?php echo $del['status']; ?></td><td><button class="btn-delete-red" onclick="supprimerDefinitivement('<?php echo $del['id']; ?>')"><i class="fas fa-trash"></i> Supprimer</button></td></tr><?php endforeach; ?></tbody>
                </table>
            <?php else: ?><p style="text-align: center; color: #9ca3af; padding: 60px;"><i class="fas fa-trash-alt" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>Corbeille vide.</p><?php endif; ?>
        </div>
    </div>
</div>

<div id="treeModal" class="modal" onclick="closeTreeModal()"><span class="close-modal" onclick="closeTreeModal()">&times;</span><img class="modal-content" id="treeModalImage"><div class="zoom-hint">🔍 Cliquez n'importe où pour fermer</div></div>
<div id="toast" class="toast-notification"></div>

<script>
let currentPrediction = null;

function showTab(tabId) {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tabId);
    window.history.pushState({}, '', url);
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active-tab'));
    document.querySelectorAll('.menu-item').forEach(menu => menu.classList.remove('active-menu'));
    document.getElementById(tabId).classList.add('active-tab');
    const tabNames = ['dashboard', 'resultats', 'historique', 'corbeille'];
    const index = tabNames.indexOf(tabId);
    if (index >= 0 && document.querySelectorAll('.menu-item')[index]) {
        document.querySelectorAll('.menu-item')[index].classList.add('active-menu');
    }
    sessionStorage.setItem('activeTab', tabId);
}

function showToast(message, isError = false) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.style.background = isError ? '#ef4444' : '#10b981';
    toast.style.display = 'block';
    setTimeout(() => { toast.style.display = 'none'; }, 4000);
}

function testPythonConnection() {
    showToast('🔧 Test de connexion Python en cours...');
    
    fetch(window.location.pathname + '?test_python=1', {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('✅ Python OK: ' + data.python_version);
            console.log('Test réussi:', data);
        } else {
            showToast('❌ Python non accessible', true);
            console.error('Test échoué:', data);
        }
    })
    .catch(error => {
        showToast('❌ Test échoué: ' + error.message, true);
        console.error('Test error:', error);
    });
}

function viewTreeImage(imagePath) {
    const modal = document.getElementById('treeModal');
    const modalImg = document.getElementById('treeModalImage');
    modal.style.display = 'block';
    modalImg.src = imagePath + '?t=' + new Date().getTime();
}

function closeTreeModal() { document.getElementById('treeModal').style.display = 'none'; }

function generateAndShowTree() {
    showToast('🌳 Génération des arbres de décision...');
    fetch(window.location.pathname + '?generate_tree=1', { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(response => response.json())
    .then(data => {
        if (data.success) { 
            displayTreeImages(); 
            showToast('✅ Arbres générés avec succès !'); 
        } else { 
            showToast('❌ ' + (data.error || 'Erreur lors de la génération'), true); 
            console.error('Tree generation error:', data); 
        }
    })
    .catch(error => { 
        showToast('❌ Erreur: ' + error.message, true); 
        console.error('Fetch error:', error); 
    });
}

function displayTreeImages() {
    const treeDiv = document.getElementById('treeDisplay');
    const timestamp = new Date().getTime();
    treeDiv.innerHTML = `<div class="tree-container"><div class="tree-title"><i class="fas fa-tree"></i> 🌳 Arbre 0</div><img src="xgboost_tree_0.png?t=${timestamp}" class="tree-image" onclick="viewTreeImage('xgboost_tree_0.png?t=${timestamp}')" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22300%22%3E%3Crect width=%22400%22 height=%22300%22 fill=%22%23f3f4f6%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 fill=%22%239ca3af%22%3EImage non disponible%3C/text%3E%3C/svg%3E'"></div>
    <div class="tree-container"><div class="tree-title"><i class="fas fa-tree"></i> 🌳 Dernier arbre</div><img src="xgboost_tree_final.png?t=${timestamp}" class="tree-image" onclick="viewTreeImage('xgboost_tree_final.png?t=${timestamp}')" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22300%22%3E%3Crect width=%22400%22 height=%22300%22 fill=%22%23f3f4f6%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 fill=%22%239ca3af%22%3EImage non disponible%3C/text%3E%3C/svg%3E'"></div>
    <div class="tree-container"><div class="tree-title"><i class="fas fa-tree"></i> 🌳 Arbre personnalisé</div><img src="xgboost_tree_custom.png?t=${timestamp}" class="tree-image" onclick="viewTreeImage('xgboost_tree_custom.png?t=${timestamp}')" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22300%22%3E%3Crect width=%22400%22 height=%22300%22 fill=%22%23f3f4f6%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 fill=%22%239ca3af%22%3EImage non disponible%3C/text%3E%3C/svg%3E'"></div>`;
}

function getFormParams() {
    const start = document.getElementById('peak_hours_start').value;
    const end = document.getElementById('peak_hours_end').value;
    let peakHours = 4;
    if (start && end) { 
        const startHour = parseInt(start.split(':')[0]); 
        const endHour = parseInt(end.split(':')[0]); 
        peakHours = Math.max(1, endHour - startHour); 
    }
    const heavyPlugins = [];
    document.querySelectorAll('#heavy_plugins_group input[type="checkbox"]:checked').forEach(checkbox => { heavyPlugins.push(checkbox.value); });
    return {
        cpu_usage_avg: parseFloat(document.getElementById('cpu_usage_avg').value) || 0,
        cpu_usage_peak: parseFloat(document.getElementById('cpu_usage_peak').value) || 0,
        ram_usage_avg: parseFloat(document.getElementById('ram_usage_avg').value) || 0,
        disk_read_iops: parseFloat(document.getElementById('disk_read_iops').value) || 0,
        disk_write_iops: parseFloat(document.getElementById('disk_write_iops').value) || 0,
        response_time: parseFloat(document.getElementById('response_time').value) || 0,
        visitors_per_day: parseFloat(document.getElementById('visitors_per_day').value) || 0,
        pageviews_per_day: parseFloat(document.getElementById('pageviews_per_day').value) || 0,
        traffic_growth_rate: parseFloat(document.getElementById('traffic_growth_rate').value) || 0,
        peak_hours_start: start, 
        peak_hours_end: end, 
        peak_hours: peakHours,
        plugin_count: parseFloat(document.getElementById('plugin_count').value) || 0,
        heavy_plugins: heavyPlugins.join(','),
        php_version: document.getElementById('php_version').value,
        cache_enabled: document.getElementById('cache_enabled').value,
        cdn_enabled: document.getElementById('cdn_enabled').value,
        wp_type: document.getElementById('wp_type').value
    };
}

function validateParams(params) {
    if (!params.cpu_usage_avg || !params.cpu_usage_peak || !params.ram_usage_avg || 
        !params.visitors_per_day || !params.traffic_growth_rate || !params.plugin_count || !params.wp_type) {
        showToast('❌ Veuillez remplir tous les champs obligatoires (*)', true);
        return false;
    }
    return true;
}

function saveParamsToJSON(params) {
    fetch(window.location.href, { 
        method: 'POST', 
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, 
        body: JSON.stringify(params) 
    }).catch(err => console.error('Save error:', err));
}

function runXGBoostPrediction() {
    const params = getFormParams();
    if (!validateParams(params)) return;
    
    saveParamsToJSON(params);
    
    document.getElementById('loadingResults').style.display = 'block';
    document.getElementById('resultsContainer').style.display = 'none';
    document.getElementById('noResults').style.display = 'none';
    showTab('resultats');
    
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 60000);
    
    fetch(window.location.pathname + '?run_python=1', {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        signal: controller.signal
    })
    .then(response => {
        clearTimeout(timeoutId);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.json();
    })
    .then(data => {
        document.getElementById('loadingResults').style.display = 'none';
        
        if (data.success === false || data.error) {
            showToast('❌ ' + (data.error || 'Erreur inconnue'), true);
            document.getElementById('noResults').style.display = 'block';
            console.error('Prediction error:', data);
            return;
        }
        
        currentPrediction = { 
            ...params, 
            predicted_load: data.predicted_load || 65, 
            xgboost_score: data.xgboost_score || 78, 
            predicted_saturation_months: data.predicted_saturation_months || 12, 
            status: data.status || 'ATTENTION', 
            recommendation: data.recommendation || 'Analyse terminée.', 
            tree_image: data.tree_image || null, 
            created_at: new Date().toISOString() 
        };
        
        displayResults(data);
        document.getElementById('resultsContainer').style.display = 'block';
        showToast('✅ Prédiction XGBoost terminée !');
    })
    .catch(error => {
        clearTimeout(timeoutId);
        document.getElementById('loadingResults').style.display = 'none';
        let errorMsg = error.name === 'AbortError' ? 'Timeout (60s)' : error.message;
        showToast('❌ Erreur: ' + errorMsg, true);
        document.getElementById('noResults').style.display = 'block';
        console.error('Fetch error:', error);
    });
}

function displayResults(data) {
    const scoresDiv = document.getElementById('scoresDisplay');
    const recommendationDiv = document.getElementById('recommendationDisplay');
    const statusClass = data.status === 'CRITIQUE' ? 'critical' : (data.status === 'ATTENTION' ? 'warning' : 'optimal');
    const statusColor = data.status === 'CRITIQUE' ? '#dc2626' : (data.status === 'ATTENTION' ? '#d97706' : '#10b981');
    let saturationText = data.predicted_saturation_months + ' mois';
    if (data.predicted_saturation_months === 999) saturationText = '♾️ Illimité';
    if (data.predicted_saturation_months === 0) saturationText = '⚠️ SATURÉ';
    
    scoresDiv.innerHTML = `<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 32px; text-align: center;">
        <div><div style="font-size: 14px; color: #6b7280;">🎯 Score XGBoost</div><div class="score-large" style="color: #3b82f6;">${data.xgboost_score}%</div><div class="gauge-container"><div class="gauge-fill ${statusClass}" style="width: ${data.xgboost_score}%"></div></div></div>
        <div><div style="font-size: 14px; color: #6b7280;">📊 Charge prédite</div><div class="score-large" style="color: ${statusColor};">${data.predicted_load}%</div><div class="gauge-container"><div class="gauge-fill ${statusClass}" style="width: ${data.predicted_load}%"></div></div></div>
        <div><div style="font-size: 14px; color: #6b7280;">⏰ Saturation</div><div class="score-large" style="color: #d97706; font-size: 32px;">${saturationText}</div><div style="margin-top: 15px;"><span class="badge-status ${data.status === 'CRITIQUE' ? 'badge-critical' : (data.status === 'ATTENTION' ? 'badge-warning' : 'badge-optimal')}">${data.status}</span></div></div>
    </div>`;
    
    recommendationDiv.innerHTML = `<div style="font-size: 16px; line-height: 1.6;"><i class="fas fa-lightbulb" style="color: #f59e0b; margin-right: 8px;"></i>${data.recommendation || 'Aucune recommandation.'}</div>`;
}

function saveCurrentResult() {
    if (!currentPrediction) { showToast('⚠️ Aucun résultat à sauvegarder', true); return; }
    const saveBtn = document.getElementById('saveResultBtn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sauvegarde...';
    fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(currentPrediction) })
    .then(response => response.json())
    .then(result => { 
        if (result.success) { showToast('✅ Analyse sauvegardée !'); setTimeout(() => window.location.reload(), 1500); } 
        else { showToast('❌ Erreur sauvegarde', true); saveBtn.disabled = false; saveBtn.innerHTML = '<i class="fas fa-save"></i> Sauvegarder'; } 
    })
    .catch(error => { showToast('❌ Erreur: ' + error, true); saveBtn.disabled = false; saveBtn.innerHTML = '<i class="fas fa-save"></i> Sauvegarder'; });
}

function archiverAnalyse(id) {
    if (!confirm('📦 Déplacer vers la corbeille ?')) return;
    fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ action: 'archive', archive_id: id }) })
    .then(response => response.json())
    .then(result => { if (result.success) { showToast('✅ Archivé'); setTimeout(() => window.location.reload(), 1000); } });
}

function supprimerDefinitivement(id) {
    if (!confirm('⚠️ Suppression définitive ?')) return;
    fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ action: 'delete', delete_id: id }) })
    .then(response => response.json())
    .then(result => { if (result.success) { showToast('🗑️ Supprimé'); setTimeout(() => window.location.reload(), 1000); } });
}

function exportToCSV() { window.location.href = window.location.pathname + '?export_full_csv=1'; }

document.addEventListener('DOMContentLoaded', function() {
    let tabToShow = new URLSearchParams(window.location.search).get('tab') || sessionStorage.getItem('activeTab') || 'dashboard';
    if (!['dashboard', 'resultats', 'historique', 'corbeille'].includes(tabToShow)) tabToShow = 'dashboard';
    showTab(tabToShow);
});
</script>
</body>
</html>
