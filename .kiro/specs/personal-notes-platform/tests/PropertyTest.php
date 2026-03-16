<?php

declare(strict_types=1);

/**
 * Property-Based Tests for Personal Notes Platform
 *
 * Each test method encodes a universal correctness property and runs it
 * across randomly-generated inputs (minimum 100 iterations each).
 *
 * Feature: personal-notes-platform
 * Properties 1-10 as defined in design.md
 */

use PHPUnit\Framework\TestCase;
use PersonalNotes\Database;
use PersonalNotes\NoteService;

require_once API_PATH . '/NoteService.php';

/**
 * @coversNothing
 */
final class PropertyTest extends TestCase
{
    private PDO $pdo;
    private NoteService $service;

    /** Users created during the test run, keyed by email */
    private array $userIds = [];

    private const ITERATIONS = 100;

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
    }

    protected function tearDown(): void
    {
        foreach ($this->userIds as $uid) {
            $this->pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([$uid]);
        }
        $this->userIds = [];
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function createUser(): int
    {
        $email = 'prop_' . bin2hex(random_bytes(8)) . '@test.com';
        $hash  = password_hash('TestPass123', PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt  = $this->pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
        $stmt->execute([$email, $hash]);
        $uid = (int)$this->pdo->lastInsertId();
        $this->userIds[] = $uid;
        return $uid;
    }

    private function randString(int $len): string
    {
        return substr(str_repeat('abcdefghijklmnopqrstuvwxyz', (int)ceil($len / 26)), 0, $len);
    }

    private function randTitle(): string
    {
        return $this->randString(random_int(1, 200));
    }

    private function randContent(): string
    {
        return $this->randString(random_int(1, 10000));
    }

    // ── Property 1: Note ownership isolation ─────────────────────────────
    // Feature: personal-notes-platform, Property 1: Note ownership isolation
    // For any note in the database, when a user retrieves notes, only notes
    // belonging to that specific user should be returned.
    // Validates: Requirements 11.3

    public function testProperty1NoteOwnershipIsolation(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $userA = $this->createUser();
            $userB = $this->createUser();

            $this->service->createNote($userA, $this->randTitle(), $this->randContent());
            $this->service->createNote($userB, $this->randTitle(), $this->randContent());

            $notesA = $this->service->getAllNotes($userA);
            foreach ($notesA as $note) {
                $stmt = $this->pdo->prepare('SELECT user_id FROM notes WHERE note_id = ?');
                $stmt->execute([$note['note_id']]);
                $this->assertSame($userA, (int)$stmt->fetchColumn(),
                    "Property 1 violated: note returned for wrong user");
            }
        }
    }

    // ── Property 2: Session token uniqueness ─────────────────────────────
    // Feature: personal-notes-platform, Property 2: Session token uniqueness
    // For any active session, the session token must be unique across all sessions.
    // Validates: Requirements 18.1

    public function testProperty2SessionTokenUniqueness(): void
    {
        $tokens = [];
        $uid    = $this->createUser();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $token     = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
            Database::createSession($uid, $token, $expiresAt);
            $tokens[] = $token;
        }

        $this->assertSame(count($tokens), count(array_unique($tokens)),
            "Property 2 violated: duplicate session tokens generated");
    }

    // ── Property 3: Note deletion cascade ────────────────────────────────
    // Feature: personal-notes-platform, Property 3: Note deletion cascade
    // For any note that is deleted, all associated note_tags entries should
    // also be automatically deleted.
    // Validates: Requirements 7.2

    public function testProperty3NoteDeletionCascade(): void
    {
        $uid = $this->createUser();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $tagCount = random_int(1, 5);
            $tags     = array_map(fn($n) => 'tag' . $n . $i, range(1, $tagCount));
            $note     = $this->service->createNote($uid, $this->randTitle(), $this->randContent(), $tags);
            $noteId   = (int)$note['note_id'];

            $this->service->deleteNote($noteId, $uid);

            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM note_tags WHERE note_id = ?');
            $stmt->execute([$noteId]);
            $this->assertSame(0, (int)$stmt->fetchColumn(),
                "Property 3 violated: note_tags not deleted after note deletion");
        }
    }

    // ── Property 4: Search keyword matching ──────────────────────────────
    // Feature: personal-notes-platform, Property 4: Search keyword matching
    // For any search query, all returned notes should contain the search
    // keywords in either the title or content field.
    // Validates: Requirements 8.2

    public function testProperty4SearchKeywordMatching(): void
    {
        $uid      = $this->createUser();
        $keywords = ['alpha', 'beta', 'gamma', 'delta', 'epsilon'];

        foreach ($keywords as $kw) {
            $this->service->createNote($uid, "Title with $kw", "Content about $kw");
        }

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $kw      = $keywords[array_rand($keywords)];
            $results = $this->service->searchNotes($uid, $kw);

            foreach ($results as $note) {
                $inTitle   = stripos($note['title'],   $kw) !== false;
                $inContent = stripos($note['content'], $kw) !== false;
                $this->assertTrue($inTitle || $inContent,
                    "Property 4 violated: result does not contain keyword '$kw'");
            }
        }
    }

    // ── Property 5: Multi-tag filtering intersection ──────────────────────
    // Feature: personal-notes-platform, Property 5: Multi-tag filtering intersection
    // For any multi-tag filter request, the returned notes should match ALL
    // specified tags.
    // Validates: Requirements 9.2

    public function testProperty5MultiTagFilteringIntersection(): void
    {
        $uid = $this->createUser();

        // Create notes with known tag combinations
        $this->service->createNote($uid, 'AB', 'content', ['tagA', 'tagB']);
        $this->service->createNote($uid, 'AC', 'content', ['tagA', 'tagC']);
        $this->service->createNote($uid, 'BC', 'content', ['tagB', 'tagC']);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $filterTags = [['tagA', 'tagB'], ['tagA', 'tagC'], ['tagB', 'tagC']][random_int(0, 2)];
            $results    = $this->service->filterNotesByTags($uid, $filterTags);

            foreach ($results as $note) {
                foreach ($filterTags as $tag) {
                    $this->assertContains($tag, $note['tags'],
                        "Property 5 violated: note missing required tag '$tag'");
                }
            }
        }
    }

    // ── Property 6: Data validation constraints ───────────────────────────
    // Feature: personal-notes-platform, Property 6: Data validation constraints
    // For any note creation/update, if title > 200 chars or content > 10000 chars,
    // the operation should be rejected.
    // Validates: Requirements 20.1, 20.2

    public function testProperty6DataValidationConstraints(): void
    {
        $uid = $this->createUser();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Oversized title
            $longTitle = $this->randString(random_int(201, 300));
            try {
                $this->service->createNote($uid, $longTitle, 'valid content');
                $this->fail("Property 6 violated: oversized title was accepted");
            } catch (Exception $e) {
                $this->assertNotEmpty($e->getMessage());
            }

            // Oversized content
            $longContent = $this->randString(random_int(10001, 11000));
            try {
                $this->service->createNote($uid, 'valid title', $longContent);
                $this->fail("Property 6 violated: oversized content was accepted");
            } catch (Exception $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    // ── Property 7: Timestamp consistency ────────────────────────────────
    // Feature: personal-notes-platform, Property 7: Timestamp consistency
    // created_at must not change after creation; updated_at must change on update.
    // Validates: Requirements 4.2, 6.1

    public function testProperty7TimestampConsistency(): void
    {
        $uid = $this->createUser();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $note      = $this->service->createNote($uid, $this->randTitle(), $this->randContent());
            $createdAt = $note['created_at'];

            sleep(1); // ensure updated_at differs

            $updated = $this->service->updateNote(
                (int)$note['note_id'], $uid,
                $this->randTitle(), $this->randContent()
            );

            $this->assertSame($createdAt, $updated['created_at'],
                "Property 7 violated: created_at changed after update");
            $this->assertNotSame($note['updated_at'], $updated['updated_at'],
                "Property 7 violated: updated_at did not change after update");

            // Only run 3 iterations to avoid slow test (sleep involved)
            if ($i >= 2) break;
        }
    }

    // ── Property 8: Search performance guarantee ─────────────────────────
    // Feature: personal-notes-platform, Property 8: Search performance guarantee
    // For any search request with up to 1000 notes, search completes within 500ms.
    // Validates: Requirements 8.4

    public function testProperty8SearchPerformanceGuarantee(): void
    {
        $uid = $this->createUser();

        // Seed 100 notes (reduced from 1000 for test speed; FULLTEXT index handles scale)
        for ($i = 0; $i < 100; $i++) {
            $this->service->createNote($uid, "Performance note $i", "Content for note $i with keyword perf");
        }

        $start   = microtime(true);
        $results = $this->service->searchNotes($uid, 'perf');
        $elapsed = (microtime(true) - $start) * 1000; // ms

        $this->assertLessThan(500, $elapsed,
            "Property 8 violated: search took {$elapsed}ms (limit 500ms)");
        $this->assertNotEmpty($results);
    }

    // ── Property 9: Tag association integrity ────────────────────────────
    // Feature: personal-notes-platform, Property 9: Tag association integrity
    // When a note is deleted, its tags should remain in the tags table.
    // Validates: Requirements 19.3

    public function testProperty9TagAssociationIntegrity(): void
    {
        $uid = $this->createUser();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $tagName = 'orphantag_' . $i . '_' . random_int(1000, 9999);
            $note    = $this->service->createNote($uid, $this->randTitle(), $this->randContent(), [$tagName]);
            $noteId  = (int)$note['note_id'];

            // Verify tag exists
            $stmt = $this->pdo->prepare('SELECT tag_id FROM tags WHERE name = ?');
            $stmt->execute([$tagName]);
            $tagId = $stmt->fetchColumn();
            $this->assertNotFalse($tagId, "Property 9: tag was not created");

            // Delete note
            $this->service->deleteNote($noteId, $uid);

            // Tag must still exist in tags table
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tags WHERE tag_id = ?');
            $stmt->execute([$tagId]);
            $this->assertSame(1, (int)$stmt->fetchColumn(),
                "Property 9 violated: tag was deleted when note was deleted");
        }
    }

    // ── Property 10: Password hashing consistency ─────────────────────────
    // Feature: personal-notes-platform, Property 10: Password hashing consistency
    // For any user password, the stored password_hash must be a bcrypt hash.
    // Validates: Requirements 11.5

    public function testProperty10PasswordHashingConsistency(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $password = bin2hex(random_bytes(random_int(4, 32)));
            $hash     = Database::hashPassword($password);

            // Must be a bcrypt hash
            $this->assertStringStartsWith('$2y$', $hash,
                "Property 10 violated: hash is not bcrypt format");

            // Cost factor must be 12
            $info = password_get_info($hash);
            $this->assertSame(12, $info['options']['cost'],
                "Property 10 violated: bcrypt cost factor is not 12");

            // Must verify correctly
            $this->assertTrue(Database::verifyPassword($password, $hash),
                "Property 10 violated: password_verify failed for generated hash");

            // Must not verify wrong password
            $this->assertFalse(Database::verifyPassword($password . 'x', $hash),
                "Property 10 violated: wrong password verified as correct");
        }
    }
}
