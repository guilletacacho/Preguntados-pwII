<?php

require_once __DIR__ . '/../app/model/PartidaModel.php';
require_once __DIR__ . '/../app/controller/PartidaController.php';
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
}
class ReportTestModel extends app\model\PartidaModel {
    public function reclamarSesionUnica($userId, $key, $seconds) { return true; }
}
function check($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TABLE game (id INTEGER PRIMARY KEY, user_id INT, total_points INT);
    CREATE TABLE question_reports (id INTEGER PRIMARY KEY, user_id INT, question_id INT, game_id INT, turn_number INT, question_snapshot TEXT, reasons TEXT, comment TEXT, status TEXT DEFAULT 'PENDIENTE', UNIQUE(user_id,question_id));
    INSERT INTO game (id,user_id,total_points) VALUES (1,1,20), (2,2,0), (3,1,0)");
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
    $_POST = array_replace(['csrf' => 'test-token', 'turn_number' => 1, 'motivos' => ['confusa'], 'comentario' => ''], $values);
    try { $controller->reportar(); } catch (RuntimeException $e) {
        if (!str_starts_with($e->getMessage(), 'redirect:')) { throw $e; }
    }
};
foreach ([['csrf' => 'invalid'], ['motivos' => []], ['motivos' => 'otro'], ['motivos' => [['otro']]], ['motivos' => ['inexistente']], ['motivos' => ['otro'], 'comentario' => '  '], ['comentario' => str_repeat('á', 2001)], ['turn_number' => 999]] as $invalid) {
    $send($invalid);
    check(isset($_SESSION['resultado']['reporteError']), 'Debe rechazar datos inválidos');
    check((int) $pdo->query('SELECT COUNT(*) FROM question_reports')->fetchColumn() === 0, 'Datos inválidos no deben persistirse');
}
$send(['motivos' => ['confusa', 'otro', 'confusa'], 'comentario' => '  Revisar opciones  ', 'question_id' => 12345, 'question_snapshot' => 'alterado']);
$report = $pdo->query('SELECT * FROM question_reports')->fetch(PDO::FETCH_ASSOC);
check($report['status'] === 'PENDIENTE' && $report['comment'] === 'Revisar opciones', 'Debe guardar un reporte pendiente');
check(json_decode($report['reasons'], true) === ['confusa', 'otro'], 'Debe guardar múltiples motivos sin repeticiones');
check((int) $report['question_id'] === 99 && json_decode($report['question_snapshot'], true) === $result, 'La copia y los IDs deben salir de la sesión');
$send([]);
check((int) $pdo->query('SELECT COUNT(*) FROM question_reports')->fetchColumn() === 1, 'No debe duplicar reportes');
check((int) $pdo->query('SELECT total_points FROM game WHERE id=1')->fetchColumn() === 20, 'Reportar no debe alterar el puntaje');
check($model->reportarPregunta(2, 100, 1, 1, $result, ['confusa'], '') === 0, 'El modelo debe rechazar partidas ajenas');
check($model->reportarPregunta(3, 99, 1, 1, $result, ['confusa'], '') === 0, 'No debe repetir reportes entre partidas');
check($model->preguntasReportadas(2) === [], 'No debe mezclar usuarios');
$_SESSION['resultado'] = array_replace($result, ['preguntaNumero' => 2, 'tiempoAgotado' => true, 'gameOver' => true, 'puntosGanados' => 0]);
$save->invoke($controller, 1, 100);
check(count($data->invoke($controller, true)) === 2, 'El resumen debe incluir todos los turnos');
check($data->invoke($controller, true)[0]['reportada'], 'Debe mostrar preguntas ya reportadas');
$_SESSION['resultado']['gameOver'] = false;
$send(['turn_number' => 1]);
check(isset($_SESSION['resultado']['reporteError']), 'Durante la partida solo permite el turno actual');
$_SESSION['resultado']['gameOver'] = true;
$send(['turn_number' => 2]);
check((int) $pdo->query('SELECT COUNT(*) FROM question_reports')->fetchColumn() === 2, 'Debe permitir reportar un tiempo agotado');
$_SESSION['preguntas_partida'][3] = ['game_id' => 2, 'question_id' => 101, 'resultado' => $result];
$send(['turn_number' => 3]);
check((int) $pdo->query('SELECT COUNT(*) FROM question_reports')->fetchColumn() === 2, 'El controlador debe rechazar turnos de otra partida');
$engine = new Mustache_Engine();
$template = file_get_contents(__DIR__ . '/../app/view/partidaView.Mustache');
$html = $engine->render($template, ['resultadoMostrado' => true, 'gameOver' => true, 'reportes' => $data->invoke($controller, true)]);
check(!str_contains($html, '<script>alert(1)</script>') && str_contains($html, '&lt;script&gt;'), 'La vista debe escapar el contenido');
check(str_contains($html, 'Ya reportaste') && str_contains($html, 'data-reporte-abrir'), 'Debe mostrar el modal y el estado');
$html = $engine->render($template, ['resultadoMostrado' => false]);
check(!str_contains($html, '/partida/reportar'), 'No debe permitir reportar antes de responder');
echo "OK: historial en sesión, copia al reportar, permisos, CSRF, validaciones, duplicados, puntaje y vista.\n";
