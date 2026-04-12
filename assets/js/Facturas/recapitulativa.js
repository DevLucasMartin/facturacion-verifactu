/**
 * Factura Recapitulativa
 * assets/js/Facturas/recapitulativa.js
 */

(() => {
    'use strict';

    const BASE = (() => {
        const p = window.location.pathname;
        const idx = p.indexOf('/SistemaGestionFacturas/');
        return idx >= 0 ? p.substring(0, idx) + '/SistemaGestionFacturas' : '/SistemaGestionFacturas';
    })();

    const RECAP_API_BASE = BASE + '/src/api';

    // ---------- Estado ----------
    let facturasSeleccionadas = [];
    let paginaActual   = 1;
    let totalPaginas   = 1;
    let porPagina      = 20;
    let totalRegistros = 0;

    // ---------- Init ----------
    document.addEventListener('DOMContentLoaded', () => {
        cargarClientes();
        inicializarEventos();
        buscarAlbaranes();
    });

    // ---------- Clientes ----------
    function cargarClientes() {
        App.api(RECAP_API_BASE + '/clientes.php?action=list&limit=500')
            .done(data => {
                if (!data.success) return;
                const sel = document.getElementById('filtroClienteRecap');
                if (!sel) return;
                (data.data || []).forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id_cliente;
                    opt.textContent = App.escapeHtml(c.nombre_fiscal || c.nombre);
                    sel.appendChild(opt);
                });
                if (typeof $ !== 'undefined' && $.fn.select2) {
                    $(sel).select2({ placeholder: 'Seleccionar cliente...', allowClear: true });
                }
            });
    }

    // ---------- Búsqueda de albaranes ----------
    function getFiltros() {
        const params = {};
        const idCliente = document.getElementById('filtroClienteRecap')?.value;
        const desde     = document.getElementById('filtroDesdeRecap')?.value;
        const hasta     = document.getElementById('filtroHastaRecap')?.value;
        if (idCliente) params.id_cliente  = idCliente;
        if (desde)     params.fecha_desde = desde;
        if (hasta)     params.fecha_hasta = hasta;
        return params;
    }

    function buscarAlbaranes(pagina = 1) {
        paginaActual = pagina;
        const params = getFiltros();
        params.action = 'pendientes_recap';
        params.page   = pagina;
        params.limit  = porPagina;

        const qs = new URLSearchParams(params).toString();

        App.showLoading();
        App.api(RECAP_API_BASE + '/albaranes.php?' + qs)
            .done(data => {
                if (data.success) {
                    renderTabla(data.data || []);
                    totalRegistros = data.total || 0;
                    totalPaginas   = data.pages || 1;
                    renderPaginacion();
                } else {
                    App.notify(data.message || 'Error al cargar albaranes', 'danger');
                }
            })
            .fail(() => App.notify('Error de conexión', 'danger'))
            .always(() => App.hideLoading());
    }

    // ---------- Render tabla albaranes ----------
    function renderTabla(albaranes) {
        const tbody = document.getElementById('albaranesBody');
        if (!tbody) return;

        if (!albaranes.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        No hay albaranes pendientes de facturación
                    </td>
                </tr>`;
            actualizarResumen();
            return;
        }

        tbody.innerHTML = albaranes.map(a => {
            const seleccionado = facturasSeleccionadas.includes(String(a.codigo));
            const total = App.formatCurrency(a.total || 0);
            const fecha = a.fecha ? a.fecha.substring(0, 10) : '-';

            return `<tr>
                <td class="text-center">
                    <input type="checkbox" class="form-check-input alb-check"
                        value="${App.escapeHtml(String(a.codigo))}"
                        ${seleccionado ? 'checked' : ''}>
                </td>
                <td>${App.escapeHtml(a.codigo)}</td>
                <td>${fecha}</td>
                <td>${App.escapeHtml(a.nombre_cliente || a.id_cliente || '-')}</td>
                <td>${App.escapeHtml(a.serie || '-')}</td>
                <td class="text-end">${total}</td>
                <td>${App.escapeHtml(a.referencia_cliente || '-')}</td>
            </tr>`;
        }).join('');

        // Eventos checkboxes
        tbody.querySelectorAll('.alb-check').forEach(cb => {
            cb.addEventListener('change', () => {
                if (cb.checked) {
                    if (!facturasSeleccionadas.includes(cb.value)) {
                        facturasSeleccionadas.push(cb.value);
                    }
                } else {
                    facturasSeleccionadas = facturasSeleccionadas.filter(v => v !== cb.value);
                }
                actualizarResumen();
            });
        });

        actualizarResumen();
    }

    // ---------- Paginación ----------
    function renderPaginacion() {
        const info = document.getElementById('paginationInfoRecap');
        const nav  = document.getElementById('paginationNavRecap');

        if (info) {
            const desde = totalRegistros === 0 ? 0 : (paginaActual - 1) * porPagina + 1;
            const hasta = Math.min(paginaActual * porPagina, totalRegistros);
            info.textContent = `Mostrando ${desde}–${hasta} de ${totalRegistros} registros`;
        }

        if (!nav) return;
        nav.innerHTML = '';
        if (totalPaginas <= 1) return;

        const crearLi = (texto, pagina, activa = false, disabled = false) => {
            const li = document.createElement('li');
            li.className = 'page-item' + (activa ? ' active' : '') + (disabled ? ' disabled' : '');
            const a = document.createElement('a');
            a.className = 'page-link';
            a.href = '#';
            a.innerHTML = texto;
            if (!disabled && !activa) {
                a.addEventListener('click', e => { e.preventDefault(); buscarAlbaranes(pagina); });
            }
            li.appendChild(a);
            return li;
        };

        nav.appendChild(crearLi('&laquo;', paginaActual - 1, false, paginaActual === 1));
        const desde = Math.max(1, paginaActual - 2);
        const hasta  = Math.min(totalPaginas, paginaActual + 2);
        for (let i = desde; i <= hasta; i++) nav.appendChild(crearLi(String(i), i, i === paginaActual));
        nav.appendChild(crearLi('&raquo;', paginaActual + 1, false, paginaActual === totalPaginas));
    }

    // ---------- Resumen selección ----------
    function actualizarResumen() {
        const resumen = document.getElementById('resumenSeleccion');
        const btnGenerar = document.getElementById('btnGenerarRecapitulativa');
        const count = facturasSeleccionadas.length;

        if (resumen) {
            resumen.textContent = count > 0
                ? `${count} albarán${count !== 1 ? 'es' : ''} seleccionado${count !== 1 ? 's' : ''}`
                : 'Ningún albarán seleccionado';
        }
        if (btnGenerar) btnGenerar.disabled = count === 0;
    }

    // ---------- Seleccionar todo ----------
    function toggleSeleccionarTodo(checked) {
        const checkboxes = document.querySelectorAll('.alb-check');
        checkboxes.forEach(cb => {
            cb.checked = checked;
            if (checked) {
                if (!facturasSeleccionadas.includes(cb.value)) facturasSeleccionadas.push(cb.value);
            } else {
                facturasSeleccionadas = facturasSeleccionadas.filter(v => v !== cb.value);
            }
        });
        actualizarResumen();
    }

    // ---------- Generar recapitulativa ----------
    function generarRecapitulativa() {
        if (!facturasSeleccionadas.length) {
            App.notify('Selecciona al menos un albarán', 'warning');
            return;
        }

        const idCliente = document.getElementById('filtroClienteRecap')?.value;
        if (!idCliente) {
            App.notify('Selecciona un cliente para la recapitulativa', 'warning');
            return;
        }

        const fechaFactura = document.getElementById('fechaRecap')?.value;
        if (!fechaFactura) {
            App.notify('Indica la fecha de la factura recapitulativa', 'warning');
            return;
        }

        if (!confirm(`¿Generar factura recapitulativa con ${facturasSeleccionadas.length} albarán(es)?`)) return;

        App.showLoading();
        App.api(RECAP_API_BASE + '/facturas.php', {
            method: 'POST',
            data: JSON.stringify({
                action:     'recapitulativa',
                id_cliente: idCliente,
                fecha:      fechaFactura,
                albaranes:  facturasSeleccionadas
            }),
            contentType: 'application/json'
        })
        .done(data => {
            if (data.success) {
                App.notify('Factura recapitulativa generada: ' + data.data.codigo, 'success');
                setTimeout(() => {
                    window.location.href = BASE + '/src/views/facturas/ver.php?codigo=' + encodeURIComponent(data.data.codigo);
                }, 1200);
            } else {
                App.notify(data.message || 'Error al generar recapitulativa', 'danger');
            }
        })
        .fail(() => App.notify('Error de conexión', 'danger'))
        .always(() => App.hideLoading());
    }

    // ---------- Eventos ----------
    function inicializarEventos() {
        const form = document.getElementById('filtrosFormRecap');
        if (form) {
            form.addEventListener('submit', e => {
                e.preventDefault();
                facturasSeleccionadas = [];
                buscarAlbaranes(1);
            });
        }

        const btnTodo = document.getElementById('btnSeleccionarTodo');
        if (btnTodo) {
            btnTodo.addEventListener('click', () => {
                const allChecked = document.querySelectorAll('.alb-check:checked').length ===
                                   document.querySelectorAll('.alb-check').length;
                toggleSeleccionarTodo(!allChecked);
            });
        }

        const btnGenerar = document.getElementById('btnGenerarRecapitulativa');
        if (btnGenerar) {
            btnGenerar.addEventListener('click', generarRecapitulativa);
        }
    }

})();
