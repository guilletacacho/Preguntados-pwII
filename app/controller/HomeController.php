<?php

namespace app\controller;

use Redirect;

class HomeController
{
    private $render;

    public function __construct($render)
    {
        $this->render = $render;
    }

    public function show()
    {
        if (!isset($_SESSION['usuario_id'])) {
            Redirect::toIndex();
        }

        $data = [
            "usuario" => $_SESSION['usuario'] ?? ""
        ];

        $this->render->renderiza("home", $data);
    }
}