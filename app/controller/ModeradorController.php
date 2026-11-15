<?php

namespace app\controller;

use Redirect;

class ModeradorController
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

        $this->render->renderiza("moderador");
    }

    public function preguntas()
    {
        if (!isset($_SESSION['usuario_id'])) {
            Redirect::toIndex();
        }

        $this->render->renderiza("moderadorPreguntas");
    }

    public function altaPregunta()
    {
        if (!isset($_SESSION['usuario_id'])) {
            Redirect::toIndex();
        }

        $this->render->renderiza("moderadorAltaPregunta");
    }
}