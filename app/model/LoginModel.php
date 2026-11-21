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

        $sql = "SELECT Id AS id, Usuario as usuario, Foto_url AS foto_url,
                       CAST(Verificado AS UNSIGNED) as verificado, rol, bloqueado, version_sesion
         FROM usuarios
           WHERE Usuario = ?
           AND Contrasena = ?";

        return $this->database->queryOne($sql, [$usuario, $passwordHash]);
    }

    public function estadoSesion($userId)
    {
        return $this->database->queryOne(
            'SELECT bloqueado, version_sesion FROM usuarios WHERE Id = ?',
            [(int) $userId]
        );
    }

    public function liberarSesionUnica($userId, $sessionKey)
    {
        \SesionUnica::liberar($this->database, $userId, $sessionKey);
    }
}
