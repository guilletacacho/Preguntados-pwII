<?php
return static function (string $content) {
$assetBase = htmlspecialchars(rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/index.php")), "/"), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preguntados</title>
    <link href="<?= $assetBase ?>/public/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $assetBase ?>/app/css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>
<?= $content ?>
<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>

<?php
};
