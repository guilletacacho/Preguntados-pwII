<?php

namespace app;

use app\controller\EventoController;
use app\controller\LoginController;
use app\controller\LugaresController;
use app\controller\ReservaController;
use app\model\EventoModel;
use app\model\LoginModel;
use app\model\LugaresModel;
use app\model\ReservaModel;
use MustacheRender;
use MyDatabase;
use Router;

require_once("helper/Autoloader.php");



class Configuration
{

    public function __construct()
    {
    }

    public function getLugaresController()
    {
        return new LugaresController(
            $this->getLugaresModel(),
            $this->gerRender()
        );
    }

    public function getReservaController()
    {
        return new ReservaController(
            $this->getReservaModel(),
            $this->gerRender()
        );
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
            $this->getLoginModel()
        );
    }

    // Los privados
    private function getReservaModel()
    {
        return new ReservaModel(
            $this->getDatabase()
        );
    }

    private function getLugaresModel()
    {
        return new LugaresModel(
            $this->getDatabase()
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
        return new Router($this, "evento", "show");
    }
}
