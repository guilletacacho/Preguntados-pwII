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
        $data = ["anioActual" => date('Y')];
        $this->render->renderiza("registro",$data);
    }

    public function disponibilidad() {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $usuario = Request::get('usuario', '');
        echo json_encode(["disponible" => is_string($usuario) && trim($usuario) !== '' && !$this->model->existeUsuario(trim($usuario))]);
    }

     public function registrar() {
        $data = ["anioActual" => date('Y')];
        $errores = [];
        foreach (['nombre', 'anioNacimiento', 'sexo', 'pais', 'usuario', 'email'] as $campo) {
            $valor = Request::post($campo, '');
            $data[$campo] = is_string($valor) ? trim($valor) : '';
            if ($data[$campo] === '') {
                $errores[$campo] = 'Completa este campo.';
            }
        }
        if ($data['anioNacimiento'] !== '' && (!ctype_digit($data['anioNacimiento']) || (int)$data['anioNacimiento'] < 1900 || (int)$data['anioNacimiento'] > (int)date('Y'))) {
            $errores['anioNacimiento'] = 'Ingresa un año entre 1900 y ' . date('Y') . '.';
        }
        if (!in_array($data['sexo'], ['Femenino', 'Masculino', 'Otro'], true)) {
            $errores['sexo'] = 'Selecciona una opción válida.';
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)){
            $errores['email'] = 'Ingresa un correo electronico valido.';
        }
        $contrasena = Request::post('contrasena', '');
        $repetida = Request::post('repetirContrasena', '');

        if (!is_string($contrasena) || preg_match_all('/./us', $contrasena) < 8) {
            $errores['contrasena'] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if (!is_string($repetida) || $repetida === '' || $contrasena !== $repetida) {
            $errores['repetirContrasena'] = 'Las contraseñas no coinciden.';
        }
        if ($data['usuario'] !== '' && $this->model->existeUsuario($data['usuario'])) {
            $errores['usuario'] = 'El usuario ya existe.';
        }
        if (
            $data['email'] !== '' &&
            filter_var($data['email'], FILTER_VALIDATE_EMAIL) &&
            $this->model->existeEmail($data['email'])
        ) {
            $errores['email'] = 'El correo electronico ya esta registrado.';
        }
        $fotoUrl = null;
        $archivo = $_FILES['imagen_url'] ?? null;

        if ($archivo !== null && $archivo['error'] !== UPLOAD_ERR_NO_FILE){
            $fotoUrl = $this->subirMiImagen($archivo);

            if ($fotoUrl === null){
                $data['error'] = "No se pudo guardar la imagen.";
                $this->render->renderiza("registro", $data);
                return;
            }
        }

        if (!$errores && $this->model->insertar(
        $data['nombre'],
        $data['anioNacimiento'],
        $data['sexo'],
        $data['pais'],
        $data['usuario'],
        $data['email'],
        $contrasena,
        $fotoUrl
    )) {
        $usuarioDb = $this->model->obtenerUsuarioPorNombre($data['usuario']);

        if (!$usuarioDb) {
            error_log("No se pudo encontrar el usuario recién registrado.");
            Redirect::to('/Preguntados/?registro=error');
            return;
        }

        $token = bin2hex(random_bytes(32));
        $expira = date('Y-m-d H:i:s', strtotime('+24 hours'));

        if (!$this->model->guardarToken($usuarioDb['Id'], $token, $expira)) {
            error_log("No se pudo guardar el token de verificación.");
            Redirect::to('/Preguntados/?registro=error');
            return;
        }

        $emailEnviado = $this->mailHelper->enviarVerificacion(
            $data['email'],
            $data['nombre'],
            $token
        );

        if (!$emailEnviado) {
            error_log("No se pudo enviar el email de verificación.");
            Redirect::to('/Preguntados/?registro=error');
            return;
        }

        Redirect::to('/Preguntados/?registro=pendiente');
        return;
    }
        $data['errores'] = $errores;
        $data['error'] = $errores ? 'Revisa los campos indicados para completar el registro.' : 'No se pudo completar el registro. Inténtalo de nuevo.';
        foreach (['Femenino', 'Masculino', 'Otro'] as $sexo) {
            $data['sexo' . $sexo] = $data['sexo'] === $sexo;
        }
        $this->render->renderiza('registro', $data);

    }

    //Function para subir imagen 

     private function subirMiImagen($archivo){

        // Verifica que PHP haya recibido el archivo correctamente.
        if(!isset($archivo['error']) || $archivo['error'] !== UPLOAD_ERR_OK){
            return null;
        }

        // Rechaza imágenes que superen los 5 MB.
        if ($archivo['size'] > 5 * 1024 * 1024){
            return null;
        }
        $tiposPermitidos = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
        ];

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $tipoReal = $finfo->file($archivo['tmp_name']);

        // Acepta únicamente los tipos MIME permitidos.
        if ( !isset($tiposPermitidos[$tipoReal])){
            return null;
        }

        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $nombreUnico = uniqid() . '.' . $extension;

        $directorio = 'app/img-foto-perfil';

        // Crea la carpeta si todavía no existe.
        if(!is_dir($directorio)){
            mkdir($directorio,0755,true);
        }

        $ruta = $directorio . '/' . $nombreUnico;

        // Comprueba que el archivo se haya movido a la carpeta destino.
        if (!move_uploaded_file($archivo['tmp_name'], $ruta)){
            return null;
        }
        return $nombreUnico;

    }
}