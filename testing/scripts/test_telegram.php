<?php

require __DIR__ . '/vendor/autoload.php';

use App\Services\LoggerService;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
try {
    $dotenv->load();
    echo "✅ Environment loaded successfully\n";
} catch (Exception $e) {
    echo "❌ Environment loading failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "🔧 Testing Telegram Integration for CIRX Backend\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// Test 1: Check configuration
echo "1. Testing Configuration:\n";
$botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
$chatId = $_ENV['TELEGRAM_CHAT_ID'] ?? null;

if (empty($botToken)) {
    echo "❌ TELEGRAM_BOT_TOKEN not configured\n";
} else {
    echo "✅ TELEGRAM_BOT_TOKEN configured\n";
}

if (empty($chatId)) {
    echo "❌ TELEGRAM_CHAT_ID not configured\n";
} else {
    echo "✅ TELEGRAM_CHAT_ID configured: {$chatId}\n";
}

if (empty($botToken) || empty($chatId)) {
    echo "\n⚠️ Please configure Telegram credentials in .env file:\n";
    echo "TELEGRAM_BOT_TOKEN=your_bot_token_here\n";
    echo "TELEGRAM_CHAT_ID=your_chat_id_here\n\n";
    echo "See TELEGRAM_SETUP.md for detailed instructions.\n";
    exit(1);
}

echo "\n2. Testing Telegram Notifications:\n";

try {
    // Test Telegram notification functionality
    $results = LoggerService::testTelegramNotifications();
    
    if ($results['success']) {
        echo "✅ Telegram notifications test successful!\n";
        foreach ($results['tests'] as $test) {
            echo "   {$test}\n";
        }
    } else {
        echo "❌ Telegram notifications test failed!\n";
        foreach ($results['errors'] as $error) {
            echo "   Error: {$error}\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Exception during testing: " . $e->getMessage() . "\n";
}

echo "\n3. Testing Error Logging Integration:\n";

try {
    // Trigger a test error through the logging system
    echo "   Triggering test error...\n";
    LoggerService::triggerTestError();
    echo "✅ Test error logged successfully (check your Telegram chat)\n";
} catch (Exception $e) {
    echo "❌ Failed to trigger test error: " . $e->getMessage() . "\n";
}

echo "\n4. Testing Different Error Types:\n";

$testErrors = [
    'cirx_transfer_failed' => 'Test CIRX transfer failure (CRITICAL)',
    'payment_verification_failed' => 'Test payment verification failure (CRITICAL)', 
    'api_rate_limit_exceeded' => 'Test rate limit exceeded (HIGH)',
    'general_error' => 'Test general error (LOW)'
];

foreach ($testErrors as $errorType => $message) {
    try {
        $logger = LoggerService::getLogger('test');
        $logger->error($message, [
            'error_type' => $errorType,
            'test' => true,
            'timestamp' => date('c'),
            'test_data' => ['value' => rand(1, 1000)]
        ]);
        echo "✅ {$errorType}: Logged successfully\n";
        
        // Small delay to prevent rate limiting
        usleep(200000); // 0.2 seconds
        
    } catch (Exception $e) {
        echo "❌ {$errorType}: Failed - " . $e->getMessage() . "\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "🎉 Telegram integration testing complete!\n\n";

echo "📱 Check your Telegram chat for notifications.\n";
echo "🔧 If you don't see messages, check:\n";
echo "   - Bot token and chat ID are correct\n";
echo "   - Bot has permission to send messages\n";
echo "   - Network connectivity to api.telegram.org\n\n";

echo "📚 For troubleshooting, see: TELEGRAM_SETUP.md\n";
echo "🌐 Test via API: http://localhost:8080/api/v1/telegram/test/connection\n";