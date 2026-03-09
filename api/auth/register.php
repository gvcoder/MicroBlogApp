<?php

declare(strict_types=1);

/**
 * User Registration Endpoint
 * 
 * Handles user registration with email validation, password hashing,
 * and database insertion using prepared statements.
 * 
 * Requirements: 1.1, 1.2, 1.3, 1.4, 1.5
 * 
 * Request Body (JSON):
 * {
 *     "email": "user@example.com",
 *     "password": "secure_password",
 *     "password_confirm": "secure_password"
 * }
 * 
 * Response (Success):
 * {
 *     "success": true,
 *     "message": "Registration successful",
 *     "data": {
 *         "user_id": 1,
 *         "email": "user@example.com"
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
 * Response (Error - Password Mismatch):
 * {
 *     "error": {
 *         "code": "VALIDATION_ERROR",
 *         "message": "Passwords do not match",
 *         "details": "The password and password confirmation do not match"
 *     }
 * }
 * 
 * Response (Error - Weak Password):
 * {
 *     "error": {
 *         "code": "VALIDATION_ERROR",
 *         "message": "Password does not meet requirements",
 *         "details": "Password must be at least 8 characters and contain uppercase, lowercase, and numbers"
 *     }
 * }
 * 
 * Response (Error - Duplicate Email):
 * {
 *     "error": {
 *         "code": "AUTH_EMAIL_EXISTS",
 *         "message": "Email already registered",
 *         "details": "An account with this email address already exists"
 *     }
 * }
 * 
 * Response (Error - Database):
 * {
 *     "error": {
 *         "code": "DATABASE_ERROR",
 *         "message": "Registration failed",
 *         "details": "Unable to create user account"
 *     }
 * }
 */

use PersonalNotes\Database;
use PDO;

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
$passwordConfirm = $input['password_confirm'] ?? '';

// Validate email format (Requirement 1.3)
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    error_log("Registration failed: Invalid email format - {$email}");
    
    echo json_encode([
        'error' => [
            'code' => 'VALIDATION_ERROR',
            'message' => 'Invalid email format',
            'details' => 'The email address provided is not valid'
        ]
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

// Validate password and password confirmation match (Requirement 1.1)
if ($password !== $passwordConfirm) {
    http_response_code(400);
    error_log("Registration failed: Password mismatch for email - {$email}");
    
    echo json_encode([
        'error' => [
            'code' => 'VALIDATION_ERROR',
            'message' => 'Passwords do not match',
            'details' => 'The password and password confirmation do not match'
        ]
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

// Validate password strength (Requirement 1.1)
if (strlen($password) < 8) {
    http_response_code(400);
    error_log("Registration failed: Password too short for email - {$email}");
    
    echo json_encode([
        'error' => [
            'code' => 'VALIDATION_ERROR',
            'message' => 'Password does not meet requirements',
            'details' => 'Password must be at least 8 characters long'
        ]
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

// Check for uppercase, lowercase, and numbers in password
if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
    http_response_code(400);
    error_log("Registration failed: Password does not meet complexity requirements for email - {$email}");
    
    echo json_encode([
        'error' => [
            'code' => 'VALIDATION_ERROR',
            'message' => 'Password does not meet requirements',
            'details' => 'Password must contain uppercase letters, lowercase letters, and numbers'
        ]
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

try {
    // Get database connection
    $pdo = Database::getConnection();
    
    // Check if email already exists (Requirement 1.2)
    $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        http_response_code(400);
        error_log("Registration failed: Email already exists - {$email}");
        
        echo json_encode([
            'error' => [
                'code' => 'AUTH_EMAIL_EXISTS',
                'message' => 'Email already registered',
                'details' => 'An account with this email address already exists'
            ]
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
    
    // Hash password using bcrypt with cost factor 12 (Requirement 1.1)
    $hashedPassword = Database::hashPassword($password);
    
    // Insert new user using prepared statement (Requirement 1.1)
    $stmt = $pdo->prepare(
        'INSERT INTO users (email, password_hash) VALUES (?, ?)'
    );
    $stmt->execute([$email, $hashedPassword]);
    
    // Get the new user ID
    $userId = Database::getLastInsertId($pdo);
    
    // Log successful registration
    error_log("Registration successful: User ID {$userId} - {$email}");
    
    // Return success response
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful',
        'data' => [
            'user_id' => (int)$userId,
            'email' => $email
        ]
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    
} catch (Exception $e) {
    // Log error and return user-friendly message (Requirement 1.5)
    error_log("Registration error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => [
            'code' => 'DATABASE_ERROR',
            'message' => 'Registration failed',
            'details' => 'Unable to create user account. Please try again later.'
        ]
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}
