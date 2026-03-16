<?php

declare(strict_types=1);

/**
 * Database connection utility with connection pooling pattern
 * Implements PDO-based database access with comprehensive error handling
 * 
 * Requirements: 11.5 (Password hashing with bcrypt)
 */

namespace PersonalNotes;

use PDO;
use PDOException;
use Exception;

/**
 * Database connection class with connection pooling pattern
 * 
 * Uses a static pool to manage PDO connections efficiently.
 * Connections are reused within the same request lifecycle.
 */
class Database
{
    /**
     * Connection pool - stores active PDO connections
     * 
     * @var array<string, PDO>
     */
    private static array $pool = [];

    /**
     * Default database configuration
     * 
     * @var array<string, mixed>
     */
    private static array $defaultConfig = [
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'personal_notes',
        'username' => 'root',
        'password' => 'Telza#45Max',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
        ],
    ];

    /**
     * Get database connection from pool or create new one
     * 
     * Implements connection pooling pattern by reusing existing connections
     * within the same request lifecycle.
     * 
     * @param array<string, mixed> $config Database configuration
     * @return PDO Database connection
     * @throws Exception If connection fails
     */
    public static function getConnection(array $config = []): PDO
    {
        $configKey = self::getConfigKey($config);
        
        // Check if connection exists in pool
        if (isset(self::$pool[$configKey])) {
            $pdo = self::$pool[$configKey];
            
            // Verify connection is still alive
            try {
                $pdo->query('SELECT 1');
                return $pdo;
            } catch (PDOException) {
                // Connection is dead, remove from pool
                unset(self::$pool[$configKey]);
            }
        }

        // Create new connection
        try {
            $pdo = self::createConnection($config);
            self::$pool[$configKey] = $pdo;
            return $pdo;
        } catch (PDOException $e) {
            throw new Exception(
                "Database connection failed: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Create a new PDO connection
     * 
     * @param array<string, mixed> $config Database configuration
     * @return PDO New PDO connection
     * @throws PDOException If connection fails
     */
    private static function createConnection(array $config): PDO
    {
        $mergedConfig = self::mergeConfig($config);
        
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $mergedConfig['host'],
            $mergedConfig['port'],
            $mergedConfig['dbname'],
            $mergedConfig['charset']
        );

        $pdo = new PDO($dsn, $mergedConfig['username'], $mergedConfig['password'], $mergedConfig['options']);
        
        // Set collation
        $pdo->exec("SET NAMES {$mergedConfig['charset']} COLLATE {$mergedConfig['collation']}");
        
        return $pdo;
    }

    /**
     * Generate unique key for connection configuration
     * 
     * @param array<string, mixed> $config Database configuration
     * @return string Configuration key
     */
    private static function getConfigKey(array $config): string
    {
        $keyParts = [
            $config['host'] ?? self::$defaultConfig['host'],
            $config['port'] ?? self::$defaultConfig['port'],
            $config['dbname'] ?? self::$defaultConfig['dbname'],
            $config['username'] ?? self::$defaultConfig['username'],
        ];
        
        return implode(':', $keyParts);
    }

    /**
     * Merge provided config with defaults
     * 
     * @param array<string, mixed> $config Database configuration
     * @return array<string, mixed> Merged configuration
     */
    private static function mergeConfig(array $config): array
    {
        $merged = self::$defaultConfig;
        
        // Merge basic config options
        $basicKeys = ['host', 'port', 'dbname', 'username', 'password', 'charset', 'collation'];
        foreach ($basicKeys as $key) {
            if (isset($config[$key])) {
                $merged[$key] = $config[$key];
            }
        }
        
        // Merge PDO options
        if (isset($config['options']) && is_array($config['options'])) {
            $merged['options'] = array_merge($merged['options'], $config['options']);
        }
        
        return $merged;
    }

    /**
     * Close a specific connection from the pool
     * 
     * @param array<string, mixed> $config Database configuration
     * @return bool True if connection was closed, false if not found
     */
    public static function closeConnection(array $config = []): bool
    {
        $configKey = self::getConfigKey($config);
        
        if (isset(self::$pool[$configKey])) {
            unset(self::$pool[$configKey]);
            return true;
        }
        
        return false;
    }

    /**
     * Close all connections in the pool
     * 
     * @return void
     */
    public static function closeAllConnections(): void
    {
        self::$pool = [];
    }

    /**
     * Hash password using bcrypt with cost factor 12
     * 
     * Requirement 11.5: When a password is stored, the System SHALL hash the password using bcrypt
     * 
     * @param string $password Plain text password
     * @return string|false Hashed password or false on failure
     */
    public static function hashPassword(string $password): string|false
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verify password against hash
     * 
     * @param string $password Plain text password
     * @param string $hash Hashed password
     * @return bool True if password matches, false otherwise
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if password needs rehashing
     * 
     * @param string $hash Hashed password
     * @return bool True if rehashing is needed
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Execute a prepared statement with error handling
     * 
     * @param PDO $pdo Database connection
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return \PDOStatement Prepared statement
     * @throws Exception If query execution fails
     */
    public static function executeQuery(PDO $pdo, string $sql, array $params = []): \PDOStatement
    {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception(
                "Query execution failed: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get last inserted ID
     * 
     * @param PDO $pdo Database connection
     * @param string|null $sequence Name of the sequence
     * @return string Last inserted ID
     */
    public static function getLastInsertId(PDO $pdo, ?string $sequence = null): string
    {
        return $pdo->lastInsertId($sequence);
    }

    /**
     * Begin a transaction
     * 
     * @param PDO $pdo Database connection
     * @return void
     * @throws Exception If transaction fails to start
     */
    public static function beginTransaction(PDO $pdo): void
    {
        try {
            $pdo->beginTransaction();
        } catch (PDOException $e) {
            throw new Exception(
                "Failed to begin transaction: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Commit a transaction
     * 
     * @param PDO $pdo Database connection
     * @return void
     * @throws Exception If commit fails
     */
    public static function commit(PDO $pdo): void
    {
        try {
            $pdo->commit();
        } catch (PDOException $e) {
            throw new Exception(
                "Failed to commit transaction: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Rollback a transaction
     * 
     * @param PDO $pdo Database connection
     * @return void
     * @throws Exception If rollback fails
     */
    public static function rollback(PDO $pdo): void
    {
        try {
            $pdo->rollBack();
        } catch (PDOException $e) {
            throw new Exception(
                "Failed to rollback transaction: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Check if in a transaction
     * 
     * @param PDO $pdo Database connection
     * @return bool True if in transaction
     */
    public static function inTransaction(PDO $pdo): bool
    {
        return $pdo->inTransaction();
    }

    /**
     * Validate session token and get user ID
     * 
     * @param string $token Session token
     * @return int|null User ID if valid session, null otherwise
     * @throws Exception If database query fails
     */
    public static function validateSession(string $token): ?int
    {
        try {
            $pdo = self::getConnection();
            
            // Check if token exists and is not expired
            $stmt = $pdo->prepare(
                'SELECT user_id FROM sessions WHERE token = ? AND expires_at > NOW()'
            );
            $stmt->execute([$token]);
            
            $result = $stmt->fetch();
            
            if ($result) {
                return (int)$result['user_id'];
            }
            
            return null;
        } catch (PDOException $e) {
            throw new Exception(
                "Session validation failed: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Delete session by token
     * 
     * @param string $token Session token to delete
     * @return bool True if session was deleted, false if not found
     * @throws Exception If database query fails
     */
    public static function deleteSession(string $token): bool
    {
        try {
            $pdo = self::getConnection();
            
            $stmt = $pdo->prepare('DELETE FROM sessions WHERE token = ?');
            $stmt->execute([$token]);
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new Exception(
                "Session deletion failed: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get user ID from session token
     * 
     * @param string $token Session token
     * @return int|null User ID if valid session, null otherwise
     * @throws Exception If database query fails
     */
    public static function getUserIdFromToken(string $token): ?int
    {
        return self::validateSession($token);
    }

    /**
     * Create a new session
     * 
     * @param int $userId User ID
     * @param string $token Session token
     * @param string $expiresAt Expiration datetime (Y-m-d H:i:s format)
     * @return bool True if session created successfully
     * @throws Exception If database query fails
     */
    public static function createSession(int $userId, string $token, string $expiresAt): bool
    {
        try {
            $pdo = self::getConnection();
            
            $stmt = $pdo->prepare(
                'INSERT INTO sessions (user_id, token, expires_at) VALUES (?, ?, ?)'
            );
            $stmt->execute([$userId, $token, $expiresAt]);
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new Exception(
                "Session creation failed: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Clean up expired sessions
     * 
     * @return int Number of sessions deleted
     * @throws Exception If database query fails
     */
    public static function cleanupExpiredSessions(): int
    {
        try {
            $pdo = self::getConnection();
            
            $stmt = $pdo->prepare('DELETE FROM sessions WHERE expires_at <= NOW()');
            $stmt->execute();
            
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception(
                "Session cleanup failed: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
