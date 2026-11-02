(function () {
    var barra = document.querySelector('.partida-progreso[data-restante]');
    var form = document.getElementById('partida-tiempo-form');
    var etiqueta = document.getElementById('partida-segundos');
    if (!barra || !form) {
        return;
    }

    var limite = parseInt(barra.dataset.limite, 10) || 20;
    var restante = parseInt(barra.dataset.restante, 10);
    var fin = Date.now() + restante * 1000;
    var relleno = barra.querySelector('span');
    var detenido = false;
    var intervalo = null;

    function pintar(segundos) {
        var porcentaje = Math.max(0, Math.min(100, (segundos / limite) * 100));
        relleno.style.width = porcentaje + '%';
        etiqueta.textContent = segundos;
        barra.classList.toggle('es-urgente', segundos <= 5);
    }

    function detener() {
        detenido = true;
        clearInterval(intervalo);
    }

    function tick() {
        if (detenido) {
            return;
        }
        var segundos = Math.max(0, Math.ceil((fin - Date.now()) / 1000));
        pintar(segundos);
        if (segundos <= 0) {
            detener();
            form.submit();
        }
    }

    // Al elegir una respuesta se frena el reloj para no pisar ese envío.
    document.querySelectorAll('.partida-respuesta-boton').forEach(function (boton) {
        boton.form.addEventListener('submit', detener);
    });

    intervalo = setInterval(tick, 250);
    tick();
})();
