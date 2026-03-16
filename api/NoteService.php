<?php

declare(strict_types=1);

/**
 * Note Service Class
 * 
 * Handles note CRUD operations with validation and business logic.
 * 
 * Requirements: 4.1-4.5, 5.1-5.5, 6.1-6.5, 7.1-7.5, 20.1-20.5
 */

namespace PersonalNotes;

use PDO;
use PDOException;
use Exception;

/**
 * NoteService class for managing note operations
 */
class NoteService
{
    /**
     * Database connection
     * 
     * @var PDO
     */
    private PDO $pdo;

    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a new note
     * 
     * Requirement 4.1: WHEN a note creation request is submitted with valid data, 
     * THE Note_Manager SHALL create the note and assign a unique identifier
     * 
     * Requirement 4.2: WHEN a note creation request is submitted, 
     * THE Note_Manager SHALL store the note with the current timestamp
     * 
     * Requirement 4.3: WHEN a note creation request includes tags, 
     * THE Note_Manager SHALL associate the tags with the note
     * 
     * @param int $userId User ID creating the note
     * @param string $title Note title
     * @param string $content Note content
     * @param array $tags Optional array of tag names
     * @return array Created note data with ID
     * @throws Exception If validation fails or database operation fails
     */
    public function createNote(int $userId, string $title, string $content, array $tags = []): array
    {
        // Validate input
        $this->validateNoteTitle($title);
        $this->validateNoteContent($content);
        
        // Validate required fields
        if (empty(trim($title))) {
            throw new Exception('Title is required', 400);
        }
        
        if (empty(trim($content))) {
            throw new Exception('Content is required', 400);
        }
        
        // Begin transaction for note and tag creation
        $this->pdo->beginTransaction();
        
        try {
            // Create note
            $stmt = $this->pdo->prepare('
                INSERT INTO notes (user_id, title, content) 
                VALUES (?, ?, ?)
            ');
            $stmt->execute([$userId, $title, $content]);
            
            $noteId = (int)$this->pdo->lastInsertId();
            
            // Associate tags if provided
            if (!empty($tags)) {
                $this->associateTags($noteId, $tags);
            }
            
            // Commit transaction
            $this->pdo->commit();
            
            // Return created note
            return $this->getNoteById($noteId, $userId);
            
        } catch (PDOException $e) {
            // Rollback on error
            $this->pdo->rollBack();
            throw new Exception(
                "Failed to create note: " . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Get all notes for a user
     * 
     * Requirement 5.1: WHEN a notes retrieval request is submitted, 
     * THE Note_Manager SHALL return all notes belonging to the user
     * 
     * @param int $userId User ID
     * @param int $limit Optional limit for pagination
     * @param int $offset Optional offset for pagination
     * @return array List of notes with tags
     * @throws Exception If database operation fails
     */
    public function getAllNotes(int $userId, int $limit = 50, int $offset = 0): array
    {
        try {
            // Get notes with pagination
            $stmt = $this->pdo->prepare('
                SELECT n.note_id, n.title, n.content, n.created_at, n.updated_at
                FROM notes n
                WHERE n.user_id = ?
                ORDER BY n.updated_at DESC
                LIMIT ? OFFSET ?
            ');
            $stmt->execute([$userId, $limit, $offset]);
            $notes = $stmt->fetchAll();
            
            // Get tags for each note
            foreach ($notes as &$note) {
                $note['tags'] = $this->getTagsForNote((int)$note['note_id']);
            }
            
            return $notes;
            
        } catch (PDOException $e) {
            throw new Exception(
                "Failed to retrieve notes: " . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Get a single note by ID
     * 
     * Requirement 5.2: WHEN a single note retrieval request is submitted, 
     * THE Note_Manager SHALL return the specific note
     * 
     * Requirement 5.3: IF a note retrieval request is for a non-existent note, 
     * THEN THE Note_Manager SHALL return a 404 Not Found response
     * 
     * @param int $noteId Note ID
     * @param int $userId User ID for ownership verification
     * @return array Note data with tags
     * @throws Exception If note not found or database operation fails
     */
    public function getNoteById(int $noteId, int $userId): array
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT n.note_id, n.title, n.content, n.created_at, n.updated_at
                FROM notes n
                WHERE n.note_id = ? AND n.user_id = ?
            ');
            $stmt->execute([$noteId, $userId]);
            $note = $stmt->fetch();
            
            if (!$note) {
                throw new Exception('Note not found', 404);
            }
            
            // Get tags for the note
            $note['tags'] = $this->getTagsForNote($noteId);
            
            return $note;
            
        } catch (PDOException $e) {
            throw new Exception(
                "Failed to retrieve note: " . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Update an existing note
     * 
     * Requirement 6.1: WHEN a note update request is submitted with valid data, 
     * THE Note_Manager SHALL update the note and modify the timestamp
     * 
     * Requirement 6.2: WHEN a note update request includes tag changes, 
     * THE Note_Manager SHALL update the associated tags
     * 
     * @param int $noteId Note ID
     * @param int $userId User ID for ownership verification
     * @param string $title Updated title
     * @param string $content Updated content
     * @param array $tags Updated array of tag names
     * @return array Updated note data
     * @throws Exception If validation fails, note not found, or database operation fails
     */
    public function updateNote(int $noteId, int $userId, string $title, string $content, array $tags = []): array
    {
        // Validate input
        $this->validateNoteTitle($title);
        $this->validateNoteContent($content);
        
        // Validate required fields
        if (empty(trim($title))) {
            throw new Exception('Title is required', 400);
        }
        
        if (empty(trim($content))) {
            throw new Exception('Content is required', 400);
        }
        
        // Begin transaction for note and tag updates
        $this->pdo->beginTransaction();
        
        try {
            // Verify note exists and belongs to user
            $this->verifyNoteOwnership($noteId, $userId);
            
            // Update note
            $stmt = $this->pdo->prepare('
                UPDATE notes 
                SET title = ?, content = ?, updated_at = CURRENT_TIMESTAMP
                WHERE note_id = ? AND user_id = ?
            ');
            $stmt->execute([$title, $content, $noteId, $userId]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Note not found or access denied', 404);
            }
            
            // Update tags
            $this->updateNoteTags($noteId, $tags);
            
            // Commit transaction
            $this->pdo->commit();
            
            // Return updated note
            return $this->getNoteById($noteId, $userId);
            
        } catch (PDOException $e) {
            // Rollback on error
            $this->pdo->rollBack();
            throw new Exception(
                "Failed to update note: " . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Delete a note
     * 
     * Requirement 7.1: WHEN a note deletion request is submitted, 
     * THE Note_Manager SHALL remove the note from storage
     * 
     * Requirement 7.2: WHEN a note deletion request is submitted, 
     * THE Note_Manager SHALL also delete any associated tags
     * (Note: This is handled automatically by ON DELETE CASCADE foreign key)
     * 
     * @param int $noteId Note ID
     * @param int $userId User ID for ownership verification
     * @return bool True if note was deleted
     * @throws Exception If note not found or database operation fails
     */
    public function deleteNote(int $noteId, int $userId): bool
    {
        try {
            // Verify note exists and belongs to user
            $this->verifyNoteOwnership($noteId, $userId);
            
            // Delete note (note_tags will be deleted automatically due to CASCADE)
            $stmt = $this->pdo->prepare('
                DELETE FROM notes 
                WHERE note_id = ? AND user_id = ?
            ');
            $stmt->execute([$noteId, $userId]);
            
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            throw new Exception(
                "Failed to delete note: " . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Validate note title length
     * 
     * Requirement 20.1: WHEN a note title exceeds 200 characters, 
     * THE System SHALL return a validation error
     * 
     * @param string $title Note title
     * @throws Exception If title exceeds maximum length
     */
    private function validateNoteTitle(string $title): void
    {
        if (strlen($title) > 200) {
            throw new Exception('Title must not exceed 200 characters', 400);
        }
    }

    /**
     * Validate note content length
     * 
     * Requirement 20.2: WHEN a note content exceeds 10000 characters, 
     * THE System SHALL return a validation error
     * 
     * @param string $content Note content
     * @throws Exception If content exceeds maximum length
     */
    private function validateNoteContent(string $content): void
    {
        if (strlen($content) > 10000) {
            throw new Exception('Content must not exceed 10000 characters', 400);
        }
    }

    /**
     * Verify that a note belongs to a specific user
     * 
     * @param int $noteId Note ID
     * @param int $userId User ID
     * @return bool True if user owns the note
     * @throws Exception If note not found or database operation fails
     */
    private function verifyNoteOwnership(int $noteId, int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT note_id FROM notes 
                WHERE note_id = ? AND user_id = ?
            ');
            $stmt->execute([$noteId, $userId]);
            
            return $stmt->fetch() !== false;
            
        } catch (PDOException $e) {
            throw new Exception(
                "Note ownership verification failed: " . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Get tags for a specific note
     * 
     * @param int $noteId Note ID
     * @return array List of tag names
     * @throws Exception If database operation fails
     */
    private function getTagsForNote(int $noteId): array
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT t.name 
                FROM tags t
                INNER JOIN note_tags nt ON t.tag_id = nt.tag_id
                WHERE nt.note_id = ?
                ORDER BY t.name
            ');
            $stmt->execute([$noteId]);
            
            $tags = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            return $tags ?: [];
            
        } catch (PDOException $e) {
            throw new Exception(
                "Failed to retrieve tags: " . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Associate tags with a note
     * 
     * @param int $noteId Note ID
     * @param array $tagNames Array of tag names
     * @throws Exception If database operation fails
     */
    private function associateTags(int $noteId, array $tagNames): void
    {
        try {
            // Remove existing tags for this note
            $stmt = $this->pdo->prepare('DELETE FROM note_tags WHERE note_id = ?');
            $stmt->execute([$noteId]);
            
            // Associate new tags
            foreach ($tagNames as $tagName) {
                $tagName = trim($tagName);
                if (empty($tagName)) {
                    continue;
                }
                
                // Validate tag name length
                if (strlen($tagName) > 50) {
                    throw new Exception('Tag name must not exceed 50 characters: ' . $tagName, 400);
                }
                
                // Get or create tag
                $tagId = $this->getOrCreateTag($tagName);
                
                // Associate tag with note
                $stmt = $this->pdo->prepare('
                    INSERT IGNORE INTO note_tags (note_id, tag_id) 
                    VALUES (?, ?)
                ');
                $stmt->execute([$noteId, $tagId]);
            }
            
        } catch (PDOException $e) {
            throw new Exception(
                "Failed to associate tags: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Update tags for a note
     * 
     * @param int $noteId Note ID
     * @param array $tagNames Array of tag names
     * @throws Exception If database operation fails
     */
    private function updateNoteTags(int $noteId, array $tagNames): void
    {
        $this->associateTags($noteId, $tagNames);
    }

    /**
     * Get tag ID by name, creating the tag if it doesn't exist
     * 
     * @param string $tagName Tag name
     * @return int Tag ID
     * @throws Exception If database operation fails
     */
    private function getOrCreateTag(string $tagName): int
    {
        try {
            // Try to get existing tag
            $stmt = $this->pdo->prepare('SELECT tag_id FROM tags WHERE name = ?');
            $stmt->execute([$tagName]);
            $tag = $stmt->fetch();
            
            if ($tag) {
                return (int)$tag['tag_id'];
            }
            
            // Create new tag
            $stmt = $this->pdo->prepare('INSERT INTO tags (name) VALUES (?)');
            $stmt->execute([$tagName]);
            
            return (int)$this->pdo->lastInsertId();
            
        } catch (PDOException $e) {
            throw new Exception(
                "Failed to get or create tag: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Combined search and filter notes by keywords AND tags
     *
     * Requirement 17.1: WHEN a request combines search keywords and tag filters,
     * THE System SHALL return notes matching both criteria
     *
     * @param int    $userId   User ID
     * @param string $keywords Search keywords (empty string = no keyword filter)
     * @param array  $tagNames Tag names to filter by (empty array = no tag filter)
     * @param int    $limit
     * @param int    $offset
     * @return array List of matching notes with tags
     * @throws Exception If database operation fails
     */
    public function searchAndFilterNotes(
        int $userId,
        string $keywords,
        array $tagNames,
        int $limit = 50,
        int $offset = 0
    ): array {
        // Delegate to simpler methods when only one criterion is active
        if ($keywords !== '' && empty($tagNames)) {
            return $this->searchNotes($userId, $keywords, $limit, $offset);
        }
        if ($keywords === '' && !empty($tagNames)) {
            return $this->filterNotesByTags($userId, $tagNames, $limit, $offset);
        }
        if ($keywords === '' && empty($tagNames)) {
            return $this->getAllNotes($userId, $limit, $offset);
        }

        try {
            $placeholders = implode(',', array_fill(0, count($tagNames), '?'));

            $stmt = $this->pdo->prepare("
                SELECT
                    n.note_id,
                    n.title,
                    n.content,
                    n.created_at,
                    n.updated_at,
                    MATCH(n.title, n.content) AGAINST(? IN BOOLEAN MODE) as relevance,
                    COUNT(DISTINCT t.name) as tag_count
                FROM notes n
                INNER JOIN note_tags nt ON n.note_id = nt.note_id
                INNER JOIN tags t       ON nt.tag_id  = t.tag_id
                WHERE n.user_id = ?
                AND MATCH(n.title, n.content) AGAINST(? IN BOOLEAN MODE)
                AND t.name IN ($placeholders)
                GROUP BY n.note_id
                HAVING tag_count = ?
                ORDER BY relevance DESC
                LIMIT ? OFFSET ?
            ");

            $params = array_merge(
                [$keywords, $userId, $keywords],
                $tagNames,
                [count($tagNames), $limit, $offset]
            );
            $stmt->execute($params);
            $notes = $stmt->fetchAll();

            foreach ($notes as &$note) {
                $note['tags'] = $this->getTagsForNote((int)$note['note_id']);
            }

            return $notes;

        } catch (PDOException $e) {
            throw new Exception(
                "Failed to search and filter notes: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Search notes by keywords
     * 
     * Requirement 8.1: WHEN a search request is submitted with keywords, 
     * THE Search_System SHALL return notes matching the search criteria
     * 
     * Requirement 8.2: WHEN a search request is submitted, 
     * THE Search_System SHALL match keywords against note titles and content
     * 
     * @param int $userId User ID
     * @param string $keywords Search keywords
     * @param int $limit Optional limit for pagination
     * @param int $offset Optional offset for pagination
     * @return array List of matching notes with tags
     * @throws Exception If database operation fails
     */
    public function searchNotes(int $userId, string $keywords, int $limit = 50, int $offset = 0): array
    {
        try {
            // Use MySQL FULLTEXT search
            $stmt = $this->pdo->prepare('
                SELECT 
                    n.note_id, 
                    n.title, 
                    n.content, 
                    n.created_at, 
                    n.updated_at,
                    MATCH(n.title, n.content) AGAINST(? IN BOOLEAN MODE) as relevance
                FROM notes n
                WHERE n.user_id = ?
                AND MATCH(n.title, n.content) AGAINST(? IN BOOLEAN MODE)
                ORDER BY relevance DESC
                LIMIT ? OFFSET ?
            ');
            $stmt->execute([$keywords, $userId, $keywords, $limit, $offset]);
            $notes = $stmt->fetchAll();
            
            // Get tags for each note
            foreach ($notes as &$note) {
                $note['tags'] = $this->getTagsForNote((int)$note['note_id']);
            }
            
            return $notes;
            
        } catch (PDOException $e) {
            throw new Exception(
                "Failed to search notes: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Filter notes by tags
     * 
     * Requirement 9.1: WHEN a tag filter request is submitted, 
     * THE Filter_System SHALL return notes matching the specified tag
     * 
     * Requirement 9.2: WHEN a multi-tag filter request is submitted, 
     * THE Filter_System SHALL return notes matching ALL specified tags
     * 
     * @param int $userId User ID
     * @param array $tagNames Array of tag names to filter by
     * @param int $limit Optional limit for pagination
     * @param int $offset Optional offset for pagination
     * @return array List of filtered notes with tags
     * @throws Exception If database operation fails
     */
    public function filterNotesByTags(int $userId, array $tagNames, int $limit = 50, int $offset = 0): array
    {
        if (empty($tagNames)) {
            return $this->getAllNotes($userId, $limit, $offset);
        }
        
        try {
            // Build query for multi-tag filtering (notes must have ALL tags)
            $placeholders = implode(',', array_fill(0, count($tagNames), '?'));
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    n.note_id, 
                    n.title, 
                    n.content, 
                    n.created_at, 
                    n.updated_at,
                    COUNT(DISTINCT t.name) as tag_count
                FROM notes n
                INNER JOIN note_tags nt ON n.note_id = nt.note_id
                INNER JOIN tags t ON nt.tag_id = t.tag_id
                WHERE n.user_id = ?
                AND t.name IN ($placeholders)
                GROUP BY n.note_id
                HAVING tag_count = ?
                ORDER BY n.updated_at DESC
                LIMIT ? OFFSET ?
            ");
            
            // Prepare parameters: userId, tag names, tag count, limit, offset
            $params = array_merge([$userId], $tagNames, [count($tagNames), $limit, $offset]);
            $stmt->execute($params);
            
            $notes = $stmt->fetchAll();
            
            // Get tags for each note
            foreach ($notes as &$note) {
                $note['tags'] = $this->getTagsForNote((int)$note['note_id']);
            }
            
            return $notes;
            
        } catch (PDOException $e) {
            throw new Exception(
                "Failed to filter notes: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
?>