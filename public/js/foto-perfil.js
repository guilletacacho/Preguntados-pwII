(() => {
    const input = document.getElementById('imagen_url');
    const dialog = document.getElementById('editor-foto');
    if (!input || !dialog) return;

    const area = document.getElementById('editor-foto-area');
    const img = document.getElementById('editor-foto-img');
    const zoom = document.getElementById('editor-foto-zoom');
    const preview = document.getElementById('foto-preview');
    const previewImg = preview.querySelector('img');
    const previewIcon = preview.querySelector('svg');
    const aviso = document.getElementById('imagen_url-aviso');
    const nombre = document.getElementById('imagen_url-nombre');
    const LADO_FINAL = 512;
    const MAX_BYTES = 5 * 1024 * 1024;

    // Archivo original elegido, para poder volver a editarlo, y el recorte ya aplicado.
    let original = null;
    let originalUrl = '';
    let aplicado = null;
    let pendiente = null;
    let pendienteUrl = '';
    let escala = 1;
    let offsetX = 0;
    let offsetY = 0;
    let arrastre = null;

    function diametro() {
        return Math.min(area.clientWidth, area.clientHeight) - 48;
    }

    function escalaBase() {
        const d = diametro();
        return Math.max(d / img.naturalWidth, d / img.naturalHeight);
    }

    // Mantiene el círculo siempre cubierto por la imagen.
    function limitar() {
        const d = diametro();
        const maxX = Math.max(0, (img.naturalWidth * escala - d) / 2);
        const maxY = Math.max(0, (img.naturalHeight * escala - d) / 2);
        offsetX = Math.min(maxX, Math.max(-maxX, offsetX));
        offsetY = Math.min(maxY, Math.max(-maxY, offsetY));
    }

    function dibujar() {
        limitar();
        img.style.width = `${img.naturalWidth * escala}px`;
        img.style.height = `${img.naturalHeight * escala}px`;
        img.style.transform = `translate(calc(-50% + ${offsetX}px), calc(-50% + ${offsetY}px))`;
    }

    function setZoom(valor) {
        zoom.value = String(Math.min(Number(zoom.max), Math.max(Number(zoom.min), valor)));
        escala = escalaBase() * Number(zoom.value);
        dibujar();
    }

    function reiniciar() {
        offsetX = 0;
        offsetY = 0;
        setZoom(1);
    }

    function abrir(archivo, url) {
        pendiente = archivo;
        pendienteUrl = url;
        img.onload = () => {
            dialog.showModal();
            reiniciar();
            area.focus();
        };
        img.src = url;
    }

    function cerrar() {
        if (pendienteUrl && pendienteUrl !== originalUrl) URL.revokeObjectURL(pendienteUrl);
        pendiente = null;
        pendienteUrl = '';
        // Si se cancela, el campo vuelve a tener el último recorte aplicado (o nada).
        const transfer = new DataTransfer();
        if (aplicado) transfer.items.add(aplicado);
        input.files = transfer.files;
        nombre.textContent = original ? original.name : 'Ninguna elegida';
        if (dialog.open) dialog.close();
    }

    function aplicar() {
        const d = diametro();
        const izquierda = area.clientWidth / 2 - (img.naturalWidth * escala) / 2 + offsetX;
        const arriba = area.clientHeight / 2 - (img.naturalHeight * escala) / 2 + offsetY;
        const lado = d / escala;
        const sx = (area.clientWidth / 2 - d / 2 - izquierda) / escala;
        const sy = (area.clientHeight / 2 - d / 2 - arriba) / escala;

        const canvas = document.createElement('canvas');
        canvas.width = LADO_FINAL;
        canvas.height = LADO_FINAL;
        canvas.getContext('2d').drawImage(img, sx, sy, lado, lado, 0, 0, LADO_FINAL, LADO_FINAL);
        canvas.toBlob(blob => {
            if (!blob) return;
            aplicado = new File([blob], 'perfil.jpg', { type: 'image/jpeg' });
            if (originalUrl && originalUrl !== pendienteUrl) URL.revokeObjectURL(originalUrl);
            original = pendiente;
            originalUrl = pendienteUrl;
            pendienteUrl = '';
            if (previewImg.src) URL.revokeObjectURL(previewImg.src);
            previewImg.src = URL.createObjectURL(blob);
            previewImg.hidden = false;
            previewIcon.style.display = 'none';
            preview.disabled = false;
            cerrar();
        }, 'image/jpeg', 0.9);
    }

    input.addEventListener('change', () => {
        aviso.textContent = '';
        const archivo = input.files[0];
        if (!archivo) {
            cerrar();
            return;
        }
        if (!['image/jpeg', 'image/png'].includes(archivo.type)) {
            aviso.textContent = 'La foto tiene que ser JPG o PNG.';
            cerrar();
            return;
        }
        if (archivo.size > MAX_BYTES) {
            aviso.textContent = 'La foto pesa más de 5 MB. Elegí una más liviana.';
            cerrar();
            return;
        }
        abrir(archivo, URL.createObjectURL(archivo));
    });

    preview.addEventListener('click', () => {
        if (original) abrir(original, originalUrl);
    });

    dialog.addEventListener('click', event => {
        const accion = event.target.closest('[data-editor]')?.dataset.editor;
        if (accion === 'cancelar') cerrar();
        if (accion === 'reiniciar') reiniciar();
        if (accion === 'aplicar') aplicar();
    });
    // La tecla Escape cierra el diálogo: se trata como Cancelar.
    dialog.addEventListener('cancel', event => {
        event.preventDefault();
        cerrar();
    });

    zoom.addEventListener('input', () => setZoom(Number(zoom.value)));
    area.addEventListener('wheel', event => {
        event.preventDefault();
        setZoom(Number(zoom.value) - event.deltaY * 0.002);
    }, { passive: false });

    area.addEventListener('pointerdown', event => {
        arrastre = { x: event.clientX - offsetX, y: event.clientY - offsetY };
        area.setPointerCapture(event.pointerId);
    });
    area.addEventListener('pointermove', event => {
        if (!arrastre) return;
        offsetX = event.clientX - arrastre.x;
        offsetY = event.clientY - arrastre.y;
        dibujar();
    });
    ['pointerup', 'pointercancel'].forEach(tipo => area.addEventListener(tipo, () => { arrastre = null; }));

    area.addEventListener('keydown', event => {
        const paso = event.shiftKey ? 30 : 10;
        const movimientos = { ArrowLeft: [-paso, 0], ArrowRight: [paso, 0], ArrowUp: [0, -paso], ArrowDown: [0, paso] };
        if (!movimientos[event.key]) return;
        event.preventDefault();
        offsetX += movimientos[event.key][0];
        offsetY += movimientos[event.key][1];
        dibujar();
    });
})();
