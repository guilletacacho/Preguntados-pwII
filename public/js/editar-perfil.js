document.addEventListener('DOMContentLoaded', () => {
    const mapElement = document.getElementById('map');
    if (mapElement && window.L) {
        const map = L.map(mapElement).setView([0, 0], 2);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a>'
        }).addTo(map);
    }

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
