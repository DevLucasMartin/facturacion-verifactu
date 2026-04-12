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
    let lineas        = [];
    let clienteActual = null;
    let tarifaActual  = 1;
    let rePorcentaje  = 0;
    let tiposIVA      = {};
    let lineaEditando = null;

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
                idArticulo:  l.Id_Articulo,
                descripcion: l.Descripcion,
                cantidad:    parseFloat(l.Cantidad)  || 0,
                precio:      parseFloat(l.Precio)    || 0,
                descuento:   parseFloat(l.Descuento) || 0,
                tipoIVA:     l.Id_Tipo_IVA
            }));
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
                lineas[lineaEditando] = { ...lineas[lineaEditando], idArticulo: articulo.Codigo, descripcion: articulo.Descripcion, precio, descuento, tipoIVA };
            } else {
                lineas.push({ idArticulo: articulo.Codigo, descripcion: articulo.Descripcion, cantidad: 1, precio, descuento, tipoIVA });
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
                    <td colspan="8" class="text-center text-muted py-4">
                        <i class="bi bi-cart-plus fs-1 d-block mb-2"></i>
                        Añada líneas a la factura
                    </td>
                </tr>`;
            return;
        }

        tbody.innerHTML = lineas.map((linea, index) => {
            const tipo       = tiposIVA[linea.tipoIVA] || { IVA: 21, RE: 0 };
            const importeBruto = linea.cantidad * linea.precio;
            const dto        = importeBruto * ((linea.descuento || 0) / 100);
            const base       = importeBruto - dto;

            return `
            <tr data-index="${index}">
                <td>${index + 1}</td>
                <td>
                    <input type="hidden" name="lineas[${index}][Id_Articulo]" value="${escapeHtml(linea.idArticulo)}">
                    <input type="hidden" name="lineas[${index}][Descripcion]" value="${escapeHtml(linea.descripcion)}">
                    <input type="hidden" name="lineas[${index}][Id_Tipo_IVA]" value="${escapeHtml(linea.tipoIVA)}">
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
                <td>
                    <span class="badge bg-secondary">${escapeHtml(linea.tipoIVA)} (${Number(tipo.IVA || 21)}%)</span>
                </td>
                <td class="text-end"><strong>${formatCurrency(base)}</strong></td>
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
    }

    window.actualizarLinea = function(index, campo, valor) {
        const n = parseFloat(valor);
        lineas[index][campo] = isNaN(n) ? 0 : n;
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
            const t = tiposIVA[l.tipoIVA] || {};
            return {
                cantidad:  Number(l.cantidad)  || 0,
                precio:    Number(l.precio)    || 0,
                descuento: Number(l.descuento) || 0,
                tipoIVA:   { codigo: l.tipoIVA, iva: Number(t.IVA ?? 21), re: Number(t.RE ?? 0) }
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
            const t = tiposIVA[l.tipoIVA] || {};
            return {
                cantidad:  Number(l.cantidad)  || 0,
                precio:    Number(l.precio)    || 0,
                descuento: Number(l.descuento) || 0,
                tipoIVA:   { codigo: l.tipoIVA, iva: Number(t.IVA ?? 21), re: Number(t.RE ?? 0) }
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
                Id_Articulo: l.idArticulo,
                Descripcion: l.descripcion,
                Cantidad:    l.cantidad,
                Precio:      l.precio,
                Descuento:   l.descuento,
                Id_Tipo_IVA: l.tipoIVA
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
            await apiSend(`/facturas.php/${encodeURIComponent(CODIGO_EDICION)}`, 'PUT', formData);
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
            await apiSend(`/facturas.php/${encodeURIComponent(CODIGO_EDICION)}`, 'PUT', formData);
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
