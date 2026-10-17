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

        $fotoUrl = null;
        $archivo = $_FILES['imagen_url'] ?? null;

        if ($archivo !== null && $archivo['error'] !== UPLOAD_ERR_NO_FILE){
            $fotoUrl = $this->subirMiImagen($archivo);

            if ($fotoUrl === null){
                $this->render->renderiza("registro", [
                    "error" => "No se pudo guardar la imagen."
                ]);
                return;
            }
        }

        $resultado = $this->model->insertar(
            Request::post('nombre'),
            Request::post('anioNacimiento'),
            Request::post('sexo'),
            Request::post('pais'),
            $usuario,
            $contrasenia,
            $fotoUrl
        );

        if($resultado){
            Redirect::to('/Preguntados/?registro=ok');
        }
    }

    private function subirMiImagen($archivo){

        if(!isset($archivo['error']) || $archivo['error'] !== UPLOAD_ERR_OK){
            return null;
        }

        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $nombreUnico = uniqid() . '.' . $extension;

        $directorio = 'app/img-foto-perfil';

        if(!is_dir($directorio)){
            mkdir($directorio,0755,true);
        }

        $ruta = $directorio . '/' . $nombreUnico;

        if (!move_uploaded_file($archivo['tmp_name'], $ruta)){
            return null;
        }
        return $nombreUnico;

    }

}