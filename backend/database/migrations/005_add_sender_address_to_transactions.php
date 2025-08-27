<?php

/**
 * Migration: Add sender_address to transactions table
 * Created: 2025-08-27
 * Description: Adds sender_address field to capture the original wallet address that initiated the payment
 */

use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function ($schema) {
        $schema->table('transactions', function (Blueprint $table) {
            // Add sender_address field to store the wallet address that sent the payment
            $table->string('sender_address', 255)->nullable()->after('payment_chain');
            
            // Add index for sender address queries
            $table->index('sender_address', 'idx_sender_address');
            
            // Add composite index for sender + status queries
            $table->index(['sender_address', 'swap_status'], 'idx_sender_status');
        });
    },
    
    'down' => function ($schema) {
        $schema->table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_sender_address');
            $table->dropIndex('idx_sender_status');
            $table->dropColumn('sender_address');
        });
    }
];