<?php

declare(strict_types=1);

/**
 * Unit tests for tags table migration
 * Tests the table structure satisfies Requirement 15.3
 */

use PHPUnit\Framework\TestCase;

final class TagsTableMigrationTest extends TestCase
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
     * Test that tags table exists
     * Requirement 15.3: Tag_Entity SHALL have fields: tag_id, name
     */
    public function testTagsTableExists(): void
    {
        $stmt = $this->pdo->query(
            "SHOW TABLES LIKE 'tags'"
        );
        $table = $stmt->fetch();

        $this->assertNotNull($table, "Tags table should exist");
    }

    /**
     * Test that tag_id field exists and is primary key
     * Requirement 15.3
     */
    public function testTagIdField(): void
    {
        $stmt = $this->pdo->query(
            "SHOW COLUMNS FROM tags WHERE Field = 'tag_id'"
        );
        $column = $stmt->fetch();

        $this->assertNotNull($column, "tag_id column should exist");
        $this->assertEquals('int', strtolower($column['Type']), "tag_id should be INT type");
        $this->assertEquals('PRI', $column['Key'], "tag_id should be PRIMARY KEY");
        $this->assertEquals('auto_increment', $column['Extra'], "tag_id should be AUTO_INCREMENT");
    }

    /**
     * Test that name field exists and is unique
     * Requirement 15.3
     */
    public function testNameField(): void
    {
        $stmt = $this->pdo->query(
            "SHOW COLUMNS FROM tags WHERE Field = 'name'"
        );
        $column = $stmt->fetch();

        $this->assertNotNull($column, "name column should exist");
        $this->assertEquals('varchar(50)', strtolower($column['Type']), "name should be VARCHAR(50) type");
        $this->assertEquals('UNI', $column['Key'], "name should have UNIQUE constraint");
        $this->assertEquals('NO', $column['Null'], "name should be NOT NULL");
    }

    /**
     * Test that unique index exists on name field
     * Requirement 15.3
     */
    public function testNameUniqueIndex(): void
    {
        $stmt = $this->pdo->query(
            "SHOW INDEX FROM tags WHERE Column_name = 'name' AND Non_unique = 0"
        );
        $index = $stmt->fetch();

        $this->assertNotNull($index, "Unique index should exist on name field");
        $this->assertEquals('name', $index['Column_name'], "Index should be on name column");
    }

    /**
     * Test that name field has index for performance
     * Requirement 15.3
     */
    public function testNameIndex(): void
    {
        $stmt = $this->pdo->query(
            "SHOW INDEX FROM tags WHERE Column_name = 'name'"
        );
        $indexes = $stmt->fetchAll();

        $this->assertNotEmpty($indexes, "Index should exist on name field");
        $this->assertCount(2, $indexes, "Should have both unique constraint and index");
    }

    /**
     * Test that table uses InnoDB engine
     * Requirement 15.3 (MySQL syntax as specified in design)
     */
    public function testTableEngine(): void
    {
        $stmt = $this->pdo->query(
            "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_NAME = 'tags'"
        );
        $tableInfo = $stmt->fetch();

        $this->assertNotNull($tableInfo, "Table info should be retrievable");
        $this->assertEquals('InnoDB', $tableInfo['ENGINE'], "Table should use InnoDB engine");
    }

    /**
     * Test that table uses utf8mb4 charset
     * Requirement 15.3 (MySQL syntax as specified in design)
     */
    public function testTableCharset(): void
    {
        $stmt = $this->pdo->query(
            "SELECT TABLE_CHARSET FROM information_schema.TABLES WHERE TABLE_NAME = 'tags'"
        );
        $tableInfo = $stmt->fetch();

        $this->assertNotNull($tableInfo, "Table info should be retrievable");
        $this->assertEquals('utf8mb4', $tableInfo['TABLE_CHARSET'], "Table should use utf8mb4 charset");
    }
}
