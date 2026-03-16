<?php

declare(strict_types=1);

// Test script for NoteService and notes API

require_once 'api/Database.php';
require_once 'api/NoteService.php';

use PersonalNotes\Database;
use PersonalNotes\NoteService;

echo "Testing Note CRUD API Implementation\n";
echo "====================================\n\n";

try {
    // Get database connection
    $pdo = Database::getConnection();
    echo "✓ Database connection successful\n";
    
    // Check if tables exist
    $tables = ['users', 'notes', 'tags', 'note_tags', 'sessions'];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT 1 FROM $table LIMIT 1");
            echo "✓ Table '$table' exists\n";
        } catch (PDOException $e) {
            echo "✗ Table '$table' does not exist or error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n";
    
    // Create NoteService instance
    $noteService = new NoteService($pdo);
    echo "✓ NoteService instance created\n";
    
    // Test user ID (assuming user exists)
    $testUserId = 1;
    
    // Test 1: Create a note
    echo "\nTest 1: Creating a note...\n";
    try {
        $note = $noteService->createNote(
            $testUserId,
            'Test Note Title',
            'This is the content of the test note.',
            ['test', 'php', 'api']
        );
        echo "✓ Note created successfully\n";
        echo "  - ID: " . $note['note_id'] . "\n";
        echo "  - Title: " . $note['title'] . "\n";
        echo "  - Tags: " . implode(', ', $note['tags']) . "\n";
        $noteId = $note['note_id'];
    } catch (Exception $e) {
        echo "✗ Note creation failed: " . $e->getMessage() . "\n";
        exit(1);
    }
    
    // Test 2: Get note by ID
    echo "\nTest 2: Getting note by ID...\n";
    try {
        $retrievedNote = $noteService->getNoteById($noteId, $testUserId);
        echo "✓ Note retrieved successfully\n";
        echo "  - Title matches: " . ($retrievedNote['title'] === 'Test Note Title' ? 'Yes' : 'No') . "\n";
        echo "  - Content matches: " . ($retrievedNote['content'] === 'This is the content of the test note.' ? 'Yes' : 'No') . "\n";
    } catch (Exception $e) {
        echo "✗ Note retrieval failed: " . $e->getMessage() . "\n";
    }
    
    // Test 3: Get all notes
    echo "\nTest 3: Getting all notes...\n";
    try {
        $allNotes = $noteService->getAllNotes($testUserId);
        echo "✓ Retrieved " . count($allNotes) . " note(s)\n";
        echo "  - First note title: " . $allNotes[0]['title'] . "\n";
    } catch (Exception $e) {
        echo "✗ Get all notes failed: " . $e->getMessage() . "\n";
    }
    
    // Test 4: Update note
    echo "\nTest 4: Updating note...\n";
    try {
        $updatedNote = $noteService->updateNote(
            $noteId,
            $testUserId,
            'Updated Test Note Title',
            'Updated content with more details.',
            ['updated', 'test', 'php']
        );
        echo "✓ Note updated successfully\n";
        echo "  - New title: " . $updatedNote['title'] . "\n";
        echo "  - New tags: " . implode(', ', $updatedNote['tags']) . "\n";
    } catch (Exception $e) {
        echo "✗ Note update failed: " . $e->getMessage() . "\n";
    }
    
    // Test 5: Search notes
    echo "\nTest 5: Searching notes...\n";
    try {
        $searchResults = $noteService->searchNotes($testUserId, 'Updated');
        echo "✓ Search completed\n";
        echo "  - Found " . count($searchResults) . " note(s) containing 'Updated'\n";
        if (count($searchResults) > 0) {
            echo "  - First result title: " . $searchResults[0]['title'] . "\n";
        }
    } catch (Exception $e) {
        echo "✗ Search failed: " . $e->getMessage() . "\n";
    }
    
    // Test 6: Filter notes by tags
    echo "\nTest 6: Filtering notes by tags...\n";
    try {
        $filteredNotes = $noteService->filterNotesByTags($testUserId, ['php', 'test']);
        echo "✓ Filter completed\n";
        echo "  - Found " . count($filteredNotes) . " note(s) with tags 'php' and 'test'\n";
    } catch (Exception $e) {
        echo "✗ Filter failed: " . $e->getMessage() . "\n";
    }
    
    // Test 7: Delete note
    echo "\nTest 7: Deleting note...\n";
    try {
        $deleted = $noteService->deleteNote($noteId, $testUserId);
        echo "✓ Note deleted: " . ($deleted ? 'Yes' : 'No') . "\n";
        
        // Verify deletion
        try {
            $noteService->getNoteById($noteId, $testUserId);
            echo "✗ Note still exists after deletion\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Note not found') !== false) {
                echo "✓ Note successfully deleted (not found when trying to retrieve)\n";
            } else {
                echo "✗ Unexpected error: " . $e->getMessage() . "\n";
            }
        }
    } catch (Exception $e) {
        echo "✗ Note deletion failed: " . $e->getMessage() . "\n";
    }
    
    // Test 8: Validation tests
    echo "\nTest 8: Testing validation...\n";
    
    // Test title too long
    try {
        $longTitle = str_repeat('a', 201);
        $noteService->createNote($testUserId, $longTitle, 'Valid content');
        echo "✗ Should have rejected title > 200 chars\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Title must not exceed 200 characters') !== false) {
            echo "✓ Correctly rejected title > 200 chars\n";
        } else {
            echo "✗ Wrong error message: " . $e->getMessage() . "\n";
        }
    }
    
    // Test content too long
    try {
        $longContent = str_repeat('a', 10001);
        $noteService->createNote($testUserId, 'Valid title', $longContent);
        echo "✗ Should have rejected content > 10000 chars\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Content must not exceed 10000 characters') !== false) {
            echo "✓ Correctly rejected content > 10000 chars\n";
        } else {
            echo "✗ Wrong error message: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n====================================\n";
    echo "All tests completed!\n";
    
} catch (Exception $e) {
    echo "✗ Test setup failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>