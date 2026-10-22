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

# Instalación

Necesitas PHP, Composer y MySQL (por ejemplo, con XAMPP). Desde la carpeta del proyecto ejecuta:

```sh
composer install
```

Composer instala PHPMailer y genera `vendor/autoload.php`, necesario para iniciar la aplicación.

# Validación de correo

El envío de emails usa PHPMailer y se probó en desarrollo con Mailtrap en modo sandbox. Los mensajes quedan capturados en Mailtrap y no se envían a destinatarios reales. Copia `config/mail.example.ini` como `config/mail.ini` y completa las credenciales de tu sandbox:

```ini
smtp_host = sandbox.smtp.mailtrap.io
smtp_port = 587
smtp_user = tu_usuario_mailtrap
smtp_pass = tu_password_mailtrap
```

`config/mail.ini` está excluido de Git: no subas credenciales SMTP al repositorio.
