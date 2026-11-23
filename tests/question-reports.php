<?php

require_once __DIR__ . '/../app/model/PartidaModel.php';
require_once __DIR__ . '/../app/model/ModeradorModel.php';
require_once __DIR__ . '/../app/model/SugerenciaModel.php';
require_once __DIR__ . '/../app/controller/PartidaController.php';
require_once __DIR__ . '/../app/controller/ModeradorReportesController.php';
require_once __DIR__ . '/../helper/ModeradorAccess.php';
require_once __DIR__ . '/../helper/Request.php';
require_once __DIR__ . '/../vendor/mustache/Mustache/Autoloader.php';
Mustache_Autoloader::register(__DIR__ . '/../vendor/mustache');

class Redirect {
    public static function to($url) { throw new RuntimeException('redirect:' . $url); }
}
class ReportTestDatabase {
    public function __construct(public PDO $pdo) {}
    public function execute($sql, $params = []) {
        $statement = $this->pdo->prepare($sql);
        try {
            $statement->execute($params);
        } catch (PDOException $e) {
            if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' && str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                $e->errorInfo[1] = 1062;
            }
            throw $e;
        }
        return $statement->rowCount();
    }
    public function queryAll($sql, $params = []) {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
    public function queryOne($sql, $params = []) {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
    public function beginTransaction() { return $this->pdo->beginTransaction(); }
    public function commit() { return $this->pdo->commit(); }
    public function rollBack() { return $this->pdo->rollBack(); }
    public function inTransaction() { return $this->pdo->inTransaction(); }
}
class ReportTestModel extends app\model\PartidaModel {
    public function reclamarSesionUnica($userId, $key, $seconds) { return true; }
}
class ReportTestRender {
    public $view;
    public $data;
    public function renderiza($view, $data = []) { $this->view = $view; $this->data = $data; }
}
function check($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->sqliteCreateFunction('NOW', static fn() => date('Y-m-d H:i:s'));
$pdo->exec("CREATE TABLE game (id INTEGER PRIMARY KEY, user_id INT, total_points INT, status TEXT DEFAULT 'EN_CURSO', finished_at TEXT);
    CREATE TABLE category (id INTEGER PRIMARY KEY, name TEXT);
    CREATE TABLE questions (id INTEGER PRIMARY KEY, question TEXT, category_id INT, difficulty_level INT DEFAULT 2, points INT DEFAULT 20, image_url TEXT);
    CREATE TABLE usuarios (Id INTEGER PRIMARY KEY, Usuario TEXT, rol TEXT);
    CREATE TABLE response (id INTEGER PRIMARY KEY, question_id INT, response TEXT, is_correct INT);
    CREATE TABLE game_answers (id INTEGER PRIMARY KEY, response_id INT);
    CREATE TABLE question_suggestions (
        id INTEGER PRIMARY KEY, user_id INT, category_id INT, published_question_id INT,
        question_text TEXT, status TEXT, created_at TEXT, image_url TEXT
    );
    CREATE TABLE question_reports (
        id INTEGER PRIMARY KEY, user_id INT, question_id INT, game_id INT, turn_number INT,
        question_snapshot TEXT, reasons TEXT, comment TEXT, status TEXT DEFAULT 'PENDIENTE',
        created_at TEXT DEFAULT CURRENT_TIMESTAMP, reviewed_by INT, reviewed_at TEXT,
        resolution_notes TEXT, UNIQUE(user_id,question_id)
    );
    INSERT INTO category (id,name) VALUES (1,'Ciencia'),(2,'Historia');
    INSERT INTO questions (id,question,category_id,difficulty_level,points) VALUES (99,'¿Pregunta original?',1,3,20),(100,'¿Pregunta con tiempo agotado?',1,2,20);
    INSERT INTO usuarios (Id,Usuario,rol) VALUES (1,'jugador','JUGADOR'),(2,'otro','JUGADOR'),(3,'moderador','MODERADOR');
    INSERT INTO game (id,user_id,total_points) VALUES (1,1,20), (2,2,0), (3,1,0);
    INSERT INTO response (id,question_id,response,is_correct) VALUES
        (11,99,'Respuesta A',1),(12,99,'Respuesta B',0),(13,99,'Respuesta C',0),(14,99,'Respuesta D',0),
        (21,100,'Otra A',0),(22,100,'Otra B',1),(23,100,'Otra C',0),(24,100,'Otra D',0);
    INSERT INTO game_answers (id,response_id) VALUES (1,11)");
$model = new ReportTestModel(new ReportTestDatabase($pdo));
$controller = new app\controller\PartidaController($model, null);
$result = ['preguntaNumero' => 1, 'pregunta' => '¿Pregunta original?', 'categoria' => 'Ciencia', 'respuestas' => [['letra' => 'A', 'texto' => '<script>alert(1)</script>', 'correcta' => true, 'seleccionada' => true]], 'puntosGanados' => 20, 'gameOver' => false];
$_SESSION = ['usuario_id' => 1, 'game_id' => 1, 'resultado' => $result, 'reporte_csrf' => 'test-token'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$save = new ReflectionMethod($controller, 'guardarResultadoReportable');
$save->invoke($controller, 1, 99);
$data = new ReflectionMethod($controller, 'datosReportes');
check(count($data->invoke($controller, false)) === 1, 'Debe mostrar el turno actual');
check((int) $pdo->query('SELECT COUNT(*) FROM question_reports')->fetchColumn() === 0, 'No debe guardar nada hasta reportar');
$send = static function ($values) use ($controller) {
    $_POST = array_replace(['csrf' => 'test-token', 'confirmar_finalizacion' => '1', 'turn_number' => 1, 'motivos' => ['confusa'], 'comentario' => ''], $values);
    try { $controller->reportar(); } catch (RuntimeException $e) {
        if (!str_starts_with($e->getMessage(), 'redirect:')) { throw $e; }
    }
};
foreach ([['confirmar_finalizacion' => null], ['confirmar_finalizacion' => ['1']], ['csrf' => 'invalid'], ['motivos' => []], ['motivos' => 'otro'], ['motivos' => [['otro']]], ['motivos' => ['inexistente']], ['motivos' => ['otro'], 'comentario' => '  '], ['comentario' => str_repeat('á', 2001)], ['turn_number' => 999]] as $invalid) {
    $send($invalid);
    check(isset($_SESSION['resultado']['reporteError']), 'Debe rechazar datos inválidos');
    check((int) $pdo->query('SELECT COUNT(*) FROM question_reports')->fetchColumn() === 0, 'Datos inválidos no deben persistirse');
}
$pdo->exec("CREATE TRIGGER fallar_finalizacion BEFORE UPDATE OF status ON game BEGIN SELECT RAISE(ABORT, 'fallo simulado'); END");
try {
    $model->reportarPregunta(1, 99, 1, 1, $result, ['confusa'], '');
    check(false, 'Debe propagar el error de finalización');
} catch (PDOException $e) {
    check(str_contains($e->getMessage(), 'fallo simulado'), 'Debe fallar al finalizar');
}
check((int) $pdo->query('SELECT COUNT(*) FROM question_reports')->fetchColumn() === 0, 'Si falla la finalización debe revertir el reporte');
check($pdo->query('SELECT status FROM game WHERE id=1')->fetchColumn() === 'EN_CURSO', 'Si falla la operaci?n debe mantener la partida en curso');
$pdo->exec('DROP TRIGGER fallar_finalizacion');
$_SESSION['resultado']['volverRuleta'] = true;
$_SESSION['turno'] = ['numero' => 2];
$send(['motivos' => ['confusa', 'otro', 'confusa'], 'comentario' => '  Revisar opciones  ', 'question_id' => 12345, 'question_snapshot' => 'alterado']);
check($_SESSION['resultado']['gameOver'] && !$_SESSION['resultado']['volverRuleta'] && !isset($_SESSION['turno']), 'Reportar debe cerrar la partida en la sesión e impedir continuar');
check($pdo->query('SELECT status FROM game WHERE id=1')->fetchColumn() === 'FINALIZADA', 'Reportar debe finalizar la partida en la base');
check($pdo->query('SELECT finished_at FROM game WHERE id=1')->fetchColumn() !== null, 'Debe guardar la fecha de finalización');
$report = $pdo->query('SELECT * FROM question_reports')->fetch(PDO::FETCH_ASSOC);
check($report['status'] === 'PENDIENTE' && $report['comment'] === 'Revisar opciones', 'Debe guardar un reporte pendiente');
check(json_decode($report['reasons'], true) === ['confusa', 'otro'], 'Debe guardar múltiples motivos sin repeticiones');
check((int) $report['question_id'] === 99 && json_decode($report['question_snapshot'], true) === $result, 'La copia y los IDs deben salir de la sesión');
$finishedAt = $pdo->query('SELECT finished_at FROM game WHERE id=1')->fetchColumn();
$send([]);
check($pdo->query('SELECT finished_at FROM game WHERE id=1')->fetchColumn() === $finishedAt, 'Reenviar no debe cambiar la fecha de finalización');
check((int) $pdo->query('SELECT COUNT(*) FROM question_reports')->fetchColumn() === 1, 'No debe duplicar reportes');
check((int) $pdo->query('SELECT total_points FROM game WHERE id=1')->fetchColumn() === 20, 'Reportar no debe alterar el puntaje');
check($model->reportarPregunta(2, 100, 1, 1, $result, ['confusa'], '') === 0, 'El modelo debe rechazar partidas ajenas');
check($model->reportarPregunta(3, 99, 1, 1, $result, ['confusa'], '') === 0, 'No debe repetir reportes entre partidas');
check($model->preguntasReportadas(2) === [], 'No debe mezclar usuarios');
$moderadorModel = new app\model\ModeradorModel(new ReportTestDatabase($pdo));
$pendientes = $moderadorModel->listarPreguntasReportadas('PENDIENTE', 'mas_viejas');
check(count($pendientes) === 1, 'El moderador debe ver el reporte pendiente');
check($pendientes[0]['question_id'] === 99 && $pendientes[0]['motivos'][0]['texto'] === 'La pregunta o las opciones son confusas', 'La bandeja debe decodificar los motivos JSON');
$detalleReportes = $moderadorModel->obtenerReportesPregunta(99);
check($detalleReportes[0]['comment'] === 'Revisar opciones', 'El detalle debe exponer el comentario nuevo');
check($detalleReportes[0]['question_snapshot']['pregunta'] === '¿Pregunta original?', 'El detalle debe decodificar la captura de la pregunta');
$pdo->exec("INSERT INTO question_reports
    (user_id,question_id,game_id,turn_number,question_snapshot,reasons,comment)
    VALUES (2,99,2,1,'{}','[\"respuesta_incorrecta\"]','La respuesta correcta debería ser B')");
$respuestasCorregidas = [
    ['id' => 11, 'response' => 'Primera opción'],
    ['id' => 12, 'response' => 'Segunda opción'],
    ['id' => 13, 'response' => 'Tercera opción'],
    ['id' => 14, 'response' => 'Cuarta opción'],
];
check($moderadorModel->corregirPregunta(
    99,
    2,
    '¿Cuál es la respuesta corregida?',
    $respuestasCorregidas,
    12,
    3,
    'Se corrigió el enunciado y la opción correcta.'
), 'El moderador debe poder corregir una pregunta');
$preguntaCorregida = $pdo->query('SELECT question, category_id, difficulty_level FROM questions WHERE id=99')->fetch(PDO::FETCH_ASSOC);
check($preguntaCorregida['question'] === '¿Cuál es la respuesta corregida?' && (int) $preguntaCorregida['category_id'] === 2, 'La corrección debe guardar enunciado y categoría');
check((int) $preguntaCorregida['difficulty_level'] === 3, 'La corrección no debe cambiar la dificultad');
check((int) $pdo->query('SELECT total_points FROM game WHERE id=1')->fetchColumn() === 20, 'La corrección no debe recalcular puntajes históricos');
$opcionesCorregidas = $pdo->query('SELECT id, response, is_correct FROM response WHERE question_id=99 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
check(count($opcionesCorregidas) === 4 && $opcionesCorregidas[1]['response'] === 'Segunda opción', 'La corrección debe actualizar las cuatro respuestas existentes');
check((int) $opcionesCorregidas[1]['is_correct'] === 1 && array_sum(array_column($opcionesCorregidas, 'is_correct')) === 1, 'Debe cambiar la respuesta correcta y dejar exactamente una');
check(array_column($opcionesCorregidas, 'id') === [11, 12, 13, 14], 'La corrección debe conservar los IDs de las respuestas');
check((int) $pdo->query('SELECT response_id FROM game_answers WHERE id=1')->fetchColumn() === 11, 'La corrección debe preservar las referencias históricas de game_answers');
$reportesCorregidos = $pdo->query('SELECT status, reviewed_by, reviewed_at, resolution_notes FROM question_reports WHERE question_id=99')->fetchAll(PDO::FETCH_ASSOC);
check(count($reportesCorregidos) === 2, 'La corrección debe resolver todos los reportes pendientes de esa pregunta');
foreach ($reportesCorregidos as $reporteCorregido) {
    check($reporteCorregido['status'] === 'RESUELTO'
        && (int) $reporteCorregido['reviewed_by'] === 3
        && $reporteCorregido['reviewed_at'] !== null
        && $reporteCorregido['resolution_notes'] === 'Se corrigió el enunciado y la opción correcta.',
        'La corrección debe registrar la resolución en cada reporte');
}
$pdo->exec("INSERT INTO question_reports
    (user_id,question_id,game_id,turn_number,question_snapshot,reasons,comment)
    VALUES (3,99,3,1,'{}','[\"confusa\"]','Reporte de prueba para validar rollback')");
$duplicadas = [
    ['id' => 11, 'response' => 'Árbol'],
    ['id' => 12, 'response' => '  árbol  '],
    ['id' => 13, 'response' => 'Tercera opción'],
    ['id' => 14, 'response' => 'Cuarta opción'],
];
try {
    $moderadorModel->corregirPregunta(99, 1, 'No debe persistir', $duplicadas, 11, 3, 'Nota de prueba');
    check(false, 'Debe rechazar respuestas duplicadas');
} catch (DomainException $e) {
    check(str_contains($e->getMessage(), 'distintas'), 'Debe informar las respuestas duplicadas');
}
$respuestaAjena = $respuestasCorregidas;
$respuestaAjena[3]['id'] = 21;
try {
    $moderadorModel->corregirPregunta(99, 1, 'No debe persistir', $respuestaAjena, 11, 3, 'Nota de prueba');
    check(false, 'Debe rechazar una respuesta de otra pregunta');
} catch (DomainException $e) {
    check(str_contains($e->getMessage(), 'pertenecen'), 'Debe informar que una respuesta es ajena');
}
$estadoTrasRollback = $pdo->query('SELECT question, category_id, difficulty_level FROM questions WHERE id=99')->fetch(PDO::FETCH_ASSOC);
check($estadoTrasRollback === $preguntaCorregida, 'Los errores de validación deben dejar la pregunta intacta');
check((int) $pdo->query("SELECT COUNT(*) FROM question_reports WHERE question_id=99 AND status='PENDIENTE'")->fetchColumn() === 1, 'Los errores de validación deben dejar el reporte pendiente');
$engine = new Mustache_Engine([
    'partials_loader' => new Mustache_Loader_FilesystemLoader(__DIR__ . '/../app/view', ['extension' => '.mustache']),
]);
$moderadorTemplate = file_get_contents(__DIR__ . '/../app/view/ModeradorReportesView.mustache');
$htmlModerador = $engine->render($moderadorTemplate, [
    'basePath' => '/Preguntados',
    'pendientes' => 1,
    'hayMensaje' => false,
    'estadoPendiente' => true,
    'estadoResuelto' => false,
    'estadoDescartado' => false,
    'ordenMasReportadas' => false,
    'ordenMasViejas' => true,
    'ordenMasNuevas' => false,
    'estado' => 'PENDIENTE',
    'orden' => 'mas_viejas',
    'cantidadPreguntas' => 1,
    'hayPreguntas' => false,
    'hayDetalle' => true,
    'csrfToken' => 'token',
    'detalle' => [
        'question_id' => 99,
        'question' => '¿Pregunta original?',
        'categoria' => 'Ciencia',
        'hayPendientes' => true,
        'hayOpciones' => false,
        'reportes' => array_replace($detalleReportes[0], ['fecha_formateada' => '15/11/2026']),
    ],
]);
check(str_contains($htmlModerador, 'Revisar opciones') && str_contains($htmlModerador, 'Captura al reportar'), 'El panel debe renderizar comentario y captura del reporte');
check(str_contains($htmlModerador, '<select id="moderador-orden" name="orden" onchange="this.form.submit()">')
    && str_contains($htmlModerador, 'value="mas_reportadas"')
    && str_contains($htmlModerador, 'value="mas_viejas" selected')
    && str_contains($htmlModerador, 'value="mas_nuevas"')
    && !str_contains($htmlModerador, '>Aplicar</button>'),
    'El selector debe aplicar el orden al cambiar y conservar la selección actual');
$renderModerador = new ReportTestRender();
$moderadorController = new app\controller\ModeradorReportesController(
    $moderadorModel,
    new app\model\SugerenciaModel(new ReportTestDatabase($pdo)),
    $renderModerador
);
$sessionJugador = $_SESSION;
$_SESSION = ['usuario_id' => 3, 'csrf_moderador' => 'moderator-token'];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['id' => '99'];
$moderadorController->editar();
check($renderModerador->view === 'ModeradorReportes'
    && $renderModerador->data['modoEditor']
    && $renderModerador->data['editorDisponible']
    && count($renderModerador->data['editorOpciones']) === 4,
    'El editor debe cargar pregunta, respuestas y contexto desde la base');
$editorContext = array_merge(['basePath' => '/Preguntados'], $renderModerador->data);
$htmlEditor = $engine->render($moderadorTemplate, $editorContext);
check(str_contains($htmlEditor, 'Corregiendo por') && str_contains($htmlEditor, 'Reporte de prueba para validar rollback'), 'El editor debe mostrar reportes pendientes y sus comentarios');
check(str_contains($htmlEditor, 'name="question"') && str_contains($htmlEditor, 'Guardar corrección y resolver reportes'), 'El editor debe enviar un formulario POST');
check(!str_contains($htmlEditor, 'name="difficulty') && !str_contains($htmlEditor, 'difficulty_level'), 'El editor no debe mostrar ni enviar la dificultad');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'csrf_token' => 'moderator-token',
    'question_id' => '99',
    'category_id' => '1',
    'question' => '¿Cuál es la respuesta corregida?',
    'responses' => [
        ['id' => '11', 'texto' => 'Repetida'],
        ['id' => '12', 'texto' => '  repetida '],
        ['id' => '13', 'texto' => 'Tercera opción'],
        ['id' => '14', 'texto' => 'Cuarta opción'],
    ],
    'correct_response_id' => '11',
    'resolution_notes' => 'Nota que debe conservarse',
];
$moderadorController->corregir();
check(str_contains($renderModerador->data['mensaje']['texto'], 'distintas')
    && $renderModerador->data['editorOpciones'][0]['response'] === 'Repetida'
    && $renderModerador->data['editorNota'] === 'Nota que debe conservarse',
    'El error de validación debe volver al formulario conservando los valores ingresados');
$_POST['responses'] = [
    ['id' => '11', 'texto' => 'Primera opción'],
    ['id' => '12', 'texto' => 'Segunda opción'],
    ['id' => '13', 'texto' => 'Tercera opción'],
    ['id' => '14', 'texto' => 'Cuarta opción'],
];
unset($_POST['correct_response_id']);
$moderadorController->corregir();
check(str_contains($renderModerador->data['mensaje']['texto'], 'marcar exactamente una'), 'El controlador debe rechazar el formulario sin respuesta correcta');
$_SESSION = $sessionJugador;
check($moderadorModel->actualizarReportesPendientes(99, 'RESUELTO', 3, 'Validación de resolución existente'), 'La resolución sin edición debe seguir disponible');
try {
    $moderadorModel->corregirPregunta(
        99,
        1,
        'Este cambio debe revertirse',
        [
            ['id' => 11, 'response' => 'Esta respuesta debe revertirse'],
            ['id' => 12, 'response' => 'Segunda opción'],
            ['id' => 13, 'response' => 'Tercera opción'],
            ['id' => 14, 'response' => 'Cuarta opción'],
        ],
        11,
        3,
        'No quedan reportes pendientes'
    );
    check(false, 'Debe revertir la corrección si ya no quedan reportes pendientes');
} catch (DomainException $e) {
    check(str_contains($e->getMessage(), 'pendientes'), 'Debe informar que ya no hay reportes pendientes');
}
check($pdo->query('SELECT question FROM questions WHERE id=99')->fetchColumn() === $preguntaCorregida['question'], 'La transacción debe revertir la pregunta si no puede resolver reportes');
check($pdo->query('SELECT response FROM response WHERE id=11')->fetchColumn() === 'Primera opción', 'La transacción debe revertir las respuestas si no puede resolver reportes');
$pdo->exec("INSERT INTO question_reports
    (user_id,question_id,game_id,turn_number,question_snapshot,reasons,comment)
    VALUES (3,100,3,2,'{}','[\"respuesta_incorrecta\"]','La respuesta correcta está mal marcada')");
$_SESSION = ['usuario_id' => 3, 'csrf_moderador' => 'moderator-token'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'csrf_token' => 'moderator-token',
    'question_id' => '100',
    'category_id' => '1',
    'question' => 'Pregunta corregida desde el controlador',
    'responses' => [
        ['id' => '21', 'texto' => 'Opción 1 corregida'],
        ['id' => '22', 'texto' => 'Opción 2 corregida'],
        ['id' => '23', 'texto' => 'Opción 3 corregida'],
        ['id' => '24', 'texto' => 'Opción 4 corregida'],
    ],
    'correct_response_id' => '23',
    'resolution_notes' => 'Se corrigió desde el formulario.',
];
try {
    $moderadorController->corregir();
    check(false, 'La corrección exitosa debe redirigir a la bandeja');
} catch (RuntimeException $e) {
    check(str_starts_with($e->getMessage(), 'redirect:/Preguntados/moderador/reportes?estado=RESUELTO'), 'La corrección exitosa debe redirigir a reportes resueltos');
}
$correccionControlador = $pdo->query('SELECT question, difficulty_level FROM questions WHERE id=100')->fetch(PDO::FETCH_ASSOC);
$reporteControlador = $pdo->query('SELECT status, reviewed_by, reviewed_at, resolution_notes FROM question_reports WHERE question_id=100')->fetch(PDO::FETCH_ASSOC);
check($correccionControlador['question'] === 'Pregunta corregida desde el controlador'
    && (int) $correccionControlador['difficulty_level'] === 2,
    'El POST válido debe corregir la pregunta sin modificar la dificultad');
check($reporteControlador['status'] === 'RESUELTO'
    && (int) $reporteControlador['reviewed_by'] === 3
    && $reporteControlador['reviewed_at'] !== null
    && $reporteControlador['resolution_notes'] === 'Se corrigió desde el formulario.',
    'El POST válido debe resolver reportes y registrar la auditoría');
$_SESSION = $sessionJugador;
$_SESSION['resultado'] = array_replace($result, ['preguntaNumero' => 2, 'tiempoAgotado' => true, 'gameOver' => true, 'puntosGanados' => 0]);
$save->invoke($controller, 1, 100);
check(count($data->invoke($controller, true)) === 2, 'El resumen debe incluir todos los turnos');
check($data->invoke($controller, true)[0]['reportada'], 'Debe mostrar preguntas ya reportadas');
$_SESSION['resultado']['gameOver'] = false;
$send(['turn_number' => 1]);
check(isset($_SESSION['resultado']['reporteError']), 'Durante la partida solo permite el turno actual');
$_SESSION['resultado']['gameOver'] = true;
$send(['turn_number' => 2]);
check((int) $pdo->query('SELECT COUNT(*) FROM question_reports')->fetchColumn() === 5, 'Debe permitir reportar un tiempo agotado');
$pendientes = $moderadorModel->listarPreguntasReportadas('PENDIENTE', 'mas_viejas');
check(count($pendientes) === 1 && $pendientes[0]['question_id'] === 100, 'La bandeja debe filtrar los reportes que siguen pendientes');
check($moderadorModel->actualizarReportesPendientes(100, 'DESCARTADO', 3, 'Reporte revisado'), 'El moderador debe poder descartar reportes');
$descartado = $pdo->query("SELECT status, reviewed_by, reviewed_at, resolution_notes FROM question_reports WHERE question_id=100 AND status='DESCARTADO'")->fetch(PDO::FETCH_ASSOC);
check($descartado['status'] === 'DESCARTADO' && (int) $descartado['reviewed_by'] === 3 && $descartado['reviewed_at'] !== null && $descartado['resolution_notes'] === 'Reporte revisado', 'Descartar debe guardar el estado y los campos de revisión');
$_SESSION['preguntas_partida'][3] = ['game_id' => 2, 'question_id' => 101, 'resultado' => $result];
$send(['turn_number' => 3]);
check((int) $pdo->query('SELECT COUNT(*) FROM question_reports')->fetchColumn() === 5, 'El controlador debe rechazar turnos de otra partida');
$template = file_get_contents(__DIR__ . '/../app/view/partidaView.Mustache');
$html = $engine->render($template, ['resultadoMostrado' => true, 'gameOver' => true, 'reportes' => $data->invoke($controller, true)]);
check(!str_contains($html, '<script>alert(1)</script>') && str_contains($html, '&lt;script&gt;'), 'La vista debe escapar el contenido');
check(str_contains($html, 'Ya reportaste') && str_contains($html, 'data-reporte-abrir'), 'Debe mostrar el modal y el estado');
$html = $engine->render($template, ['resultadoMostrado' => true, 'reportes' => [['id' => 1, 'reportada' => false]]]);
check(str_contains($html, 'name="confirmar_finalizacion"') && str_contains($html, 'Reportar y finalizar'), 'Debe explicar y exigir la confirmación');
$html = $engine->render($template, ['resultadoMostrado' => true, 'esCorrecta' => true, 'gameOver' => true, 'finalizadaPorReporte' => true, 'volverRuleta' => false]);
check(!str_contains($html, 'Volver a la ruleta') && str_contains($html, 'Volver al lobby') && str_contains($html, 'Jugar de nuevo'), 'Tras reportar un acierto debe ofrecer salir o iniciar otra partida');
$html = $engine->render($template, ['resultadoMostrado' => false]);
check(!str_contains($html, '/partida/reportar') && !str_contains($html, '/reportarPregunta/crear'), 'No debe permitir reportar antes de responder');
check(!str_contains($html, 'csrf_token') && !str_contains($html, 'name="question_id"'), 'La vista no debe conservar los campos del formulario anterior');
echo "OK: reportes de jugador, CSRF, historial y vista; bandeja del moderador, motivos JSON y resolución/descarte.\n";
