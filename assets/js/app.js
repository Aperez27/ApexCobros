/* APEX · utilidades de interfaz */
(function () {
    'use strict';

    // Menú lateral en móvil
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('toggleSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (sidebar && toggle) {
        toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
        backdrop && backdrop.addEventListener('click', () => sidebar.classList.remove('open'));
    }

    // Formato de miles en campos de dinero: 1500000 -> 1.500.000
    window.apexMoney = function (n) {
        return '$ ' + Math.round(Number(n) || 0).toLocaleString('es-CO');
    };
    document.querySelectorAll('input[data-money]').forEach((input) => {
        const fmt = () => {
            const digits = input.value.replace(/\D/g, '');
            input.value = digits ? Number(digits).toLocaleString('es-CO') : '';
        };
        input.addEventListener('input', fmt);
        fmt();
    });

    // Confirmación en formularios sensibles
    // ---------------------------------------------------------------- notificaciones (toasts)
    const cerrarToast = (t) => {
        if (!t || t.classList.contains('saliendo')) return;
        t.classList.add('saliendo');
        setTimeout(() => t.remove(), 260);
    };
    const programarToast = (t) => {
        const ms = Number(t.dataset.timeout || 6000);
        const barra = t.querySelector('.apex-toast-progress');
        let restante = ms, inicio = Date.now(), timer;
        const correr = () => {
            inicio = Date.now();
            timer = setTimeout(() => cerrarToast(t), restante);
            if (barra) {
                barra.style.transition = 'transform ' + restante + 'ms linear';
                requestAnimationFrame(() => { barra.style.transform = 'scaleX(0)'; });
            }
        };
        const pausar = () => {
            clearTimeout(timer);
            restante -= Date.now() - inicio;
            if (barra) {
                const escala = barra.getBoundingClientRect().width / t.getBoundingClientRect().width;
                barra.style.transition = 'none';
                barra.style.transform = 'scaleX(' + escala + ')';
            }
        };
        t.addEventListener('mouseenter', pausar);
        t.addEventListener('mouseleave', correr);
        t.addEventListener('focusin', pausar);
        t.querySelector('.apex-toast-close').addEventListener('click', () => cerrarToast(t));
        correr();
    };
    document.querySelectorAll('.apex-toast').forEach((t, i) => setTimeout(() => programarToast(t), i * 120));

    /** Muestra una notificación desde JavaScript: apexToast('success', 'Mensaje', 'Título opcional') */
    window.apexToast = function (tipo, mensaje, titulo) {
        const iconos = { success: 'check-circle-fill', info: 'info-circle-fill', warning: 'exclamation-triangle-fill', danger: 'x-octagon-fill' };
        const titulos = { success: 'Operación exitosa', info: 'Información', warning: 'Atención', danger: 'No se pudo completar' };
        let stack = document.getElementById('toastStack');
        if (!stack) {
            stack = document.createElement('div');
            stack.id = 'toastStack';
            stack.className = 'toast-stack';
            stack.setAttribute('aria-live', 'polite');
            document.body.appendChild(stack);
        }
        const t = document.createElement('div');
        t.className = 'apex-toast ' + (iconos[tipo] ? tipo : 'info');
        t.setAttribute('role', tipo === 'danger' || tipo === 'warning' ? 'alert' : 'status');
        t.dataset.timeout = tipo === 'danger' ? 9000 : 6000;
        t.innerHTML = '<div class="apex-toast-icon"><i class="bi"></i></div><div class="min-w-0"><div class="apex-toast-title"></div>'
            + '<div class="apex-toast-msg"></div></div><button type="button" class="apex-toast-close" aria-label="Cerrar notificación">'
            + '<i class="bi bi-x-lg"></i></button><div class="apex-toast-progress"></div>';
        t.querySelector('.apex-toast-icon i').classList.add('bi-' + (iconos[tipo] || iconos.info));
        t.querySelector('.apex-toast-title').textContent = titulo || titulos[tipo] || titulos.info;
        t.querySelector('.apex-toast-msg').textContent = mensaje;
        stack.appendChild(t);
        programarToast(t);
    };

    // ---------------------------------------------------------------- diálogo de confirmación
    /**
     * apexConfirm({ titulo, mensaje, boton, tono: 'danger'|'warning'|'primary', campo: 'Etiqueta del motivo' })
     * Devuelve una promesa: false si cancela, true (o el texto escrito si pidió un campo) si confirma.
     */
    window.apexConfirm = function (op) {
        return new Promise((resolve) => {
            if (!window.bootstrap) { resolve(window.confirm(op.mensaje || '¿Continuar?')); return; }
            const tono = op.tono || 'primary';
            const icono = { danger: 'exclamation-octagon', warning: 'exclamation-triangle', primary: 'question-circle' }[tono] || 'question-circle';
            const el = document.createElement('div');
            el.className = 'modal fade confirm-modal ' + tono;
            el.tabIndex = -1;
            el.setAttribute('aria-modal', 'true');
            el.setAttribute('role', 'dialog');
            el.innerHTML = '<div class="modal-dialog modal-dialog-centered" style="max-width:420px"><div class="modal-content">'
                + '<div class="modal-body"><div class="confirm-icon"><i class="bi"></i></div>'
                + '<div class="confirm-titulo" id="confirmTitulo"></div><p class="confirm-texto"></p></div>'
                + '<div class="modal-footer"><button type="button" class="btn btn-light border" data-accion="cancelar">Cancelar</button>'
                + '<button type="button" class="btn" data-accion="ok"></button></div></div></div>';
            el.setAttribute('aria-labelledby', 'confirmTitulo');
            el.querySelector('.confirm-icon i').classList.add('bi-' + icono);
            el.querySelector('.confirm-titulo').textContent = op.titulo || '¿Estás seguro?';
            el.querySelector('.confirm-texto').textContent = op.mensaje || '';
            const ok = el.querySelector('[data-accion=ok]');
            ok.textContent = op.boton || 'Confirmar';
            ok.classList.add(tono === 'danger' ? 'btn-danger' : tono === 'warning' ? 'btn-warning' : 'btn-primary');
            let campo = null;
            if (op.campo) {
                const wrap = document.createElement('div');
                wrap.className = 'confirm-campo';
                wrap.innerHTML = '<label class="form-label" for="confirmCampo"></label>'
                    + '<textarea class="form-control" id="confirmCampo" rows="2" maxlength="255"></textarea>'
                    + '<div class="invalid-feedback">Este dato es obligatorio.</div>';
                wrap.querySelector('label').textContent = op.campo;
                el.querySelector('.modal-body').appendChild(wrap);
                campo = wrap.querySelector('textarea');
            }
            document.body.appendChild(el);
            const modal = new bootstrap.Modal(el, { backdrop: 'static' });
            let resultado = false;
            ok.addEventListener('click', () => {
                if (campo) {
                    const v = campo.value.trim();
                    campo.classList.toggle('is-invalid', v === '');
                    if (v === '') { campo.focus(); return; }
                    resultado = v;
                } else {
                    resultado = true;
                }
                modal.hide();
            });
            el.querySelector('[data-accion=cancelar]').addEventListener('click', () => modal.hide());
            el.addEventListener('shown.bs.modal', () => (campo || ok).focus());
            el.addEventListener('hidden.bs.modal', () => { modal.dispose(); el.remove(); resolve(resultado); });
            modal.show();
        });
    };

    // Formularios que piden confirmación: data-confirm, data-confirm-title, data-confirm-btn,
    // data-confirm-tone y data-confirm-input (pide un motivo que se envía en el campo "motivo")
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            if (form.dataset.confirmado === '1') { delete form.dataset.confirmado; return; }
            e.preventDefault();
            e.stopImmediatePropagation();
            apexConfirm({
                titulo: form.dataset.confirmTitle,
                mensaje: form.dataset.confirm,
                boton: form.dataset.confirmBtn,
                tono: form.dataset.confirmTone || 'danger',
                campo: form.dataset.confirmInput,
            }).then((r) => {
                if (!r) return;
                if (typeof r === 'string') {
                    let input = form.querySelector('[name=motivo]');
                    if (!input) {
                        input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'motivo';
                        form.appendChild(input);
                    }
                    input.value = r;
                }
                form.dataset.confirmado = '1';
                form.requestSubmit ? form.requestSubmit() : form.submit();
            });
        });
    });

    // ---------------------------------------------------------------- menú lateral: degradados cuando hay más opciones
    const nav = document.querySelector('.side-nav');
    if (nav) {
        const fades = () => {
            const max = nav.scrollHeight - nav.clientHeight;
            nav.classList.toggle('fade-top', nav.scrollTop > 4);
            nav.classList.toggle('fade-bottom', max > 4 && nav.scrollTop < max - 4);
        };
        const activo = nav.querySelector('.side-link.active');
        if (activo && (activo.offsetTop + activo.offsetHeight > nav.clientHeight)) {
            nav.scrollTop = activo.offsetTop - nav.clientHeight / 2;
        }
        nav.addEventListener('scroll', fades, { passive: true });
        window.addEventListener('resize', fades);
        fades();
    }

    // Evita doble envío
    document.querySelectorAll('form[method=post]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            setTimeout(() => {
                if (e.defaultPrevented) return;
                form.querySelectorAll('button[type=submit], button:not([type])').forEach((b) => { b.disabled = true; });
            }, 0);
        });
    });

    // Seleccionar todos (asignación masiva)
    const all = document.getElementById('checkAll');
    if (all) {
        const boxes = document.querySelectorAll('input[name="clientes[]"]');
        const counter = document.getElementById('selCount');
        const update = () => {
            const n = [...boxes].filter((b) => b.checked).length;
            if (counter) counter.textContent = n;
            document.querySelectorAll('[data-needs-selection]').forEach((el) => el.classList.toggle('d-none', n === 0));
        };
        all.addEventListener('change', () => { boxes.forEach((b) => { b.checked = all.checked; }); update(); });
        boxes.forEach((b) => b.addEventListener('change', update));
        update();
    }

    // Medidor de fortaleza de contraseña (la validación real se hace en el servidor)
    document.querySelectorAll('input[data-strength]').forEach((input) => {
        const box = document.querySelector(input.dataset.strength);
        if (!box) return;
        const bar = box.querySelector('[data-bar]');
        const label = box.querySelector('[data-label]');
        const update = () => {
            const v = input.value;
            const req = {
                len: v.length >= 10,
                case: /\p{Lu}/u.test(v) && /\p{Ll}/u.test(v),
                num: /\d/.test(v),
                sym: /[^\p{L}\d]/u.test(v),
            };
            let score = Object.values(req).filter(Boolean).length;
            if (v.length >= 14 && score === 4) score = 5;
            Object.entries(req).forEach(([k, ok]) => {
                const li = box.querySelector('[data-req="' + k + '"]');
                li.classList.toggle('text-success', ok);
                li.querySelector('i').className = 'bi ' + (ok ? 'bi-check-circle-fill' : 'bi-circle');
            });
            const niveles = [['Escribe una contraseña', 0, ''], ['Muy débil', 20, 'bg-danger'], ['Débil', 40, 'bg-danger'],
                ['Regular', 60, 'bg-warning'], ['Buena', 80, 'bg-info'], ['Muy segura', 100, 'bg-success']];
            const [txt, pct, cls] = v ? niveles[Math.max(1, score)] : niveles[0];
            label.textContent = txt;
            bar.style.width = pct + '%';
            bar.className = 'progress-bar ' + cls;
        };
        input.addEventListener('input', update);
        update();
    });

    // Botones de imprimir (sin manejadores inline, compatibles con la CSP)
    document.querySelectorAll('[data-print]').forEach((b) => b.addEventListener('click', () => window.print()));

    // Modal de gestión de cobro
    const modalGestion = document.getElementById('modalGestion');
    if (modalGestion) {
        const sel = modalGestion.querySelector('[name=resultado]');
        const bloque = modalGestion.querySelector('[data-g=promesa]');
        const toggle = () => bloque.classList.toggle('d-none', sel.value !== 'promesa');
        sel.addEventListener('change', toggle);
        modalGestion.addEventListener('show.bs.modal', (ev) => {
            const b = ev.relatedTarget;
            if (!b || !b.dataset.credito) return;
            modalGestion.querySelector('[name=credito_id]').value = b.dataset.credito;
            modalGestion.querySelector('[data-g=cliente]').textContent = (b.dataset.cliente || '') + ' · ' + (b.dataset.codigo || '');
            const m = modalGestion.querySelector('[name=monto_promesa]');
            m.value = b.dataset.sugerido ? Number(b.dataset.sugerido).toLocaleString('es-CO') : '';
            toggle();
        });
        toggle();
    }

    // Formulario de cierre de caja: calcula la diferencia mientras se escribe
    document.querySelectorAll('form[data-cierre]').forEach((f) => {
        const input = f.querySelector('[name=entregado]');
        const out = f.querySelector('[data-diferencia]');
        const esperado = Number(f.dataset.cierre);
        const upd = () => {
            const v = Number((input.value || '').replace(/\D/g, ''));
            if (!input.value) { out.textContent = ''; return; }
            if (esperado < 0 && v === 0) {
                out.textContent = 'Reembolso al cobrador ' + apexMoney(-esperado);
                out.className = 'small fw-semibold text-info';
                return;
            }
            const d = v - esperado;
            out.textContent = d === 0 ? 'Cuadra exacto' : (d < 0 ? 'Faltante ' : 'Sobrante ') + apexMoney(Math.abs(d));
            out.className = 'small fw-semibold ' + (d === 0 ? 'text-success' : d < 0 ? 'text-danger' : 'text-info');
        };
        input.addEventListener('input', upd);
        upd();
    });

    // Modal de pago: carga datos del crédito desde el botón que lo abre
    const modalPago = document.getElementById('modalPago');
    if (modalPago) {
        modalPago.addEventListener('show.bs.modal', (ev) => {
            const b = ev.relatedTarget;
            if (!b || !b.dataset.credito) return;
            modalPago.querySelector('[name=credito_id]').value = b.dataset.credito;
            modalPago.querySelector('[data-f=cliente]').textContent = b.dataset.cliente || '';
            modalPago.querySelector('[data-f=codigo]').textContent = b.dataset.codigo || '';
            modalPago.querySelector('[data-f=saldo]').textContent = apexMoney(b.dataset.saldo);
            const monto = modalPago.querySelector('[name=monto]');
            monto.value = Number(b.dataset.sugerido || 0).toLocaleString('es-CO');
            monto.dataset.max = b.dataset.saldo;
        });
        modalPago.addEventListener('shown.bs.modal', () => {
            const m = modalPago.querySelector('[name=monto]');
            m.focus();
            m.select();
        });
        modalPago.querySelectorAll('[data-quick]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const m = modalPago.querySelector('[name=monto]');
                const v = btn.dataset.quick === 'saldo' ? m.dataset.max : btn.dataset.quick;
                m.value = Number(v).toLocaleString('es-CO');
            });
        });
    }
})();
