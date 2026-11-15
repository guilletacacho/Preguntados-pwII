(() => {
    document.querySelectorAll('[data-reporte-abrir]').forEach(button => {
        button.addEventListener('click', () => {
            const dialog = document.getElementById(button.dataset.reporteAbrir);
            if (dialog && !dialog.open) dialog.showModal();
        });
    });
    document.querySelectorAll('.reporte-dialogo').forEach(dialog => {
        dialog.querySelectorAll('[data-reporte-cerrar]').forEach(button => {
            button.addEventListener('click', () => dialog.close());
        });
        dialog.addEventListener('click', event => {
            const rect = dialog.getBoundingClientRect();
            if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
        });
        const form = dialog.querySelector('form');
        if (!form) return;
        const choices = [...form.querySelectorAll('input[type="checkbox"]')];
        const comment = form.querySelector('textarea');
        const update = () => {
            choices[0].setCustomValidity(choices.some(choice => choice.checked) ? '' : 'Elegí al menos un motivo.');
            const other = choices.some(choice => choice.value === 'otro' && choice.checked);
            comment.required = other;
            comment.setCustomValidity(other && !comment.value.trim() ? 'Contanos el motivo del reporte.' : '');
            form.querySelector('[data-reporte-opcional]').textContent = other ? 'Obligatorio' : 'Opcional';
            form.querySelector('[data-reporte-contador]').textContent = Array.from(comment.value).length + ' / 2000';
        };
        form.addEventListener('input', update);
        update();
    });
})();
