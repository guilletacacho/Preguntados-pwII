-- Estructura de tabla para la tabla `usuario`

CREATE TABLE `usuario` (
  `Id` int(11) NOT NULL,
  `Nombre` varchar(50) NOT NULL,
  `Anio_nacimiento` year(4) NOT NULL,
  `Sexo` varchar(30) NOT NULL,
  `Pais` varchar(50) NOT NULL,
  `Contrasena` varchar(255) NOT NULL,
  `Nombre_usuario` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO usuario (`Id`, `Nombre`, `Anio_nacimiento`, `Sexo`, `Pais`, `Contrasena`, `Nombre_usuario`) VALUES
(1, 'Nahuel', 2002, 'Masculino', 'Argentina', '1234', 'Nahuelt00');

ALTER TABLE `usuario`
  ADD PRIMARY KEY (`Id`);


ALTER TABLE `usuario`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;