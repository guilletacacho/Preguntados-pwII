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

    if (window.history && window.history.replaceState) {
        window.history.replaceState({ partida: true }, '', window.location.href);
    }
})();
