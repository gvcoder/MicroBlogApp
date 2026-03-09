# Project Structure

## Directory Organization

```
.
├── .git/                    # Git repository
├── .kiro/                   # Kiro configuration and specs
│   ├── settings/           # Kiro settings
│   ├── specs/              # Feature specifications
│   │   └── personal-notes-platform/
│   │       ├── migrations/ # Database migration files
│   │       ├── tests/      # Test files
│   │       ├── .config.kiro # Spec configuration
│   │       ├── design.md   # Technical design document
│   │       ├── phpunit.xml # PHPUnit configuration
│   │       ├── requirements.md # Requirements document
│   │       └── tasks.md    # Implementation tasks
│   └── steering/           # Steering documents (this directory)
├── api/                    # Backend API implementation
│   ├── auth/              # Authentication endpoints
│   │   └── register.php   # User registration endpoint
│   └── Database.php       # Database connection utility
├── LICENSE                 # Project license
├── mysql_bkp.sql          # Database backup/schema
└── README.md              # Project overview
```

## API Structure

### Authentication Endpoints (`api/auth/`)
- **register.php**: User registration with email validation and password hashing
- **login.php**: User authentication (to be implemented)
- **logout.php**: Session termination (to be implemented)

### Note Management Endpoints (to be implemented)
- **notes.php**: CRUD operations for notes
- **search.php**: Keyword search functionality
- **tags.php**: Tag management operations

### Core Classes
- **Database.php**: Database connection utility with connection pooling
  - Namespace: `PersonalNotes`
  - Implements PDO-based database access
  - Provides password hashing with bcrypt
  - Handles transactions and prepared statements

## Database Schema

### Current Tables (from mysql_bkp.sql)
- **Persons**: Demo table with basic person information
  - PersonID (int)
  - LastName (varchar(255))
  - FirstName (varchar(255))
  - Address (varchar(255))
  - City (varchar(255))

### Required Tables (from requirements)
- **users**: User accounts
  - user_id (int, primary key)
  - email (varchar(255), unique)
  - password_hash (varchar(255))
  - created_at (timestamp)
- **notes**: User notes
  - note_id (int, primary key)
  - user_id (int, foreign key to users)
  - title (varchar(200))
  - content (text)
  - created_at (timestamp)
  - updated_at (timestamp)
- **tags**: Note tags
  - tag_id (int, primary key)
  - name (varchar(50), unique)
- **note_tags**: Note-tag relationships
  - note_id (int, foreign key to notes)
  - tag_id (int, foreign key to tags)
- **sessions**: User sessions
  - session_id (int, primary key)
  - user_id (int, foreign key to users)
  - token (varchar(255), unique)
  - expires_at (timestamp)

## File Naming Conventions

### PHP Files
- Use `.php` extension
- File names should be lowercase with hyphens for multi-word names
- Endpoint files should describe their function (e.g., `register.php`, `notes.php`)
- Class files should match class name (e.g., `Database.php` for `Database` class)

### Database Files
- Use `.sql` extension
- Migration files should be timestamped (e.g., `20240101_create_users_table.sql`)
- Backup files should indicate date/version (e.g., `mysql_bkp.sql`)

### Documentation Files
- Use `.md` extension for markdown files
- Use descriptive names (e.g., `requirements.md`, `design.md`)

## Code Organization Principles

### Separation of Concerns
- **API Endpoints**: Handle HTTP requests/responses, input validation
- **Business Logic**: Implement core functionality in classes
- **Data Access**: Database operations in dedicated classes
- **Configuration**: Environment-specific settings

### File Structure Rules
1. Each API endpoint should be in its own file
2. Shared utility classes should be in the root `api/` directory
3. Related endpoints should be grouped in subdirectories (e.g., `auth/`)
4. Database migrations should be versioned and stored in `migrations/` directory
5. Tests should mirror the source structure in `tests/` directory

### Import/Require Guidelines
- Use `require_once` for class files
- Use relative paths from the document root
- Avoid circular dependencies
- Group related imports together

## Development Workflow

### Adding New Features
1. Update requirements in `requirements.md`
2. Update design in `design.md` if needed
3. Add tasks to `tasks.md`
4. Create database migrations if schema changes are needed
5. Implement API endpoints
6. Write tests
7. Update documentation

### Database Changes
1. Create migration file in `migrations/` directory
2. Update schema documentation
3. Update `Database.php` if new utility methods are needed
4. Test migration with sample data

### API Development
1. Follow RESTful conventions
2. Implement proper error handling
3. Add input validation
4. Include comprehensive logging
5. Follow security best practices

## Testing Structure

### Test Organization
- Unit tests for individual classes/methods
- Integration tests for API endpoints
- Database tests for data operations
- Property-based tests for correctness properties

### Test File Location
- Tests should be in `.kiro/specs/personal-notes-platform/tests/`
- Test files should mirror source structure
- Use descriptive test names that indicate what is being tested

## Configuration Management

### Environment Configuration
- Database credentials should be configurable
- API keys and secrets should be environment variables
- Logging levels should be configurable
- Feature flags should be supported

### Version Control
- Keep configuration files out of version control when they contain secrets
- Use `.gitignore` for sensitive files
- Document required environment variables