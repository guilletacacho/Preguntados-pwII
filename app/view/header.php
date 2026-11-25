<?php
$escape = static function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
$basePath = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/index.php")), "/");
$siteUrl = $escape($basePath);
$loggedIn = isset($_SESSION['usuario_id']);
$fotoUrl = (string) ($_SESSION['foto_url'] ?? null);
$currentPage = $_GET['controller'] ?? 'login';
?>
<header class="site-header">
    <nav class="container site-nav" aria-label="Navegación principal">
        <a class="site-brand" href="<?= $siteUrl ?>/home/show">
            <span>Preguntados</span>
            <span class="cinta" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span></span>
        </a>
        <div class="nav-actions">
            <?php if ($loggedIn): ?>
                <details class="profile-menu">
                    <summary>
                        <span class="profile-avatar" aria-hidden="true"><?php if ($fotoUrl !== ''): ?><img src="<?= $siteUrl ?>/app/img-foto-perfil/<?= $escape($fotoUrl) ?>" alt=""><?php else: ?><?= $escape(mb_strtoupper(mb_substr($_SESSION['usuario'] ?? 'U', 0, 1))) ?><?php endif; ?></span>
                        <span class="profile-label"><?= $escape($_SESSION['usuario'] ?? 'Jugador') ?></span>
                        <span class="menu-chevron" aria-hidden="true"></span>
                    </summary>
                    <div class="profile-dropdown">
                        <?php if (!empty($layoutData['puedeModerar'])): ?>
                            <a class="moderador-menu-link" href="<?= $siteUrl ?>/moderador"<?= !empty($layoutData['enModeracion']) ? ' aria-current="location"' : '' ?>>
                                <span>Moderación</span>
                                <span class="moderador-menu-badge"><?= (int) ($layoutData['reportesPendientes'] ?? 0) ?></span>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($layoutData['esAdmin'])): ?>
                            <a class="moderador-menu-link" href="<?= $siteUrl ?>/admin"<?= !empty($layoutData['enAdministracion']) ? ' aria-current="location"' : '' ?>>
                                <span>Administración</span>
                            </a>
                        <?php endif; ?>
                        <a href="<?= $siteUrl ?>/perfil">
                        Mi perfil
                        </a>
                        <a class="logout-link"
                        href="<?= $siteUrl ?>/login/logout">
                        Salir
                        </a>
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
