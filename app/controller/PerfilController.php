<?php

namespace app\controller;

class PerfilController
{
    private $render;

    public function __construct($render)
    {
        $this->render = $render;
    }

    public function show()
    {
        if (!isset($_SESSION['usuario_id'])) {
            \Redirect::toIndex();
        }

        $this->render->renderiza('perfil', ['usuario' => $_SESSION['usuario'] ?? 'Jugador']);
    }
}
