(() => {
    const root = document.querySelector('.partida-shell, .roulette-shell');
    if (!root) return;
    const panel = root.querySelector('.partida-contenido, .roulette-panel');
    if (!panel) return;
    const answers = root.querySelector('.partida-respuestas');
    const key = 'preguntados:game-view:' + location.pathname.split('/partida')[0];
    let previous;
    try {
        previous = JSON.parse(sessionStorage.getItem(key));
        sessionStorage.removeItem(key);
    } catch (_) {  }

    const remember = () => {
        if (!answers || root.dataset.result === 'true') return;
        try {
            sessionStorage.setItem(key, JSON.stringify({
                question: root.dataset.question,
                top: answers.getBoundingClientRect().top,
                savedAt: Date.now()
            }));
        } catch (_) {  }
    };
    root.addEventListener('submit', remember);
    window.addEventListener('pagehide', remember);

    const position = () => {
        const box = panel.getBoundingClientRect();
        let target;
        if (answers && root.dataset.result === 'true' &&
            previous?.question === root.dataset.question &&
            Number.isFinite(previous.top) && Date.now() - previous.savedAt < 120000) {

            target = window.scrollY + answers.getBoundingClientRect().top - previous.top;
        } else {

            target = window.scrollY + box.top - Math.max(12, (window.innerHeight - box.height) / 2);
        }
        window.scrollTo({ top: Math.max(0, target), behavior: 'instant' });
    };


    Promise.resolve(document.fonts?.ready).then(() => {
        requestAnimationFrame(() => requestAnimationFrame(position));
    });
})();
