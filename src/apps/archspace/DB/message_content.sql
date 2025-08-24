-- Table to store actual message content for diplomatic messages
DROP TABLE IF EXISTS diplomatic_message_content;

CREATE TABLE diplomatic_message_content (
    message_id INT UNSIGNED NOT NULL,
    content TEXT,
    PRIMARY KEY(message_id),
    FOREIGN KEY (message_id) REFERENCES diplomatic_message(id) ON DELETE CASCADE
);