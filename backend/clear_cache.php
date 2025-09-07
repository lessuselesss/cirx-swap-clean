<?php
// Simple cache clearing script for production deployment issues
// This file should be accessed once after deployment to clear PHP OPcache

header('Content-Type: application/json');

$result = [];

// Clear OPcache if available
if (function_exists('opcache_reset')) {
    $opcacheCleared = opcache_reset();
    $result['opcache'] = $opcacheCleared ? 'cleared' : 'failed';
} else {
    $result['opcache'] = 'not_available';
}

// Clear realpath cache
clearstatcache(true);
$result['stat_cache'] = 'cleared';

// Get OPcache status
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status();
    $result['opcache_status'] = [
        'enabled' => $status !== false,
        'restart_pending' => $status['restart_pending'] ?? false,
        'restart_in_progress' => $status['restart_in_progress'] ?? false
    ];
}

// Current timestamp for verification
$result['timestamp'] = date('c');
$result['message'] = 'Cache clearing completed';

echo json_encode($result, JSON_PRETTY_PRINT);
?>