<?php

declare(strict_types=1);

/**
 * Unit tests for sessions table migration
 * Tests the table structure satisfies Requirement 15.5
 */

use PHPUnit\Framework\TestCase;

final class SessionsTableMigrationTest extends TestCase
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
     * Test that sessions table exists
     * Requirement 15.5: Session_Entity SHALL have fields: session_id, user_id, token, expires_at
     */
    public function testSessionsTableExists(): void
    {
        $stmt = $this->pdo->query(
            "SHOW TABLES LIKE 'sessions'"
        );
        $table = $stmt->fetch();

        $this->assertNotNull($table, "Sessions table should exist");
    }

    /**
     * Test that session_id field exists and is primary key
     * Requirement 15.5
     */
    public function testSessionIdField(): void
    {
        $stmt = $this->pdo->query(
            "SHOW COLUMNS FROM sessions WHERE Field = 'session_id'"
        );
        $column = $stmt->fetch();

        $this->assertNotNull($column, "session_id column should exist");
        $this->assertEquals('int', strtolower($column['Type']), "session_id should be INT type");
        $this->assertEquals('PRI', $column['Key'], "session_id should be PRIMARY KEY");
        $this->assertEquals('auto_increment', $column['Extra'], "session_id should be AUTO_INCREMENT");
    }

    /**
     * Test that user_id field exists and is not null
     * Requirement 15.5
     */
    public function testUserIdField(): void
    {
        $stmt = $this->pdo->query(
            "SHOW COLUMNS FROM sessions WHERE Field = 'user_id'"
        );
        $column = $stmt->fetch();

        $this->assertNotNull($column, "user_id column should exist");
        $this->assertEquals('int', strtolower($column['Type']), "user_id should be INT type");
        $this->assertEquals('NO', $column['Null'], "user_id should be NOT NULL");
    }

    /**
     * Test that token field exists and is unique
     * Requirement 15.5: Session_Entity SHALL have unique index on token field
     */
    public function testTokenField(): void
    {
        $stmt = $this->pdo->query(
            "SHOW COLUMNS FROM sessions WHERE Field = 'token'"
        );
        $column = $stmt->fetch();

        $this->assertNotNull($column, "token column should exist");
        $this->assertEquals('varchar(255)', strtolower($column['Type']), "token should be VARCHAR(255) type");
        $this->assertEquals('UNI', $column['Key'], "token should have UNIQUE constraint");
        $this->assertEquals('NO', $column['Null'], "token should be NOT NULL");
    }

    /**
     * Test that expires_at field exists and is not null
     * Requirement 15.5
     */
    public function testExpiresAtField(): void
    {
        $stmt = $this->pdo->query(
            "SHOW COLUMNS FROM sessions WHERE Field = 'expires_at'"
        );
        $column = $stmt->fetch();

        $this->assertNotNull($column, "expires_at column should exist");
        $this->assertEquals('timestamp', strtolower($column['Type']), "expires_at should be TIMESTAMP type");
        $this->assertEquals('NO', $column['Null'], "expires_at should be NOT NULL");
    }

    /**
     * Test that unique index exists on token field
     * Requirement 15.5
     */
    public function testTokenUniqueIndex(): void
    {
        $stmt = $this->pdo->query(
            "SHOW INDEX FROM sessions WHERE Column_name = 'token' AND Non_unique = 0"
        );
        $index = $stmt->fetch();

        $this->assertNotNull($index, "Unique index should exist on token field");
        $this->assertEquals('token', $index['Column_name'], "Index should be on token column");
    }

    /**
     * Test that index exists on user_id field for performance
     * Requirement 15.5
     */
    public function testUserIdIndex(): void
    {
        $stmt = $this->pdo->query(
            "SHOW INDEX FROM sessions WHERE Column_name = 'user_id' AND Non_unique = 1"
        );
        $index = $stmt->fetch();

        $this->assertNotNull($index, "Index should exist on user_id field");
        $this->assertEquals('user_id', $index['Column_name'], "Index should be on user_id column");
    }

    /**
     * Test that index exists on token field for performance
     * Requirement 15.5
     */
    public function testTokenIndex(): void
    {
        $stmt = $this->pdo->query(
            "SHOW INDEX FROM sessions WHERE Column_name = 'token' AND Non_unique = 1"
        );
        $index = $stmt->fetch();

        $this->assertNotNull($index, "Index should exist on token field");
        $this->assertEquals('token', $index['Column_name'], "Index should be on token column");
    }

    /**
     * Test that index exists on expires_at field for performance
     * Requirement 15.5
     */
    public function testExpiresAtIndex(): void
    {
        $stmt = $this->pdo->query(
            "SHOW INDEX FROM sessions WHERE Column_name = 'expires_at' AND Non_unique = 1"
        );
        $index = $stmt->fetch();

        $this->assertNotNull($index, "Index should exist on expires_at field");
        $this->assertEquals('expires_at', $index['Column_name'], "Index should be on expires_at column");
    }

    /**
     * Test that foreign key constraint exists on user_id
     * Requirement 15.5: Session_Entity SHALL have foreign key constraint to users table with ON DELETE CASCADE
     */
    public function testForeignKeyConstraint(): void
    {
        $stmt = $this->pdo->query(
            "SELECT 
                CONSTRAINT_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                DELETE_RULE
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_NAME = 'sessions'
            AND COLUMN_NAME = 'user_id'
            AND REFERENCED_TABLE_NAME = 'users'"
        );
        $fk = $stmt->fetch();

        $this->assertNotNull($fk, "Foreign key constraint should exist on user_id");
        $this->assertEquals('user_id', $fk['COLUMN_NAME'], "FK should be on user_id column");
        $this->assertEquals('users', $fk['REFERENCED_TABLE_NAME'], "FK should reference users table");
        $this->assertEquals('CASCADE', $fk['DELETE_RULE'], "FK should have ON DELETE CASCADE");
    }

    /**
     * Test that table uses InnoDB engine
     * Requirement 15.5 (MySQL syntax as specified in design)
     */
    public function testTableEngine(): void
    {
        $stmt = $this->pdo->query(
            "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_NAME = 'sessions'"
        );
        $tableInfo = $stmt->fetch();

        $this->assertNotNull($tableInfo, "Table info should be retrievable");
        $this->assertEquals('InnoDB', $tableInfo['ENGINE'], "Table should use InnoDB engine");
    }

    /**
     * Test that table uses utf8mb4 charset
     * Requirement 15.5 (MySQL syntax as specified in design)
     */
    public function testTableCharset(): void
    {
        $stmt = $this->pdo->query(
            "SELECT TABLE_CHARSET FROM information_schema.TABLES WHERE TABLE_NAME = 'sessions'"
        );
        $tableInfo = $stmt->fetch();

        $this->assertNotNull($tableInfo, "Table info should be retrievable");
        $this->assertEquals('utf8mb4', $tableInfo['TABLE_CHARSET'], "Table should use utf8mb4 charset");
    }
}
