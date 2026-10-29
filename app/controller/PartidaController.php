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

        if(!isset($_SESSION['game_id'])){
            \Redirect::to('/Preguntados/home/show');
        }

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
            'preguntaNumero' => $_SESSION['pregunta_numero'],
            'puntos' => $_SESSION['puntos']
        ];


        $this->render->renderiza('partida',$data);
    }

    public function responderPregunta(){

    //verifica que la sesion de game este en curso 
    if(!isset($_SESSION['game_id']) || (int) $_SESSION['game_id'] <= 0){
            \Redirect::to('/home/show');
    }

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
        \Redirect::to('/partida/show');
    }

    $respuestas = $this->model->obtenerRespuestasPorPregunta($questionId);

    $respuestaCorrecta = null;
    
    if(!$esCorrecta){
        $respuestaCorrecta = $this->model->obtenerRespuestaCorrecta(
            $questionId);
    }

    $gameId = (int) $_SESSION['game_id'];

    $puntosPregunta = 0;

    if($esCorrecta){
        $preguntaPuntos = $this->model->obtenerPuntosPregunta($questionId);

        if($preguntaPuntos){
            $puntosPregunta = (int) $preguntaPuntos['points'];
        }

        $_SESSION['puntos'] += $puntosPregunta;
        $_SESSION['correctas']++;
    }

    //REGISTRAR RESPUESTA
    $earnedPoints = (int) $puntosPregunta;

    $this->model->registrarRespuesta(
        $gameId,
        $questionId,
        $responseId,
        $earnedPoints
    );
 
    //ACTUALIZARPARTIDA

    $this->model->actualizarPartida(
        $_SESSION['puntos'],
        $_SESSION['correctas'],
        $gameId
    );

    if(!$esCorrecta){
        $this->model->finalizarPartida($gameId);
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
        'preguntaNumero' => $_SESSION['pregunta_numero'],
        'puntos' => $_SESSION['puntos']
    ];

        $this->render->renderiza('partida', $data);
    }

    public function iniciar(){

        $userId = isset($_SESSION['usuario_id'])
            ? (int) $_SESSION['usuario_id']
            : 0;

        if($userId <= 0 ){
            \Redirect::toIndex();
        }

        $gameId = $this->model->iniciarPartida($userId);

        if($gameId <= 0){
            \Redirect::toIndex();
        }

        $_SESSION['game_id'] = $gameId;
        $_SESSION['puntos'] = 0;
        $_SESSION['correctas'] = 0;
        $_SESSION['pregunta_numero'] = 1;

        //redirige a show() el que tiene preguntas y respuestas
        \Redirect::to('/partida/show');


    }

    public function abandonar(){

    if(!isset($_SESSION['game_id']) || (int) $_SESSION['game_id'] <= 0){
            $destino = \Request::get('destino', '/home/show');
        \Redirect::to($destino);
    }

    $gameId = (int) $_SESSION['game_id'];

    $this->model->abandonoPartida($gameId);

    //elimina todos los datos de la partida en la sesion
    unset(
        $_SESSION['game_id'],
        $_SESSION['puntos'],
        $_SESSION['correctas'],
        $_SESSION['pregunta_numero']
    );
    $destino = \Request::get('destino','/home/show'); 
    \Redirect::to($destino);
    }

    public function salir(){

       if(!isset($_SESSION['game_id']) || (int) $_SESSION['game_id'] <= 0){
            \Redirect::to('/home/show');
    }

        unset(
            $_SESSION['game_id'],
            $_SESSION['puntos'],
            $_SESSION['correctas'],
            $_SESSION['pregunta_numero']
        );

        \Redirect::to('/home/show');
    }
}