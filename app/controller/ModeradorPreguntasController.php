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
        ModeradorAccess::exigir($this->model);
        $this->asegurarCsrfToken();

        $categoriaInput = $_GET['categoria_id'] ?? '';
        $categoriaId = null;
        $textoInput = Request::get('texto', '');
        if (!is_string($textoInput)) {
            http_response_code(400);
            echo 'El texto de búsqueda no es válido.';
            return;
        }
        $texto = trim($textoInput);

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
            $estado,
            $texto
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
            'texto' => $texto !== '' ? $texto : null,
        ]);

        $preguntas = $this->model->obtenerPreguntas(
            $porPagina,
            $offset,
            $categoriaId,
            $dificultad,
            $estado,
            $texto
        );

        $mensaje = $this->consumirMensajePregunta();
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
            'csrfToken' => $_SESSION['csrf_moderador'],
            'mensaje' => $mensaje,
            'paginaAnterior' => $pagina - 1,
            'paginaSiguiente' => $pagina + 1,
            'hayPaginaAnterior' => $pagina > 1,
            'hayPaginaSiguiente' => $pagina < $ultimaPagina,
        ];

        $this->render->renderiza('moderadorPreguntas', $datos);
    }

    public function altaPregunta()
    {
        ModeradorAccess::exigir($this->model);

        $this->renderizarAltaPregunta($this->model->obtenerCategorias());
    }

    
    public function categorias()
    {
        ModeradorAccess::exigir($this->model);

        $editarId = Request::get('editar');
        $categoria = null;
        if (is_string($editarId) && preg_match('/\A[1-9][0-9]*\z/', $editarId)) {
            $categoria = $this->model->obtenerCategoria((int) $editarId);
        }

        $mensaje = Request::get('guardada') === '1' ? 'Categoría guardada.' : null;
        $this->mostrarCategorias($categoria, null, $mensaje);
    }

    public function guardarCategoria()
    {
        ModeradorAccess::exigir($this->model);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Método no permitido.';
            return;
        }

        if (!$this->csrfSugerenciasValido($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            return;
        }

        $id = filter_var($_POST['category_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $actual = $id ? $this->model->obtenerCategoria($id) : null;
        if ($id && $actual === null) {
            Redirect::to('/Preguntados/moderador/categorias');
        }

        $nombre = is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '';
        $color = is_string($_POST['color'] ?? null) ? strtoupper(trim($_POST['color'])) : '';
        $errores = [];

        $largo = preg_match_all('/./us', $nombre);
        if ($nombre === '' || $largo === false || $largo > 40) {
            $errores[] = 'El nombre es obligatorio y no puede superar los 40 caracteres.';
        } elseif ($this->model->existeNombreCategoria($nombre, $id ?: 0)) {
            $errores[] = 'Ya existe una categoría con ese nombre.';
        }

        if (!preg_match('/\A#[0-9A-F]{6}\z/', $color)) {
            $errores[] = 'El color tiene que ser un hexadecimal como #3B9EEA.';
        }

        $archivo = $_FILES['icono'] ?? null;
        $hayArchivo = is_array($archivo) && ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if (!$hayArchivo && $actual === null) {
            $errores[] = 'Subí el SVG del personaje.';
        }

        $icono = $actual['icono'] ?? null;
        if (!$errores && $hayArchivo) {
            $subida = $this->guardarSvg($archivo);
            if (isset($subida['error'])) {
                $errores[] = $subida['error'];
            } else {
                $icono = $subida['ruta'];
            }
        }

        if ($errores) {
            $this->mostrarCategorias([
                'id' => $id ?: null,
                'name' => $nombre,
                'color' => preg_match('/\A#[0-9A-F]{6}\z/', $color) ? $color : ($actual['color'] ?? '#9A6BF2'),
                'icono' => $actual['icono'] ?? null,
            ], implode(' ', $errores));
            return;
        }

        if ($actual === null) {
            $this->model->crearCategoria($nombre, $color, $icono);
        } else {
            $this->model->actualizarCategoria($id, $nombre, $color, $icono);
            if ($icono !== $actual['icono'] && strpos($actual['icono'], 'categorias/') === 0) {
                @unlink('public/img/' . $actual['icono']);
            }
        }

        Redirect::to('/Preguntados/moderador/categorias?guardada=1');
    }

    public function crearPregunta()
    {
        ModeradorAccess::exigir($this->model);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            return;
        }
        if (!$this->csrfSugerenciasValido($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            return;
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

        $imagen = null;
        $archivo = $_FILES['image'] ?? null;
        if (!$errores && $archivo !== null && ($archivo['error'] ?? null) !== UPLOAD_ERR_NO_FILE) {
            $imagen = \ImageUploader::upload($archivo, 'app/img-preguntas');
            if ($imagen === null) {
                $errores[] = 'La imagen debe ser JPG o PNG y no superar los 5 MB.';
            }
        }
        if ($errores) {
            $this->renderizarAltaPregunta($categorias, implode(' ', array_unique($errores)));
            return;
        }

        try {
            $this->model->crearPreguntaConRespuestas($categoryId, $question, 1, 1, $responses, $imagen);
        } catch (\Throwable $error) {
            $this->eliminarImagenSubida($imagen !== null ? 'app/img-preguntas/' . $imagen : null);
            throw $error;
        }

        Redirect::to('/Preguntados/moderador/preguntas');
    }


    public function desactivarPregunta()
    {
        ModeradorAccess::exigir($this->model);

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

        if (!$this->csrfSugerenciasValido($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
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
        ModeradorAccess::exigir($this->model);

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

        if (!$this->csrfSugerenciasValido($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
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

    public function editarPregunta()
    {

        ModeradorAccess::exigir($this->model);
        $this->asegurarCsrfToken();

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

        $rutaImagen = $pregunta['image_url'] ?? '';
        $editorImagen = is_string($rutaImagen) && trim($rutaImagen) !== ''
            ? basename(str_replace('\\', '/', $rutaImagen))
            : '';

        $mensaje = $this->consumirMensajePregunta();
        $datos = [
            'editorQuestionId' => $id,
            'sugerenciasPendientes' => $this->contarSugerenciasPendientes(),
            'editorPregunta' => $pregunta['question'],
            'mensaje' => $mensaje,
            'hayMensaje' => $mensaje !== null,
            'editorImagen' => $editorImagen,
            'hayEditorImagen' => $editorImagen !== '',
            'editorCategorias' => $categorias,
            'editorOpciones' => $opciones,
            'editorDisponible' => count($opciones) === 4 && count($categorias) > 0,
            'csrfToken' => $_SESSION['csrf_moderador'],
        ];

        $this->render->renderiza('moderadorEditarPregunta', $datos);
    }

    public function actualizarPregunta(){

        ModeradorAccess::exigir($this->model);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            return;
        }

        if (!$this->csrfSugerenciasValido($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            return;
        }

        $id = filter_var($_POST['question_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1 || !is_array($_POST['responses'] ?? null)) {
            Redirect::to('/Preguntados/moderador/preguntas');
            return;
        }

        $respuestas = [];
        foreach ($_POST['responses'] as $respuesta) {
            if (!is_array($respuesta) || !isset($respuesta['id']) || !is_string($respuesta['texto'] ?? null)) {
                $this->guardarMensajePregunta('Los datos de las respuestas no son válidos.', 'error');
                Redirect::to('/Preguntados/moderador/editarPregunta?id=' . (int) $id);
                return;
            }
            $respuestas[] = ['id' => $respuesta['id'], 'response' => $respuesta['texto']];
        }

        $quitarImagen = ($_POST['remove_image'] ?? '') === '1';
        $archivo = $_FILES['image'] ?? null;
        $imagenNueva = null;
        $rutaImagenNueva = null;

        try {
            if ($quitarImagen && is_array($archivo) && ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                throw new \DomainException('Elegí una imagen nueva o quitá la actual, pero no ambas cosas.');
            }

            if ($archivo !== null) {
                if (!is_array($archivo)) {
                    throw new \DomainException('El archivo de imagen no es válido.');
                }
                $errorCarga = $archivo['error'] ?? null;
                if ($errorCarga === UPLOAD_ERR_INI_SIZE || $errorCarga === UPLOAD_ERR_FORM_SIZE) {
                    throw new \DomainException('La imagen no puede superar los 5 MB.');
                }
                if ($errorCarga !== UPLOAD_ERR_NO_FILE && $errorCarga !== UPLOAD_ERR_OK) {
                    throw new \DomainException('No se pudo recibir la imagen. Intentá nuevamente.');
                }
                if ($errorCarga === UPLOAD_ERR_OK) {
                    $imagenNueva = \ImageUploader::upload($archivo, 'app/img-preguntas');
                    if ($imagenNueva === null) {
                        throw new \DomainException('La imagen debe ser JPG o PNG y no superar los 5 MB.');
                    }
                    $rutaImagenNueva = 'app/img-preguntas/' . $imagenNueva;
                }
            }

            $imagenAnterior = $this->model->actualizarPregunta(
                $id,
                $_POST['category_id'] ?? '',
                $_POST['question'] ?? '',
                $respuestas,
                $_POST['correct_response_id'] ?? '',
                $imagenNueva,
                $quitarImagen
            );
        } catch (\DomainException $error) {
            $this->eliminarImagenSubida($rutaImagenNueva);
            $this->guardarMensajePregunta($error->getMessage(), 'error');
            Redirect::to('/Preguntados/moderador/editarPregunta?id=' . (int) $id);
            return;
        } catch (\Throwable $error) {
            $this->eliminarImagenSubida($rutaImagenNueva);
            throw $error;
        }

        $mensaje = 'La pregunta fue actualizada.';
        if ($imagenNueva !== null || $quitarImagen) {
            $imagenVigente = $imagenNueva;
            if ($imagenAnterior !== null && $imagenAnterior !== $imagenVigente) {
                if (!$this->eliminarImagenSiNoReferenciada($imagenAnterior)) {
                    $mensaje = 'La pregunta se guardó, pero no se pudo eliminar la imagen anterior.';
                }
            }
        }
        $this->guardarMensajePregunta($mensaje, $mensaje === 'La pregunta fue actualizada.' ? 'exito' : 'error');

        Redirect::to('/Preguntados/moderador/preguntas');
    }

    //METODOS PRIVADOS 

    private function mostrarCategorias($formulario = null, $error = null, $mensaje = null)
    {
        $this->asegurarCsrfToken();
        $editandoId = (int) ($formulario['id'] ?? 0);

        $categorias = $this->model->listarCategoriasConDetalle();
        foreach ($categorias as &$categoria) {
            $categoria['editando'] = (int) $categoria['id'] === $editandoId;
        }
        unset($categoria);

        $this->render->renderiza('moderadorCategorias', [
            'pendientes' => $this->model->contarReportesPendientes(),
            'sugerenciasPendientes' => $this->contarSugerenciasPendientes(),
            'categorias' => $categorias,
            'formEditando' => $editandoId > 0,
            'csrfToken' => $_SESSION['csrf_moderador'],
            'formId' => $editandoId ?: '',
            'formNombre' => $formulario['name'] ?? '',
            'formColor' => strtolower($formulario['color'] ?? '#9A6BF2'),
            'formIcono' => $formulario['icono'] ?? null,
            'error' => $error,
            'mensaje' => $mensaje,
        ]);
    }

        private function guardarSvg($archivo)
    {
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'] ?? '')) {
            return ['error' => 'No se pudo subir el SVG. Probá de nuevo.'];
        }
        if ((int) ($archivo['size'] ?? 0) > 300 * 1024) {
            return ['error' => 'El SVG no puede pesar más de 300 KB.'];
        }
        if (strtolower(pathinfo($archivo['name'] ?? '', PATHINFO_EXTENSION)) !== 'svg') {
            return ['error' => 'El archivo tiene que ser .svg.'];
        }

        $contenido = file_get_contents($archivo['tmp_name']);
        if ($contenido === false || !preg_match('/<svg[\s>]/i', $contenido)) {
            return ['error' => 'El archivo no es un SVG válido.'];
        }


        $peligroso = '/<script|<foreignobject|<iframe|<embed|<object|<!entity|\son[a-z]+\s*=|javascript:'
            . '|href\s*=\s*["\']\s*(?!#|data:image\/)/i';
        if (preg_match($peligroso, $contenido)) {
            return ['error' => 'El SVG tiene scripts o enlaces externos. Exportalo de nuevo como SVG simple.'];
        }

        $directorio = 'public/img/categorias';
        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        $nombre = bin2hex(random_bytes(8)) . '.svg';
        if (!move_uploaded_file($archivo['tmp_name'], $directorio . '/' . $nombre)) {
            return ['error' => 'No se pudo guardar el SVG.'];
        }

        return ['ruta' => 'categorias/' . $nombre];
    }

    private function contarSugerenciasPendientes()
    {
        return count($this->sugerenciaModel->mostrarPreguntasSugeridas('PENDIENTE'));
    }

    private function renderizarAltaPregunta(array $categorias, $error = null)
    {
        $this->asegurarCsrfToken();
        $this->render->renderiza('moderadorAltaPregunta', [
            'categorias' => $categorias,
            'pendientes' => $this->model->contarReportesPendientes(),
            'sugerenciasPendientes' => $this->contarSugerenciasPendientes(),
            'csrfToken' => $_SESSION['csrf_moderador'],
            'error' => $error,
        ]);
    }

    private function asegurarCsrfToken()
    {
        if (empty($_SESSION['csrf_moderador'])) {
            $_SESSION['csrf_moderador'] = bin2hex(random_bytes(32));
        }
    }

    private function csrfSugerenciasValido($tokenEnviado)
    {
        $tokenSesion = $_SESSION['csrf_moderador'] ?? '';
        return is_string($tokenSesion) && $tokenSesion !== ''
            && is_string($tokenEnviado) && hash_equals($tokenSesion, $tokenEnviado);
    }

    private function guardarMensajePregunta($texto, $tipo)
    {
        $_SESSION['mensaje_preguntas'] = ['texto' => $texto, 'tipo' => $tipo];
    }

    private function consumirMensajePregunta()
    {
        $mensaje = $_SESSION['mensaje_preguntas'] ?? null;
        unset($_SESSION['mensaje_preguntas']);
        return $mensaje;
    }

    private function eliminarImagenSubida($ruta)
    {
        if ($ruta !== null && is_file($ruta) && !unlink($ruta)) {
            throw new \RuntimeException('No se pudo eliminar una imagen de pregunta que no se guardó.');
        }
    }

    private function eliminarImagenSiNoReferenciada($filename)
    {
        if (!is_string($filename) || !preg_match('/\A[a-f0-9]{32}\.(jpg|png)\z/i', $filename)
            || $this->model->imagenReferenciada($filename)) {
            return true;
        }

        $ruta = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'img-preguntas' . DIRECTORY_SEPARATOR . $filename;
        if (is_file($ruta) && !unlink($ruta)) {
            return false;
        }

        return true;
    }
}
