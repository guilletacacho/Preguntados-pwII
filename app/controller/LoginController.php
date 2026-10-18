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
        if (isset($_SESSION['usuario_id'])) {
            Redirect::to('/Preguntados/home/show');
        }

        $data = [
        "error" => Request::get("login_error") !== null
            ? "Usuario o contraseña incorrectos."
            : ""
        ];

        $this->render->renderiza("login", $data);
    }

    public function login()
    {
        $usuario = $this->model->autenticar(Request::post('Usuario'), Request::post('Contrasena'));

        if ($usuario === null) {
            Redirect::to('/Preguntados/?login_error=1');
        }

        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario'] = $usuario['usuario'];
        Redirect::to('/Preguntados/home/show');
    }

    public function logout()
    {
        $_SESSION = [];
        session_destroy();
        Redirect::toIndex();
    }
}
