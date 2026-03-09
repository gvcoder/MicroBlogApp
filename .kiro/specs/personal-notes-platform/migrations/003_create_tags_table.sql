-- Create tags table
-- Requirement 15.3: Tag_Entity SHALL have fields: tag_id, name
-- - Tag_Entity SHALL have unique index on name field

CREATE TABLE tags (
    tag_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
