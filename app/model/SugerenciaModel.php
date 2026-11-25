<?php

namespace app\model;

class SugerenciaModel{

    private $database;

    public function __construct($database){
        $this->database = $database;
    }

    public function categoriaExiste($categoryId){

        $sql = "SELECT id FROM category WHERE id = ? AND is_active = 1";

        return $this->database->queryOne($sql,[$categoryId]) !== null;
    }

    public function crearSugerencia($userId, $categoryId, $questionText, $imageUrl = null){

        $userId = filter_var($userId, FILTER_VALIDATE_INT);
        $categoryId = filter_var($categoryId, FILTER_VALIDATE_INT);

        if ($userId === false || $userId < 1) {
            throw new \DomainException('La sesión de usuario no es válida.');
        }

        if ($categoryId === false || $categoryId < 1) {
            throw new \DomainException('Seleccioná una categoría válida.');
        }

        if (!$this->categoriaExiste($categoryId)) {
            throw new \DomainException('La categoría seleccionada no existe.');
        }

        if (!is_string($questionText)) {
            throw new \DomainException('La pregunta no es válida.');
        }

        $questionText = trim($questionText);

        if ($questionText === '' || mb_strlen($questionText, 'UTF-8') > 500) {
            throw new \DomainException(
                'La pregunta es obligatoria y admite hasta 500 caracteres.'
            );
        }

        if ($imageUrl !== null && (!is_string($imageUrl) || $imageUrl === '' || mb_strlen($imageUrl) > 255)) {
            throw new \DomainException('La referencia de imagen no es válida.');
        }

        $sql = "INSERT INTO question_suggestions (user_id, category_id, question_text, image_url, status)
                VALUES (?, ?, ?, ?, 'PENDIENTE')";

        $this->database->execute($sql, [
            $userId,
            $categoryId,
            $questionText,
            $imageUrl,
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
                qs.status AS estado,
                qs.image_url AS imagen,
                qs.resolution_notes AS motivo_rechazo
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
                c.name AS categoria,
                qs.image_url AS image_url
            FROM question_suggestions qs
            INNER JOIN category c ON c.id = qs.category_id
            WHERE qs.id = ?
            AND qs.status = 'PENDIENTE'
            LIMIT 1";

        return $this->database->queryAll($sql, [(int) $id]);
   }

   public function obtenerCategorias(){

        $sql = "SELECT id, name, color FROM category WHERE is_active = 1 ORDER BY name";

        return $this->database->queryAll($sql);
   }

   public function publicarSugerencia($id,$moderadorId, $datos){

        $datos = $this->validarDatosFinales($datos);

        $this->database->beginTransaction();

        try{
            $sugerencia = $this->verificarPendiente($id);

            $questionId = $this->insertarPregunta(
                $datos['category_id'],
                $datos['question'],
                $datos['difficulty_level'],
                $datos['image_url'] ?? ($sugerencia['image_url'] ?? null)
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

   public function contarPreguntasAprobadas()
    {
        $fila = $this->database->queryOne(
            "SELECT COUNT(*) AS total
             FROM question_suggestions
             WHERE status = 'APROBADA' AND published_question_id IS NOT NULL"
        );

        return (int) ($fila['total'] ?? 0);
    }

    public function obtenerPreguntasAprobadasPaginadas($pagina, $porPagina)
    {
        $pagina = max(1, (int) $pagina);
        $porPagina = max(1, min((int) $porPagina, 10));
        $offset = ($pagina - 1) * $porPagina;

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
                LIMIT {$porPagina} OFFSET {$offset}";

        return $this->database->queryAll($sql);
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

   private function insertarPregunta($categoryId,$question,$difficulty,$imageUrl){

        $sql = "INSERT INTO questions (category_id, question, difficulty_level, points, image_url)
            VALUES (?, ?, ?, 1, ?)";

        $this->database->execute($sql,[$categoryId,$question,$difficulty,$imageUrl]);

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
            "SELECT id, image_url
            FROM question_suggestions
            WHERE id = ? AND status = 'PENDIENTE'
            FOR UPDATE",
            [(int) $id]
        );

        if ($sugerencia === null) {
            throw new \DomainException('La sugerencia no existe o ya fue revisada.');
        }

        return $sugerencia;
    }

    private function validarDatosFinales(array $datos)
    {
        $categoryId = filter_var(
            $datos['category_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        // Las sugerencias aprobadas arrancan en media y se recalculan solas.
        $difficulty = 2;

        $correctIndex = filter_var(
            $datos['correct_response'] ?? null,
            FILTER_VALIDATE_INT
        );

        $question = $datos['question'] ?? null;
        $responses = $datos['responses'] ?? null;

        if ($categoryId === false || $categoryId < 1) {
            throw new \DomainException('Seleccioná una categoría válida.');
        }
        if (!$this->categoriaExiste($categoryId)) {
            throw new \DomainException('La categoría seleccionada no existe.');
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

        $imageUrl = $datos['image_url'] ?? null;
        if ($imageUrl !== null && (!is_string($imageUrl) || $imageUrl === '' || mb_strlen($imageUrl) > 255)) {
            throw new \DomainException('La imagen seleccionada no es válida.');
        }
        $datos['image_url'] = $imageUrl;

        return $datos;
    }
}
