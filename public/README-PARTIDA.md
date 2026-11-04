# Flujo de partida (modo adaptativo)

Este documento explica cómo funcionan el controlador, el modelo, las vistas, el
temporizador y las reglas de dificultad de la partida.

## Archivos principales

### PartidaController

`app/controller/PartidaController.php` coordina el flujo. Recibe las llamadas del
navegador, usa el modelo para consultar o modificar datos y prepara lo que
renderiza Mustache.

Métodos públicos:

- `iniciar`: crea una partida o **reanuda** la que ya está `EN_CURSO`. Nunca
  crea una segunda partida activa para el mismo usuario.
- `show`: muestra la ruleta. La categoría y la pregunta se eligen **una sola
  vez por turno** y quedan guardadas en sesión, así F5 no las cambia.
- `girar`: marca la ruleta como girada (`POST`). El JS lo llama al terminar la
  animación; también funciona sin JS.
- `pregunta`: muestra la pregunta del turno actual. Arranca el reloj la primera
  vez que se ve la pregunta.
- `responderPregunta`: valida el token elegido, registra la respuesta y redirige
  al resultado (`POST` + `Redirect` + `GET`, para que F5 no reenvíe nada).
- `tiempoAgotado`: el JS lo llama cuando llega a cero. El servidor verifica que
  el tiempo realmente se haya agotado (`POST`).
- `resultado`: muestra el resultado guardado en sesión. Se puede recargar sin
  cambiar nada.
- `continuar`: descarta el resultado y vuelve a la ruleta (o al home si la
  partida terminó).
- `abandonar`: marca la partida activa como `ABANDONADA`, limpia la sesión y
  redirige a un destino interno validado.
- `salir`: limpia la sesión de partida y vuelve al home.

Métodos internos importantes:

- `partidaActiva`: busca la partida `EN_CURSO` del usuario y sincroniza los
  contadores desde la base (el navegador nunca es la fuente de verdad).
- `elegirPregunta`: prueba categorías al azar hasta encontrar una pregunta
  compatible con el nivel del jugador.
- `nuevaRonda`: arma el turno en `$_SESSION['turno']` (pregunta, categoría,
  dificultad, `girada` en `false`, `inicio` en `null` y opciones con token).
- `prepararOpciones`: elige 4 respuestas (siempre incluye la correcta) y les
  asigna un token aleatorio. El id real de la respuesta no sale del servidor.
- `procesarRespuesta` / `procesarTiempoAgotado`: calculan el resultado, lo
  guardan en sesión y redirigen a `resultado`.

### PartidaModel

`app/model/PartidaModel.php` contiene las consultas SQL.

- `obtenerPartida`, `obtenerPartidaEnCurso`, `iniciarPartida`, `ultimoTurno`,
  `actualizarPartida`, `finalizarPartida`, `abandonoPartida`.
- `obtenerCategorias`: categorías con el total de preguntas y cuántas quedan
  **sin responder en esa partida**.
- `obtenerPreguntaDeDificultad`: devuelve la pregunta menos usada de una
  **categoría + dificultad exactas**. Si ya salieron todas, repite mezcladas
  (rotando por las que menos veces salieron).
- `obtenerPreguntaParaJugador`: usa la dificultad exacta del nivel del jugador
  (básico=fácil, intermedio=medio, experimentado=difícil). Solo cae a otra
  dificultad si la categoría no tiene **ninguna** pregunta de la ideal.
- `obtenerRespuestasCompletas`,
  `obtenerRespuestaCorrecta`, `esRespuestaCorrecta`.
- `obtenerEstadisticasJugador`: respondidas / correctas históricas y nivel.
- `registrarEntrega`, `registrarRespuestaEstadistica`, `recalcularDificultad`.
- `registrarRespuesta`: inserta en `game_answers` con `turn_number`. Si se
  reenvía el mismo turno, el índice único `uk_game_answers_turn` devuelve 0 y no
  se suman puntos.

## Reglas de dificultad (`helper/Dificultad.php`)

### Dificultad de una pregunta

1. Las primeras **10 entregas** la pregunta vale como **media** (20 puntos).
2. A partir de la entrega 10 se calcula `aciertos / entregas`:
   - `>= 0,70` → **fácil** (10 puntos)
   - `<= 0,30` → **difícil** (30 puntos)
   - resto → **media** (20 puntos)
3. La dificultad se guarda en `question_stats` y se refleja en `questions`
   (columnas `difficulty_level` y `points`).

### Nivel del jugador

1. Las primeras **10 respuestas** el jugador es **intermedio**.
2. Después, con todo su historial (`game_answers`):
   - `>= 0,70` → **experimentado**
   - `<= 0,30` → **básico**
   - resto → **intermedio**

### Qué preguntas recibe cada nivel

| Nivel del jugador | Dificultad de la pregunta |
|-------------------|---------------------------|
| Básico            | Fácil                     |
| Intermedio        | Medio                     |
| Experimentado     | Difícil                   |

La categoría que sale en la ruleta es **siempre** la de la pregunta y se usa la
dificultad exacta del nivel del jugador: nunca se cambia ni de categoría ni de
dificultad. Si ya respondió **todas las preguntas de esa categoría + dificultad**,
se reinicia ese grupo y se las vuelve a dar mezcladas (primero las que menos
veces salieron, para que roten). Si el jugador sube o baja de nivel, cambia la
dificultad y recibe las preguntas de ese nuevo nivel (las que todavía no salieron
son nuevas para él). La partida nunca se termina por falta de preguntas.

Única excepción: si el banco todavía no tiene **ninguna** pregunta de esa
dificultad (al principio todas arrancan en medio), se usa la dificultad más
cercana para no trabar la partida mientras se calibra.

## Temporizador

- Cada pregunta tiene **20 segundos** (`TIEMPO_LIMITE`) y 2 de gracia
  (`TIEMPO_GRACIA`).
- El reloj **arranca cuando el jugador ve la pregunta** (`partida/pregunta`), no
  mientras gira la ruleta.
- El inicio real se guarda en `$_SESSION['turno']['inicio']` con
  `microtime(true)`. F5 mantiene el mismo inicio.
- La barra y el contador del navegador son **solo visuales**
  (`public/js/partida-temporizador.js`). Aunque se modifiquen desde el
  inspector, el servidor compara contra su propio reloj al responder.
- Al llegar a 0, el JS envía el formulario oculto a `partida/tiempoAgotado`.
  El servidor solo lo acepta si el tiempo realmente venció.
- Si la respuesta llega después del límite + gracia, se trata como tiempo
  agotado.

## Respuestas sin filtrar la correcta

- La vista solo recibe `letra`, `texto` y un **token opaco** por opción.
- El mapeo `token → response_id` y cuál es la correcta viven en
  `$_SESSION['turno']`, en el servidor.
- `responderPregunta` solo acepta un token generado para ese turno. No se acepta
  `question_id` ni `response_id` enviados por el navegador.
- Las opciones se mezclan; la correcta no tiene una posición ni un id previsible.

## Reenvíos y loops de peticiones (el “for” para sumar puntos infinitos)

Tres capas, probadas con requests reales:

- **El turno se consume al responder.** `procesarRespuesta` hace
  `unset($_SESSION['turno'])` y deja `$_SESSION['resultado']`; cualquier POST
  posterior a `partida/responderPregunta` cae en la redirección a
  `partida/resultado` sin tocar la base.
- **Llave única por turno.** `uk_game_answers_turn (game_id, turn_number)`
  (más el `turn_number` que toma el servidor, nunca el navegador). Si el mismo
  turno llegara a insertarse dos veces, `registrarRespuesta` captura el error
  1062, devuelve `0` y el flujo **no suma puntos**.
- **El puntaje se recalcula desde la base.** `PartidaModel::actualizarPartida`
  hace `SUM(earned_points)` y `COUNT(earned_points > 0)` sobre `game_answers` y
  con eso escribe `game.total_points` / `game.correct_answers`. No es un
  acumulador: aunque alguna ruta llegara a sumar dos veces en sesión, el número
  final sigue atado a las respuestas realmente registradas.
- **Nada de esto limita cuantas veces se juega.** Un token que no pertenece al
  turno (24 hex, `random_bytes(12)`) solo devuelve la pregunta; no cierra la
  partida ni suma nada. La partida termina cuando el jugador falla.

Medido: 25 POSTs secuenciales con la respuesta correcta suman **+20 una sola
vez**; 20 POSTs en paralelo idem (el bloqueo del `$_SESSION` de PHP serializa
las peticiones y la llave única descartan el resto); 12 POSTs con tokens
forjados no suman puntos ni insertan filas.

## F5, botón “atrás” y multi-pestaña

- `index.php` envía `Cache-Control: no-store`, así el navegador no reutiliza
  páginas viejas.
- `public/js/partida-navegacion.js` recarga al volver desde el bfcache o al
  apretar “atrás”.
- El resultado se muestra por `GET` (`partida/resultado`) y queda en sesión: F5
  muestra lo mismo y no reenvía la respuesta.
- Si hay un resultado pendiente, `partida/show` redirige a `partida/resultado`:
  no se puede volver a la pregunta anterior.
- La categoría se fija al crear el turno. Al apretar Girar, `ruleta.js` avisa al
  servidor ANTES de la animación y `partida/girar` marca `$_SESSION['turno']['girada']`;
  `partida/pregunta` también la marca, así que aunque el navegador recargue a
  mitad del giro o se vuelva atrás, la ruleta ya aparece con la categoría
  caída y el botón "Ir a la pregunta". El reloj de la pregunta no se reinicia.
- La ruleta no muestra nivel del jugador ni dificultad de la pregunta: solo el
  número de ronda y los puntos. La dificultad se ve únicamente en la pregunta.
- **Una sola partida activa por cuenta**: la columna generada
  `game.en_curso` vale 1 solo para `EN_CURSO`, y el índice único
  `uk_game_en_curso (user_id, en_curso)` impide dos. `iniciar` reanuda la
  existente; si dos pestañas intentan crear a la vez, la segunda recibe el
  error 1062 y también reanuda.
- **Una sola pestaña jugando (la última manda)**. El PHP `$_SESSION` es compartido
  por las pestañas del navegador, así que el servidor no las distingue solo con
  la cookie. `partida-navegacion.js` guarda un sello de 16 caracteres hex en
  `sessionStorage` (por pestaña) y lo agrega a todo enlace, formulario y pedido
  de `/partida/`. `PartidaController::controlarPestana` (lo llama
  `requiereUsuario`, o sea todas las páginas de partida) hace:
  - sello nuevo o sin dueña -> esa pestaña toma el control
    (`$_SESSION['pestana']`) y la anterior pasa a `$_SESSION['pestana_previas']`;
  - sello conocido que ya no manda -> se corta la respuesta con la vista
    `pestanaBloqueadaView` ("Otra pestaña tiene esta partida"), o 403 si es
    un XHR;
  - la bloqueada puede tocar "Seguir jugando acá" (`POST /partida/pestana`) y
    recupera el control, bloqueando a la otra.
  Con JavaScript apagado no hay sello: no se bloquea nada y manda solo la
  restricción de una partida por cuenta.

## Estados de partida

- **EN_CURSO**: activa. Hay como máximo una por usuario.
- **FINALIZADA**: respuesta incorrecta o tiempo agotado. Se guarda
  `finished_at`.
- **ABANDONADA**: el usuario salió con una navegación controlada
  (`partida/abandonar`). Se guarda `finished_at`.

## Migración de base de datos

Para una base existente hay que importar una vez:

```text
app/sql/migracion_dificultad_niveles.sql
```

Agrega `usuarios.Provincia`, `game_answers.turn_number`, quita el índice único
`uk_game_answers_question` (para poder repetir preguntas), la tabla
`question_stats`, la columna generada `game.en_curso` y su índice único, y deja
todas las preguntas en nivel medio. Es idempotente: se puede correr más de una
vez.

Para una instalación nueva, `app/sql/preguntados.sql` ya incluye todo.

Opcional: `app/sql/preguntas_extra.sql` suma 30 preguntas por categoría (todas
arrancan en medio).

## Navegación que abandona una partida

```text
/partida/abandonar?destino=/home/show
/partida/abandonar?destino=/perfil
/partida/abandonar?destino=/login/logout
```

`destinoSeguro` solo acepta rutas internas que empiecen con `/`; cualquier otra
cosa cae en `/home/show`.

## Pruebas realizadas

- Login, inicio/reanudación y una sola partida `EN_CURSO`.
- Ruleta: categoría estable ante F5 y giro no repetido.
- Respuesta correcta: +20 puntos, resultado por `GET`, F5 estable, reenvío sin
  duplicar.
- Respuesta incorrecta: correcta marcada en verde, partida `FINALIZADA`, nueva
  partida con id distinto.
- Tiempo agotado: el servidor lo resuelve solo y muestra la correcta.
- Reloj: arranca al ver la pregunta, F5 no lo reinicia y a los 20 s el servidor
  cierra el turno.
- Fallback de dificultad: un jugador básico recibe preguntas de una categoría
  que solo tiene difíciles.
- Preguntas agotadas: la categoría y la dificultad que salen mandan; si ya
  salieron todas las de esa combinación, se repiten mezcladas (rotando por las
  menos usadas) y la partida sigue. Nunca cambia de categoría ni de dificultad.
- Cambio de nivel del jugador: al pasar a básico/intermedio/experimentado cambia
  la dificultad de las preguntas que recibe.
