<?php

namespace app\controller;

use Redirect;

class LugaresController
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
        if (!isset($_SESSION['usuario'])) {
            Redirect::toIndex();
        }

        $lugares = $this->model->getLugares();

        $data = ["lugares" => $lugares, "usuario" => $_SESSION['usuario']];

        $this->render->renderiza("lugares", $data);
    }
}