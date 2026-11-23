-- Migración tomada de los comentarios de feature/moderador (commit acca90f).
-- Ejecutar en phpMyAdmin si la BD ya existía. No crea usuarios nuevos.

SET @partida_schema_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'questions' AND column_name = 'image_url'),
    'SELECT 1',
    'ALTER TABLE questions ADD COLUMN image_url VARCHAR(255) DEFAULT NULL'
);
PREPARE partida_schema_stmt FROM @partida_schema_sql;
EXECUTE partida_schema_stmt;
DEALLOCATE PREPARE partida_schema_stmt;

CREATE TABLE IF NOT EXISTS `question_suggestions` (
    id BIGINT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    question_text VARCHAR(500) NOT NULL,
    image_url VARCHAR(255) DEFAULT NULL,
    status ENUM('PENDIENTE', 'APROBADA', 'RECHAZADA') NOT NULL DEFAULT 'PENDIENTE',
    reviewed_by INT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    resolution_notes TEXT DEFAULT NULL,
    published_question_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_question_suggestions_published_question (published_question_id),
    KEY idx_question_suggestions_user (user_id),
    KEY idx_question_suggestions_category (category_id),
    KEY idx_question_suggestions_reviewer (reviewed_by),
    KEY idx_question_suggestions_status_created (status, created_at),
    CONSTRAINT fk_question_suggestions_user FOREIGN KEY (user_id) REFERENCES usuarios (Id),
    CONSTRAINT fk_question_suggestions_category FOREIGN KEY (category_id) REFERENCES category (id),
    CONSTRAINT fk_question_suggestions_reviewer FOREIGN KEY (reviewed_by) REFERENCES usuarios (Id) ON DELETE SET NULL,
    CONSTRAINT fk_question_suggestions_published_question FOREIGN KEY (published_question_id) REFERENCES questions (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @partida_schema_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'category' AND column_name = 'color'),
    'SELECT 1',
    'ALTER TABLE category ADD COLUMN color CHAR(7) NOT NULL DEFAULT ''#9A6BF2'''
);
PREPARE partida_schema_stmt FROM @partida_schema_sql;
EXECUTE partida_schema_stmt;
DEALLOCATE PREPARE partida_schema_stmt;

SET @partida_schema_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'category' AND column_name = 'icono'),
    'SELECT 1',
    'ALTER TABLE category ADD COLUMN icono VARCHAR(255) NOT NULL DEFAULT ''personajes/ciencia.svg'''
);
PREPARE partida_schema_stmt FROM @partida_schema_sql;
EXECUTE partida_schema_stmt;
DEALLOCATE PREPARE partida_schema_stmt;

UPDATE category SET color = '#3B9EEA', icono = 'personajes/geografia.svg' WHERE name = 'Geografía' AND icono = 'personajes/ciencia.svg' AND color = '#9A6BF2';
UPDATE category SET color = '#F28C28', icono = 'personajes/historia.svg'  WHERE name = 'Historia'  AND icono = 'personajes/ciencia.svg' AND color = '#9A6BF2';
UPDATE category SET color = '#F2C230', icono = 'personajes/arte.svg'      WHERE name = 'Arte'      AND icono = 'personajes/ciencia.svg' AND color = '#9A6BF2';
UPDATE category SET color = '#7CC243', icono = 'personajes/deporte.svg'   WHERE name = 'Deportes'  AND icono = 'personajes/ciencia.svg' AND color = '#9A6BF2';
UPDATE category SET color = '#E84855', icono = 'personajes/cine.svg'      WHERE name = 'Cine y TV' AND icono = 'personajes/ciencia.svg' AND color = '#9A6BF2';
