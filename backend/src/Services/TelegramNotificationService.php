<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Telegram notification service for CIRX OTC Backend error alerts
 * 
 * Sends error notifications to configured Telegram chat via Bot API
 */
class TelegramNotificationService
{
    private Client $httpClient;
    private LoggerInterface $logger;
    private string $botToken;
    private string $chatId;
    private string $baseUrl;
    private array $criticalErrorTypes;
    private array $highPriorityErrorTypes;
    
    public function __construct(
        Client $httpClient,
        LoggerInterface $logger,
        string $botToken,
        string $chatId
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->botToken = $botToken;
        $this->chatId = $chatId;
        $this->baseUrl = "https://api.telegram.org/bot{$botToken}";
        
        // Define error priority levels
        $this->criticalErrorTypes = [
            'database_connection_failed',
            'cirx_transfer_failed',
            'payment_verification_failed',
            'blockchain_client_error',
            'wallet_configuration_error'
        ];
        
        $this->highPriorityErrorTypes = [
            'transaction_timeout',
            'api_rate_limit_exceeded',
            'authentication_failure',
            'invalid_transaction_state',
            'blockchain_rpc_error'
        ];
    }
    
    /**
     * Send a formatted error notification to Telegram
     */
    public function sendErrorNotification(
        string $errorType,
        string $message,
        array $context = [],
        string $level = 'error'
    ): bool {
        try {
            $emoji = $this->getErrorEmoji($errorType);
            $priority = $this->getErrorPriority($errorType);
            $formattedText = $this->formatErrorMessage($emoji, $errorType, $message, $context, $level);
            
            // Send with appropriate notification settings
            $silent = ($priority === 'low');
            
            return $this->sendMessage($formattedText, $silent);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send Telegram notification', [
                'error' => $e->getMessage(),
                'original_error_type' => $errorType,
                'original_message' => $message
            ]);
            return false;
        }
    }
    
    /**
     * Send a critical error alert with high priority
     */
    public function sendCriticalAlert(
        string $title,
        string $message,
        array $context = []
    ): bool {
        try {
            $text = "🔥 *CRITICAL ALERT - CIRX OTC* 🔥\n\n";
            $text .= "⚠️ *{$title}*\n\n";
            $text .= "📝 *Message:* {$message}\n";
            $text .= "⏰ *Time:* " . date('Y-m-d H:i:s T') . "\n";
            $text .= "🖥️ *Server:* " . gethostname() . "\n";
            $text .= "🌍 *Environment:* " . ($_ENV['APP_ENV'] ?? 'unknown') . "\n";
            
            if (!empty($context)) {
                $text .= "\n📊 *Context:*\n";
                $contextText = $this->formatContext($context);
                
                // Ensure message doesn't exceed Telegram's 4096 character limit
                if (strlen($text . $contextText) > 4000) {
                    $contextText = substr($contextText, 0, 4000 - strlen($text)) . "...\n[TRUNCATED]";
                }
                
                $text .= $contextText;
            }
            
            // Critical alerts are never silent
            return $this->sendMessage($text, false);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send critical Telegram alert', [
                'error' => $e->getMessage(),
                'title' => $title,
                'message' => $message
            ]);
            return false;
        }
    }
    
    /**
     * Send a basic message to Telegram
     */
    public function sendMessage(string $text, bool $silent = false): bool
    {
        try {
            $response = $this->httpClient->post($this->baseUrl . '/sendMessage', [
                'json' => [
                    'chat_id' => $this->chatId,
                    'text' => $text,
                    'parse_mode' => 'Markdown',
                    'disable_notification' => $silent
                ],
                'timeout' => 10,
                'connect_timeout' => 5
            ]);
            
            $result = json_decode($response->getBody()->getContents(), true);
            
            if ($result['ok'] ?? false) {
                $this->logger->debug('Telegram notification sent successfully', [
                    'message_id' => $result['result']['message_id'] ?? null,
                    'chat_id' => $this->chatId
                ]);
                return true;
            } else {
                $this->logger->warning('Telegram API returned error', [
                    'error_code' => $result['error_code'] ?? null,
                    'description' => $result['description'] ?? 'Unknown error'
                ]);
                return false;
            }
            
        } catch (GuzzleException $e) {
            $this->logger->error('Telegram API request failed', [
                'error' => $e->getMessage(),
                'chat_id' => $this->chatId
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error sending Telegram message', [
                'error' => $e->getMessage(),
                'chat_id' => $this->chatId
            ]);
            return false;
        }
    }
    
    /**
     * Format error message for Telegram
     */
    private function formatErrorMessage(
        string $emoji,
        string $errorType,
        string $message,
        array $context,
        string $level
    ): string {
        $text = "{$emoji} *CIRX Backend Alert*\n\n";
        $text .= "🔴 *Error Type:* {$errorType}\n";
        $text .= "📊 *Level:* " . strtoupper($level) . "\n";
        $text .= "📝 *Message:* {$message}\n";
        $text .= "⏰ *Time:* " . date('Y-m-d H:i:s T') . "\n";
        $text .= "🖥️ *Server:* " . gethostname() . "\n";
        $text .= "🌍 *Environment:* " . ($_ENV['APP_ENV'] ?? 'unknown') . "\n";
        
        if (!empty($context)) {
            $text .= "\n📊 *Context:*\n";
            $contextText = $this->formatContext($context);
            
            // Ensure total message doesn't exceed Telegram's limit
            if (strlen($text . $contextText) > 4000) {
                $contextText = substr($contextText, 0, 4000 - strlen($text)) . "...\n[TRUNCATED]";
            }
            
            $text .= $contextText;
        }
        
        return $text;
    }
    
    /**
     * Format context array for display
     */
    private function formatContext(array $context): string
    {
        $formatted = "";
        
        // Priority fields first
        $priorityFields = ['transaction_id', 'user_address', 'amount', 'error_code', 'request_id'];
        
        foreach ($priorityFields as $field) {
            if (isset($context[$field]) && is_scalar($context[$field])) {
                $formatted .= "• *{$field}:* `{$context[$field]}`\n";
                unset($context[$field]);
            }
        }
        
        // Remaining fields
        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                // Sanitize sensitive data
                if ($this->isSensitiveField($key)) {
                    $value = '[REDACTED]';
                }
                $formatted .= "• *{$key}:* `{$value}`\n";
            } elseif (is_array($value) && count($value) < 5) {
                $formatted .= "• *{$key}:* `" . json_encode($value) . "`\n";
            }
        }
        
        return $formatted;
    }
    
    /**
     * Get appropriate emoji for error type
     */
    private function getErrorEmoji(string $errorType): string
    {
        $emojiMap = [
            'database_connection_failed' => '💾❌',
            'cirx_transfer_failed' => '💸❌',
            'payment_verification_failed' => '💳❌',
            'blockchain_client_error' => '⛓️❌',
            'blockchain_rpc_error' => '🔗❌',
            'api_rate_limit_exceeded' => '🚦❌',
            'authentication_failure' => '🔐❌',
            'transaction_timeout' => '⏱️❌',
            'wallet_configuration_error' => '👛❌',
            'invalid_transaction_state' => '🔄❌',
            'general_error' => '⚠️',
            'default' => '🔴'
        ];
        
        return $emojiMap[$errorType] ?? $emojiMap['default'];
    }
    
    /**
     * Determine error priority level
     */
    private function getErrorPriority(string $errorType): string
    {
        if (in_array($errorType, $this->criticalErrorTypes)) {
            return 'critical';
        }
        
        if (in_array($errorType, $this->highPriorityErrorTypes)) {
            return 'high';
        }
        
        return 'low';
    }
    
    /**
     * Check if field contains sensitive data
     */
    private function isSensitiveField(string $fieldName): bool
    {
        $sensitiveFields = [
            'password',
            'secret',
            'token',
            'key',
            'private_key',
            'api_key',
            'auth',
            'authorization',
            'cookie',
            'session'
        ];
        
        $fieldLower = strtolower($fieldName);
        
        foreach ($sensitiveFields as $sensitive) {
            if (strpos($fieldLower, $sensitive) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Test Telegram connectivity
     */
    public function testConnection(): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/getMe', [
                'timeout' => 10
            ]);
            
            $result = json_decode($response->getBody()->getContents(), true);
            
            if ($result['ok'] ?? false) {
                $botInfo = $result['result'];
                
                return [
                    'success' => true,
                    'bot_username' => $botInfo['username'],
                    'bot_first_name' => $botInfo['first_name'],
                    'can_join_groups' => $botInfo['can_join_groups'] ?? false,
                    'can_read_all_group_messages' => $botInfo['can_read_all_group_messages'] ?? false
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result['description'] ?? 'Unknown error'
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send a test notification
     */
    public function sendTestNotification(): bool
    {
        $testMessage = "🧪 *CIRX Backend Test Notification*\n\n";
        $testMessage .= "✅ Telegram notifications are working!\n";
        $testMessage .= "⏰ *Time:* " . date('Y-m-d H:i:s T') . "\n";
        $testMessage .= "🖥️ *Server:* " . gethostname() . "\n";
        $testMessage .= "🌍 *Environment:* " . ($_ENV['APP_ENV'] ?? 'unknown');
        
        return $this->sendMessage($testMessage, false);
    }
}