<?php

declare(strict_types=1);

/**
 * Notes API Endpoint
 * 
 * Handles CRUD operations for notes with authentication and validation.
 * 
 * Requirements: 4.1-4.5, 5.1-5.5, 6.1-6.5, 7.1-7.5, 20.1-20.5
 */

// Set JSON content type
header('Content-Type: application/json');

// Include required files
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/NoteService.php';
require_once __DIR__ . '/auth/middleware.php';

use PersonalNotes\Database;
use PersonalNotes\NoteService;

try {
    // Set JSON content type
    setJsonContentType();
    
    // Get database connection
    $pdo = Database::getConnection();
    
    // Create note service
    $noteService = new NoteService($pdo);
    
    // Get authenticated user ID
    $userId = validateAuthToken();
    
    // Get request method
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Parse request URI
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($requestUri, PHP_URL_PATH);
    $pathSegments = explode('/', trim($path, '/'));
    
    // Check if this is a notes endpoint
    $isNotesEndpoint = end($pathSegments) === 'notes' || in_array('notes', $pathSegments);
    
    if (!$isNotesEndpoint) {
        http_response_code(404);
        echo json_encode([
            'error' => [
                'code' => 'ENDPOINT_NOT_FOUND',
                'message' => 'Endpoint not found',
                'details' => 'The requested endpoint does not exist'
            ]
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
    
    // Handle different HTTP methods
    switch ($method) {
        case 'GET':
            handleGetRequest($noteService, $userId, $pathSegments);
            break;
            
        case 'POST':
            handlePostRequest($noteService, $userId);
            break;
            
        case 'PUT':
            handlePutRequest($noteService, $userId, $pathSegments);
            break;
            
        case 'DELETE':
            handleDeleteRequest($noteService, $userId, $pathSegments);
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'error' => [
                    'code' => 'METHOD_NOT_ALLOWED',
                    'message' => 'Method not allowed',
                    'details' => 'The requested method is not supported for this endpoint'
                ]
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
    }
    
} catch (Exception $e) {
    // Handle exceptions with appropriate HTTP status codes
    $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
    http_response_code($statusCode);
    
    echo json_encode([
        'error' => [
            'code' => 'API_ERROR',
            'message' => $e->getMessage(),
            'details' => $statusCode === 500 ? 'Internal server error' : $e->getMessage()
        ]
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/**
 * Handle GET requests
 * 
 * GET /api/notes - Get all notes
 * GET /api/notes/{id} - Get specific note
 * GET /api/notes?tag={tag} - Filter by tag
 * GET /api/notes?q={keywords} - Search by keywords
 * 
 * @param NoteService $noteService Note service instance
 * @param int $userId Authenticated user ID
 * @param array $pathSegments URL path segments
 */
function handleGetRequest(NoteService $noteService, int $userId, array $pathSegments): void
{
    // Check if requesting specific note by ID
    $noteId = extractNoteIdFromPath($pathSegments);
    
    if ($noteId !== null) {
        // GET /api/notes/{id} - Get specific note
        $note = $noteService->getNoteById($noteId, $userId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Note retrieved successfully',
            'data' => $note
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        return;
    }
    
    // Check for query parameters
    $queryParams = $_GET;
    
    // Parse optional search and tag parameters
    $keywords = trim($queryParams['q'] ?? '');
    $tagParam = $queryParams['tag'] ?? '';
    $tags = array_values(array_filter(array_map('trim', explode(',', $tagParam))));

    // Combined search + filter, or either alone
    if ($keywords !== '' || !empty($tags)) {
        $limit  = max(1, min(100, (int)($queryParams['limit'] ?? 50)));
        $page   = max(1, (int)($queryParams['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        $notes = $noteService->searchAndFilterNotes($userId, $keywords, $tags, $limit, $offset);

        echo json_encode([
            'success' => true,
            'message' => 'Notes retrieved successfully',
            'data' => [
                'notes'        => $notes,
                'search_query' => $keywords ?: null,
                'tags'         => $tags ?: null,
                'pagination'   => ['page' => $page, 'limit' => $limit, 'count' => count($notes)]
            ]
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        return;
    }
    
    // Handle pagination parameters
    $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 50;
    $offset = isset($queryParams['offset']) ? (int)$queryParams['offset'] : 0;
    
    // Validate pagination limits
    $limit = max(1, min(100, $limit)); // Limit between 1 and 100
    $offset = max(0, $offset);
    
    // GET /api/notes - Get all notes (with optional pagination)
    $notes = $noteService->getAllNotes($userId, $limit, $offset);
    
    echo json_encode([
        'success' => true,
        'message' => 'Notes retrieved successfully',
        'data' => [
            'notes' => $notes,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'count' => count($notes)
            ]
        ]
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/**
 * Handle POST requests
 * 
 * POST /api/notes - Create new note
 * 
 * @param NoteService $noteService Note service instance
 * @param int $userId Authenticated user ID
 */
function handlePostRequest(NoteService $noteService, int $userId): void
{
    // Get request body
    $input = getRequestBody();
    
    // Validate required fields
    $validated = validateRequiredFields($input, ['title', 'content']);
    
    // Get tags if provided
    $tags = [];
    if (isset($input['tags']) && is_array($input['tags'])) {
        $tags = array_map('trim', $input['tags']);
        $tags = array_filter($tags); // Remove empty tags
    }
    
    // Create note
    $note = $noteService->createNote(
        $userId,
        $validated['title'],
        $validated['content'],
        $tags
    );
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Note created successfully',
        'data' => $note
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/**
 * Handle PUT requests
 * 
 * PUT /api/notes/{id} - Update existing note
 * 
 * @param NoteService $noteService Note service instance
 * @param int $userId Authenticated user ID
 * @param array $pathSegments URL path segments
 */
function handlePutRequest(NoteService $noteService, int $userId, array $pathSegments): void
{
    // Get note ID from path
    $noteId = extractNoteIdFromPath($pathSegments);
    
    if ($noteId === null) {
        http_response_code(400);
        echo json_encode([
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Note ID is required',
                'details' => 'Note ID must be provided in the URL path'
            ]
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
    
    // Get request body
    $input = getRequestBody();
    
    // Validate required fields
    $validated = validateRequiredFields($input, ['title', 'content']);
    
    // Get tags if provided
    $tags = [];
    if (isset($input['tags']) && is_array($input['tags'])) {
        $tags = array_map('trim', $input['tags']);
        $tags = array_filter($tags); // Remove empty tags
    }
    
    // Update note
    $note = $noteService->updateNote(
        $noteId,
        $userId,
        $validated['title'],
        $validated['content'],
        $tags
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Note updated successfully',
        'data' => $note
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/**
 * Handle DELETE requests
 * 
 * DELETE /api/notes/{id} - Delete note
 * 
 * @param NoteService $noteService Note service instance
 * @param int $userId Authenticated user ID
 * @param array $pathSegments URL path segments
 */
function handleDeleteRequest(NoteService $noteService, int $userId, array $pathSegments): void
{
    // Get note ID from path
    $noteId = extractNoteIdFromPath($pathSegments);
    
    if ($noteId === null) {
        http_response_code(400);
        echo json_encode([
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Note ID is required',
                'details' => 'Note ID must be provided in the URL path'
            ]
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
    
    // Delete note
    $deleted = $noteService->deleteNote($noteId, $userId);
    
    if (!$deleted) {
        http_response_code(404);
        echo json_encode([
            'error' => [
                'code' => 'NOTE_NOT_FOUND',
                'message' => 'Note not found',
                'details' => 'The specified note does not exist or you do not have permission to delete it'
            ]
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Note deleted successfully',
        'data' => [
            'note_id' => $noteId,
            'deleted' => true
        ]
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/**
 * Extract note ID from URL path segments
 * 
 * @param array $pathSegments URL path segments
 * @return int|null Note ID or null if not found
 */
function extractNoteIdFromPath(array $pathSegments): ?int
{
    // Find 'notes' in path and get next segment
    $notesIndex = array_search('notes', $pathSegments);
    
    if ($notesIndex !== false && isset($pathSegments[$notesIndex + 1])) {
        $noteIdSegment = $pathSegments[$notesIndex + 1];
        
        // Check if it's a valid integer
        if (ctype_digit($noteIdSegment)) {
            return (int)$noteIdSegment;
        }
    }
    
    return null;
}

/**
 * Get request body as associative array
 * 
 * @return array Request body data
 */
function getRequestBody(): array
{
    // Get raw input
    $rawInput = file_get_contents('php://input');
    
    if (empty($rawInput)) {
        return [];
    }
    
    // Try to decode as JSON
    $data = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'error' => [
                'code' => 'INVALID_JSON',
                'message' => 'Invalid JSON format',
                'details' => 'The request body must be valid JSON'
            ]
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
    
    return $data ?: [];
}
?>