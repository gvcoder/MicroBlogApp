<?php

declare(strict_types=1);

/**
 * Session validation endpoint
 *
 * GET /api/auth/session.php
 * Returns the current user's email if the session token is valid.
 * Used by the frontend on page load to restore an active session.
 *
 * Requirements: 18.1, 18.2, 18.3
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../Database.php';

use PersonalNotes\Database;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'Only GET allowed']]);
    exit;
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!preg_match('/Bearer\s+(.+)$/i', $authHeader, $m)) {
    http_response_code(401);
    echo json_encode(['error' => ['code' => 'AUTH_TOKEN_REQUIRED', 'message' => 'No token']]);
    exit;
}

$token = trim($m[1]);

try {
    $userId = Database::validateSession($token);
    if ($userId === null) {
        http_response_code(401);
        echo json_encode(['error' => ['code' => 'AUTH_SESSION_EXPIRED', 'message' => 'Session expired']]);
        exit;
    }

    $pdo  = Database::getConnection();
    $stmt = $pdo->prepare('SELECT email FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'data'    => ['user_id' => $userId, 'email' => $user['email'] ?? '']
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => ['code' => 'SERVER_ERROR', 'message' => 'Session check failed']]);
}
