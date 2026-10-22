CREATE DATABASE IF NOT EXISTS preguntados CHARACTER SET utf8mb4;

Use preguntados;
-- Estructura de tabla para la tabla `usuario`

CREATE TABLE `usuarios` (
  `Id` int(11) NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(50) NOT NULL,
  `Anio_nacimiento` year(4) NOT NULL,
  `Sexo` varchar(30) NOT NULL,
  `Pais` varchar(50) NOT NULL,
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

CREATE TABLE `verificacion_tokens` (
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

