/* APEX · Service worker: consultar el aplicativo sin internet.
 *
 *  - Estilos, scripts y fuentes: primero del celular (caché); si no, de la red.
 *  - Páginas: primero de la red y se guarda una copia. Sin señal se muestra la última copia guardada,
 *    con un aviso arriba ("copia guardada, puede no estar actualizada"). Si la red tarda más de 8 s
 *    y ya hay copia, se muestra la copia (la red, si responde, la actualiza para la próxima).
 *  - Las páginas con avisos de un solo uso (X-Apex-Copia: no) se vuelven a pedir limpias para guardarlas.
 *  - No se guardan: ingreso, sincronización, descargas (Excel, respaldo) ni nada que no sea HTML.
 *  - Una página nunca vista con señal muestra assets/offline.html con las páginas que sí están guardadas.
 *  - Las copias (tienen datos de clientes) se borran al cerrar sesión (mensaje "limpiar").
 *
 * Los cobros hechos sin conexión NO se guardan aquí: los guarda assets/js/offline.js en IndexedDB.
 */
'use strict';

const VERSION = 'apex-v2';
const ESTATICOS = VERSION + '-estaticos';
const PAGINAS = 'apex-paginas';
const BASE = new URL('./', self.location).pathname;           // ej: /ApexCobros/
const OFFLINE = BASE + 'assets/offline.html';
const CDN = ['https://cdn.jsdelivr.net', 'https://fonts.googleapis.com', 'https://fonts.gstatic.com'];
const ESPERA_RED_MS = 8000;
const MAX_PAGINAS = 300;
const NO_GUARDAR = /^(auth|sincronizar|respaldo)(\/|$)|\/(excel|exportar|descargar|simular|ordenar)(\/|$)|^ayuda\/ver(\/|$)/;

const esEstatico = (url) => CDN.includes(url.origin)
    || (url.origin === self.location.origin && url.pathname.startsWith(BASE + 'assets/'));
const esGuardable = (url) => url.origin === self.location.origin && url.pathname.startsWith(BASE)
    && !esEstatico(url) && !url.pathname.endsWith('.js') && !url.pathname.endsWith('.webmanifest')
    && !NO_GUARDAR.test(url.pathname.slice(BASE.length));

self.addEventListener('install', (ev) => {
    ev.waitUntil(caches.open(ESTATICOS).then((c) => c.add(OFFLINE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (ev) => {
    ev.waitUntil(caches.keys()
        .then((keys) => Promise.all(keys.filter((k) => k !== ESTATICOS && k !== PAGINAS).map((k) => caches.delete(k))))
        .then(() => self.clients.claim()));
});

self.addEventListener('fetch', (ev) => {
    const req = ev.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (req.mode === 'navigate') {
        ev.respondWith(navegar(ev, req, url));
    } else if (esEstatico(url)) {
        ev.respondWith(estatico(req));
    }
});

// ---------------------------------------------------------------- páginas
async function navegar(ev, req, url) {
    if (!esGuardable(url)) {
        try { return await fetch(req); } catch (e) { return paginaSinConexion(); }
    }
    const cache = await caches.open(PAGINAS);
    const exacta = await cache.match(req.url);
    const red = fetch(req).then((res) => { ev.waitUntil(guardarCopia(cache, req.url, res.clone())); return res; });
    ev.waitUntil(red.catch(() => null));
    try {
        // Con copia guardada no se espera indefinidamente: con señal muy débil se muestra la copia
        return exacta ? await conLimite(red, ESPERA_RED_MS) : await red;
    } catch (e) {
        // Sin la página exacta, la misma pantalla con otros filtros (ej: clientes?q=… → clientes)
        const copia = exacta || (await cache.match(req.url, { ignoreSearch: true }));
        return copia ? marcarCopia(copia) : paginaSinConexion();
    }
}

async function guardarCopia(cache, url, res) {
    if (!res.ok || res.redirected || !(res.headers.get('Content-Type') || '').includes('text/html')) return;
    if (res.headers.get('X-Apex-Copia') === 'no') {
        // Tenía avisos de un solo uso: se pide de nuevo, ya limpia (los avisos se consumieron)
        res = await fetch(url, { credentials: 'same-origin', cache: 'no-store' }).catch(() => null);
        if (!res || !res.ok || res.redirected || res.headers.get('X-Apex-Copia') === 'no') return;
    }
    await cache.delete(url);           // al volver a guardarla pasa al final (la más reciente)
    await cache.put(url, res);
    const claves = await cache.keys();
    for (const k of claves.slice(0, Math.max(0, claves.length - MAX_PAGINAS))) await cache.delete(k);
}

/** La copia se muestra con un aviso arriba y marcada para offline.js (meta apex-copia = 1). */
async function marcarCopia(res) {
    const fecha = res.headers.get('Date') ? new Date(res.headers.get('Date')) : null;
    const cuando = fecha && !isNaN(fecha) ? ' el ' + fecha.toLocaleString('es-CO', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '';
    const aviso = '<div role="status" style="position:sticky;top:0;z-index:2000;background:#fef3c7;color:#92400e;'
        + 'border-bottom:1px solid #fcd34d;padding:6px 12px;font:600 13px/1.4 system-ui,sans-serif;text-align:center">'
        + 'Sin conexión · copia guardada' + cuando + ' · puede no estar actualizada</div>';
    const html = (await res.text())
        .replace('<meta name="apex-copia" content="0">', '<meta name="apex-copia" content="1">')
        .replace(/<body([^>]*)>/i, (m) => m + aviso);
    return new Response(html, { status: 200, statusText: 'OK', headers: res.headers });
}

async function paginaSinConexion() {
    const res = await caches.match(OFFLINE);
    if (!res) return new Response('Sin conexión', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
    const cache = await caches.open(PAGINAS);
    const vistas = new Set();
    const enlaces = (await cache.keys()).reverse()
        .map((r) => new URL(r.url))
        .filter((u) => !u.search && !vistas.has(u.pathname) && vistas.add(u.pathname))
        .slice(0, 12)
        .map((u) => '<li><a href="' + u.pathname + '">' + nombrePagina(u.pathname.slice(BASE.length)) + '</a></li>')
        .join('');
    const html = (await res.text()).replaceAll('{{BASE}}', BASE)
        .replace('{{PAGINAS}}', enlaces || '<li>Todavía no hay pantallas guardadas. Ábrelas con internet para poder verlas sin señal.</li>');
    return new Response(html, { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}

function nombrePagina(ruta) {
    const nombres = { '': 'Panel', dashboard: 'Panel', ruta: 'Ruta de cobro', 'ruta/hoja': 'Hoja de ruta', clientes: 'Clientes',
        creditos: 'Créditos', pagos: 'Pagos', caja: 'Caja', cobradores: 'Cobradores y cartera', reportes: 'Reportes',
        usuarios: 'Usuarios', auditoria: 'Auditoría', configuracion: 'Configuración', ayuda: 'Ayuda', perfil: 'Mi perfil' };
    const r = ruta.replace(/\/$/, '');
    if (r in nombres) return nombres[r];
    const m = r.match(/^(clientes|creditos|cobradores|pagos)\/(ver|estado|pazysalvo|recibo)\/(\d+)$/);
    if (m) return ({ clientes: 'Cliente', creditos: 'Crédito', cobradores: 'Cartera del cobrador', pagos: 'Recibo' }[m[1]])
        + ({ estado: ' · estado de cuenta', pazysalvo: ' · paz y salvo' }[m[2]] || '') + ' #' + m[3];
    return r.replace(/[<>&"]/g, '');
}

// ---------------------------------------------------------------- estilos, scripts y fuentes
async function estatico(req) {
    const cache = await caches.open(ESTATICOS);
    const guardado = await cache.match(req);
    if (guardado) return guardado;
    const res = await fetch(req);
    if (res.ok || res.type === 'opaque') cache.put(req, res.clone());
    return res;
}

// ---------------------------------------------------------------- mensajes de offline.js
self.addEventListener('message', (ev) => {
    const msg = ev.data || {};
    if (msg.tipo === 'precache' && Array.isArray(msg.urls)) {
        ev.waitUntil(precache(msg.urls));
    } else if (msg.tipo === 'limpiar') {
        ev.waitUntil(caches.delete(PAGINAS));
    }
});

async function precache(urls) {
    const [estaticos, paginas] = await Promise.all([caches.open(ESTATICOS), caches.open(PAGINAS)]);
    // De a pocas para no saturar la conexión ni el servidor
    const lista = urls.slice(0, 120);
    for (let i = 0; i < lista.length; i += 4) {
        await Promise.all(lista.slice(i, i + 4).map(async (u) => {
            try {
                const url = new URL(u, self.location);
                if (esEstatico(url)) {
                    if (await estaticos.match(url.href)) return;
                    const res = await fetch(url, { mode: url.origin === self.location.origin ? 'same-origin' : 'cors' })
                        .catch(() => fetch(url, { mode: 'no-cors' }));
                    if (res.ok || res.type === 'opaque') await estaticos.put(url.href, res);
                } else if (esGuardable(url)) {
                    const res = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
                    await guardarCopia(paginas, url.href, res);   // si la sesión venció (redirige al login) no se guarda
                }
            } catch (e) { /* se intentará en la próxima visita */ }
        }));
    }
}

function conLimite(promesa, ms) {
    return Promise.race([promesa, new Promise((_, rej) => setTimeout(() => rej(new Error('tiempo')), ms))]);
}
