CREATE DATABASE IF NOT EXISTS preguntados CHARACTER SET utf8mb4;

Use preguntados;
-- Estructura de tabla para la tabla `usuarios`

CREATE TABLE IF NOT EXISTS `usuarios` (
  `Id` int(11) NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(50) NOT NULL,
  `Anio_nacimiento` year(4) NOT NULL,
  `Sexo` varchar(30) NOT NULL,
  `Provincia` varchar(150) NOT NULL,
  `Pais` varchar(150) NOT NULL,
  `Usuario` varchar(255) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `Contrasena` varchar(255) NOT NULL,
  `Verificado` tinyint(1) NOT NULL DEFAULT 0,
  `Token` varchar(64) DEFAULT NULL,
  `Foto_url` varchar(255) NULL,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uk_usuarios_usuario` (`Usuario`),
  UNIQUE KEY `uk_usuarios_email` (`Email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `verificacion_tokens` (
  `Id` int(11) NOT NULL AUTO_INCREMENT,
  `UsuarioId` int(11) NOT NULL,
  `Token` varchar(64) NOT NULL,
  `Expira` datetime NOT NULL,
  `Usado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uk_token` (`Token`),
  KEY `fk_usuario` (`UsuarioId`),
  CONSTRAINT `fk_usuario` FOREIGN KEY (`UsuarioId`) REFERENCES `usuarios` (`Id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @migracion_provincia = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `usuarios` ADD COLUMN `Provincia` varchar(150) NOT NULL DEFAULT '''' AFTER `Sexo`',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'usuarios'
    AND COLUMN_NAME = 'Provincia'
);

PREPARE sentencia_provincia FROM @migracion_provincia;
EXECUTE sentencia_provincia;
DEALLOCATE PREPARE sentencia_provincia;
