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
  `Contrasena` varchar(255) NOT NULL,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uk_usuarios_usuario` (`Usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO usuarios
    (`Nombre`, `Anio_nacimiento`, `Sexo`, `Pais`, `Usuario`, `Contrasena`)
VALUES
    ('Nahuel', 2002, 'masculino', 'Argentina', 'prueba', MD5('1234'));
