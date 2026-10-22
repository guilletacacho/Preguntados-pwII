<?php

namespace app\model;

class RegistroModel{

    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function insertar($nombre,$anioNacimiento,$sexo,$pais,$usuario,$email,$contrasena,$fotoUrl){

        $sql = "INSERT INTO usuarios 
        (Nombre,anio_nacimiento,Sexo,Pais,Usuario,Email,Contrasena,Verificado,Token,Foto_url)
        VALUES (?,?,?,?,?,?,?,0,NULL,?)";

        return $this->database->execute($sql,[
            $nombre,
            $anioNacimiento,
            $sexo,
            $pais,
            $usuario,
            $email,
            md5($contrasena),
            $fotoUrl
        ]);
    }

    public function existeUsuario($usuario){
        $sql = "SELECT Id FROM usuarios WHERE Usuario = ?";
        $fila = $this->database->queryOne($sql,[$usuario]);

        return $fila !== null;
    }

    public function existeEmail($email){
        $sql = "SELECT Id FROM usuarios WHERE Email = ?";
        $fila = $this->database->queryOne($sql,[$email]);

        return $fila !== null;
    }

    public function guardarToken($usuarioId, $token, $expira){
        $sql = "INSERT INTO verificacion_tokens (UsuarioId, Token, Expira) VALUES (?,?,?)";
        return $this->database->execute($sql,[$usuarioId, $token, $expira]);
    }

    public function obtenerUsuarioPorToken($token){
        $sql = "SELECT u.Id, u.Nombre, u.Usuario, u.Email 
                FROM verificacion_tokens vt
                JOIN usuarios u ON u.Id = vt.UsuarioId
                WHERE vt.Token = ? AND vt.Usado = 0 AND vt.Expira > NOW()";
        return $this->database->queryOne($sql,[$token]);
    }

    public function marcarVerificado($usuarioId){
        $sql = "UPDATE usuarios SET Verificado = 1 WHERE Id = ?";
        return $this->database->execute($sql,[$usuarioId]);
    }

    public function marcarTokenUsado($token){
        $sql = "UPDATE verificacion_tokens SET Usado = 1 WHERE Token = ?";
        return $this->database->execute($sql,[$token]);
    }

    public function obtenerUsuarioPorNombre($usuario){
        $sql = "SELECT * FROM usuarios WHERE Usuario = ?";
        return $this->database->queryOne($sql,[$usuario]);
    }
}
