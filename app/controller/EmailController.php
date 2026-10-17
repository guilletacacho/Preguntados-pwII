<?php

namespace app\controller;

use Redirect;
use Request;

class EmailController{

    private $model;
    private $render;

    public function __construct($model,$render)
    {
        $this->model = $model;
        $this->render = $render;
    }

    public function verificar(){
        $token = Request::get("token");

        if(empty($token)){
            Redirect::to('/Preguntados/?registro=error');
            return;
        }

        $usuario = $this->model->obtenerUsuarioPorToken($token);

        if(!$usuario){
            $this->render->renderiza("verificacion",[
                "error" => "El enlace de verificación es inválido o ha expirado."
            ]);
            return;
        }

        $this->model->marcarVerificado($usuario['Id']);
        $this->model->marcarTokenUsado($token);

        $this->render->renderiza("verificacion",[
            "exito" => true,
            "nombre" => $usuario['Nombre']
        ]);
    }
}
