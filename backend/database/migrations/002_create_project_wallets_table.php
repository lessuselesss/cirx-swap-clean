<?php

/**
 * Migration: Create project_wallets table
 * Created: 2024-01-01
 * Description: Creates the project wallets table for storing encrypted wallet credentials
 */

use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function ($schema) {
        $schema->create('project_wallets', function (Blueprint $table) {
            $table->id();
            $table->string('chain', 50);
            $table->string('address', 255);
            $table->text('private_key_encrypted');
            $table->boolean('is_cirx_treasury_wallet')->default(false);
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['chain', 'is_cirx_treasury_wallet']);
            $table->index('address');
        });
    },
    
    'down' => function ($schema) {
        $schema->dropIfExists('project_wallets');
    }
];