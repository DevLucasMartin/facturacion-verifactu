const BASE_FACT = (() => {
    const p = window.location.pathname;
    const idx = p.indexOf('/SistemaGestionFacturas/');
    return idx >= 0 ? p.substring(0, idx) + '/SistemaGestionFacturas' : '/SistemaGestionFacturas';
})();

const FACT_API_BASE = BASE_FACT + '/src/api';

const NIF_LETRAS = 'TRWAGMYFPDXBNJZSQVHLCKE';

let cli_pagActual  = 1;
let cli_busqueda   = '';
let cli_busqTimer  = null;
let cli_modal      = null;
let cli_modoEdicion = false;
let cli_codigoEdicion = null;

$(document).ready(function () {
    cli_modal = new bootstrap.Modal(document.getElementById('nuevoClienteModal'));

    cargarClientes();
    cli_cargarFormaPago();
    cli_cargarTarifas();


    $('#clientesBuscar').on('input', function () {
        clearTimeout(cli_busqTimer);
        cli_busqTimer = setTimeout(function () {
            cli_busqueda = $('#clientesBuscar').val().trim();
            cli_pagActual = 1;
            cargarClientes();
        }, 350);
    });

    $('#btnNuevoCliente').on('click', abrirNuevoCliente);

    $('#nuevoClienteForm').on('submit', function (e) {
        e.preventDefault();
        guardarNuevoCliente();
    });
});

function abrirNuevoCliente() {
    cli_modoEdicion    = false;
    cli_codigoEdicion  = null;
    $('#nuevoClienteModalTitle').text('Nuevo Cliente');
    $('#nuevoClienteMsg').addClass('d-none').removeClass('alert-success alert-danger alert-warning').addClass('alert-info').text('');
    $('#nuevoClienteForm')[0].reset();
    $('#ncRE').val('0');
    $('#ncCodigo').prop('readonly', false).removeClass('is-invalid is-valid');
    $('#ncCodigoError').text('');
    $('#ncNif').removeClass('is-invalid is-valid');
    $('#ncNifError').text('');
    cli_modal.show();
}

function abrirModificarCliente(codigo) {
    cli_modoEdicion   = true;
    cli_codigoEdicion = codigo;
    $('#nuevoClienteModalTitle').text('Modificar Cliente');
    $('#nuevoClienteMsg').addClass('d-none').removeClass('alert-success alert-danger alert-warning').addClass('alert-info').text('');
    $('#nuevoClienteForm')[0].reset();
    $('#ncCodigo').prop('readonly', true).removeClass('is-invalid is-valid').val(codigo);
    $('#ncNif').removeClass('is-invalid is-valid');
    $('#ncNifError').text('');

    App.api(`${FACT_API_BASE}/clientes.php/${encodeURIComponent(codigo)}`)
        .done(function (response) {
            const c = response.data;
            $('#ncApellidos').val(c.Apellidos        || '');
            $('#ncArchivar').val(c.Archivar_Como      || '');
            $('#ncNif').val(c.NIF                     || '');
            $('#ncFormaPago').val(c.Id_Forma_Pago      || '');
            $('#ncTarifa').val(c.Tarifa                || 1);
            $('#ncRE').val(c.RE_Porcentaje             || 0);
            $('#ncEmail').val(c.Email_Facturacion       || '');
            cli_modal.show();
        })
        .fail(function () {
            App.notify('Error al cargar los datos del cliente', 'danger');
        });
}

function cli_cargarFormaPago() {
    App.api(FACT_API_BASE + '/formas_pago.php')
        .done(function (response) {
            const opts = (response.data || []).map(function (c) {
                return `<option value="${cli_esc(c.Id_Forma_Pago)}">${cli_esc(c.Id_Forma_Pago + (c.Descripcion ? ' – ' + c.Descripcion : ''))}</option>`;
            }).join('');
            $('#ncFormaPago').html('<option value="">— Sin especificar —</option>' + opts);
        });
}


function cli_cargarTarifas() {
    App.api(FACT_API_BASE + '/tarifas.php')
        .done(function (response) {
            const opts = (response.data || []).map(function (c) {
                return `<option value="${cli_esc(c.Tarifa)}">${cli_esc(c.Tarifa)}</option>`;
            }).join('');
            $('#ncTarifa').html(opts || '<option value="1">1</option>');
        });
}

function cli_validarNIF(input) {
    const val     = input.value.toUpperCase().trim();
    input.value   = val;
    const errorEl = document.getElementById('ncNifError');

    if (val === '') {
        input.classList.remove('is-invalid', 'is-valid');
        errorEl.textContent = '';
        return true;
    }

    const matchDNI = val.match(/^(\d{8})([A-Z])$/);
    const matchNIE = val.match(/^([XYZ])(\d{7})([A-Z])$/);
    const matchCIF = val.match(/^([ABCDEFGHJNPQRSUVW])(\d{7})([0-9A-Z])$/);

    if (matchDNI) {
        const letraEsperada = NIF_LETRAS[parseInt(matchDNI[1], 10) % 23];
        if (matchDNI[2] !== letraEsperada) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            errorEl.textContent = `La letra del NIF no es correcta (debería ser ${letraEsperada}).`;
            return false;
        }
    } else if (matchNIE) {
        const prefijos = { X: 0, Y: 1, Z: 2 };
        const numero   = parseInt(String(prefijos[matchNIE[1]]) + matchNIE[2], 10);
        const letraEsperada = NIF_LETRAS[numero % 23];
        if (matchNIE[3] !== letraEsperada) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            errorEl.textContent = `La letra del NIE no es correcta (debería ser ${letraEsperada}).`;
            return false;
        }
    } else if (matchCIF) {
        const digitos  = matchCIF[2];
        const control  = matchCIF[3];
        const letra    = matchCIF[1];

        // Suma dígitos en posiciones impares (1,3,5,7) multiplicados por 2
        let suma = 0;
        for (let i = 0; i < 7; i++) {
            const d = parseInt(digitos[i], 10);
            if ((i + 1) % 2 === 0) {
                suma += d;
            } else {
                const doble = d * 2;
                suma += doble < 10 ? doble : doble - 9;
            }
        }

        const digitoControl = (10 - (suma % 10)) % 10;
        const letrasControl = 'JABCDEFGHI';
        const letraControl  = letrasControl[digitoControl];

        // Algunos tipos de CIF solo admiten letra, otros solo dígito, otros ambos
        const soloLetra  = /^[KLMNPQRS]$/.test(letra);
        const soloDigito = /^[ABEH]$/.test(letra);

        const controlEsperado = soloLetra ? letraControl : (soloDigito ? String(digitoControl) : null);

        const esValido = soloLetra
            ? control === letraControl
            : soloDigito
                ? control === String(digitoControl)
                : (control === letraControl || control === String(digitoControl));

        if (!esValido) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            const esperado = controlEsperado ?? `${digitoControl} o ${letraControl}`;
            errorEl.textContent = `El dígito de control del CIF no es correcto (debería ser ${esperado}).`;
            return false;
        }
    } else {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        errorEl.textContent = 'Formato incorrecto. DNI: 12345678Z · NIE: X1234567L · CIF: A12345678.';
        return false;
    }

    input.classList.remove('is-invalid');
    input.classList.add('is-valid');
    errorEl.textContent = '';
    return true;
}

function cli_validarCodigo(input) {
    const val     = input.value.toUpperCase();
    input.value   = val;
    const valid   = /^[A-Z0-9]{0,12}$/.test(val);
    const errorEl = document.getElementById('ncCodigoError');
    if (!valid) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        errorEl.textContent = 'Solo letras y números, máx. 12 caracteres.';
    } else if (val.length > 0) {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
        errorEl.textContent = '';
    } else {
        input.classList.remove('is-invalid', 'is-valid');
        errorEl.textContent = '';
    }
}

function guardarNuevoCliente() {
    const codigoVal   = ($('#ncCodigo').val()   || '').trim().toUpperCase();
    const archivarVal = ($('#ncArchivar').val() || '').trim();

    if (!cli_modoEdicion) {
        if (!codigoVal || !/^[A-Z0-9]{1,12}$/.test(codigoVal)) {
            $('#ncCodigo').addClass('is-invalid');
            document.getElementById('ncCodigoError').textContent = 'El código es obligatorio (solo letras y números, máx. 12).';
            return;
        }
    }
    if (!archivarVal) {
        $('#nuevoClienteMsg').removeClass('d-none alert-info alert-success alert-warning').addClass('alert-danger').text('El campo "Archivar como" es obligatorio.');
        return;
    }
    if (!cli_validarNIF(document.getElementById('ncNif'))) return;

    const payload = {
        Apellidos:         ($('#ncApellidos').val() || '').trim(),
        Archivar_Como:     archivarVal,
        NIF:               ($('#ncNif').val()       || '').trim(),
        Id_Forma_Pago:     ($('#ncFormaPago').val() || '').trim(),
        Email_Facturacion: ($('#ncEmail').val()     || '').trim() || null,
        Tarifa:            parseInt($('#ncTarifa').val(), 10) || 1,
        Aplica_RE:         parseFloat($('#ncRE').val()) > 0 ? 1 : 0,
        RE_Porcentaje:     parseFloat($('#ncRE').val()) || 0,
    };
    if (!cli_modoEdicion) {
        payload.Codigo  = codigoVal;
        payload.Archivar = archivarVal;
    }

    const url    = cli_modoEdicion ? `${FACT_API_BASE}/clientes.php/${encodeURIComponent(cli_codigoEdicion)}` : `${FACT_API_BASE}/clientes.php`;
    const method = cli_modoEdicion ? 'PUT' : 'POST';
    const msgOk  = cli_modoEdicion ? 'Cliente actualizado correctamente.' : `Cliente ${cli_esc(codigoVal)} creado correctamente.`;

    $('#ncBtnGuardar').prop('disabled', true);

    App.api(url, { method, data: JSON.stringify(payload), contentType: 'application/json' })
        .done(function () {
            $('#nuevoClienteMsg').removeClass('d-none alert-info alert-danger alert-warning').addClass('alert-success').text(msgOk);
            setTimeout(function () {
                cli_modal.hide();
                cargarClientes();
            }, 800);
        })
        .fail(function (xhr) {
            const msg = xhr.responseJSON?.message || 'Error al guardar el cliente.';
            const esDuplicado = !cli_modoEdicion && (msg.includes('ya existente') || msg.includes('already'));
            $('#nuevoClienteMsg').removeClass('d-none alert-info alert-success').addClass(esDuplicado ? 'alert-warning' : 'alert-danger').text(msg);
            if (esDuplicado) {
                $('#ncCodigo').addClass('is-invalid');
                document.getElementById('ncCodigoError').textContent = 'Este código ya existe.';
            }
        })
        .always(function () {
            $('#ncBtnGuardar').prop('disabled', false);
        });
}

function cargarClientes() {
    const url = cli_busqueda.length >= 2
        ? `${FACT_API_BASE}/clientes.php?action=search&q=${encodeURIComponent(cli_busqueda)}&limit=50`
        : `${FACT_API_BASE}/clientes.php?page=${cli_pagActual}&per_page=25`;

    App.api(url)
        .done(function (response) {
            const items = response.data || [];
            const total = response.total ?? items.length;
            renderClientes(items);
            $('#clientesTotalLabel').text(total + ' clientes');
            if (!cli_busqueda) renderPaginacion(response);
        })
        .fail(function () {
            $('#clientesTableBody').html(`
                <tr>
                    <td colspan="7" class="text-center py-4 text-danger">
                        <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
                        Error al cargar los clientes
                    </td>
                </tr>`);
        });
}

function renderClientes(clientes) {
    if (!clientes.length) {
        $('#clientesTableBody').html(`
            <tr>
                <td colspan="7" class="text-center py-4 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    No se encontraron clientes
                </td>
            </tr>`);
        $('#clientesPaginacion').hide();
        return;
    }

    $('#clientesTableBody').html(clientes.map(function (c) {
        const activo = c.Activo === 'S' || c.Activo == null;
        const tieneRE = parseFloat(c.RE_Porcentaje || 0) > 0 || c.Aplica_RE === 'S';

        return `
            <tr class="fact-tr-hover">
                <td><strong>${cli_esc(c.Codigo)}</strong></td>
                <td>${cli_esc(c.Archivar_Como || '')}</td>
                <td>${cli_esc(c.NIF || '-')}</td>
                <td>${cli_esc(c.Id_Forma_Pago || '-')}</td>
                <td class="text-center">
                    ${tieneRE
                        ? `<span class="fact-badge" style="background:#fff3e0;color:#e65100;">${cli_esc(c.RE_Porcentaje)}%</span>`
                        : '<span class="text-muted">—</span>'}
                </td>
                <td class="text-center">
                    ${activo
                        ? '<i class="bi bi-check-circle-fill text-success"></i>'
                        : '<i class="bi bi-x-circle-fill text-danger"></i>'}
                </td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-primary fact-btn"
                            title="Modificar"
                            onclick="abrirModificarCliente('${cli_esc(c.Codigo)}')">
                        <i class="bi bi-pencil"></i>
                    </button>
                </td>
            </tr>`;
    }).join(''));
}

function renderPaginacion(response) {
    const total      = response.total       ?? 0;
    const pagActual  = response.page        ?? 1;
    const totalPags  = response.total_pages ?? 1;
    const perPage    = response.per_page    ?? 25;
    const desde      = (pagActual - 1) * perPage + 1;
    const hasta      = Math.min(pagActual * perPage, total);

    if (totalPags <= 1) {
        $('#clientesPaginacion').hide();
        return;
    }

    $('#clientesPaginacion').show();
    $('#clientesPagInfo').text(`Mostrando ${desde}–${hasta} de ${total}`);

    let links = '';
    links += `<li class="page-item ${pagActual <= 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" data-pag="${pagActual - 1}">&laquo;</a></li>`;

    for (let p = Math.max(1, pagActual - 2); p <= Math.min(totalPags, pagActual + 2); p++) {
        links += `<li class="page-item ${p === pagActual ? 'active' : ''}">
            <a class="page-link" href="#" data-pag="${p}">${p}</a></li>`;
    }

    links += `<li class="page-item ${pagActual >= totalPags ? 'disabled' : ''}">
        <a class="page-link" href="#" data-pag="${pagActual + 1}">&raquo;</a></li>`;

    $('#clientesPagLinks').html(links);

    $('#clientesPagLinks').off('click').on('click', 'a.page-link', function (e) {
        e.preventDefault();
        const p = parseInt($(this).data('pag'));
        if (p >= 1 && p <= totalPags && p !== cli_pagActual) {
            cli_pagActual = p;
            cargarClientes();
        }
    });
}

function cli_esc(text) {
    return String(text ?? '')
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;').replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
