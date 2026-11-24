# Sugerencias de preguntas

## Arquitectura MVC

El jugador propone una categoría y un enunciado. La propuesta se guarda como `PENDIENTE`; el moderador corrige el enunciado, redacta las respuestas y decide si la publica o la rechaza.

El recorrido general es:

`Vista → Controller → Model → Base de datos`

El controlador recibe la petición y prepara los datos para la vista. El modelo contiene las consultas y reglas de persistencia. Las vistas muestran formularios y resultados, sin consultar directamente la base.

## Implementación paso a paso

### 1. Model: persistencia y reglas

Archivo: `app/model/SugerenciaModel.php`.

- `crearSugerencia($userId, $categoryId, $questionText)`: inserta una propuesta en `question_suggestions` con estado `PENDIENTE`.
- `mostrarPreguntasSugeridas($estado, $categoriaId = null)`: arma la bandeja filtrada por estado y, opcionalmente, categoría. Usa consultas parametrizadas.
- `obtenerSugerenciaPorId($id)`: carga una sugerencia pendiente para el editor.
- `obtenerCategorias()`: obtiene las categorías disponibles.
- `contarPreguntasAprobadas()`: devuelve el total de sugerencias aprobadas y publicadas.
- `obtenerPreguntasAprobadasPaginadas($pagina, $porPagina)`: devuelve una página de aprobadas recientes para el panel del jugador.
- `obtenerUltimasPreguntasAprobadas($limite = 10)`: devuelve las aprobadas más recientes para la página del jugador.
- `rechazarSugerencia($id, $moderadorId, $nota)`: cambia a `RECHAZADA` únicamente si todavía está pendiente y registra quién la revisó, cuándo y la nota.
- `publicarSugerencia($id, $moderadorId, $datos)`: valida los datos finales, crea la pregunta y respuestas, y actualiza la sugerencia como aprobada.

#### Cómo funciona la transacción de publicación

`publicarSugerencia()` agrupa los cambios que deben ocurrir juntos:

1. Valida pregunta, categoría, dificultad y las cuatro respuestas; comprueba que sean distintas y que haya una correcta.
2. Inicia la transacción con `beginTransaction()`.
3. `verificarPendiente()` bloquea y comprueba la sugerencia con `SELECT ... FOR UPDATE`, evitando que se publique si ya fue revisada.
4. `insertarPregunta()` crea la fila en `questions`.
5. `insertarRespuestas()` crea las cuatro filas en `response` asociadas a la pregunta.
6. `marcarAprobada()` guarda el estado `APROBADA`, el moderador, la fecha y el ID de la pregunta publicada.
7. Si todo sale bien, `commit()` confirma los cambios. Si ocurre un error, `rollBack()` los deshace.

La transacción evita dejar una pregunta sin sus cuatro respuestas o una sugerencia aprobada sin pregunta publicada.

### 2. Controller del jugador

Archivo: `app/controller/HomeController.php`.

- `mostrarSugerencias()` requiere una sesión, cuenta las aprobadas y llama a `obtenerPreguntasAprobadasPaginadas($pagina, 2)`. Pasa a la vista `preguntasAprobadas`, `paginaAprobadas`, `totalPaginasAprobadas` y los datos de la paginación.
- `enviarSugerencia()` requiere una sesión, recibe `category` y `question` del formulario y llama a `crearSugerencia()` con el ID del usuario autenticado.

### 3. Controller del moderador

Archivo: `app/controller/ModeradorController.php`.

- `sugerencias()` obtiene el estado y la categoría seleccionados, consulta las categorías y pide al modelo las sugerencias correspondientes.
- `editarSugerencia()` exige acceso de moderador, carga una propuesta pendiente y prepara las categorías y cuatro campos de respuesta.
- `publicarSugerencias()` exige acceso de moderador, recibe las respuestas finales y llama a `publicarSugerencia()`.
- `rechazarSugerencia()` exige acceso de moderador y llama a `rechazarSugerencia()` con la nota indicada.

### 4. Vistas

- `app/view/sugerenciasView.mustache`: formulario para enviar una propuesta y lista dinámica de aprobadas. La lista muestra pregunta, categoría y autor; `public/css/sugerencias.css` define el scroll y los colores por categoría.
- `app/view/moderadorSugerenciasView.mustache`: bandeja, filtros de estado y categoría, y acciones para editar o rechazar.
- `app/view/moderadorSugerenciasEditarView.mustache`: formulario para corregir el enunciado, elegir categoría y dificultad, completar cuatro respuestas y marcar la correcta.
- `app/view/homeView.mustache`: enlace desde preguntas frecuentes a la página de sugerencias.

### 5. Base de datos e integración

- `app/sql/preguntados.sql` crea `question_suggestions`, con referencias al usuario, categoría, moderador y pregunta publicada.
- Las preguntas aprobadas se guardan en `questions`; sus respuestas se guardan en `response`.
- `app/Configuration.php` inyecta `SugerenciaModel` en `HomeController` y `ModeradorController`.
- El router del proyecto resuelve las acciones MVC usadas por los enlaces y formularios.

## Pendiente para terminar

- Validar en servidor el envío del jugador y agregar CSRF a las acciones de sugerencias. El editor incluye un campo CSRF, pero falta generar y comprobar el token en estas acciones.
- Exigir rol de moderador también en `sugerencias()`, y cargar las categorías del formulario del jugador desde la base.
- Agregar confirmación después del envío y una migración para bases ya instaladas (`estructura.sql` o migración específica).
- La consulta del estado de las propuestas por su autor todavía no está implementada.
