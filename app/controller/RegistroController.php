<?php 

namespace app\controller;

use Redirect;
use Request;

class RegistroController{

    private $model;
    private $render;
    private $mailHelper;

    public function __construct($model,$render,$mailHelper)
    {
        $this->model = $model;
        $this->render = $render;
        $this->mailHelper = $mailHelper;
    }

    public function show(){
        $data = [];
        $this->render->renderiza("registro",$data);
    }

    public function registrar(){
        $usuario = Request::post("usuario");
        $email = Request::post("email");
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

        if($this->model->existeEmail($email)){
            $this->render->renderiza("registro",["error" => "El correo electrónico ya está registrado."]);
            return;
        }

        $resultado = $this->model->insertar(
            Request::post('nombre'),
            Request::post('anioNacimiento'),
            Request::post('sexo'),
            Request::post('pais'),
            $usuario,
            $email,
            $contrasenia
        );

        if($resultado){
            // Obtener el ID del usuario recién insertado
            $usuarioDb = $this->model->obtenerUsuarioPorNombre($usuario);
            
            // Generar token de verificación
            $token = bin2hex(random_bytes(32));
            $expira = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $this->model->guardarToken($usuarioDb['Id'], $token, $expira);

            // Enviar email de verificación
            $emailEnviado = $this->mailHelper->enviarVerificacion(
                $email,
                Request::post('nombre'),
                $token
            );

            if($emailEnviado){
                Redirect::to('/Preguntados/?registro=pendiente');
            } else {
                // Si falla el envío, eliminar el usuario y mostrar error
                error_log("No se pudo enviar el email de verificación a: $email");
                Redirect::to('/Preguntados/?registro=error');
            }
        }
    }
}
