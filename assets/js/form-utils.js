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

    // ─── Validación de formato NIF-IVA (VIES) por país UE ────────────────────
    // Patrones sobre el número SIN el prefijo de país (Grecia usa prefijo EL).
    const VAT_UE_FORMATS = {
        AT: { regex: /^U\d{8}$/,                            formato: 'ATU + 8 dígitos' },
        BE: { regex: /^[01]\d{9}$/,                         formato: 'BE + 10 dígitos (empieza por 0 o 1)' },
        BG: { regex: /^\d{9,10}$/,                          formato: 'BG + 9 o 10 dígitos' },
        CY: { regex: /^\d{8}[A-Z]$/,                        formato: 'CY + 8 dígitos + 1 letra' },
        CZ: { regex: /^\d{8,10}$/,                          formato: 'CZ + 8, 9 o 10 dígitos' },
        DE: { regex: /^\d{9}$/,                             formato: 'DE + 9 dígitos' },
        DK: { regex: /^\d{8}$/,                             formato: 'DK + 8 dígitos' },
        EE: { regex: /^\d{9}$/,                             formato: 'EE + 9 dígitos' },
        ES: { regex: /^[A-Z0-9]\d{7}[A-Z0-9]$/,             formato: 'ES + 9 caracteres' },
        FI: { regex: /^\d{8}$/,                             formato: 'FI + 8 dígitos' },
        FR: { regex: /^[A-Z0-9]{2}\d{9}$/,                  formato: 'FR + 2 caracteres + 9 dígitos' },
        GR: { regex: /^\d{9}$/,                             formato: 'EL + 9 dígitos' },
        HR: { regex: /^\d{11}$/,                            formato: 'HR + 11 dígitos' },
        HU: { regex: /^\d{8}$/,                             formato: 'HU + 8 dígitos' },
        IE: { regex: /^(\d{7}[A-Z]{1,2}|\d[A-Z+*]\d{5}[A-Z])$/, formato: 'IE + 7 dígitos + 1-2 letras' },
        IT: { regex: /^\d{11}$/,                            formato: 'IT + 11 dígitos' },
        LT: { regex: /^(\d{9}|\d{12})$/,                    formato: 'LT + 9 o 12 dígitos' },
        LU: { regex: /^\d{8}$/,                             formato: 'LU + 8 dígitos' },
        LV: { regex: /^\d{11}$/,                            formato: 'LV + 11 dígitos' },
        MT: { regex: /^\d{8}$/,                             formato: 'MT + 8 dígitos' },
        NL: { regex: /^\d{9}B\d{2}$/,                       formato: 'NL + 9 dígitos + B + 2 dígitos' },
        PL: { regex: /^\d{10}$/,                            formato: 'PL + 10 dígitos' },
        PT: { regex: /^\d{9}$/,                             formato: 'PT + 9 dígitos' },
        RO: { regex: /^\d{2,10}$/,                          formato: 'RO + 2 a 10 dígitos' },
        SE: { regex: /^\d{12}$/,                            formato: 'SE + 12 dígitos' },
        SI: { regex: /^\d{8}$/,                             formato: 'SI + 8 dígitos' },
        SK: { regex: /^\d{10}$/,                            formato: 'SK + 10 dígitos' },
        XI: { regex: /^(\d{9}(\d{3})?|GD\d{3}|HA\d{3})$/,   formato: 'XI + 9 o 12 dígitos' },
    };

    /**
     * Valida el formato VIES de un NIF-IVA para un país de la UE.
     * Acepta el número con o sin prefijo de país.
     * Devuelve null si es válido (o si el país no está en la lista), o un mensaje de error.
     */
    function validarVatUE(vat, pais) {
        pais = (pais || '').toUpperCase().trim();
        const fmt = VAT_UE_FORMATS[pais];
        if (!fmt) return null;

        let v = (vat || '').toUpperCase().replace(/[\s.\-]/g, '');
        const prefijos = pais === 'GR' ? ['EL', 'GR'] : [pais];
        for (const p of prefijos) {
            if (v.startsWith(p)) { v = v.slice(p.length); break; }
        }
        if (v === '') return 'Falta el número de IVA después del prefijo de país.';
        if (!fmt.regex.test(v)) {
            return `El NIF-IVA no tiene el formato válido para ${pais} (${fmt.formato}).`;
        }
        return null;
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

    return { escapeHtml, formatCurrency, debounce, makeApiClient, validarVatUE };
})();
