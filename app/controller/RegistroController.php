<?php 

namespace app\controller;

use Redirect;
use Request;

class RegistroController{

    private $model;
    private $render;

    public function __construct($model,$render)
    {
        $this->model = $model;
        $this->render = $render;
    }

    public function show(){
        $data = [];
        $this->render->renderiza("registro",$data);
    }

    public function registrar(){
        $usuario = Request::post("usuario");
        $contrasenia = Request::post('contrasena');
        $contraseniaRepetida = Request::post('repetirContrasena');

        if($contrasenia !== $contraseniaRepetida){
            $this->render->renderiza("registro",["error" => "Las contraseñas no coinciden."]);
            return;
        }

        if($this->model->existeUsuario($usuario)){
            $this->render->renderiza("registro",["error" => "El usuario ya existe."]);
            return;
        }

        $resultado = $this->model->insertar(
            Request::post('nombre'),
            Request::post('anioNacimiento'),
            Request::post('sexo'),
            Request::post('pais'),
            Request::post($usuario),
            Request::post($contrasenia)
        );

        if($resultado){
            Redirect::to('/Preguntados/?registro=ok');
        }
    }
}