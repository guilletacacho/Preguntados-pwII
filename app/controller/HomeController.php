<?php

namespace app\controller;

use Redirect;

class HomeController
{
    private $render;
    private $presence;
    private $curiosities;

    public function __construct($render, $presence, $curiosities)
    {
        $this->render = $render;
        $this->presence = $presence;
        $this->curiosities = $curiosities;
    }

    public function show()
    {
        if (!isset($_SESSION['usuario_id'])) {
            Redirect::toIndex();
        }

        header('Cache-Control: no-store');
        $this->presence->touch($_SESSION['usuario_id'], session_id());
        $data = [
            "usuario" => $_SESSION['usuario'] ?? "",
            "curiosidad" => $this->curiosities->next(),
            "usuariosOnline" => $this->presence->countOnline()
        ];

        $this->render->renderiza("home", $data);
    }
}
