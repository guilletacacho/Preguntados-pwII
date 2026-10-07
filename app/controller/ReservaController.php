<?php

namespace app\controller;

use Redirect;
use Request;

class ReservaController
{
    private $model;
    private $render;

    public function __construct($model, $render)
    {
        $this->model = $model;
        $this->render = $render;
    }

    public function sucess()
    {
        if (!isset($_SESSION['usuario'])) {
            Redirect::toIndex();
        }

        $reserva_id = (int)Request::get('id', 0);

        $reserva = $this->model->getReserva($reserva_id);

        if ($reserva === null) {
            Redirect::toIndex();
        }

        $this->render->renderiza("exito", $reserva);
    }

    public function show()
    {
        if (!isset($_SESSION['usuario'])) {
            Redirect::toIndex();
        }

        $data["reservas"] = $this->model->getReservas();

        $this->render->renderiza("reservas", $data);
    }

    public function mostrarUnLugar()
    {
        if (!isset($_SESSION['usuario'])) {
            Redirect::toIndex();
        }

        $lugar_id = (int)Request::get('id', Request::post('lugar_id', 0));

        $lugar = $this->model->getLugar($lugar_id);

        if ($lugar === null) {
            Redirect::to('/Preguntados/lugares');
        }


        $this->render->renderiza("reserva", $lugar);
    }

    public function crearReserva()
    {
        $lugar_id = Request::post('lugar_id', 0);
        $nombre = Request::post('nombre');
        $apellido = Request::post('apellido');
        $dni = Request::post('dni');
        $email = Request::post('email');
        $telefono = Request::post('telefono');

        $reserva_id = $this->model->crearReserva($nombre, $apellido, $dni, $email, $telefono, $lugar_id);

        Redirect::to("/Preguntados/reserva/sucess?id=$reserva_id");
    }
}