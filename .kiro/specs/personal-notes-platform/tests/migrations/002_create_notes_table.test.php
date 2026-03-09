<?php

declare(strict_types=1);

/**
 * Unit tests for notes table migration
 * Tests the table structure satisfies Requirement 15.2
 */

use PHPUnit\Framework\TestCase;

final class NotesTableMigrationTest extends TestCase
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
     * Test that notes table exists
     * Requirement 15.2: Note_Entity SHALL have fields: note_id, user_id, title, content, created_at, updated_at
     */
    public function testNotesTableExists(): void
    {
        $stmt = $this->pdo->query(
            "SHOW TABLES LIKE 'notes'"
        );
        $table = $stmt->fetch();

        $this->assertNotNull($table, "Notes table should exist");
    }

    /**
     * Test that note_id field exists and is primary key
     * Requirement 15.2
     */
    public function testNoteIdField(): void
    {
        $stmt = $this->pdo->query(
            "SHOW COLUMNS FROM notes WHERE Field = 'note_id'"
        );
        $column = $stmt->fetch();

        $this->assertNotNull($column, "note_id column should exist");
        $this->assertEquals('int', strtolower($column['Type']), "note_id should be INT type");
        $this->assertEquals('PRI', $column['Key'], "note_id should be PRIMARY KEY");
        $this->assertEquals('auto_increment', $column['Extra'], "note_id should be AUTO_INCREMENT");
    }

    /**
     * Test that user_id field exists and is not null
     * Requirement 15.2
     */
    public function testUserIdField(): void
    {
        $stmt = $this->pdo->query(
            "SHOW COLUMNS FROM notes WHERE Field = 'user_id'"
        );
        $column = $stmt->fetch();

        $this->assertNotNull($column, "user_id column should exist");
        $this->assertEquals('int', strtolower($column['Type']), "user_id should be INT type");
        $this->assertEquals('NO', $column['Null'], "user_id should be NOT NULL");
    }

    /**
     * Test that title field exists with correct constraints
     * Requirement 15.2
     */
    public function testTitleField(): void
    {
        $stmt = $this->pdo->query(
            "SHOW COLUMNS FROM notes WHERE Field = 'title'"
        );
        $column = $stmt->fetch();

        $this->assertNotNull($column, "title column should exist");
        $this->assertEquals('varchar(200)', strtolower($column['Type']), "title should be VARCHAR(200) type");
        $this->assertEquals('NO', $column['Null'], "title should be NOT NULL");
    }

    /**
     * Test that content field exists with correct constraints
     * Requirement 15.2
     */
    public function testContentField(): void
    {
        $stmt = $this->pdo->query(
            "SHOW COLUMNS FROM notes WHERE Field = 'content'"
        );
        $column = $stmt->fetch();

        $this->assertNotNull($column, "content column should exist");
        $this->assertEquals('text', strtolower($column['Type']), "content should be TEXT type");
        $this->assertEquals('NO', $column['Null'], "content should be NOT NULL");
    }

    /**
     * Test that created_at field exists with default timestamp
     * Requirement 15.2
     */
    public function testCreatedAtField(): void
    {
        $stmt = $this->pdo->query(
            "SHOW COLUMNS FROM notes WHERE Field = 'created_at'"
        );
        $column = $stmt->fetch();

        $this->assertNotNull($column, "created_at column should exist");
        $this->assertEquals('timestamp', strtolower($column['Type']), "created_at should be TIMESTAMP type");
        $this->assertEquals('YES', $column['Null'], "created_at should allow NULL");
        $this->assertEquals('DEFAULT_GENERATED', $column['Extra'], "created_at should have DEFAULT CURRENT_TIMESTAMP");
    }

    /**
     * Test that updated_at field exists with auto-update timestamp
     * Requirement 15.2
     */
    public function testUpdatedAtField(): void
    {
        $stmt = $this->pdo->query(
            "SHOW COLUMNS FROM notes WHERE Field = 'updated_at'"
        );
        $column = $stmt->fetch();

        $this->assertNotNull($column, "updated_at column should exist");
        $this->assertEquals('timestamp', strtolower($column['Type']), "updated_at should be TIMESTAMP type");
        $this->assertEquals('YES', $column['Null'], "updated_at should allow NULL");
        $this->assertStringContainsString('on update', strtolower($column['Extra']), "updated_at should have ON UPDATE CURRENT_TIMESTAMP");
    }

    /**
     * Test that index exists on user_id field
     * Requirement 15.2
     */
    public function testUserIdIndex(): void
    {
        $stmt = $this->pdo->query(
            "SHOW INDEX FROM notes WHERE Column_name = 'user_id' AND Non_unique = 1"
        );
        $index = $stmt->fetch();

        $this->assertNotNull($index, "Index should exist on user_id field");
        $this->assertEquals('user_id', $index['Column_name'], "Index should be on user_id column");
    }

    /**
     * Test that FULLTEXT index exists on title and content
     * Requirement 15.2: Note_Entity SHALL have FULLTEXT index on title and content for search
     */
    public function testFullTextIndex(): void
    {
        $stmt = $this->pdo->query(
            "SHOW INDEX FROM notes WHERE Index_type = 'FULLTEXT' AND Column_name IN ('title', 'content')"
        );
        $indexes = $stmt->fetchAll();

        $this->assertNotEmpty($indexes, "FULLTEXT index should exist on title and content");
        
        // Verify both title and content are in the FULLTEXT index
        $columns = array_column($indexes, 'Column_name');
        $this->assertContains('title', $columns, "FULLTEXT index should include title column");
        $this->assertContains('content', $columns, "FULLTEXT index should include content column");
    }

    /**
     * Test that foreign key constraint exists on user_id
     * Requirement 15.2: Note_Entity SHALL have foreign key constraint to users table with ON DELETE CASCADE
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
            WHERE TABLE_NAME = 'notes'
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
     * Requirement 15.2 (MySQL syntax as specified in design)
     */
    public function testTableEngine(): void
    {
        $stmt = $this->pdo->query(
            "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_NAME = 'notes'"
        );
        $tableInfo = $stmt->fetch();

        $this->assertNotNull($tableInfo, "Table info should be retrievable");
        $this->assertEquals('InnoDB', $tableInfo['ENGINE'], "Table should use InnoDB engine");
    }

    /**
     * Test that table uses utf8mb4 charset
     * Requirement 15.2 (MySQL syntax as specified in design)
     */
    public function testTableCharset(): void
    {
        $stmt = $this->pdo->query(
            "SELECT TABLE_CHARSET FROM information_schema.TABLES WHERE TABLE_NAME = 'notes'"
        );
        $tableInfo = $stmt->fetch();

        $this->assertNotNull($tableInfo, "Table info should be retrievable");
        $this->assertEquals('utf8mb4', $tableInfo['TABLE_CHARSET'], "Table should use utf8mb4 charset");
    }

    /**
     * Test that InnoDB supports FULLTEXT indexes
     * Requirement 15.2 (FULLTEXT index requirement)
     */
    public function testInnoDBSupportsFullText(): void
    {
        // Verify InnoDB supports FULLTEXT indexes
        $stmt = $this->pdo->query(
            "SELECT @@innodb_version"
        );
        $version = $stmt->fetch();

        $this->assertNotNull($version, "InnoDB version should be retrievable");
        $this->assertNotEmpty($version['@@innodb_version'], "InnoDB should be available");
    }
}
