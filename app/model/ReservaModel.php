<?php

namespace app\model;
class ReservaModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function getReserva($id)
    {
        $sql = "SELECT reservas.*, lugares.sector, lugares.precio
        FROM reservas
        INNER JOIN lugares ON reservas.lugar_id = lugares.id
        WHERE reservas.id = $id";

        $reserva = $this->database->queryOne($sql);

        $reserva['precio'] = number_format($reserva['precio'], 2, ',', '.');

        return $reserva;
    }

    public function getReservas()
    {
        $sql_reservas = "SELECT reservas.*, lugares.sector, lugares.precio
                  FROM reservas
                  INNER JOIN lugares ON reservas.lugar_id = lugares.id
                  ORDER BY reservas.fecha_reserva DESC";

        $reservas = $this->database->queryAll($sql_reservas);

        foreach ($reservas as &$r) {
            $r['nombre_completo'] = $r['nombre'] . ' ' . $r['apellido'];
            $r['precio'] = number_format($r['precio'], 2, ',', '.');
            $r['fecha_reserva'] = date('d/m/Y H:i', strtotime($r['fecha_reserva']));
        }
        unset($r);

        return $reservas;
    }

    public function getLugar($id)
    {
        $sql_lugar = "SELECT * FROM lugares WHERE id = $id AND disponible = 1";
        $lugar = $this->database->queryOne($sql_lugar);
        $lugar['precio'] = number_format($lugar['precio'], 2, ',', '.');
        return $lugar;
    }

    public function crearReserva($nombre, $apellido, $dni, $email, $telefono, $lugarId)
    {
        $sql_insert = "INSERT INTO reservas (lugar_id, nombre, apellido, dni, email, telefono)
                    VALUES ($lugarId, '$nombre', '$apellido', '$dni', '$email', '$telefono')";

        $this->database->execute($sql_insert);

        $reserva_id = $this->database->lastInsertId();

        $sql_update = "UPDATE lugares SET disponible = 0 WHERE id = $lugarId";
        $this->database->execute($sql_update);

        return $reserva_id;
    }
}