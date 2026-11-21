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

        $layoutData = ['esModerador' => false, 'esAdmin' => false, 'reportesPendientes' => 0];
        if (isset($_SESSION['usuario_id'])) {
            $userId = (int) $_SESSION['usuario_id'];

            if ($this->adminModel !== null && $this->adminModel->esAdmin($userId)) {
                $layoutData['esAdmin'] = true;
                $_SESSION['rol'] = 'ADMIN';
            } elseif ($this->moderadorModel !== null && $this->moderadorModel->esModerador($userId)) {
                $layoutData['esModerador'] = true;
                $_SESSION['rol'] = 'MODERADOR';
                $layoutData['reportesPendientes'] = $this->moderadorModel->contarReportesPendientes();
            } else {
                $_SESSION['rol'] = 'JUGADOR';
            }
        }

        $data['esModerador'] = $layoutData['esModerador'];
        $data['esAdmin'] = $layoutData['esAdmin'];
        $content = $this->mustache->render($view . "View", $data);
        $layout = require($this->viewPath . "/layout.php");
        $layout($content, $layoutData);
    }
}
