<?php

namespace app\controller;

class PresenceController
{
    private $model;

    public function __construct($model)
    {
        $this->model = $model;
    }

    public function ping()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            echo json_encode(['error' => 'method']);
            return;
        }
        if (!isset($_SESSION['usuario_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'session']);
            return;
        }
        if (!isset($_SESSION['presence_token']) || !hash_equals($_SESSION['presence_token'], (string) ($_POST['token'] ?? ''))) {
            http_response_code(403);
            echo json_encode(['error' => 'token']);
            return;
        }
        $userId = $_SESSION['usuario_id'];
        $sessionId = session_id();
        $this->model->touch($userId, $sessionId);
        $online = $this->model->countOnline();
        session_write_close();
        echo json_encode(['online' => $online]);
    }
}
