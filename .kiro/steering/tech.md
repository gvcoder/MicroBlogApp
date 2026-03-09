# Technology Stack

## Backend
- **Language**: PHP 8.0+
- **Database**: MySQL 5.7+
- **Web Server**: Apache/Nginx
- **API**: RESTful JSON API

## Key Libraries & Patterns
- **PDO**: Database access with prepared statements
- **Connection Pooling**: Database connection reuse pattern
- **bcrypt**: Password hashing with cost factor 12
- **JSON**: All API responses use JSON format
- **Strict Types**: PHP strict typing enabled (`declare(strict_types=1)`)

## Database Configuration
- **Host**: localhost
- **Port**: 3306
- **Database**: personal_notes
- **Charset**: utf8mb4
- **Collation**: utf8mb4_unicode_ci
- **PDO Options**:
  - Error mode: Exception
  - Fetch mode: Associative array
  - Emulated prepares: Disabled
  - Persistent connections: Disabled

## Code Conventions

### PHP Standards
- Use `declare(strict_types=1)` in all PHP files
- Namespace: `PersonalNotes` for core classes
- Class naming: PascalCase (e.g., `Database`, `NoteManager`)
- Method naming: camelCase (e.g., `getConnection`, `hashPassword`)
- Constant naming: UPPER_SNAKE_CASE
- Property visibility: Always declare (private, protected, public)

### Database Layer
- Use prepared statements for all queries
- Implement connection pooling pattern
- Handle transactions properly (begin/commit/rollback)
- Use bcrypt for password hashing with cost factor 12
- Validate all inputs before database operations

### API Design
- JSON request/response format
- Consistent error response structure:
  ```json
  {
    "error": {
      "code": "ERROR_CODE",
      "message": "User-friendly message",
      "details": "Technical details"
    }
  }
  ```
- Success response structure:
  ```json
  {
    "success": true,
    "message": "Operation message",
    "data": { /* response data */ }
  }
  ```
- HTTP status codes:
  - 200: Success
  - 201: Created
  - 400: Validation error
  - 401: Unauthorized
  - 403: Forbidden
  - 404: Not found
  - 405: Method not allowed
  - 500: Server error

### Security Requirements
- Validate email format with `filter_var($email, FILTER_VALIDATE_EMAIL)`
- Password requirements: min 8 chars, uppercase, lowercase, numbers
- Hash passwords with bcrypt (cost factor 12)
- Use session tokens for authentication
- Check user ownership for all note operations
- Log security-relevant events with `error_log()`

## Common Commands

### Database Operations
```bash
# MySQL connection
mysql -u root -p personal_notes

# Import database backup
mysql -u root -p personal_notes < mysql_bkp.sql
```

### PHP Development
```bash
# Start PHP development server (if needed)
php -S localhost:8000 -t api/

# Check PHP syntax
php -l api/auth/register.php
```

### Testing
- Test files are located in `.kiro/specs/personal-notes-platform/tests/`
- Use PHPUnit for testing (config in `.kiro/specs/personal-notes-platform/phpunit.xml`)

## Project Structure
- API endpoints in `api/` directory
- Database class in `api/Database.php`
- Authentication endpoints in `api/auth/`
- Database backup in `mysql_bkp.sql`

## Development Notes
- The project follows requirements-first workflow (see spec config)
- All requirements are documented in `.kiro/specs/personal-notes-platform/requirements.md`
- Design decisions are in `.kiro/specs/personal-notes-platform/design.md`
- Implementation tasks are in `.kiro/specs/personal-notes-platform/tasks.md`
- Database migrations are in `.kiro/specs/personal-notes-platform/migrations/`