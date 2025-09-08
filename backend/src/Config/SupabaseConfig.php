<?php

namespace App\Config;

/**
 * Supabase Configuration Helper
 * 
 * This class manages the database connection configuration for Supabase PostgreSQL
 * replacing the SQLite configuration with a more robust cloud-based solution.
 */
class SupabaseConfig
{
    /**
     * Get database configuration based on environment
     * 
     * @return array Database configuration array for Eloquent
     */
    public static function getDatabaseConfig(): array
    {
        $connectionType = $_ENV['DB_CONNECTION'] ?? 'pgsql';
        
        // If still using SQLite (legacy/local development)
        if ($connectionType === 'sqlite') {
            return self::getSqliteConfig();
        }
        
        // Use PostgreSQL/Supabase for production
        return self::getSupabaseConfig();
    }
    
    /**
     * Get Supabase PostgreSQL configuration
     * 
     * @return array PostgreSQL configuration for Eloquent
     */
    private static function getSupabaseConfig(): array
    {
        // Parse DATABASE_URL if provided (recommended approach)
        if (isset($_ENV['DATABASE_URL'])) {
            return self::parseConnectionUrl($_ENV['DATABASE_URL']);
        }
        
        // Fall back to individual environment variables
        return [
            'driver' => 'pgsql',
            'host' => $_ENV['DB_HOST'] ?? 'aws-0-us-east-1.pooler.supabase.com',
            'port' => $_ENV['DB_PORT'] ?? '6543', // Transaction pooler port
            'database' => $_ENV['DB_DATABASE'] ?? 'postgres',
            'username' => $_ENV['DB_USERNAME'] ?? 'postgres',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => $_ENV['DB_SCHEMA'] ?? 'public',
            'sslmode' => $_ENV['DB_SSLMODE'] ?? 'require',
            // Connection pooling settings for serverless
            'options' => [
                \PDO::ATTR_PERSISTENT => false, // Don't use persistent connections with pooler
                \PDO::ATTR_TIMEOUT => 30,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
            ]
        ];
    }
    
    /**
     * Parse a PostgreSQL connection URL
     * 
     * @param string $url The connection URL
     * @return array Parsed configuration
     */
    private static function parseConnectionUrl(string $url): array
    {
        $parsed = parse_url($url);
        
        if (!$parsed) {
            throw new \Exception('Invalid DATABASE_URL format');
        }
        
        $config = [
            'driver' => 'pgsql',
            'host' => $parsed['host'] ?? 'localhost',
            'port' => $parsed['port'] ?? 5432,
            'database' => ltrim($parsed['path'] ?? '', '/') ?: 'postgres',
            'username' => $parsed['user'] ?? 'postgres',
            'password' => $parsed['pass'] ?? '',
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'require',
            'options' => [
                \PDO::ATTR_PERSISTENT => false,
                \PDO::ATTR_TIMEOUT => 30,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
            ]
        ];
        
        // Parse additional options from query string
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $params);
            
            if (isset($params['sslmode'])) {
                $config['sslmode'] = $params['sslmode'];
            }
            
            if (isset($params['search_path'])) {
                $config['search_path'] = $params['search_path'];
            }
            
            // Handle pgbouncer mode
            if (isset($params['pgbouncer']) && $params['pgbouncer'] === 'true') {
                // Disable prepared statements for transaction pooling mode
                $config['options'][\PDO::ATTR_EMULATE_PREPARES] = true;
            }
        }
        
        return $config;
    }
    
    /**
     * Get SQLite configuration (legacy/fallback)
     * 
     * @return array SQLite configuration for Eloquent
     */
    private static function getSqliteConfig(): array
    {
        $dbPath = $_ENV['DB_DATABASE'] ?? 'storage/database.sqlite';
        
        // Make path absolute if it's relative
        if (!str_starts_with($dbPath, '/')) {
            $dbPath = dirname(__DIR__, 2) . '/' . $dbPath;
        }
        
        return [
            'driver' => 'sqlite',
            'database' => $dbPath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ];
    }
    
    /**
     * Test database connection
     * 
     * @return bool True if connection successful
     */
    public static function testConnection(): bool
    {
        try {
            $config = self::getDatabaseConfig();
            
            if ($config['driver'] === 'pgsql') {
                $dsn = sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s',
                    $config['host'],
                    $config['port'],
                    $config['database']
                );
                
                $pdo = new \PDO(
                    $dsn,
                    $config['username'],
                    $config['password'],
                    $config['options'] ?? []
                );
                
                // Test with a simple query
                $result = $pdo->query('SELECT NOW()')->fetch();
                return !empty($result);
                
            } else {
                // SQLite test
                if (file_exists($config['database'])) {
                    $pdo = new \PDO('sqlite:' . $config['database']);
                    $result = $pdo->query('SELECT datetime("now")')->fetch();
                    return !empty($result);
                }
            }
            
            return false;
            
        } catch (\Exception $e) {
            error_log('Database connection test failed: ' . $e->getMessage());
            return false;
        }
    }
    
    
    /**
     * Get connection info for debugging
     * 
     * @return array Connection information (with password masked)
     */
    public static function getConnectionInfo(): array
    {
        $config = self::getDatabaseConfig();
        
        // Mask sensitive information
        if (isset($config['password'])) {
            $config['password'] = substr($config['password'], 0, 3) . '***';
        }
        
        return [
            'driver' => $config['driver'],
            'host' => $config['host'] ?? 'N/A',
            'port' => $config['port'] ?? 'N/A',
            'database' => $config['database'] ?? 'N/A',
            'username' => $config['username'] ?? 'N/A',
            'search_path' => $config['search_path'] ?? 'N/A',
            'sslmode' => $config['sslmode'] ?? 'N/A',
            'connection_test' => self::testConnection() ? 'SUCCESS' : 'FAILED'
        ];
    }
}