document.addEventListener('DOMContentLoaded', () => {
    const mapElement = document.querySelector('.ubicacion-mapa');
    if (!mapElement) return;

    const status = mapElement.parentElement.querySelector('.ubicacion-mapa-estado');
    if (!window.L) {
        status.textContent = 'No se pudo cargar el mapa.';
        return;
    }

    const map = L.map(mapElement).setView([0, 0], 2);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a>'
    }).addTo(map);

    const provinceInput = document.getElementById('provincia');
    const countryInput = document.getElementById('pais');
    let marker;
    let timer;
    let request;

    async function mostrarUbicacion() {
        const provincia = provinceInput ? provinceInput.value.trim() : mapElement.dataset.provincia.trim();
        const pais = countryInput ? countryInput.value.trim() : mapElement.dataset.pais.trim();
        const query = [provincia, pais].filter(Boolean).join(', ');

        if (!query) {
            status.textContent = 'No hay una provincia o país guardado para ubicar en el mapa.';
            return;
        }

        if (request) request.abort();
        request = new AbortController();
        status.textContent = 'Buscando ubicación…';

        try {
            const url = new URL('https://nominatim.openstreetmap.org/search');
            url.search = new URLSearchParams({
                format: 'jsonv2',
                limit: '1',
                'accept-language': 'es',
                q: query
            });
            const response = await fetch(url, { signal: request.signal });
            if (!response.ok) throw new Error('geocoding');
            const results = await response.json();
            if (!Array.isArray(results) || !results.length) {
                status.textContent = 'No se encontró esa ubicación en el mapa.';
                return;
            }

            const point = [Number(results[0].lat), Number(results[0].lon)];
            if (!point.every(Number.isFinite)) throw new Error('coordinates');
            if (marker) marker.setLatLng(point);
            else marker = L.marker(point).addTo(map);
            map.setView(point, provincia ? 8 : 5);
            status.textContent = `Mostrando una ubicación aproximada para ${query}.`;
        } catch (error) {
            if (error.name === 'AbortError') return;
            status.textContent = 'No se pudo resolver la ubicación. Revisá la conexión e intentá nuevamente.';
        }
    }

    mostrarUbicacion();

    if (provinceInput && countryInput) {
        const actualizarConDemora = () => {
            clearTimeout(timer);
            timer = setTimeout(mostrarUbicacion, 1200);
        };
        provinceInput.addEventListener('input', actualizarConDemora);
        countryInput.addEventListener('input', actualizarConDemora);
    }
});
