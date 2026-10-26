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
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
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
