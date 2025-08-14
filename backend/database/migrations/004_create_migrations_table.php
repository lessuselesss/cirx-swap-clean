<?php

/**
 * Migration: Create migrations tracking table
 * Created: 2024-01-01
 * Description: Creates the migrations table to track which migrations have been run
 */

use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function ($schema) {
        $schema->create('migrations', function (Blueprint $table) {
            $table->id();
            $table->string('migration', 255);
            $table->integer('batch');
            $table->timestamp('created_at')->useCurrent();
            
            $table->unique('migration');
            $table->index('batch');
        });
    },
    
    'down' => function ($schema) {
        $schema->dropIfExists('migrations');
    }
];