<?php

namespace app;

use app\controller\LoginController;
use app\controller\HomeController;
use app\controller\RegistroController;
use app\controller\EmailController;
use app\controller\RankingController;
use app\controller\PartidaController;
use app\controller\ModeradorController;
use app\controller\AdminController;
use app\model\PartidaModel;
use app\model\ModeradorModel;
use app\model\AdminModel;
use app\model\PerfilModel;
use app\model\LoginModel;
use app\model\RegistroModel;
use app\model\RankingModel;
use MustacheRender;
use MyDatabase;
use Router;
use MailHelper;

require_once("helper/Autoloader.php");



class Configuration
{
    private $database = null;

    public function __construct()
    {
    }
    //inicio de cargar controladores y model para redireccionamientos
    public function getLoginController()
    {
        return new LoginController(
            $this->getLoginModel(),
            $this->gerRender(),
            $this->getPresenceModel()
        );
    }

    public function validarSesionActiva()
    {
        if (!isset($_SESSION['usuario_id'])) {
            return;
        }

        $login = $this->getLoginModel();
        $estado = $login->estadoSesion($_SESSION['usuario_id']);
        if ($estado && !$estado['bloqueado'] && (int) $estado['version_sesion'] === (int) ($_SESSION['version_sesion'] ?? 0)) {
            return;
        }

        $login->liberarSesionUnica($_SESSION['usuario_id'], $_SESSION['sesion_unica'] ?? '');
        $this->getPresenceModel()->remove(session_id());
        $_SESSION = [];
        session_destroy();
        \Redirect::to(!empty($estado['bloqueado']) ? '/Preguntados/?login_error=3' : '/Preguntados/');
    }

    public function getHomeController()
    {
        return new HomeController(
            $this->gerRender(),
            $this->getPresenceModel(),
            new \app\model\CuriosityModel()
        );
    }

    public function getPresenceController()
    {
        return new \app\controller\PresenceController($this->getPresenceModel());
    }

    private function getPresenceModel()
    {
        return new \app\model\PresenceModel($this->getDatabase());
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

    public function getRankingController()
    {
        return new RankingController(
            $this->getRankingModel(),
            $this->gerRender()
        );
    }

    public function getPartidaController(){
        return new PartidaController(
            $this->getPartidaModel(),
            $this->gerRender()
        );
    }

    public function getModeradorController()
    {   
        return new ModeradorController(
            $this->getModeradorModel(),
            $this->gerRender()
        );
    }

    public function getAdminController()
    {
        return new AdminController(
            $this->getAdminModel(),
            $this->gerRender()
        );
    }

    public function getPerfilController()
    {
        return new \app\controller\PerfilController(
            $this->getPerfilModel(),
            $this->gerRender()
        );
    }

    //fin de carga de controladores y model para redireccionamientos
    private function getDatabase()
    {
        if ($this->database !== null) {
            return $this->database;
        }

        $configFile = "config/config.ini";

        if (!file_exists($configFile)) {
            die("Error: no se encontró el archivo de configuración. Crea 'config/config.ini' copiando 'config/config.example.ini'.");
        }

        $config = parse_ini_file($configFile);

        $this->database = new MyDatabase($config["db_host"],
            $config["db_user"],
            $config["db_pass"],
            $config["db_name"],
            $config["db_port"]
        );

        return $this->database;
    }

    private function gerRender()
    {
        return new MustacheRender($this->getModeradorModel(), $this->getAdminModel());
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

    private function getPerfilModel(){
        return new PerfilModel(
            $this->getDatabase()
        );
    }

    private function getRankingModel(){
        return new RankingModel(
            $this->getDatabase()
        );
    }

    private function getPartidaModel(){
        return new PartidaModel(
            $this->getDatabase()
        );
    }

    private function getModeradorModel(){
        return new ModeradorModel(
            $this->getDatabase()
        );
    }

    private function getAdminModel(){
        return new AdminModel(
            $this->getDatabase()
        );
    }

    public function getRouter()
    {
        return new Router($this, "login", "show");
    }
}
