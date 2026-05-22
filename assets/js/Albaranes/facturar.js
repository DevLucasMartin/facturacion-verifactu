const BASE = (() => {
    const p = window.location.pathname;
    const idx = p.indexOf('/SistemaGestionFacturas/');
    return idx >= 0 ? p.substring(0, idx) + '/SistemaGestionFacturas' : '/SistemaGestionFacturas';
})();

const FACT_API_BASE = BASE + '/src/api';
const { apiSend: factApiSend } = FormUtils.makeApiClient(FACT_API_BASE);

// Códigos de albarán pasados desde PHP (via data-* attribute)
const CODIGOS_ALBARANES = JSON.parse(document.getElementById('app-data').dataset.codigos || '[]');

// Estado: { [codigo]: { albaran: {...}, lineasSeleccionadas: Set<int> } }
const estadoAlbaranes = {};

// Cliente seleccionado
let clienteActual = null;

const CALIFICACION_EXPORTACION = ['S2', 'E2', 'E5', 'N2'];
let nifExportacion = '';
let nifExportacionVacio = false;
let paisExportacion = '';

function esEmpresaOProfesional() {
    return document.querySelector('input[name="destinatario_tipo"]:checked')?.value === 'empresa';
}

function actualizarVisibilidadDestinatario() {
    const tipo  = document.querySelector('input[name="Tipo_Documento"]:checked')?.value;
    const panel = document.getElementById('destinatarioSimplificada');
    if (panel) panel.style.display = (tipo === 'SIMPLIFICADA') ? '' : 'none';
    if (tipo !== 'SIMPLIFICADA') {
        const av = document.getElementById('avisoSimplificada');
        if (av) av.style.display = 'none';
    }
}

$(document).ready(function() {

    // Cargar formas de pago
    cargarFormasPago();

    // Preparar modal de cliente y dropdown de albaranes
    initClienteModal();
    initClienteDisplayBox();
    inicializarPaisExportacionSelect2();

    // Cargar cada albarán
    if (CODIGOS_ALBARANES.length === 0) {
        $('#albaranesContainer').html('<div class="alert alert-warning">No se indicaron albaranes.</div>');
        return;
    }

    const promesas = CODIGOS_ALBARANES.map(codigo => cargarAlbaran(codigo));

    Promise.all(promesas).then(() => {
        renderAlbaranes();
        actualizarResumen();
        $('#btnSeleccionarTodas').prop('disabled', false);
        // Pre-cargar el cliente del primer albarán
        const primerCodigo = Object.keys(estadoAlbaranes)[0];
        if (primerCodigo) {
            const idCliente = estadoAlbaranes[primerCodigo].albaran.Id_Cliente;
            if (idCliente) preCargarCliente(idCliente);
        }
    }).catch(err => {
        console.error('Error cargando albaranes:', err);
        $('#albaranesContainer').html('<div class="alert alert-danger">Error al cargar los albaranes.</div>');
    });

    // Submit
    $('#facturarForm').on('submit', function(e) {
        e.preventDefault();
        facturar();
    });

    // Actualizar resumen al cambiar tipo documento o tipo destinatario
    $('input[name="Tipo_Documento"]').on('change', actualizarResumen);
    $(document).on('change', 'input[name="destinatario_tipo"]', actualizarResumen);
});

function cargarFormasPago() {
    $.getJSON(`${FACT_API_BASE}/formas_pago.php`)
        .done(function(resp) {
            const lista = resp?.data ?? [];
            const sel   = $('#selectFormaPago');
            sel.empty();
            sel.append(new Option('Elige la forma de pago...', ''));
            lista.forEach(function(fp) {
                const label = fp.Descripcion ? `${fp.Id_Forma_Pago} – ${fp.Descripcion}` : fp.Id_Forma_Pago;
                sel.append(new Option(label, fp.Id_Forma_Pago));
            });
            // Aplicar valor pendiente si el cliente ya fue cargado antes que las opciones
            const pending = sel[0]?.dataset?.pendingValue;
            if (pending) {
                sel.val(pending);
                delete sel[0].dataset.pendingValue;
            }
        })
        .fail(function() {
            console.warn('No se pudieron cargar las formas de pago.');
        });
}

function cargarAlbaran(codigo) {
    return new Promise(function(resolve, reject) {
        App.api(`${FACT_API_BASE}/albaranes.php/${encodeURIComponent(codigo)}`)
            .done(function(resp) {
                const a = resp.data;
                const seleccionadas = new Set();
                estadoAlbaranes[codigo] = { albaran: a, lineasSeleccionadas: seleccionadas };
                resolve();
            })
            .fail(function(xhr) {
                reject(new Error(`Error cargando albarán ${codigo}: ` + (xhr.responseJSON?.message ?? xhr.status)));
            });
    });
}

// ── Gestión del cliente ──────────────────────────────────────────────────────

function mostrarCliente(c) {
    clienteActual = c;
    document.getElementById('inputIdCliente').value         = c.Codigo || '';
    document.getElementById('clienteNombre').textContent    = c.Archivar_Como || c.Nombre || c.Codigo || '';
    document.getElementById('clienteNIF').textContent       = c.NIF || '';
    document.getElementById('clienteVacio').style.display   = 'none';
    document.getElementById('clienteInfo').style.display    = '';

    // Pre-seleccionar la forma de pago del cliente
    const fp = (c.Id_Forma_Pago || '').toUpperCase().trim();
    if (fp) {
        const sel = document.getElementById('selectFormaPago');
        if (sel) {
            sel.value = fp;
            // Si aún no están cargadas las opciones, reintentar tras cargarFormasPago
            if (!sel.value || sel.value.trim() === '') {
                sel.dataset.pendingValue = fp;
            }
        }
    }

    const inputNif = document.getElementById('inputNifExportacion');
    if (inputNif) { inputNif.value = ''; nifExportacion = ''; }
    actualizarVistaNifExportacion();
}

function preCargarCliente(idCliente) {
    $.getJSON(`${FACT_API_BASE}/clientes.php/${encodeURIComponent(idCliente)}`)
        .done(function(resp) {
            if (resp.data) mostrarCliente(resp.data);
        })
        .fail(function() {
            mostrarCliente({ Codigo: idCliente, Nombre: idCliente, NIF: '' });
        });
}

window.seleccionarCliente = function() {
    const modalEl = document.getElementById('clienteModal');
    if (!modalEl) return;
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
    const input = document.getElementById('buscarCliente');
    if (input) { input.value = ''; input.focus(); }
    buscarClientes('');
};

function initClienteDisplayBox() {
    const box      = document.getElementById('clienteDisplayBox');
    const dropdown = document.getElementById('clienteAlbaranesDropdown');
    if (!box || !dropdown) return;

    box.addEventListener('click', function(e) {
        e.stopPropagation();
        if (dropdown.style.display === 'none') {
            abrirDropdownAlbaranes();
        } else {
            dropdown.style.display = 'none';
        }
    });

    dropdown.addEventListener('click', function(e) { e.stopPropagation(); });
    document.addEventListener('click', function() {
        if (dropdown) dropdown.style.display = 'none';
    });
}

function abrirDropdownAlbaranes() {
    const dropdown = document.getElementById('clienteAlbaranesDropdown');
    if (!dropdown) return;

    const vistos   = new Set();
    const clientes = [];
    Object.values(estadoAlbaranes).forEach(function(estado) {
        const id = estado.albaran.Id_Cliente;
        if (!id || vistos.has(id)) return;
        vistos.add(id);
        const c = estado.albaran.cliente;
        clientes.push({
            Codigo: id,
            Nombre: c?.Nombre || c?.Archivar_Como || id,
            NIF:    c?.NIF || '',
        });
    });

    if (!clientes.length) {
        dropdown.innerHTML = '<div class="px-2 py-1 text-muted">Sin clientes</div>';
        dropdown.style.display = '';
        return;
    }

    dropdown.innerHTML = clientes.map(function(c) {
        const activo = clienteActual?.Codigo === c.Codigo ? ' bg-light' : '';
        return `<div class="px-2 py-1${activo}"
                     style="cursor:pointer;"
                     onmouseenter="this.style.background='#f8f9fa'"
                     onmouseleave="this.style.background='${activo ? '#f8f9fa' : ''}'"
                     onclick="seleccionarClienteAlbaran('${escAttr(c.Codigo)}')">
                    <span class="fw-semibold d-block">${escHtml(c.Nombre)}</span>
                    ${c.NIF ? `<span class="text-muted" style="font-size:11px;">${escHtml(c.NIF)}</span>` : ''}
                </div>`;
    }).join('<div class="border-top my-1"></div>');

    dropdown.style.display = '';
}

window.seleccionarClienteAlbaran = function(idCliente) {
    const dropdown = document.getElementById('clienteAlbaranesDropdown');
    if (dropdown) dropdown.style.display = 'none';

    for (const estado of Object.values(estadoAlbaranes)) {
        if (estado.albaran.Id_Cliente === idCliente && estado.albaran.cliente) {
            mostrarCliente(estado.albaran.cliente);
            return;
        }
    }
    preCargarCliente(idCliente);
};

function actualizarVistaNifExportacion() {
    let hayExportacion = false;
    Object.values(estadoAlbaranes).forEach(function(estado) {
        (estado.albaran.lineas || []).forEach(function(l) {
            if (estado.lineasSeleccionadas.has(parseInt(l.Linea))) {
                const calif = (l.Calificacion || 'S1').toUpperCase();
                if (CALIFICACION_EXPORTACION.includes(calif)) hayExportacion = true;
            }
        });
    });
    const section = document.getElementById('nifExportacionSection');
    if (!section) return;
    section.classList.toggle('d-none', !hayExportacion);
    if (hayExportacion) {
        const inputEl = document.getElementById('inputNifExportacion');
        if (inputEl && inputEl.value === '') {
            const defaultVal = clienteActual?.NIF || '';
            inputEl.value    = defaultVal.toUpperCase();
            nifExportacion   = inputEl.value;
        }
        const hayE5 = Object.values(estadoAlbaranes).some(function(estado) {
            return (estado.albaran.lineas || []).some(function(l) {
                return estado.lineasSeleccionadas.has(parseInt(l.Linea))
                    && (l.Calificacion || '').toUpperCase() === 'E5';
            });
        });
        const chkVacio = document.getElementById('chkNifVacio');
        const chkVacioWrap = chkVacio?.closest('.form-check');
        if (chkVacioWrap) chkVacioWrap.classList.toggle('d-none', hayE5);
        if (hayE5 && chkVacio?.checked) {
            chkVacio.checked = false;
            nifExportacionVacio = false;
            const inputNif = document.getElementById('inputNifExportacion');
            if (inputNif) inputNif.disabled = false;
        }
    }
}

window.cambiarNifExportacion = function(valor) {
    nifExportacion = valor.trim().toUpperCase();
    validarFormatoVatExportacion();
};

window.cambiarNifExportacionVacio = function(checked) {
    nifExportacionVacio = checked;
    const inputEl = document.getElementById('inputNifExportacion');
    if (inputEl) inputEl.disabled = checked;
    validarFormatoVatExportacion();
};

window.cambiarPaisExportacion = function(valor) {
    paisExportacion = (valor || '').toUpperCase().trim();
    validarFormatoVatExportacion();
};

function inicializarPaisExportacionSelect2() {
    if (!window.jQuery || !$.fn || !$.fn.select2) return;
    const $sel = $('#inputPaisExportacion');
    if (!$sel.length || $sel.data('select2')) return;
    $sel.select2({
        placeholder: 'Selecciona país',
        allowClear: true,
        width: '260px',
        language: {
            noResults: () => 'Sin resultados',
            searching: () => 'Buscando…',
            inputTooShort: () => 'Escribe para buscar'
        }
    });
    $sel.on('change.paisExp', function() { window.cambiarPaisExportacion(this.value); });
    $sel.on('select2:clear.paisExp', function() {
        $(this).on('select2:opening.cancelOpen', function(e) {
            e.preventDefault();
            $(this).off('select2:opening.cancelOpen');
        });
    });
}

function validarFormatoVatExportacion() {
    const inputNif = document.getElementById('inputNifExportacion');
    const errorBox = document.getElementById('vatFormatoError');
    const errorTxt = document.getElementById('vatFormatoErrorTexto');
    if (!inputNif || !errorBox || !errorTxt) return;
    const hayE5 = Object.values(estadoAlbaranes).some(function(estado) {
        return (estado.albaran.lineas || []).some(function(l) {
            return estado.lineasSeleccionadas.has(parseInt(l.Linea))
                && (l.Calificacion || '').toUpperCase() === 'E5';
        });
    });
    if (!hayE5 || !nifExportacion || !/^[A-Z]{2}$/.test(paisExportacion)) {
        inputNif.classList.remove('is-invalid');
        errorBox.classList.add('d-none');
        return;
    }
    const err = window.FormUtils?.validarVatUE?.(nifExportacion, paisExportacion);
    if (err) {
        inputNif.classList.add('is-invalid');
        errorTxt.textContent = err;
        errorBox.classList.remove('d-none');
    } else {
        inputNif.classList.remove('is-invalid');
        errorBox.classList.add('d-none');
    }
}

function initClienteModal() {
    let _timer = null;
    document.getElementById('buscarCliente')?.addEventListener('input', function() {
        clearTimeout(_timer);
        _timer = setTimeout(() => buscarClientes(this.value.trim()), 250);
    });
}

function buscarClientes(query) {
    const tbody = document.getElementById('clientesBody');
    if (!tbody) return;
    tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted">Cargando...</td></tr>`;

    const url = query
        ? `${FACT_API_BASE}/clientes.php?action=search&q=${encodeURIComponent(query)}`
        : `${FACT_API_BASE}/clientes.php?per_page=20`;

    $.getJSON(url)
        .done(function(resp) {
            const lista = resp.data ?? [];
            if (!lista.length) {
                tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted">Sin resultados</td></tr>`;
                return;
            }
            tbody.innerHTML = lista.map(c => `
                <tr style="cursor:pointer" onclick="seleccionarClienteReal('${escAttr(String(c.Codigo))}')">
                    <td>${escHtml(c.Codigo)}</td>
                    <td>${escHtml(c.NIF || '-')}</td>
                    <td>${escHtml(c.Archivar_Como || '-')}</td>
                    <td>${escHtml(c.Id_Forma_Pago || '-')}</td>
                </tr>`).join('');
        })
        .fail(function() {
            tbody.innerHTML = `<tr><td colspan="4" class="text-danger text-center">Error al cargar clientes</td></tr>`;
        });
}

window.seleccionarClienteReal = function(idCliente) {
    $.getJSON(`${FACT_API_BASE}/clientes.php/${encodeURIComponent(idCliente)}`)
        .done(function(resp) {
            if (resp.data) {
                mostrarCliente(resp.data);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('clienteModal')).hide();
            }
        });
};

// ── Renderizado de albaranes ───────────────────────────────────────────────

function renderAlbaranes() {
    const container = $('#albaranesContainer');
    container.empty();

    Object.entries(estadoAlbaranes).forEach(function([codigo, estado]) {
        const a       = estado.albaran;
        const lineas  = a.lineas || [];
        const cerrado   = String(a.Cerrado   || '').toUpperCase() === 'S';
        const facturado = String(a.Facturado || '').toUpperCase() === 'S';

        let html = `
        <div class="card mb-3 fact-card" id="card-${escHtml(codigo)}">
            <div class="card-header d-flex justify-content-between align-items-center fact-card-header">
                <span>
                    <i class="bi bi-file-earmark me-2"></i>
                    <strong>${escHtml(codigo)}</strong>
                    &nbsp;·&nbsp; ${escHtml(a.cliente?.Archivar_Como || a.Id_Cliente || '')}
                    &nbsp;·&nbsp; Canal: ${escHtml(a.Id_Canal || '-')}
                </span>
                <div>
                    ${cerrado   ? '<span class="badge bg-success me-1">Cerrado</span>'  : '<span class="badge bg-warning me-1">Abierto</span>'}
                    ${facturado ? '<span class="badge bg-info">Facturado</span>'         : ''}
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 fact-table">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width:40px;">
                                    <input type="checkbox" class="form-check-input check-all"
                                        data-codigo="${escAttr(codigo)}"
                                        onchange="toggleTodas('${escHtml(codigo)}')">
                                </th>
                                <th style="width:40px;">#</th>
                                <th>Artículo</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-end">Precio</th>
                                <th class="text-end">Dto.%</th>
                                <th class="text-end">IVA</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>`;

        if (lineas.length === 0) {
            html += `<tr><td colspan="8" class="text-center text-muted py-3">Sin líneas.</td></tr>`;
        } else {
            lineas.forEach(function(l) {
                const lineaNum = parseInt(l.Linea);
                const checked  = estado.lineasSeleccionadas.has(lineaNum) ? 'checked' : '';
                const yaFact   = String(l.Facturada || '').toUpperCase() === 'S';
                const disabled = yaFact ? 'disabled title="Ya facturada"' : '';
                const rowClass = yaFact ? 'table-secondary text-muted' : '';
                const total    = fmtEur(l.Total);

                html += `
                <tr class="${rowClass}">
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input linea-check"
                            data-codigo="${escAttr(codigo)}"
                            data-linea="${lineaNum}"
                            ${checked ? 'checked' : ''}
                            ${disabled}
                            onchange="toggleLinea('${escHtml(codigo)}', ${lineaNum}, this.checked); actualizarCheckCabecera('${escHtml(codigo)}');">
                    </td>
                    <td>${lineaNum}</td>
                    <td>
                        <strong>${escHtml(l.Id_Articulo || '')}</strong>
                        ${yaFact ? '<span class="badge bg-secondary ms-1">Ya facturada</span>' : ''}
                    </td>
                    <td class="text-end">${fmtNum(l.Cantidad)}</td>
                    <td class="text-end">${fmtEur(l.Precio)}</td>
                    <td class="text-end">${fmtNum(l.Descuento)}%</td>
                    <td class="text-end">${escHtml(l.Id_Tipo_IVA)}</td>
                    <td class="text-end"><strong>${total}</strong></td>
                </tr>`;
            });
        }

        html += `
                        </tbody>
                    </table>
                </div>
            </div>
        </div>`;

        container.append(html);
    });
}

function toggleLinea(codigo, lineaNum, checked) {
    if (!estadoAlbaranes[codigo]) return;
    if (checked) {
        estadoAlbaranes[codigo].lineasSeleccionadas.add(lineaNum);
    } else {
        estadoAlbaranes[codigo].lineasSeleccionadas.delete(lineaNum);
    }
    actualizarResumen();
}

function actualizarCheckCabecera(codigo) {
    const lineas   = document.querySelectorAll(`input.linea-check[data-codigo="${codigo}"]:not(:disabled)`);
    const cabecera = document.querySelector(`input.check-all[data-codigo="${codigo}"]`);
    if (!cabecera) return;
    const total   = lineas.length;
    const marcadas = [...lineas].filter(cb => cb.checked).length;
    cabecera.checked       = (marcadas === total && total > 0);
    cabecera.indeterminate = (marcadas > 0 && marcadas < total);
}

function toggleTodas(codigo) {
    if (!estadoAlbaranes[codigo]) return;
    const estado  = estadoAlbaranes[codigo];
    const lineas  = estado.albaran.lineas || [];
    const noFact  = lineas.filter(l => String(l.Facturada || '').toUpperCase() !== 'S');
    const allOn   = noFact.every(l => estado.lineasSeleccionadas.has(parseInt(l.Linea)));

    noFact.forEach(function(l) {
        const lineaNum = parseInt(l.Linea);
        const cb = document.querySelector(`input.linea-check[data-codigo="${escAttr(codigo)}"][data-linea="${lineaNum}"]`);
        if (allOn) {
            estado.lineasSeleccionadas.delete(lineaNum);
            if (cb) cb.checked = false;
        } else {
            estado.lineasSeleccionadas.add(lineaNum);
            if (cb) cb.checked = true;
        }
    });
    actualizarResumen();
    actualizarCheckCabecera(codigo);
}

function actualizarResumen() {
    let totalLineas       = 0;
    let albaranesConLineas = 0;
    let totalImporte      = 0;

    Object.values(estadoAlbaranes).forEach(function(estado) {
        const n = estado.lineasSeleccionadas.size;
        totalLineas += n;
        if (n > 0) albaranesConLineas++;
        (estado.albaran.lineas || []).forEach(function(l) {
            if (estado.lineasSeleccionadas.has(parseInt(l.Linea))) {
                totalImporte += parseFloat(l.Total || 0);
            }
        });
    });

    const txt = totalLineas > 0
        ? `${totalLineas} línea(s) seleccionada(s) de ${albaranesConLineas} albarán(es).`
        : 'Ninguna línea seleccionada.';

    $('#resumenLineas').text(txt);
    $('#btnFacturar').prop('disabled', false);

    actualizarVisibilidadDestinatario();
    actualizarVistaNifExportacion();

    const tipoDoc   = document.querySelector('input[name="Tipo_Documento"]:checked')?.value;
    const avisoSimpl = document.getElementById('avisoSimplificada');
    if (avisoSimpl) {
        const limite  = esEmpresaOProfesional() ? 3000 : 400;
        avisoSimpl.style.display = (tipoDoc === 'SIMPLIFICADA' && totalImporte > limite) ? '' : 'none';
        const txtEl = document.getElementById('avisoSimplificadaTexto');
        if (txtEl) {
            txtEl.innerHTML = esEmpresaOProfesional()
                ? 'El destinatario es <strong>empresa o profesional</strong>: límite <strong>3.000,00 €</strong> (IVA incl.). Usa una factura ordinaria.'
                : 'El destinatario no es empresa ni profesional: límite <strong>400,00 €</strong> (IVA incl.). Usa una factura ordinaria.';
        }
    }
}

async function facturar() {
    const albaranesPay = [];
    Object.entries(estadoAlbaranes).forEach(function([codigo, estado]) {
        if (estado.lineasSeleccionadas.size > 0) {
            albaranesPay.push({
                codigo: codigo,
                lineas: Array.from(estado.lineasSeleccionadas),
            });
        }
    });

    if (albaranesPay.length === 0) {
        App.notify('Debes seleccionar al menos una línea del albarán para poder crear la factura.', 'warning');
        return;
    }

    const tipoDocumento = new FormData(document.getElementById('facturarForm')).get('Tipo_Documento') || 'FACTURA';
    if (tipoDocumento !== 'SIMPLIFICADA' && !clienteActual) {
        App.notify('Debe seleccionar un cliente para crear una factura normal.', 'warning');
        return;
    }

    if (tipoDocumento === 'SIMPLIFICADA') {
        let totalImporte = 0;
        Object.values(estadoAlbaranes).forEach(function(estado) {
            (estado.albaran.lineas || []).forEach(function(l) {
                if (estado.lineasSeleccionadas.has(parseInt(l.Linea))) {
                    totalImporte += parseFloat(l.Total || 0);
                }
            });
        });
        const esEmpresa = esEmpresaOProfesional();
        const limite    = esEmpresa ? 3000 : 400;
        if (totalImporte > limite) {
            const quien = esEmpresa
                ? 'empresa o profesional (máx. 3.000,00 €)'
                : 'particular (máx. 400,00 €)';
            App.notify(`El total supera el límite para factura simplificada con destinatario ${quien}. Usa una factura ordinaria.`, 'warning');
            return;
        }
    }

    if (tipoDocumento !== 'SIMPLIFICADA' && clienteActual) {
        const nifConfirmado = await App.confirmarNIF(clienteActual.NIF || '', {
            clienteCodigo: clienteActual.Codigo,
            apiBase: FACT_API_BASE,
        });
        if (nifConfirmado === null) return;
        if (nifConfirmado !== clienteActual.NIF) {
            clienteActual.NIF = nifConfirmado;
            const elNIF = document.getElementById('clienteNIF');
            if (elNIF) elNIF.textContent = nifConfirmado;
        }
    }

    const formData = new FormData(document.getElementById('facturarForm'));
    const hayExportacionEnPayload = albaranesPay.some(function(ab) {
        const estado = estadoAlbaranes[ab.codigo];
        return (estado?.albaran.lineas || []).some(function(l) {
            return estado.lineasSeleccionadas.has(parseInt(l.Linea)) &&
                CALIFICACION_EXPORTACION.includes((l.Calificacion || 'S1').toUpperCase());
        });
    });

    const payload = {
        albaranes:           albaranesPay,
        Tipo_Documento:      formData.get('Tipo_Documento')      || 'FACTURA',
        Fecha:               formData.get('Fecha')               || new Date().toISOString().slice(0, 10),
        Id_Forma_Pago:       formData.get('Id_Forma_Pago')       || '',
        Observaciones:       formData.get('Observaciones')       || '',
        Descuento_Especial:  parseFloat(formData.get('Descuento_Especial')  || '0'),
        Descuento_Comercial: parseFloat(formData.get('Descuento_Comercial') || '0'),
        Descuento_PP:        parseFloat(formData.get('Descuento_PP')        || '0'),
        Id_Cliente:          clienteActual?.Codigo || formData.get('Id_Cliente') || '',
    };

    if (hayExportacionEnPayload) {
        payload.nif_exportacion       = nifExportacionVacio ? '' : nifExportacion;
        payload.nif_exportacion_vacio = nifExportacionVacio;
        if (/^[A-Z]{2}$/.test(paisExportacion)) {
            payload.pais_exportacion = paisExportacion;
        }
    }

    const btn = document.getElementById('btnFacturar');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creando...';

    App.showLoading();
    try {
        const resp = await factApiSend('/facturas.php/from-albaranes', payload, 'POST');

        if (resp.ok === false || resp.success === false) {
            throw new Error(resp.message || 'Error al crear la factura.');
        }

        const codigo = resp.data?.codigo ?? '';
        if (!codigo) {
            throw new Error('La factura se procesó pero no se obtuvo el código. Revisa el listado de facturas.');
        }

        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enviando a VeriFactu...';

        const verifactuPayload = { tipo_origen: 'FACTURA', id_documento: codigo };
        if (hayExportacionEnPayload) {
            const hayE5envio = albaranesPay.some(function(ab) {
                const estado = estadoAlbaranes[ab.codigo];
                return (estado?.albaran?.lineas || []).some(function(l) {
                    return ab.lineas.includes(parseInt(l.Linea))
                        && (l.Calificacion || '').toUpperCase() === 'E5';
                });
            });
            if (hayE5envio) {
                if (!nifExportacion) {
                    App.hideLoading();
                    App.notify('Para una operación E5 (entrega intracomunitaria) debes indicar el VAT del destinatario.', 'danger');
                    return;
                }
                if (!/^[A-Z]{2}$/.test(paisExportacion)) {
                    App.hideLoading();
                    App.notify('Selecciona el código de país del destinatario para una operación E5.', 'danger');
                    return;
                }
                const errVat = window.FormUtils?.validarVatUE?.(nifExportacion, paisExportacion);
                if (errVat) {
                    App.hideLoading();
                    App.notify(errVat + ' Revisa que el VAT corresponda al país seleccionado.', 'danger');
                    return;
                }
                const yaPrefijado = nifExportacion.startsWith(paisExportacion);
                verifactuPayload.nif_exportacion       = yaPrefijado ? nifExportacion : (paisExportacion + nifExportacion);
                verifactuPayload.nif_exportacion_vacio = false;
            } else {
                verifactuPayload.nif_exportacion       = nifExportacionVacio ? '' : nifExportacion;
                verifactuPayload.nif_exportacion_vacio = nifExportacionVacio;
            }
            if (/^[A-Z]{2}$/.test(paisExportacion)) {
                verifactuPayload.pais_exportacion = paisExportacion;
            }
        }

        const verifResp = await factApiSend('/verifactu.php/enviar', verifactuPayload, 'POST');
        if (verifResp?.ok === false || verifResp?.success === false) {
            console.warn('VeriFactu warning:', verifResp?.message);
        }

        App.hideLoading();
        App.notify('Factura ' + codigo + ' creada y enviada a Hacienda.', 'success');
        setTimeout(function() {
            window.location = BASE + '/src/views/facturas/ver.php?codigo=' + encodeURIComponent(codigo);
        }, 1000);
    } catch (err) {
        App.hideLoading();
        App.notify(err.message || 'Error al crear la factura.', 'danger');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-file-earmark-check me-1"></i>Facturar y Enviar a Verifactu';
    }
}

function toggleTodasGlobal() {
    let totalNoFact     = 0;
    let totalSeleccionadas = 0;

    Object.values(estadoAlbaranes).forEach(function(estado) {
        (estado.albaran.lineas || []).forEach(function(l) {
            if (String(l.Facturada || '').toUpperCase() !== 'S') {
                totalNoFact++;
                if (estado.lineasSeleccionadas.has(parseInt(l.Linea))) totalSeleccionadas++;
            }
        });
    });

    const seleccionar = totalSeleccionadas < totalNoFact;

    Object.entries(estadoAlbaranes).forEach(function([codigo, estado]) {
        (estado.albaran.lineas || []).forEach(function(l) {
            if (String(l.Facturada || '').toUpperCase() === 'S') return;
            const lineaNum = parseInt(l.Linea);
            const cb = document.querySelector(`input.linea-check[data-codigo="${escAttr(codigo)}"][data-linea="${lineaNum}"]`);
            if (seleccionar) {
                estado.lineasSeleccionadas.add(lineaNum);
                if (cb) cb.checked = true;
            } else {
                estado.lineasSeleccionadas.delete(lineaNum);
                if (cb) cb.checked = false;
            }
        });
        actualizarCheckCabecera(codigo);
    });

    const btn = document.getElementById('btnSeleccionarTodas');
    btn.innerHTML = seleccionar
        ? '<i class="bi bi-x-square me-1"></i>Deseleccionar todas'
        : '<i class="bi bi-check2-all me-1"></i>Seleccionar todas';

    actualizarResumen();
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function escHtml(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                          .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
function escAttr(s) {
    return String(s ?? '').replace(/"/g, '\\"');
}
function fmtNum(v, dec) {
    dec = dec !== undefined ? dec : 2;
    return (parseFloat(v) || 0).toLocaleString('es-ES', {
        minimumFractionDigits: dec, maximumFractionDigits: dec
    });
}
function fmtEur(v) { return fmtNum(v) + ' €'; }
