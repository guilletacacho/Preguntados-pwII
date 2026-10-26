document.addEventListener('DOMContentLoaded', () => {
    // Mostrar u ocultar cada contraseña, igual que en el registro.
    document.querySelectorAll('#editar-form [data-password-toggle]').forEach(boton => {
        const campo = document.getElementById(boton.dataset.passwordToggle);
        boton.addEventListener('click', () => {
            const visible = campo.type === 'password';
            campo.type = visible ? 'text' : 'password';
            boton.setAttribute('aria-pressed', String(visible));
            boton.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
        });
    });
});
