SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `usuarios` (
  `Id` INT NOT NULL AUTO_INCREMENT,
  `Nombre` VARCHAR(50) NOT NULL,
  `Anio_nacimiento` YEAR NOT NULL,
  `Sexo` VARCHAR(30) NOT NULL,
  `Provincia` VARCHAR(150) NOT NULL DEFAULT '',
  `Pais` VARCHAR(150) NOT NULL,
  `Usuario` VARCHAR(255) NOT NULL,
  `Email` VARCHAR(255) NOT NULL,
  `Contrasena` VARCHAR(255) NOT NULL,
  `Verificado` TINYINT(1) NOT NULL DEFAULT 0,
  `Token` VARCHAR(64) DEFAULT NULL,
  `Foto_url` VARCHAR(255) DEFAULT NULL,
  `rol` ENUM('JUGADOR', 'MODERADOR', 'ADMIN') NOT NULL DEFAULT 'JUGADOR',
  `bloqueado` TINYINT(1) NOT NULL DEFAULT 0,
  `version_sesion` INT UNSIGNED NOT NULL DEFAULT 0,
  `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uk_usuarios_usuario` (`Usuario`),
  UNIQUE KEY `uk_usuarios_email` (`Email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `verificacion_tokens` (
  `Id` INT NOT NULL AUTO_INCREMENT,
  `UsuarioId` INT NOT NULL,
  `Token` VARCHAR(64) NOT NULL,
  `Expira` DATETIME NOT NULL,
  `Usado` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uk_token` (`Token`),
  KEY `fk_usuario` (`UsuarioId`),
  CONSTRAINT `fk_usuario` FOREIGN KEY (`UsuarioId`) REFERENCES `usuarios` (`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `user_presence` (
  `session_key` CHAR(64) NOT NULL,
  `user_id` INT NOT NULL,
  `last_seen` DATETIME NOT NULL,
  PRIMARY KEY (`session_key`),
  KEY `idx_presence_last_seen` (`last_seen`),
  KEY `idx_presence_user` (`user_id`),
  CONSTRAINT `fk_presence_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `user_session_lock` (
  `user_id` INT NOT NULL,
  `session_key` VARCHAR(64) NOT NULL,
  `last_seen` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  KEY `idx_user_session_lock_seen` (`last_seen`),
  CONSTRAINT `fk_user_session_lock_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `category` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_category_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `questions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `category_id` INT NOT NULL,
  `question` VARCHAR(500) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `difficulty_level` TINYINT NOT NULL DEFAULT 2,
  `points` INT NOT NULL DEFAULT 20,
  PRIMARY KEY (`id`),
  KEY `idx_questions_category` (`category_id`),
  CONSTRAINT `chk_questions_difficulty` CHECK (`difficulty_level` BETWEEN 1 AND 3),
  CONSTRAINT `chk_questions_points` CHECK (`points` >= 0),
  CONSTRAINT `fk_questions_category` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `response` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `question_id` INT NOT NULL,
  `response` VARCHAR(255) NOT NULL,
  `is_correct` BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (`id`),
  KEY `idx_response_question` (`question_id`),
  CONSTRAINT `fk_response_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  CONSTRAINT `fk_question_stats_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `game` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` DATETIME DEFAULT NULL,
  `status` ENUM('EN_CURSO', 'FINALIZADA', 'ABANDONADA') NOT NULL DEFAULT 'EN_CURSO',
  `total_points` INT NOT NULL DEFAULT 0,
  `correct_answers` INT NOT NULL DEFAULT 0,
  `en_curso` TINYINT GENERATED ALWAYS AS (IF(`status` = 'EN_CURSO', 1, NULL)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_game_en_curso` (`user_id`, `en_curso`),
  KEY `idx_game_user` (`user_id`),
  KEY `idx_game_finished_at` (`finished_at`),
  CONSTRAINT `chk_game_total_points` CHECK (`total_points` >= 0),
  CONSTRAINT `chk_game_correct_answers` CHECK (`correct_answers` >= 0),
  CONSTRAINT `fk_game_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `game_answers` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `game_id` INT NOT NULL,
  `question_id` INT NOT NULL,
  `response_id` INT NOT NULL,
  `response_time_seconds` DECIMAL(8,2) DEFAULT NULL,
  `earned_points` INT NOT NULL DEFAULT 0,
  `answered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `turn_number` INT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_game_answers_turn` (`game_id`, `turn_number`),
  KEY `idx_game_answers_game_question` (`game_id`, `question_id`),
  KEY `idx_game_answers_question` (`question_id`),
  KEY `idx_game_answers_response` (`response_id`),
  CONSTRAINT `chk_game_answers_time` CHECK (`response_time_seconds` IS NULL OR `response_time_seconds` >= 0),
  CONSTRAINT `chk_game_answers_points` CHECK (`earned_points` >= 0),
  CONSTRAINT `fk_game_answers_game` FOREIGN KEY (`game_id`) REFERENCES `game` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_game_answers_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `fk_game_answers_response` FOREIGN KEY (`response_id`) REFERENCES `response` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `statistics` (
  `user_id` INT NOT NULL,
  `played_games` INT NOT NULL DEFAULT 0,
  `wins` INT NOT NULL DEFAULT 0,
  `avg_response_time` DECIMAL(8,2) DEFAULT NULL,
  `historical_points` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_statistics_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS question_reports (
  id INT NOT NULL AUTO_INCREMENT COMMENT 'ID DEL REPORTE, SE GENERA SOLO',
  user_id INT NOT NULL COMMENT 'USUARIO QUE REPORTO LA PREGUNTA',
  question_id INT NOT NULL COMMENT 'ID DE LA PREGUNTA REPORTADA',
  game_id INT NOT NULL COMMENT 'PARTIDA EN LA QUE SALIO LA PREGUNTA',
  turn_number INT NOT NULL COMMENT 'NUMERO DE TURNO EN EL QUE SALIO LA PREGUNTA',
  question_snapshot LONGTEXT NOT NULL COMMENT 'COPIA EN JSON DE LA PREGUNTA, OPCIONES, RESPUESTA ELEGIDA, CORRECTA Y PUNTOS AL MOMENTO DE JUGAR',
  reasons TEXT NOT NULL COMMENT 'MOTIVOS DEL REPORTE GUARDADOS EN UNA LISTA JSON',
  comment VARCHAR(2000) NOT NULL DEFAULT '' COMMENT 'COMENTARIO DEL JUGADOR, OBLIGATORIO SI ELIGE OTRO MOTIVO',
  status ENUM('PENDIENTE', 'EN_REVISION', 'RESUELTO', 'DESCARTADO') NOT NULL DEFAULT 'PENDIENTE' COMMENT 'ESTADO DEL REPORTE PARA GESTIONARLO EN EL DASHBOARD',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'FECHA Y HORA EN QUE SE ENVIO EL REPORTE, SE COMPLETA SOLA',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'FECHA Y HORA DEL ULTIMO CAMBIO EN EL REPORTE, SE ACTUALIZA SOLA',
  reviewed_by INT DEFAULT NULL COMMENT 'USUARIO QUE REVISO EL REPORTE, LO COMPLETA EL DASHBOARD',
  reviewed_at DATETIME DEFAULT NULL COMMENT 'FECHA Y HORA DE LA REVISION, LA COMPLETA EL DASHBOARD',
  resolution_notes TEXT DEFAULT NULL COMMENT 'EXPLICACION DE LO QUE SE HIZO CON EL REPORTE, LA COMPLETA EL DASHBOARD',
  PRIMARY KEY (id),
  UNIQUE KEY uk_report_user_question (user_id, question_id),
  KEY idx_report_game (game_id),
  KEY idx_report_status_created (status, created_at),
  CONSTRAINT fk_report_user FOREIGN KEY (user_id) REFERENCES usuarios(Id),
  CONSTRAINT fk_report_game FOREIGN KEY (game_id) REFERENCES game(id),
  CONSTRAINT fk_report_reviewer FOREIGN KEY (reviewed_by) REFERENCES usuarios(Id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Actualizar instalaciones existentes sin duplicar columnas ni cambiar datos.
SET @partida_schema_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'rol'),
    'SELECT 1',
    'ALTER TABLE usuarios ADD COLUMN rol ENUM(''JUGADOR'', ''MODERADOR'', ''ADMIN'') NOT NULL DEFAULT ''JUGADOR'''
);
PREPARE partida_schema_stmt FROM @partida_schema_sql;
EXECUTE partida_schema_stmt;
DEALLOCATE PREPARE partida_schema_stmt;

SET @partida_schema_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'questions' AND column_name = 'is_active'),
    'SELECT 1',
    'ALTER TABLE questions ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1'
);
PREPARE partida_schema_stmt FROM @partida_schema_sql;
EXECUTE partida_schema_stmt;
DEALLOCATE PREPARE partida_schema_stmt;
