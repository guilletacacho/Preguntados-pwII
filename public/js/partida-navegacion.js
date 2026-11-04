(function () {
    // Las páginas de partida no deben volver desde la caché del navegador
    // ni desde el bfcache: el estado real vive en el servidor.
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            window.location.reload();
        }
    });

    // "Atrás" no debe mostrar una pregunta ya respondida. Recargamos para
    // que el servidor decida qué corresponde mostrar.
    window.addEventListener('popstate', function () {
        window.location.reload();
    });

    // Sello de ESTA pestaña. El servidor lo usa para que solo una pestaña
    // de la cuenta pueda jugar: la segunda queda bloqueada hasta que el
    // usuario elija tomar el control.
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

    if (window.history && window.history.replaceState) {
        window.history.replaceState({ partida: true }, '', conSello(window.location.pathname + window.location.search));
    }
})();
