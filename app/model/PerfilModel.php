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

    public function obtenerUltimasPartidas($userId){

        $sql = "SELECT id, status, total_points, finished_at
                FROM game
                WHERE user_id = ? 
                AND (
                    status = 'FINALIZADA'
                    OR (status = 'ABANDONADA' AND total_points >= 1)
                )
                ORDER BY finished_at DESC, id DESC
                LIMIT 3";
        
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
