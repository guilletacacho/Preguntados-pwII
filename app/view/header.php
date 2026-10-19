<?php
$escape = static function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
$basePath = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/index.php")), "/");
$siteUrl = $escape($basePath);
$loggedIn = isset($_SESSION['usuario_id']);
$currentPage = $_GET['controller'] ?? 'login';
?>
<header class="site-header">
    <nav class="container site-nav" aria-label="Navegación principal">
        <a class="site-brand" href="<?= $siteUrl ?>/home">
            <span class="brand-mark" aria-hidden="true">?</span>
            <span>Preguntados<span class="brand-caption">Un mundo de preguntas</span></span>
        </a>
        <div class="nav-actions">
            <a class="site-nav-link <?= $currentPage === 'home' ? 'is-active' : '' ?>" href="<?= $siteUrl ?>/home" <?= $currentPage === 'home' ? 'aria-current="page"' : '' ?>>Inicio</a>
            <?php if ($loggedIn): ?>
                <details class="profile-menu">
                    <summary><span class="profile-avatar" aria-hidden="true"><?= $escape(strtoupper(substr($_SESSION['usuario'] ?? 'U', 0, 1))) ?></span><span class="profile-label"><?= $escape($_SESSION['usuario'] ?? 'Jugador') ?></span><span class="menu-chevron" aria-hidden="true"></span></summary>
                    <div class="profile-dropdown">
                        <div class="profile-greeting"><strong><?= $escape($_SESSION['usuario'] ?? 'Jugador') ?></strong><span>Qu&eacute; bueno verte por ac&aacute;</span></div>
                        <a href="<?= $siteUrl ?>/perfil">Ver mi perfil <span aria-hidden="true">&#8599;</span></a>
                    </div>
                </details>
                <a class="site-nav-link logout-link" href="<?= $siteUrl ?>/login/logout">Salir</a>
            <?php else: ?>
                <a class="site-nav-link <?= $currentPage === 'login' ? 'is-active' : '' ?>" href="<?= $siteUrl ?>/login" <?= $currentPage === 'login' ? 'aria-current="page"' : '' ?>>Iniciar sesión</a>
                <a class="site-nav-link signup-link" href="<?= $siteUrl ?>/registro" <?= $currentPage === 'registro' ? 'aria-current="page"' : '' ?>>Registrarse</a>
            <?php endif; ?>
        </div>
    </nav>
</header>
