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

        $userId = (int) $fila['Id'];
        
        $partidas = $this->model->obtenerUltimasPartidas((int) $fila['Id']);
        $partidasView = [];
        foreach ($partidas as $partida) {
            
            $esFinalizada = $partida['status'] === 'FINALIZADA';
            $esAbandonada = $partida['status'] === 'ABANDONADA';

            $partidasView[] = [
                'estado' => $esFinalizada ? 'FINALIZADA' : 'ABANDONADA',
                'fecha' => $partida['finished_at']
                ? date('d/m/Y', strtotime($partida['finished_at']))
                : '',
                'horario' => $partida['finished_at']
                ? date('H:i', strtotime($partida['finished_at']))
                : '',
                'esFinalizada' => $esFinalizada,
                'esAbandonada' => $esAbandonada,
                'puntos' => (int) $partida['total_points'],
        ];
    }

        $puntajeTotal = $this->model->obtenerPuntajeTotal($userId);
        $cantidadPartidas = $this->model->obtenerCantidadPartidasFinalizadas($userId);
        $puestoHistorico = $this->model->obtenerPuestoHistorico($userId);

        $fotoArchivo = 'app/img-foto-perfil/' . basename((string) $fila['Foto_url']);
        $fotoVersion = ($fila['Foto_url'] && is_file($fotoArchivo)) ? filemtime($fotoArchivo) : '';
        $edad = null;
        $anioNacimiento = (int) ($fila['Anio_nacimiento'] ?? 0);
        $mesNacimiento = (int) ($fila['Mes_nacimiento'] ?? 0);
        $diaNacimiento = (int) ($fila['Dia_nacimiento'] ?? 0);
        if (checkdate($mesNacimiento, $diaNacimiento, $anioNacimiento)) {
            $fechaNacimiento = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $anioNacimiento, $mesNacimiento, $diaNacimiento));
            $hoy = new \DateTimeImmutable('today');
            if ($fechaNacimiento <= $hoy) {
                $edad = $fechaNacimiento->diff($hoy)->y;
            }
        }

        $data = [
            'nombre' => $fila['Nombre'],
            'usuario' => $fila['Usuario'],
            'usuarioUrl' => urlencode($fila['Usuario']),
            'inicial' => mb_strtoupper(mb_substr($fila['Nombre'], 0, 1)),
            'pais' => $fila['Pais'],
            'provincia' => $fila['Provincia'],
            'ubicacion' => implode(', ', array_filter([$fila['Provincia'], $fila['Pais']])),
            'edad' => $edad,
            'hayEdad' => $edad !== null,
            'fotoUrl' => $fila['Foto_url'],
            'fotoVersion' => $fotoVersion,
            'esMiPerfil' => (int) $fila['Id'] === (int) $_SESSION['usuario_id'],
            'puntaje' => (int) $puntajeTotal['puntaje'],
            'puesto' => $puestoHistorico ? (int) $puestoHistorico['puesto'] : null,
            'cantidadPartidas' => (int) $cantidadPartidas['cantidad'],
            'hayPartidas' => count($partidasView) > 0,
            'partidas' => $partidasView,
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

        header('Content-type: image/png');
        echo $this->generarQrPng($this->armarEnlacePerfil($usuario));
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
            'mesNacimiento' => $fila['Mes_nacimiento'],
            'diaNacimiento' => $fila['Dia_nacimiento'],
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

        foreach (['nombre', 'anioNacimiento', 'mesNacimiento', 'diaNacimiento', 'sexo', 'provincia', 'pais', 'email'] as $campo) {
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
        if (
            $data['anioNacimiento'] === ''
            || $data['mesNacimiento'] === ''
            || $data['diaNacimiento'] === ''
            || !$this->edadValida($data['anioNacimiento'], $data['mesNacimiento'], $data['diaNacimiento'])
        ) {
            $errores['anioNacimiento'] = 'Tenés que tener entre 16 y 100 años para jugar.';
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
            $fotoNueva = \ImageUploader::upload($archivo, 'app/img-foto-perfil');
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

        $this->model->actualizar($id, $data['nombre'], $data['anioNacimiento'], $data['mesNacimiento'], $data['diaNacimiento'], $data['sexo'], $data['provincia'], $data['pais'], $data['email'], $fotoNueva);
        $_SESSION['foto_url'] = $fotoNueva !== null ? $fotoNueva : ($actual['Foto_url'] ?? null);

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

    public function quitarFoto()
    {
        $this->exigirSesion();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            \Redirect::to('/Preguntados/perfil/editar');
        }

        $token = \Request::post('csrf_token');
        if (!is_string($token) || empty($_SESSION['csrf_perfil']) || !hash_equals($_SESSION['csrf_perfil'], $token)) {
            http_response_code(403);
            return;
        }

        $id = (int) $_SESSION['usuario_id'];
        $actual = $this->model->obtenerParaEditar($id);

        if ($actual && $actual['Foto_url']) {
            $archivo = 'app/img-foto-perfil/' . basename($actual['Foto_url']);
            $this->model->quitarFoto($id);
            if (is_file($archivo)) {
                unlink($archivo);
            }
        }

        $_SESSION['foto_url'] = null;
        \Redirect::to('/Preguntados/perfil/editar');
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

    private function datosNacimiento(array $data): array
    {
        $anioActual = (int) date('Y');
        $data['anioActual'] = $anioActual;
        $data['anioMinimoNacimiento'] = $anioActual - 100;

        $mesSeleccionado = (string) ($data['mesNacimiento'] ?? '');
        $diaSeleccionado = (string) ($data['diaNacimiento'] ?? '');

        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        $data['meses'] = [];
        foreach ($meses as $valor => $etiqueta) {
            $data['meses'][] = [
                'valor' => (string) $valor,
                'etiqueta' => $etiqueta,
                'selected' => $mesSeleccionado === (string) $valor,
            ];
        }

        $data['dias'] = [];
        for ($dia = 1; $dia <= 31; $dia++) {
            $data['dias'][] = [
                'valor' => (string) $dia,
                'etiqueta' => str_pad((string) $dia, 2, '0', STR_PAD_LEFT),
                'selected' => $diaSeleccionado === (string) $dia,
            ];
        }

        return $data;
    }

    private function edadValida($anio, $mes, $dia): bool
    {
        if (!ctype_digit((string) $anio) || !ctype_digit((string) $mes) || !ctype_digit((string) $dia)) {
            return false;
        }

        $anio = (int) $anio;
        $mes = (int) $mes;
        $dia = (int) $dia;
        if (!checkdate($mes, $dia, $anio)) {
            return false;
        }

        $nacimiento = new \DateTime(sprintf('%04d-%02d-%02d', $anio, $mes, $dia));
        $hoy = new \DateTime('today');
        if ($nacimiento > $hoy) {
            return false;
        }

        $edad = $hoy->diff($nacimiento)->y;
        return $edad >= 16 && $edad <= 100;
    }

    private function renderizarEditar($data)
    {
        $_SESSION['csrf_perfil'] ??= bin2hex(random_bytes(32));
        $data['csrfToken'] = $_SESSION['csrf_perfil'];
        $data = $this->datosNacimiento($data);
        foreach (['Femenino', 'Masculino', 'Otro'] as $sexo) {
            $data['sexo' . $sexo] = ($data['sexo'] ?? '') === $sexo;
        }

        $this->render->renderiza('editarPerfil', $data);
    }

    // Genera el PNG sin GD: phpqrcode solo sabe dibujar usando esa extensión.
    private function generarQrPng($texto)
    {
        require_once 'vendor/phpqrcode/qrlib.php';

        $tamanoPunto = 6;
        $margen = 4;

        $encoder = \QRencode::factory(QR_ECLEVEL_M, $tamanoPunto, $margen);
        ob_start();
        $matriz = $encoder->encode($texto);
        ob_end_clean();

        $alto = count($matriz);
        $ancho = strlen($matriz[0]);
        $lado = ($ancho + 2 * $margen) * $tamanoPunto;

        $crudo = '';
        for ($y = 0; $y < $lado; $y++) {
            $crudo .= chr(0);
            $fy = intdiv($y, $tamanoPunto) - $margen;
            for ($x = 0; $x < $lado; $x++) {
                $fx = intdiv($x, $tamanoPunto) - $margen;
                $negro = $fy >= 0 && $fy < $alto && $fx >= 0 && $fx < $ancho && $matriz[$fy][$fx] === '1';
                $crudo .= chr($negro ? 0 : 255);
            }
        }

        $chunk = function ($tipo, $datos) {
            return pack('N', strlen($datos))
                . $tipo
                . $datos
                . pack('N', crc32($tipo . $datos) & 0xffffffff);
        };

        return "\x89PNG\r\n\x1a\n"
            . $chunk('IHDR', pack('NNCCCCC', $lado, $lado, 8, 0, 0, 0, 0))
            . $chunk('IDAT', gzcompress($crudo, 9))
            . $chunk('IEND', '');
    }

    private function armarEnlacePerfil($usuario)
    {
        $config = parse_ini_file("config/config.ini");
        $base = rtrim($config['app_url'] ?? '', '/');
        if ($base === '') {
            $protocolo = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $ruta = rtrim(str_replace("\\", "/", dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
            $base = "{$protocolo}://{$host}{$ruta}";
        }

        return $base . '/perfil?usuario=' . urlencode($usuario);
    }
}
