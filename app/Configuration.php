<?php

namespace app;

use app\controller\LoginController;
use app\controller\HomeController;
use app\controller\RegistroController;
use app\controller\EmailController;

use app\model\LoginModel;
use app\model\RegistroModel;
use MustacheRender;
use MyDatabase;
use Router;
use MailHelper;

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
            $this->gerRender(),
            $this->getMailHelper()
        );
    }

    public function getEmailController(){
        return new EmailController(
            $this->getRegistroModel(),
            $this->gerRender()
        );
    }

    //fin de carga de controladores y model para redireccionamientos

    private function getDatabase()
    {
        $configFile = "config/config.ini";

        if (!file_exists($configFile)) {
            die("Error: no se encontró el archivo de configuración. Crea 'config/config.ini' copiando 'config/config.example.ini'.");
        }

        $config = parse_ini_file($configFile);

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

    private function getMailHelper()
    {
        return new MailHelper();
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
