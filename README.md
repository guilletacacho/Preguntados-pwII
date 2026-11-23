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

Si ya tenés una base instalada, importá `app/sql/migracion_gestion_usuarios.sql` sobre esa base para habilitar el bloqueo y las fechas de alta. Las cuentas anteriores a la migración conservan una fecha de alta desconocida.

Importá también `app/sql/migracion_historial_admin.sql` para registrar las nuevas acciones administrativas. El historial comienza al habilitar esta función.

Si la base instalada todavía no tiene sugerencias o categorías editables, importá `app/sql/migracion_categorias_sugerencias.sql`.

Para habilitar Administración, configurá `@id_admin` en `app/sql/migracion_panel_admin.sql` con el ID de una cuenta propia ya verificada y ejecutá la consulta en la base del proyecto. El rol `ADMIN` puede entrar a Administración y Moderación. El rol `MODERADOR` tiene acceso a Moderación.

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
