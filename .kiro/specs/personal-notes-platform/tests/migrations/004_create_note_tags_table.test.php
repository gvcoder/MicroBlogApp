<?php

declare(strict_types=1);

/**
 * Unit tests for note_tags table migration
 * Tests the table structure satisfies Requirement 15.4
 */

use PHPUnit\Framework\TestCase;

final class NoteTagsTableMigrationTest extends TestCase
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
     * Test that note_tags table exists
     * Requirement 15.4: Note_Tag_Entity SHALL have fields: note_id, tag_id
     */
    public function testNoteTagsTableExists(): void
    {
        $stmt = $this->pdo->query(
            "SHOW TABLES LIKE 'note_tags'"
        );
        $table = $stmt->fetch();

        $this->assertNotNull($table, "note_tags table should exist");
    }

    /**
     * Test that note_id field exists
     * Requirement 15.4
     */
    public function testNoteIdField(): void
    {
        $stmt = $this->pdo->query(
            "SHOW COLUMNS FROM note_tags WHERE Field = 'note_id'"
        );
        $column = $stmt->fetch();

        $this->assertNotNull($column, "note_id column should exist");
        $this->assertEquals('int', strtolower($column['Type']), "note_id should be INT type");
        $this->assertEquals('NO', $column['Null'], "note_id should be NOT NULL");
    }

    /**
     * Test that tag_id field exists
     * Requirement 15.4
     */
    public function testTagIdField(): void
    {
        $stmt = $this->pdo->query(
            "SHOW COLUMNS FROM note_tags WHERE Field = 'tag_id'"
        );
        $column = $stmt->fetch();

        $this->assertNotNull($column, "tag_id column should exist");
        $this->assertEquals('int', strtolower($column['Type']), "tag_id should be INT type");
        $this->assertEquals('NO', $column['Null'], "tag_id should be NOT NULL");
    }

    /**
     * Test that composite primary key exists (note_id, tag_id)
     * Requirement 15.4
     */
    public function testCompositePrimaryKey(): void
    {
        $stmt = $this->pdo->query(
            "SHOW INDEX FROM note_tags WHERE Key_name = 'PRIMARY'"
        );
        $indexes = $stmt->fetchAll();

        $this->assertCount(2, $indexes, "Primary key should have 2 columns");
        
        $columnNames = array_column($indexes, 'Column_name');
        $this->assertContains('note_id', $columnNames, "Primary key should include note_id");
        $this->assertContains('tag_id', $columnNames, "Primary key should include tag_id");
        
        $seqInIndex = array_column($indexes, 'Seq_in_index');
        $this->assertEquals([1, 2], $seqInIndex, "Columns should be in sequence 1, 2");
    }

    /**
     * Test that foreign key constraint exists for note_id
     * Requirement 15.4: Foreign key to notes table with ON DELETE CASCADE
     */
    public function testForeignKeyNoteId(): void
    {
        // Get foreign key information
        $stmt = $this->pdo->query("
            SELECT 
                CONSTRAINT_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME,
                DELETE_RULE
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'note_tags'
            AND COLUMN_NAME = 'note_id'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $fk = $stmt->fetch();

        $this->assertNotNull($fk, "Foreign key should exist for note_id");
        $this->assertEquals('notes', $fk['REFERENCED_TABLE_NAME'], "Foreign key should reference notes table");
        $this->assertEquals('note_id', $fk['REFERENCED_COLUMN_NAME'], "Foreign key should reference note_id column");
        $this->assertEquals('CASCADE', $fk['DELETE_RULE'], "ON DELETE should be CASCADE");
    }

    /**
     * Test that foreign key constraint exists for tag_id
     * Requirement 15.4: Foreign key to tags table with ON DELETE CASCADE
     */
    public function testForeignKeyTagId(): void
    {
        // Get foreign key information
        $stmt = $this->pdo->query("
            SELECT 
                CONSTRAINT_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME,
                DELETE_RULE
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'note_tags'
            AND COLUMN_NAME = 'tag_id'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $fk = $stmt->fetch();

        $this->assertNotNull($fk, "Foreign key should exist for tag_id");
        $this->assertEquals('tags', $fk['REFERENCED_TABLE_NAME'], "Foreign key should reference tags table");
        $this->assertEquals('tag_id', $fk['REFERENCED_COLUMN_NAME'], "Foreign key should reference tag_id column");
        $this->assertEquals('CASCADE', $fk['DELETE_RULE'], "ON DELETE should be CASCADE");
    }

    /**
     * Test that index exists on note_id for performance
     * Requirement 15.4 (for efficient joins)
     */
    public function testNoteIdIndex(): void
    {
        $stmt = $this->pdo->query(
            "SHOW INDEX FROM note_tags WHERE Column_name = 'note_id'"
        );
        $indexes = $stmt->fetchAll();

        $this->assertNotEmpty($indexes, "Index should exist on note_id field");
        $this->assertGreaterThan(0, count($indexes), "Should have at least one index on note_id");
    }

    /**
     * Test that index exists on tag_id for performance
     * Requirement 15.4 (for efficient joins)
     */
    public function testTagIdIndex(): void
    {
        $stmt = $this->pdo->query(
            "SHOW INDEX FROM note_tags WHERE Column_name = 'tag_id'"
        );
        $indexes = $stmt->fetchAll();

        $this->assertNotEmpty($indexes, "Index should exist on tag_id field");
        $this->assertGreaterThan(0, count($indexes), "Should have at least one index on tag_id");
    }

    /**
     * Test that table uses InnoDB engine
     * Requirement 15.4 (MySQL syntax as specified in design)
     */
    public function testTableEngine(): void
    {
        $stmt = $this->pdo->query(
            "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_NAME = 'note_tags'"
        );
        $tableInfo = $stmt->fetch();

        $this->assertNotNull($tableInfo, "Table info should be retrievable");
        $this->assertEquals('InnoDB', $tableInfo['ENGINE'], "Table should use InnoDB engine");
    }

    /**
     * Test that table uses utf8mb4 charset
     * Requirement 15.4 (MySQL syntax as specified in design)
     */
    public function testTableCharset(): void
    {
        $stmt = $this->pdo->query(
            "SELECT TABLE_CHARSET FROM information_schema.TABLES WHERE TABLE_NAME = 'note_tags'"
        );
        $tableInfo = $stmt->fetch();

        $this->assertNotNull($tableInfo, "Table info should be retrievable");
        $this->assertEquals('utf8mb4', $tableInfo['TABLE_CHARSET'], "Table should use utf8mb4 charset");
    }
}
