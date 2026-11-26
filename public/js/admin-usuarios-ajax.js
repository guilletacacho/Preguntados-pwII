(() => {
    const formulario = document.querySelector('[data-admin-usuarios-form]');
    const tbody = document.querySelector('[data-admin-usuarios-tbody]');
    const tablaContenedor = document.querySelector('[data-admin-tabla-contenedor]');
    const resumenPaginacion = document.querySelector('[data-admin-paginacion-resumen]');
    const navPaginacion = document.querySelector('[data-admin-paginacion-nav]');
    const errorFechas = document.querySelector('[data-admin-error-fechas]');
    const errorAjax = document.querySelector('[data-admin-ajax-error]');
    const botonLimpiar = document.querySelector('[data-admin-limpiar]');
    const dialogoBloqueoForm = document.querySelector('#admin-dialogo-bloqueo form');

    if (!formulario || !tbody || !resumenPaginacion || !navPaginacion) return;

    const ajaxUrl = formulario.dataset.ajaxUrl;
    const basePath = formulario.dataset.basePath || '';
    if (!ajaxUrl) return;

    const inputBusqueda = formulario.elements.namedItem('busqueda');
    const inputPuntosMinimos = formulario.elements.namedItem('puntosMinimos');

    let temporizador = null;
    let controlador = null;

    const SVG_NS = 'http://www.w3.org/2000/svg';
    const ICONOS = {
        editar: [
            ['path', { d: 'M12 20h9' }],
            ['path', { d: 'M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4Z' }],
        ],
        bloquear: [
            ['rect', { x: '4', y: '11', width: '16', height: '10', rx: '2' }],
            ['path', { d: 'M8 11V7a4 4 0 0 1 8 0v4' }],
        ],
        desbloquear: [
            ['rect', { x: '4', y: '11', width: '16', height: '10', rx: '2' }],
            ['path', { d: 'M8 11V7a4 4 0 0 1 7.46-2' }],
        ],
        eliminar: [
            ['path', { d: 'M3 6h18' }],
            ['path', { d: 'M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2' }],
            ['path', { d: 'M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6' }],
            ['path', { d: 'M10 11v6' }],
            ['path', { d: 'M14 11v6' }],
        ],
    };

    function crearIconoSvg(tipo) {
        const svg = document.createElementNS(SVG_NS, 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '2');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.setAttribute('aria-hidden', 'true');
        (ICONOS[tipo] || []).forEach(([tag, attrs]) => {
            const nodo = document.createElementNS(SVG_NS, tag);
            Object.entries(attrs).forEach(([k, v]) => nodo.setAttribute(k, v));
            svg.appendChild(nodo);
        });
        return svg;
    }

    function crearElemento(tag, clase, texto) {
        const el = document.createElement(tag);
        if (clase) el.className = clase;
        if (texto !== undefined) el.textContent = texto;
        return el;
    }

    function crearInputOculto(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = String(value);
        return input;
    }

    function construirParametros(pagina) {
        const datos = new FormData(formulario);
        const params = new URLSearchParams();
        for (const [clave, valor] of datos.entries()) {
            params.set(clave, typeof valor === 'string' ? valor : '');
        }
        if (pagina > 1) {
            params.set('pagina', String(pagina));
        }
        return params;
    }

    function sincronizarUrl(data) {
        const filtros = data.filtros || {};
        const params = new URLSearchParams();
        const valoresPorDefecto = { orden: 'alta_desc' };

        Object.entries(filtros).forEach(([clave, valor]) => {
            if (valor !== '' && valor !== valoresPorDefecto[clave]) {
                params.set(clave, valor);
            }
        });
        if (data.pagina > 1) {
            params.set('pagina', String(data.pagina));
        }

        const qs = params.toString();
        const url = `${basePath}/admin/usuarios${qs ? `?${qs}` : ''}`;
        window.history.replaceState(null, '', url);
    }

    function construirFilaUsuario(usuario, queryFiltros, pagina, csrfToken) {
        const tr = document.createElement('tr');
        const sufijoListado = `?${queryFiltros}&pagina=${pagina}`;

        // Usuario
        const tdUsuario = document.createElement('td');
        tdUsuario.appendChild(crearElemento('strong', '', usuario.usuario));
        tr.appendChild(tdUsuario);

        // Nombre y correo
        const tdNombre = document.createElement('td');
        tdNombre.appendChild(document.createTextNode(usuario.nombre || ''));
        tdNombre.appendChild(document.createElement('br'));
        tdNombre.appendChild(crearElemento('span', 'moderador-item-meta', usuario.email || ''));
        tr.appendChild(tdNombre);

        // Rol
        const tdRol = document.createElement('td');
        const clasesBadge = ['moderador-badge'];
        if (usuario.esAdmin) clasesBadge.push('moderador-badge-alerta');
        if (usuario.esModerador) clasesBadge.push('moderador-badge-verde');
        tdRol.appendChild(crearElemento('span', clasesBadge.join(' '), usuario.rol));
        tr.appendChild(tdRol);

        // Estado
        const tdEstado = document.createElement('td');
        tdEstado.appendChild(document.createTextNode(usuario.verificado ? 'Verificado' : 'Sin verificar'));
        if (usuario.bloqueado) {
            tdEstado.appendChild(document.createElement('br'));
            tdEstado.appendChild(crearElemento('span', 'admin-estado-bloqueado', 'Bloqueado'));
        }
        tr.appendChild(tdEstado);

        // Puntos
        const tdPuntos = crearElemento('td', '', String(usuario.puntos ?? 0));
        tr.appendChild(tdPuntos);

        // Fecha de alta
        const tdFecha = document.createElement('td');
        if (usuario.fechaAlta) {
            tdFecha.textContent = usuario.fechaAlta;
        } else {
            tdFecha.appendChild(crearElemento('span', 'moderador-item-meta', 'Sin registro'));
        }
        tr.appendChild(tdFecha);

        // Acciones
        const tdAcciones = document.createElement('td');
        const contenedorAcciones = crearElemento('div', 'admin-usuario-acciones');

        const enlaceEditar = crearElemento('a', 'moderador-boton moderador-boton-corregir admin-accion-icono');
        enlaceEditar.href = `${basePath}/admin/editarUsuario?id=${encodeURIComponent(usuario.id)}`;
        enlaceEditar.title = 'Editar';
        enlaceEditar.setAttribute('aria-label', `Editar a ${usuario.usuario}`);
        enlaceEditar.appendChild(crearIconoSvg('editar'));
        contenedorAcciones.appendChild(enlaceEditar);

        if (!usuario.esAdmin) {
            if (!usuario.bloqueado) {
                const botonBloquear = crearElemento('button', 'moderador-boton admin-boton-bloqueo admin-accion-icono');
                botonBloquear.type = 'button';
                botonBloquear.title = 'Bloquear';
                botonBloquear.setAttribute('aria-haspopup', 'dialog');
                botonBloquear.setAttribute('aria-label', `Bloquear a ${usuario.usuario}`);
                botonBloquear.dataset.adminBloqueo = '';
                botonBloquear.dataset.id = String(usuario.id);
                botonBloquear.dataset.usuario = usuario.usuario;
                botonBloquear.appendChild(crearIconoSvg('bloquear'));
                contenedorAcciones.appendChild(botonBloquear);
            } else {
                const formDesbloquear = document.createElement('form');
                formDesbloquear.method = 'post';
                formDesbloquear.action = `${basePath}/admin/cambiarBloqueoUsuario${sufijoListado}`;
                formDesbloquear.appendChild(crearInputOculto('id', usuario.id));
                formDesbloquear.appendChild(crearInputOculto('csrf_token', csrfToken));

                const botonDesbloquear = crearElemento('button', 'moderador-boton admin-boton-desbloqueo admin-accion-icono');
                botonDesbloquear.type = 'submit';
                botonDesbloquear.name = 'bloquear';
                botonDesbloquear.value = '0';
                botonDesbloquear.title = 'Desbloquear';
                botonDesbloquear.setAttribute('aria-label', `Desbloquear a ${usuario.usuario}`);
                botonDesbloquear.appendChild(crearIconoSvg('desbloquear'));
                formDesbloquear.appendChild(botonDesbloquear);

                contenedorAcciones.appendChild(formDesbloquear);
            }

            const formEliminar = document.createElement('form');
            formEliminar.method = 'post';
            formEliminar.action = `${basePath}/admin/bajaUsuario${sufijoListado}`;
            formEliminar.addEventListener('submit', (evento) => {
                if (!window.confirm('¿Eliminar esta cuenta de forma permanente?')) {
                    evento.preventDefault();
                }
            });
            formEliminar.appendChild(crearInputOculto('id', usuario.id));
            formEliminar.appendChild(crearInputOculto('csrf_token', csrfToken));

            const botonEliminar = crearElemento('button', 'moderador-boton moderador-boton-descartar admin-accion-icono');
            botonEliminar.type = 'submit';
            botonEliminar.title = 'Eliminar';
            botonEliminar.setAttribute('aria-label', `Eliminar a ${usuario.usuario}`);
            botonEliminar.appendChild(crearIconoSvg('eliminar'));
            formEliminar.appendChild(botonEliminar);

            contenedorAcciones.appendChild(formEliminar);
        }

        tdAcciones.appendChild(contenedorAcciones);
        tr.appendChild(tdAcciones);

        return tr;
    }

    function renderizarTabla(data) {
        const fragmento = document.createDocumentFragment();
        const usuarios = Array.isArray(data.usuarios) ? data.usuarios : [];

        if (usuarios.length === 0) {
            const tr = document.createElement('tr');
            const td = crearElemento('td', 'moderador-vacio', 'No hay usuarios con esos filtros.');
            td.colSpan = 7;
            tr.appendChild(td);
            fragmento.appendChild(tr);
        } else {
            usuarios.forEach((usuario) => {
                fragmento.appendChild(
                    construirFilaUsuario(usuario, data.queryFiltros || '', data.pagina, data.csrfToken || '')
                );
            });
        }

        tbody.replaceChildren(fragmento);
    }

    function renderizarPaginacion(data) {
        resumenPaginacion.textContent = `Página ${data.pagina} de ${data.paginas} · ${data.totalUsuarios} usuarios`;

        const fragmento = document.createDocumentFragment();
        const queryFiltros = data.queryFiltros || '';

        if (data.hayPaginaAnterior) {
            const enlaceAnterior = crearElemento('a', '', 'Anterior');
            enlaceAnterior.href = `${basePath}/admin/usuarios?${queryFiltros}&pagina=${data.paginaAnterior}`;
            enlaceAnterior.dataset.pagina = String(data.paginaAnterior);
            fragmento.appendChild(enlaceAnterior);
        } else {
            fragmento.appendChild(crearElemento('span', 'paginacion-deshabilitada', 'Anterior'));
        }

        if (data.hayPaginaSiguiente) {
            const enlaceSiguiente = crearElemento('a', '', 'Siguiente');
            enlaceSiguiente.href = `${basePath}/admin/usuarios?${queryFiltros}&pagina=${data.paginaSiguiente}`;
            enlaceSiguiente.dataset.pagina = String(data.paginaSiguiente);
            fragmento.appendChild(enlaceSiguiente);
        } else {
            fragmento.appendChild(crearElemento('span', 'paginacion-deshabilitada', 'Siguiente'));
        }

        navPaginacion.replaceChildren(fragmento);
    }

    function actualizarDialogoBloqueo(data) {
        if (!dialogoBloqueoForm) return;
        dialogoBloqueoForm.action = `${basePath}/admin/cambiarBloqueoUsuario?${data.queryFiltros || ''}&pagina=${data.pagina}`;
        const campoCsrf = dialogoBloqueoForm.elements.namedItem('csrf_token');
        if (campoCsrf && data.csrfToken) {
            campoCsrf.value = data.csrfToken;
        }
    }

    function mostrarErrorAjax(mensaje) {
        if (!errorAjax) return;
        if (mensaje) {
            errorAjax.textContent = mensaje;
            errorAjax.hidden = false;
        } else {
            errorAjax.textContent = '';
            errorAjax.hidden = true;
        }
    }

    async function cargarUsuarios(pagina = 1) {
        if (controlador) {
            controlador.abort();
        }
        const actual = new AbortController();
        controlador = actual;

        if (tablaContenedor) {
            tablaContenedor.classList.add('is-loading');
            tablaContenedor.setAttribute('aria-busy', 'true');
        }
        mostrarErrorAjax('');

        try {
            const params = construirParametros(pagina);
            const respuesta = await fetch(`${ajaxUrl}?${params.toString()}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                cache: 'no-store',
                signal: actual.signal,
            });

            const data = await respuesta.json().catch(() => null);
            if (!respuesta.ok || !data) {
                throw new Error(data && data.error ? data.error : 'No se pudo cargar el listado de usuarios.');
            }

            if (errorFechas) {
                errorFechas.hidden = !data.errorFechas;
            }

            renderizarTabla(data);
            renderizarPaginacion(data);
            actualizarDialogoBloqueo(data);
            sincronizarUrl(data);
        } catch (error) {
            if (error.name === 'AbortError') return;
            mostrarErrorAjax(error.message || 'No se pudo actualizar el listado de usuarios.');
        } finally {
            if (controlador === actual && tablaContenedor) {
                tablaContenedor.classList.remove('is-loading');
                tablaContenedor.removeAttribute('aria-busy');
            }
        }
    }

    function programarBusqueda() {
        clearTimeout(temporizador);
        temporizador = setTimeout(() => {
            cargarUsuarios(1);
        }, 250);
    }

    if (inputBusqueda) {
        inputBusqueda.addEventListener('input', programarBusqueda);
    }

    if (inputPuntosMinimos) {
        inputPuntosMinimos.addEventListener('input', programarBusqueda);
    }

    formulario.querySelectorAll('select, input[type="date"]').forEach((campo) => {
        campo.addEventListener('change', () => {
            clearTimeout(temporizador);
            cargarUsuarios(1);
        });
    });

    formulario.addEventListener('submit', (evento) => {
        evento.preventDefault();
        clearTimeout(temporizador);
        cargarUsuarios(1);
    });

    if (botonLimpiar) {
        botonLimpiar.addEventListener('click', (evento) => {
            evento.preventDefault();
            clearTimeout(temporizador);
            ['busqueda', 'rol', 'estado', 'desde', 'hasta', 'puntosMinimos'].forEach((nombre) => {
                const campo = formulario.elements.namedItem(nombre);
                if (campo) campo.value = '';
            });
            const orden = formulario.elements.namedItem('orden');
            if (orden) orden.value = 'alta_desc';
            cargarUsuarios(1);
        });
    }

    navPaginacion.addEventListener('click', (evento) => {
        const enlace = evento.target.closest('a[data-pagina]');
        if (!enlace) return;
        evento.preventDefault();
        clearTimeout(temporizador);
        const pagina = Number.parseInt(enlace.dataset.pagina, 10);
        if (Number.isInteger(pagina) && pagina >= 1) {
            cargarUsuarios(pagina);
        }
    });
})();
