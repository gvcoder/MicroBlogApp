# Database Migrations

This directory contains SQL migration files for the Personal Notes Platform database schema.

## Running Migrations

To run all migrations:

```bash
mysql -u [username] -p [database_name] < 001_create_users_table.sql
mysql -u [username] -p [database_name] < 002_create_notes_table.sql
mysql -u [username] -p [database_name] < 003_create_tags_table.sql
mysql -u [username] -p [database_name] < 004_create_note_tags_table.sql
mysql -u [username] -p [database_name] < 005_create_sessions_table.sql
```

## Running Tests

To run the migration tests:

```bash
# Set up environment variables
export DB_HOST=localhost
export DB_PORT=3306
export DB_NAME=personal_notes
export DB_USER=root
export DB_PASSWORD=your_password

# Run PHPUnit tests
phpunit tests/migrations/001_create_users_table.test.php
```

## Requirements

- MySQL 5.7+ or MySQL 8.0+
- PHP 7.4+ with PDO MySQL extension
- PHPUnit for testing

## Table Schema

### Users Table (001_create_users_table.sql)

Satisfies Requirement 15.1: User_Entity SHALL have fields: user_id, email, password_hash, created_at

```sql
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Notes Table (002_create_notes_table.sql)

Satisfies Requirement 15.2: Note_Entity SHALL have fields: note_id, user_id, title, content, created_at, updated_at

```sql
CREATE TABLE notes (
    note_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    FULLTEXT INDEX idx_title_content (title, content),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Tags Table (003_create_tags_table.sql)

Satisfies Requirement 15.3: Tag_Entity SHALL have fields: tag_id, name

```sql
CREATE TABLE tags (
    tag_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
### Tags Table (003_create_tags_table.sql)

Satisfies Requirement 15.3: Tag_Entity SHALL have fields: tag_id, name

```sql
CREATE TABLE tags (
    tag_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Note_Tags Table (004_create_note_tags_table.sql)

Satisfies Requirement 15.4: Note_Tag_Entity SHALL have fields: note_id, tag_id

```sql
CREATE TABLE note_tags (
    note_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (note_id, tag_id),
    FOREIGN KEY (note_id) REFERENCES notes(note_id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(tag_id) ON DELETE CASCADE,
    INDEX idx_note_id (note_id),
    INDEX idx_tag_id (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Sessions Table (005_create_sessions_table.sql)

Satisfies Requirement 15.5: Session_Entity SHALL have fields: session_id, user_id, token, expires_at

```sql
CREATE TABLE sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_token (token),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
