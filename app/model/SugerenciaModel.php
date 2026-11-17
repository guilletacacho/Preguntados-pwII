<?php

namespace app\model;

class SugerenciaModel{

    private $database;

    public function __construct($database){
        $this->database = $database;
    }

    public function crearSugerencia($userId,$categoryId,$questionText){

        $sql = "INSERT INTO question_suggestions (user_id, category_id, question_text,status) VALUES (?, ?, ?,'PENDIENTE')";

        $this->database->execute($sql,[
            (int) $userId,
            (int) $categoryId,
            trim($questionText)
        ]);

        return (int) $this->database->lastInsertId();
   }
}