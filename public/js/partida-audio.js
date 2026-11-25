(() => {
    const vista = document.querySelector('[data-audio-escena]');
    const controles = vista?.querySelector('.partida-audio');
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (!controles || !AudioContext) return;

    const clave = 'preguntados:audio:';
    const preferencias = { fx: true, musica: false };
    try {
        preferencias.fx = localStorage.getItem(clave + 'fx') !== '0';
        preferencias.musica = localStorage.getItem(clave + 'musica') === '1';
    } catch (_) { /* Los controles siguen funcionando en la vista actual. */ }

    // Cada nota indica altura MIDI, retardo, duración y volumen.
    const efectos = {
        pulsar: [[81, 0, .065, .085]],
        elegir: [[69, 0, .10, .14], [76, .035, .12, .09]],
        rueda: [[84, 0, .038, .06]],
        categoria: [[72, 0, .15, .16], [76, .085, .15, .15], [79, .17, .30, .17]],
        acierto: [[72, 0, .14, .15], [76, .08, .14, .15], [79, .16, .17, .15], [84, .26, .34, .19]],
        error: [[67, 0, .19, .16], [63, .13, .23, .14], [60, .25, .28, .16]],
        aviso: [[78, 0, .09, .105]],
        tiempo: [[77, 0, .13, .17], [72, .12, .15, .14], [65, .24, .31, .15]]
    };

    // El primer índice del acorde es el bajo. En la figura, null es un silencio.
    const acordesJuego = [[48, 67, 76, 71], [45, 64, 72, 67], [41, 60, 69, 64], [43, 62, 71, 69]];
    const temaRuleta = {
        pulso: .25, timbre: 'triangle', bajos: [0, 4], figura: [2, 1, 3, 2, 1, 3, 2, 1], acordes: acordesJuego
    };
    const temaGeneral = {
        pulso: .36, timbre: 'triangle', bajos: [0, 4], figura: [2, null, 3, null, 1, null, 2, null], acordes: acordesJuego
    };
    const temas = {
        // Geografía lleva una melodía abierta, con pausas entre las notas.
        geografia: {
            pulso: .28, timbre: 'sine', bajos: [0, 5], figura: [1, null, 2, 3, null, 2, 1, null], duracion: 1.9,
            acordes: [[45, 69, 72, 76], [41, 65, 69, 72], [48, 67, 72, 76], [43, 67, 71, 74]]
        },
        // Historia usa una figura de marcha en compás ternario.
        historia: {
            pulso: .29, timbre: 'triangle', bajos: [0, 3], figura: [1, null, 2, 3, null, 2],
            acordes: [[50, 62, 65, 69], [46, 62, 65, 70], [43, 62, 67, 70], [45, 61, 64, 69]]
        },
        // Ciencia combina un pulso electrónico breve con notas altas.
        ciencia: {
            pulso: .23, timbre: 'sine', bajos: [0, 4], figura: [2, null, 1, 3, null, 2, null, 3], duracion: .85,
            acordes: [[53, 72, 76, 83], [55, 74, 78, 81], [52, 71, 74, 81], [48, 67, 71, 78]]
        },
        // Arte se apoya en un vals de notas arpegiadas.
        arte: {
            pulso: .27, timbre: 'triangle', bajos: [0], figura: [2, 1, 3, 1, 2, null],
            acordes: [[48, 67, 76, 71], [50, 69, 77, 72], [43, 65, 74, 71], [48, 67, 72, 76]]
        },
        // Deportes acentúa el bajo sincopado y mantiene una melodía corta.
        deportes: {
            pulso: .21, timbre: 'triangle', timbreBajo: 'triangle', bajos: [0, 3, 4, 7], figura: [2, 1, null, 2, 3, null, 2, 1], duracion: .75,
            acordes: [[40, 64, 67, 71], [43, 62, 67, 71], [45, 64, 69, 72], [47, 63, 66, 69]]
        },
        'cine y tv': {
            pulso: .39, timbre: 'sine', bajos: [0, 4], figura: [1, null, 2, null, 3, null, 2, null], duracion: 2.5,
            acordes: [[41, 65, 68, 72], [49, 65, 68, 73], [44, 67, 72, 75], [48, 64, 67, 70]]
        }
    };

    const esRuleta = vista.dataset.audioEscena === 'ruleta';
    const categoriaRevelada = vista.querySelector('[data-spun="true"] [data-selected="true"]');
    const normalizarCategoria = nombre => (nombre ?? '').normalize('NFD').replace(/\p{M}/gu, '').trim().toLowerCase();
    const temaDeCategoria = categoria => Object.hasOwn(temas, categoria) ? temas[categoria] : temaGeneral;
    let categoria = normalizarCategoria(categoriaRevelada?.dataset.name ?? vista.dataset.audioCategoria);
    let tema = esRuleta && !categoriaRevelada ? temaRuleta : temaDeCategoria(categoria);
    let contexto;
    let canales;
    let pistaMusica;
    let indiceNota = 0;
    let proximaNota = 0;
    let temporizador;
    let ultimoPaso = -Infinity;
    let salida = false;
    let resultadoPendiente = preferencias.fx && vista.querySelector('[data-audio-resultado]')?.dataset.audioResultado;

    if (!esRuleta) {
        try {
            const anterior = JSON.parse(sessionStorage.getItem(clave + 'tema'));
            if (anterior?.categoria === categoria && Number.isSafeInteger(anterior.indiceNota) && anterior.indiceNota >= 0) {
                indiceNota = anterior.indiceNota;
            }
        } catch (_) { /* El tema puede empezar desde la primera nota. */ }
    }

    function tocar(canal, altura, inicio, duracion, volumen, timbre) {
        const oscilador = contexto.createOscillator();
        const envolvente = contexto.createGain();
        oscilador.type = timbre;
        oscilador.frequency.value = 440 * 2 ** ((altura - 69) / 12);
        envolvente.gain.setValueAtTime(.0001, inicio);
        envolvente.gain.exponentialRampToValueAtTime(volumen, inicio + .006);
        envolvente.gain.exponentialRampToValueAtTime(.0001, inicio + duracion);
        oscilador.connect(envolvente).connect(canal);
        oscilador.start(inicio);
        oscilador.stop(inicio + duracion);
        oscilador.onended = () => {
            oscilador.disconnect();
            envolvente.disconnect();
        };
    }

    function efecto(nombre) {
        if (!preferencias.fx || contexto?.state !== 'running' || document.hidden || salida) return;
        const ahora = contexto.currentTime;
        if (nombre === 'rueda') {
            if (ahora - ultimoPaso < .06) return;
            ultimoPaso = ahora;
        }
        for (const [altura, retardo, duracion, volumen] of efectos[nombre]) {
            tocar(canales.fx, altura, ahora + retardo, duracion, volumen, 'triangle');
        }
    }

    function cambiarCategoria(nombre) {
        const nuevaCategoria = normalizarCategoria(nombre);
        if (nuevaCategoria === categoria && tema !== temaRuleta) return;
        categoria = nuevaCategoria;
        tema = temaDeCategoria(categoria);
        indiceNota = 0;
        proximaNota = contexto ? contexto.currentTime + .28 : 0;

        if (pistaMusica) {
            const anterior = pistaMusica;
            anterior.gain.setTargetAtTime(0, contexto.currentTime, .025);
            setTimeout(() => anterior.disconnect(), 150);
            pistaMusica = null;
        }
        sincronizarAudio();
    }

    function programarMusica() {
        if (!pistaMusica) {
            pistaMusica = contexto.createGain();
            pistaMusica.connect(canales.musica);
        }
        const { acordes, figura, pulso } = tema;
        const ahora = contexto.currentTime;
        proximaNota = Math.max(proximaNota, ahora + .015);
        while (proximaNota < ahora + .18) {
            const posicion = indiceNota % figura.length;
            const acorde = acordes[Math.floor(indiceNota / figura.length) % acordes.length];
            if (tema.bajos.includes(posicion)) {
                tocar(pistaMusica, acorde[0], proximaNota, pulso * 2, .075, tema.timbreBajo ?? 'sine');
            }
            if (figura[posicion] !== null) {
                tocar(pistaMusica, acorde[figura[posicion]], proximaNota, pulso * (tema.duracion ?? 1.4), .043, tema.timbre);
            }
            indiceNota++;
            proximaNota += pulso;
        }
    }

    function sincronizarAudio() {
        if (!contexto) return;
        const audible = contexto.state === 'running' && !document.hidden && !salida;
        const tocarMusica = audible && preferencias.musica;
        const ahora = contexto.currentTime;
        canales.fx.gain.setTargetAtTime(audible && preferencias.fx ? .74 : 0, ahora, .015);
        canales.musica.gain.setTargetAtTime(tocarMusica ? (tema === temaRuleta ? .46 : .35) : 0, ahora, .025);

        if (resultadoPendiente && audible && preferencias.fx) {
            efecto(resultadoPendiente);
            resultadoPendiente = null;
            proximaNota = ahora + .45;
        }
        if (tocarMusica && temporizador === undefined) {
            programarMusica();
            temporizador = setInterval(programarMusica, 100);
        } else if (!tocarMusica && temporizador !== undefined) {
            clearInterval(temporizador);
            temporizador = undefined;
        }
    }

    function iniciarAudio() {
        if (document.hidden || salida || (!preferencias.fx && !preferencias.musica)) return;
        if (!contexto) {
            try { contexto = new AudioContext(); } catch (_) { return; }
            canales = { fx: contexto.createGain(), musica: contexto.createGain() };
            for (const canal of Object.values(canales)) {
                canal.gain.value = 0;
                canal.connect(contexto.destination);
            }
            contexto.addEventListener('statechange', sincronizarAudio);
        }
        if (contexto.state !== 'running') contexto.resume().catch(() => {});
        sincronizarAudio();
    }

    function activarConInteraccion(event) {
        if (!event.isTrusted) return;
        try { sessionStorage.setItem(clave + 'interaccion', '1'); } catch (_) {}
        iniciarAudio();
    }

    function efectoDeControl(nombre) {
        if (!preferencias.fx || !contexto) return;
        if (contexto.state === 'running') {
            efecto(nombre);
        } else {
            contexto.resume().then(() => efecto(nombre), () => {});
        }
    }

    for (const boton of controles.querySelectorAll('[data-audio-control]')) {
        const canal = boton.dataset.audioControl;
        const pintar = () => {
            boton.setAttribute('aria-pressed', String(preferencias[canal]));
        };
        pintar();
        boton.addEventListener('click', (event) => {
            preferencias[canal] = !preferencias[canal];
            if (!preferencias.fx) resultadoPendiente = null;
            pintar();
            try { localStorage.setItem(clave + canal, preferencias[canal] ? '1' : '0'); } catch (_) {}
            activarConInteraccion(event);
            sincronizarAudio();
            efectoDeControl('pulsar');
        });
    }

    controles.hidden = false;
    window.audioPartida = { efecto, cambiarCategoria };
    document.addEventListener('pointerdown', activarConInteraccion, { capture: true, passive: true });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') activarConInteraccion(event);
    }, true);
    document.addEventListener('click', (event) => {
        const control = event.target.closest('button, a[href], summary');
        if (!event.isTrusted || !control || control.matches(':disabled, [data-audio-control]')) return;
        activarConInteraccion(event);
        efectoDeControl(control.matches('.partida-respuesta-boton') ? 'elegir' : 'pulsar');
    }, true);
    document.addEventListener('visibilitychange', sincronizarAudio);
    window.addEventListener('pagehide', () => {
        salida = true;
        sincronizarAudio();
        try { sessionStorage.setItem(clave + 'tema', JSON.stringify({ categoria, indiceNota })); } catch (_) {}
    });
    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) return;
        salida = false;
        sincronizarAudio();
    });

    // Si el navegador permite reanudar el audio tras la navegación, el
    // resultado y la música pueden sonar al abrir la siguiente vista.
    try {
        if (sessionStorage.getItem(clave + 'interaccion') === '1') iniciarAudio();
    } catch (_) {}
})();
