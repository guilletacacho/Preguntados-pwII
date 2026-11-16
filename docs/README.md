# Panel de moderación

Este documento describe cómo funciona el panel de moderación implementado en la aplicación, incluyendo el control de acceso, los reportes de preguntas y la administración del catálogo.

## Cómo entrar

1. Iniciar sesión con una cuenta verificada.
2. Si la cuenta tiene el rol `MODERADOR`, aparece el enlace **Panel moderador** en la navegación.
3. La ruta de entrada es `/Preguntados/moderador` (método `show`). Desde el panel se puede ir a **Reportes** (`/Preguntados/moderador/reportes`) o **Preguntas** (`/Preguntados/moderador/preguntas`).

El enlace se muestra según el rol consultado en la base de datos. No hay un token de invitación ni un código especial para entrar.

## Quiénes pueden acceder

El rol se guarda en `usuarios.rol`. `ModeradorModel::esModerador()` considera moderador únicamente a quien tenga exactamente `MODERADOR`; `JUGADOR` no da permisos de moderación. El rol predeterminado para nuevas cuentas es `JUGADOR`. Aunque algunos esquemas también contemplen `ADMIN`, el control de acceso actual no lo reconoce como moderador.

Para asignar el rol, una persona con acceso administrativo a la base puede actualizar la cuenta, por ejemplo:

```sql
UPDATE usuarios SET rol = 'MODERADOR' WHERE Usuario = 'nombre_de_usuario';
```

La migración `app/sql/migracion_rol_moderador.sql` agrega la columna de rol en instalaciones que la necesiten; hay que ejecutarla una sola vez y adaptar el enum si la base ya usa el esquema consolidado de `app/sql/preguntados.sql`.

### Diferencia importante en las verificaciones actuales

El enlace y el resumen del panel se muestran solo a moderadores, pero las verificaciones de permiso no son uniformes en todos los métodos del controlador:

- `ModeradorAccess::exigir()` consulta el rol en la base y devuelve HTTP 403 si no es `MODERADOR`. Se usa en `reportes`, `detalle`, `editar`, `corregir`, `resolver`, `descartar`, `editarPregunta` y `actualizarPregunta`.
- `show`, `preguntas`, `altaPregunta`, `crearPregunta`, `desactivarPregunta` y `reactivarPregunta` solo comprueban que haya una sesión iniciada; no llaman a `exigir()`.

Por eso, el menú oculto no equivale por sí solo a una autorización: en el código actual, varias rutas de administración de preguntas no verifican el rol en el servidor.

## ¿Se usa un token?

No se usa token para conceder el rol ni para iniciar sesión en el panel. Para proteger algunos formularios contra CSRF, el controlador crea `$_SESSION['csrf_moderador']` con `bin2hex(random_bytes(32))` y lo incluye como `csrf_token` en los formularios de reportes/corrección. Al recibir el POST, compara el valor de sesión y el enviado con `hash_equals()`. Es un token aleatorio de 32 bytes (representado como 64 caracteres hexadecimales), ligado a la sesión; no reemplaza la comprobación de rol.

La validación CSRF está en `corregir`, `resolver` y `descartar`. Los formularios de alta, edición, activación y desactivación de preguntas no incluyen actualmente esa protección.

## Controlador: `ModeradorController`

El controlador recibe `ModeradorModel` y el renderizador. Procesa la ruta, valida entrada/permisos cuando corresponde y prepara los datos de las vistas.

### Reportes

- `show()`: arma el resumen del panel con cantidad de pendientes, resueltos, preguntas activas y reportes recientes. Solo exige sesión iniciada.
- `reportes()`: verifica rol, filtra reportes por estado y orden, prepara el detalle seleccionado y genera/pasa el token CSRF a la vista.
- `detalle()`: valida el identificador de pregunta y redirige a la bandeja con ese detalle seleccionado.
- `editar()`: verifica rol y que haya reportes pendientes; muestra el formulario para corregir la pregunta.
- `corregir()`: acepta solo POST con rol y token CSRF válidos; valida categoría, enunciado, cuatro respuestas distintas y respuesta correcta. Delega la corrección al modelo y resuelve los reportes pendientes en la misma operación.
- `resolver()` / `descartar()`: seleccionan el estado final y delegan el procesamiento común.
- `procesarResolucion($estado)`: valida permiso, POST, CSRF, pregunta, reportes pendientes y nota (hasta 500 caracteres); actualiza los reportes pendientes y muestra el resultado.
- `armarDetalle($questionId)`: combina la pregunta, sus opciones y reportes, y prepara fechas/estados para la vista.
- `renderizarEditor($questionId, $formulario, $error)`: prepara el formulario de corrección, conserva los valores enviados si hubo errores y pasa el CSRF.
- `validarFormularioCorreccion(...)`: valida los datos del formulario antes de guardarlos: categoría existente, enunciado y nota obligatorios, cuatro opciones existentes/no repetidas y una correcta.
- `asegurarCsrfToken()`: crea el token de sesión si todavía no existe.
- `formatearFecha()` y `guardarMensaje()`: utilidades para presentar fechas y mensajes de resultado.

### Administración de preguntas

- `preguntas()`: lista preguntas con filtros por categoría, dificultad y estado, paginadas de a 10. Solo exige sesión iniciada.
- `altaPregunta()`: carga el formulario de alta con categorías. Solo exige sesión iniciada.
- `crearPregunta()`: valida los datos enviados (cuatro respuestas y exactamente una correcta) y crea la pregunta con dificultad y puntaje iniciales fijados por el servidor en `1`. Solo exige sesión iniciada.
- `desactivarPregunta()` / `reactivarPregunta()`: reciben el ID por POST y cambian el estado activo si corresponde. Solo exigen sesión iniciada.
- `editarPregunta()`: verifica rol, carga pregunta, categorías y respuestas para editar.
- `actualizarPregunta()`: verifica rol y POST, y delega la actualización de la pregunta y las respuestas al modelo.

## Modelo: `ModeradorModel`

El modelo concentra las consultas y escrituras de base de datos. Usa consultas parametrizadas para los valores recibidos.

### Acceso y reportes

- `esModerador($userId)`: consulta `usuarios.rol` y compara con `MODERADOR`.
- `contarReportesPendientes()` / `contarReportesResueltos()`: cuentan reportes por estado.
- `listarPreguntasReportadas($status, $orden)`: agrupa los reportes por pregunta, resume motivos y ordena por volumen o antigüedad.
- `obtenerDetallePregunta($questionId)`: obtiene enunciado y categoría de la pregunta.
- `listarCategorias()` / `obtenerOpciones($questionId)`: devuelven categorías u opciones de respuesta.
- `obtenerReportesPregunta($questionId)`: obtiene los reportes de una pregunta, sus usuarios/revisores y decodifica motivos y snapshot.
- `tieneReportesPendientes($questionId)`: comprueba si queda al menos un reporte pendiente.
- `actualizarReportesPendientes($questionId, $status, $moderadorId, $nota)`: actualiza todos los reportes pendientes de la pregunta con estado, revisor, fecha y nota, dentro de una transacción.
- `corregirPregunta(...)`: en una transacción valida y actualiza la pregunta y sus cuatro respuestas, verifica que quede exactamente una correcta y resuelve los reportes pendientes asociados. Revierte los cambios si ocurre un error.
- `codigosMotivos($reasons)` / `decodificarJson($valor)`: convierten JSON guardado en la base y descartan motivos que no estén reconocidos.

### Catálogo de preguntas

- `agregarPregunta(...)` / `agregarRespuesta(...)`: insertan una pregunta y sus respuestas.
- `desactivarPregunta($id)` / `reactivarPregunta($id)`: cambian `is_active` solo si el estado previo permite la operación.
- `actualizarPregunta(...)`: actualiza categoría, enunciado y respuestas; marca la opción seleccionada como correcta.
- `obtenerPreguntas(...)`: obtiene una página de preguntas, con categoría, dificultad efectiva, actividad y estadísticas.
- `contarPreguntas(...)`: cuenta preguntas aplicando los mismos filtros.
- `construirFiltrosPreguntas(...)`: arma las condiciones parametrizadas de categoría, dificultad y estado, reutilizadas por la lista y el conteo.
- `obtenerCategorias()`: devuelve las categorías ordenadas.
- `obtenerUltimosReportes($limite)`: prepara los reportes recientes para el resumen del panel.
- `buscarPreguntaPorId($id)`: actualmente está incompleto: su implementación solo retorna `null` y no realiza una consulta.

## Relación con la base y otras referencias

- Los roles y el esquema de reportes están definidos en `app/sql/estructura.sql` y `app/sql/preguntados.sql`; el cambio de rol está en `app/sql/migracion_rol_moderador.sql`.
- Los reportes guardan estado, revisor, fecha y nota de resolución. La corrección directa también actualiza la pregunta y las respuestas; resolver o descartar solo cambia el estado de los reportes.
- La explicación del reporte desde la partida y los datos que captura está en [`reportes-preguntas.md`](./reportes-preguntas.md).
