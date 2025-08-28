<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Simple API test without Slim Framework
$response = [
    'status' => 'success',
    'message' => 'API endpoint is accessible',
    'timestamp' => date('c'),
    'endpoints' => [
        'health' => '/v1/health',
        'transactions' => '/v1/transactions/initiate-swap',
        'status' => '/v1/transactions/{id}/status'
    ],
    'note' => 'This is a test endpoint - the real API uses Slim Framework'
];

echo json_encode($response, JSON_PRETTY_PRINT);