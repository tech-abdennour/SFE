bon se code il permet de telecharger le json des données remplis dans saisie des paramètres après avoir cliquée sur lancer prédiction xgboost mias je veux qu'elle se telecharge içi C:\Users\algebra\Desktop\TP_CODE\SFE\sfe-docker-nginx\app pas dans telechargement :
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
        cpu_usage_avg TEXT,
        cpu_usage_peak TEXT,
        ram_usage_avg TEXT,
        disk_io TEXT,
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
        is_deleted INTEGER DEFAULT 0
    )");
    
    // Création de la table des sauvegardes supprimées (corbeille)
    $pdo->exec("CREATE TABLE IF NOT EXISTS deleted_sauvegardes (
        id TEXT PRIMARY KEY,
        created_at TEXT,
        cpu_usage_avg TEXT,
        cpu_usage_peak TEXT,
        ram_usage_avg TEXT,
        disk_io TEXT,
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
        deleted_at TEXT
    )");
} catch (Exception $e) {
    error_log("SQLite error: " . $e->getMessage());
}

// --- MODÈLE XGBOOST SIMULÉ ---
class XGBoostPredictor {
    
    private $feature_weights = [
        'cpu_usage_avg' => 0.15,
        'cpu_usage_peak' => 0.12,
        'ram_usage_avg' => 0.13,
        'disk_io' => 0.05,
        'response_time' => 0.08,
        'visitors_per_day' => 0.10,
        'pageviews_per_day' => 0.08,
        'traffic_growth_rate' => 0.12,
        'peak_hours' => 0.04,
        'plugin_count' => 0.06,
        'heavy_plugins' => 0.04,
        'php_version' => 0.02,
        'cache_enabled' => 0.03,
        'cdn_enabled' => 0.03,
        'wp_type' => 0.05
    ];
    
    private $heavy_plugins_risk = [
        'woocommerce' => 0.25,
        'elementor' => 0.20,
        'wpml' => 0.15,
        'yoast' => 0.10,
        'revslider' => 0.12,
        'gravityforms' => 0.08
    ];
    
    private $php_scores = [
        '7.4' => 0.85,
        '8.0' => 0.90,
        '8.1' => 0.95,
        '8.2' => 1.00,
        '8.3' => 1.05
    ];
    
    private $pack_capacity = [
        'small' => ['max_visitors' => 10000, 'base_load' => 40, 'recommended_cpu' => 70],
        'medium' => ['max_visitors' => 50000, 'base_load' => 30, 'recommended_cpu' => 65],
        'performance' => ['max_visitors' => 1000000, 'base_load' => 20, 'recommended_cpu' => 50],
        'enterprise' => ['max_visitors' => 9999999, 'base_load' => 15, 'recommended_cpu' => 40]
    ];
    
    public function predict($params) {
        $score = 0;
        
        $cpu_avg = floatval($params['cpu_usage_avg'] ?? 50);
        $cpu_peak = floatval($params['cpu_usage_peak'] ?? 70);
        $ram_avg = floatval($params['ram_usage_avg'] ?? 50);
        
        $cpu_score = ($cpu_avg * 0.6 + $cpu_peak * 0.4) / 100;
        $score += $cpu_score * 15 * $this->feature_weights['cpu_usage_avg'];
        
        $disk_io = floatval($params['disk_io'] ?? 50);
        $score += ($disk_io / 100) * 5 * $this->feature_weights['disk_io'];
        
        $response_time = floatval($params['response_time'] ?? 500);
        $response_score = min(1, $response_time / 2000);
        $score += $response_score * 8 * $this->feature_weights['response_time'];
        
        $visitors = floatval($params['visitors_per_day'] ?? 1000);
        $pageviews = floatval($params['pageviews_per_day'] ?? 3000);
        $growth_rate = floatval($params['traffic_growth_rate'] ?? 10);
        
        $visitors_score = min(1, $visitors / 50000);
        $pageviews_score = min(1, $pageviews / 150000);
        $score += ($visitors_score * 0.5 + $pageviews_score * 0.5) * 10 * $this->feature_weights['visitors_per_day'];
        
        $growth_score = min(1, $growth_rate / 100);
        $score += $growth_score * 12 * $this->feature_weights['traffic_growth_rate'];
        
        $plugin_count = intval($params['plugin_count'] ?? 20);
        $plugin_score = min(1, $plugin_count / 50);
        $score += $plugin_score * 6 * $this->feature_weights['plugin_count'];
        
        $heavy_plugins = explode(',', strtolower($params['heavy_plugins'] ?? ''));
        $heavy_risk = 0;
        foreach ($heavy_plugins as $plugin) {
            $plugin = trim($plugin);
            if (isset($this->heavy_plugins_risk[$plugin])) {
                $heavy_risk += $this->heavy_plugins_risk[$plugin];
            }
        }
        $heavy_risk = min(1, $heavy_risk);
        $score += $heavy_risk * 4 * $this->feature_weights['heavy_plugins'];
        
        $php_version = $params['php_version'] ?? '7.4';
        $php_score = $this->php_scores[$php_version] ?? 0.85;
        $php_penalty = (1 - ($php_score - 0.8) / 0.3);
        $php_penalty = max(0, min(1, $php_penalty));
        $score += $php_penalty * 2 * $this->feature_weights['php_version'];
        
        $cache_enabled = ($params['cache_enabled'] ?? 'non') === 'oui';
        if (!$cache_enabled) {
            $score += 3 * $this->feature_weights['cache_enabled'];
        }
        
        $cdn_enabled = ($params['cdn_enabled'] ?? 'non') === 'oui';
        if (!$cdn_enabled) {
            $score += 3 * $this->feature_weights['cdn_enabled'];
        }
        
        $wp_type = $params['wp_type'] ?? 'medium';
        $pack_factor = 1 - ($this->pack_capacity[$wp_type]['base_load'] / 100);
        $score += $pack_factor * 5 * $this->feature_weights['wp_type'];
        
        $peak_hours = intval($params['peak_hours'] ?? 4);
        $peak_score = min(1, $peak_hours / 12);
        $score += $peak_score * 4 * $this->feature_weights['peak_hours'];
        
        $final_score = min(100, max(0, $score * 100));
        
        $predicted_load = min(100, max(0, 
            $cpu_avg * 0.3 + 
            $cpu_peak * 0.2 + 
            $ram_avg * 0.2 + 
            $final_score * 0.3
        ));
        
        $saturation_months = $this->calculateSaturationMonths($predicted_load, $growth_rate, $wp_type);
        
        return [
            'xgboost_score' => round($final_score, 1),
            'predicted_load' => round($predicted_load, 1),
            'saturation_months' => $saturation_months,
            'confidence' => round(85 - ($final_score * 0.3), 1)
        ];
    }
    
    private function calculateSaturationMonths($current_load, $growth_rate, $wp_type) {
        $threshold = 90;
        $capacity_buffer = $this->pack_capacity[$wp_type]['recommended_cpu'] ?? 70;
        
        if ($current_load >= $threshold) {
            return 0;
        }
        
        if ($growth_rate <= 0) {
            return 999;
        }
        
        $months = log($threshold / max(1, $current_load)) / log(1 + $growth_rate / 100);
        
        if ($wp_type === 'small') {
            $months = $months * 0.8;
        } elseif ($wp_type === 'medium') {
            $months = $months * 0.9;
        } elseif ($wp_type === 'performance') {
            $months = $months * 1.2;
        }
        
        return max(0, round($months));
    }
    
    public function getRecommendation($predicted_load, $saturation_months, $wp_type, $params) {
        $cpu_avg = floatval($params['cpu_usage_avg'] ?? 50);
        $ram_avg = floatval($params['ram_usage_avg'] ?? 50);
        $growth = floatval($params['traffic_growth_rate'] ?? 10);
        
        $recommendations = [];
        
        if ($predicted_load >= 90 || $saturation_months <= 1) {
            $recommendations[] = "🔴 **URGENT** : Saturation immédiate détectée. Migration vers un pack supérieur REQUISE dans les 48h.";
            $recommendations[] = "👉 Recommandation : Passer de " . strtoupper($wp_type) . " à " . $this->getNextPack($wp_type);
        }
        elseif ($predicted_load >= 80 || $saturation_months <= 3) {
            $recommendations[] = "🟠 **CRITIQUE** : Saturation prévue dans {$saturation_months} mois. Planifiez une migration immédiate.";
            $recommendations[] = "👉 Recommandation : Migration recommandée vers " . $this->getNextPack($wp_type);
        }
        elseif ($predicted_load >= 70 || $saturation_months <= 6) {
            $recommendations[] = "🟡 **ATTENTION** : Saturation dans {$saturation_months} mois. Une migration sera nécessaire.";
            $recommendations[] = "👉 Recommandation : Commencer à planifier la migration vers " . $this->getNextPack($wp_type);
        }
        else {
            $recommendations[] = "🟢 **OPTIMAL** : Infrastructure stable pour {$saturation_months} mois.";
            $recommendations[] = "👉 Recommandation : Pas de migration immédiate requise. Réévaluer dans 3 mois.";
        }
        
        if ($cpu_avg > 75) {
            $recommendations[] = "⚠️ Charge CPU élevée ({$cpu_avg}%) - Envisagez l'optimisation des requêtes ou un CPU dédié.";
        }
        
        if ($ram_avg > 80) {
            $recommendations[] = "⚠️ Mémoire RAM saturée ({$ram_avg}%) - Augmentez la mémoire ou optez pour Redis/Memcached.";
        }
        
        if (($params['cache_enabled'] ?? 'non') !== 'oui') {
            $recommendations[] = "💡 Activez un cache WordPress (Redis/LiteSpeed) pour réduire la charge serveur de 30-50%.";
        }
        
        if (($params['cdn_enabled'] ?? 'non') !== 'oui') {
            $recommendations[] = "🌍 Activez un CDN pour réduire la charge sur les ressources statiques.";
        }
        
        $plugin_count = intval($params['plugin_count'] ?? 0);
        if ($plugin_count > 30) {
            $recommendations[] = "🔌 Trop de plugins actifs ({$plugin_count}) - Nettoyez les plugins inutilisés pour améliorer les performances.";
        }
        
        $php_version = $params['php_version'] ?? '7.4';
        if (version_compare($php_version, '8.0', '<')) {
            $recommendations[] = "🐘 Mettez à jour PHP vers la version 8.2+ pour un gain de performances de 20-30%.";
        }
        
        return implode('<br>', $recommendations);
    }
    
    private function getNextPack($current) {
        $packs = ['small', 'medium', 'performance', 'enterprise'];
        $current_index = array_search($current, $packs);
        if ($current_index !== false && $current_index < count($packs) - 1) {
            return strtoupper($packs[$current_index + 1]);
        }
        return "ENTERPRISE (serveur dédié haute performance)";
    }
}

$xgboost = new XGBoostPredictor();

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
        $stmt = $pdo->prepare("INSERT INTO predictions (
            id, created_at, cpu_usage_avg, cpu_usage_peak, ram_usage_avg, disk_io, response_time,
            visitors_per_day, pageviews_per_day, traffic_growth_rate, peak_hours_start, peak_hours_end, peak_hours,
            plugin_count, heavy_plugins, php_version, cache_enabled, cdn_enabled,
            wp_type, predicted_load, predicted_saturation_months, xgboost_score,
            status, recommendation, is_deleted
        ) VALUES (
            :id, :created_at, :cpu_usage_avg, :cpu_usage_peak, :ram_usage_avg, :disk_io, :response_time,
            :visitors_per_day, :pageviews_per_day, :traffic_growth_rate, :peak_hours_start, :peak_hours_end, :peak_hours,
            :plugin_count, :heavy_plugins, :php_version, :cache_enabled, :cdn_enabled,
            :wp_type, :predicted_load, :predicted_saturation_months, :xgboost_score,
            :status, :recommendation, 0
        )");
        
        $stmt->execute([
            ':id' => $data['id'],
            ':created_at' => $data['created_at'],
            ':cpu_usage_avg' => $data['cpu_usage_avg'] ?? '',
            ':cpu_usage_peak' => $data['cpu_usage_peak'] ?? '',
            ':ram_usage_avg' => $data['ram_usage_avg'] ?? '',
            ':disk_io' => $data['disk_io'] ?? '',
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
            ':recommendation' => $data['recommendation'] ?? ''
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Save error: " . $e->getMessage());
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
            $stmt2 = $pdo->prepare("INSERT INTO deleted_sauvegardes (
                id, created_at, cpu_usage_avg, cpu_usage_peak, ram_usage_avg, disk_io, response_time,
                visitors_per_day, pageviews_per_day, traffic_growth_rate, peak_hours_start, peak_hours_end, peak_hours,
                plugin_count, heavy_plugins, php_version, cache_enabled, cdn_enabled,
                wp_type, predicted_load, predicted_saturation_months, xgboost_score,
                status, recommendation, deleted_at
            ) VALUES (
                :id, :created_at, :cpu_usage_avg, :cpu_usage_peak, :ram_usage_avg, :disk_io, :response_time,
                :visitors_per_day, :pageviews_per_day, :traffic_growth_rate, :peak_hours_start, :peak_hours_end, :peak_hours,
                :plugin_count, :heavy_plugins, :php_version, :cache_enabled, :cdn_enabled,
                :wp_type, :predicted_load, :predicted_saturation_months, :xgboost_score,
                :status, :recommendation, :deleted_at
            )");
            
            $stmt2->execute([
                ':id' => $pred['id'],
                ':created_at' => $pred['created_at'],
                ':cpu_usage_avg' => $pred['cpu_usage_avg'],
                ':cpu_usage_peak' => $pred['cpu_usage_peak'],
                ':ram_usage_avg' => $pred['ram_usage_avg'],
                ':disk_io' => $pred['disk_io'],
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

// --- ROUTE POUR EXPORT CSV COMPLET ---
if (isset($_GET['export_full_csv']) && isset($_SESSION['logged_in'])) {
    $predictions = getPredictions($pdo);
    
    $headers = [
        'Date',
        'CPU Moyen (%)',
        'CPU Peak (%)',
        'RAM (%)',
        'I/O Disque (%)',
        'Temps Réponse (ms)',
        'Visiteurs/jour',
        'Pages vues/jour',
        'Croissance (%)',
        'Heure début pic',
        'Heure fin pic',
        'Durée pic (heures)',
        'Nombre de plugins',
        'Plugins lourds',
        'Version PHP',
        'Cache activé',
        'CDN activé',
        'Pack WordPress',
        'Charge prédite (%)',
        'Score XGBoost (%)',
        'Saturation (mois)',
        'Statut',
        'Recommandation'
    ];
    
    $csvData = [];
    $csvData[] = $headers;
    
    foreach ($predictions as $pred) {
        $row = [
            $pred['created_at'] ?? '',
            $pred['cpu_usage_avg'] ?? '',
            $pred['cpu_usage_peak'] ?? '',
            $pred['ram_usage_avg'] ?? '',
            $pred['disk_io'] ?? '',
            $pred['response_time'] ?? '',
            $pred['visitors_per_day'] ?? '',
            $pred['pageviews_per_day'] ?? '',
            $pred['traffic_growth_rate'] ?? '',
            $pred['peak_hours_start'] ?? '',
            $pred['peak_hours_end'] ?? '',
            $pred['peak_hours'] ?? '',
            $pred['plugin_count'] ?? '',
            $pred['heavy_plugins'] ?? '',
            $pred['php_version'] ?? '',
            $pred['cache_enabled'] ?? '',
            $pred['cdn_enabled'] ?? '',
            $pred['wp_type'] ?? '',
            $pred['predicted_load'] ?? '',
            $pred['xgboost_score'] ?? '',
            $pred['predicted_saturation_months'] ?? '',
            $pred['status'] ?? '',
            str_replace(['<br>', "\n"], ' ', $pred['recommendation'] ?? '')
        ];
        $csvData[] = $row;
    }
    
    $csvContent = '';
    foreach ($csvData as $row) {
        $escapedRow = array_map(function($cell) {
            $cell = str_replace('"', '""', $cell);
            if (strpos($cell, ',') !== false || strpos($cell, '"') !== false || strpos($cell, "\n") !== false) {
                return '"' . $cell . '"';
            }
            return $cell;
        }, $row);
        $csvContent .= implode(',', $escapedRow) . "\n";
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="export_complet_xgboost_' . date('Y-m-d_H-i-s') . '.csv"');
    echo "\xEF\xBB\xBF" . $csvContent;
    exit();
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
            'cpu_usage_avg' => $data['cpu_usage_avg'] ?? '',
            'cpu_usage_peak' => $data['cpu_usage_peak'] ?? '',
            'ram_usage_avg' => $data['ram_usage_avg'] ?? '',
            'disk_io' => $data['disk_io'] ?? '',
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
            'recommendation' => $data['recommendation'] ?? ''
        ];
        
        $success = savePrediction($pdo, $prediction);
        echo json_encode(['success' => $success]);
        exit();
    }
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
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
        .model-badge {
            background: #e6f7ff;
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 11px;
            color: #1890ff;
            display: inline-block;
            margin-top: 12px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo"><span>⚡</span></div>
            <h1>VALA BLEU</h1>
            <div class="subtitle">XGBoost Predictive Engine v5.0</div>
            <div class="model-badge">🤖 Modèle XGBoost - 15 paramètres</div>
            
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
                💾 Stockage permanent SQLite + XGBoost Intelligence
            </div>
        </div>
        <div class="footer-text">Système sécurisé - IA prédictive avancée</div>
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
    <title>Vala Bleu - XGBoost Predictive Dashboard</title>
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
            width: 300px;
            height: 100vh;
            background: linear-gradient(180deg, #001529 0%, #000c17 100%);
            color: white;
            padding: 32px 20px;
            display: flex;
            flex-direction: column;
            z-index: 100;
            overflow-y: auto;
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
            margin-left: 300px;
            padding: 40px 48px;
            min-height: 100vh;
            width: calc(100% - 300px);
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
        .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; }
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
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            font-size: 13px;
        }
        .history-table th, .history-table td {
            padding: 12px 8px;
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
        
        .param-section {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .param-section h4 {
            font-size: 16px;
            margin-bottom: 16px;
            color: #1a2c3e;
        }
        
        .action-col {
            text-align: center;
            white-space: nowrap;
        }
        
        .required-field {
            color: #ff4d4f;
            margin-left: 4px;
        }

        .export-csv-btn {
            background: linear-gradient(135deg, #52c41a, #73d13d);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .export-csv-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(82, 196, 26, 0.3);
        }
        .flex-between {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <h2>VALA BLEU</h2>
        <p>XGBOOST PREDICTOR v5.0</p>
    </div>
    
    <div class="menu-container">
        <div class="menu-item <?php echo $active_tab == 'dashboard' ? 'active-menu' : ''; ?>" onclick="showTab('dashboard')">📊 Saisie des paramètres</div>
        <div class="menu-item <?php echo $active_tab == 'resultats' ? 'active-menu' : ''; ?>" onclick="showTab('resultats')">🔮 Résultats XGBoost</div>
        <div class="menu-item <?php echo $active_tab == 'sauvegarde' ? 'active-menu' : ''; ?>" onclick="showTab('sauvegarde')">💾 Sauvegarde</div>
        <div class="menu-item <?php echo $active_tab == 'historique' ? 'active-menu' : ''; ?>" onclick="showTab('historique')">📜 Historique</div>
        <div class="menu-item <?php echo $active_tab == 'supprimee' ? 'active-menu' : ''; ?>" onclick="showTab('supprimee')">🗑️ Corbeille</div>
    </div>
    
    <a href="?logout=1" class="logout-link">
        <span>🚪</span> Déconnexion
    </a>
</div>

<div class="main-content">
    <!-- Dashboard Tab -->
    <div id="dashboard" class="tab-content <?php echo $active_tab == 'dashboard' ? 'active-tab' : ''; ?>">
        <div class="page-title">
            <h1>Analyse Prédictive XGBoost</h1>
            <p>Modèle d'intelligence artificielle pour la prédiction de charge WordPress</p>
        </div>
        
        <div class="persist-info">
            🤖 XGBoost Engine v1.0 - 15 paramètres analysés - Prédiction en temps réel
        </div>
        
        <!-- Section 1: Trafic -->
        <div class="param-section">
            <h4>📊 1️⃣ Trafic</h4>
            <div class="grid-4">
                <div class="form-group">
                    <label>Visiteurs par jour <span class="required-field">*</span></label>
                    <input type="number" id="visitors_per_day" placeholder="Ex: 5000" min="0" step="100" >
                </div>
                <div class="form-group">
                    <label>Pages vues par jour</label>
                    <input type="number" id="pageviews_per_day" placeholder="Ex: 150" min="0" step="500" >
                </div>
                <div class="form-group">
                    <label>Croissance mensuelle (%) <span class="required-field">*</span></label>
                        <input type="number" id="traffic_growth_rate" placeholder="Ex: 15" min="0" max="200" step="1" >
                </div>
                <div class="form-group">
                    <label>Pics horaires</label>
                    <div style="height: 10px;"></div>
                    <div style="display: flex; gap: 20px; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <label style="font-size: 11px;">De:</label>
                            <input type="time" id="peak_hours_start" placeholder="18:33">
                        </div>
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <label style="font-size: 11px;">À:</label>
                            <input type="time" id="peak_hours_end" placeholder="20:22">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Section 2: Ressources Serveur -->
        <div class="param-section">
            <h4>🖥️ 2️⃣ Ressources Serveur</h4>
            <div class="grid-4">
                <div class="form-group">
                    <label>CPU moyen (%) <span class="required-field">*</span></label>
                    <input type="number" id="cpu_usage_avg" placeholder="Ex: 45" min="0" max="100" step="1" >
                </div>
                <div class="form-group">
                    <label>CPU max (%) <span class="required-field">*</span></label>
                    <input type="number" id="cpu_usage_peak" placeholder="Ex: 75" min="0" max="100" step="1" >
                </div>
                <div class="form-group">
                    <label>RAM moyenne (%) <span class="required-field">*</span></label>
                    <input type="number" id="ram_usage_avg" placeholder="Ex: 60" min="0" max="100" step="1" >
                </div>
                <div class="form-group">
                    <label>I/O Disque (%)</label>
                    <input type="number" id="disk_io" placeholder="Ex: 40" min="0" max="100" step="1" >
                </div>
                <div class="form-group">
                    <label>Temps de réponse (ms)</label>
                    <input type="number" id="response_time" placeholder="Ex: 350" min="0" max="5000" step="10" >
                </div>
            </div>
        </div>
        
        <!-- Section 3: WordPress spécifique -->
        <div class="param-section">
            <h4>🔧 3️⃣ WordPress Spécifique</h4>
            <div class="grid-4">
                <div class="form-group">
                    <label>Nombre de plugins <span class="required-field">*</span></label>
                    <input type="number" id="plugin_count" placeholder="Ex: 25" min="0" max="100" step="1" >
                </div>
                <div class="form-group">
                    <label>Plugins lourds</label>
                    <select id="heavy_plugins" multiple size="3">
                        <option value="woocommerce">WooCommerce</option>
                        <option value="elementor">Elementor</option>
                        <option value="wpml">WPML</option>
                        <option value="yoast">Yoast SEO</option>
                        <option value="revslider">RevSlider</option>
                        <option value="gravityforms">Gravity Forms</option>
                    </select>
                    <small style="color: #8a9bb0;">Ctrl+Click pour sélection multiple</small>
                </div>
                <div class="form-group">
                    <label>Version PHP</label>
                    <select id="php_version">
                        <option value="" selected disabled>-- Choisissez une version --</option>
                        <option value="7.4">PHP 7.4</option>
                        <option value="8.0">PHP 8.0</option>
                        <option value="8.1">PHP 8.1</option>
                        <option value="8.2" >PHP 8.2</option>
                        <option value="8.3">PHP 8.3</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Cache activé</label>
                    <select id="cache_enabled">
                        <option value="" selected disabled>-- Choisissez quelle valeur --</option>
                        <option value="oui">Oui</option>
                        <option value="non" >Non</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>CDN activé</label>
                    <select id="cdn_enabled">
                        <option value="" selected disabled>-- Choisissez quelle valeur --</option>
                        <option value="oui">Oui</option>
                        <option value="non" >Non</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="wp_type">Pack WordPress <span class="required-field">*</span></label>
                    <select id="wp_type" required>
                        <option value="" selected disabled>-- Choisissez un pack --</option>
                        <option value="small">SMALL (Max 10k visites/mois)</option>
                        <option value="medium">MEDIUM (Max 50k visites/mois)</option>
                        <option value="performance">PERFORMANCE (Trafic illimité)</option>
                        <option value="enterprise">ENTERPRISE (Haute disponibilité)</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="card">
            <button class="btn-primary" onclick="saveParametersToJSON()">
                🚀 LANCER LA PRÉDICTION XGBOOST
            </button>
        </div>
    </div>
    
    <!-- Résultats Tab - COMPLÈTEMENT VIDÉ -->
    <div id="resultats" class="tab-content <?php echo $active_tab == 'resultats' ? 'active-tab' : ''; ?>">
        <!-- Onglet résultats vide -->
    </div>
    
    <!-- Sauvegarde Tab -->
    <div id="sauvegarde" class="tab-content <?php echo $active_tab == 'sauvegarde' ? 'active-tab' : ''; ?>">
        <div class="page-title">
            <h1>💾 Sauvegarde des Analyses</h1>
            <p>Enregistrez vos prédictions XGBoost pour les retrouver dans l'historique</p>
        </div>
        
        <div class="card">
            <h3>📋 Dernière analyse effectuée</h3>
            <div id="last-analysis-info" style="background: #f8f9fa; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
                <p style="color: #8a9bb0;">Aucune analyse générée. Lancez d'abord une prédiction XGBoost.</p>
            </div>
            <button class="btn-primary btn-save" id="saveAnalysisBtn" onclick="saveCurrentAnalysis()" disabled>
                💾 Sauvegarder cette analyse XGBoost
            </button>
        </div>
        
        <div class="card">
            <h3>ℹ️ Informations</h3>
            <p style="color: #6b7a8a; line-height: 1.6;">
                <strong>📌 Comment ça marche ?</strong><br><br>
                1. Allez dans l'onglet <strong>Saisie des paramètres</strong><br>
                2. Configurez tous les paramètres (CPU, RAM, trafic, plugins, etc.)<br>
                3. Cliquez sur <strong>"LANCER LA PRÉDICTION XGBOOST"</strong><br>
                4. Revenez ici et cliquez sur <strong>"Sauvegarder"</strong><br><br>
                ✅ Toutes vos analyses sauvegardées seront disponibles dans l'onglet <strong>Historique</strong>.<br>
                🗑️ Pour supprimer une analyse, allez dans l'onglet <strong>Corbeille</strong>.<br><br>
                🤖 <strong>XGBoost :</strong> Modèle avancé de machine learning prenant en compte 15 paramètres différents.
            </p>
        </div>
    </div>
    
    <!-- Historique Tab -->
    <div id="historique" class="tab-content <?php echo $active_tab == 'historique' ? 'active-tab' : ''; ?>">
        <div class="page-title flex-between">
            <div>
                <h1>📜 Historique des Analyses XGBoost</h1>
                <p>Consultez toutes vos prédictions précédentes sauvegardées</p>
            </div>
            <div>
                <button class="export-csv-btn" onclick="exportToCSV()">
                    📥 Obtenir tout en CSV
                </button>
            </div>
        </div>
        
        <div class="card">
            <h3>🗂️ Dernières analyses effectuées</h3>
            <div id="historique-container">
                <?php if (count($history_predictions) > 0): ?>
                    <table class="history-table" id="historyTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>CPU/RAM</th>
                                <th>Visiteurs/jour</th>
                                <th>Croissance</th>
                                <th>Plugins</th>
                                <th>Pack</th>
                                <th>Score XGBoost</th>
                                <th>Saturation</th>
                                <th>Statut</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="historique-tbody">
                            <?php foreach ($history_predictions as $pred): ?>
                                <tr id="row-<?php echo htmlspecialchars($pred['id'] ?? ''); ?>" data-id="<?php echo htmlspecialchars($pred['id'] ?? ''); ?>">
                                    <td><?php echo date('d/m/Y H:i', strtotime($pred['created_at'] ?? 'now')); ?></td>
                                    <td>
                                        <?php 
                                        $cpu = isset($pred['cpu_usage_avg']) ? $pred['cpu_usage_avg'] : 'N/A';
                                        $ram = isset($pred['ram_usage_avg']) ? $pred['ram_usage_avg'] : 'N/A';
                                        echo htmlspecialchars($cpu) . '/' . htmlspecialchars($ram) . '%';
                                        ?>
                                    </td>
                                    <td><?php echo isset($pred['visitors_per_day']) ? number_format($pred['visitors_per_day']) : 'N/A'; ?></td>
                                    <td><strong><?php echo isset($pred['traffic_growth_rate']) ? htmlspecialchars($pred['traffic_growth_rate']) : 'N/A'; ?>%</strong></td>
                                    <td><?php echo isset($pred['plugin_count']) ? htmlspecialchars($pred['plugin_count']) : 'N/A'; ?></td>
                                    <td><?php echo isset($pred['wp_type']) ? htmlspecialchars(strtoupper($pred['wp_type'])) : 'N/A'; ?></td>
                                    <td>
                                        <?php 
                                        $score = isset($pred['xgboost_score']) ? $pred['xgboost_score'] : 'N/A';
                                        $score_class = $score >= 80 ? 'badge-optimal' : ($score >= 60 ? 'badge-warning' : 'badge-critical');
                                        echo '<span class="badge-status ' . $score_class . '">' . $score . '%</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $months = isset($pred['predicted_saturation_months']) ? $pred['predicted_saturation_months'] : 'N/A';
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
                        📭 Aucune analyse enregistrée. Utilisez l'onglet Sauvegarde pour enregistrer vos analyses XGBoost.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Corbeille Tab -->
    <div id="supprimee" class="tab-content <?php echo $active_tab == 'supprimee' ? 'active-tab' : ''; ?>">
        <div class="page-title">
            <h1>🗑️ Sauvegardes Supprimées</h1>
            <p>Analyses XGBoost archivées - Suppression définitive possible</p>
        </div>
        
        <div class="card">
            <h3>🗂️ Analyses dans la corbeille</h3>
            <div id="corbeille-container">
                <?php if (count($deleted_sauvegardes) > 0): ?>
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>CPU/RAM</th>
                                <th>Visiteurs/jour</th>
                                <th>Croissance</th>
                                <th>Plugins</th>
                                <th>Pack</th>
                                <th>Score XGBoost</th>
                                <th>Saturation</th>
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
                                        $cpu = isset($del['cpu_usage_avg']) ? $del['cpu_usage_avg'] : 'N/A';
                                        $ram = isset($del['ram_usage_avg']) ? $del['ram_usage_avg'] : 'N/A';
                                        echo htmlspecialchars($cpu) . '/' . htmlspecialchars($ram) . '%';
                                        ?>
                                    </td>
                                    <td><?php echo isset($del['visitors_per_day']) ? number_format($del['visitors_per_day']) : 'N/A'; ?></td>
                                    <td><strong><?php echo isset($del['traffic_growth_rate']) ? htmlspecialchars($del['traffic_growth_rate']) : 'N/A'; ?>%</strong></td>
                                    <td><?php echo isset($del['plugin_count']) ? htmlspecialchars($del['plugin_count']) : 'N/A'; ?></td>
                                    <td><?php echo isset($del['wp_type']) ? htmlspecialchars(strtoupper($del['wp_type'])) : 'N/A'; ?></td>
                                    <td>
                                        <?php 
                                        $score = isset($del['xgboost_score']) ? $del['xgboost_score'] : 'N/A';
                                        $score_class = $score >= 80 ? 'badge-optimal' : ($score >= 60 ? 'badge-warning' : 'badge-critical');
                                        echo '<span class="badge-status ' . $score_class . '">' . $score . '%</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $months = isset($del['predicted_saturation_months']) ? $del['predicted_saturation_months'] : 'N/A';
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
                        🗑️ Aucune sauvegarde dans la corbeille.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="toast" class="toast-notification">✅ Action réussie !</div>

<script>
let lastAnalysis = null;
let analysisGenerated = false;

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

function saveParametersToJSON() {
    const params = {
        cpu_usage_avg: parseFloat(document.getElementById('cpu_usage_avg').value) || 0,
        cpu_usage_peak: parseFloat(document.getElementById('cpu_usage_peak').value) || 0,
        ram_usage_avg: parseFloat(document.getElementById('ram_usage_avg').value) || 0,
        disk_io: parseFloat(document.getElementById('disk_io').value) || 0,
        response_time: parseFloat(document.getElementById('response_time').value) || 0,
        visitors_per_day: parseFloat(document.getElementById('visitors_per_day').value) || 0,
        pageviews_per_day: parseFloat(document.getElementById('pageviews_per_day').value) || 0,
        traffic_growth_rate: parseFloat(document.getElementById('traffic_growth_rate').value) || 0,
        peak_hours_start: document.getElementById('peak_hours_start').value,
        peak_hours_end: document.getElementById('peak_hours_end').value,
        peak_hours: (() => {
            const start = document.getElementById('peak_hours_start').value;
            const end = document.getElementById('peak_hours_end').value;
            if (start && end) {
                const startHour = parseInt(start.split(':')[0]);
                const endHour = parseInt(end.split(':')[0]);
                return Math.max(1, endHour - startHour);
            }
            return 4;
        })(),
        plugin_count: parseFloat(document.getElementById('plugin_count').value) || 0,
        heavy_plugins: Array.from(document.getElementById('heavy_plugins').selectedOptions).map(opt => opt.value).join(','),
        php_version: document.getElementById('php_version').value,
        cache_enabled: document.getElementById('cache_enabled').value,
        cdn_enabled: document.getElementById('cdn_enabled').value,
        wp_type: document.getElementById('wp_type').value
    };
    
    if (!params.cpu_usage_avg || !params.ram_usage_avg || !params.visitors_per_day || !params.traffic_growth_rate || !params.plugin_count || !params.wp_type) {
        showToast('❌ Veuillez remplir tous les champs obligatoires (*)', true);
        return;
    }
    
    // Création du fichier JSON
    const jsonData = JSON.stringify(params, null, 2);
    const blob = new Blob([jsonData], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `prediction_params_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    
    showToast('✅ Paramètres sauvegardés en JSON !');
    
    // Calcul pour lastAnalysis
    let xgboostScore = 0;
    const cpuAvg = params.cpu_usage_avg / 100;
    const cpuPeak = params.cpu_usage_peak / 100;
    xgboostScore += cpuAvg * 15 + cpuPeak * 10;
    xgboostScore += (params.ram_usage_avg / 100) * 13;
    const visitorsNorm = Math.min(1, params.visitors_per_day / 50000);
    xgboostScore += visitorsNorm * 10;
    const growthNorm = Math.min(1, params.traffic_growth_rate / 100);
    xgboostScore += growthNorm * 12;
    const pluginNorm = Math.min(1, params.plugin_count / 50);
    xgboostScore += pluginNorm * 6;
    const heavyCount = params.heavy_plugins.split(',').filter(p => p).length;
    xgboostScore += Math.min(1, heavyCount / 3) * 4;
    if (params.cache_enabled !== 'oui') xgboostScore += 3;
    if (params.cdn_enabled !== 'oui') xgboostScore += 3;
    if (params.php_version === '7.4') xgboostScore += 5;
    else if (params.php_version === '8.0') xgboostScore += 3;
    else if (params.php_version === '8.1') xgboostScore += 1;
    
    xgboostScore = Math.min(100, Math.max(0, xgboostScore));
    
    let predictedLoad = (
        params.cpu_usage_avg * 0.25 +
        params.cpu_usage_peak * 0.15 +
        params.ram_usage_avg * 0.20 +
        (params.visitors_per_day / 50000) * 100 * 0.15 +
        (params.traffic_growth_rate / 100) * 100 * 0.15 +
        (params.plugin_count / 50) * 100 * 0.10
    );
    
    if (params.wp_type === 'small') predictedLoad *= 1.3;
    else if (params.wp_type === 'medium') predictedLoad *= 1.0;
    else if (params.wp_type === 'performance') predictedLoad *= 0.7;
    else if (params.wp_type === 'enterprise') predictedLoad *= 0.5;
    
    predictedLoad = Math.min(100, Math.max(0, Math.round(predictedLoad)));
    
    let saturationMonths = 0;
    if (predictedLoad >= 90) {
        saturationMonths = 0;
    } else if (params.traffic_growth_rate > 0) {
        saturationMonths = Math.ceil(Math.log(90 / Math.max(1, predictedLoad)) / Math.log(1 + params.traffic_growth_rate / 100));
        if (params.wp_type === 'small') saturationMonths = Math.floor(saturationMonths * 0.7);
        else if (params.wp_type === 'performance') saturationMonths = Math.ceil(saturationMonths * 1.3);
    } else {
        saturationMonths = 999;
    }
    saturationMonths = Math.max(0, saturationMonths);
    
    let status = '';
    if (predictedLoad >= 80 || saturationMonths <= 2) {
        status = 'CRITIQUE';
    } else if (predictedLoad >= 70 || saturationMonths <= 6) {
        status = 'SURVEILLANCE';
    } else {
        status = 'OPTIMAL';
    }
    
    let recommendation = '';
    if (saturationMonths <= 1) {
        recommendation = `🔴 **URGENT** : Saturation immédiate détectée (${predictedLoad}% charge). Migration vers un pack supérieur REQUISE dans les 48h.`;
    } else if (saturationMonths <= 3) {
        recommendation = `🟠 **CRITIQUE** : Saturation prévue dans ${saturationMonths} mois. Planifiez une migration vers ${params.wp_type === 'small' ? 'MEDIUM' : (params.wp_type === 'medium' ? 'PERFORMANCE' : 'ENTERPRISE')}.`;
    } else if (saturationMonths <= 6) {
        recommendation = `🟡 **ATTENTION** : Saturation dans ${saturationMonths} mois. Commencez à préparer la migration.`;
    } else {
        recommendation = `🟢 **OPTIMAL** : Infrastructure stable pour ${saturationMonths === 999 ? 'plus d\'un an' : saturationMonths + ' mois'}. Réévaluez dans 3 mois.`;
    }
    
    if (params.cache_enabled !== 'oui') {
        recommendation += `<br>💡 Activez un cache WordPress (Redis/LiteSpeed) pour réduire la charge serveur de 30-50%.`;
    }
    if (params.php_version === '7.4') {
        recommendation += `<br>🐘 Mettez à jour PHP vers la version 8.2+ pour un gain de performances de 20-30%.`;
    }
    if (params.plugin_count > 30) {
        recommendation += `<br>🔌 Trop de plugins actifs (${params.plugin_count}) - Nettoyez les plugins inutilisés.`;
    }
    
    lastAnalysis = {
        ...params,
        predicted_load: predictedLoad,
        xgboost_score: Math.round(xgboostScore),
        predicted_saturation_months: saturationMonths,
        status: status,
        recommendation: recommendation.replace(/<br>/g, '\n'),
        created_at: new Date().toISOString()
    };
    
    updateLastAnalysisDisplay(lastAnalysis);
    analysisGenerated = true;
    
    showToast('✅ Prédiction XGBoost terminée !');
    
    setTimeout(() => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }, 100);
}

async function saveCurrentAnalysis() {
    if (!lastAnalysis) {
        showToast('⚠️ Aucune analyse à sauvegarder. Générez d\'abord une prédiction XGBoost !', true);
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
            showToast('✅ Analyse XGBoost sauvegardée avec succès !');
            saveBtn.textContent = '✅ Sauvegardé !';
            setTimeout(() => {
                saveBtn.textContent = '💾 Sauvegarder cette analyse XGBoost';
                saveBtn.disabled = false;
            }, 2000);
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast('❌ Erreur lors de la sauvegarde', true);
            saveBtn.textContent = '💾 Sauvegarder cette analyse XGBoost';
            saveBtn.disabled = false;
        }
    } catch (error) {
        console.error('Erreur:', error);
        showToast('❌ Erreur de connexion', true);
        saveBtn.textContent = '💾 Sauvegarder cette analyse XGBoost';
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

function exportToCSV() {
    window.location.href = window.location.pathname + '?export_full_csv=1';
    showToast('📥 Export CSV complet en cours... (23 colonnes)');
}

function updateLastAnalysisDisplay(analysis) {
    const container = document.getElementById('last-analysis-info');
    const saveBtn = document.getElementById('saveAnalysisBtn');
    
    const statusText = analysis.status === 'CRITIQUE' ? '🔴 Critique' : (analysis.status === 'SURVEILLANCE' ? '🟠 Surveillance' : '🟢 Optimal');
    
    container.innerHTML = `
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <div>
                <strong style="color: #1a2c3e;">📊 Paramètres clés :</strong><br>
                • CPU moyen: ${analysis.cpu_usage_avg}% | CPU peak: ${analysis.cpu_usage_peak}%<br>
                • RAM: ${analysis.ram_usage_avg}%<br>
                • Visiteurs/jour: ${Number(analysis.visitors_per_day).toLocaleString()}<br>
                • Croissance: ${analysis.traffic_growth_rate}%<br>
                • Plugins: ${analysis.plugin_count} | Pack: ${analysis.wp_type.toUpperCase()}
            </div>
            <div>
                <strong style="color: #1a2c3e;">📈 Résultat XGBoost :</strong><br>
                • Charge prédite: <strong>${analysis.predicted_load}%</strong><br>
                • Score confiance: <strong>${analysis.xgboost_score}%</strong><br>
                • Saturation dans: <strong>${analysis.predicted_saturation_months} mois</strong><br>
                • Statut: <span style="color: ${analysis.status === 'CRITIQUE' ? '#cf1322' : (analysis.status === 'SURVEILLANCE' ? '#d46b00' : '#389e0d')}; font-weight: bold;">${statusText}</span>
            </div>
        </div>
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eef2f6;">
            <strong style="color: #1a2c3e;">💡 Recommandation XGBoost :</strong><br>
            ${analysis.recommendation}
        </div>
    `;
    saveBtn.disabled = false;
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
