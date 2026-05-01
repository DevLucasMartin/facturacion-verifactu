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

    // Estado paginación líneas
    let lineasAll  = [];
    let lineasPage = 1;
    const PAGE_SIZE = 10;

    document.addEventListener('DOMContentLoaded', () => {
        if (!CODIGO) {
            document.getElementById('facturaData').innerHTML =
                '<div class="alert alert-danger">Código de factura no especificado.</div>';
            return;
        }
        cargarTodo();
    });

    let facturaTotal = 0;

    async function cargarTodo() {
        await Promise.all([
            cargarFactura(),
            cargarLineas(),
            cargarTotales(),
            cargarCliente(),
            cargarVerifactu()
        ]);
    }

    // ─── Factura ─────────────────────────────────────────────────────────────

    function cargarFactura() {
        return new Promise(resolve => {
            App.api(API + '/facturas.php?action=get&codigo=' + encodeURIComponent(CODIGO))
                .done(data => {
                    if (data.success) renderFactura(data.data);
                    else document.getElementById('facturaData').innerHTML =
                        `<div class="alert alert-warning">${App.escapeHtml(data.message || 'No encontrada')}</div>`;
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

        const tipoLabels = {
            'FACTURA':        'Factura',
            'SIMPLIFICADA':   'Factura Simplificada',
            'RECTIFICATIVA':  'Factura Rectificativa',
            'RECAPITULATIVA': 'Factura Recapitulativa'
        };

        const estadoMap = {
            'BORRADOR': ['secondary', 'Borrador'],
            'EMITIDA':  ['success',   'Emitida'],
            'ANULADA':  ['danger',    'Anulada']
        };
        const [estadoColor, estadoLabel] = estadoMap[f.estado] || ['secondary', f.estado || '-'];
        const cobradoHtml = f.cobrada
            ? '<span class="badge bg-success">Cobrado</span>'
            : '<span class="badge bg-warning text-dark">Pendiente</span>';

        const fecha = f.fecha ? String(f.fecha).substring(0, 10) : '-';

        let filas = `
            <dt class="col-sm-4">Código:</dt>
            <dd class="col-sm-8"><strong>${App.escapeHtml(f.codigo)}</strong></dd>

            <dt class="col-sm-4">Tipo:</dt>
            <dd class="col-sm-8">${App.escapeHtml(tipoLabels[f.tipo_documento] || f.tipo_documento || '-')}</dd>

            <dt class="col-sm-4">Fecha:</dt>
            <dd class="col-sm-8">${fecha}</dd>

            ${f.canal ? `<dt class="col-sm-4">Canal:</dt><dd class="col-sm-8">${App.escapeHtml(f.canal)}</dd>` : ''}

            <dt class="col-sm-4">Estado:</dt>
            <dd class="col-sm-8"><span class="badge bg-${estadoColor}">${estadoLabel}</span></dd>

            <dt class="col-sm-4">Cobrado:</dt>
            <dd class="col-sm-8">${cobradoHtml}</dd>

            ${f.tipo_documento === 'SIMPLIFICADA' ? `
            <dt class="col-sm-4">Recapitulada:</dt>
            <dd class="col-sm-8">
                ${f.recapitulada
                    ? '<span class="badge bg-success">Sí</span>'
                    : '<span class="badge bg-secondary">No</span>'}
            </dd>` : ''}`;

        panel.innerHTML = `<dl class="row mb-0">${filas}</dl>` +
            (f.observaciones
                ? `<hr><p class="mb-0"><strong>Observaciones:</strong> ${App.escapeHtml(f.observaciones)}</p>`
                : '');

        facturaTotal = f.total || 0;
        renderPago(f.total || 0, f.importe_cobrado || 0, f.cobrada);

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

    // ─── Líneas (con paginación) ──────────────────────────────────────────────

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
        const count = document.getElementById('lineasCount');
        if (count) count.textContent = `${lineas.length} línea${lineas.length !== 1 ? 's' : ''}`;

        if (!lineas.length) {
            document.getElementById('lineasBody').innerHTML =
                '<tr><td colspan="8" class="text-center text-muted py-3">Sin líneas</td></tr>';
            document.getElementById('lineasPaginacion').style.display = 'none';
            return;
        }

        const hayRE = lineas.some(l => parseFloat(l.porcentaje_re || 0) > 0);
        const thRE  = document.getElementById('thCalifRE');
        if (thRE) thRE.textContent = hayRE ? 'RE%' : 'Calif.';

        lineasAll  = lineas;
        lineasPage = 1;
        renderLineasPage();
    }

    function renderLineasPage() {
        const start = (lineasPage - 1) * PAGE_SIZE;
        const slice = lineasAll.slice(start, start + PAGE_SIZE);

        document.getElementById('lineasBody').innerHTML = slice.map((l, i) => {
            const desc  = parseFloat(l.descuento || 0);
            const re    = parseFloat(l.porcentaje_re || 0);
            return `<tr>
                <td class="text-muted">${start + i + 1}</td>
                <td>${App.escapeHtml(l.descripcion || l.referencia || '-')}</td>
                <td class="text-end">${parseFloat(l.cantidad || 0).toFixed(2)}</td>
                <td class="text-end">${App.formatCurrency(l.precio_unitario || 0)}</td>
                <td class="text-end">${desc > 0 ? desc.toFixed(2) + '%' : '-'}</td>
                <td class="text-end">${parseFloat(l.porcentaje_iva || 0).toFixed(0)}%</td>
                <td class="text-end">${re > 0 ? re.toFixed(2) + '%' : App.escapeHtml(l.tipo_iva || '-')}</td>
                <td class="text-end fw-semibold">${App.formatCurrency(l.total || 0)}</td>
            </tr>`;
        }).join('');

        renderPaginacionLineas(lineasAll.length);
    }

    function renderPaginacionLineas(total) {
        const cont       = document.getElementById('lineasPaginacion');
        const totalPages = Math.ceil(total / PAGE_SIZE);

        if (totalPages <= 1) {
            cont.style.display = 'none';
            return;
        }

        const from = (lineasPage - 1) * PAGE_SIZE + 1;
        const to   = Math.min(lineasPage * PAGE_SIZE, total);

        let html = `<small class="text-muted">Mostrando ${from}–${to} de ${total}</small>
            <nav><ul class="pagination pagination-sm mb-0">
            <li class="page-item${lineasPage === 1 ? ' disabled' : ''}">
                <a class="page-link" href="#" onclick="cambiarPaginaLineas(${lineasPage - 1}); return false;">Anterior</a>
            </li>`;

        for (let p = 1; p <= totalPages; p++) {
            if (p === 1 || p === totalPages || (p >= lineasPage - 2 && p <= lineasPage + 2)) {
                html += `<li class="page-item${p === lineasPage ? ' active' : ''}">
                    <a class="page-link" href="#" onclick="cambiarPaginaLineas(${p}); return false;">${p}</a>
                </li>`;
            } else if (p === lineasPage - 3 || p === lineasPage + 3) {
                html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        html += `<li class="page-item${lineasPage === totalPages ? ' disabled' : ''}">
            <a class="page-link" href="#" onclick="cambiarPaginaLineas(${lineasPage + 1}); return false;">Siguiente</a>
        </li></ul></nav>`;

        cont.innerHTML = html;
        cont.style.display = 'flex';
    }

    window.cambiarPaginaLineas = function(p) {
        const totalPages = Math.ceil(lineasAll.length / PAGE_SIZE);
        if (p < 1 || p > totalPages) return;
        lineasPage = p;
        renderLineasPage();
    };

    // ─── Totales ─────────────────────────────────────────────────────────────

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
        if (parseFloat(t.subtotal || 0) !== parseFloat(t.base_imponible || 0))
            filas.push(['Subtotal', App.formatCurrency(t.subtotal || 0)]);
        if (parseFloat(t.dto_especial  || 0) > 0) filas.push(['Dto. Especial',  '- ' + App.formatCurrency(t.dto_especial)]);
        if (parseFloat(t.dto_comercial || 0) > 0) filas.push(['Dto. Comercial', '- ' + App.formatCurrency(t.dto_comercial)]);
        if (parseFloat(t.dto_pp        || 0) > 0) filas.push(['Dto. P.P.',      '- ' + App.formatCurrency(t.dto_pp)]);
        filas.push(['Base imponible', App.formatCurrency(t.base_imponible || 0)]);

        (t.cuotas_iva || []).forEach(c => {
            filas.push([`IVA ${c.porcentaje_iva}%`, App.formatCurrency(c.cuota_iva || 0)]);
            if (parseFloat(c.cuota_re || 0) > 0)
                filas.push([`RE ${c.porcentaje_re}%`, App.formatCurrency(c.cuota_re)]);
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

    // ─── Cliente ─────────────────────────────────────────────────────────────

    function cargarCliente() {
        return new Promise(resolve => {
            App.api(API + '/facturas.php?action=cliente&codigo=' + encodeURIComponent(CODIGO))
                .done(data => {
                    renderCliente(data.success ? data.data : null);
                })
                .fail(() => renderCliente(null))
                .always(resolve);
        });
    }

    function renderCliente(c) {
        const panel = document.getElementById('clientePanel');
        if (!panel) return;

        if (!c) {
            panel.innerHTML = '<p class="text-muted mb-0"><i class="bi bi-person-dash me-1"></i>Sin cliente</p>';
            return;
        }

        panel.innerHTML = `<dl class="row mb-0">
            <dt class="col-sm-4">Nombre:</dt>
            <dd class="col-sm-8">${App.escapeHtml(c.nombre_fiscal || c.nombre || '-')}</dd>
            ${c.nif ? `<dt class="col-sm-4">NIF:</dt><dd class="col-sm-8">${App.escapeHtml(c.nif)}</dd>` : ''}
            ${c.direccion ? `<dt class="col-sm-4">Dirección:</dt><dd class="col-sm-8">${App.escapeHtml(c.direccion)}</dd>` : ''}
            ${c.poblacion ? `<dt class="col-sm-4">Población:</dt><dd class="col-sm-8">${App.escapeHtml((c.cp || '') + ' ' + c.poblacion).trim()}</dd>` : ''}
            ${c.email ? `<dt class="col-sm-4">Email:</dt><dd class="col-sm-8"><a href="mailto:${App.escapeHtml(c.email)}">${App.escapeHtml(c.email)}</a></dd>` : ''}
        </dl>`;
    }

    // ─── Pago ─────────────────────────────────────────────────────────────────

    function renderPago(total, cobrado, esCobrada) {
        const badge = document.getElementById('pagoBadge');
        const panel = document.getElementById('pagoPanel');
        if (!panel) return;

        const pendiente = Math.max(0, Math.round((total - cobrado) * 100) / 100);

        if (badge) {
            if (esCobrada) {
                badge.className   = 'badge bg-success';
                badge.textContent = 'Cobrado';
            } else {
                badge.className   = 'badge bg-warning text-dark';
                badge.textContent = 'Pendiente';
            }
        }

        if (esCobrada) {
            panel.innerHTML = `
                <div class="text-center py-2">
                    <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i>
                    <p class="mb-0 text-success fw-semibold">Factura cobrada en su totalidad</p>
                </div>`;
            return;
        }

        const hoy = new Date().toISOString().split('T')[0];

        panel.innerHTML = `
            <dl class="row mb-3">
                <dt class="col-sm-5">Total factura:</dt>
                <dd class="col-sm-7 fw-semibold">${App.formatCurrency(total)}</dd>
                ${cobrado > 0 ? `
                <dt class="col-sm-5">Ya cobrado:</dt>
                <dd class="col-sm-7 text-success">${App.formatCurrency(cobrado)}</dd>` : ''}
                <dt class="col-sm-5">Pendiente:</dt>
                <dd class="col-sm-7 fw-bold text-danger" id="pagoPendiente">${App.formatCurrency(pendiente)}</dd>
            </dl>
            <div class="row g-2 align-items-end">
                <div class="col-sm-5">
                    <label class="form-label form-label-sm mb-1">Fecha</label>
                    <input type="date" id="pagoFecha" class="form-control form-control-sm" value="${hoy}">
                </div>
                <div class="col-sm-5">
                    <label class="form-label form-label-sm mb-1">Importe a pagar</label>
                    <input type="number" id="pagoImporte" class="form-control form-control-sm"
                           min="0.01" max="${pendiente}" step="0.01"
                           placeholder="${App.formatCurrency(pendiente)}">
                </div>
                <div class="col-sm-2">
                    <button class="btn btn-success btn-sm w-100" onclick="registrarPago()">
                        <i class="bi bi-check-lg"></i> Pagar
                    </button>
                </div>
            </div>`;

        // Evitar negativos en el input
        document.getElementById('pagoImporte').addEventListener('input', function() {
            if (parseFloat(this.value) < 0) this.value = '';
        });
    }

    window.registrarPago = function() {
        const importeInput = document.getElementById('pagoImporte');
        const fechaInput   = document.getElementById('pagoFecha');
        const importe      = parseFloat(importeInput?.value || 0);
        const fecha        = fechaInput?.value || new Date().toISOString().split('T')[0];

        if (!importe || importe <= 0) {
            App.notify('Introduce un importe válido', 'warning');
            return;
        }

        App.showLoading();
        App.api(API + '/facturas.php?action=pagar', {
            method: 'POST',
            data: JSON.stringify({ codigo: CODIGO, importe, fecha }),
            contentType: 'application/json'
        })
        .done(data => {
            if (!data.success) {
                App.notify(data.message || 'Error al registrar el pago', 'danger');
                return;
            }
            if (data.data.cobrada) {
                App.notify('Factura cobrada en su totalidad', 'success');
                renderPago(facturaTotal, facturaTotal, true);
                // Actualizar badge de cobrado en la cabecera de factura
                const cobradoEl = document.querySelector('#facturaData .badge.bg-warning, #facturaData .badge.bg-success');
                if (cobradoEl && cobradoEl.textContent !== 'Cobrado') {
                    cobradoEl.className   = 'badge bg-success';
                    cobradoEl.textContent = 'Cobrado';
                }
            } else {
                App.notify(`Pago de ${App.formatCurrency(importe)} registrado`, 'success');
                renderPago(facturaTotal, data.data.importe_cobrado, false);
            }
        })
        .fail(() => App.notify('Error de conexión', 'danger'))
        .always(() => App.hideLoading());
    };

    // ─── Verifactu ───────────────────────────────────────────────────────────

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

        const estadoMap = {
            'PENDIENTE': ['warning text-dark', 'Pendiente'],
            'GENERADO':  ['info text-dark',    'Generado'],
            'ENVIADO':   ['success',            'Enviado'],
            'ERROR':     ['danger',             'Error'],
            'ANULADO':   ['secondary',          'Anulado']
        };
        const [color, label] = estadoMap[v?.estado] || ['secondary', v?.estado || '-'];

        if (badge) {
            badge.className   = `badge bg-${color}`;
            badge.textContent = label;
        }

        if (!panel) return;

        let detalles = '<dl class="row mb-0">';
        if (v.fecha_envio)
            detalles += `<dt class="col-sm-4">Fecha envío:</dt>
                         <dd class="col-sm-8">${new Date(v.fecha_envio).toLocaleString('es-ES')}</dd>`;
        if (v.hash)
            detalles += `<dt class="col-sm-4">Huella:</dt>
                         <dd class="col-sm-8"><code class="small text-break">${App.escapeHtml(v.hash.substring(0, 32))}…</code></dd>`;
        if (v.csv)
            detalles += `<dt class="col-sm-4">CSV:</dt>
                         <dd class="col-sm-8"><code class="text-success">${App.escapeHtml(v.csv)}</code></dd>`;
        if (v.url_verificacion)
            detalles += `<dt class="col-sm-4">Verificación:</dt>
                         <dd class="col-sm-8"><a href="${App.escapeHtml(v.url_verificacion)}" target="_blank" rel="noopener">Ver en AEAT</a></dd>`;
        if (v.descripcion_error)
            detalles += `<dt class="col-sm-4">${v.estado === 'ENVIADO' ? 'Error anterior:' : 'Error:'}</dt>
                         <dd class="col-sm-8 ${v.estado !== 'ENVIADO' ? 'text-danger' : ''}">${App.escapeHtml(v.descripcion_error)}</dd>`;
        if (v.reintentos > 0)
            detalles += `<dt class="col-sm-4">Reintentos:</dt>
                         <dd class="col-sm-8">${v.reintentos}</dd>`;
        detalles += '</dl>';

        let acciones = '';
        if (v.estado === 'ERROR')
            acciones = '<button class="btn btn-outline-primary btn-sm me-1" onclick="reintentar()"><i class="bi bi-arrow-clockwise me-1"></i>Reintentar</button>';
        else if (v.estado !== 'ENVIADO' && v.estado !== 'ANULADO')
            acciones = '<button class="btn btn-success btn-sm" onclick="firmarYEnviar()"><i class="bi bi-send me-1"></i>Enviar a Hacienda</button>';

        panel.innerHTML = detalles + (acciones ? `<hr><div class="text-center">${acciones}</div>` : '');

        if (v.estado === 'ENVIADO' || v.estado === 'ANULADO') {
            const btn = document.getElementById('btnVerifactu');
            if (btn) btn.style.display = 'none';
        }
    }

    function renderVerifactuVacio() {
        const badge = document.getElementById('verifactuBadge');
        const panel = document.getElementById('verifactuPanel');
        if (badge) { badge.className = 'badge bg-secondary'; badge.textContent = 'Sin enviar'; }
        if (panel) panel.innerHTML = `
            <p class="text-muted mb-2">Esta factura aún no ha sido enviada a Hacienda.</p>
            <div class="text-center">
                <button class="btn btn-success btn-sm" onclick="firmarYEnviar()">
                    <i class="bi bi-send me-1"></i>Enviar a Hacienda
                </button>
            </div>`;
    }

    // ─── Acciones globales ────────────────────────────────────────────────────

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

    window.reintentar = function() {
        if (!confirm('¿Reintentar el envío de esta factura a Hacienda?')) return;
        App.showLoading();
        App.api(API + '/verifactu.php/reintentar/FACTURA/' + encodeURIComponent(CODIGO), {
            method: 'POST'
        })
        .done(data => {
            if (data.success) {
                App.notify('Factura enviada correctamente', 'success');
                setTimeout(() => cargarVerifactu(), 1500);
            } else {
                App.notify(data.message || 'Error al reintentar', 'danger');
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
            API + '/facturas.php?action=ver_pdf&codigo=' + encodeURIComponent(CODIGO),
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
