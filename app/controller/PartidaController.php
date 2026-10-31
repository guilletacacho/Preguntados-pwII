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
        if (!isset($_SESSION['usuario_id'], $_SESSION['game_id'])) {
            \Redirect::toIndex();
        }
        if (isset($_SESSION['categoria_actual']) && ($_SESSION['preguntas_categoria'] ?? 0) > 0) {
            if (!isset($_SESSION['pregunta_pendiente'])) {
                $pregunta = $this->model->obtenerPreguntaAleatoria($_SESSION['categoria_actual'], (int) $_SESSION['game_id']);
                if (!$pregunta) {
                    unset($_SESSION['categoria_actual'], $_SESSION['preguntas_categoria']);
                    $this->show();
                    return;
                }
                $_SESSION['pregunta_pendiente'] = $pregunta['question_id'];
            }
            $this->pregunta();
            return;
        }
        $categorias = $this->model->obtenerCategorias((int) $_SESSION['game_id']);
        $disponibles = array_values(array_filter($categorias, static function ($categoria) {
            return (int) $categoria['preguntas'] > 0;
        }));
        if (!$disponibles) {
            unset($_SESSION['pregunta_pendiente'], $_SESSION['opciones_pendientes'], $_SESSION['categoria_actual'], $_SESSION['preguntas_categoria']);
            $this->render->renderiza('partida', ['sinPreguntas' => true, 'puntos' => $_SESSION['puntos']]);
            return;
        }
        if (!isset($_SESSION['pregunta_pendiente'])) {
            $categoria = $disponibles[random_int(0, count($disponibles) - 1)];
            $_SESSION['categoria_actual'] = (int) $categoria['id'];
            $_SESSION['preguntas_categoria'] = 0;
            $pregunta = $this->model->obtenerPreguntaAleatoria((int) $categoria['id'], (int) $_SESSION['game_id']);
            $_SESSION['pregunta_pendiente'] = $pregunta['question_id'];
        }
        $pregunta = $this->model->obtenerPreguntaPorId($_SESSION['pregunta_pendiente']);
        $slugs = ['Geografía' => 'geografia', 'Historia' => 'historia', 'Ciencia' => 'ciencia', 'Arte' => 'arte', 'Deportes' => 'deporte', 'Deporte' => 'deporte', 'Cine y TV' => 'cine', 'Cine' => 'cine'];
        foreach ($categorias as &$categoria) {
            $categoria['slug'] = $slugs[$categoria['name']] ?? 'ciencia';
            $categoria['seleccionada'] = $categoria['name'] === $pregunta['categoria'];
        }
        unset($categoria);
        $this->render->renderiza('ruleta', ['categorias' => $categorias, 'puntos' => $_SESSION['puntos']]);
    }

    public function pregunta(){

        if(!isset($_SESSION['usuario_id'], $_SESSION['game_id'])){
            \Redirect::to('/Preguntados/home/show');
        }
        if (!isset($_SESSION['pregunta_pendiente'])) {
            \Redirect::to('/Preguntados/partida/show');
        }

        $pregunta = $this->model->obtenerPreguntaPorId($_SESSION['pregunta_pendiente']);

        //solo se ejecuta si no hay preguntas cargadas en la base
        if (!$pregunta) {
            $this->render->renderiza('partida', ['sinPreguntas' => true]);
            return;
        }

        // El orden se sortea una sola vez y se conserva también al corregir.
        if ((int) ($_SESSION['opciones_pendientes']['question_id'] ?? 0) !== (int) $pregunta['question_id']) {
            $_SESSION['opciones_pendientes'] = [
                'question_id' => (int) $pregunta['question_id'],
                'respuestas' => $this->model->obtenerRespuestasPorPregunta($pregunta['question_id'])
            ];
        }
        $respuestas = $_SESSION['opciones_pendientes']['respuestas'];

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
            'preguntaCategoria' => ($_SESSION['preguntas_categoria'] ?? 0) + 1,
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
    $turnNumber = (int) \Request::post('turn_number', 0);

    if ($turnNumber !== (int) $_SESSION['pregunta_numero']) {
        \Redirect::to('/Preguntados/partida/show');
    }

    if ($questionId !== (int) ($_SESSION['pregunta_pendiente'] ?? 0)) {
        \Redirect::to('/Preguntados/partida/show');
    }


    if($questionId <= 0 || $responseId <= 0){
        \Redirect::to('/Preguntados/partida/show');
    }

    if ((int) ($_SESSION['opciones_pendientes']['question_id'] ?? 0) !== $questionId) {
        \Redirect::to('/Preguntados/partida/pregunta');
    }
    $respuestas = $_SESSION['opciones_pendientes']['respuestas'];
    $opcionesIds = array_map('intval', array_column($respuestas, 'id'));
    if (!in_array($responseId, $opcionesIds, true)) {
        \Redirect::to('/Preguntados/partida/pregunta');
    }

    $esCorrecta = $this->model->esRespuestaCorrecta(
        $questionId,
        $responseId
    );

    $pregunta = $this->model->obtenerPreguntaPorId($questionId);

    if (!$pregunta){
        \Redirect::to('/partida/show');
    }

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

    }

    //REGISTRAR RESPUESTA
    $earnedPoints = (int) $puntosPregunta;

    $registrada = $this->model->registrarRespuesta(
        $gameId,
        $questionId,
        $responseId,
        $earnedPoints,
        $turnNumber
    );
    if ($registrada === 0) {
        unset($_SESSION['pregunta_pendiente'], $_SESSION['opciones_pendientes']);
        \Redirect::to('/Preguntados/partida/show');
    }
    if ($esCorrecta) {
        $_SESSION['puntos'] += $puntosPregunta;
        $_SESSION['correctas']++;
    }
 
    //ACTUALIZARPARTIDA

    $this->model->actualizarPartida(
        $_SESSION['puntos'],
        $_SESSION['correctas'],
        $gameId
    );

    unset($_SESSION['pregunta_pendiente'], $_SESSION['opciones_pendientes']);
    $preguntaCategoria = ($_SESSION['preguntas_categoria'] ?? 0) + 1;
    $volverRuleta = $preguntaCategoria >= 4;
    if ($esCorrecta) {
        $_SESSION['preguntas_categoria'] = $preguntaCategoria;
        if ($volverRuleta) {
            unset($_SESSION['categoria_actual']);
            $_SESSION['preguntas_categoria'] = 0;
        }
    } else {
        unset($_SESSION['categoria_actual'], $_SESSION['preguntas_categoria']);
    }

    if(!$esCorrecta){
        $this->model->finalizarPartida($gameId);
        unset($_SESSION['game_id']);
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
        'preguntaCategoria' => $preguntaCategoria,
        'volverRuleta' => $volverRuleta,
        'puntos' => $_SESSION['puntos']
    ];

        if ($esCorrecta) {
            $_SESSION['pregunta_numero']++;
        }
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
        unset($_SESSION['pregunta_pendiente'], $_SESSION['opciones_pendientes'], $_SESSION['categoria_actual'], $_SESSION['preguntas_categoria']);

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
        $_SESSION['pregunta_numero'],
        $_SESSION['pregunta_pendiente'],
        $_SESSION['opciones_pendientes'],
        $_SESSION['categoria_actual'],
        $_SESSION['preguntas_categoria']
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
            $_SESSION['pregunta_numero'],
            $_SESSION['pregunta_pendiente'],
            $_SESSION['opciones_pendientes'],
            $_SESSION['categoria_actual'],
            $_SESSION['preguntas_categoria']
        );

        \Redirect::to('/home/show');
    }
}
