<?php

/**
 * Migration: Add retry fields to transactions table
 * Created: 2024-01-02
 * Description: Adds retry_count and last_retry_at fields for background worker retry logic
 */

use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function ($schema) {
        $schema->table('transactions', function (Blueprint $table) {
            $table->integer('retry_count')->default(0)->after('failure_reason');
            $table->datetime('last_retry_at')->nullable()->after('retry_count');
            
            // Add composite index for worker queries
            $table->index(['retry_count', 'last_retry_at'], 'idx_retry_fields');
            $table->index(['swap_status', 'retry_count'], 'idx_status_retry');
        });
    },
    
    'down' => function ($schema) {
        $schema->table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_retry_fields');
            $table->dropIndex('idx_status_retry');
            $table->dropColumn(['retry_count', 'last_retry_at']);
        });
    }
];