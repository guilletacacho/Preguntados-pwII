<?php

namespace app\controller;

use Dificultad;

class PartidaController{

    private const TIEMPO_LIMITE = 20;
    private const TIEMPO_GRACIA = 2;

    /** Categoría => archivo de imagen en public/img/personajes. */
    private const SLUGS = [
        'Geografía' => 'geografia',
        'Historia'  => 'historia',
        'Ciencia'   => 'ciencia',
        'Arte'      => 'arte',
        'Deportes'  => 'deporte',
        'Deporte'   => 'deporte',
        'Cine y TV' => 'cine',
        'Cine'      => 'cine',
    ];

    private $model;
    private $render;

    public function __construct($model,$render)
    {
        $this->model = $model;
        $this->render = $render;
    }

    // ─────────────────────────────────────────────────────────────
    // INICIO / REANUDACIÓN
    // ─────────────────────────────────────────────────────────────

    /**
     * Inicia una partida o reanuda la que ya está EN_CURSO.
     * Nunca crea una segunda partida activa para el mismo usuario:
     * el índice uk_game_en_curso de la base lo impide.
     */
    public function iniciar(){

        $userId = $this->requiereUsuario();

        $partida = $this->model->iniciarPartida($userId);
        $gameId = (int) $partida['id'];

        $mismoJuego = (int) ($_SESSION['game_id'] ?? 0) === $gameId;

        // Si había un resultado sin leer, no lo pisamos.
        if ($mismoJuego && !empty($_SESSION['resultado'])) {
            \Redirect::to('/Preguntados/partida/resultado');
        }

        if (!$mismoJuego) {
            unset($_SESSION['turno'], $_SESSION['resultado'], $_SESSION['ruleta_girada']);
        }

        $_SESSION['game_id'] = $gameId;
        $_SESSION['puntos'] = (int) $partida['total_points'];
        $_SESSION['correctas'] = (int) $partida['correct_answers'];

        if (!$mismoJuego || !isset($_SESSION['turno'])) {
            $_SESSION['pregunta_numero'] = $this->model->ultimoTurno($gameId) + 1;
        }

        $_SESSION['partida_reanudada'] = empty($partida['nueva']);

        //redirige a show() el que tiene preguntas y respuestas
        \Redirect::to('/Preguntados/partida/show');
    }

    // ─────────────────────────────────────────────────────────────
    // RULETA
    // ─────────────────────────────────────────────────────────────

    public function show(){

        $this->requiereUsuario();

        // Si quedó un resultado sin continuar, lo mostramos (F5 incluido).
        if (!empty($_SESSION['resultado'])) {
            \Redirect::to('/Preguntados/partida/resultado');
        }

        $partida = $this->partidaActiva();
        $gameId = (int) $partida['id'];

        $categorias = $this->model->obtenerCategorias($gameId);

        // Cualquier categoría con preguntas puede salir en la ruleta.
        // La pregunta SIEMPRE es de la categoría que salió: si ya se
        // respondieron todas las de esa categoría, se repite una de ahí.
        // Nunca se cambia a otra categoría.
        $disponibles = array_values(array_filter($categorias, static function ($categoria) {
            return (int) $categoria['total'] > 0;
        }));

        if (!$disponibles) {
            // No hay preguntas cargadas en el sistema: no es un fin de partida.
            \Redirect::to('/Preguntados/home/show');
        }

        // La categoría y la pregunta se eligen UNA sola vez por turno.
        // Así, al apretar F5, no cambia ni la categoría ni la pregunta.
        if (!isset($_SESSION['turno'])) {
            $pregunta = $this->elegirPregunta($disponibles, $gameId);

            if ($pregunta === null) {
                // Sin preguntas cargadas: volvemos al home, nunca "se terminó".
                \Redirect::to('/Preguntados/home/show');
            }

            $this->nuevaRonda($pregunta);
        }

        $turno = $_SESSION['turno'];

        foreach ($categorias as &$categoria) {
            $categoria['slug'] = $this->slugCategoria($categoria['name']);
            $categoria['seleccionada'] = $categoria['name'] === $turno['categoria'];
        }
        unset($categoria);

        $nivel = $this->nivelJugador();

        $this->render->renderiza('ruleta', [
            'categorias' => $categorias,
            'puntos' => (int) ($_SESSION['puntos'] ?? 0),
            'preguntaNumero' => (int) $turno['numero'],
            'ruletaGirada' => !empty($_SESSION['ruleta_girada']),
            'partidaReanudada' => !empty($_SESSION['partida_reanudada']),
            'nivelJugador' => $nivel['etiqueta'],
            'dificultadPregunta' => Dificultad::etiqueta((int) $turno['dificultad']),
        ]);

        // El aviso de partida reanudada se muestra una sola vez.
        unset($_SESSION['partida_reanudada']);
    }

    /**
     * Marca la ruleta como "ya girada" para que F5 no repita la animación.
     * Lo llama el JS al terminar el giro. También sirve sin JS.
     */
    public function girar(){

        $this->requiereUsuario();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $_SESSION['ruleta_girada'] = true;
        }

        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            http_response_code(204);
            return;
        }

        \Redirect::to('/Preguntados/partida/show');
    }

    // ─────────────────────────────────────────────────────────────
    // PREGUNTA
    // ─────────────────────────────────────────────────────────────

    public function pregunta(){

        $this->requiereUsuario();

        if (!empty($_SESSION['resultado'])) {
            \Redirect::to('/Preguntados/partida/resultado');
        }

        if (!isset($_SESSION['turno'])) {
            \Redirect::to('/Preguntados/partida/show');
        }

        $turno = $_SESSION['turno'];

        // El reloj arranca cuando el jugador ve la pregunta, no mientras
        // gira la ruleta. Si recarga (F5), se mantiene el mismo inicio.
        if (empty($turno['inicio'])) {
            $turno['inicio'] = microtime(true);
            $_SESSION['turno']['inicio'] = $turno['inicio'];
        }

        // Si el tiempo ya venció (por ejemplo, dejó la pestaña abierta),
        // el servidor resuelve el turno como agotado.
        if ($this->tiempoRestante($turno) <= 0) {
            $this->procesarTiempoAgotado($turno);
            return;
        }

        $nivel = $this->nivelJugador();

        $this->render->renderiza('partida', [
            'resultadoMostrado' => false,
            'categoria' => $turno['categoria'],
            'categoriaSlug' => $turno['slug'],
            'pregunta' => $turno['pregunta'],
            // Solo se manda letra + texto + un token opaco.
            // El id real de la respuesta y cuál es la correcta quedan en el servidor.
            'respuestas' => $this->opcionesParaVista($turno),
            'preguntaNumero' => (int) $turno['numero'],
            'puntos' => (int) ($_SESSION['puntos'] ?? 0),
            'tiempoLimite' => self::TIEMPO_LIMITE,
            'tiempoRestante' => $this->tiempoRestante($turno),
            'dificultad' => Dificultad::etiqueta((int) $turno['dificultad']),
            'nivelJugador' => $nivel['etiqueta'],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // RESPUESTA
    // ─────────────────────────────────────────────────────────────

    public function responderPregunta(){

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            \Redirect::to('/Preguntados/partida/show');
        }

        $this->requiereUsuario();

        if (!empty($_SESSION['resultado'])) {
            \Redirect::to('/Preguntados/partida/resultado');
        }

        if (!isset($_SESSION['turno'])) {
            \Redirect::to('/Preguntados/partida/show');
        }

        $turno = $_SESSION['turno'];
        $token = (string) \Request::post('opcion', '');

        // El cliente solo puede elegir uno de los tokens que el servidor
        // generó para ESTA pregunta. No se acepta question_id ni turn_number
        // enviados por el navegador.
        $elegida = null;
        foreach ($turno['opciones'] as $opcion) {
            if ($token !== '' && hash_equals($opcion['token'], $token)) {
                $elegida = $opcion;
                break;
            }
        }

        if ($elegida === null) {
            \Redirect::to('/Preguntados/partida/pregunta');
        }

        $transcurrido = $this->tiempoTranscurrido($turno);

        // Contestó tarde: vale como tiempo agotado, no como acierto.
        if ($transcurrido > self::TIEMPO_LIMITE + self::TIEMPO_GRACIA) {
            $this->procesarTiempoAgotado($turno);
            return;
        }

        $this->procesarRespuesta($turno, $elegida, min($transcurrido, self::TIEMPO_LIMITE));
    }

    public function tiempoAgotado(){

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            \Redirect::to('/Preguntados/partida/show');
        }

        $this->requiereUsuario();

        if (!isset($_SESSION['turno']) || !empty($_SESSION['resultado'])) {
            \Redirect::to('/Preguntados/partida/show');
        }

        $turno = $_SESSION['turno'];

        // Solo se acepta si el tiempo realmente se agotó.
        if ($this->tiempoTranscurrido($turno) < self::TIEMPO_LIMITE - 1) {
            \Redirect::to('/Preguntados/partida/pregunta');
        }

        $this->procesarTiempoAgotado($turno);
    }

    // ─────────────────────────────────────────────────────────────
    // RESULTADO (GET: sobrevive F5 y al botón "atrás")
    // ─────────────────────────────────────────────────────────────

    public function resultado(){

        $this->requiereUsuario();

        if (empty($_SESSION['resultado'])) {
            \Redirect::to('/Preguntados/partida/show');
        }

        $this->render->renderiza('partida', $_SESSION['resultado']);
    }

    /**
     * El jugador ya leyó el resultado: se limpia y sigue la partida.
     */
    public function continuar(){

        $this->requiereUsuario();

        $resultado = $_SESSION['resultado'] ?? null;
        unset($_SESSION['resultado']);

        if ($resultado === null) {
            \Redirect::to('/Preguntados/partida/show');
        }

        if (!empty($resultado['gameOver'])) {
            unset($_SESSION['game_id']);
            \Redirect::to('/Preguntados/home/show');
        }

        \Redirect::to('/Preguntados/partida/show');
    }

    // ─────────────────────────────────────────────────────────────
    // SALIR
    // ─────────────────────────────────────────────────────────────

    private function destinoSeguro($destino){
        $destino = (string) $destino;
        if ($destino === '' || $destino[0] !== '/' || strpos($destino, '//') === 0 || strpos($destino, '\\') !== false) {
            return '/home/show';
        }
        return $destino;
    }

    public function abandonar(){

        if(isset($_SESSION['game_id']) && (int) $_SESSION['game_id'] > 0){
            $this->model->abandonoPartida((int) $_SESSION['game_id']);
        }

        $this->limpiarSesionPartida();

        $destino = \Request::get('destino','/home/show');
        \Redirect::to('/Preguntados' . $this->destinoSeguro($destino));
    }

    public function salir(){

        $this->limpiarSesionPartida();

        \Redirect::to('/Preguntados/home/show');
    }

    // ─────────────────────────────────────────────────────────────
    // INTERNOS
    // ─────────────────────────────────────────────────────────────

    private function requiereUsuario(){

        $userId = isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : 0;

        if ($userId <= 0) {
            \Redirect::toIndex();
        }

        return $userId;
    }

    /**
     * Devuelve la partida EN_CURSO del usuario, adoptándola en la sesión
     * si hace falta. Si no hay ninguna, manda al home.
     */
    private function partidaActiva(){

        $userId = $this->requiereUsuario();

        $gameId = (int) ($_SESSION['game_id'] ?? 0);
        $partida = $gameId > 0 ? $this->model->obtenerPartida($gameId) : null;

        if ($partida === null
            || (int) $partida['user_id'] !== $userId
            || $partida['status'] !== 'EN_CURSO') {
            $partida = $this->model->obtenerPartidaEnCurso($userId);
            $gameId = $partida !== null ? (int) $partida['id'] : 0;
        }

        if ($partida === null) {
            \Redirect::to('/Preguntados/home/show');
        }

        if ((int) ($_SESSION['game_id'] ?? 0) !== $gameId) {
            // Otra pestaña / otra sesión había arrancado esta partida:
            // se retoma desde el estado guardado en la base.
            unset($_SESSION['turno'], $_SESSION['resultado'], $_SESSION['ruleta_girada']);
            $_SESSION['game_id'] = $gameId;
            $_SESSION['pregunta_numero'] = $this->model->ultimoTurno($gameId) + 1;
        }

        // Los contadores siempre salen de la base, no del navegador.
        $_SESSION['puntos'] = (int) $partida['total_points'];
        $_SESSION['correctas'] = (int) $partida['correct_answers'];

        return $partida;
    }

    private function nivelJugador(){

        $userId = (int) ($_SESSION['usuario_id'] ?? 0);

        return $this->model->obtenerEstadisticasJugador($userId);
    }

    /**
     * Elige una categoría al azar entre las que tienen preguntas y devuelve
     * una pregunta de ESA categoría. Si ya salieron todas las de esa
     * categoría, se repite una de la misma; nunca se pasa a otra.
     */
    private function elegirPregunta(array $disponibles, $gameId){

        $nivel = $this->nivelJugador();
        $categorias = $disponibles;
        shuffle($categorias);

        foreach ($categorias as $categoria) {
            $pregunta = $this->model->obtenerPreguntaParaJugador(
                (int) $categoria['id'],
                (int) $gameId,
                (int) $nivel['nivel']
            );

            if ($pregunta !== null) {
                return $pregunta;
            }
        }

        return null;
    }

    private function nuevaRonda(array $pregunta){

        $respuestas = $this->model->obtenerRespuestasCompletas((int) $pregunta['question_id']);

        $_SESSION['turno'] = [
            'numero' => (int) ($_SESSION['pregunta_numero'] ?? 1),
            'question_id' => (int) $pregunta['question_id'],
            'categoria' => $pregunta['categoria'],
            'slug' => $this->slugCategoria($pregunta['categoria']),
            'pregunta' => $pregunta['pregunta'],
            'dificultad' => (int) $pregunta['dificultad'],
            'inicio' => null,
            'opciones' => $this->prepararOpciones($respuestas),
        ];

        unset($_SESSION['resultado'], $_SESSION['ruleta_girada']);

        // Una sola entrega por turno: F5 no infla las estadísticas.
        $this->model->registrarEntrega((int) $pregunta['question_id']);
    }

    /**
     * Arma 4 opciones: siempre incluye la correcta. Cada opción lleva un
     * token aleatorio que es lo único que viaja al navegador.
     */
    private function prepararOpciones(array $respuestas){

        $correctas = array_values(array_filter($respuestas, static function ($respuesta) {
            return (int) $respuesta['is_correct'] === 1;
        }));
        $incorrectas = array_values(array_filter($respuestas, static function ($respuesta) {
            return (int) $respuesta['is_correct'] !== 1;
        }));

        shuffle($incorrectas);

        if (count($correctas) === 1) {
            $seleccion = array_merge([$correctas[0]], array_slice($incorrectas, 0, 3));
        } else {
            // Datos incompletos: se usan las primeras cuatro para no romper.
            $seleccion = array_slice($respuestas, 0, 4);
        }

        shuffle($seleccion);

        $letras = ['A', 'B', 'C', 'D'];
        $opciones = [];

        foreach ($seleccion as $indice => $respuesta) {
            $opciones[] = [
                'token' => bin2hex(random_bytes(12)),
                'texto' => $respuesta['response'],
                'letra' => $letras[$indice] ?? '',
                'response_id' => (int) $respuesta['id'],
            ];
        }

        return $opciones;
    }

    private function opcionesParaVista(array $turno){

        $opciones = [];

        foreach ($turno['opciones'] as $opcion) {
            $opciones[] = [
                'token' => $opcion['token'],
                'letra' => $opcion['letra'],
                'texto' => $opcion['texto'],
            ];
        }

        return $opciones;
    }

    private function opcionesParaResultado(array $turno, $tokenElegido, $correctaId, $esCorrecta){

        $opciones = [];

        foreach ($turno['opciones'] as $opcion) {
            $fueElegida = $tokenElegido !== null && hash_equals($opcion['token'], (string) $tokenElegido);
            $esLaCorrecta = $correctaId !== null && (int) $opcion['response_id'] === (int) $correctaId;

            $opciones[] = [
                'letra' => $opcion['letra'],
                'texto' => $opcion['texto'],
                'seleccionada' => $fueElegida,
                'correcta' => ($esCorrecta && $fueElegida) || $esLaCorrecta,
                'incorrecta' => !$esCorrecta && $fueElegida,
            ];
        }

        return $opciones;
    }

    private function procesarRespuesta(array $turno, array $elegida, $tiempo){

        $gameId = (int) ($_SESSION['game_id'] ?? 0);
        $questionId = (int) $turno['question_id'];
        $responseId = (int) $elegida['response_id'];

        if ($gameId <= 0) {
            \Redirect::to('/Preguntados/home/show');
        }

        $esCorrecta = $this->model->esRespuestaCorrecta($questionId, $responseId);
        $puntosGanados = $esCorrecta ? Dificultad::puntos((int) $turno['dificultad']) : 0;

        $registrada = $this->model->registrarRespuesta(
            $gameId,
            $questionId,
            $responseId,
            $puntosGanados,
            (int) $turno['numero'],
            round($tiempo, 2)
        );

        // Un reenvío no debe modificar la respuesta original ni sumar puntos.
        if ($registrada === 0) {
            unset($_SESSION['turno']);
            \Redirect::to('/Preguntados/partida/show');
        }

        $this->model->registrarRespuestaEstadistica($questionId, $esCorrecta);

        if ($esCorrecta) {
            $_SESSION['puntos'] = (int) ($_SESSION['puntos'] ?? 0) + $puntosGanados;
            $_SESSION['correctas'] = (int) ($_SESSION['correctas'] ?? 0) + 1;
        }

        $this->model->actualizarPartida(
            (int) $_SESSION['puntos'],
            (int) $_SESSION['correctas'],
            $gameId
        );

        $gameOver = !$esCorrecta;
        $correcta = $gameOver ? $this->model->obtenerRespuestaCorrecta($questionId) : null;

        if ($gameOver) {
            $this->model->finalizarPartida($gameId);
        } else {
            $_SESSION['pregunta_numero'] = (int) $turno['numero'] + 1;
        }

        $_SESSION['resultado'] = [
            'resultadoMostrado' => true,
            'preguntaNumero' => (int) $turno['numero'],
            'categoria' => $turno['categoria'],
            'categoriaSlug' => $turno['slug'],
            'pregunta' => $turno['pregunta'],
            'respuestas' => $this->opcionesParaResultado(
                $turno,
                $elegida['token'],
                $correcta['id'] ?? null,
                $esCorrecta
            ),
            'esCorrecta' => $esCorrecta,
            'tiempoAgotado' => false,
            'puntosGanados' => $puntosGanados,
            'puntos' => (int) $_SESSION['puntos'],
            'gameOver' => $gameOver,
            'volverRuleta' => !$gameOver,
            'dificultad' => Dificultad::etiqueta((int) $turno['dificultad']),
            'nivelJugador' => $this->nivelJugador()['etiqueta'],
        ];

        // El turno ya se consumió: no se puede volver a responder ni recargar.
        unset($_SESSION['turno'], $_SESSION['ruleta_girada']);

        \Redirect::to('/Preguntados/partida/resultado');
    }

    private function procesarTiempoAgotado(array $turno){

        $gameId = (int) ($_SESSION['game_id'] ?? 0);
        $questionId = (int) $turno['question_id'];

        if ($gameId <= 0) {
            \Redirect::to('/Preguntados/home/show');
        }

        if (empty($_SESSION['resultado'])) {
            $this->model->finalizarPartida($gameId);
            $this->model->registrarRespuestaEstadistica($questionId, false);
        }

        $correcta = $this->model->obtenerRespuestaCorrecta($questionId);

        $_SESSION['resultado'] = [
            'resultadoMostrado' => true,
            'preguntaNumero' => (int) $turno['numero'],
            'categoria' => $turno['categoria'],
            'categoriaSlug' => $turno['slug'],
            'pregunta' => $turno['pregunta'],
            'respuestas' => $this->opcionesParaResultado($turno, null, $correcta['id'] ?? null, false),
            'esCorrecta' => false,
            'tiempoAgotado' => true,
            'puntosGanados' => 0,
            'puntos' => (int) ($_SESSION['puntos'] ?? 0),
            'gameOver' => true,
            'volverRuleta' => false,
            'dificultad' => Dificultad::etiqueta((int) $turno['dificultad']),
            'nivelJugador' => $this->nivelJugador()['etiqueta'],
        ];

        unset($_SESSION['turno'], $_SESSION['ruleta_girada']);

        \Redirect::to('/Preguntados/partida/resultado');
    }

    private function tiempoTranscurrido(array $turno){

        return microtime(true) - (float) ($turno['inicio'] ?? microtime(true));
    }

    private function tiempoRestante(array $turno){

        return max(0, (int) ceil(self::TIEMPO_LIMITE - $this->tiempoTranscurrido($turno)));
    }

    private function slugCategoria($categoria){

        return self::SLUGS[$categoria] ?? 'ciencia';
    }

    private function limpiarSesionPartida(){

        unset(
            $_SESSION['game_id'],
            $_SESSION['puntos'],
            $_SESSION['correctas'],
            $_SESSION['pregunta_numero'],
            $_SESSION['turno'],
            $_SESSION['resultado'],
            $_SESSION['ruleta_girada'],
            $_SESSION['partida_reanudada'],
            // claves del flujo viejo, por si quedaron dando vueltas
            $_SESSION['pregunta_pendiente'],
            $_SESSION['opciones_pendientes'],
            $_SESSION['pregunta_inicio'],
            $_SESSION['categoria_actual'],
            $_SESSION['preguntas_categoria']
        );
    }
}
