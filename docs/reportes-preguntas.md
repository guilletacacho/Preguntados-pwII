# Reportes de preguntas

Se puede reportar desde el resultado de cada pregunta, después de responder o agotar el tiempo. Al terminar por error o tiempo agotado, el resultado incluye el resumen de todos los turnos de esa partida. El avance es manual: abrir el formulario no consume tiempo ni altera puntos.

Se admite un reporte por usuario y pregunta, incluso si vuelve a aparecer en otra partida. Acepta varios motivos y un comentario de hasta 2000 caracteres, obligatorio para `otro`. Solo el dueño de la partida puede reportar sus resultados, mediante POST con token CSRF.

## Base de datos

Las tablas están incluidas en `app/sql/preguntados.sql` y `app/sql/estructura.sql`. En una instalación nueva, esos esquemas consolidados son la fuente de verdad. `CREATE TABLE IF NOT EXISTS` no actualiza una tabla `question_reports` existente con el esquema anterior; para esa actualización revisá `app/sql/migracion_reportes_pregunta_resultados.sql`. La migración anterior se retiró porque creaba otra estructura y no adaptaba la tabla existente. La propuesta nueva crea un respaldo, mantiene los campos legados y se puede repetir; debe revisarse y probarse en una copia de la base antes de ejecutarla. No la ejecuté.

- `question_reports` es la única tabla de esta funcionalidad. Guarda usuario, pregunta original, partida, turno, motivos, comentario, fechas y estado inicial `PENDIENTE`.
- `question_snapshot` guarda una copia en JSON con pregunta, categoría, opciones mostradas, respuesta seleccionada, respuesta considerada correcta, puntos y tiempo agotado. Se toma del resultado guardado en el servidor, y se escribe en la base únicamente cuando el jugador envía el reporte. `question_id` conserva la referencia original aunque luego se elimine la pregunta.
- Las preguntas resueltas de la partida se conservan temporalmente en la sesión PHP para mostrar el resumen final. Se limpian al salir o iniciar otra partida. No se guardan filas por cada turno en la base.
- Estados previstos para el dashboard: `PENDIENTE`, `EN_REVISION`, `RESUELTO`, `DESCARTADO`. Se incluyen `reviewed_by`, `reviewed_at` y `resolution_notes` para la gestión posterior.

Los códigos de motivos son `respuesta_incorrecta`, `varias_validas`, `confusa`, `desactualizada`, `inapropiada` y `otro`.

Consulta de ejemplo para el equipo del dashboard:

```sql
SELECT r.*, u.Usuario
FROM question_reports r
JOIN usuarios u ON u.Id = r.user_id
WHERE r.status = 'PENDIENTE'
ORDER BY r.created_at, r.id;
```

La gestión, sus permisos y las modificaciones de preguntas corresponden al dashboard y no se implementan aquí. Resolver un reporte no recalcula puntajes automáticamente.

## Verificación

`php tests/question-reports.php` verifica consultas y lógica con SQLite en memoria, sin modificar la base local.

Las pruebas cubren historial en sesión, persistencia solo al reportar, copia obtenida del servidor, propiedad de las partidas, CSRF, motivos inválidos, comentario obligatorio, longitud, reportes duplicados entre partidas, puntaje, resumen, tiempo agotado y escape HTML.
