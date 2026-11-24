<?php
return static function (string $content, array $layoutData = []) {
$assetBase = htmlspecialchars(rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/index.php")), "/"), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preguntados</title>
    <link rel="icon" type="image/svg+xml" href="<?= $assetBase ?>/public/img/personajes/cine.svg">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= $assetBase ?>/public/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $assetBase ?>/app/css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>" rel="stylesheet">
    <?php if (!empty($layoutData['tieneEstilosSugerencias'])): ?>
        <link href="<?= $assetBase ?>/public/css/sugerencias.css?v=<?= filemtime(__DIR__ . '/../../public/css/sugerencias.css') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (!empty($layoutData['enAdministracion'])): ?>
        <link href="<?= $assetBase ?>/app/css/admin-panel.css?v=<?= filemtime(__DIR__ . '/../css/admin-panel.css') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (!empty($layoutData['tieneNavegacionLateral'])): ?>
        <script src="<?= $assetBase ?>/public/js/navegacion-lateral.js" defer></script>
    <?php endif; ?>
</head>
<?php
$clasesCuerpo = [];
if (!empty($layoutData['enAdministracion'])) {
    $clasesCuerpo[] = 'admin-panel';
}
?>
<body<?= $clasesCuerpo ? ' class="' . implode(' ', $clasesCuerpo) . '"' : '' ?>>
<?php if (isset($_SESSION['usuario_id'])): ?>
<?php
if (!isset($_SESSION['presence_token'])) {
    $_SESSION['presence_token'] = bin2hex(random_bytes(32));
}
?>
<script src="<?= $assetBase ?>/public/js/presencia.js?v=1"
        data-url="<?= $assetBase ?>/presence/ping"
        data-token="<?= htmlspecialchars($_SESSION['presence_token'], ENT_QUOTES, 'UTF-8') ?>" defer></script>
<?php endif; ?>
<?php require __DIR__ . '/header.php'; ?>
<?= $content ?>
<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>

<?php
};
