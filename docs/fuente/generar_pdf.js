/**
 * APEX · Genera los manuales en PDF a partir de los HTML de esta carpeta.
 *
 * Requisitos: Node.js y el paquete playwright-core (npm i playwright-core) y Microsoft Edge o Chrome instalado.
 *   node docs/fuente/generar_pdf.js
 * Los PDF quedan en la carpeta docs/.
 */
const path = require('path');
const fs = require('fs');
const { chromium } = require('playwright-core');

const MANUALES = [
    ['01_manual_administrador.html', 'APEX_01_Manual_del_Administrador.pdf', 'Manual del administrador'],
    ['02_manual_cobrador.html', 'APEX_02_Manual_del_Cobrador.pdf', 'Manual del cobrador'],
    ['03_guia_rapida.html', 'APEX_03_Guia_de_Inicio_Rapido.pdf', 'Guía de inicio rápido'],
    ['04_procedimientos.html', 'APEX_04_Procedimientos_Operativos.pdf', 'Procedimientos operativos'],
    ['05_instalacion.html', 'APEX_05_Manual_de_Instalacion_y_Produccion.pdf', 'Instalación y puesta en producción'],
    ['06_manual_tecnico.html', 'APEX_06_Manual_Tecnico.pdf', 'Manual técnico'],
    ['07_seguridad.html', 'APEX_07_Manual_de_Seguridad.pdf', 'Manual de seguridad'],
];

const NAVEGADORES = [
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
    'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
];

(async () => {
    const exe = NAVEGADORES.find((p) => fs.existsSync(p));
    const browser = await chromium.launch(exe ? { executablePath: exe } : {});
    const soloUno = process.argv[2];
    for (const [html, pdf, titulo] of MANUALES) {
        if (soloUno && !html.startsWith(soloUno)) continue;
        const page = await browser.newPage();
        const errores = [];
        page.on('pageerror', (e) => errores.push(e.message));
        page.on('requestfailed', (r) => errores.push('No se cargó: ' + r.url()));
        await page.goto('file:///' + path.join(__dirname, html).replace(/\\/g, '/'), { waitUntil: 'networkidle' });
        await page.emulateMedia({ media: 'print' });
        const destino = path.join(__dirname, '..', pdf);
        await page.pdf({
            path: destino, format: 'Letter', printBackground: true, preferCSSPageSize: true, displayHeaderFooter: true,
            headerTemplate: '<div></div>',
            footerTemplate: `<div style="width:100%;font-size:7.5pt;color:#64748b;padding:0 16mm;display:flex;justify-content:space-between;font-family:Segoe UI,sans-serif">
                <span>APEX · ${titulo}</span><span>Página <span class="pageNumber"></span> de <span class="totalPages"></span></span></div>`,
        });
        const kb = Math.round(fs.statSync(destino).size / 1024);
        console.log(`${pdf}  (${kb} KB)${errores.length ? '  ⚠ ' + errores.join(' | ') : ''}`);
        await page.close();
    }
    await browser.close();
})();
