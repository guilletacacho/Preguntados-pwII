(() => {
    const dialogo = document.querySelector('#admin-dialogo-bloqueo');
    const formulario = dialogo.querySelector('form');
    const cuenta = dialogo.querySelector('[data-admin-cuenta]');

    document.querySelectorAll('[data-admin-bloqueo]').forEach((boton) => {
        boton.addEventListener('click', () => {
            formulario.reset();
            formulario.elements.namedItem('id').value = boton.dataset.id;
            cuenta.textContent = boton.dataset.usuario;
            dialogo.showModal();
        });
    });

    dialogo.querySelector('[data-admin-cancelar]').addEventListener('click', () => dialogo.close());
})();
