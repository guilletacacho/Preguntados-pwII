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
    const winnerBanner = root.querySelector('[data-roulette-winner]');
    const winnerName = root.querySelector('[data-roulette-winner-name]');

    if (!winner || !cards.length) return;

    const basePath = root.dataset.basePath || '';
    let selected;
    let animation;
    let finished = false;
    let startCard;
    let activeCard;
    let markerAnimation;
    const marker = root.querySelector('.roulette-marker');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const center = element => viewport.clientWidth / 2 - element.offsetLeft - element.offsetWidth / 2;
    const spinFrames = () => {
        const start = center(startCard);
        const end = center(selected);
        return [
            { transform: `translateX(${start}px)` },
            { transform: `translateX(${end}px)` }
        ];
    };

    const tick = () => {
        if (!animation || animation.playState !== 'running') return;
        const x = new DOMMatrixReadOnly(getComputedStyle(track).transform).m41;
        const stride = track.children[1].offsetLeft - track.children[0].offsetLeft;
        const index = Math.max(0, Math.min(track.children.length - 1,
            Math.round((viewport.clientWidth / 2 - x - track.firstElementChild.offsetWidth / 2) / stride)));
        const current = track.children[index];
        if (current !== activeCard) {
            activeCard?.classList.remove('is-passing');
            activeCard = current;
            current.classList.add('is-passing');
            viewport.style.setProperty('--roulette-accent', getComputedStyle(current).getPropertyValue('--category-color'));
            if (marker && !reducedMotion.matches) {
                markerAnimation?.cancel();
                markerAnimation = marker.animate([
                    { transform: 'rotate(-8deg)' }, { transform: 'rotate(4deg)', offset: .5 }, { transform: 'rotate(0)' }
                ], { duration: 130 });
            }
        }
        requestAnimationFrame(tick);
    };

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
        const destino = basePath + '/partida/girar';
        fetch(window.pestanaDePartida ? window.pestanaDePartida.conSello(destino) : destino, {
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

        if (winnerBanner && winnerName) {
            winnerName.textContent = winner.dataset.name;
            const color = getComputedStyle(selected).getPropertyValue('--category-color').trim();
            if (color) winnerBanner.style.setProperty('--roulette-accent', color);
            winnerBanner.hidden = false;
            winnerBanner.classList.add('is-visible');
            if (!conCelebracion) winnerBanner.classList.add('sin-salto');
        }

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
            if (spin.disabled || finished) return;
            spin.disabled = true;
            root.setAttribute('aria-busy', 'true');
            status.textContent = 'Eligiendo tu categoría…';
            root.classList.add('is-spinning');
            startCard = cards[Math.floor(cards.length / 2)];
            const reduced = reducedMotion.matches;
            // El giro ocurre en esta vista, antes de revelar la ganadora.
            // Se mantiene la duración, con menos recorrido en movimiento reducido.
            for (let i = 0; i < (reduced ? cards.length : cards.length * 5); i++) {
                track.append(cards[i % cards.length].cloneNode(true));
            }
            // Completar el último ciclo hasta la ganadora sin alterar el orden.
            for (const card of cards) {
                const copy = card.cloneNode(true);
                track.append(copy);
                if (card === winner) {
                    selected = copy;
                    break;
                }
            }
            for (let i = 0; i < 6; i++) {
                track.append(cards[i % cards.length].cloneNode(true));
            }
            animation = track.animate(spinFrames(), {
                duration: 4500,
                easing: 'cubic-bezier(0, .5, .5, 1)',
                fill: 'forwards'
            });
            requestAnimationFrame(tick);

            try {
                await animation.finished;
            } catch (error) {
                // La animación se canceló (por ejemplo, recarga): no seguimos.
                return;
            }

            track.style.transform = `translateX(${center(selected)}px)`;
            animation.cancel();
            animation = null;
            activeCard?.classList.remove('is-passing');
            markerAnimation?.cancel();
            root.setAttribute('aria-busy', 'false');
            mostrarGanadora(true);
            avisarGiro();
        });
    }

    const align = () => {
        if (finished && selected) {
            track.style.transform = `translateX(${center(selected)}px)`;
        } else if (animation && animation.playState === 'running') {
            // Keep the current progress when rotating the phone mid-spin.
            animation.effect.setKeyframes(spinFrames());
        } else if (!animation) {
            const initial = cards[Math.floor(cards.length / 2)];
            track.style.transform = `translateX(${center(initial)}px)`;
        }
    };

    new ResizeObserver(align).observe(viewport);
    window.addEventListener('resize', align);
    align();
})();
