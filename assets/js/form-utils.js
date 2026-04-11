/**
 * FormUtils - Utilidades para formularios
 * Usado por nuevo.js y editar.js
 */
const FormUtils = (() => {

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function debounce(fn, delay) {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), delay);
        };
    }

    function formatCurrency(value) {
        return (parseFloat(value) || 0).toLocaleString('es-ES', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }) + ' €';
    }

    /**
     * Devuelve un cliente API para el baseUrl indicado.
     * apiGet(path)           → GET  baseUrl + path, devuelve Promise<json>
     * apiSend(path, method, data) → POST/PUT baseUrl + path, devuelve Promise<json>
     */
    function makeApiClient(baseUrl) {

        async function apiGet(path) {
            const res = await fetch(baseUrl + path);
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                throw new Error(err.message || `Error ${res.status}`);
            }
            return res.json();
        }

        async function apiSend(path, method, data) {
            const res = await fetch(baseUrl + path, {
                method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                throw new Error(err.message || `Error ${res.status}`);
            }
            return res.json();
        }

        return { apiGet, apiSend };
    }

    return { escapeHtml, debounce, formatCurrency, makeApiClient };

})();
