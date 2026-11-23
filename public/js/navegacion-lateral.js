const navegacion = document.querySelector('.home-sidenav');
const menu = navegacion.querySelector('.home-side-menu');
const boton = navegacion.querySelector('.home-side-toggle');
const clave = 'navegacionLateralAbierta';

function mostrarMenu(abierto) {
    boton.setAttribute('aria-expanded', String(abierto));
    menu.classList.toggle('is-expanded', abierto);
}

function guardarMenu(abierto) {
    mostrarMenu(abierto);
    try {
        sessionStorage.setItem(clave, abierto ? '1' : '0');
    } catch {
        // El menú sigue funcionando cuando el navegador bloquea el almacenamiento.
    }
}

try {
    const estado = sessionStorage.getItem(clave);
    if (estado !== null) {
        mostrarMenu(estado === '1');
    }
} catch {
    // Se conserva el estado inicial del menú.
}

boton.addEventListener('click', () => guardarMenu(boton.getAttribute('aria-expanded') !== 'true'));
menu.addEventListener('keydown', (evento) => {
    if (evento.key === 'Escape') {
        guardarMenu(false);
        boton.focus();
    }
});
