<?php

namespace Database;

use Exception;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Database migration system for CIRX OTC Backend
 * 
 * Handles versioned migrations with rollback support and proper tracking
 */
class Migrator
{
    private $schema;
    private $connection;
    private $migrationsPath;
    private $currentBatch = 0;

    public function __construct(Capsule $capsule, string $migrationsPath = null)
    {
        $this->schema = $capsule->schema();
        $this->connection = $capsule->getConnection();
        $this->migrationsPath = $migrationsPath ?? __DIR__ . '/migrations';
    }

    /**
     * Run all pending migrations
     */
    public function migrate(): array
    {
        $this->ensureMigrationsTable();
        
        $results = [
            'migrations_run' => [],
            'already_run' => [],
            'errors' => []
        ];

        $pendingMigrations = $this->getPendingMigrations();
        
        if (empty($pendingMigrations)) {
            echo "✅ No pending migrations found.\n";
            return $results;
        }

        $this->currentBatch = $this->getNextBatchNumber();
        
        foreach ($pendingMigrations as $migration) {
            try {
                echo "🔄 Running migration: {$migration}\n";
                
                $this->runMigration($migration, 'up');
                $this->recordMigration($migration);
                
                $results['migrations_run'][] = $migration;
                echo "✅ Migration completed: {$migration}\n";
                
            } catch (Exception $e) {
                $error = "❌ Migration failed: {$migration} - " . $e->getMessage();
                echo $error . "\n";
                $results['errors'][] = $error;
                break; // Stop on first error
            }
        }

        return $results;
    }

    /**
     * Rollback the last batch of migrations
     */
    public function rollback(int $steps = 1): array
    {
        $this->ensureMigrationsTable();
        
        $results = [
            'migrations_rolled_back' => [],
            'errors' => []
        ];

        $batches = $this->getMigrationBatches($steps);
        
        if (empty($batches)) {
            echo "✅ No migrations to rollback.\n";
            return $results;
        }

        foreach ($batches as $batch) {
            $migrations = $this->getMigrationsInBatch($batch);
            
            // Rollback in reverse order
            foreach (array_reverse($migrations) as $migration) {
                try {
                    echo "🔄 Rolling back migration: {$migration}\n";
                    
                    $this->runMigration($migration, 'down');
                    $this->removeMigrationRecord($migration);
                    
                    $results['migrations_rolled_back'][] = $migration;
                    echo "✅ Rollback completed: {$migration}\n";
                    
                } catch (Exception $e) {
                    $error = "❌ Rollback failed: {$migration} - " . $e->getMessage();
                    echo $error . "\n";
                    $results['errors'][] = $error;
                    return $results; // Stop on first error
                }
            }
        }

        return $results;
    }

    /**
     * Get migration status
     */
    public function status(): array
    {
        $this->ensureMigrationsTable();
        
        $allMigrations = $this->getAllMigrationFiles();
        $runMigrations = $this->getRunMigrations();
        
        $status = [];
        foreach ($allMigrations as $migration) {
            $status[] = [
                'migration' => $migration,
                'status' => in_array($migration, $runMigrations) ? 'RAN' : 'PENDING',
                'batch' => $this->getMigrationBatch($migration)
            ];
        }
        
        return $status;
    }

    /**
     * Reset database - rollback all migrations
     */
    public function reset(): array
    {
        $this->ensureMigrationsTable();
        
        $allBatches = $this->getAllBatches();
        return $this->rollback(count($allBatches));
    }

    /**
     * Fresh migration - reset and then migrate
     */
    public function fresh(): array
    {
        $resetResults = $this->reset();
        $migrateResults = $this->migrate();
        
        return array_merge_recursive($resetResults, $migrateResults);
    }

    /**
     * Ensure migrations tracking table exists
     */
    private function ensureMigrationsTable(): void
    {
        if (!$this->schema->hasTable('migrations')) {
            $this->schema->create('migrations', function ($table) {
                $table->id();
                $table->string('migration', 255);
                $table->integer('batch');
                $table->timestamp('created_at')->useCurrent();
                
                $table->unique('migration');
                $table->index('batch');
            });
            
            echo "✅ Created migrations tracking table\n";
        }
    }

    /**
     * Get all migration files that haven't been run
     */
    private function getPendingMigrations(): array
    {
        $allMigrations = $this->getAllMigrationFiles();
        $runMigrations = $this->getRunMigrations();
        
        return array_values(array_diff($allMigrations, $runMigrations));
    }

    /**
     * Get all migration files from the migrations directory
     */
    private function getAllMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }
        
        $files = scandir($this->migrationsPath);
        $migrations = [];
        
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php' && $file !== '.' && $file !== '..') {
                $migrations[] = pathinfo($file, PATHINFO_FILENAME);
            }
        }
        
        sort($migrations);
        return $migrations;
    }

    /**
     * Get migrations that have already been run
     */
    private function getRunMigrations(): array
    {
        if (!$this->schema->hasTable('migrations')) {
            return [];
        }
        
        return $this->connection->table('migrations')
            ->orderBy('migration')
            ->pluck('migration')
            ->toArray();
    }

    /**
     * Run a single migration
     */
    private function runMigration(string $migration, string $direction = 'up'): void
    {
        $migrationFile = $this->migrationsPath . "/{$migration}.php";
        
        if (!file_exists($migrationFile)) {
            throw new Exception("Migration file not found: {$migrationFile}");
        }
        
        $migrationData = require $migrationFile;
        
        if (!isset($migrationData[$direction]) || !is_callable($migrationData[$direction])) {
            throw new Exception("Migration {$migration} does not have a valid '{$direction}' method");
        }
        
        $migrationData[$direction]($this->schema);
    }

    /**
     * Record a migration as completed
     */
    private function recordMigration(string $migration): void
    {
        $this->connection->table('migrations')->insert([
            'migration' => $migration,
            'batch' => $this->currentBatch
        ]);
    }

    /**
     * Remove migration record
     */
    private function removeMigrationRecord(string $migration): void
    {
        $this->connection->table('migrations')
            ->where('migration', $migration)
            ->delete();
    }

    /**
     * Get the next batch number
     */
    private function getNextBatchNumber(): int
    {
        if (!$this->schema->hasTable('migrations')) {
            return 1;
        }
        
        return $this->connection->table('migrations')->max('batch') + 1;
    }

    /**
     * Get migration batches for rollback
     */
    private function getMigrationBatches(int $steps): array
    {
        if (!$this->schema->hasTable('migrations')) {
            return [];
        }
        
        return $this->connection->table('migrations')
            ->distinct()
            ->orderByDesc('batch')
            ->limit($steps)
            ->pluck('batch')
            ->toArray();
    }

    /**
     * Get migrations in a specific batch
     */
    private function getMigrationsInBatch(int $batch): array
    {
        return $this->connection->table('migrations')
            ->where('batch', $batch)
            ->orderBy('migration')
            ->pluck('migration')
            ->toArray();
    }

    /**
     * Get all batches
     */
    private function getAllBatches(): array
    {
        if (!$this->schema->hasTable('migrations')) {
            return [];
        }
        
        return $this->connection->table('migrations')
            ->distinct()
            ->orderByDesc('batch')
            ->pluck('batch')
            ->toArray();
    }

    /**
     * Get batch number for a migration
     */
    private function getMigrationBatch(string $migration): ?int
    {
        if (!$this->schema->hasTable('migrations')) {
            return null;
        }
        
        $result = $this->connection->table('migrations')
            ->where('migration', $migration)
            ->first();
            
        return $result ? $result->batch : null;
    }
}