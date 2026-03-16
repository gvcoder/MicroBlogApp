<?php

declare(strict_types=1);

/**
 * Tags / Filter API Endpoint
 *
 * GET  /api/tags              - List all tags used by the authenticated user
 * GET  /api/notes?tag={name}  - Filter notes by one or more tags (handled in notes.php)
 *
 * This file also exposes a standalone tag-filter endpoint:
 * GET  /api/tags?filter={tag1,tag2}  - Return notes matching ALL specified tags
 *
 * Requirements: 9.1, 9.2, 9.3, 9.4, 9.5
 */

header('Content-Type: application/json');

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/NoteService.php';
require_once __DIR__ . '/auth/middleware.php';

use PersonalNotes\Database;
use PersonalNotes\NoteService;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'error' => [
            'code'    => 'METHOD_NOT_ALLOWED',
            'message' => 'Method not allowed',
            'details' => 'Only GET requests are allowed'
        ]
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

try {
    $userId = validateAuthToken();
    $pdo    = Database::getConnection();
    $noteService = new NoteService($pdo);

    // If a filter param is provided, return filtered notes
    if (isset($_GET['filter']) && $_GET['filter'] !== '') {
        $tagParam = $_GET['filter'];
        $tags     = array_values(array_filter(array_map('trim', explode(',', $tagParam))));

        if (empty($tags)) {
            http_response_code(400);
            echo json_encode([
                'error' => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'At least one tag is required',
                    'details' => 'Provide comma-separated tag names in the "filter" parameter'
                ]
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }

        $limit  = max(1, min(100, (int)($_GET['limit'] ?? 50)));
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        $notes = $noteService->filterNotesByTags($userId, $tags, $limit, $offset);

        echo json_encode([
            'success' => true,
            'message' => 'Notes filtered by tags',
            'data'    => [
                'notes'      => $notes,
                'tags'       => $tags,
                'pagination' => [
                    'page'  => $page,
                    'limit' => $limit,
                    'count' => count($notes),
                ]
            ]
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }

    // Otherwise list all distinct tags belonging to the user's notes
    $stmt = $pdo->prepare('
        SELECT DISTINCT t.tag_id, t.name
        FROM tags t
        INNER JOIN note_tags nt ON t.tag_id = nt.tag_id
        INNER JOIN notes n      ON nt.note_id = n.note_id
        WHERE n.user_id = ?
        ORDER BY t.name
    ');
    $stmt->execute([$userId]);
    $tags = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'message' => 'Tags retrieved successfully',
        'data'    => ['tags' => $tags]
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

} catch (Exception $e) {
    $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
    http_response_code($code);
    echo json_encode([
        'error' => [
            'code'    => 'FILTER_ERROR',
            'message' => $e->getMessage(),
            'details' => $code === 500 ? 'Internal server error' : $e->getMessage()
        ]
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
