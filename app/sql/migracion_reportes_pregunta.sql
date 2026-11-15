USE preguntados;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `question_reports` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `question_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `reason` ENUM('RESPUESTA_INCORRECTA', 'AMBIGUA', 'ERROR_TEXTO', 'OTRO') NOT NULL,
  `detail` VARCHAR(500) DEFAULT NULL,
  `status` ENUM('PENDIENTE', 'RESUELTO', 'DESCARTADO') NOT NULL DEFAULT 'PENDIENTE',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_question_reports_user_question` (`user_id`, `question_id`),
  KEY `idx_question_reports_status` (`status`),
  CONSTRAINT `fk_question_reports_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_question_reports_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Pruebas con IDs existentes: se revierten para no dejar reportes de prueba.
START TRANSACTION;

INSERT IGNORE INTO question_reports (question_id, user_id, reason, detail)
SELECT q.id, u.Id, 'RESPUESTA_INCORRECTA', 'Prueba de migración (se revierte)'
FROM (SELECT id FROM questions ORDER BY id LIMIT 0, 1) q
CROSS JOIN (SELECT Id FROM usuarios ORDER BY Id LIMIT 0, 1) u;

INSERT IGNORE INTO question_reports (question_id, user_id, reason, detail)
SELECT q.id, u.Id, 'AMBIGUA', 'Prueba de migración (se revierte)'
FROM (SELECT id FROM questions ORDER BY id LIMIT 1, 1) q
CROSS JOIN (SELECT Id FROM usuarios ORDER BY Id LIMIT 0, 1) u;

INSERT IGNORE INTO question_reports (question_id, user_id, reason, detail)
SELECT q.id, u.Id, 'ERROR_TEXTO', 'Prueba de migración (se revierte)'
FROM (SELECT id FROM questions ORDER BY id LIMIT 2, 1) q
CROSS JOIN (SELECT Id FROM usuarios ORDER BY Id LIMIT 0, 1) u;

INSERT IGNORE INTO question_reports (question_id, user_id, reason, detail)
SELECT q.id, u.Id, 'OTRO', 'Prueba de migración (se revierte)'
FROM (SELECT id FROM questions ORDER BY id LIMIT 3, 1) q
CROSS JOIN (SELECT Id FROM usuarios ORDER BY Id LIMIT 0, 1) u;

ROLLBACK;
