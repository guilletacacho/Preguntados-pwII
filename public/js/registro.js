(() => {
    const form = document.getElementById('registro-form');
    if (!form) return;
    form.querySelectorAll('[data-password-toggle]').forEach(button => {
        const input = document.getElementById(button.dataset.passwordToggle);
        button.hidden = false;
        button.addEventListener('click', () => {
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(visible));
            button.setAttribute('aria-label', visible ? 'Ocultar contrase\u00f1a' : 'Mostrar contrase\u00f1a');
        });
    });

    const fields = [...form.querySelectorAll('input:not([type="file"]):not([type="hidden"]):not([readonly]), select')];
    const username = form.elements.usuario;
    const password = form.elements.contrasena;
    const repeat = form.elements.repetirContrasena;
    const latitude = form.elements.latitude;
    const longitude = form.elements.longitude;
    const locationInput = form.elements.ubicacion;
    const locationError = document.getElementById('ubicacion-error');
    const mapElement = document.getElementById('map');
    let timer;
    let request;
    let checkedName = '';
    let available = null;
    let submitting = false;
    let map;
    let marker;

    if (mapElement && window.L) {
        map = L.map(mapElement).setView([0, 0], 2);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a>'
        }).addTo(map);
    }

    function mostrarUbicacion(position) {
        const lat = position.coords.latitude;
        const lon = position.coords.longitude;
        latitude.value = String(lat);
        longitude.value = String(lon);

        if (map) {
            const point = [lat, lon];
            if (marker) marker.setLatLng(point);
            else marker = L.marker(point).addTo(map);
            map.setView(point, 13);
        }
    }

    function obtenerUbicacion() {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('unsupported'));
                return;
            }
            navigator.geolocation.getCurrentPosition(resolve, reject, {
                enableHighAccuracy: false,
                maximumAge: 0,
                timeout: 10000
            });
        });
    }

    function mensajeErrorUbicacion(error) {
        if (error.message === 'unsupported') return 'Este navegador no permite obtener la ubicación.';
        if (error.code === 1) return 'Permití el acceso a la ubicación para completar el registro.';
        if (error.code === 3) return 'No pudimos obtener tu ubicación a tiempo. Intentá de nuevo.';
        return 'No pudimos obtener tu ubicación. Revisá el permiso y la conexión e intentá de nuevo.';
    }

    async function actualizarUbicacion() {
        locationInput.value = 'Buscando tu ubicación…';
        locationError.textContent = '';
        try {
            const position = await obtenerUbicacion();
            mostrarUbicacion(position);
            const url = new URL(form.action, window.location.href);
            url.searchParams.set('method', 'ubicacion');
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ latitude: latitude.value, longitude: longitude.value }),
                cache: 'no-store'
            });
            if (!response.ok) throw new Error('geocoding');
            const result = await response.json();
            const provincia = typeof result.provincia === 'string' ? result.provincia : '';
            const pais = typeof result.pais === 'string' ? result.pais : '';
            locationInput.value = [pais, provincia].filter(Boolean).join(', ');
            if (!locationInput.value) throw new Error('empty-location');
            locationError.textContent = '';
            locationInput.classList.remove('is-invalid');
            locationInput.removeAttribute('aria-invalid');
            return true;
        } catch (error) {
            locationInput.value = '';
            locationError.textContent = error.message === 'geocoding' || error.message === 'empty-location'
                ? 'Detectamos tu ubicación, pero no pudimos obtener el país y la provincia. Intentá de nuevo.'
                : mensajeErrorUbicacion(error);
            locationInput.classList.add('is-invalid');
            locationInput.setAttribute('aria-invalid', 'true');
            return false;
        }
    }

    actualizarUbicacion();

    function show(field, message) {
        field.setCustomValidity(message);
        field.classList.toggle('is-invalid', Boolean(message));
        field.setAttribute('aria-invalid', String(Boolean(message)));
        document.getElementById(`${field.id}-error`).textContent = message;
        return !message;
    }

    function validate(field) {
        let message = '';
        if (!field.value.trim()) message = 'Completa este campo.';
        else if (field.name === 'anioNacimiento' && (!/^\d+$/.test(field.value) || Number(field.value) < 1900 || Number(field.value) > Number(field.max))) {
            message = `Ingresa un año entre 1900 y ${field.max}.`;
        } else if (field === password && [...field.value].length < 8) {
            message = 'La contraseña debe tener al menos 8 caracteres.';
        } else if (field === repeat && field.value !== password.value) {
            message = 'Las contraseñas no coinciden.';
        } else if (field === username && checkedName === field.value.trim() && available === false) {
            message = 'El usuario ya existe.';
        }
        return show(field, message);
    }

    async function checkUsername() {
        const name = username.value.trim();
        if (!name) return;
        if (request) request.abort();
        const current = new AbortController();
        request = current;
        const url = new URL(form.action, window.location.href);
        url.searchParams.set('method', 'disponibilidad');
        url.searchParams.set('usuario', name);
        try {
            const response = await fetch(url, { signal: current.signal, cache: 'no-store' });
            if (!response.ok) throw new Error('availability');
            const result = await response.json();
            if (username.value.trim() !== name) return;
            checkedName = name;
            available = result.disponible;
            validate(username);
        } catch (error) {
            // El servidor vuelve a comprobar la disponibilidad al enviar.
            if (error.name !== 'AbortError') {
                checkedName = '';
                available = null;
            }
        }
    }

    fields.forEach(field => {
        const error = document.getElementById(`${field.id}-error`).textContent.trim();
        if (error) show(field, error);
        field.addEventListener('input', () => {
            if (field === username) {
                clearTimeout(timer);
                if (request) request.abort();
                checkedName = '';
                available = null;
                if (field.value.trim()) timer = setTimeout(checkUsername, 400);
            }
            validate(field);
            if (field === password && repeat.value) validate(repeat);
        });
        field.addEventListener('blur', () => validate(field));
        field.addEventListener('change', () => validate(field));
    });

    form.noValidate = true;
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (submitting) return;
        if (fields.map(validate).includes(false)) {
            form.querySelector('.is-invalid').focus();
            return;
        }
        submitting = true;
        clearTimeout(timer);
        try {
            await checkUsername();
            if (fields.map(validate).includes(false)) {
                form.querySelector('.is-invalid').focus();
                return;
            }
            if (!await actualizarUbicacion()) {
                locationInput.focus();
                return;
            }
            HTMLFormElement.prototype.submit.call(form);
        } finally {
            submitting = false;
        }
    });
})();
