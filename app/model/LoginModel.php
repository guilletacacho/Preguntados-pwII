<?php

namespace app\model;
class LoginModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function autenticar($usuario, $password)
    {
        $passwordHash = md5($password);

        // A propósito sin prepared statements: lo arreglamos en la clase de refactor
        $sql = "SELECT Id AS id, Usuario as usuario, Verificado as verificado
         FROM usuarios
           WHERE Usuario = '$usuario'
           AND Contrasena = '$passwordHash'";

        return $this->database->queryOne($sql);
    }
}
