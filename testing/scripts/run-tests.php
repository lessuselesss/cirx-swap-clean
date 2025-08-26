#!/usr/bin/env php
<?php

/**
 * Test Runner Script for CIRX OTC Backend
 * 
 * Provides convenient commands for running different types of tests:
 * - Unit tests (fast, no external dependencies)
 * - Integration tests (database required)
 * - E2E tests (blockchain connectivity and funding required)
 */

require_once __DIR__ . '/../vendor/autoload.php';

class TestRunner
{
    private array $config = [
        'phpunit_bin' => 'vendor/bin/phpunit',
        'base_dir' => __DIR__ . '/..',
        'coverage_dir' => 'coverage',
        'reports_dir' => 'reports'
    ];
    
    public function run(array $argv): int
    {
        $command = $argv[1] ?? 'help';
        
        // Ensure required directories exist
        $this->ensureDirectories();
        
        return match($command) {
            'unit' => $this->runUnitTests($argv),
            'integration' => $this->runIntegrationTests($argv),
            'e2e' => $this->runE2ETests($argv),
            'all' => $this->runAllTests($argv),
            'setup-e2e' => $this->setupE2ETesting($argv),
            'check-e2e' => $this->checkE2EEnvironment($argv),
            'coverage' => $this->generateCoverageReport($argv),
            'help' => $this->showHelp(),
            default => $this->showError("Unknown command: {$command}")
        };
    }
    
    private function runUnitTests(array $argv): int
    {
        $this->output("Running Unit Tests...");
        
        $options = $this->parseTestOptions($argv);
        $command = $this->buildPhpunitCommand('--testsuite=Unit', $options);
        
        return $this->executeCommand($command);
    }
    
    private function runIntegrationTests(array $argv): int
    {
        $this->output("Running Integration Tests...");
        
        // Ensure test database exists
        $this->setupTestDatabase();
        
        $options = $this->parseTestOptions($argv);
        $command = $this->buildPhpunitCommand('--testsuite=Integration', $options);
        
        return $this->executeCommand($command);
    }
    
    private function runE2ETests(array $argv): int
    {
        $this->output("Running E2E Tests...");
        
        // Check E2E environment
        if (!$this->checkE2EEnvironment($argv, false)) {
            $this->output("E2E environment check failed. Run 'check-e2e' for details.");
            return 1;
        }
        
        $options = $this->parseTestOptions($argv);
        $command = $this->buildPhpunitCommand('--configuration=phpunit.e2e.xml', $options);
        
        return $this->executeCommand($command);
    }
    
    private function runAllTests(array $argv): int
    {
        $this->output("Running All Tests...");
        
        $results = [];
        
        // Run unit tests
        $this->output("\n=== UNIT TESTS ===");
        $results['unit'] = $this->runUnitTests(['', 'unit', '--no-coverage']);
        
        // Run integration tests
        $this->output("\n=== INTEGRATION TESTS ===");
        $results['integration'] = $this->runIntegrationTests(['', 'integration', '--no-coverage']);
        
        // Run E2E tests if environment is available
        if ($this->checkE2EEnvironment($argv, false)) {
            $this->output("\n=== E2E TESTS ===");
            $results['e2e'] = $this->runE2ETests(['', 'e2e', '--no-coverage']);
        } else {
            $this->output("\n=== E2E TESTS SKIPPED ===");
            $this->output("E2E environment not configured. Run 'setup-e2e' to configure.");
            $results['e2e'] = 0; // Don't fail on missing E2E environment
        }
        
        // Generate combined coverage report
        if (!in_array('--no-coverage', $argv)) {
            $this->output("\n=== COVERAGE REPORT ===");
            $this->generateCoverageReport(['', 'coverage']);
        }
        
        // Report results
        $this->output("\n=== TEST RESULTS SUMMARY ===");
        $totalFailures = 0;
        foreach ($results as $type => $result) {
            $status = $result === 0 ? '✓ PASSED' : '✗ FAILED';
            $this->output("{$type}: {$status}");
            $totalFailures += $result;
        }
        
        if ($totalFailures === 0) {
            $this->output("\n🎉 All tests passed!");
        } else {
            $this->output("\n❌ Some tests failed. Check output above for details.");
        }
        
        return min($totalFailures, 1); // Return 1 if any failures, 0 if all passed
    }
    
    private function setupE2ETesting(array $argv): int
    {
        $this->output("Setting up E2E Testing Environment...");
        
        $envFile = $this->config['base_dir'] . '/.env';
        
        if (!file_exists($envFile)) {
            $this->output("❌ .env file not found. Please create it first.");
            return 1;
        }
        
        $envContents = file_get_contents($envFile);
        $requiredVars = [
            'SEED_PHRASE' => 'your seed phrase goes here',
            'SEPOLIA_RPC_URL' => 'https://sepolia.infura.io/v3/your-project-id',
            'E2E_TESTING_ENABLED' => 'true'
        ];
        
        $missingVars = [];
        foreach ($requiredVars as $var => $placeholder) {
            if (strpos($envContents, "{$var}=") === false || 
                strpos($envContents, $placeholder) !== false) {
                $missingVars[] = $var;
            }
        }
        
        if (!empty($missingVars)) {
            $this->output("❌ Missing or placeholder E2E configuration variables:");
            foreach ($missingVars as $var) {
                $this->output("  - {$var}");
            }
            $this->output("\nPlease update your .env file with actual values.");
            return 1;
        }
        
        // Create E2E test database
        $this->setupE2EDatabase();
        
        $this->output("✅ E2E testing environment configured successfully!");
        $this->output("\nTo run E2E tests:");
        $this->output("  php bin/run-tests.php e2e");
        
        return 0;
    }
    
    private function checkE2EEnvironment(array $argv, bool $showOutput = true): bool
    {
        if ($showOutput) {
            $this->output("Checking E2E Testing Environment...");
        }
        
        $envFile = $this->config['base_dir'] . '/.env';
        if (!file_exists($envFile)) {
            if ($showOutput) $this->output("❌ .env file not found");
            return false;
        }
        
        $envContents = file_get_contents($envFile);
        $checks = [
            'SEED_PHRASE' => [
                'required' => true,
                'check' => fn($val) => !empty($val) && $val !== 'your seed phrase goes here'
            ],
            'SEPOLIA_RPC_URL' => [
                'required' => true,
                'check' => fn($val) => !empty($val) && strpos($val, 'your-project-id') === false
            ],
            'E2E_TESTING_ENABLED' => [
                'required' => true,
                'check' => fn($val) => $val === 'true'
            ]
        ];
        
        $allPassed = true;
        foreach ($checks as $var => $config) {
            $envVar = $_ENV[$var] ?? '';
            
            if ($config['required'] && !$config['check']($envVar)) {
                if ($showOutput) {
                    $this->output("❌ {$var}: " . ($envVar ? 'Invalid value' : 'Not set'));
                }
                $allPassed = false;
            } elseif ($showOutput) {
                $this->output("✅ {$var}: OK");
            }
        }
        
        if ($showOutput) {
            if ($allPassed) {
                $this->output("\n🎉 E2E environment is properly configured!");
            } else {
                $this->output("\n❌ E2E environment needs configuration. Run 'setup-e2e' for help.");
            }
        }
        
        return $allPassed;
    }
    
    private function generateCoverageReport(array $argv): int
    {
        $this->output("Generating Coverage Report...");
        
        $command = $this->buildPhpunitCommand('--coverage-html=' . $this->config['coverage_dir']);
        return $this->executeCommand($command);
    }
    
    private function parseTestOptions(array $argv): array
    {
        $options = [];
        
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--')) {
                $options[] = $arg;
            }
        }
        
        return $options;
    }
    
    private function buildPhpunitCommand(string $baseOptions, array $additionalOptions = []): string
    {
        $command = $this->config['phpunit_bin'] . ' ' . $baseOptions;
        
        // Add additional options
        foreach ($additionalOptions as $option) {
            if (!str_contains($command, $option)) {
                $command .= ' ' . $option;
            }
        }
        
        return $command;
    }
    
    private function setupTestDatabase(): void
    {
        $dbPath = $this->config['base_dir'] . '/storage/testing.sqlite';
        $dbDir = dirname($dbPath);
        
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }
        
        if (!file_exists($dbPath)) {
            touch($dbPath);
        }
    }
    
    private function setupE2EDatabase(): void
    {
        $dbPath = $this->config['base_dir'] . '/storage/testing.e2e.sqlite';
        $dbDir = dirname($dbPath);
        
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }
        
        if (!file_exists($dbPath)) {
            touch($dbPath);
        }
    }
    
    private function ensureDirectories(): void
    {
        $dirs = [
            $this->config['coverage_dir'],
            $this->config['reports_dir'],
            'storage'
        ];
        
        foreach ($dirs as $dir) {
            $fullPath = $this->config['base_dir'] . '/' . $dir;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
            }
        }
    }
    
    private function executeCommand(string $command): int
    {
        $this->output("Executing: {$command}");
        
        $cwd = $this->config['base_dir'];
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ], $pipes, $cwd);
        
        if (!is_resource($process)) {
            $this->output("❌ Failed to start process");
            return 1;
        }
        
        fclose($pipes[0]);
        
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        
        fclose($pipes[1]);
        fclose($pipes[2]);
        
        $returnCode = proc_close($process);
        
        if (!empty($output)) {
            echo $output;
        }
        
        if (!empty($error)) {
            echo $error;
        }
        
        return $returnCode;
    }
    
    private function output(string $message): void
    {
        echo $message . "\n";
    }
    
    private function showError(string $message): int
    {
        $this->output("❌ Error: {$message}");
        $this->showHelp();
        return 1;
    }
    
    private function showHelp(): int
    {
        $this->output("CIRX OTC Backend Test Runner");
        $this->output("");
        $this->output("Usage: php bin/run-tests.php <command> [options]");
        $this->output("");
        $this->output("Commands:");
        $this->output("  unit          Run unit tests (fast, no external dependencies)");
        $this->output("  integration   Run integration tests (requires database)");
        $this->output("  e2e           Run E2E tests (requires blockchain connectivity)");
        $this->output("  all           Run all test suites");
        $this->output("  setup-e2e     Setup E2E testing environment");
        $this->output("  check-e2e     Check E2E environment configuration");
        $this->output("  coverage      Generate coverage report");
        $this->output("  help          Show this help message");
        $this->output("");
        $this->output("Options:");
        $this->output("  --filter=X    Run only tests matching pattern X");
        $this->output("  --no-coverage Disable coverage reporting");
        $this->output("  --verbose     Show detailed output");
        $this->output("  --debug       Show debug information");
        $this->output("");
        $this->output("Examples:");
        $this->output("  php bin/run-tests.php unit");
        $this->output("  php bin/run-tests.php e2e --filter=testCompleteETHSwapFlow");
        $this->output("  php bin/run-tests.php all --no-coverage");
        $this->output("");
        
        return 0;
    }
}

// Run the test runner
$runner = new TestRunner();
exit($runner->run($argv));