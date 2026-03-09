-- Create note_tags junction table
-- Requirement 15.4: Note_Tag_Entity SHALL have fields: note_id, tag_id
-- - Note_Tag_Entity SHALL have composite primary key (note_id, tag_id)
-- - Note_Tag_Entity SHALL have foreign key constraint to notes table with ON DELETE CASCADE
-- - Note_Tag_Entity SHALL have foreign key constraint to tags table with ON DELETE CASCADE

CREATE TABLE note_tags (
    note_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (note_id, tag_id),
    FOREIGN KEY (note_id) REFERENCES notes(note_id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(tag_id) ON DELETE CASCADE,
    INDEX idx_note_id (note_id),
    INDEX idx_tag_id (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
