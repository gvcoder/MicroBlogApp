<?php

declare(strict_types=1);

/**
 * Unit tests for search and filter functionality in NoteService
 * Requirements: 8.1-8.5, 9.1-9.5
 */

use PHPUnit\Framework\TestCase;
use PersonalNotes\NoteService;

require_once API_PATH . '/NoteService.php';

/**
 * @covers NoteService
 */
final class SearchFilterTest extends TestCase
{
    private PDO $pdo;
    private NoteService $service;
    private int $userId;

    protected function setUp(): void
    {
        $host   = $_ENV['DB_HOST']     ?? 'localhost';
        $port   = $_ENV['DB_PORT']     ?? '3306';
        $dbName = $_ENV['DB_NAME']     ?? 'personal_notes_test';
        $user   = $_ENV['DB_USER']     ?? 'root';
        $pass   = $_ENV['DB_PASSWORD'] ?? '';

        $this->pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );

        $this->service = new NoteService($this->pdo);

        $hash = password_hash('TestPass123', PASSWORD_BCRYPT, ['cost' => 12]);
        $this->pdo->exec("DELETE FROM users WHERE email = 'searchtest@example.com'");
        $stmt = $this->pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
        $stmt->execute(['searchtest@example.com', $hash]);
        $this->userId = (int)$this->pdo->lastInsertId();

        // Seed notes
        $this->service->createNote($this->userId, 'PHP Tutorial',   'Learn PHP basics',          ['php', 'tutorial']);
        $this->service->createNote($this->userId, 'MySQL Guide',    'Database with MySQL',        ['mysql', 'database']);
        $this->service->createNote($this->userId, 'PHP and MySQL',  'Using PHP with MySQL',       ['php', 'mysql']);
        $this->service->createNote($this->userId, 'JavaScript Tips','Frontend JS techniques',     ['js']);
        $this->service->createNote($this->userId, 'No Tags Note',   'A note without any tags');
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([$this->userId]);
    }

    // ── Search ────────────────────────────────────────────────────────────

    /** Req 8.1, 8.2 */
    public function testSearchReturnMatchingNotes(): void
    {
        $results = $this->service->searchNotes($this->userId, 'PHP');
        $this->assertNotEmpty($results);
        foreach ($results as $note) {
            $matchesTitle   = stripos($note['title'],   'PHP') !== false;
            $matchesContent = stripos($note['content'], 'PHP') !== false;
            $this->assertTrue($matchesTitle || $matchesContent, "Note should contain keyword");
        }
    }

    /** Req 8.3 */
    public function testSearchWithNoMatchReturnsEmpty(): void
    {
        $results = $this->service->searchNotes($this->userId, 'xyznonexistentkeyword123');
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /** Req 8.2 — matches title */
    public function testSearchMatchesTitleField(): void
    {
        $results = $this->service->searchNotes($this->userId, 'Tutorial');
        $titles  = array_column($results, 'title');
        $this->assertContains('PHP Tutorial', $titles);
    }

    /** Req 8.2 — matches content */
    public function testSearchMatchesContentField(): void
    {
        $results  = $this->service->searchNotes($this->userId, 'Frontend');
        $contents = array_column($results, 'content');
        $found    = array_filter($contents, fn($c) => stripos($c, 'Frontend') !== false);
        $this->assertNotEmpty($found);
    }

    // ── Filter ────────────────────────────────────────────────────────────

    /** Req 9.1 */
    public function testFilterBySingleTagReturnsMatchingNotes(): void
    {
        $results = $this->service->filterNotesByTags($this->userId, ['php']);
        $this->assertNotEmpty($results);
        foreach ($results as $note) {
            $this->assertContains('php', $note['tags']);
        }
    }

    /** Req 9.2 — intersection */
    public function testFilterByMultipleTagsReturnsIntersection(): void
    {
        $results = $this->service->filterNotesByTags($this->userId, ['php', 'mysql']);
        $this->assertNotEmpty($results);
        foreach ($results as $note) {
            $this->assertContains('php',   $note['tags']);
            $this->assertContains('mysql', $note['tags']);
        }
    }

    /** Req 9.3 */
    public function testFilterWithNoMatchReturnsEmpty(): void
    {
        $results = $this->service->filterNotesByTags($this->userId, ['nonexistenttag999']);
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /** Req 9.1 — case-insensitive */
    public function testFilterTagIsCaseInsensitive(): void
    {
        $lower = $this->service->filterNotesByTags($this->userId, ['php']);
        $upper = $this->service->filterNotesByTags($this->userId, ['PHP']);
        $this->assertCount(count($lower), $upper);
    }

    // ── Combined ──────────────────────────────────────────────────────────

    /** Req 17.1 */
    public function testCombinedSearchAndFilterReturnsBothCriteria(): void
    {
        $results = $this->service->searchAndFilterNotes($this->userId, 'MySQL', ['php']);
        foreach ($results as $note) {
            $matchesKeyword = stripos($note['title'], 'MySQL') !== false
                           || stripos($note['content'], 'MySQL') !== false;
            $this->assertTrue($matchesKeyword);
            $this->assertContains('php', $note['tags']);
        }
    }

    /** Req 17.4 */
    public function testCombinedWithNoMatchReturnsEmpty(): void
    {
        $results = $this->service->searchAndFilterNotes($this->userId, 'xyznonexistent', ['php']);
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }
}
