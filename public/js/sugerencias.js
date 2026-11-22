(function () {
    const categorySelect = document.querySelector('#categoria-sugerida');
    const formCard = document.querySelector('.sugerencias-form-card');
    const formToggle = document.querySelector('.sugerencias-cta');
    const formWrap = document.querySelector('#sugerencias-form-wrap');
    const sideToggle = document.querySelector('.sugerencias-shell .home-side-toggle');
    const sideMenu = document.querySelector('.sugerencias-shell .home-side-menu');
    const imageInput = document.querySelector('#imagen-sugerida');
    const imagePreviewWrap = document.querySelector('#imagen-sugerida-preview-wrap');
    const imagePreview = document.querySelector('#imagen-sugerida-preview');
    const imageName = document.querySelector('#imagen-sugerida-nombre');

    if (formToggle && formWrap && formToggle.getAttribute('aria-expanded') === 'true') {
        formWrap.removeAttribute('inert');
    }

    if (categorySelect && formCard) {
        categorySelect.addEventListener('change', function () {
            const categoryColor = this.selectedOptions[0]?.dataset.color;
            if (categoryColor) {
                formCard.dataset.category = categoryColor;
            } else {
                delete formCard.dataset.category;
            }
        });
    }

    if (formToggle && formWrap) {
        formToggle.addEventListener('click', function () {
            const expanded = this.getAttribute('aria-expanded') !== 'true';
            this.setAttribute('aria-expanded', String(expanded));
            formWrap.classList.toggle('is-open', expanded);
            formWrap.setAttribute('aria-hidden', String(!expanded));
            this.disabled = expanded;
            if (expanded) {
                formWrap.removeAttribute('inert');
            } else {
                formWrap.setAttribute('inert', '');
            }
        });
    }

    if (imageInput && imagePreviewWrap && imagePreview && imageName) {
        let previewUrl = null;

        imageInput.addEventListener('change', function () {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
                previewUrl = null;
            }

            const file = this.files && this.files[0];
            if (!file) {
                imagePreview.removeAttribute('src');
                imagePreviewWrap.hidden = true;
                imageName.textContent = 'Ninguna imagen seleccionada.';
                return;
            }

            if (!['image/jpeg', 'image/png'].includes(file.type)) {
                this.value = '';
                imagePreview.removeAttribute('src');
                imagePreviewWrap.hidden = true;
                imageName.textContent = 'Elegí un archivo JPG o PNG.';
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                this.value = '';
                imagePreview.removeAttribute('src');
                imagePreviewWrap.hidden = true;
                imageName.textContent = 'La imagen no puede superar los 5 MB.';
                return;
            }

            previewUrl = URL.createObjectURL(file);
            imagePreview.src = previewUrl;
            imagePreviewWrap.hidden = false;
            imageName.textContent = file.name;
        });
    }

    if (sideToggle && sideMenu) {
        sideToggle.addEventListener('click', function () {
            const expanded = this.getAttribute('aria-expanded') !== 'true';
            this.setAttribute('aria-expanded', String(expanded));
            this.parentElement.classList.toggle('is-expanded', expanded);
        });
        sideMenu.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                this.classList.remove('is-expanded');
                sideToggle.setAttribute('aria-expanded', 'false');
                sideToggle.focus();
            }
        });
    }
})();