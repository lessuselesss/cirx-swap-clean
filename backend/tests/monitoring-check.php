#!/usr/bin/env php
<?php

/**
 * CIRX Transaction Monitoring CLI
 * 
 * This script checks for production issues that comprehensive tests
 * don't catch in live environments, specifically:
 * 
 * 1. Transactions stuck in payment_verified state (CIRX wallet config issues)
 * 2. High CIRX transfer failure rates
 * 3. Wallet configuration problems
 * 
 * Usage:
 *   php monitoring-check.php
 *   php monitoring-check.php --json
 *   php monitoring-check.php --alerts-only
 * 
 * Cron Example (check every 5 minutes):
 *   0,5,10,15,20,25,30,35,40,45,50,55 * * * * /path/to/backend/monitoring-check.php --alerts-only >> /var/log/cirx-monitoring.log 2>&1
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\TransactionMonitoringService;
use App\Commands\MonitoringCheckCommand;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogHandler;

// Show usage if help requested
if (in_array('--help', $argv) || in_array('-h', $argv)) {
    MonitoringCheckCommand::usage();
    exit(0);
}

try {
    // Load environment variables
    if (file_exists(__DIR__ . '/.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->load();
    }
    
    // Configure logger for monitoring
    $logger = new Logger('monitoring');
    
    // Log to syslog for production monitoring
    if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
        $logger->pushHandler(new SyslogHandler('cirx-monitoring', LOG_USER, Logger::WARNING));
    }
    
    // Also log to file for debugging
    $logFile = $_ENV['MONITORING_LOG_FILE'] ?? __DIR__ . '/logs/monitoring.log';
    if (is_writable(dirname($logFile))) {
        $logger->pushHandler(new StreamHandler($logFile, Logger::INFO));
    }
    
    // Initialize services
    $monitoringService = new TransactionMonitoringService($logger);
    $command = new MonitoringCheckCommand($monitoringService, $logger);
    
    // Execute monitoring check
    $exitCode = $command->execute(array_slice($argv, 1));
    
    exit($exitCode);
    
} catch (Exception $e) {
    $errorMessage = "FATAL: Monitoring check script failed - " . $e->getMessage();
    
    // Try to log the error
    if (isset($logger)) {
        $logger->critical($errorMessage, [
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'script' => __FILE__
        ]);
    }
    
    // Output error for cron/manual execution
    if (in_array('--json', $argv)) {
        echo json_encode([
            'error' => 'Script execution failed',
            'message' => $e->getMessage(),
            'timestamp' => Carbon\Carbon::now()->toISOString()
        ]) . "\n";
    } else {
        echo $errorMessage . "\n";
    }
    
    exit(3); // Fatal error exit code
}