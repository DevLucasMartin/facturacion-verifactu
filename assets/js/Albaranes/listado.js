const BASE_LIST = (() => {
    const p = window.location.pathname;
    const idx = p.indexOf('/SistemaGestionFacturas/');
    return idx >= 0 ? p.substring(0, idx) + '/SistemaGestionFacturas' : '/SistemaGestionFacturas';
})();

const FACT_API_BASE = BASE_LIST + '/src/api';

$(document).ready(function() {

    if (typeof $ === 'undefined') {
        console.error('jQuery NO está cargado.');
        return;
    }
    if (typeof $.fn.select2 === 'undefined') {
        console.error('Select2 NO está cargado.');
        return;
    }

    // ====== Estado ======
    let fact_currentPage = 1;
    let fact_totalPages  = 1;
    let fact_filtros     = {};
    let fact_filtros_aplicados = $('#filtrosForm').serialize();

    $('#filtroEstado').select2({
        placeholder: 'Todos',
        allowClear:  true,
        width:       '100%'
    });

    // ====== Select2: Cobrado ======
    $('#filtroCobrado').select2({
        placeholder: 'Todos',
        allowClear:  true,
        width:       '100%'
    });

    // ====== Select2: Canales ======
    $('#filtroCanal').select2({
        ajax: {
            url:      `${FACT_API_BASE}/canales.php`,
            dataType: 'json',
            delay:    150,
            data: function(params) {
                return { _type: 'query', q: params.term || '' };
            },
            processResults: function(data) {
                const rows = (data && data.data) ? data.data : [];
                return {
                    results: rows.map(function(c) {
                        return { id: c.id_canal, text: c.nombre };
                    })
                };
            },
            cache: true
        },
        allowClear:  true,
        placeholder: 'Seleccionar canal',
        width:       '100%'
    });

    // ====== Select2: Clientes ======
    $('#filtroCliente').select2({
        ajax: {
            url:      `${FACT_API_BASE}/clientes.php`,
            dataType: 'json',
            delay:    200,
            data: function(params) {
                return { action: 'search', q: params.term || '', limit: 30 };
            },
            processResults: function(resp) {
                const lista = (resp && resp.data) ? resp.data : [];
                return {
                    results: lista.map(function(c) {
                        return {
                            id:   c.Codigo,
                            text: (c.Nombre || c.Nombre || c.Codigo) + ' (' + (c.NIF || '-') + ')'
                        };
                    })
                };
            },
            cache: true
        },
        allowClear:         true,
        placeholder:        'Buscar cliente...',
        minimumInputLength: 2,
        width:              '100%'
    });

    $('#filtroCodigo').select2({
        ajax: {
            url:      `${FACT_API_BASE}/albaranes.php/search`,
            dataType: 'json',
            delay:    200,
            data: function(params) {
                return { q: params.term || '', limit: 30 };
            },
            processResults: function(resp) {
                const rows = resp?.data ?? [];
                return {
                    results: rows.map(r => ({ id: r.Codigo, text: r.Codigo }))
                };
            }
        },
        allowClear:         true,
        placeholder:        'Buscar código...',
        minimumInputLength: 2,
        width:              '100%'
    });

    // ====== Helper petición ======
    function peticionListadoFacturas(urlEndpoint, opcionesExtra = {}) {
        if (typeof App !== 'undefined' && typeof App.api === 'function') {
            return App.api(urlEndpoint, Object.assign({ url: urlEndpoint }, opcionesExtra));
        }
        return $.ajax(Object.assign({ url: urlEndpoint, method: 'GET', dataType: 'json' }, opcionesExtra));
    }

    // ====== Cargar albaranes ======
    function cargarListadoFacturas(fact_list_numPagina = 1) {
        fact_currentPage        = fact_list_numPagina;
        fact_filtros.page       = fact_list_numPagina;
        fact_filtros.per_page   = fact_filtros.per_page || 25;

        const fact_rutaConsulta = `${FACT_API_BASE}/albaranes.php?` + $.param(fact_filtros);

        peticionListadoFacturas(fact_rutaConsulta, { method: 'GET' })
            .done(function(r) {
                // App.api normaliza: data=items[], total/pages/page en raíz
                const d = r?.data ?? {};
                const fact_coleccionFacturas = Array.isArray(d) ? d : (d.items ?? []);
                const perPage = 25;
                const total   = Number(r?.total ?? d.total ?? fact_coleccionFacturas.length);
                const metadatosPaginacion = {
                    page:        Number(r?.page  ?? d.page  ?? 1),
                    per_page:    perPage,
                    total:       total,
                    total_pages: Number(r?.pages ?? d.total_pages ?? Math.ceil(total / perPage))
                };

                pintarTablaAlbaranes(fact_coleccionFacturas);
                generarControlPaginacion(metadatosPaginacion);
            })
            .fail(function(err) {
                console.error('Error cargando albaranes:', err.status, err.responseText);
                pintarTablaAlbaranes([]);
                $('#paginationNav').html('');
                $('#paginationInfo').text('Error cargando albaranes');
            });
    }

    window.cargarListadoFacturas = cargarListadoFacturas;

    // ====== Render tabla ======
    function pintarTablaAlbaranes(fact_listadoFacturas) {
        $('#mensaje-buscando').fadeOut(200, function() {
            $('.table-responsive, .card-footer').fadeIn(200);
        });
        const fact_cuerpoTabla = $('#albaranesTableBody');

        if (!fact_listadoFacturas || fact_listadoFacturas.length === 0) {
            fact_cuerpoTabla.html(`
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        No se encontraron albaranes
                    </td>
                </tr>`);
            return;
        }

        const fact_etiquetaFacturado = {
            'S': { label: 'Sí', class: 'bg-success' },
            'N': { label: 'No', class: 'bg-secondary' }
        };

        fact_cuerpoTabla.html(fact_listadoFacturas.map(function(facturaActual) {
            const facturado = fact_etiquetaFacturado[facturaActual.Facturado] || { label: '-', class: 'bg-secondary' };

            const totalFormatted = (typeof App !== 'undefined' && typeof App.formatCurrency === 'function')
                ? App.formatCurrency(facturaActual.Total)
                : (facturaActual.Total ?? '-');

            const verHref  = BASE_LIST + '/src/views/albaranes/ver.php?codigo=' + encodeURIComponent(facturaActual.Codigo);
            const editHref = BASE_LIST + '/src/views/albaranes/editar.php?codigo=' + encodeURIComponent(facturaActual.Codigo);

            const esFacturado = facturaActual.Facturado === 'S';
            const accionesBotones = esFacturado
                ? `<a href="${editHref}" class="btn btn-outline-secondary albr-btn disabled" title="No se puede editar un albarán facturado" aria-disabled="true" tabindex="-1">
                    <i class="bi bi-pencil"></i>
                </a>
                <button class="btn btn-outline-secondary albr-btn" title="No se puede eliminar un albarán facturado" disabled>
                    <i class="bi bi-trash"></i>
                </button>`
                : `<a href="${editHref}" class="btn btn-outline-primary albr-btn" title="Editar">
                    <i class="bi bi-pencil"></i>
                </a>
                <button class="btn btn-outline-danger albr-btn albr-btn-delete" title="Eliminar"
                    data-codigo="${fact_escapeHtml(facturaActual.Codigo)}">
                    <i class="bi bi-trash"></i>
                </button>`;

            return `
                <tr class="hover-bg-light cursor-pointer"
                    data-href="${verHref}">
                    <td class="text-center albr-td-stop">
                        ${facturaActual.Facturado !== 'S' ? `
                            <input type="checkbox" class="form-check-input alb-check"
                                value="${fact_escapeHtml(facturaActual.Codigo)}">
                        ` : ''}
                    </td>
                    <td><strong>${fact_escapeHtml(facturaActual.Codigo)}</strong></td>
                    <td>${fact_formatoFecha(facturaActual.Fecha)}</td>
                    <td>${fact_escapeHtml(facturaActual.Id_Canal || '-')}</td>
                    <td>${fact_escapeHtml(facturaActual.Nombre || 'Sin cliente')}</td>
                    <td class="text-end"><strong>${fact_escapeHtml(String(totalFormatted))}</strong></td>
                    <td class="text-center"><span class="badge ${facturado.class}">${fact_escapeHtml(facturado.label)}</span></td>
                    <td class="text-center albr-td-stop">
                        <div class="btn-group btn-group-sm albr-btn-group">
                            ${accionesBotones}
                        </div>
                    </td>
                </tr>`;
        }).join(''));
    }

    // ====== Render paginación ======
    function generarControlPaginacion(fact_meta) {
        const fact_domInfoPag = $('#paginationInfo');
        const fact_domContNav = $('#paginationNav');

        const fact_pagActual  = Number(fact_meta.page     || 1);
        const fact_porPag     = Number(fact_meta.per_page || 25);
        const fact_totalPags  = Number(fact_meta.total    || 0);
        fact_totalPages        = Number(fact_meta.total_pages || 1);

        const fact_rangoDesde = fact_totalPags === 0 ? 0 : (fact_pagActual - 1) * fact_porPag + 1;
        const fact_rangoHasta = Math.min(fact_pagActual * fact_porPag, fact_totalPags);
        fact_domInfoPag.text(`Mostrando ${fact_rangoDesde} - ${fact_rangoHasta} de ${fact_totalPags} registros`);

        if (fact_totalPages <= 1) {
            fact_domContNav.html('');
            return;
        }

        let html = '';
        html += `<li class="page-item ${fact_pagActual <= 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${fact_pagActual - 1}">Anterior</a>
                 </li>`;

        for (let i = 1; i <= fact_totalPages; i++) {
            if (i === 1 || i === fact_totalPages || (i >= fact_pagActual - 2 && i <= fact_pagActual + 2)) {
                html += `<li class="page-item ${i === fact_pagActual ? 'active' : ''}">
                            <a class="page-link" href="#" data-page="${i}">${i}</a>
                         </li>`;
            } else if (i === fact_pagActual - 3 || i === fact_pagActual + 3) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        html += `<li class="page-item ${fact_pagActual >= fact_totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${fact_pagActual + 1}">Siguiente</a>
                 </li>`;

        fact_domContNav.html(html);
    }

    // ====== Helpers ======
    function fact_formatoFecha(fact_stringFecha) {
        if (!fact_stringFecha) return '-';
        const d = new Date(fact_stringFecha.date || fact_stringFecha);
        return d.toLocaleDateString('es-ES', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function fact_escapeHtml(str) {
        return String(str ?? '')
            .replaceAll('&', '&amp;').replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;').replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    // ====== Eliminar ======
    window.fact_solicitarBorrado = function(fact_codFactura) {
        App.confirm({
            icon:        'warning',
            title:       'Eliminar albarán',
            text:        '¿Está seguro de que desea eliminar este albarán?',
            confirmText: 'Eliminar',
            danger:      true
        }).then(function (ok) {
            if (!ok) return;

            const url = `${FACT_API_BASE}/albaranes.php/` + encodeURIComponent(fact_codFactura);

            peticionListadoFacturas(url, { method: 'DELETE' })
                .done(function() {
                    if (typeof App !== 'undefined' && typeof App.notify === 'function') {
                        App.notify('Albarán eliminado correctamente', 'success');
                    }
                    cargarListadoFacturas(fact_currentPage);
                })
                .fail(function(err) {
                    console.error('Error eliminando albarán:', err.status, err.responseText);
                    App.alert('No se pudo eliminar el albarán. Revisa consola/Network.', { icon: 'error', title: 'Error' });
                });
        });
    };

    // ====== Submit filtros ======
    $('#filtrosForm').on('submit', function(e) {
        e.preventDefault();
        fact_filtros           = $(this).serializeObject();
        fact_filtros_aplicados = $(this).serialize();
        cargarListadoFacturas(1);
    });

    $.fn.serializeObject = function() {
        const datos = {};
        const lista = this.serializeArray();
        $.each(lista, function() {
            if (datos[this.name] !== undefined) {
                if (!datos[this.name].push) datos[this.name] = [datos[this.name]];
                datos[this.name].push(this.value || '');
            } else {
                datos[this.name] = this.value || '';
            }
        });
        Object.keys(datos).forEach(key => { if (datos[key] === '') delete datos[key]; });
        return datos;
    };

    const mostrarBuscando = () => {
        if ($('#mensaje-buscando').is(':visible')) return;
        $('.table-responsive, .card-footer').stop(true, true).fadeOut(200, function() {
            $('#mensaje-buscando').stop(true, true).fadeIn(200);
        });
    };

    const hayFiltrosModificados = () => $('#filtrosForm').serialize() !== fact_filtros_aplicados;

    const restaurarTabla = () => {
        if (!hayFiltrosModificados()) {
            $('#mensaje-buscando').stop(true, true).fadeOut(200, function() {
                $('.table-responsive, .card-footer').fadeIn(200);
            });
        }
    };

    $('#filtrosForm').find('input').on('focus', mostrarBuscando);
    $('#filtrosForm').find('select').on('focus', mostrarBuscando);
    $('#filtrosForm').find('input').on('blur', restaurarTabla);

    const selectoresSelect2 = '#filtroCanal, #filtroCliente, #filtroCodigo, #filtroEstado, #filtroCobrado';
    $(selectoresSelect2).on('select2:open', function() {
        $('#mensaje-buscando, .table-responsive, .card-footer').stop(true, true);
        mostrarBuscando();
    });
    $(selectoresSelect2).on('select2:close select2:select select2:unselect', function() {
        setTimeout(restaurarTabla, 150);
    });

    // ====== Exportar a Excel ======
    $('#btnExportarExcel').on('click', function() {
        const filtros   = $('#filtrosForm').serialize();
        const urlExport = `${FACT_API_BASE}/albaranes.php/exportar?${filtros}`;

        document.getElementById('exportIndicator').style.display = 'block';
        document.getElementById('btnExportarExcel').disabled     = true;
        document.getElementById('btnExportarExcel').innerHTML    = '<i class="bi bi-hourglass-split me-1"></i>Generando...';

        const link = document.createElement('a');
        link.href  = urlExport;
        link.download = 'albaranes.xlsx';
        link.click();

        setTimeout(() => {
            document.getElementById('exportIndicator').style.display = 'none';
            document.getElementById('btnExportarExcel').disabled     = false;
            document.getElementById('btnExportarExcel').innerHTML    = '<i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel';
        }, 3000);
    });

    // ====== Event delegation ======

    // Paginación
    $(document).on('click', '#paginationNav .page-link', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page !== undefined && !$(this).closest('.page-item').hasClass('disabled')) {
            cargarListadoFacturas(Number(page));
        }
    });

    // Fila → ver albarán
    $(document).on('click', '#albaranesTableBody tr[data-href]', function(e) {
        if (!$(e.target).closest('.albr-td-stop').length) {
            window.location = $(this).data('href');
        }
    });

    // Botón eliminar
    $(document).on('click', '#albaranesTableBody .albr-btn-delete', function(e) {
        e.stopPropagation();
        fact_solicitarBorrado($(this).data('codigo'));
    });

    // Checkbox selección
    $(document).on('change', '#albaranesTableBody .alb-check', function() {
        actualizarSeleccion();
    });

    // ====== Carga inicial ======
    cargarListadoFacturas(1);
});

// ====== Selección múltiple para facturar ======
window.actualizarSeleccion = function() {
    const checks  = document.querySelectorAll('.alb-check:checked');
    const total   = checks.length;
    const btn     = document.getElementById('btnFacturarSeleccionados');
    const numSpan = document.getElementById('numSeleccionados');

    if (numSpan) numSpan.textContent = total;
    if (btn) btn.style.setProperty('display', total > 0 ? 'inline-flex' : 'none', 'important');

    const allChecks = document.querySelectorAll('.alb-check');
    const checkAll  = document.getElementById('checkAll');
    if (checkAll) {
        checkAll.checked       = total > 0 && total === allChecks.length;
        checkAll.indeterminate = total > 0 && total < allChecks.length;
    }
};

window.toggleCheckAll = function(master) {
    document.querySelectorAll('.alb-check').forEach(function(cb) {
        cb.checked = master.checked;
    });
    actualizarSeleccion();
};

window.facturarSeleccionados = function() {
    const checks = document.querySelectorAll('.alb-check:checked');
    if (checks.length === 0) return;

    const params = Array.from(checks)
        .map(cb => 'albaranes[]=' + encodeURIComponent(cb.value))
        .join('&');

    window.location = BASE_LIST + '/src/views/albaranes/facturar.php?' + params;
};

// ====== Plantillas ======
function _plantEscHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

window.abrirModalPlantillas = function() {
    const modalEl = document.getElementById('plantillasModal');
    if (!modalEl) return;
    bootstrap.Modal.getOrCreateInstance(modalEl).show();

    const buscador = document.getElementById('buscadorPlantillas');
    if (buscador) buscador.value = '';

    const tbody = document.getElementById('plantillasBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Cargando...</td></tr>';

    fetch(FACT_API_BASE + '/albaranes.php/plantillas')
        .then(r => r.json())
        .then(json => {
            const items = json.data || [];
            if (!items.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No hay plantillas guardadas</td></tr>';
                return;
            }
            tbody.innerHTML = items.map(p =>
                `<tr data-codigo="${_plantEscHtml(p.Codigo)}">
                    <td class="plant-nombre-cell" onclick="window.usarPlantilla('${_plantEscHtml(p.Codigo)}')">
                        <span class="plant-nombre-text">${_plantEscHtml(p.Nombre_Plantilla || p.Codigo)}</span>
                        <input type="text" class="plant-nombre-input form-control form-control-sm"
                            value="${_plantEscHtml(p.Nombre_Plantilla || p.Codigo)}" style="display:none;">
                    </td>
                    <td onclick="window.usarPlantilla('${_plantEscHtml(p.Codigo)}')">${_plantEscHtml(p.Id_Canal || '-')}</td>
                    <td onclick="window.usarPlantilla('${_plantEscHtml(p.Codigo)}')">${_plantEscHtml(p.NombreCliente || '-')}</td>
                    <td class="text-center">
                        <button class="btn btn-outline-primary btn-sm btn-renombrar" title="Renombrar"
                            data-codigo="${_plantEscHtml(p.Codigo)}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-danger btn-sm" title="Eliminar"
                            onclick="event.stopPropagation(); window.eliminarPlantilla('${_plantEscHtml(p.Codigo)}')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>`
            ).join('');

            // Event delegation para botón renombrar
            tbody.querySelectorAll('.btn-renombrar').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const codigo  = this.getAttribute('data-codigo');
                    const cell    = this.closest('tr').querySelector('.plant-nombre-cell');
                    const textEl  = cell.querySelector('.plant-nombre-text');
                    const inputEl = cell.querySelector('.plant-nombre-input');

                    textEl.style.display  = 'none';
                    inputEl.style.display = 'inline-block';
                    inputEl.onclick = function(ev) { ev.stopPropagation(); };
                    inputEl.focus();
                    inputEl.select();

                    const guardar = function() {
                        const nuevoNombre = inputEl.value.trim();
                        if (nuevoNombre && nuevoNombre !== textEl.textContent.trim()) {
                            fetch(FACT_API_BASE + '/albaranes.php/' + encodeURIComponent(codigo) + '/plantilla', {
                                method:  'PUT',
                                headers: { 'Content-Type': 'application/json' },
                                body:    JSON.stringify({ nombre: nuevoNombre })
                            })
                            .then(r => r.json())
                            .then(json => {
                                if (json.success) {
                                    textEl.textContent = nuevoNombre;
                                    App.notify('Plantilla renombrada correctamente', 'success');
                                } else {
                                    App.notify(json.message || 'Error al renombrar', 'error');
                                }
                                textEl.style.display  = '';
                                inputEl.style.display = 'none';
                            })
                            .catch(e => {
                                App.notify(e.message, 'error');
                                textEl.style.display  = '';
                                inputEl.style.display = 'none';
                            });
                        } else {
                            textEl.style.display  = '';
                            inputEl.style.display = 'none';
                        }
                    };

                    inputEl.addEventListener('blur', guardar);
                    inputEl.addEventListener('keydown', function(ev) {
                        if (ev.key === 'Enter')  inputEl.blur();
                        if (ev.key === 'Escape') { inputEl.value = textEl.textContent.trim(); inputEl.blur(); }
                    });
                });
            });

            if (buscador) {
                buscador.oninput = function() {
                    const q = this.value.toLowerCase();
                    tbody.querySelectorAll('tr').forEach(function(row) {
                        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
                    });
                };
                buscador.focus();
            }
        })
        .catch(e => {
            tbody.innerHTML = `<tr><td colspan="4" class="text-danger text-center">${_plantEscHtml(e.message)}</td></tr>`;
        });
};

window.usarPlantilla = function(codigo) {
    window.location.href = BASE_LIST + '/src/views/albaranes/nuevo.php?plantilla=' + encodeURIComponent(codigo);
};

// ====== Confirmación eliminar plantilla ======
let _plantillaAEliminar = null;

window.confirmarEliminarPlantilla = function(codigo) {
    _plantillaAEliminar = codigo;
    const modal = new bootstrap.Modal(document.getElementById('confirmarEliminarModal'));
    modal.show();
};

document.getElementById('btnConfirmarEliminar')?.addEventListener('click', function() {
    if (!_plantillaAEliminar) return;
    const codigo = _plantillaAEliminar;
    _plantillaAEliminar = null;

    bootstrap.Modal.getInstance(document.getElementById('confirmarEliminarModal'))?.hide();

    fetch(FACT_API_BASE + '/albaranes.php/' + encodeURIComponent(codigo) + '/plantilla', { method: 'DELETE' })
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                App.notify('Plantilla eliminada correctamente', 'success');
                abrirModalPlantillas();
            } else {
                App.notify(json.message || 'Error al eliminar la plantilla', 'error');
            }
        })
        .catch(e => App.notify(e.message, 'error'));
});

window.eliminarPlantilla = function(codigo) {
    confirmarEliminarPlantilla(codigo);
};
