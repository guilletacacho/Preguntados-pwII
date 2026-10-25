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

    public function ubicacion()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $latitudRecibida = Request::post('latitude', '');
        $longitudRecibida = Request::post('longitude', '');
        if (
            !is_string($latitudRecibida) ||
            !is_numeric($latitudRecibida) ||
            !is_string($longitudRecibida) ||
            !is_numeric($longitudRecibida)
        ) {
            http_response_code(400);
            echo json_encode(['error' => 'Coordenadas inválidas.']);
            return;
        }

        $latitud = (float) $latitudRecibida;
        $longitud = (float) $longitudRecibida;
        if (!is_finite($latitud) || !is_finite($longitud) || $latitud < -90 || $latitud > 90 || $longitud < -180 || $longitud > 180) {
            http_response_code(400);
            echo json_encode(['error' => 'Coordenadas fuera de rango.']);
            return;
        }

        try {
            echo json_encode($this->geocodificar($latitud, $longitud), JSON_UNESCAPED_UNICODE);
        } catch (\RuntimeException $exception) {
            error_log('No se pudo resolver la ubicación del navegador: ' . $exception->getMessage());
            http_response_code(502);
            echo json_encode(['error' => 'No se pudo obtener el nombre de la ubicación.']);
        }
    }

     public function registrar() {
        $data = ["anioActual" => date('Y'), "pais" => '', "provincia" => '', "ubicacion" => ''];
        $errores = [];
        foreach (['nombre', 'anioNacimiento', 'sexo', 'usuario', 'email'] as $campo) {
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

        $latitudRecibida = Request::post('latitude', '');
        $longitudRecibida = Request::post('longitude', '');
        if (
            !is_string($latitudRecibida) ||
            !is_numeric($latitudRecibida) ||
            !is_string($longitudRecibida) ||
            !is_numeric($longitudRecibida)
        ) {
            $errores['ubicacion'] = 'No pudimos obtener tu ubicación. Permití el acceso a la ubicación e intentá de nuevo.';
        } else {
            $latitud = (float) $latitudRecibida;
            $longitud = (float) $longitudRecibida;
            if (!is_finite($latitud) || !is_finite($longitud) || $latitud < -90 || $latitud > 90 || $longitud < -180 || $longitud > 180) {
                $errores['ubicacion'] = 'La ubicación recibida no es válida. Intentá obtenerla nuevamente.';
            } elseif (!$errores) {
                try {
                    $ubicacion = $this->geocodificar($latitud, $longitud);
                    $data['pais'] = $ubicacion['pais'];
                    $data['provincia'] = $ubicacion['provincia'];
                    $data['ubicacion'] = implode(', ', array_filter([$data['pais'], $data['provincia']]));
                } catch (\RuntimeException $exception) {
                    error_log('No se pudo resolver la ubicación del registro: ' . $exception->getMessage());
                    $errores['ubicacion'] = 'No pudimos convertir tu ubicación. Intentá registrarte nuevamente.';
                }
            }
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
        $data['provincia'],
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

    private function geocodificar(float $latitud, float $longitud): array
    {
        $endpoint = getenv('PREGUNTADOS_GEOCODER_REVERSE_URL');
        if (!is_string($endpoint) || trim($endpoint) === '') {
            $endpoint = 'https://nominatim.openstreetmap.org/reverse';
        }
        $endpointInfo = parse_url($endpoint);
        if (!is_array($endpointInfo) || ($endpointInfo['scheme'] ?? '') !== 'https' || empty($endpointInfo['host'])) {
            throw new \RuntimeException('La URL del servicio de geocodificación debe usar HTTPS.');
        }

        $directorioCache = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'preguntados-nominatim-cache';
        if (!is_dir($directorioCache) && !@mkdir($directorioCache, 0700, true) && !is_dir($directorioCache)) {
            throw new \RuntimeException('No se pudo crear el caché local de geocodificación.');
        }

        $claveCache = hash('sha256', $endpoint . '|' . sprintf('%.3f,%.3f', $latitud, $longitud));
        $archivoCache = $directorioCache . DIRECTORY_SEPARATOR . $claveCache . '.json';
        $archivoControl = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'preguntados-nominatim.lock';
        $control = @fopen($archivoControl, 'c+');
        if ($control === false || !@flock($control, LOCK_EX)) {
            if (is_resource($control)) {
                fclose($control);
            }
            throw new \RuntimeException('No se pudo acceder al control de solicitudes de geocodificación.');
        }

        try {
            if (is_file($archivoCache)) {
                $contenidoCache = @file_get_contents($archivoCache);
                if ($contenidoCache === false) {
                    error_log('No se pudo leer el caché de geocodificación.');
                    $cache = null;
                } else {
                    $cache = json_decode($contenidoCache, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        error_log('El caché de geocodificación contiene JSON inválido.');
                    }
                }
                if (
                    is_array($cache) &&
                    isset($cache['creado'], $cache['pais']) &&
                    is_string($cache['pais']) &&
                    array_key_exists('ciudad', $cache) &&
                    (int) $cache['creado'] > time() - 30 * 24 * 60 * 60
                ) {
                    return [
                        'pais' => $cache['pais'],
                        'provincia' => is_string($cache['provincia'] ?? null) ? $cache['provincia'] : '',
                        'ciudad' => is_string($cache['ciudad']) ? $cache['ciudad'] : '',
                    ];
                }
            }

            rewind($control);
            $ultimaSolicitud = (float) trim((string) stream_get_contents($control));
            $espera = 1.0 - (microtime(true) - $ultimaSolicitud);
            if ($espera > 0) {
                usleep((int) ($espera * 1000000));
            }
            if (!ftruncate($control, 0)) {
                throw new \RuntimeException('No se pudo actualizar el control de solicitudes de geocodificación.');
            }
            rewind($control);
            if (fwrite($control, (string) microtime(true)) === false || !fflush($control)) {
                throw new \RuntimeException('No se pudo registrar la solicitud de geocodificación.');
            }

            $url = rtrim($endpoint, '?') . '?' . http_build_query([
                'format' => 'jsonv2',
                'lat' => $latitud,
                'lon' => $longitud,
                'zoom' => 10,
                'addressdetails' => 1,
            ]);
            $contexto = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: Preguntados/1.0 (+https://github.com/guilletacacho/Preguntados-pwII)\r\nAccept-Language: es\r\n",
                    'timeout' => 10,
                    'ignore_errors' => true,
                ],
            ]);
            $respuesta = @file_get_contents($url, false, $contexto);
            if ($respuesta === false || !isset($http_response_header[0]) || strpos($http_response_header[0], ' 200 ') === false) {
                throw new \RuntimeException('El servicio de geocodificación no respondió correctamente.');
            }

            $resultado = json_decode($respuesta, true);
            if (!is_array($resultado) || !is_array($resultado['address'] ?? null)) {
                throw new \RuntimeException('El servicio de geocodificación devolvió una respuesta inválida.');
            }

            $direccion = $resultado['address'];
            $pais = is_string($direccion['country'] ?? null) ? trim($direccion['country']) : '';
            $provincia = '';
            foreach (['state', 'province', 'region'] as $campo) {
                if (is_string($direccion[$campo] ?? null) && trim($direccion[$campo]) !== '') {
                    $provincia = trim($direccion[$campo]);
                    break;
                }
            }
            $ciudad = '';
            foreach (['city', 'town', 'village', 'municipality', 'hamlet'] as $campo) {
                if (is_string($direccion[$campo] ?? null) && trim($direccion[$campo]) !== '') {
                    $ciudad = trim($direccion[$campo]);
                    break;
                }
            }
            if ($pais === '') {
                throw new \RuntimeException('La respuesta no contiene un país reconocible.');
            }

            $cache = json_encode([
                'creado' => time(),
                'pais' => $pais,
                'provincia' => $provincia,
                'ciudad' => $ciudad,
            ], JSON_UNESCAPED_UNICODE);
            if ($cache === false || file_put_contents($archivoCache, $cache, LOCK_EX) === false) {
                error_log('No se pudo guardar el caché de geocodificación.');
            }

            return ['pais' => $pais, 'provincia' => $provincia, 'ciudad' => $ciudad];
        } finally {
            flock($control, LOCK_UN);
            fclose($control);
        }
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