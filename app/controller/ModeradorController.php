<?php

namespace app\controller;

use app\model\ModeradorModel;
use ModeradorAccess;
use Redirect;
use Request;

class ModeradorController
{
    private const ESTADOS = ['PENDIENTE', 'RESUELTO', 'DESCARTADO'];

    private $model;
    private $render;

    public function __construct(ModeradorModel $model, $render)
    {
        $this->model = $model;
        $this->render = $render;
    }

    public function show()
    {
        ModeradorAccess::exigir($this->model);

        $estado = Request::get('estado', 'PENDIENTE');
        if (!is_string($estado) || !in_array($estado, self::ESTADOS, true)) {
            $estado = 'PENDIENTE';
        }

        $orden = Request::get('orden', 'mas_viejas');
        if (!is_string($orden) || !in_array($orden, ['mas_reportadas', 'mas_viejas', 'mas_nuevas'], true)) {
            $orden = 'mas_viejas';
        }

        if (empty($_SESSION['csrf_moderador'])) {
            $_SESSION['csrf_moderador'] = bin2hex(random_bytes(32));
        }

        $preguntas = $this->model->listarPreguntasReportadas($estado, $orden);
        $questionId = Request::get('id');
        $detalle = null;
        if ($questionId !== null) {
            if (is_string($questionId) && preg_match('/\A[0-9]+\z/', $questionId) && (int) $questionId > 0) {
                $detalle = $this->armarDetalle((int) $questionId);
            }
        } elseif ($preguntas) {
            $detalle = $this->armarDetalle((int) $preguntas[0]['question_id']);
        }

        foreach ($preguntas as &$pregunta) {
            $pregunta['pluralReportes'] = (int) $pregunta['total_reportes'] !== 1;
            $pregunta['seleccionada'] = $detalle !== null
                && (int) $pregunta['question_id'] === (int) $detalle['question_id'];
            $pregunta['primer_reporte_formateado'] = $this->formatearFecha($pregunta['primer_reporte']);
        }
        unset($pregunta);

        $mensaje = $_SESSION['mensaje_moderador'] ?? null;
        unset($_SESSION['mensaje_moderador']);

        $this->render->renderiza('moderador', [
            'preguntas' => $preguntas,
            'hayPreguntas' => count($preguntas) > 0,
            'cantidadPreguntas' => count($preguntas),
            'detalle' => $detalle,
            'hayDetalle' => $detalle !== null,
            'estado' => $estado,
            'orden' => $orden,
            'estadoPendiente' => $estado === 'PENDIENTE',
            'estadoResuelto' => $estado === 'RESUELTO',
            'estadoDescartado' => $estado === 'DESCARTADO',
            'ordenMasReportadas' => $orden === 'mas_reportadas',
            'ordenMasViejas' => $orden === 'mas_viejas',
            'ordenMasNuevas' => $orden === 'mas_nuevas',
            'csrfToken' => $_SESSION['csrf_moderador'],
            'mensaje' => $mensaje,
            'hayMensaje' => $mensaje !== null,
            'pendientes' => $this->model->contarReportesPendientes(),
        ]);
    }

    public function detalle()
    {
        ModeradorAccess::exigir($this->model);

        $id = Request::get('id');
        if (!is_string($id) || !preg_match('/\A[0-9]+\z/', $id) || (int) $id < 1) {
            $this->guardarMensaje('La pregunta solicitada no es válida.', 'error');
            Redirect::to('/Preguntados/moderador');
        }

        if ($this->model->obtenerDetallePregunta((int) $id) === null
            || !$this->model->obtenerReportesPregunta((int) $id)) {
            $this->guardarMensaje('No se encontraron reportes para esa pregunta.', 'error');
            Redirect::to('/Preguntados/moderador');
        }

        $estado = Request::get('estado', 'PENDIENTE');
        if (!is_string($estado) || !in_array($estado, self::ESTADOS, true)) {
            $estado = 'PENDIENTE';
        }
        $orden = Request::get('orden', 'mas_viejas');
        if (!is_string($orden) || !in_array($orden, ['mas_reportadas', 'mas_viejas', 'mas_nuevas'], true)) {
            $orden = 'mas_viejas';
        }

        Redirect::to('/Preguntados/moderador?estado=' . $estado . '&orden=' . $orden . '&id=' . (int) $id);
    }

    public function resolver()
    {
        $this->procesarResolucion('RESUELTO');
    }

    public function descartar()
    {
        $this->procesarResolucion('DESCARTADO');
    }

    private function procesarResolucion($estado)
    {
        ModeradorAccess::exigir($this->model);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Redirect::to('/Preguntados/moderador');
        }

        $tokenSesion = $_SESSION['csrf_moderador'] ?? '';
        $tokenEnviado = $_POST['csrf_token'] ?? '';
        if (!is_string($tokenSesion) || !is_string($tokenEnviado)
            || $tokenSesion === '' || !hash_equals($tokenSesion, $tokenEnviado)) {
            $this->guardarMensaje('No se pudo validar el formulario. Volvé a intentarlo.', 'error');
            Redirect::to('/Preguntados/moderador');
        }

        $questionId = $_POST['question_id'] ?? null;
        if (!is_string($questionId) || !preg_match('/\A[0-9]+\z/', $questionId) || (int) $questionId < 1) {
            $this->guardarMensaje('La pregunta seleccionada no es válida.', 'error');
            Redirect::to('/Preguntados/moderador');
        }
        $questionId = (int) $questionId;

        $nota = $_POST['resolution_note'] ?? '';
        if (!is_string($nota)) {
            $this->guardarMensaje('La nota de resolución no es válida.', 'error');
            Redirect::to('/Preguntados/moderador');
        }
        $nota = trim($nota);
        $cantidadCaracteres = preg_match_all('/./us', $nota);
        if ($cantidadCaracteres === false || $cantidadCaracteres > 500) {
            $this->guardarMensaje('La nota debe ser texto válido y no superar los 500 caracteres.', 'error');
            Redirect::to('/Preguntados/moderador');
        }

        if ($this->model->obtenerDetallePregunta($questionId) === null
            || !$this->model->tieneReportesPendientes($questionId)) {
            $this->guardarMensaje('La pregunta no tiene reportes pendientes para actualizar.', 'error');
            Redirect::to('/Preguntados/moderador');
        }

        $actualizado = $this->model->actualizarReportesPendientes(
            $questionId,
            $estado,
            (int) $_SESSION['usuario_id'],
            $nota === '' ? null : $nota
        );

        if (!$actualizado) {
            $this->guardarMensaje('Los reportes ya no estaban pendientes. Actualizá la bandeja.', 'error');
            Redirect::to('/Preguntados/moderador');
        }

        $texto = $estado === 'RESUELTO' ? 'Reportes resueltos correctamente.' : 'Reportes descartados correctamente.';
        $this->guardarMensaje($texto, 'success');
        Redirect::to('/Preguntados/moderador?estado=' . $estado);
    }

    private function armarDetalle($questionId)
    {
        $detalle = $this->model->obtenerDetallePregunta($questionId);
        if ($detalle === null) {
            return null;
        }

        $reportes = $this->model->obtenerReportesPregunta($questionId);
        if (!$reportes) {
            return null;
        }

        $detalle['opciones'] = $this->model->obtenerOpciones($questionId);
        foreach ($detalle['opciones'] as &$opcion) {
            $opcion['correcta'] = (int) $opcion['is_correct'] === 1;
        }
        unset($opcion);

        foreach ($reportes as &$reporte) {
            $reporte['fecha_formateada'] = $this->formatearFecha($reporte['created_at']);
            $reporte['esPendiente'] = $reporte['status'] === 'PENDIENTE';
        }
        unset($reporte);

        $detalle['reportes'] = $reportes;
        $detalle['hayPendientes'] = $this->model->tieneReportesPendientes($questionId);
        $detalle['hayOpciones'] = count($detalle['opciones']) > 0;
        return $detalle;
    }

    private function formatearFecha($fecha)
    {
        $timestamp = strtotime($fecha);
        return $timestamp === false ? '' : date('d/m/Y H:i', $timestamp);
    }

    private function guardarMensaje($texto, $tipo)
    {
        $_SESSION['mensaje_moderador'] = [
            'texto' => $texto,
            'tipo' => $tipo,
        ];
    }
}
