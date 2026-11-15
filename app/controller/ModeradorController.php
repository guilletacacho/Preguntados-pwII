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

        $this->asegurarCsrfToken();

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

    public function editar()
    {
        ModeradorAccess::exigir($this->model);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            Redirect::to('/Preguntados/moderador');
        }

        $id = Request::get('id');
        if (!is_string($id) || !preg_match('/\A[0-9]+\z/', $id) || (int) $id < 1) {
            $this->guardarMensaje('La pregunta solicitada no es válida.', 'error');
            Redirect::to('/Preguntados/moderador');
        }

        $questionId = (int) $id;
        if ($this->model->obtenerDetallePregunta($questionId) === null
            || !$this->model->tieneReportesPendientes($questionId)) {
            $this->guardarMensaje('La pregunta no tiene reportes pendientes para corregir.', 'error');
            Redirect::to('/Preguntados/moderador');
        }

        $this->renderizarEditor($questionId, null, null);
    }

    public function corregir()
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

        if ($this->model->obtenerDetallePregunta($questionId) === null
            || !$this->model->tieneReportesPendientes($questionId)) {
            $this->guardarMensaje('La pregunta no tiene reportes pendientes para corregir.', 'error');
            Redirect::to('/Preguntados/moderador');
        }

        $formulario = [
            'category_id' => $_POST['category_id'] ?? '',
            'question' => $_POST['question'] ?? '',
            'responses' => $_POST['responses'] ?? [],
            'correct_response_id' => $_POST['correct_response_id'] ?? '',
            'resolution_notes' => $_POST['resolution_notes'] ?? '',
        ];
        $error = $this->validarFormularioCorreccion($questionId, $formulario);
        if ($error !== null) {
            $this->renderizarEditor($questionId, $formulario, $error);
            return;
        }

        try {
            $this->model->corregirPregunta(
                $questionId,
                (int) $formulario['category_id'],
                trim($formulario['question']),
                $formulario['respuestas_validadas'],
                (int) $formulario['correct_response_id'],
                (int) $_SESSION['usuario_id'],
                trim($formulario['resolution_notes'])
            );
        } catch (\DomainException $exception) {
            $this->renderizarEditor($questionId, $formulario, $exception->getMessage());
            return;
        }

        $this->guardarMensaje('Pregunta corregida y reportes resueltos correctamente.', 'success');
        Redirect::to('/Preguntados/moderador?estado=RESUELTO&id=' . $questionId);
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

        $nota = $_POST['resolution_notes'] ?? '';
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
            $reporte['fecha_revision_formateada'] = $reporte['reviewed_at'] === null
                ? null
                : $this->formatearFecha($reporte['reviewed_at']);
        }
        unset($reporte);

        $detalle['reportes'] = $reportes;
        $detalle['hayPendientes'] = $this->model->tieneReportesPendientes($questionId);
        $detalle['hayOpciones'] = count($detalle['opciones']) > 0;
        return $detalle;
    }

    private function renderizarEditor($questionId, $formulario, $error)
    {
        $this->asegurarCsrfToken();
        $detalle = $this->model->obtenerDetallePregunta($questionId);
        $opciones = $this->model->obtenerOpciones($questionId);
        $categorias = $this->model->listarCategorias();
        $reportes = array_values(array_filter(
            $this->model->obtenerReportesPregunta($questionId),
            static function ($reporte) {
                return $reporte['status'] === 'PENDIENTE';
            }
        ));

        $respuestaCorrectaAnterior = '';
        $idCorrectoActual = '';
        foreach ($opciones as $opcion) {
            if ((int) $opcion['is_correct'] === 1) {
                $idCorrectoActual = (string) $opcion['id'];
                $respuestaCorrectaAnterior = $opcion['response'];
                break;
            }
        }

        $respuestasEnviadas = is_array($formulario) && is_array($formulario['responses'] ?? null)
            ? $formulario['responses']
            : [];
        $idCorrectoSeleccionado = is_array($formulario)
            ? (is_scalar($formulario['correct_response_id'] ?? null)
                ? (string) $formulario['correct_response_id']
                : '')
            : $idCorrectoActual;

        foreach ($opciones as $indice => &$opcion) {
            $opcion['indice'] = $indice;
            $opcion['letra'] = chr(65 + $indice);
            $opcion['respuesta_id'] = (int) $opcion['id'];
            $opcion['correcta'] = (int) $opcion['is_correct'] === 1;

            if (isset($respuestasEnviadas[$indice]['texto'])
                && is_string($respuestasEnviadas[$indice]['texto'])) {
                $opcion['response'] = $respuestasEnviadas[$indice]['texto'];
            }
            $opcion['seleccionada'] = $idCorrectoSeleccionado === (string) $opcion['id'];
        }
        unset($opcion);

        foreach ($reportes as &$reporte) {
            $reporte['tieneComentario'] = trim((string) $reporte['comment']) !== '';
        }
        unset($reporte);

        $categoryId = is_array($formulario)
            ? (is_scalar($formulario['category_id'] ?? null) ? (string) $formulario['category_id'] : '')
            : (string) $detalle['category_id'];
        foreach ($categorias as &$categoria) {
            $categoria['seleccionada'] = (string) $categoria['id'] === $categoryId;
        }
        unset($categoria);

        $respuestaCorrectaActual = '';
        foreach ($opciones as $opcion) {
            if ($opcion['seleccionada']) {
                $respuestaCorrectaActual = $opcion['response'];
                break;
            }
        }

        $mensaje = $error === null ? null : ['texto' => $error, 'tipo' => 'error'];
        $this->render->renderiza('moderador', [
            'modoEditor' => true,
            'editorDisponible' => count($opciones) === 4 && count($categorias) > 0,
            'editorPregunta' => is_array($formulario) && is_string($formulario['question'])
                ? $formulario['question']
                : $detalle['question'],
            'editorNota' => is_array($formulario) && is_string($formulario['resolution_notes'])
                ? $formulario['resolution_notes']
                : '',
            'editorQuestionId' => (int) $questionId,
            'editorOpciones' => $opciones,
            'editorCategorias' => $categorias,
            'editorReportes' => $reportes,
            'cantidadReportesCorreccion' => count($reportes),
            'pluralReportesCorreccion' => count($reportes) !== 1,
            'respuestaCorrectaAnterior' => $respuestaCorrectaAnterior !== ''
                ? $respuestaCorrectaAnterior
                : 'No hay una respuesta correcta marcada',
            'respuestaCorrectaActual' => $respuestaCorrectaActual !== ''
                ? $respuestaCorrectaActual
                : 'Seleccioná una respuesta',
            'csrfToken' => $_SESSION['csrf_moderador'],
            'mensaje' => $mensaje,
            'hayMensaje' => $mensaje !== null,
            'pendientes' => $this->model->contarReportesPendientes(),
        ]);
    }

    private function validarFormularioCorreccion($questionId, array &$formulario)
    {
        $categoriaId = $formulario['category_id'];
        if ((!is_string($categoriaId) && !is_int($categoriaId))
            || !preg_match('/\A[0-9]+\z/', (string) $categoriaId)
            || (int) $categoriaId < 1) {
            return 'Seleccioná una categoría válida.';
        }
        $categorias = array_map('intval', array_column($this->model->listarCategorias(), 'id'));
        if (!in_array((int) $categoriaId, $categorias, true)) {
            return 'La categoría seleccionada no existe.';
        }

        $pregunta = $formulario['question'];
        if (!is_string($pregunta)) {
            return 'El enunciado debe ser texto válido.';
        }
        $pregunta = trim($pregunta);
        $longitudPregunta = preg_match_all('/./us', $pregunta);
        if ($pregunta === '' || $longitudPregunta === false || $longitudPregunta > 500) {
            return 'El enunciado es obligatorio y no debe superar los 500 caracteres.';
        }

        $nota = $formulario['resolution_notes'];
        if (!is_string($nota)) {
            return 'La nota de resolución debe ser texto válido.';
        }
        $nota = trim($nota);
        $longitudNota = preg_match_all('/./us', $nota);
        if ($nota === '' || $longitudNota === false || $longitudNota > 500) {
            return 'La nota de resolución es obligatoria y no debe superar los 500 caracteres.';
        }

        $respuestasEnviadas = $formulario['responses'];
        if (!is_array($respuestasEnviadas) || count($respuestasEnviadas) !== 4) {
            return 'Debés completar exactamente las cuatro respuestas.';
        }

        $opcionesActuales = $this->model->obtenerOpciones($questionId);
        if (count($opcionesActuales) !== 4) {
            return 'La pregunta no tiene exactamente cuatro respuestas para corregir.';
        }
        $idsActuales = array_map('intval', array_column($opcionesActuales, 'id'));
        $respuestasValidadas = [];
        $idsEnviados = [];
        $normalizadas = [];
        foreach (array_values($respuestasEnviadas) as $respuesta) {
            if (!is_array($respuesta) || !isset($respuesta['id'], $respuesta['texto'])
                || (!is_string($respuesta['id']) && !is_int($respuesta['id']))
                || !is_string($respuesta['texto'])
                || !preg_match('/\A[0-9]+\z/', (string) $respuesta['id'])) {
                return 'Los datos de las respuestas no son válidos.';
            }

            $id = (int) $respuesta['id'];
            $texto = trim($respuesta['texto']);
            $longitud = preg_match_all('/./us', $texto);
            if ($id < 1 || $texto === '' || $longitud === false || $longitud > 255) {
                return 'Cada respuesta es obligatoria y no debe superar los 255 caracteres.';
            }

            $normalizada = mb_strtolower(preg_replace('/\s+/u', ' ', $texto), 'UTF-8');
            if (isset($normalizadas[$normalizada])) {
                return 'Las cuatro respuestas deben ser distintas, ignorando mayúsculas y espacios.';
            }

            $idsEnviados[] = $id;
            $normalizadas[$normalizada] = true;
            $respuestasValidadas[] = ['id' => $id, 'response' => $texto];
        }

        sort($idsActuales);
        $idsUnicos = array_values(array_unique($idsEnviados));
        sort($idsUnicos);
        if (count($idsEnviados) !== 4 || $idsUnicos !== $idsActuales) {
            return 'Las cuatro respuestas deben pertenecer a la pregunta que estás corrigiendo.';
        }

        $correctaId = $formulario['correct_response_id'];
        if ((!is_string($correctaId) && !is_int($correctaId))
            || !preg_match('/\A[0-9]+\z/', (string) $correctaId)
            || !in_array((int) $correctaId, $idsActuales, true)) {
            return 'Debés marcar exactamente una respuesta correcta.';
        }

        $formulario['category_id'] = (string) (int) $categoriaId;
        $formulario['question'] = $pregunta;
        $formulario['resolution_notes'] = $nota;
        $formulario['correct_response_id'] = (string) (int) $correctaId;
        $formulario['respuestas_validadas'] = $respuestasValidadas;
        return null;
    }

    private function asegurarCsrfToken()
    {
        if (empty($_SESSION['csrf_moderador'])) {
            $_SESSION['csrf_moderador'] = bin2hex(random_bytes(32));
        }
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
