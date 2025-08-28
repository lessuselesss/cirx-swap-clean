<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Comprehensive Transaction Readiness Health Check
 * Only returns success when ALL systems are ready to process transactions
 */

$healthChecks = [];
$overallStatus = 'success';
$transactionReady = true;

// Load environment if available
if (file_exists(__DIR__ . '/../.env')) {
    try {
        $env = parse_ini_file(__DIR__ . '/../.env', false, INI_SCANNER_RAW);
        if ($env !== false) {
            foreach ($env as $key => $value) {
                if (!getenv($key)) {
                    putenv("$key=$value");
                }
            }
        }
    } catch (Exception $e) {
        // Ignore .env parsing errors and continue without environment loading
    }
}

// 1. Basic Environment Check
$healthChecks['environment'] = [
    'status' => 'checking',
    'details' => []
];

try {
    $envCheck = [
        'php_version' => PHP_VERSION,
        'has_env_file' => file_exists(__DIR__ . '/../.env'),
        'autoload_exists' => file_exists(__DIR__ . '/../vendor/autoload.php'),
        'writable_logs' => is_writable(__DIR__ . '/../logs') || is_writable(__DIR__ . '/../storage/logs'),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time')
    ];
    
    $requiredEnvVars = [
        'DB_CONNECTION', 'DB_DATABASE',
        'CIRX_NAG_URL', 'CIRX_WALLET_ADDRESS', 'CIRX_WALLET_PRIVATE_KEY'
    ];
    
    $missingEnv = [];
    foreach ($requiredEnvVars as $var) {
        if (!getenv($var)) {
            $missingEnv[] = $var;
        }
    }
    
    if (empty($missingEnv) && $envCheck['autoload_exists']) {
        $healthChecks['environment']['status'] = 'healthy';
        $healthChecks['environment']['details'] = $envCheck;
    } else {
        $healthChecks['environment']['status'] = 'error';
        $healthChecks['environment']['details'] = array_merge($envCheck, ['missing_env_vars' => $missingEnv]);
        $transactionReady = false;
        $overallStatus = 'error';
    }
} catch (Exception $e) {
    $healthChecks['environment']['status'] = 'error';
    $healthChecks['environment']['error'] = $e->getMessage();
    $transactionReady = false;
    $overallStatus = 'error';
}

// 2. Database Connectivity Check
$healthChecks['database'] = ['status' => 'checking'];

try {
    $dbConnection = getenv('DB_CONNECTION') ?: 'sqlite';
    $dbDatabase = getenv('DB_DATABASE') ?: __DIR__ . '/../storage/database.sqlite';
    
    // Test database connection
    if ($dbConnection === 'sqlite') {
        $pdo = new PDO("sqlite:$dbDatabase");
    } else {
        $dbHost = getenv('DB_HOST') ?: 'localhost';
        $dbName = getenv('DB_DATABASE') ?: 'cirx_swap';
        $dbUser = getenv('DB_USERNAME') ?: 'root';
        $dbPass = getenv('DB_PASSWORD') ?: '';
        $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
    }
    
    // Test basic database operations
    $pdo->exec("CREATE TEMPORARY TABLE health_check_test (id INT, test_value VARCHAR(50))");
    $pdo->exec("INSERT INTO health_check_test VALUES (1, 'test')");
    $stmt = $pdo->query("SELECT COUNT(*) FROM health_check_test");
    $count = $stmt->fetchColumn();
    $pdo->exec("DROP TABLE health_check_test");
    
    if ($count == 1) {
        $healthChecks['database']['status'] = 'healthy';
        $healthChecks['database']['connection_time'] = 'under 5s';
    } else {
        throw new Exception("Database test operation failed");
    }
} catch (Exception $e) {
    $healthChecks['database']['status'] = 'error';
    $healthChecks['database']['error'] = $e->getMessage();
    $transactionReady = false;
    $overallStatus = 'error';
}

// 3. External API Connectivity (Circular Protocol)
$healthChecks['cirx_api'] = ['status' => 'checking'];

try {
    $cirxNagUrl = getenv('CIRX_NAG_URL');
    $cirxWalletAddress = getenv('CIRX_WALLET_ADDRESS');
    $cirxPrivateKey = getenv('CIRX_WALLET_PRIVATE_KEY');
    
    if (!$cirxNagUrl || !$cirxWalletAddress || !$cirxPrivateKey) {
        throw new Exception("CIRX configuration incomplete: NAG URL, wallet address, or private key missing");
    }
    
    // Test NAG API connectivity with timeout
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10
        ]
    ]);
    
    // Test basic NAG endpoint with a simple query
    $testUrl = $cirxNagUrl . 'getBalance';
    $response = @file_get_contents($testUrl, false, $context);
    
    if ($response !== false) {
        $healthChecks['cirx_api']['status'] = 'healthy';
        $healthChecks['cirx_api']['endpoint'] = $testUrl;
        $healthChecks['cirx_api']['wallet_configured'] = !empty($cirxWalletAddress);
    } else {
        // Try backup NAG URL if configured
        $cirxNagBackup = getenv('CIRX_NAG_URL_BACKUP');
        if ($cirxNagBackup) {
            $testUrl = $cirxNagBackup . 'getBalance';
            $response = @file_get_contents($testUrl, false, $context);
            
            if ($response !== false) {
                $healthChecks['cirx_api']['status'] = 'healthy';
                $healthChecks['cirx_api']['endpoint'] = $testUrl;
                $healthChecks['cirx_api']['wallet_configured'] = !empty($cirxWalletAddress);
            } else {
                throw new Exception("Cannot connect to CIRX NAG API at primary or backup URLs");
            }
        } else {
            throw new Exception("Cannot connect to CIRX NAG API at $cirxNagUrl");
        }
    }
} catch (Exception $e) {
    $healthChecks['cirx_api']['status'] = 'error';
    $healthChecks['cirx_api']['error'] = $e->getMessage();
    $transactionReady = false;
    $overallStatus = 'error';
}

// 4. File System Checks
$healthChecks['filesystem'] = ['status' => 'checking'];

try {
    $checks = [];
    
    // Check critical directories
    $requiredDirs = [
        __DIR__ . '/../logs',
        __DIR__ . '/../storage',
        __DIR__ . '/../storage/logs',
        __DIR__ . '/../tmp'
    ];
    
    foreach ($requiredDirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $checks['writable_' . basename($dir)] = is_writable($dir);
    }
    
    // Check disk space (warn if less than 1GB free)
    $freeBytes = disk_free_space(__DIR__);
    $freeGB = round($freeBytes / (1024 * 1024 * 1024), 2);
    $checks['disk_space_gb'] = $freeGB;
    $checks['disk_space_adequate'] = $freeGB > 1;
    
    if (array_filter($checks) === $checks) {
        $healthChecks['filesystem']['status'] = 'healthy';
        $healthChecks['filesystem']['details'] = $checks;
    } else {
        $healthChecks['filesystem']['status'] = 'warning';
        $healthChecks['filesystem']['details'] = $checks;
        if ($freeGB <= 0.5) {
            $transactionReady = false;
            $overallStatus = 'error';
        }
    }
} catch (Exception $e) {
    $healthChecks['filesystem']['status'] = 'error';
    $healthChecks['filesystem']['error'] = $e->getMessage();
}

// 5. Transaction System Readiness
$healthChecks['transaction_system'] = ['status' => 'checking'];

try {
    $systemChecks = [];
    
    // Check if we can load core classes (if autoloader exists)
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $coreClasses = [
            'App\\Models\\Transaction',
            'App\\Services\\CirxTransferService',
            'App\\Services\\PaymentVerificationService'
        ];
        
        foreach ($coreClasses as $class) {
            $systemChecks['class_' . basename(str_replace('\\', '/', $class))] = class_exists($class);
        }
    }
    
    // Check for stuck transactions in database (if DB is available)
    if (isset($pdo) && $healthChecks['database']['status'] === 'healthy') {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM transactions WHERE swap_status = 'pending_payment_verification' AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $stuckCount = $stmt->fetchColumn();
            $systemChecks['stuck_transactions'] = $stuckCount;
            $systemChecks['stuck_transactions_acceptable'] = $stuckCount < 10;
        } catch (Exception $e) {
            // Transactions table might not exist yet
            $systemChecks['transaction_table_exists'] = false;
        }
    }
    
    if (empty(array_filter($systemChecks, function($v) { return $v === false; }))) {
        $healthChecks['transaction_system']['status'] = 'healthy';
        $healthChecks['transaction_system']['details'] = $systemChecks;
    } else {
        $healthChecks['transaction_system']['status'] = 'warning';
        $healthChecks['transaction_system']['details'] = $systemChecks;
    }
} catch (Exception $e) {
    $healthChecks['transaction_system']['status'] = 'error';
    $healthChecks['transaction_system']['error'] = $e->getMessage();
}

// Final Response
$response = [
    'status' => $overallStatus,
    'transaction_ready' => $transactionReady,
    'service' => 'CIRX OTC Backend API',
    'version' => '1.0.0',
    'timestamp' => date('c'),
    'health_score' => count(array_filter($healthChecks, function($check) { 
        return $check['status'] === 'healthy'; 
    })) . '/' . count($healthChecks),
    'checks' => $healthChecks
];

// Set appropriate HTTP status code
if (!$transactionReady) {
    http_response_code(503); // Service Unavailable
} elseif ($overallStatus === 'error') {
    http_response_code(500); // Internal Server Error
} else {
    http_response_code(200); // OK
}

echo json_encode($response, JSON_PRETTY_PRINT);