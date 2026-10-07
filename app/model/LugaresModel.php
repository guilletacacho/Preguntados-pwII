<?php

namespace app\model;
class LugaresModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function getLugares()
    {
        $sql_lugares = "SELECT * 
        FROM entradas_messi.lugares 
        WHERE disponible = 1 ORDER BY precio DESC";

        return $this->database->queryAll($sql_lugares);
    }
}