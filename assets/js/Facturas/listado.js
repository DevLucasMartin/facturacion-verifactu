/**
 * Listado de Facturas
 * assets/js/Facturas/listado.js
 */

(() => {
    'use strict';

    const BASE = (() => {
        const p = window.location.pathname;
        const idx = p.indexOf('/SistemaGestionFacturas/');
        return idx >= 0 ? p.substring(0, idx) + '/SistemaGestionFacturas' : '/SistemaGestionFacturas';
    })();

    const FACT_API_BASE = BASE + '/src/api';

    // ---------- Estado ----------
    let paginaActual  = 1;
    let totalPaginas  = 1;
    let porPagina     = 20;
    let totalRegistros = 0;

    // ---------- Init ----------
    document.addEventListener('DOMContentLoaded', () => {
        cargarFiltros();
        buscarFacturas();
        inicializarEventos();
    });

    // ---------- Filtros ----------
    function cargarFiltros() {
        cargarCodigos();
        cargarCanales();
        cargarClientes();
    }

    function cargarCodigos() {
        App.api(FACT_API_BASE + '/facturas.php?action=codigos')
            .done(data => {
                if (!data.success) return;
                const sel = document.getElementById('filtroCodigo');
                if (!sel) return;
                (data.data || []).forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c;
                    opt.textContent = c;
                    sel.appendChild(opt);
                });
            });
    }

    function cargarCanales() {
        App.api(FACT_API_BASE + '/canales.php?action=list')
            .done(data => {
                if (!data.success) return;
                const sel = document.getElementById('filtroCanal');
                if (!sel) return;
                (data.data || []).forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id_canal;
                    opt.textContent = App.escapeHtml(c.nombre);
                    sel.appendChild(opt);
                });
            });
    }

    function cargarClientes() {
        App.api(FACT_API_BASE + '/clientes.php?action=list&limit=500')
            .done(data => {
                if (!data.success) return;
                const sel = document.getElementById('filtroCliente');
                if (!sel) return;
                (data.data || []).forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id_cliente;
                    opt.textContent = App.escapeHtml(c.nombre_fiscal || c.nombre);
                    sel.appendChild(opt);
                });
            });
    }

    // ---------- Búsqueda ----------
    function getFiltros() {
        const form = document.getElementById('filtrosForm');
        if (!form) return {};
        const fd = new FormData(form);
        const params = {};
        fd.forEach((v, k) => { if (v !== '') params[k] = v; });
        return params;
    }

    function buscarFacturas(pagina = 1) {
        paginaActual = pagina;
        const params = getFiltros();
        params.page  = pagina;
        params.limit = porPagina;

        const qs = new URLSearchParams(params).toString();

        App.showLoading();
        App.api(FACT_API_BASE + '/facturas.php?action=list&' + qs)
            .done(data => {
                if (data.success) {
                    renderTabla(data.data || []);
                    totalRegistros = data.total || 0;
                    totalPaginas   = data.pages || 1;
                    renderPaginacion();
                } else {
                    App.notify(data.message || 'Error al cargar facturas', 'danger');
                }
            })
            .fail(() => App.notify('Error de conexión', 'danger'))
            .always(() => App.hideLoading());
    }

    // ---------- Render tabla ----------
    function renderTabla(facturas) {
        const tbody = document.getElementById('facturasTableBody');
        if (!tbody) return;

        if (!facturas.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        No se encontraron facturas
                    </td>
                </tr>`;
            return;
        }

        tbody.innerHTML = facturas.map(f => {
            const estadoBadge  = badgeEstado(f.estado);
            const verifBadge   = badgeVerifactu(f.estado_verifactu);
            const total        = App.formatCurrency(f.total || 0);
            const fecha        = f.fecha ? f.fecha.substring(0, 10) : '-';
            const cliente      = App.escapeHtml(f.nombre_cliente || f.id_cliente || '-');
            const tipo         = App.escapeHtml(f.tipo_documento || '-');
            const verHref      = BASE + '/src/views/facturas/ver.php?codigo=' + encodeURIComponent(f.codigo);
            const editHref     = BASE + '/src/views/facturas/editar.php?codigo=' + encodeURIComponent(f.codigo);

            return `<tr class="cursor-pointer" data-href="${verHref}" style="cursor:pointer;">
                <td><a href="${verHref}" class="text-decoration-none fw-semibold">${App.escapeHtml(f.codigo)}</a></td>
                <td>${fecha}</td>
                <td>${tipo}</td>
                <td>${cliente}</td>
                <td class="text-end">${total}</td>
                <td class="text-center">${estadoBadge}</td>
                <td class="text-center">${verifBadge}</td>
                <td class="text-center">
                    <a href="${verHref}" class="btn btn-xs btn-outline-secondary btn-sm" title="Ver">
                        <i class="bi bi-eye"></i>
                    </a>
                    ${f.estado === 'BORRADOR' ? `<a href="${editHref}" class="btn btn-xs btn-outline-primary btn-sm ms-1" title="Editar">
                        <i class="bi bi-pencil"></i>
                    </a>` : ''}
                    ${f.tipo_documento !== 'RECTIFICATIVA' ? `<button type="button"
                        class="btn btn-xs btn-outline-warning btn-sm ms-1"
                        title="Crear rectificativa"
                        onclick="crearRectificativa('${App.escapeHtml(f.codigo)}'); event.stopPropagation();">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>` : ''}
                </td>
            </tr>`;
        }).join('');

        // Filas clickables
        tbody.querySelectorAll('tr[data-href]').forEach(tr => {
            tr.addEventListener('click', e => {
                if (e.target.closest('a,button')) return;
                window.location.href = tr.dataset.href;
            });
        });
    }

    function badgeEstado(estado) {
        const map = {
            'BORRADOR':  'secondary',
            'EMITIDA':   'success',
            'ANULADA':   'danger',
            'PAGADA':    'primary'
        };
        const color = map[estado] || 'secondary';
        return `<span class="badge bg-${color}">${App.escapeHtml(estado || '-')}</span>`;
    }

    function badgeVerifactu(estado) {
        const map = {
            'NO_ENVIADA':   ['secondary', 'No enviada'],
            'ENVIADA':      ['success',   'Enviada'],
            'ACEPTADA':     ['success',   'Aceptada'],
            'RECHAZADA':    ['danger',    'Rechazada'],
            'PENDIENTE':    ['warning',   'Pendiente'],
            'ERROR':        ['danger',    'Error']
        };
        const [color, label] = map[estado] || ['secondary', estado || '-'];
        return `<span class="badge bg-${color}">${label}</span>`;
    }

    // ---------- Paginación ----------
    function renderPaginacion() {
        const info = document.getElementById('paginationInfo');
        const nav  = document.getElementById('paginationNav');
        if (info) {
            const desde = totalRegistros === 0 ? 0 : (paginaActual - 1) * porPagina + 1;
            const hasta = Math.min(paginaActual * porPagina, totalRegistros);
            info.textContent = `Mostrando ${desde}–${hasta} de ${totalRegistros} registros`;
        }
        if (!nav) return;
        nav.innerHTML = '';

        if (totalPaginas <= 1) return;

        const crearLi = (texto, pagina, activa = false, disabled = false) => {
            const li   = document.createElement('li');
            li.className = 'page-item' + (activa ? ' active' : '') + (disabled ? ' disabled' : '');
            const a    = document.createElement('a');
            a.className = 'page-link';
            a.href = '#';
            a.innerHTML = texto;
            if (!disabled && !activa) {
                a.addEventListener('click', e => { e.preventDefault(); buscarFacturas(pagina); });
            }
            li.appendChild(a);
            return li;
        };

        nav.appendChild(crearLi('&laquo;', paginaActual - 1, false, paginaActual === 1));

        const desde = Math.max(1, paginaActual - 2);
        const hasta  = Math.min(totalPaginas, paginaActual + 2);
        for (let i = desde; i <= hasta; i++) {
            nav.appendChild(crearLi(String(i), i, i === paginaActual));
        }

        nav.appendChild(crearLi('&raquo;', paginaActual + 1, false, paginaActual === totalPaginas));
    }

    // ---------- Acciones ----------
    window.crearRectificativa = function(codigo) {
        if (!confirm(`¿Crear una factura rectificativa para ${codigo}?`)) return;
        App.showLoading();
        App.api(FACT_API_BASE + '/facturas.php', {
            method: 'POST',
            data: JSON.stringify({ action: 'rectificativa', codigo }),
            contentType: 'application/json'
        })
        .done(data => {
            if (data.success) {
                App.notify('Rectificativa creada: ' + data.data.codigo, 'success');
                setTimeout(() => {
                    window.location.href = BASE + '/src/views/facturas/ver.php?codigo=' + encodeURIComponent(data.data.codigo);
                }, 1000);
            } else {
                App.notify(data.message || 'Error al crear rectificativa', 'danger');
            }
        })
        .fail(() => App.notify('Error de conexión', 'danger'))
        .always(() => App.hideLoading());
    };

    // ---------- Exportar Excel ----------
    function exportarExcel() {
        const params = getFiltros();
        params.action = 'export';
        const qs = new URLSearchParams(params).toString();

        const indicator = document.getElementById('exportIndicator');
        if (indicator) indicator.style.display = 'flex';

        const link = document.createElement('a');
        link.href = FACT_API_BASE + '/facturas.php?' + qs;
        link.download = 'facturas.xlsx';
        link.click();

        setTimeout(() => { if (indicator) indicator.style.display = 'none'; }, 3000);
    }

    // ---------- Eventos ----------
    function inicializarEventos() {
        const form = document.getElementById('filtrosForm');
        if (form) {
            form.addEventListener('submit', e => {
                e.preventDefault();
                buscarFacturas(1);
            });
        }

        const btnExcel = document.getElementById('btnExportarExcel');
        if (btnExcel) {
            btnExcel.addEventListener('click', exportarExcel);
        }
    }

})();
