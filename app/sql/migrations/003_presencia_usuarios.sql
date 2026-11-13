CREATE TABLE IF NOT EXISTS user_presence (
    session_key CHAR(64) NOT NULL PRIMARY KEY,
    user_id INT NOT NULL,
    last_seen DATETIME NOT NULL,
    KEY idx_presence_last_seen (last_seen),
    KEY idx_presence_user (user_id),
    CONSTRAINT fk_presence_user FOREIGN KEY (user_id) REFERENCES usuarios (Id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
