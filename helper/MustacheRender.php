<?php

require_once __DIR__ . "/../vendor/mustache/Mustache/Autoloader.php";
Mustache_Autoloader::register(__DIR__ . "/../vendor/mustache");

class MustacheRender
{
    private $mustache;
    private $viewPath;
    private $moderadorModel;
    private $adminModel;

    public function __construct($moderadorModel = null, $adminModel = null)
    {
        $this->viewPath = dirname(__DIR__) . "/app/view";
        $this->moderadorModel = $moderadorModel;
        $this->adminModel = $adminModel;
        $this->mustache = new Mustache_Engine([
            "delimiters" => "{{ }}",
            "loader" => new Mustache_Loader_FilesystemLoader($this->viewPath, ["extension" => ".mustache"])
        ]);
    }

    public function renderiza($view, $data = [])
    {
        $basePath = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/index.php")), "/");
        $data["basePath"] = $basePath;
        $data['ruletaVersion'] = filemtime(__DIR__ . '/../public/js/ruleta.js');

        $enAdministracion = strpos($view, 'admin') === 0;
        $enModeracion = stripos($view, 'moderador') === 0;
        $enInicio = $view === 'home';
        $enRanking = $view === 'ranking';
        $enFaq = $view === 'ayudaPreguntasFrecuentes';
        $enTerminos = $view === 'ayudaTerminos';
        $esSugerencias = $view === 'sugerencias';
        $layoutData = [
            'esModerador' => false,
            'esAdmin' => false,
            'puedeModerar' => false,
            'reportesPendientes' => 0,
            'enAdministracion' => $enAdministracion,
            'enModeracion' => $enModeracion,
            'enInicio' => $enInicio,
            'enRanking' => $enRanking,
            'enFaq' => $enFaq,
            'enTerminos' => $enTerminos,
            'partidaSalida' => $data['partidaSalida'] ?? '',
            'tieneEstilosSugerencias' => $esSugerencias,
            'navegacionExpandida' => $enRanking || $enFaq || $enTerminos,
            'tieneEnlacesAyuda' => $enInicio || $enRanking || $view === 'sugerencias' || $enFaq || $enTerminos,
            'tieneNavegacionLateral' => $enInicio || $enRanking || $view === 'sugerencias' || $enAdministracion || $enModeracion || $enFaq || $enTerminos,
        ];
        if (isset($_SESSION['usuario_id'])) {
            $userId = (int) $_SESSION['usuario_id'];

            if ($this->adminModel !== null && $this->adminModel->esAdmin($userId)) {
                $layoutData['esAdmin'] = true;
                $_SESSION['rol'] = 'ADMIN';
            } elseif ($this->moderadorModel !== null && $this->moderadorModel->puedeModerar($userId)) {
                $layoutData['esModerador'] = true;
                $_SESSION['rol'] = 'MODERADOR';
            } else {
                $_SESSION['rol'] = 'JUGADOR';
            }

            $layoutData['puedeModerar'] = $layoutData['esAdmin'] || $layoutData['esModerador'];
            if ($layoutData['puedeModerar'] && $this->moderadorModel !== null) {
                $layoutData['reportesPendientes'] = $data['pendientes'] ?? $this->moderadorModel->contarReportesPendientes();
            }
        }

        $data = $layoutData + $data;
        $data['enResumenModeracion'] = $view === 'moderador';
        $data['enReportes'] = $view === 'ModeradorReportes';
        $data['ubicacionReportes'] = empty($data['modoEditor']) && empty($data['hayDetalle']) ? 'page' : 'location';
        $data['enSugerencias'] = in_array($view, ['moderadorSugerencias', 'moderadorSugerenciasEditar'], true);
        $data['ubicacionSugerencias'] = $view === 'moderadorSugerencias' ? 'page' : 'location';
        $data['enPreguntas'] = in_array($view, ['moderadorPreguntas', 'moderadorAltaPregunta', 'moderadorEditarPregunta'], true);
        $data['ubicacionPreguntas'] = $view === 'moderadorPreguntas' ? 'page' : 'location';
        $data['enCategorias'] = $view === 'moderadorCategorias';
        $data['enUsuariosAdmin'] = in_array($view, ['adminUsuarios', 'adminAltaUsuario', 'adminEditarUsuario'], true);
        $data['ubicacionUsuariosAdmin'] = $view === 'adminUsuarios' ? 'page' : 'location';
        $content = $this->mustache->render($view . "View", $data);
        $layout = require($this->viewPath . "/layout.php");
        $layout($content, $layoutData);
    }
}
