<?php
namespace app\model;

class PartidaModel{
    
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function obtenerPreguntaAleatoria(){

        $sql = "SELECT 
                    q.id AS question_id,
                    q.question AS pregunta,
                    c.name AS categoria
                FROM questions q
                INNER JOIN category c ON c.id = q.category_id
                ORDER BY RAND()
                LIMIT 1";

        return $this->database->queryOne($sql);
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

    public function registrarRespuesta($gameId,$questionId,$responseId,$earnedPoints){
        $sql = "INSERT INTO game_answers
                (game_id, question_id, response_id, earned_points)
                VALUES (?, ?, ?, ?)";

        return $this->database->execute($sql,[$gameId,$questionId,$responseId,$earnedPoints]);
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