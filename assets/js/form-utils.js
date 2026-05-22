/**
 * FormUtils — utilidades para formularios de edición/creación
 * Expone: FormUtils.escapeHtml, FormUtils.debounce, FormUtils.formatCurrency,
 *         FormUtils.makeApiClient(baseUrl)
 */
const FormUtils = (() => {
    'use strict';

    // ─── Compartidas con App ──────────────────────────────────────────────────
    const escapeHtml = App.escapeHtml;
    const formatCurrency = App.formatCurrency;

    // ─── Debounce ─────────────────────────────────────────────────────────────
    function debounce(fn, delay = 300) {
        let timer;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    // ─── Normalizar respuesta (misma lógica que App) ──────────────────────────
    function normalizeResponse(data) {
        if (!data || typeof data !== 'object') return data;
        if (data.ok !== undefined && data.success === undefined) {
            data.success = !!data.ok;
        }
        if (data.data && Array.isArray(data.data.items)) {
            data.total = data.data.total;
            data.pages = data.data.total_pages;
            data.page  = data.data.page;
            data.data  = data.data.items;
        }
        return data;
    }

    // ─── Cliente de API async/await ───────────────────────────────────────────
    /**
     * Devuelve { apiGet, apiSend } ligados a baseUrl.
     * apiGet(path)                → Promise<normalizedResponse>
     * apiSend(path, body, method) → Promise<normalizedResponse>
     */
    function makeApiClient(baseUrl) {
        const base = baseUrl.replace(/\/$/, '');

        async function apiGet(path) {
            const res = await fetch(base + path, {
                headers: { 'Accept': 'application/json' },
            });
            const json = await res.json();
            return normalizeResponse(json);
        }

        function getCsrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        }

        async function apiSend(path, body = {}, method = 'POST') {
            const res = await fetch(base + path, {
                method,
                headers: {
                    'Content-Type':  'application/json',
                    'Accept':        'application/json',
                    'X-CSRF-Token':  getCsrfToken(),
                },
                body: JSON.stringify(body),
            });
            const json = await res.json();
            return normalizeResponse(json);
        }

        return { apiGet, apiSend };
    }

    return { escapeHtml, formatCurrency, debounce, makeApiClient };
})();
