/**
 * Nueva Factura
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

    // ---------- Estado ----------
    let lineas        = [];
    let clienteActual = null;
    let tarifaActual  = 1;
    let rePorcentaje  = 0;
    let tiposIVA      = {};
    let lineaEditando = null;

    // ---------- Helpers de notificación ----------
    function notify(msg, type = 'warning') {
        const panel = document.getElementById('erroresPanel');
        const list  = document.getElementById('erroresList');
        if (!panel || !list) { App.notify(msg, type); return; }
        panel.className = 'alert mt-3 alert-' + type;
        panel.style.display = 'block';
        list.innerHTML = `<li>${escapeHtml(msg)}</li>`;
        setTimeout(() => { if (panel?.isConnected) panel.style.display = 'none'; }, 5000);
    }

    // ---------- Init ----------
    document.addEventListener('DOMContentLoaded', async () => {
        try {
            await cargarTiposIVA();
            await cargarCanales();
            inicializarEventos();
            renderLineas();
            recalcular();
            mostrarBotonGuardarCliente(false);
        } catch (e) {
            notify(e.message || 'Error inicializando', 'danger');
            console.error(e);
        }
    });

    // ---------- Carga inicial ----------
    async function cargarTiposIVA() {
        const data = await apiGet('/tipos-iva.php?action=list');
        if (!data.success) return;
        tiposIVA = {};
        (data.data || []).forEach(t => { tiposIVA[t.codigo] = t; });

        const sel = document.getElementById('lineaTipoIVA');
        if (!sel) return;
        sel.innerHTML = '';
        Object.values(tiposIVA).forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.codigo;
            opt.textContent = `${t.codigo} (${t.iva}%)`;
            sel.appendChild(opt);
        });
    }

    async function cargarCanales() {
        const data = await apiGet('/canales.php?action=list');
        if (!data.success) return;
        const sel = document.getElementById('canal');
        if (!sel) return;
        sel.innerHTML = '<option value="">Sin canal</option>';
        (data.data || []).forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id_canal;
            opt.textContent = escapeHtml(c.nombre);
            sel.appendChild(opt);
        });
    }

    // ---------- Búsqueda de clientes ----------
    function inicializarBusquedaCliente() {
        const input = document.getElementById('clienteBusqueda');
        if (!input) return;

        const buscarDebounced = debounce(async (q) => {
            if (q.length < 2) return;
            const data = await apiGet('/clientes.php?action=search&q=' + encodeURIComponent(q));
            if (data.success) renderResultadosCliente(data.data || []);
        }, 300);

        input.addEventListener('input', () => buscarDebounced(input.value.trim()));
    }

    function renderResultadosCliente(clientes) {
        const lista = document.getElementById('clienteResultados');
        if (!lista) return;

        if (!clientes.length) {
            lista.innerHTML = '<li class="list-group-item text-muted">Sin resultados</li>';
            lista.style.display = 'block';
            return;
        }

        lista.innerHTML = clientes.map(c => `
            <li class="list-group-item list-group-item-action" style="cursor:pointer;"
                data-id="${c.id_cliente}">
                <strong>${escapeHtml(c.nombre_fiscal || c.nombre)}</strong>
                <small class="text-muted ms-2">${escapeHtml(c.nif || '')}</small>
            </li>`).join('');

        lista.querySelectorAll('li[data-id]').forEach(li => {
            li.addEventListener('click', () => seleccionarCliente(li.dataset.id));
        });

        lista.style.display = 'block';
    }

    async function seleccionarCliente(idCliente) {
        const lista = document.getElementById('clienteResultados');
        if (lista) lista.style.display = 'none';

        const data = await apiGet('/clientes.php?action=get&id=' + encodeURIComponent(idCliente));
        if (!data.success) { notify('Error al cargar cliente', 'danger'); return; }

        clienteActual = data.data;
        rePorcentaje  = parseFloat(clienteActual.porcentaje_re || 0);

        mostrarDatosCliente(clienteActual);
        recalcular();
    }

    function mostrarDatosCliente(c) {
        const panel = document.getElementById('clienteSeleccionado');
        const input = document.getElementById('clienteBusqueda');
        if (input) input.value = c.nombre_fiscal || c.nombre;
        if (!panel) return;

        panel.innerHTML = `
            <div class="alert alert-success py-2 mb-0">
                <strong>${escapeHtml(c.nombre_fiscal || c.nombre)}</strong>
                — NIF: ${escapeHtml(c.nif || '-')}
                ${c.porcentaje_re > 0 ? `<span class="badge bg-warning ms-2">RE ${c.porcentaje_re}%</span>` : ''}
                <button type="button" class="btn-close btn-sm float-end" id="btnQuitarCliente"></button>
            </div>`;

        document.getElementById('id_cliente').value = c.id_cliente;

        document.getElementById('btnQuitarCliente')?.addEventListener('click', () => {
            clienteActual = null;
            rePorcentaje  = 0;
            document.getElementById('id_cliente').value = '';
            if (input) input.value = '';
            panel.innerHTML = '';
            recalcular();
        });
    }

    // ---------- Búsqueda de artículos ----------
    function inicializarBusquedaArticulo() {
        const input = document.getElementById('articuloBusqueda');
        if (!input) return;

        const buscarDebounced = debounce(async (q) => {
            if (q.length < 2) return;
            const tarifa = tarifaActual;
            const data = await apiGet(`/articulos.php?action=search&q=${encodeURIComponent(q)}&tarifa=${tarifa}`);
            if (data.success) renderResultadosArticulo(data.data || []);
        }, 300);

        input.addEventListener('input', () => buscarDebounced(input.value.trim()));
    }

    function renderResultadosArticulo(articulos) {
        const lista = document.getElementById('articuloResultados');
        if (!lista) return;

        if (!articulos.length) {
            lista.innerHTML = '<li class="list-group-item text-muted">Sin resultados</li>';
            lista.style.display = 'block';
            return;
        }

        lista.innerHTML = articulos.map(a => `
            <li class="list-group-item list-group-item-action" style="cursor:pointer;"
                data-id="${a.id_articulo}"
                data-precio="${a.precio || 0}"
                data-iva="${a.tipo_iva || 'GEN'}"
                data-desc="${escapeHtml(a.descripcion || a.referencia)}">
                <strong>${escapeHtml(a.referencia || '')}</strong>
                <span class="ms-2">${escapeHtml(a.descripcion || '')}</span>
                <span class="float-end text-primary">${formatCurrency(a.precio || 0)}</span>
            </li>`).join('');

        lista.querySelectorAll('li[data-id]').forEach(li => {
            li.addEventListener('click', () => {
                document.getElementById('lineaIdArticulo').value  = li.dataset.id;
                document.getElementById('lineaDescripcion').value = li.dataset.desc;
                document.getElementById('lineaPrecio').value      = li.dataset.precio;
                const sel = document.getElementById('lineaTipoIVA');
                if (sel) sel.value = li.dataset.iva;
                lista.style.display = 'none';
                document.getElementById('articuloBusqueda').value = li.dataset.desc;
                actualizarCalculoLinea();
            });
        });

        lista.style.display = 'block';
    }

    // ---------- Líneas ----------
    function actualizarCalculoLinea() {
        const cantidad  = parseFloat(document.getElementById('lineaCantidad')?.value || 0);
        const precio    = parseFloat(document.getElementById('lineaPrecio')?.value || 0);
        const descuento = parseFloat(document.getElementById('lineaDescuento')?.value || 0);
        const codigoIVA = document.getElementById('lineaTipoIVA')?.value || 'GEN';
        const tipoIVA   = tiposIVA[codigoIVA] || { codigo: codigoIVA, iva: 0, re: 0 };

        const resultado = FacturacionCalculos.calcularLinea(cantidad, precio, descuento, tipoIVA, rePorcentaje);

        const totalLinea = document.getElementById('lineaTotal');
        if (totalLinea) totalLinea.textContent = formatCurrency(resultado.total);
    }

    function agregarLinea() {
        const idArticulo  = document.getElementById('lineaIdArticulo')?.value?.trim();
        const descripcion = document.getElementById('lineaDescripcion')?.value?.trim();
        const cantidad    = parseFloat(document.getElementById('lineaCantidad')?.value || 0);
        const precio      = parseFloat(document.getElementById('lineaPrecio')?.value || 0);
        const descuento   = parseFloat(document.getElementById('lineaDescuento')?.value || 0);
        const codigoIVA   = document.getElementById('lineaTipoIVA')?.value || 'GEN';

        if (!idArticulo)  { notify('Selecciona un artículo'); return; }
        if (cantidad <= 0) { notify('La cantidad debe ser mayor que 0'); return; }
        if (precio < 0)    { notify('El precio no puede ser negativo'); return; }

        const tipoIVA = tiposIVA[codigoIVA] || { codigo: codigoIVA, iva: 0, re: 0 };

        const linea = {
            id: lineaEditando ?? Date.now(),
            idArticulo,
            descripcion: descripcion || idArticulo,
            cantidad,
            precio,
            descuento,
            tipoIVA
        };

        if (lineaEditando !== null) {
            const idx = lineas.findIndex(l => l.id === lineaEditando);
            if (idx >= 0) lineas[idx] = linea;
            lineaEditando = null;
        } else {
            lineas.push(linea);
        }

        limpiarFormLinea();
        renderLineas();
        recalcular();
    }

    function editarLinea(id) {
        const linea = lineas.find(l => l.id === id);
        if (!linea) return;

        lineaEditando = id;
        document.getElementById('lineaIdArticulo').value  = linea.idArticulo;
        document.getElementById('lineaDescripcion').value = linea.descripcion;
        document.getElementById('articuloBusqueda').value = linea.descripcion;
        document.getElementById('lineaCantidad').value    = linea.cantidad;
        document.getElementById('lineaPrecio').value      = linea.precio;
        document.getElementById('lineaDescuento').value   = linea.descuento;
        const sel = document.getElementById('lineaTipoIVA');
        if (sel) sel.value = linea.tipoIVA?.codigo || 'GEN';
        actualizarCalculoLinea();

        document.getElementById('lineaDescripcion')?.scrollIntoView({ behavior: 'smooth' });
    }

    function eliminarLinea(id) {
        lineas = lineas.filter(l => l.id !== id);
        if (lineaEditando === id) { lineaEditando = null; limpiarFormLinea(); }
        renderLineas();
        recalcular();
    }

    function limpiarFormLinea() {
        ['lineaIdArticulo','lineaDescripcion','articuloBusqueda'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        const cant = document.getElementById('lineaCantidad');
        if (cant) cant.value = '1';
        const prec = document.getElementById('lineaPrecio');
        if (prec) prec.value = '0';
        const desc = document.getElementById('lineaDescuento');
        if (desc) desc.value = '0';
        const total = document.getElementById('lineaTotal');
        if (total) total.textContent = formatCurrency(0);
        lineaEditando = null;
    }

    function renderLineas() {
        const tbody = document.getElementById('lineasBody');
        if (!tbody) return;

        if (!lineas.length) {
            tbody.innerHTML = `
                <tr id="lineasVacio">
                    <td colspan="8" class="text-center text-muted py-3">
                        <i class="bi bi-inbox me-1"></i>Sin líneas. Añade artículos arriba.
                    </td>
                </tr>`;
            return;
        }

        tbody.innerHTML = lineas.map((l, i) => {
            const resultado = FacturacionCalculos.calcularLinea(
                l.cantidad, l.precio, l.descuento, l.tipoIVA, rePorcentaje
            );
            return `<tr>
                <td class="text-muted">${i + 1}</td>
                <td>${escapeHtml(l.descripcion)}</td>
                <td class="text-end">${l.cantidad.toFixed(2)}</td>
                <td class="text-end">${formatCurrency(l.precio)}</td>
                <td class="text-end">${l.descuento > 0 ? l.descuento.toFixed(2) + '%' : '-'}</td>
                <td class="text-end">${l.tipoIVA?.iva ?? 0}%</td>
                <td class="text-end fw-semibold">${formatCurrency(resultado.total)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-xs btn-outline-secondary btn-sm me-1"
                        onclick="editarLineaFn(${l.id})" title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-danger btn-sm"
                        onclick="eliminarLineaFn(${l.id})" title="Eliminar">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>`;
        }).join('');
    }

    // Exponer para onclick inline
    window.editarLineaFn   = editarLinea;
    window.eliminarLineaFn = eliminarLinea;

    // ---------- Totales ----------
    function recalcular() {
        const descuentos = {
            especial:       parseFloat(document.getElementById('dtoEspecial')?.value  || 0),
            comercial:      parseFloat(document.getElementById('dtoComercial')?.value || 0),
            pp:             parseFloat(document.getElementById('dtoPP')?.value        || 0),
            tipoDocumento:  document.getElementById('tipo_documento')?.value || 'FACTURA'
        };

        const resultado = FacturacionCalculos.calcularFactura(lineas, descuentos, rePorcentaje);
        renderTotales(resultado);
    }

    function renderTotales(r) {
        const set = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = formatCurrency(val);
        };

        set('totalSubtotal',    r.subtotal);
        set('totalDtoLineas',   r.descuentos.importeDtoLineas);
        set('totalDtoEspecial', r.descuentos.importeDtoEspecial);
        set('totalDtoComercial',r.descuentos.importeDtoComercial);
        set('totalDtoPP',       r.descuentos.importeDtoPP);
        set('totalBase',        r.baseImponible);
        set('totalIVA',         r.importeIVA);
        set('totalRE',          r.importeRE);
        set('totalFactura',     r.total);

        // Desglose por tipos de IVA
        const desglose = document.getElementById('desgloseIVA');
        if (desglose && r.cuotasIVA.length) {
            desglose.innerHTML = r.cuotasIVA.map(c => `
                <tr>
                    <td class="text-muted small">IVA ${c.porcentajeIVA}% (base ${formatCurrency(c.baseImponible)})</td>
                    <td class="text-end small">${formatCurrency(c.cuotaIVA)}</td>
                </tr>
                ${c.cuotaRE > 0 ? `<tr>
                    <td class="text-muted small">RE ${c.porcentajeRE}%</td>
                    <td class="text-end small">${formatCurrency(c.cuotaRE)}</td>
                </tr>` : ''}`).join('');
        }

        // Errores de validación
        if (r.errores.length) {
            notify(r.errores.join(' | '), 'warning');
        }
    }

    // ---------- Guardar factura ----------
    async function guardarFactura(enviarVerifactu = false) {
        if (!document.getElementById('id_cliente')?.value) {
            notify('Selecciona un cliente', 'warning'); return;
        }
        if (!lineas.length) {
            notify('Añade al menos una línea', 'warning'); return;
        }

        const fecha         = document.getElementById('fecha')?.value;
        const tipo_documento = document.getElementById('tipo_documento')?.value || 'FACTURA';
        const id_cliente    = document.getElementById('id_cliente')?.value;
        const id_canal      = document.getElementById('canal')?.value || null;
        const observaciones = document.getElementById('observaciones')?.value || '';

        const descuentos = {
            especial:  parseFloat(document.getElementById('dtoEspecial')?.value  || 0),
            comercial: parseFloat(document.getElementById('dtoComercial')?.value || 0),
            pp:        parseFloat(document.getElementById('dtoPP')?.value        || 0)
        };

        const payload = {
            action: 'create',
            fecha,
            tipo_documento,
            id_cliente,
            id_canal,
            observaciones,
            descuentos,
            enviar_verifactu: enviarVerifactu,
            lineas: lineas.map(l => ({
                id_articulo:  l.idArticulo,
                descripcion:  l.descripcion,
                cantidad:     l.cantidad,
                precio:       l.precio,
                descuento:    l.descuento,
                tipo_iva:     l.tipoIVA?.codigo || 'GEN'
            }))
        };

        App.showLoading();
        try {
            const data = await apiSend('/facturas.php', payload);
            if (data.success) {
                App.notify('Factura creada: ' + data.data.codigo, 'success');
                setTimeout(() => {
                    window.location.href = BASE + '/src/views/facturas/ver.php?codigo=' + encodeURIComponent(data.data.codigo);
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

    // ---------- Nuevo cliente (modal) ----------
    function mostrarBotonGuardarCliente(mostrar) {
        const btn = document.getElementById('btnGuardarNuevoCliente');
        if (btn) btn.style.display = mostrar ? '' : 'none';
    }

    async function guardarNuevoCliente() {
        const nombre_fiscal = document.getElementById('ncNombreFiscal')?.value?.trim();
        const nif           = document.getElementById('ncNif')?.value?.trim();

        if (!nombre_fiscal) { notify('El nombre fiscal es obligatorio', 'warning'); return; }
        if (!nif)           { notify('El NIF es obligatorio', 'warning'); return; }

        const payload = {
            action:         'create',
            nombre_fiscal,
            nif,
            email:          document.getElementById('ncEmail')?.value?.trim() || '',
            telefono:       document.getElementById('ncTelefono')?.value?.trim() || '',
            direccion:      document.getElementById('ncDireccion')?.value?.trim() || '',
            cp:             document.getElementById('ncCp')?.value?.trim() || '',
            poblacion:      document.getElementById('ncPoblacion')?.value?.trim() || '',
            provincia:      document.getElementById('ncProvincia')?.value?.trim() || '',
            pais:           document.getElementById('ncPais')?.value?.trim() || 'ES',
            porcentaje_re:  parseFloat(document.getElementById('ncRe')?.value || 0)
        };

        App.showLoading();
        try {
            const data = await apiSend('/clientes.php', payload);
            if (data.success) {
                App.notify('Cliente creado', 'success');
                const modal = bootstrap.Modal.getInstance(document.getElementById('modalNuevoCliente'));
                modal?.hide();
                await seleccionarCliente(data.data.id_cliente);
            } else {
                notify(data.message || 'Error al crear cliente', 'danger');
            }
        } catch (e) {
            notify(e.message || 'Error de conexión', 'danger');
        } finally {
            App.hideLoading();
        }
    }

    // ---------- Eventos ----------
    function inicializarEventos() {
        inicializarBusquedaCliente();
        inicializarBusquedaArticulo();

        // Línea — recalcular al cambiar campos
        ['lineaCantidad','lineaPrecio','lineaDescuento','lineaTipoIVA'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', actualizarCalculoLinea);
            document.getElementById(id)?.addEventListener('change', actualizarCalculoLinea);
        });

        // Añadir línea
        document.getElementById('btnAgregarLinea')?.addEventListener('click', agregarLinea);

        // Descuentos generales
        ['dtoEspecial','dtoComercial','dtoPP'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', recalcular);
        });

        // Tipo de documento
        document.getElementById('tipo_documento')?.addEventListener('change', recalcular);

        // Guardar
        document.getElementById('btnGuardar')?.addEventListener('click', () => guardarFactura(false));
        document.getElementById('btnGuardarEnviar')?.addEventListener('click', () => guardarFactura(true));

        // Nuevo cliente modal
        document.getElementById('btnGuardarNuevoCliente')?.addEventListener('click', guardarNuevoCliente);
        mostrarBotonGuardarCliente(true);

        // Cerrar dropdown de resultados al hacer click fuera
        document.addEventListener('click', e => {
            if (!e.target.closest('#clienteBusqueda') && !e.target.closest('#clienteResultados')) {
                const lista = document.getElementById('clienteResultados');
                if (lista) lista.style.display = 'none';
            }
            if (!e.target.closest('#articuloBusqueda') && !e.target.closest('#articuloResultados')) {
                const lista = document.getElementById('articuloResultados');
                if (lista) lista.style.display = 'none';
            }
        });
    }

})();
