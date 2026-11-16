-- Migración propuesta de la tabla anterior al esquema de reportes por resultado.
-- No ejecutar sobre la base real sin probar primero en una copia y validar el respaldo.
-- Compatible con MySQL/MariaDB que soporten procedimientos almacenados e InnoDB.
-- Se puede ejecutar más de una vez; el respaldo inicial se conserva y no se duplica.

CREATE TABLE IF NOT EXISTS question_reports_backup_20261115 LIKE question_reports;
INSERT IGNORE INTO question_reports_backup_20261115
    (id, question_id, user_id, reason, detail, status, created_at)
SELECT id, question_id, user_id, reason, detail, status, created_at
FROM question_reports
WHERE reason IS NOT NULL;

DELIMITER $$

DROP PROCEDURE IF EXISTS migrar_question_reports_resultados $$
CREATE PROCEDURE migrar_question_reports_resultados()
BEGIN
    DECLARE existe INT DEFAULT 0;

    SELECT COUNT(*) INTO existe
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'question_reports'
      AND column_name = 'game_id';
    IF existe = 0 THEN
        ALTER TABLE question_reports ADD COLUMN game_id INT NULL AFTER question_id;
    END IF;

    SELECT COUNT(*) INTO existe
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'question_reports'
      AND column_name = 'turn_number';
    IF existe = 0 THEN
        ALTER TABLE question_reports ADD COLUMN turn_number INT NULL AFTER game_id;
    END IF;

    SELECT COUNT(*) INTO existe
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'question_reports'
      AND column_name = 'question_snapshot';
    IF existe = 0 THEN
        ALTER TABLE question_reports ADD COLUMN question_snapshot LONGTEXT NULL AFTER turn_number;
    END IF;

    SELECT COUNT(*) INTO existe
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'question_reports'
      AND column_name = 'reasons';
    IF existe = 0 THEN
        ALTER TABLE question_reports ADD COLUMN reasons TEXT NULL AFTER question_snapshot;
    END IF;

    SELECT COUNT(*) INTO existe
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'question_reports'
      AND column_name = 'comment';
    IF existe = 0 THEN
        ALTER TABLE question_reports ADD COLUMN comment VARCHAR(2000) NOT NULL DEFAULT '' AFTER reasons;
    END IF;

    SELECT COUNT(*) INTO existe
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'question_reports'
      AND column_name = 'updated_at';
    IF existe = 0 THEN
        ALTER TABLE question_reports
            ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
    END IF;

    SELECT COUNT(*) INTO existe
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'question_reports'
      AND column_name = 'reviewed_by';
    IF existe = 0 THEN
        ALTER TABLE question_reports ADD COLUMN reviewed_by INT NULL AFTER updated_at;
    END IF;

    SELECT COUNT(*) INTO existe
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'question_reports'
      AND column_name = 'reviewed_at';
    IF existe = 0 THEN
        ALTER TABLE question_reports ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by;
    END IF;

    SELECT COUNT(*) INTO existe
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'question_reports'
      AND column_name = 'resolution_notes';
    IF existe = 0 THEN
        ALTER TABLE question_reports ADD COLUMN resolution_notes TEXT NULL AFTER reviewed_at;
    END IF;

    -- Campos nuevos sin equivalencia histórica quedan NULL; los datos que sí
    -- tienen equivalente se copian desde las columnas legadas, que se conservan.
    UPDATE question_reports
    SET reasons = CASE reason
            WHEN 'RESPUESTA_INCORRECTA' THEN JSON_ARRAY('respuesta_incorrecta')
            WHEN 'AMBIGUA' THEN JSON_ARRAY('confusa')
            WHEN 'ERROR_TEXTO' THEN JSON_ARRAY('confusa')
            WHEN 'OTRO' THEN JSON_ARRAY('otro')
            ELSE JSON_ARRAY()
        END,
        comment = COALESCE(detail, '')
    WHERE reasons IS NULL OR reasons = '';

    ALTER TABLE question_reports
        MODIFY COLUMN reason ENUM('RESPUESTA_INCORRECTA', 'AMBIGUA', 'ERROR_TEXTO', 'OTRO') NULL,
        MODIFY COLUMN status ENUM('PENDIENTE', 'EN_REVISION', 'RESUELTO', 'DESCARTADO')
            NOT NULL DEFAULT 'PENDIENTE',
        MODIFY COLUMN reasons TEXT NOT NULL;

    SELECT COUNT(*) INTO existe
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'question_reports'
      AND index_name = 'idx_report_game';
    IF existe = 0 THEN
        ALTER TABLE question_reports ADD KEY idx_report_game (game_id);
    END IF;

    SELECT COUNT(*) INTO existe
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'question_reports'
      AND index_name = 'idx_report_status_created';
    IF existe = 0 THEN
        ALTER TABLE question_reports ADD KEY idx_report_status_created (status, created_at);
    END IF;

    SELECT COUNT(*) INTO existe
    FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'question_reports'
      AND constraint_name = 'fk_question_reports_question'
      AND constraint_type = 'FOREIGN KEY';
    IF existe > 0 THEN
        ALTER TABLE question_reports DROP FOREIGN KEY fk_question_reports_question;
    END IF;

    SELECT COUNT(*) INTO existe
    FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'question_reports'
      AND constraint_name = 'fk_report_game'
      AND constraint_type = 'FOREIGN KEY';
    IF existe = 0 THEN
        ALTER TABLE question_reports
            ADD CONSTRAINT fk_report_game FOREIGN KEY (game_id) REFERENCES game (id);
    END IF;

    SELECT COUNT(*) INTO existe
    FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'question_reports'
      AND constraint_name = 'fk_report_reviewer'
      AND constraint_type = 'FOREIGN KEY';
    IF existe = 0 THEN
        ALTER TABLE question_reports
            ADD CONSTRAINT fk_report_reviewer FOREIGN KEY (reviewed_by) REFERENCES usuarios (Id)
            ON DELETE SET NULL;
    END IF;
END $$

CALL migrar_question_reports_resultados() $$
DROP PROCEDURE migrar_question_reports_resultados $$

DELIMITER ;
