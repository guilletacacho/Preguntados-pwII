<?php

namespace app\model;

use PDOException;

class ReportarPreguntaModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function existePregunta($questionId)
    {
        return $this->database->queryOne(
            "SELECT id FROM questions WHERE id = ? LIMIT 1",
            [$questionId]
        ) !== null;
    }

    public function usuarioYaReporto($userId, $questionId)
    {
        return $this->database->queryOne(
            "SELECT id FROM question_reports WHERE user_id = ? AND question_id = ? LIMIT 1",
            [$userId, $questionId]
        ) !== null;
    }

    public function guardarReporte($userId, $questionId, $reason, $detail)
    {
        try {
            return $this->database->execute(
                "INSERT INTO question_reports (question_id, user_id, reason, detail, status)
                 VALUES (?, ?, ?, ?, 'PENDIENTE')",
                [$questionId, $userId, $reason, $detail]
            ) > 0;
        } catch (PDOException $exception) {
            // El índice UNIQUE también protege contra dos envíos simultáneos.
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                return false;
            }

            throw $exception;
        }
    }
}
