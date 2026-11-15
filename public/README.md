# Dificultad de las preguntas

La dificultad tiene dos partes distintas: la dificultad global de cada pregunta y el nivel individual de cada jugador.

## Dificultad de una pregunta

La dificultad se calcula con las estadísticas acumuladas de esa pregunta entre todos los jugadores; no hay una dificultad diferente para cada usuario.

Los niveles son:

| Nivel | Valor |
| --- | ---: |
| Fácil | 1 |
| Media | 2 |
| Difícil | 3 |

Una pregunta se considera media durante sus primeras 9 entregas. Al llegar a 10, se evalúa la proporción de aciertos sobre el total de entregas:

- **70 % o más de aciertos:** fácil.
- **30 % o menos de aciertos:** difícil.
- **Más de 30 % y menos de 70 %:** media.

Al registrarse cada respuesta se actualizan las estadísticas y se vuelve a calcular la dificultad usando el historial completo. No se recalcula por bloques de 10 ni queda fijada para siempre. Como el cálculo usa las entregas como denominador, una pregunta entregada pero no respondida no suma un acierto.

## Nivel del jugador y selección de preguntas

El nivel del jugador se calcula por separado, con sus propias respuestas:

- Antes de tener 10 respuestas registradas, se lo considera **intermedio**.
- Al llegar a 10, y con cada respuesta posterior, se evalúa su proporción de respuestas correctas:
  - **70 % o más:** experimentado.
  - **30 % o menos:** básico.
  - **Más de 30 % y menos de 70 %:** intermedio.

El juego intenta ofrecer una pregunta acorde a ese nivel:

| Nivel del jugador | Dificultad que busca |
| --- | --- |
| Básico | Fácil |
| Intermedio | Media |
| Experimentado | Difícil |

Si no hay preguntas disponibles de la dificultad buscada, el juego prueba otras dificultades disponibles para poder continuar la partida.

Actualmente, responder correctamente otorga 1 punto sin importar la dificultad.

## Nota sobre preguntas nuevas

Aunque el alta de moderación guarda inicialmente una pregunta nueva con valor fácil, la dificultad efectiva que usa la partida se calcula a partir de las estadísticas. Por eso, mientras tenga menos de 10 entregas, se considera media.

Las reglas se implementan en [`../helper/Dificultad.php`](../helper/Dificultad.php) y las estadísticas de preguntas y jugadores se actualizan en [`../app/model/PartidaModel.php`](../app/model/PartidaModel.php).
