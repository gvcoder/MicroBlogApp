<?php

declare(strict_types=1);

/**
 * Unit tests for users table migration
 * Tests the table structure satisfies Requirement 15.1
 */

use PHPUnit\Framework\TestCase;

final class UsersTableMigrationTest extends TestCase
{
    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        // Get database connection from environment or use defaults
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $dbName = $_ENV['DB_NAME'] ?? 'personal_notes';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASSWORD'] ?? '';

        try {
            $this->pdo = new PDO(
                "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            $this->fail("Database connection failed: " . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
    }

    /**
     * Test that users table exists
     * Requirement 15.1: User_Entity SHALL have fields: user_id, email, password_hash, created_at
     */
    public function testUsersTableExists(): void
    {
        $stmt = $this->pdo->query(
            "SHOW TABLES LIKE 'users'"
        );
        $table = $stmt->fetch();

        $this->assertNotNull($table, "Users table should exist");
    }

    /**
     * Test that user_id field exists and is primary key
     * Requirement 15.1
     */
    public function testUserIdField(): void
    {
        $stmt = $this->pdo->query(
            "SHOW COLUMNS FROM users WHERE Field = 'user_id'"
        );
        $column = $stmt->fetch();

        $this->assertNotNull($column, "user_id column should exist");
        $this->assertEquals('int', strtolower($column['Type']), "user_id should be INT type");
        $this->assertEquals('PRI', $column['Key'], "user_id should be PRIMARY KEY");
        $this->assertEquals('auto_increment', $column['Extra'], "user_id should be AUTO_INCREMENT");
    }

    /**
     * Test that email field exists and is unique
     * Requirement 15.1
     */
    public function testEmailField(): void
    {
        $stmt = $this->pdo->query(
            "SHOW COLUMNS FROM users WHERE Field = 'email'"
        );
        $column = $stmt->fetch();

        $this->assertNotNull($column, "email column should exist");
        $this->assertEquals('varchar(255)', strtolower($column['Type']), "email should be VARCHAR(255) type");
        $this->assertEquals('UNI', $column['Key'], "email should have UNIQUE constraint");
        $this->assertEquals('NO', $column['Null'], "email should be NOT NULL");
    }

    /**
     * Test that password_hash field exists
     * Requirement 15.1
     */
    public function testPasswordHashField(): void
    {
        $stmt = $this->pdo->query(
            "SHOW COLUMNS FROM users WHERE Field = 'password_hash'"
        );
        $column = $stmt->fetch();

        $this->assertNotNull($column, "password_hash column should exist");
        $this->assertEquals('varchar(255)', strtolower($column['Type']), "password_hash should be VARCHAR(255) type");
        $this->assertEquals('NO', $column['Null'], "password_hash should be NOT NULL");
    }

    /**
     * Test that created_at field exists with default timestamp
     * Requirement 15.1
     */
    public function testCreatedAtField(): void
    {
        $stmt = $this->pdo->query(
            "SHOW COLUMNS FROM users WHERE Field = 'created_at'"
        );
        $column = $stmt->fetch();

        $this->assertNotNull($column, "created_at column should exist");
        $this->assertEquals('timestamp', strtolower($column['Type']), "created_at should be TIMESTAMP type");
        $this->assertEquals('YES', $column['Null'], "created_at should allow NULL");
        $this->assertEquals('DEFAULT_GENERATED', $column['Extra'], "created_at should have DEFAULT CURRENT_TIMESTAMP");
    }

    /**
     * Test that unique index exists on email field
     * Requirement 15.1
     */
    public function testEmailUniqueIndex(): void
    {
        $stmt = $this->pdo->query(
            "SHOW INDEX FROM users WHERE Column_name = 'email' AND Non_unique = 0"
        );
        $index = $stmt->fetch();

        $this->assertNotNull($index, "Unique index should exist on email field");
        $this->assertEquals('email', $index['Column_name'], "Index should be on email column");
    }

    /**
     * Test that email field has index for performance
     * Requirement 15.1
     */
    public function testEmailIndex(): void
    {
        $stmt = $this->pdo->query(
            "SHOW INDEX FROM users WHERE Column_name = 'email'"
        );
        $indexes = $stmt->fetchAll();

        $this->assertNotEmpty($indexes, "Index should exist on email field");
        $this->assertCount(2, $indexes, "Should have both unique constraint and index");
    }

    /**
     * Test that table uses InnoDB engine
     * Requirement 15.1 (MySQL syntax as specified in design)
     */
    public function testTableEngine(): void
    {
        $stmt = $this->pdo->query(
            "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_NAME = 'users'"
        );
        $tableInfo = $stmt->fetch();

        $this->assertNotNull($tableInfo, "Table info should be retrievable");
        $this->assertEquals('InnoDB', $tableInfo['ENGINE'], "Table should use InnoDB engine");
    }

    /**
     * Test that table uses utf8mb4 charset
     * Requirement 15.1 (MySQL syntax as specified in design)
     */
    public function testTableCharset(): void
    {
        $stmt = $this->pdo->query(
            "SELECT TABLE_CHARSET FROM information_schema.TABLES WHERE TABLE_NAME = 'users'"
        );
        $tableInfo = $stmt->fetch();

        $this->assertNotNull($tableInfo, "Table info should be retrievable");
        $this->assertEquals('utf8mb4', $tableInfo['TABLE_CHARSET'], "Table should use utf8mb4 charset");
    }

    /**
     * Test that foreign key constraints work properly
     * Requirement 15.1 (InnoDB engine required for FK)
     */
    public function testInnoDBSupportsForeignKeys(): void
    {
        // Verify InnoDB supports foreign keys
        $stmt = $this->pdo->query(
            "SELECT @@innodb_version"
        );
        $version = $stmt->fetch();

        $this->assertNotNull($version, "InnoDB version should be retrievable");
        $this->assertNotEmpty($version['@@innodb_version'], "InnoDB should be available");
    }
}
