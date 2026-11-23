<?php

namespace app\model;

class ModeradorModel
{
    private $database;

    private const MOTIVOS = [
        'respuesta_incorrecta' => 'La respuesta correcta parece incorrecta',
        'varias_validas' => 'Hay más de una respuesta válida',
        'confusa' => 'La pregunta o las opciones son confusas',
        'desactualizada' => 'La información está desactualizada',
        'inapropiada' => 'El contenido es inapropiado',
        'otro' => 'Otro motivo',
    ];

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function esModerador($userId)
    {
        $fila = $this->database->queryOne(
            "SELECT rol FROM usuarios WHERE Id = ? LIMIT 1",
            [(int) $userId]
        );

        return $fila !== null && $fila['rol'] === 'MODERADOR';
    }

    public function contarReportesPendientes()
    {
        $fila = $this->database->queryOne(
            "SELECT COUNT(*) AS cantidad FROM question_reports WHERE status = 'PENDIENTE'"
        );

        return (int) ($fila['cantidad'] ?? 0);
    }

    public function contarReportesResueltos(){
        $sql = "SELECT COUNT(*) AS cantidad FROM question_reports WHERE status = 'RESUELTO'";

        $fila = $this->database->queryOne($sql);

        return (int) ($fila['cantidad'] ?? 0);
    }
    public function listarPreguntasReportadas($status, $orden)
    {
        $ordenes = [
            'mas_reportadas' => static function ($a, $b) {
                return $b['total_reportes'] <=> $a['total_reportes']
                    ?: strcmp($a['primer_reporte'], $b['primer_reporte'])
                    ?: $a['question_id'] <=> $b['question_id'];
            },
            'mas_viejas' => static function ($a, $b) {
                return strcmp($a['primer_reporte'], $b['primer_reporte'])
                    ?: $a['question_id'] <=> $b['question_id'];
            },
            'mas_nuevas' => static function ($a, $b) {
                return strcmp($b['primer_reporte'], $a['primer_reporte'])
                    ?: $b['question_id'] <=> $a['question_id'];
            },
        ];
        $comparar = $ordenes[$orden] ?? $ordenes['mas_viejas'];

        $filas = $this->database->queryAll(
            "SELECT qr.question_id, q.question, c.name AS categoria,
                    qr.reasons, qr.question_snapshot, qr.created_at
             FROM question_reports qr
             LEFT JOIN questions q ON q.id = qr.question_id
             LEFT JOIN category c ON c.id = q.category_id
             WHERE qr.status = ?",
            [$status]
        );

        $preguntas = [];
        foreach ($filas as $fila) {
            $questionId = (int) $fila['question_id'];
            if (!isset($preguntas[$questionId])) {
                $snapshot = $this->decodificarJson($fila['question_snapshot']);
                $preguntas[$questionId] = [
                    'question_id' => $questionId,
                    'question' => $fila['question'] ?: ($snapshot['pregunta'] ?? 'Pregunta eliminada'),
                    'categoria' => $fila['categoria'] ?: ($snapshot['categoria'] ?? 'Sin categoría'),
                    'total_reportes' => 0,
                    'primer_reporte' => $fila['created_at'],
                    'conteo_motivos' => [],
                ];
            }

            $preguntas[$questionId]['total_reportes']++;
            if (strcmp($fila['created_at'], $preguntas[$questionId]['primer_reporte']) < 0) {
                $preguntas[$questionId]['primer_reporte'] = $fila['created_at'];
            }
            foreach ($this->codigosMotivos($fila['reasons']) as $codigo) {
                $preguntas[$questionId]['conteo_motivos'][$codigo] =
                    ($preguntas[$questionId]['conteo_motivos'][$codigo] ?? 0) + 1;
            }
        }

        foreach ($preguntas as &$pregunta) {
            $pregunta['motivos'] = [];
            foreach ($pregunta['conteo_motivos'] as $codigo => $cantidad) {
                $pregunta['motivos'][] = [
                    'texto' => self::MOTIVOS[$codigo] ?? 'Otro motivo',
                    'cantidad' => $cantidad,
                ];
            }
            unset($pregunta['conteo_motivos']);
        }
        unset($pregunta);

        $preguntas = array_values($preguntas);
        usort($preguntas, $comparar);

        return $preguntas;
    }

    public function obtenerDetallePregunta($questionId)
    {
        return $this->database->queryOne(
            "SELECT q.id AS question_id, q.question, q.category_id, q.image_url, c.name AS categoria
             FROM questions q
             INNER JOIN category c ON c.id = q.category_id
             WHERE q.id = ?
             LIMIT 1",
            [(int) $questionId]
        );
    }

    public function listarCategorias()
    {
        return $this->database->queryAll(
            "SELECT id, name FROM category ORDER BY name"
        );
    }

    public function obtenerOpciones($questionId)
    {
        return $this->database->queryAll(
            "SELECT id, response, is_correct
             FROM response
             WHERE question_id = ?
             ORDER BY id",
            [(int) $questionId]
        );
    }

    public function obtenerReportesPregunta($questionId)
    {
        $reportes = $this->database->queryAll(
            "SELECT qr.id, qr.reasons, qr.comment, qr.question_snapshot, qr.status,
                    qr.created_at, qr.reviewed_by, qr.reviewed_at, qr.resolution_notes,
                    u.Usuario AS usuario, reviewer.Usuario AS revisor
             FROM question_reports qr
             INNER JOIN usuarios u ON u.Id = qr.user_id
             LEFT JOIN usuarios reviewer ON reviewer.Id = qr.reviewed_by
             WHERE qr.question_id = ?
             ORDER BY qr.created_at DESC, qr.id DESC",
            [(int) $questionId]
        );

        foreach ($reportes as &$reporte) {
            $reporte['motivos'] = [];
            foreach ($this->codigosMotivos($reporte['reasons']) as $codigo) {
                $reporte['motivos'][] = [
                    'texto' => self::MOTIVOS[$codigo] ?? 'Otro motivo',
                ];
            }
            $reporte['question_snapshot'] = $this->decodificarJson($reporte['question_snapshot']);
            $reporte['hay_snapshot'] = $reporte['question_snapshot'] !== null;
        }
        unset($reporte);

        return $reportes;
    }

    public function tieneReportesPendientes($questionId)
    {
        return $this->database->queryOne(
            "SELECT id
             FROM question_reports
             WHERE question_id = ? AND status = 'PENDIENTE'
             LIMIT 1",
            [(int) $questionId]
        ) !== null;
    }

    public function actualizarReportesPendientes($questionId, $status, $moderadorId, $nota)
    {
        if (!in_array($status, ['RESUELTO', 'DESCARTADO'], true)) {
            throw new \InvalidArgumentException('Estado de resolución no válido.');
        }

        $this->database->beginTransaction();
        try {
            $actualizados = $this->database->execute(
                "UPDATE question_reports
                 SET status = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP,
                     resolution_notes = ?
                 WHERE question_id = ? AND status = 'PENDIENTE'",
                [$status, (int) $moderadorId, $nota, (int) $questionId]
            );

            if ($actualizados < 1) {
                $this->database->rollBack();
                return false;
            }

            $this->database->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function corregirPregunta($questionId, $categoryId, $question, array $responses, $correctResponseId, $moderadorId, $nota)
    {
        $this->database->beginTransaction();
        try {
            $pregunta = $this->database->queryOne(
                "SELECT id FROM questions WHERE id = ? LIMIT 1",
                [(int) $questionId]
            );
            $categoria = $this->database->queryOne(
                "SELECT id FROM category WHERE id = ? LIMIT 1",
                [(int) $categoryId]
            );
            $opcionesActuales = $this->database->queryAll(
                "SELECT id FROM response WHERE question_id = ? ORDER BY id",
                [(int) $questionId]
            );

            if ($pregunta === null || $categoria === null) {
                throw new \DomainException('La pregunta o la categoría seleccionada ya no existe.');
            }
            if (count($opcionesActuales) !== 4 || count($responses) !== 4) {
                throw new \DomainException('La pregunta debe tener exactamente cuatro respuestas.');
            }

            $idsActuales = array_map('intval', array_column($opcionesActuales, 'id'));
            $idsEnviados = [];
            $respuestasNormalizadas = [];
            foreach ($responses as $respuesta) {
                if (!is_array($respuesta) || !isset($respuesta['id'], $respuesta['response'])
                    || !is_string($respuesta['response'])) {
                    throw new \DomainException('Los datos de las respuestas no son válidos.');
                }

                $id = filter_var($respuesta['id'], FILTER_VALIDATE_INT);
                $texto = trim($respuesta['response']);
                $longitud = preg_match_all('/./us', $texto);
                if ($id === false || $id < 1 || $texto === '' || $longitud === false || $longitud > 255) {
                    throw new \DomainException('Cada respuesta debe tener un texto válido de hasta 255 caracteres.');
                }

                $normalizada = mb_strtolower(preg_replace('/\s+/u', ' ', $texto), 'UTF-8');
                if (isset($respuestasNormalizadas[$normalizada])) {
                    throw new \DomainException('Las cuatro respuestas deben ser distintas.');
                }

                $idsEnviados[] = (int) $id;
                $respuestasNormalizadas[$normalizada] = true;
            }

            sort($idsActuales);
            $idsUnicos = array_values(array_unique($idsEnviados));
            sort($idsUnicos);
            if (count($idsEnviados) !== 4 || $idsUnicos !== $idsActuales) {
                throw new \DomainException('Las respuestas enviadas no pertenecen a esta pregunta.');
            }

            $correctResponseId = filter_var($correctResponseId, FILTER_VALIDATE_INT);
            if ($correctResponseId === false || !in_array((int) $correctResponseId, $idsActuales, true)) {
                throw new \DomainException('Debés marcar exactamente una respuesta correcta.');
            }

            $question = is_string($question) ? trim($question) : '';
            $questionLength = preg_match_all('/./us', $question);
            $nota = is_string($nota) ? trim($nota) : '';
            $notaLength = preg_match_all('/./us', $nota);
            if ($question === '' || $questionLength === false || $questionLength > 500) {
                throw new \DomainException('El enunciado debe tener texto válido de hasta 500 caracteres.');
            }
            if ($nota === '' || $notaLength === false || $notaLength > 500) {
                throw new \DomainException('La nota de resolución es obligatoria y debe tener hasta 500 caracteres.');
            }

            $this->database->execute(
                "UPDATE questions SET question = ?, category_id = ? WHERE id = ?",
                [$question, (int) $categoryId, (int) $questionId]
            );

            foreach ($responses as $respuesta) {
                $responseId = (int) $respuesta['id'];
                $this->database->execute(
                    "UPDATE response
                     SET response = ?, is_correct = ?
                     WHERE id = ? AND question_id = ?",
                    [
                        trim($respuesta['response']),
                        $responseId === (int) $correctResponseId ? 1 : 0,
                        $responseId,
                        (int) $questionId,
                    ]
                );
            }

            $opcionesGuardadas = $this->database->queryAll(
                "SELECT id, is_correct FROM response WHERE question_id = ? ORDER BY id",
                [(int) $questionId]
            );
            $idsGuardados = array_map('intval', array_column($opcionesGuardadas, 'id'));
            $idsEsperados = $idsActuales;
            sort($idsGuardados);
            sort($idsEsperados);
            $correctasGuardadas = array_sum(array_map('intval', array_column($opcionesGuardadas, 'is_correct')));
            $idsCorrectosGuardados = array_map(
                'intval',
                array_column(array_filter($opcionesGuardadas, static function ($opcion) {
                    return (int) $opcion['is_correct'] === 1;
                }), 'id')
            );
            if (count($opcionesGuardadas) !== 4 || $idsGuardados !== $idsEsperados
                || $correctasGuardadas !== 1 || $idsCorrectosGuardados !== [(int) $correctResponseId]) {
                throw new \DomainException('No se pudieron guardar las cuatro respuestas y su opción correcta.');
            }

            $actualizados = $this->database->execute(
                "UPDATE question_reports
                 SET status = 'RESUELTO', reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP,
                     resolution_notes = ?
                 WHERE question_id = ? AND status = 'PENDIENTE'",
                [(int) $moderadorId, $nota, (int) $questionId]
            );
            if ($actualizados < 1) {
                throw new \DomainException('La pregunta ya no tiene reportes pendientes para resolver.');
            }

            $this->database->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    private function codigosMotivos($reasons)
    {
        $codigos = $this->decodificarJson($reasons);
        if (!is_array($codigos)) {
            return [];
        }

        return array_values(array_filter($codigos, static function ($codigo) {
            return is_string($codigo) && isset(self::MOTIVOS[$codigo]);
        }));
    }

    private function decodificarJson($valor)
    {
        if (!is_string($valor) || $valor === '') {
            return null;
        }

        $decodificado = json_decode($valor, true);
        return json_last_error() === JSON_ERROR_NONE && is_array($decodificado)
            ? $decodificado
            : null;
    }

    public function agregarPregunta($categoryId, $question, $difficultyLevel, $points, $imageUrl = null){
        
        $sql = "INSERT INTO questions (category_id, question, difficulty_level, points, image_url)
            VALUES (?, ?, ?, ?, ?)";

    $this->database->execute($sql, [
        $categoryId,
        $question,
        $difficultyLevel,
        $points,
        $imageUrl
    ]);

    return (int) $this->database->lastInsertId();
    }

    public function crearPreguntaConRespuestas($categoryId, $question, $difficultyLevel, $points, array $responses, $imageUrl = null)
    {
        $this->database->beginTransaction();
        try {
            $questionId = $this->agregarPregunta(
                $categoryId,
                $question,
                $difficultyLevel,
                $points,
                $imageUrl
            );
            if ($questionId < 1) {
                throw new \RuntimeException('No se pudo crear la pregunta.');
            }

            foreach ($responses as $response) {
                $this->agregarRespuesta(
                    $questionId,
                    $response['text'],
                    $response['is_correct']
                );
            }

            $this->database->commit();
            return $questionId;
        } catch (\Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }
    
    public function agregarRespuesta($questionId, $response, $isCorrect){
        
        $sql = "INSERT INTO response (question_id, response, is_correct)
            VALUES (?, ?, ?)";
        
        return $this->database->execute($sql, [$questionId, $response, $isCorrect]);
    }

    public function desactivarPregunta($id){

        $sql = "UPDATE questions SET is_active = 0 WHERE id = ? AND is_active = 1";
        
        return $this->database->execute($sql, [$id]);
    }

    public function reactivarPregunta( $id){

        $sql = "UPDATE questions SET is_active = 1 WHERE id = ? AND is_active = 0";

        return $this->database->execute($sql, [$id]);
    }
    
    public function actualizarPregunta($questionId, $categoryId, $question, array $responses, $correctResponseId, $imageUrl = null, $removeImage = false){

        $this->database->beginTransaction();
        try {
            $pregunta = $this->database->queryOne(
                "SELECT image_url FROM questions WHERE id = ? FOR UPDATE",
                [(int) $questionId]
            );
            if ($pregunta === null) {
                throw new \DomainException('La pregunta ya no existe.');
            }

            $imagenAnterior = $pregunta['image_url'] ?? null;
            if ($imageUrl !== null || $removeImage) {
                $imagenActualizada = $imageUrl !== null ? $imageUrl : null;
                $this->database->execute(
                    "UPDATE questions SET category_id = ?, question = ?, image_url = ? WHERE id = ?",
                    [(int) $categoryId, $question, $imagenActualizada, (int) $questionId]
                );
            } else {
                $this->database->execute(
                    "UPDATE questions SET category_id = ?, question = ? WHERE id = ?",
                    [(int) $categoryId, $question, (int) $questionId]
                );
            }

            foreach ($responses as $response) {
                $responseId = (int) $response['id'];
                $this->database->execute(
                    "UPDATE response SET response = ?, is_correct = ? WHERE id = ? AND question_id = ?",
                    [
                        trim($response['response']),
                        $responseId === (int) $correctResponseId ? 1 : 0,
                        $responseId,
                        (int) $questionId,
                    ]
                );
            }

            $this->database->commit();
            return $imagenAnterior;
        } catch (\Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function imagenReferenciada($filename)
    {
        $referencias = $this->database->queryOne(
            "SELECT (
                (SELECT COUNT(*) FROM questions WHERE image_url = ?)
                + (SELECT COUNT(*) FROM question_suggestions WHERE image_url = ?)
            ) AS total",
            [$filename, $filename]
        );

        return (int) ($referencias['total'] ?? 0) > 0;
    }

    // OBTIENE UNA PAGINA DE PREGUNTAS, CON UN LIMITE Y DESPLAZAMIENTO CONTROLADOS.
    public function obtenerPreguntas($limite = 10, $offset = 0, $categoriaId = null, $dificultad = null, $estado = null){
        $limite = max(1, (int) $limite);
        $offset = max(0, (int) $offset);

        $filtros = $this->construirFiltrosPreguntas(
        $categoriaId,
        $dificultad,
        $estado
        );
        
        // USA LA MISMA DIFICULTAD EFECTIVA QUE APLICA LA PARTIDA.
        $expresionDificultad = \Dificultad::sqlDificultad();

        $sql = "SELECT
                    q.id,
                    q.is_active AS activa,
                    q.question,
                    c.name AS categoria,
                    c.color AS categoria_color,
                    {$expresionDificultad} AS dificultad,
                    COALESCE(qs.times_delivered, 0) AS entregas,
                    CASE
                        WHEN COALESCE(qs.times_answered, 0) = 0 THEN 0
                        ELSE ROUND(100 * qs.times_correct / qs.times_answered)
                    END AS acierto
                FROM questions q
                INNER JOIN category c ON c.id = q.category_id
                LEFT JOIN question_stats qs ON qs.question_id = q.id
                {$filtros['where']}
                ORDER BY q.id ASC
                LIMIT {$limite} OFFSET {$offset}";

        return $this->database->queryAll($sql,$filtros['parametros']);
    }

    // CUENTA TODAS LAS PREGUNTAS PARA MOSTRAR EL TOTAL REAL DE LA PAGINACION.
    public function contarPreguntas($categoriaId = null, $dificultad = null, $estado = null)
    {

    $filtros = $this->construirFiltrosPreguntas(
        $categoriaId,
        $dificultad,
        $estado
    );

    $sql = "SELECT COUNT(q.id) AS total
            FROM questions q
            LEFT JOIN question_stats qs ON qs.question_id = q.id
            {$filtros['where']}";

    $fila = $this->database->queryOne($sql, $filtros['parametros']);

    return (int) ($fila['total'] ?? 0);
    }

    public function buscarPreguntaPorTexto($texto){

        $expresionDificultad = \Dificultad::sqlDificultad();

        $sql = "SELECT
                    q.id,
                    q.is_active AS activa,
                    q.question,
                    c.name AS categoria,
                    c.color AS categoria_color,
                    {$expresionDificultad} AS dificultad,
                    COALESCE(qs.times_delivered, 0) AS entregas,
                    CASE
                        WHEN COALESCE(qs.times_answered, 0) = 0 THEN 0
                        ELSE ROUND(100 * qs.times_correct / qs.times_answered)
                    END AS acierto
                FROM questions q
                INNER JOIN category c ON c.id = q.category_id
                LEFT JOIN question_stats qs ON qs.question_id = q.id
                WHERE q.question LIKE ?
                ORDER BY q.id ASC";

        $parametro = '%' . $texto . '%';
        return $this->database->queryAll($sql, [$parametro]);
    }

    public function listarCategoriasConDetalle(){

        $sql = "SELECT c.id, c.name, c.color, c.icono, COUNT(q.id) AS preguntas
                FROM category c
                LEFT JOIN questions q ON q.category_id = c.id AND q.is_active = 1
                GROUP BY c.id, c.name, c.color, c.icono
                ORDER BY c.name";

        return $this->database->queryAll($sql);
    }

    public function obtenerCategoria($id){

        $sql = "SELECT id, name, color, icono FROM category WHERE id = ? LIMIT 1";

        return $this->database->queryOne($sql, [(int) $id]);
    }

    public function existeNombreCategoria($nombre, $excluirId = 0){

        $sql = "SELECT id FROM category WHERE name = ? AND id <> ? LIMIT 1";

        return $this->database->queryOne($sql, [$nombre, (int) $excluirId]) !== null;
    }

    public function crearCategoria($nombre, $color, $icono){

        $sql = "INSERT INTO category (name, color, icono) VALUES (?, ?, ?)";
        $this->database->execute($sql, [$nombre, $color, $icono]);

        return (int) $this->database->lastInsertId();
    }

    public function actualizarCategoria($id, $nombre, $color, $icono){

        $sql = "UPDATE category SET name = ?, color = ?, icono = ? WHERE id = ?";

        return $this->database->execute($sql, [$nombre, $color, $icono, (int) $id]);
    }

    public function obtenerCategorias(){

        $sql = "SELECT id, name FROM category ORDER BY name";

        return $this->database->queryAll($sql);
    }

    public function obtenerUltimosReportes($limite = 4){

        $limite = max(1, (int) $limite);

        $sql = "SELECT qr.status, qr.reasons, qr.question_snapshot, qr.created_at,
                q.question, c.name AS categoria
                FROM question_reports qr
                LEFT JOIN questions q ON q.id = qr.question_id
                LEFT JOIN category c ON c.id = q.category_id
                ORDER BY qr.created_at DESC, qr.id DESC
                LIMIT {$limite}";

        $reportes = $this->database->queryAll($sql);

            foreach ($reportes as &$reporte) {
            $snapshot = $this->decodificarJson($reporte['question_snapshot']);

            $reporte['pregunta'] = $reporte['question']
                ?: ($snapshot['pregunta'] ?? 'Pregunta eliminada');
            $reporte['categoria'] = $reporte['categoria']
                ?: ($snapshot['categoria'] ?? 'Sin categoría');

            $motivos = [];
            foreach ($this->codigosMotivos($reporte['reasons']) as $codigo) {
                $motivos[] = self::MOTIVOS[$codigo] ?? 'Otro motivo';
            }
            $reporte['motivoResumen'] = implode(', ', $motivos);
        }
        unset($reporte);
        
        return $reportes;
    }
    private function construirFiltrosPreguntas($categoriaId, $dificultad, $estado)
    {
        $condiciones = [];
        $parametros = [];

        if ($categoriaId !== null && $categoriaId !== '') {
            $condiciones[] = "q.category_id = ?";
            $parametros[] = (int) $categoriaId;
        }

        if ($dificultad !== null && $dificultad !== '') {
            $expresionDificultad = \Dificultad::sqlDificultad();
            $condiciones[] = "({$expresionDificultad}) = ?";
            $parametros[] = (int) $dificultad;
        }

        if ($estado === 'activa') {
            $condiciones[] = "q.is_active = 1";
        } elseif ($estado === 'inactiva') {
            $condiciones[] = "q.is_active = 0";
        }

        $where = empty($condiciones)
            ? ''
            : ' WHERE ' . implode(' AND ', $condiciones);

        return [
            'where' => $where,
            'parametros' => $parametros
        ];
    }
}
