-- ============================================================
-- Migracion: una sola sesion por cuenta
--
-- Que hace:
--   Crea user_session_lock, la tabla donde se guarda que sesion PHP
--   esta jugando y cuando se la vio por ultima vez. Sirve para bloquear
--   el ingreso a jugar desde una ventana de incognito u otro dispositivo
--   mientras la cuenta ya esta en partida en otra sesion.
--
-- Se puede correr mas de una vez: usa CREATE TABLE IF NOT EXISTS.
-- Antes de importar, selecciona la base del proyecto (preguntados).
-- ============================================================

SET NAMES utf8mb4;

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

-- Verificacion: debe devolver 1.
SELECT 'user_session_lock' AS chequeo, COUNT(*) AS ok
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_session_lock';
