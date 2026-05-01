const BASE_FACT = (() => {
    const p = window.location.pathname;
    const idx = p.indexOf('/SistemaGestionFacturas/');
    return idx >= 0 ? p.substring(0, idx) + '/SistemaGestionFacturas' : '/SistemaGestionFacturas';
})();

const FACT_API_BASE = BASE_FACT + '/src/api';

let modalCanal  = null;
let modoEdicion = false;

$(document).ready(function () {
    modalCanal = new bootstrap.Modal(document.getElementById('canalModal'));

    cargarCanales();

    $('#btnNuevoCanal').on('click', function () {
        abrirModalNuevo();
    });

    $('#canalForm').on('submit', function (e) {
        e.preventDefault();
        guardarCanal();
    });

    $('#inputCodigo').on('input', function () {
        this.value = this.value.toUpperCase();
    });

    $('#inputTipoPrioridad').on('input', function () {
        this.value = this.value.toUpperCase();
    });

    $('#inputDepartamento').on('input', function () {
        this.value = this.value.toUpperCase();
    });
});

// ─── Canales ─────────────────────────────────────────────────────────────────

function cargarCanales() {
    App.api(FACT_API_BASE + '/canales.php')
        .done(function (response) {
            renderCanales(response.data || []);
        })
        .fail(function () {
            $('#canalesTableBody').html(`
                <tr>
                    <td colspan="8" class="text-center py-4 text-danger">
                        <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
                        Error al cargar los canales
                    </td>
                </tr>`);
        });
}

function renderCanales(canales) {
    if (!canales.length) {
        $('#canalesTableBody').html(`
            <tr>
                <td colspan="8" class="text-center py-4 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    No hay canales configurados
                </td>
            </tr>`);
        return;
    }

    $('#canalesTableBody').html(canales.map(function (c) {
        const esTicket  = c.Ticket === 'S';
        const esDefecto = c.Facturacion_Defecto === 'S' || c.Facturacion_Defecto === 1 || c.Facturacion_Defecto === '1';
        const cliente   = can_escapeHtml(c.Id_Cliente_Facturacion || '-');

        return `
            <tr data-id="${can_escapeHtml(c.Codigo)}">
                <td><strong>${can_escapeHtml(c.Codigo)}</strong></td>
                <td>${can_escapeHtml(c.Descripcion)}</td>
                <td class="text-center">
                    ${esTicket
                        ? '<span class="badge bg-warning text-dark">Ticket</span>'
                        : '<span class="badge bg-info text-dark">Facturación</span>'}
                </td>
                <td class="text-center">
                    ${esDefecto ? '<i class="bi bi-check-circle-fill text-success"></i>' : '-'}
                </td>
                <td>${cliente}</td>
                <td class="text-center">${can_escapeHtml(c.Prioridad != null ? c.Prioridad : '-')}</td>
                <td>${can_escapeHtml(c.Departamento || '-')}</td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-primary fact-btn"
                            title="Editar"
                            onclick='abrirModalEditar(${JSON.stringify(c)})'>
                        <i class="bi bi-pencil"></i>
                    </button>
                </td>
            </tr>`;
    }).join(''));
}

function abrirModalNuevo() {
    modoEdicion = false;
    $('#modalTitulo').text('Nuevo canal');
    $('#canalForm')[0].reset();
    $('#inputCodigo').prop('readonly', false);
    modalCanal.show();
}

function abrirModalEditar(canal) {
    modoEdicion = true;
    $('#modalTitulo').text('Editar canal');
    $('#canalForm')[0].reset();

    $('#inputCodigo').val(canal.Codigo).prop('readonly', true);
    $('#inputDescripcion').val(canal.Descripcion);
    $('#inputColor').val(canal.Color != null ? canal.Color : '');
    $('#inputClienteFacturacion').val(canal.Id_Cliente_Facturacion || '');
    $('#inputDireccionFacturacion').val(canal.Direccion_Facturacion || '');
    $('#inputPorcentajeAntieconomico').val(canal.Porcentaje_Antieconomico != null ? canal.Porcentaje_Antieconomico : '');
    $('#inputPrioridad').val(canal.Prioridad != null ? canal.Prioridad : '');
    $('#inputTipoPrioridad').val(canal.Tipo_Prioridad || '');
    $('#inputDepartamento').val(canal.Departamento || '');

    $('#checkTicket').prop('checked', canal.Ticket === 'S');
    const defecto = canal.Facturacion_Defecto === 'S' || canal.Facturacion_Defecto === 1 || canal.Facturacion_Defecto === '1';
    $('#checkDefecto').prop('checked', defecto);
    $('#checkCobrarFranquicia').prop('checked', canal.Cobrar_Franquicia === true || canal.Cobrar_Franquicia === 1 || canal.Cobrar_Franquicia === '1');
    $('#checkComputable').prop('checked', canal.Computable === true || canal.Computable === 1 || canal.Computable === '1');

    modalCanal.show();
}

function guardarCanal() {
    const payload = {
        Codigo:                   $('#inputCodigo').val().trim().toUpperCase(),
        Descripcion:              $('#inputDescripcion').val().trim(),
        Color:                    $('#inputColor').val() !== '' ? parseInt($('#inputColor').val(), 10) : null,
        Ticket:                   $('#checkTicket').is(':checked') ? 'S' : 'N',
        Facturacion_Defecto:      $('#checkDefecto').is(':checked') ? 'S' : 'N',
        Id_Cliente_Facturacion:   $('#inputClienteFacturacion').val().trim() || null,
        Direccion_Facturacion:    $('#inputDireccionFacturacion').val().trim() || null,
        Porcentaje_Antieconomico: $('#inputPorcentajeAntieconomico').val() !== '' ? parseInt($('#inputPorcentajeAntieconomico').val(), 10) : null,
        Prioridad:                $('#inputPrioridad').val() !== '' ? parseInt($('#inputPrioridad').val(), 10) : null,
        Tipo_Prioridad:           $('#inputTipoPrioridad').val().trim().toUpperCase() || null,
        Departamento:             $('#inputDepartamento').val().trim().toUpperCase() || null,
        Cobrar_Franquicia:        $('#checkCobrarFranquicia').is(':checked') ? 1 : 0,
        Computable:               $('#checkComputable').is(':checked') ? 1 : 0,
    };

    if (!payload.Codigo || !payload.Descripcion) {
        App.notify('Código y descripción son obligatorios', 'warning');
        return;
    }

    const method = modoEdicion ? 'PUT' : 'POST';
    $('#btnGuardar').prop('disabled', true);

    App.api(FACT_API_BASE + '/canales.php', {
        method,
        data:        JSON.stringify(payload),
        contentType: 'application/json',
    })
        .done(function (response) {
            App.notify(response.message || 'Guardado correctamente', 'success');
            modalCanal.hide();
            cargarCanales();
        })
        .fail(function (xhr) {
            App.notify(xhr.responseJSON?.message || 'Error al guardar', 'danger');
        })
        .always(function () {
            $('#btnGuardar').prop('disabled', false);
        });
}

// ─── Utilidades ──────────────────────────────────────────────────────────────

function can_escapeHtml(text) {
    return String(text ?? '')
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;').replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
