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
                    <button type="button" class="btn btn-sm btn-primary fact-btn" onclick="seleccionarCliente()">
                        <i class="bi bi-arrow-repeat me-1"></i>Cambiar
                    </button>
                </div>
                <div class="card-body fact-card-body">
                    <div id="clienteEmpty" class="text-muted text-center py-3">
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
                                    <th style="width:50px;">#</th>
                                    <th>Artículo</th>
                                    <th style="width:100px;">Cantidad</th>
                                    <th style="width:100px;">Precio (€)</th>
                                    <th style="width:95px;">Dto.%</th>
                                    <th style="width:80px;">IVA</th>
                                    <th style="width:90px;">Base imp.</th>
                                    <th style="width:90px;">IVA calc.</th>
                                    <th style="width:60px;"></th>
                                </tr>
                            </thead>
                            <tbody id="lineasBody">
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
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

                        <dt class="col-7" id="reLabel" style="display:none;">RE:</dt>
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
                    <a href="/SistemaGestionFacturas/src/views/albaranes/listado.php"
                       class="btn btn-outline-secondary w-100 fact-btn">
                        <i class="bi bi-x-lg me-1"></i>Cancelar
                    </a>
                </div>
            </div>
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

<?php
$content = ob_get_clean();

ob_start();
?>
<script src="/SistemaGestionFacturas/assets/js/Albaranes/nuevo.js" defer></script>
<?php
$extra_js = ob_get_clean();

include __DIR__ . '/../layout/base.php';
