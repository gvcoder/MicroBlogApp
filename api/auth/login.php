<?php

declare(strict_types=1);

/**
 * User Login Endpoint
 * 
 * Handles user authentication with credential verification and session creation.
 * 
 * Requirements: 2.1, 2.2, 2.3, 2.4, 2.5
 * 
 * Request Body (JSON):
 * {
 *     "email": "user@example.com",
 *     "password": "secure_password"
 * }
 * 
 * Response (Success):
 * {
 *     "success": true,
 *     "message": "Login successful",
 *     "data": {
 *         "user_id": 1,
 *         "email": "user@example.com",
 *         "session_token": "generated_token_here",
 *         "expires_at": "2026-03-10 14:30:00"
 *     }
 * }
 * 
 * Response (Error - Invalid Email):
 * {
 *     "error": {
 *         "code": "VALIDATION_ERROR",
 *         "message": "Invalid email format",
 *         "details": "The email address provided is not valid"
 *     }
 * }
 * 
 * Response (Error - Invalid Credentials):
 * {
 *     "error": {
 *         "code": "AUTH_INVALID_CREDENTIALS",
 *         "message": "Invalid credentials",
 *         "details": "The email or password is incorrect"
 *     }
 * }
 * 
 * Response (Error - Database):
 * {
 *     "error": {
 *         "code": "DATABASE_ERROR",
 *         "message": "Login failed",
 *         "details": "Unable to authenticate user. Please try again later."
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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate JSON input
if ($input === null) {
    http_response_code(400);
    echo json_encode([
        'error' => [
            'code' => 'VALIDATION_ERROR',
            'message' => 'Invalid JSON input',
            'details' => 'Request body must be valid JSON'
        ]
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

// Extract and validate required fields
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    error_log("Login failed: Invalid email format - {$email}");
    
    echo json_encode([
        'error' => [
            'code' => 'VALIDATION_ERROR',
            'message' => 'Invalid email format',
            'details' => 'The email address provided is not valid'
        ]
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

// Validate password is not empty
if (empty($password)) {
    http_response_code(400);
    error_log("Login failed: Empty password for email - {$email}");
    
    echo json_encode([
        'error' => [
            'code' => 'VALIDATION_ERROR',
            'message' => 'Password required',
            'details' => 'Password field cannot be empty'
        ]
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

try {
    // Get database connection
    $pdo = Database::getConnection();
    
    // Get user by email
    $stmt = $pdo->prepare('SELECT user_id, email, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // User not found - but we return generic error for security
        http_response_code(401);
        error_log("Login failed: User not found - {$email}");
        
        echo json_encode([
            'error' => [
                'code' => 'AUTH_INVALID_CREDENTIALS',
                'message' => 'Invalid credentials',
                'details' => 'The email or password is incorrect'
            ]
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
    
    // Verify password
    if (!Database::verifyPassword($password, $user['password_hash'])) {
        http_response_code(401);
        error_log("Login failed: Invalid password for user ID {$user['user_id']} - {$email}");
        
        echo json_encode([
            'error' => [
                'code' => 'AUTH_INVALID_CREDENTIALS',
                'message' => 'Invalid credentials',
                'details' => 'The email or password is incorrect'
            ]
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
    
    // Check if password needs rehashing (security best practice)
    if (Database::needsRehash($user['password_hash'])) {
        $newHash = Database::hashPassword($password);
        $updateStmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
        $updateStmt->execute([$newHash, $user['user_id']]);
        error_log("Password rehashed for user ID {$user['user_id']}");
    }
    
    // Generate session token
    $token = bin2hex(random_bytes(32)); // 64-character hex token
    
    // Set expiration time (24 hours from now)
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Create session in database using Database class method
    $sessionCreated = Database::createSession((int)$user['user_id'], $token, $expiresAt);
    
    if (!$sessionCreated) {
        throw new Exception('Failed to create session');
    }
    
    // Log successful login
    error_log("Login successful: User ID {$user['user_id']} - {$email}");
    
    // Return success response with session token
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            'user_id' => (int)$user['user_id'],
            'email' => $user['email'],
            'session_token' => $token,
            'expires_at' => $expiresAt
        ]
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    
} catch (Exception $e) {
    // Log error and return user-friendly message
    error_log("Login error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => [
            'code' => 'DATABASE_ERROR',
            'message' => 'Login failed',
            'details' => 'Unable to authenticate user. Please try again later.'
        ]
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}