<?php

namespace app\controller;

use ModeradorAccess;
use Redirect;
use Request;

class ModeradorPreguntasController
{
    private $model;
    private $sugerenciaModel;
    private $render;

    public function __construct($model, $sugerenciaModel, $render)
    {
        $this->model = $model;
        $this->sugerenciaModel = $sugerenciaModel;
        $this->render = $render;
    }

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

        Redirect::to('/Preguntados/moderadorPreguntas/preguntas');
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

        Redirect::to('/Preguntados/moderadorPreguntas/preguntas');
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

        Redirect::to('/Preguntados/moderadorPreguntas/preguntas');
    }

    public function editarPregunta(){

    ModeradorAccess::exigir($this->model);

    $id = Request::get('id');
    if (!is_string($id) || !preg_match('/\A[0-9]+\z/', $id) || (int) $id < 1) {
        Redirect::to('/Preguntados/moderadorPreguntas/preguntas');
    }

    $id = (int) $id;
    $pregunta = $this->model->obtenerDetallePregunta($id);

    if ($pregunta === null) {
        Redirect::to('/Preguntados/moderadorPreguntas/preguntas');
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
            Redirect::to('/Preguntados/moderadorPreguntas/preguntas');
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

        Redirect::to('/Preguntados/moderadorPreguntas/preguntas');
    }

    //METODOS PRIVADOS 

    private function contarSugerenciasPendientes()
    {
        return count($this->sugerenciaModel->mostrarPreguntasSugeridas('PENDIENTE'));
    }
}
