const BASE_FACT = (() => {
    const p = window.location.pathname;
    const idx = p.indexOf('/SistemaGestionFacturas/');
    return idx >= 0 ? p.substring(0, idx) + '/SistemaGestionFacturas' : '/SistemaGestionFacturas';
})();

const FACT_API_BASE = BASE_FACT + '/src/api';

let vf_currentPage   = 1;
let vf_filtroEstado  = '';
let vf_filtroTipo    = '';
let vf_modalDetalles = null;

$(document).ready(function () {
    vf_modalDetalles = new bootstrap.Modal(document.getElementById('detallesModal'));

    // Paginación
    $(document).on('click', '#paginationNav .page-link', function (e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page !== undefined && !$(this).closest('.page-item').hasClass('disabled')) {
            cargarRegistros(Number(page));
        }
    });

    // Botones de acción en registros
    $(document).on('click', '#registrosBody .vf-btn-reintentar', function () {
        reintentar($(this).data('tipo') + '/' + $(this).data('id'));
    });
    $(document).on('click', '#registrosBody .vf-btn-enviar', function () {
        enviar($(this).data('tipo') + '/' + $(this).data('id'));
    });
    $(document).on('click', '#registrosBody .vf-btn-detalles', function () {
        verDetalles($(this).data('tipo') + '/' + $(this).data('id'));
    });

    cargarCertificado();
    cargarEstadisticas();
    cargarRegistros();
});

function cargarCertificado() {
    App.api(FACT_API_BASE + '/verifactu.php/verifactu/certificado').done(function (response) {
        if (!response.data) return;
        const cert   = response.data;
        const banner = $('#certExpiryBanner');
        const msg    = $('#certExpiryMessage');

        if (!cert.configurado) {
            msg.text('El certificado VeriFACTU no está configurado. Las facturas no podrán enviarse a Hacienda.');
            banner.removeClass('d-none alert-warning alert-danger').addClass('alert-danger');
        }
    });
}

function cargarEstadisticas() {
    App.api(FACT_API_BASE + '/verifactu.php/verifactu/stats').done(function (response) {
        const stats = response.data || {};
        $('#statTotal').text(stats.total || 0);
        $('#statEnviados').text(stats.enviados || 0);
        $('#statPendientes').text(stats.pendientes || 0);
        $('#statErrores').text(stats.errores || 0);
    });
}

function cargarRegistros(page = 1) {
    vf_currentPage = page;
    let url = FACT_API_BASE + '/verifactu.php/verifactu?page=' + page + '&per_page=20';

    if (vf_filtroEstado) url += '&estado=' + vf_filtroEstado;
    if (vf_filtroTipo)   url += '&tipo='   + vf_filtroTipo;

    App.showLoading();
    App.api(url)
        .done(function (response) {
            const data       = response.data || {};
            const registros  = Array.isArray(data) ? data : (data.items ?? []);
            const total      = Number(response.total ?? data.total ?? registros.length);
            const pagination = {
                page:        Number(response.page  ?? data.page  ?? 1),
                per_page:    20,
                total:       total,
                total_pages: Number(response.pages ?? data.total_pages ?? Math.ceil(total / 20))
            };
            renderRegistros(registros);
            renderPaginacion(pagination);
        })
        .fail(function () {
            renderRegistros([]);
            renderPaginacion({});
        })
        .always(function () {
            App.hideLoading();
        });
}

function renderRegistros(registros) {
    const tbody = $('#registrosBody');
    $('#registrosCount').text(registros.length + ' registros');

    if (!registros || registros.length === 0) {
        tbody.html(`
            <tr>
                <td colspan="7" class="text-center py-4 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    No hay registros de Verifactu
                </td>
            </tr>`);
        return;
    }

    const estadoLabels = {
        PENDIENTE: { label: 'Pendiente', class: 'bg-warning text-dark' },
        ENVIADO:   { label: 'Enviado',   class: 'bg-success' },
        ERROR:     { label: 'Error',     class: 'bg-danger' },
        ANULADO:   { label: 'Anulado',   class: 'bg-secondary' }
    };

    tbody.html(registros.map(function (registro) {
        const estado  = estadoLabels[registro.Estado_Envio] || { label: registro.Estado_Envio || 'Desconocido', class: 'bg-secondary' };
        const cliente = registro.Cliente_Nombre || 'Sin cliente';
        const total   = parseFloat(registro.Factura_Total || 0);

        return `
            <tr class="${registro.Estado_Envio === 'ERROR' ? 'table-danger' : ''}">
                <td>
                    <strong>${vf_escapeHtml(registro.Id_Documento)}</strong><br>
                    <small class="text-muted">${vf_escapeHtml(registro.Tipo_Origen)}</small>
                </td>
                <td>${vf_formatFecha(registro.Fecha_Generacion)}</td>
                <td>${vf_escapeHtml(cliente.substring(0, 35))}</td>
                <td class="text-end"><strong>${App.formatCurrency(total)}</strong></td>
                <td><span class="badge ${estado.class}">${estado.label}</span></td>
                <td>
                    ${registro.CSV_Hacienda
                        ? '<code class="small">' + vf_escapeHtml(registro.CSV_Hacienda) + '</code>'
                        : '-'}
                </td>
                <td class="text-center">
                    <div class="btn-group btn-group-sm fact-btn-group">
                        ${registro.Estado_Envio === 'ERROR' ? `
                        <button class="btn btn-outline-primary vf-btn-reintentar" title="Reintentar"
                            data-tipo="${vf_escapeHtml(registro.Tipo_Origen)}" data-id="${vf_escapeHtml(registro.Id_Documento)}">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>` : ''}
                        ${registro.Estado_Envio === 'PENDIENTE' || registro.Estado_Envio === 'GENERADO' ? `
                        <button class="btn btn-outline-success vf-btn-enviar" title="Enviar a Hacienda"
                            data-tipo="${vf_escapeHtml(registro.Tipo_Origen)}" data-id="${vf_escapeHtml(registro.Id_Documento)}">
                            <i class="bi bi-send"></i>
                        </button>` : ''}
                        <button class="btn btn-outline-info vf-btn-detalles" title="Ver detalles"
                            data-tipo="${vf_escapeHtml(registro.Tipo_Origen)}" data-id="${vf_escapeHtml(registro.Id_Documento)}">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </td>
            </tr>`;
    }).join(''));
}

function renderPaginacion(pagination) {
    const nav  = $('#paginationNav');
    const info = $('#paginationInfo');

    if (!pagination || pagination.total_pages <= 1) {
        nav.html('');
        info.text('');
        return;
    }

    info.text(`Página ${pagination.page} de ${pagination.total_pages} (${pagination.total} total)`);

    let html = '';
    html += `<li class="page-item ${pagination.page <= 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pagination.page - 1}">Anterior</a>
             </li>`;

    for (let i = 1; i <= pagination.total_pages; i++) {
        if (i === 1 || i === pagination.total_pages || (i >= pagination.page - 2 && i <= pagination.page + 2)) {
            html += `<li class="page-item ${i === pagination.page ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                     </li>`;
        } else if (i === pagination.page - 3 || i === pagination.page + 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }

    html += `<li class="page-item ${pagination.page >= pagination.total_pages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pagination.page + 1}">Siguiente</a>
             </li>`;

    nav.html(html);
}

function aplicarFiltro() {
    vf_filtroEstado = $('#filtroEstado').val();
    vf_filtroTipo   = $('#filtroTipo').val();
    cargarRegistros(1);
}

function recargarEstadisticas() {
    cargarCertificado();
    cargarEstadisticas();
    cargarRegistros(vf_currentPage);
}

function procesarCola() {
    if (!confirm('¿Procesar la cola de facturas pendientes? Esto enviará todas las facturas pendientes a Hacienda.')) return;

    App.showLoading();
    App.api(FACT_API_BASE + '/verifactu.php/verifactu/cola', { method: 'POST' })
        .done(function (response) {
            const resultado = response.data || {};
            const msg = `Cola procesada: ${resultado.enviados || 0} enviados, ${resultado.errores || 0} errores`;
            App.notify(msg, resultado.errores > 0 ? 'warning' : 'success');
            recargarEstadisticas();
        })
        .fail(function (xhr) {
            App.notify(xhr.responseJSON?.message || 'Error al procesar la cola', 'danger');
        })
        .always(function () {
            App.hideLoading();
        });
}

function reintentar(codigo) {
    if (!confirm('¿Reintentar el envío de este documento a Hacienda?')) return;

    App.showLoading();
    App.api(FACT_API_BASE + '/verifactu.php/verifactu/reintentar/' + codigo, { method: 'POST' })
        .done(function () {
            App.notify('Documento enviado correctamente', 'success');
            recargarEstadisticas();
        })
        .fail(function (xhr) {
            App.notify(xhr.responseJSON?.message || 'Error al reintentar', 'danger');
        })
        .always(function () {
            App.hideLoading();
        });
}

function enviar(codigo) {
    if (!confirm('¿Enviar este documento a Hacienda?')) return;

    const partes = codigo.split('/');
    App.showLoading();
    App.api(FACT_API_BASE + '/verifactu.php/verifactu/enviar', {
        method:      'POST',
        data:        JSON.stringify({ tipo_origen: partes[0], id_documento: partes[1] }),
        contentType: 'application/json'
    })
        .done(function () {
            App.notify('Documento enviado correctamente', 'success');
            recargarEstadisticas();
        })
        .fail(function (xhr) {
            App.notify(xhr.responseJSON?.message || 'Error al enviar', 'danger');
        })
        .always(function () {
            App.hideLoading();
        });
}

function verDetalles(tipoCodigo) {
    App.api(FACT_API_BASE + '/verifactu.php/verifactu/estado/' + tipoCodigo)
        .done(function (response) {
            const registro = response.data;
            if (!registro) {
                App.notify('Registro no encontrado', 'warning');
                return;
            }

            const estadoLabels = {
                PENDIENTE: { label: 'Pendiente', class: 'bg-warning text-dark' },
                ENVIADO:   { label: 'Enviado',   class: 'bg-success' },
                ERROR:     { label: 'Error',     class: 'bg-danger' },
                ANULADO:   { label: 'Anulado',   class: 'bg-secondary' }
            };
            const estado = estadoLabels[registro.Estado_Envio] || { label: registro.Estado_Envio, class: 'bg-secondary' };

            const html = `
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Documento:</strong> ${vf_escapeHtml(registro.Id_Documento)}</p>
                        <p><strong>Registro:</strong> ${vf_escapeHtml(registro.Id || '-')}</p>
                        <p><strong>Tipo:</strong> ${vf_escapeHtml(registro.Tipo_Origen)}</p>
                        <p><strong>Fecha:</strong> ${vf_formatFechaObj(registro.Fecha_Generacion)}</p>
                        <p><strong>Fecha Envío:</strong> ${vf_formatFechaObj(registro.Fecha_Envio)}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Estado:</strong> <span class="badge ${estado.class}">${estado.label}</span></p>
                        <p><strong>CSV:</strong> ${registro.CSV_Hacienda ? '<code>' + vf_escapeHtml(registro.CSV_Hacienda) + '</code>' : '-'}</p>
                        <p><strong>Verificar en AEAT:</strong> ${registro.URL_Verificacion ? '<a href="' + vf_escapeHtml(registro.URL_Verificacion) + '" target="_blank" rel="noopener noreferrer">Ver en Hacienda <i class="bi bi-box-arrow-up-right"></i></a>' : '-'}</p>
                        <p><strong>Huella:</strong> <code class="small">${registro.Huella_Actual ? vf_escapeHtml(registro.Huella_Actual.substring(0, 32)) + '...' : '-'}</code></p>
                        <p><strong>Reintentos:</strong> ${registro.Reintentos || 0}</p>
                    </div>
                </div>
                ${registro.Ultimo_Error ? `
                <div class="alert alert-danger mt-3">
                    <strong>Error:</strong> ${vf_escapeHtml(registro.Ultimo_Error)}
                </div>` : ''}`;

            $('#detallesContent').html(html);
            vf_modalDetalles.show();
        })
        .fail(function () {
            App.notify('Error al cargar detalles', 'danger');
        });
}

// ====== Helpers ======
function vf_formatFecha(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr.date || dateStr);
    return d.toLocaleDateString('es-ES', { year: 'numeric', month: 'short', day: 'numeric' });
}

function vf_formatFechaObj(val) {
    if (!val) return '-';
    const d = new Date((val && val.date) ? val.date : val);
    return isNaN(d) ? '-' : d.toLocaleDateString('es-ES', { year: 'numeric', month: 'short', day: 'numeric' });
}

function vf_escapeHtml(str) {
    return String(str ?? '')
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;').replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
