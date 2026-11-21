-- Ejecutar sobre la base existente. Las fechas de alta anteriores quedan desconocidas.
SET @usuarios_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'bloqueado'),
    'SELECT 1',
    'ALTER TABLE usuarios ADD COLUMN bloqueado TINYINT(1) NOT NULL DEFAULT 0'
);
PREPARE usuarios_stmt FROM @usuarios_sql;
EXECUTE usuarios_stmt;
DEALLOCATE PREPARE usuarios_stmt;

SET @usuarios_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'version_sesion'),
    'SELECT 1',
    'ALTER TABLE usuarios ADD COLUMN version_sesion INT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE usuarios_stmt FROM @usuarios_sql;
EXECUTE usuarios_stmt;
DEALLOCATE PREPARE usuarios_stmt;

SET @usuarios_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'creado_en'),
    'SELECT 1',
    'ALTER TABLE usuarios ADD COLUMN creado_en DATETIME DEFAULT NULL'
);
PREPARE usuarios_stmt FROM @usuarios_sql;
EXECUTE usuarios_stmt;
DEALLOCATE PREPARE usuarios_stmt;

ALTER TABLE usuarios ALTER COLUMN creado_en SET DEFAULT CURRENT_TIMESTAMP;
