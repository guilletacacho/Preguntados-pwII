<footer class="site-footer">
    <div class="cinta" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span></div>
    <div class="container footer-content">
        <span>&copy; <?= date('Y') ?> Preguntados</span>
        <span>Grupo 7</span>
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
