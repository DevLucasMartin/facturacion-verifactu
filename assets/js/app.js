/**
 * App — utilidades globales del módulo de facturación
 * Expone: App.api, App.notify, App.showLoading, App.hideLoading,
 *         App.escapeHtml, App.formatCurrency
 */
const App = (() => {
    'use strict';

    // ─── Normalización de respuestas ─────────────────────────────────────────
    // El servidor devuelve { ok, data, message }.
    // Response::paginated() anida la paginación en data.items/total/total_pages.
    // El JS de las vistas espera { success, data:[], total, pages }.
    function normalizeResponse(data) {
        if (!data || typeof data !== 'object') return data;

        // ok → success
        if (data.ok !== undefined && data.success === undefined) {
            data.success = !!data.ok;
        }

        // Aplanar paginación: { data: { items, total, page, per_page, total_pages } }
        if (data.data && Array.isArray(data.data.items)) {
            data.total  = data.data.total;
            data.pages  = data.data.total_pages;
            data.page   = data.data.page;
            data.data   = data.data.items;
        }

        return data;
    }

    // ─── CSRF token ──────────────────────────────────────────────────────────
    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    // ─── API helper (jQuery deferred) ────────────────────────────────────────
    function api(url, opts = {}) {
        const method = opts.method || 'GET';
        const ajaxOpts = {
            url,
            type:     method,
            dataType: 'json',
        };
        if (opts.data)        ajaxOpts.data        = opts.data;
        if (opts.contentType) ajaxOpts.contentType = opts.contentType;

        if (!['GET', 'HEAD'].includes(method.toUpperCase())) {
            ajaxOpts.headers = { 'X-CSRF-Token': getCsrfToken() };
        }

        const def = $.Deferred();
        $.ajax(ajaxOpts)
            .done(raw  => def.resolve(normalizeResponse(raw)))
            .fail((xhr, status, err) => {
                let parsed = null;
                try { parsed = JSON.parse(xhr.responseText); } catch (_) {}
                def.reject(xhr, status, err, parsed);
            });
        return def;
    }

    // ─── Notificaciones toast ────────────────────────────────────────────────
    const COLOR = {
        success:  '#198754',
        danger:   '#dc3545',
        warning:  '#e6a817',
        info:     '#0a7a8f',
        primary:  '#0d6efd',
        secondary:'#6c757d',
    };

    const ALERT_TYPE = {
        success: 'success',
        danger:  'danger',
        warning: 'warning',
        info:    'info',
        primary: 'primary',
    };

    function notify(message, type = 'info', duration) {
        const container = document.getElementById('toast-container');
        if (!container) { console.warn('[notify]', message); return; }

        if (duration === undefined) {
            duration = (type === 'danger' || type === 'warning') ? 7000 : 4000;
        }

        const el = document.createElement('div');
        if (type === 'danger') el.className = 'toast-danger';
        el.style.cssText = [
            'background:'  + (COLOR[type] || '#333'),
            'color:#fff',
            'padding:.7rem 1.1rem',
            'border-radius:6px',
            'font-size:.9rem',
            'max-width:420px',
            'box-shadow:0 4px 14px rgba(0,0,0,.3)',
            'word-break:break-word',
            'opacity:1',
            'transition:opacity .3s',
            'cursor:pointer',
        ].join(';');
        el.textContent = message;
        el.title = 'Clic para cerrar';
        el.onclick = () => {
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 320);
        };
        container.appendChild(el);

        setTimeout(() => {
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 320);
        }, duration);

        const panel = document.getElementById('accionesMsg');
        if (panel) {
            const alertType = ALERT_TYPE[type] || 'secondary';
            panel.className = 'alert alert-' + alertType + ' mt-2 mb-0 py-2 small';
            panel.style.display = '';
            panel.textContent = message;
        }
    }

    // ─── Loading overlay ─────────────────────────────────────────────────────
    function showLoading() {
        const el = document.getElementById('loading-overlay');
        if (el) el.classList.add('active');
    }

    function hideLoading() {
        const el = document.getElementById('loading-overlay');
        if (el) el.classList.remove('active');
    }

    // ─── Utilidades ─────────────────────────────────────────────────────────
    function escapeHtml(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g,  '&amp;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;')
            .replace(/"/g,  '&quot;')
            .replace(/'/g,  '&#039;');
    }

    function formatCurrency(amount, symbol = '€') {
        const n = parseFloat(amount) || 0;
        return new Intl.NumberFormat('es-ES', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(n) + '\u00a0' + symbol;
    }

    // ─── Modal confirmación NIF ──────────────────────────────────────────────
    // opciones: { clienteCodigo, apiBase }
    // Devuelve el NIF confirmado (string) o null si se cancela.
    // Si el NIF cambia y se pasan opciones, actualiza la BD antes de resolver.
    function confirmarNIF(nifActual, opciones) {
        return new Promise(function (resolve) {
            const existing = document.getElementById('modalConfirmarNIF');
            if (existing) {
                try { bootstrap.Modal.getInstance(existing)?.hide(); } catch (_) {}
                existing.remove();
            }

            const esc = escapeHtml;
            document.body.insertAdjacentHTML('beforeend', `
            <div class="modal fade" id="modalConfirmarNIF" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-dialog-centered" style="max-width:380px">
                    <div class="modal-content">
                        <div class="modal-header py-2">
                            <h6 class="modal-title mb-0">
                                <i class="bi bi-person-badge me-2 text-primary"></i>Verificar NIF del cliente
                            </h6>
                        </div>
                        <div class="modal-body pb-2">
                            <p class="text-muted small mb-2">
                                Comprueba que el NIF del cliente es correcto antes de continuar.
                            </p>
                            <input type="text" id="inputConfirmarNIF"
                                class="form-control form-control-lg text-uppercase fw-semibold text-center"
                                value="${esc(nifActual)}" placeholder="NIF del cliente"
                                autocomplete="off" spellcheck="false" maxlength="20">
                            <div id="errorConfirmarNIF" class="invalid-feedback d-none" style="display:block !important"></div>
                        </div>
                        <div class="modal-footer py-2 justify-content-between">
                            <button type="button" class="btn btn-secondary" id="btnCancelarConfirmarNIF">
                                <i class="bi bi-x-lg me-1"></i>Cancelar
                            </button>
                            <button type="button" class="btn btn-primary" id="btnAceptarConfirmarNIF">
                                <i class="bi bi-check-lg me-1"></i>Confirmar
                            </button>
                        </div>
                    </div>
                </div>
            </div>`);

            const modalEl = document.getElementById('modalConfirmarNIF');
            const bsModal = new bootstrap.Modal(modalEl);
            const inputEl = document.getElementById('inputConfirmarNIF');
            const errEl   = document.getElementById('errorConfirmarNIF');
            const btnOk   = document.getElementById('btnAceptarConfirmarNIF');

            let resolved = false;
            function finish(nif) {
                if (resolved) return;
                resolved = true;
                bsModal.hide();
                resolve(nif);
            }

            function mostrarError(msg) {
                if (!errEl) return;
                errEl.textContent = msg;
                errEl.classList.remove('d-none');
                if (inputEl) inputEl.classList.add('is-invalid');
            }
            function limpiarError() {
                if (!errEl) return;
                errEl.classList.add('d-none');
                if (inputEl) inputEl.classList.remove('is-invalid');
            }

            btnOk.onclick = async function () {
                const nifNuevo = (inputEl?.value || '').trim().toUpperCase();
                limpiarError();

                if (nifNuevo === nifActual || !opciones?.clienteCodigo || !opciones?.apiBase) {
                    finish(nifNuevo);
                    return;
                }

                btnOk.disabled = true;
                btnOk.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

                try {
                    const resp = await fetch(
                        opciones.apiBase + '/clientes.php/' + encodeURIComponent(opciones.clienteCodigo) + '/nif',
                        {
                            method: 'PATCH',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                            body: JSON.stringify({ NIF: nifNuevo }),
                        }
                    );
                    const json = await resp.json().catch(() => ({}));
                    if (!resp.ok || json.ok === false || json.success === false) {
                        mostrarError(json.message || 'NIF no válido. Revisa el formato.');
                        btnOk.disabled = false;
                        btnOk.innerHTML = '<i class="bi bi-check-lg me-1"></i>Confirmar';
                        return;
                    }
                    finish(nifNuevo);
                } catch (_) {
                    mostrarError('Error de conexión al actualizar el NIF.');
                    btnOk.disabled = false;
                    btnOk.innerHTML = '<i class="bi bi-check-lg me-1"></i>Confirmar';
                }
            };

            document.getElementById('btnCancelarConfirmarNIF').onclick = function () {
                finish(null);
            };

            if (inputEl) {
                inputEl.addEventListener('input', function () {
                    this.value = this.value.toUpperCase();
                    limpiarError();
                });
                inputEl.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') btnOk.click();
                    if (e.key === 'Escape') finish(null);
                });
            }

            modalEl.addEventListener('hidden.bs.modal', function () {
                if (!resolved) finish(null);
                modalEl.remove();
            });

            bsModal.show();
            setTimeout(function () { if (inputEl) inputEl.select(); }, 350);
        });
    }

    // ─── Diálogos (SweetAlert2) ─────────────────────────────────────────────────
    // Confirmación. Devuelve una Promise<boolean> que resuelve true si se acepta.
    function confirm(opts) {
        opts = opts || {};
        if (typeof Swal === 'undefined') {
            // Fallback al diálogo nativo si SweetAlert2 no está cargado
            return Promise.resolve(window.confirm(opts.text || opts.title || '¿Confirmar?'));
        }
        return Swal.fire({
            icon:               opts.icon || 'question',
            title:              opts.title || '¿Confirmar?',
            text:               opts.text || '',
            showCancelButton:   true,
            confirmButtonText:  opts.confirmText || 'Aceptar',
            cancelButtonText:   opts.cancelText || 'Cancelar',
            confirmButtonColor: opts.confirmColor || (opts.danger ? '#dc3545' : '#198754'),
            cancelButtonColor:  '#6c757d',
            reverseButtons:     true
        }).then(function (result) { return result.isConfirmed === true; });
    }

    // Aviso simple. Devuelve una Promise que resuelve al cerrar.
    function alert(message, opts) {
        opts = opts || {};
        if (typeof Swal === 'undefined') {
            window.alert(message);
            return Promise.resolve();
        }
        return Swal.fire({
            icon:               opts.icon || 'info',
            title:              opts.title || '',
            text:               message || '',
            confirmButtonText:  opts.confirmText || 'Entendido',
            confirmButtonColor: opts.confirmColor || '#0d6efd'
        });
    }

    // ─── API pública ──────────────────────────────────────────────────────────
    return { api, notify, showLoading, hideLoading, escapeHtml, formatCurrency, confirmarNIF, confirm, alert };
})();

// ─── Excel Background Export via Service Worker ──────────────────────────────
const ExcelExport = (() => {
    'use strict';

    const CACHE_NAME = 'excel-exports-v1';
    const LS_KEY     = 'excelExportPending';
    const SW_PATH    = '/SistemaGestionFacturas/excel-sw.js';
    const SW_SCOPE   = '/SistemaGestionFacturas/';

    function showIndicator() {
        const el = document.getElementById('exportIndicator');
        if (el) el.style.display = 'block';
    }

    function hideIndicator() {
        const el = document.getElementById('exportIndicator');
        if (el) el.style.display = 'none';
    }

    async function triggerDownload(filename) {
        try {
            const cache    = await caches.open(CACHE_NAME);
            const response = await cache.match('pending-export');
            if (!response) return; // otra pestaña ya lo recogió
            const blob = await response.blob();
            await cache.delete('pending-export');
            const url = URL.createObjectURL(blob);
            const a   = document.createElement('a');
            a.href     = url;
            a.download = filename || 'export.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            setTimeout(() => URL.revokeObjectURL(url), 10000);
        } catch (e) {
            console.error('[ExcelExport] Error al recuperar el archivo:', e);
        }
    }

    function init() {
        if (!('serviceWorker' in navigator)) return;

        // Mostrar indicador si había una exportación en curso al navegar
        if (localStorage.getItem(LS_KEY)) showIndicator();

        navigator.serviceWorker.register(SW_PATH, { scope: SW_SCOPE })
            .catch(err => console.error('[ExcelExport] SW no registrado:', err));

        navigator.serviceWorker.addEventListener('message', async event => {
            const { type, filename, error } = event.data || {};

            if (type === 'EXPORT_STARTED') {
                localStorage.setItem(LS_KEY, filename || '1');
                showIndicator();
            } else if (type === 'EXPORT_DONE') {
                localStorage.removeItem(LS_KEY);
                hideIndicator();
                await triggerDownload(filename);
                App.notify('Excel generado y descargado correctamente', 'success');
            } else if (type === 'EXPORT_ERROR') {
                localStorage.removeItem(LS_KEY);
                hideIndicator();
                App.notify('Error al generar el Excel: ' + (error || 'Error desconocido'), 'danger');
            }
        });
    }

    function start(url, filename) {
        const ctrl = navigator.serviceWorker?.controller;
        if (!ctrl) {
            // SW aún no controla la página (primera carga) — descarga directa
            const a   = document.createElement('a');
            a.href     = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            return;
        }
        ctrl.postMessage({ type: 'START_EXPORT', url, filename });
    }

    document.addEventListener('DOMContentLoaded', init);

    return { start };
})();
