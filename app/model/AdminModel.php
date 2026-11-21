<?php

namespace app\model;

class AdminModel
{
    private $database;

    private const ROLES = ['JUGADOR', 'MODERADOR', 'ADMIN'];

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

    /**
     * Estadísticas reales. El usuario logueado queda fuera de los cálculos
     * para que el panel no se cuente a sí mismo como "jugando ahora".
     */
    public function obtenerEstadisticas($usuarioActual = 0)
    {
        $usuarioActual = (int) $usuarioActual;

        $usuarios = $this->database->queryOne(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CAST(Verificado AS UNSIGNED)), 0) AS verificados,
                    COALESCE(SUM(rol = 'JUGADOR'), 0) AS jugadores,
                    COALESCE(SUM(rol = 'MODERADOR'), 0) AS moderadores,
                    COALESCE(SUM(rol = 'ADMIN'), 0) AS administradores
             FROM usuarios"
        );

        $conectados = $this->database->queryOne(
            "SELECT COUNT(DISTINCT user_id) AS total
             FROM user_presence
             WHERE last_seen >= NOW() - INTERVAL 90 SECOND
               AND user_id <> ?",
            [$usuarioActual]
        );

        $partidas = $this->database->queryOne(
            "SELECT COALESCE(SUM(status = 'FINALIZADA'), 0) AS finalizadas,
                    COALESCE(SUM(DATE(started_at) = CURDATE()), 0) AS hoy
             FROM game"
        );

        $reportes = $this->database->queryOne(
            "SELECT COUNT(*) AS total
             FROM question_reports
             WHERE status IN ('PENDIENTE', 'EN_REVISION')"
        );

        // Población real: usuarios con al menos una respuesta registrada.
        $jugadoresFila = $this->database->queryOne(
            "SELECT COUNT(DISTINCT g.user_id) AS total
             FROM game g
             INNER JOIN game_answers ga ON ga.game_id = g.id
             WHERE g.user_id <> ?",
            [$usuarioActual]
        );
        $totalJugadores = (int) ($jugadoresFila['total'] ?? 0);

        $niveles = $this->calcularNiveles($usuarioActual, $totalJugadores);
        $paises = $this->calcularDistribucion(
            "SELECT COALESCE(NULLIF(TRIM(u.Pais), ''), 'Sin dato') AS etiqueta,
                    COUNT(DISTINCT u.Id) AS cantidad
             FROM usuarios u
             WHERE u.Id <> ?
               AND EXISTS (
                   SELECT 1 FROM game g
                   INNER JOIN game_answers ga ON ga.game_id = g.id
                   WHERE g.user_id = u.Id
               )
             GROUP BY etiqueta
             ORDER BY cantidad DESC, etiqueta ASC",
            $usuarioActual,
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
                   WHERE g.user_id = u.Id
               )
             GROUP BY etiqueta
             ORDER BY cantidad DESC, etiqueta ASC",
            $usuarioActual,
            $totalJugadores
        );

        $edades = $this->calcularEdades($usuarioActual);

        return [
            'totalUsuarios' => (int) ($usuarios['total'] ?? 0),
            'usuariosVerificados' => (int) ($usuarios['verificados'] ?? 0),
            'jugadores' => (int) ($usuarios['jugadores'] ?? 0),
            'moderadores' => (int) ($usuarios['moderadores'] ?? 0),
            'administradores' => (int) ($usuarios['administradores'] ?? 0),
            'usuariosOnline' => (int) ($conectados['total'] ?? 0),
            'jugadoresReales' => $totalJugadores,
            'partidasFinalizadas' => (int) ($partidas['finalizadas'] ?? 0),
            'partidasHoy' => (int) ($partidas['hoy'] ?? 0),
            'reportesPendientes' => (int) ($reportes['total'] ?? 0),
            'niveles' => $niveles,
            'paises' => $paises,
            'sexos' => $sexos,
            'edades' => $edades['rangos'],
            'edadPromedio' => $edades['promedio'],
            'anioActual' => (int) date('Y'),
        ];
    }

    /**
     * Usa exactamente la misma regla que Dificultad::nivelJugador() del juego.
     */
    private function calcularNiveles($usuarioActual, $totalJugadores)
    {
        $filas = $this->database->queryAll(
            "SELECT g.user_id,
                    COUNT(*) AS respondidas,
                    COALESCE(SUM(r.is_correct = 1), 0) AS correctas
             FROM game_answers ga
             INNER JOIN game g ON g.id = ga.game_id
             INNER JOIN response r ON r.id = ga.response_id
             WHERE g.user_id <> ?
             GROUP BY g.user_id",
            [$usuarioActual]
        );

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

    private function calcularDistribucion($sql, $usuarioActual, $total)
    {
        $filas = $this->database->queryAll($sql, [$usuarioActual]);
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
     * Edad estimada: solo se guarda el año de nacimiento, no el día y mes.
     */
    private function calcularEdades($usuarioActual)
    {
        $filas = $this->database->queryAll(
            "SELECT u.Anio_nacimiento AS anio
             FROM usuarios u
             WHERE u.Id <> ?
               AND EXISTS (
                   SELECT 1 FROM game g
                   INNER JOIN game_answers ga ON ga.game_id = g.id
                   WHERE g.user_id = u.Id
               )",
            [$usuarioActual]
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
        $suma = 0;
        $cantidadConEdad = 0;

        foreach ($filas as $fila) {
            $anio = (int) $fila['anio'];
            if ($anio < 1900 || $anio > $anioActual + 5) {
                $rangos['Sin dato']++;
                continue;
            }

            $edad = $anioActual - $anio;
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
        ];
    }

    public function listarUsuarios($rol, $busqueda, $pagina, $porPagina = 10)
    {
        if (!in_array($rol, self::ROLES, true)) {
            $rol = '';
        }

        $where = ['1 = 1'];
        $params = [];

        if ($rol !== '') {
            $where[] = 'rol = ?';
            $params[] = $rol;
        }

        if ($busqueda !== '') {
            $like = '%' . $busqueda . '%';
            $where[] = '(Usuario LIKE ? OR Email LIKE ? OR Nombre LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $condicion = implode(' AND ', $where);

        $conteo = $this->database->queryOne(
            "SELECT COUNT(*) AS total FROM usuarios WHERE $condicion",
            $params
        );
        $total = (int) ($conteo['total'] ?? 0);
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina = min(max(1, (int) $pagina), $paginas);
        $offset = ($pagina - 1) * $porPagina;

        $usuarios = $this->database->queryAll(
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
             WHERE $condicion
             ORDER BY Id DESC
             LIMIT $porPagina OFFSET $offset",
            $params
        );

        foreach ($usuarios as &$usuario) {
            $usuario['id'] = (int) $usuario['id'];
            $usuario['verificado'] = (int) $usuario['verificado'];
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

    public function crear($formulario)
    {
        $sql = "INSERT INTO usuarios
                (Nombre, Anio_nacimiento, Sexo, Provincia, Pais, Usuario, Email,
                 Contrasena, Verificado, Token, Foto_url, rol)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?)";

        $this->database->execute($sql, [
            $formulario['nombre'],
            (int) $formulario['anioNacimiento'],
            $formulario['sexo'],
            $formulario['provincia'],
            $formulario['pais'],
            $formulario['usuario'],
            $formulario['email'],
            md5($formulario['contrasena']),
            (int) $formulario['verificado'],
            $formulario['rol'],
        ]);

        return $this->database->lastInsertId();
    }

    public function actualizar($formulario)
    {
        $cambiaContrasena = isset($formulario['contrasena']) && $formulario['contrasena'] !== '';

        $sql = "UPDATE usuarios
                SET Nombre = ?, Anio_nacimiento = ?, Sexo = ?, Provincia = ?, Pais = ?,
                    Usuario = ?, Email = ?, Verificado = ?, rol = ?";

        $params = [
            $formulario['nombre'],
            (int) $formulario['anioNacimiento'],
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
        $params[] = (int) $formulario['id'];

        return $this->database->execute($sql, $params);
    }

    public function eliminar($id)
    {
        return $this->database->execute(
            "DELETE FROM usuarios WHERE Id = ?",
            [(int) $id]
        );
    }
}
