# Configurar la base de datos

1. Crea la base `entradas_messi` e importa `app/sql/entradas_messi.sql` desde phpMyAdmin.
2. Edita `config/config.ini` con tus datos:

```ini
db_host = localhost
db_user = root
db_pass =
db_name = entradas_messi
db_port = 3307
```

`db_port` debe coincidir con el puerto de MySQL de tu instalación (por ejemplo, `3306` o `3307`).

La conexión se arma en `app/Configuration.php`, en `getDatabase()`, que lee el INI y pasa los valores a `helper/MyDatabase.php`. Allí el constructor los usa en el DSN de PDO (`host`, `dbname` y `port`). Si cambias los nombres de las claves del INI, actualiza también los índices leídos en `Configuration.php` o agregar unicamente el port por el que se usa en tu maquina 3306|3307...
