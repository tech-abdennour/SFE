<?php
session_start();

// Vérification de la session utilisateur
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

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
        ram_usage_max TEXT,
        disk_usage_avg TEXT,
        disk_usage_max TEXT,
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
        save_type TEXT DEFAULT 'Manuel',
        is_deleted INTEGER DEFAULT 0
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS deleted_sauvegardes (
        id TEXT PRIMARY KEY,
        created_at TEXT,
        cpu_usage_avg TEXT,
        cpu_usage_peak TEXT,
        ram_usage_avg TEXT,
        ram_usage_max TEXT,
        disk_usage_avg TEXT,
        disk_usage_max TEXT,
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
        save_type TEXT DEFAULT 'Manuel',
        deleted_at TEXT
    )");
} catch (Exception $e) {
    error_log("SQLite error: " . $e->getMessage());
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
            id, created_at, cpu_usage_avg, cpu_usage_peak, ram_usage_avg, ram_usage_max, disk_usage_avg, disk_usage_max, disk_read_iops, disk_write_iops, response_time,
            visitors_per_day, pageviews_per_day, traffic_growth_rate, peak_hours_start, peak_hours_end, peak_hours,
            plugin_count, heavy_plugins, php_version, cache_enabled, cdn_enabled,
            wp_type, predicted_load, predicted_saturation_months, xgboost_score,
            status, recommendation, save_type, is_deleted
        ) VALUES (
            :id, :created_at, :cpu_usage_avg, :cpu_usage_peak, :ram_usage_avg, :ram_usage_max, :disk_usage_avg, :disk_usage_max, :disk_read_iops, :disk_write_iops, :response_time,
            :visitors_per_day, :pageviews_per_day, :traffic_growth_rate, :peak_hours_start, :peak_hours_end, :peak_hours,
            :plugin_count, :heavy_plugins, :php_version, :cache_enabled, :cdn_enabled,
            :wp_type, :predicted_load, :predicted_saturation_months, :xgboost_score,
            :status, :recommendation, :save_type, 0
        )");
        
        $stmt->execute([
            ':id' => $data['id'],
            ':created_at' => $data['created_at'],
            ':cpu_usage_avg' => $data['cpu_usage_avg'] ?? '',
            ':cpu_usage_peak' => $data['cpu_usage_peak'] ?? '',
            ':ram_usage_avg' => $data['ram_usage_avg'] ?? '',
            ':ram_usage_max' => $data['ram_usage_max'] ?? '',
            ':disk_usage_avg' => $data['disk_usage_avg'] ?? '',
            ':disk_usage_max' => $data['disk_usage_max'] ?? '',
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
            ':save_type' => $data['save_type'] ?? 'Manuel'
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Save error: " . $e->getMessage());
        return false;
    }
}

function archivePrediction($pdo, $id) {
    if ($pdo === null) return false;
    try {
        $stmt = $pdo->prepare("SELECT * FROM predictions WHERE id = :id AND is_deleted = 0");
        $stmt->execute([':id' => $id]);
        $pred = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pred) {
            $stmt2 = $pdo->prepare("INSERT INTO deleted_sauvegardes (
                id, created_at, cpu_usage_avg, cpu_usage_peak, ram_usage_avg, ram_usage_max, disk_usage_avg, disk_usage_max, disk_read_iops, disk_write_iops, response_time,
                visitors_per_day, pageviews_per_day, traffic_growth_rate, peak_hours_start, peak_hours_end, peak_hours,
                plugin_count, heavy_plugins, php_version, cache_enabled, cdn_enabled,
                wp_type, predicted_load, predicted_saturation_months, xgboost_score,
                status, recommendation, save_type, deleted_at
            ) VALUES (
                :id, :created_at, :cpu_usage_avg, :cpu_usage_peak, :ram_usage_avg, :ram_usage_max, :disk_usage_avg, :disk_usage_max, :disk_read_iops, :disk_write_iops, :response_time,
                :visitors_per_day, :pageviews_per_day, :traffic_growth_rate, :peak_hours_start, :peak_hours_end, :peak_hours,
                :plugin_count, :heavy_plugins, :php_version, :cache_enabled, :cdn_enabled,
                :wp_type, :predicted_load, :predicted_saturation_months, :xgboost_score,
                :status, :recommendation, :save_type, :deleted_at
            )");
            
            $stmt2->execute([
                ':id' => $pred['id'],
                ':created_at' => $pred['created_at'],
                ':cpu_usage_avg' => $pred['cpu_usage_avg'],
                ':cpu_usage_peak' => $pred['cpu_usage_peak'],
                ':ram_usage_avg' => $pred['ram_usage_avg'],
                ':ram_usage_max' => $pred['ram_usage_max'],
                ':disk_usage_avg' => $pred['disk_usage_avg'],
                ':disk_usage_max' => $pred['disk_usage_max'],
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
                ':save_type' => $pred['save_type'] ?? 'Manuel',
                ':deleted_at' => date('Y-m-d H:i:s')
            ]);
            
            $stmt3 = $pdo->prepare("DELETE FROM predictions WHERE id = :id");
            $stmt3->execute([':id' => $id]);
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Archive error: " . $e->getMessage());
        return false;
    }
}

function deletePermanently($pdo, $id) {
    if ($pdo === null) return false;
    try {
        $stmt = $pdo->prepare("DELETE FROM deleted_sauvegardes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return true;
    } catch (Exception $e) {
        error_log("Delete error: " . $e->getMessage());
        return false;
    }
}

function restorePrediction($pdo, $id) {
    if ($pdo === null) return false;
    try {
        $stmt = $pdo->prepare("SELECT * FROM deleted_sauvegardes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $pred = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pred) {
            $stmt2 = $pdo->prepare("INSERT INTO predictions (
                id, created_at, cpu_usage_avg, cpu_usage_peak, ram_usage_avg, ram_usage_max, disk_usage_avg, disk_usage_max, disk_read_iops, disk_write_iops, response_time,
                visitors_per_day, pageviews_per_day, traffic_growth_rate, peak_hours_start, peak_hours_end, peak_hours,
                plugin_count, heavy_plugins, php_version, cache_enabled, cdn_enabled,
                wp_type, predicted_load, predicted_saturation_months, xgboost_score,
                status, recommendation, save_type, is_deleted
            ) VALUES (
                :id, :created_at, :cpu_usage_avg, :cpu_usage_peak, :ram_usage_avg, :ram_usage_max, :disk_usage_avg, :disk_usage_max, :disk_read_iops, :disk_write_iops, :response_time,
                :visitors_per_day, :pageviews_per_day, :traffic_growth_rate, :peak_hours_start, :peak_hours_end, :peak_hours,
                :plugin_count, :heavy_plugins, :php_version, :cache_enabled, :cdn_enabled,
                :wp_type, :predicted_load, :predicted_saturation_months, :xgboost_score,
                :status, :recommendation, :save_type, 0
            )");
            
            $stmt2->execute([
                ':id' => $pred['id'],
                ':created_at' => $pred['created_at'],
                ':cpu_usage_avg' => $pred['cpu_usage_avg'],
                ':cpu_usage_peak' => $pred['cpu_usage_peak'],
                ':ram_usage_avg' => $pred['ram_usage_avg'],
                ':ram_usage_max' => $pred['ram_usage_max'],
                ':disk_usage_avg' => $pred['disk_usage_avg'],
                ':disk_usage_max' => $pred['disk_usage_max'],
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
                ':save_type' => $pred['save_type'] ?? 'Manuel'
            ]);
            
            $stmt3 = $pdo->prepare("DELETE FROM deleted_sauvegardes WHERE id = :id");
            $stmt3->execute([':id' => $id]);
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Restore error: " . $e->getMessage());
        return false;
    }
}

function emptyTrash($pdo) {
    if ($pdo === null) return false;
    try {
        $stmt = $pdo->prepare("DELETE FROM deleted_sauvegardes");
        $stmt->execute();
        return true;
    } catch (Exception $e) {
        error_log("Empty trash error: " . $e->getMessage());
        return false;
    }
}

// --- ROUTES AJAX ---
if (isset($_GET['export_full_csv']) && isset($_SESSION['user'])) {
    $predictions = getPredictions($pdo);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="export_' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Pack', 'Visiteurs/j', 'Croissance', 'CPU', 'RAM', 'Plugins', 'Score XGBoost', 'Charge', 'Statut', 'Sauvegarde']);
    
    foreach ($predictions as $pred) {
        fputcsv($output, [
            $pred['created_at'] ?? '',
            $pred['wp_type'] ?? '',
            $pred['visitors_per_day'] ?? '',
            $pred['traffic_growth_rate'] ?? '',
            $pred['cpu_usage_avg'] ?? '',
            $pred['ram_usage_avg'] ?? '',
            $pred['plugin_count'] ?? '',
            $pred['xgboost_score'] ?? '',
            $pred['predicted_load'] ?? '',
            $pred['status'] ?? '',
            $pred['save_type'] ?? 'Manuel'
        ]);
    }
    fclose($output);
    exit();
}

// --- SAUVEGARDE DES PARAMÈTRES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if ($data && isset($_SESSION['user'])) {
        if (isset($data['action'])) {
            if ($data['action'] === 'delete' && isset($data['delete_id'])) {
                echo json_encode(['success' => deletePermanently($pdo, $data['delete_id'])]);
                exit();
            }
            if ($data['action'] === 'archive' && isset($data['archive_id'])) {
                echo json_encode(['success' => archivePrediction($pdo, $data['archive_id'])]);
                exit();
            }
            if ($data['action'] === 'restore' && isset($data['restore_id'])) {
                echo json_encode(['success' => restorePrediction($pdo, $data['restore_id'])]);
                exit();
            }
            if ($data['action'] === 'empty_trash') {
                echo json_encode(['success' => emptyTrash($pdo)]);
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
                'ram_usage_max' => $data['ram_usage_max'] ?? '',
                'disk_usage_avg' => $data['disk_usage_avg'] ?? '',
                'disk_usage_max' => $data['disk_usage_max'] ?? '',
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
                'save_type' => 'Manuel'
            ];
            echo json_encode(['success' => savePrediction($pdo, $prediction)]);
            exit();
        }
        
        // CORRECTION : Export des paramètres en JSON
        $jsonFolder = __DIR__ . '/Donnee_parametres';
        if (!file_exists($jsonFolder)) mkdir($jsonFolder, 0777, true);
        
        $filename = $jsonFolder . '/parameters_' . date('Y-m-d_H-i-s') . '.json';
        $dataToSave = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user' => $_SESSION['user'],
            'parameters' => $data
        ];
        
        if (file_put_contents($filename, json_encode($dataToSave, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            echo json_encode(['status' => 'success', 'message' => 'Paramètres sauvegardés', 'file' => $filename]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Erreur sauvegarde']);
        }
        exit();
    }
    echo json_encode(['success' => false]);
    exit();
}

// Gestion déconnexion
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    
    if (isset($_COOKIE['remember_user'])) {
        setcookie("remember_user", "", time() - 3600, "/");
    }
    
    header("Location: index.php?logout=1");
    exit();
}

$history_predictions = getPredictions($pdo);
$deleted_sauvegardes = getDeletedSauvegardes($pdo);
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : (isset($_SESSION['last_tab']) ? $_SESSION['last_tab'] : 'dashboard');
$_SESSION['last_tab'] = $active_tab;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <title>Vala Bleu - Dashboard</title>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header"><h2>VALA BLEU</h2><p>DASHBOARD</p></div>
    <div class="menu-item" onclick="showTab('dashboard')">Paramètres</div>
    <div class="menu-item" onclick="showTab('resultats')">Résultats</div>
    <div class="menu-item" onclick="showTab('historique')">Historique</div>
    <div class="menu-item" onclick="showTab('corbeille')">Corbeille</div>
    <a href="?logout=1" class="logout-link">Déconnexion</a>
</div>

<div class="main-content">
    <div id="dashboard" class="tab-content">
        <div class="page-title"><h1>Analyse des paramètres</h1><p>Configuration pour l'analyse de charge WordPress</p></div>
        
        <div class="param-section">
            <h4>Trafic</h4>
            <div class="grid-4">
                <div class="form-group"><label>Visiteurs/jour *</label><input type="number" id="visitors_per_day" value="5000" placeholder="Ex: 5000"></div>
                <div class="form-group"><label>Pages vues/jour</label><input type="number" id="pageviews_per_day" value="15000" placeholder="Ex: 15000"></div>
                <div class="form-group"><label>Taux Croissance (%) *</label><input type="number" id="traffic_growth_rate" value="15" placeholder="Ex: 15"></div>
                <div class="form-group"><label>Pics horaires</label><div style="display: flex; gap: 10px;"><input type="time" id="peak_hours_start" value="09:00"><span>à</span><input type="time" id="peak_hours_end" value="18:00"></div></div>
            </div>
        </div>
        
        <div class="param-section">
            <h4>Ressources Serveur</h4>
            <div class="grid-4">
                <div class="form-group"><label>CPU moyen (%) *</label><input type="number" id="cpu_usage_avg" value="45" placeholder="Ex: 45"></div>
                <div class="form-group"><label>CPU max (%) *</label><input type="number" id="cpu_usage_peak" value="75" placeholder="Ex: 75"></div>
                <div class="form-group"><label>RAM moyenne (%) *</label><input type="number" id="ram_usage_avg" value="60" placeholder="Ex: 60"></div>
                <div class="form-group"><label>RAM max (%) *</label><input type="number" id="ram_usage_max" value="85" placeholder="Ex: 85"></div>
                <div class="form-group"><label>Disque utilisé (%)</label><input type="number" id="disk_usage_avg" value="45" placeholder="Ex: 45"></div>
                <div class="form-group"><label>Disque max (%)</label><input type="number" id="disk_usage_max" value="70" placeholder="Ex: 70"></div>
                <div class="form-group"><label>Temps réponse (ms)</label><input type="number" id="response_time" value="350" placeholder="Ex: 350"></div>
                <div class="form-group"><label>I/O Disque (IOPS)</label><div class="double-input"><div class="input-half"><label>Read IOPS</label><input type="number" id="disk_read_iops" value="150" placeholder="Read"></div><div class="input-half"><label>Write IOPS</label><input type="number" id="disk_write_iops" value="80" placeholder="Write"></div></div></div>
            </div>
        </div>
        
        <div class="param-section">
            <h4>WordPress</h4>
            <div class="grid-4">
                <div class="form-group"><label>Nombre de plugins *</label><input type="number" id="plugin_count" value="25" placeholder="Ex: 25"></div>
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
                <div class="form-group"><label>Pack WordPress *</label><select id="wp_type"><option value="small">SMALL</option><option value="medium" selected>MEDIUM</option><option value="performance">PERFORMANCE</option><option value="enterprise">ENTERPRISE</option></select></div>
            </div>
        </div>
        
        <div class="card">
            <button class="btn-primary" onclick="runAnalysis()">LANCER L'ANALYSE</button>
        </div>
    </div>
    
    <div id="resultats" class="tab-content">
        <div class="page-title"><h1>Résultats</h1><p>Analyse basée sur les paramètres fournis</p></div>
        <div id="resultsContainer" style="display: none;">
            <div class="card"><h3>Scores de Performance</h3><div id="scoresDisplay"></div></div>
            <div class="card"><h3>Recommandation</h3><div id="recommendationDisplay"></div></div>
            <div class="card"><button class="btn-primary btn-save" id="saveResultBtn" onclick="saveCurrentResult()">Sauvegarder cette analyse</button></div>
        </div>
                <!-- SECTION DES GRAPHIQUES - AFFICHÉE DIRECTEMENT DEPUIS L'API -->
        <div class="card" id="imagesContainer" style="display: none;">
            <h3>Graphiques d'analyse</h3>
            <div id="imagesDisplay" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-top: 20px;">
            </div>
        </div>
    <div class="card" id="treeDownloads">
        <h3>Téléchargement des arbres XGBoost</h3>
        <div style="display:flex; flex-direction:column; gap:10px; margin-top:10px;">
            <a href="http://localhost:8000/download/tree0">
                📥 Télécharger Tree 0
            </a>

            <a href="http://localhost:8000/download/tree-final">
                📥 Télécharger Tree Final
            </a>
        </div>
    </div>
        <div id="noResults" class="card"><p>Aucune analyse générée.</p><p>Remplissez les paramètres et cliquez sur "LANCER L'ANALYSE".</p></div>
        <div id="loadingResults" class="card" style="display: none;"><p>Calcul en cours...</p></div>
    </div>
    
    <div id="historique" class="tab-content">
        <div class="historique-page">
            <div class="config-card">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Date</th><th>Pack</th><th>Visiteurs/j</th><th>Croissance</th><th>CPU/RAM</th><th>Plugins</th><th>XGBoost</th><th>Sauvegarde</th><th>Statut</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($history_predictions) > 0): ?>
                            <?php foreach ($history_predictions as $pred): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($pred['created_at'])); ?></td>
                                    <td><?php echo ucfirst($pred['wp_type'] ?? 'N/A'); ?></td>
                                    <td><?php echo number_format($pred['visitors_per_day']); ?></td>
                                    <td><?php echo $pred['traffic_growth_rate']; ?>%</td>
                                    <td><?php echo $pred['cpu_usage_avg']; ?>% / <?php echo $pred['ram_usage_avg']; ?>%</td>
                                    <td><?php echo $pred['plugin_count']; ?></td>
                                    <td><?php echo $pred['xgboost_score']; ?>%</td>
                                    <td><?php echo $pred['save_type'] ?? 'Manuel'; ?></td>
                                    <td><?php echo $pred['status']; ?></td>
                                    <td>
                                        <button class="btn-icon" title="Archiver" onclick="archiverAnalyse('<?php echo $pred['id']; ?>')">Archiver</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="10">Aucune analyse sauvegardée.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div id="corbeille" class="tab-content">
        <div class="corbeille-page">
            <div class="config-card">
                <div>
                    <h4>Éléments supprimés</h4>
                    <button class="btn-primary" onclick="viderCorbeille()">Vider la corbeille</button>
                </div>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Date</th><th>Pack</th><th>Visiteurs/j</th><th>Croissance</th><th>CPU/RAM</th><th>Plugins</th><th>XGBoost</th><th>Sauvegarde</th><th>Statut</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($deleted_sauvegardes) > 0): ?>
                            <?php foreach ($deleted_sauvegardes as $del): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($del['created_at'])); ?></td>
                                    <td><?php echo ucfirst($del['wp_type'] ?? 'N/A'); ?></td>
                                    <td><?php echo number_format($del['visitors_per_day']); ?></td>
                                    <td><?php echo $del['traffic_growth_rate']; ?>%</td>
                                    <td><?php echo $del['cpu_usage_avg']; ?>% / <?php echo $del['ram_usage_avg']; ?>%</td>
                                    <td><?php echo $del['plugin_count']; ?></td>
                                    <td><?php echo $del['xgboost_score']; ?>%</td>
                                    <td><?php echo $del['save_type'] ?? 'Manuel'; ?></td>
                                    <td>Supprimé</td>
                                    <td>
                                        <button title="Restaurer" onclick="restaurerAnalyse('<?php echo $del['id']; ?>')">Restaurer</button>
                                        <button title="Supprimer définitivement" onclick="supprimerDefinitivement('<?php echo $del['id']; ?>')">Supprimer</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="10">Corbeille vide.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

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
    toast.style.display = 'block';
    setTimeout(() => { toast.style.display = 'none'; }, 4000);
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
        ram_usage_max: parseFloat(document.getElementById('ram_usage_max').value) || 0,
        disk_usage_avg: parseFloat(document.getElementById('disk_usage_avg').value) || 0,
        disk_usage_max: parseFloat(document.getElementById('disk_usage_max').value) || 0,
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
    if (!params.cpu_usage_avg || !params.cpu_usage_peak || !params.ram_usage_avg || !params.ram_usage_max ||
        !params.visitors_per_day || !params.traffic_growth_rate || !params.plugin_count || !params.wp_type) {
        showToast('Veuillez remplir tous les champs obligatoires (*)', true);
        return false;
    }
    return true;
}

// CORRECTION : Sauvegarde des paramètres en JSON avant l'appel API
function saveParamsToJSON(params) {
    return fetch(window.location.pathname, { 
        method: 'POST', 
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, 
        body: JSON.stringify(params) 
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            console.log('Paramètres sauvegardés:', data.file);
            return true;
        } else {
            console.error('Erreur sauvegarde paramètres:', data.message);
            return false;
        }
    })
    .catch(err => {
        console.error('Save error:', err);
        return false;
    });
}
// FONCTION : Afficher les images directement depuis la réponse de l'API
function displayImages(images) {
    const imagesContainer = document.getElementById('imagesContainer');
    const imagesDisplay = document.getElementById('imagesDisplay');
    
    if (!images || images.length === 0) {
        imagesContainer.style.display = 'none';
        return;
    }
    
    const imageNames = {
        'tree': 'Arbre de décision',
        'correlation': 'Matrice de corrélation',
        'dashboard': 'Dashboard',
        'feature_importance': 'Importance des caractéristiques'
    };
    
    let html = '';
    
    images.forEach(function(image) {
        const displayName = imageNames[image.type] || image.type;
        const imageUrl = image.url;
        const imageName = imageUrl.split('/').pop();
        
        html += '<div style="border: 1px solid #ddd; padding: 10px; border-radius: 8px; text-align: center; background: white;">' +
            '<h4 style="margin-top: 0;">' + displayName + '</h4>' +
            '<img src="' + imageUrl + '" alt="' + displayName + '" style="max-width: 100%; height: auto; border-radius: 4px; cursor: pointer;" onclick="window.open(\'' + imageUrl + '\', \'_blank\')" onerror="this.parentElement.innerHTML=\'<p style=color:red;>❌ Erreur chargement:<br>' + imageUrl + '</p>\'">' +
            '<p style="font-size: 11px; color: #666; margin-top: 8px; word-break: break-all;">' +
                imageName +
            '</p>' +
        '</div>';
    });
    
    imagesDisplay.innerHTML = html;
    imagesContainer.style.display = 'block';
}

function runAnalysis() {
    const params = getFormParams();
    if (!validateParams(params)) return;

    document.getElementById('loadingResults').style.display = 'block';
    document.getElementById('resultsContainer').style.display = 'none';
    document.getElementById('noResults').style.display = 'none';

    showTab('resultats');

    saveParamsToJSON(params).then(() => {
        fetch("http://localhost:8000/predict/from-file")
            .then(response => response.json())
            .then(res => {
                console.log('📦 Réponse API complète:', res);
                
                document.getElementById('loadingResults').style.display = 'none';

                if (res.status === "success") {
                    const data = res.output.result;
                    const images = res.output.images;
                    
                    currentPrediction = data;
                    
                    // Afficher les scores
                    displayResults(data);
                    
                    // Afficher les images
                    displayImages(images);
                    
                    document.getElementById('resultsContainer').style.display = 'block';
                    showToast('✅ Prédiction terminée');
                    
                } else {
                    showToast('❌ Erreur API', true);
                    document.getElementById('noResults').style.display = 'block';
                }
            })
            .catch(err => {
                document.getElementById('loadingResults').style.display = 'none';
                showToast('❌ API indisponible', true);
                console.error(err);
            });
    });
}
function loadTrees() {
    const container = document.getElementById("imagesDisplay");

    const html = `
        <div style="margin-top:20px; display:flex; flex-direction:column; gap:15px;">

            <h4>🌳 Arbres XGBoost</h4>

            <img src="/analysis_exports/xgboost_tree_0.png"
                 style="max-width:100%; border-radius:8px; border:1px solid #ddd;">

            <img src="/analysis_exports/xgboost_tree_final.png"
                 style="max-width:100%; border-radius:8px; border:1px solid #ddd;">

        </div>
    `;

    container.innerHTML += html;
}
function displayResults(data) {
    let saturationText = data.predicted_saturation_months + ' mois';
    if (data.predicted_saturation_months === 999) saturationText = 'Illimité';
    if (data.predicted_saturation_months === 0) saturationText = 'SATURÉ';
    
    document.getElementById('scoresDisplay').innerHTML = 
        '<div>' +
            '<div>Score: ' + data.xgboost_score + '%</div>' +
            '<div>Charge prédite: ' + data.predicted_load + '%</div>' +
            '<div>Saturation: ' + saturationText + '</div>' +
            '<div>Statut: ' + data.status + '</div>' +
        '</div>';
    
    document.getElementById('recommendationDisplay').innerHTML = 
        '<div>' + (data.recommendation || 'Aucune recommandation.') + '</div>';
}

function saveCurrentResult() {
    if (!currentPrediction) { showToast('Aucun résultat à sauvegarder', true); return; }
    const saveBtn = document.getElementById('saveResultBtn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = 'Sauvegarde...';
    fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(currentPrediction) })
    .then(response => response.json())
    .then(result => { 
        if (result.success) { showToast('Analyse sauvegardée !'); setTimeout(() => window.location.reload(), 1500); } 
        else { showToast('Erreur sauvegarde', true); saveBtn.disabled = false; saveBtn.innerHTML = 'Sauvegarder'; } 
    })
    .catch(error => { showToast('Erreur: ' + error, true); saveBtn.disabled = false; saveBtn.innerHTML = 'Sauvegarder'; });
}

function archiverAnalyse(id) {
    if (!confirm('Déplacer vers la corbeille ?')) return;
    fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ action: 'archive', archive_id: id }) })
    .then(response => response.json())
    .then(result => { if (result.success) { showToast('Archivé'); setTimeout(() => window.location.reload(), 1000); } });
}

function restaurerAnalyse(id) {
    if (!confirm('Restaurer cette analyse ?')) return;
    fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ action: 'restore', restore_id: id }) })
    .then(response => response.json())
    .then(result => { if (result.success) { showToast('Restauré'); setTimeout(() => window.location.reload(), 1000); } });
}

function supprimerDefinitivement(id) {
    if (!confirm('Suppression définitive ?')) return;
    fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ action: 'delete', delete_id: id }) })
    .then(response => response.json())
    .then(result => { if (result.success) { showToast('Supprimé'); setTimeout(() => window.location.reload(), 1000); } });
}

function viderCorbeille() {
    if (!confirm('Vider toute la corbeille ? Cette action est irréversible.')) return;
    fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ action: 'empty_trash' }) })
    .then(response => response.json())
    .then(result => { if (result.success) { showToast('Corbeille vidée'); setTimeout(() => window.location.reload(), 1000); } });
}

document.addEventListener('DOMContentLoaded', function() {
    let tabToShow = new URLSearchParams(window.location.search).get('tab') || sessionStorage.getItem('activeTab') || 'dashboard';
    if (!['dashboard', 'resultats', 'historique', 'corbeille'].includes(tabToShow)) tabToShow = 'dashboard';
    showTab(tabToShow);
});
</script>
</body>
</html>