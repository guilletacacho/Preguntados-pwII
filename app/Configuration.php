<?php

namespace app;

use app\controller\EventoController;
use app\controller\LoginController;
use app\model\EventoModel;
use app\model\LoginModel;
use MustacheRender;
use MyDatabase;
use Router;

require_once("helper/Autoloader.php");



class Configuration
{

    public function __construct()
    {
    }

    public function getEventoController()
    {
        return new EventoController(
            $this->getEventoModel(),
            $this->gerRender()
        );
    }

    public function getLoginController()
    {
        return new LoginController(
            $this->getLoginModel(),
            $this->gerRender()
        );
    }


    private function getDatabase()
    {
        $config = parse_ini_file("config/config.ini");

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

    private function getEventoModel()
    {
        return new EventoModel(
            $this->getDatabase()
        );
    }

    private function getLoginModel()
    {
        return new LoginModel(
            $this->getDatabase()
        );
    }

    public function getRouter()
    {
        return new Router($this, "login", "show");
    }
}
