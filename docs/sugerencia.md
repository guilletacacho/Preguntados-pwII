# Sugerencias de preguntas

## Planteo

El usuario puede proponer una pregunta desde el home. Envía únicamente la categoría y el enunciado; el moderador investiga el tema y redacta las respuestas al revisarla. Las sugerencias no se incorporan a las partidas hasta que un moderador las aprueba y publica.

## Flujo

1. En el home, debajo de las preguntas frecuentes, aparece **¿Puedo enviar una pregunta como sugerencia?**. Al pulsar el enlace, el usuario entra a una página de sugerencias.
2. La página muestra un botón para desplegar el formulario y, a su derecha, las últimas 10 preguntas sugeridas que fueron aprobadas. Así el usuario puede ver ejemplos de propuestas aceptadas.
3. El formulario solicita categoría y enunciado. No solicita respuestas.
4. Al enviar, el servidor valida los datos, guarda la sugerencia como `PENDIENTE` asociada al usuario autenticado y redirige al home con un mensaje de confirmación.
5. El moderador ve las sugerencias pendientes en su panel. Al abrir una, puede corregir el enunciado, investigar y redactar cuatro respuestas distintas, marcando una como correcta, o descartar la sugerencia.
6. Al aprobar, el sistema publica la pregunta y sus respuestas en las tablas existentes `questions` y `response`, y guarda el resultado de la revisión y el vínculo a la pregunta publicada. Al descartar, registra el estado `RECHAZADA` y una nota opcional.

## Implementación paso a paso

Los nombres de métodos y vistas son sugeridos; se pueden adaptar a las convenciones existentes del proyecto.

### 1. Completar el acceso desde el home

**Archivo:** `app/view/homeView.mustache`

- Convertir el enlace visual de sugerencias en un enlace a la nueva página, por ejemplo `{{basePath}}/sugerencias`.
- Mantener la pregunta dentro de las preguntas frecuentes, debajo de la consulta de rankings.

### 2. Crear la página de sugerencias

**Archivo nuevo sugerido:** `app/view/sugerenciasView.mustache`

- Mostrar un botón **Sugerir una pregunta** que despliegue el formulario.
- Incluir campos de categoría y enunciado, con token CSRF.
- En un diseño de dos columnas, mostrar a la derecha las últimas 10 sugerencias aprobadas, con el enunciado y, si corresponde, la categoría.
- Mostrar mensajes de validación o confirmación.

### 3. Agregar las rutas y acciones del usuario

**Archivos:** archivo de rutas del proyecto y `app/controller/HomeController.php`.

- Registrar una ruta GET, por ejemplo `/sugerencias`, que invoque `mostrarSugerencias()`.
- En `mostrarSugerencias()`, obtener las categorías y las últimas 10 sugerencias aprobadas, y cargar `sugerenciasView.mustache`.
- Registrar una ruta POST, por ejemplo `/sugerencias/enviar`, que invoque `enviarSugerencia()`.
- En `enviarSugerencia()`, verificar sesión y CSRF, validar categoría y enunciado, guardar la sugerencia como pendiente y redirigir al home con un mensaje.
- Agregar o reutilizar `validarSugerencia($datos)` para comprobar categoría válida y enunciado no vacío dentro del límite establecido.

### 4. Crear la persistencia de sugerencias

**Archivo nuevo sugerido:** `app/model/SugerenciaModel.php`.

- `crearSugerencia($userId, $datos)`: insertar usuario, categoría y enunciado con estado `PENDIENTE`.
- `obtenerUltimasAprobadas($limite = 10)`: obtener las sugerencias aprobadas más recientes para la columna pública.
- `obtenerSugerencias($estado, $limite, $offset)` y `contarSugerencias($estado)`: recuperar la bandeja de moderación.
- `obtenerSugerenciaPorId($id)`: cargar detalle, categoría, autor y estado.
- Usar consultas parametrizadas.

### 5. Incorporar la tabla al esquema

**Archivo:** `app/sql/preguntados.sql`; si corresponde, también `app/sql/estructura.sql`. Para bases ya instaladas, crear una migración en `app/sql/`.

Agregar una sola tabla nueva, `question_suggestions`, con estos datos:

- `id`: identificador de la sugerencia.
- `user_id`: usuario que la envió; referencia a `usuarios.Id`.
- `category_id`: categoría propuesta; referencia a `category.id`.
- `question_text`: enunciado enviado por el usuario.
- `status`: `PENDIENTE`, `APROBADA` o `RECHAZADA`; por defecto `PENDIENTE`.
- `reviewed_by`, `reviewed_at`: moderador y fecha de revisión.
- `resolution_notes`: nota opcional sobre la resolución.
- `published_question_id`: referencia a `questions.id` una vez publicada.
- `created_at`, `updated_at`: fechas de creación y última modificación.

No se guardan respuestas en `question_suggestions`: las redacta el moderador y se almacenan en la tabla existente `response`, vinculadas a `questions` mediante `response.question_id`. Conviene que `published_question_id` sea único para evitar vincular una pregunta publicada con más de una sugerencia.

### 6. Crear la bandeja y el editor del moderador

**Archivos:** `app/controller/ModeradorController.php`, una vista como `app/view/moderadorSugerenciasView.mustache`, el archivo de rutas y `app/model/SugerenciaModel.php`.

- Agregar una acción para listar sugerencias, priorizando las pendientes, y otra para mostrar el detalle.
- En la vista, ofrecer las acciones **Revisar** y **Descartar**.
- El editor debe cargar el enunciado y permitir corregirlo y redactar cuatro respuestas distintas, con exactamente una correcta.
- Comprobar el rol `MODERADOR` en el servidor para todas las rutas de revisión.

Métodos sugeridos:

- `listarSugerencias($estado, $pagina)`: listar y paginar la bandeja.
- `obtenerSugerencia($id)`: devolver el detalle para revisión.
- `aprobarSugerencia($id, $datosFinales)`: validar y publicar la versión final.
- `rechazarSugerencia($id, $nota)`: registrar el rechazo y su nota opcional.
- `validarRevisionSugerencia($datos)`: validar enunciado y respuestas redactadas por el moderador.

### 7. Publicar la pregunta de forma atómica

**Archivos:** `app/model/SugerenciaModel.php` y el mecanismo existente que crea preguntas y respuestas.

- En `publicarSugerencia($id, $moderadorId, $datosFinales)`, verificar que la sugerencia siga pendiente.
- Dentro de una transacción, crear la fila de `questions`, insertar las cuatro filas de `response`, actualizar la sugerencia a `APROBADA` y guardar `reviewed_by`, `reviewed_at` y `published_question_id`.
- Si alguna operación falla, revertir toda la transacción.
- Al rechazar, actualizar el estado, moderador, fecha y nota sin crear una pregunta.

## Reglas a considerar

- Validar CSRF en los envíos y consultas parametrizadas en la persistencia.
- El autor puede consultar el estado de sus propias sugerencias, pero no modificarlas después del envío.
- Comprobar que la sugerencia continúe `PENDIENTE` antes de aprobarla o rechazarla.
- Limitar longitudes y definir cómo tratar sugerencias duplicadas.
- Validar que las respuestas finales sean cuatro, distintas y que exactamente una sea correcta.
- La consulta de las últimas 10 aprobadas debe mostrar solo contenido publicado, no sugerencias pendientes o rechazadas.

## Resumen

**Jugador:** entra desde el home → abre la página de sugerencias → despliega el formulario → envía categoría y enunciado → vuelve al home. **Moderador:** revisa la bandeja → corrige o descarta → si aprueba, redacta respuestas y publica la pregunta. La tabla `question_suggestions` conserva la propuesta original y los datos de revisión; el catálogo existente almacena la versión final y sus respuestas.