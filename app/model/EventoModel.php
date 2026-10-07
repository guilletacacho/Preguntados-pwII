<?php

namespace app\model;
class EventoModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function getEvento()
    {
        $sql_evento = "SELECT * FROM evento LIMIT 1";

        return $this->database->queryOne($sql_evento);
    }
}
