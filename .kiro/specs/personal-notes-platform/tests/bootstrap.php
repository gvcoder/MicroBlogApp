<?php

declare(strict_types=1);

/**
 * Test bootstrap file for Personal Notes Platform
 * Sets up autoloading and environment configuration
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define base path
define('BASE_PATH', __DIR__ . '/../../..');

// Define API path
define('API_PATH', BASE_PATH . '/api');

// Define tests path
define('TESTS_PATH', __DIR__);

// Load Database class
require_once API_PATH . '/Database.php';

// Set default environment variables if not set
$_ENV['DB_HOST'] = $_ENV['DB_HOST'] ?? 'localhost';
$_ENV['DB_PORT'] = $_ENV['DB_PORT'] ?? '3306';
$_ENV['DB_NAME'] = $_ENV['DB_NAME'] ?? 'personal_notes';
$_ENV['DB_USER'] = $_ENV['DB_USER'] ?? 'root';
$_ENV['DB_PASSWORD'] = $_ENV['DB_PASSWORD'] ?? '';
