const BASE_FACT = (() => {
    const p = window.location.pathname;
    const idx = p.indexOf('/SistemaGestionFacturas/');
    return idx >= 0 ? p.substring(0, idx) + '/SistemaGestionFacturas' : '/SistemaGestionFacturas';
})();

const FACT_API_BASE = BASE_FACT + '/src/api';

let modalIVA       = null;
let modoEdicionIVA = false;

$(document).ready(function () {
    modalIVA = new bootstrap.Modal(document.getElementById('ivaModal'));

    cargarTiposIVA();

    $('#btnNuevoIVA').on('click', function () {
        abrirModalNuevoIVA();
    });

    $('#ivaForm').on('submit', function (e) {
        e.preventDefault();
        guardarIVA();
    });

    $('#ivaInputCodigo').on('input', function () {
        this.value = this.value.toUpperCase();
    });
});

// ─── Tipos de IVA ────────────────────────────────────────────────────────────

function cargarTiposIVA() {
    App.api(FACT_API_BASE + '/tiposiva.php')
        .done(function (response) {
            renderTiposIVA(response.data || []);
        })
        .fail(function () {
            $('#ivaTableBody').html(`
                <tr>
                    <td colspan="8" class="text-center py-4 text-danger">
                        <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
                        Error al cargar los tipos de IVA
                    </td>
                </tr>`);
        });
}

function renderTiposIVA(tipos) {
    if (!tipos.length) {
        $('#ivaTableBody').html(`
            <tr>
                <td colspan="8" class="text-center py-4 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    No hay tipos de IVA configurados
                </td>
            </tr>`);
        return;
    }

    const territorioLabel = {
        PENINSULAR:    'Peninsular',
        CANARIAS:      'Canarias',
        CEUTA_MELILLA: 'Ceuta / Melilla',
        ESPECIAL:      'Especial',
    };

    $('#ivaTableBody').html(tipos.map(function (t) {
        const activo     = t.Activo === 'S' || t.Activo === null;
        const territorio = t.Tipo_Territorio ? (territorioLabel[t.Tipo_Territorio] || t.Tipo_Territorio) : '-';

        return `
            <tr data-id="${iva_escapeHtml(t.Codigo)}">
                <td><strong>${iva_escapeHtml(t.Codigo)}</strong></td>
                <td>${iva_escapeHtml(t.Descripcion)}</td>
                <td class="text-end">${parseFloat(t.IVA || 0).toFixed(2)} %</td>
                <td class="text-end">${parseFloat(t.RE || 0).toFixed(2)} %</td>
                <td>${iva_escapeHtml(territorio)}</td>
                <td class="text-center">${iva_escapeHtml(t.Orden != null ? t.Orden : '0')}</td>
                <td class="text-center">
                    ${activo
                        ? '<i class="bi bi-check-circle-fill text-success"></i>'
                        : '<i class="bi bi-x-circle-fill text-secondary"></i>'}
                </td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-primary fact-btn"
                            title="Editar"
                            onclick='abrirModalEditarIVA(${JSON.stringify(t)})'>
                        <i class="bi bi-pencil"></i>
                    </button>
                </td>
            </tr>`;
    }).join(''));
}

function abrirModalNuevoIVA() {
    modoEdicionIVA = false;
    $('#ivaTitulo').text('Nuevo tipo IVA');
    $('#ivaForm')[0].reset();
    $('#ivaInputCodigo').prop('readonly', false);
    $('#ivaInputIVA').val('0');
    $('#ivaInputRE').val('0');
    $('#ivaInputOrden').val('0');
    $('#ivaCheckActivo').prop('checked', true);
    $('#ivaCheckActualizado').prop('checked', true);
    modalIVA.show();
}

function abrirModalEditarIVA(tipo) {
    modoEdicionIVA = true;
    $('#ivaTitulo').text('Editar tipo IVA');
    $('#ivaForm')[0].reset();

    $('#ivaInputCodigo').val(tipo.Codigo).prop('readonly', true);
    $('#ivaInputDescripcion').val(tipo.Descripcion);
    $('#ivaInputIVA').val(parseFloat(tipo.IVA || 0).toFixed(2));
    $('#ivaInputRE').val(parseFloat(tipo.RE || 0).toFixed(2));
    $('#ivaInputOrden').val(tipo.Orden != null ? tipo.Orden : 0);
    $('#ivaInputTerritorio').val(tipo.Tipo_Territorio || '');
    $('#ivaInputCodigoVerifactu').val(tipo.Codigo_Verifactu || '');
    $('#ivaInputCuentaIVASoportado').val(tipo.Cuenta_IVA_Soportado || '');
    $('#ivaInputCuentaIVARepercutido').val(tipo.Cuenta_IVA_Repercutido || '');
    $('#ivaInputCuentaRESoportado').val(tipo.Cuenta_RE_Soportado || '');
    $('#ivaInputCuentaRERepercutido').val(tipo.Cuenta_RE_Repercutido || '');
    $('#ivaCheckActivo').prop('checked', tipo.Activo === 'S' || tipo.Activo === null);
    $('#ivaCheckActualizado').prop('checked', tipo.Actualizado == null || tipo.Actualizado != 0);

    modalIVA.show();
}

function guardarIVA() {
    const payload = {
        Codigo:                  $('#ivaInputCodigo').val().trim().toUpperCase(),
        Descripcion:             $('#ivaInputDescripcion').val().trim(),
        IVA:                     parseFloat($('#ivaInputIVA').val()) || 0,
        RE:                      parseFloat($('#ivaInputRE').val()) || 0,
        Tipo_Territorio:         $('#ivaInputTerritorio').val() || null,
        Activo:                  $('#ivaCheckActivo').is(':checked') ? 'S' : 'N',
        Orden:                   parseInt($('#ivaInputOrden').val(), 10) || 0,
        Codigo_Verifactu:        $('#ivaInputCodigoVerifactu').val().trim() || null,
        Cuenta_IVA_Soportado:    $('#ivaInputCuentaIVASoportado').val().trim() || null,
        Cuenta_IVA_Repercutido:  $('#ivaInputCuentaIVARepercutido').val().trim() || null,
        Cuenta_RE_Soportado:     $('#ivaInputCuentaRESoportado').val().trim() || null,
        Cuenta_RE_Repercutido:   $('#ivaInputCuentaRERepercutido').val().trim() || null,
        Actualizado:             $('#ivaCheckActualizado').is(':checked') ? 1 : 0,
    };

    if (!payload.Codigo || !payload.Descripcion) {
        App.notify('Código y descripción son obligatorios', 'warning');
        return;
    }

    const method = modoEdicionIVA ? 'PUT' : 'POST';
    $('#ivaBtnGuardar').prop('disabled', true);

    App.api(FACT_API_BASE + '/tiposiva.php', {
        method,
        data:        JSON.stringify(payload),
        contentType: 'application/json',
    })
        .done(function (response) {
            App.notify(response.message || 'Guardado correctamente', 'success');
            modalIVA.hide();
            cargarTiposIVA();
        })
        .fail(function (xhr) {
            App.notify(xhr.responseJSON?.message || 'Error al guardar', 'danger');
        })
        .always(function () {
            $('#ivaBtnGuardar').prop('disabled', false);
        });
}

// ─── Utilidades ──────────────────────────────────────────────────────────────

function iva_escapeHtml(text) {
    return String(text ?? '')
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;').replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
