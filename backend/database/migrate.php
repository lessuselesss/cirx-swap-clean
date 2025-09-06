<?php

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Dotenv\Dotenv;

echo "🗄️  Running Database Migrations\n";
echo "==============================\n\n";

// Load environment variables - try .env file first, fallback to environment
try {
    // Try to load .env from parent directory (backend/)
    $dotenv = Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->load();
    echo "✅  Loaded environment from .env file\n";
} catch (Exception $e) {
    // Fallback to environment variables (for GitHub Actions)
    echo "⚠️  Could not load .env file: " . $e->getMessage() . "\n";
    echo "✅  Using environment variables instead\n";
    
    // Verify required environment variables exist
    if (!getenv('DB_CONNECTION')) {
        echo "❌  Error: DB_CONNECTION environment variable not set\n";
        exit(1);
    }
}

// Set up database connection
$capsule = new Capsule();

if ($_ENV['DB_CONNECTION'] === 'sqlite') {
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => $_ENV['DB_DATABASE'],
        'prefix' => '',
    ]);
} else {
    $capsule->addConnection([
        'driver' => $_ENV['DB_CONNECTION'],
        'host' => $_ENV['DB_HOST'],
        'port' => $_ENV['DB_PORT'] ?? 3306,
        'database' => $_ENV['DB_DATABASE'],
        'username' => $_ENV['DB_USERNAME'],
        'password' => $_ENV['DB_PASSWORD'],
        'charset' => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix' => '',
    ]);
}

$capsule->setAsGlobal();
$capsule->bootEloquent();

$schema = $capsule->schema();

try {
    echo "1. Creating/updating transactions table...\n";
    
    if ($schema->hasTable('transactions')) {
        echo "   ℹ️  Table 'transactions' exists, checking for missing columns...\n";
        
        // Add missing retry_count column
        if (!$schema->hasColumn('transactions', 'retry_count')) {
            $schema->table('transactions', function ($table) {
                $table->integer('retry_count')->default(0)->after('failure_reason');
            });
            echo "   ✅ Added 'retry_count' column\n";
        }
        
        // Add missing last_retry_at column
        if (!$schema->hasColumn('transactions', 'last_retry_at')) {
            $schema->table('transactions', function ($table) {
                $table->datetime('last_retry_at')->nullable()->after('retry_count');
            });
            echo "   ✅ Added 'last_retry_at' column\n";
        }
        
        // Add missing index for retry fields
        $indexes = $schema->getConnection()->getDoctrineSchemaManager()->listTableIndexes('transactions');
        $hasRetryIndex = false;
        foreach ($indexes as $index) {
            if (in_array('retry_count', $index->getColumns()) && in_array('last_retry_at', $index->getColumns())) {
                $hasRetryIndex = true;
                break;
            }
        }
        
        if (!$hasRetryIndex) {
            $schema->table('transactions', function ($table) {
                $table->index(['retry_count', 'last_retry_at'], 'idx_retry_fields');
            });
            echo "   ✅ Added retry fields index\n";
        }
        
        echo "   ✅ Table 'transactions' updated successfully\n";
    } else {
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
            $table->integer('retry_count')->default(0);
            $table->datetime('last_retry_at')->nullable();
            $table->timestamps();
            
            $table->index('payment_tx_id');
            $table->index('cirx_recipient_address');
            $table->index('swap_status');
            $table->index(['retry_count', 'last_retry_at']);
        });
        echo "   ✅ Table 'transactions' created successfully\n";
    }

    echo "\n2. Creating project_wallets table...\n";
    
    if ($schema->hasTable('project_wallets')) {
        echo "   ⏭️  Table 'project_wallets' already exists, skipping\n";
    } else {
        $schema->create('project_wallets', function ($table) {
            $table->id();
            $table->string('chain', 50);
            $table->string('address', 255);
            $table->text('private_key_encrypted');
            $table->boolean('is_cirx_treasury_wallet')->default(false);
            $table->timestamps();
            
            $table->index(['chain', 'is_cirx_treasury_wallet']);
        });
        echo "   ✅ Table 'project_wallets' created successfully\n";
    }

    echo "\n🎉 All migrations completed successfully!\n";
    echo "✅ Database is ready for use\n";

} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}