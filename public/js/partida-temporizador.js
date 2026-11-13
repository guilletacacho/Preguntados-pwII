(function () {
    var barra = document.querySelector('.partida-progreso[data-restante]');
    var form = document.getElementById('partida-tiempo-form');
    var etiqueta = document.getElementById('partida-segundos');
    var respuesta = document.getElementById('partida-respuesta-form');

    if (!barra || !form) {
        return;
    }

    // OJO: esto es solo la parte visual. El tiempo real lo controla el
    // servidor con la hora en que se entregó la pregunta. Aunque alguien
    // modifique estos números desde el inspector, la respuesta se valida
    // igual contra el reloj del backend.
    var limite = parseInt(barra.dataset.limite, 10) || 20;
    var restante = parseInt(barra.dataset.restante, 10);
    var fin = Date.now() + restante * 1000;
    var relleno = barra.querySelector('span');
    var detenido = false;
    var intervalo = null;

    function pintar(segundos) {
        var porcentaje = Math.max(0, Math.min(100, (segundos / limite) * 100));
        if (relleno) {
            relleno.style.width = porcentaje + '%';
        }
        if (etiqueta) {
            etiqueta.textContent = segundos;
        }
        barra.classList.toggle('es-urgente', segundos <= 5);
    }

    function detener() {
        detenido = true;
        if (intervalo !== null) {
            clearInterval(intervalo);
            intervalo = null;
        }
    }

    function enviar() {
        if (detenido) {
            return;
        }
        detener();
        form.submit();
    }

    function tick() {
        if (detenido) {
            return;
        }

        var segundos = Math.max(0, Math.ceil((fin - Date.now()) / 1000));
        pintar(segundos);

        if (segundos <= 0) {
            enviar();
        }
    }

    if (respuesta) {
        respuesta.addEventListener('submit', function () {
            detener();
        });
    }

    // Si la página vuelve desde el bfcache, el reloj siguió corriendo:
    // recargamos para que el servidor recalcule el tiempo real.
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            window.location.reload();
        }
    });

    pintar(restante);
    intervalo = window.setInterval(tick, 250);
})();
