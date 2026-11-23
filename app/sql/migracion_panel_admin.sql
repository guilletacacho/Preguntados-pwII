-- Indicá el Id de una cuenta propia, registrada y verificada, antes de ejecutar.
SET @id_admin = NULL;

UPDATE usuarios
SET rol = 'ADMIN'
WHERE Id = @id_admin AND Verificado = 1;
