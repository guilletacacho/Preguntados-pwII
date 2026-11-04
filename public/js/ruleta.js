(() => {
    const root = document.querySelector('[data-roulette]');
    if (!root) return;

    const track = root.querySelector('.roulette-track');
    const viewport = root.querySelector('.roulette-window');
    const cards = Array.from(track.children);
    const winner = cards.find(card => card.dataset.selected === 'true');
    const spin = root.querySelector('.roulette-spin');
    const next = root.querySelector('.roulette-continue');
    const status = root.querySelector('.roulette-status');

    if (!winner || !cards.length) return;

    const basePath = root.dataset.basePath || '';
    let selected;
    let animation;
    let finished = false;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const center = element => viewport.clientWidth / 2 - element.offsetLeft - element.offsetWidth / 2;

    const celebrate = () => {
        if (reducedMotion.matches || !selected) return;
        const burst = document.createElement('div');
        burst.className = 'roulette-burst';
        burst.setAttribute('aria-hidden', 'true');
        burst.style.color = getComputedStyle(selected).getPropertyValue('--category-color');
        for (let i = 0; i < 12; i++) {
            const dot = document.createElement('i');
            const angle = (Math.PI * 2 * i) / 12;
            dot.style.setProperty('--dx', `${Math.cos(angle) * (48 + i % 3 * 12)}px`);
            dot.style.setProperty('--dy', `${Math.sin(angle) * (38 + i % 3 * 10)}px`);
            dot.style.setProperty('--delay', `${i % 3 * 35}ms`);
            burst.append(dot);
        }
        viewport.append(burst);
        window.setTimeout(() => burst.remove(), 1000);
    };

    // Le avisa al servidor que el giro ya se mostró, para que F5 no
    // repita la animación ni cambie la categoría. La categoría la eligió el
    // servidor antes de pintar la página, así que si el aviso no llega,
    // simplemente se vuelve a mostrar el giro.
    const avisarGiro = () => {
        fetch(window.pestanaDePartida.conSello(basePath + '/partida/girar'), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            keepalive: true
        }).catch(() => {});
    };

    const mostrarGanadora = (conCelebracion) => {
        finished = true;
        root.classList.add('ganadora');
        selected.classList.add('is-winner');
        if (conCelebracion) celebrate();
        root.classList.remove('is-spinning');
        status.textContent = `¡Te tocó ${winner.dataset.name}!`;
        spin.hidden = true;
        next.hidden = false;
        next.focus({ preventScroll: true });
    };

    if (root.dataset.spun === 'true') {
        // F5 después del giro: la categoría ya está ahí plantada, sin animación
        // y sin volver a ofrecer el botón de girar.
        const relleno = cards.filter((card) => card !== winner);
        track.replaceChildren();
        for (let i = 0; i < relleno.length; i++) {
            track.append(relleno[i].cloneNode(true));
        }
        selected = winner.cloneNode(true);
        track.append(selected);
        for (let i = 0; i < relleno.length; i++) {
            track.append(relleno[i].cloneNode(true));
        }
        mostrarGanadora(false);
    } else {
        spin.addEventListener('click', async () => {
            spin.disabled = true;
            avisarGiro();
            status.textContent = 'Eligiendo tu categoría…';
            root.classList.add('is-spinning');
            track.replaceChildren();
            for (let i = 0; i < 55; i++) {
                track.append(cards[Math.floor(Math.random() * cards.length)].cloneNode(true));
            }
            selected = winner.cloneNode(true);
            track.append(selected);
            for (let i = 0; i < 6; i++) {
                track.append(cards[i % cards.length].cloneNode(true));
            }
            const reduced = reducedMotion.matches;
            animation = track.animate([
                { transform: `translateX(${center(track.firstElementChild)}px)` },
                { transform: `translateX(${center(selected)}px)` }
            ], { duration: reduced ? 1 : 6200, easing: 'cubic-bezier(.12,.74,.12,1)', fill: 'forwards' });

            try {
                await animation.finished;
            } catch (error) {
                // La animación se canceló (por ejemplo, recarga): no seguimos.
                return;
            }

            track.style.transform = `translateX(${center(selected)}px)`;
            animation.cancel();
            animation = null;
            mostrarGanadora(true);
        });
    }

    const align = () => {
        if (finished && selected) {
            track.style.transform = `translateX(${center(selected)}px)`;
        } else if (animation && animation.playState === 'running') {
            // Keep the current progress when rotating the phone mid-spin.
            animation.effect.setKeyframes([
                { transform: `translateX(${center(track.firstElementChild)}px)` },
                { transform: `translateX(${center(selected)}px)` }
            ]);
        } else if (!animation) {
            const initial = cards[Math.floor(cards.length / 2)];
            track.style.transform = `translateX(${center(initial)}px)`;
        }
    };

    new ResizeObserver(align).observe(viewport);
    window.addEventListener('resize', align);
    align();
})();
