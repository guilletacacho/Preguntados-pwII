<?php

namespace app\controller;

use ModeradorAccess;
use Redirect;
use Request;

class ModeradorController
{
    private const ESTADOS = ['PENDIENTE', 'RESUELTO', 'DESCARTADO'];

    private $model;
    private $sugerenciaModel;
    private $render;

    public function __construct($model, $sugerenciaModel, $render)
    {
        $this->model = $model;
        $this->sugerenciaModel = $sugerenciaModel;
        $this->render = $render;
    }

    public function show()
    {
        if (!isset($_SESSION['usuario_id'])) {
            Redirect::toIndex();
        }

        $ultimosReportes = $this->model->obtenerUltimosReportes(4);

        $clasesEstado = [
            'PENDIENTE' => 'estado-pendiente',
            'EN_REVISION' => 'estado-revision',
            'RESUELTO' => 'estado-resuelto',
            'DESCARTADO' => 'estado-descartado',
        ];

        foreach ($ultimosReportes as &$reporte) {
            $reporte['claseEstado'] = $clasesEstado[$reporte['status']] ?? '';
            $reporte['fechaFormateada'] = $this->formatearFecha($reporte['created_at']);
        }
        unset($reporte);

        $datos = [
            'pendientes' => $this->model->contarReportesPendientes(),
            'sugerenciasPendientes' => $this->contarSugerenciasPendientes(),
            'preguntasActivas' => $this->model->contarPreguntas(null,null,'activa'),
            'reportesResueltos' => $this->model->contarReportesResueltos(),
            'ultimosReportes' => $ultimosReportes,
        ];
        $this->render->renderiza("moderador",$datos);
    }


    public function reportes()
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

        $this->render->renderiza('ModeradorReportes', [
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
            'sugerenciasPendientes' => $this->contarSugerenciasPendientes(),
        ]);
    }

    public function detalle()
    {
        ModeradorAccess::exigir($this->model);

        $id = Request::get('id');
        if (!is_string($id) || !preg_match('/\A[0-9]+\z/', $id) || (int) $id < 1) {
            $this->guardarMensaje('La pregunta solicitada no es válida.', 'error');
            Redirect::to('/Preguntados/moderador/reportes');
        }

        if ($this->model->obtenerDetallePregunta((int) $id) === null
            || !$this->model->obtenerReportesPregunta((int) $id)) {
            $this->guardarMensaje('No se encontraron reportes para esa pregunta.', 'error');
            Redirect::to('/Preguntados/moderador/reportes');
        }

        $estado = Request::get('estado', 'PENDIENTE');
        if (!is_string($estado) || !in_array($estado, self::ESTADOS, true)) {
            $estado = 'PENDIENTE';
        }
        $orden = Request::get('orden', 'mas_viejas');
        if (!is_string($orden) || !in_array($orden, ['mas_reportadas', 'mas_viejas', 'mas_nuevas'], true)) {
            $orden = 'mas_viejas';
        }

        Redirect::to('/Preguntados/moderador/reportes?estado=' . $estado . '&orden=' . $orden . '&id=' . (int) $id);
    }

    public function editar()
    {
        ModeradorAccess::exigir($this->model);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            Redirect::to('/Preguntados/moderador/reportes');
        }

        $id = Request::get('id');
        if (!is_string($id) || !preg_match('/\A[0-9]+\z/', $id) || (int) $id < 1) {
            $this->guardarMensaje('La pregunta solicitada no es válida.', 'error');
            Redirect::to('/Preguntados/moderador/reportes');
        }

        $questionId = (int) $id;
        if ($this->model->obtenerDetallePregunta($questionId) === null
            || !$this->model->tieneReportesPendientes($questionId)) {
            $this->guardarMensaje('La pregunta no tiene reportes pendientes para corregir.', 'error');
            Redirect::to('/Preguntados/moderador/reportes');
        }

        $this->renderizarEditor($questionId, null, null);
    }

    public function corregir()
    {
        ModeradorAccess::exigir($this->model);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Redirect::to('/Preguntados/moderador/reportes');
        }

        $tokenSesion = $_SESSION['csrf_moderador'] ?? '';
        $tokenEnviado = $_POST['csrf_token'] ?? '';
        if (!is_string($tokenSesion) || !is_string($tokenEnviado)
            || $tokenSesion === '' || !hash_equals($tokenSesion, $tokenEnviado)) {
            $this->guardarMensaje('No se pudo validar el formulario. Volvé a intentarlo.', 'error');
            Redirect::to('/Preguntados/moderador/reportes');
        }

        $questionId = $_POST['question_id'] ?? null;
        if (!is_string($questionId) || !preg_match('/\A[0-9]+\z/', $questionId) || (int) $questionId < 1) {
            $this->guardarMensaje('La pregunta seleccionada no es válida.', 'error');
            Redirect::to('/Preguntados/moderador/reportes');
        }
        $questionId = (int) $questionId;

        if ($this->model->obtenerDetallePregunta($questionId) === null
            || !$this->model->tieneReportesPendientes($questionId)) {
            $this->guardarMensaje('La pregunta no tiene reportes pendientes para corregir.', 'error');
            Redirect::to('/Preguntados/moderador/reportes');
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
        Redirect::to('/Preguntados/moderador/reportes?estado=RESUELTO&id=' . $questionId);
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
            Redirect::to('/Preguntados/moderador/reportes');
        }

        $tokenSesion = $_SESSION['csrf_moderador'] ?? '';
        $tokenEnviado = $_POST['csrf_token'] ?? '';
        if (!is_string($tokenSesion) || !is_string($tokenEnviado)
            || $tokenSesion === '' || !hash_equals($tokenSesion, $tokenEnviado)) {
            $this->guardarMensaje('No se pudo validar el formulario. Volvé a intentarlo.', 'error');
            Redirect::to('/Preguntados/moderador/reportes');
        }

        $questionId = $_POST['question_id'] ?? null;
        if (!is_string($questionId) || !preg_match('/\A[0-9]+\z/', $questionId) || (int) $questionId < 1) {
            $this->guardarMensaje('La pregunta seleccionada no es válida.', 'error');
            Redirect::to('/Preguntados/moderador/reportes');
        }
        $questionId = (int) $questionId;

        $nota = $_POST['resolution_notes'] ?? '';
        if (!is_string($nota)) {
            $this->guardarMensaje('La nota de resolución no es válida.', 'error');
            Redirect::to('/Preguntados/moderador/reportes');
        }
        $nota = trim($nota);
        $cantidadCaracteres = preg_match_all('/./us', $nota);
        if ($cantidadCaracteres === false || $cantidadCaracteres > 500) {
            $this->guardarMensaje('La nota debe ser texto válido y no superar los 500 caracteres.', 'error');
            Redirect::to('/Preguntados/moderador/reportes');
        }

        if ($this->model->obtenerDetallePregunta($questionId) === null
            || !$this->model->tieneReportesPendientes($questionId)) {
            $this->guardarMensaje('La pregunta no tiene reportes pendientes para actualizar.', 'error');
            Redirect::to('/Preguntados/moderador/reportes');
        }

        $actualizado = $this->model->actualizarReportesPendientes(
            $questionId,
            $estado,
            (int) $_SESSION['usuario_id'],
            $nota === '' ? null : $nota
        );

        if (!$actualizado) {
            $this->guardarMensaje('Los reportes ya no estaban pendientes. Actualizá la bandeja.', 'error');
            Redirect::to('/Preguntados/moderador/reportes');
        }

        $texto = $estado === 'RESUELTO' ? 'Reportes resueltos correctamente.' : 'Reportes descartados correctamente.';
        $this->guardarMensaje($texto, 'success');
        Redirect::to('/Preguntados/moderador/reportes?estado=' . $estado);
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
        $this->render->renderiza('ModeradorReportes', [
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
            'sugerenciasPendientes' => $this->contarSugerenciasPendientes(),
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

    private function contarSugerenciasPendientes()
    {
        return count($this->sugerenciaModel->mostrarPreguntasSugeridas('PENDIENTE'));
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

    //EMPIEZA ABM DE SECCION PREGUNTAS

    public function preguntas()
    {
        if (!isset($_SESSION['usuario_id'])) {
            Redirect::toIndex();
        }

        $categoriaInput = $_GET['categoria_id'] ?? '';
        $categoriaId = null;
        $texto = trim($_GET['texto'] ?? '');

        if ($categoriaInput !== '') {
            $categoriaId = (is_string($categoriaInput) || is_int($categoriaInput))
                ? filter_var($categoriaInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                : false;

            if ($categoriaId === false) {
                http_response_code(400);
                echo 'La categoría no es válida.';
                return;
            }
        }

        $dificultadInput = $_GET['dificultad'] ?? '';
        $dificultad = null;

        if ($dificultadInput !== '') {
            if (!is_string($dificultadInput) || !in_array($dificultadInput, ['1', '2', '3'], true)) {
                http_response_code(400);
                echo 'La dificultad no es válida.';
                return;
            }

            $dificultad = (int) $dificultadInput;
        }

        $estadoInput = $_GET['estado'] ?? 'activa';

        if (!is_string($estadoInput) || !in_array($estadoInput, ['activa', 'inactiva', 'todas'], true)) {
            http_response_code(400);
            echo 'El estado no es válido.';
            return;
        }

        $estado = $estadoInput === 'todas' ? null : $estadoInput;

        $categorias = $this->model->obtenerCategorias();
        foreach ($categorias as &$categoria) {
            $categoria['seleccionada'] = $categoriaId !== null
                && (int) $categoria['id'] === $categoriaId;
        }
        unset($categoria);

        // PAGINA LOS RESULTADOS Y PREPARA LOS DATOS DEL CONTADOR Y LOS ENLACES.
        $porPagina = 10;
        $paginaInput = $_GET['pagina'] ?? '1';
        $pagina = (is_string($paginaInput) || is_int($paginaInput))
            ? filter_var($paginaInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        $pagina = $pagina === false ? 1 : $pagina;

        $totalPreguntas = $this->model->contarPreguntas(
            $categoriaId,
            $dificultad,
            $estado
        );
        $ultimaPagina = max(1, (int) ceil($totalPreguntas / $porPagina));
        $pagina = min($pagina, $ultimaPagina);
        $offset = ($pagina - 1) * $porPagina;

        $desde = $totalPreguntas > 0 ? $offset + 1 : 0;
        $hasta = min($offset + $porPagina, $totalPreguntas);
        $queryFiltros = http_build_query([
            'categoria_id' => $categoriaId,
            'dificultad' => $dificultad,
            'estado' => $estadoInput,
        ]);

        if($texto !== ''){
            $preguntas = $this->model->buscarPreguntaPorTexto($texto);
        }else{
            $preguntas = $this->model->obtenerPreguntas(
                $porPagina,
                $offset,
                $categoriaId,
                $dificultad,
                $estado
            );
        }

        $datos = [
            'pendientes' => $this->model->contarReportesPendientes(),
            'sugerenciasPendientes' => $this->contarSugerenciasPendientes(),
            'preguntas' => $preguntas,
            'categorias' => $categorias,
            'todasCategoriasSeleccionada' => $categoriaId === null,
            'todaDificultadSeleccionada' => $dificultad === null,
            'dificultadFacilSeleccionada' => $dificultad === 1,
            'dificultadMediaSeleccionada' => $dificultad === 2,
            'dificultadDificilSeleccionada' => $dificultad === 3,
            'estadoActivaSeleccionada' => $estadoInput === 'activa',
            'estadoTodasSeleccionada' => $estadoInput === 'todas',
            'estadoInactivaSeleccionada' => $estadoInput === 'inactiva',
            'queryFiltros' => $queryFiltros,
            'desde' => $desde,
            'hasta' => $hasta,
            'totalPreguntas' => $totalPreguntas,
            'paginaAnterior' => $pagina - 1,
            'paginaSiguiente' => $pagina + 1,
            'hayPaginaAnterior' => $pagina > 1,
            'hayPaginaSiguiente' => $pagina < $ultimaPagina,
        ];

        $this->render->renderiza('moderadorPreguntas', $datos);
    }

    public function altaPregunta()
    {
        if (!isset($_SESSION['usuario_id'])) {
            Redirect::toIndex();
        }

        $datos = [
            'categorias' => $this->model->obtenerCategorias(),
            'pendientes' => $this->model->contarReportesPendientes(),
            'sugerenciasPendientes' => $this->contarSugerenciasPendientes(),
        ];

        $this->render->renderiza("moderadorAltaPregunta", $datos);
    }

    public function crearPregunta()
    {
        if (!isset($_SESSION['usuario_id'])) {
            Redirect::toIndex();
        }

        $categorias = $this->model->obtenerCategorias();
        $errores = [];

        // Acepta únicamente un ID numérico que exista en las categorías disponibles.
        $categoryIdInput = $_POST['category_id'] ?? null;
        $categoryId = (is_string($categoryIdInput) || is_int($categoryIdInput))
            ? filter_var($categoryIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        $categoriaValida = false;
        foreach ($categorias as $categoria) {
            if ($categoryId !== false && (int) $categoria['id'] === $categoryId) {
                $categoriaValida = true;
                break;
            }
        }
        if (!$categoriaValida) {
            $errores[] = 'Seleccioná una categoría válida.';
        }

        // La dificultad y los puntos son reglas del servidor, no valores del formulario.
        $questionInput = $_POST['question'] ?? null;
        $question = is_string($questionInput) ? trim($questionInput) : '';
        $questionLength = preg_match_all('/./us', $question);
        if ($question === '' || $questionLength === false || $questionLength > 500) {
            $errores[] = 'La pregunta es obligatoria y no puede superar los 500 caracteres.';
        }

        // El formulario envía cuatro respuestas indexadas; una sola debe estar marcada como correcta.
        $responsesInput = $_POST['responses'] ?? null;
        $responses = [];
        $correctas = 0;
        if (!is_array($responsesInput) || count($responsesInput) !== 4) {
            $errores[] = 'Completá las cuatro respuestas.';
        } else {
            for ($indice = 0; $indice < 4; $indice++) {
                $responseInput = $responsesInput[$indice] ?? null;
                if (!is_array($responseInput) || !is_string($responseInput['text'] ?? null)) {
                    $errores[] = 'Completá las cuatro respuestas.';
                    continue;
                }

                $texto = trim($responseInput['text']);
                $textoLength = preg_match_all('/./us', $texto);
                if ($texto === '' || $textoLength === false || $textoLength > 255) {
                    $errores[] = 'Cada respuesta es obligatoria y no puede superar los 255 caracteres.';
                    continue;
                }
                if (in_array($texto, array_column($responses, 'text'), true)) {
                    $errores[] = 'Las cuatro respuestas deben ser distintas.';
                    continue;
                }

                $correctaInput = $responseInput['is_correct'] ?? null;
                if ($correctaInput !== null && $correctaInput !== '1') {
                    $errores[] = 'La selección de respuesta correcta no es válida.';
                    continue;
                }
                $esCorrecta = $correctaInput === '1';
                if ($esCorrecta) {
                    $correctas++;
                }

                $responses[] = [
                    'text' => $texto,
                    'is_correct' => $esCorrecta ? 1 : 0,
                ];
            }
        }

        if ($correctas !== 1) {
            $errores[] = 'Marcá exactamente una respuesta como correcta.';
        }

        if ($errores) {
            $this->render->renderiza('moderadorAltaPregunta', [
                'categorias' => $categorias,
                'pendientes' => $this->model->contarReportesPendientes(),
                'sugerenciasPendientes' => $this->contarSugerenciasPendientes(),
                'error' => implode(' ', array_unique($errores)),
            ]);
            return;
        }

        $questionId = $this->model->agregarPregunta($categoryId, $question, 1, 1);

        // Cada respuesta queda asociada al ID generado al insertar la pregunta.
        foreach ($responses as $response) {
            $this->model->agregarRespuesta($questionId, $response['text'], $response['is_correct']);
        }

        Redirect::to('/Preguntados/moderador/preguntas');
    }

    public function desactivarPregunta()
    {
        if (!isset($_SESSION['usuario_id'])) {
            Redirect::toIndex();
        }

        // VALIDA QUE EL ID RECIBIDO SEA UN ENTERO POSITIVO.
        $idInput = $_POST['question_id'] ?? null;
        $id = (is_string($idInput) || is_int($idInput))
            ? filter_var($idInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Método no permitido.';
            return;
        }

        if ($id === false) {
            http_response_code(400);
            echo 'El identificador de la pregunta no es válido.';
            return;
        }

        // EL MODEL SOLO ACTUALIZA PREGUNTAS QUE TODAVIA ESTAN ACTIVAS.
        if ($this->model->desactivarPregunta($id) !== 1) {
            http_response_code(404);
            echo 'La pregunta no existe o ya está desactivada.';
            return;
        }

        Redirect::to('/Preguntados/moderador/preguntas');
    }

    public function reactivarPregunta()
    {
        if (!isset($_SESSION['usuario_id'])) {
            Redirect::toIndex();
        }

        // VALIDA QUE EL ID RECIBIDO SEA UN ENTERO POSITIVO.
        $idInput = $_POST['question_id'] ?? null;
        $id = (is_string($idInput) || is_int($idInput))
            ? filter_var($idInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Método no permitido.';
            return;
        }

        if ($id === false) {
            http_response_code(400);
            echo 'El identificador de la pregunta no es válido.';
            return;
        }

        // ACTUALIZA PREGUNTAS INACTIVAS a ACTIVAS.
        if ($this->model->reactivarPregunta($id) !== 1) {
            http_response_code(404);
            echo 'La pregunta no existe o ya está activada.';
            return;
        }

        Redirect::to('/Preguntados/moderador/preguntas');
    }

    public function editarPregunta(){

    ModeradorAccess::exigir($this->model);

    $id = Request::get('id');
    if (!is_string($id) || !preg_match('/\A[0-9]+\z/', $id) || (int) $id < 1) {
        Redirect::to('/Preguntados/moderador/preguntas');
    }

    $id = (int) $id;
    $pregunta = $this->model->obtenerDetallePregunta($id);

    if ($pregunta === null) {
        Redirect::to('/Preguntados/moderador/preguntas');
    }
    
    $categorias = $this->model->listarCategorias();
    foreach ($categorias as &$categoria) {
        $categoria['seleccionada'] = (int) $categoria['id'] === (int) $pregunta['category_id'];
    }
    unset($categoria);

    $opciones = $this->model->obtenerOpciones($id);
    foreach ($opciones as $indice => &$opcion) {
        $opcion['indice'] = $indice;
        $opcion['letra'] = chr(65 + $indice);
        $opcion['respuesta_id'] = (int) $opcion['id'];
        $opcion['seleccionada'] = (int) $opcion['is_correct'] === 1;
    }
    unset($opcion);

    $datos = [
        'editorQuestionId' => $id,
        'editorPregunta' => $pregunta['question'],
        'editorCategorias' => $categorias,
        'editorOpciones' => $opciones,
        'editorDisponible' => count($opciones) === 4 && count($categorias) > 0,
    ];

    $this->render->renderiza('moderadorEditarPregunta', $datos);

    }


    public function actualizarPregunta(){

        ModeradorAccess::exigir($this->model);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            return;
        }

        $id = filter_var($_POST['question_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || !is_array($_POST['responses'] ?? null)) {
            Redirect::to('/Preguntados/moderador/preguntas');
        }

        $respuestas = [];
        foreach ($_POST['responses'] as $r) {
            $respuestas[] = ['id' => $r['id'], 'response' => $r['texto']];
        }

        $this->model->actualizarPregunta(
        $id,
        $_POST['category_id'] ?? '',
        $_POST['question'] ?? '',
        $respuestas,
        $_POST['correct_response_id'] ?? ''
        );

        Redirect::to('/Preguntados/moderador/preguntas');
    }

    //SUGERENCIAS VISTAS Y METODOS

    public function sugerencias(){
        
        if (!isset($_SESSION['usuario_id'])) {
            Redirect::toIndex();
        }

        $estado = strtoupper(Request::get('estado', 'PENDIENTE'));
        $estadosValidos = ['PENDIENTE', 'APROBADA', 'RECHAZADA'];

        if (!in_array($estado, $estadosValidos, true)) {
            $estado = 'PENDIENTE';
        }
        

        $categorias = $this->sugerenciaModel->obtenerCategorias();
        $categoriaInput = $_GET['categoria_id'] ?? '';
        $categoriaId = null;

        if (is_string($categoriaInput) && $categoriaInput !== '') {
            $categoriaValidada = filter_var(
                $categoriaInput,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            foreach ($categorias as $categoria) {
                if ($categoriaValidada !== false
                    && (int) $categoria['id'] === $categoriaValidada) {
                    $categoriaId = $categoriaValidada;
                    break;
                }
            }
        }

        foreach ($categorias as &$categoria) {
            $categoria['seleccionada'] = (int) $categoria['id'] === $categoriaId;
        }
        unset($categoria);

        $preguntas = $this->sugerenciaModel->mostrarPreguntasSugeridas($estado, $categoriaId);
        foreach ($preguntas as &$pregunta) {
            $pregunta['puedeRevisar'] = $estado === 'PENDIENTE';
        }
        unset($pregunta);

        $datos = [
            'sugerencias' => count($preguntas),
            'sugerenciasPendientes' => $this->contarSugerenciasPendientes(),
            'preguntas' => $preguntas,
            'categorias' => $categorias,
            'todasCategoriasSeleccionada' => $categoriaId === null,
            'categoriaId' => $categoriaId,
            'hayCategoria' => $categoriaId !== null,
            'estadoSeleccionado' => $estado,
            'estadoPendiente' => $estado === 'PENDIENTE',
            'estadoAprobada' => $estado === 'APROBADA',
            'estadoRechazada' => $estado === 'RECHAZADA',
        ];

        $this->render->renderiza('moderadorSugerencias', $datos);
    }

    public function editarSugerencia(){

        $id = Request::get('id');

        ModeradorAccess::exigir($this->model);

        $filas = $this->sugerenciaModel->obtenerSugerenciaPorId($id);
        $sugerencia = $filas[0] ?? null;

        if ($sugerencia === null) {
            Redirect::to('/Preguntados/moderador/sugerencias');
            return;
        }

        $categorias = $this->sugerenciaModel->obtenerCategorias();
        foreach ($categorias as &$categoria) {
            $categoria['seleccionada'] =
                (int) $categoria['id'] === (int) $sugerencia['categoria_id'];
        }
        unset($categoria);

        $respuestas = [];
        foreach (['A', 'B', 'C', 'D'] as $indice =>$letra) {
            $respuestas[] = [
                'indice' => $indice,
                'letra' => $letra,
                'texto' => '',
            ];
        }

        $pendientes = $this->sugerenciaModel->mostrarPreguntasSugeridas('PENDIENTE');

        $datos = [
            'sugerenciaId' => $sugerencia['id'],
            'pregunta' => $sugerencia['pregunta'],
            'categorias' => $categorias,
            'respuestas' => $respuestas,
            'sugerenciasPendientes' => count($pendientes),
        ];

        $this->render->renderiza('moderadorSugerenciasEditar', $datos);
    }

    public function publicarSugerencias(){

         ModeradorAccess::exigir($this->model);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            return;
        }
        
        $id = filter_var($_POST['suggestion_id'] ?? null, FILTER_VALIDATE_INT);

        if ($id === false || $id < 1) {
            $this->guardarMensaje('La sugerencia seleccionada no es válida.', 'error');
            Redirect::to('/Preguntados/moderador/sugerencias');
            return;
        }

        $datosFinales = [
        'category_id' => $_POST['category_id'] ?? null,
        'question' => $_POST['question'] ?? '',
        'responses' => $_POST['responses'] ?? [],
        'correct_response' => $_POST['correct_response'] ?? null,
        'difficulty_level' => $_POST['difficulty_level'] ?? 2,
        ];

        try {
        $this->sugerenciaModel->publicarSugerencia(
            $id,
            (int) $_SESSION['usuario_id'],
            $datosFinales
        );

        $this->guardarMensaje('La sugerencia fue aprobada y publicada.', 'exito');
        } catch (\DomainException $error) {
            $this->guardarMensaje($error->getMessage(), 'error');
        } catch (\Throwable $error) {
            $this->guardarMensaje('No se pudo publicar la sugerencia.', 'error');
        }

        Redirect::to('/Preguntados/moderador/sugerencias');
    
    }

    public function rechazarSugerencia(){

        ModeradorAccess::exigir($this->model);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            return;
        }

        $id = filter_var(
            $_POST['suggestion_id'] ?? null,
            FILTER_VALIDATE_INT
        );
        $nota = $_POST['resolution_notes'] ?? '';

        if ($id === false || $id < 1 || !is_string($nota)) {
            $this->guardarMensaje('La sugerencia o el motivo no son válidos.', 'error');
            Redirect::to('/Preguntados/moderador/sugerencias?estado=PENDIENTE');
            return;
        }

        try {
            $rechazada = $this->sugerenciaModel->rechazarSugerencia(
                $id,
                (int) $_SESSION['usuario_id'],
                $nota
            );

            if (!$rechazada) {
                $this->guardarMensaje('La sugerencia ya no está pendiente.', 'error');
                Redirect::to('/Preguntados/moderador/sugerencias?estado=PENDIENTE');
                return;
            }

            $this->guardarMensaje('La sugerencia fue rechazada.', 'success');
        } catch (\DomainException $error) {
            $this->guardarMensaje($error->getMessage(), 'error');
            Redirect::to('/Preguntados/moderador/sugerencias?estado=PENDIENTE');
            return;
        }

        Redirect::to('/Preguntados/moderador/sugerencias?estado=RECHAZADA');
    }
}
