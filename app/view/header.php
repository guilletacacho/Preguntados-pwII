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
            <span>Preguntados</span>
            <span class="cinta" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span></span>
        </a>
        <div class="nav-actions">
            <?php if ($loggedIn): ?>
                <a class="site-nav-link <?= $currentPage === 'ranking' ? 'is-active' : '' ?>" href="<?= $siteUrl ?>/ranking" <?= $currentPage === 'ranking' ? 'aria-current="page"' : '' ?>>Ranking</a>
                <details class="profile-menu">
                    <summary><span class="profile-avatar" aria-hidden="true"><?= $escape(strtoupper(substr($_SESSION['usuario'] ?? 'U', 0, 1))) ?></span><span class="profile-label"><?= $escape($_SESSION['usuario'] ?? 'Jugador') ?></span><span class="menu-chevron" aria-hidden="true"></span></summary>
                    <div class="profile-dropdown">
                        <a href="<?= $siteUrl ?>/perfil">Mi perfil</a>
                        <a class="logout-link" href="<?= $siteUrl ?>/login/logout">Salir</a>
                    </div>
                </details>
            <?php elseif ($currentPage === 'registro'): ?>
                <a class="site-nav-link nav-cta-secundario" href="<?= $siteUrl ?>/login">Iniciar sesión</a>
            <?php else: ?>
                <a class="site-nav-link signup-link" href="<?= $siteUrl ?>/registro">Registrarse</a>
            <?php endif; ?>
        </div>
    </nav>
</header>
