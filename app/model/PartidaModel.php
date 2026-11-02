<?php
namespace app\model;

class PartidaModel{
    
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function obtenerCategorias($gameId){
        // "preguntas" cuenta solo las que todavía no se respondieron en esta partida.
        $sql = "SELECT c.id, c.name, COUNT(q.id) AS preguntas
                FROM category c
                LEFT JOIN questions q ON q.category_id = c.id
                    AND q.id NOT IN (SELECT ga.question_id FROM game_answers ga WHERE ga.game_id = ?)
                GROUP BY c.id, c.name
                ORDER BY c.id";

        return $this->database->queryAll($sql, [$gameId]);
    }

    public function preguntaYaRespondida($gameId, $questionId){
        $sql = "SELECT id
                FROM game_answers
                WHERE game_id = ? AND question_id = ?
                LIMIT 1";

        return $this->database->queryOne($sql, [$gameId, $questionId]) !== null;
    }

    public function obtenerPreguntaAleatoria($categoriaId, $gameId){

        $sql = "SELECT 
                    q.id AS question_id,
                    q.question AS pregunta,
                    c.name AS categoria
                FROM questions q
                INNER JOIN category c ON c.id = q.category_id
                WHERE (? IS NULL OR q.category_id = ?)
                    AND q.id NOT IN (SELECT ga.question_id FROM game_answers ga WHERE ga.game_id = ?)
                ORDER BY RAND()
                LIMIT 1";

        return $this->database->queryOne($sql, [$categoriaId, $categoriaId, $gameId]);
    }

    
    public function obtenerPreguntaPorId($questionId){
        $sql = "SELECT 
                    q.id AS question_id,
                    q.question AS pregunta,
                    c.name AS categoria
                FROM questions q
                INNER JOIN category c ON c.id = q.category_id
                WHERE q.id = ?
                LIMIT 1";   

        return $this->database->queryOne($sql,[$questionId]);
    }

    public function obtenerRespuestasPorPregunta($questionId){
        
        $sql = "SELECT 
                    id, response
                FROM response
                WHERE question_id = ?
                ORDER BY RAND()
                LIMIT 4";

        return $this->database->queryAll($sql, [$questionId]);
    }

    public function obtenerRespuestaCorrecta($questionId){
        
        $sql = "SELECT id,response
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

        return $this->database->queryOne($sql,[$responseId,$questionId]
        ) !== null;
    }

    public function obtenerPuntosPregunta($questionId){
        
        $sql = "SELECT points
                FROM questions
                WHERE id = ?
                LIMIT 1";

        return $this->database->queryOne($sql,[$questionId]);
    }

    public function registrarRespuesta($gameId,$questionId,$responseId,$earnedPoints,$turnNumber,$responseTime = null){
        $sql = "INSERT INTO game_answers
                (game_id, question_id, response_id, earned_points, turn_number, response_time_seconds)
                VALUES (?, ?, ?, ?, ?, ?)";

        try {
            return $this->database->execute($sql,[$gameId,$questionId,$responseId,$earnedPoints,$turnNumber,$responseTime]);
        } catch (\PDOException $exception) {
            // Un reenvío no debe modificar la respuesta original ni sumar puntos.
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062
                && strpos($exception->getMessage(), 'uk_game_answers_turn') !== false) {
                return 0;
            }
            throw $exception;
        }
    }

    public function actualizarPartida($totalPoints, $correctAnswers, $gameId){

        $sql = "UPDATE game
                SET total_points = ?,
                    correct_answers = ?
                WHERE id = ?";

        return $this->database->execute($sql,[$totalPoints, $correctAnswers, $gameId]);

    }

    
    public function iniciarPartida($userId){

        $sql = "INSERT INTO game (user_id)
                VALUES (?)";
        
        $this->database->execute($sql,[$userId]);

        return (int) $this->database->lastInsertId();
    }

    public function abandonoPartida($gameId){
        $sql = "UPDATE game
                SET status = 'ABANDONADA',
                finished_at = NOW()
                WHERE id = ?
                AND status = 'EN_CURSO'";

        return $this->database->execute($sql,[$gameId]);
    }

    public function finalizarPartida($gameId){

        $sql = "UPDATE game
                SET status = 'FINALIZADA',
                finished_at = NOW()
                WHERE id = ? 
                AND status = 'EN_CURSO'";

        return $this->database->execute($sql,[$gameId]);
    }
}
