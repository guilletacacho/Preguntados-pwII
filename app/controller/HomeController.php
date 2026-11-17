<?php

namespace app\controller;

use Redirect;

class HomeController
{
    private $render;
    private $presence;
    private $curiosities;
    private $sugerenciaModel;

    public function __construct($render, $presence, $curiosities, $sugerenciaModel)
    {
        $this->render = $render;
        $this->presence = $presence;
        $this->curiosities = $curiosities;
        $this->sugerenciaModel = $sugerenciaModel;
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

    public function mostrarSugerencias(){
        if (!isset($_SESSION['usuario_id'])) {
            Redirect::toIndex();
        }

        $this->render->renderiza("sugerencias");
    }

    public function enviarSugerencia(){
        if (!isset($_SESSION['usuario_id'])) {
            Redirect::toIndex();
        }

        $categoryId = $_POST['category'] ?? null;
        $questionText = $_POST['question'] ?? null;
        $userId = $_SESSION['usuario_id'] ?? null;

        $this->sugerenciaModel->crearSugerencia($userId, $categoryId, $questionText);

        Redirect::to("/Preguntados/home/show");
    }
}
