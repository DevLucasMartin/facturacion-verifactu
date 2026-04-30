(() => {
    const BASE = (() => {
        const p = window.location.pathname;
        const idx = p.indexOf('/SistemaGestionFacturas/');
        return idx >= 0 ? p.substring(0, idx) + '/SistemaGestionFacturas' : '/SistemaGestionFacturas';
    })();

    const API = BASE + '/src/api';
    const { escapeHtml, debounce, formatCurrency } = FormUtils;
    const { apiGet, apiSend } = FormUtils.makeApiClient(API);

    const CODIGO_EDICION = document.querySelector('input[name="codigo"]')?.value?.trim();

    function notify(msg, type = 'warning') {
        const panel = document.getElementById('erroresPanel');
        const list  = document.getElementById('erroresList');
        if (!panel || !list) { console.warn(msg); return; }
        panel.className = 'alert mt-3 alert-' + type;
        panel.style.display = 'block';
        list.innerHTML = `<li>${escapeHtml(msg)}</li>`;
        setTimeout(() => { if (panel?.isConnected) panel.style.display = 'none'; }, 5000);
    }

    // ---------- Estado ----------
    let lineas             = [];
    let clienteActual      = null;
    let tarifaActual       = 1;
    let rePorcentaje       = 0;
    let tiposIVA           = {};
    let lineaEditando      = null;
    let claveRegimenGlobal = '01';

    const CLAVE_REGIMEN_E456 = ['E4', 'E5', 'E6'];

    // ---------- Helpers calificación ----------
    function esExentoONoSujeto(calif) {
        return ['N1','N2','E1','E2','E3','E4','E5','E6'].includes(calif);
    }

    function textoIvaExento(calif) {
        return (calif === 'N1' || calif === 'N2') ? 'No sujeto' : 'Exento';
    }

    const CALIFICACION_HELP = {
        S1: 'Operación <strong>sujeta y no exenta</strong>, sin inversión del sujeto pasivo.',
        S2: 'Operación <strong>sujeta y no exenta</strong>, con inversión del sujeto pasivo.',
        N1: 'Operación <strong>no sujeta</strong> por los artículos 7, 14 u otras causas.',
        N2: 'Operación <strong>no sujeta</strong> por las reglas de localización.',
        E1: '<strong>Exenta – Art. 20 LIVA.</strong> Exenciones interiores.',
        E2: '<strong>Exenta – Art. 21 LIVA.</strong> Exportaciones definitivas.',
        E3: '<strong>Exenta – Art. 22 LIVA.</strong> Operaciones asimiladas a exportaciones.',
        E4: '<strong>Exenta – Art. 23 y 24 LIVA.</strong> Zonas francas.',
        E5: '<strong>Exenta – Art. 25 LIVA.</strong> Entregas intracomunitarias de bienes.',
        E6: '<strong>Exenta – Otros artículos LIVA.</strong>',
    };

    window.actualizarPopoverCalif = function (index) {
        const valor  = document.getElementById(`calif-select-${index}`)?.value || 'S1';
        const iconEl = document.getElementById(`calif-info-${index}`);
        if (!iconEl) return;
        const popover = bootstrap.Popover.getOrCreateInstance(iconEl);
        popover.setContent({
            '.popover-header': 'Calificación de la operación',
            '.popover-body':   CALIFICACION_HELP[valor] || valor,
        });
    };

    function buildIvaOptions(ivaSeleccionado) {
        return Object.values(tiposIVA)
            .filter(t => t.Activo !== 'N')
            .map(t => {
                const label = `${escapeHtml(t.Descripcion || t.Codigo)} (${t.IVA}%)`;
                return `<option value="${escapeHtml(t.Codigo)}" ${ivaSeleccionado === t.Codigo ? 'selected' : ''}>${label}</option>`;
            }).join('');
    }

    // ---------- Init ----------
    document.addEventListener('DOMContentLoaded', async () => {
        try {
            await cargarTiposIVA();
            await cargarCanales();
            inicializarEventos();

            if (CODIGO_EDICION) {
                await cargarFactura(CODIGO_EDICION);
            } else {
                notify('No se ha indicado ningún código de factura', 'danger');
            }

            renderLineas();
            recalcular();
            mostrarBotonGuardarCliente(false);
        } catch (e) {
            notify(e.message || 'Error inicializando', 'danger');
            console.error(e);
        }
    });

    function inicializarEventos() {
        document.querySelectorAll('input[name="Tipo_Documento"]').forEach(r => {
            r.addEventListener('change', () => {
                const tipo = document.querySelector('input[name="Tipo_Documento"]:checked')?.value;
                document.getElementById('facturaOrigenRow').style.display = (tipo === 'RECTIFICATIVA') ? '' : 'none';
                recalcular();
            });
        });

        ['inputDtoEspecial', 'inputDtoComercial', 'inputDtoPP'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', recalcular);
        });

        const buscarClienteInput = document.getElementById('buscarCliente');
        if (buscarClienteInput) {
            buscarClienteInput.addEventListener('input', debounce(() => {
                buscarClientes(buscarClienteInput.value.trim());
            }, 250));
        }

        const buscarArticuloInput = document.getElementById('buscarArticulo');
        if (buscarArticuloInput) {
            buscarArticuloInput.addEventListener('input', debounce(() => {
                buscarArticulos(buscarArticuloInput.value.trim());
            }, 250));
        }

        document.getElementById('facturaForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            guardarFactura().catch(err => {
                notify(err.message || 'Error guardando factura', 'danger');
                console.error(err);
            });
        });
    }

    // ---------- Loaders ----------
    async function cargarTiposIVA() {
        const json = await apiGet('/tiposiva.php');
        tiposIVA = {};
        (json.data || []).forEach(t => { tiposIVA[t.Codigo] = t; });
    }

    async function cargarCanales() {
        const json = await apiGet('/canales.php');
        const select = document.getElementById('selectCanal');
        if (!select) return;
        select.innerHTML = '';
        (json.data || []).forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.Codigo;
            opt.textContent = c.Descripcion;
            select.appendChild(opt);
        });
    }

    // ---------- Cargar factura existente ----------
    async function cargarFactura(codigo) {
        const json = await apiGet(`/facturas.php/${encodeURIComponent(codigo)}`);
        const f = json.data;

        const r = document.querySelector(`input[name="Tipo_Documento"][value="${f.Tipo_Documento}"]`);
        if (r) r.checked = true;

        document.getElementById('selectCanal').value = f.Id_Canal || '';
        const fechaStr = (typeof f.Fecha === 'object' && f.Fecha !== null) ? f.Fecha.date : (f.Fecha || '');
        document.getElementById('inputFecha').value = fechaStr.substring(0, 10);

        if (f.Id_Cliente) await window.seleccionarClienteReal(f.Id_Cliente);

        document.getElementById('inputDtoEspecial').value  = f.Descuento_Especial  || 0;
        document.getElementById('inputDtoComercial').value = f.Descuento_Comercial || 0;
        document.getElementById('inputDtoPP').value        = f.Descuento_PP        || 0;
        document.getElementById('inputObservaciones').value = f.Observaciones || '';

        if (Array.isArray(f.lineas)) {
            lineas = f.lineas.map(l => ({
                idArticulo:   l.Id_Articulo,
                descripcion:  l.Descripcion,
                cantidad:     parseFloat(l.Cantidad)  || 0,
                precio:       parseFloat(l.Precio)    || 0,
                descuento:    parseFloat(l.Descuento) || 0,
                tipoIVA:      l.Id_Tipo_IVA,
                calificacion: l.Calificacion || 'S1',
            }));
            const e456 = f.lineas.find(l => CLAVE_REGIMEN_E456.includes(l.Calificacion || ''));
            if (e456?.Clave_Regimen) claveRegimenGlobal = e456.Clave_Regimen;
        }

        document.getElementById('facturaOrigenRow').style.display = (f.Tipo_Documento === 'RECTIFICATIVA') ? '' : 'none';
        if (f.Tipo_Documento === 'RECTIFICATIVA') {
            document.getElementById('selectFacturaOrigen').value = f.Id_Factura_Origen    || '';
            document.getElementById('selectMotivo').value        = f.Motivo_Rectificacion || '';
        }
    }

    // ---------- Cliente Modal ----------
    window.seleccionarCliente = function() {
        const modalEl = document.getElementById('clienteModal');
        const input   = document.getElementById('buscarCliente');
        if (!modalEl) return;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        if (input) { input.value = ''; input.focus(); }
        buscarClientes('');
    };

    async function buscarClientes(query) {
        const tbody = document.getElementById('clientesBody');
        if (!tbody) return;
        tbody.innerHTML = `<tr><td colspan="5" class="text-muted text-center">Cargando...</td></tr>`;
        try {
            const url = query
                ? `/clientes.php/search?q=${encodeURIComponent(query)}`
                : `/clientes.php?limit=20`;
            const json = await apiGet(url);
            const rows = (json.data || []).map(c => `
                <tr style="cursor:pointer" onclick="seleccionarClienteReal('${escapeHtml(c.Codigo)}')">
                    <td>${escapeHtml(c.Codigo)}</td>
                    <td>${escapeHtml(c.NIF || '-')}</td>
                    <td>${escapeHtml(c.Razon_Social || c.Nombre || '-')}</td>
                    <td>${escapeHtml(c.Id_Forma_Pago || '-')}</td>
                </tr>`).join('');
            tbody.innerHTML = rows || `<tr><td colspan="5" class="text-muted text-center">No hay clientes</td></tr>`;
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-danger text-center">${escapeHtml(e.message)}</td></tr>`;
        }
    }

    window.seleccionarClienteReal = async function(id) {
        try {
            const json = await apiGet(`/clientes.php/${encodeURIComponent(id)}`);
            clienteActual = json.data;
            tarifaActual  = parseInt(clienteActual?.Tarifa) || 1;
            rePorcentaje  = parseFloat(clienteActual?.RE_Porcentaje) || 0;

            document.getElementById('clienteEmpty').style.display = 'none';
            document.getElementById('clienteData').style.display  = '';
            document.getElementById('inputIdCliente').value        = clienteActual.Codigo || '';
            document.getElementById('clienteNombre').textContent   = clienteActual.Razon_Social || clienteActual.Nombre || '';
            document.getElementById('clienteNIF').textContent      = clienteActual.NIF || '';
            document.getElementById('clienteDireccion').textContent = clienteActual.Direccion || '-';
            document.getElementById('clienteFormaPago').textContent = clienteActual.Id_Forma_Pago || '-';
            document.getElementById('clienteTarifa').textContent   = 'Tarifa ' + tarifaActual;

            if (rePorcentaje > 0) {
                document.getElementById('reRow').style.display     = '';
                document.getElementById('clienteRE').textContent   = rePorcentaje + '%';
                document.getElementById('reDisplay').style.display = '';
            } else {
                document.getElementById('reRow').style.display     = 'none';
                document.getElementById('reDisplay').style.display = 'none';
            }

            bootstrap.Modal.getOrCreateInstance(document.getElementById('clienteModal')).hide();
            recalcular();
        } catch (e) {
            notify(e.message || 'Error seleccionando cliente', 'danger');
        }
    };

    window.abrirNuevoCliente = function() {
        $('#nuevoClienteMsg').removeClass('d-none alert-success alert-danger').addClass('alert-info').text('Rellena el formulario.');
        $('#nuevoClienteForm')[0].reset();
        $('#ncRE').val('0');
        $('#nuevoClienteModal').modal('show');
        setTimeout(() => $('#ncNif').trigger('focus'), 150);
    };

    $(document).on('submit', '#nuevoClienteForm', function(e) {
        e.preventDefault();
        const nif    = $('#ncNif').val().trim();
        const nombre = $('#ncNombre').val().trim();
        if (!nif || !nombre) {
            $('#nuevoClienteMsg').removeClass('d-none alert-info alert-success').addClass('alert-danger').text('Faltan campos obligatorios: NIF y Nombre.');
            return;
        }
        const fake = {
            Codigo:        'TEMP-' + Math.floor(Math.random() * 100000),
            NIF:           nif,
            Razon_Social:  nombre,
            Direccion:     $('#ncDireccion').val().trim() || '-',
            Id_Forma_Pago: $('#ncFormaPago').val().trim() || '-',
            Tarifa:        parseInt($('#ncTarifa').val(), 10) || 1,
            RE_Porcentaje: parseFloat($('#ncRE').val()) || 0
        };
        clienteActual = fake;
        tarifaActual  = fake.Tarifa || 1;
        rePorcentaje  = parseFloat(fake.RE_Porcentaje) || 0;
        $('#clienteEmpty').hide();
        $('#clienteData').show();
        $('#inputIdCliente').val(fake.Codigo);
        $('#clienteNombre').text(fake.Razon_Social);
        $('#clienteNIF').text(fake.NIF);
        $('#clienteDireccion').text(fake.Direccion);
        $('#clienteFormaPago').text(fake.Id_Forma_Pago);
        $('#clienteTarifa').text('Tarifa ' + tarifaActual);
        if (rePorcentaje > 0) { $('#reRow').show(); $('#clienteRE').text(rePorcentaje + '%'); $('#reDisplay').show(); }
        else                  { $('#reRow').hide(); $('#reDisplay').hide(); }
        $('#nuevoClienteMsg').removeClass('d-none alert-info alert-danger').addClass('alert-success').text('Cliente seleccionado.');
        setTimeout(() => { $('#nuevoClienteModal').modal('hide'); recalcular(); }, 600);
        mostrarBotonGuardarCliente(true);
    });

    function mostrarBotonGuardarCliente(show) { $('#btnGuardarCliente').toggleClass('d-none', !show); }

    window.guardarClienteDemo = function() {
        if (!clienteActual) { notify('No hay cliente seleccionado', 'warning'); return; }
        notify('Cliente actualizado.', 'success');
        mostrarBotonGuardarCliente(false);
    };

    // ---------- Artículo Modal ----------
    window.agregarLinea = function() {
        lineaEditando = lineas.length;
        const modalEl = document.getElementById('articuloModal');
        const input   = document.getElementById('buscarArticulo');
        if (!modalEl) return;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        if (input) { input.value = ''; input.focus(); }
        buscarArticulos('');
    };

    async function buscarArticulos(query) {
        const tbody = document.getElementById('articulosBody');
        if (!tbody) return;
        tbody.innerHTML = `<tr><td colspan="5" class="text-muted text-center">Cargando...</td></tr>`;
        try {
            const url = query
                ? `/articulos.php/search?q=${encodeURIComponent(query)}&tarifa=${encodeURIComponent(tarifaActual)}`
                : `/articulos.php?page=1&per_page=25&tarifa=${encodeURIComponent(tarifaActual)}`;
            const json  = await apiGet(url);
            const items = Array.isArray(json.data) ? json.data : (json.data?.items || json.data?.rows || []);
            const rows  = items.map(a => {
                const precio = Number(a['Precio_Venta_' + tarifaActual] ?? a.Precio_Venta_1 ?? a.Precio ?? 0);
                return `
                <tr style="cursor:pointer" onclick="seleccionarArticulo('${escapeHtml(a.Codigo)}')">
                    <td>${escapeHtml(a.Codigo)}</td>
                    <td>${escapeHtml(a.Descripcion || '')}</td>
                    <td>${escapeHtml(a.Id_Tipo_IVA || '')}</td>
                    <td>${formatCurrency(precio)}</td>
                    <td><button type="button" class="btn btn-sm btn-primary"><i class="bi bi-check"></i></button></td>
                </tr>`;
            }).join('');
            tbody.innerHTML = rows || `<tr><td colspan="5" class="text-muted text-center">No hay artículos</td></tr>`;
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-danger text-center">${escapeHtml(e.message)}</td></tr>`;
        }
    }

    window.seleccionarArticulo = async function(codigo) {
        try {
            const json     = await apiGet(`/articulos.php/${encodeURIComponent(codigo)}`);
            const articulo = json.data;
            const precio   = Number(articulo['Precio_Venta_' + tarifaActual] ?? articulo.Precio_Venta_1 ?? 0);
            const tipoIVA  = articulo.Id_Tipo_IVA || 'GEN';
            const descuento = Number(articulo.Descuento ?? 0);

            if (lineaEditando !== null && lineaEditando < lineas.length) {
                lineas[lineaEditando] = { ...lineas[lineaEditando], idArticulo: articulo.Codigo, descripcion: articulo.Descripcion, precio, descuento, tipoIVA, calificacion: lineas[lineaEditando].calificacion || 'S1' };
            } else {
                lineas.push({ idArticulo: articulo.Codigo, descripcion: articulo.Descripcion, cantidad: 1, precio, descuento, tipoIVA, calificacion: 'S1' });
            }

            renderLineas();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('articuloModal')).hide();
            lineaEditando = null;
            recalcular();
        } catch (e) {
            notify(e.message || 'Error seleccionando artículo', 'danger');
        }
    };

    // ---------- Líneas ----------
    function renderLineas() {
        const tbody = document.getElementById('lineasBody');
        if (!tbody) return;

        if (lineas.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="10" class="text-center text-muted py-4">
                        <i class="bi bi-cart-plus fs-1 d-block mb-2"></i>
                        Añada líneas a la factura
                    </td>
                </tr>`;
            return;
        }

        tbody.innerHTML = lineas.map((linea, index) => {
            const tipo         = tiposIVA[linea.tipoIVA] || { IVA: 21, RE: 0 };
            const importeBruto = linea.cantidad * linea.precio;
            const base         = importeBruto * (1 - (linea.descuento || 0) / 100);
            const ivaPct       = Number(tipo.IVA ?? 21);
            const sinIVA       = esExentoONoSujeto(linea.calificacion);
            const IVACalc      = (linea.calificacion === 'S2' || sinIVA) ? 0 : base * (ivaPct / 100);

            return `
            <tr data-index="${index}">
                <td>${index + 1}</td>
                <td>
                    <input type="hidden" name="lineas[${index}][Id_Articulo]" value="${escapeHtml(linea.idArticulo)}">
                    <input type="hidden" name="lineas[${index}][Descripcion]" value="${escapeHtml(linea.descripcion)}">
                    <strong>${escapeHtml(linea.idArticulo)}</strong><br>
                    <small class="text-muted">${escapeHtml((linea.descripcion || '').substring(0, 40))}</small>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm fact-form-control"
                        name="lineas[${index}][Cantidad]" value="${Number(linea.cantidad).toFixed(2)}"
                        step="any" onchange="actualizarLinea(${index}, 'cantidad', this.value)">
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm fact-form-control"
                        name="lineas[${index}][Precio]" value="${Number(linea.precio).toFixed(2)}"
                        min="0" step="any" onchange="actualizarLinea(${index}, 'precio', this.value)">
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm fact-form-control"
                        name="lineas[${index}][Descuento]" value="${Number(linea.descuento || 0)}"
                        min="0" max="100" step="any" onchange="actualizarLinea(${index}, 'descuento', this.value)">
                </td>
                <td class="${sinIVA ? 'iva-exento-cell' : ''}">
                    <select class="form-control form-control-sm fact-form-control"
                        id="iva-select-${index}" name="lineas[${index}][Id_Tipo_IVA]"
                        onchange="actualizarLinea(${index}, 'tipoIVA', this.value)"
                        style="${sinIVA ? 'display:none' : ''}">
                        ${buildIvaOptions(linea.tipoIVA)}
                    </select>
                    <span id="iva-text-${index}" class="text-muted fw-bold" style="${sinIVA ? '' : 'display:none'}">${textoIvaExento(linea.calificacion)}</span>
                </td>
                <td>
                    <input type="hidden" name="lineas[${index}][Calificacion]" value="${escapeHtml(linea.calificacion || 'S1')}">
                    <div class="d-flex align-items-center gap-1">
                    <select class="form-control form-control-sm fact-form-control"
                        id="calif-select-${index}"
                        onchange="actualizarLinea(${index}, 'calificacion', this.value); actualizarPopoverCalif(${index})">
                        <option value="S1" ${(linea.calificacion || 'S1') === 'S1' ? 'selected' : ''}>Sujeta no exenta – Sin inv. (S1)</option>
                        <option value="S2" ${linea.calificacion === 'S2' ? 'selected' : ''}>Sujeta no exenta – Con inv. (S2)</option>
                        <option value="N1" ${linea.calificacion === 'N1' ? 'selected' : ''}>No sujeta – Art. 7, 14… (N1)</option>
                        <option value="N2" ${linea.calificacion === 'N2' ? 'selected' : ''}>No sujeta – Localización (N2)</option>
                        <option value="E1" ${linea.calificacion === 'E1' ? 'selected' : ''}>Exenta – Art. 20 (E1)</option>
                        <option value="E2" ${linea.calificacion === 'E2' ? 'selected' : ''}>Exenta – Art. 21 export. (E2)</option>
                        <option value="E3" ${linea.calificacion === 'E3' ? 'selected' : ''}>Exenta – Art. 22 (E3)</option>
                        <option value="E4" ${linea.calificacion === 'E4' ? 'selected' : ''}>Exenta – Art. 23-24 (E4)</option>
                        <option value="E5" ${linea.calificacion === 'E5' ? 'selected' : ''}>Exenta – Art. 25 UE (E5)</option>
                        <option value="E6" ${linea.calificacion === 'E6' ? 'selected' : ''}>Exenta – Otros (E6)</option>
                    </select>
                    <span id="calif-info-${index}"
                        class="text-muted"
                        style="cursor:pointer; font-size:1rem; line-height:1; flex-shrink:0;"
                        tabindex="0"
                        data-bs-toggle="popover"
                        data-bs-trigger="focus"
                        data-bs-placement="left"
                        data-bs-html="true"
                        data-bs-title="Calificación de la operación"
                        data-bs-content="${escapeHtml(CALIFICACION_HELP[linea.calificacion || 'S1'] || '')}">
                        &#9432;
                    </span>
                    </div>
                </td>
                <td class="text-end"><strong id="base-linea-${index}">${formatCurrency(base)}</strong></td>
                <td class="text-end"><strong id="iva-linea-${index}">${sinIVA ? textoIvaExento(linea.calificacion) : formatCurrency(IVACalc)}</strong></td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline-warning me-1"
                        onclick="editarLinea(${index})" title="Cambiar artículo">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger"
                        onclick="eliminarLinea(${index})" title="Eliminar">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>`;
        }).join('');

        lineas.forEach((_, index) => {
            const el = document.getElementById(`calif-info-${index}`);
            if (el) bootstrap.Popover.getOrCreateInstance(el, { html: true });
        });
    }

    window.actualizarLinea = function(index, campo, valor) {
        if (campo === 'tipoIVA') {
            lineas[index].tipoIVA = valor;
        } else if (campo === 'calificacion') {
            lineas[index].calificacion = valor;
            const hiddenInput = document.querySelector(`input[name="lineas[${index}][Calificacion]"]`);
            if (hiddenInput) hiddenInput.value = valor;

            const sinIVA    = esExentoONoSujeto(valor);
            const ivaSelect = document.getElementById(`iva-select-${index}`);
            const ivaText   = document.getElementById(`iva-text-${index}`);
            if (ivaSelect) ivaSelect.style.display = sinIVA ? 'none' : '';
            if (ivaText) {
                ivaText.style.display = sinIVA ? '' : 'none';
                if (sinIVA) ivaText.textContent = textoIvaExento(valor);
            }
        } else {
            const n = parseFloat(valor);
            lineas[index][campo] = isNaN(n) ? 0 : n;
        }

        const linea          = lineas[index];
        const tipo           = tiposIVA[linea.tipoIVA] || {};
        const importe_bruto  = Number(linea.cantidad) * Number(linea.precio);
        const base_imponible = importe_bruto * (1 - (Number(linea.descuento) || 0) / 100);
        const ivaPct         = Number(tipo.IVA ?? 21);
        const sinIVA         = esExentoONoSujeto(linea.calificacion);
        const IVA_calculado  = (linea.calificacion === 'S2' || sinIVA) ? 0 : base_imponible * (ivaPct / 100);

        const baseEl = document.getElementById(`base-linea-${index}`);
        const ivaEl  = document.getElementById(`iva-linea-${index}`);
        if (baseEl) baseEl.textContent = formatCurrency(base_imponible);
        if (ivaEl)  ivaEl.textContent  = sinIVA ? textoIvaExento(linea.calificacion) : formatCurrency(IVA_calculado);

        recalcular();
    };

    window.eliminarLinea = function(index) {
        lineas.splice(index, 1);
        renderLineas();
        recalcular();
    };

    window.editarLinea = function(index) {
        lineaEditando = index;
        const modalEl = document.getElementById('articuloModal');
        const input   = document.getElementById('buscarArticulo');
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        if (input) { input.value = ''; input.focus(); }
        buscarArticulos('');
    };

    // ---------- Totales ----------
    function recalcular() {
        if (!lineas.length) {
            setTotals(0, 0, 0, 0, 0);
            document.getElementById('descuentosDisplay').style.display = 'none';
            document.getElementById('erroresPanel').style.display = 'none';
            return;
        }

        const lineasParaCalculo = lineas.map(l => {
            const sinIVA = esExentoONoSujeto(l.calificacion);
            const t      = sinIVA ? {} : (tiposIVA[l.tipoIVA] || {});
            return {
                cantidad:  Number(l.cantidad)  || 0,
                precio:    Number(l.precio)    || 0,
                descuento: Number(l.descuento) || 0,
                tipoIVA: sinIVA ? null : {
                    codigo: l.tipoIVA,
                    iva:    l.calificacion === 'S2' ? 0 : Number(t.IVA ?? 21),
                    re:     Number(t.RE ?? 0),
                },
            };
        });

        const descuentos = {
            especial:      parseFloat(document.getElementById('inputDtoEspecial').value)  || 0,
            comercial:     parseFloat(document.getElementById('inputDtoComercial').value) || 0,
            pp:            parseFloat(document.getElementById('inputDtoPP').value)        || 0,
            tipoDocumento: document.querySelector('input[name="Tipo_Documento"]:checked')?.value
        };

        const r = FacturacionCalculos.calcularFactura(lineasParaCalculo, descuentos, rePorcentaje);

        document.getElementById('subtotalDisplay').textContent      = formatCurrency(r.subtotal);
        document.getElementById('baseImponibleDisplay').textContent = formatCurrency(r.baseImponible);
        document.getElementById('ivaDisplay').textContent           = formatCurrency(r.importeIVA);
        document.getElementById('reValue').textContent              = formatCurrency(r.importeRE);
        document.getElementById('totalDisplay').textContent         = formatCurrency(r.total);

        const totalDescuentos = (r.descuentos?.importeDtoEspecial  || 0)
                              + (r.descuentos?.importeDtoComercial || 0)
                              + (r.descuentos?.importeDtoPP        || 0);

        if (totalDescuentos > 0) {
            document.getElementById('descuentosDisplay').style.display = '';
            document.getElementById('descuentosValue').textContent = '-' + formatCurrency(totalDescuentos);
        } else {
            document.getElementById('descuentosDisplay').style.display = 'none';
        }

        if (r.errores?.length) {
            document.getElementById('erroresPanel').style.display = '';
            document.getElementById('erroresList').innerHTML = r.errores.map(e => `<li>${escapeHtml(e)}</li>`).join('');
        } else {
            document.getElementById('erroresPanel').style.display = 'none';
        }
    }

    function setTotals(sub, _dto, base, iva, total) {
        document.getElementById('subtotalDisplay').textContent      = formatCurrency(sub);
        document.getElementById('baseImponibleDisplay').textContent = formatCurrency(base);
        document.getElementById('ivaDisplay').textContent           = formatCurrency(iva);
        document.getElementById('reValue').textContent              = formatCurrency(0);
        document.getElementById('totalDisplay').textContent         = formatCurrency(total);
    }

    // ---------- Guardar ----------
    function buildFormData() {
        if (!document.getElementById('inputIdCliente').value) {
            notify('Debe seleccionar un cliente', 'warning'); return null;
        }
        if (!lineas.length) {
            notify('Debe añadir al menos una línea', 'warning'); return null;
        }

        const lineasParaCalculo = lineas.map(l => {
            const sinIVA = esExentoONoSujeto(l.calificacion);
            const t      = sinIVA ? {} : (tiposIVA[l.tipoIVA] || {});
            return {
                cantidad:  Number(l.cantidad)  || 0,
                precio:    Number(l.precio)    || 0,
                descuento: Number(l.descuento) || 0,
                tipoIVA: sinIVA ? null : {
                    codigo: l.tipoIVA,
                    iva:    l.calificacion === 'S2' ? 0 : Number(t.IVA ?? 21),
                    re:     Number(t.RE ?? 0),
                },
            };
        });
        const descuentos = {
            especial:      parseFloat(document.getElementById('inputDtoEspecial').value)  || 0,
            comercial:     parseFloat(document.getElementById('inputDtoComercial').value) || 0,
            pp:            parseFloat(document.getElementById('inputDtoPP').value)        || 0,
            tipoDocumento: document.querySelector('input[name="Tipo_Documento"]:checked')?.value
        };
        const totales = FacturacionCalculos.calcularFactura(lineasParaCalculo, descuentos, rePorcentaje);

        const formData = {
            Id_Canal:            document.getElementById('selectCanal').value,
            Fecha:               document.getElementById('inputFecha').value,
            Id_Cliente:          document.getElementById('inputIdCliente').value,
            Tipo_Documento:      document.querySelector('input[name="Tipo_Documento"]:checked')?.value,
            Observaciones:       document.getElementById('inputObservaciones')?.value || '',
            Descuento_Especial:  parseFloat(document.getElementById('inputDtoEspecial').value)  || 0,
            Descuento_PP:        parseFloat(document.getElementById('inputDtoPP').value)        || 0,
            Descuento_Comercial: parseFloat(document.getElementById('inputDtoComercial').value) || 0,
            Base_Imponible:      totales.baseImponible || 0,
            Importe_IVA:         totales.importeIVA    || 0,
            Importe_RE:          totales.importeRE     || 0,
            Total:               totales.total         || 0,
            lineas: lineas.map(l => ({
                Id_Articulo:   l.idArticulo,
                Descripcion:   l.descripcion,
                Cantidad:      l.cantidad,
                Precio:        l.precio,
                Descuento:     l.descuento,
                Id_Tipo_IVA:   l.tipoIVA,
                Calificacion:  l.calificacion || 'S1',
                Clave_Regimen: CLAVE_REGIMEN_E456.includes(l.calificacion) ? claveRegimenGlobal : '01',
            }))
        };

        if (formData.Tipo_Documento === 'RECTIFICATIVA') {
            formData.Id_Factura_Origen    = document.getElementById('selectFacturaOrigen')?.value;
            formData.Motivo_Rectificacion = document.getElementById('selectMotivo')?.value;
            if (!formData.Id_Factura_Origen || !formData.Motivo_Rectificacion) {
                notify('Debe seleccionar factura origen y motivo de rectificación', 'warning'); return null;
            }
        }

        return formData;
    }

    async function guardarFactura() {
        const formData = buildFormData();
        if (!formData) return;

        const btn = document.getElementById('btnGuardar');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...'; }

        try {
            await apiSend(`/facturas.php/${encodeURIComponent(CODIGO_EDICION)}`, formData, 'PUT');
            notify('Borrador guardado correctamente', 'success');
            setTimeout(() => { window.location.href = BASE + '/src/views/facturas/listado.php'; }, 800);
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar como borrador'; }
        }
    }

    window.confirmarFactura = async function() {
        const formData = buildFormData();
        if (!formData) return;

        const btn = document.getElementById('btnConfirmar');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Confirmando...'; }

        try {
            formData.Cerrada = 'S';
            await apiSend(`/facturas.php/${encodeURIComponent(CODIGO_EDICION)}`, formData, 'PUT');
            notify('Factura confirmada correctamente', 'success');
            setTimeout(() => {
                window.location.href = BASE + '/src/views/facturas/ver.php?codigo=' + encodeURIComponent(CODIGO_EDICION);
            }, 800);
        } catch(err) {
            notify(err.message || 'Error al confirmar la factura', 'danger');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Guardar y confirmar'; }
        }
    };

    window.calcularPreview = function() { recalcular(); };

})();
