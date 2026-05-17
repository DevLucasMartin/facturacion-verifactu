(() => {
    const BASE = (() => {
        const p = window.location.pathname;
        const idx = p.indexOf('/SistemaGestionFacturas/');
        return idx >= 0 ? p.substring(0, idx) + '/SistemaGestionFacturas' : '/SistemaGestionFacturas';
    })();

    const API = BASE + '/src/api';
    const { escapeHtml, debounce, formatCurrency } = FormUtils;
    const { apiGet, apiSend } = FormUtils.makeApiClient(API);

    // ---------- Notify ----------
    function notify(msg, type = 'warning') {
        const panel = document.getElementById('erroresPanel');
        const list  = document.getElementById('erroresList');
        if (!panel || !list) { console.warn(msg); return; }
        panel.className = 'alert mt-3 alert-' + (type === 'warning' ? 'warning' : type);
        panel.style.display = 'block';
        list.innerHTML = `<li>${escapeHtml(msg)}</li>`;
        setTimeout(() => { if (panel && panel.isConnected) panel.style.display = 'none'; }, 5000);
    }

    // ---------- State ----------
    let lineas              = [];
    let clienteActual       = null;
    let tarifaActual        = 1;
    let rePorcentaje        = 0;
    let aplicaREGlobal      = false;
    let claveRegimenGlobal  = '01';
    let tiposIVA            = {};
    let territorioActual    = 'PENINSULAR';
    let lineaEditando       = null;

    // ---------- Init ----------
    document.addEventListener('DOMContentLoaded', async () => {
        try {
            await Promise.all([
                cargarTiposIVA(),
                cargarCanales(),
                cargarTarifas(),
                cargarFormaPago(),
                cargarPaises(),
                cargarTiposCliente(),
            ]);
            inicializarEventos();

            const codigo = document.querySelector('input[name="codigo"]')?.value?.trim();
            if (codigo) await cargarAlbaran(codigo);

            const plantillaCodigo = new URLSearchParams(window.location.search).get('plantilla');
            if (plantillaCodigo && !codigo) await cargarDesdePlantilla(plantillaCodigo);

            renderLineas();
            recalcular();
            mostrarBotonGuardarCliente(false);
        } catch (e) {
            notify(e.message || 'Error inicializando', 'danger');
            console.error(e);
        }
    });

    function inicializarEventos() {
        const selCR = document.getElementById('selectClaveRegimen');
        if (selCR) selCR.innerHTML = buildClaveRegimenOptions(claveRegimenGlobal);

        document.getElementById('selectCanal')?.addEventListener('change', actualizarNumeroDocumento);
        document.getElementById('inputFecha')?.addEventListener('change', actualizarNumeroDocumento);

        ['inputDtoEspecial', 'inputDtoComercial', 'inputDtoPP'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', recalcular);
        });

        document.getElementById('selectTerritorio')?.addEventListener('change', function () {
            cambiarTerritorio(this.value);
        });

        document.getElementById('selectRE')?.addEventListener('change', function () {
            aplicaREGlobal = this.value === '1';
            rePorcentaje = aplicaREGlobal
                ? (clienteActual?.Aplica_RE ? (parseFloat(clienteActual?.RE_Porcentaje) || 0) : 0)
                : 0;
            lineas.forEach(l => { l.aplicaRE = aplicaREGlobal; });
            actualizarVistaRE();
            renderLineas();
            recalcular();
        });

        document.getElementById('selectTarifa')?.addEventListener('change', (e) => {
            tarifaActual = e.target.value;
            if (clienteActual) {
                clienteActual.Tarifa = e.target.value;
                const span = document.getElementById('clienteTarifa');
                if (span) span.textContent = e.target.value;
            }
        });

        document.getElementById('selectFormaPago')?.addEventListener('change', (e) => {
            if (clienteActual) {
                clienteActual.Id_Forma_Pago = e.target.value;
                const span = document.getElementById('clienteFormaPago');
                if (span) span.textContent = e.target.value;
            }
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

        document.getElementById('albaranForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            guardarAlbaran().catch(err => {
                notify(err.message || 'Error guardando albarán', 'danger');
                console.error(err);
            });
        });

        document.querySelectorAll('input[name="modalTipoDoc"]').forEach(r =>
            r.addEventListener('change', actualizarModalDestinatario));
        document.querySelectorAll('input[name="modalDestinatarioTipo"]').forEach(r =>
            r.addEventListener('change', actualizarModalDestinatario));

        document.getElementById('tipoFacturaModal')?.addEventListener('change', (e) => {
            if (e.target.name === 'modalTipoDoc' || e.target.name === 'modalDestinatarioTipo') {
                actualizarModalDestinatario();
            }
        });
    }

    // ---------- Calificación operación ----------
    const CALIFICACION_HELP = {
        S1: { cls: 'alert-primary',   campo: 'CalificacionOperacion', texto: 'Operación <strong>sujeta y no exenta</strong>, sin inversión del sujeto pasivo. La más habitual en ventas nacionales con IVA normal.' },
        S2: { cls: 'alert-primary',   campo: 'CalificacionOperacion', texto: 'Operación <strong>sujeta y no exenta</strong>, con inversión del sujeto pasivo.' },
        N1: { cls: 'alert-secondary', campo: 'CalificacionOperacion', texto: 'Operación <strong>no sujeta</strong> por los artículos 7, 14 u otras causas.' },
        N2: { cls: 'alert-secondary', campo: 'CalificacionOperacion', texto: 'Operación <strong>no sujeta</strong> por las reglas de localización.' },
        E1: { cls: 'alert-success',   campo: 'OperacionExenta',       texto: '<strong>Exenta – Art. 20 LIVA.</strong> Exenciones interiores.' },
        E2: { cls: 'alert-success',   campo: 'OperacionExenta',       texto: '<strong>Exenta – Art. 21 LIVA.</strong> Exportaciones definitivas.' },
        E3: { cls: 'alert-success',   campo: 'OperacionExenta',       texto: '<strong>Exenta – Art. 22 LIVA.</strong> Operaciones asimiladas a exportaciones.' },
        E4: { cls: 'alert-success',   campo: 'OperacionExenta',       texto: '<strong>Exenta – Art. 23 y 24 LIVA.</strong> Zonas francas.' },
        E5: { cls: 'alert-success',   campo: 'OperacionExenta',       texto: '<strong>Exenta – Art. 25 LIVA.</strong> Entregas intracomunitarias de bienes.' },
        E6: { cls: 'alert-success',   campo: 'OperacionExenta',       texto: '<strong>Exenta – Otros artículos LIVA.</strong>' },
    };

    function buildCalifPopoverContent(valor) {
        const info = CALIFICACION_HELP[valor];
        if (!info) return valor;
        return info.texto;
    }

    window.actualizarPopoverCalif = function (index) {
        const valor  = document.getElementById(`calif-select-${index}`)?.value || 'S1';
        const iconEl = document.getElementById(`calif-info-${index}`);
        if (!iconEl) return;
        const popover = bootstrap.Popover.getOrCreateInstance(iconEl);
        popover.setContent({
            '.popover-header': 'Calificación de la operación',
            '.popover-body':   buildCalifPopoverContent(valor),
        });
    };

    const PAISES_UE = new Set([
        'AT','AUT','BE','BEL','BG','BGR','CY','CYP','CZ','CZE',
        'DE','DEU','DK','DNK','EE','EST','FI','FIN','FR','FRA',
        'GR','GRC','HR','HRV','HU','HUN','IE','IRL','IT','ITA',
        'LT','LTU','LU','LUX','LV','LVA','MT','MLT','NL','NLD',
        'PL','POL','PT','PRT','RO','ROU','SE','SWE','SI','SVN','SK','SVK',
    ]);

    function clienteEsEspanol() {
        if (!clienteActual) return false;
        const pais = (clienteActual.Id_Pais || '').toUpperCase().trim();
        return pais === 'ESP' || pais === 'ES';
    }

    function clienteEsUE() {
        if (!clienteActual) return false;
        const pais = (clienteActual.Id_Pais || '').toUpperCase().trim();
        return PAISES_UE.has(pais);
    }

    function actualizarAvisoN2() {
        const aviso   = document.getElementById('avisoClienteN2');
        const textoEl = document.getElementById('avisoClienteN2Texto');
        if (!aviso || !textoEl) return;
        if (!clienteActual) { aviso.style.display = 'none'; return; }

        let mensaje = null;
        if (lineas.some(l => l.calificacion === 'E5') && !clienteEsUE() && !clienteEsEspanol()) {
            mensaje = 'Si la Operación Exenta elegida es E5, debe escoger un cliente de la UE.';
        }
        if (mensaje) { textoEl.textContent = mensaje; aviso.style.display = ''; }
        else          { aviso.style.display = 'none'; }
    }

    function validarClienteN2() {
        if (!clienteActual) return true;
        if (lineas.some(l => l.calificacion === 'E5') && !clienteEsUE() && !clienteEsEspanol()) return false;
        return true;
    }

    function mostrarPopupN2() {
        const textoEl = document.getElementById('errorN2Texto');
        if (textoEl) {
            textoEl.textContent = lineas.some(l => l.calificacion === 'E5') && !clienteEsUE() && !clienteEsEspanol()
                ? 'No puede crear una factura con Operación Exenta E5 sin un cliente de la UE.'
                : 'El cliente seleccionado no es válido para la calificación de operación elegida.';
        }
        const modalEl = document.getElementById('errorN2Modal');
        if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    // ---------- Loaders ----------
    async function cargarTiposIVA() {
        const json = await apiGet('/tiposiva.php');
        tiposIVA = {};
        (json.data || []).forEach(t => { tiposIVA[t.Codigo] = t; });
    }

    // Fix: la API devuelve 'PENINSULA' pero el JS usa 'PENINSULAR'
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

    const CLAVE_REGIMEN_E456       = ['E4', 'E5', 'E6'];
    const CALIFICACION_EXPORTACION = ['S2', 'E2', 'E5'];

    let nifExportacion      = '';
    let nifExportacionVacio = false;

    const CLAVE_REGIMEN_LABELS = {
        '01': '01 – Régimen general',
        '02': '02 – Exportación',
        '03': '03 – Bienes usados / arte / antigüedades',
        '04': '04 – Oro de inversión',
        '05': '05 – Agencias de viajes',
        '06': '06 – Grupo entidades IVA (Nivel Avanzado)',
        '07': '07 – Criterio de caja',
        '08': '08 – IPSI / IGIC',
        '09': '09 – Agencias de viaje mediadoras',
        '10': '10 – Cobros por cuenta de terceros',
        '11': '11 – Arrendamiento de local de negocio',
        '14': '14 – IVA pendiente obras Adm. Pública',
        '15': '15 – IVA pendiente tracto sucesivo',
        '17': '17 – OSS e IOSS',
        '18': '18 – Recargo de equivalencia',
        '19': '19 – REAGYP',
        '20': '20 – Régimen simplificado',
    };

    function buildClaveRegimenOptions(selected = '01') {
        const codes = ['01','02','03','04','05','06','07','08','09','10','11','14','15','17','18','19','20'];
        return codes.map(code => {
            const label = CLAVE_REGIMEN_LABELS[code] || code;
            return `<option value="${code}" ${selected === code ? 'selected' : ''}>${escapeHtml(label)}</option>`;
        }).join('');
    }

    const CALIFS_FUERA_ESPANA = ['E5', 'E2', 'N2'];

    function buildCalifOptions(selected, territorio) {
        const todas = [
            { value: 'S1', label: 'Sujeta y no exenta – Sin inversión (S1)' },
            { value: 'S2', label: 'Sujeta y no exenta – Con inversión (S2)' },
            { value: 'N1', label: 'No sujeta – Art. 7, 14 y otros (N1)' },
            { value: 'N2', label: 'No sujeta por reglas de localización (N2)' },
            { value: 'E1', label: 'Exenta – Art. 20 LIVA (E1)' },
            { value: 'E2', label: 'Exenta – Art. 21 LIVA (exportaciones) (E2)' },
            { value: 'E3', label: 'Exenta – Art. 22 LIVA (E3)' },
            { value: 'E4', label: 'Exenta – Art. 23 y 24 LIVA (E4)' },
            { value: 'E5', label: 'Exenta – Art. 25 LIVA (intracomunitarias UE) (E5)' },
            { value: 'E6', label: 'Exenta – Otros artículos (E6)' },
        ];
        const lista = territorio === 'FUERA_ESPANA'
            ? todas.filter(o => CALIFS_FUERA_ESPANA.includes(o.value))
            : todas.filter(o => !CALIFS_FUERA_ESPANA.includes(o.value));
        return lista.map(o =>
            `<option value="${o.value}" ${(selected || 'S1') === o.value ? 'selected' : ''}>${o.label}</option>`
        ).join('');
    }

    function buildIvaOptions(ivaSeleccionado, showRE = false) {
        const territorioFiltro = territorioActual === 'FUERA_ESPANA' ? 'PENINSULAR' : territorioActual;
        const tipos = Object.values(tiposIVA).filter(t =>
            t.Activo !== 'N' &&
            (!territorioFiltro || resolverTerritorio(t) === territorioFiltro)
        );
        return tipos.map(t => {
            const rePct = Number(t.RE ?? 0);
            const label = showRE && rePct > 0
                ? `${escapeHtml(t.Descripcion || t.Codigo)} (${t.IVA}% + ${rePct}% RE)`
                : `${escapeHtml(t.Descripcion || t.Codigo)} (${t.IVA}%)`;
            return `<option value="${escapeHtml(t.Codigo)}" ${ivaSeleccionado === t.Codigo ? 'selected' : ''}>${label}</option>`;
        }).join('');
    }

    function actualizarVistaRE() {
        const thRE    = document.getElementById('thRE');
        const thCalif = document.getElementById('thCalif');
        if (thRE)    thRE.classList.toggle('d-none', !aplicaREGlobal);
        if (thCalif) thCalif.classList.toggle('d-none', aplicaREGlobal);
        if (!aplicaREGlobal) {
            const reDisp = document.getElementById('reDisplay');
            if (reDisp) reDisp.style.display = 'none';
        }
    }

    function actualizarVistaClaveRegimen() {
        const hayE456 = lineas.some(l => CLAVE_REGIMEN_E456.includes(l.calificacion));
        document.getElementById('labelRE')?.classList.toggle('d-none', hayE456);
        document.getElementById('selectRE')?.classList.toggle('d-none', hayE456);
        document.getElementById('labelClaveRegimen')?.classList.toggle('d-none', !hayE456);
        document.getElementById('selectClaveRegimen')?.classList.toggle('d-none', !hayE456);
    }

    window.cambiarClaveRegimenGlobal = function (valor) {
        claveRegimenGlobal = valor;
    };

    function actualizarVistaNifExportacion() {
        const hayExportacion = lineas.some(l => CALIFICACION_EXPORTACION.includes(l.calificacion));
        const section = document.getElementById('nifExportacionSection');
        if (!section) return;
        section.classList.toggle('d-none', !hayExportacion);
        if (hayExportacion) {
            const inputEl = document.getElementById('inputNifExportacion');
            if (inputEl && inputEl.value === '') {
                const defaultVal = clienteActual?.NIF || document.getElementById('inputIdCliente')?.value || '';
                inputEl.value  = defaultVal.toUpperCase();
                nifExportacion = inputEl.value;
            }
        }
    }

    window.cambiarNifExportacion = function (valor) {
        nifExportacion = valor.trim().toUpperCase();
    };

    window.cambiarNifExportacionVacio = function (checked) {
        nifExportacionVacio = checked;
        const inputEl = document.getElementById('inputNifExportacion');
        if (inputEl) inputEl.disabled = checked;
    };

    function defaultIvaTerritorio() {
        if (!territorioActual) return null;
        return Object.values(tiposIVA).find(t =>
            resolverTerritorio(t) === territorioActual && t.Activo !== 'N'
        )?.Codigo || null;
    }

    window.cambiarTerritorio = function (valor) {
        territorioActual = valor;
        if (territorioActual === 'FUERA_ESPANA') {
            lineas.forEach(linea => {
                if (!CALIFS_FUERA_ESPANA.includes(linea.calificacion)) {
                    linea.calificacion = 'N2';
                }
            });
        } else {
            lineas.forEach(linea => {
                linea.calificacion = 'S1';
                if (resolverTerritorio(tiposIVA[linea.tipoIVA] || {}) !== territorioActual) {
                    linea.tipoIVA = defaultIvaTerritorio() || linea.tipoIVA;
                }
            });
        }
        actualizarVistaRE();
        renderLineas();
        recalcular();
    };

    // Fix: la API devuelve id_canal/nombre (aliases), no Codigo/Descripcion
    async function cargarCanales() {
        const json = await apiGet('/canales.php');
        const select = document.getElementById('selectCanal');
        if (!select) return;
        select.innerHTML = '';
        (json.data || []).forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id_canal;
            opt.textContent = c.nombre;
            select.appendChild(opt);
        });
    }

    async function cargarTarifas() {
        const json = await apiGet('/tarifas.php');
        document.querySelectorAll('.select-tarifa').forEach(select => {
            select.innerHTML = '';
            (json.data || []).forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.Tarifa;
                opt.textContent = c.Tarifa;
                select.appendChild(opt);
            });
        });
    }

    async function cargarFormaPago() {
        const json = await apiGet('/formas_pago.php');
        document.querySelectorAll('.select-forma-pago').forEach(select => {
            select.innerHTML = '';
            (json.data || []).forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.Id_Forma_Pago;
                opt.textContent = c.Descripcion ? `${c.Id_Forma_Pago} – ${c.Descripcion}` : c.Id_Forma_Pago;
                select.appendChild(opt);
            });
        });
    }

    async function cargarPaises() {
        const json = await apiGet('/catalogos.php?tabla=paises');
        const select = document.getElementById('ncPais');
        if (!select) return;
        (json.data || []).forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.label;
            select.appendChild(opt);
        });
    }

    async function cargarTiposCliente() {
        const json = await apiGet('/catalogos.php?tabla=tipos_cliente');
        const select = document.getElementById('ncTipoCliente');
        if (!select) return;
        (json.data || []).forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = t.label;
            select.appendChild(opt);
        });
    }

    async function actualizarNumeroDocumento() {
        const canal = document.getElementById('selectCanal')?.value;
        const fecha = document.getElementById('inputFecha')?.value;
        if (!canal || !fecha) return;
        try {
            const json = await apiGet(`/albaranes.php/next?canal=${encodeURIComponent(canal)}&fecha=${encodeURIComponent(fecha)}`);
            document.getElementById('numeroDocumento').value = json.data?.codigo || '';
        } catch (e) {
            console.warn(e);
        }
    }

    // ---------- Cliente Modal ----------
    window.seleccionarCliente = function () {
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
            const url  = query
                ? `/clientes.php/search?q=${encodeURIComponent(query)}`
                : `/clientes.php?limit=20`;
            const json = await apiGet(url);
            const rows = (json.data || []).map(c => `
                <tr style="cursor:pointer" onclick="seleccionarClienteReal('${escapeHtml(c.Codigo)}')">
                    <td>${escapeHtml(c.Codigo)}</td>
                    <td>${escapeHtml(c.NIF || '-')}</td>
                    <td>${escapeHtml(c.Archivar_Como || '-')}</td>
                    <td>${escapeHtml(c.Id_Forma_Pago || '-')}</td>
                </tr>`).join('');
            tbody.innerHTML = rows || `<tr><td colspan="5" class="text-muted text-center">No hay clientes</td></tr>`;
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-danger text-center">${escapeHtml(e.message)}</td></tr>`;
        }
    }

    window.seleccionarClienteReal = async function (id) {
        try {
            const json = await apiGet(`/clientes.php/${encodeURIComponent(id)}`);
            clienteActual = json.data;
            tarifaActual  = clienteActual?.Tarifa || 1;

            const selTarifa = document.getElementById('selectTarifa');
            if (selTarifa && tarifaActual != null) selTarifa.value = String(tarifaActual);

            const selFormaPago = document.getElementById('selectFormaPago');
            if (selFormaPago && clienteActual?.Id_Forma_Pago != null) {
                const fpNorm = String(clienteActual.Id_Forma_Pago).toUpperCase();
                selFormaPago.value = fpNorm;
                if (!selFormaPago.value) selFormaPago.value = String(clienteActual.Id_Forma_Pago);
                clienteActual.Id_Forma_Pago = selFormaPago.value || clienteActual.Id_Forma_Pago;
            }

            rePorcentaje   = clienteActual?.Aplica_RE ? (parseFloat(clienteActual?.RE_Porcentaje) || 0) : 0;
            aplicaREGlobal = !!(clienteActual?.Aplica_RE == 1);

            const selRE = document.getElementById('selectRE');
            if (selRE) selRE.value = (rePorcentaje > 0) ? '1' : '0';
            actualizarVistaRE();
            lineas.forEach(l => { l.aplicaRE = aplicaREGlobal; });

            document.getElementById('clienteEmpty').style.display   = 'none';
            document.getElementById('clienteData').style.display    = '';
            document.getElementById('inputIdCliente').value         = clienteActual.Codigo || '';
            document.getElementById('clienteNombre').textContent    = clienteActual.Archivar_Como || '';
            document.getElementById('clienteNIF').textContent       = clienteActual.NIF || '';
            document.getElementById('clienteDireccion').textContent = clienteActual.Direccion || '-';
            document.getElementById('clienteFormaPago').textContent = (clienteActual.Id_Forma_Pago || '-').toUpperCase();
            document.getElementById('clienteTarifa').textContent    = tarifaActual;
            document.getElementById('clienteTipoIVA').textContent   = clienteActual.Id_Tipo_IVA || '-';
            document.getElementById('clienteAplicaRE').textContent  = aplicaREGlobal ? 'Sí' : 'No';

            bootstrap.Modal.getOrCreateInstance(document.getElementById('clienteModal')).hide();
            mostrarBtnBorrar(true);
            recalcular();
            actualizarAvisoN2();

            const inputNif = document.getElementById('inputNifExportacion');
            if (inputNif) { inputNif.value = ''; nifExportacion = ''; }
            actualizarVistaNifExportacion();
        } catch (e) {
            notify(e.message || 'Error seleccionando cliente', 'danger');
        }
    };

    window.abrirNuevoCliente = function () {
        $('#nuevoClienteMsg')
            .removeClass('d-none alert-success alert-danger alert-warning')
            .addClass('alert-info')
            .text('Rellena el formulario para crear un cliente nuevo');
        $('#nuevoClienteForm')[0].reset();
        $('#ncRE').val('0');
        $('#ncCodigo').removeClass('is-invalid is-valid');
        $('#ncCodigoError').text('');
        $('#ncNif').removeClass('is-invalid is-valid');
        $('#ncNifError').text('');
        $('#nuevoClienteModal').modal('show');
    };

    const NIF_LETRAS = 'TRWAGMYFPDXBNJZSQVHLCKE';

    window.validarNIF = function (input) {
        const val     = input.value.toUpperCase().trim();
        input.value   = val;
        const errorEl = document.getElementById('ncNifError');

        if (val === '') {
            input.classList.remove('is-invalid', 'is-valid');
            errorEl.textContent = '';
            return true;
        }

        const match = val.match(/^(\d{8})([A-Z])$/);
        if (!match) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            errorEl.textContent = 'Formato incorrecto. Debe ser 8 dígitos seguidos de una letra (ej: 12345678Z).';
            return false;
        }

        const letraEsperada = NIF_LETRAS[parseInt(match[1], 10) % 23];
        if (match[2] !== letraEsperada) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            errorEl.textContent = 'La letra del NIF no es correcta.';
            return false;
        }

        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
        errorEl.textContent = '';
        return true;
    };

    window.validarCodigoCliente = function (input) {
        const val     = input.value.toUpperCase();
        input.value   = val;
        const valid   = /^[A-Z0-9]{0,12}$/.test(val);
        const errorEl = document.getElementById('ncCodigoError');
        if (!valid) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            errorEl.textContent = 'Solo letras y números, máx. 12 caracteres.';
        } else if (val.length > 0) {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            errorEl.textContent = '';
        } else {
            input.classList.remove('is-invalid', 'is-valid');
            errorEl.textContent = '';
        }
    };

    $(document).on('submit', '#nuevoClienteForm', async function (e) {
        e.preventDefault();
        const submitBtn = $(this).find('[type=submit]');
        submitBtn.prop('disabled', true);

        const codigoVal = ($('#ncCodigo').val() || '').trim().toUpperCase();
        if (!codigoVal || !/^[A-Z0-9]{1,12}$/.test(codigoVal)) {
            $('#ncCodigo').addClass('is-invalid');
            document.getElementById('ncCodigoError').textContent = 'El código es obligatorio y solo puede contener letras y números (máx. 12).';
            submitBtn.prop('disabled', false);
            return;
        }

        if (!window.validarNIF(document.getElementById('ncNif'))) {
            submitBtn.prop('disabled', false);
            return;
        }

        const payload = {
            Codigo:               codigoVal,
            Nombre:               ($('#ncNombre').val()            || '').trim(),
            Apellidos:            ($('#ncApellidos').val()         || '').trim(),
            Organizacion:         ($('#ncOrganizacion').val()      || '').trim(),
            Archivar:             ($('#ncArchivar').val()          || '').trim(),
            NIF:                  ($('#ncNif').val()               || '').trim(),
            Direccion:            ($('#ncDireccion').val()         || '').trim(),
            Poblacion:            ($('#ncPoblacion').val()         || '').trim(),
            Provincia:            ($('#ncProvincia').val()         || '').trim(),
            Id_Pais:              ($('#ncPais').val()              || '').trim(),
            Id_Tipo_Cliente:      ($('#ncTipoCliente').val()       || '').trim(),
            Id_Forma_Pago:        ($('#selectFormaPagoNuevo').val()|| '').trim(),
            Tarifa:               parseInt($('#selectTarifaNuevo').val(), 10) || 1,
            Aplica_RE:            parseInt($('#ncRE').val(), 10) === 1 ? 1 : 0,
            Descuento_Especial:   parseFloat($('#ncDtoEspecial').val())  || 0,
            Descuento_Comercial:  parseFloat($('#ncDtoComercial').val()) || 0,
            Descuento_Pronto_Pago: parseFloat($('#ncDtoPP').val())       || 0,
            Activo:               $('#ncActivo').val() || 'S',
        };

        try {
            // Fix: orden correcto apiSend(path, body, method)
            const json = await apiSend('/clientes.php', payload, 'POST');
            const cli  = json.data;
            await window.seleccionarClienteReal(cli.Codigo);

            $('#nuevoClienteMsg')
                .removeClass('d-none alert-info alert-danger')
                .addClass('alert-success')
                .text('Cliente creado correctamente.');

            setTimeout(() => { $('#nuevoClienteModal').modal('hide'); }, 800);
            mostrarBotonGuardarCliente(false);
        } catch (err) {
            const esDuplicado = err.message === 'Codigo de cliente ya existente';
            $('#nuevoClienteMsg')
                .removeClass('d-none alert-info alert-success alert-danger alert-warning')
                .addClass(esDuplicado ? 'alert-warning' : 'alert-danger')
                .text(err.message || 'Error al guardar el cliente.');
            if (esDuplicado) {
                $('#ncCodigo').addClass('is-invalid');
                document.getElementById('ncCodigoError').textContent = 'Este código ya existe.';
            }
        } finally {
            submitBtn.prop('disabled', false);
        }
    });

    function mostrarBotonGuardarCliente(show) {
        $('#btnGuardarCliente').toggleClass('d-none', !show);
    }

    function mostrarBtnBorrar(show) {
        const btn = document.getElementById('btnBorrarCliente');
        if (btn) btn.style.display = show ? '' : 'none';
    }

    window.borrarCliente = function () {
        clienteActual  = null;
        rePorcentaje   = 0;
        aplicaREGlobal = false;

        const selRE = document.getElementById('selectRE');
        if (selRE) selRE.value = '0';
        actualizarVistaRE();
        lineas.forEach(l => { l.aplicaRE = false; });

        document.getElementById('inputIdCliente').value         = '';
        document.getElementById('clienteNombre').textContent    = '';
        document.getElementById('clienteNIF').textContent       = '';
        document.getElementById('clienteDireccion').textContent = '';
        document.getElementById('clienteFormaPago').textContent = '';
        document.getElementById('clienteTarifa').textContent    = '';
        document.getElementById('clienteTipoIVA').textContent   = '';
        document.getElementById('clienteAplicaRE').textContent  = '';

        const reDisp = document.getElementById('reDisplay');
        if (reDisp) reDisp.style.display = 'none';

        document.getElementById('clienteData').style.display  = 'none';
        document.getElementById('clienteEmpty').style.display = '';

        mostrarBotonGuardarCliente(false);
        mostrarBtnBorrar(false);
        recalcular();
        actualizarAvisoN2();
    };

    // ---------- Artículo Modal ----------
    window.agregarLinea = function () {
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
            const url  = query
                ? `/articulos.php/search?q=${encodeURIComponent(query)}&tarifa=${encodeURIComponent(tarifaActual)}`
                : `/articulos.php?page=1&per_page=25`;
            const json  = await apiGet(url);
            const items = Array.isArray(json.data) ? json.data : (json.data?.items || json.data?.rows || []);
            const rows  = items.map(a => {
                const precio = Number(a['Precio_Venta_' + tarifaActual] ?? a.Precio_Venta_1 ?? a.Precio ?? 0);
                return `<tr style="cursor:pointer" onclick="seleccionarArticulo('${escapeHtml(a.Codigo)}')">
                    <td>${escapeHtml(a.Codigo)}</td>
                    <td>${escapeHtml(a.Descripcion || '')}</td>
                    <td>${escapeHtml(a.Id_Tipo_IVA || '')}</td>
                    <td>${formatCurrency(precio)}</td>
                </tr>`;
            }).join('');
            tbody.innerHTML = rows || `<tr><td colspan="5" class="text-muted text-center">No hay artículos</td></tr>`;
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-danger text-center">${escapeHtml(e.message)}</td></tr>`;
        }
    }

    window.seleccionarArticulo = async function (codigo) {
        try {
            const json     = await apiGet(`/articulos.php/${encodeURIComponent(codigo)}`);
            const articulo = json.data;
            const precio   = Number(articulo['Precio_Venta_' + tarifaActual] ?? articulo.Precio_Venta_1 ?? 0);
            const tipoIVARaw = articulo.Id_Tipo_IVA || clienteActual?.Id_Tipo_IVA || 'GEN';
            const tipoIVA  = (territorioActual && resolverTerritorio(tiposIVA[tipoIVARaw] || {}) !== territorioActual)
                ? (defaultIvaTerritorio() || tipoIVARaw)
                : tipoIVARaw;
            const descuento = Number(articulo.Descuento ?? 0);

            if (lineaEditando !== null && lineaEditando < lineas.length) {
                lineas[lineaEditando] = {
                    idArticulo:   articulo.Codigo,
                    descripcion:  articulo.Descripcion,
                    cantidad:     lineas[lineaEditando].cantidad,
                    precio, descuento, tipoIVA,
                    calificacion: lineas[lineaEditando].calificacion || 'S1',
                    aplicaRE:     lineas[lineaEditando].aplicaRE ?? aplicaREGlobal,
                };
            } else {
                lineas.push({ idArticulo: articulo.Codigo, descripcion: articulo.Descripcion, cantidad: 1, precio, descuento, tipoIVA, calificacion: 'S1', aplicaRE: aplicaREGlobal });
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
    function esExentoONoSujeto(calif) {
        return ['N1','N2','E1','E2','E3','E4','E5','E6'].includes(calif);
    }

    function textoIvaExento(calif) {
        return (calif === 'N1' || calif === 'N2') ? 'No sujeto' : 'Exento';
    }

    function renderLineas() {
        const tbody = document.getElementById('lineasBody');
        if (!tbody) return;

        if (lineas.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="10" class="text-center text-muted py-4">
                        <i class="bi bi-cart-plus fs-1 d-block mb-2"></i>
                        Añada líneas al albarán
                    </td>
                </tr>`;
            return;
        }

        tbody.innerHTML = lineas.map((linea, index) => {
            const tipo         = tiposIVA[linea.tipoIVA] || { IVA: 21, RE: 0 };
            const importeBruto = linea.cantidad * linea.precio;
            const base         = importeBruto * (1 - (linea.descuento || 0) / 100);
            const ivaPct       = Number(tipo.IVA ?? 21);
            const rePct        = linea.aplicaRE ? Number(tipo.RE ?? 0) : 0;
            const IVACalc      = (linea.calificacion === 'S2' || esExentoONoSujeto(linea.calificacion)) ? 0 : base * ((ivaPct + rePct) / 100);
            const reLinea      = linea.aplicaRE ? '1' : '0';

            const reTd = aplicaREGlobal ? `
                <td>
                    <select class="form-control form-control-sm fact-form-control"
                        id="re-select-${index}"
                        onchange="actualizarLinea(${index}, 're', this.value)">
                        <option value="0" ${!linea.aplicaRE ? 'selected' : ''}>No</option>
                        <option value="1" ${linea.aplicaRE ? 'selected' : ''}>Sí</option>
                    </select>
                </td>` : '';

            const califTd = !aplicaREGlobal ? `
                <td>
                    <input type="hidden" name="lineas[${index}][Calificacion]" value="${escapeHtml(linea.calificacion || 'S1')}">
                    <div class="d-flex align-items-center gap-1">
                        <select class="form-control form-control-sm fact-form-control"
                            id="calif-select-${index}"
                            onchange="actualizarLinea(${index}, 'calificacion', this.value); actualizarPopoverCalif(${index})">
                            ${buildCalifOptions(linea.calificacion, territorioActual)}
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
                            data-bs-content="${escapeHtml(buildCalifPopoverContent(linea.calificacion || 'S1'))}">
                            &#9432;
                        </span>
                    </div>
                </td>` : '';

            return `
                <tr data-index="${index}">
                    <td>${index + 1}</td>
                    <td>
                        <input type="hidden" name="lineas[${index}][Id_Articulo]" value="${escapeHtml(linea.idArticulo)}">
                        <input type="hidden" name="lineas[${index}][Descripcion]" value="${escapeHtml(linea.descripcion)}">
                        <input type="hidden" name="lineas[${index}][Id_Tipo_IVA]" value="${escapeHtml(linea.tipoIVA)}">
                        <input type="hidden" name="lineas[${index}][Aplica_RE]" id="re-hidden-${index}" value="${reLinea}">
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
                    <td class="${esExentoONoSujeto(linea.calificacion) ? 'iva-exento-cell' : ''}">
                        <select class="form-control form-control-sm fact-form-control"
                            id="iva-select-${index}" name="lineas[${index}][Id_Tipo_IVA]"
                            onchange="actualizarLinea(${index}, 'tipoIVA', this.value)"
                            style="${esExentoONoSujeto(linea.calificacion) ? 'display:none' : ''}">
                            ${buildIvaOptions(linea.tipoIVA)}
                        </select>
                        <span id="iva-text-${index}" class="text-muted fw-bold"
                            style="${esExentoONoSujeto(linea.calificacion) ? '' : 'display:none'}">
                            ${textoIvaExento(linea.calificacion)}
                        </span>
                    </td>
                    ${reTd}
                    ${califTd}
                    <td class="text-end"><strong id="base-linea-${index}">${formatCurrency(base)}</strong></td>
                    <td class="text-end"><strong id="iva-linea-${index}">${esExentoONoSujeto(linea.calificacion) ? textoIvaExento(linea.calificacion) : formatCurrency(IVACalc)}</strong></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-danger fact-btn"
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

        actualizarVistaClaveRegimen();
    }

    window.actualizarLinea = function (index, campo, valor) {
        if (campo === 'tipoIVA') {
            lineas[index].tipoIVA = valor;
        } else if (campo === 're') {
            lineas[index].aplicaRE = valor === '1';
            const hidden = document.getElementById(`re-hidden-${index}`);
            if (hidden) hidden.value = valor;
            const ivaSelect = document.getElementById(`iva-select-${index}`);
            if (ivaSelect) ivaSelect.innerHTML = buildIvaOptions(lineas[index].tipoIVA, lineas[index].aplicaRE);
        } else if (campo === 'calificacion') {
            lineas[index].calificacion = valor;
            const hiddenInput = document.querySelector(`input[name="lineas[${index}][Calificacion]"]`);
            if (hiddenInput) hiddenInput.value = valor;

            const sinIVA    = esExentoONoSujeto(valor);
            const ivaSelect = document.getElementById(`iva-select-${index}`);
            const ivaText   = document.getElementById(`iva-text-${index}`);

            if (ivaSelect && ivaText) {
                ivaSelect.style.display = sinIVA ? 'none' : '';
                ivaText.style.display   = sinIVA ? '' : 'none';
                if (sinIVA) ivaText.textContent = textoIvaExento(valor);
            }

            actualizarVistaClaveRegimen();
            actualizarVistaNifExportacion();
        } else {
            const n = parseFloat(valor);
            lineas[index][campo] = isNaN(n) ? 0 : n;
        }

        if (['cantidad','precio','descuento','tipoIVA','re','calificacion'].includes(campo)) {
            const baseImp         = document.getElementById(`base-linea-${index}`);
            const lineaTotal      = document.getElementById(`iva-linea-${index}`);
            const linea_cantidad  = Number(lineas[index].cantidad)  || 0;
            const linea_precio    = Number(lineas[index].precio)     || 0;
            const linea_descuento = Number(lineas[index].descuento)  || 0;
            const tipo            = tiposIVA[lineas[index].tipoIVA] || {};
            const linea_IVA       = Number(tipo.IVA ?? 21);
            const linea_RE        = lineas[index].aplicaRE ? Number(tipo.RE ?? 0) : 0;
            const importe_bruto   = linea_cantidad * linea_precio;
            const base_imponible  = importe_bruto - importe_bruto * (linea_descuento / 100);
            const califActual     = lineas[index].calificacion;
            const IVA_calculado   = (califActual === 'S2' || esExentoONoSujeto(califActual)) ? 0 : base_imponible * ((linea_IVA + linea_RE) / 100);

            if (baseImp)    baseImp.textContent    = formatCurrency(base_imponible);
            if (lineaTotal) lineaTotal.textContent = esExentoONoSujeto(califActual) ? textoIvaExento(califActual) : formatCurrency(IVA_calculado);
        }

        recalcular();
    };

    window.eliminarLinea = function (index) {
        lineas.splice(index, 1);
        renderLineas();
        recalcular();
        actualizarAvisoN2();
    };

    // ---------- Totales ----------
    function recalcular() {
        if (!lineas.length) {
            setTotals(0, 0, 0, 0, 0);
            const dDisp = document.getElementById('descuentosDisplay');
            const rDisp = document.getElementById('reDisplay');
            const eDisp = document.getElementById('erroresPanel');
            if (dDisp) dDisp.style.display = 'none';
            if (rDisp) rDisp.style.display = 'none';
            const rLbl = document.getElementById('reLabel');
            if (rLbl) rLbl.style.display = 'none';
            if (eDisp) eDisp.style.display = 'none';
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
                    iva:    l.calificacion === 'S2' ? 0 : Number(t.IVA ?? t.iva ?? 21),
                    re:     l.aplicaRE ? Number(t.RE ?? t.re ?? 0) : 0,
                },
            };
        });

        const descuentos = {
            especial:      parseFloat(document.getElementById('inputDtoEspecial').value)  || 0,
            comercial:     parseFloat(document.getElementById('inputDtoComercial').value) || 0,
            pp:            parseFloat(document.getElementById('inputDtoPP').value)        || 0,
            tipoDocumento: 'ALBARAN',
        };

        const r = FacturacionCalculos.calcularFactura(lineasParaCalculo, descuentos, 0);

        const elSub  = document.getElementById('subtotalDisplay');
        const elBase = document.getElementById('baseImponibleDisplay');
        const elIVA  = document.getElementById('ivaDisplay');
        const elTot  = document.getElementById('totalDisplay');
        if (elSub)  elSub.textContent  = formatCurrency(r.subtotal);
        if (elBase) elBase.textContent = formatCurrency(r.baseImponible);
        if (elIVA)  elIVA.textContent  = formatCurrency(r.importeIVA);
        if (elTot)  elTot.textContent  = formatCurrency(r.total);

        const totalDescuentos = (r.descuentos?.importeDtoLineas   || 0)
                              + (r.descuentos?.importeDtoEspecial  || 0)
                              + (r.descuentos?.importeDtoComercial || 0)
                              + (r.descuentos?.importeDtoPP        || 0);

        const dDisp = document.getElementById('descuentosDisplay');
        const dVal  = document.getElementById('descuentosValue');
        if (dDisp) dDisp.style.display = totalDescuentos > 0 ? '' : 'none';
        if (dVal && totalDescuentos > 0) dVal.textContent = '-' + formatCurrency(totalDescuentos);

        const importeREMostrar = aplicaREGlobal ? r.importeRE : 0;
        const rDisp  = document.getElementById('reDisplay');
        const rLabel = document.getElementById('reLabel');
        const rVal   = document.getElementById('reValue');
        if (rDisp)  rDisp.style.display  = importeREMostrar > 0 ? '' : 'none';
        if (rLabel) rLabel.style.display = importeREMostrar > 0 ? '' : 'none';
        if (rVal)  rVal.textContent    = formatCurrency(importeREMostrar);

        const eDisp = document.getElementById('erroresPanel');
        const eList = document.getElementById('erroresList');
        if (eDisp && eList) {
            if (r.errores?.length) {
                eDisp.style.display = '';
                eList.innerHTML = r.errores.map(e => `<li>${escapeHtml(e)}</li>`).join('');
            } else {
                eDisp.style.display = 'none';
            }
        }
    }

    function calcularTotalConTipoDoc(tipoDoc) {
        if (!lineas.length) return 0;
        const lineasParaCalculo = lineas.map(l => {
            const sinIVA = esExentoONoSujeto(l.calificacion);
            const t = sinIVA ? {} : (tiposIVA[l.tipoIVA] || {});
            return {
                cantidad:  Number(l.cantidad)  || 0,
                precio:    Number(l.precio)    || 0,
                descuento: Number(l.descuento) || 0,
                tipoIVA: sinIVA ? null : {
                    codigo: l.tipoIVA,
                    iva:    l.calificacion === 'S2' ? 0 : Number(t.IVA ?? t.iva ?? 21),
                    re:     l.aplicaRE ? Number(t.RE ?? t.re ?? 0) : 0,
                },
            };
        });
        const descuentos = {
            especial:      parseFloat(document.getElementById('inputDtoEspecial')?.value)  || 0,
            comercial:     parseFloat(document.getElementById('inputDtoComercial')?.value) || 0,
            pp:            parseFloat(document.getElementById('inputDtoPP')?.value)        || 0,
            tipoDocumento: tipoDoc,
        };
        return FacturacionCalculos.calcularFactura(lineasParaCalculo, descuentos, 0).total;
    }

    function setTotals(sub, _dto, base, iva, total) {
        const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = formatCurrency(val); };
        set('subtotalDisplay', sub);
        set('baseImponibleDisplay', base);
        set('ivaDisplay', iva);
        set('reValue', 0);
        set('totalDisplay', total);
    }

    function actualizarModalDestinatario() {
        const tipo  = document.querySelector('input[name="modalTipoDoc"]:checked')?.value;
        const panel = document.getElementById('modalDestinatarioSimplificada');
        const aviso = document.getElementById('modalAvisoSimplificada');
        if (!panel || !aviso) return;

        panel.style.display = (tipo === 'SIMPLIFICADA') ? '' : 'none';
        if (tipo !== 'SIMPLIFICADA') { aviso.style.display = 'none'; return; }

        const esEmpresa = document.querySelector('input[name="modalDestinatarioTipo"]:checked')?.value === 'empresa';
        const limite    = esEmpresa ? 3000 : 400;
        const total     = calcularTotalConTipoDoc('SIMPLIFICADA');

        aviso.style.display = total > limite ? '' : 'none';
        const txtEl = document.getElementById('modalAvisoSimplificadaTexto');
        if (txtEl) {
            txtEl.innerHTML = esEmpresa
                ? 'El destinatario es <strong>empresa o profesional</strong>: límite <strong>3.000,00 €</strong> (IVA incl.). Usa una factura ordinaria.'
                : 'El destinatario no es empresa ni profesional: límite <strong>400,00 €</strong> (IVA incl.). Usa una factura ordinaria.';
        }
    }

    // ---------- Guardar ----------
    async function guardarAlbaran(redirect = true, tipoDoc = null) {
        const esSimplificada = tipoDoc === 'SIMPLIFICADA';
        if (!esSimplificada && !document.getElementById('inputIdCliente').value) {
            notify('Debe seleccionar un cliente', 'warning');
            return;
        }
        if (!lineas.length) {
            notify('Debe añadir al menos una línea', 'warning');
            return;
        }
        if (!validarClienteN2()) {
            mostrarPopupN2();
            return;
        }

        const codigoEdicion = document.querySelector('input[name="codigo"]')?.value?.trim();
        if (!codigoEdicion) await actualizarNumeroDocumento();

        const formData = {
            Id_Canal:            document.getElementById('selectCanal').value,
            Fecha:               document.getElementById('inputFecha').value,
            Id_Cliente:          document.getElementById('inputIdCliente').value,
            Observaciones:       document.querySelector('textarea[name="Observaciones"]')?.value || '',
            Descuento_Especial:  parseFloat(document.getElementById('inputDtoEspecial').value)  || 0,
            Descuento_PP:        parseFloat(document.getElementById('inputDtoPP').value)         || 0,
            Descuento_Comercial: parseFloat(document.getElementById('inputDtoComercial').value) || 0,
            Id_Forma_Pago:       document.getElementById('selectFormaPago')?.value || clienteActual?.Id_Forma_Pago || '',
            Id_Tarifa:           document.getElementById('selectTarifa')?.value    || clienteActual?.Tarifa        || '',
            lineas: lineas.map(l => ({
                Id_Articulo:   l.idArticulo,
                Descripcion:   l.descripcion,
                Cantidad:      l.cantidad,
                Precio:        l.precio,
                Descuento:     l.descuento,
                Id_Tipo_IVA:   l.tipoIVA,
                Aplica_RE:     l.aplicaRE ? 1 : 0,
                Calificacion:  l.calificacion || 'S1',
                Clave_Regimen: CLAVE_REGIMEN_E456.includes(l.calificacion) ? claveRegimenGlobal : '01',
            })),
        };

        const path   = codigoEdicion ? `/albaranes.php/${encodeURIComponent(codigoEdicion)}` : `/albaranes.php`;
        const method = codigoEdicion ? 'PUT' : 'POST';

        // Fix: orden correcto apiSend(path, body, method) + comprobación de error
        const json = await apiSend(path, formData, method);
        if (json.success === false) {
            const detail = json.error_detail?.message || json.errors?.join(', ') || '';
            throw new Error((json.message || 'Error al guardar albarán') + (detail ? ': ' + detail : ''));
        }
        notify('Albarán guardado correctamente', 'success');

        if (redirect) {
            setTimeout(() => {
                window.location.href = BASE + '/src/views/albaranes/listado.php';
            }, 800);
        }

        return json;
    }

    // ---------- Cargar albarán (edición) ----------
    async function cargarAlbaran(codigo) {
        const json = await apiGet(`/albaranes.php/${encodeURIComponent(codigo)}`);
        const f    = json.data;

        document.getElementById('selectCanal').value      = f.Id_Canal;
        document.getElementById('inputFecha').value       = (f.Fecha || '').substring(0, 10);
        document.getElementById('numeroDocumento').value  = f.Codigo || '';

        const estado     = f.Cerrado === 'S' ? 'Cerrado' : (f.Facturado === 'S' ? 'Facturado' : 'Abierto');
        const badgeClass = f.Cerrado === 'S' ? 'bg-success' : (f.Facturado === 'S' ? 'bg-info' : 'bg-warning');
        const estadoEl   = document.getElementById('estadoDocumento');
        if (estadoEl) estadoEl.innerHTML = `<span class="badge ${badgeClass}">${estado}</span>`;

        if (f.Id_Cliente) await window.seleccionarClienteReal(f.Id_Cliente);

        document.getElementById('inputDtoEspecial').value  = f.Descuento_Especial  || 0;
        document.getElementById('inputDtoComercial').value = f.Descuento_Comercial || 0;
        document.getElementById('inputDtoPP').value        = f.Descuento_PP        || 0;

        const obsEl = document.querySelector('textarea[name="Observaciones"]');
        if (obsEl) obsEl.value = f.Observaciones || '';

        const selFP = document.getElementById('selectFormaPago');
        if (selFP && f.Id_Forma_Pago) {
            const fpNorm = String(f.Id_Forma_Pago).toUpperCase();
            selFP.value  = fpNorm;
            if (!selFP.value) selFP.value = String(f.Id_Forma_Pago);
        }

        const selTarifa = document.getElementById('selectTarifa');
        if (selTarifa && f.Id_Tarifa) selTarifa.value = String(f.Id_Tarifa);

        if (Array.isArray(f.lineas)) {
            lineas = f.lineas.map(l => ({
                idArticulo:   l.Id_Articulo,
                descripcion:  l.Descripcion,
                cantidad:     parseFloat(l.Cantidad)  || 0,
                precio:       parseFloat(l.Precio)    || 0,
                descuento:    parseFloat(l.Descuento) || 0,
                tipoIVA:      l.Id_Tipo_IVA,
                calificacion: l.Calificacion || l.CalificacionOperacion || 'S1',
                aplicaRE:     !!(l.Aplica_RE === 'S' || l.Aplica_RE == 1 || l.Aplica_RE === true),
            }));
            const e456 = f.lineas.find(l => CLAVE_REGIMEN_E456.includes(l.Calificacion || l.CalificacionOperacion || ''));
            if (e456 && e456.Clave_Regimen) {
                claveRegimenGlobal = e456.Clave_Regimen;
                const selCR = document.getElementById('selectClaveRegimen');
                if (selCR) selCR.value = claveRegimenGlobal;
            }
        }
    }

    // ---------- Cargar desde plantilla ----------
    async function cargarDesdePlantilla(codigo) {
        const json = await apiGet(`/albaranes.php/${encodeURIComponent(codigo)}`);
        const f    = json.data;

        if (f.Id_Canal) document.getElementById('selectCanal').value = f.Id_Canal;
        if (f.Id_Cliente) await window.seleccionarClienteReal(f.Id_Cliente);

        document.getElementById('inputDtoEspecial').value  = f.Descuento_Especial  || 0;
        document.getElementById('inputDtoComercial').value = f.Descuento_Comercial || 0;
        document.getElementById('inputDtoPP').value        = f.Descuento_PP        || 0;

        const obsEl = document.querySelector('textarea[name="Observaciones"]');
        if (obsEl) obsEl.value = f.Observaciones || '';

        const selFP = document.getElementById('selectFormaPago');
        if (selFP && f.Id_Forma_Pago) {
            selFP.value = String(f.Id_Forma_Pago).toUpperCase();
            if (!selFP.value) selFP.value = String(f.Id_Forma_Pago);
        }

        const selTarifa = document.getElementById('selectTarifa');
        if (selTarifa && f.Id_Tarifa) selTarifa.value = String(f.Id_Tarifa);

        if (Array.isArray(f.lineas)) {
            lineas = f.lineas.map(l => ({
                idArticulo:   l.Id_Articulo,
                descripcion:  l.Descripcion,
                cantidad:     parseFloat(l.Cantidad)  || 0,
                precio:       parseFloat(l.Precio)    || 0,
                descuento:    parseFloat(l.Descuento) || 0,
                tipoIVA:      l.Id_Tipo_IVA,
                calificacion: l.Calificacion || 'S1',
                aplicaRE:     !!(l.Aplica_RE === 'S' || l.Aplica_RE == 1 || l.Aplica_RE === true),
            }));
        }
    }

    // ---------- Guardar como Plantilla ----------
    window.abrirModalGuardarPlantilla = function () {
        const modalEl = document.getElementById('guardarPlantillaModal');
        if (!modalEl) return;
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        const input = document.getElementById('inputNombrePlantilla');
        if (input) input.value = '';
        modal.show();

        document.getElementById('btnConfirmarPlantilla').onclick = async function () {
            const nombre = (document.getElementById('inputNombrePlantilla')?.value || '').trim();
            if (!nombre) { document.getElementById('inputNombrePlantilla')?.focus(); return; }
            modal.hide();
            try {
                await guardarComoPlantilla(nombre);
            } catch (err) {
                notify(err.message || 'Error guardando plantilla', 'danger');
            }
        };
    };

    async function guardarComoPlantilla(nombre) {
        if (!document.getElementById('inputIdCliente').value) {
            notify('Debe seleccionar un cliente', 'warning'); return;
        }
        if (!lineas.length) {
            notify('Debe añadir al menos una línea', 'warning'); return;
        }
        if (!validarClienteN2()) { mostrarPopupN2(); return; }

        const formData = {
            Id_Canal:            document.getElementById('selectCanal').value,
            Fecha:               document.getElementById('inputFecha').value,
            Id_Cliente:          document.getElementById('inputIdCliente').value,
            Observaciones:       document.querySelector('textarea[name="Observaciones"]')?.value || '',
            Descuento_Especial:  parseFloat(document.getElementById('inputDtoEspecial').value)   || 0,
            Descuento_PP:        parseFloat(document.getElementById('inputDtoPP').value)          || 0,
            Descuento_Comercial: parseFloat(document.getElementById('inputDtoComercial').value)  || 0,
            Id_Forma_Pago:       document.getElementById('selectFormaPago')?.value || clienteActual?.Id_Forma_Pago || '',
            Id_Tarifa:           document.getElementById('selectTarifa')?.value    || clienteActual?.Tarifa        || '',
            Es_Plantilla:        'S',
            Nombre_Plantilla:    nombre,
            lineas: lineas.map(l => ({
                Id_Articulo: l.idArticulo,
                Descripcion: l.descripcion,
                Cantidad:    l.cantidad,
                Precio:      l.precio,
                Descuento:   l.descuento,
                Id_Tipo_IVA: l.tipoIVA,
            })),
        };

        // Fix: orden correcto apiSend(path, body, method)
        await apiSend('/albaranes.php', formData, 'POST');
        notify('Plantilla guardada correctamente', 'success');
        setTimeout(() => {
            window.location.href = BASE + '/src/views/albaranes/listado.php';
        }, 800);
    }

    // ---------- Facturar y enviar a VeriFACTU ----------
    window.abrirModalFacturarEnviar = function () {
        document.querySelector('input[name="modalTipoDoc"][value="FACTURA"]').checked = true;
        const destParticular = document.querySelector('input[name="modalDestinatarioTipo"][value="particular"]');
        if (destParticular) destParticular.checked = true;
        actualizarModalDestinatario();

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('facturarEnviarModal'));
        modal.show();

        document.getElementById('btnConfirmarFacturar').onclick = async function () {
            const tipoDoc = document.querySelector('input[name="modalTipoDoc"]:checked')?.value || 'FACTURA';
            const fecha   = new Date().toISOString().slice(0, 10);

            if (tipoDoc === 'SIMPLIFICADA') {
                const esEmpresa = document.querySelector('input[name="modalDestinatarioTipo"]:checked')?.value === 'empresa';
                const limite    = esEmpresa ? 3000 : 400;
                const total     = calcularTotalConTipoDoc('SIMPLIFICADA');
                if (total > limite) {
                    notify(`El total supera el límite para factura simplificada. Usa una factura ordinaria.`, 'warning');
                    return;
                }
            }

            modal.hide();
            await ejecutarFacturarYEnviar(tipoDoc, fecha);
        };
    };

    // ---------- Guardar y Facturar (sin VeriFACTU) ----------
    window.guardarYFacturar = function () {
        if (!lineas.length) {
            notify('Debe añadir al menos una línea', 'warning');
            return;
        }
        if (!validarClienteN2()) { mostrarPopupN2(); return; }

        const modalEl = document.getElementById('tipoFacturaModal');
        if (!modalEl) return;

        document.getElementById('modalTipoDocFactura').checked = true;
        actualizarModalDestinatario();

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        document.getElementById('btnConfirmarTipoFactura').onclick = async function () {
            const tipoDoc   = document.querySelector('input[name="modalTipoDoc"]:checked')?.value || 'FACTURA';
            const idCliente = document.getElementById('inputIdCliente').value;

            if (tipoDoc !== 'SIMPLIFICADA' && !idCliente) {
                notify('Debe seleccionar un cliente para crear una factura ordinaria.', 'warning');
                return;
            }

            if (tipoDoc === 'SIMPLIFICADA') {
                const esEmpresa = document.querySelector('input[name="modalDestinatarioTipo"]:checked')?.value === 'empresa';
                const limite    = esEmpresa ? 3000 : 400;
                const total     = calcularTotalConTipoDoc('SIMPLIFICADA');
                if (total > limite) {
                    notify('El total supera el límite para factura simplificada. Usa una factura ordinaria.', 'warning');
                    return;
                }
            }

            modal.hide();

            try {
                App.showLoading?.();

                const fecha    = new Date().toISOString().slice(0, 10);
                const lineasPayload = lineas.map(l => ({
                    Id_Articulo:   l.idArticulo,
                    Descripcion:   l.descripcion,
                    Cantidad:      l.cantidad,
                    Precio:        l.precio,
                    Descuento:     l.descuento,
                    Id_Tipo_IVA:   l.tipoIVA,
                    Aplica_RE:     l.aplicaRE ? 1 : 0,
                    Calificacion:  l.calificacion || 'S1',
                    Clave_Regimen: CLAVE_REGIMEN_E456.includes(l.calificacion) ? claveRegimenGlobal : '01',
                }));

                let albCodigo = null;

                // Si hay cliente, guardar el albarán primero (flujo normal)
                if (idCliente) {
                    const saveJson = await guardarAlbaran(false, tipoDoc);
                    albCodigo = saveJson?.data?.codigo;
                    if (!albCodigo) throw new Error('No se obtuvo el código del albarán.');
                }

                const factPayload = {
                    Id_Canal:            document.getElementById('selectCanal').value,
                    Fecha:               fecha,
                    Id_Cliente:          idCliente || null,
                    Tipo_Documento:      tipoDoc,
                    Id_Forma_Pago:       document.getElementById('selectFormaPago')?.value || '',
                    Observaciones:       document.querySelector('textarea[name="Observaciones"]')?.value || '',
                    Descuento_Especial:  parseFloat(document.getElementById('inputDtoEspecial')?.value)  || 0,
                    Descuento_Comercial: parseFloat(document.getElementById('inputDtoComercial')?.value) || 0,
                    Descuento_PP:        parseFloat(document.getElementById('inputDtoPP')?.value)        || 0,
                    lineas:              lineasPayload,
                };
                if (albCodigo) factPayload.Id_Albaran = albCodigo;

                const factJson   = await apiSend('/facturas.php', factPayload, 'POST');
                const factCodigo = factJson?.data?.codigo;
                if (!factCodigo) {
                    const detail = factJson?.message || factJson?.errors?.join(', ') || '';
                    throw new Error('No se obtuvo el código de la factura.' + (detail ? ' ' + detail : ''));
                }

                App.hideLoading?.();
                notify('Factura ' + factCodigo + ' creada correctamente.', 'success');
                setTimeout(() => {
                    window.location.href = BASE + '/src/views/facturas/ver.php?codigo=' + encodeURIComponent(factCodigo);
                }, 800);

            } catch (err) {
                App.hideLoading?.();
                notify(err.message || 'Error al facturar.', 'danger');
                console.error(err);
            }
        };
    };

    async function ejecutarFacturarYEnviar(tipoDoc, fecha) {
        try {
            if (tipoDoc !== 'SIMPLIFICADA' && !document.getElementById('inputIdCliente').value) {
                notify('Debe seleccionar un cliente para crear una factura normal.', 'warning');
                return;
            }
            if (!validarClienteN2()) { mostrarPopupN2(); return; }

            App.showLoading?.();

            const saveJson  = await guardarAlbaran(false, tipoDoc);
            const albCodigo = saveJson?.data?.codigo;
            if (!albCodigo) throw new Error('No se obtuvo el código del albarán.');

            const albJson          = await apiGet(`/albaranes.php/${encodeURIComponent(albCodigo)}`);
            const lineasPendientes = (albJson.data?.lineas || [])
                .filter(l => String(l.Facturada || '').toUpperCase() !== 'S')
                .map(l => parseInt(l.Linea));

            if (lineasPendientes.length === 0) {
                App.hideLoading?.();
                notify('No hay líneas pendientes de facturar.', 'warning');
                return;
            }

            const factPayload = {
                Id_Canal:            document.getElementById('selectCanal').value,
                Fecha:               fecha,
                Id_Cliente:          document.getElementById('inputIdCliente').value,
                Tipo_Documento:      tipoDoc,
                Id_Forma_Pago:       document.getElementById('selectFormaPago')?.value || '',
                Observaciones:       document.querySelector('textarea[name="Observaciones"]')?.value || '',
                Descuento_Especial:  parseFloat(document.getElementById('inputDtoEspecial')?.value)  || 0,
                Descuento_Comercial: parseFloat(document.getElementById('inputDtoComercial')?.value) || 0,
                Descuento_PP:        parseFloat(document.getElementById('inputDtoPP')?.value)        || 0,
                lineas: lineas.map(l => ({
                    Id_Articulo:   l.idArticulo,
                    Descripcion:   l.descripcion,
                    Cantidad:      l.cantidad,
                    Precio:        l.precio,
                    Descuento:     l.descuento,
                    Id_Tipo_IVA:   l.tipoIVA,
                    Aplica_RE:     l.aplicaRE ? 1 : 0,
                    Calificacion:  l.calificacion || 'S1',
                    Clave_Regimen: CLAVE_REGIMEN_E456.includes(l.calificacion) ? claveRegimenGlobal : '01',
                })),
            };

            // Fix: orden correcto apiSend(path, body, method)
            const factJson   = await apiSend('/facturas.php', factPayload, 'POST');
            const factCodigo = factJson?.data?.codigo;
            if (!factCodigo) throw new Error('No se obtuvo el código de la factura.');

            const verifactuPayload = { tipo_origen: 'FACTURA', id_documento: factCodigo };
            if (lineas.some(l => CALIFICACION_EXPORTACION.includes(l.calificacion))) {
                verifactuPayload.nif_exportacion       = nifExportacionVacio ? '' : nifExportacion;
                verifactuPayload.nif_exportacion_vacio = nifExportacionVacio;
            }
            await apiSend('/verifactu.php/enviar', verifactuPayload, 'POST');

            App.hideLoading?.();
            notify('Factura ' + factCodigo + ' creada y enviada a Hacienda.', 'success');
            setTimeout(() => {
                window.location.href = BASE + '/src/views/facturas/ver.php?codigo=' + encodeURIComponent(factCodigo);
            }, 1000);

        } catch (err) {
            App.hideLoading?.();
            notify(err.message || 'Error en el proceso de facturación.', 'danger');
            console.error(err);
        }
    }
})();
