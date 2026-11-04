<?php
namespace app\model;

use Dificultad;
use Log;
use PDOException;

class PartidaModel{

    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    // ─────────────────────────────────────────────────────────────
    // PARTIDA
    // ─────────────────────────────────────────────────────────────

    public function obtenerPartida($gameId){
        $sql = "SELECT id, user_id, status, total_points, correct_answers, started_at, finished_at
                FROM game
                WHERE id = ?
                LIMIT 1";

        return $this->database->queryOne($sql, [$gameId]);
    }

    public function obtenerPartidaEnCurso($userId){
        // Con el índice uk_game_en_curso hay como máximo una por usuario.
        $sql = "SELECT id, user_id, status, total_points, correct_answers, started_at, finished_at
                FROM game
                WHERE user_id = ?
                    AND status = 'EN_CURSO'
                ORDER BY id DESC
                LIMIT 1";

        return $this->database->queryOne($sql, [$userId]);
    }

    /**
     * Devuelve la partida EN_CURSO del usuario o crea una nueva.
     * El índice único uk_game_en_curso evita que dos pestañas creen dos.
     */
    public function iniciarPartida($userId){
        $existente = $this->obtenerPartidaEnCurso($userId);
        if ($existente !== null) {
            return $existente + ['nueva' => false];
        }

        try {
            $this->database->execute("INSERT INTO game (user_id) VALUES (?)", [$userId]);
            $gameId = (int) $this->database->lastInsertId();
        } catch (PDOException $exception) {
            // Carrera entre dos pestañas: la segunda choca con el índice único.
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) {
                throw $exception;
            }

            $existente = $this->obtenerPartidaEnCurso($userId);
            if ($existente === null) {
                throw $exception;
            }

            return $existente + ['nueva' => false];
        }

        return [
            'id' => $gameId,
            'user_id' => $userId,
            'status' => 'EN_CURSO',
            'total_points' => 0,
            'correct_answers' => 0,
            'started_at' => null,
            'finished_at' => null,
            'nueva' => true,
        ];
    }

    public function ultimoTurno($gameId){
        // COUNT(*) cubre las respuestas viejas, que no tienen turn_number:
        // sin eso la partida arranca en "Ronda 1" con puntos ya ganados.
        $sql = "SELECT GREATEST(COALESCE(MAX(turn_number), 0), COUNT(*)) AS ultimo
                FROM game_answers
                WHERE game_id = ?";

        $fila = $this->database->queryOne($sql, [$gameId]);

        return (int) ($fila['ultimo'] ?? 0);
    }

    /**
     * Recalcula los totales desde game_answers. Los puntos del jugador no
     * vienen del navegador ni de un acumulador: salen de las respuestas
     * efectivamente registradas, así un reenvío no puede inflarlos.
     */
    public function actualizarPartida($gameId){
        $totales = $this->database->queryOne(
            "SELECT COALESCE(SUM(earned_points), 0) AS puntos,
                    COALESCE(SUM(CASE WHEN earned_points > 0 THEN 1 ELSE 0 END), 0) AS correctas
             FROM game_answers
             WHERE game_id = ?",
            [$gameId]
        );

        $puntos = (int) ($totales['puntos'] ?? 0);
        $correctas = (int) ($totales['correctas'] ?? 0);

        $sql = "UPDATE game
                SET total_points = ?,
                    correct_answers = ?
                WHERE id = ?";

        $this->database->execute($sql, [$puntos, $correctas, $gameId]);

        return ['puntos' => $puntos, 'correctas' => $correctas];
    }

    public function finalizarPartida($gameId){
        $sql = "UPDATE game
                SET status = 'FINALIZADA',
                    finished_at = NOW()
                WHERE id = ?
                    AND status = 'EN_CURSO'";

        return $this->database->execute($sql, [$gameId]);
    }

    public function abandonoPartida($gameId){
        $sql = "UPDATE game
                SET status = 'ABANDONADA',
                    finished_at = NOW()
                WHERE id = ?
                    AND status = 'EN_CURSO'";

        return $this->database->execute($sql, [$gameId]);
    }

    // ─────────────────────────────────────────────────────────────
    // CATEGORÍAS Y PREGUNTAS
    // ─────────────────────────────────────────────────────────────

    public function obtenerCategorias($gameId){
        // "preguntas" = preguntas que todavía no salieron en esta partida.
        // "total"    = preguntas cargadas en la categoría.
        // Si una pregunta se repitió, game_answers tiene más de una fila para
        // ella: por eso se cuenta con DISTINCT.
        $sql = "SELECT c.id, c.name,
                       COUNT(DISTINCT q.id) AS total,
                       COUNT(DISTINCT CASE WHEN ga.question_id IS NULL THEN q.id END) AS preguntas
                FROM category c
                LEFT JOIN questions q ON q.category_id = c.id
                LEFT JOIN (
                    SELECT DISTINCT question_id
                    FROM game_answers
                    WHERE game_id = ?
                ) ga ON ga.question_id = q.id
                GROUP BY c.id, c.name
                ORDER BY c.id";

        return $this->database->queryAll($sql, [$gameId]);
    }

    /**
     * Devuelve la pregunta menos usada de una categoría y dificultad exactas.
     *
     * - Si hay preguntas que todavía no salieron en la partida (0 usos),
     *   elige una de esas al azar.
     * - Si ya salieron todas, elige entre las que menos veces salieron:
     *   así el ciclo se reinicia y las repeticiones vienen mezcladas.
     *
     * Devuelve null solo si la categoría no tiene NINGUNA pregunta de esa
     * dificultad (el banco todavía no está calibrado para ese nivel).
     */
    public function obtenerPreguntaDeDificultad($categoriaId, $gameId, $dificultad){
        $expresion = Dificultad::sqlDificultad();

        $sql = "SELECT
                    q.id AS question_id,
                    q.question AS pregunta,
                    c.name AS categoria,
                    q.category_id,
                    {$expresion} AS dificultad
                FROM questions q
                INNER JOIN category c ON c.id = q.category_id
                LEFT JOIN question_stats qs ON qs.question_id = q.id
                LEFT JOIN (
                    SELECT question_id, COUNT(*) AS veces
                    FROM game_answers
                    WHERE game_id = ?
                    GROUP BY question_id
                ) usos ON usos.question_id = q.id
                WHERE q.category_id = ?
                    AND ({$expresion}) = ?
                ORDER BY COALESCE(usos.veces, 0) ASC, RAND()
                LIMIT 1";

        return $this->database->queryOne($sql, [(int) $gameId, (int) $categoriaId, (int) $dificultad]);
    }

    /**
     * Pregunta para el nivel del jugador dentro de una categoría.
     * Usa la dificultad exacta del nivel (básico=fácil, intermedio=medio,
     * experimentado=difícil). Si la categoría no tiene ninguna pregunta de
     * esa dificultad, recién ahí usa la más cercana para no trabar la
     * partida mientras el banco se termina de calibrar.
     */
    public function obtenerPreguntaParaJugador($categoriaId, $gameId, $nivelJugador){
        $nivelJugador = (int) $nivelJugador;
        $dificultad = Dificultad::dificultadDeNivel($nivelJugador);

        $pregunta = $this->obtenerPreguntaDeDificultad((int) $categoriaId, (int) $gameId, $dificultad);
        if ($pregunta !== null) {
            return $pregunta;
        }

        foreach (Dificultad::preferencia($nivelJugador) as $alternativa) {
            if ($alternativa === $dificultad) {
                continue;
            }

            $pregunta = $this->obtenerPreguntaDeDificultad((int) $categoriaId, (int) $gameId, $alternativa);
            if ($pregunta !== null) {
                return $pregunta;
            }
        }

        return null;
    }

    public function obtenerPreguntaPorId($questionId){
        $expresion = Dificultad::sqlDificultad();

        $sql = "SELECT
                    q.id AS question_id,
                    q.question AS pregunta,
                    c.name AS categoria,
                    q.category_id,
                    {$expresion} AS dificultad,
                    COALESCE(qs.times_delivered, 0) AS entregas,
                    COALESCE(qs.times_correct, 0) AS aciertos
                FROM questions q
                INNER JOIN category c ON c.id = q.category_id
                LEFT JOIN question_stats qs ON qs.question_id = q.id
                WHERE q.id = ?
                LIMIT 1";

        return $this->database->queryOne($sql, [$questionId]);
    }

    /**
     * Devuelve TODAS las respuestas de la pregunta, incluida is_correct.
     * Ese dato se queda en el servidor: nunca se manda a la vista.
     */
    public function obtenerRespuestasCompletas($questionId){
        $sql = "SELECT id, response, is_correct
                FROM response
                WHERE question_id = ?";

        return $this->database->queryAll($sql, [$questionId]);
    }

    public function obtenerRespuestaCorrecta($questionId){
        $sql = "SELECT id, response
                FROM response
                WHERE question_id = ?
                    AND is_correct = 1
                LIMIT 1";

        return $this->database->queryOne($sql, [$questionId]);
    }

    public function esRespuestaCorrecta($questionId, $responseId){
        $sql = "SELECT id
                FROM response
                WHERE id = ?
                    AND question_id = ?
                    AND is_correct = 1
                LIMIT 1";

        return $this->database->queryOne($sql, [$responseId, $questionId]) !== null;
    }

    // ─────────────────────────────────────────────────────────────
    // ESTADÍSTICAS: DIFICULTAD DE PREGUNTAS Y NIVEL DEL JUGADOR
    // ─────────────────────────────────────────────────────────────

    /**
     * Historial del jugador y su nivel actual.
     */
    public function obtenerEstadisticasJugador($userId){
        $sql = "SELECT COUNT(*) AS respondidas,
                       COALESCE(SUM(r.is_correct = 1), 0) AS correctas
                FROM game_answers ga
                INNER JOIN game g ON g.id = ga.game_id
                INNER JOIN response r ON r.id = ga.response_id
                WHERE g.user_id = ?";

        $fila = $this->database->queryOne($sql, [$userId]);

        $respondidas = (int) ($fila['respondidas'] ?? 0);
        $correctas = (int) ($fila['correctas'] ?? 0);
        $nivel = Dificultad::nivelJugador($respondidas, $correctas);

        return [
            'respondidas' => $respondidas,
            'correctas' => $correctas,
            'ratio' => $respondidas > 0 ? round($correctas / $respondidas, 2) : 0.0,
            'nivel' => $nivel,
            'etiqueta' => Dificultad::etiquetaJugador($nivel),
        ];
    }

    /**
     * Suma una entrega a la pregunta. Se llama una sola vez por turno,
     * no en cada render (así F5 no infla las estadísticas).
     */
    public function registrarEntrega($questionId){
        $sql = "INSERT INTO question_stats
                    (question_id, times_delivered, times_answered, times_correct, difficulty_level, points)
                VALUES (?, 1, 0, 0, ?, ?)
                ON DUPLICATE KEY UPDATE
                    times_delivered = times_delivered + 1";

        $this->ejecutarEstadistica($sql, [
            $questionId,
            Dificultad::MEDIO,
            Dificultad::puntos(Dificultad::MEDIO),
        ]);
    }

    /**
     * Registra que la pregunta fue respondida (bien o mal) y recalcula
     * su dificultad si ya salió de la calibración.
     */
    public function registrarRespuestaEstadistica($questionId, $esCorrecta){
        $acierto = $esCorrecta ? 1 : 0;

        $sql = "INSERT INTO question_stats
                    (question_id, times_delivered, times_answered, times_correct, difficulty_level, points)
                VALUES (?, 0, 1, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    times_answered = times_answered + 1,
                    times_correct = times_correct + ?";

        $this->ejecutarEstadistica($sql, [
            $questionId,
            $acierto,
            Dificultad::MEDIO,
            Dificultad::puntos(Dificultad::MEDIO),
            $acierto,
        ]);

        $this->recalcularDificultad($questionId);
    }

    private function recalcularDificultad($questionId){
        $sql = "SELECT times_delivered, times_correct
                FROM question_stats
                WHERE question_id = ?";

        $fila = $this->database->queryOne($sql, [$questionId]);
        if ($fila === null) {
            return;
        }

        $nivel = Dificultad::calcular((int) $fila['times_delivered'], (int) $fila['times_correct']);
        $puntos = Dificultad::puntos($nivel);

        $this->ejecutarEstadistica(
            "UPDATE question_stats SET difficulty_level = ?, points = ? WHERE question_id = ?",
            [$nivel, $puntos, $questionId]
        );

        $this->ejecutarEstadistica(
            "UPDATE questions SET difficulty_level = ?, points = ? WHERE id = ?",
            [$nivel, $puntos, $questionId]
        );
    }

    /**
     * Si falta la tabla question_stats, la partida sigue funcionando
     * (sin modo adaptativo) en lugar de romperse.
     */
    private function ejecutarEstadistica($sql, $params){
        try {
            $this->database->execute($sql, $params);
        } catch (PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1146) {
                Log::warning('Falta la tabla question_stats. Importá app/sql/migracion_dificultad_niveles.sql');
                return;
            }

            throw $exception;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // RESPUESTAS
    // ─────────────────────────────────────────────────────────────

    public function registrarRespuesta($gameId, $questionId, $responseId, $earnedPoints, $turnNumber, $responseTime = null){
        $sql = "INSERT INTO game_answers
                (game_id, question_id, response_id, earned_points, turn_number, response_time_seconds)
                VALUES (?, ?, ?, ?, ?, ?)";

        try {
            return $this->database->execute($sql, [
                $gameId,
                $questionId,
                $responseId,
                $earnedPoints,
                $turnNumber,
                $responseTime,
            ]);
        } catch (PDOException $exception) {
            // Un reenvío del mismo turno choca con uk_game_answers_turn:
            // no se modifica la respuesta original ni se suman puntos.
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                return 0;
            }

            throw $exception;
        }
    }
}
