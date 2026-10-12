<?php

namespace app\model;

class RegistroModel{
    
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function insertar($nombre,$anioNacimiento,$sexo,$pais,$usuario,$contrasena){

        $sql = "INSERT INTO usuarios 
        (Nombre,anio_nacimiento,Sexo,Pais,Usuario,Contrasena)
        VALUES (?,?,?,?,?,?)";

        return $this->database->execute($sql,[
            $nombre,
            $anioNacimiento,
            $sexo,
            $pais,
            $usuario,
            md5($contrasena)
        ]);
    }

    public function existeUsuario($usuario){
        $sql = "SELECT Id FROM usuarios WHERE Usuario = ?";
        $fila = $this->database->queryOne($sql,[$usuario]);

        return $fila !== null;
    }
}