<?php

namespace app\model;

class ModeradorModel
{
    private $database;

    private const MOTIVOS = [
        'RESPUESTA_INCORRECTA' => 'La respuesta correcta parece incorrecta',
        'AMBIGUA' => 'La pregunta es ambigua',
        'ERROR_TEXTO' => 'Hay un error de texto',
        'OTRO' => 'Otro problema',
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

    public function listarPreguntasReportadas($status, $orden)
    {
        $ordenes = [
            'mas_reportadas' => 'total_reportes DESC, primer_reporte ASC, q.id ASC',
            'mas_viejas' => 'primer_reporte ASC, q.id ASC',
            'mas_nuevas' => 'primer_reporte DESC, q.id DESC',
        ];
        $ordenSql = $ordenes[$orden] ?? $ordenes['mas_viejas'];

        $sql = "SELECT q.id AS question_id,
                       q.question,
                       c.name AS categoria,
                       COUNT(qr.id) AS total_reportes,
                       MIN(qr.created_at) AS primer_reporte,
                       motivos.por_motivo
                FROM question_reports qr
                INNER JOIN questions q ON q.id = qr.question_id
                INNER JOIN category c ON c.id = q.category_id
                LEFT JOIN (
                    SELECT agrupados.question_id,
                           GROUP_CONCAT(CONCAT(agrupados.reason, ':', agrupados.cantidad)
                                        ORDER BY agrupados.reason SEPARATOR ',') AS por_motivo
                    FROM (
                        SELECT question_id, reason, COUNT(*) AS cantidad
                        FROM question_reports
                        WHERE status = ?
                        GROUP BY question_id, reason
                    ) agrupados
                    GROUP BY agrupados.question_id
                ) motivos ON motivos.question_id = q.id
                WHERE qr.status = ?
                GROUP BY q.id, q.question, c.name, motivos.por_motivo
                ORDER BY {$ordenSql}";

        $preguntas = $this->database->queryAll($sql, [$status, $status]);
        foreach ($preguntas as &$pregunta) {
            $pregunta['total_reportes'] = (int) $pregunta['total_reportes'];
            $pregunta['motivos'] = $this->formatearMotivos($pregunta['por_motivo']);
        }
        unset($pregunta);

        return $preguntas;
    }

    public function obtenerDetallePregunta($questionId)
    {
        return $this->database->queryOne(
            "SELECT q.id AS question_id, q.question, c.name AS categoria
             FROM questions q
             INNER JOIN category c ON c.id = q.category_id
             WHERE q.id = ?
             LIMIT 1",
            [(int) $questionId]
        );
    }

    public function obtenerOpciones($questionId)
    {
        return $this->database->queryAll(
            "SELECT id, response, is_correct
             FROM response
             WHERE question_id = ?
             ORDER BY id
             LIMIT 4",
            [(int) $questionId]
        );
    }

    public function obtenerReportesPregunta($questionId)
    {
        $reportes = $this->database->queryAll(
            "SELECT qr.id, qr.reason, qr.detail, qr.status, qr.created_at,
                    u.Usuario AS usuario
             FROM question_reports qr
             INNER JOIN usuarios u ON u.Id = qr.user_id
             WHERE qr.question_id = ?
             ORDER BY qr.created_at DESC, qr.id DESC",
            [(int) $questionId]
        );

        foreach ($reportes as &$reporte) {
            $reporte['motivo_texto'] = self::MOTIVOS[$reporte['reason']] ?? 'Otro problema';
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
                 SET status = ?, resolved_by = ?, resolved_at = NOW(), resolution_note = ?
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

    private function formatearMotivos($motivos)
    {
        if (!is_string($motivos) || $motivos === '') {
            return [];
        }

        $formateados = [];
        foreach (explode(',', $motivos) as $motivo) {
            $partes = explode(':', $motivo, 2);
            if (count($partes) !== 2) {
                continue;
            }

            $formateados[] = [
                'texto' => self::MOTIVOS[$partes[0]] ?? 'Otro problema',
                'cantidad' => (int) $partes[1],
            ];
        }

        return $formateados;
    }
}
