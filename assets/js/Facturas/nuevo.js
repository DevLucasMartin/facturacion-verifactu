/**
 * Nueva Factura / Rectificativa
 * assets/js/Facturas/nuevo.js
 */

(() => {
    'use strict';

    const BASE = (() => {
        const p = window.location.pathname;
        const idx = p.indexOf('/SistemaGestionFacturas/');
        return idx >= 0 ? p.substring(0, idx) + '/SistemaGestionFacturas' : '/SistemaGestionFacturas';
    })();

    const API = BASE + '/src/api';
    const { escapeHtml, debounce, formatCurrency } = FormUtils;
    const { apiGet, apiSend } = FormUtils.makeApiClient(API);

    // Detectar parámetro ?rectifica= en la URL
    const urlParams       = new URLSearchParams(window.location.search);
    const RECTIFICA_CODE  = urlParams.get('rectifica') || '';

    // Estado
    let lineas             = [];
    let clienteActual      = null;
    let tarifaActual       = 1;
    let rePorcentaje       = 0;
    let claveRegimenGlobal = '01';
    let tiposIVA           = {};
    let territorioActual   = 'PENINSULAR';
    let lineaEditando      = null;

    // ---------- Notify ----------
    function notify(msg, type = 'warning') {
        const panel = document.getElementById('erroresPanel');
        const list  = document.getElementById('erroresList');
        if (!panel || !list) { App.notify(msg, type); return; }
        panel.className = 'alert mt-3 alert-' + type;
        panel.style.display = 'block';
        list.innerHTML = `<li>${escapeHtml(msg)}</li>`;
        setTimeout(() => { if (panel?.isConnected) panel.style.display = 'none'; }, 6000);
    }

    // ---------- Init ----------
    document.addEventListener('DOMContentLoaded', async () => {
        try {
            await Promise.all([
                cargarTiposIVA(),
                cargarCanales(),
                cargarFormasPago(),
                cargarTarifas(),
            ]);
            inicializarEventos();

            if (RECTIFICA_CODE) {
                await cargarDatosRectificativa(RECTIFICA_CODE);
            }

            renderLineas();
            recalcular();
        } catch (e) {
            notify(e.message || 'Error inicializando', 'danger');
            console.error(e);
        }
    });

    // ---------- Carga inicial ----------
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
            opt.value       = c.Codigo;
            opt.textContent = c.Descripcion ? `${c.Codigo} – ${c.Descripcion}` : c.Codigo;
            select.appendChild(opt);
        });
    }

    async function cargarFormasPago() {
        const json = await apiGet('/formas_pago.php');
        document.querySelectorAll('.select-forma-pago').forEach(select => {
            select.innerHTML = '<option value="">Seleccionar...</option>';
            (json.data || []).forEach(fp => {
                const opt = document.createElement('option');
                opt.value       = fp.Id_Forma_Pago;
                opt.textContent = fp.Descripcion ? `${fp.Id_Forma_Pago} – ${fp.Descripcion}` : fp.Id_Forma_Pago;
                select.appendChild(opt);
            });
        });
    }

    async function cargarTarifas() {
        const json = await apiGet('/tarifas.php');
        document.querySelectorAll('.select-tarifa').forEach(select => {
            select.innerHTML = '';
            (json.data || []).forEach(t => {
                const opt = document.createElement('option');
                opt.value       = t.Tarifa;
                opt.textContent = 'Tarifa ' + t.Tarifa;
                select.appendChild(opt);
            });
        });
    }

    // ---------- Helpers IVA / Calificación ----------
    function resolverTerritorio(t) {
        if (t.Tipo_Territorio) {
            return t.Tipo_Territorio === 'PENINSULA' ? 'PENINSULAR' : t.Tipo_Territorio;
        }
        const c = t.Codigo || '';
        if (c.startsWith('IG') || c.startsWith('I1')) return 'CANARIAS';
        if (c.startsWith('IP')) return 'CEUTA_MELILLA';
        return 'PENINSULAR';
    }

    function defaultIvaTerritorio() {
        return Object.values(tiposIVA).find(t =>
            resolverTerritorio(t) === territorioActual && t.Activo !== 'N'
        )?.Codigo || null;
    }

    function buildIvaOptions(ivaSeleccionado) {
        const tipos = Object.values(tiposIVA).filter(t =>
            t.Activo !== 'N' &&
            (!territorioActual || resolverTerritorio(t) === territorioActual)
        );
        return tipos.map(t => {
            const label = `${escapeHtml(t.Descripcion || t.Codigo)} (${t.IVA}%)`;
            return `<option value="${escapeHtml(t.Codigo)}" ${ivaSeleccionado === t.Codigo ? 'selected' : ''}>${label}</option>`;
        }).join('');
    }

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

    const CLAVE_REGIMEN_E456 = ['E4', 'E5', 'E6'];

    window.cambiarTerritorio = function (valor) {
        territorioActual = valor;
        lineas.forEach(linea => {
            if (resolverTerritorio(tiposIVA[linea.tipoIVA] || {}) !== territorioActual) {
                linea.tipoIVA = defaultIvaTerritorio() || linea.tipoIVA;
            }
        });
        renderLineas();
        recalcular();
    };

    // ---------- Pre-carga de rectificativa ----------
    async function cargarDatosRectificativa(codigo) {
        // Mostrar campos de rectificación
        const motivoRow = document.getElementById('motivoRectificacionRow');
        if (motivoRow) motivoRow.style.display = '';

        const origenRow = document.getElementById('facturaOrigenRow');
        if (origenRow) origenRow.style.display = '';

        // Marcar radio RECTIFICATIVA si existe (en nuevo.php ya viene checked por PHP)
        const radioRect = document.querySelector('input[name="Tipo_Documento"][value="RECTIFICATIVA"]');
        if (radioRect) radioRect.checked = true;

        // Cargar datos de la factura origen
        const [fResp, lResp] = await Promise.all([
            apiGet(`/facturas.php?action=get&codigo=${encodeURIComponent(codigo)}`),
            apiGet(`/facturas.php?action=lineas&codigo=${encodeURIComponent(codigo)}`),
        ]);

        const f = fResp.data;
        if (f) {
            // Canal igual al original
            const selCanal = document.getElementById('selectCanal');
            if (selCanal && f.canal) selCanal.value = f.canal;

            // Forma de pago del original
            const selFP = document.getElementById('selectFormaPago');
            if (selFP && f.id_forma_pago) selFP.value = f.id_forma_pago;

            // Factura origen
            const selOrigen = document.getElementById('selectFacturaOrigen');
            if (selOrigen) {
                selOrigen.innerHTML = `<option value="${escapeHtml(codigo)}">${escapeHtml(codigo)}</option>`;
                selOrigen.value = codigo;
            }

            // Cargar cliente del original
            if (f.id_cliente) {
                await window.seleccionarClienteReal(f.id_cliente);
            }
        }

        // Cargar líneas del original (negadas para abono)
        if (lResp.success && Array.isArray(lResp.data)) {
            lineas = lResp.data.map(l => ({
                idArticulo:   l.referencia        || '',
                descripcion:  l.descripcion       || '',
                cantidad:     l.cantidad          || 0,
                precio:       l.precio_unitario   || 0,
                descuento:    l.descuento         || 0,
                tipoIVA:      l.tipo_iva          || '',
                calificacion: l.Calificacion      || 'S1',
            }));
        }
    }

    // ---------- Eventos ----------
    function inicializarEventos() {
        // Cambio tipo documento → mostrar/ocultar campos rectificativa
        document.querySelectorAll('input[name="Tipo_Documento"]').forEach(r => {
            r.addEventListener('change', () => {
                const tipo = document.querySelector('input[name="Tipo_Documento"]:checked')?.value;
                const motivoRow = document.getElementById('motivoRectificacionRow');
                const origenRow = document.getElementById('facturaOrigenRow');
                if (motivoRow) motivoRow.style.display = (tipo === 'RECTIFICATIVA') ? '' : 'none';
                if (origenRow) origenRow.style.display = (tipo === 'RECTIFICATIVA') ? '' : 'none';
                recalcular();
            });
        });

        // Descuentos
        ['inputDtoEspecial', 'inputDtoComercial', 'inputDtoPP'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', recalcular);
        });

        // Búsqueda de clientes (en modal)
        const buscarClienteInput = document.getElementById('buscarCliente');
        if (buscarClienteInput) {
            buscarClienteInput.addEventListener('input', debounce(() => {
                buscarClientes(buscarClienteInput.value.trim());
            }, 250));
        }

        // Búsqueda de artículos (en modal)
        const buscarArticuloInput = document.getElementById('buscarArticulo');
        if (buscarArticuloInput) {
            buscarArticuloInput.addEventListener('input', debounce(() => {
                buscarArticulos(buscarArticuloInput.value.trim());
            }, 250));
        }

        // Modal guardar y enviar — confirmar
        document.getElementById('btnConfirmarGuardarEnviar')?.addEventListener('click', () => {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('guardarEnviarModal')).hide();
            guardarFactura(true);
        });

        // Nuevo cliente form submit
        document.getElementById('nuevoClienteForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            await guardarNuevoCliente();
        });

        // Tarifa
        document.getElementById('selectTarifa')?.addEventListener('change', (e) => {
            tarifaActual = parseInt(e.target.value) || 1;
        });

        // Territorio
        document.getElementById('selectTerritorio')?.addEventListener('change', function () {
            window.cambiarTerritorio(this.value);
        });
    }

    // ---------- Modal: seleccionar cliente ----------
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
        tbody.innerHTML = `<tr><td colspan="4" class="text-muted text-center">Cargando...</td></tr>`;
        try {
            const url = query
                ? `/clientes.php/search?q=${encodeURIComponent(query)}`
                : `/clientes.php?limit=20`;
            const json = await apiGet(url);
            const rows = (json.data || []).map(c => `
                <tr style="cursor:pointer" onclick="seleccionarClienteReal('${escapeHtml(c.Codigo)}')">
                    <td>${escapeHtml(c.Codigo || '')}</td>
                    <td>${escapeHtml(c.NIF || '-')}</td>
                    <td>${escapeHtml(c.Archivar_Como || '-')}</td>
                    <td>${escapeHtml(c.Id_Forma_Pago || '-')}</td>
                </tr>`).join('');
            tbody.innerHTML = rows || `<tr><td colspan="4" class="text-muted text-center">Sin resultados</td></tr>`;
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-danger text-center">${escapeHtml(e.message)}</td></tr>`;
        }
    }

    window.seleccionarClienteReal = async function(id) {
        try {
            const json = await apiGet(`/clientes.php/${encodeURIComponent(id)}`);
            clienteActual = json.data;
            tarifaActual  = parseInt(clienteActual?.Tarifa) || 1;
            rePorcentaje  = parseFloat(clienteActual?.RE_Porcentaje) || 0;

            document.getElementById('clienteEmpty').style.display  = 'none';
            document.getElementById('clienteData').style.display   = '';
            document.getElementById('inputIdCliente').value         = clienteActual.Codigo || '';
            document.getElementById('clienteNombre').textContent    = clienteActual.Archivar_Como || '';
            document.getElementById('clienteNIF').textContent       = clienteActual.NIF || '';
            document.getElementById('clienteDireccion').textContent = clienteActual.Direccion || '-';
            document.getElementById('clienteFormaPago').textContent = (clienteActual.Id_Forma_Pago || '-').toUpperCase();
            document.getElementById('clienteTarifa').textContent    = 'Tarifa ' + tarifaActual;
            document.getElementById('clienteTipoIVA').textContent   = clienteActual.Id_Tipo_IVA || '-';
            document.getElementById('clienteAplicaRE').textContent  = (clienteActual.Aplica_RE == 1) ? 'Sí' : 'No';

            // Pre-seleccionar forma de pago del cliente
            const selFP = document.getElementById('selectFormaPago');
            if (selFP && clienteActual.Id_Forma_Pago) selFP.value = clienteActual.Id_Forma_Pago;

            // Pre-seleccionar tarifa del cliente
            const selT = document.getElementById('selectTarifa');
            if (selT) selT.value = tarifaActual;

            if (rePorcentaje > 0) {
                document.getElementById('reRow').style.display     = '';
                document.getElementById('clienteRE').textContent   = rePorcentaje + '%';
                document.getElementById('reDisplay').style.display = '';
            } else {
                document.getElementById('reRow').style.display     = 'none';
                document.getElementById('reDisplay').style.display = 'none';
            }

            // Cargar facturas origen si es rectificativa
            const tipo = document.querySelector('input[name="Tipo_Documento"]:checked')?.value;
            if (tipo === 'RECTIFICATIVA' && !RECTIFICA_CODE) {
                cargarFacturasOrigen(clienteActual.Codigo);
            }

            const modal = document.getElementById('clienteModal');
            if (modal) bootstrap.Modal.getOrCreateInstance(modal).hide();
            recalcular();
        } catch (e) {
            notify(e.message || 'Error seleccionando cliente', 'danger');
        }
    };

    async function cargarFacturasOrigen(idCliente) {
        const select = document.getElementById('selectFacturaOrigen');
        if (!select) return;
        try {
            const json = await apiGet(`/facturas.php?action=origen&cliente=${encodeURIComponent(idCliente)}`);
            select.innerHTML = '<option value="">Seleccionar factura origen...</option>';
            (json.data || []).forEach(f => {
                const opt = document.createElement('option');
                opt.value       = f.Codigo;
                opt.textContent = `${f.Codigo} — ${f.Fecha ? f.Fecha.substring(0, 10) : ''}`;
                select.appendChild(opt);
            });
        } catch (_) { /* silencioso */ }
    }

    // ---------- Modal: nuevo cliente ----------
    window.abrirNuevoCliente = function() {
        const modalEl = document.getElementById('nuevoClienteModal');
        if (!modalEl) return;
        document.getElementById('nuevoClienteForm')?.reset();
        document.getElementById('nuevoClienteMsg')?.classList.add('d-none');
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    };

    async function guardarNuevoCliente() {
        const nombre = document.getElementById('ncNombre')?.value?.trim();
        const nif    = document.getElementById('ncNif')?.value?.trim();
        const msg    = document.getElementById('nuevoClienteMsg');

        if (!nombre) { notify('El nombre es obligatorio', 'warning'); return; }
        if (!nif)    { notify('El NIF es obligatorio',    'warning'); return; }

        const payload = {
            Nombre:      nombre,
            Apellidos:   document.getElementById('ncApellidos')?.value?.trim()   || '',
            Organizacion:document.getElementById('ncOrganizacion')?.value?.trim() || '',
            Archivar_Como:document.getElementById('ncArchivar')?.value?.trim()   || '',
            NIF:         nif,
            Direccion:   document.getElementById('ncDireccion')?.value?.trim()   || '',
            Poblacion:   document.getElementById('ncPoblacion')?.value?.trim()   || '',
            Id_Forma_Pago: document.getElementById('selectFormaPagoNuevo')?.value || '',
            Tarifa:      parseInt(document.getElementById('selectTarifaNuevo')?.value) || 1,
            RE_Porcentaje: parseFloat(document.getElementById('ncRE')?.value)    || 0,
        };

        try {
            const data = await apiSend('/clientes.php', payload, 'POST');
            if (data.success) {
                if (msg) { msg.classList.remove('d-none', 'alert-danger'); msg.classList.add('alert-success'); msg.textContent = 'Cliente creado.'; }
                setTimeout(async () => {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('nuevoClienteModal')).hide();
                    await window.seleccionarClienteReal(data.data?.Codigo || data.data?.id_cliente);
                }, 600);
            } else {
                if (msg) { msg.classList.remove('d-none', 'alert-success'); msg.classList.add('alert-danger'); msg.textContent = data.message || 'Error al crear cliente'; }
            }
        } catch (e) {
            notify(e.message || 'Error de conexión', 'danger');
        }
    }

    window.guardarClienteDemo = function() {
        notify('Funcionalidad de guardado de cliente.', 'info');
    };

    // ---------- Modal: artículos ----------
    window.agregarLinea = function() {
        lineaEditando = null;
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
            const items = Array.isArray(json.data) ? json.data : (json.data?.items || []);
            const rows  = items.map(a => {
                const precio = Number(a['Precio_Venta_' + tarifaActual] ?? a.Precio_Venta_1 ?? a.Precio ?? 0);
                return `
                <tr style="cursor:pointer" onclick="seleccionarArticulo('${escapeHtml(a.Codigo)}')">
                    <td>${escapeHtml(a.Codigo || '')}</td>
                    <td>${escapeHtml(a.Descripcion || '')}</td>
                    <td>${escapeHtml(a.Id_Tipo_IVA || '')}</td>
                    <td>${formatCurrency(precio)}</td>
                    <td><button type="button" class="btn btn-sm btn-primary"><i class="bi bi-check"></i></button></td>
                </tr>`;
            }).join('');
            tbody.innerHTML = rows || `<tr><td colspan="5" class="text-muted text-center">Sin artículos</td></tr>`;
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-danger text-center">${escapeHtml(e.message)}</td></tr>`;
        }
    }

    window.seleccionarArticulo = async function(codigo) {
        try {
            const json     = await apiGet(`/articulos.php/${encodeURIComponent(codigo)}`);
            const articulo = json.data;
            const precio   = Number(articulo['Precio_Venta_' + tarifaActual] ?? articulo.Precio_Venta_1 ?? 0);
            const tipoIVA  = articulo.Id_Tipo_IVA || Object.keys(tiposIVA)[0] || '';
            const descuento = Number(articulo.Descuento ?? 0);

            const nuevaLinea = {
                idArticulo:  articulo.Codigo,
                descripcion: articulo.Descripcion || articulo.Codigo,
                cantidad:    1,
                precio,
                descuento,
                tipoIVA,
                calificacion: 'S1',
            };

            if (lineaEditando !== null && lineaEditando < lineas.length) {
                lineas[lineaEditando] = {
                    ...lineas[lineaEditando],
                    ...nuevaLinea,
                    calificacion: lineas[lineaEditando].calificacion || 'S1',
                };
            } else {
                lineas.push(nuevaLinea);
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

        if (!lineas.length) {
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
                        name="lineas[${index}][Cantidad]"
                        value="${Number(linea.cantidad) === 0 ? '' : Number(linea.cantidad)}"
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
                        value="${Number(linea.descuento) === 0 ? '' : Number(linea.descuento)}"
                        placeholder="0.00" min="0" max="100" step="0.25"
                        oninput="actualizarLinea(${index}, 'descuento', this.value)">
                </td>
                <td class="${esExentoONoSujeto(linea.calificacion) ? 'iva-exento-cell' : ''}">
                    <select class="form-control form-control-sm fact-form-control"
                        id="iva-select-${index}" name="lineas[${index}][Id_Tipo_IVA]"
                        onchange="actualizarLinea(${index}, 'tipoIVA', this.value)"
                        style="${esExentoONoSujeto(linea.calificacion) ? 'display:none' : ''}">
                        ${buildIvaOptions(linea.tipoIVA)}
                    </select>
                    <span id="iva-text-${index}" class="text-muted fw-bold" style="${esExentoONoSujeto(linea.calificacion) ? '' : 'display:none'}">${textoIvaExento(linea.calificacion)}</span>
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
                <td class="text-center">
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

    window.actualizarLinea = function (index, campo, valor) {
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

        // Actualizar base e IVA de la línea en tiempo real
        const linea           = lineas[index];
        const tipo            = tiposIVA[linea.tipoIVA] || {};
        const importe_bruto   = Number(linea.cantidad) * Number(linea.precio);
        const base_imponible  = importe_bruto * (1 - (Number(linea.descuento) || 0) / 100);
        const ivaPct          = Number(tipo.IVA ?? 21);
        const sinIVA          = esExentoONoSujeto(linea.calificacion);
        const IVA_calculado   = (linea.calificacion === 'S2' || sinIVA) ? 0 : base_imponible * (ivaPct / 100);

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
            ['subtotalDisplay','baseImponibleDisplay','ivaDisplay','reValue','totalDisplay'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = formatCurrency(0);
            });
            const dd = document.getElementById('descuentosDisplay');
            if (dd) dd.style.display = 'none';
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
                    iva:    l.calificacion === 'S2' ? 0 : Number(t.IVA ?? 0),
                    re:     0,
                },
            };
        });

        const descuentos = {
            especial:      parseFloat(document.getElementById('inputDtoEspecial')?.value)  || 0,
            comercial:     parseFloat(document.getElementById('inputDtoComercial')?.value) || 0,
            pp:            parseFloat(document.getElementById('inputDtoPP')?.value)        || 0,
            tipoDocumento: document.querySelector('input[name="Tipo_Documento"]:checked')?.value,
        };

        const r = FacturacionCalculos.calcularFactura(lineasParaCalculo, descuentos, rePorcentaje);

        document.getElementById('subtotalDisplay').textContent       = formatCurrency(r.subtotal);
        document.getElementById('baseImponibleDisplay').textContent  = formatCurrency(r.baseImponible);
        document.getElementById('ivaDisplay').textContent            = formatCurrency(r.importeIVA);
        document.getElementById('reValue').textContent               = formatCurrency(r.importeRE);
        document.getElementById('totalDisplay').textContent          = formatCurrency(r.total);

        const totalDto = (r.descuentos?.importeDtoEspecial  || 0)
                       + (r.descuentos?.importeDtoComercial || 0)
                       + (r.descuentos?.importeDtoPP        || 0);

        const ddisplay = document.getElementById('descuentosDisplay');
        const dvalue   = document.getElementById('descuentosValue');
        if (totalDto > 0) {
            if (ddisplay) ddisplay.style.display = '';
            if (dvalue)   dvalue.textContent = '-' + formatCurrency(totalDto);
        } else {
            if (ddisplay) ddisplay.style.display = 'none';
        }

        if (r.errores?.length) {
            const panel = document.getElementById('erroresPanel');
            const list  = document.getElementById('erroresList');
            if (panel) panel.style.display = '';
            if (list)  list.innerHTML = r.errores.map(e => `<li>${escapeHtml(e)}</li>`).join('');
        }
    }

    // ---------- Guardar factura ----------
    function buildPayload(enviarVerifactu = false) {
        if (!document.getElementById('inputIdCliente')?.value) {
            notify('Selecciona un cliente', 'warning'); return null;
        }
        if (!lineas.length) {
            notify('Añade al menos una línea', 'warning'); return null;
        }

        const idFormaPago = document.getElementById('selectFormaPago')?.value;
        if (!idFormaPago) {
            notify('Selecciona una forma de pago', 'warning'); return null;
        }

        const tipo    = document.querySelector('input[name="Tipo_Documento"]:checked')?.value || 'FACTURA';
        const payload = {
            Id_Canal:            document.getElementById('selectCanal')?.value      || '',
            Fecha:               document.getElementById('inputFecha')?.value       || '',
            Id_Cliente:          document.getElementById('inputIdCliente')?.value   || '',
            Id_Forma_Pago:       idFormaPago,
            Tipo_Documento:      tipo,
            Observaciones:       document.getElementById('textareaObservaciones')?.value || '',
            Descuento_Especial:  parseFloat(document.getElementById('inputDtoEspecial')?.value)  || 0,
            Descuento_PP:        parseFloat(document.getElementById('inputDtoPP')?.value)        || 0,
            Descuento_Comercial: parseFloat(document.getElementById('inputDtoComercial')?.value) || 0,
            enviar_verifactu:    enviarVerifactu,
            lineas: lineas.map(l => ({
                Id_Articulo:   l.idArticulo,
                Descripcion:   l.descripcion,
                Cantidad:      l.cantidad,
                Precio:        l.precio,
                Descuento:     l.descuento,
                Id_Tipo_IVA:   l.tipoIVA,
                Calificacion:  l.calificacion || 'S1',
                Clave_Regimen: CLAVE_REGIMEN_E456.includes(l.calificacion) ? claveRegimenGlobal : '01',
            })),
        };

        if (tipo === 'RECTIFICATIVA') {
            payload.Id_Factura_Origen            = document.getElementById('selectFacturaOrigen')?.value || RECTIFICA_CODE;
            payload.Motivo_Rectificacion         = document.getElementById('selectMotivo')?.value;
            payload.Tipo_Rectificativa_Verifactu = document.getElementById('selectTipoRectificativa')?.value;
            payload.Subtipo_Rectificativa        = document.getElementById('selectSubtipoRectificativa')?.value;

            if (!payload.Id_Factura_Origen || !payload.Motivo_Rectificacion) {
                notify('Selecciona factura origen y motivo de rectificación', 'warning'); return null;
            }
        }

        return payload;
    }

    async function guardarFactura(enviarVerifactu = false) {
        const payload = buildPayload(enviarVerifactu);
        if (!payload) return;

        App.showLoading();
        try {
            const data = await apiSend('/facturas.php', payload, 'POST');
            if (data.success) {
                App.notify('Factura creada: ' + (data.data?.codigo || ''), 'success');
                setTimeout(() => {
                    window.location.href = BASE + '/src/views/facturas/ver.php?codigo='
                        + encodeURIComponent(data.data?.codigo || '');
                }, 800);
            } else {
                notify(data.message || 'Error al guardar', 'danger');
            }
        } catch (e) {
            notify(e.message || 'Error de conexión', 'danger');
        } finally {
            App.hideLoading();
        }
    }

    // ---------- Modal guardar y enviar ----------
    window.abrirModalGuardarEnviar = function() {
        const payload = buildPayload(false);
        if (!payload) return;   // Validación previa
        const modalEl = document.getElementById('guardarEnviarModal');
        if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
    };

})();
