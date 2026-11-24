-- Edad exacta: guarda también mes y día de nacimiento.
-- Ejecutar en phpMyAdmin o mysql sobre la BD ya existente. No inventa fechas para usuarios anteriores.

SET @partida_schema_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'Mes_nacimiento'),
    'SELECT 1',
    'ALTER TABLE usuarios ADD COLUMN Mes_nacimiento TINYINT UNSIGNED DEFAULT NULL AFTER Anio_nacimiento'
);
PREPARE partida_schema_stmt FROM @partida_schema_sql;
EXECUTE partida_schema_stmt;
DEALLOCATE PREPARE partida_schema_stmt;

SET @partida_schema_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'Dia_nacimiento'),
    'SELECT 1',
    'ALTER TABLE usuarios ADD COLUMN Dia_nacimiento TINYINT UNSIGNED DEFAULT NULL AFTER Mes_nacimiento'
);
PREPARE partida_schema_stmt FROM @partida_schema_sql;
EXECUTE partida_schema_stmt;
DEALLOCATE PREPARE partida_schema_stmt;
