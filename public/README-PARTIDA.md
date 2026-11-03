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
  dificultad, inicio en `null` y opciones con token).
- `prepararOpciones`: elige 4 respuestas (siempre incluye la correcta) y les
  asigna un token aleatorio. El id real de la respuesta no sale del servidor.
- `procesarRespuesta` / `procesarTiempoAgotado`: calculan el resultado, lo
  guardan en sesión y redirigen a `resultado`.

### PartidaModel

`app/model/PartidaModel.php` contiene las consultas SQL.

- `obtenerPartida`, `obtenerPartidaEnCurso`, `iniciarPartida`, `ultimoTurno`,
  `actualizarPartida`, `finalizarPartida`, `abandonoPartida`.
- `obtenerCategorias`: categorías con la cantidad de preguntas **sin responder
  en esa partida**.
- `obtenerPreguntaParaJugador`: recorre las dificultades preferidas para el
  nivel del jugador hasta encontrar una pregunta libre en la categoría.
- `obtenerPreguntaPorDificultad`: calcula la dificultad efectiva de cada
  pregunta a partir de `question_stats`.
- `preguntaYaRespondida`, `obtenerRespuestasCompletas`,
  `obtenerRespuestaCorrecta`, `esRespuestaCorrecta`.
- `obtenerEstadisticasJugador`: respondidas / correctas históricas y nivel.
- `registrarEntrega`, `registrarRespuestaEstadistica`, `recalcularDificultad`.
- `registrarRespuesta`: inserta en `game_answers` con `turn_number`. Si la
  pregunta ya estaba respondida en la partida, el índice único devuelve 0 y no
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

| Nivel del jugador | Orden de preferencia          |
|-------------------|-------------------------------|
| Básico            | fácil → medio → difícil       |
| Intermedio        | medio → fácil → difícil       |
| Experimentado     | difícil → medio → fácil       |

Si la categoría no tiene preguntas del nivel ideal, se pasa al siguiente. Si no
tiene de ningún nivel, esa categoría deja de ofrecerse. La partida **nunca se
termina por falta de preguntas**: cuando ya salieron todas las preguntas del
juego, se vuelven a ofrecer (se repiten). Dentro de una misma partida no se
repiten hasta que el jugador ya respondió todas; recién ahí se reinicia el pool.

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

## F5, botón “atrás” y multi-pestaña

- `index.php` envía `Cache-Control: no-store`, así el navegador no reutiliza
  páginas viejas.
- `public/js/partida-navegacion.js` recarga al volver desde el bfcache o al
  apretar “atrás”.
- El resultado se muestra por `GET` (`partida/resultado`) y queda en sesión: F5
  muestra lo mismo y no reenvía la respuesta.
- Si hay un resultado pendiente, `partida/show` redirige a `partida/resultado`:
  no se puede volver a la pregunta anterior.
- La categoría se fija al crear el turno y `partida/girar` marca
  `ruleta_girada`; F5 después del giro muestra la misma categoría sin repetir la
  animación.
- **Una sola partida activa por cuenta**: la columna generada
  `game.en_curso` vale 1 solo para `EN_CURSO`, y el índice único
  `uk_game_en_curso (user_id, en_curso)` impide dos. `iniciar` reanuda la
  existente; si dos pestañas intentan crear a la vez, la segunda recibe el
  error 1062 y también reanuda.

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
- Preguntas agotadas: la partida no termina; primero usa todas las que faltan y
  recién después repite (sin repetir la última).
