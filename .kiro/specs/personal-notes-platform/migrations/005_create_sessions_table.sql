-- Create sessions table
-- Requirement 15.5: Session_Entity SHALL have fields: session_id, user_id, token, expires_at
-- - Session_Entity SHALL have unique index on token field
-- - Session_Entity SHALL have foreign key constraint to users table with ON DELETE CASCADE

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
