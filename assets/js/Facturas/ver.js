/**
 * Ver Factura
 * assets/js/Facturas/ver.js
 */

(() => {
    'use strict';

    const BASE = (() => {
        const p = window.location.pathname;
        const idx = p.indexOf('/SistemaGestionFacturas/');
        return idx >= 0 ? p.substring(0, idx) + '/SistemaGestionFacturas' : '/SistemaGestionFacturas';
    })();

    const API = BASE + '/src/api';

    const CODIGO = document.getElementById('app-data')?.dataset?.codigo || '';

    document.addEventListener('DOMContentLoaded', () => {
        if (!CODIGO) {
            document.getElementById('facturaData').innerHTML =
                '<div class="alert alert-danger">Código de factura no especificado.</div>';
            return;
        }
        cargarTodo();
    });

    async function cargarTodo() {
        await Promise.all([
            cargarFactura(),
            cargarLineas(),
            cargarTotales(),
            cargarCliente(),
            cargarVerifactu()
        ]);
    }

    // ---------- Factura ----------
    function cargarFactura() {
        return new Promise(resolve => {
            App.api(API + '/facturas.php?action=get&codigo=' + encodeURIComponent(CODIGO))
                .done(data => {
                    if (data.success) {
                        renderFactura(data.data);
                    } else {
                        document.getElementById('facturaData').innerHTML =
                            `<div class="alert alert-warning">${App.escapeHtml(data.message || 'No encontrada')}</div>`;
                    }
                })
                .fail(() => {
                    document.getElementById('facturaData').innerHTML =
                        '<div class="alert alert-danger">Error al cargar la factura.</div>';
                })
                .always(resolve);
        });
    }

    function renderFactura(f) {
        const panel = document.getElementById('facturaData');
        if (!panel) return;

        const tipoBadge = `<span class="badge bg-primary">${App.escapeHtml(f.tipo_documento || '-')}</span>`;
        const estadoBadge = (() => {
            const map = { 'BORRADOR': 'secondary', 'EMITIDA': 'success', 'ANULADA': 'danger', 'PAGADA': 'primary' };
            return `<span class="badge bg-${map[f.estado] || 'secondary'}">${App.escapeHtml(f.estado || '-')}</span>`;
        })();

        panel.innerHTML = `
            <div class="row g-3">
                <div class="col-sm-6">
                    <small class="text-muted d-block">Código</small>
                    <strong>${App.escapeHtml(f.codigo)}</strong>
                </div>
                <div class="col-sm-6">
                    <small class="text-muted d-block">Fecha</small>
                    <strong>${f.fecha ? f.fecha.substring(0,10) : '-'}</strong>
                </div>
                <div class="col-sm-6">
                    <small class="text-muted d-block">Tipo</small>
                    ${tipoBadge}
                </div>
                <div class="col-sm-6">
                    <small class="text-muted d-block">Estado</small>
                    ${estadoBadge}
                </div>
                ${f.canal ? `<div class="col-sm-6">
                    <small class="text-muted d-block">Canal</small>
                    <span>${App.escapeHtml(f.canal)}</span>
                </div>` : ''}
                ${f.observaciones ? `<div class="col-12">
                    <small class="text-muted d-block">Observaciones</small>
                    <span>${App.escapeHtml(f.observaciones)}</span>
                </div>` : ''}
            </div>`;

        // Mostrar/ocultar botón editar
        const btnEdit = document.getElementById('btnEditar');
        if (btnEdit) {
            if (f.estado === 'BORRADOR') {
                btnEdit.href = BASE + '/src/views/facturas/editar.php?codigo=' + encodeURIComponent(f.codigo);
                btnEdit.style.display = '';
            } else {
                btnEdit.style.display = 'none';
            }
        }
    }

    // ---------- Líneas ----------
    function cargarLineas() {
        return new Promise(resolve => {
            App.api(API + '/facturas.php?action=lineas&codigo=' + encodeURIComponent(CODIGO))
                .done(data => {
                    if (data.success) renderLineas(data.data || []);
                })
                .fail(() => {
                    document.getElementById('lineasBody').innerHTML =
                        '<tr><td colspan="8" class="text-center text-danger">Error al cargar líneas.</td></tr>';
                })
                .always(resolve);
        });
    }

    function renderLineas(lineas) {
        const tbody = document.getElementById('lineasBody');
        const count = document.getElementById('lineasCount');
        if (!tbody) return;

        if (count) count.textContent = `${lineas.length} línea${lineas.length !== 1 ? 's' : ''}`;

        if (!lineas.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">Sin líneas</td></tr>';
            return;
        }

        // Detectar si hay recargo de equivalencia
        const hayRE = lineas.some(l => parseFloat(l.porcentaje_re || 0) > 0);
        const thRE  = document.getElementById('thCalifRE');
        if (thRE) thRE.textContent = hayRE ? 'RE%' : 'Calif.';

        tbody.innerHTML = lineas.map((l, i) => {
            const desc   = parseFloat(l.descuento || 0);
            const re     = parseFloat(l.porcentaje_re || 0);
            const total  = App.formatCurrency(l.total || 0);

            return `<tr>
                <td class="text-muted">${i + 1}</td>
                <td>${App.escapeHtml(l.descripcion || l.referencia || '-')}</td>
                <td class="text-end">${parseFloat(l.cantidad || 0).toFixed(2)}</td>
                <td class="text-end">${App.formatCurrency(l.precio_unitario || 0)}</td>
                <td class="text-end">${desc > 0 ? desc.toFixed(2) + '%' : '-'}</td>
                <td class="text-end">${parseFloat(l.porcentaje_iva || 0).toFixed(0)}%</td>
                <td class="text-end">${re > 0 ? re.toFixed(2) + '%' : App.escapeHtml(l.tipo_iva || '-')}</td>
                <td class="text-end fw-semibold">${total}</td>
            </tr>`;
        }).join('');
    }

    // ---------- Totales ----------
    function cargarTotales() {
        return new Promise(resolve => {
            App.api(API + '/facturas.php?action=totales&codigo=' + encodeURIComponent(CODIGO))
                .done(data => {
                    if (data.success) renderTotales(data.data);
                })
                .fail(() => {
                    document.getElementById('totalesPanel').innerHTML =
                        '<div class="alert alert-danger">Error al cargar totales.</div>';
                })
                .always(resolve);
        });
    }

    function renderTotales(t) {
        const panel = document.getElementById('totalesPanel');
        if (!panel || !t) return;

        const filas = [];
        if (parseFloat(t.subtotal || 0) !== parseFloat(t.base_imponible || 0)) {
            filas.push(['Subtotal', App.formatCurrency(t.subtotal || 0)]);
        }
        if (parseFloat(t.dto_especial || 0) > 0) {
            filas.push(['Dto. Especial', '- ' + App.formatCurrency(t.dto_especial)]);
        }
        if (parseFloat(t.dto_comercial || 0) > 0) {
            filas.push(['Dto. Comercial', '- ' + App.formatCurrency(t.dto_comercial)]);
        }
        if (parseFloat(t.dto_pp || 0) > 0) {
            filas.push(['Dto. P.P.', '- ' + App.formatCurrency(t.dto_pp)]);
        }
        filas.push(['Base imponible', App.formatCurrency(t.base_imponible || 0)]);

        // Cuotas IVA
        (t.cuotas_iva || []).forEach(c => {
            filas.push([
                `IVA ${c.porcentaje_iva}%`,
                App.formatCurrency(c.cuota_iva || 0)
            ]);
            if (parseFloat(c.cuota_re || 0) > 0) {
                filas.push([
                    `RE ${c.porcentaje_re}%`,
                    App.formatCurrency(c.cuota_re)
                ]);
            }
        });

        panel.innerHTML = `
            <table class="table table-sm mb-0">
                <tbody>
                    ${filas.map(([k, v]) => `<tr>
                        <td class="text-muted">${k}</td>
                        <td class="text-end">${v}</td>
                    </tr>`).join('')}
                    <tr class="table-active fw-bold">
                        <td>TOTAL</td>
                        <td class="text-end fs-5">${App.formatCurrency(t.total || 0)}</td>
                    </tr>
                </tbody>
            </table>`;
    }

    // ---------- Cliente ----------
    function cargarCliente() {
        return new Promise(resolve => {
            App.api(API + '/facturas.php?action=cliente&codigo=' + encodeURIComponent(CODIGO))
                .done(data => {
                    if (data.success) renderCliente(data.data);
                })
                .always(resolve);
        });
    }

    function renderCliente(c) {
        const panel = document.getElementById('clientePanel');
        if (!panel || !c) return;

        panel.innerHTML = `
            <p class="mb-1 fw-semibold">${App.escapeHtml(c.nombre_fiscal || c.nombre || '-')}</p>
            ${c.nif ? `<p class="mb-1 text-muted small">NIF: ${App.escapeHtml(c.nif)}</p>` : ''}
            ${c.direccion ? `<p class="mb-1 small">${App.escapeHtml(c.direccion)}</p>` : ''}
            ${c.poblacion ? `<p class="mb-1 small">${App.escapeHtml(c.cp || '')} ${App.escapeHtml(c.poblacion)}</p>` : ''}
            ${c.email ? `<p class="mb-0 small"><a href="mailto:${App.escapeHtml(c.email)}">${App.escapeHtml(c.email)}</a></p>` : ''}`;
    }

    // ---------- Verifactu ----------
    function cargarVerifactu() {
        return new Promise(resolve => {
            App.api(API + '/verifactu.php?action=estado&codigo=' + encodeURIComponent(CODIGO))
                .done(data => {
                    if (data.success) renderVerifactu(data.data);
                    else renderVerifactuVacio();
                })
                .fail(() => renderVerifactuVacio())
                .always(resolve);
        });
    }

    function renderVerifactu(v) {
        const badge = document.getElementById('verifactuBadge');
        const panel = document.getElementById('verifactuPanel');

        const map = {
            'NO_ENVIADA': ['secondary', 'No enviada'],
            'ENVIADA':    ['info',      'Enviada'],
            'ACEPTADA':   ['success',   'Aceptada'],
            'RECHAZADA':  ['danger',    'Rechazada'],
            'PENDIENTE':  ['warning',   'Pendiente'],
            'ERROR':      ['danger',    'Error']
        };
        const [color, label] = map[v?.estado] || ['secondary', v?.estado || '-'];

        if (badge) {
            badge.className = `badge bg-${color}`;
            badge.textContent = label;
        }

        if (!panel) return;
        panel.innerHTML = `
            <div class="row g-2">
                <div class="col-sm-6">
                    <small class="text-muted d-block">Estado</small>
                    <span class="badge bg-${color}">${label}</span>
                </div>
                ${v?.fecha_envio ? `<div class="col-sm-6">
                    <small class="text-muted d-block">Fecha envío</small>
                    <span>${v.fecha_envio.substring(0,16).replace('T',' ')}</span>
                </div>` : ''}
                ${v?.hash ? `<div class="col-12">
                    <small class="text-muted d-block">Hash</small>
                    <code class="small text-break">${App.escapeHtml(v.hash)}</code>
                </div>` : ''}
                ${v?.csv ? `<div class="col-12">
                    <small class="text-muted d-block">CSV Hacienda</small>
                    <code class="small">${App.escapeHtml(v.csv)}</code>
                </div>` : ''}
                ${v?.descripcion_error ? `<div class="col-12">
                    <small class="text-muted d-block">Error</small>
                    <span class="text-danger small">${App.escapeHtml(v.descripcion_error)}</span>
                </div>` : ''}
            </div>`;
    }

    function renderVerifactuVacio() {
        const badge = document.getElementById('verifactuBadge');
        const panel = document.getElementById('verifactuPanel');
        if (badge) { badge.className = 'badge bg-secondary'; badge.textContent = 'No enviada'; }
        if (panel) panel.innerHTML = '<p class="text-muted mb-0">Esta factura aún no ha sido enviada a Hacienda.</p>';
    }

    // ---------- Acciones globales ----------
    window.firmarYEnviar = function() {
        if (!confirm('¿Enviar esta factura a la AEAT a través de Verifactu?')) return;
        App.showLoading();
        App.api(API + '/verifactu.php', {
            method: 'POST',
            data: JSON.stringify({ id_documento: CODIGO }),
            contentType: 'application/json'
        })
        .done(data => {
            if (data.success) {
                App.notify('Factura enviada correctamente', 'success');
                setTimeout(() => cargarVerifactu(), 1500);
            } else {
                App.notify(data.message || 'Error al enviar', 'danger');
            }
        })
        .fail(() => App.notify('Error de conexión', 'danger'))
        .always(() => App.hideLoading());
    };

    window.descargarXml = function(tipo) {
        window.open(
            API + '/facturas.php?action=xml&tipo=' + tipo + '&codigo=' + encodeURIComponent(CODIGO),
            '_blank'
        );
    };

    window.verPdf = function() {
        window.open(
            API + '/facturas.php?action=pdf&codigo=' + encodeURIComponent(CODIGO),
            '_blank'
        );
    };

    window.descargarPdf = function() {
        window.open(
            API + '/facturas.php?action=pdf&codigo=' + encodeURIComponent(CODIGO),
            '_blank'
        );
    };

    window.enviarEmail = function() {
        const email = prompt('Dirección de email:');
        if (!email) return;
        App.showLoading();
        App.api(API + '/facturas.php', {
            method: 'POST',
            data: JSON.stringify({ action: 'email', codigo: CODIGO, email }),
            contentType: 'application/json'
        })
        .done(data => {
            if (data.success) App.notify('Email enviado correctamente', 'success');
            else App.notify(data.message || 'Error al enviar email', 'danger');
        })
        .fail(() => App.notify('Error de conexión', 'danger'))
        .always(() => App.hideLoading());
    };

})();
