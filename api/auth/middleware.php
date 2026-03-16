<?php

declare(strict_types=1);

/**
 * Authentication Middleware
 * 
 * Provides functions for validating session tokens and protecting API endpoints.
 * 
 * Requirements: 11.1, 11.2, 18.1, 18.2, 18.3
 */

use PersonalNotes\Database;

/**
 * Validate session token from request headers
 * 
 * @return int User ID if valid session
 * @throws Exception If token is invalid or missing
 */
function validateAuthToken(): int
{
    // Get authorization header
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    // Extract token from "Bearer {token}" format
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = trim($matches[1]);
    } else {
        $token = '';
    }
    
    // Validate token is present
    if (empty($token)) {
        http_response_code(401);
        echo json_encode([
            'error' => [
                'code' => 'AUTH_TOKEN_REQUIRED',
                'message' => 'Authentication required',
                'details' => 'Session token is required for this operation'
            ]
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
    
    // Validate the session token
    $userId = Database::validateSession($token);
    
    if ($userId === null) {
        http_response_code(401);
        echo json_encode([
            'error' => [
                'code' => 'AUTH_INVALID_TOKEN',
                'message' => 'Invalid session',
                'details' => 'The session token is invalid or expired. Please login again.'
            ]
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
    
    return $userId;
}

/**
 * Check if user owns a specific note
 * 
 * @param PDO $pdo Database connection
 * @param int $noteId Note ID to check
 * @param int $userId User ID to verify ownership
 * @return bool True if user owns the note
 * @throws Exception If database query fails
 */
function verifyNoteOwnership(PDO $pdo, int $noteId, int $userId): bool
{
    try {
        $stmt = $pdo->prepare('SELECT user_id FROM notes WHERE note_id = ?');
        $stmt->execute([$noteId]);
        $note = $stmt->fetch();
        
        if (!$note) {
            return false; // Note doesn't exist
        }
        
        return (int)$note['user_id'] === $userId;
    } catch (PDOException $e) {
        throw new Exception(
            "Note ownership verification failed: " . $e->getMessage(),
            $e->getCode(),
            $e
        );
    }
}

/**
 * Set JSON content type header
 */
function setJsonContentType(): void
{
    header('Content-Type: application/json');
}

/**
 * Validate required fields in input data
 * 
 * @param array $input Input data array
 * @param array $requiredFields List of required field names
 * @return array Validated fields
 * @throws Exception If required fields are missing
 */
function validateRequiredFields(array $input, array $requiredFields): array
{
    $missingFields = [];
    $validated = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field] ?? ''))) {
            $missingFields[] = $field;
        } else {
            $validated[$field] = trim($input[$field]);
        }
    }
    
    if (!empty($missingFields)) {
        http_response_code(400);
        echo json_encode([
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Missing required fields',
                'details' => 'The following fields are required: ' . implode(', ', $missingFields)
            ]
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
    
    return $validated;
}

/**
 * Validate email format
 * 
 * @param string $email Email address to validate
 * @return bool True if email is valid
 */
function validateEmailFormat(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate note title length
 * 
 * @param string $title Note title
 * @return bool True if title is valid (max 200 chars)
 */
function validateNoteTitle(string $title): bool
{
    return strlen($title) <= 200;
}

/**
 * Validate note content length
 * 
 * @param string $content Note content
 * @return bool True if content is valid (max 10000 chars)
 */
function validateNoteContent(string $content): bool
{
    return strlen($content) <= 10000;
}

/**
 * Validate tag name length
 * 
 * @param string $tagName Tag name
 * @return bool True if tag name is valid (max 50 chars)
 */
function validateTagName(string $tagName): bool
{
    return strlen($tagName) <= 50;
}