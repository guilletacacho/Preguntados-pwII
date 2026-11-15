<?php

namespace app\model; 

class ModeradorModel{

    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function agregarPregunta($categoryId, $question, $difficultyLevel, $points){
        
        $sql = "INSERT INTO questions (category_id, question, difficulty_level, points)
            VALUES (?, ?, ?, ?)";

    $this->database->execute($sql, [
        $categoryId,
        $question,
        $difficultyLevel,
        $points
    ]);

    return (int) $this->database->lastInsertId();
    }
    
    public function agregarRespuesta($questionId, $response, $isCorrect){
        
        $sql = "INSERT INTO response (question_id, response, is_correct)
            VALUES (?, ?, ?)";
        
        return $this->database->execute($sql, [$questionId, $response, $isCorrect]);
    }

    public function eliminarPregunta(){
        return ;
    }
    
    // OBTIENE UNA PAGINA DE PREGUNTAS, CON UN LIMITE Y DESPLAZAMIENTO CONTROLADOS.
    public function obtenerPreguntas($limite = 10, $offset = 0){
        $limite = max(1, (int) $limite);
        $offset = max(0, (int) $offset);

        // USA LA MISMA DIFICULTAD EFECTIVA QUE APLICA LA PARTIDA.
        $expresionDificultad = \Dificultad::sqlDificultad();

        $sql = "SELECT
                    q.id,
                    q.question,
                    c.name AS categoria,
                    {$expresionDificultad} AS dificultad,
                    COALESCE(qs.times_delivered, 0) AS entregas,
                    CASE
                        WHEN COALESCE(qs.times_answered, 0) = 0 THEN 0
                        ELSE ROUND(100 * qs.times_correct / qs.times_answered)
                    END AS acierto
                FROM questions q
                INNER JOIN category c ON c.id = q.category_id
                LEFT JOIN question_stats qs ON qs.question_id = q.id
                ORDER BY q.id DESC
                LIMIT {$limite} OFFSET {$offset}";

        return $this->database->queryAll($sql);
    }

    // CUENTA TODAS LAS PREGUNTAS PARA MOSTRAR EL TOTAL REAL DE LA PAGINACION.
    public function contarPreguntas()
{
    $sql = "SELECT COUNT(*) AS total FROM questions";
    $fila = $this->database->queryOne($sql);

    return (int) ($fila['total'] ?? 0);
}

    public function buscarPreguntaPorId($id){
        return ;
    }

    public function obtenerCategorias(){

        $sql = "SELECT id, name FROM category ORDER BY name";

        return $this->database->queryAll($sql);
    }
}