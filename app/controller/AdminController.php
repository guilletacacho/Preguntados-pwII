<?php

namespace app\controller;

use app\model\AdminModel;
use AdminAccess;
use Redirect;
use Request;

class AdminController
{
    private const ROLES = [
        'JUGADOR' => 'Jugador',
        'MODERADOR' => 'Moderador',
        'ADMIN' => 'Administrador',
    ];

    // El rol ADMIN se asigna desde la base. Estos son los roles editables en el panel.
    private const ROLES_SELECCIONABLES = [
        'JUGADOR' => 'Jugador',
        'MODERADOR' => 'Moderador',
    ];

    private const SEXOS = [
        'No especificado' => 'Sin especificar',
        'Masculino' => 'Masculino',
        'Femenino' => 'Femenino',
        'Otro' => 'Otro',
    ];

    private const ESTADOS = [
        '' => 'Todas las cuentas',
        'activas' => 'Sin bloqueo',
        'bloqueadas' => 'Bloqueadas',
    ];
    private const ORDENES = [
        'alta_desc' => 'Altas más recientes',
        'alta_asc' => 'Altas más antiguas',
        'puntos_desc' => 'Mayor puntaje',
        'puntos_asc' => 'Menor puntaje',
    ];
    private const ACCIONES_HISTORIAL = [
        'CREACION' => 'Alta de usuario',
        'EDICION' => 'Edición',
        'CAMBIO_ROL' => 'Cambio de rol',
        'BLOQUEO' => 'Bloqueo',
        'DESBLOQUEO' => 'Desbloqueo',
        'ELIMINACION' => 'Eliminación',
    ];

    private $model;
    private $render;

    public function __construct(AdminModel $model, $render)
    {
        $this->model = $model;
        $this->render = $render;
    }

    public function show()
    {
        AdminAccess::exigir($this->model);
        header('Cache-Control: no-store');
        $this->asegurarCsrfToken();

        $periodo = $this->model->normalizarPeriodo(Request::get('periodo'));
        $datos = $this->model->obtenerEstadisticas((int) $_SESSION['usuario_id'], $periodo);
        $datos['esResumen'] = true;
        $datos['periodos'] = $this->model->opcionesPeriodo($periodo);
        $datos['totalUsuariosHistorico'] = $this->model->contarUsuarios();
        $datos['fechaImpresion'] = date('d/m/Y H:i');
        $datos['mensaje'] = $this->consumirMensaje();
        $datos['sinVerificar'] = max(0, (int) $datos['totalUsuarios'] - (int) $datos['usuariosVerificados']);
        $datos['graficosJson'] = $this->prepararGraficos($datos);

        $this->render->renderiza('admin', $datos);
    }

    public function usuarios()
    {
        AdminAccess::exigir($this->model);
        header('Cache-Control: no-store');
        $this->asegurarCsrfToken();

        $listado = $this->datosListadoUsuarios();
        $filtros = $listado['filtros'];

        $datos = $listado + [
            'esUsuarios' => true,
            'roles' => $this->opcionesConSeleccion(['' => 'Todos los roles'] + self::ROLES, $filtros['rol']),
            'estados' => $this->opcionesConSeleccion(self::ESTADOS, $filtros['estado']),
            'ordenes' => $this->opcionesConSeleccion(self::ORDENES, $filtros['orden']),
            'mensaje' => $this->consumirMensaje(),
        ];

        $this->render->renderiza('adminUsuarios', $datos);
    }

    public function usuariosAjax()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        if (!isset($_SESSION['usuario_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Tu sesión venció. Volvé a iniciar sesión.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!$this->model->esAdmin((int) $_SESSION['usuario_id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'No tenés permisos para consultar usuarios.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $_SESSION['rol'] = 'ADMIN';
        $this->asegurarCsrfToken();

        echo json_encode(
            $this->datosListadoUsuarios(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    private function datosListadoUsuarios()
    {
        $filtros = $this->filtrosUsuarios();
        $pagina = max(1, $this->leerId(Request::get('pagina')));
        $resultado = $this->model->listarUsuarios($filtros['rol'], $filtros['busqueda'], $pagina, 10, $filtros);

        return [
            'usuarios' => $resultado['usuarios'],
            'totalUsuarios' => $resultado['total'],
            'pagina' => $resultado['pagina'],
            'paginas' => $resultado['paginas'],
            'paginaAnterior' => $resultado['pagina'] - 1,
            'paginaSiguiente' => $resultado['pagina'] + 1,
            'hayPaginaAnterior' => $resultado['pagina'] > 1,
            'hayPaginaSiguiente' => $resultado['pagina'] < $resultado['paginas'],
            'queryFiltros' => http_build_query($filtros),
            'filtros' => $filtros,
            'errorFechas' => $filtros['desde'] !== '' && $filtros['hasta'] !== '' && $filtros['desde'] > $filtros['hasta'],
            'csrfToken' => $_SESSION['csrf_admin'],
        ];
    }

    public function historial()
    {
        AdminAccess::exigir($this->model);

        $accion = Request::get('accion');
        $accion = is_string($accion) && isset(self::ACCIONES_HISTORIAL[$accion]) ? $accion : '';
        $busqueda = Request::get('busqueda');
        $busqueda = is_string($busqueda) ? mb_substr(trim($busqueda), 0, 100) : '';
        $resultado = $this->model->listarHistorial($accion, $busqueda, max(1, $this->leerId(Request::get('pagina'))));

        foreach ($resultado['eventos'] as &$evento) {
            $evento['accion'] = self::ACCIONES_HISTORIAL[$evento['accion']];
            $evento['rolAnterior'] = self::ROLES[$evento['rolAnterior']] ?? '';
            $evento['rolNuevo'] = self::ROLES[$evento['rolNuevo']] ?? '';
        }
        unset($evento);

        $this->render->renderiza('adminHistorial', $resultado + [
            'esHistorial' => true,
            'totalUsuarios' => $this->model->contarUsuarios(),
            'acciones' => $this->opcionesConSeleccion(['' => 'Todas las acciones'] + self::ACCIONES_HISTORIAL, $accion),
            'busqueda' => $busqueda,
            'hayFiltros' => $accion !== '' || $busqueda !== '',
            'queryFiltros' => http_build_query(['accion' => $accion, 'busqueda' => $busqueda]),
            'hayPaginaAnterior' => $resultado['pagina'] > 1,
            'hayPaginaSiguiente' => $resultado['pagina'] < $resultado['paginas'],
            'paginaAnterior' => $resultado['pagina'] - 1,
            'paginaSiguiente' => $resultado['pagina'] + 1,
        ]);
    }

    public function altaUsuario()
    {
        AdminAccess::exigir($this->model);
        $this->asegurarCsrfToken();

        $this->render->renderiza('adminAltaUsuario', $this->datosNacimiento([
            'esAlta' => true,
            'roles' => $this->rolesConSeleccion('JUGADOR'),
            'sexos' => $this->sexosConSeleccion('No especificado'),
            'csrfToken' => $_SESSION['csrf_admin'],
            'mensaje' => $this->consumirMensaje(),
        ]));
    }

    public function crearUsuario()
    {
        AdminAccess::exigir($this->model);
        $this->asegurarCsrfToken();

        $formulario = $this->leerFormulario();

        if (!$this->tokenValido()) {
            $formulario['errores'] = ['El formulario venció. Volvé a intentarlo.'];
            $this->mostrarAltaConError($formulario);
            return;
        }

        $errores = $this->validar($formulario, true);

        if (!$errores && $formulario['rol'] === 'ADMIN') {
            $errores[] = 'Solo puede existir un administrador.';
        }
        if (!$errores && $this->model->existeUsuario($formulario['usuario'])) {
            $errores[] = 'Ese nombre de usuario ya está en uso.';
        }
        if (!$errores && $this->model->existeEmail($formulario['email'])) {
            $errores[] = 'Ese correo ya está en uso.';
        }

        if ($errores) {
            $formulario['errores'] = $errores;
            $this->mostrarAltaConError($formulario);
            return;
        }

        try {
            $this->model->crear($formulario, (int) $_SESSION['usuario_id']);
        } catch (\RuntimeException $e) {
            $formulario['errores'] = ['No se pudo crear el usuario. Revisá los datos e intentá de nuevo.'];
            $this->mostrarAltaConError($formulario);
            return;
        }

        $this->guardarMensaje('El usuario se creó correctamente.', 'success');
        Redirect::to('/Preguntados/admin/usuarios');
    }

    public function editarUsuario()
    {
        AdminAccess::exigir($this->model);
        $this->asegurarCsrfToken();

        $id = $this->leerId(Request::get('id'));
        $usuario = $id > 0 ? $this->model->obtenerUsuario($id) : null;

        if ($usuario === null) {
            $this->guardarMensaje('No se encontró ese usuario.', 'error');
            Redirect::to('/Preguntados/admin/usuarios');
        }

        $this->mostrarFichaUsuario($usuario, $usuario['rol'], $this->consumirMensaje());
    }

    public function actualizarUsuario()
    {
        AdminAccess::exigir($this->model);
        $this->asegurarCsrfToken();

        $id = $this->leerId(Request::post('id'));
        $actual = $id > 0 ? $this->model->obtenerUsuario($id) : null;

        if ($actual === null) {
            $this->guardarMensaje('No se encontró ese usuario.', 'error');
            Redirect::to('/Preguntados/admin/usuarios');
        }

        // Del formulario solo se usa el rol: cualquier otro dato que llegue se ignora.
        $rol = Request::post('rol');
        $rol = is_string($rol) ? $rol : '';

        $error = null;
        if (!$this->tokenValido()) {
            $error = 'El formulario venció. Volvé a intentarlo.';
        } elseif ($actual['rol'] === 'ADMIN') {
            $error = 'El rol de un administrador no se puede cambiar.';
        } elseif (!array_key_exists($rol, self::ROLES_SELECCIONABLES)) {
            $error = 'Elegí un rol válido.';
        }

        if ($error !== null) {
            $this->mostrarFichaUsuario($actual, $rol, ['texto' => $error, 'tipo' => 'error']);
            return;
        }

        try {
            $filas = $this->model->cambiarRol($id, $rol, (int) $_SESSION['usuario_id']);
        } catch (\RuntimeException $e) {
            $this->mostrarFichaUsuario($actual, $rol, ['texto' => 'No se pudo cambiar el rol. Intentá de nuevo.', 'tipo' => 'error']);
            return;
        }

        $this->guardarMensaje(
            $filas > 0 ? 'El rol de ' . $actual['usuario'] . ' se actualizó.' : $actual['usuario'] . ' ya tenía ese rol.',
            'success'
        );
        Redirect::to('/Preguntados/admin/usuarios');
    }

    public function bajaUsuario()
    {
        AdminAccess::exigir($this->model);
        $volver = $this->urlListadoUsuarios();
        $id = $this->validarAccionListado($volver);

        try {
            if ($this->model->eliminar($id, (int) $_SESSION['usuario_id'])) {
                $this->guardarMensaje('El usuario se eliminó.', 'success');
            } else {
                $this->guardarMensaje('No se encontró una cuenta que se pueda eliminar.', 'error');
            }
        } catch (\RuntimeException $e) {
            $this->guardarMensaje('No se pudo eliminar el usuario. Puede tener reportes o datos relacionados.', 'error');
        }

        Redirect::to($volver);
    }

    public function cambiarBloqueoUsuario()
    {
        AdminAccess::exigir($this->model);
        $volver = $this->urlListadoUsuarios();
        $id = $this->validarAccionListado($volver);
        $accion = Request::post('bloquear');
        if (!in_array($accion, ['0', '1'], true)) {
            $this->guardarMensaje('La acción de bloqueo no es válida.', 'error');
            Redirect::to($volver);
        }

        $bloquear = $accion === '1';
        $motivo = Request::post('motivo');
        $motivo = is_string($motivo) ? trim($motivo) : '';
        if ($bloquear && ($motivo === '' || mb_strlen($motivo) > 250)) {
            $this->guardarMensaje('Ingresá un motivo de bloqueo de hasta 250 caracteres.', 'error');
            Redirect::to($volver);
        }

        try {
            if ($this->model->cambiarBloqueo($id, $bloquear, (int) $_SESSION['usuario_id'], $bloquear ? $motivo : null)) {
                $this->guardarMensaje($bloquear ? 'La cuenta quedó bloqueada.' : 'La cuenta se desbloqueó.', 'success');
            } else {
                $this->guardarMensaje('No se encontró una cuenta cuyo bloqueo se pueda cambiar.', 'error');
            }
        } catch (\RuntimeException $e) {
            $this->guardarMensaje('No se pudo cambiar el bloqueo. Volvé a intentarlo.', 'error');
        }

        Redirect::to($volver);
    }

    private function validarAccionListado($volver)
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !$this->tokenValido()) {
            $this->guardarMensaje('El formulario venció. Volvé a intentarlo.', 'error');
            Redirect::to($volver);
        }

        $id = $this->leerId(Request::post('id'));
        if (!$id || $id === (int) $_SESSION['usuario_id']) {
            $this->guardarMensaje('No se puede aplicar esa acción a la cuenta indicada.', 'error');
            Redirect::to($volver);
        }

        return $id;
    }

    private function urlListadoUsuarios()
    {
        return '/Preguntados/admin/usuarios?' . http_build_query(
            $this->filtrosUsuarios() + ['pagina' => max(1, $this->leerId(Request::get('pagina')))]
        );
    }

    private function filtrosUsuarios()
    {
        $filtros = [];
        foreach (['rol', 'estado', 'orden', 'busqueda', 'desde', 'hasta', 'puntosMinimos'] as $clave) {
            $valor = Request::get($clave);
            $filtros[$clave] = is_string($valor) ? $valor : '';
        }

        $filtros['rol'] = isset(self::ROLES[$filtros['rol']]) ? $filtros['rol'] : '';
        $filtros['estado'] = isset(self::ESTADOS[$filtros['estado']]) ? $filtros['estado'] : '';
        $filtros['orden'] = isset(self::ORDENES[$filtros['orden']]) ? $filtros['orden'] : 'alta_desc';
        $filtros['busqueda'] = mb_substr(trim($filtros['busqueda']), 0, 100);
        $filtros['desde'] = $this->fechaFiltro($filtros['desde']);
        $filtros['hasta'] = $this->fechaFiltro($filtros['hasta']);
        $minimo = filter_var($filtros['puntosMinimos'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $filtros['puntosMinimos'] = $minimo === false ? '' : (string) $minimo;

        return $filtros;
    }

    private function fechaFiltro($valor)
    {
        if (!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $valor, $partes)) {
            return '';
        }
        return (int) $partes[1] >= 1000 && checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1]) ? $valor : '';
    }

    private function mostrarAltaConError(array $formulario)
    {
        $formulario['esAlta'] = true;
        $formulario['tieneErrores'] = true;
        $formulario['roles'] = $this->rolesConSeleccion($formulario['rol']);
        $formulario['sexos'] = $this->sexosConSeleccion($formulario['sexo']);
        $formulario['csrfToken'] = $_SESSION['csrf_admin'];

        $this->render->renderiza('adminAltaUsuario', $this->datosNacimiento($formulario));
    }

    // Ficha de solo lectura del usuario; lo único editable es el rol.
    private function mostrarFichaUsuario(array $usuario, $rolSeleccionado, $mensaje)
    {
        $meses = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
            7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];
        $mes = (int) ($usuario['mesNacimiento'] ?? 0);
        $dia = (int) ($usuario['diaNacimiento'] ?? 0);
        $usuario['fechaNacimiento'] = isset($meses[$mes]) && $dia > 0
            ? $dia . ' de ' . $meses[$mes] . ' de ' . $usuario['anioNacimiento']
            : (string) $usuario['anioNacimiento'];

        $creado = !empty($usuario['creadoEn']) ? strtotime($usuario['creadoEn']) : false;
        $usuario['fechaAlta'] = $creado ? date('d/m/Y', $creado) : null;
        $usuario['sexoTexto'] = self::SEXOS[$this->normalizarSexo($usuario['sexo'])] ?? 'Sin especificar';
        $usuario['rolTexto'] = self::ROLES[$usuario['rol']] ?? $usuario['rol'];
        $usuario['rolFijoAdmin'] = $usuario['rol'] === 'ADMIN';
        $usuario['roles'] = $this->rolesConSeleccion($rolSeleccionado);
        $usuario['csrfToken'] = $_SESSION['csrf_admin'];
        $usuario['mensaje'] = $mensaje;

        $this->render->renderiza('adminEditarUsuario', $usuario);
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

    private function leerFormulario()
    {
        $anioActual = (int) date('Y');
        $anio = filter_var(
            Request::post('anioNacimiento', ''),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => $anioActual - 100, 'max_range' => $anioActual]]
        );
        $mes = filter_var(
            Request::post('mesNacimiento', ''),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 12]]
        );
        $dia = filter_var(
            Request::post('diaNacimiento', ''),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 31]]
        );

        $rol = (string) Request::post('rol', 'JUGADOR');
        if (!array_key_exists($rol, self::ROLES)) {
            $rol = 'JUGADOR';
        }

        return [
            'nombre' => trim((string) Request::post('nombre', '')),
            'anioNacimiento' => $anio === false ? '' : (string) $anio,
            'mesNacimiento' => $mes === false ? '' : (string) $mes,
            'diaNacimiento' => $dia === false ? '' : (string) $dia,
            'sexo' => $this->normalizarSexo(Request::post('sexo', 'No especificado')),
            'provincia' => trim((string) Request::post('provincia', '')),
            'pais' => trim((string) Request::post('pais', '')),
            'usuario' => trim((string) Request::post('usuario', '')),
            'email' => trim((string) Request::post('email', '')),
            'contrasena' => (string) Request::post('contrasena', ''),
            'verificado' => Request::post('verificado') === '1' ? 1 : 0,
            'rol' => $rol,
        ];
    }

    private function validar(array &$formulario, $exigirContrasena)
    {
        $errores = [];

        if ($this->longitud($formulario['nombre']) < 1 || $this->longitud($formulario['nombre']) > 50) {
            $errores[] = 'El nombre es obligatorio y no puede superar los 50 caracteres.';
        }

        if (
            $formulario['anioNacimiento'] === ''
            || $formulario['mesNacimiento'] === ''
            || $formulario['diaNacimiento'] === ''
            || !$this->edadValida(
                $formulario['anioNacimiento'],
                $formulario['mesNacimiento'],
                $formulario['diaNacimiento']
            )
        ) {
            $errores[] = 'La fecha de nacimiento debe dar una edad entre 16 y 100 años.';
        }

        if (!array_key_exists($formulario['sexo'], self::SEXOS)) {
            $errores[] = 'Elegí un sexo válido.';
        }

        if ($this->longitud($formulario['provincia']) > 150 || $this->longitud($formulario['pais']) > 150) {
            $errores[] = 'La provincia y el país no pueden superar los 150 caracteres.';
        }

        if (!preg_match('/\A[^\r\n]{3,255}\z/u', $formulario['usuario'])) {
            $errores[] = 'El usuario debe tener entre 3 y 255 caracteres y no puede tener saltos de línea.';
        }

        if (filter_var($formulario['email'], FILTER_VALIDATE_EMAIL) === false
            || $this->longitud($formulario['email']) > 255) {
            $errores[] = 'Ingresá un correo válido.';
        }

        if (!array_key_exists($formulario['rol'], self::ROLES)) {
            $errores[] = 'Elegí un rol válido.';
        }

        if ($exigirContrasena && $this->longitud($formulario['contrasena']) < 8) {
            $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if (!$exigirContrasena
            && $formulario['contrasena'] !== ''
            && $this->longitud($formulario['contrasena']) < 8) {
            $errores[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
        }

        return $errores;
    }

    private function longitud($texto)
    {
        return preg_match_all('/./us', (string) $texto);
    }

    private function leerId($valor)
    {
        $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? 0 : (int) $id;
    }

    private function rolesConSeleccion($seleccionado)
    {
        return $this->opcionesConSeleccion(self::ROLES_SELECCIONABLES, $seleccionado);
    }

    private function sexosConSeleccion($seleccionado)
    {
        $actual = $this->normalizarSexo($seleccionado);
        $opciones = [];
        foreach (self::SEXOS as $valor => $etiqueta) {
            $opciones[] = [
                'value' => $valor,
                'label' => $etiqueta,
                'seleccionado' => $valor === $actual,
            ];
        }

        return $opciones;
    }

    private function normalizarSexo($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return 'No especificado';
        }

        $mapa = [
            'sin especificar' => 'No especificado',
            'no especificado' => 'No especificado',
            'masculino' => 'Masculino',
            'femenino' => 'Femenino',
            'otro' => 'Otro',
        ];
        $clave = mb_strtolower($valor, 'UTF-8');

        return $mapa[$clave] ?? $valor;
    }

    private function opcionesConSeleccion(array $opciones, $seleccionado)
    {
        $resultado = [];
        foreach ($opciones as $valor => $etiqueta) {
            $resultado[] = [
                'value' => $valor,
                'label' => $etiqueta,
                'seleccionado' => $valor === $seleccionado,
            ];
        }

        return $resultado;
    }

    private function prepararGraficos(array $datos)
    {
        $graficos = [
            'roles' => [
                ['etiqueta' => 'Jugadores', 'cantidad' => (int) $datos['jugadores']],
                ['etiqueta' => 'Moderadores', 'cantidad' => (int) $datos['moderadores']],
                ['etiqueta' => 'Administradores', 'cantidad' => (int) $datos['administradores']],
            ],
            'totalUsuarios' => (int) $datos['totalUsuarios'],
            'jugadoresReales' => (int) $datos['jugadoresReales'],
            'verificados' => (int) $datos['usuariosVerificados'],
            'sinVerificar' => (int) $datos['sinVerificar'],
            'niveles' => [],
            'paises' => [],
            'edades' => [],
            'sexos' => [],
            'categorias' => [],
        ];

        foreach (['niveles', 'paises', 'edades', 'sexos', 'categorias'] as $clave) {
            foreach ($datos[$clave] as $fila) {
                $graficos[$clave][] = [
                    'etiqueta' => (string) $fila['etiqueta'],
                    'cantidad' => (int) $fila['cantidad'],
                ] + array_intersect_key($fila, ['color' => true]);
            }
        }

        $json = json_encode(
            $graficos,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        return $json === false ? '{}' : $json;
    }

    private function asegurarCsrfToken()
    {
        if (empty($_SESSION['csrf_admin'])) {
            $_SESSION['csrf_admin'] = bin2hex(random_bytes(32));
        }
    }

    private function tokenValido()
    {
        $token = (string) Request::post('csrf_token', '');

        return $token !== '' && hash_equals((string) ($_SESSION['csrf_admin'] ?? ''), $token);
    }

    private function guardarMensaje($texto, $tipo)
    {
        $_SESSION['mensaje_admin'] = ['texto' => $texto, 'tipo' => $tipo];
    }

    private function consumirMensaje()
    {
        $mensaje = $_SESSION['mensaje_admin'] ?? null;
        unset($_SESSION['mensaje_admin']);

        return $mensaje;
    }
}
