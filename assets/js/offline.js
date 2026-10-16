/* APEX · Trabajo sin conexión en la ruta de cobro.
 *
 * Cómo funciona:
 *  1. Cada cobro, gestión ("no pagó") u orden de ruta se intenta enviar al servidor. Si no hay señal,
 *     la petición no responde a tiempo o la sesión venció, se guarda en el celular (IndexedDB, cola "cola").
 *  2. La cola se envía sola, en el mismo orden en que se registró, al volver la conexión, al abrir APEX
 *     y cada 30 segundos. Cada operación lleva un uuid: si llega dos veces el servidor la registra una sola vez.
 *  3. Lo guardado pertenece al usuario que lo registró: solo se envía con su sesión. Cerrar sesión no lo borra.
 *  4. El service worker (sw.js) guarda una copia de la ruta y de la hoja de ruta para abrirlas sin internet.
 */
(function () {
    'use strict';

    const meta = (n) => (document.querySelector('meta[name="' + n + '"]') || {}).content || '';
    const BASE = meta('base-url').replace(/\/?$/, '/');
    if (!BASE || BASE === '/') return;
    const USUARIO = Number(meta('usuario-id')) || 0;     // 0 = no hay sesión (pantalla de ingreso)
    const ES_RUTA = !!document.getElementById('listaRuta');
    const ROL = meta('usuario-rol');
    const ES_COPIA = meta('apex-copia') === '1';   // página servida desde la copia guardada (sin señal)
    const TIEMPO_ENVIO_MS = 12000;

    // ---------------------------------------------------------------- service worker
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(BASE + 'sw.js', { scope: new URL(BASE).pathname }).catch(() => {});
    }
    const avisarSW = (msg) => {
        if (!('serviceWorker' in navigator)) return;
        navigator.serviceWorker.ready.then((reg) => reg.active && reg.active.postMessage(msg)).catch(() => {});
    };
    const limpiarPaginas = () => {
        avisarSW({ tipo: 'limpiar' });
        if (window.caches) caches.delete('apex-paginas').catch(() => {});
    };

    // Sin señal, las acciones que necesitan el servidor no se envían (el navegador mostraría un error
    // y se perdería lo escrito): se avisa y el formulario queda como estaba.
    document.addEventListener('submit', (ev) => {
        const form = ev.target;
        if (navigator.onLine || form.dataset.offline || (form.method || '').toLowerCase() !== 'post') return;
        ev.preventDefault();
        ev.stopImmediatePropagation();
        const accion = form.getAttribute('action') || '';
        const msg = /pagos\/guardar|gestiones\/guardar/.test(accion)
            ? 'Sin conexión. Para registrar el cobro sin internet hazlo desde la Ruta de cobro.'
            : /auth\/logout/.test(accion)
                ? 'Sin conexión. Para cerrar sesión necesitas internet.'
                : 'Sin conexión. Esta acción necesita internet; lo que escribiste sigue en el formulario.';
        if (window.apexToast) apexToast('warning', msg, 'Necesita internet');
        else alert(msg);
    }, true);

    // ---------------------------------------------------------------- cola en IndexedDB
    let dbPromesa = null;
    const abrir = () => dbPromesa || (dbPromesa = new Promise((resolve, reject) => {
        const req = indexedDB.open('apex-offline', 1);
        req.onupgradeneeded = () => req.result.createObjectStore('cola', { keyPath: 'seq', autoIncrement: true });
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    }));
    const tx = async (modo, fn) => {
        const db = await abrir();
        return new Promise((resolve, reject) => {
            const t = db.transaction('cola', modo);
            const r = fn(t.objectStore('cola'));
            t.oncomplete = () => resolve(r && 'result' in r ? r.result : undefined);
            t.onerror = () => reject(t.error);
            t.onabort = () => reject(t.error || new Error('IndexedDB'));
        });
    };
    const todos = async () => (await tx('readonly', (s) => s.getAll())) || [];
    const guardarItem = (item) => tx('readwrite', (s) => s.put(item));
    const borrarItem = (seq) => tx('readwrite', (s) => s.delete(seq));
    const mios = async () => (await todos()).filter((i) => i.usuarioId === USUARIO).sort((a, b) => a.seq - b.seq);

    const uuid = () => {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        const b = crypto.getRandomValues(new Uint8Array(16));
        b[6] = (b[6] & 0x0f) | 0x40; b[8] = (b[8] & 0x3f) | 0x80;
        const h = Array.from(b, (x) => x.toString(16).padStart(2, '0')).join('');
        return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
    };
    const ahoraLocal = () => {
        const d = new Date(), p = (n) => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
    };
    const horaCorta = (s) => (s || '').slice(11, 16);

    /** Agrega una operación a la cola. tipo: pago | gestion | orden */
    async function encolar(tipo, datos, resumen, id) {
        if (!USUARIO) throw new Error('Sin sesión');
        if (tipo === 'orden') {
            // Del orden de la ruta solo importa el último
            for (const i of await mios()) {
                if (i.tipo === 'orden' && String(i.datos.cobrador_id) === String(datos.cobrador_id)) await borrarItem(i.seq);
            }
        }
        await guardarItem({ uuid: id || uuid(), usuarioId: USUARIO, tipo, datos, resumen: resumen || {}, creadoEn: ahoraLocal(), estado: 'pendiente', error: null });
        await pintar();
        programarEnvio(1500);
    }

    // ---------------------------------------------------------------- envío de formularios de la ruta
    const fetchConLimite = (url, op, ms) => {
        const ctrl = new AbortController();
        const t = setTimeout(() => ctrl.abort(), ms);
        return fetch(url, Object.assign({}, op, { signal: ctrl.signal })).finally(() => clearTimeout(t));
    };
    const tokenVencido = (res) => !!(res && res.token_vencido);
    const necesitaSesion = (r, res) => tokenVencido(res) || (res && res.redirect && /\/(auth\/login|perfil)/.test(res.redirect));

    document.querySelectorAll('form[data-offline]').forEach((form) => {
        const modal = form.closest('.modal');
        const campoUuid = form.querySelector('[name=op_uuid]');
        // Un uuid nuevo por cada vez que se abre: así un reintento del mismo cobro nunca se duplica
        if (modal) modal.addEventListener('show.bs.modal', () => { campoUuid.value = uuid(); });
        if (!campoUuid.value) campoUuid.value = uuid();

        form.addEventListener('submit', async (ev) => {
            ev.preventDefault();
            const boton = form.querySelector('button:not([type=button])');
            if (boton.disabled) return;
            const tipo = form.dataset.offline;
            if (tipo === 'pago') {
                const m = form.querySelector('[name=monto]');
                const valor = Number((m.value || '').replace(/\D/g, ''));
                if (!valor || (m.dataset.max && valor > Number(m.dataset.max))) {
                    apexToast('danger', valor ? 'El valor supera el saldo pendiente (' + apexMoney(m.dataset.max) + ').' : 'Ingresa el valor recibido.');
                    return;
                }
            }
            boton.disabled = true;
            const fd = new FormData(form);
            const guardarLocal = async (motivo) => {
                await guardarDesdeFormulario(form, fd, tipo);
                if (modal && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modal).hide();
                apexToast('warning', motivo + ' Quedó guardado en este celular y se enviará solo cuando vuelva el internet.', 'Guardado en el celular');
                boton.disabled = false;
            };
            try {
                if (!navigator.onLine) { await guardarLocal('Sin conexión.'); return; }
                let r, res = null;
                try {
                    r = await fetchConLimite(form.action, { method: 'POST', body: fd, credentials: 'same-origin',
                        headers: { 'X-Apex-Fetch': '1', 'Accept': 'application/json' } }, TIEMPO_ENVIO_MS);
                    res = await r.json().catch(() => null);
                } catch (e) {
                    await guardarLocal('La señal está fallando.');
                    return;
                }
                if (necesitaSesion(r, res)) { await guardarLocal('Tu sesión se cerró.'); return; }
                if (!res || !res.redirect) { await guardarLocal('El servidor no respondió bien.'); return; }
                window.location.assign(res.redirect);   // guardado: la ruta muestra el aviso y el recibo
            } catch (e) {
                boton.disabled = false;
                apexToast('danger', 'No se pudo guardar: ' + (e.message || e));
            }
        });
    });

    async function guardarDesdeFormulario(form, fd, tipo) {
        const datos = {};
        fd.forEach((v, k) => { if (!['_csrf', 'volver', 'ruta_cobrador', 'op_uuid'].includes(k)) datos[k] = String(v); });
        const boton = document.querySelector('.ruta-col[data-credito="' + datos.credito_id + '"] [data-cliente]');
        const cliente = boton ? boton.dataset.cliente : '';
        let texto;
        if (tipo === 'pago') {
            texto = 'Cobro de ' + apexMoney(Number((datos.monto || '').replace(/\D/g, ''))) + ' · ' + cliente;
        } else {
            const sel = form.querySelector('[name=resultado]');
            texto = (sel ? sel.options[sel.selectedIndex].text : 'Gestión') + ' · ' + cliente;
        }
        await encolar(tipo, datos, { texto, cliente, credito: datos.credito_id }, fd.get('op_uuid') || undefined);
    }

    // ---------------------------------------------------------------- sincronización
    let enviando = false, pideSesion = false, temporizador = null;

    function programarEnvio(ms) {
        clearTimeout(temporizador);
        temporizador = setTimeout(sincronizar, ms);
    }

    async function pedirEstado() {
        const r = await fetchConLimite(BASE + 'sincronizar/estado', { credentials: 'same-origin',
            headers: { 'X-Apex-Fetch': '1', 'Accept': 'application/json' } }, TIEMPO_ENVIO_MS);
        const res = await r.json().catch(() => null);
        if (!res || res.redirect) { pideSesion = true; return null; }
        pideSesion = false;
        return res.usuario_id === USUARIO ? res.csrf : null;
    }

    async function enviar(item, csrf) {
        const cuerpo = new URLSearchParams({ uuid: item.uuid, tipo: item.tipo, creado_en: item.creadoEn, datos: JSON.stringify(item.datos) });
        const r = await fetchConLimite(BASE + 'sincronizar/operacion', { method: 'POST', body: cuerpo, credentials: 'same-origin',
            headers: { 'X-CSRF-Token': csrf, 'X-Apex-Fetch': '1', 'Accept': 'application/json' } }, TIEMPO_ENVIO_MS * 2);
        return { r, res: await r.json().catch(() => null) };
    }

    async function sincronizar() {
        if (enviando || !USUARIO || !navigator.onLine) return;
        const lote = (await mios()).filter((i) => i.estado === 'pendiente');
        if (!lote.length) { pintar(); return; }
        const trabajo = async () => {
            enviando = true;
            pintar();
            let enviados = 0, rechazados = 0;
            try {
                let csrf = await pedirEstado();
                if (!csrf) return;
                // Una por una y en el orden en que se registraron
                for (const item of lote) {
                    let { r, res } = await enviar(item, csrf);
                    if (tokenVencido(res)) {           // token vencido: se pide uno nuevo y se reintenta una vez
                        csrf = await pedirEstado();
                        if (!csrf) break;
                        ({ r, res } = await enviar(item, csrf));
                    }
                    if (necesitaSesion(r, res)) { pideSesion = true; break; }
                    if (res && res.ok) {
                        await borrarItem(item.seq);
                        enviados++;
                    } else if (res && res.definitivo) {
                        item.estado = 'error';
                        item.error = res.mensaje || 'El servidor rechazó la operación.';
                        await guardarItem(item);
                        rechazados++;
                    } else {
                        break;                         // falla del servidor o de la red: se reintenta más tarde
                    }
                    await pintar();
                }
            } catch (e) {
                /* sin señal a mitad del envío: lo que falta sigue en la cola */
            } finally {
                enviando = false;
                await pintar();
            }
            if (enviados) {
                apexToast('success', enviados === 1 ? 'Se envió 1 operación guardada en el celular.' : 'Se enviaron ' + enviados + ' operaciones guardadas en el celular.', 'Sincronizado');
                if (ES_RUTA && !document.querySelector('.modal.show') && !document.querySelector('#listaRuta.ordenando')) {
                    setTimeout(() => window.location.reload(), 1800);   // la ruta se recarga con los datos reales
                }
            }
            if (rechazados) apexToast('danger', 'El servidor rechazó ' + rechazados + ' operación(es). Revísalas en la ruta.', 'Revisar');
        };
        // Evita que dos pestañas envíen a la vez (el servidor igual descarta duplicados)
        if (navigator.locks) await navigator.locks.request('apex-sincronizar', { ifAvailable: true }, (lock) => lock && trabajo());
        else await trabajo();
    }

    // ---------------------------------------------------------------- interfaz
    const pill = document.getElementById('estadoConexion');
    const panel = document.getElementById('pendientesSync');
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    async function pintar() {
        if (!USUARIO) return;
        let items = [];
        try { items = await mios(); } catch (e) { return; }
        const pendientes = items.filter((i) => i.estado === 'pendiente').length;
        const errores = items.length - pendientes;
        const enLinea = navigator.onLine;

        if (pill) {
            let txt = '', cls = '';
            if (!enLinea) { txt = '<i class="bi bi-wifi-off"></i> Sin conexión' + (items.length ? ' · ' + items.length + '<span class="d-none d-sm-inline"> guardada(s)</span>' : ''); cls = 'sin-red'; }
            else if (enviando) { txt = '<span class="spinner-border spinner-border-sm"></span> Enviando…'; cls = 'enviando'; }
            else if (pideSesion && items.length) { txt = '<i class="bi bi-person-lock"></i> Ingresa para enviar ' + items.length; cls = 'error'; }
            else if (errores) { txt = '<i class="bi bi-exclamation-triangle"></i> ' + errores + ' por revisar'; cls = 'error'; }
            else if (pendientes) { txt = '<i class="bi bi-cloud-arrow-up"></i> ' + pendientes + ' por enviar'; cls = 'pendiente'; }
            pill.innerHTML = txt;
            pill.className = 'btn btn-sm conexion-pill ' + cls + (txt ? '' : ' d-none');
        }
        const aviso = document.getElementById('avisoSinConexion');
        if (aviso) aviso.classList.toggle('d-none', enLinea);

        if (!ES_RUTA) return;
        pintarTarjetas(items);
        if (!panel) return;
        panel.classList.toggle('d-none', !items.length);
        panel.querySelector('[data-sync=cantidad]').textContent = items.length;
        panel.querySelector('[data-sync=login]').classList.toggle('d-none', !(pideSesion && enLinea));
        panel.querySelector('[data-sync=ahora]').disabled = enviando || !enLinea;
        panel.querySelector('[data-sync=lista]').innerHTML = items.map((i) => {
            const icono = { pago: 'cash', gestion: 'journal-text', orden: 'arrows-move' }[i.tipo] || 'dot';
            const estado = i.estado === 'error'
                ? '<div class="small text-danger"><i class="bi bi-exclamation-triangle"></i> ' + esc(i.error) + '</div>'
                : '<div class="small text-secondary">Guardado a las ' + esc(horaCorta(i.creadoEn)) + ' · pendiente de envío</div>';
            const acciones = (i.estado === 'error' ? '<button type="button" class="btn btn-sm btn-light border" data-reintentar="' + i.seq + '">Reintentar</button>' : '')
                + '<button type="button" class="btn btn-sm btn-light border text-danger" data-descartar="' + i.seq + '" aria-label="Descartar"><i class="bi bi-trash"></i></button>';
            return '<li class="list-group-item d-flex align-items-center gap-2"><i class="bi bi-' + icono + ' text-primary"></i>'
                + '<div class="min-w-0 me-auto"><div class="fw-semibold text-truncate">' + esc(i.resumen.texto || i.tipo) + '</div>' + estado + '</div>'
                + '<div class="d-flex gap-1">' + acciones + '</div></li>';
        }).join('');
    }

    let ordenAplicado = false;
    function pintarTarjetas(items) {
        const lista = document.getElementById('listaRuta');
        lista.querySelectorAll('.sync-badge').forEach((b) => b.remove());
        lista.querySelectorAll('.ruta-item.pendiente-sync').forEach((b) => b.classList.remove('pendiente-sync'));
        items.forEach((i) => {
            if (i.tipo === 'orden') return;
            const col = lista.querySelector('.ruta-col[data-credito="' + i.datos.credito_id + '"]');
            if (!col) return;
            const card = col.querySelector('.ruta-item');
            const b = document.createElement('div');
            b.className = 'sync-badge small mb-2 px-2 py-1 rounded-2 ' + (i.estado === 'error' ? 'tone-danger' : 'tone-warning');
            const qué = i.tipo === 'pago' ? 'Cobró ' + apexMoney(Number((i.datos.monto || '').replace(/\D/g, ''))) : 'Gestión registrada';
            b.innerHTML = '<i class="bi bi-' + (i.estado === 'error' ? 'exclamation-triangle' : 'cloud-arrow-up') + '"></i> '
                + esc(qué) + (i.estado === 'error' ? ' · no se pudo enviar' : ' · guardado en el celular, sin enviar');
            card.insertBefore(b, card.children[1] || null);
            if (i.tipo === 'pago') card.classList.add('pendiente-sync');
        });
        // Si hay un orden de ruta sin enviar, la copia guardada de la ruta se muestra ya en ese orden
        const orden = items.filter((i) => i.tipo === 'orden' && String(i.datos.cobrador_id) === lista.dataset.cobrador).pop();
        if (orden && !ordenAplicado && !lista.classList.contains('ordenando')) {
            ordenAplicado = true;
            const pos = new Map(String(orden.datos.clientes).split(',').map((id, k) => [id, k]));
            Array.from(lista.children)
                .sort((a, b) => (pos.get(a.dataset.cliente) ?? 1e9) - (pos.get(b.dataset.cliente) ?? 1e9))
                .forEach((el) => lista.appendChild(el));
            lista.querySelectorAll('.ruta-num').forEach((el, k) => { el.textContent = k + 1; });
        }
    }

    if (panel) {
        panel.addEventListener('click', async (ev) => {
            if (ev.target.closest('[data-sync=ahora]')) { sincronizar(); return; }
            const re = ev.target.closest('[data-reintentar]');
            const boton = re || ev.target.closest('[data-descartar]');
            if (!boton) return;
            const seq = Number(boton.dataset.reintentar || boton.dataset.descartar);
            const item = (await mios()).find((i) => i.seq === seq);
            if (!item) return;
            if (re) {
                item.estado = 'pendiente';
                item.error = null;
                await guardarItem(item);
                await pintar();
                sincronizar();
            } else {
                const ok = await apexConfirm({ titulo: 'Descartar lo guardado', tono: 'danger', boton: 'Descartar',
                    mensaje: '«' + (item.resumen.texto || item.tipo) + '» se borrará de este celular y NO llegará al sistema. '
                        + (item.tipo === 'pago' ? 'Si recibiste ese dinero tendrás que registrarlo de nuevo. ' : '') + '¿Continuar?' });
                if (!ok) return;
                await borrarItem(seq);
                await pintar();
            }
        });
    }

    // Al cerrar sesión: se borran las copias de páginas con datos de clientes; lo pendiente se conserva
    document.querySelectorAll('form[action$="auth/logout"]').forEach((form) => {
        form.addEventListener('submit', async (ev) => {
            if (form.dataset.confirmadoSync) { limpiarPaginas(); return; }
            ev.preventDefault();
            const n = USUARIO ? (await mios()).length : 0;
            if (n) {
                const ok = await apexConfirm({ titulo: 'Tienes ' + n + ' operación(es) sin enviar', tono: 'warning', boton: 'Cerrar sesión',
                    mensaje: 'Se conservan en este celular y se enviarán cuando vuelvas a ingresar con tu usuario y haya internet. ¿Cerrar sesión de todas formas?' });
                if (!ok) return;
            }
            limpiarPaginas();
            form.dataset.confirmadoSync = '1';
            form.submit();
        });
    });

    // ---------------------------------------------------------------- arranque
    if (!USUARIO) {
        // Pantalla de ingreso: la sesión terminó, se borran las copias de páginas
        limpiarPaginas();
        if (window.indexedDB) {
            todos().then((items) => {
                if (!items.length) return;
                const form = document.querySelector('form');
                if (!form) return;
                const a = document.createElement('div');
                a.className = 'alert alert-warning small';
                a.setAttribute('role', 'status');
                a.textContent = 'Hay ' + items.length + ' operación(es) de la ruta guardadas en este celular. Ingresa con el usuario que las registró para enviarlas.';
                form.parentNode.insertBefore(a, form);
            }).catch(() => {});
        }
        return;
    }
    if (!window.indexedDB) return;

    window.apexOffline = { encolar, sincronizar };
    window.addEventListener('online', () => { pintar(); programarEnvio(1000); });
    window.addEventListener('offline', pintar);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) programarEnvio(500); });
    setInterval(() => { if (navigator.onLine) sincronizar(); }, 30000);
    pintar().then(() => programarEnvio(800));

    // Con señal se guardan copias para consultar sin internet. Cada pantalla visitada se guarda sola (sw.js);
    // además, cada 30 minutos se guardan las pantallas principales y, desde la ruta, la ficha de cada cliente.
    if (navigator.onLine && !ES_COPIA) {
        window.addEventListener('load', () => {
            const recursos = performance.getEntriesByType('resource').map((e) => e.name)
                .filter((u) => /^https:\/\/(cdn\.jsdelivr\.net|fonts\.googleapis\.com|fonts\.gstatic\.com)\//.test(u) || u.startsWith(BASE + 'assets/'));
            const urls = [].concat(recursos);
            const hoja = document.getElementById('btnHoja');
            if (hoja) urls.push(hoja.href);                     // la hoja cambia con cada cobro: siempre fresca

            const clave = 'apex-copias-' + USUARIO + (ES_RUTA ? '-ruta' : '');
            let ultima = 0;
            try { ultima = Number(localStorage.getItem(clave)) || 0; } catch (e) { /* sin almacenamiento */ }
            if (Date.now() - ultima > 30 * 60 * 1000) {
                const comunes = ['', 'ruta', 'clientes', 'creditos', 'pagos', 'caja', 'ayuda'];
                const porRol = ROL === 'admin' ? ['cobradores', 'reportes'] : ['cobradores/ver/' + USUARIO];
                comunes.concat(porRol).forEach((p) => urls.push(BASE + p));
                if (ES_RUTA) {
                    document.querySelectorAll('#listaRuta .ruta-col').forEach((col) => {
                        urls.push(BASE + 'creditos/ver/' + col.dataset.credito, BASE + 'clientes/ver/' + col.dataset.cliente);
                    });
                }
                try { localStorage.setItem(clave, String(Date.now())); } catch (e) { /* sin almacenamiento */ }
            }
            avisarSW({ tipo: 'precache', urls: Array.from(new Set(urls)).filter((u) => u !== location.href) });
        });
    }

})();
