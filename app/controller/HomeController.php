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

        $datos = [
        'preguntasAprobadas' => $this->sugerenciaModel->obtenerUltimasPreguntasAprobadas(10),
        'csrfToken' => $this->asegurarCsrfSugerencias(),
    ];

        $this->render->renderiza("sugerencias", $datos);
    }

    public function enviarSugerencia(){
        if (!isset($_SESSION['usuario_id'])) {
            Redirect::toIndex();
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            return;
        }
        
        if (!$this->csrfSugerenciasValido($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            return;
        }

        try{

            $datos = $this->validarDatosSugerencia(
                $_POST['category'] ?? null,
                $_POST['question'] ?? null
            );

            $this->sugerenciaModel->crearSugerencia(
                $_SESSION['usuario_id'],
                $datos['categoryId'],
                $datos['questionText']
            );
        
            Redirect::to("/Preguntados/home/show");
            return;
        }catch (\DomainException $error){
            $_SESSION['sugerencia_error'] = $error->getMessage();
            Redirect::to('/Preguntados/home/mostrarSugerencias');
            return;
        }
    }

    //METODOS PRIVADOS

    private function validarDatosSugerencia($categoryInput, $questionInput)
    {
        $categoryId = filter_var(
            $categoryInput,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($categoryId === false) {
            throw new \DomainException('Seleccioná una categoría válida.');
        }

        if (!is_string($questionInput)) {
            throw new \DomainException('La pregunta no es válida.');
        }

        $questionText = trim($questionInput);

        if ($questionText === '' || mb_strlen($questionText, 'UTF-8') > 500) {
            throw new \DomainException(
                'La pregunta es obligatoria y admite hasta 500 caracteres.'
            );
        }

        return [
            'categoryId' => $categoryId,
            'questionText' => $questionText,
        ];
    }

    private function asegurarCsrfSugerencias()
        {
            if (empty($_SESSION['csrf_sugerencias'])) {
                $_SESSION['csrf_sugerencias'] = bin2hex(random_bytes(32));
            }

            return $_SESSION['csrf_sugerencias'];
        }

    private function csrfSugerenciasValido($tokenEnviado)
        {
            $tokenSesion = $_SESSION['csrf_sugerencias'] ?? '';

            return is_string($tokenSesion)
                && is_string($tokenEnviado)
                && $tokenSesion !== ''
                && hash_equals($tokenSesion, $tokenEnviado);
        }
    
}
