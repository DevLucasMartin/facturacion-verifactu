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
    $(document).on('click', '#registrosBody .vf-btn-detalles', function () {
        verDetalles($(this).data('tipo') + '/' + $(this).data('id'));
    });

    // Reenviar (subsanación) — solo aparece en filas en ERROR
    $(document).on('click', '#registrosBody .vf-btn-reenviar', function () {
        reenviarError($(this).data('tipo'), $(this).data('id'), $(this).data('doc'));
    });

    // Procesar cola (envío diferido de facturas pendientes)
    $('#btnProcesarCola').on('click', function () {
        procesarCola(false);
    });

    // Auto-envío al recuperar Internet: cuando el navegador detecta que vuelve
    // la conexión, intenta vaciar la cola sin que el usuario haga nada.
    window.addEventListener('online', function () {
        procesarCola(true);
    });

    cargarCertificado();
    cargarEstadisticas();
    cargarRegistros();
});

function cargarCertificado() {
    App.api(FACT_API_BASE + '/verifactu.php/certificado').done(function (response) {
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
    App.api(FACT_API_BASE + '/verifactu.php/stats').done(function (response) {
        const stats = response.data || {};
        $('#statTotal').text(stats.total || 0);
        $('#statEnviados').text(stats.enviados || 0);
        $('#statPendientes').text(stats.pendientes || 0);
        $('#statErrores').text(stats.errores || 0);

        // Cola de envío diferido = solo PENDIENTE (facturas hechas sin conexión).
        // ERROR (rechazo AEAT) y GENERADO (retenida) se excluyen a propósito.
        const enCola = (stats.pendientes || 0);
        actualizarBotonCola(enCola);
    });
}

// Ajusta el contador y habilita/deshabilita el botón "Procesar cola".
function actualizarBotonCola(enCola) {
    const btn = $('#btnProcesarCola');
    if (btn.data('procesando')) return;   // no tocar mientras envía
    $('#colaCount').text(enCola);
    btn.prop('disabled', enCola === 0);
}

// Envía la cola pendiente a Hacienda. auto=true cuando lo dispara el evento
// 'online' (silencia avisos de "no hay nada" / "sin conexión").
function procesarCola(auto = false) {
    const btn = $('#btnProcesarCola');
    if (btn.data('procesando')) return;             // evitar dobles clics / dobles disparos
    if (auto && Number($('#colaCount').text()) === 0) return; // nada que enviar

    const original = btn.html();
    btn.data('procesando', true).prop('disabled', true)
       .html('<span class="spinner-border spinner-border-sm me-1" role="status"></span>Enviando…');

    App.api(FACT_API_BASE + '/verifactu.php/enviar-cola', { method: 'POST' })
        .done(function (response) {
            const r = response.data || {};

            if (r.sin_conexion) {
                if (!auto) App.notify('Sin conexión con la AEAT. Las facturas siguen en cola.', 'warning');
            } else if (r.detenido) {
                App.notify(
                    'Cola detenida tras ' + (r.enviados || 0) + ' envío(s): ' + (r.motivo_parada || 'revise el registro'),
                    'danger', 9000
                );
            } else if ((r.total_cola || 0) === 0) {
                if (!auto) App.notify('No hay facturas pendientes en la cola', 'info');
            } else {
                App.notify((r.enviados || 0) + ' factura(s) enviada(s) a Hacienda correctamente', 'success');
            }
        })
        .fail(function () {
            if (!auto) App.notify('Error al procesar la cola', 'danger');
        })
        .always(function () {
            btn.data('procesando', false).html(original);
            recargarEstadisticas();   // refresca KPIs, tabla y el propio botón
        });
}

function cargarRegistros(page = 1) {
    vf_currentPage = page;
    let url = FACT_API_BASE + '/verifactu.php?page=' + page + '&per_page=20';

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

    const tipoDocLabels = {
        FACTURA:        'Factura',
        COMPLETA:       'Factura',
        RECTIFICATIVA:  'Rectificativa',
        RECAPITULATIVA: 'Recapitulativa',
        SIMPLIFICADA:   'Simplificada'
    };

    tbody.html(registros.map(function (registro) {
        const estado  = estadoLabels[registro.Estado_Envio] || { label: registro.Estado_Envio || 'Desconocido', class: 'bg-secondary' };
        const cliente = registro.Cliente_Nombre || 'Sin cliente';
        const total   = parseFloat(registro.Factura_Total || 0);
        const tipoDoc = tipoDocLabels[registro.Tipo_Documento] || registro.Tipo_Origen || '-';

        return `
            <tr class="${registro.Estado_Envio === 'ERROR' ? 'table-danger' : ''}">
                <td>
                    <strong>${vf_escapeHtml(registro.Id_Documento)}</strong><br>
                    <small class="text-muted">${vf_escapeHtml(tipoDoc)}</small>
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
                        <button class="btn btn-outline-info vf-btn-detalles" title="Ver detalles"
                            data-tipo="${vf_escapeHtml(registro.Tipo_Origen)}" data-id="${vf_escapeHtml(registro.Id_Documento)}">
                            <i class="bi bi-eye"></i>
                        </button>
                        ${registro.Estado_Envio === 'ERROR' ? `
                        <button class="btn btn-outline-warning vf-btn-reenviar" title="Reenviar a Hacienda (subsanación, sin re-firmar)"
                            data-tipo="${vf_escapeHtml(registro.Tipo_Origen)}" data-id="${vf_escapeHtml(registro.Id_Documento)}" data-doc="${vf_escapeHtml(registro.Id_Documento)}">
                            <i class="bi bi-arrow-repeat"></i>
                        </button>` : ''}
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

function verDetalles(tipoCodigo) {
    App.api(FACT_API_BASE + '/verifactu.php/estado/' + tipoCodigo)
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

// Reenvía a Hacienda una factura rechazada (subsanación). NO re-firma: reutiliza
// el registro existente y su huella, así la cadena de las posteriores no se rompe.
function reenviarError(tipo, id, doc) {
    App.confirm({
        icon:        'question',
        title:       'Reenviar a Hacienda',
        text:        'Se reenviará ' + (doc || id) + ' con sus datos actuales, sin re-firmar (misma huella). ' +
                     'Asegúrate de haber corregido antes el motivo del rechazo (p. ej. el NIF del cliente).',
        confirmText: 'Reenviar',
    }).then(function (ok) {
        if (!ok) return;

        App.showLoading();
        App.api(FACT_API_BASE + '/verifactu.php/reenviar/' + tipo + '/' + encodeURIComponent(id), { method: 'POST' })
            .done(function (response) {
                App.notify((response && response.message) || 'Factura reenviada a Hacienda correctamente', 'success');
            })
            .fail(function (xhr, status, err, parsed) {
                App.notify((parsed && parsed.message) || 'Error al reenviar. Revisa el motivo del rechazo.', 'danger', 8000);
            })
            .always(function () {
                App.hideLoading();
                recargarEstadisticas();   // refresca tabla/KPIs: si fue OK, la fila pasa a Enviado
            });
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
