<?php

namespace app\controller;

use app\model\ReportarPreguntaModel;
use Redirect;

class ReportarPreguntaController
{
    private const MOTIVOS = [
        'RESPUESTA_INCORRECTA',
        'AMBIGUA',
        'ERROR_TEXTO',
        'OTRO',
    ];

    private $model;

    public function __construct(ReportarPreguntaModel $model)
    {
        $this->model = $model;
    }

    public function crear()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Redirect::to('/Preguntados/partida/show');
        }

        if (!isset($_SESSION['usuario_id'])) {
            Redirect::to('/Preguntados/login/show');
        }

        $turno = $_SESSION['turno'] ?? null;
        if (!is_array($turno) || !isset($turno['question_id']) || !empty($_SESSION['resultado'])) {
            $this->guardarMensaje('No hay una pregunta activa para reportar.', 'error');
            Redirect::to('/Preguntados/partida/show');
        }

        $tokenSesion = $_SESSION['csrf_reportar_pregunta'] ?? '';
        $tokenEnviado = $_POST['csrf_token'] ?? '';
        if (!is_string($tokenSesion) || !is_string($tokenEnviado)
            || $tokenSesion === '' || !hash_equals($tokenSesion, $tokenEnviado)) {
            $this->guardarMensaje('No se pudo validar el formulario. Volvé a intentarlo.', 'error');
            Redirect::to('/Preguntados/partida/pregunta');
        }

        $questionIdEnviado = $_POST['question_id'] ?? null;
        if (!is_string($questionIdEnviado) || !preg_match('/\A[0-9]+\z/', $questionIdEnviado) || (int) $questionIdEnviado < 1) {
            $this->guardarMensaje('La pregunta enviada no es válida.', 'error');
            Redirect::to('/Preguntados/partida/pregunta');
        }

        $questionId = (int) $questionIdEnviado;
        if ($questionId !== (int) $turno['question_id'] || !$this->model->existePregunta($questionId)) {
            $this->guardarMensaje('Solo podés reportar la pregunta que estás viendo.', 'error');
            Redirect::to('/Preguntados/partida/pregunta');
        }

        $reason = $_POST['reason'] ?? '';
        if (!is_string($reason) || !in_array($reason, self::MOTIVOS, true)) {
            $this->guardarMensaje('Seleccioná un motivo válido para el reporte.', 'error');
            Redirect::to('/Preguntados/partida/pregunta');
        }

        $detail = $_POST['detail'] ?? '';
        if (!is_string($detail)) {
            $this->guardarMensaje('El detalle enviado no es válido.', 'error');
            Redirect::to('/Preguntados/partida/pregunta');
        }
        $detail = trim($detail);
        $cantidadCaracteres = preg_match_all('/./us', $detail);
        if ($cantidadCaracteres === false || $cantidadCaracteres > 500) {
            $this->guardarMensaje('El detalle debe ser texto válido y no superar los 500 caracteres.', 'error');
            Redirect::to('/Preguntados/partida/pregunta');
        }

        $userId = (int) $_SESSION['usuario_id'];
        if ($this->model->usuarioYaReporto($userId, $questionId)) {
            $this->guardarMensaje('Ya reportaste esta pregunta. ¡Gracias por avisarnos!', 'info');
            Redirect::to('/Preguntados/partida/pregunta');
        }

        $guardado = $this->model->guardarReporte(
            $userId,
            $questionId,
            $reason,
            $detail === '' ? null : $detail
        );

        if ($guardado) {
            $this->guardarMensaje('Gracias por tu reporte.', 'success');
        } else {
            $this->guardarMensaje('Ya reportaste esta pregunta. ¡Gracias por avisarnos!', 'info');
        }

        Redirect::to('/Preguntados/partida/pregunta');
    }

    private function guardarMensaje($texto, $tipo)
    {
        $_SESSION['mensaje_reporte_pregunta'] = [
            'texto' => $texto,
            'tipo' => $tipo,
        ];
    }
}
