<?php

namespace app\model;

class AdminModel
{
    private $database;

    private const ROLES = ['JUGADOR', 'MODERADOR', 'ADMIN'];

    private const PERIODOS = [
        'dia' => ['etiqueta' => 'Hoy', 'inicio' => 'today'],
        'semana' => ['etiqueta' => 'Última semana', 'inicio' => '-7 days'],
        'mes' => ['etiqueta' => 'Último mes', 'inicio' => '-1 month'],
        'anio' => ['etiqueta' => 'Último año', 'inicio' => '-1 year'],
        'historico' => ['etiqueta' => 'Histórico', 'inicio' => null],
    ];

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function esAdmin($userId)
    {
        $fila = $this->database->queryOne(
            "SELECT rol FROM usuarios WHERE Id = ? LIMIT 1",
            [(int) $userId]
        );

        return $fila !== null && $fila['rol'] === 'ADMIN';
    }

    public function normalizarPeriodo($periodo)
    {
        return is_string($periodo) && isset(self::PERIODOS[$periodo]) ? $periodo : 'historico';
    }

    public function opcionesPeriodo($actual)
    {
        $opciones = [];
        foreach (self::PERIODOS as $clave => $periodo) {
            $opciones[] = [
                'value' => $clave,
                'label' => $periodo['etiqueta'],
                'seleccionado' => $clave === $actual,
            ];
        }
        return $opciones;
    }

    private function inicioPeriodo($periodo)
    {
        $expresion = self::PERIODOS[$periodo]['inicio'];
        if ($expresion === null) {
            return null;
        }
        try {
            return (new \DateTimeImmutable($expresion))->format('Y-m-d H:i:s');
        } catch (\Exception $error) {
            return null;
        }
    }

    /**
     * Estadísticas reales. El usuario logueado queda fuera de los cálculos
     * y el período se aplica a todo lo que tiene fecha.
     */
    public function obtenerEstadisticas($usuarioActual = 0, $periodo = 'historico')
    {
        $usuarioActual = (int) $usuarioActual;
        $periodo = $this->normalizarPeriodo($periodo);
        $inicio = $this->inicioPeriodo($periodo);

        $sqlUsuarios = "SELECT COUNT(*) AS total,
                               COALESCE(SUM(CAST(Verificado AS UNSIGNED)), 0) AS verificados,
                               COALESCE(SUM(rol = 'JUGADOR'), 0) AS jugadores,
                               COALESCE(SUM(rol = 'MODERADOR'), 0) AS moderadores,
                               COALESCE(SUM(rol = 'ADMIN'), 0) AS administradores
                        FROM usuarios";
        $paramsUsuarios = [];
        if ($inicio !== null) {
            $sqlUsuarios .= " WHERE creado_en >= ?";
            $paramsUsuarios[] = $inicio;
        }
        $usuarios = $this->database->queryOne($sqlUsuarios, $paramsUsuarios);

        $conectados = $this->database->queryOne(
            "SELECT COUNT(DISTINCT user_id) AS total
             FROM user_presence
             WHERE last_seen >= NOW() - INTERVAL 90 SECOND
               AND user_id <> ?",
            [$usuarioActual]
        );

        $sqlPartidas = "SELECT COALESCE(SUM(status = 'FINALIZADA'), 0) AS finalizadas FROM game";
        $paramsPartidas = [];
        if ($inicio !== null) {
            $sqlPartidas .= " WHERE started_at >= ?";
            $paramsPartidas[] = $inicio;
        }
        $partidas = $this->database->queryOne($sqlPartidas, $paramsPartidas);
        $hoy = $this->database->queryOne(
            "SELECT COUNT(*) AS total FROM game WHERE DATE(started_at) = CURDATE()"
        );

        $reportesAbiertos = $this->database->queryOne(
            "SELECT COUNT(*) AS total
             FROM question_reports
             WHERE status IN ('PENDIENTE', 'EN_REVISION')"
        );
        $sqlReportes = "SELECT COUNT(*) AS total, COUNT(DISTINCT question_id) AS preguntas FROM question_reports";
        $paramsReportes = [];
        if ($inicio !== null) {
            $sqlReportes .= " WHERE created_at >= ?";
            $paramsReportes[] = $inicio;
        }
        $reportes = $this->database->queryOne($sqlReportes, $paramsReportes);

        $sqlJugadores = "SELECT COUNT(DISTINCT g.user_id) AS total
                         FROM game g
                         INNER JOIN game_answers ga ON ga.game_id = g.id
                         WHERE g.user_id <> ?";
        $paramsJugadores = [$usuarioActual];
        if ($inicio !== null) {
            $sqlJugadores .= " AND g.started_at >= ?";
            $paramsJugadores[] = $inicio;
        }
        $jugadoresFila = $this->database->queryOne($sqlJugadores, $paramsJugadores);
        $totalJugadores = (int) ($jugadoresFila['total'] ?? 0);

        $niveles = $this->calcularNiveles($usuarioActual, $totalJugadores, $inicio);
        $categorias = $this->calcularCategorias($usuarioActual, $inicio);
        $masAcertada = ($categorias[0]['cantidad'] ?? 0) > 0
            ? $categorias[0]
            : ['etiqueta' => 'Sin datos', 'cantidad' => 0, 'sinDatos' => true];

        $extraPaises = $inicio !== null ? " AND g.started_at >= ?" : "";
        $paramsPerfil = $inicio !== null ? [$usuarioActual, $inicio] : [$usuarioActual];

        $paises = $this->calcularDistribucion(
            "SELECT COALESCE(NULLIF(TRIM(u.Pais), ''), 'Sin dato') AS etiqueta,
                    COUNT(DISTINCT u.Id) AS cantidad
             FROM usuarios u
             WHERE u.Id <> ?
               AND EXISTS (
                   SELECT 1 FROM game g
                   INNER JOIN game_answers ga ON ga.game_id = g.id
                   WHERE g.user_id = u.Id $extraPaises
               )
             GROUP BY etiqueta
             ORDER BY cantidad DESC, etiqueta ASC",
            $paramsPerfil,
            $totalJugadores
        );

        $sexos = $this->calcularDistribucion(
            "SELECT COALESCE(NULLIF(TRIM(u.Sexo), ''), 'Sin dato') AS etiqueta,
                    COUNT(DISTINCT u.Id) AS cantidad
             FROM usuarios u
             WHERE u.Id <> ?
               AND EXISTS (
                   SELECT 1 FROM game g
                   INNER JOIN game_answers ga ON ga.game_id = g.id
                   WHERE g.user_id = u.Id $extraPaises
               )
             GROUP BY etiqueta
             ORDER BY cantidad DESC, etiqueta ASC",
            $paramsPerfil,
            $totalJugadores
        );

        $edades = $this->calcularEdades($usuarioActual, $inicio);

        return [
            'totalUsuarios' => (int) ($usuarios['total'] ?? 0),
            'usuariosVerificados' => (int) ($usuarios['verificados'] ?? 0),
            'jugadores' => (int) ($usuarios['jugadores'] ?? 0),
            'moderadores' => (int) ($usuarios['moderadores'] ?? 0),
            'administradores' => (int) ($usuarios['administradores'] ?? 0),
            'usuariosOnline' => (int) ($conectados['total'] ?? 0),
            'jugadoresReales' => $totalJugadores,
            'partidasFinalizadas' => (int) ($partidas['finalizadas'] ?? 0),
            'partidasHoy' => (int) ($hoy['total'] ?? 0),
            'reportesPendientes' => (int) ($reportesAbiertos['total'] ?? 0),
            'reportesPeriodo' => (int) ($reportes['total'] ?? 0),
            'preguntasReportadas' => (int) ($reportes['preguntas'] ?? 0),
            'niveles' => $niveles,
            'categorias' => $categorias,
            'categoriaMasCorrecta' => $masAcertada,
            'paises' => $paises,
            'sexos' => $sexos,
            'edades' => $edades['rangos'],
            'edadPromedio' => $edades['promedio'],
            'edadesConFechaCompleta' => $edades['conFechaCompleta'],
            'edadesSinFechaCompleta' => $edades['sinFechaCompleta'],
            'anioActual' => (int) date('Y'),
            'periodo' => $periodo,
            'etiquetaPeriodo' => self::PERIODOS[$periodo]['etiqueta'],
            'periodoDesde' => $inicio !== null ? date('d/m/Y', strtotime($inicio)) : '',
            'esHistorico' => $periodo === 'historico',
        ];
    }

    /**
     * Usa exactamente la misma regla que Dificultad::nivelJugador() del juego.
     */
    private function calcularNiveles($usuarioActual, $totalJugadores, $inicio = null)
    {
        $sql = "SELECT g.user_id,
                       COUNT(*) AS respondidas,
                       COALESCE(SUM(ga.earned_points > 0), 0) AS correctas
                FROM game_answers ga
                INNER JOIN game g ON g.id = ga.game_id
                WHERE g.user_id <> ?";
        $params = [$usuarioActual];
        if ($inicio !== null) {
            $sql .= " AND g.started_at >= ?";
            $params[] = $inicio;
        }
        $sql .= " GROUP BY g.user_id";
        $filas = $this->database->queryAll($sql, $params);

        $conteo = [1 => 0, 2 => 0, 3 => 0];
        foreach ($filas as $fila) {
            $nivel = \Dificultad::nivelJugador(
                (int) $fila['respondidas'],
                (int) $fila['correctas']
            );
            if (isset($conteo[$nivel])) {
                $conteo[$nivel]++;
            }
        }

        $niveles = [];
        foreach ([1, 2, 3] as $nivel) {
            $cantidad = $conteo[$nivel];
            $niveles[] = [
                'nivel' => $nivel,
                'etiqueta' => \Dificultad::etiquetaJugador($nivel),
                'cantidad' => $cantidad,
                'porcentaje' => $totalJugadores > 0
                    ? round($cantidad * 100 / $totalJugadores, 1)
                    : 0,
            ];
        }

        return $niveles;
    }

    private function calcularDistribucion($sql, array $params, $total)
    {
        $filas = $this->database->queryAll($sql, $params);
        $resultado = [];

        foreach ($filas as $fila) {
            $cantidad = (int) $fila['cantidad'];
            $resultado[] = [
                'etiqueta' => (string) $fila['etiqueta'],
                'cantidad' => $cantidad,
                'porcentaje' => $total > 0 ? round($cantidad * 100 / $total, 1) : 0,
            ];
        }

        return $resultado;
    }

    /**
     * Todas las categorías existentes, con sus aciertos reales en el período.
     * Si crean una categoría nueva aparece enseguida, y si responden bien una
     * pregunta de Deporte se suma a esa fila sin tocar nada a mano.
     */
    private function calcularCategorias($usuarioActual, $inicio = null)
    {
        $condicionFecha = $inicio !== null ? 'g.started_at >= ?' : '1 = 1';
        $params = [$usuarioActual];
        if ($inicio !== null) {
            $params[] = $inicio;
        }

        $sql = "SELECT c.*, COALESCE(aciertos.cantidad, 0) AS cantidad
                FROM category c
                LEFT JOIN (
                    SELECT q.category_id, COUNT(*) AS cantidad
                    FROM questions q
                    JOIN game_answers ga ON ga.question_id = q.id
                    JOIN game g ON g.id = ga.game_id
                    WHERE g.user_id <> ? AND ga.earned_points > 0 AND $condicionFecha
                    GROUP BY q.category_id
                ) aciertos ON aciertos.category_id = c.id
                ORDER BY cantidad DESC, c.name ASC";

        $filas = $this->database->queryAll($sql, $params);
        $total = 0;
        foreach ($filas as $fila) {
            $total += (int) $fila['cantidad'];
        }

        $resultado = [];
        foreach ($filas as $fila) {
            $cantidad = (int) $fila['cantidad'];
            $porcentaje = $total > 0 ? round($cantidad * 100 / $total, 1) : 0;
            $resultado[] = [
                'etiqueta' => (string) $fila['name'],
                'color' => $fila['color'] ?? null,
                'cantidad' => $cantidad,
                'porcentaje' => $porcentaje,
                'porcentajeTexto' => number_format($porcentaje, 1, ',', '.'),
            ];
        }
        return $resultado;
    }

    /**
     * Edad exacta cuando hay día y mes guardados. Las cuentas anteriores a la migración
     * no tienen esos datos y se estiman solo con el año.
     */
    private function calcularEdades($usuarioActual, $inicio = null)
    {
        $extraFecha = $inicio !== null ? " AND g.started_at >= ?" : "";
        $params = $inicio !== null ? [$usuarioActual, $inicio] : [$usuarioActual];
        $filas = $this->database->queryAll(
            "SELECT u.Anio_nacimiento AS anio, u.Mes_nacimiento AS mes, u.Dia_nacimiento AS dia
             FROM usuarios u
             WHERE u.Id <> ?
               AND EXISTS (
                   SELECT 1 FROM game g
                   INNER JOIN game_answers ga ON ga.game_id = g.id
                   WHERE g.user_id = u.Id $extraFecha
               )",
            $params
        );

        $rangos = [
            'Menos de 18' => 0,
            '18 a 24' => 0,
            '25 a 34' => 0,
            '35 a 44' => 0,
            '45 o más' => 0,
            'Sin dato' => 0,
        ];

        $anioActual = (int) date('Y');
        $hoy = new \DateTime('today');
        $suma = 0;
        $cantidadConEdad = 0;
        $conFechaCompleta = 0;
        $sinFechaCompleta = 0;

        foreach ($filas as $fila) {
            $anio = (int) $fila['anio'];
            if ($anio < 1900 || $anio > $anioActual + 5) {
                $rangos['Sin dato']++;
                continue;
            }

            $mes = $fila['mes'] === null ? null : (int) $fila['mes'];
            $dia = $fila['dia'] === null ? null : (int) $fila['dia'];

            if ($mes !== null && $dia !== null && checkdate($mes, $dia, $anio)) {
                $nacimiento = new \DateTime(sprintf('%04d-%02d-%02d', $anio, $mes, $dia));
                if ($nacimiento > $hoy) {
                    $rangos['Sin dato']++;
                    continue;
                }
                $edad = $hoy->diff($nacimiento)->y;
                $conFechaCompleta++;
            } else {
                $edad = $anioActual - $anio;
                $sinFechaCompleta++;
            }

            $suma += $edad;
            $cantidadConEdad++;

            if ($edad < 18) {
                $rangos['Menos de 18']++;
            } elseif ($edad <= 24) {
                $rangos['18 a 24']++;
            } elseif ($edad <= 34) {
                $rangos['25 a 34']++;
            } elseif ($edad <= 44) {
                $rangos['35 a 44']++;
            } else {
                $rangos['45 o más']++;
            }
        }

        $total = count($filas);
        $resultado = [];
        foreach ($rangos as $etiqueta => $cantidad) {
            $resultado[] = [
                'etiqueta' => $etiqueta,
                'cantidad' => $cantidad,
                'porcentaje' => $total > 0 ? round($cantidad * 100 / $total, 1) : 0,
            ];
        }

        return [
            'rangos' => $resultado,
            'promedio' => $cantidadConEdad > 0
                ? round($suma / $cantidadConEdad, 1)
                : 0,
            'conFechaCompleta' => $conFechaCompleta,
            'sinFechaCompleta' => $sinFechaCompleta,
        ];
    }

    public function listarUsuarios($rol, $busqueda, $pagina, $porPagina = 10, array $filtros = [])
    {
        if (!in_array($rol, self::ROLES, true)) {
            $rol = '';
        }

        $where = ['1 = 1'];
        $params = [];

        if ($rol !== '') {
            $where[] = 'u.rol = ?';
            $params[] = $rol;
        }

        if ($busqueda !== '') {
            $like = '%' . $busqueda . '%';
            $where[] = '(u.Usuario LIKE ? OR u.Email LIKE ? OR u.Nombre LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $estado = $filtros['estado'] ?? '';
        if ($estado === 'activas' || $estado === 'bloqueadas') {
            $where[] = 'u.bloqueado = ?';
            $params[] = (int) ($estado === 'bloqueadas');
        }

        if ($filtros['desde'] ?? '') {
            $where[] = 'u.creado_en >= ?';
            $params[] = $filtros['desde'] . ' 00:00:00';
        }
        if ($filtros['hasta'] ?? '') {
            $where[] = 'u.creado_en <= ?';
            $params[] = $filtros['hasta'] . ' 23:59:59';
        }

        $puntajes = "LEFT JOIN (
                        SELECT user_id, SUM(total_points) AS total
                        FROM game WHERE status = 'FINALIZADA' GROUP BY user_id
                    ) p ON p.user_id = u.Id";
        $minimo = $filtros['puntosMinimos'] ?? '';
        $filtraPuntos = $minimo !== '';
        if ($filtraPuntos) {
            $where[] = 'COALESCE(p.total, 0) >= ?';
            $params[] = $minimo;
        }

        switch ($filtros['orden'] ?? '') {
            case 'alta_asc': $orden = 'u.creado_en ASC, u.Id ASC'; break;
            case 'puntos_desc': $orden = 'puntos DESC, u.Id DESC'; break;
            case 'puntos_asc': $orden = 'puntos ASC, u.Id DESC'; break;
            default: $orden = 'u.creado_en DESC, u.Id DESC';
        }

        $condicion = implode(' AND ', $where);
        $unionConteo = $filtraPuntos ? $puntajes : '';

        $conteo = $this->database->queryOne(
            "SELECT COUNT(*) AS total FROM usuarios u $unionConteo WHERE $condicion",
            $params
        );
        $total = (int) ($conteo['total'] ?? 0);
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina = min(max(1, (int) $pagina), $paginas);
        $offset = ($pagina - 1) * $porPagina;

        $usuarios = $this->database->queryAll(
            "SELECT u.Id AS id,
                    u.Nombre AS nombre,
                    u.Anio_nacimiento AS anioNacimiento,
                    u.Sexo AS sexo,
                    u.Provincia AS provincia,
                    u.Pais AS pais,
                    u.Usuario AS usuario,
                    u.Email AS email,
                    CAST(u.Verificado AS UNSIGNED) AS verificado,
                    u.rol, u.bloqueado,
                    DATE_FORMAT(u.creado_en, '%d/%m/%Y') AS fechaAlta,
                    COALESCE(p.total, 0) AS puntos
             FROM usuarios u
             $puntajes
             WHERE $condicion
             ORDER BY $orden
             LIMIT $porPagina OFFSET $offset",
            $params
        );

        foreach ($usuarios as &$usuario) {
            $usuario['id'] = (int) $usuario['id'];
            $usuario['verificado'] = (int) $usuario['verificado'];
            $usuario['bloqueado'] = (bool) $usuario['bloqueado'];
            $usuario['anioNacimiento'] = (int) $usuario['anioNacimiento'];
            $usuario['esAdmin'] = $usuario['rol'] === 'ADMIN';
            $usuario['esModerador'] = $usuario['rol'] === 'MODERADOR';
        }
        unset($usuario);

        return [
            'usuarios' => $usuarios,
            'total' => $total,
            'pagina' => $pagina,
            'paginas' => $paginas,
        ];
    }

    public function obtenerUsuario($id)
    {
        $usuario = $this->database->queryOne(
            "SELECT Id AS id,
                    Nombre AS nombre,
                    Anio_nacimiento AS anioNacimiento,
                    Sexo AS sexo,
                    Provincia AS provincia,
                    Pais AS pais,
                    Usuario AS usuario,
                    Email AS email,
                    CAST(Verificado AS UNSIGNED) AS verificado,
                    rol
             FROM usuarios
             WHERE Id = ?
             LIMIT 1",
            [(int) $id]
        );

        if ($usuario === null) {
            return null;
        }

        $usuario['id'] = (int) $usuario['id'];
        $usuario['verificado'] = (int) $usuario['verificado'];
        $usuario['anioNacimiento'] = (int) $usuario['anioNacimiento'];

        return $usuario;
    }

    public function existeUsuario($usuario, $exceptoId = 0)
    {
        $fila = $this->database->queryOne(
            "SELECT Id FROM usuarios WHERE Usuario = ? AND Id <> ? LIMIT 1",
            [$usuario, (int) $exceptoId]
        );

        return $fila !== null;
    }

    public function existeEmail($email, $exceptoId = 0)
    {
        $fila = $this->database->queryOne(
            "SELECT Id FROM usuarios WHERE Email = ? AND Id <> ? LIMIT 1",
            [$email, (int) $exceptoId]
        );

        return $fila !== null;
    }

    public function contarAdmins()
    {
        $fila = $this->database->queryOne(
            "SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'ADMIN'"
        );

        return (int) ($fila['total'] ?? 0);
    }

    public function contarUsuarios()
    {
        $fila = $this->database->queryOne('SELECT COUNT(*) AS total FROM usuarios');
        return (int) $fila['total'];
    }

    public function listarHistorial($accion, $busqueda, $pagina)
    {
        $where = ['1 = 1'];
        $params = [];
        if ($accion !== '') {
            $where[] = 'accion = ?';
            $params[] = $accion;
        }
        if ($busqueda !== '') {
            $where[] = '(actor_usuario LIKE ? OR cuenta_usuario LIKE ?)';
            $params[] = '%' . $busqueda . '%';
            $params[] = '%' . $busqueda . '%';
        }

        $condicion = implode(' AND ', $where);
        $conteo = $this->database->queryOne("SELECT COUNT(*) AS total FROM admin_historial WHERE $condicion", $params);
        $total = (int) $conteo['total'];
        $porPagina = 20;
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina = min(max(1, (int) $pagina), $paginas);
        $offset = ($pagina - 1) * $porPagina;
        $eventos = $this->database->queryAll(
            "SELECT actor_usuario AS administrador,
                    cuenta_id AS cuentaId, cuenta_usuario AS cuenta,
                    accion, rol_anterior AS rolAnterior, rol_nuevo AS rolNuevo, motivo,
                    DATE_FORMAT(creado_en, '%d/%m/%Y %H:%i:%s') AS fecha
             FROM admin_historial WHERE $condicion
             ORDER BY id DESC LIMIT $porPagina OFFSET $offset",
            $params
        );

        return ['eventos' => $eventos, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];
    }

    public function crear($formulario, int $actorId)
    {
        $sql = "INSERT INTO usuarios
                (Nombre, Anio_nacimiento, Mes_nacimiento, Dia_nacimiento, Sexo, Provincia, Pais, Usuario, Email,
                 Contrasena, Verificado, Token, Foto_url, rol)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?)";

        $params = [
            $formulario['nombre'],
            (int) $formulario['anioNacimiento'],
            (int) $formulario['mesNacimiento'],
            (int) $formulario['diaNacimiento'],
            $formulario['sexo'],
            $formulario['provincia'],
            $formulario['pais'],
            $formulario['usuario'],
            $formulario['email'],
            md5($formulario['contrasena']),
            (int) $formulario['verificado'],
            $formulario['rol'],
        ];

        return $this->operacionAdministrativa($actorId, function ($actor) use ($sql, $params, $formulario) {
            $this->database->execute($sql, $params);
            $id = $this->database->lastInsertId();
            $this->registrarAccion($actor, $id, $formulario['usuario'], 'CREACION', null, null, $formulario['rol']);
            return $id;
        });
    }

    public function actualizar($formulario, int $actorId)
    {
        $cambiaContrasena = isset($formulario['contrasena']) && $formulario['contrasena'] !== '';

        $sql = "UPDATE usuarios
                SET Nombre = ?, Anio_nacimiento = ?, Mes_nacimiento = ?, Dia_nacimiento = ?,
                    Sexo = ?, Provincia = ?, Pais = ?, Usuario = ?, Email = ?, Verificado = ?, rol = ?";

        $params = [
            $formulario['nombre'],
            (int) $formulario['anioNacimiento'],
            (int) $formulario['mesNacimiento'],
            (int) $formulario['diaNacimiento'],
            $formulario['sexo'],
            $formulario['provincia'],
            $formulario['pais'],
            $formulario['usuario'],
            $formulario['email'],
            (int) $formulario['verificado'],
            $formulario['rol'],
        ];

        if ($cambiaContrasena) {
            $sql .= ", Contrasena = ?";
            $params[] = md5($formulario['contrasena']);
        }

        $sql .= " WHERE Id = ?";
        $id = (int) $formulario['id'];
        $params[] = $id;

        return $this->operacionAdministrativa($actorId, function ($actor) use ($id, $sql, $params, $formulario) {
            $actual = $this->database->queryOne('SELECT rol FROM usuarios WHERE Id = ? FOR UPDATE', [$id]);
            if ($actual === null || (($actual['rol'] === 'ADMIN') !== ($formulario['rol'] === 'ADMIN'))) {
                throw new \RuntimeException('La cuenta cambió antes de guardar la edición.');
            }

            $filas = $this->database->execute($sql, $params);
            if ($filas > 0) {
                if ($actual['rol'] !== $formulario['rol']) {
                    $this->registrarAccion($actor, $id, $formulario['usuario'], 'CAMBIO_ROL', null, $actual['rol'], $formulario['rol']);
                } else {
                    $this->registrarAccion($actor, $id, $formulario['usuario'], 'EDICION');
                }
            }

            return $filas;
        });
    }

    public function eliminar($id, int $actorId)
    {
        return $this->operacionAdministrativa($actorId, function ($actor) use ($id) {
            $cuenta = $this->database->queryOne(
                "SELECT Usuario FROM usuarios WHERE Id = ? AND rol <> 'ADMIN' FOR UPDATE",
                [$id]
            );
            if ($cuenta === null) {
                return 0;
            }

            $filas = $this->database->execute("DELETE FROM usuarios WHERE Id = ? AND rol <> 'ADMIN'", [$id]);
            if ($filas > 0) {
                $this->registrarAccion($actor, $id, $cuenta['Usuario'], 'ELIMINACION');
            }
            return $filas;
        });
    }

    public function cambiarBloqueo($id, bool $bloquear, int $actorId, ?string $motivo)
    {
        return $this->operacionAdministrativa($actorId, function ($actor) use ($id, $bloquear, $motivo) {
            $usuario = $this->database->queryOne(
                "SELECT Usuario, bloqueado FROM usuarios WHERE Id = ? AND rol <> 'ADMIN' FOR UPDATE",
                [$id]
            );
            if ($usuario !== null && (bool) $usuario['bloqueado'] !== $bloquear) {
                $this->database->execute(
                    'UPDATE usuarios SET bloqueado = ?, version_sesion = version_sesion + ? WHERE Id = ?',
                    [(int) $bloquear, (int) $bloquear, $id]
                );
                if ($bloquear) {
                    $this->database->execute('DELETE FROM user_presence WHERE user_id = ?', [$id]);
                    $this->database->execute('DELETE FROM user_session_lock WHERE user_id = ?', [$id]);
                }
                $this->registrarAccion($actor, $id, $usuario['Usuario'], $bloquear ? 'BLOQUEO' : 'DESBLOQUEO', $motivo);
            }
            return $usuario !== null;
        });
    }

    private function operacionAdministrativa(int $actorId, callable $cambio)
    {
        $this->database->beginTransaction();
        try {
            $actor = $this->database->queryOne("SELECT Id, Usuario FROM usuarios WHERE Id = ? AND rol = 'ADMIN' FOR UPDATE", [$actorId]);
            if ($actor === null) {
                throw new \RuntimeException('No se encontró la cuenta administradora.');
            }

            $resultado = $cambio($actor);
            $this->database->commit();
            return $resultado;
        } catch (\Throwable $e) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $e;
        }
    }

    private function registrarAccion(array $actor, $id, $usuario, string $accion, ?string $motivo = null, ?string $rolAnterior = null, ?string $rolNuevo = null)
    {
        $this->database->execute(
            'INSERT INTO admin_historial (actor_id, actor_usuario, cuenta_id, cuenta_usuario, accion, motivo, rol_anterior, rol_nuevo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$actor['Id'], $actor['Usuario'], $id, $usuario, $accion, $motivo, $rolAnterior, $rolNuevo]
        );
    }
}
