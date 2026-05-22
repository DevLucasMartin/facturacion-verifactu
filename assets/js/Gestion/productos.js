const BASE_FACT = (() => {
    const p = window.location.pathname;
    const idx = p.indexOf('/SistemaGestionFacturas/');
    return idx >= 0 ? p.substring(0, idx) + '/SistemaGestionFacturas' : '/SistemaGestionFacturas';
})();

const FACT_API_BASE = BASE_FACT + '/src/api';

let prod_pagActual    = 1;
let prod_busqueda     = '';
let prod_busqTimer    = null;
let prod_modal        = null;
let prod_modoEdicion  = false;
let prod_codigoEdit   = null;

$(document).ready(function () {
    prod_modal = new bootstrap.Modal(document.getElementById('productoModal'));

    prod_cargarTiposIVA();
    cargarProductos();

    $('#productosBuscar').on('input', function () {
        clearTimeout(prod_busqTimer);
        prod_busqTimer = setTimeout(function () {
            prod_busqueda = $('#productosBuscar').val().trim();
            prod_pagActual = 1;
            cargarProductos();
        }, 350);
    });

    $('#btnNuevoProducto').on('click', abrirNuevoProducto);

    $('#productoForm').on('submit', function (e) {
        e.preventDefault();
        guardarProducto();
    });
});

function prod_cargarTiposIVA() {
    App.api(FACT_API_BASE + '/tiposiva.php')
        .done(function (response) {
            const opts = (response.data || []).map(function (t) {
                return `<option value="${prod_esc(t.Codigo)}">${prod_esc(t.Codigo + ' – ' + t.Descripcion + ' (' + t.IVA + '%)')}</option>`;
            }).join('');
            $('#pTipoIVA').html('<option value="">— Seleccionar —</option>' + opts);
        });
}

function abrirNuevoProducto() {
    prod_modoEdicion = false;
    prod_codigoEdit  = null;
    $('#productoModalTitle').text('Nuevo Producto');
    $('#productoMsg').addClass('d-none').removeClass('alert-success alert-danger alert-warning').addClass('alert-info').text('');
    $('#productoForm')[0].reset();
    $('#pCodigo').prop('readonly', false).removeClass('is-invalid is-valid');
    $('#pCodigoError').text('');
    $('#pActivo').prop('checked', true);
    $('#pActivoWrapper').hide();
    prod_modal.show();
}

function abrirModificarProducto(codigo) {
    prod_modoEdicion = true;
    prod_codigoEdit  = codigo;
    $('#productoModalTitle').text('Modificar Producto');
    $('#productoMsg').addClass('d-none').removeClass('alert-success alert-danger alert-warning').addClass('alert-info').text('');
    $('#productoForm')[0].reset();
    $('#pCodigo').prop('readonly', true).removeClass('is-invalid is-valid').val(codigo);
    $('#pCodigoError').text('');
    $('#pActivoWrapper').show();

    App.api(`${FACT_API_BASE}/articulos.php/${encodeURIComponent(codigo)}`)
        .done(function (response) {
            const a = response.data;
            $('#pDescripcion').val(a.Descripcion      || '');
            $('#pModelo').val(a.Modelo                || '');
            $('#pCodigoBarras').val(a.Codigo_Barras   || '');
            $('#pTipoIVA').val(a.Id_Tipo_IVA          || '');
            $('#pPrecio1').val(parseFloat(a.Precio_Venta_1 || 0).toFixed(4));
            $('#pPrecio2').val(parseFloat(a.Precio_Venta_2 || 0).toFixed(4));
            $('#pPrecio3').val(parseFloat(a.Precio_Venta_3 || 0).toFixed(4));
            $('#pDescuento').val(parseFloat(a.Descuento    || 0).toFixed(2));
            $('#pActivo').prop('checked', a.Activo !== 'N');
            prod_modal.show();
        })
        .fail(function () {
            App.notify('Error al cargar los datos del producto', 'danger');
        });
}

function prod_validarCodigo(input) {
    const val     = input.value.toUpperCase();
    input.value   = val;
    const valid   = /^[A-Z0-9\-_.]{0,30}$/.test(val);
    const errorEl = document.getElementById('pCodigoError');
    if (!valid) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        errorEl.textContent = 'Solo letras, números y guiones, máx. 30 caracteres.';
    } else if (val.length > 0) {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
        errorEl.textContent = '';
    } else {
        input.classList.remove('is-invalid', 'is-valid');
        errorEl.textContent = '';
    }
}

function guardarProducto() {
    const codigoVal      = ($('#pCodigo').val()      || '').trim().toUpperCase();
    const descripcionVal = ($('#pDescripcion').val() || '').trim();
    const tipoIVAVal     = ($('#pTipoIVA').val()     || '').trim();

    if (!prod_modoEdicion) {
        if (!codigoVal) {
            $('#pCodigo').addClass('is-invalid');
            document.getElementById('pCodigoError').textContent = 'El código es obligatorio.';
            return;
        }
    }
    if (!descripcionVal) {
        $('#productoMsg').removeClass('d-none alert-info alert-success alert-warning').addClass('alert-danger').text('La descripción es obligatoria.');
        return;
    }
    if (!tipoIVAVal) {
        $('#productoMsg').removeClass('d-none alert-info alert-success alert-warning').addClass('alert-danger').text('Debes seleccionar un tipo de IVA.');
        return;
    }

    const payload = {
        Descripcion:   descripcionVal,
        Modelo:        ($('#pModelo').val()       || '').trim() || null,
        Codigo_Barras: ($('#pCodigoBarras').val() || '').trim() || null,
        Id_Tipo_IVA:   tipoIVAVal,
        Precio_Venta_1: parseFloat($('#pPrecio1').val()) || 0,
        Precio_Venta_2: parseFloat($('#pPrecio2').val()) || 0,
        Precio_Venta_3: parseFloat($('#pPrecio3').val()) || 0,
        Descuento:      parseFloat($('#pDescuento').val()) || 0,
    };

    if (!prod_modoEdicion) {
        payload.Codigo = codigoVal;
    } else {
        payload.Activo = $('#pActivo').prop('checked') ? 'S' : 'N';
    }

    const url    = prod_modoEdicion
        ? `${FACT_API_BASE}/articulos.php/${encodeURIComponent(prod_codigoEdit)}`
        : `${FACT_API_BASE}/articulos.php`;
    const method = prod_modoEdicion ? 'PUT' : 'POST';
    const msgOk  = prod_modoEdicion ? 'Producto actualizado correctamente.' : `Producto ${prod_esc(codigoVal)} creado correctamente.`;

    $('#pBtnGuardar').prop('disabled', true);

    App.api(url, { method, data: JSON.stringify(payload), contentType: 'application/json' })
        .done(function () {
            $('#productoMsg').removeClass('d-none alert-info alert-danger alert-warning').addClass('alert-success').text(msgOk);
            setTimeout(function () {
                prod_modal.hide();
                cargarProductos();
            }, 800);
        })
        .fail(function (xhr) {
            const msg = xhr.responseJSON?.message || 'Error al guardar el producto.';
            const esDuplicado = !prod_modoEdicion && msg.includes('ya existe');
            $('#productoMsg').removeClass('d-none alert-info alert-success').addClass(esDuplicado ? 'alert-warning' : 'alert-danger').text(msg);
            if (esDuplicado) {
                $('#pCodigo').addClass('is-invalid');
                document.getElementById('pCodigoError').textContent = 'Este código ya existe.';
            }
        })
        .always(function () {
            $('#pBtnGuardar').prop('disabled', false);
        });
}

function cargarProductos() {
    let url;
    if (prod_busqueda.length >= 2) {
        url = `${FACT_API_BASE}/articulos.php?gestion&q=${encodeURIComponent(prod_busqueda)}&page=1&per_page=10`;
    } else {
        url = `${FACT_API_BASE}/articulos.php?gestion&page=${prod_pagActual}&per_page=10`;
    }

    App.api(url)
        .done(function (response) {
            const items = response.data || [];
            const total = response.total ?? items.length;
            renderProductos(items);
            $('#productosTotalLabel').text(total + ' productos');
            if (!prod_busqueda) renderPaginacion(response);
        })
        .fail(function () {
            $('#productosTableBody').html(`
                <tr>
                    <td colspan="8" class="text-center py-4 text-danger">
                        <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
                        Error al cargar los productos
                    </td>
                </tr>`);
        });
}

function renderProductos(productos) {
    if (!productos.length) {
        $('#productosTableBody').html(`
            <tr>
                <td colspan="8" class="text-center py-4 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    No se encontraron productos
                </td>
            </tr>`);
        $('#productosPaginacion').hide();
        return;
    }

    $('#productosTableBody').html(productos.map(function (p) {
        const activo = p.Activo !== 'N';
        const precio = parseFloat(p.Precio_Venta_1 || 0).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
        const dto    = parseFloat(p.Descuento || 0);

        return `
            <tr class="fact-tr-hover">
                <td><strong>${prod_esc(p.Codigo)}</strong></td>
                <td>${prod_esc(p.Descripcion || '')}</td>
                <td>${prod_esc(p.Codigo_Barras || '—')}</td>
                <td>${prod_esc(p.Id_Tipo_IVA || '—')}</td>
                <td class="text-end">${precio} €</td>
                <td class="text-end">${dto > 0 ? dto.toFixed(2) + ' %' : '<span class="text-muted">—</span>'}</td>
                <td class="text-center">
                    ${activo
                        ? '<i class="bi bi-check-circle-fill text-success"></i>'
                        : '<i class="bi bi-x-circle-fill text-danger"></i>'}
                </td>
                <td class="text-center d-flex gap-1 justify-content-center">
                    <button class="btn btn-sm btn-outline-primary fact-btn"
                            title="Modificar"
                            onclick="abrirModificarProducto('${prod_esc(p.Codigo)}')">
                        <i class="bi bi-pencil"></i>
                    </button>
                    ${activo
                        ? `<button class="btn btn-sm btn-outline-danger fact-btn"
                                   title="Desactivar"
                                   onclick="eliminarProducto('${prod_esc(p.Codigo)}', '${prod_esc(p.Descripcion)}')">
                               <i class="bi bi-slash-circle"></i>
                           </button>`
                        : `<button class="btn btn-sm btn-outline-success fact-btn"
                                   title="Activar"
                                   onclick="activarProducto('${prod_esc(p.Codigo)}', '${prod_esc(p.Descripcion)}')">
                               <i class="bi bi-check-circle"></i>
                           </button>`}
                </td>
            </tr>`;
    }).join(''));
}

function renderPaginacion(response) {
    const total     = response.total  ?? 0;
    const pagActual = response.page   ?? 1;
    const totalPags = response.pages  ?? 1;
    const perPage   = response.per_page ?? 10;
    const desde     = (pagActual - 1) * perPage + 1;
    const hasta     = Math.min(pagActual * perPage, total);

    if (totalPags <= 1) {
        $('#productosPaginacion').hide();
        return;
    }

    $('#productosPaginacion').show();
    $('#productosPagInfo').text(`Mostrando ${desde}–${hasta} de ${total}`);

    let links = '';
    links += `<li class="page-item ${pagActual <= 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" data-pag="${pagActual - 1}">&laquo;</a></li>`;

    for (let p = Math.max(1, pagActual - 2); p <= Math.min(totalPags, pagActual + 2); p++) {
        links += `<li class="page-item ${p === pagActual ? 'active' : ''}">
            <a class="page-link" href="#" data-pag="${p}">${p}</a></li>`;
    }

    links += `<li class="page-item ${pagActual >= totalPags ? 'disabled' : ''}">
        <a class="page-link" href="#" data-pag="${pagActual + 1}">&raquo;</a></li>`;

    $('#productosPagLinks').html(links);

    $('#productosPagLinks').off('click').on('click', 'a.page-link', function (e) {
        e.preventDefault();
        const p = parseInt($(this).data('pag'));
        if (p >= 1 && p <= totalPags && p !== prod_pagActual) {
            prod_pagActual = p;
            cargarProductos();
        }
    });
}

function eliminarProducto(codigo, descripcion) {
    if (!confirm(`¿Desactivar el producto "${descripcion}" (${codigo})?\n\nNo aparecerá en los selectores de facturas ni albaranes, pero se conserva en el histórico.`)) return;

    App.api(`${FACT_API_BASE}/articulos.php/${encodeURIComponent(codigo)}`, { method: 'DELETE' })
        .done(function () {
            App.notify('Producto desactivado correctamente', 'success');
            cargarProductos();
        })
        .fail(function (xhr) {
            const msg = xhr.responseJSON?.message || 'Error al desactivar el producto.';
            App.notify(msg, 'danger');
        });
}

function activarProducto(codigo, descripcion) {
    if (!confirm(`¿Activar el producto "${descripcion}" (${codigo})?\n\nVolverá a aparecer en los selectores de facturas y albaranes.`)) return;

    App.api(`${FACT_API_BASE}/articulos.php/${encodeURIComponent(codigo)}/activar`, { method: 'PATCH' })
        .done(function () {
            App.notify('Producto activado correctamente', 'success');
            cargarProductos();
        })
        .fail(function (xhr) {
            const msg = xhr.responseJSON?.message || 'Error al activar el producto.';
            App.notify(msg, 'danger');
        });
}

function prod_esc(text) {
    return String(text ?? '')
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;').replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
