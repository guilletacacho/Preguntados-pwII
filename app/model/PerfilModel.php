<?php

namespace app\model;

class PerfilModel {
    private $database;

    public function __construct($database) {
        $this->database = $database;
    }

    public function obtenerPorId($id){
        $sql = "SELECT Id, Nombre, Usuario, Provincia, Pais, Foto_url FROM usuarios WHERE Id = ?";

        return $this->database->queryOne($sql,[$id]);
    }

    public function obtenerPorUsuario($usuario){
        $sql = "SELECT Id, Nombre, Usuario, Provincia, Pais, Foto_url FROM usuarios WHERE Usuario = ? AND Verificado = 1";

        return $this->database->queryOne($sql,[$usuario]);
    }

    public function obtenerParaEditar($id){
        $sql = "SELECT Id, Nombre, Anio_nacimiento, Sexo, Provincia, Pais, Usuario, Email, Foto_url FROM usuarios WHERE Id = ?";

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
    public function actualizar($id, $nombre, $anio, $sexo, $provincia, $pais, $email, $fotoUrl){
        $sql = "UPDATE usuarios
                SET Nombre = ?, Anio_nacimiento = ?, Sexo = ?, Provincia = ?, Pais = ?, Email = ?, Foto_url = COALESCE(?, Foto_url)
                WHERE Id = ?";

        return $this->database->execute($sql,[$nombre, $anio, $sexo, $provincia, $pais, $email, $fotoUrl, $id]);
    }

    public function actualizarContrasena($id, $contrasena){
        $sql = "UPDATE usuarios SET Contrasena = ? WHERE Id = ?";

        return $this->database->execute($sql,[md5($contrasena), $id]);
    }
}