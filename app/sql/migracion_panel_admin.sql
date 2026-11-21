-- Panel administrable: cuenta inicial para el ABM de usuarios.
-- Se puede importar en cualquier computadora (local o hosting) las veces que haga falta.
-- El INSERT solo crea la cuenta si todavía no existen ese usuario ni ese correo.
-- Usuario: admin_panel
-- Contraseña: Admin1234!
-- IMPORTANTE: cambiar la contraseña después del primer ingreso.

INSERT INTO usuarios
    (Nombre, Anio_nacimiento, Sexo, Provincia, Pais, Usuario, Email,
     Contrasena, Verificado, Token, Foto_url, rol)
SELECT
    'Administrador', 1990, 'No especificado', '', 'Argentina',
    'admin_panel', 'admin_panel@preguntados.local',
    '552b2ebe774bb5aaa0ad2021da259d22', 1, NULL, NULL, 'ADMIN'
WHERE NOT EXISTS (
    SELECT 1 FROM usuarios
    WHERE Usuario = 'admin_panel' OR Email = 'admin_panel@preguntados.local'
);
