USE preguntados;
SET NAMES utf8mb4;

ALTER TABLE usuarios
    ADD COLUMN rol ENUM('JUGADOR', 'MODERADOR') NOT NULL DEFAULT 'JUGADOR';

ALTER TABLE question_reports
    ADD COLUMN resolved_by INT DEFAULT NULL,
    ADD COLUMN resolved_at DATETIME DEFAULT NULL,
    ADD COLUMN resolution_note VARCHAR(500) DEFAULT NULL,
    ADD KEY idx_question_reports_resolved_by (resolved_by),
    ADD CONSTRAINT fk_question_reports_resolved_by
        FOREIGN KEY (resolved_by) REFERENCES usuarios (Id) ON DELETE SET NULL;

-- Promové manualmente una cuenta reemplazando el usuario:
-- UPDATE usuarios SET rol = 'MODERADOR' WHERE Usuario = 'nombre_de_usuario';
