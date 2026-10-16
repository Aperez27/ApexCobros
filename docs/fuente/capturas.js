// Capturas para los manuales de APEX (instancia temporal ApexDocs)
const { chromium } = require('playwright-core');
const OUT = process.argv[2];
const B = 'http://localhost/ApexDocs/';
const IDS = { mora: 22, renov: 46, pagado: 3, alDia: 4, cliMora: 18, cliRenov: 6, cob: 2, pago: 470 };

(async () => {
  const b = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe' });
  const errs = [];
  const nueva = async (w, h, scale = 1) => {
    const ctx = await b.newContext({ viewport: { width: w, height: h }, deviceScaleFactor: scale });
    const p = await ctx.newPage();
    p.on('pageerror', e => errs.push(p.url() + ' :: ' + e.message));
    return p;
  };
  const login = async (p, u, c) => {
    await p.goto(B + 'auth/login');
    await p.fill('#usuario', u); await p.fill('#password', c);
    await Promise.all([p.waitForNavigation(), p.click('button.btn-primary')]);
  };
  const ir = async (p, url) => { await p.goto(B + url, { waitUntil: 'networkidle' }); await p.waitForTimeout(500); };
  const sinToasts = async (p) => p.evaluate(() => document.querySelectorAll('.apex-toast').forEach(t => t.remove()));
  const foto = async (p, nombre, opts = {}) => { await p.screenshot({ path: `${OUT}/${nombre}.png`, ...opts }); };

  // ---------------- Escritorio (administrador)
  const p = await nueva(1440, 900);
  await ir(p, 'auth/login'); await foto(p, 'login');
  await p.fill('#usuario', 'admin'); await p.fill('#password', 'clave-equivocada');
  await Promise.all([p.waitForNavigation(), p.click('button.btn-primary')]);
  await foto(p, 'login_error', { clip: { x: 900, y: 150, width: 480, height: 480 } });
  await login(p, 'admin', 'Admin123*');
  await p.waitForTimeout(700); await foto(p, 'toast_bienvenida', { clip: { x: 1000, y: 0, width: 440, height: 110 } });
  await sinToasts(p);
  await foto(p, 'dashboard'); await foto(p, 'dashboard_completo', { fullPage: true });
  await foto(p, 'menu_lateral', { clip: { x: 0, y: 0, width: 256, height: 900 } });

  await ir(p, 'clientes'); await foto(p, 'clientes');
  await p.check('#checkAll'); await p.waitForTimeout(300);
  await foto(p, 'clientes_asignacion', { clip: { x: 256, y: 260, width: 1184, height: 120 } });
  await p.uncheck('#checkAll');
  await ir(p, 'clientes/crear'); await foto(p, 'cliente_form', { fullPage: true });
  await ir(p, 'clientes/ver/' + IDS.cliRenov); await foto(p, 'cliente_ficha', { fullPage: true });
  await ir(p, 'clientes/ver/' + IDS.cliMora); await foto(p, 'cliente_mora', { fullPage: true });

  await ir(p, 'creditos'); await foto(p, 'creditos');
  await ir(p, 'creditos/crear');
  await p.type('#buscarCliente', 'a'); await p.fill('#buscarCliente', '');
  await p.locator('#cliente_id option:not([disabled])').nth(3).evaluate(o => { o.selected = true; o.parentElement.dispatchEvent(new Event('change', { bubbles: true })); });
  await p.type('#monto', '500000'); await p.waitForTimeout(1000);
  await foto(p, 'credito_crear');
  await ir(p, 'creditos/ver/' + IDS.mora); await foto(p, 'credito_ver'); await foto(p, 'credito_ver_completo', { fullPage: true });
  await p.click('button:has-text("Registrar pago")'); await p.waitForTimeout(600);
  await foto(p, 'modal_pago');
  await p.keyboard.press('Escape'); await p.waitForTimeout(400);
  await p.click('button:has-text("No pagó / gestión")'); await p.waitForTimeout(500);
  await p.selectOption('#gResultado', 'promesa'); await p.waitForTimeout(300);
  await foto(p, 'modal_gestion');
  await p.keyboard.press('Escape'); await p.waitForTimeout(400);
  await p.click('button:has-text("Documentos")'); await p.waitForTimeout(300);
  await foto(p, 'menu_documentos', { clip: { x: 900, y: 80, width: 540, height: 260 } });
  await p.keyboard.press('Escape');
  await p.locator('form[data-confirm-input] button').first().click(); await p.waitForTimeout(600);
  await foto(p, 'confirmar_anulacion');
  await p.click('.confirm-modal [data-accion=cancelar]'); await p.waitForTimeout(400);

  await ir(p, 'creditos/renovar/' + IDS.alDia);
  await p.type('#monto', '900000'); await p.waitForTimeout(1000);
  await foto(p, 'renovar');
  await ir(p, 'creditos/ver/' + IDS.renov); await foto(p, 'credito_renovado');
  await ir(p, 'creditos/estado/' + IDS.mora); await foto(p, 'estado_cuenta', { fullPage: true });
  await ir(p, 'creditos/pazysalvo/' + IDS.pagado); await foto(p, 'paz_y_salvo', { fullPage: true });
  await ir(p, 'pagos/recibo/' + IDS.pago); await foto(p, 'recibo', { fullPage: true });

  await ir(p, 'pagos?desde=' + new Date(Date.now() - 6 * 864e5).toISOString().slice(0, 10)); await foto(p, 'pagos');
  await ir(p, 'ruta'); await foto(p, 'ruta_admin');
  await ir(p, 'caja'); await foto(p, 'caja_admin'); await foto(p, 'caja_admin_completa', { fullPage: true });
  await ir(p, 'cobradores'); await foto(p, 'cobradores', { fullPage: true });
  await ir(p, 'cobradores/ver/' + IDS.cob); await foto(p, 'cobrador_ver', { fullPage: true });
  await ir(p, 'reportes?desde=2026-06-01'); await foto(p, 'reportes'); await foto(p, 'reportes_completo', { fullPage: true });
  await ir(p, 'auditoria?desde=2026-01-01'); await foto(p, 'auditoria');
  await ir(p, 'respaldo'); await foto(p, 'respaldo');
  await ir(p, 'usuarios'); await foto(p, 'usuarios');
  await ir(p, 'usuarios/crear'); await p.type('#password', 'Ruta.Norte#2026'); await foto(p, 'usuario_form', { fullPage: true });
  await ir(p, 'configuracion'); await foto(p, 'configuracion');
  await ir(p, 'perfil'); await foto(p, 'perfil');

  // Cambio de contraseña obligatorio
  const q = await nueva(1440, 900);
  await login(q, 'demo.andres', 'Ruta#Segura2026'); await q.waitForTimeout(600);
  await foto(q, 'cambio_clave_obligatorio');
  await q.type('#nueva', 'Andres.Ruta#2026'); await q.waitForTimeout(300);
  await foto(q, 'medidor_clave', { clip: { x: 760, y: 150, width: 660, height: 560 } });

  // ---------------- Celular (cobrador)
  const m = await nueva(390, 844, 2);
  await ir(m, 'auth/login'); await foto(m, 'movil_login');
  await login(m, 'demo.carlos', 'Ruta#Segura2026'); await m.waitForTimeout(700);
  await sinToasts(m);
  await foto(m, 'movil_ruta'); await foto(m, 'movil_ruta_completa', { fullPage: true });
  await m.click('#toggleSidebar'); await m.waitForTimeout(500); await foto(m, 'movil_menu');
  await m.click('#sidebarBackdrop', { force: true }).catch(() => {}); await ir(m, 'ruta');
  await m.locator('button:has-text("Cobrar")').first().click(); await m.waitForTimeout(700); await foto(m, 'movil_cobrar');
  await m.keyboard.press('Escape'); await m.waitForTimeout(400);
  await m.locator('button:has-text("No pagó")').first().click(); await m.waitForTimeout(500);
  await m.selectOption('#gResultado', 'no_estaba'); await foto(m, 'movil_no_pago');
  await m.keyboard.press('Escape'); await m.waitForTimeout(400);
  await ir(m, 'caja'); await foto(m, 'movil_caja', { fullPage: true });
  await ir(m, 'clientes'); await foto(m, 'movil_clientes');
  await ir(m, 'dashboard'); await foto(m, 'movil_panel');
  await ir(m, 'pagos/recibo/445'); await foto(m, 'movil_recibo', { fullPage: true });

  console.log('Capturas listas. Errores JS:', errs.length ? errs : 'ninguno');
  await b.close();
})();
