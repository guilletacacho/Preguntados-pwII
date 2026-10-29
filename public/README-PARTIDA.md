# Flujo de partida

Este documento explica cómo se relacionan el controlador, el modelo, la vista y la base de datos para ejecutar una partida.

## Archivos principales

### PartidaController

`app/controller/PartidaController.php` coordina el flujo de la partida. Recibe llamadas del navegador, usa el modelo para consultar o modificar datos y prepara los valores que renderiza Mustache.

Métodos principales:

- `iniciar`: crea una nueva fila en `game`, guarda el ID de partida y reinicia los datos temporales de sesión.
- `show`: obtiene una pregunta aleatoria y sus respuestas, y renderiza la vista.
- `responderPregunta`: valida la respuesta, registra los puntos, actualiza la partida y muestra el resultado.
- `abandonar`: marca una partida activa como `ABANDONADA`, limpia la sesión de partida y redirige.
- `salir`: limpia la sesión cuando la partida ya fue finalizada por derrota.

### PartidaModel

`app/model/PartidaModel.php` contiene las consultas SQL usadas por la partida.

Métodos principales:

- `obtenerPreguntaAleatoria`: obtiene una pregunta con su categoría.
- `obtenerPreguntaPorId`: obtiene los datos de una pregunta conocida.
- `obtenerRespuestasPorPregunta`: obtiene las opciones de una pregunta.
- `obtenerRespuestaCorrecta`: obtiene la opción con `is_correct = 1`.
- `esRespuestaCorrecta`: verifica que la opción seleccionada exista, pertenezca a la pregunta y sea correcta.
- `obtenerPuntosPregunta`: obtiene el valor de `questions.points`.
- `iniciarPartida`: crea una partida para el usuario.
- `registrarRespuesta`: inserta la respuesta seleccionada en `game_answers`.
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

- No repetir preguntas dentro de una misma partida.
- Incrementar `pregunta_numero` al avanzar.
- Definir una cantidad máxima de preguntas por partida.
- Evitar que se vuelva a responder una pregunta ya registrada.
- Incorporar un temporizador.
