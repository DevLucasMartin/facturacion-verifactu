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

    // ─── API helper (jQuery deferred) ────────────────────────────────────────
    function api(url, opts = {}) {
        const ajaxOpts = {
            url,
            type:     opts.method      || 'GET',
            dataType: 'json',
        };
        if (opts.data)        ajaxOpts.data        = opts.data;
        if (opts.contentType) ajaxOpts.contentType = opts.contentType;

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

    function notify(message, type = 'info', duration = 4000) {
        const container = document.getElementById('toast-container');
        if (!container) { console.warn('[notify]', message); return; }

        const el = document.createElement('div');
        el.style.cssText = [
            'background:'  + (COLOR[type] || '#333'),
            'color:#fff',
            'padding:.65rem 1rem',
            'border-radius:6px',
            'font-size:.86rem',
            'max-width:340px',
            'box-shadow:0 2px 10px rgba(0,0,0,.25)',
            'word-break:break-word',
            'opacity:1',
            'transition:opacity .3s',
        ].join(';');
        el.textContent = message;
        container.appendChild(el);

        setTimeout(() => {
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 320);
        }, duration);
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

    // ─── API pública ──────────────────────────────────────────────────────────
    return { api, notify, showLoading, hideLoading, escapeHtml, formatCurrency };
})();
