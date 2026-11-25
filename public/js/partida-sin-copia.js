(function () {
    // Mientras la pregunta está en juego, el texto y la imagen no se pueden
    // seleccionar, copiar ni arrastrar a un buscador. No es infalible (una
    // captura de pantalla o reescribir la pregunta siguen siendo posibles),
    // pero con el tiempo limitado lo vuelve poco práctico.
    var zona = document.querySelector('.partida-sin-copia');
    if (!zona) {
        return;
    }

    function bloquear(evento) {
        if (zona.contains(evento.target)) {
            evento.preventDefault();
        }
    }

    ['copy', 'cut', 'contextmenu', 'selectstart', 'dragstart'].forEach(function (tipo) {
        document.addEventListener(tipo, bloquear);
    });

    // Ctrl/Cmd + C, X o A copian o seleccionan aunque no se vea la selección.
    document.addEventListener('keydown', function (evento) {
        var tecla = (evento.key || '').toLowerCase();
        if ((evento.ctrlKey || evento.metaKey) && (tecla === 'c' || tecla === 'x' || tecla === 'a')) {
            evento.preventDefault();
        }
    });

    // Si quedó algo seleccionado de antes, se descarta.
    if (window.getSelection) {
        window.getSelection().removeAllRanges();
    }
})();
