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
        $sql = "SELECT id, usuario FROM usuarios WHERE usuario = '$usuario' AND password = '$passwordHash'";

        return $this->database->queryOne($sql);
    }
}
