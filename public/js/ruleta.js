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
    let selected;
    let animation;
    let finished = false;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const center = element => viewport.clientWidth / 2 - element.offsetLeft - element.offsetWidth / 2;
    const celebrate = () => {
        if (reducedMotion.matches) return;
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
    spin.addEventListener('click', async () => {
        if (spin.disabled || finished) return;
        spin.disabled = true;
        status.textContent = 'Eligiendo tu categoría…';
        root.classList.add('is-spinning');
        track.replaceChildren();
        for (let i = 0; i < 55; i++) {
            track.append(cards[Math.floor(Math.random() * cards.length)].cloneNode(true));
        }
        selected = winner.cloneNode(true);
        track.append(selected);
        for (let i = 0; i < 6; i++) track.append(cards[i % cards.length].cloneNode(true));
        const reduced = reducedMotion.matches;

        if (reduced) track.style.transform = `translateX(${center(selected)}px)`;
        animation = track.animate(reduced ? [
            { opacity: 1 },
            { opacity: .25, offset: .35 },
            { opacity: 1 }
        ] : [
            { transform: `translateX(${center(track.firstElementChild)}px)` },
            { transform: `translateX(${center(selected)}px)` }
        ], { duration: reduced ? 700 : 6200, easing: 'cubic-bezier(.12,.74,.12,1)', fill: 'forwards' });
        await animation.finished;
        finished = true;
        track.style.transform = `translateX(${center(selected)}px)`;
        animation.cancel();
        selected.classList.add('is-winner');
        celebrate();
        root.classList.remove('is-spinning');
        status.textContent = `¡Te tocó ${winner.dataset.name}!`;
        spin.hidden = true;
        next.hidden = false;
        next.focus({ preventScroll: true });
    });
    const align = () => {
        if (finished) {
            track.style.transform = `translateX(${center(selected)}px)`;
        } else if (animation && animation.playState === 'running') {
            if (reducedMotion.matches) {
                track.style.transform = `translateX(${center(selected)}px)`;
                return;
            }
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
