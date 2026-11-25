<?php

namespace app\model;

class PerfilModel {
    private $database;

    public function __construct($database) {
        $this->database = $database;
    }

    public function obtenerPorId($id){
        $sql = "SELECT Id, Nombre, Anio_nacimiento, Mes_nacimiento, Dia_nacimiento, Usuario, Provincia, Pais, Foto_url FROM usuarios WHERE Id = ?";

        return $this->database->queryOne($sql,[$id]);
    }

    public function obtenerPorUsuario($usuario){
        $sql = "SELECT Id, Nombre, Anio_nacimiento, Mes_nacimiento, Dia_nacimiento, Usuario, Provincia, Pais, Foto_url FROM usuarios WHERE Usuario = ? AND Verificado = 1";

        return $this->database->queryOne($sql,[$usuario]);
    }

    public function obtenerParaEditar($id){
        $sql = "SELECT Id, Nombre, Anio_nacimiento, Mes_nacimiento, Dia_nacimiento, Sexo, Provincia, Pais, Usuario, Email, Foto_url FROM usuarios WHERE Id = ?";

        return $this->database->queryOne($sql,[$id]);
    }

    public function existeEmailDeOtro($email, $id){
        $sql = "SELECT Id FROM usuarios WHERE Email = ? AND Id <> ?";

        return $this->database->queryOne($sql,[$email, $id]) !== null;
    }

    public function contrasenaCorrecta($id, $contrasena){
        $sql = "SELECT Id FROM usuarios WHERE Id = ? AND Contrasena = ?";

        return $this->database->queryOne($sql,[$id, md5($contrasena)]) !== null;
    }

    // aviso: si $fotoUrl es null se conserva la foto actual.
    public function actualizar($id, $nombre, $anio, $mes, $dia, $sexo, $provincia, $pais, $email, $fotoUrl){
        $sql = "UPDATE usuarios
                SET Nombre = ?, Anio_nacimiento = ?, Mes_nacimiento = ?, Dia_nacimiento = ?, Sexo = ?, Provincia = ?, Pais = ?, Email = ?, Foto_url = COALESCE(?, Foto_url)
                WHERE Id = ?";

        return $this->database->execute($sql,[$nombre, $anio, $mes, $dia, $sexo, $provincia, $pais, $email, $fotoUrl, $id]);
    }

    public function quitarFoto($id){
        $sql = "UPDATE usuarios SET Foto_url = NULL WHERE Id = ?";

        return $this->database->execute($sql, [$id]);
    }

    public function actualizarContrasena($id, $contrasena){
        $sql = "UPDATE usuarios SET Contrasena = ? WHERE Id = ?";

        return $this->database->execute($sql,[md5($contrasena), $id]);
    }

    // Últimas partidas, cada una con sus preguntas en orden (el "camino" del perfil).
    public function obtenerPartidasConPreguntas($userId, $limite = 5){

        $sql = "SELECT id, status, total_points, finished_at
                FROM game
                WHERE user_id = ?
                AND (
                    status = 'FINALIZADA'
                    OR (status = 'ABANDONADA' AND total_points >= 1)
                )
                ORDER BY finished_at DESC, id DESC
                LIMIT " . max(1, (int) $limite);

        $partidas = $this->database->queryAll($sql, [$userId]);
        if (!$partidas) {
            return [];
        }

        $ids = array_map(fn($partida) => (int) $partida['id'], $partidas);
        $marcas = implode(',', array_fill(0, count($ids), '?'));

        $preguntas = $this->database->queryAll(
            "SELECT ga.game_id, c.name AS categoria, c.color, c.icono,
                    CAST(r.is_correct AS UNSIGNED) AS acertada
             FROM game_answers ga
             INNER JOIN questions q ON q.id = ga.question_id
             INNER JOIN category c ON c.id = q.category_id
             INNER JOIN response r ON r.id = ga.response_id
             WHERE ga.game_id IN ($marcas)
             ORDER BY ga.game_id, ga.turn_number, ga.id",
            $ids
        );

        $porPartida = [];
        foreach ($preguntas as $pregunta) {
            $porPartida[(int) $pregunta['game_id']][] = $pregunta;
        }
        foreach ($partidas as &$partida) {
            $partida['preguntas'] = $porPartida[(int) $partida['id']] ?? [];
        }
        unset($partida);

        return $partidas;
    }

    // Preguntas respondidas y acertadas por categoría, incluidas las que todavía no le tocaron.
    public function obtenerAciertosPorCategoria($userId){

        $sql = "SELECT c.name AS categoria, c.color, c.icono,
                       COUNT(ga.id) AS respondidas,
                       COALESCE(SUM(r.is_correct), 0) AS acertadas
                FROM category c
                LEFT JOIN questions q ON q.category_id = c.id
                LEFT JOIN game_answers ga ON ga.question_id = q.id
                    AND ga.game_id IN (SELECT id FROM game WHERE user_id = ?)
                LEFT JOIN response r ON r.id = ga.response_id
                WHERE c.is_active = 1
                GROUP BY c.id, c.name, c.color, c.icono
                ORDER BY respondidas DESC, c.name";

        return $this->database->queryAll($sql, [$userId]);
    }

    public function obtenerPuntajeTotal($userId){
        
        $sql = "SELECT COALESCE(SUM(total_points),0) AS puntaje
                FROM game
                WHERE user_id = ?
                AND status = 'FINALIZADA'";

        return $this->database->queryOne($sql, [$userId]);
    }

    public function obtenerCantidadPartidasFinalizadas($userId){

        $sql = "SELECT COUNT(*) AS cantidad
                FROM game
                WHERE user_id = ?
                AND status = 'FINALIZADA'";

        return $this->database->queryOne($sql, [$userId]);
    }

    public function obtenerPuestoHistorico($userId){

        $sql = "WITH ranking_historico AS (
                SELECT
                    u.Id,
                    SUM(g.total_points) AS puntos,
                    ROW_NUMBER() OVER (
                        ORDER BY SUM(g.total_points) DESC, u.Id ASC
                    ) AS puesto
                FROM usuarios u
                JOIN game g ON g.user_id = u.Id
                WHERE g.status = 'FINALIZADA'
                GROUP BY u.Id
            )
            SELECT puesto
            FROM ranking_historico
            WHERE Id = ?";

        return $this->database->queryOne($sql, [$userId]);
    }

}
