<?php

/**
 * Reglas del modo adaptativo.
 *
 * DIFICULTAD DE UNA PREGUNTA (1 = fácil, 2 = medio, 3 = difícil)
 *   - Las primeras CALIBRACION entregas la pregunta vale como MEDIA.
 *   - A partir de ahí se compara cuántas veces se entregó contra cuántas
 *     veces se respondió bien:
 *       aciertos / entregas >= 0.70  -> fácil
 *       aciertos / entregas <= 0.30  -> difícil
 *       resto                        -> medio
 *
 * NIVEL DEL JUGADOR (1 = básico, 2 = intermedio, 3 = experimentado)
 *   - Las primeras CALIBRACION respuestas el jugador es INTERMEDIO.
 *   - Después, mismo criterio con su historial:
 *       aciertos / respuestas >= 0.70 -> experimentado
 *       aciertos / respuestas <= 0.30 -> básico
 *       resto                         -> intermedio
 *
 * El nivel del jugador define qué dificultad de pregunta se le ofrece.
 * Si no quedan preguntas de esa dificultad, se cae a la siguiente del
 * orden de preferencia (ver preferencia()).
 */
class Dificultad
{
    public const FACIL   = 1;
    public const MEDIO   = 2;
    public const DIFICIL = 3;

    public const BASICO        = 1;
    public const INTERMEDIO    = 2;
    public const EXPERIMENTADO = 3;

    /** Entregas necesarias antes de reclasificar una pregunta. */
    public const CALIBRACION = 10;
    /** Entregas necesarias antes de clasificar a un jugador. */
    public const CALIBRACION_JUGADOR = 10;

    public const RATIO_FACIL   = 0.70;
    public const RATIO_DIFICIL = 0.30;

    private const PUNTOS = [
        self::FACIL   => 10,
        self::MEDIO   => 20,
        self::DIFICIL => 30,
    ];

    private const ETIQUETAS = [
        self::FACIL   => 'Fácil',
        self::MEDIO   => 'Medio',
        self::DIFICIL => 'Difícil',
    ];

    private const ETIQUETAS_JUGADOR = [
        self::BASICO        => 'Básico',
        self::INTERMEDIO    => 'Intermedio',
        self::EXPERIMENTADO => 'Experimentado',
    ];

    /**
     * Dificultad que le corresponde a una pregunta según su historial.
     */
    public static function calcular(int $entregas, int $aciertos): int
    {
        if ($entregas < self::CALIBRACION) {
            return self::MEDIO;
        }

        $ratio = $aciertos / $entregas;

        if ($ratio >= self::RATIO_FACIL) {
            return self::FACIL;
        }
        if ($ratio <= self::RATIO_DIFICIL) {
            return self::DIFICIL;
        }

        return self::MEDIO;
    }

    /**
     * Nivel que le corresponde a un jugador según su historial.
     */
    public static function nivelJugador(int $respondidas, int $correctas): int
    {
        if ($respondidas < self::CALIBRACION_JUGADOR) {
            return self::INTERMEDIO;
        }

        $ratio = $correctas / $respondidas;

        if ($ratio >= self::RATIO_FACIL) {
            return self::EXPERIMENTADO;
        }
        if ($ratio <= self::RATIO_DIFICIL) {
            return self::BASICO;
        }

        return self::INTERMEDIO;
    }

    public static function puntos(int $dificultad): int
    {
        return self::PUNTOS[$dificultad] ?? self::PUNTOS[self::MEDIO];
    }

    public static function etiqueta(int $dificultad): string
    {
        return self::ETIQUETAS[$dificultad] ?? self::ETIQUETAS[self::MEDIO];
    }

    public static function etiquetaJugador(int $nivel): string
    {
        return self::ETIQUETAS_JUGADOR[$nivel] ?? self::ETIQUETAS_JUGADOR[self::INTERMEDIO];
    }

    /**
     * Orden en el que se buscan preguntas para un nivel de jugador.
     * Siempre devuelve las tres dificultades: así, si se acaban las del
     * nivel ideal, la partida puede seguir con las del nivel más cercano.
     *
     * @return int[]
     */
    public static function preferencia(int $nivelJugador): array
    {
        if ($nivelJugador === self::BASICO) {
            return [self::FACIL, self::MEDIO, self::DIFICIL];
        }

        if ($nivelJugador === self::EXPERIMENTADO) {
            return [self::DIFICIL, self::MEDIO, self::FACIL];
        }

        return [self::MEDIO, self::FACIL, self::DIFICIL];
    }

    /**
     * Fragmento SQL que calcula la dificultad efectiva de una pregunta.
     * Debe coincidir con calcular(). Usa el alias qs para question_stats.
     */
    public static function sqlDificultad(): string
    {
        return sprintf(
            "CASE
                WHEN COALESCE(qs.times_delivered, 0) < %d THEN %d
                WHEN (qs.times_correct / qs.times_delivered) >= %.2f THEN %d
                WHEN (qs.times_correct / qs.times_delivered) <= %.2f THEN %d
                ELSE %d
            END",
            self::CALIBRACION,
            self::MEDIO,
            self::RATIO_FACIL,
            self::FACIL,
            self::RATIO_DIFICIL,
            self::DIFICIL,
            self::MEDIO
        );
    }
}
