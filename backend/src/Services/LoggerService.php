<?php

namespace App\Services;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\IntrospectionProcessor;
use Exception;

/**
 * Centralized logging service for CIRX OTC Backend
 * 
 * Provides structured logging with multiple handlers and processors
 */
class LoggerService
{
    private static ?Logger $logger = null;
    private static array $loggers = [];

    /**
     * Get the main application logger
     */
    public static function getLogger(string $channel = 'cirx-otc'): Logger
    {
        if (!isset(self::$loggers[$channel])) {
            self::$loggers[$channel] = self::createLogger($channel);
        }

        return self::$loggers[$channel];
    }

    /**
     * Create a configured logger instance
     */
    private static function createLogger(string $channel): Logger
    {
        $logger = new Logger($channel);

        // Add processors for context
        $logger->pushProcessor(new UidProcessor());
        $logger->pushProcessor(new ProcessIdProcessor());
        $logger->pushProcessor(new MemoryUsageProcessor());
        $logger->pushProcessor(new IntrospectionProcessor(Logger::DEBUG, ['Monolog\\'], 1));

        // Configure handlers based on environment
        $environment = $_ENV['APP_ENV'] ?? 'development';
        $logLevel = self::parseLogLevel($_ENV['LOG_LEVEL'] ?? 'info');

        if ($environment === 'production') {
            self::addProductionHandlers($logger, $logLevel);
        } else {
            self::addDevelopmentHandlers($logger, $logLevel);
        }

        return $logger;
    }

    /**
     * Add production logging handlers
     */
    private static function addProductionHandlers(Logger $logger, int $logLevel): void
    {
        // Rotating file handler for application logs
        $logPath = $_ENV['LOG_FILE_PATH'] ?? '/var/log/cirx-otc/application.log';
        $maxFiles = (int) ($_ENV['LOG_MAX_FILES'] ?? 14);
        
        try {
            $fileHandler = new RotatingFileHandler($logPath, $maxFiles, $logLevel);
            $fileHandler->setFormatter(new JsonFormatter());
            $logger->pushHandler($fileHandler);
        } catch (Exception $e) {
            // Fallback to error log if file logging fails
            error_log("Failed to create file logger: " . $e->getMessage());
        }

        // Error log handler as fallback
        $errorHandler = new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, $logLevel);
        $errorHandler->setFormatter(new JsonFormatter());
        $logger->pushHandler($errorHandler);

        // Separate handler for errors and critical logs
        if ($logLevel <= Logger::ERROR) {
            $errorLogPath = dirname($logPath) . '/errors.log';
            try {
                $errorFileHandler = new RotatingFileHandler($errorLogPath, $maxFiles, Logger::ERROR);
                $errorFileHandler->setFormatter(new JsonFormatter());
                $logger->pushHandler($errorFileHandler);
            } catch (Exception $e) {
                error_log("Failed to create error logger: " . $e->getMessage());
            }
        }
    }

    /**
     * Add development logging handlers
     */
    private static function addDevelopmentHandlers(Logger $logger, int $logLevel): void
    {
        // Console/STDOUT handler for development
        $consoleHandler = new StreamHandler('php://stdout', $logLevel);
        $consoleHandler->setFormatter(new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            'Y-m-d H:i:s'
        ));
        $logger->pushHandler($consoleHandler);

        // File handler for development (if configured)
        $logPath = $_ENV['LOG_FILE_PATH'] ?? 'storage/logs/application.log';
        if ($logPath && $logPath !== 'php://stdout') {
            try {
                // Ensure directory exists
                $logDir = dirname($logPath);
                if (!is_dir($logDir)) {
                    mkdir($logDir, 0755, true);
                }

                $fileHandler = new StreamHandler($logPath, $logLevel);
                $fileHandler->setFormatter(new LineFormatter(
                    "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                    'Y-m-d H:i:s'
                ));
                $logger->pushHandler($fileHandler);
            } catch (Exception $e) {
                error_log("Failed to create development file logger: " . $e->getMessage());
            }
        }
    }

    /**
     * Parse log level string to Monolog constant
     */
    private static function parseLogLevel(string $level): int
    {
        return match (strtolower($level)) {
            'debug' => Logger::DEBUG,
            'info' => Logger::INFO,
            'notice' => Logger::NOTICE,
            'warning', 'warn' => Logger::WARNING,
            'error' => Logger::ERROR,
            'critical' => Logger::CRITICAL,
            'alert' => Logger::ALERT,
            'emergency' => Logger::EMERGENCY,
            default => Logger::INFO,
        };
    }

    /**
     * Log API request
     */
    public static function logApiRequest(
        string $method,
        string $path,
        array $context = [],
        string $clientId = null,
        int $responseCode = null
    ): void {
        $logger = self::getLogger('api');
        
        $logContext = array_merge([
            'type' => 'api_request',
            'http_method' => $method,
            'path' => $path,
            'client_id' => $clientId,
            'response_code' => $responseCode,
            'timestamp' => date('c')
        ], $context);

        $message = "API Request: {$method} {$path}";
        if ($responseCode) {
            $message .= " [{$responseCode}]";
        }

        $logger->info($message, $logContext);
    }

    /**
     * Log worker activity
     */
    public static function logWorkerActivity(
        string $workerName,
        string $action,
        array $context = [],
        string $level = 'info'
    ): void {
        $logger = self::getLogger('worker');
        
        $logContext = array_merge([
            'type' => 'worker_activity',
            'worker' => $workerName,
            'action' => $action,
            'timestamp' => date('c')
        ], $context);

        $message = "Worker {$workerName}: {$action}";

        match ($level) {
            'debug' => $logger->debug($message, $logContext),
            'info' => $logger->info($message, $logContext),
            'warning' => $logger->warning($message, $logContext),
            'error' => $logger->error($message, $logContext),
            'critical' => $logger->critical($message, $logContext),
            default => $logger->info($message, $logContext),
        };
    }

    /**
     * Log transaction events
     */
    public static function logTransaction(
        string $transactionId,
        string $event,
        array $context = [],
        string $level = 'info'
    ): void {
        $logger = self::getLogger('transaction');
        
        $logContext = array_merge([
            'type' => 'transaction_event',
            'transaction_id' => $transactionId,
            'event' => $event,
            'timestamp' => date('c')
        ], $context);

        $message = "Transaction {$transactionId}: {$event}";

        match ($level) {
            'debug' => $logger->debug($message, $logContext),
            'info' => $logger->info($message, $logContext),
            'warning' => $logger->warning($message, $logContext),
            'error' => $logger->error($message, $logContext),
            'critical' => $logger->critical($message, $logContext),
            default => $logger->info($message, $logContext),
        };
    }

    /**
     * Log security events
     */
    public static function logSecurity(
        string $event,
        array $context = [],
        string $level = 'warning'
    ): void {
        $logger = self::getLogger('security');
        
        $logContext = array_merge([
            'type' => 'security_event',
            'event' => $event,
            'timestamp' => date('c'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ], $context);

        $message = "Security Event: {$event}";

        match ($level) {
            'info' => $logger->info($message, $logContext),
            'warning' => $logger->warning($message, $logContext),
            'error' => $logger->error($message, $logContext),
            'critical' => $logger->critical($message, $logContext),
            default => $logger->warning($message, $logContext),
        };
    }

    /**
     * Log blockchain interactions
     */
    public static function logBlockchain(
        string $chain,
        string $action,
        array $context = [],
        string $level = 'info'
    ): void {
        $logger = self::getLogger('blockchain');
        
        $logContext = array_merge([
            'type' => 'blockchain_interaction',
            'chain' => $chain,
            'action' => $action,
            'timestamp' => date('c')
        ], $context);

        $message = "Blockchain {$chain}: {$action}";

        match ($level) {
            'debug' => $logger->debug($message, $logContext),
            'info' => $logger->info($message, $logContext),
            'warning' => $logger->warning($message, $logContext),
            'error' => $logger->error($message, $logContext),
            'critical' => $logger->critical($message, $logContext),
            default => $logger->info($message, $logContext),
        };
    }

    /**
     * Log worker operations (alias for integration tests)
     */
    public static function logWorker(
        string $workerType,
        string $message,
        array $context = [],
        string $level = 'info'
    ): void {
        self::logWorkerActivity($workerType, $message, $context, $level);
    }

    /**
     * Get logging statistics
     */
    public static function getLoggingStatistics(): array
    {
        $logPath = $_ENV['LOG_FILE_PATH'] ?? 'storage/logs/application.log';
        $stats = [
            'configured_level' => $_ENV['LOG_LEVEL'] ?? 'info',
            'environment' => $_ENV['APP_ENV'] ?? 'development',
            'log_file' => $logPath,
            'active_loggers' => array_keys(self::$loggers),
        ];

        // Add file statistics if file logging is enabled
        if (file_exists($logPath)) {
            $stats['file_size'] = filesize($logPath);
            $stats['file_modified'] = date('c', filemtime($logPath));
        }

        return $stats;
    }

    /**
     * Test logging functionality
     */
    public static function testLogging(): array
    {
        $results = [
            'success' => true,
            'tests' => [],
            'errors' => []
        ];

        try {
            // Test each log level
            $logger = self::getLogger('test');
            
            $levels = ['debug', 'info', 'warning', 'error'];
            foreach ($levels as $level) {
                try {
                    $logger->$level("Test {$level} message", ['test' => true]);
                    $results['tests'][] = "✅ {$level} level logging working";
                } catch (Exception $e) {
                    $results['tests'][] = "❌ {$level} level logging failed: " . $e->getMessage();
                    $results['errors'][] = $e->getMessage();
                    $results['success'] = false;
                }
            }

            // Test structured logging methods
            self::logApiRequest('GET', '/test', ['test' => true]);
            $results['tests'][] = "✅ API request logging working";

            self::logTransaction('test-123', 'test_event', ['test' => true]);
            $results['tests'][] = "✅ Transaction logging working";

        } catch (Exception $e) {
            $results['success'] = false;
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }
}