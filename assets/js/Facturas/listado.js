const BASE_FACT = (() => {
    const p = window.location.pathname;
    const idx = p.indexOf('/SistemaGestionFacturas/');
    return idx >= 0 ? p.substring(0, idx) + '/SistemaGestionFacturas' : '/SistemaGestionFacturas';
})();

const FACT_API_BASE = BASE_FACT + '/src/api';

$(document).ready(function() {

    // ====== Comprobaciones ======
    if (typeof $ === 'undefined') {
        console.error('jQuery NO está cargado.');
        return;
    }
    if (typeof $.fn.select2 === 'undefined') {
        console.error('Select2 NO está cargado.');
        return;
    }

    // ====== Estado ======
    let fact_totalPages  = 1;
    let fact_filtros     = {};
    let fact_filtros_aplicados = $('#filtrosForm').serialize();

    // ====== Select2: Tipo documento ======
    $('select[name="tipo_documento"]').select2({
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
                        return { id: c.Codigo, text: c.Descripcion ? `${c.Codigo} – ${c.Descripcion}` : c.Codigo };
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

    // ====== Select2: Código factura ======
    $('#filtroCodigo').select2({
        ajax: {
            url:      `${FACT_API_BASE}/facturas.php/search`,
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

    // ====== Cargar facturas ======
    function cargarListadoFacturas(fact_list_numPagina = 1) {
        fact_filtros.page     = fact_list_numPagina;
        fact_filtros.per_page = fact_filtros.per_page || 25;

        const fact_rutaConsulta = `${FACT_API_BASE}/facturas.php?` + $.param(fact_filtros);

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

                pintarTablaFacturas(fact_coleccionFacturas);
                generarControlPaginacion(metadatosPaginacion);
            })
            .fail(function(err) {
                console.error('Error cargando facturas:', err.status, err.responseText);
                pintarTablaFacturas([]);
                $('#paginationNav').html('');
                $('#paginationInfo').text('Error cargando facturas');
            });
    }

    window.cargarListadoFacturas = cargarListadoFacturas;

    // ====== Render tabla ======
    function pintarTablaFacturas(fact_listadoFacturas) {
        $('#mensaje-buscando').fadeOut(200, function() {
            $('.table-responsive, .card-footer').fadeIn(200);
        });

        const fact_cuerpoTabla = $('#facturasTableBody');

        if (!fact_listadoFacturas || fact_listadoFacturas.length === 0) {
            fact_cuerpoTabla.html(`
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        No se encontraron facturas
                    </td>
                </tr>`);
            return;
        }

        const fact_etiquetaTipo = {
            'FACTURA':       { label: 'Factura',   class: 'bg-primary' },
            'SIMPLIFICADA':  { label: 'Simplif.',  class: 'bg-info text-dark' },
            'RECTIFICATIVA': { label: 'Abono',     class: 'bg-warning text-dark' },
            'RECAPITULATIVA':{ label: 'Recap.',    class: 'bg-secondary' }
        };

        fact_cuerpoTabla.html(fact_listadoFacturas.map(function(facturaActual) {
            const fact_tipo = fact_etiquetaTipo[facturaActual.tipo_documento] || {
                label: facturaActual.tipo_documento || '-',
                class: 'bg-secondary'
            };

            const fact_estado = facturaActual.cobrada === 'S'
                ? { label: 'Cobrado',   class: 'bg-success' }
                : { label: 'Pendiente', class: 'bg-warning text-dark' };

            const totalFormatted = (typeof App !== 'undefined' && typeof App.formatCurrency === 'function')
                ? App.formatCurrency(facturaActual.total)
                : (facturaActual.total ?? '-');

            const verHref      = BASE_FACT + '/src/views/facturas/ver.php?codigo=' + encodeURIComponent(facturaActual.codigo);
            const rectificaHref = BASE_FACT + '/src/views/facturas/nuevo.php?rectifica=' + encodeURIComponent(facturaActual.codigo);

            const estaEnviada = facturaActual.estado_verifactu === 'ENVIADA' || facturaActual.estado_verifactu === 'ACEPTADA';

            return `
                <tr class="hover-bg-light fact-tr-hover"
                    data-href="${verHref}">
                    <td><strong>${fact_escapeHtml(facturaActual.codigo)}</strong></td>
                    <td>${fact_formatoFecha(facturaActual.fecha)}</td>
                    <td><span class="badge ${fact_tipo.class}">${fact_escapeHtml(fact_tipo.label)}</span></td>
                    <td>${fact_escapeHtml(facturaActual.nombre_cliente || 'Sin cliente')}</td>
                    <td class="text-end"><strong>${fact_escapeHtml(String(totalFormatted))}</strong></td>
                    <td class="text-center"><span class="badge ${fact_estado.class}">${fact_escapeHtml(fact_estado.label)}</span></td>
                    <td class="text-center">
                        ${estaEnviada
                            ? '<span style="color:#28a745;"><i class="bi bi-circle-fill" title="Enviada a Hacienda"></i></span>'
                            : '<span class="text-muted"><i class="bi bi-circle" title="No enviada"></i></span>'}
                    </td>
                    <td class="text-center" onclick="event.stopPropagation()">
                        <div class="btn-group btn-group-sm">
                            <a href="${rectificaHref}"
                               class="btn btn-outline-warning"
                               title="Crear factura rectificativa">
                               <i class="bi bi-file-text"></i>
                            </a>
                        </div>
                    </td>
                </tr>`;
        }).join(''));
    }

    // ====== Render paginación ======
    function generarControlPaginacion(fact_meta) {
        const fact_domInfoPag = $('#paginationInfo');
        const fact_domContNav = $('#paginationNav');

        const fact_pagActual = Number(fact_meta.page     || 1);
        const fact_porPag    = Number(fact_meta.per_page || 25);
        const fact_totalPags = Number(fact_meta.total    || 0);
        fact_totalPages       = Number(fact_meta.total_pages || 1);

        const fact_rangoDesde = fact_totalPags === 0 ? 0 : (fact_pagActual - 1) * fact_porPag + 1;
        const fact_rangoHasta = Math.min(fact_pagActual * fact_porPag, fact_totalPags);
        fact_domInfoPag.text(`Mostrando ${fact_rangoDesde} - ${fact_rangoHasta} de ${fact_totalPags} registros`);

        if (fact_totalPages <= 1) {
            fact_domContNav.html('');
            return;
        }

        let html = '';
        html += `<li class="page-item ${fact_pagActual <= 1 ? 'disabled' : ''}">
                    <a class="page-link fact-page-link" href="#" data-page="${fact_pagActual - 1}">Anterior</a>
                 </li>`;

        for (let i = 1; i <= fact_totalPages; i++) {
            if (i === 1 || i === fact_totalPages || (i >= fact_pagActual - 2 && i <= fact_pagActual + 2)) {
                html += `<li class="page-item ${i === fact_pagActual ? 'active' : ''}">
                            <a class="page-link fact-page-link" href="#" data-page="${i}">${i}</a>
                         </li>`;
            } else if (i === fact_pagActual - 3 || i === fact_pagActual + 3) {
                html += `<li class="page-item disabled"><span class="page-link fact-page-link">...</span></li>`;
            }
        }

        html += `<li class="page-item ${fact_pagActual >= fact_totalPages ? 'disabled' : ''}">
                    <a class="page-link fact-page-link" href="#" data-page="${fact_pagActual + 1}">Siguiente</a>
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
    $('#filtrosForm').find('input, select').on('blur input select change', function() {
        restaurarTabla();
    });

    const selectoresSelect2 = '#filtroCodigo, #filtroCanal, #filtroCliente, #filtroTipo, #filtroCobrado';

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
        const urlExport = `${FACT_API_BASE}/facturas.php/exportar?${filtros}`;

        document.getElementById('exportIndicator').style.display = 'block';
        document.getElementById('btnExportarExcel').disabled     = true;
        document.getElementById('btnExportarExcel').innerHTML    = '<i class="bi bi-hourglass-split me-1"></i>Generando...';

        const link = document.createElement('a');
        link.href  = urlExport;
        link.download = 'facturas.xlsx';
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

    // Fila → ver factura
    $(document).on('click', '#facturasTableBody tr[data-href]', function(e) {
        if (!$(e.target).closest('[onclick]').length) {
            window.location = $(this).data('href');
        }
    });

    // ====== Carga inicial ======
    cargarListadoFacturas(1);
});
