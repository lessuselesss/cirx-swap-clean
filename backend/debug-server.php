<?php

require_once 'vendor/autoload.php';

echo "🔍 Debugging Server Issues\n";
echo "==========================\n\n";

// Test 1: Check if we can create Transaction without server
echo "1. Testing Transaction model directly...\n";

use App\Models\Transaction;
use Illuminate\Database\Capsule\Manager as Capsule;

try {
    // Set up database connection (in-memory SQLite for testing)
    $capsule = new Capsule();
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    // Create test table
    $schema = $capsule->schema();
    $schema->create('transactions', function ($table) {
        $table->string('id', 36)->primary();
        $table->string('payment_tx_id', 255)->unique();
        $table->string('payment_chain', 50);
        $table->string('cirx_recipient_address', 255);
        $table->decimal('amount_paid', 65, 18);
        $table->string('payment_token', 10);
        $table->enum('swap_status', [
            'pending_payment_verification',
            'payment_verified',
            'cirx_transfer_pending',
            'cirx_transfer_initiated',
            'completed',
            'failed_payment_verification',
            'failed_cirx_transfer'
        ])->default('pending_payment_verification');
        $table->string('cirx_transfer_tx_id', 255)->nullable();
        $table->text('failure_reason')->nullable();
        $table->timestamps();
        
        $table->index('payment_tx_id');
        $table->index('cirx_recipient_address');
    });

    // Test Transaction creation
    $transaction = Transaction::create([
        'id' => '123e4567-e89b-12d3-a456-426614174000',
        'payment_tx_id' => '0x123456789abcdef123456789abcdef123456789abcdef123456789abcdef1234',
        'payment_chain' => 'ethereum',
        'cirx_recipient_address' => '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
        'amount_paid' => '1.5',
        'payment_token' => 'ETH',
        'swap_status' => 'pending_payment_verification',
    ]);

    echo "   ✅ Transaction model works: {$transaction->id}\n";
    
} catch (Exception $e) {
    echo "   ❌ Transaction model error: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
}

// Test 2: Test Controller directly
echo "\n2. Testing TransactionController directly...\n";

use App\Controllers\TransactionController;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

try {

    $controller = new TransactionController();
    $requestFactory = new ServerRequestFactory();
    $responseFactory = new ResponseFactory();

    $requestData = [
        'txId' => '0x223456789abcdef123456789abcdef123456789abcdef123456789abcdef1234',
        'paymentChain' => 'ethereum',
        'cirxRecipientAddress' => '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
        'amountPaid' => '2.0',
        'paymentToken' => 'ETH'
    ];

    $request = $requestFactory->createServerRequest('POST', '/transactions/initiate-swap');
    $request = $request->withParsedBody($requestData);
    $response = $responseFactory->createResponse();

    $result = $controller->initiateSwap($request, $response);
    $body = json_decode((string) $result->getBody(), true);

    echo "   ✅ Controller works. Status: {$result->getStatusCode()}\n";
    echo "   📄 Response: " . json_encode($body, JSON_PRETTY_PRINT) . "\n";
    
} catch (Exception $e) {
    echo "   ❌ Controller error: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n🔍 Debug complete!\n";