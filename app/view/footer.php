<?php $enSesion = isset($_SESSION['usuario_id']); ?>
<footer class="site-footer">
    <div class="container footer-barra">
        <a class="site-brand" href="<?= $assetBase ?>/home/show">
            <span>Preguntados</span>
            <span class="cinta" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span></span>
        </a>

        <?php if ($enSesion): ?>
        <!-- Todas estas secciones piden sesión: sin loguearse llevarían de vuelta al login. -->
        <nav class="footer-links" aria-label="Pie de página">
            <a href="<?= $assetBase ?>/home/show">Inicio</a>
            <a href="<?= $assetBase ?>/partida/iniciar">Jugar</a>
            <a href="<?= $assetBase ?>/ranking">Rankings</a>
            <a href="<?= $assetBase ?>/home/preguntasFrecuentes">Preguntas frecuentes</a>
            <a href="<?= $assetBase ?>/home/terminos">Términos y condiciones</a>
            <a href="<?= $assetBase ?>/home/mostrarSugerencias">Sugerir una pregunta</a>
        </nav>
        <?php endif; ?>

        <div class="footer-firma">
            <span>&copy; <?= date('Y') ?> Grupo 7</span>
            <a class="footer-github" href="https://github.com/guilletacacho/Preguntados-pwII" target="_blank" rel="noopener noreferrer" aria-label="Ver Preguntados en GitHub">
                <svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82a7.6 7.6 0 0 1 2-.27c.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.01 8.01 0 0 0 16 8c0-4.42-3.58-8-8-8Z"/></svg>
            </a>
        </div>
    </div>
</footer>
