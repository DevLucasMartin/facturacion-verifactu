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
        list.innerHTML = '<li>' + escapeHtml(msg) + '</li>';
        setTimeout(function() { if (panel?.isConnected) panel.style.display = 'none'; }, 5000);
    }

    // Estado
    let lineas           = [];
    let clienteActual    = null;
    let tarifaActual     = 1;
    let rePorcentaje     = 0;
    let tiposIVA         = {};
    let territorioActual = 'PENINSULAR';
    let lineaEditando    = null;

    // Init
    document.addEventListener('DOMContentLoaded', async () => {
        try {
            await cargarTiposIVA();
            await cargarCanales();
            await cargarFormasPago();
            inicializarEventos();

            // Cargar el albarán existente automáticamente
            if (CODIGO_EDICION) {
                await cargarAlbaran(CODIGO_EDICION);
            } else {
                notify('No se ha indicado ningún código de albarán', 'danger');
            }

            renderLineas();
            recalcular();
        } catch (e) {
            notify(e.message || 'Error inicializando', 'danger');
            console.error(e);
        }
    });

    function inicializarEventos() {
        ['inputDtoEspecial', 'inputDtoComercial', 'inputDtoPP'].forEach(function(id) {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', recalcular);
        });

        document.getElementById('selectTerritorio')?.addEventListener('change', function() {
            cambiarTerritorio(this.value);
        });

        var buscarClienteInput = document.getElementById('buscarCliente');
        if (buscarClienteInput) {
            buscarClienteInput.addEventListener('input', debounce(function() {
                buscarClientes(buscarClienteInput.value.trim());
            }, 250));
        }

        var buscarArticuloInput = document.getElementById('buscarArticulo');
        if (buscarArticuloInput) {
            buscarArticuloInput.addEventListener('input', debounce(function() {
                buscarArticulos(buscarArticuloInput.value.trim());
            }, 250));
        }

        document.getElementById('albaranForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            guardarAlbaran()
                .then(function() {
                    console.log('Albarán guardado correctamente');
                })
                .catch(function(err) {
                    notify(err.message || 'Error guardando albarán', 'danger');
                    console.error('Error guardando:', err);
                });
        });
    }

    // Loaders
    async function cargarTiposIVA() {
        var json = await apiGet('/tiposiva.php');
        tiposIVA = {};
        (json.data || []).forEach(function(t) { tiposIVA[t.Codigo] = t; });
    }

    function resolverTerritorio(t) {
        if (t.Tipo_Territorio) {
            return t.Tipo_Territorio === 'PENINSULA' ? 'PENINSULAR' : t.Tipo_Territorio;
        }
        const c = t.Codigo || '';
        if (c.startsWith('IG') || c.startsWith('I1')) return 'CANARIAS';
        if (c.startsWith('IP')) return 'CEUTA_MELILLA';
        if (c === 'ISP' || c === 'NSJ' || c === 'EX0') return 'ESPECIAL';
        return 'PENINSULAR';
    }

    function buildIvaOptions(ivaSeleccionado) {
        const tipos = Object.values(tiposIVA).filter(t =>
            t.Activo !== 'N' &&
            (!territorioActual || resolverTerritorio(t) === territorioActual)
        );
        return tipos.map(t =>
            `<option value="${escapeHtml(t.Codigo)}" ${ivaSeleccionado === t.Codigo ? 'selected' : ''}>` +
            `${escapeHtml(t.Descripcion || t.Codigo)} (${t.IVA}%)</option>`
        ).join('');
    }

    function defaultIvaTerritorio() {
        if (!territorioActual) return null;
        return Object.values(tiposIVA).find(t =>
            resolverTerritorio(t) === territorioActual && t.Activo !== 'N'
        )?.Codigo || null;
    }

    window.cambiarTerritorio = function(valor) {
        territorioActual = valor;
        if (territorioActual) {
            lineas.forEach(linea => {
                if (resolverTerritorio(tiposIVA[linea.tipoIVA] || {}) !== territorioActual) {
                    linea.tipoIVA = defaultIvaTerritorio() || linea.tipoIVA;
                }
            });
        }
        renderLineas();
        recalcular();
    };

    async function cargarCanales() {
        var json = await apiGet('/canales.php');
        var select = document.getElementById('selectCanal');
        if (!select) return;
        select.innerHTML = '';
        (json.data || []).forEach(function(c) {
            var opt = document.createElement('option');
            opt.value = c.Codigo;
            opt.textContent = c.Descripcion;
            select.appendChild(opt);
        });
    }

    async function cargarFormasPago() {
        const json = await apiGet('/formas_pago.php');
        const select = document.getElementById('selectFormaPago');
        if (!select) return;
        select.innerHTML = '<option value="">Seleccionar...</option>';
        (json.data || []).forEach(fp => {
            const opt = document.createElement('option');
            opt.value = fp.Id_Forma_Pago;
            opt.textContent = fp.Id_Forma_Pago;
            select.appendChild(opt);
        });
    }

    // Cargar albarán existente
    async function cargarAlbaran(codigo) {
        var json = await apiGet('/albaranes.php/' + encodeURIComponent(codigo));
        var f = json.data;

        if (!f) {
            notify('Albarán no encontrado', 'danger');
            return;
        }

        // Canal y fecha
        document.getElementById('selectCanal').value = f.Id_Canal || '';
        document.getElementById('numeroDocumento').value = f.Codigo || '';
        var fechaStr = (typeof f.Fecha === 'object' && f.Fecha !== null) ? f.Fecha.date : (f.Fecha || '');
        document.getElementById('inputFecha').value = fechaStr.substring(0, 10);

        // Cliente
        if (f.Id_Cliente) {
            await window.seleccionarClienteReal(f.Id_Cliente);
        }

        // Descuentos
        document.getElementById('inputDtoEspecial').value  = f.Descuento_Especial  || 0;
        document.getElementById('inputDtoComercial').value = f.Descuento_Comercial || 0;
        document.getElementById('inputDtoPP').value        = f.Descuento_PP        || 0;

        // Observaciones
        document.getElementById('inputObservaciones').value = f.Observaciones || '';

        // Forma de pago
        document.getElementById('selectFormaPago').value = (f.Id_Forma_Pago || '').toUpperCase();

        // Líneas existentes
        if (Array.isArray(f.lineas)) {
            lineas = f.lineas.map(function(l) {
                return {
                    idArticulo:  l.Id_Articulo,
                    descripcion: l.Descripcion,
                    cantidad:    parseFloat(l.Cantidad)  || 0,
                    precio:      parseFloat(l.Precio)    || 0,
                    descuento:   parseFloat(l.Descuento) || 0,
                    tipoIVA:     l.Id_Tipo_IVA
                };
            });
        }
    }

    // Cliente Modal
    window.seleccionarCliente = function() {
        var modalEl = document.getElementById('clienteModal');
        var input   = document.getElementById('buscarCliente');
        if (!modalEl) return;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        if (input) { input.value = ''; input.focus(); }
        buscarClientes('');
    };

    async function buscarClientes(query) {
        var tbody = document.getElementById('clientesBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="4" class="text-muted text-center">Cargando...</td></tr>';
        try {
            var url = query
                ? '/clientes.php/search?q=' + encodeURIComponent(query)
                : '/clientes.php?limit=20';
            var json = await apiGet(url);
            var rows = (json.data || []).map(function(c) {
                return '<tr style="cursor:pointer" onclick="seleccionarClienteReal(\'' + escapeHtml(c.Codigo) + '\')">' +
                    '<td>' + escapeHtml(c.Codigo) + '</td>' +
                    '<td>' + escapeHtml(c.NIF || '-') + '</td>' +
                    '<td>' + escapeHtml(c.Razon_Social || c.Nombre || '-') + '</td>' +
                    '<td>' + escapeHtml(c.Id_Forma_Pago || '-') + '</td>' +
                    '</tr>';
            }).join('');
            tbody.innerHTML = rows || '<tr><td colspan="4" class="text-muted text-center">No hay clientes</td></tr>';
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-danger text-center">' + escapeHtml(e.message) + '</td></tr>';
        }
    }

    window.seleccionarClienteReal = async function(id) {
        try {
            var json = await apiGet('/clientes.php/' + encodeURIComponent(id));
            clienteActual = json.data;
            tarifaActual  = parseInt(clienteActual?.Tarifa) || 1;
            rePorcentaje  = parseFloat(clienteActual?.RE_Porcentaje) || 0;

            document.getElementById('clienteEmpty').style.display = 'none';
            document.getElementById('clienteData').style.display  = '';
            document.getElementById('inputIdCliente').value        = clienteActual.Codigo || '';
            document.getElementById('clienteCodigo').textContent   = clienteActual.Codigo || '';
            document.getElementById('clienteNombre').textContent   = clienteActual.Razon_Social || clienteActual.Nombre || '';
            document.getElementById('clienteNIF').textContent      = clienteActual.NIF      || '';
            document.getElementById('clienteDireccion').textContent = clienteActual.Direccion || '-';
            document.getElementById('clienteFormaPago').textContent = clienteActual.Id_Forma_Pago || '-';
            document.getElementById('clienteTipoIVA').textContent  = clienteActual.Id_Tipo_IVA || '-';
            document.getElementById('clienteAplicaRE').textContent = (clienteActual.Aplica_RE == 1) ? 'Sí' : 'No';

            if (rePorcentaje > 0) {
                document.getElementById('reLabel').style.display   = '';
                document.getElementById('reDisplay').style.display = '';
            } else {
                document.getElementById('reLabel').style.display   = 'none';
                document.getElementById('reDisplay').style.display = 'none';
            }

            var modalEl = document.getElementById('clienteModal');
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            recalcular();
        } catch (e) {
            notify(e.message || 'Error seleccionando cliente', 'danger');
        }
    };

    // Artículo Modal
    window.agregarLinea = function() {
        lineaEditando = lineas.length;
        var modalEl = document.getElementById('articuloModal');
        var input   = document.getElementById('buscarArticulo');
        if (!modalEl) return;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        if (input) { input.value = ''; input.focus(); }
        buscarArticulos('');
    };

    async function buscarArticulos(query) {
        var tbody = document.getElementById('articulosBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center">Cargando...</td></tr>';
        try {
            var url = query
                ? '/articulos.php/search?q=' + encodeURIComponent(query) + '&tarifa=' + encodeURIComponent(tarifaActual)
                : '/articulos.php?page=1&per_page=25&tarifa=' + encodeURIComponent(tarifaActual);
            var json  = await apiGet(url);
            var items = Array.isArray(json.data) ? json.data : (json.data?.items || json.data?.rows || []);
            var rows  = items.map(function(a) {
                var precio = Number(a['Precio_Venta_' + tarifaActual] ?? a.Precio_Venta_1 ?? a.Precio ?? 0);
                return '<tr style="cursor:pointer" onclick="seleccionarArticulo(\'' + escapeHtml(a.Codigo) + '\')">' +
                    '<td>' + escapeHtml(a.Codigo) + '</td>' +
                    '<td>' + escapeHtml(a.Descripcion || '') + '</td>' +
                    '<td>' + escapeHtml(a.Id_Tipo_IVA || '') + '</td>' +
                    '<td>' + formatCurrency(precio) + '</td>' +
                    '</tr>';
            }).join('');
            tbody.innerHTML = rows || '<tr><td colspan="5" class="text-muted text-center">No hay artículos</td></tr>';
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-danger text-center">' + escapeHtml(e.message) + '</td></tr>';
        }
    }

    window.seleccionarArticulo = async function(codigo) {
        try {
            var json     = await apiGet('/articulos.php/' + encodeURIComponent(codigo));
            var articulo = json.data;
            var precio   = Number(articulo['Precio_Venta_' + tarifaActual] ?? articulo.Precio_Venta_1 ?? 0);
            var tipoIVARaw = clienteActual?.Id_Tipo_IVA || articulo.Id_Tipo_IVA || 'GEN';
            var tipoIVA = (territorioActual && resolverTerritorio(tiposIVA[tipoIVARaw] || {}) !== territorioActual)
                ? (defaultIvaTerritorio() || tipoIVARaw)
                : tipoIVARaw;
            var descuento = Number(articulo.Descuento ?? 0);

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

    // ---------- Líneas de factura ----------
    function renderLineas() {
        const tbody = document.getElementById('lineasBody');
        if (!tbody) return;

        if (lineas.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        <i class="bi bi-cart-plus fs-1 d-block mb-2"></i>
                        Añada líneas a la factura
                    </td>
                </tr>`;
            return;
        }

        tbody.innerHTML = lineas.map((linea, index) => {
            const tipo        = tiposIVA[linea.tipoIVA] || { IVA: 21, RE: 0 };
            const importeBruto = linea.cantidad * linea.precio;
            const dto          = importeBruto * ((linea.descuento || 0) / 100);
            const base         = importeBruto - dto;
            const ivaSeleccionado = linea.tipoIVA || clienteActual?.Id_Tipo_IVA || 'GEN';
            const ivaActual    = Number(tiposIVA[ivaSeleccionado]?.IVA ?? 21);
            const IVACalc      = base * (ivaActual / 100);

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
                            name="lineas[${index}][Cantidad]"
                            value="${Number(linea.cantidad) == 0 ? '' : Number(linea.cantidad)}"
                            placeholder="0" min="1" step="1"
                            oninput="actualizarLinea(${index}, 'cantidad', this.value)">
                    </td>
                    <td>
                        <input type="number" class="form-control form-control-sm fact-form-control"
                            name="lineas[${index}][Precio]"
                            value="${Number(linea.precio).toFixed(2) == 0 ? '' : Number(linea.precio).toFixed(2)}"
                            placeholder="0.00" step="0.01"
                            oninput="actualizarLinea(${index}, 'precio', this.value)">
                    </td>
                    <td>
                        <input type="number" class="form-control form-control-sm fact-form-control"
                            name="lineas[${index}][Descuento]"
                            value="${Number(linea.descuento) == 0 ? '' : Number(linea.descuento)}"
                            placeholder="0.00" min="0" max="100" step="0.25"
                            oninput="actualizarLinea(${index}, 'descuento', this.value)">
                    </td>
                    <td>
                        <select class="form-control form-control-sm fact-form-control"
                            id="iva-select-${index}"
                            name="lineas[${index}][Id_Tipo_IVA]"
                            onchange="actualizarLinea(${index}, 'tipoIVA', this.value)">
                            ${buildIvaOptions(linea.tipoIVA)}
                        </select>
                    </td>
                    <td class="text-end"><strong id="base-linea-${index}">${formatCurrency(base)}</strong></td>
                    <td class="text-end"><strong id="iva-linea-${index}">${formatCurrency(IVACalc)}</strong></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-danger fact-btn"
                            onclick="eliminarLinea(${index})" title="Eliminar">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>`;
        }).join('');
    }

    window.actualizarLinea = function(index, campo, valor) {
        if (campo === 'tipoIVA') {
            lineas[index].tipoIVA = valor;
        } else {
            const n = parseFloat(valor);
            lineas[index][campo] = isNaN(n) ? 0 : n;
        }

        const ivaSelect     = document.getElementById(`iva-select-${index}`);
        const tipoIVACodigo = ivaSelect ? ivaSelect.value : (lineas[index].tipoIVA || 'GEN');
        const linea_IVA     = Number(tiposIVA[tipoIVACodigo]?.IVA ?? 21);

        if (campo === 'cantidad' || campo === 'precio' || campo === 'descuento' || campo === 'tipoIVA') {
            const baseImp   = document.getElementById(`base-linea-${index}`);
            const lineaTotal = document.getElementById(`iva-linea-${index}`);

            const linea_cantidad  = Number(lineas[index].cantidad) || 0;
            const linea_precio    = Number(lineas[index].precio)   || 0;
            const linea_descuento = Number(lineas[index].descuento) || 0;

            const importe_bruto   = linea_cantidad * linea_precio;
            const dto             = importe_bruto * (linea_descuento / 100);
            const base_imponible  = importe_bruto - dto;
            const IVA_calculado   = base_imponible * (linea_IVA / 100);

            if (baseImp)    baseImp.textContent    = formatCurrency(base_imponible);
            if (lineaTotal) lineaTotal.textContent = formatCurrency(IVA_calculado);
        }

        // Recalcular IVA de todas las líneas
        lineas.forEach((linea, i) => {
            const ivaSel   = document.getElementById(`iva-select-${i}`);
            const ivaCodigo = ivaSel ? ivaSel.value : (linea.tipoIVA || 'GEN');
            const ivaVal   = Number(tiposIVA[ivaCodigo]?.IVA ?? 21);

            const l_cantidad  = Number(linea.cantidad) || 0;
            const l_precio    = Number(linea.precio)   || 0;
            const l_descuento = Number(linea.descuento) || 0;

            const l_bruto  = l_cantidad * l_precio;
            const l_dto    = l_bruto * (l_descuento / 100);
            const l_base   = l_bruto - l_dto;
            const l_iva    = l_base * (ivaVal / 100);

            const ivaEl = document.getElementById(`iva-linea-${i}`);
            if (ivaEl) ivaEl.textContent = formatCurrency(l_iva);
        });

        recalcular();
    };

    window.eliminarLinea = function(index) {
        lineas.splice(index, 1);
        renderLineas();
        recalcular();
    };

    // Totales
    function recalcular() {
        if (!lineas.length) {
            setTotals(0, 0, 0, 0, 0);
            document.getElementById('descuentosDisplay').style.display = 'none';
            document.getElementById('erroresPanel').style.display      = 'none';
            return;
        }

        var lineasParaCalculo = lineas.map(function(l) {
            var t = tiposIVA[l.tipoIVA] || {};
            return {
                cantidad:  Number(l.cantidad)  || 0,
                precio:    Number(l.precio)    || 0,
                descuento: Number(l.descuento) || 0,
                tipoIVA:   { iva: Number(t.IVA ?? 21), re: Number(t.RE ?? 0) }
            };
        });

        var descuentos = {
            especial:  parseFloat(document.getElementById('inputDtoEspecial').value)  || 0,
            comercial: parseFloat(document.getElementById('inputDtoComercial').value) || 0,
            pp:        parseFloat(document.getElementById('inputDtoPP').value)        || 0
        };

        var r = FacturacionCalculos.calcularFactura(lineasParaCalculo, descuentos, rePorcentaje);

        document.getElementById('subtotalDisplay').textContent      = formatCurrency(r.subtotal);
        document.getElementById('baseImponibleDisplay').textContent = formatCurrency(r.baseImponible);
        document.getElementById('ivaDisplay').textContent           = formatCurrency(r.importeIVA);
        document.getElementById('reValue').textContent              = formatCurrency(r.importeRE);
        document.getElementById('totalDisplay').textContent         = formatCurrency(r.total);

        var totalDescuentos = (r.descuentos?.importeDtoLineas    || 0)
                            + (r.descuentos?.importeDtoEspecial  || 0)
                            + (r.descuentos?.importeDtoComercial || 0)
                            + (r.descuentos?.importeDtoPP        || 0);

        if (totalDescuentos > 0) {
            document.getElementById('descuentosDisplay').style.display = '';
            document.getElementById('descuentosValue').textContent     = formatCurrency(totalDescuentos);
        } else {
            document.getElementById('descuentosDisplay').style.display = 'none';
        }

        if (r.errores?.length) {
            document.getElementById('erroresPanel').style.display = '';
            document.getElementById('erroresList').innerHTML = r.errores.map(function(e) {
                return '<li>' + escapeHtml(e) + '</li>';
            }).join('');
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

    async function guardarAlbaran() {
        if (!document.getElementById('inputIdCliente').value) {
            notify('Debe seleccionar un cliente', 'warning');
            return;
        }
        if (!lineas.length) {
            notify('Debe añadir al menos una línea', 'warning');
            return;
        }

        const codigoEdicion = document.querySelector('input[name="codigo"]')?.value?.trim();

        const formData = {
            Id_Canal:           document.getElementById('selectCanal').value,
            Fecha:              document.getElementById('inputFecha').value,
            Id_Cliente:         document.getElementById('inputIdCliente').value,
            Observaciones:      document.querySelector('textarea[name="Observaciones"]')?.value || '',
            Descuento_Especial: parseFloat(document.getElementById('inputDtoEspecial').value)  || 0,
            Descuento_PP:       parseFloat(document.getElementById('inputDtoPP').value)        || 0,
            Descuento_Comercial:parseFloat(document.getElementById('inputDtoComercial').value) || 0,
            Id_Forma_Pago:      (document.getElementById('selectFormaPago')?.value || '').trim() ||
                                (clienteActual?.Id_Forma_Pago || ''),
            lineas: lineas.map(l => ({
                Id_Articulo: l.idArticulo,
                Descripcion: l.descripcion,
                Cantidad:    l.cantidad,
                Precio:      l.precio,
                Descuento:   l.descuento,
                Id_Tipo_IVA: l.tipoIVA
            }))
        };

        const path   = codigoEdicion ? `/albaranes.php/${encodeURIComponent(codigoEdicion)}` : `/albaranes.php`;
        const method = codigoEdicion ? 'PUT' : 'POST';

        const json = await apiSend(path, formData, method);
        notify('Albarán guardado correctamente', 'success');

        setTimeout(() => {
            window.location.href = BASE + '/src/views/albaranes/listado.php';
        }, 800);

        return json;
    }

})();
