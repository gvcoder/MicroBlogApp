-- Create notes table
-- Requirement 15.2: Note_Entity SHALL have fields: note_id, user_id, title, content, created_at, updated_at
-- - Note_Entity SHALL have FULLTEXT index on title and content for search
-- - Note_Entity SHALL have foreign key constraint to users table with ON DELETE CASCADE

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
