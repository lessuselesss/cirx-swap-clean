<?php

namespace App\Services;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;

/**
 * Custom Monolog handler for Telegram notifications
 * 
 * Integrates TelegramNotificationService with Monolog logging system
 */
class TelegramHandler extends AbstractProcessingHandler
{
    private TelegramNotificationService $telegramService;
    private array $rateLimitCache = [];
    private int $rateLimitWindow = 300; // 5 minutes
    private int $maxMessagesPerWindow = 3; // Max 3 messages per 5 minutes per error type
    
    public function __construct(
        TelegramNotificationService $telegramService,
        int $level = Logger::ERROR,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
        $this->telegramService = $telegramService;
    }
    
    /**
     * Process log record and send to Telegram
     */
    protected function write(LogRecord $record): void
    {
        // Extract error type from context
        $errorType = $record->context['error_type'] ?? 
                    $record->context['type'] ?? 
                    'general_error';
        
        // Apply rate limiting
        if ($this->isRateLimited($errorType)) {
            return;
        }
        
        // Determine if this is a critical error
        $isCritical = $record->level >= Logger::CRITICAL ||
                     $this->isCriticalErrorType($errorType);
        
        // Send notification
        if ($isCritical) {
            $this->telegramService->sendCriticalAlert(
                $errorType,
                $record->message,
                $record->context
            );
        } else {
            $this->telegramService->sendErrorNotification(
                $errorType,
                $record->message,
                $record->context,
                $record->level_name
            );
        }
        
        // Update rate limit tracking
        $this->updateRateLimit($errorType);
    }
    
    /**
     * Check if error type is rate limited
     */
    private function isRateLimited(string $errorType): bool
    {
        $now = time();
        $cacheKey = $errorType;
        
        if (!isset($this->rateLimitCache[$cacheKey])) {
            return false;
        }
        
        $rateLimitData = $this->rateLimitCache[$cacheKey];
        
        // Clean old entries
        $rateLimitData['timestamps'] = array_filter(
            $rateLimitData['timestamps'],
            fn($timestamp) => ($now - $timestamp) < $this->rateLimitWindow
        );
        
        // Check if we've exceeded the limit
        $messageCount = count($rateLimitData['timestamps']);
        
        if ($messageCount >= $this->maxMessagesPerWindow) {
            return true;
        }
        
        // Update cache
        $this->rateLimitCache[$cacheKey] = $rateLimitData;
        
        return false;
    }
    
    /**
     * Update rate limit tracking
     */
    private function updateRateLimit(string $errorType): void
    {
        $now = time();
        $cacheKey = $errorType;
        
        if (!isset($this->rateLimitCache[$cacheKey])) {
            $this->rateLimitCache[$cacheKey] = [
                'timestamps' => []
            ];
        }
        
        $this->rateLimitCache[$cacheKey]['timestamps'][] = $now;
    }
    
    /**
     * Check if error type should always be treated as critical
     */
    private function isCriticalErrorType(string $errorType): bool
    {
        $criticalErrorTypes = [
            'database_connection_failed',
            'cirx_transfer_failed',
            'payment_verification_failed',
            'blockchain_client_error',
            'wallet_configuration_error'
        ];
        
        return in_array($errorType, $criticalErrorTypes);
    }
    
    /**
     * Clean old rate limit entries
     */
    public function cleanRateLimitCache(): void
    {
        $now = time();
        
        foreach ($this->rateLimitCache as $errorType => $data) {
            $this->rateLimitCache[$errorType]['timestamps'] = array_filter(
                $data['timestamps'],
                fn($timestamp) => ($now - $timestamp) < $this->rateLimitWindow
            );
            
            // Remove empty entries
            if (empty($this->rateLimitCache[$errorType]['timestamps'])) {
                unset($this->rateLimitCache[$errorType]);
            }
        }
    }
    
    /**
     * Get rate limit statistics
     */
    public function getRateLimitStats(): array
    {
        $stats = [];
        $now = time();
        
        foreach ($this->rateLimitCache as $errorType => $data) {
            $recentCount = count(array_filter(
                $data['timestamps'],
                fn($timestamp) => ($now - $timestamp) < $this->rateLimitWindow
            ));
            
            $stats[$errorType] = [
                'recent_messages' => $recentCount,
                'limit' => $this->maxMessagesPerWindow,
                'window_seconds' => $this->rateLimitWindow,
                'is_limited' => $recentCount >= $this->maxMessagesPerWindow
            ];
        }
        
        return $stats;
    }
    
    /**
     * Configure rate limiting
     */
    public function setRateLimit(int $maxMessages, int $windowSeconds): void
    {
        $this->maxMessagesPerWindow = $maxMessages;
        $this->rateLimitWindow = $windowSeconds;
    }
}