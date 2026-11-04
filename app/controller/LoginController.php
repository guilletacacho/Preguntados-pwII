<?php 

namespace app\controller;

use Redirect;
use Request;

class LoginController
{
    private $model;
    private $render;
    private $presence;

    public function __construct($model,$render,$presence)
    {
        $this->model = $model;
        $this->render = $render;
        $this->presence = $presence;
    }

    
    public function show(){
        $error = "";
        if (Request::get("login_error") === "1") {
            $error = "Usuario o contraseña incorrectos.";
        } elseif (Request::get("login_error") === "2") {
            $error = "Cuenta no validada.";
        }

        $data = [
            "error" => $error,
            "pendiente" => Request::get("registro") === "pendiente",
            "verificado" => Request::get("registro") === "verificado"
        ];

        $this->render->renderiza("login", $data);
    }

    public function login()
    {
        $usuario = $this->model->autenticar(Request::post('Usuario'), Request::post('Contrasena'));

        if ($usuario === null) {
            Redirect::to('/Preguntados/?login_error=1');
            return;
        }

        if (!$usuario['verificado']) {
            Redirect::to('/Preguntados/?login_error=2');
            return;
        }

        $this->presence->remove(session_id());
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario'] = $usuario['usuario'];
        $this->presence->touch($usuario['id'], session_id());
        Redirect::to('/Preguntados/home');
    }

    public function logout()
    {
        $this->presence->remove(session_id());
        $_SESSION = [];
        session_destroy();
        Redirect::toIndex();
    }
}
