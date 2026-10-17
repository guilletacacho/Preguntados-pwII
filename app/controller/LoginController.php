<?php 

namespace app\controller;

use Redirect;
use Request;

class LoginController
{
    private $model;
    private $render;

    public function __construct($model,$render)
    {
        $this->model = $model;
        $this->render = $render;
    }

    
    public function show(){
        $error = "";
        if (Request::get("login_error") === "1") {
            $error = "Usuario o contraseña incorrectos.";
        } elseif (Request::get("login_error") === "2") {
            $error = "Tu cuenta no ha sido verificada. Revisa tu correo para activarla.";
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

        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario'] = $usuario['usuario'];
        Redirect::to('/Preguntados/home/show');
    }

    public function logout()
    {
        session_destroy();
        Redirect::toIndex();
    }
}
