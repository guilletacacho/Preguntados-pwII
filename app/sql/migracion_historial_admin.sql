-- Los nombres e IDs se conservan en el historial aunque se borre una cuenta.
CREATE TABLE IF NOT EXISTS admin_historial (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_id INT NOT NULL,
  actor_usuario VARCHAR(255) NOT NULL,
  cuenta_id INT NOT NULL,
  cuenta_usuario VARCHAR(255) NOT NULL,
  accion ENUM('CREACION', 'EDICION', 'CAMBIO_ROL', 'BLOQUEO', 'DESBLOQUEO', 'ELIMINACION') NOT NULL,
  rol_anterior ENUM('JUGADOR', 'MODERADOR', 'ADMIN') DEFAULT NULL,
  rol_nuevo ENUM('JUGADOR', 'MODERADOR', 'ADMIN') DEFAULT NULL,
  motivo VARCHAR(250) DEFAULT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_historial_accion (accion, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
