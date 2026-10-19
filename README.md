# Configurar la base de datos

1. Importa `app/sql/preguntados.sql` desde phpMyAdmin. El script crea la base `preguntados`.
2. Copia `config/config.example.ini` como `config/config.ini` y ajusta los datos de MySQL:

```ini
db_host = localhost
db_user = root
db_pass = ""
db_name = preguntados
db_port = 3306
```

`db_port` debe coincidir con el puerto de MySQL de tu instalación. La contraseña vacía corresponde a una instalación local de XAMPP sin contraseña para `root`.

`config/config.ini` está excluido de Git para mantener las credenciales locales.
