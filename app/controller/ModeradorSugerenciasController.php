<?php

namespace app\controller;

use ModeradorAccess;
use Redirect;
use Request;

class ModeradorSugerenciasController
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

    public function sugerencias(){
        
        ModeradorAccess::exigir($this->model);

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
            $pregunta['mostrarMotivoRechazo'] = $estado === 'RECHAZADA';
            if ($pregunta['mostrarMotivoRechazo'] && trim((string) ($pregunta['motivo_rechazo'] ?? '')) === '') {
                $pregunta['motivo_rechazo'] = 'Sin motivo registrado.';
            }
        }
        unset($pregunta);

        $this->asegurarCsrfToken();

        $mensaje = $_SESSION['mensaje_moderador'] ?? null;
        unset($_SESSION['mensaje_moderador']);

        $datos = [
            'sugerencias' => count($preguntas),
            'pendientes' => $this->model->contarReportesPendientes(),
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
            'csrfToken' => $_SESSION['csrf_moderador'],
            'mensaje' => $mensaje,
            'hayMensaje' => $mensaje !== null,
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

        $this->asegurarCsrfToken();

        $mensaje = $_SESSION['mensaje_moderador'] ?? null;
        unset($_SESSION['mensaje_moderador']);

        $datos = [
            'sugerenciaId' => $sugerencia['id'],
            'pregunta' => $sugerencia['pregunta'],
            'pendientes' => $this->model->contarReportesPendientes(),
            'categorias' => $categorias,
            'respuestas' => $respuestas,
            'sugerenciasPendientes' => count($pendientes),
            'csrfToken' => $_SESSION['csrf_moderador'],
            'imagenSugerida' => $sugerencia['image_url'] ?? null,
            'hayImagenSugerida' => is_string($sugerencia['image_url'] ?? null)
                && $sugerencia['image_url'] !== '',
            'mensaje' => $mensaje,
            'hayMensaje' => $mensaje !== null,
        ];

        $this->render->renderiza('moderadorSugerenciasEditar', $datos);
    }

    public function publicarSugerencias(){

         ModeradorAccess::exigir($this->model);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            return;
        }

        if (!$this->csrfSugerenciasValido($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
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
        'difficulty_level' => 2,
        ];

        $imagenSubida = null;
        try {
        $archivo = $_FILES['image'] ?? null;
        if ($archivo !== null && ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $imagenSubida = \ImageUploader::upload($archivo, 'app/img-preguntas');
            if ($imagenSubida === null) {
                throw new \DomainException('La imagen debe ser JPG o PNG y no superar los 5 MB.');
            }
        }
        $datosFinales['image_url'] = $imagenSubida;

        $this->sugerenciaModel->publicarSugerencia(
            $id,
            (int) $_SESSION['usuario_id'],
            $datosFinales
        );

        $this->guardarMensaje('La sugerencia fue aprobada y publicada.', 'exito');
        } catch (\DomainException $error) {
            $this->eliminarImagenSubida($imagenSubida);
            $this->guardarMensaje($error->getMessage(), 'error');
        } catch (\Throwable $error) {
            $this->eliminarImagenSubida($imagenSubida);
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

        if (!$this->csrfSugerenciasValido($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
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

    //METODOS PRIVADOS
    private function eliminarImagenSubida($filename)
    {
        if (!is_string($filename) || !preg_match('/\\A[a-f0-9]{32}\\.(jpg|png)\\z/i', $filename)) {
            return;
        }

        $ruta = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'img-preguntas'
            . DIRECTORY_SEPARATOR . $filename;
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }

    private function csrfSugerenciasValido($tokenEnviado)
    {
        $tokenSesion = $_SESSION['csrf_moderador'] ?? '';

        return is_string($tokenSesion)
            && is_string($tokenEnviado)
            && $tokenSesion !== ''
            && hash_equals($tokenSesion, $tokenEnviado);
    }

    private function asegurarCsrfToken()
    {
        if (empty($_SESSION['csrf_moderador'])) {
            $_SESSION['csrf_moderador'] = bin2hex(random_bytes(32));
        }
    }
    
    private function guardarMensaje($texto, $tipo)
    {
        $_SESSION['mensaje_moderador'] = [
            'texto' => $texto,
            'tipo' => $tipo,
        ];
    }
    
    private function contarSugerenciasPendientes()
    {
        return count($this->sugerenciaModel->mostrarPreguntasSugeridas('PENDIENTE'));
    }
}
