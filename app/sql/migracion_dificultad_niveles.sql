-- ============================================================
-- Migración: modo adaptativo (dificultad de preguntas + nivel de jugador)
--
-- Qué hace:
--   1. Agrega usuarios.Provincia si falta (lo usa Perfil/Registro).
--   2. Agrega game_answers.turn_number si falta (lo usa PartidaModel)
--      y quita el UNIQUE (game_id, question_id) para poder repetir
--      preguntas cuando ya salieron todas en la misma partida.
--   3. Crea question_stats: entregas / respuestas / aciertos por pregunta.
--   4. Deja todas las preguntas en nivel MEDIO (20 puntos) para que
--      empiecen a calibrarse con las primeras 10 entregas.
--   5. Garantiza UNA sola partida EN_CURSO por usuario.
--
-- Se puede correr más de una vez: cada paso chequea si ya existe.
-- Antes de importar, seleccioná la base del proyecto (preguntados).
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- 1. usuarios.Provincia
-- ------------------------------------------------------------
SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `usuarios` ADD COLUMN `Provincia` VARCHAR(150) NOT NULL DEFAULT '''' AFTER `Sexo`',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'Provincia'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 2. game_answers.turn_number + índice único
-- ------------------------------------------------------------
SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `game_answers` ADD COLUMN `turn_number` INT DEFAULT NULL AFTER `earned_points`',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'game_answers'
      AND COLUMN_NAME = 'turn_number'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `game_answers` ADD UNIQUE KEY `uk_game_answers_turn` (`game_id`, `turn_number`)',
        'SELECT 1'
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'game_answers'
      AND INDEX_NAME = 'uk_game_answers_turn'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 2b. Permitir repetir preguntas dentro de la misma partida
--     Se quita el UNIQUE (game_id, question_id) y se deja un índice
--     común. El control de reenvíos queda en uk_game_answers_turn.
-- ------------------------------------------------------------
SET @sql = (
    SELECT IF(
        COUNT(*) > 0,
        'ALTER TABLE `game_answers` DROP INDEX `uk_game_answers_question`',
        'SELECT 1'
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'game_answers'
      AND INDEX_NAME = 'uk_game_answers_question'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `game_answers` ADD KEY `idx_game_answers_game_question` (`game_id`, `question_id`)',
        'SELECT 1'
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'game_answers'
      AND INDEX_NAME = 'idx_game_answers_game_question'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 3. question_stats
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `question_stats` (
    `question_id` INT NOT NULL,
    `times_delivered` INT NOT NULL DEFAULT 0,
    `times_answered` INT NOT NULL DEFAULT 0,
    `times_correct` INT NOT NULL DEFAULT 0,
    `difficulty_level` TINYINT NOT NULL DEFAULT 2,
    `points` INT NOT NULL DEFAULT 20,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`question_id`),
    KEY `idx_question_stats_difficulty` (`difficulty_level`),
    CONSTRAINT `fk_question_stats_question`
        FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Una fila por pregunta. No se backfillea el historial viejo a propósito:
-- todas las preguntas arrancan en 0 entregas (nivel medio) y se calibran
-- jugando, tal como se pidió.
INSERT INTO `question_stats`
    (`question_id`, `times_delivered`, `times_answered`, `times_correct`, `difficulty_level`, `points`)
SELECT `id`, 0, 0, 0, 2, 20
FROM `questions`
ON DUPLICATE KEY UPDATE `question_id` = `question_id`;

-- ------------------------------------------------------------
-- 4. Todas las preguntas arrancan en MEDIO (20 puntos)
--    Las que ya tienen historial de juego no se tocan.
-- ------------------------------------------------------------
UPDATE `questions` q
INNER JOIN `question_stats` qs ON qs.question_id = q.id
SET q.`difficulty_level` = 2,
    q.`points` = 20
WHERE qs.`times_delivered` = 0
  AND qs.`times_answered` = 0;

-- ------------------------------------------------------------
-- 5. Una sola partida EN_CURSO por usuario
--    Primero se cierran los duplicados históricos (queda la más nueva).
-- ------------------------------------------------------------
UPDATE `game` g
INNER JOIN (
    SELECT `id`
    FROM (
        SELECT `id`,
               ROW_NUMBER() OVER (PARTITION BY `user_id` ORDER BY `id` DESC) AS rn
        FROM `game`
        WHERE `status` = 'EN_CURSO'
    ) ranked
    WHERE ranked.rn > 1
) dup ON dup.id = g.id
SET g.`status` = 'ABANDONADA',
    g.`finished_at` = NOW();

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `game` ADD COLUMN `en_curso` TINYINT GENERATED ALWAYS AS (IF(`status` = ''EN_CURSO'', 1, NULL)) STORED',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'game'
      AND COLUMN_NAME = 'en_curso'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `game` ADD UNIQUE KEY `uk_game_en_curso` (`user_id`, `en_curso`)',
        'SELECT 1'
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'game'
      AND INDEX_NAME = 'uk_game_en_curso'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 6. Una sola sesion por cuenta (normal + incognito/dispositivo)
--    Las pestañas del mismo navegador comparten cookie; una ventana de
--    incognito u otro dispositivo abre otra sesion PHP. Esta tabla guarda
--    que sesion esta jugando y cuando se la vio por ultima vez.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_session_lock` (
  `user_id` INT NOT NULL,
  `session_key` VARCHAR(64) NOT NULL,
  `last_seen` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  KEY `idx_user_session_lock_seen` (`last_seen`),
  CONSTRAINT `fk_user_session_lock_user` FOREIGN KEY (`user_id`)
    REFERENCES `usuarios` (`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Verificación
-- ------------------------------------------------------------
SELECT 'Provincia' AS chequeo, COUNT(*) AS ok
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'Provincia'
UNION ALL
SELECT 'turn_number', COUNT(*)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_answers' AND COLUMN_NAME = 'turn_number'
UNION ALL
SELECT 'question_stats', COUNT(*)
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'question_stats'
UNION ALL
SELECT 'uk_game_en_curso', COUNT(*)
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game' AND INDEX_NAME = 'uk_game_en_curso'
UNION ALL
SELECT 'uk_game_answers_question (debe ser 0)', COUNT(*)
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_answers' AND INDEX_NAME = 'uk_game_answers_question'
UNION ALL
SELECT 'idx_game_answers_game_question', COUNT(*)
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_answers' AND INDEX_NAME = 'idx_game_answers_game_question';

SELECT 'user_session_lock' AS chequeo, COUNT(*) AS ok
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_session_lock';

SELECT `difficulty_level`, COUNT(*) AS preguntas
FROM `questions`
GROUP BY `difficulty_level`;
