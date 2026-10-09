CREATE DATABASE IF NOT EXISTS preguntados CHARACTER SET utf8mb4;

Use preguntados;
-- Estructura de tabla para la tabla `usuario`

CREATE TABLE `usuarios` (
  `Id` int(11) NOT NULL,
  `Nombre` varchar(50) NOT NULL,
  `Anio_nacimiento` year(4) NOT NULL,
  `Sexo` varchar(30) NOT NULL,
  `Pais` varchar(50) NOT NULL,
  `Usuario` varchar(255) NOT NULL,
  `Contrasena` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO usuarios
    (`Id`, `Nombre`, `Anio_nacimiento`, `Sexo`, `Pais`, `Usuario`, `Contrasena`)
VALUES
    (1, 'Nahuel', 2002, 'masculino', 'Argentina', 'Nahuelt00', MD5('1234'));
