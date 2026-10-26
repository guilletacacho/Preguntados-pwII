document.addEventListener('DOMContentLoaded', () => {
    const copiar = document.getElementById('perfil-copiar');
    if (!copiar) return;

    copiar.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(copiar.dataset.enlace);
            copiar.textContent = 'Enlace copiado';
        } catch {
            copiar.textContent = 'No se pudo copiar';
        }
        setTimeout(() => { copiar.textContent = 'Copiar enlace'; }, 2000);
    });
});
