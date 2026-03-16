<?php

declare(strict_types=1);

/**
 * Unit tests for NoteService
 * Requirements: 4.1-4.5, 5.1-5.5, 6.1-6.5, 7.1-7.5, 20.1-20.5
 */

use PHPUnit\Framework\TestCase;
use PersonalNotes\NoteService;

require_once API_PATH . '/NoteService.php';

/**
 * @covers NoteService
 */
final class NoteServiceTest extends TestCase
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

        // Create a test user
        $hash = password_hash('TestPass123', PASSWORD_BCRYPT, ['cost' => 12]);
        $this->pdo->exec("DELETE FROM users WHERE email = 'notetest@example.com'");
        $stmt = $this->pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
        $stmt->execute(['notetest@example.com', $hash]);
        $this->userId = (int)$this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        // Cascade deletes notes, note_tags, sessions via FK
        $this->pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([$this->userId]);
    }

    // ── Create ────────────────────────────────────────────────────────────

    /** Req 4.1, 4.2 */
    public function testCreateNoteReturnsNoteWithId(): void
    {
        $note = $this->service->createNote($this->userId, 'Hello', 'World');
        $this->assertArrayHasKey('note_id', $note);
        $this->assertSame('Hello', $note['title']);
        $this->assertSame('World', $note['content']);
        $this->assertNotEmpty($note['created_at']);
    }

    /** Req 4.3 */
    public function testCreateNoteWithTagsAssociatesTags(): void
    {
        $note = $this->service->createNote($this->userId, 'Tagged', 'Content', ['php', 'testing']);
        $this->assertContains('php',     $note['tags']);
        $this->assertContains('testing', $note['tags']);
    }

    /** Req 4.4, 20.4 */
    public function testCreateNoteWithEmptyTitleThrows(): void
    {
        $this->expectException(Exception::class);
        $this->service->createNote($this->userId, '', 'Content');
    }

    /** Req 4.4, 20.5 */
    public function testCreateNoteWithEmptyContentThrows(): void
    {
        $this->expectException(Exception::class);
        $this->service->createNote($this->userId, 'Title', '');
    }

    /** Req 20.1 */
    public function testCreateNoteWithTitleTooLongThrows(): void
    {
        $this->expectException(Exception::class);
        $this->service->createNote($this->userId, str_repeat('a', 201), 'Content');
    }

    /** Req 20.2 */
    public function testCreateNoteWithContentTooLongThrows(): void
    {
        $this->expectException(Exception::class);
        $this->service->createNote($this->userId, 'Title', str_repeat('x', 10001));
    }

    // ── Read ──────────────────────────────────────────────────────────────

    /** Req 5.1 */
    public function testGetAllNotesReturnsOnlyUserNotes(): void
    {
        $this->service->createNote($this->userId, 'Note A', 'Content A');
        $this->service->createNote($this->userId, 'Note B', 'Content B');

        $notes = $this->service->getAllNotes($this->userId);
        foreach ($notes as $note) {
            // All returned notes must belong to this user
            $stmt = $this->pdo->prepare('SELECT user_id FROM notes WHERE note_id = ?');
            $stmt->execute([$note['note_id']]);
            $this->assertSame($this->userId, (int)$stmt->fetchColumn());
        }
        $this->assertGreaterThanOrEqual(2, count($notes));
    }

    /** Req 5.2 */
    public function testGetNoteByIdReturnsCorrectNote(): void
    {
        $created = $this->service->createNote($this->userId, 'Specific', 'Body');
        $fetched = $this->service->getNoteById((int)$created['note_id'], $this->userId);
        $this->assertSame($created['note_id'], $fetched['note_id']);
        $this->assertSame('Specific', $fetched['title']);
    }

    /** Req 5.3 */
    public function testGetNoteByIdNotFoundThrows(): void
    {
        $this->expectException(Exception::class);
        $this->service->getNoteById(999999, $this->userId);
    }

    // ── Update ────────────────────────────────────────────────────────────

    /** Req 6.1 */
    public function testUpdateNoteChangesContent(): void
    {
        $note    = $this->service->createNote($this->userId, 'Old', 'Old content');
        $updated = $this->service->updateNote((int)$note['note_id'], $this->userId, 'New', 'New content');
        $this->assertSame('New', $updated['title']);
        $this->assertSame('New content', $updated['content']);
    }

    /** Req 6.2 */
    public function testUpdateNoteReplacesTags(): void
    {
        $note    = $this->service->createNote($this->userId, 'T', 'C', ['old-tag']);
        $updated = $this->service->updateNote((int)$note['note_id'], $this->userId, 'T', 'C', ['new-tag']);
        $this->assertContains('new-tag', $updated['tags']);
        $this->assertNotContains('old-tag', $updated['tags']);
    }

    /** Req 6.3 */
    public function testUpdateNoteNotFoundThrows(): void
    {
        $this->expectException(Exception::class);
        $this->service->updateNote(999999, $this->userId, 'T', 'C');
    }

    /** Req 20.1 */
    public function testUpdateNoteWithTitleTooLongThrows(): void
    {
        $note = $this->service->createNote($this->userId, 'Title', 'Content');
        $this->expectException(Exception::class);
        $this->service->updateNote((int)$note['note_id'], $this->userId, str_repeat('a', 201), 'Content');
    }

    // ── Delete ────────────────────────────────────────────────────────────

    /** Req 7.1 */
    public function testDeleteNoteRemovesIt(): void
    {
        $note = $this->service->createNote($this->userId, 'Del', 'Me');
        $id   = (int)$note['note_id'];
        $this->assertTrue($this->service->deleteNote($id, $this->userId));

        $this->expectException(Exception::class);
        $this->service->getNoteById($id, $this->userId);
    }

    /** Req 7.2 — cascade via FK */
    public function testDeleteNoteRemovesNoteTags(): void
    {
        $note = $this->service->createNote($this->userId, 'Tagged', 'Content', ['cascade-tag']);
        $id   = (int)$note['note_id'];
        $this->service->deleteNote($id, $this->userId);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM note_tags WHERE note_id = ?');
        $stmt->execute([$id]);
        $this->assertSame(0, (int)$stmt->fetchColumn());
    }

    /** Req 7.3 */
    public function testDeleteNonExistentNoteThrows(): void
    {
        $this->expectException(Exception::class);
        $this->service->deleteNote(999999, $this->userId);
    }
}
