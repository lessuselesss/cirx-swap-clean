<?php

/**
 * Migration: Create transactions table
 * Created: 2024-01-01
 * Description: Creates the main transactions table for CIRX OTC swaps
 */

use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function ($schema) {
        $schema->create('transactions', function (Blueprint $table) {
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
            
            // Indexes for performance
            $table->index('payment_tx_id');
            $table->index('cirx_recipient_address');
            $table->index('swap_status');
            $table->index('created_at');
        });
    },
    
    'down' => function ($schema) {
        $schema->dropIfExists('transactions');
    }
];