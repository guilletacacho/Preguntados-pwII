<?php

class MyDatabase
{
    private $conexion;

    public function __construct($host, $user, $pass, $db, $port)
    {
        $this->conexion = new PDO("mysql:host=$host;dbname=$db;port=$port", $user, $pass);
    }

    public function queryOne(string $sql, array $params = [])
    {
        Log::info($sql);
        $resultado = $this->conexion->prepare($sql);
        $resultado->execute($params);
        $fila = $resultado->fetch(PDO::FETCH_ASSOC);
        return $fila === false ? null : $fila;
    }

    public function queryAll(string $sql)
    {
        Log::info($sql);
        $resultado = $this->conexion->query($sql, PDO::FETCH_ASSOC);
        return $resultado->fetchAll();
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
