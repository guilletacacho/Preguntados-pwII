<?php
require __DIR__ . '/../app/model/CuriosityModel.php';

$facts = (new ReflectionClass(\app\model\CuriosityModel::class))->getConstant('FACTS');
if (count($facts) < 112) {
    throw new RuntimeException('Expected at least 100 additions to the original 12 facts.');
}
$texts = array_column($facts, 'texto');
if (count(array_unique($texts)) !== count($texts)) {
    throw new RuntimeException('Duplicate curiosity text.');
}
$categories = ['Ciencia', 'Geografía', 'Deporte', 'Arte', 'Cine', 'Historia'];
foreach ($facts as $fact) {
    if (!in_array($fact['categoria'], $categories, true) || trim($fact['texto']) === '') {
        throw new RuntimeException('Invalid curiosity.');
    }
}
$_SESSION = [];
$model = new \app\model\CuriosityModel();
$previous = null;
for ($i = 0; $i < 2000; $i++) {
    $fact = $model->next();
    if ($fact['texto'] === $previous || !in_array($fact['color'], ['ciencia', 'geografia', 'deporte', 'arte', 'cine', 'historia'], true)) {
        throw new RuntimeException('Repeated consecutive curiosity or invalid color.');
    }
    $previous = $fact['texto'];
}
echo 'PASS: ' . count($facts) . " unique facts, six categories, 2000 selections without consecutive repeats.\n";
