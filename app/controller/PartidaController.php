<?php

namespace app\controller;

use Dificultad;

class PartidaController{

    private const TIEMPO_LIMITE = 20;
    private const TIEMPO_GRACIA = 1;

    /** Parametro que lleva el sello de la pestaña. */
    private const SELLO_PESTANA = 'pesta';
    /** Pestañas ya vistas que se recuerdan para poder bloquearlas. */
    private const MAX_PESTANAS = 5;

    /** Segundos sin actividad antes de que otra sesión pueda tomar la cuenta. */
    private const SESION_VIGENCIA = 45;

    private const MOTIVOS_REPORTE = [
        'respuesta_incorrecta' => 'La respuesta marcada como correcta es incorrecta',
        'varias_validas' => 'Hay más de una respuesta válida',
        'confusa' => 'La pregunta o las opciones son confusas',
        'desactualizada' => 'La información está desactualizada',
        'inapropiada' => 'El contenido es inapropiado',
        'otro' => 'Otro motivo',
    ];

    private $model;
    private $render;

    public function __construct($model,$render)
    {
        $this->model = $model;
        $this->render = $render;
    }

    // ─────────────────────────────────────────────────────────────
    // INICIO
    // ─────────────────────────────────────────────────────────────

    /**
     * Jugar inicia una partida nueva. Los turnos de una partida en curso
     * siguen por la ruleta y el resultado.
     */
    public function iniciar(){

        $userId = $this->requiereUsuario();

        $enCurso = $this->model->obtenerPartidaEnCurso($userId);
        if ($enCurso !== null) {
            $this->model->abandonoPartida((int) $enCurso['id']);
        }

        $partida = $this->model->iniciarPartida($userId);
        $gameId = (int) $partida['id'];
        unset($_SESSION['turno'], $_SESSION['resultado'], $_SESSION['preguntas_partida'], $_SESSION['partida_salida']);
        $_SESSION['game_id'] = $gameId;
        $_SESSION['puntos'] = (int) $partida['total_points'];
        $_SESSION['correctas'] = (int) $partida['correct_answers'];
        $_SESSION['pregunta_numero'] = $this->model->ultimoTurno($gameId) + 1;

        //redirige a show() el que tiene preguntas y respuestas
        \Redirect::to('/Preguntados/partida/show');
    }

    // ─────────────────────────────────────────────────────────────
    // RULETA
    // ─────────────────────────────────────────────────────────────

    public function show(){

        $this->requiereUsuario();

        // Si quedó un resultado sin continuar, lo mostramos primero.
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

        // La categoría y la pregunta se eligen una sola vez por turno.
        if (!isset($_SESSION['turno'])) {
            $pregunta = $this->elegirPregunta($disponibles, $gameId);

            if ($pregunta === null) {
                // Sin preguntas cargadas: volvemos al home, nunca "se terminó".
                \Redirect::to('/Preguntados/home/show');
            }

            $this->nuevaRonda($pregunta);
        }

        $turno = $_SESSION['turno'];

        foreach ($disponibles as &$categoria) {
            $categoria['seleccionada'] = $categoria['name'] === $turno['categoria'];
        }
        unset($categoria);

        $this->renderizarPartida('ruleta', [
            'categorias' => $disponibles,
            'puntos' => (int) ($_SESSION['puntos'] ?? 0),
            'rondaNumero' => (int) $turno['numero'],
            'ruletaGirada' => !empty($turno['girada']),
            'categoria' => $turno['categoria'],
            'categoriaIcono' => $turno['icono'] ?? 'personajes/ciencia.svg',
        ]);
    }

    /**
     * Marca la ruleta como "ya girada" para que no se repita la animación.
     * Lo llama el JS al terminar el giro. También sirve sin JS.
     */
    public function girar(){

        $this->requiereUsuario();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_SESSION['turno'])) {
            $_SESSION['turno']['girada'] = true;
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

        // El reloj arranca al abrir la pregunta y conserva el inicio del turno.
        // Llegar a la pregunta también da por completado el giro de la ruleta.
        if (empty($turno['girada'])) {
            $turno['girada'] = true;
            $_SESSION['turno']['girada'] = true;
        }

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
        $this->renderizarPartida('partidaJuego', [
            'resultadoMostrado' => false,
            'categoria' => $turno['categoria'],
            'categoriaIcono' => $turno['icono'] ?? 'personajes/ciencia.svg',
            'pregunta' => $turno['pregunta'],
            'imagen' => $turno['imagen'] ?? null,
            'hayImagen' => !empty($turno['imagen']),
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
    // RESULTADO
    // ─────────────────────────────────────────────────────────────

    private function datosReportes($todos){
        if (empty($_SESSION['reporte_csrf'])) {
            $_SESSION['reporte_csrf'] = bin2hex(random_bytes(32));
        }
        $reportadas = array_map('intval', array_column($this->model->preguntasReportadas((int) $_SESSION['usuario_id']), 'question_id'));
        $datos = [];
        foreach ($_SESSION['preguntas_partida'] ?? [] as $numero => $fila) {
            if ((int) $fila['game_id'] !== (int) ($_SESSION['game_id'] ?? 0)
                || (!$todos && (int) $numero !== (int) $_SESSION['resultado']['preguntaNumero'])) {
                continue;
            }
            $resultado = $fila['resultado'];
            $motivos = [];
            foreach (self::MOTIVOS_REPORTE as $codigo => $etiqueta) {
                $motivos[] = ['codigo' => $codigo, 'etiqueta' => $etiqueta];
            }
            $datos[] = [
                'id' => $numero, 'numero' => $numero,
                'pregunta' => $resultado['pregunta'], 'respuestas' => $resultado['respuestas'],
                'reportada' => in_array((int) $fila['question_id'], $reportadas, true), 'motivos' => $motivos,
                'actual' => (int) $numero === (int) $_SESSION['resultado']['preguntaNumero'],
                'csrf' => $_SESSION['reporte_csrf'],
            ];
        }
        return $datos;
    }

    public function reportar(){
        $userId = $this->requiereUsuario();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || empty($_SESSION['resultado'])) {
            \Redirect::to('/Preguntados/partida/show');
        }
        $token = $_POST['csrf'] ?? null;
        $motivos = $_POST['motivos'] ?? [];
        $comentario = $_POST['comentario'] ?? '';
        $id = filter_var($_POST['turn_number'] ?? null, FILTER_VALIDATE_INT);
        $valido = is_string($token) && isset($_SESSION['reporte_csrf']) && hash_equals($_SESSION['reporte_csrf'], $token)
            && ($_POST['confirmar_finalizacion'] ?? null) === '1'
            && is_array($motivos) && count($motivos) > 0 && count($motivos) <= count(self::MOTIVOS_REPORTE)
            && is_string($comentario) && mb_strlen($comentario) <= 2000;
        foreach (is_array($motivos) ? $motivos : [] as $motivo) {
            if (!is_string($motivo) || !isset(self::MOTIVOS_REPORTE[$motivo])) {
                $valido = false;
            }
        }
        if (is_array($motivos) && in_array('otro', $motivos, true) && (!is_string($comentario) || trim($comentario) === '')) {
            $valido = false;
        }
        $permitidos = array_column($this->datosReportes(!empty($_SESSION['resultado']['gameOver'])), 'id');
        if (!$valido || !$id || !in_array($id, array_map('intval', $permitidos), true)) {
            $_SESSION['resultado']['reporteError'] = 'Confirmá el envío y la finalización de la partida. Seleccioná un motivo. Para Otro, escribí un comentario. Máximo: 2000 caracteres.';
            unset($_SESSION['resultado']['reporteMensaje']);
        } else {
            try {
                $pregunta = $_SESSION['preguntas_partida'][$id];
                $guardado = $this->model->reportarPregunta(
                    (int) $_SESSION['game_id'], (int) $pregunta['question_id'], $id, $userId,
                    $pregunta['resultado'], array_values(array_unique($motivos)), trim($comentario)
                );
                $_SESSION['resultado']['gameOver'] = true;
                $_SESSION['resultado']['volverRuleta'] = false;
                $_SESSION['resultado']['finalizadaPorReporte'] = true;
                unset($_SESSION['turno']);
                $_SESSION['resultado']['reporteMensaje'] = $guardado > 0
                    ? 'Gracias. Revisaremos la pregunta. La partida finalizó y conservás todos los puntos ganados.'
                    : 'Ya reportaste esta pregunta. La partida está finalizada y conservás todos los puntos ganados.';
                unset($_SESSION['resultado']['reporteError']);
            } catch (\PDOException $exception) {
                \Log::warning('No se pudo guardar el reporte: ' . $exception->getCode());
                $_SESSION['resultado']['reporteError'] = 'No pudimos guardar el reporte. Intentá nuevamente.';
                unset($_SESSION['resultado']['reporteMensaje']);
            }
        }
        \Redirect::to('/Preguntados/partida/resultado');
    }

    public function resultado(){

        $this->requiereUsuario();

        if (empty($_SESSION['resultado'])) {
            \Redirect::to('/Preguntados/partida/show');
        }

        $datos = $_SESSION['resultado'];
        $datos['reportes'] = $this->datosReportes(!empty($datos['gameOver']));
        $this->renderizarPartida('partidaJuego', $datos);
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
            // La partida terminó: la cuenta queda libre para empezar otra,
            // incluso desde una sesión distinta.
            $this->cerrarPartidaActual();
            \Redirect::to('/Preguntados/home/show');
        }

        \Redirect::to('/Preguntados/partida/show');
    }

    /**
     * Latido de la pestaña que está jugando. Mantiene vivo el bloqueo por
     * cuenta y, si otra sesión tomó el control, devuelve 403 para que el JS
     * muestre el cartel correspondiente.
     */
    public function latido(){

        if ($this->tokenPartidaValido()) {
            $this->requiereUsuario();
        }
        http_response_code(204);
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

        $this->cerrarPartidaActual();

        $destino = \Request::get('destino','/home/show');
        \Redirect::to('/Preguntados' . $this->destinoSeguro($destino));
    }

    public function salir(){

        $this->cerrarPartidaActual();

        \Redirect::to('/Preguntados/home/show');
    }

    public function avisoSalida(){
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            return;
        }

        $duena = $_SESSION['pestana'] ?? '';
        if ($this->tokenPartidaValido() && ($duena === '' || $duena === $this->selloPestana())) {
            $this->cerrarPartidaActual();
        }

        http_response_code(204);
    }

    public function cerrarPartidaActual(){
        $gameId = (int) ($_SESSION['game_id'] ?? 0);
        if ($gameId > 0) {
            $this->model->abandonoPartida($gameId);
        }
        $this->liberarSesionUnicaActual();
        $this->limpiarSesionPartida();
    }

    private function renderizarPartida($vista, array $datos){
        if (empty($datos['gameOver']) && !empty($_SESSION['game_id'])) {
            $_SESSION['partida_salida'] ??= bin2hex(random_bytes(32));
            $datos['partidaSalida'] = $_SESSION['partida_salida'];
        }
        $this->render->renderiza($vista, $datos);
    }

    private function tokenPartidaValido(){
        $token = \Request::post('token');
        return is_string($token) && isset($_SESSION['partida_salida'])
            && hash_equals($_SESSION['partida_salida'], $token);
    }

    // ─────────────────────────────────────────────────────────────
    // INTERNOS
    // ─────────────────────────────────────────────────────────────

    private function requiereUsuario(){

        $userId = isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : 0;

        if ($userId <= 0) {
            \Redirect::toIndex();
        }

        // Toda página de partida pasa por aquí: la pestaña que no tiene el
        // control queda bloqueada antes de leer o modificar nada.
        $this->controlarPestana();

        // Además de la pestaña, la cuenta entera queda tomada por una sola
        // sesión. Esto cubre el caso de una ventana de incógnito u otro
        // dispositivo, que tiene otra cookie PHP y no comparte $_SESSION.
        $this->verificarSesionUnica($userId);

        return $userId;
    }

    /**
     * Clave opaca de esta sesión PHP. No se expone al navegador: solo se
     * guarda en la base para distinguir sesiones de la misma cuenta.
     */
    private function claveSesionUnica(){

        if (empty($_SESSION['sesion_unica'])) {
            $_SESSION['sesion_unica'] = bin2hex(random_bytes(16));
        }

        return (string) $_SESSION['sesion_unica'];
    }

    /**
     * Reclama la cuenta para esta sesión. Si otra sesión la tiene tomada y
     * sigue activa, muestra el cartel y no deja tocar la partida.
     */
    private function verificarSesionUnica($userId){

        $clave = $this->claveSesionUnica();

        if ($this->model->reclamarSesionUnica($userId, $clave, self::SESION_VIGENCIA)) {
            return;
        }

        // Los latidos automáticos no necesitan HTML: el JS recibe 403 y
        // recarga para mostrar el cartel en pantalla.
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            http_response_code(403);
            exit;
        }

        $this->render->renderiza('sesionActiva');
        exit;
    }

    /**
     * Suelta la cuenta solo si esta sesión es la que la tenía tomada.
     */
    private function liberarSesionUnicaActual(){

        $userId = (int) ($_SESSION['usuario_id'] ?? 0);
        $sessionKey = (string) ($_SESSION['sesion_unica'] ?? '');

        if ($userId > 0 && $sessionKey !== '') {
            $this->model->liberarSesionUnica($userId, $sessionKey);
        }
    }

    /**
     * Juega una sola pestaña: la última que abrió el juego. Una pestaña nueva
     * toma el control y la anterior queda con la sesión cerrada en su próximo
     * clic: no puede recuperar el control. El estado de la partida nunca se
     * duplica: lo decide el servidor.
     *
     * El sello lo agrega public/js/partida-navegacion.js a cada enlace y
     * formulario de partida. Sin JavaScript no llega sello, así que no hay
     * forma de distinguir las pestañas y no se bloquea nada.
     */
    private function controlarPestana(){

        $sello = $this->selloPestana();
        $duena = isset($_SESSION['pestana']) ? (string) $_SESSION['pestana'] : '';

        if ($sello === '' || $sello === $duena) {
            return;
        }

        // Sello desconocido: es una pestaña nueva y pasa a mandar.
        if (!in_array($sello, $_SESSION['pestana_previas'] ?? [], true)) {
            $this->tomarControl($sello);
            return;
        }

        // Sello conocido que ya no manda: es una pestaña vieja. No recupera
        // el control: queda con la sesión cerrada.
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            http_response_code(403);
            exit;
        }

        $this->render->renderiza('sesionCerrada');
        exit;
    }

    /**
     * Sello de la pestaña que pide la página; '' si no trae uno válido.
     * Son 16 caracteres hexadecimales, los genera la propia pestaña.
     */
    private function selloPestana(){

        $sello = (string) ($_GET[self::SELLO_PESTANA] ?? $_POST[self::SELLO_PESTANA] ?? '');

        return (strlen($sello) === 16 && ctype_xdigit($sello)) ? $sello : '';
    }

    /**
     * Le da el control a $sello y anota a quién se lo quitó, para que esa
     * pestaña quede con la sesión cerrada en vez de recuperarlo sin avisar.
     */
    private function tomarControl($sello){

        $duena = isset($_SESSION['pestana']) ? (string) $_SESSION['pestana'] : '';

        if ($duena !== '' && $duena !== $sello) {
            $previas = $_SESSION['pestana_previas'] ?? [];
            // Se recuerdan solo las últimas MAX_PESTANAS: la lista no crece nunca.
            $_SESSION['pestana_previas'] = array_slice(array_merge($previas, [$duena]), -self::MAX_PESTANAS);
        }

        $_SESSION['pestana'] = $sello;
    }

    /**
     * Devuelve la partida EN_CURSO que pertenece a esta sesión. Si la
     * sesión perdió esa partida, no la adopta: la marca ABANDONADA y
     * manda al home para que el jugador empiece una nueva con "Jugar".
     */
    private function partidaActiva(){

        $userId = $this->requiereUsuario();

        $gameId = (int) ($_SESSION['game_id'] ?? 0);
        $partida = $gameId > 0 ? $this->model->obtenerPartida($gameId) : null;

        if ($partida === null
            || (int) $partida['user_id'] !== $userId
            || $partida['status'] !== 'EN_CURSO') {

            // Quedó una partida EN_CURSO de una sesión anterior:
            // no se retoma con otra pregunta, se abandona.
            $enCurso = $this->model->obtenerPartidaEnCurso($userId);
            if ($enCurso !== null) {
                $this->model->abandonoPartida((int) $enCurso['id']);
            }

            \Redirect::to('/Preguntados/home/show');
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
     * Elige una categoría al azar entre las que tienen preguntas de la
     * dificultad que le corresponde al jugador y devuelve una pregunta de
     * ESA categoría y dificultad.
     *
     * Si ya salieron todas las de esa categoría+dificultad, se repiten
     * mezcladas. Si el banco todavía no tiene preguntas de esa dificultad
     * (al principio todas son medias), usa la dificultad más cercana para
     * que la partida nunca se trabe.
     */
    private function elegirPregunta(array $disponibles, $gameId){

        $nivel = $this->nivelJugador();
        $nivelJugador = (int) $nivel['nivel'];
        $dificultad = Dificultad::dificultadDeNivel($nivelJugador);

        $categorias = $disponibles;
        shuffle($categorias);

        // 1) Categorías que tengan preguntas de la dificultad exacta.
        foreach ($categorias as $categoria) {
            $pregunta = $this->model->obtenerPreguntaDeDificultad(
                (int) $categoria['id'],
                (int) $gameId,
                $dificultad
            );

            if ($pregunta !== null) {
                return $pregunta;
            }
        }

        // 2) Ninguna categoría tiene preguntas de esa dificultad todavía.
        foreach ($categorias as $categoria) {
            $pregunta = $this->model->obtenerPreguntaParaJugador(
                (int) $categoria['id'],
                (int) $gameId,
                $nivelJugador
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
            'icono' => $pregunta['categoria_icono'],
            'color' => $pregunta['categoria_color'],
            'pregunta' => $pregunta['pregunta'],
            'imagen' => $pregunta['image_url'] ?? null,
            'dificultad' => (int) $pregunta['dificultad'],
            'inicio' => null,
            'girada' => false,
            'opciones' => $this->prepararOpciones($respuestas),
        ];

        unset($_SESSION['resultado']);

        // Registrar una sola entrega por turno mantiene las estadísticas correctas.
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

        // Los totales se recalculan desde game_answers: en el navegador no se
        // acumula nada, así que un for de reenvíos no puede inflar el puntaje.
        $totales = $this->model->actualizarPartida($gameId);
        $_SESSION['puntos'] = $totales['puntos'];
        $_SESSION['correctas'] = $totales['correctas'];

        $gameOver = !$esCorrecta;
        $correcta = $gameOver ? $this->model->obtenerRespuestaCorrecta($questionId) : null;

        if ($gameOver) {
            $this->model->finalizarPartida($gameId);
            unset($_SESSION['pestana'], $_SESSION['pestana_previas']);
        } else {
            $_SESSION['pregunta_numero'] = (int) $turno['numero'] + 1;
        }

        $_SESSION['resultado'] = [
            'resultadoMostrado' => true,
            'preguntaNumero' => (int) $turno['numero'],
            'categoria' => $turno['categoria'],
            'categoriaIcono' => $turno['icono'] ?? 'personajes/ciencia.svg',
            'pregunta' => $turno['pregunta'],
            'imagen' => $turno['imagen'] ?? null,
            'hayImagen' => !empty($turno['imagen']),
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
        $this->guardarResultadoReportable($gameId, $questionId);

        unset($_SESSION['turno']);

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
            unset($_SESSION['pestana'], $_SESSION['pestana_previas']);
            $this->model->registrarRespuestaEstadistica($questionId, false);
        }

        $correcta = $this->model->obtenerRespuestaCorrecta($questionId);

        $_SESSION['resultado'] = [
            'resultadoMostrado' => true,
            'preguntaNumero' => (int) $turno['numero'],
            'categoria' => $turno['categoria'],
            'categoriaIcono' => $turno['icono'] ?? 'personajes/ciencia.svg',
            'pregunta' => $turno['pregunta'],
            'imagen' => $turno['imagen'] ?? null,
            'hayImagen' => !empty($turno['imagen']),
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

        $this->guardarResultadoReportable($gameId, $questionId);

        unset($_SESSION['turno']);

        \Redirect::to('/Preguntados/partida/resultado');
    }

    private function guardarResultadoReportable($gameId, $questionId){
        $numero = (int) $_SESSION['resultado']['preguntaNumero'];
        $_SESSION['preguntas_partida'][$numero] = [
            'game_id' => $gameId,
            'question_id' => $questionId,
            'resultado' => $_SESSION['resultado'],
        ];
    }

    private function tiempoTranscurrido(array $turno){

        return microtime(true) - (float) ($turno['inicio'] ?? microtime(true));
    }

    private function tiempoRestante(array $turno){

        return max(0, (int) ceil(self::TIEMPO_LIMITE - $this->tiempoTranscurrido($turno)));
    }

    private function limpiarSesionPartida(){

        unset(
            $_SESSION['game_id'],
            $_SESSION['puntos'],
            $_SESSION['correctas'],
            $_SESSION['pregunta_numero'],
            $_SESSION['turno'],
            $_SESSION['resultado'],
            $_SESSION['preguntas_partida'],
            $_SESSION['pestana'],
            $_SESSION['pestana_previas'],
            $_SESSION['partida_reanudada'],
            $_SESSION['partida_salida'],
            // claves del flujo viejo, por si quedaron dando vueltas
            $_SESSION['pregunta_pendiente'],
            $_SESSION['opciones_pendientes'],
            $_SESSION['pregunta_inicio'],
            $_SESSION['categoria_actual'],
            $_SESSION['preguntas_categoria']
        );
    }
}
