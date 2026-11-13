(() => {
    const { url, token } = document.currentScript.dataset;
    let pending = false;
    let stopped = false;
    const update = async () => {
        if (pending || stopped) return;
        pending = true;
        try {
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                body: new URLSearchParams({ token }),
                signal: AbortSignal.timeout(10000)
            });
            if (response.status === 401 || response.status === 403) {
                stopped = true;
                clearInterval(interval);
                showUnavailable();
                return;
            }
            if (!response.ok) throw new Error('presence');
            const { online } = await response.json();
            if (!Number.isInteger(online) || online < 0) throw new Error('presence');
            document.querySelectorAll('[data-online-count]').forEach(element => {
                element.textContent = online === 1 ? '1 usuario conectado' : `${online} usuarios conectados`;
            });
        } catch (_) {
            showUnavailable();
        } finally {
            pending = false;
        }
    };
    const showUnavailable = () => {
        document.querySelectorAll('[data-online-count]').forEach(element => {
            element.textContent = 'Comprobando usuarios conectados…';
        });
    };
    const interval = setInterval(update, 15000);
    window.addEventListener('online', update);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) update();
    });
    window.addEventListener('pagehide', () => { stopped = true; clearInterval(interval); });
    update();
})();
