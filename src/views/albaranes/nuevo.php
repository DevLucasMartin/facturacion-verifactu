<?php
/**
 * Nuevo Albarán
 */

$fact_page_title    = 'Nuevo albarán';
$fact_page_subtitle = 'Crear documento de entrega';
$fact_active_menu   = 'albaranes';

ob_start();
?>

<form id="albaranForm" method="POST">
    <input type="hidden" name="codigo" value="">

    <div class="row">
        <!-- Columna Principal -->
        <div class="col-lg-8">

            <!-- Datos del documento -->
            <div class="card mb-4 fact-card">
                <div class="card-header fact-card-header">
                    <span><i class="bi bi-file-earmark-text me-2"></i>Datos del Documento</span>
                </div>
                <div class="card-body fact-card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fact-form-label">Canal *</label>
                            <select name="Id_Canal" id="selectCanal" class="form-select fact-form-select" required>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fact-form-label">Número</label>
                            <input type="text" class="form-control fact-form-control" id="numeroDocumento" readonly
                                placeholder="Se asignará al guardar">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fact-form-label">Fecha *</label>
                            <input type="date" class="form-control fact-form-control" id="inputFecha" name="Fecha"
                                value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fact-form-label">Cliente *</label>
                            <div class="input-group">
                                <input type="text" class="form-control fact-form-control" id="inputIdCliente"
                                    name="Id_Cliente" readonly required>
                                <button type="button" class="btn btn-outline-secondary"
                                    onclick="seleccionarCliente()" title="Buscar cliente">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-md-4">
                            <label class="form-label fact-form-label">Dto. Especial %</label>
                            <input type="number" class="form-control fact-form-control" id="inputDtoEspecial"
                                value="0" min="0" max="100" step="any" placeholder="0.00%">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fact-form-label">Dto. Comercial %</label>
                            <input type="number" class="form-control fact-form-control" id="inputDtoComercial"
                                value="0" min="0" max="100" step="any" placeholder="0.00%">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fact-form-label">Dto. Pronto Pago %</label>
                            <input type="number" class="form-control fact-form-control" id="inputDtoPP"
                                value="0" min="0" max="100" step="any" placeholder="0.00%">
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-md-4">
                            <label class="form-label fact-form-label">Forma de Pago</label>
                            <select name="Id_Forma_Pago" id="selectFormaPago" class="form-select fact-form-select select-forma-pago">
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fact-form-label">Tarifa</label>
                            <select id="selectTarifa" name="Tarifa" class="form-select fact-form-select select-tarifa">
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fact-form-label">Clave Régimen IVA</label>
                            <select id="selectClaveRegimen" name="Clave_Regimen" class="form-select fact-form-select">
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-md-8">
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
                    <div id="clienteData" style="display:none;">
                        <dl class="row mb-0">
                            <dt class="col-sm-3">Código:</dt>
                            <dd class="col-sm-9"><strong id="clienteCodigo"></strong></dd>
                            <dt class="col-sm-3">Nombre:</dt>
                            <dd class="col-sm-9" id="clienteNombre"></dd>
                            <dt class="col-sm-3">NIF:</dt>
                            <dd class="col-sm-9" id="clienteNIF"></dd>
                            <dt class="col-sm-3">Dirección:</dt>
                            <dd class="col-sm-9" id="clienteDireccion"></dd>
                            <dt class="col-sm-3">Forma Pago:</dt>
                            <dd class="col-sm-9" id="clienteFormaPago"></dd>
                            <dt class="col-sm-3">Tipo IVA:</dt>
                            <dd class="col-sm-9" id="clienteTipoIVA"></dd>
                            <dt class="col-sm-3">Aplica RE:</dt>
                            <dd class="col-sm-9" id="clienteAplicaRE"></dd>
                            <dt class="col-sm-3">Tarifa:</dt>
                            <dd class="col-sm-9" id="clienteTarifa"></dd>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Territorio / Régimen fiscal -->
            <div class="card mb-4 fact-card">
                <div class="card-header fact-card-header">
                    <span><i class="bi bi-geo-alt me-2"></i>Territorio / Régimen fiscal</span>
                </div>
                <div class="card-body fact-card-body">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-5">
                            <label class="form-label mb-0" for="selectTerritorio"><strong>Territorio:</strong></label>
                            <select id="selectTerritorio" class="form-select form-select-sm mt-1">
                                <option value="PENINSULAR" selected>Península e Islas Baleares (IVA)</option>
                                <option value="CANARIAS">Islas Canarias (IGIC)</option>
                                <option value="CEUTA_MELILLA">Ceuta y Melilla (IPSI)</option>
                                <option value="FUERA_ESPANA">Fuera de España</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-0" for="selectRE"><strong>Rec. Equivalencia:</strong></label>
                            <select id="selectRE" class="form-select form-select-sm mt-1">
                                <option value="0">No aplica</option>
                                <option value="1">Sí aplica</option>
                            </select>
                        </div>
                        <div class="col-md-3 align-self-end">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Los impuestos se filtran según el territorio.
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Líneas de albarán -->
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

            <!-- Panel de errores -->
            <div id="erroresPanel" class="alert alert-danger mt-3" style="display:none;">
                <ul class="mb-0" id="erroresList"></ul>
            </div>
        </div>

        <!-- Columna Lateral -->
        <div class="col-lg-4">
            <div style="position: sticky; top: 80px;">
            <!-- Totales -->
            <div class="card mb-4 fact-card">
                <div class="card-header fact-card-header">
                    <span><i class="bi bi-calculator me-2"></i>Totales</span>
                </div>
                <div class="card-body fact-card-body">
                    <dl class="row mb-0">
                        <dt class="col-7">Subtotal:</dt>
                        <dd class="col-5 text-end" id="subtotalDisplay">0,00 €</dd>

                        <dt class="col-7" id="descuentosLabel">Descuentos:</dt>
                        <dd class="col-5 text-end text-danger" id="descuentosDisplay" style="display:none;">
                            -<span id="descuentosValue">0,00 €</span>
                        </dd>

                        <dt class="col-7">Base Imponible:</dt>
                        <dd class="col-5 text-end" id="baseImponibleDisplay">0,00 €</dd>

                        <dt class="col-7">IVA:</dt>
                        <dd class="col-5 text-end" id="ivaDisplay">0,00 €</dd>

                        <dt class="col-7" id="reLabel" style="display:none;">Rec. Equivalencia:</dt>
                        <dd class="col-5 text-end" id="reDisplay" style="display:none;">
                            <span id="reValue">0,00 €</span>
                        </dd>

                        <hr>
                        <dt class="col-7"><strong>Total:</strong></dt>
                        <dd class="col-5 text-end"><strong id="totalDisplay">0,00 €</strong></dd>
                    </dl>
                </div>
            </div>

            <!-- Acciones -->
            <div class="card mb-4 fact-card">
                <div class="card-header fact-card-header">
                    <span><i class="bi bi-gear me-2"></i>Acciones</span>
                </div>
                <div class="card-body fact-card-body">
                    <button type="submit" class="btn btn-primary fact-btn w-100 mb-2" id="btnGuardar">
                        <i class="bi bi-floppy me-1"></i>Guardar albarán
                    </button>
                    <button type="button" class="btn btn-success fact-btn w-100 mb-2" id="btnGuardarFacturar"
                            onclick="guardarYFacturar()">
                        <i class="bi bi-receipt me-1"></i>Guardar y Facturar
                    </button>
                    <a href="/SistemaGestionFacturas/src/views/albaranes/listado.php"
                       class="btn btn-outline-secondary w-100 fact-btn">
                        <i class="bi bi-x-lg me-1"></i>Cancelar
                    </a>
                </div>
            </div>
            </div><!-- /sticky -->
        </div>
    </div>
</form>

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
                        <div class="col-md-3">
                            <label class="form-label fact-form-label">Código <span class="text-danger">*</span></label>
                            <input type="text" class="form-control fact-form-control" id="ncCodigo" name="ncCodigo"
                                   placeholder="CLI001" maxlength="12"
                                   oninput="validarCodigoCliente(this)">
                            <div class="invalid-feedback" id="ncCodigoError"></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fact-form-label">Apellidos</label>
                            <input type="text" class="form-control fact-form-control" id="ncApellidos" name="ncApellidos">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fact-form-label">Archivar como <span class="text-danger">*</span></label>
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
                            <label class="form-label fact-form-label">Tarifa</label>
                            <select class="form-select fact-form-select select-tarifa" id="selectTarifaNuevo" name="ncTarifa"></select>
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
