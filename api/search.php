<?php

declare(strict_types=1);

/**
 * Search API Endpoint
 *
 * Handles full-text keyword search across note titles and content.
 *
 * Requirements: 8.1, 8.2, 8.3, 8.4, 8.5
 *
 * GET /api/search?q={keywords}&page={page}&limit={limit}
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
            'code' => 'METHOD_NOT_ALLOWED',
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

    $keywords = trim($_GET['q'] ?? '');

    if ($keywords === '') {
        http_response_code(400);
        echo json_encode([
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Search query is required',
                'details' => 'Provide a non-empty value for the "q" parameter'
            ]
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }

    $limit  = max(1, min(100, (int)($_GET['limit'] ?? 50)));
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;

    $notes = $noteService->searchNotes($userId, $keywords, $limit, $offset);

    echo json_encode([
        'success' => true,
        'message' => 'Search completed successfully',
        'data' => [
            'notes'        => $notes,
            'search_query' => $keywords,
            'pagination'   => [
                'page'   => $page,
                'limit'  => $limit,
                'count'  => count($notes),
            ]
        ]
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

} catch (Exception $e) {
    $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
    http_response_code($code);
    echo json_encode([
        'error' => [
            'code'    => 'SEARCH_ERROR',
            'message' => $e->getMessage(),
            'details' => $code === 500 ? 'Internal server error' : $e->getMessage()
        ]
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
