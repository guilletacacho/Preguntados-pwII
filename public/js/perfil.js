document.addEventListener('DOMContentLoaded', () => {
    const mapElement = document.getElementById('perfil-map');
    if (mapElement && window.L) {
        const map = L.map(mapElement).setView([0, 0], 2);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a>'
        }).addTo(map);
    }

    const copiar = document.getElementById('perfil-copiar');
    if (copiar) copiar.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(copiar.dataset.enlace);
            copiar.textContent = 'Enlace copiado';
        } catch {
            copiar.textContent = 'No se pudo copiar';
        }
        setTimeout(() => { copiar.textContent = 'Copiar enlace'; }, 2000);
    });
});
