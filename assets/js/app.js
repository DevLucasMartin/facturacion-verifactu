/**
 * App - Utilidades globales
 * Sistema de Gestión de Facturas / VeriFACTU
 */
const App = (() => {

    const BASE_URL = '/SistemaGestionFacturas/src/api';

    // ── Notificaciones ──────────────────────────────────────────────────────

    function notify(message, type = 'info') {
        const typeMap = {
            success: { bg: '#198754', icon: 'bi-check-circle-fill' },
            error:   { bg: '#dc3545', icon: 'bi-x-circle-fill' },
            warning: { bg: '#ffc107', icon: 'bi-exclamation-triangle-fill', text: '#212529' },
            info:    { bg: '#0dcaf0', icon: 'bi-info-circle-fill', text: '#212529' },
        };
        const t = typeMap[type] || typeMap.info;
        const color = t.text || '#fff';

        const toast = document.createElement('div');
        toast.style.cssText = [
            `background:${t.bg}`, `color:${color}`,
            'padding:.65rem 1rem', 'border-radius:6px',
            'display:flex', 'align-items:center', 'gap:.6rem',
            'font-size:.85rem', 'box-shadow:0 2px 8px rgba(0,0,0,.2)',
            'min-width:220px', 'max-width:360px',
            'animation:fadeInUp .2s ease',
        ].join(';');
        toast.innerHTML = `<i class="bi ${t.icon}"></i><span>${escapeHtml(String(message))}</span>`;

        const container = document.getElementById('toast-container');
        if (container) {
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 4000);
        } else {
            console.warn('[App.notify]', message);
        }
    }

    // ── Loading overlay ─────────────────────────────────────────────────────

    function showLoading() {
        document.getElementById('loading-overlay')?.classList.add('active');
    }

    function hideLoading() {
        document.getElementById('loading-overlay')?.classList.remove('active');
    }

    // ── Petición AJAX (compatible jQuery) ───────────────────────────────────

    /**
     * Wrapper sobre $.ajax que devuelve un objeto con .done/.fail/.always
     * para compatibilidad con el código existente que usa jQuery deferred.
     */
    function api(url, options = {}) {
        const defaults = {
            method: options.method || 'GET',
            dataType: 'json',
        };

        if (options.contentType === 'application/json' && options.data) {
            defaults.contentType = 'application/json';
            defaults.data = options.data;
        }

        return $.ajax(Object.assign(defaults, options, { url }));
    }

    // ── Formato moneda ───────────────────────────────────────────────────────

    function formatCurrency(value) {
        return (parseFloat(value) || 0).toLocaleString('es-ES', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }) + ' €';
    }

    // ── Escape HTML ──────────────────────────────────────────────────────────

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // ── API pública ──────────────────────────────────────────────────────────
    return { notify, showLoading, hideLoading, api, formatCurrency, escapeHtml, BASE_URL };

})();

// Inyectar animación keyframe si no existe
if (!document.getElementById('_app_keyframes')) {
    const s = document.createElement('style');
    s.id = '_app_keyframes';
    s.textContent = '@keyframes fadeInUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}';
    document.head.appendChild(s);
}
