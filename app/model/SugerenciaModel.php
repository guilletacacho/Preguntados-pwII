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

   public function mostrarPreguntasSugeridas($estado, $categoriaId = null){

        $sql = "SELECT
                qs.id,
                COALESCE(q.question, qs.question_text) AS pregunta,
                u.Usuario AS usuario,
                qs.created_at AS fecha,
                c.name AS categoria,
                qs.status AS estado
            FROM question_suggestions qs
            INNER JOIN usuarios u ON u.Id = qs.user_id
            LEFT JOIN questions q ON q.id = qs.published_question_id
            INNER JOIN category c
                ON c.id = COALESCE(q.category_id, qs.category_id)
            WHERE qs.status = ?";

        $parametros = [$estado];

        if ($categoriaId !== null && $categoriaId !== '') {
            $sql .= " AND COALESCE(q.category_id, qs.category_id) = ?";
            $parametros[] = (int) $categoriaId;
        }

        $sql .= " ORDER BY qs.created_at DESC";


        return $this->database->queryAll($sql,$parametros);
   }

   public function obtenerSugerenciaPorId($id){

        $sql = "SELECT
                qs.id,
                qs.question_text AS pregunta,
                qs.category_id AS categoria_id,
                c.name AS categoria
            FROM question_suggestions qs
            INNER JOIN category c ON c.id = qs.category_id
            WHERE qs.id = ?
            AND qs.status = 'PENDIENTE'
            LIMIT 1";

        return $this->database->queryAll($sql, [(int) $id]);
   }

   public function obtenerCategorias(){

        $sql = "SELECT id, name FROM category";

        return $this->database->queryAll($sql);
   }

   public function publicarSugerencia($id,$moderadorId, $datos){

        $datos = $this->validarDatosFinales($datos);

        $this->database->beginTransaction();

        try{
            $this->verificarPendiente($id);

            $questionId = $this->insertarPregunta(
                $datos['category_id'],
                $datos['question'],
                $datos['difficulty_level']
            );

            $this->insertarRespuestas(
                $questionId,
                $datos['responses'],
                $datos['correct_response']
            );

            $this->marcarAprobada($id,$moderadorId,$questionId);

            $this->database->commit();

            return $questionId;
        } catch (\Throwable $error){
            if ($this->database->inTransaction()){
                $this->database->rollBack();
            }
            throw $error;
        }

   }

   public function rechazarSugerencia($id,$moderadorId,$nota){

        $nota = trim($nota);

        if ($nota === '' || mb_strlen($nota) > 500) {
            throw new \DomainException(
                'Escribí un motivo de rechazo de hasta 500 caracteres.'
            );
        } 

        $sql = "UPDATE question_suggestions
            SET status = 'RECHAZADA',
                reviewed_by = ?,
                reviewed_at = CURRENT_TIMESTAMP,
                resolution_notes = ?
            WHERE id = ? AND status = 'PENDIENTE'";

        return $this->database->execute($sql, [(int) $moderadorId, $nota, (int) $id]) === 1;

   }

   public function obtenerUltimasPreguntasAprobadas($limite = 10){

        $limite = max(1, min((int) $limite, 10));
   
        $sql = "SELECT
                q.question AS pregunta,
                c.name AS categoria,
                u.Usuario AS usuario
            FROM question_suggestions qs
            INNER JOIN questions q
                ON q.id = qs.published_question_id
            INNER JOIN category c
                ON c.id = q.category_id
            INNER JOIN usuarios u
                ON u.Id = qs.user_id
            WHERE qs.status = 'APROBADA'
              AND qs.published_question_id IS NOT NULL
            ORDER BY qs.reviewed_at DESC, qs.id DESC
            LIMIT {$limite}";

        return $this->database->queryAll($sql);
    }

    //METODOS PRIVADOS 

    private function insertarPregunta($categoryId,$question,$difficulty){

        $sql = "INSERT INTO questions (category_id, question, difficulty_level, points)
            VALUES (?, ?, ?, 1)";

        $this->database->execute($sql,[$categoryId,$question,$difficulty]);

        return (int) $this->database->lastInsertId();

    }

    private function insertarRespuestas($questionId, array $responses, $correctIndex)
    {
        $sql = "INSERT INTO response (question_id, response, is_correct)
                VALUES (?, ?, ?)";

        foreach ($responses as $index => $response) {
            $this->database->execute($sql, [
                $questionId,
                trim($response),
                (int) ($index === (int) $correctIndex),
            ]);
        }
    }

    private function marcarAprobada($id,$moderadorId,$questionId){
        
        $sql = "UPDATE question_suggestions
            SET status = 'APROBADA',
                reviewed_by = ?,
                reviewed_at = CURRENT_TIMESTAMP,
                published_question_id = ?
            WHERE id = ? AND status = 'PENDIENTE'";

        $actualizadas = $this->database->execute(
            $sql,
            [(int) $moderadorId,$questionId,(int) $id]
        );

        if ($actualizadas !== 1){
            throw new \DomainException('La sugerencia ya no está pendiente.');
        }
    }

    private function verificarPendiente($id)
    {
        $sugerencia = $this->database->queryOne(
            "SELECT id
            FROM question_suggestions
            WHERE id = ? AND status = 'PENDIENTE'
            FOR UPDATE",
            [(int) $id]
        );

        if ($sugerencia === null) {
            throw new \DomainException('La sugerencia no existe o ya fue revisada.');
        }
    }

    private function validarDatosFinales(array $datos)
    {
        $categoryId = filter_var(
            $datos['category_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $difficulty = filter_var(
            $datos['difficulty_level'] ?? null,
            FILTER_VALIDATE_INT
        );

        $correctIndex = filter_var(
            $datos['correct_response'] ?? null,
            FILTER_VALIDATE_INT
        );

        $question = $datos['question'] ?? null;
        $responses = $datos['responses'] ?? null;

        if ($categoryId === false || $categoryId < 1) {
            throw new \DomainException('Seleccioná una categoría válida.');
        }

        if (!in_array($difficulty, [1, 2, 3], true)) {
            throw new \DomainException('Seleccioná una dificultad válida.');
        }

        if (!is_string($question)) {
            throw new \DomainException('La pregunta no es válida.');
        }

        $question = trim($question);

        if ($question === '' || mb_strlen($question) > 500) {
            throw new \DomainException('La pregunta es obligatoria y admite hasta 500 caracteres.');
        }

        if (!is_array($responses) || count($responses) !== 4) {
            throw new \DomainException('Completá las cuatro respuestas.');
        }

        $responses = array_values($responses);

        foreach ($responses as &$response) {
            if (!is_string($response)) {
                throw new \DomainException('Las respuestas no son válidas.');
            }

            $response = trim($response);

            if ($response === '' || mb_strlen($response) > 255) {
                throw new \DomainException('Cada respuesta es obligatoria y admite hasta 255 caracteres.');
            }
        }
        unset($response);

        $normalizadas = array_map(
            static function ($response) {
                $response = preg_replace('/\s+/u', ' ', $response);
                return mb_strtolower($response, 'UTF-8');
            },
            $responses
        );

        if (count(array_unique($normalizadas)) !== 4) {
            throw new \DomainException('Las cuatro respuestas deben ser distintas.');
        }

        if ($correctIndex === false || $correctIndex < 0 || $correctIndex > 3) {
            throw new \DomainException('Marcá una respuesta correcta.');
        }

        $datos['category_id'] = $categoryId;
        $datos['question'] = $question;
        $datos['difficulty_level'] = $difficulty;
        $datos['responses'] = $responses;
        $datos['correct_response'] = $correctIndex;

        return $datos;
    }
}
