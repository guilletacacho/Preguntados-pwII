<?php

class MyDatabase
{
    private $conexion;

    public function __construct($host, $user, $pass, $db, $port)
    {
        $this->conexion = new PDO(
    "mysql:host=$host;dbname=$db;port=$port;charset=utf8mb4",
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$this->conexion->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    public function queryOne(string $sql, array $params = [])
    {
        Log::info($sql);
        $resultado = $this->conexion->prepare($sql);
        $resultado->execute($params);
        $fila = $resultado->fetch(PDO::FETCH_ASSOC);
        return $fila === false ? null : $fila;
    }

    public function queryAll(string $sql, array $params = [])
    {
        Log::info($sql);
        $resultado = $this->conexion->prepare($sql);
        $resultado->execute($params);
        return $resultado->fetchAll(PDO::FETCH_ASSOC);
    }

    public function execute(string $sql, array $params = [])
    {
        Log::info($sql);
        $statement = $this->conexion->prepare($sql);
        $statement -> execute($params);

        return $statement->rowCount();
    }

    public function lastInsertId()
    {
        return $this->conexion->lastInsertId();
    }
}
