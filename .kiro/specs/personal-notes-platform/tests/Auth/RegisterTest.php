<?php

declare(strict_types=1);

/**
 * Unit tests for user registration endpoint
 * Tests email validation, password hashing, and duplicate handling
 * 
 * Requirements: 1.1, 1.2, 1.3, 1.4, 1.5
 */

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
final class RegisterTest extends TestCase
{
    private string $apiUrl = 'http://localhost/api/auth/register.php';
    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        // Get database connection from environment or use defaults
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $dbName = $_ENV['DB_NAME'] ?? 'personal_notes_test';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASSWORD'] ?? '';

        try {
            $this->pdo = new PDO(
                "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
            
            // Clean up any test users
            $this->pdo->exec("DELETE FROM users WHERE email LIKE 'test_%@example.com'");
        } catch (PDOException $e) {
            $this->fail("Test database connection failed: " . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        // Clean up test users
        if ($this->pdo) {
            $this->pdo->exec("DELETE FROM users WHERE email LIKE 'test_%@example.com'");
        }
        $this->pdo = null;
    }

    /**
     * Test successful registration with valid credentials
     * Requirement 1.1: WHEN a registration request is submitted with valid credentials, 
     * THE Registration_System SHALL create a new user account
     */
    public function testSuccessfulRegistration(): void
    {
        $email = 'test_user_' . time() . '@example.com';
        $password = 'TestPass123';

        $response = $this->sendRegistrationRequest($email, $password, $password);

        $this->assertEquals(201, $response['status']);
        $this->assertArrayHasKey('success', $response['body']);
        $this->assertTrue($response['body']['success']);
        $this->assertEquals('Registration successful', $response['body']['message']);
        $this->assertArrayHasKey('data', $response['body']);
        $this->assertEquals($email, $response['body']['data']['email']);
        $this->assertArrayHasKey('user_id', $response['body']['data']);

        // Verify user was created in database
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        $this->assertNotNull($user);
        $this->assertEquals($email, $user['email']);
        $this->assertNotEmpty($user['password_hash']);
        $this->assertTrue(password_verify($password, $user['password_hash']));
    }

    /**
     * Test registration with existing email
     * Requirement 1.2: WHEN a registration request is submitted with an existing email, 
     * THE Registration_System SHALL return an error message
     */
    public function testRegistrationWithExistingEmail(): void
    {
        $email = 'test_existing@example.com';
        $password = 'TestPass123';

        // Create user first
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $this->pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
        $stmt->execute([$email, $hashedPassword]);

        // Try to register again
        $response = $this->sendRegistrationRequest($email, $password, $password);

        $this->assertEquals(400, $response['status']);
        $this->assertArrayHasKey('error', $response['body']);
        $this->assertEquals('AUTH_EMAIL_EXISTS', $response['body']['error']['code']);
        $this->assertEquals('Email already registered', $response['body']['error']['message']);
    }

    /**
     * Test registration with invalid email format
     * Requirement 1.3: WHEN a registration request is submitted with invalid email format, 
     * THE Registration_System SHALL return an error message
     */
    public function testRegistrationWithInvalidEmail(): void
    {
        $email = 'invalid-email';
        $password = 'TestPass123';

        $response = $this->sendRegistrationRequest($email, $password, $password);

        $this->assertEquals(400, $response['status']);
        $this->assertArrayHasKey('error', $response['body']);
        $this->assertEquals('VALIDATION_ERROR', $response['body']['error']['code']);
        $this->assertEquals('Invalid email format', $response['body']['error']['message']);
    }

    /**
     * Test registration with missing email
     * Requirement 1.3
     */
    public function testRegistrationWithMissingEmail(): void
    {
        $password = 'TestPass123';

        $response = $this->sendRegistrationRequest('', $password, $password);

        $this->assertEquals(400, $response['status']);
        $this->assertArrayHasKey('error', $response['body']);
        $this->assertEquals('VALIDATION_ERROR', $response['body']['error']['code']);
        $this->assertEquals('Invalid email format', $response['body']['error']['message']);
    }

    /**
     * Test registration with password mismatch
     * Requirement 1.1
     */
    public function testRegistrationWithPasswordMismatch(): void
    {
        $email = 'test_mismatch@example.com';
        $password = 'TestPass123';
        $passwordConfirm = 'TestPass456';

        $response = $this->sendRegistrationRequest($email, $password, $passwordConfirm);

        $this->assertEquals(400, $response['status']);
        $this->assertArrayHasKey('error', $response['body']);
        $this->assertEquals('VALIDATION_ERROR', $response['body']['error']['code']);
        $this->assertEquals('Passwords do not match', $response['body']['error']['message']);
    }

    /**
     * Test registration with weak password (too short)
     * Requirement 1.1
     */
    public function testRegistrationWithWeakPasswordShort(): void
    {
        $email = 'test_weak@example.com';
        $password = 'Short1'; // Only 6 characters

        $response = $this->sendRegistrationRequest($email, $password, $password);

        $this->assertEquals(400, $response['status']);
        $this->assertArrayHasKey('error', $response['body']);
        $this->assertEquals('VALIDATION_ERROR', $response['body']['error']['code']);
        $this->assertEquals('Password does not meet requirements', $response['body']['error']['message']);
    }

    /**
     * Test registration with weak password (no uppercase)
     * Requirement 1.1
     */
    public function testRegistrationWithWeakPasswordNoUppercase(): void
    {
        $email = 'test_weak@example.com';
        $password = 'testpass123'; // No uppercase

        $response = $this->sendRegistrationRequest($email, $password, $password);

        $this->assertEquals(400, $response['status']);
        $this->assertArrayHasKey('error', $response['body']);
        $this->assertEquals('VALIDATION_ERROR', $response['body']['error']['code']);
        $this->assertEquals('Password does not meet requirements', $response['body']['error']['message']);
    }

    /**
     * Test registration with weak password (no lowercase)
     * Requirement 1.1
     */
    public function testRegistrationWithWeakPasswordNoLowercase(): void
    {
        $email = 'test_weak@example.com';
        $password = 'TESTPASS123'; // No lowercase

        $response = $this->sendRegistrationRequest($email, $password, $password);

        $this->assertEquals(400, $response['status']);
        $this->assertArrayHasKey('error', $response['body']);
        $this->assertEquals('VALIDATION_ERROR', $response['body']['error']['code']);
        $this->assertEquals('Password does not meet requirements', $response['body']['error']['message']);
    }

    /**
     * Test registration with weak password (no numbers)
     * Requirement 1.1
     */
    public function testRegistrationWithWeakPasswordNoNumbers(): void
    {
        $email = 'test_weak@example.com';
        $password = 'TestPassword'; // No numbers

        $response = $this->sendRegistrationRequest($email, $password, $password);

        $this->assertEquals(400, $response['status']);
        $this->assertArrayHasKey('error', $response['body']);
        $this->assertEquals('VALIDATION_ERROR', $response['body']['error']['code']);
        $this->assertEquals('Password does not meet requirements', $response['body']['error']['message']);
    }

    /**
     * Test registration with invalid JSON
     * Requirement 1.5
     */
    public function testRegistrationWithInvalidJson(): void
    {
        $response = $this->sendRawRequest('invalid json');

        $this->assertEquals(400, $response['status']);
        $this->assertArrayHasKey('error', $response['body']);
        $this->assertEquals('VALIDATION_ERROR', $response['body']['error']['code']);
        $this->assertEquals('Invalid JSON input', $response['body']['error']['message']);
    }

    /**
     * Test registration with missing fields
     * Requirement 1.5
     */
    public function testRegistrationWithMissingFields(): void
    {
        $response = $this->sendRawRequest('{"email": "test@example.com"}');

        $this->assertEquals(400, $response['status']);
        $this->assertArrayHasKey('error', $response['body']);
        $this->assertEquals('VALIDATION_ERROR', $response['body']['error']['code']);
        $this->assertEquals('Invalid email format', $response['body']['error']['message']);
    }

    /**
     * Test registration with GET request (should fail)
     * Requirement 1.5
     */
    public function testRegistrationWithGetMethod(): void
    {
        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertEquals(405, $httpCode);
    }

    /**
     * Test password hashing with bcrypt cost factor 12
     * Requirement 1.1: Passwords are hashed using bcrypt with cost factor 12
     */
    public function testPasswordHashingCostFactor(): void
    {
        $email = 'test_hashing@example.com';
        $password = 'TestPass123';

        $response = $this->sendRegistrationRequest($email, $password, $password);

        $this->assertEquals(201, $response['status']);

        // Verify password was hashed with bcrypt cost factor 12
        $stmt = $this->pdo->prepare('SELECT password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        $this->assertNotNull($user);
        $cost = password_get_info($user['password_hash'])['cost'];
        $this->assertEquals(12, $cost, "Bcrypt cost factor should be 12");
    }

    /**
     * Test that password is not stored in plain text
     * Requirement 1.1
     */
    public function testPasswordNotStoredInPlainText(): void
    {
        $email = 'test_plaintext@example.com';
        $password = 'TestPass123';

        $response = $this->sendRegistrationRequest($email, $password, $password);

        $this->assertEquals(201, $response['status']);

        // Verify password is not stored in plain text
        $stmt = $this->pdo->prepare('SELECT password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        $this->assertNotNull($user);
        $this->assertNotEquals($password, $user['password_hash']);
        $this->assertStringStartsWith('$2y$', $user['password_hash']);
    }

    /**
     * Test registration with special characters in email
     * Requirement 1.1
     */
    public function testRegistrationWithSpecialCharsInEmail(): void
    {
        $email = 'test.user+tag@example.com';
        $password = 'TestPass123';

        $response = $this->sendRegistrationRequest($email, $password, $password);

        $this->assertEquals(201, $response['status']);
        $this->assertEquals($email, $response['body']['data']['email']);
    }

    /**
     * Test registration with long email
     * Requirement 1.1
     */
    public function testRegistrationWithLongEmail(): void
    {
        $email = str_repeat('a', 240) . '@example.com';
        $password = 'TestPass123';

        $response = $this->sendRegistrationRequest($email, $password, $password);

        $this->assertEquals(201, $response['status']);
        $this->assertEquals($email, $response['body']['data']['email']);
    }

    /**
     * Test registration with long password
     * Requirement 1.1
     */
    public function testRegistrationWithLongPassword(): void
    {
        $email = 'test_long@example.com';
        $password = str_repeat('a', 100) . 'A1'; // 100 chars + uppercase + number

        $response = $this->sendRegistrationRequest($email, $password, $password);

        $this->assertEquals(201, $response['status']);
    }

    /**
     * Test registration with unicode characters in password
     * Requirement 1.1
     */
    public function testRegistrationWithUnicodePassword(): void
    {
        $email = 'test_unicode@example.com';
        $password = 'ТестПароль123'; // Russian characters

        $response = $this->sendRegistrationRequest($email, $password, $password);

        $this->assertEquals(201, $response['status']);
    }

    /**
     * Send registration request and return response
     */
    private function sendRegistrationRequest(string $email, string $password, string $passwordConfirm): array
    {
        $data = [
            'email' => $email,
            'password' => $password,
            'password_confirm' => $passwordConfirm
        ];

        return $this->sendRawRequest(json_encode($data));
    }

    /**
     * Send raw request and return response
     */
    private function sendRawRequest(string $body): array
    {
        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($body)
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $body = json_decode($response, true);
        if ($body === null) {
            $body = ['raw' => $response];
        }

        return [
            'status' => $httpCode,
            'body' => $body
        ];
    }
}
