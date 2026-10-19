<?php
$siteUrl = htmlspecialchars(rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/index.php")), "/"), ENT_QUOTES, 'UTF-8');
?>
<footer class="site-footer">
    <div class="container footer-content">
        <div><a class="footer-brand" href="<?= $siteUrl ?>/home">Preguntados<span aria-hidden="true"> ✦</span></a><p>Siempre hay algo nuevo por descubrir.</p></div>
        <div class="footer-meta"><span>Hecho con curiosidad · Grupo n°7</span><span>&copy; <?= date('Y') ?> Preguntados</span></div>
    </div>
</footer>
<script>
    document.addEventListener('click', function (event) {
        document.querySelectorAll('.profile-menu[open]').forEach(function (menu) {
            if (!menu.contains(event.target)) menu.open = false;
        });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.profile-menu[open]').forEach(function (menu) {
                menu.open = false;
                menu.querySelector('summary').focus();
            });
        }
    });
</script>
