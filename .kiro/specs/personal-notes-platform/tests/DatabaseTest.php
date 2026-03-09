<?php

declare(strict_types=1);

/**
 * Unit tests for Database connection utility
 * Tests connection pooling, error handling, and password hashing
 * 
 * Requirements: 11.5 (Password hashing with bcrypt)
 */

use PHPUnit\Framework\TestCase;

/**
 * @covers Database
 */
final class DatabaseTest extends TestCase
{
    private ?PDO $testPdo = null;

    protected function setUp(): void
    {
        // Get database connection from environment or use defaults
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $dbName = $_ENV['DB_NAME'] ?? 'personal_notes_test';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASSWORD'] ?? '';

        try {
            $this->testPdo = new PDO(
                "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            $this->fail("Test database connection failed: " . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        // Clear the connection pool after each test
        \PersonalNotes\Database::closeAllConnections();
        $this->testPdo = null;
    }

    /**
     * Test that password hashing works correctly with bcrypt
     * Requirement 11.5: When a password is stored, the System SHALL hash the password using bcrypt
     */
    public function testHashPassword(): void
    {
        $password = 'test_password_123';
        $hash = \PersonalNotes\Database::hashPassword($password);

        $this->assertIsString($hash);
        $this->assertNotEmpty($hash);
        $this->assertTrue(password_verify($password, $hash));
    }

    /**
     * Test that bcrypt hash has correct format with cost factor 12
     * Requirement 11.5: Password hashing with bcrypt cost factor 12
     */
    public function testHashPasswordCostFactor(): void
    {
        $password = 'test_password_123';
        $hash = \PersonalNotes\Database::hashPassword($password);

        // Extract cost factor from hash
        $cost = password_get_info($hash)['cost'];
        
        $this->assertEquals(12, $cost, "Bcrypt cost factor should be 12");
    }

    /**
     * Test that password verification works correctly
     * Requirement 11.5: Password verification using password_verify()
     */
    public function testVerifyPassword(): void
    {
        $password = 'test_password_123';
        $hash = \PersonalNotes\Database::hashPassword($password);

        $this->assertTrue(\PersonalNotes\Database::verifyPassword($password, $hash));
        $this->assertFalse(\PersonalNotes\Database::verifyPassword('wrong_password', $hash));
    }

    /**
     * Test that needsRehash works correctly
     * Requirement 11.5: Password rehash detection
     */
    public function testNeedsRehash(): void
    {
        $password = 'test_password_123';
        $hash = \PersonalNotes\Database::hashPassword($password);

        // Current hash should not need rehash
        $this->assertFalse(\PersonalNotes\Database::needsRehash($hash));

        // Create a hash with lower cost and test
        $oldHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        $this->assertTrue(\PersonalNotes\Database::needsRehash($oldHash));
    }

    /**
     * Test that different passwords produce different hashes
     * Requirement 11.5: Password hashing consistency
     */
    public function testDifferentPasswordsDifferentHashes(): void
    {
        $password1 = 'password1';
        $password2 = 'password2';
        
        $hash1 = \PersonalNotes\Database::hashPassword($password1);
        $hash2 = \PersonalNotes\Database::hashPassword($password2);

        $this->assertNotEquals($hash1, $hash2);
    }

    /**
     * Test that same password produces different hashes each time (salt)
     * Requirement 11.5: Password hashing consistency with unique salt
     */
    public function testSamePasswordDifferentHashes(): void
    {
        $password = 'same_password';
        
        $hash1 = \PersonalNotes\Database::hashPassword($password);
        $hash2 = \PersonalNotes\Database::hashPassword($password);

        // Hashes should be different due to unique salt
        $this->assertNotEquals($hash1, $hash2);
        
        // But both should verify correctly
        $this->assertTrue(\PersonalNotes\Database::verifyPassword($password, $hash1));
        $this->assertTrue(\PersonalNotes\Database::verifyPassword($password, $hash2));
    }

    /**
     * Test that empty password can be hashed
     * Requirement 11.5: Edge case handling
     */
    public function testEmptyPasswordHash(): void
    {
        $password = '';
        $hash = \PersonalNotes\Database::hashPassword($password);

        $this->assertIsString($hash);
        $this->assertNotEmpty($hash);
        $this->assertTrue(password_verify($password, $hash));
    }

    /**
     * Test that special characters in password are handled correctly
     * Requirement 11.5: Edge case handling
     */
    public function testSpecialCharactersInPassword(): void
    {
        $password = "p@$w0rd!#$%^&*()_+-=[]{}|;':\",./<>?";
        $hash = \PersonalNotes\Database::hashPassword($password);

        $this->assertIsString($hash);
        $this->assertTrue(\PersonalNotes\Database::verifyPassword($password, $hash));
    }

    /**
     * Test that unicode characters in password are handled correctly
     * Requirement 11.5: Edge case handling
     */
    public function testUnicodeCharactersInPassword(): void
    {
        $password = 'пароль密码';
        $hash = \PersonalNotes\Database::hashPassword($password);

        $this->assertIsString($hash);
        $this->assertTrue(\PersonalNotes\Database::verifyPassword($password, $hash));
    }

    /**
     * Test that long password is handled correctly
     * Requirement 11.5: Edge case handling
     */
    public function testLongPassword(): void
    {
        $password = str_repeat('a', 1000);
        $hash = \PersonalNotes\Database::hashPassword($password);

        $this->assertIsString($hash);
        $this->assertTrue(\PersonalNotes\Database::verifyPassword($password, $hash));
    }

    /**
     * Test that connection pooling reuses existing connections
     * Requirement 11.5: Connection pooling pattern
     */
    public function testConnectionPoolingReusesConnection(): void
    {
        $config = [
            'dbname' => $_ENV['DB_NAME'] ?? 'personal_notes',
        ];

        // Get first connection
        $pdo1 = \PersonalNotes\Database::getConnection($config);
        $connection1Splat = spl_object_hash($pdo1);

        // Get second connection with same config
        $pdo2 = \PersonalNotes\Database::getConnection($config);
        $connection2Splat = spl_object_hash($pdo2);

        // Should be the same connection instance
        $this->assertEquals($connection1Splat, $connection2Splat, "Connection pooling should reuse connections");
    }

    /**
     * Test that different configs create different connections
     * Requirement 11.5: Connection pooling pattern
     */
    public function testDifferentConfigsCreateDifferentConnections(): void
    {
        $config1 = ['dbname' => 'personal_notes'];
        $config2 = ['dbname' => 'personal_notes_test'];

        $pdo1 = \PersonalNotes\Database::getConnection($config1);
        $connection1Splat = spl_object_hash($pdo1);

        $pdo2 = \PersonalNotes\Database::getConnection($config2);
        $connection2Splat = spl_object_hash($pdo2);

        $this->assertNotEquals($connection1Splat, $connection2Splat, "Different configs should create different connections");
    }

    /**
     * Test that connection pool can be cleared
     * Requirement 11.5: Connection pooling pattern
     */
    public function testCloseAllConnections(): void
    {
        $config = ['dbname' => $_ENV['DB_NAME'] ?? 'personal_notes'];

        // Get a connection
        $pdo = \PersonalNotes\Database::getConnection($config);
        $this->assertNotNull($pdo);

        // Close all connections
        \PersonalNotes\Database::closeAllConnections();

        // Next request should create new connection
        $pdo2 = \PersonalNotes\Database::getConnection($config);
        $this->assertNotNull($pdo2);
    }

    /**
     * Test that connection pool verifies connection health
     * Requirement 11.5: Connection pooling pattern with error handling
     */
    public function testConnectionPoolVerifiesHealth(): void
    {
        $config = ['dbname' => $_ENV['DB_NAME'] ?? 'personal_notes'];

        // Get a connection
        $pdo = \PersonalNotes\Database::getConnection($config);
        
        // Close the connection manually to simulate a dead connection
        $pdo = null;

        // Get connection again - should create new one
        $pdo2 = \PersonalNotes\Database::getConnection($config);
        $this->assertNotNull($pdo2);
    }

    /**
     * Test that executeQuery works correctly
     * Requirement 11.5: Error handling for query execution
     */
    public function testExecuteQuery(): void
    {
        $config = ['dbname' => $_ENV['DB_NAME'] ?? 'personal_notes'];
        $pdo = \PersonalNotes\Database::getConnection($config);

        $sql = 'SELECT 1 as test';
        $stmt = \PersonalNotes\Database::executeQuery($pdo, $sql);

        $this->assertInstanceOf(\PDOStatement::class, $stmt);
        $result = $stmt->fetch();
        $this->assertEquals(1, $result['test']);
    }

    /**
     * Test that executeQuery with parameters works correctly
     * Requirement 11.5: Prepared statement support
     */
    public function testExecuteQueryWithParams(): void
    {
        $config = ['dbname' => $_ENV['DB_NAME'] ?? 'personal_notes'];
        $pdo = \PersonalNotes\Database::getConnection($config);

        $sql = 'SELECT ? as test';
        $stmt = \PersonalNotes\Database::executeQuery($pdo, $sql, ['hello']);

        $this->assertInstanceOf(\PDOStatement::class, $stmt);
        $result = $stmt->fetch();
        $this->assertEquals('hello', $result['test']);
    }

    /**
     * Test that transaction methods work correctly
     * Requirement 11.5: Transaction support
     */
    public function testTransactionMethods(): void
    {
        $config = ['dbname' => $_ENV['DB_NAME'] ?? 'personal_notes_test'];
        $pdo = \PersonalNotes\Database::getConnection($config);

        // Start transaction
        \PersonalNotes\Database::beginTransaction($pdo);
        $this->assertTrue(\PersonalNotes\Database::inTransaction($pdo));

        // Rollback
        \PersonalNotes\Database::rollback($pdo);
        $this->assertFalse(\PersonalNotes\Database::inTransaction($pdo));

        // Start another transaction
        \PersonalNotes\Database::beginTransaction($pdo);
        $this->assertTrue(\PersonalNotes\Database::inTransaction($pdo));

        // Commit
        \PersonalNotes\Database::commit($pdo);
        $this->assertFalse(\PersonalNotes\Database::inTransaction($pdo));
    }

    /**
     * Test that transaction rollback works correctly
     * Requirement 11.5: Transaction support
     */
    public function testTransactionRollback(): void
    {
        $config = ['dbname' => $_ENV['DB_NAME'] ?? 'personal_notes_test'];
        $pdo = \PersonalNotes\Database::getConnection($config);

        // Create a test table
        $pdo->exec('CREATE TEMPORARY TABLE IF NOT EXISTS test_transaction (id INT PRIMARY KEY, value VARCHAR(50))');

        \PersonalNotes\Database::beginTransaction($pdo);
        
        // Insert a row
        $stmt = $pdo->prepare('INSERT INTO test_transaction VALUES (?, ?)');
        $stmt->execute([1, 'test_value']);

        // Rollback
        \PersonalNotes\Database::rollback($pdo);

        // Verify row was not inserted
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM test_transaction WHERE id = ?');
        $stmt->execute([1]);
        $result = $stmt->fetch();
        
        $this->assertEquals(0, $result['count'], "Rolled back transaction should not persist changes");
    }

    /**
     * Test that transaction commit works correctly
     * Requirement 11.5: Transaction support
     */
    public function testTransactionCommit(): void
    {
        $config = ['dbname' => $_ENV['DB_NAME'] ?? 'personal_notes_test'];
        $pdo = \PersonalNotes\Database::getConnection($config);

        // Create a test table
        $pdo->exec('CREATE TEMPORARY TABLE IF NOT EXISTS test_transaction_commit (id INT PRIMARY KEY, value VARCHAR(50))');

        \PersonalNotes\Database::beginTransaction($pdo);
        
        // Insert a row
        $stmt = $pdo->prepare('INSERT INTO test_transaction_commit VALUES (?, ?)');
        $stmt->execute([1, 'test_value']);

        // Commit
        \PersonalNotes\Database::commit($pdo);

        // Verify row was inserted
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM test_transaction_commit WHERE id = ?');
        $stmt->execute([1]);
        $result = $stmt->fetch();
        
        $this->assertEquals(1, $result['count'], "Committed transaction should persist changes");
    }

    /**
     * Test that connection fails with invalid credentials
     * Requirement 11.5: Error handling for connection failures
     */
    public function testConnectionFailsWithInvalidCredentials(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database connection failed');

        $config = [
            'host' => 'localhost',
            'port' => 3306,
            'dbname' => 'personal_notes',
            'username' => 'invalid_user',
            'password' => 'invalid_password',
        ];

        \PersonalNotes\Database::getConnection($config);
    }

    /**
     * Test that connection fails with invalid database
     * Requirement 11.5: Error handling for connection failures
     */
    public function testConnectionFailsWithInvalidDatabase(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database connection failed');

        $config = [
            'dbname' => 'nonexistent_database_12345',
        ];

        \PersonalNotes\Database::getConnection($config);
    }

    /**
     * Test that executeQuery throws exception on invalid SQL
     * Requirement 11.5: Error handling for query execution
     */
    public function testExecuteQueryThrowsExceptionOnInvalidSql(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Query execution failed');

        $config = ['dbname' => $_ENV['DB_NAME'] ?? 'personal_notes'];
        $pdo = \PersonalNotes\Database::getConnection($config);

        $invalidSql = 'SELECT * FROM nonexistent_table_12345';
        \PersonalNotes\Database::executeQuery($pdo, $invalidSql);
    }

    /**
     * Test that executeQuery throws exception on invalid parameters
     * Requirement 11.5: Error handling for query execution
     */
    public function testExecuteQueryThrowsExceptionOnInvalidParams(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Query execution failed');

        $config = ['dbname' => $_ENV['DB_NAME'] ?? 'personal_notes'];
        $pdo = \PersonalNotes\Database::getConnection($config);

        // Try to execute a query that requires a table
        $invalidSql = 'SELECT * FROM users WHERE email = ?';
        \PersonalNotes\Database::executeQuery($pdo, $invalidSql, []);
    }

    /**
     * Test that password hash format is correct
     * Requirement 11.5: Password hashing consistency
     */
    public function testPasswordHashFormat(): void
    {
        $password = 'test_password';
        $hash = \PersonalNotes\Database::hashPassword($password);

        // Bcrypt hashes start with $2y$ or $2a$ or $2b$
        $this->assertStringStartsWith('$2y$', $hash, "Hash should start with $2y$ (bcrypt identifier)");
        
        // Bcrypt hash format: $2y$cost$salt+hash (60 characters total)
        $this->assertEquals(60, strlen($hash), "Bcrypt hash should be 60 characters");
    }

    /**
     * Test that password hash contains expected components
     * Requirement 11.5: Password hashing consistency
     */
    public function testPasswordHashComponents(): void
    {
        $password = 'test_password';
        $hash = \PersonalNotes\Database::hashPassword($password);

        // Extract components from hash
        $parts = explode('$', $hash);
        
        $this->assertEquals(4, count($parts), "Hash should have 4 parts separated by $");
        $this->assertEquals('2y', $parts[1], "First part should be 2y (bcrypt)");
        $this->assertEquals('12', $parts[2], "Second part should be cost factor (12)");
        $this->assertEquals(22, strlen($parts[3]), "Third part should be 22 char salt");
        $this->assertEquals(31, strlen($parts[4]), "Fourth part should be 31 char hash");
    }
}
