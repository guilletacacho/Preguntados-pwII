<?php

namespace app\controller;

use Request;

class EventoController
{
    private $model;
    private $render;

    public function __construct($model, $render)
    {
        $this->model = $model;
        $this->render = $render;
    }

    public function show()
    {
        $data = $this->model->getEvento();
        $data["error"] = Request::get('login_error') !== null ? "Usuario o contraseña incorrectos." : "";
        $data["fecha_evento_formateada"] = date('d/m/Y H:i', strtotime($data['fecha_evento']));
        $data["logueado"] = isset($_SESSION['usuario']);
        $data["usuario"] = $_SESSION['usuario'] ?? null;

        $this->render->renderiza("evento", $data);
    }

    public function miami()
    {
        $data["nombre"] = Request::get('nombre');
        $this->render->renderiza("miami", $data);
    }
}
