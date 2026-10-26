<?php

namespace app\controller;

class PerfilController
{
    private $model;
    private $render;

    public function __construct($model, $render)
    {
        $this->model = $model;
        $this->render = $render;
    }

    public function show()
    {
        $this->exigirSesion();

        $usuarioPedido = \Request::get('usuario');

        if ($usuarioPedido === null) {
            $fila = $this->model->obtenerPorId($_SESSION['usuario_id']);
        } else {
            $fila = is_string($usuarioPedido) ? $this->model->obtenerPorUsuario($usuarioPedido) : null;
        }

        if (!$fila) {
            \Redirect::toIndex();
        }

        $data = [
            'nombre' => $fila['Nombre'],
            'usuario' => $fila['Usuario'],
            'usuarioUrl' => urlencode($fila['Usuario']),
            'inicial' => mb_strtoupper(mb_substr($fila['Nombre'], 0, 1)),
            'pais' => $fila['Pais'],
            'provincia' => $fila['Provincia'],
            'ubicacion' => implode(', ', array_filter([$fila['Provincia'], $fila['Pais']])),
            'fotoUrl' => $fila['Foto_url'],
            'esMiPerfil' => (int) $fila['Id'] === (int) $_SESSION['usuario_id'],
            'puntaje' => 0,
            'puesto' => null,
            'cantidadPartidas' => 0,
            'hayPartidas' => false,
            'partidas' => [],
            'enlacePerfil' => $this->armarEnlacePerfil($fila['Usuario']),
        ];

        $this->render->renderiza('perfil', $data);
    }

    public function qr()
    {
        $usuario = \Request::get('usuario', '');

        if (!is_string($usuario) || trim($usuario) === '' || !$this->model->obtenerPorUsuario($usuario)) {
            http_response_code(404);
            exit;
        }

        require_once 'vendor/phpqrcode/qrlib.php';
        \QRcode::png($this->armarEnlacePerfil($usuario), false, QR_ECLEVEL_M, 6, 1);
        exit;
    }

    public function editar()
    {
        $this->exigirSesion();

        $fila = $this->model->obtenerParaEditar($_SESSION['usuario_id']);

        if (!$fila) {
            \Redirect::toIndex();
        }

        $this->renderizarEditar([
            'nombre' => $fila['Nombre'],
            'anioNacimiento' => $fila['Anio_nacimiento'],
            'sexo' => $fila['Sexo'],
            'provincia' => $fila['Provincia'],
            'pais' => $fila['Pais'],
            'email' => $fila['Email'],
            'usuario' => $fila['Usuario'],
            'fotoUrl' => $fila['Foto_url'],
        ]);
    }

    public function guardar()
    {
        $this->exigirSesion();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            \Redirect::to('/Preguntados/perfil/editar');
        }

        $id = $_SESSION['usuario_id'];
        $actual = $this->model->obtenerParaEditar($id);

        if (!$actual) {
            \Redirect::toIndex();
        }

        $data = [
            'usuario' => $actual['Usuario'],
            'fotoUrl' => $actual['Foto_url'],
        ];
        $errores = [];

        foreach (['nombre', 'anioNacimiento', 'sexo', 'provincia', 'pais', 'email'] as $campo) {
            $data[$campo] = trim($this->textoPost($campo));
            if ($data[$campo] === '') {
                $errores[$campo] = 'Completá este campo.';
            }
        }

        if (mb_strlen($data['nombre']) > 50) {
            $errores['nombre'] = 'El nombre puede tener hasta 50 caracteres.';
        }
        if (mb_strlen($data['provincia']) > 150) {
            $errores['provincia'] = 'Este campo puede tener hasta 150 caracteres.';
        }
        if (mb_strlen($data['pais']) > 150) {
            $errores['pais'] = 'Este campo puede tener hasta 150 caracteres.';
        }
        if ($data['anioNacimiento'] !== '' && (!ctype_digit($data['anioNacimiento']) || (int) $data['anioNacimiento'] < 1900 || (int) $data['anioNacimiento'] > (int) date('Y'))) {
            $errores['anioNacimiento'] = 'Ingresá un año entre 1900 y ' . date('Y') . '.';
        }
        if (!in_array($data['sexo'], ['Femenino', 'Masculino', 'Otro'], true)) {
            $errores['sexo'] = 'Seleccioná una opción válida.';
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errores['email'] = 'Ingresá un correo electrónico válido.';
        } elseif ($data['email'] !== '' && $this->model->existeEmailDeOtro($data['email'], $id)) {
            $errores['email'] = 'Ese correo ya está registrado en otra cuenta.';
        }

        $contrasenaActual = $this->textoPost('contrasenaActual');
        $contrasena = $this->textoPost('contrasena');
        $repetida = $this->textoPost('repetirContrasena');
        $cambiaContrasena = $contrasenaActual !== '' || $contrasena !== '' || $repetida !== '';

        if ($cambiaContrasena) {
            if (!$this->model->contrasenaCorrecta($id, $contrasenaActual)) {
                $errores['contrasenaActual'] = 'La contraseña actual no es correcta.';
            }
            if (preg_match_all('/./us', $contrasena) < 8) {
                $errores['contrasena'] = 'La contraseña nueva debe tener al menos 8 caracteres.';
            }
            if ($contrasena !== $repetida) {
                $errores['repetirContrasena'] = 'Las contraseñas no coinciden.';
            }
        }

        $fotoNueva = null;
        $archivo = $_FILES['imagen_url'] ?? null;

        if (!$errores && $archivo !== null && $archivo['error'] !== UPLOAD_ERR_NO_FILE) {
            $fotoNueva = $this->subirImagen($archivo);
            if ($fotoNueva === null) {
                $errores['imagen_url'] = 'No se pudo guardar la imagen.';
            }
        }

        if ($errores) {
            $data['errores'] = $errores;
            $data['error'] = 'Revisá los campos indicados.';
            $data['erroresContrasena'] = isset($errores['contrasenaActual']) || isset($errores['contrasena']) || isset($errores['repetirContrasena']);
            $this->renderizarEditar($data);
            return;
        }

        $this->model->actualizar($id, $data['nombre'], $data['anioNacimiento'], $data['sexo'], $data['provincia'], $data['pais'], $data['email'], $fotoNueva);

        if ($cambiaContrasena) {
            $this->model->actualizarContrasena($id, $contrasena);
        }

        if ($fotoNueva !== null && $actual['Foto_url']) {
            $fotoVieja = 'app/img-foto-perfil/' . basename($actual['Foto_url']);
            if (is_file($fotoVieja)) {
                unlink($fotoVieja);
            }
        }

        \Redirect::to('/Preguntados/perfil');
    }

    private function exigirSesion()
    {
        if (!isset($_SESSION['usuario_id'])) {
            \Redirect::toIndex();
        }
    }


    private function textoPost($campo)
    {
        $valor = \Request::post($campo, '');

        return is_string($valor) ? $valor : '';
    }

    private function renderizarEditar($data)
    {
        $data['anioActual'] = date('Y');
        foreach (['Femenino', 'Masculino', 'Otro'] as $sexo) {
            $data['sexo' . $sexo] = ($data['sexo'] ?? '') === $sexo;
        }

        $this->render->renderiza('editarPerfil', $data);
    }

    private function armarEnlacePerfil($usuario)
    {
        $config = parse_ini_file("config/config.ini");
        $base = rtrim($config['app_url'] ?? '', '/');

        return $base . '/perfil?usuario=' . urlencode($usuario);
    }

    private function subirImagen($archivo)
    {
        if (!isset($archivo['error']) || $archivo['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        if ($archivo['size'] > 5 * 1024 * 1024) {
            return null;
        }

        $tiposPermitidos = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ];

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $tipoReal = $finfo->file($archivo['tmp_name']);

        if (!isset($tiposPermitidos[$tipoReal])) {
            return null;
        }

        $nombreUnico = uniqid() . '.' . $tiposPermitidos[$tipoReal];
        $directorio = 'app/img-foto-perfil';

        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        if (!move_uploaded_file($archivo['tmp_name'], $directorio . '/' . $nombreUnico)) {
            return null;
        }

        return $nombreUnico;
    }
}
