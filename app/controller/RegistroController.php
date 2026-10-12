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
        $data = "";

        $this->render->renderiza("registro",$data);
    }

    public function registrar(){
        $resultado = $this->model->insertar(
            Request::post('nombre'),
            Request::post('anioNacimiento'),
            Request::post('sexo'),
            Request::post('pais'),
            Request::post('usuario'),
            Request::post('contrasena')
        );

        if($resultado){
            Redirect::to('/Preguntados/?registro=ok');
        }
    }
}