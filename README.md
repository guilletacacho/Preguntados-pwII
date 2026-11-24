# <span style="color: #2e8b57">Preguntados</span>

Preguntados es un sitio de trivia en el que podés jugar partidas de preguntas y respuestas organizadas por categorías. Cada pregunta tiene un tiempo límite; tus aciertos suman puntos y podés revisar el resultado de la partida al terminar. También podés sugerir preguntas y reportar las que tengan errores.

El sitio cuenta con tres roles:

- **Jugador:** juega partidas, consulta sus resultados, sugiere preguntas y puede reportar preguntas problemáticas.
- **Moderador:** revisa los reportes y las sugerencias, y gestiona las preguntas y categorías del juego.
- **Administrador:** administra las cuentas y sus roles, puede bloquear usuarios y consultar el panel de estadísticas y el historial administrativo. También tiene acceso a las herramientas de moderación.

# <span style="color: #2e8b57">A tener en cuenta</span>

- Necesitas PHP, Composer y MySQL para instalar y ejecutar el sitio.
- La configuración local de la base de datos y del correo se guarda en archivos excluidos de Git. Consulta las secciones siguientes para prepararlos.

# <span style="color: #2e8b57">Configuración de la base de datos</span>

1. En phpMyAdmin, importa `app/sql/preguntados.sql`. Este archivo crea la base de datos `preguntados` con la estructura y los datos iniciales necesarios para el proyecto.
2. Copia `config/config.example.ini` como `config/config.ini` y ajusta la conexión a MySQL:

```ini
db_host = localhost
db_user = root
db_pass = ""
db_name = preguntados
db_port = 3306
app_url = http://localhost/preguntados
```

El puerto debe coincidir con el de MySQL en tu instalación. En una instalación local de XAMPP, `root` suele tener la contraseña vacía.

# <span style="color: #2e8b57">Instalación</span>

Necesitas PHP, Composer y MySQL (por ejemplo, mediante XAMPP). Desde la carpeta del proyecto ejecuta:

```sh
composer install
```

Esto instala PHPMailer y genera `vendor/autoload.php`, que la aplicación necesita para funcionar.

# <span style="color: #2e8b57">Configuración del correo</span>

El proyecto usa PHPMailer para enviar correos y Mailtrap como servidor SMTP de desarrollo. Crea una cuenta o sandbox en Mailtrap, copia `config/mail.example.ini` como `config/mail.ini` y completa los datos SMTP de tu bandeja:

```ini
smtp_host = sandbox.smtp.mailtrap.io
smtp_port = 587
smtp_user = tu_usuario_mailtrap
smtp_pass = tu_password_mailtrap
from_email = no-reply@preguntados.com
from_name = Preguntados
```

Los mensajes enviados al sandbox quedan en Mailtrap y no llegan a destinatarios reales.

# <span style="color: #2e8b57">Archivos de configuración</span>

`config/config.ini` y `config/mail.ini` están excluidos de Git. Crea ambos a partir de sus archivos de ejemplo y no agregues credenciales locales al repositorio.
