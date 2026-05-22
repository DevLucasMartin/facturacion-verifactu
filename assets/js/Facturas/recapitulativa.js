(() => {
    'use strict';

    const BASE = (() => {
        const p = window.location.pathname;
        const idx = p.indexOf('/SistemaGestionFacturas/');
        return idx >= 0 ? p.substring(0, idx) + '/SistemaGestionFacturas' : '/SistemaGestionFacturas';
    })();

    const RECAP_API_BASE = BASE + '/src/api';

    let simplificadasActuales = [];
    let clienteNIFSeleccionado = '';

    $(document).ready(function () {
        cargarCanales();
        cargarFormasPago();
        inicializarSelectCliente();
        poblarAnios();

        $('#selectCanal, #selectAnio, #selectMes').on('change', cargarSimplificadas);

        $('#recapForm').on('submit', function (e) {
            e.preventDefault();
            crearRecapitulativa().catch(function (err) {
                App.notify(err.message || 'Error inesperado', 'danger');
            });
        });
    });

    function inicializarSelectCliente() {
        $('#selectCliente').select2({
            ajax: {
                url: RECAP_API_BASE + '/clientes.php',
                dataType: 'json',
                delay: 200,
                data: function (params) {
                    return { action: 'search', q: params.term || '', limit: 30 };
                },
                processResults: function (resp) {
                    const lista = (resp && resp.data) ? resp.data : [];
                    return {
                        results: lista.map(function (c) {
                            const apellidos = c.Apellidos ? ' ' + c.Apellidos : '';
                            return {
                                id:   c.Codigo,
                                text: (c.Nombre || c.Archivar_Como || 'Sin nombre') + apellidos + ' (' + (c.NIF || '-') + ')',
                                nif:  c.NIF || ''
                            };
                        })
                    };
                },
                cache: true
            },
            allowClear: true,
            placeholder: 'Buscar cliente...',
            minimumInputLength: 2,
            width: '100%'
        }).on('select2:select', function (e) {
            clienteNIFSeleccionado = e.params.data.nif || '';
        }).on('select2:clear', function () {
            clienteNIFSeleccionado = '';
        });
    }

    function poblarAnios() {
        const sel = $('#selectAnio');
        const actual = new Date().getFullYear();
        for (let y = actual; y >= actual - 4; y--) {
            sel.append(new Option(y, y));
        }
        sel.val(actual);
    }

    function cargarCanales() {
        $.getJSON(RECAP_API_BASE + '/canales.php')
            .done(function (resp) {
                const lista = resp && resp.data ? resp.data : [];
                const sel = $('#selectCanal');
                lista.forEach(function (c) {
                    sel.append(new Option(c.Descripcion || c.nombre || c.Codigo, c.Codigo || c.id_canal));
                });
            })
            .fail(function () { console.warn('No se pudieron cargar los canales.'); });
    }

    function cargarFormasPago() {
        $.getJSON(RECAP_API_BASE + '/formas_pago.php')
            .done(function (resp) {
                const lista = resp && resp.data ? resp.data : [];
                const sel = $('#selectFormaPago');
                lista.forEach(function (fp) {
                    const label = fp.Descripcion ? fp.Id_Forma_Pago + ' – ' + fp.Descripcion : fp.Id_Forma_Pago;
                    sel.append(new Option(label, fp.Id_Forma_Pago));
                });
            })
            .fail(function () { console.warn('No se pudieron cargar las formas de pago.'); });
    }

    function cargarSimplificadas() {
        const canal = $('#selectCanal').val();
        const mes   = $('#selectMes').val();

        if (!canal || !mes) {
            simplificadasActuales = [];
            renderSimplificadas();
            actualizarResumen();
            return;
        }

        const params = { id_canal: canal, mes: mes };
        const anio = $('#selectAnio').val();
        if (anio) params.anio = anio;

        $('#simplificadasContainer').html(
            '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>'
        );

        App.api(RECAP_API_BASE + '/facturas.php/simplificadas-pendientes?' + $.param(params))
            .done(function (resp) {
                simplificadasActuales = resp && resp.data && resp.data.facturas ? resp.data.facturas : [];
                renderSimplificadas();
                actualizarResumen();
            })
            .fail(function (xhr) {
                console.error('Error cargando simplificadas:', xhr);
                $('#simplificadasContainer').html(
                    '<div class="alert alert-danger m-3">Error al cargar las facturas simplificadas.</div>'
                );
            });
    }

    function renderSimplificadas() {
        const container = $('#simplificadasContainer');

        if (!simplificadasActuales.length) {
            container.html(
                '<div class="text-center py-4 text-muted">' +
                '<i class="bi bi-inbox fs-2 d-block mb-2"></i>' +
                'No hay facturas simplificadas pendientes con esos filtros.' +
                '</div>'
            );
            actualizarResumen();
            return;
        }

        const verifactuBadge = {
            'ENVIADO':   '<span class="badge bg-success">Enviado</span>',
            'PENDIENTE': '<span class="badge bg-warning text-dark">Pendiente</span>',
            'ERROR':     '<span class="badge bg-danger">Error</span>',
            'ANULADO':   '<span class="badge bg-secondary">Anulado</span>',
        };

        const rows = simplificadasActuales.map(function (f) {
            const estado = f.Cerrada === 'S'
                ? '<span class="fact-badge fact-badge-cerrada">Cerrada</span>'
                : '<span class="fact-badge fact-badge-abierta">Abierta</span>';
            const vfBadge = verifactuBadge[f.Estado_Envio] || '<span class="badge bg-secondary">–</span>';
            return `
            <tr class="fact-tr-hover">
                <td class="text-center" onclick="event.stopPropagation()">
                    <input type="checkbox" class="form-check-input chk-simplificada"
                           value="${escHtml(f.Codigo)}" checked
                           onchange="actualizarResumenPublic()">
                </td>
                <td style="cursor:pointer"
                    onclick="window.location='${BASE}/src/views/facturas/ver.php?codigo=${encodeURIComponent(f.Codigo)}'">
                    <strong>${escHtml(f.Codigo)}</strong>
                </td>
                <td>${fmtFecha(f.Fecha)}</td>
                <td>${escHtml(f.Nombre || '–')}</td>
                <td class="text-end"><strong>${fmtEur(f.Total)}</strong></td>
                <td class="text-center">${estado}</td>
                <td class="text-center">${vfBadge}</td>
            </tr>`;
        }).join('');

        container.html(`
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 fact-table">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:40px">
                                <input type="checkbox" class="form-check-input" id="chkTodas"
                                       checked title="Seleccionar todas"
                                       onchange="toggleTodasSimplificadasPublic(this.checked)">
                            </th>
                            <th>Código</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th class="text-end">Total</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Verifactu</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        `);

        actualizarResumen();
    }

    function actualizarResumen() {
        const seleccionadas = getSeleccionadas();
        const n     = seleccionadas.length;
        const total = simplificadasActuales
            .filter(function (f) { return seleccionadas.includes(f.Codigo); })
            .reduce(function (s, f) { return s + parseFloat(f.Total || 0); }, 0);

        const chkTodas = document.getElementById('chkTodas');
        if (chkTodas) {
            const totalChk = $('.chk-simplificada').length;
            chkTodas.checked       = n === totalChk && totalChk > 0;
            chkTodas.indeterminate = n > 0 && n < totalChk;
        }

        if (n === 0) {
            $('#resumenRecap').text('No hay facturas seleccionadas.');
            $('#btnCrear').prop('disabled', true);
        } else {
            $('#resumenRecap').text(n + ' factura(s) seleccionada(s) · Total: ' + fmtEur(total));
            $('#btnCrear').prop('disabled', false);
        }
    }

    window.actualizarResumenPublic       = actualizarResumen;
    window.toggleTodasSimplificadasPublic = function (checked) {
        $('.chk-simplificada').prop('checked', checked);
        actualizarResumen();
    };

    function getSeleccionadas() {
        return $('.chk-simplificada:checked').map(function () {
            return $(this).val();
        }).get();
    }

    async function crearRecapitulativa() {
        const formData      = new FormData(document.getElementById('recapForm'));
        const seleccionadas = getSeleccionadas();

        const payload = {
            Id_Canal:      formData.get('Id_Canal')      || '',
            Id_Cliente:    formData.get('Id_Cliente')    || '',
            Fecha:         formData.get('Fecha')          || new Date().toISOString().slice(0, 10),
            Id_Forma_Pago: formData.get('Id_Forma_Pago') || '',
            Observaciones: formData.get('Observaciones') || '',
            anio:          formData.get('anio')           || '',
            mes:           formData.get('mes')            || '',
            codigos:       seleccionadas,
        };

        if (!payload.Id_Canal)      { App.notify('Selecciona un canal.', 'warning');                          return; }
        if (!payload.Id_Cliente)    { App.notify('Selecciona un cliente.', 'warning');                        return; }
        if (!payload.Id_Forma_Pago) { App.notify('Debe seleccionar una forma de pago.', 'warning');           return; }
        if (!payload.mes)           { App.notify('Debe seleccionar un mes.', 'warning');                      return; }
        if (!seleccionadas.length)  { App.notify('Selecciona al menos una factura simplificada.', 'warning'); return; }
        if (!simplificadasActuales.length) { App.notify('No hay facturas simplificadas para recapitular.', 'warning'); return; }

        const nifConfirmado = await App.confirmarNIF(clienteNIFSeleccionado, {
            clienteCodigo: $('#selectCliente').val() || '',
            apiBase: RECAP_API_BASE,
        });
        if (nifConfirmado === null) return;
        if (nifConfirmado !== clienteNIFSeleccionado) clienteNIFSeleccionado = nifConfirmado;

        const btn = document.getElementById('btnCrear');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creando y enviando...';

        App.showLoading();
        App.api(RECAP_API_BASE + '/facturas.php/crear-recapitulativa', {
            method:      'POST',
            contentType: 'application/json',
            data:        JSON.stringify(payload),
        })
        .done(function (resp) {
            const codigo = resp.data && resp.data.codigo ? resp.data.codigo : '';
            if (!codigo) {
                App.hideLoading();
                App.notify('No se obtuvo el código de la recapitulativa.', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-send me-1"></i>Crear y enviar a VeriFACTU';
                return;
            }

            App.api(RECAP_API_BASE + '/verifactu.php/enviar', {
                method:      'POST',
                contentType: 'application/json',
                data:        JSON.stringify({ tipo_origen: 'FACTURA', id_documento: codigo }),
            })
            .done(function () {
                App.hideLoading();
                App.notify('Recapitulativa ' + codigo + ' creada y enviada a Hacienda.', 'success');
                setTimeout(function () {
                    window.location.href = BASE + '/src/views/facturas/ver.php?codigo=' + encodeURIComponent(codigo);
                }, 1000);
            })
            .fail(function (xhr) {
                App.hideLoading();
                App.notify('Recapitulativa ' + codigo + ' creada, pero error al enviar a VeriFACTU: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error'), 'warning');
                setTimeout(function () {
                    window.location.href = BASE + '/src/views/facturas/ver.php?codigo=' + encodeURIComponent(codigo);
                }, 1500);
            });
        })
        .fail(function (xhr) {
            App.hideLoading();
            const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error al crear la recapitulativa.';
            App.notify(msg, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send me-1"></i>Crear y enviar a VeriFACTU';
        });
    }

    // ---------- Helpers ----------
    function escHtml(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                              .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    function fmtNum(v, dec) {
        dec = dec !== undefined ? dec : 2;
        return (parseFloat(v) || 0).toLocaleString('es-ES', { minimumFractionDigits: dec, maximumFractionDigits: dec });
    }
    function fmtEur(v) { return fmtNum(v) + ' €'; }
    function fmtFecha(v) {
        if (!v) return '-';
        const d = new Date(v.date || v);
        return d.toLocaleDateString('es-ES', { year: 'numeric', month: 'short', day: 'numeric' });
    }

})();
