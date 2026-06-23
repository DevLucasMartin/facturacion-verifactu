<?php
/**
 * Nuevo Albarán
 */

$fact_page_title    = 'Nuevo documento';
$fact_page_subtitle = 'Crear documento de entrega';
$fact_active_menu   = 'albaranes';

ob_start();
?>

<style>
    /* Layout vertical de Nuevo Albarán */
    .albaran-flow { max-width: 1200px; margin: 0 auto; padding-bottom: 90px; }
    .albaran-stat {
        background: var(--bs-light, #f8f9fa);
        border-radius: .5rem;
        padding: .6rem .85rem;
        text-align: center;
    }
    .albaran-stat .stat-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; color: #6c757d; }
    .albaran-stat .stat-value { font-size: 1.05rem; font-weight: 600; }
    .albaran-stat.stat-total { background: var(--bs-primary, #0d6efd); color: #fff; }
    .albaran-stat.stat-total .stat-label { color: rgba(255,255,255,.85); }
    /* Barra de acciones fija inferior */
    .albaran-action-bar {
        position: fixed;
        left: 0; right: 0; bottom: 0;
        background: #fff;
        border-top: 1px solid #dee2e6;
        box-shadow: 0 -2px 10px rgba(0,0,0,.06);
        padding: .65rem 1rem;
        z-index: 1030;
    }
    .albaran-action-bar .bar-total { font-size: 1.35rem; font-weight: 700; }
</style>

<form id="albaranForm" method="POST">
    <input type="hidden" name="codigo" value="">
    <input type="hidden" id="inputIdCliente" name="Id_Cliente">

    <div class="albaran-flow">

        <!-- Datos del documento -->
        <div class="card mb-4 fact-card">
            <div class="card-header fact-card-header">
                <span><i class="bi bi-file-earmark-text me-2"></i>Datos del Documento</span>
            </div>
            <div class="card-body fact-card-body">
                <div class="row g-3">
                    <div class="col-md-3 col-sm-6">
                        <label class="form-label fact-form-label">Canal *</label>
                        <select name="Id_Canal" id="selectCanal" class="form-select fact-form-select" required>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label fact-form-label">Número</label>
                        <input type="text" class="form-control fact-form-control" id="numeroDocumento" readonly
                            placeholder="Al guardar">
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label fact-form-label">Fecha *</label>
                        <input type="date" class="form-control fact-form-control" id="inputFecha" name="Fecha"
                            value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label fact-form-label">Clave Régimen IVA</label>
                        <select id="selectClaveRegimenDoc" name="Clave_Regimen" class="form-select fact-form-select">
                        </select>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <label class="form-label fact-form-label">Forma de Pago</label>
                        <select name="Id_Forma_Pago" id="selectFormaPago" class="form-select fact-form-select select-forma-pago">
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fact-form-label">Observaciones</label>
                        <textarea class="form-control fact-form-control" id="inputObservaciones"
                            name="Observaciones" rows="2"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Datos del cliente -->
        <div class="card mb-4 fact-card" id="clienteCard">
            <div class="card-header fact-card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person me-2"></i>Cliente</span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-primary fact-btn" onclick="seleccionarCliente()">
                        <i class="bi bi-search me-1"></i>Buscar Cliente
                    </button>
                    <button type="button" class="btn btn-sm btn-danger fact-btn" id="btnBorrarCliente" onclick="borrarCliente()" style="display:none;">
                        <i class="bi bi-x-lg me-1"></i>Quitar
                    </button>
                </div>
            </div>
            <div class="card-body fact-card-body">
                <div id="clienteEmpty" class="text-muted text-center py-3" onclick="seleccionarCliente()" style="cursor:pointer;">
                    <i class="bi bi-person-plus fs-1 d-block mb-2"></i>
                    Pulse "Buscar cliente" para seleccionar
                </div>
                <div id="clienteData" class="row g-3" style="display:none;">
                    <div class="col-md-3">
                        <div class="fact-form-label text-muted small">Código</div>
                        <div><strong id="clienteCodigo"></strong></div>
                    </div>
                    <div class="col-md-5">
                        <div class="fact-form-label text-muted small">Nombre</div>
                        <div id="clienteNombre"></div>
                    </div>
                    <div class="col-md-4">
                        <div class="fact-form-label text-muted small">NIF</div>
                        <div id="clienteNIF"></div>
                    </div>
                    <div class="col-md-12">
                        <div class="fact-form-label text-muted small">Dirección</div>
                        <div id="clienteDireccion"></div>
                    </div>
                    <div class="col-md-4">
                        <div class="fact-form-label text-muted small">Forma Pago</div>
                        <div id="clienteFormaPago"></div>
                    </div>
                    <div class="col-md-4">
                        <div class="fact-form-label text-muted small">Tipo IVA</div>
                        <div id="clienteTipoIVA"></div>
                    </div>
                    <div class="col-md-4">
                        <div class="fact-form-label text-muted small">Aplica RE</div>
                        <div id="clienteAplicaRE"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Territorio / Régimen fiscal -->
        <div class="card mb-4 fact-card">
            <div class="card-header fact-card-header">
                <span><i class="bi bi-geo-alt me-2"></i>Territorio / Régimen fiscal</span>
            </div>
            <div class="card-body fact-card-body">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <label class="form-label mb-0" for="selectTerritorio"><strong>Territorio:</strong></label>
                    <select id="selectTerritorio" class="form-select form-select-sm" style="width:auto; min-width:260px;">
                        <option value="PENINSULAR" selected>Península e Islas Baleares (IVA)</option>
                        <option value="CANARIAS">Islas Canarias (IGIC)</option>
                        <option value="CEUTA_MELILLA">Ceuta y Melilla (IPSI)</option>
                        <option value="FUERA_ESPANA">Fuera de España (exportación / intracomunitario)</option>
                    </select>
                    <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Al elegir territorio, los impuestos de las líneas se filtran automáticamente.</small>
                </div>
                <div class="d-flex align-items-center gap-3 flex-wrap mt-3">
                    <label id="labelPaisExportacion" class="form-label mb-0 d-none" for="inputPaisExportacion"><strong>País destino:</strong></label>
                    <select id="inputPaisExportacion" class="form-select form-select-sm d-none" style="width:260px;" onchange="cambiarPaisExportacion(this.value)">
                    </select>
                    <label id="labelRE" class="form-label mb-0" for="selectRE"><strong>Rec. Equivalencia:</strong></label>
                    <select id="selectRE" class="form-select form-select-sm" style="width:auto;">
                        <option value="0">No</option>
                        <option value="1">Sí</option>
                    </select>
                    <label id="labelClaveRegimen" class="form-label mb-0 d-none" for="selectClaveRegimen"><strong>Clave Régimen:</strong></label>
                    <select id="selectClaveRegimen" class="form-select form-select-sm d-none" style="width:auto;" onchange="cambiarClaveRegimenGlobal(this.value)">
                    </select>
                </div>
            </div>
        </div>

        <!-- Líneas de albarán (ancho completo) -->
        <div class="card mb-4 fact-card">
            <div class="card-header fact-card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-ul me-2"></i>Líneas</span>
                <button type="button" class="btn btn-sm btn-primary fact-btn" onclick="agregarLinea()">
                    <i class="bi bi-plus-lg me-1"></i>Añadir Línea
                </button>
            </div>
            <div class="card-body p-0 fact-card-body" style="padding:0 !important;">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 fact-table" id="lineasTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th style="min-width:130px;">Artículo</th>
                                <th style="width:90px;">Cantidad</th>
                                <th style="width:150px;">Precio (€)</th>
                                <th style="width:130px;">Dto.%</th>
                                <th style="width:140px;">IVA</th>
                                <th id="thRE" class="d-none" style="width:100px;">RE</th>
                                <th id="thCalif" style="width:120px;">Cal. Operación</th>
                                <th style="width:90px;">Base Imp.</th>
                                <th style="width:90px;">IVA calc.</th>
                                <th style="width:90px;">Total</th>
                                <th style="width:60px;"></th>
                            </tr>
                        </thead>
                        <tbody id="lineasBody">
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">
                                    <i class="bi bi-plus-circle fs-1 d-block mb-2"></i>
                                    Añada líneas al albarán
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- NIF exportación (visible solo para S2/N2/E2/E5) -->
        <div id="nifExportacionSection" class="card mb-4 fact-card d-none">
            <div class="card-header fact-card-header">
                <span><i class="bi bi-globe me-2"></i>Identificador fiscal del destinatario</span>
            </div>
            <div class="card-body fact-card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label fact-form-label">NIF / VAT del destinatario</label>
                        <input type="text" id="inputNifExportacion" class="form-control fact-form-control text-uppercase"
                            placeholder="Ej: ESA12345674 o FR12345678901"
                            oninput="cambiarNifExportacion(this.value)">
                        <div id="vatFormatoError" class="d-none mt-1">
                            <small class="text-danger" id="vatFormatoErrorTexto"></small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="chkNifVacio"
                                onchange="cambiarNifExportacionVacio(this.checked)">
                            <label class="form-check-label" for="chkNifVacio">
                                Enviar sin identificador fiscal (operación sin NIF)
                            </label>
                        </div>
                    </div>
                </div>
                <div id="viesDisplay" class="alert alert-info mt-2 d-none py-2">
                    <small><strong>Identificador VIES que se enviará a AEAT:</strong> <span id="viesValue" class="fw-bold"></span></small>
                </div>
            </div>
        </div>

        <!-- Aviso cliente N2/E5 -->
        <div id="avisoClienteN2" class="alert alert-warning" style="display:none;">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <span id="avisoClienteN2Texto"></span>
        </div>

        <!-- Totales y descuentos -->
        <div class="card mb-4 fact-card">
            <div class="card-header fact-card-header">
                <span><i class="bi bi-calculator me-2"></i>Totales</span>
            </div>
            <div class="card-body fact-card-body">
                <div class="row g-3">
                    <!-- Descuentos -->
                    <div class="col-lg-5">
                        <div class="row g-2 align-items-end">
                            <div class="col-4">
                                <label class="form-label fact-form-label mb-1">Dto. Especial %</label>
                                <input type="number" class="form-control form-control-sm fact-form-control" id="inputDtoEspecial"
                                    value="0" min="0" max="100" step="any" placeholder="0.00%">
                            </div>
                            <div class="col-4">
                                <label class="form-label fact-form-label mb-1">Dto. Comercial %</label>
                                <input type="number" class="form-control form-control-sm fact-form-control" id="inputDtoComercial"
                                    value="0" min="0" max="100" step="any" placeholder="0.00%">
                            </div>
                            <div class="col-4">
                                <label class="form-label fact-form-label mb-1">Dto. Pronto Pago %</label>
                                <input type="number" class="form-control form-control-sm fact-form-control" id="inputDtoPP"
                                    value="0" min="0" max="100" step="any" placeholder="0.00%">
                            </div>
                        </div>
                    </div>
                    <!-- Resumen de importes -->
                    <div class="col-lg-7">
                        <div class="row g-2">
                            <div class="col-6 col-md-3">
                                <div class="albaran-stat">
                                    <div class="stat-label">Subtotal</div>
                                    <div class="stat-value" id="subtotalDisplay">0,00 €</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="albaran-stat" id="descuentosDisplay" style="display:none;">
                                    <div class="stat-label" id="descuentosLabel">Descuentos</div>
                                    <div class="stat-value text-danger"><span id="descuentosValue">0,00 €</span></div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="albaran-stat">
                                    <div class="stat-label">Base Imp.</div>
                                    <div class="stat-value" id="baseImponibleDisplay">0,00 €</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="albaran-stat">
                                    <div class="stat-label">IVA</div>
                                    <div class="stat-value" id="ivaDisplay">0,00 €</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="albaran-stat" id="reDisplay" style="display:none;">
                                    <div class="stat-label" id="reLabel">Rec. Equiv.</div>
                                    <div class="stat-value"><span id="reValue">0,00 €</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="erroresPanel" class="alert alert-danger mt-3 mb-0" style="display:none;">
                    <ul class="mb-0" id="erroresList"></ul>
                </div>
                <div id="accionesMsg" style="display:none;"></div>
            </div>
        </div>

    </div>

    <!-- Barra de acciones fija inferior -->
    <div class="albaran-action-bar">
        <div class="albaran-flow d-flex justify-content-between align-items-center flex-wrap gap-2 mb-0 pb-0" style="padding-bottom:0 !important;">
            <div class="d-flex align-items-baseline gap-2">
                <span class="text-muted">Total:</span>
                <span class="bar-total text-primary" id="totalDisplay">0,00 €</span>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="/SistemaGestionFacturas/src/views/albaranes/listado.php"
                   class="btn btn-outline-secondary fact-btn">
                    <i class="bi bi-x-lg me-1"></i>Cancelar
                </a>
                <button type="button" class="btn btn-outline-primary fact-btn"
                        onclick="abrirModalGuardarPlantilla()">
                    <i class="bi bi-layout-text-window me-1"></i>Plantilla
                </button>
                <button type="submit" class="btn btn-primary fact-btn" id="btnGuardar">
                    <i class="bi bi-floppy me-1"></i>Guardar albarán
                </button>
                <button type="button" class="btn btn-success fact-btn" id="btnGuardarFacturar"
                        onclick="guardarYFacturar()">
                    <i class="bi bi-receipt me-1"></i>Guardar, Facturar y Enviar a Verifactu
                </button>
            </div>
        </div>
    </div>
</form>

<!-- Modal error N2 -->
<div class="modal fade" id="errorN2Modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Validación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="errorN2Texto"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Guardar Plantilla -->
<div class="modal fade" id="guardarPlantillaModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Guardar como plantilla</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label fact-form-label">Nombre de la plantilla</label>
                <input type="text" class="form-control fact-form-control" id="inputNombrePlantilla"
                    placeholder="Ej: Pedido mensual cliente X">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnConfirmarPlantilla">
                    <i class="bi bi-floppy me-1"></i>Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cliente -->
<div class="modal fade" id="clienteModal" tabindex="-1" aria-labelledby="clienteModalLabel">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="clienteModalLabel">Seleccionar Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control fact-form-control" id="buscarCliente"
                        placeholder="Buscar por nombre, NIF o código...">
                </div>
                <div class="table-responsive" style="max-height:400px; overflow-y:auto;">
                    <table class="table table-hover table-sm mb-0 fact-table">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Código</th>
                                <th>NIF</th>
                                <th>Nombre</th>
                                <th>Forma Pago</th>
                            </tr>
                        </thead>
                        <tbody id="clientesBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Artículo -->
<div class="modal fade" id="articuloModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Seleccionar Artículo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control fact-form-control" id="buscarArticulo"
                        placeholder="Buscar por código, descripción o código de barras...">
                </div>
                <div class="table-responsive" style="max-height:400px; overflow-y:auto;">
                    <table class="table table-sm table-hover fact-table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Tipo IVA</th>
                                <th>Precio</th>
                            </tr>
                        </thead>
                        <tbody id="articulosBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tipo de Factura -->
<div class="modal fade" id="tipoFacturaModal" tabindex="-1" aria-labelledby="tipoFacturaModalLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tipoFacturaModalLabel">Tipo de factura</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="modalTipoDoc" id="modalTipoDocFactura" value="FACTURA" checked>
                        <label class="form-check-label" for="modalTipoDocFactura">Factura</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="modalTipoDoc" id="modalTipoDocSimplificada" value="SIMPLIFICADA">
                        <label class="form-check-label" for="modalTipoDocSimplificada">Simplificada</label>
                    </div>
                </div>
                <div id="modalDestinatarioSimplificada" style="display:none;">
                    <label class="form-label fw-semibold">Tipo de destinatario</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="modalDestinatarioTipo" id="modalDestParticular" value="particular" checked>
                        <label class="form-check-label" for="modalDestParticular">Particular (límite 400 €)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="modalDestinatarioTipo" id="modalDestEmpresa" value="empresa">
                        <label class="form-check-label" for="modalDestEmpresa">Empresa o profesional (límite 3.000 €)</label>
                    </div>
                    <div id="modalAvisoSimplificada" class="alert alert-warning mt-2 mb-0" style="display:none;">
                        <span id="modalAvisoSimplificadaTexto"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnConfirmarTipoFactura">
                    <i class="bi bi-receipt me-1"></i>Confirmar y facturar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nuevo Cliente -->
<div class="modal fade" id="nuevoClienteModal" tabindex="-1" aria-modal="true" aria-labelledby="nuevoClienteModalTitleAlb">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="nuevoClienteModalTitleAlb">Nuevo Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="nuevoClienteMsg" class="alert alert-info d-none mb-3"></div>
                <form id="nuevoClienteForm">
                    <div class="row g-3">
                        <div class="col-md-3" style="display:none">
                            <label class="form-label fact-form-label">Código</label>
                            <input type="text" class="form-control fact-form-control" id="ncCodigo" name="ncCodigo"
                                   readonly>
                            <div class="invalid-feedback" id="ncCodigoError"></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fact-form-label">Apellidos</label>
                            <input type="text" class="form-control fact-form-control" id="ncApellidos" name="ncApellidos">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fact-form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control fact-form-control" id="ncArchivar" name="ncArchivar"
                                   placeholder="Nombre o razón social">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fact-form-label">NIF <span class="text-danger">*</span></label>
                            <input type="text" class="form-control fact-form-control" id="ncNif" name="ncNif"
                                   placeholder="12345678A" oninput="validarNIF(this)">
                            <div class="invalid-feedback" id="ncNifError"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fact-form-label">Forma de pago</label>
                            <select class="form-select fact-form-select select-forma-pago" id="selectFormaPagoNuevo" name="ncFormaPago"></select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fact-form-label">RE %</label>
                            <input type="number" class="form-control fact-form-control" id="ncRE" name="ncRE"
                                   min="0" max="100" step="0.01" value="0">
                        </div>
                    </div>
                    <hr class="my-3">
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary fact-btn" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary fact-btn">
                            <i class="bi bi-check-lg me-1"></i>Guardar cliente
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

ob_start();
?>
<script src="/SistemaGestionFacturas/assets/js/Albaranes/nuevo.js" defer></script>
<?php
$extra_js = ob_get_clean();

include __DIR__ . '/../layout/base.php';
