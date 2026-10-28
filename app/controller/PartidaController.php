<?php

namespace app\controller;

class PartidaController{

    private $model;
    private $render;

    public function __construct($model,$render)
    {
        $this->model = $model;
        $this->render = $render;
    }

    public function show(){

        $pregunta = $this->model->obtenerPreguntaAleatoria();

        //solo se ejecuta si no hay preguntas cargadas en la base
        if (!$pregunta) {
            $this->render->renderiza('partida', ['sinPreguntas' => true]);
            return;
        }

        $respuestas = $this->model->obtenerRespuestasPorPregunta(
            $pregunta['question_id']
        );

        $letras = ['A', 'B', 'C', 'D'];
        foreach ($respuestas as $indice => &$respuesta) {
            $respuesta['letra'] = $letras[$indice] ?? '';
        }
        unset($respuesta);

        $categoriaSlugs = [
            'Geografía' => 'geografia',
            'Historia' => 'historia',
            'Ciencia' => 'ciencia',
            'Arte' => 'arte',
            'Deportes' => 'deporte',
            'Cine y TV' => 'cine'
        ];

        $categoria = $pregunta['categoria'];
        $data = [
            'questionId' => $pregunta['question_id'],
            'categoria' => $categoria,
            'categoriaSlug' => $categoriaSlugs[$categoria] ?? 'ciencia',
            'pregunta' => $pregunta['pregunta'],
            'respuestas' => $respuestas,
            'preguntaNumero' => 1,
            'puntos' => 0
        ];


        $this->render->renderiza('partida',$data);
    }

    public function responderPregunta(){

    $questionId = (int) \Request::post('question_id',0);
    $responseId = (int) \Request::post('response_id',0);

    if($questionId <= 0 || $responseId <= 0){
        \Redirect::to('/Preguntados/partida/show');
    }

    $esCorrecta = $this->model->esRespuestaCorrecta(
        $questionId,
        $responseId
    );

    $pregunta = $this->model->obtenerPreguntaPorId($questionId);

    if (!$pregunta){
        \Redirect::to('/Preguntados/partida/show');
    }

    $respuestas = $this->model->obtenerRespuestasPorPregunta($questionId);

    $respuestaCorrecta = null;
    
    if(!$esCorrecta){
        $respuestaCorrecta = $this->model->obtenerRespuestaCorrecta(
            $questionId
        );
    }

    $letras = [ 'A','B','C','D'];

    foreach($respuestas as $indice => &$respuesta){
        
        $respuestaId = (int) $respuesta['id'];

        $respuesta['letra'] = $letras[$indice] ?? '';

        //indica si el usuario selecciono esta opcion
        $respuesta['seleccionada'] = $respuestaId === $responseId;
    
        //verde si acero o si es la correcta luego de fallar
        $respuesta['correcta'] = ($esCorrecta && $respuesta['seleccionada']) || 
        ( $respuestaCorrecta !== null && $respuestaId === (int) $respuestaCorrecta['id']);

        //rojo solo cuando el usuario se equivoca
        $respuesta['incorrecta'] = !$esCorrecta && $respuesta['seleccionada'];
    }
    unset($respuesta);

    $categoriaSlugs = [
        'Geografía' => 'geografia',
        'Historia' => 'historia',
        'Ciencia' => 'ciencia',
        'Arte' => 'arte',
        'Deportes' => 'deporte',
        'Cine y TV' => 'cine'
    ];

    $categoria = $pregunta['categoria'];

    $data = [
        'questionId' => $questionId,
        'categoria' => $categoria,
        'categoriaSlug' => $categoriaSlugs[$categoria] ?? 'ciencia',
        'pregunta' => $pregunta['pregunta'],
        'respuestas' => $respuestas,

        //permitir que mustache muestre el resultado
        'resultadoMostrado' => true,
        'esCorrecta' => $esCorrecta,

        'preguntaNumero' => 1,
        'puntos' => 0
    ];

        $this->render->renderiza('partida', $data);
    }
}