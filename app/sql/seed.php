<?php
// Uso: php app/sql/seed.php. Carga aditiva; no modifica cuentas existentes.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$root = dirname(__DIR__, 2);
$config = parse_ini_file($root . '/config/config.ini');
$db = new PDO(
    'mysql:host=' . $config['db_host'] . ';port=' . $config['db_port'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
    $config['db_user'], $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$run = static function ($sql, $params = []) use ($db) {
    $statement = $db->prepare($sql);
    $statement->execute($params);
    return $statement;
};

// Primera opción correcta. Cuatro preguntas por categoría, de dificultad variable.
$catalog = [
    'Geografía' => [
        ['¿Cuál es la capital de Argentina?', 'Buenos Aires', 'Rosario', 'Córdoba', 'Mendoza'],
        ['¿En qué continente está Egipto?', 'África', 'Europa', 'Oceanía', 'América'],
        ['¿Cuál es el océano más grande?', 'Pacífico', 'Atlántico', 'Índico', 'Ártico'],
        ['¿Cuál es la capital de Japón?', 'Tokio', 'Kioto', 'Osaka', 'Seúl'],
    ],
    'Historia' => [
        ['¿En qué año se declaró la independencia argentina?', '1816', '1810', '1820', '1806'],
        ['¿Qué civilización construyó Machu Picchu?', 'Inca', 'Romana', 'Egipcia', 'Vikinga'],
        ['¿En qué año terminó la Segunda Guerra Mundial?', '1945', '1939', '1918', '1950'],
        ['¿Quién cruzó los Andes con su ejército libertador?', 'José de San Martín', 'Manuel Belgrano', 'Mariano Moreno', 'Juan Manuel de Rosas'],
    ],
    'Ciencia' => [
        ['¿Cuál es el símbolo químico del agua?', 'H2O', 'CO2', 'O2', 'NaCl'],
        ['¿Qué planeta es conocido como el planeta rojo?', 'Marte', 'Venus', 'Júpiter', 'Saturno'],
        ['¿Qué órgano bombea la sangre?', 'Corazón', 'Pulmón', 'Hígado', 'Riñón'],
        ['¿Cuál es la unidad de resistencia eléctrica?', 'Ohmio', 'Voltio', 'Amperio', 'Vatio'],
    ],
    'Arte' => [
        ['¿Quién pintó la Mona Lisa?', 'Leonardo da Vinci', 'Pablo Picasso', 'Claude Monet', 'Salvador Dalí'],
        ['¿Quién escribió Don Quijote de la Mancha?', 'Miguel de Cervantes', 'Jorge Luis Borges', 'Julio Cortázar', 'Federico García Lorca'],
        ['¿Cuántas cuerdas tiene una guitarra clásica?', 'Seis', 'Cuatro', 'Cinco', 'Ocho'],
        ['¿Qué artista pintó La noche estrellada?', 'Vincent van Gogh', 'Diego Velázquez', 'Frida Kahlo', 'Edvard Munch'],
    ],
    'Deportes' => [
        ['¿Cuántos jugadores tiene un equipo de fútbol en cancha?', 'Once', 'Diez', 'Nueve', 'Doce'],
        ['¿En qué deporte se utiliza una raqueta y un volante?', 'Bádminton', 'Tenis', 'Pádel', 'Squash'],
        ['¿Cuántos aros tiene el símbolo olímpico?', 'Cinco', 'Cuatro', 'Seis', 'Siete'],
        ['¿Cuántos puntos vale un tiro libre en básquet?', 'Uno', 'Dos', 'Tres', 'Cuatro'],
    ],
    'Cine y TV' => [
        ['¿Cómo se llama el vaquero de Toy Story?', 'Woody', 'Buzz', 'Rex', 'Andy'],
        ['¿Quién dirigió Jurassic Park (1993)?', 'Steven Spielberg', 'James Cameron', 'George Lucas', 'Tim Burton'],
        ['¿Qué familia vive en Springfield en una serie animada?', 'Los Simpson', 'Los Picapiedra', 'Los Supersónicos', 'Los Addams'],
        ['¿Qué personaje dice «Yo soy tu padre» en Star Wars?', 'Darth Vader', 'Yoda', 'Obi-Wan Kenobi', 'Han Solo'],
    ],
];
$names = ['Lucía Pérez', 'Mateo García', 'Sofía López', 'Benjamín Torres', 'Valentina Díaz', 'Santiago Ruiz', 'Martina Silva', 'Tomás Romero', 'Camila Castro', 'Nicolás Fernández', 'Julieta Gómez', 'Joaquín Acosta', 'Catalina Molina', 'Agustín Herrera', 'Delfina Medina', 'Bruno Rojas', 'Paula Vega', 'Lucas Costa'];
$countries = ['Argentina', 'Uruguay', 'Chile', 'Perú', 'Colombia', 'México'];
$now = new DateTimeImmutable('now', new DateTimeZone('America/Argentina/Buenos_Aires'));
$format = static function ($date) { return $date->format('Y-m-d H:i:s'); };
$created = 0;
$skipped = 0;

$db->beginTransaction();
try {
    $questions = [];
    foreach ($catalog as $category => $items) {
        $id = $run('SELECT id FROM category WHERE name = ?', [$category])->fetchColumn();
        if ($id === false) {
            $run('INSERT INTO category (name) VALUES (?)', [$category]);
            $id = $db->lastInsertId();
        }
        foreach ($items as $index => $item) {
            $questionId = $run('SELECT id FROM questions WHERE category_id = ? AND question = ?', [$id, $item[0]])->fetchColumn();
            if ($questionId !== false) {
                // No alterar preguntas que ya existan; usarlas solamente si tienen opciones válidas.
                $options = $run('SELECT id, is_correct FROM response WHERE question_id = ? ORDER BY id', [$questionId])->fetchAll(PDO::FETCH_ASSOC);
                $correct = array_values(array_filter($options, static function ($r) { return (bool) $r['is_correct']; }));
                $wrong = array_values(array_filter($options, static function ($r) { return !$r['is_correct']; }));
                if (count($correct) !== 1 || count($wrong) < 1) {
                    throw new RuntimeException('Pregunta existente sin opciones válidas: ' . $item[0]);
                }
                $points = (int) $run('SELECT points FROM questions WHERE id = ?', [$questionId])->fetchColumn();
                $questions[] = [$questionId, $points, $correct[0]['id'], $wrong[0]['id']];
                continue;
            }
            $difficulty = $index % 3 + 1;
            $points = $difficulty * 10;
            $run('INSERT INTO questions (category_id, question, difficulty_level, points) VALUES (?, ?, ?, ?)', [$id, $item[0], $difficulty, $points]);
            $questionId = $db->lastInsertId();
            $optionIds = [];
            foreach (array_slice($item, 1) as $option => $text) {
                $run('INSERT INTO response (question_id, response, is_correct) VALUES (?, ?, ?)', [$questionId, $text, (int) ($option === 0)]);
                $optionIds[] = $db->lastInsertId();
            }
            $questions[] = [$questionId, $points, $optionIds[0], $optionIds[1]];
        }
    }

    foreach ($names as $index => $name) {
        $number = $index + 1;
        $username = sprintf('demo%02d', $number);
        $email = $username . '@example.com';
        $existing = $run('SELECT Id, Usuario, Email FROM usuarios WHERE Usuario = ? OR Email = ?', [$username, $email])->fetchAll(PDO::FETCH_ASSOC);
        if ($existing) {
            if (count($existing) !== 1 || $existing[0]['Usuario'] !== $username || $existing[0]['Email'] !== $email) {
                throw new RuntimeException('Colisión con una cuenta existente: ' . $username);
            }
            $skipped++;
            continue;
        }
        $verified = $number <= 15;
        $run('INSERT INTO usuarios (Nombre, Anio_nacimiento, Sexo, Pais, Usuario, Email, Contrasena, Verificado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
            $name, 1985 + $index, $index % 2 === 0 ? 'Femenino' : 'Masculino',
            $countries[$index % count($countries)], $username, $email, md5('Demo1234!'), (int) $verified,
        ]);
        $userId = $db->lastInsertId();
        $created++;
        // Tokens sintéticos: usados, pendientes vigentes y pendientes vencidos.
        $expires = $now->modify($verified || $number === 18 ? '-2 days' : '+1 day');
        $token = hash('sha256', 'preguntados-demo-token-' . $username);
        $run('INSERT INTO verificacion_tokens (UsuarioId, Token, Expira, Usado) VALUES (?, ?, ?, ?)', [$userId, $token, $format($expires), (int) $verified]);

        if ($verified) {
            // Diez partidas finalizadas, una abandonada y una en curso por usuario.
            foreach ([0, 1, 2, 3, 7, 12, 20, 35, 65, 100, 4, 0] as $gameIndex => $days) {
                $status = $gameIndex === 11 ? 'EN_CURSO' : ($gameIndex === 10 ? 'ABANDONADA' : 'FINALIZADA');
                $start = $now->modify('-' . $days . ' days')->modify('-' . (20 + $number * 8 + $gameIndex) . ' minutes');
                $answerCount = $status === 'FINALIZADA' ? 12 : ($status === 'ABANDONADA' ? 4 : 2);
                $finish = $status === 'EN_CURSO' ? null : $format($start->modify('+' . ($answerCount * 20 + 10) . ' seconds'));
                $run('INSERT INTO game (user_id, started_at, finished_at, status) VALUES (?, ?, ?, ?)', [$userId, $format($start), $finish, $status]);
                $gameId = $db->lastInsertId();
                $score = 0;
                $correctCount = 0;
                for ($answer = 0; $answer < $answerCount; $answer++) {
                    $question = $questions[($answer + $gameIndex * 3 + $index) % count($questions)];
                    $correct = (($answer * 3 + $gameIndex + $index) % 10) < (3 + $index % 7);
                    $earned = $correct ? $question[1] : 0;
                    $seconds = 3 + (($answer * 7 + $index + $gameIndex) % 160) / 10;
                    $run('INSERT INTO game_answers (game_id, question_id, response_id, response_time_seconds, earned_points, answered_at) VALUES (?, ?, ?, ?, ?, ?)', [
                        $gameId, $question[0], $correct ? $question[2] : $question[3], $seconds, $earned,
                        $format($start->modify('+' . (($answer + 1) * 20) . ' seconds')),
                    ]);
                    $score += $earned;
                    $correctCount += (int) $correct;
                }
                $run('UPDATE game SET total_points = ?, correct_answers = ? WHERE id = ?', [$score, $correctCount, $gameId]);
            }
        }
        // Estadísticas de partidas finalizadas. Victoria demo: al menos 8 aciertos de 12.
        $run("INSERT INTO statistics (user_id, played_games, wins, avg_response_time, historical_points)
            SELECT ?, COUNT(*), COALESCE(SUM(correct_answers >= 8), 0),
            (SELECT AVG(a.response_time_seconds) FROM game_answers a JOIN game g ON g.id = a.game_id WHERE g.user_id = ? AND g.status = 'FINALIZADA'),
            COALESCE(SUM(total_points), 0) FROM game WHERE user_id = ? AND status = 'FINALIZADA'", [$userId, $userId, $userId]);
    }
    $db->commit();
    echo "Carga completa. Usuarios creados: $created. Usuarios demo existentes omitidos: $skipped." . PHP_EOL;
} catch (Throwable $error) {
    $db->rollBack();
    fwrite(STDERR, 'Carga revertida: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
