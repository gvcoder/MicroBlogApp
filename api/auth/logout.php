<?php

declare(strict_types=1);

/**
 * User Logout Endpoint
 * 
 * Handles session termination by invalidating the session token.
 * 
 * Requirements: 3.1, 3.2, 3.3
 * 
 * Request Headers:
 * Authorization: Bearer {session_token}
 * 
 * Response (Success):
 * {
 *     "success": true,
 *     "message": "Logout successful"
 * }
 * 
 * Response (Error - Missing Token):
 * {
 *     "error": {
 *         "code": "AUTH_TOKEN_REQUIRED",
 *         "message": "Authentication required",
 *         "details": "Session token is required for logout"
 *     }
 * }
 * 
 * Response (Error - Invalid Token):
 * {
 *     "error": {
 *         "code": "AUTH_INVALID_TOKEN",
 *         "message": "Invalid session",
 *         "details": "The session token is invalid or expired"
 *     }
 * }
 * 
 * Response (Error - Database):
 * {
 *     "error": {
 *         "code": "DATABASE_ERROR",
 *         "message": "Logout failed",
 *         "details": "Unable to terminate session. Please try again later."
 *     }
 * }
 */

use PersonalNotes\Database;

// Set content type
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'error' => [
            'code' => 'API_METHOD_NOT_ALLOWED',
            'message' => 'Method not allowed',
            'details' => 'Only POST requests are allowed'
        ]
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

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
    error_log("Logout failed: Missing authorization token");
    
    echo json_encode([
        'error' => [
            'code' => 'AUTH_TOKEN_REQUIRED',
            'message' => 'Authentication required',
            'details' => 'Session token is required for logout'
        ]
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

try {
    // Validate the session token first
    $userId = Database::validateSession($token);
    
    if ($userId === null) {
        http_response_code(401);
        error_log("Logout failed: Invalid or expired token");
        
        echo json_encode([
            'error' => [
                'code' => 'AUTH_INVALID_TOKEN',
                'message' => 'Invalid session',
                'details' => 'The session token is invalid or expired'
            ]
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
    
    // Delete the session
    $deleted = Database::deleteSession($token);
    
    if (!$deleted) {
        // This shouldn't happen if validateSession returned true, but handle it anyway
        error_log("Logout warning: Session deletion failed for valid token, user ID {$userId}");
    }
    
    // Log successful logout
    error_log("Logout successful: User ID {$userId}");
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Logout successful'
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    
} catch (Exception $e) {
    // Log error and return user-friendly message
    error_log("Logout error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => [
            'code' => 'DATABASE_ERROR',
            'message' => 'Logout failed',
            'details' => 'Unable to terminate session. Please try again later.'
        ]
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}