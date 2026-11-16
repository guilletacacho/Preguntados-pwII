<?php

require_once __DIR__ . "/../vendor/mustache/Mustache/Autoloader.php";
Mustache_Autoloader::register(__DIR__ . "/../vendor/mustache");

class MustacheRender
{
    private $mustache;
    private $viewPath;
    private $moderadorModel;

    public function __construct($moderadorModel = null)
    {
        $this->viewPath = dirname(__DIR__) . "/app/view";
        $this->moderadorModel = $moderadorModel;
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

        $layoutData = ['esModerador' => false, 'reportesPendientes' => 0];
        if (isset($_SESSION['usuario_id']) && $this->moderadorModel !== null) {
            $layoutData['esModerador'] = $this->moderadorModel->esModerador((int) $_SESSION['usuario_id']);
            $_SESSION['rol'] = $layoutData['esModerador'] ? 'MODERADOR' : 'JUGADOR';
            if ($layoutData['esModerador']) {
                $layoutData['reportesPendientes'] = $this->moderadorModel->contarReportesPendientes();
            }
        }

        $data['esModerador'] = $layoutData['esModerador'];
        $content = $this->mustache->render($view . "View", $data);
        $layout = require($this->viewPath . "/layout.php");
        $layout($content, $layoutData);
    }
}
