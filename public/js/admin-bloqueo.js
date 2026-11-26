(() => {
    const dialogo = document.querySelector('#admin-dialogo-bloqueo');
    if (!dialogo) return;
    const formulario = dialogo.querySelector('form');
    const cuenta = dialogo.querySelector('[data-admin-cuenta]');

    document.addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-admin-bloqueo]');
        if (!boton) return;
        formulario.reset();
        formulario.elements.namedItem('id').value = boton.dataset.id;
        cuenta.textContent = boton.dataset.usuario;
        dialogo.showModal();
    });

    dialogo.querySelector('[data-admin-cancelar]').addEventListener('click', () => dialogo.close());
})();
