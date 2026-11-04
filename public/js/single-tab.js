(function () {
    'use strict';

    var config = document.currentScript.dataset;
    var key = 'preguntados:single-tab:' + config.base + ':' + config.user;
    var status = document.getElementById('single-tab-status');
    var template = document.getElementById('single-tab-content');
    var release;

    function blocked(message) {
        status.replaceChildren();
        var title = document.createElement('h1');
        title.textContent = 'No se puede abrir esta pestaña';
        var description = document.createElement('p');
        description.textContent = message;
        var retry = document.createElement('button');
        retry.className = 'btn btn-primary';
        retry.textContent = 'Reintentar';
        retry.onclick = function () { window.location.reload(); };
        status.append(title, description, retry);
    }

    async function showContent() {
        if (document.readyState === 'loading') {
            await new Promise(function (resolve) {
                document.addEventListener('DOMContentLoaded', resolve, { once: true });
            });
        }

        var content = template.content.cloneNode(true);
        var scripts = Array.from(content.querySelectorAll('script'));
        scripts.forEach(function (script) { script.remove(); });
        status.replaceWith(content);
        template.remove();


        for (var original of scripts) {
            var script = document.createElement('script');
            for (var attribute of original.attributes) {
                if (attribute.name !== 'defer' && attribute.name !== 'async') {
                    script.setAttribute(attribute.name, attribute.value);
                }
            }
            script.textContent = original.textContent;
            if (original.src) {
                await new Promise(function (resolve) {
                    script.onload = resolve;
                    script.onerror = resolve;
                    document.body.appendChild(script);
                });
            } else {
                document.body.appendChild(script);
            }
        }

        document.dispatchEvent(new Event('DOMContentLoaded'));
    }

    window.addEventListener('pagehide', function () {
        if (release) release();
    });
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) window.location.reload();
    });

    if (!navigator.locks) {
        blocked('El navegador no permite comprobar las pestañas. Usá un navegador actualizado y accedé mediante HTTPS o localhost.');
        return;
    }

    var controller = new AbortController();

    var timeout = setTimeout(function () { controller.abort(); }, 1000);
    navigator.locks.request(key, { signal: controller.signal }, async function () {
        clearTimeout(timeout);
        var held = new Promise(function (resolve) { release = resolve; });
        await showContent();
        await held;
    }).catch(function () {
        clearTimeout(timeout);
        blocked('Ya tenés una pestaña abierta con este usuario. Continuá allí o cerrala y presioná Reintentar.');
    });
})();
