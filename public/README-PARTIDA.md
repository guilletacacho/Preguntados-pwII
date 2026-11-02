# Flujo de partida

Este documento explica cómo se relacionan el controlador, el modelo, la vista y la base de datos para ejecutar una partida.

## Archivos principales

### PartidaController

`app/controller/PartidaController.php` coordina el flujo de la partida. Recibe llamadas del navegador, usa el modelo para consultar o modificar datos y prepara los valores que renderiza Mustache.

Métodos principales:

- `iniciar`: crea una nueva fila en `game`, guarda el ID de partida y reinicia los datos temporales de sesión.
- `show`: elige la categoría y la pregunta pendiente, y muestra la ruleta. Si no quedan preguntas sin responder, finaliza la partida.
- `pregunta`: muestra la pregunta pendiente con sus respuestas y el temporizador.
- `responderPregunta`: valida la respuesta, registra los puntos y el tiempo usado, actualiza la partida y muestra el resultado.
- `tiempoAgotado`: termina la partida cuando se acaba el tiempo de la pregunta y muestra la respuesta correcta.
- `abandonar`: marca una partida activa como `ABANDONADA`, limpia la sesión de partida y redirige.
- `salir`: limpia la sesión cuando la partida ya fue finalizada por derrota.
- `destinoSeguro`: valida el parámetro `destino` de `abandonar`; solo acepta rutas internas que empiecen con `/`.

### PartidaModel

`app/model/PartidaModel.php` contiene las consultas SQL usadas por la partida.

Métodos principales:

- `obtenerCategorias`: devuelve las categorías con la cantidad de preguntas que todavía no se respondieron en la partida.
- `obtenerPreguntaAleatoria`: obtiene una pregunta al azar de la categoría, excluyendo las ya respondidas en la partida.
- `preguntaYaRespondida`: indica si la pregunta ya está registrada en `game_answers` para esa partida.
- `obtenerPreguntaPorId`: obtiene los datos de una pregunta conocida.
- `obtenerRespuestasPorPregunta`: obtiene las opciones de una pregunta.
- `obtenerRespuestaCorrecta`: obtiene la opción con `is_correct = 1`.
- `esRespuestaCorrecta`: verifica que la opción seleccionada exista, pertenezca a la pregunta y sea correcta.
- `obtenerPuntosPregunta`: obtiene el valor de `questions.points`.
- `iniciarPartida`: crea una partida para el usuario.
- `registrarRespuesta`: inserta la respuesta seleccionada en `game_answers`, con el número de turno y el tiempo de respuesta (`response_time_seconds`).
- `actualizarPartida`: actualiza el puntaje y los aciertos acumulados en `game`.
- `finalizarPartida`: cambia una partida activa a `FINALIZADA`.
- `abandonoPartida`: cambia una partida activa a `ABANDONADA`.

## Flujo normal

1. El usuario presiona **Jugar**.
2. El navegador llama a `/partida/iniciar`.
3. `iniciar()` obtiene `$_SESSION['usuario_id']`.
4. El modelo crea una fila en `game`.
5. El controlador guarda el nuevo `game_id` y reinicia los acumulados.
6. Se redirige a `/partida/show`.
7. `show()` consulta una pregunta aleatoria y sus cuatro respuestas.
8. La vista crea un formulario por opción.
9. El usuario envía `question_id` y `response_id`.
10. `responderPregunta()` valida si la respuesta es correcta.
11. Si fue correcta, suma los puntos configurados en la pregunta.
12. El modelo registra la respuesta en `game_answers`.
13. El modelo actualiza `total_points` y `correct_answers` en `game`.
14. Si fue incorrecta, el modelo marca la partida como `FINALIZADA`.
15. La vista muestra el resultado y las opciones disponibles.

## Conexión entre los métodos y la vista

`show()` pasa a Mustache una estructura similar a:

```php
[
    'questionId' => $pregunta['question_id'],
    'categoria' => $pregunta['categoria'],
    'categoriaSlug' => 'ciencia',
    'pregunta' => $pregunta['pregunta'],
    'respuestas' => $respuestas,
    'preguntaNumero' => $_SESSION['pregunta_numero'],
    'puntos' => $_SESSION['puntos']
]
```

`partidaView.Mustache` usa esos datos para:

- Mostrar categoría, pregunta y puntaje.
- Renderizar las respuestas como formularios mientras no hay un resultado.
- Mostrar las respuestas coloreadas cuando `resultadoMostrado` es verdadero.
- Mostrar “Siguiente” si la respuesta fue correcta.
- Mostrar “Volver al lobby” y “Jugar de nuevo” si fue incorrecta.

## Temporizador

Cada pregunta tiene 20 segundos (`TIEMPO_LIMITE` en `PartidaController`).

1. `pregunta()` guarda `$_SESSION['pregunta_inicio']` la primera vez que se muestra la pregunta. Si se recarga la página, el reloj sigue corriendo.
2. La vista recibe `tiempoRestante` y `tiempoLimite`, y los pone en `data-restante` y `data-limite` de la barra `.partida-progreso`.
3. `public/js/partida-temporizador.js` actualiza la barra y el contador cada 250 ms. En los últimos 5 segundos la barra se pone roja.
4. Al llegar a 0, el script envía el formulario oculto hacia `/partida/tiempoAgotado`. Si el jugador elige una respuesta antes, el reloj se detiene.
5. `tiempoAgotado()` verifica en el servidor que el tiempo realmente se agotó, finaliza la partida y muestra "¡Se acabó el tiempo!" con la respuesta correcta marcada.
6. `responderPregunta()` también calcula el tiempo usado. Si pasó más del límite más 2 segundos de gracia (`TIEMPO_GRACIA`), trata la respuesta como tiempo agotado.

La cuenta regresiva en pantalla es solo visual; la decisión final la toma el servidor. Una pregunta que se agota no se registra en `game_answers`, porque `response_id` no admite `NULL`.

## Preguntas sin repetir

- Las preguntas ya respondidas en la partida (las que están en `game_answers`) no se vuelven a elegir.
- La ruleta solo ofrece categorías que todavía tienen preguntas sin responder.
- Si no queda ninguna, `show()` finaliza la partida y muestra el mensaje de que no hay preguntas disponibles.
- Antes de registrar una respuesta, `responderPregunta()` consulta `preguntaYaRespondida()`. Si la pregunta ya estaba registrada, redirige a `show` sin sumar puntos ni insertar nada. Esto se suma al número de turno, a la pregunta pendiente en sesión y al índice único `uk_game_answers_turn`.

## Orden de las respuestas

Las respuestas se sortean una sola vez por pregunta y se guardan en `$_SESSION['opciones_pendientes']`. Al recargar o al mostrar el resultado se usa la misma lista, así que el orden no cambia.

## Estados de partida

### EN_CURSO

La partida fue creada y todavía está activa.

Se usa para una partida que el usuario puede estar respondiendo.

### FINALIZADA

El jugador respondió incorrectamente y el juego terminó.

La fecha queda guardada en `finished_at`.

### ABANDONADA

El usuario salió de una partida todavía activa usando una navegación controlada por `abandonar()`.

La fecha queda guardada en `finished_at`.

La partida también pasa a `FINALIZADA` cuando se agota el tiempo de una pregunta o cuando no quedan preguntas sin responder.

## Navegación que abandona una partida

El header puede enviar al controlador el destino después de abandonar:

```text
/partida/abandonar?destino=/home/show
/partida/abandonar?destino=/perfil
/partida/abandonar?destino=/login/logout
```

`abandonar()` ejecuta el modelo solo si todavía existe `game_id` y luego limpia los datos temporales de la partida.

La actualización SQL solo se realiza cuando `status = 'EN_CURSO'`; por eso una partida ya `FINALIZADA` no se convierte en `ABANDONADA`.

## Funcionalidades pendientes

- Incrementar `pregunta_numero` al avanzar.
- Definir una cantidad máxima de preguntas por partida.
