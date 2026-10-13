<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preguntados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/Preguntados/app/css/style.css" rel="stylesheet">
</head>
<body>
<header class="site-header">
    <nav class="navbar navbar-expand">
        <div class="container">
            <a class="navbar-brand home-link" href="/Preguntados/home">Inicio</a>
            <?php if (isset($_SESSION['usuario_id'])) : ?>
                <a class="nav-link logout-link" href="/Preguntados/login/logout">Cerrar sesión</a>
            <?php endif; ?>
        </div>
    </nav>
</header>
