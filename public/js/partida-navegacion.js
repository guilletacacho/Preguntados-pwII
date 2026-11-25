(function () {
    var continuando = false;

    // Las páginas de partida no deben volver desde la caché del navegador
    // ni desde el bfcache: el estado real vive en el servidor.
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            continuando = true;
            window.location.reload();
        }
    });

    // Sello de ESTA pestaña. El servidor lo usa para que solo una pestaña
    // pueda jugar: la última que entra a Jugar toma el control y la anterior
    // queda con la sesión cerrada. Además, el servidor bloquea la cuenta
    // entera si se abre otra sesión (incógnito u otro dispositivo).
    var clave = 'preguntados-pestana';
    var parametro = 'pesta';
    var sello = '';
    try { sello = window.sessionStorage.getItem(clave) || ''; } catch (error) { sello = ''; }

    if (!/^[0-9a-f]{16}$/.test(sello)) {
        var bytes = new Uint8Array(8);
        if (window.crypto && window.crypto.getRandomValues) {
            window.crypto.getRandomValues(bytes);
        } else {
            for (var i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
        }
        sello = Array.prototype.map.call(bytes, function (n) {
            return (n < 16 ? '0' : '') + n.toString(16);
        }).join('');
        try { window.sessionStorage.setItem(clave, sello); } catch (error) { /* sin storage: no se bloquea */ }
    }

    function conSello(url) {
        try {
            var absoluta = new URL(url, window.location.href);
            if (absoluta.pathname.indexOf('/partida/') === -1) return url;
            if (absoluta.searchParams.get(parametro) === sello) return url;
            absoluta.searchParams.set(parametro, sello);
            return absoluta.pathname + absoluta.search + absoluta.hash;
        } catch (error) {
            return url;
        }
    }

    function marcarVinculos() {
        document.querySelectorAll('a[href]').forEach(function (vinculo) {
            var destino = vinculo.getAttribute('href');
            if (destino && destino.indexOf('/partida/') !== -1) vinculo.setAttribute('href', conSello(destino));
        });
        document.querySelectorAll('form[action]').forEach(function (formulario) {
            var destino = formulario.getAttribute('action');
            if (destino && destino.indexOf('/partida/') !== -1) formulario.setAttribute('action', conSello(destino));
        });
        var selloOculto = document.querySelector('[data-pestana-sello]');
        if (selloOculto) selloOculto.value = sello;
    }

    window.pestanaDePartida = { sello: sello, conSello: conSello };

    marcarVinculos();

    var dialogo = document.querySelector('[data-partida-salida]');
    if (dialogo) {
        var urlAviso = new URL(dialogo.dataset.partidaSalida, window.location.href);
        var rutaPartida = urlAviso.pathname.slice(0, urlAviso.pathname.lastIndexOf('/') + 1);
        var destinoSalida;
        var salidaConfirmada = false;
        var destinoAtras = new URL('../home/show', urlAviso).href;

        // El token limita los latidos a la partida que abrió esta vista.
        var latir = function () {
            fetch(conSello(rutaPartida + 'latido'), {
                method: 'POST',
                body: new URLSearchParams({ token: dialogo.dataset.token }),
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (respuesta) {
                if (respuesta.status === 403) {
                    continuando = true;
                    window.location.href = conSello(rutaPartida + 'show');
                }
            }).catch(function () { /* sin red: el servidor decide en el próximo clic */ });
        };
        window.setInterval(latir, 15000);

        function siguePartida(url, acciones) {
            return url.origin === window.location.origin
                && acciones.some(function (accion) { return url.pathname === rutaPartida + accion; });
        }

        document.addEventListener('click', function (event) {
            var enlace = event.target.closest('a[href]');
            if (!enlace || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey
                || event.shiftKey || event.altKey || (enlace.target && enlace.target !== '_self')
                || enlace.hasAttribute('download')) return;

            var url = new URL(enlace.href);
            if (url.protocol !== 'http:' && url.protocol !== 'https:') return;
            if (url.hash && url.origin === location.origin && url.pathname === location.pathname
                && url.search === location.search) return;
            if (siguePartida(url, ['pregunta', 'continuar'])) {
                continuando = true;
                return;
            }

            event.preventDefault();
            destinoSalida = url.href;
            dialogo.showModal();
        });

        document.addEventListener('submit', function (event) {
            if (!event.defaultPrevented && siguePartida(new URL(event.target.action),
                ['responderPregunta', 'tiempoAgotado', 'reportar', 'girar'])) {
                continuando = true;
            }
        });

        // El botón Atrás del navegador no pasa por el click de links.
        // Se agrega un estado centinela para interceptarlo solo en partida.
        if (window.history && window.history.pushState) {
            window.history.pushState({ partidaGuard: true }, '', conSello(window.location.pathname + window.location.search + window.location.hash));
            window.addEventListener('popstate', function () {
                if (continuando || salidaConfirmada) return;
                window.history.pushState({ partidaGuard: true }, '', conSello(window.location.pathname + window.location.search + window.location.hash));
                destinoSalida = destinoAtras;
                if (!dialogo.open) dialogo.showModal();
            });
        }

        dialogo.querySelector('[data-partida-quedarse]').addEventListener('click', function () {
            dialogo.close();
        });
        function avisarSalida() {
            var destino = conSello(urlAviso.href);
            var datos = new URLSearchParams({ token: dialogo.dataset.token });
            if (!navigator.sendBeacon || !navigator.sendBeacon(destino, datos)) {
                fetch(destino, { method: 'POST', body: datos, keepalive: true }).catch(function () {});
            }
        }

        dialogo.querySelector('[data-partida-abandonar]').addEventListener('click', function () {
            salidaConfirmada = true;
            avisarSalida();
            window.location.assign(destinoSalida);
        });

        // No se usa beforeunload: en F5 el navegador muestra un aviso genérico y,
        // si se acepta, la recarga debe reanudar la misma partida. El abandono se
        // confirma con el modal o con el botón Atrás del navegador.
    }

    if (window.history && window.history.replaceState) {
        window.history.replaceState({ partida: true }, '', conSello(window.location.pathname + window.location.search));
    }
})();
