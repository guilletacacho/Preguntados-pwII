<?php

namespace app;

use app\controller\LoginController;
use app\controller\HomeController;
use app\controller\RegistroController;

use app\model\LoginModel;
use app\model\RegistroModel;
use MustacheRender;
use MyDatabase;
use Router;

require_once("helper/Autoloader.php");



class Configuration
{

    public function __construct()
    {
    }
    //inicio de cargar controladores y model para redireccionamientos
    public function getLoginController()
    {
        return new LoginController(
            $this->getLoginModel(),
            $this->gerRender()
        );
    }

    public function getHomeController()
    {
    return new HomeController(
        $this->gerRender()
    );
    }

    public function getRegistroController(){
        return new RegistroController(
            $this->getRegistroModel(),
            $this->gerRender()
        );
    }

    //fin de carga de controladores y model para redireccionamientos

    public function getPerfilController()
    {
        return new \app\controller\PerfilController($this->gerRender());
    }

    private function getDatabase()
    {
        $configPath = dirname(__DIR__) . "/config/config.ini";
        if (!is_readable($configPath)) {
            throw new \RuntimeException("Falta config/config.ini. Copia config/config.example.ini y configura los datos de MySQL.");
        }

        $config = parse_ini_file($configPath, false, INI_SCANNER_RAW);
        if ($config === false) {
            throw new \RuntimeException("No se pudo leer config/config.ini. Revisa su formato.");
        }

        foreach (["db_host", "db_user", "db_pass", "db_name", "db_port"] as $key) {
            if (!array_key_exists($key, $config) || ($key !== "db_pass" && trim($config[$key]) === "")) {
                throw new \RuntimeException("Falta configurar " . $key . " en config/config.ini.");
            }
        }

        return new MyDatabase($config["db_host"],
            $config["db_user"],
            $config["db_pass"],
            $config["db_name"],
            $config["db_port"]
        );
    }

    private function gerRender()
    {
        return new MustacheRender();
    }

    private function getLoginModel()
    {
        return new LoginModel(
            $this->getDatabase()
        );
    }

    private function getRegistroModel(){
        return new RegistroModel(
            $this->getDatabase()
        );
    }

    public function getRouter()
    {
        return new Router($this, "login", "show");
    }
}
