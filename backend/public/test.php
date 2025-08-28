<?php
echo json_encode([
    'status' => 'ok',
    'message' => 'PHP is working',
    'timestamp' => date('c'),
    'server_info' => [
        'php_version' => PHP_VERSION,
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'unknown'
    ]
]);