<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Simple health check without dependencies
$response = [
    'status' => 'success',
    'service' => 'CIRX OTC Backend API',
    'version' => '1.0.0',
    'timestamp' => date('c'),
    'php_version' => PHP_VERSION,
    'endpoints' => [
        'health' => '/health.php',
        'test' => '/test.php'
    ],
    'environment' => [
        'has_env_file' => file_exists(__DIR__ . '/../.env'),
        'autoload_exists' => file_exists(__DIR__ . '/../vendor/autoload.php')
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT);