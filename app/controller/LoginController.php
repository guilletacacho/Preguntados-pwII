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
        // Con la cuenta ya abierta este formulario es un callejón sin salida:
        // la pestaña nueva tiene que parar en su inicio, no en "Iniciar sesión".
        if (isset($_SESSION['usuario_id'])) {
            Redirect::to('/Preguntados/home/show');
        }

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

        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario'] = $usuario['usuario'];
        Redirect::to('/Preguntados/home');
    }

    public function logout()
    {
        $userId = (int) ($_SESSION['usuario_id'] ?? 0);
        $sessionKey = (string) ($_SESSION['sesion_unica'] ?? '');

        if ($userId > 0 && $sessionKey !== '') {
            $this->model->liberarSesionUnica($userId, $sessionKey);
        }

        $_SESSION = [];
        session_destroy();
        Redirect::toIndex();
    }
}
