(function () {
    const categorySelect = document.querySelector('#categoria-sugerida');
    const formCard = document.querySelector('.sugerencias-form-card');
    const formToggle = document.querySelector('.sugerencias-cta');
    const formWrap = document.querySelector('#sugerencias-form-wrap');
    const sideToggle = document.querySelector('.sugerencias-shell .home-side-toggle');
    const sideMenu = document.querySelector('.sugerencias-shell .home-side-menu');

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