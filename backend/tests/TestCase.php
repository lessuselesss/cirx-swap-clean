<?php

namespace Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Builder;

abstract class TestCase extends PHPUnitTestCase
{
    protected Capsule $db;
    protected Builder $schema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
        parent::tearDown();
    }

    protected function setUpDatabase(): void
    {
        $this->db = new Capsule();
        
        // Configure in-memory SQLite for testing
        $this->db->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $this->db->setAsGlobal();
        $this->db->bootEloquent();
        
        $this->schema = $this->db->schema();
        
        // Create test tables
        $this->createTestTables();
    }

    protected function tearDownDatabase(): void
    {
        // Clean up
        if ($this->schema->hasTable('transactions')) {
            $this->schema->drop('transactions');
        }
        if ($this->schema->hasTable('project_wallets')) {
            $this->schema->drop('project_wallets');
        }
    }

    protected function createTestTables(): void
    {
        // Create transactions table
        if (!$this->schema->hasTable('transactions')) {
            $this->schema->create('transactions', function ($table) {
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
                $table->timestamp('last_retry_at')->nullable();
                $table->timestamps();
                
                $table->index('payment_tx_id');
                $table->index('cirx_recipient_address');
            });
        }

        // Create project_wallets table
        if (!$this->schema->hasTable('project_wallets')) {
            $this->schema->create('project_wallets', function ($table) {
                $table->id();
                $table->string('chain', 50);
                $table->string('address', 255);
                $table->text('private_key_encrypted');
                $table->boolean('is_cirx_treasury_wallet')->default(false);
                $table->timestamps();
            });
        }
    }

    protected function createTransaction(array $attributes = []): array
    {
        $defaults = [
            'id' => $this->generateUuid(),
            'payment_tx_id' => '0x' . bin2hex(random_bytes(32)),
            'payment_chain' => 'ethereum',
            'cirx_recipient_address' => '0x' . bin2hex(random_bytes(20)),
            'amount_paid' => '1.0',
            'payment_token' => 'ETH',
            'swap_status' => 'pending_payment_verification',
            'created_at' => new \DateTime(),
            'updated_at' => new \DateTime(),
        ];

        return array_merge($defaults, $attributes);
    }

    protected function generateUuid(): string
    {
        return \Ramsey\Uuid\Uuid::uuid4()->toString();
    }
}