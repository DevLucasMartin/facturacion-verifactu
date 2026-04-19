var codigoAlbaran = document.getElementById('app-data').dataset.codigo || '';

const BASE_VER = (() => {
    const p = window.location.pathname;
    const idx = p.indexOf('/SistemaGestionFacturas/');
    return idx >= 0 ? p.substring(0, idx) + '/SistemaGestionFacturas' : '/SistemaGestionFacturas';
})();

const API_VER = BASE_VER + '/src/api';

function fmtDate(v) {
    if (!v) return '-';
    var s = String(v).replace('T', ' ');
    var datePart = s.split(' ')[0] || s;
    var p = datePart.split('-');
    if (p.length === 3) return p[2] + '/' + p[1] + '/' + p[0];
    return s;
}

function fmtNum(v, dec) {
    dec = dec !== undefined ? dec : 2;
    return (parseFloat(v) || 0).toLocaleString('es-ES', {
        minimumFractionDigits: dec,
        maximumFractionDigits: dec
    });
}

function fmtEur(v) {
    return fmtNum(v, 2) + ' €';
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

var tiposIVA = {};

function fmtTipoIVA(linea) {
    var tipo = tiposIVA[linea.Id_Tipo_IVA];
    if (tipo) {
        return escapeHtml(tipo.Descripcion || linea.Id_Tipo_IVA);
    }
    return escapeHtml(linea.Id_Tipo_IVA || '-');
}

$(document).ready(function() {
    App.api(API_VER + '/tiposiva.php')
        .done(function(res) {
            (res.data || []).forEach(function(t) {
                tiposIVA[t.Codigo] = t;
            });
        })
        .always(function() {
            cargarAlbaran(codigoAlbaran);
        });
});

function cargarAlbaran(codigo) {
    App.api(API_VER + '/albaranes.php/' + encodeURIComponent(codigo))
        .done(function(response) {
            var a = response.data;
            renderAlbaranData(a);
            renderTotales(a);
            renderCliente(a);
            renderLineas(a.lineas || []);
        })
        .fail(function(xhr) {
            var msg = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'Error al cargar el albarán';
            $('#albaranData').html(
                '<div class="alert alert-danger mb-0"><strong>Error:</strong> ' +
                escapeHtml(msg) + '</div>'
            );
            $('#lineasBody').html(
                '<tr><td colspan="8" class="text-center text-danger py-3">No se pudieron cargar las líneas.</td></tr>'
            );
            $('#totalesPanel').html('<div class="text-danger">No se pudieron cargar los totales.</div>');
            $('#clientePanel').html('<div class="text-danger">No se pudo cargar el cliente.</div>');
        });
}

function renderAlbaranData(a) {
    var cerrado   = String(a.Cerrado   || '').toUpperCase() === 'S';
    var facturado = String(a.Facturado || '').toUpperCase() === 'S';

    var html =
        '<div class="row">' +
        '<div class="col-md-6"><dl class="row mb-0">' +
        '<dt class="col-sm-4">Código:</dt><dd class="col-sm-8"><strong>' +
        escapeHtml(a.Codigo || '') + '</strong></dd>' +
        '<dt class="col-sm-4">Fecha:</dt><dd class="col-sm-8">' +
        fmtDate(a.Fecha) + '</dd>' +
        '<dt class="col-sm-4">Canal:</dt><dd class="col-sm-8">' +
        escapeHtml(a.Id_Canal || '-') + '</dd>' +
        '</dl></div>' +
        '<div class="col-md-6"><dl class="row mb-0">' +
        '<dt class="col-sm-4">Estado:</dt><dd class="col-sm-8">' +
        '<span class="badge ' + (cerrado ? 'bg-success' : 'bg-warning text-dark') + '">' +
        (cerrado ? 'Cerrado' : 'Abierto') + '</span>' +
        '</dd>' +
        '<dt class="col-sm-4">Facturado:</dt><dd class="col-sm-8">' +
        '<span class="badge ' + (facturado ? 'bg-success' : 'bg-secondary') + '">' +
        (facturado ? 'Sí' : 'No') + '</span>' +
        '</dd>' +
        '</dl></div>' +
        '</div>';

    if (a.Observaciones) {
        html += '<hr><p class="mb-0"><strong>Observaciones:</strong> ' + escapeHtml(a.Observaciones) + '</p>';
    }

    $('#albaranData').html(html);

    // Ocultar botón Facturar si ya está completamente facturado
    if (facturado) {
        $('#btnFacturar').hide();
    }
}

function renderTotales(a) {
    var dtoLineas = (a.lineas || []).reduce(function(acc, l) {
        return acc + (parseFloat(l.Importe_Descuento) || 0);
    }, 0);

    var dtoTotal = dtoLineas
        + (parseFloat(a.Importe_Dto_Especial)  || 0)
        + (parseFloat(a.Importe_Dto_PP)         || 0)
        + (parseFloat(a.Importe_Dto_Comercial)  || 0);

    var html =
        '<dl class="row mb-0">' +
        '<dt class="col-7">Subtotal:</dt><dd class="col-5 text-end">' + fmtEur(a.Importe_Bruto) + '</dd>';

    if (dtoTotal > 0) {
        html += '<dt class="col-7 text-danger">Descuentos:</dt><dd class="col-5 text-end text-danger">-' + fmtEur(dtoTotal) + '</dd>';
    }

    html +=
        '<dt class="col-7">Base Imponible:</dt><dd class="col-5 text-end">' + fmtEur(a.Base_Imponible) + '</dd>' +
        '<dt class="col-7">IVA:</dt><dd class="col-5 text-end">' + fmtEur(a.Importe_IVA) + '</dd>';

    if (parseFloat(a.Importe_RE) > 0) {
        html += '<dt class="col-7">RE:</dt><dd class="col-5 text-end">' + fmtEur(a.Importe_RE) + '</dd>';
    }

    html +=
        '<hr><dt class="col-7"><strong>Total:</strong></dt>' +
        '<dd class="col-5 text-end"><strong>' + fmtEur(a.Total) + '</strong></dd>' +
        '</dl>';

    $('#totalesPanel').html(html);
}

function renderCliente(a) {
    var c = a.cliente;
    if (!c) {
        $('#clientePanel').html('<div class="text-muted">Sin datos de cliente.</div>');
        return;
    }
    var html =
        '<dl class="row mb-0">' +
        '<dt class="col-sm-4">Nombre:</dt><dd class="col-sm-8">' + escapeHtml(c.Archivar_Como || '-') + '</dd>' +
        '<dt class="col-sm-4">Id Cliente:</dt><dd class="col-sm-8">' + escapeHtml(c.Codigo || '-') + '</dd>' +
        '<dt class="col-sm-4">NIF:</dt><dd class="col-sm-8">' + escapeHtml(c.NIF || '-') + '</dd>' +
        '<dt class="col-sm-4">Dirección:</dt><dd class="col-sm-8">' + escapeHtml(c.Direccion || '-') + '</dd>' +
        '<dt class="col-sm-4">Forma Pago:</dt><dd class="col-sm-8">' + escapeHtml(a.Id_Forma_Pago || '-') + '</dd>' +
        '<dt class="col-sm-4">Tipo IVA:</dt><dd class="col-sm-8">' + escapeHtml(c.Id_Tipo_IVA || '-') + '</dd>' +
        '<dt class="col-sm-4">Aplica RE:</dt><dd class="col-sm-8">' + (c.Aplica_RE == 1 ? 'Sí' : 'No') + '</dd>' +
        '</dl>';
    $('#clientePanel').html(html);
}

var lineasAll  = [];
var lineasPage = 1;
var PAGE_SIZE  = 10;

function renderLineas(lineas) {
    if (!lineas.length) {
        $('#lineasBody').html('<tr><td colspan="8" class="text-center text-muted py-3">Sin líneas.</td></tr>');
        $('#lineasPaginacion').hide();
        $('#lineasCount').text('');
        return;
    }
    lineasAll  = lineas;
    lineasPage = 1;
    $('#lineasCount').text(lineas.length + ' líneas');
    renderLineasPage();
}

function renderLineasPage() {
    var start = (lineasPage - 1) * PAGE_SIZE;
    var end   = start + PAGE_SIZE;
    var slice = lineasAll.slice(start, end);

    var rows = slice.map(function(l, i) {
        return '<tr>' +
            '<td>' + (parseInt(l.Linea) || start + i + 1) + '</td>' +
            '<td><strong>' + escapeHtml(l.Id_Articulo || '') + '</strong><br>' +
            '<small class="text-muted">' + escapeHtml(l.Descripcion || '') + '</small></td>' +
            '<td class="text-end">' + fmtNum(l.Cantidad) + '</td>' +
            '<td class="text-end">' + fmtEur(l.Precio) + '</td>' +
            '<td class="text-end">' + fmtNum(l.Descuento) + '%</td>' +
            '<td class="text-end">' + escapeHtml(l.Id_Tipo_IVA || '-') + '</td>' +
            '<td class="text-end">' + fmtTipoIVA(l) + '</td>' +
            '<td class="text-end"><strong>' + fmtEur(l.Total) + '</strong></td>' +
            '</tr>';
    });
    $('#lineasBody').html(rows.join(''));
    renderPaginacion(lineasAll.length);
}

function renderPaginacion(total) {
    var totalPages = Math.ceil(total / PAGE_SIZE);
    if (totalPages <= 1) {
        $('#lineasPaginacion').hide();
        return;
    }
    var from = (lineasPage - 1) * PAGE_SIZE + 1;
    var to   = Math.min(lineasPage * PAGE_SIZE, total);

    var html =
        '<small class="text-muted">Mostrando ' + from + '–' + to + ' de ' + total + '</small>' +
        '<nav><ul class="pagination pagination-sm mb-0">' +
        '<li class="page-item' + (lineasPage === 1 ? ' disabled' : '') + '">' +
        '<a class="page-link" href="#" onclick="cambiarPagina(' + (lineasPage - 1) + '); return false;">Anterior</a></li>';

    for (var p = 1; p <= totalPages; p++) {
        if (p === 1 || p === totalPages || (p >= lineasPage - 2 && p <= lineasPage + 2)) {
            html += '<li class="page-item' + (p === lineasPage ? ' active' : '') + '">' +
                '<a class="page-link" href="#" onclick="cambiarPagina(' + p + '); return false;">' + p + '</a></li>';
        } else if (p === lineasPage - 3 || p === lineasPage + 3) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    html +=
        '<li class="page-item' + (lineasPage === totalPages ? ' disabled' : '') + '">' +
        '<a class="page-link" href="#" onclick="cambiarPagina(' + (lineasPage + 1) + '); return false;">Siguiente</a></li>' +
        '</ul></nav>';

    $('#lineasPaginacion').html(html).css('display', 'flex');
}

function cambiarPagina(p) {
    var totalPages = Math.ceil(lineasAll.length / PAGE_SIZE);
    if (p < 1 || p > totalPages) return;
    lineasPage = p;
    renderLineasPage();
}

function verPdf() {
    window.open(API_VER + '/albaranes.php/' + encodeURIComponent(codigoAlbaran) + '/vista', '_blank');
}

function descargarPdf() {
    window.location = API_VER + '/albaranes.php/' + encodeURIComponent(codigoAlbaran) + '/descargar';
}

function enviarEmail(proforma) {
    var email = prompt('Introduce el email de destino:');
    if (!email || email.trim() === '') return;

    App.showLoading();
    App.api(API_VER + '/albaranes.php/' + encodeURIComponent(codigoAlbaran) + '/email', {
        method:      'POST',
        contentType: 'application/json',
        data:        JSON.stringify({ email: email, proforma: proforma ? true : false })
    })
    .done(function(response) {
        App.notify(response.message || 'Email enviado', 'success');
    })
    .fail(function(xhr) {
        var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Error al enviar el email';
        App.notify(msg, 'error');
    })
    .always(function() {
        App.hideLoading();
    });
}

// ---------- Guardar como Plantilla ----------
function guardarComoPlantilla(nombre) {
    App.showLoading();
    return App.api(API_VER + '/albaranes.php/' + encodeURIComponent(codigoAlbaran) + '/plantilla', {
        method:      'POST',
        contentType: 'application/json',
        data:        JSON.stringify({ nombre: nombre })
    })
    .done(function(response) {
        App.notify(response.message || 'Plantilla guardada', 'success');
    })
    .fail(function(xhr) {
        var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Error al guardar plantilla';
        App.notify(msg, 'error');
    })
    .always(function() {
        App.hideLoading();
    });
}

window.abrirModalGuardarPlantilla = function() {
    const modalEl = document.getElementById('guardarPlantillaModal');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const input = document.getElementById('inputNombrePlantilla');
    if (input) input.value = '';
    modal.show();

    document.getElementById('btnConfirmarPlantilla').onclick = function() {
        const nombre = (document.getElementById('inputNombrePlantilla')?.value || '').trim();
        if (!nombre) {
            document.getElementById('inputNombrePlantilla')?.focus();
            return;
        }
        modal.hide();
        guardarComoPlantilla(nombre);
    };
};
