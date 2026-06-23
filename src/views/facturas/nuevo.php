<?php
/**
 * Nueva / Editar Factura
 * Sistema de Gestión de Facturas / VeriFACTU
 */

$es_edicion   = isset($_GET['codigo']);
$es_rectifica = isset($_GET['rectifica']);

$fact_page_title    = $es_edicion ? 'Editar Factura' : 'Nueva Factura';
$fact_page_subtitle = $es_edicion ? 'Modificar documento de venta' : 'Crear nuevo documento de venta';
$fact_active_menu   = 'facturas';

$fact_codigo = $_GET['codigo'] ?? '';
$codigoGET   = $_SERVER['QUERY_STRING'] ?? '';

ob_start();
?>

<style>
    /* Layout vertical de factura */
    .doc-flow { max-width: 1200px; margin: 0 auto; padding-bottom: 90px; }
    .doc-stat {
        background: var(--bs-light, #f8f9fa);
        border-radius: .5rem;
        padding: .6rem .85rem;
        text-align: center;
    }
    .doc-stat .stat-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; color: #6c757d; }
    .doc-stat .stat-value { font-size: 1.05rem; font-weight: 600; }
    /* Barra de acciones fija inferior */
    .doc-action-bar {
        position: fixed;
        left: 0; right: 0; bottom: 0;
        background: #fff;
        border-top: 1px solid #dee2e6;
        box-shadow: 0 -2px 10px rgba(0,0,0,.06);
        padding: .65rem 1rem;
        z-index: 1030;
    }
    .doc-action-bar .bar-total { font-size: 1.35rem; font-weight: 700; }
</style>

<form id="facturaForm" method="POST">
    <input type="hidden" name="codigo" value="<?= htmlspecialchars($fact_codigo, ENT_QUOTES, 'UTF-8') ?>">

    <div class="doc-flow">

            <!-- Datos del documento -->
            <div class="card fact-card mb-4">
                <div class="card-header fact-card-header">
                    <span><i class="bi bi-file-earmark-text me-2"></i>Datos del Documento</span>
                </div>
                <div class="card-body fact-card-body">
                    <div class="row g-3">
                        <!-- Tipo de documento -->
                        <div class="col-md-3">
                            <label class="form-label">Tipo *</label>
                            <div class="btn-group w-100" role="group">
                                <?php if (!str_contains($codigoGET, 'rectifica')): ?>
                                    <input type="radio" class="btn-check" name="Tipo_Documento" id="tipoFactura" value="FACTURA" checked required>
                                    <label class="btn btn-outline-primary btn-sm fact-btn" for="tipoFactura">Factura</label>
                                    <input type="radio" class="btn-check" name="Tipo_Documento" id="tipoSimplificada" value="SIMPLIFICADA">
                                    <label class="btn btn-outline-primary btn-sm fact-btn" for="tipoSimplificada">Simplif.</label>
                                <?php else: ?>
                                    <input type="radio" class="btn-check" name="Tipo_Documento" id="tipoRectificativa" value="RECTIFICATIVA" checked required>
                                    <label class="btn btn-outline-primary btn-sm fact-btn" for="tipoRectificativa">Abono</label>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- Canal / Serie -->
                        <div class="col-md-3">
                            <label class="form-label fact-form-label" for="selectCanal">Canal</label>
                            <select name="Id_Canal" id="selectCanal" class="form-select fact-form-select" required></select>
                        </div>
                        <!-- Número (solo lectura) -->
                        <div class="col-md-2">
                            <label class="form-label fact-form-label" for="numeroDocumento">Número</label>
                            <input type="text" class="form-control fact-form-control" id="numeroDocumento"
                                   readonly placeholder="Al guardar">
                        </div>
                        <!-- Fecha -->
                        <div class="col-md-2">
                            <label class="form-label fact-form-label" for="inputFecha">Fecha</label>
                            <input type="date" name="Fecha" id="inputFecha" class="form-control fact-form-control"
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <!-- Forma de pago -->
                        <div class="col-md-2">
                            <label class="form-label fact-form-label" for="selectFormaPago">Forma de Pago</label>
                            <select name="Id_Forma_Pago" id="selectFormaPago" class="form-select fact-form-select select-forma-pago" required></select>
                        </div>
                    </div>

                    <!-- Campos rectificación -->
                    <div class="row g-3 mt-2" id="motivoRectificacionRow" style="display: none;">
                        <div class="col-md-4">
                            <label class="form-label fact-form-label" for="selectTipoRectificativa">Tipo rectificativa AEAT *</label>
                            <select name="Tipo_Rectificativa_Verifactu" id="selectTipoRectificativa" class="form-select fact-form-select">
                                <option value="R1">R1 – Error fundado en derecho (art. 80.1/2/6 LIVA)</option>
                                <option value="R2">R2 – Concurso de acreedores (art. 80.3 LIVA)</option>
                                <option value="R3">R3 – Créditos incobrables (art. 80.4 LIVA)</option>
                                <option value="R4">R4 – Otras causas</option>
                                <option value="R5">R5 – Simplificada rectificativa</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fact-form-label" for="selectSubtipoRectificativa">Método *</label>
                            <select name="Subtipo_Rectificativa" id="selectSubtipoRectificativa" class="form-select fact-form-select">
                                <option value="I">I – Por diferencias</option>
                                <option value="S">S – Por sustitución</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fact-form-label" for="selectMotivo">Motivo Rectificación *</label>
                            <select name="Motivo_Rectificacion" id="selectMotivo" class="form-select fact-form-select">
                                <option value="">Seleccionar...</option>
                                <option value="I">Por error en el documento</option>
                                <option value="D">Por desviación en el producto</option>
                                <option value="O">Por otras causas</option>
                            </select>
                        </div>
                        <div class="col-md-12" id="importeRectificacionRow" style="display: none;">
                            <div class="row g-2 align-items-end">
                                <div class="col-12">
                                    <small class="text-muted">Importes rectificados (recomendado para R1/R2/R3)</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fact-form-label" for="inputBaseRectificada">Base rectificada</label>
                                    <input type="number" name="Base_Rectificada" id="inputBaseRectificada"
                                           class="form-control fact-form-control" step="0.01" placeholder="0.00">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fact-form-label" for="inputCuotaRectificada">Cuota rectificada</label>
                                    <input type="number" name="Cuota_Rectificada" id="inputCuotaRectificada"
                                           class="form-control fact-form-control" step="0.01" placeholder="0.00">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fact-form-label" for="inputCuotaRecargoRectificado">Cuota recargo rect.</label>
                                    <input type="number" name="Cuota_Recargo_Rectificado" id="inputCuotaRecargoRectificado"
                                           class="form-control fact-form-control" step="0.01" placeholder="0.00">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Datos del cliente -->
            <div class="card fact-card mb-4">
                <div class="card-header fact-card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-person me-2"></i>Cliente</span>
                    <div class="btn-group btn-group-sm fact-btn-group" role="group">
                        <button type="button" class="btn btn-primary fact-cli-btn" onclick="seleccionarCliente()">
                            <i class="bi bi-arrow-repeat me-1"></i>Seleccionar
                        </button>
                    </div>
                </div>
                <div class="card-body fact-card-body" id="clientePanel">
                    <div id="clienteEmpty">
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-person-plus fs-1 d-block mb-2"></i>
                            <p class="mb-2">Seleccione un cliente</p>
                            <button type="button" class="btn btn-primary fact-btn" onclick="seleccionarCliente()">
                                <i class="bi bi-search me-1"></i>Buscar Cliente
                            </button>
                        </div>
                    </div>
                    <div id="clienteData" style="display: none;">
                        <input type="hidden" name="Id_Cliente" id="inputIdCliente" value="">
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Nombre:</strong> <span id="clienteNombre"></span></p>
                                <p class="mb-1"><strong>NIF:</strong> <span id="clienteNIF"></span></p>
                                <p class="mb-1"><strong>Dirección:</strong> <span id="clienteDireccion"></span></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Forma de pago:</strong> <span id="clienteFormaPago"></span></p>
                                <p class="mb-1"><strong>Tipo IVA:</strong> <span id="clienteTipoIVA"></span></p>
                                <p class="mb-1"><strong>Aplica RE:</strong> <span id="clienteAplicaRE"></span></p>
                                <p class="mb-1" id="reRow" style="display: none;">
                                    <strong>RE:</strong> <span class="badge bg-warning text-dark" id="clienteRE"></span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="button" id="btnGuardarCliente"
                                class="btn btn-sm btn-success fact-btn d-none"
                                onclick="guardarClienteDemo()">
                            <i class="bi bi-save me-1"></i>Guardar cliente
                        </button>
                    </div>
                </div>
            </div>

            <!-- Territorio / Régimen fiscal -->
            <div class="card fact-card mb-4">
                <div class="card-header fact-card-header">
                    <span><i class="bi bi-geo-alt me-2"></i>Territorio / Régimen fiscal</span>
                </div>
                <div class="card-body fact-card-body">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <label class="form-label mb-0" for="selectTerritorio"><strong>Territorio:</strong></label>
                        <select id="selectTerritorio" class="form-select form-select-sm" style="width:auto;min-width:260px;">
                            <option value="PENINSULAR" selected>Península e Islas Baleares (IVA)</option>
                            <option value="CANARIAS">Islas Canarias (IGIC)</option>
                            <option value="CEUTA_MELILLA">Ceuta y Melilla (IPSI)</option>
                        </select>
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>Los impuestos de las líneas se filtran por territorio.
                        </small>
                    </div>
                </div>
            </div>

            <!-- Factura origen (solo rectificativas) -->
            <div class="card fact-card mb-4" id="facturaOrigenRow" style="display: none;">
                <div class="card-header fact-card-header">
                    <span><i class="bi bi-file-earmark-text me-2"></i>Factura Origen</span>
                </div>
                <div class="card-body fact-card-body">
                    <?php if ($es_rectifica): ?>
                        <input type="hidden" id="hiddenFacturaOrigen" value="<?= htmlspecialchars($_GET['rectifica'], ENT_QUOTES, 'UTF-8') ?>">
                        <p class="mb-0">
                            <span class="text-muted me-2">Factura Origen:</span>
                            <strong><?= htmlspecialchars($_GET['rectifica'], ENT_QUOTES, 'UTF-8') ?></strong>
                        </p>
                    <?php else: ?>
                        <label class="form-label fact-form-label">Seleccionar factura que se rectifica *</label>
                        <select id="selectFacturaOrigen" class="form-select fact-form-select">
                            <option value="">Seleccione un cliente primero</option>
                        </select>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Preview factura origen -->
            <div class="card fact-card mb-4" id="facturaOrigenPanel" style="display:none;">
                <div class="card-header fact-card-header">
                    <span><i class="bi bi-file-earmark-text me-2"></i>Documento (Factura origen)</span>
                </div>
                <div class="card-body fact-card-body" id="facturaOrigenData">
                    <div class="text-muted">Seleccione una factura origen para ver sus datos.</div>
                </div>
            </div>

            <!-- Líneas -->
            <div class="card fact-card mb-4">
                <div class="card-header fact-card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-ul me-2"></i>Líneas</span>
                    <button type="button" class="btn btn-sm btn-primary fact-cli-btn" onclick="agregarLinea()">
                        <i class="bi bi-plus-lg me-1"></i>Añadir Línea
                    </button>
                </div>
                <div class="card-body fact-card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 fact-table" id="lineasTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:50px;">#</th>
                                    <th>Artículo</th>
                                    <th style="width:100px;">Cantidad</th>
                                    <th style="width:120px;">Precio (€)</th>
                                    <th style="width:95px;">Dto.%</th>
                                    <th style="width:150px;">Impuestos</th>
                                    <th style="width:160px;">Calificación</th>
                                    <th style="width:90px;">Base imp.</th>
                                    <th style="width:90px;">IVA calc.</th>
                                    <th style="width:90px;">Total</th>
                                    <th style="width:60px;">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="lineasBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Observaciones -->
            <div class="card fact-card mb-4">
                <div class="card-header fact-card-header">
                    <span><i class="bi bi-chat-left-text me-2"></i>Observaciones</span>
                </div>
                <div class="card-body fact-card-body">
                    <textarea name="Observaciones" id="textareaObservaciones" class="form-control fact-form-control"
                              rows="3" placeholder="Observaciones adicionales..."></textarea>
                </div>
            </div>

            <!-- Totales y descuentos -->
            <div class="card fact-card mb-4">
                <div class="card-header fact-card-header">
                    <span><i class="bi bi-calculator me-2"></i>Totales</span>
                </div>
                <div class="card-body fact-card-body">
                    <div class="row g-3">
                        <!-- Descuentos -->
                        <div class="col-lg-5">
                            <label class="form-label fact-form-label mb-1">Descuentos</label>
                            <div class="row g-2 align-items-end">
                                <div class="col-4">
                                    <input type="number" name="Descuento_Especial" id="inputDtoEspecial"
                                           class="form-control form-control-sm fact-form-control"
                                           placeholder="Esp.%" min="0" max="100" step="0.25" value="0">
                                    <small class="text-muted">Especial %</small>
                                </div>
                                <div class="col-4">
                                    <input type="number" name="Descuento_Comercial" id="inputDtoComercial"
                                           class="form-control form-control-sm fact-form-control"
                                           placeholder="Com.%" min="0" max="100" step="0.25" value="0">
                                    <small class="text-muted">Comercial %</small>
                                </div>
                                <div class="col-4">
                                    <input type="number" name="Descuento_PP" id="inputDtoPP"
                                           class="form-control form-control-sm fact-form-control"
                                           placeholder="PP%" min="0" max="100" step="0.25" value="0">
                                    <small class="text-muted">Pronto Pago %</small>
                                </div>
                            </div>
                        </div>
                        <!-- Resumen de importes -->
                        <div class="col-lg-7">
                            <div class="row g-2">
                                <div class="col-6 col-md-3">
                                    <div class="doc-stat">
                                        <div class="stat-label">Subtotal</div>
                                        <div class="stat-value" id="subtotalDisplay">0,00 €</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="doc-stat" id="descuentosDisplay" style="display:none;">
                                        <div class="stat-label">Descuentos</div>
                                        <div class="stat-value text-danger" id="descuentosValue">0,00 €</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="doc-stat">
                                        <div class="stat-label">Base Imp.</div>
                                        <div class="stat-value" id="baseImponibleDisplay">0,00 €</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="doc-stat">
                                        <div class="stat-label">IVA</div>
                                        <div class="stat-value" id="ivaDisplay">0,00 €</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="doc-stat" id="reDisplay" style="display:none;">
                                        <div class="stat-label">Rec. Equiv.</div>
                                        <div class="stat-value" id="reValue">0,00 €</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Destinatario simplificada -->
                    <div id="destinatarioSimplificada" class="mt-3" style="display:none; max-width:480px;">
                        <label class="form-label small text-muted">Tipo de destinatario</label>
                        <div class="btn-group" role="group">
                            <input type="radio" class="btn-check" name="destinatario_tipo" id="destParticular" value="particular" checked>
                            <label class="btn btn-outline-secondary btn-sm" for="destParticular">
                                <i class="bi bi-person me-1"></i>Particular
                            </label>
                            <input type="radio" class="btn-check" name="destinatario_tipo" id="destEmpresa" value="empresa">
                            <label class="btn btn-outline-secondary btn-sm" for="destEmpresa">
                                <i class="bi bi-building me-1"></i>Empresa / Profesional
                            </label>
                        </div>
                    </div>
                    <div id="avisoSimplificada" class="alert alert-warning mt-3 mb-0 small" style="display:none;">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <strong>Límite factura simplificada superado.</strong><br>
                        <span id="avisoSimplificadaTexto"></span>
                    </div>
                    <div id="erroresPanel" class="alert alert-danger mt-3 mb-0" style="display:none;">
                        <ul class="mb-0 small" id="erroresList"></ul>
                    </div>
                    <div id="accionesMsg" style="display:none;"></div>
                </div>
            </div>

    </div>

    <!-- Barra de acciones fija inferior -->
    <div class="doc-action-bar">
        <div class="doc-flow d-flex justify-content-between align-items-center flex-wrap gap-2" style="padding-bottom:0 !important;">
            <div class="d-flex align-items-baseline gap-2">
                <span class="text-muted">Total:</span>
                <span class="bar-total text-primary" id="totalDisplay">0,00 €</span>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="/SistemaGestionFacturas/src/views/facturas/listado.php"
                   class="btn btn-outline-secondary fact-btn">
                    <i class="bi bi-x-lg me-1"></i>Cancelar
                </a>
                <button type="button" class="btn btn-success fact-btn" id="btnGuardarEnviar"
                        onclick="abrirModalGuardarEnviar()">
                    <i class="bi bi-send me-1"></i>Guardar y enviar a VeriFACTU
                </button>
            </div>
        </div>
    </div>
</form>

<!-- Modal Cliente -->
<div class="modal fade" id="clienteModal" tabindex="-1" aria-modal="true" aria-labelledby="clienteModalTitle">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="clienteModalTitle">Seleccionar Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control fact-form-control" id="buscarCliente"
                           placeholder="Buscar por nombre, NIF o código...">
                </div>
                <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                    <table class="table table-hover table-sm mb-0 fact-table" id="clientesTable">
                        <thead class="table-light sticky-top">
                            <tr><th>Código</th><th>NIF</th><th>Nombre</th><th>Forma Pago</th></tr>
                        </thead>
                        <tbody id="clientesBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nuevo Cliente -->
<div class="modal fade" id="nuevoClienteModal" tabindex="-1" aria-modal="true" aria-labelledby="nuevoClienteModalTitle">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="nuevoClienteModalTitle">Nuevo Cliente</h5>
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
                            <input type="text" class="form-control fact-form-control" id="ncNif" name="ncNif" placeholder="12345678A">
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

<!-- Modal Artículo -->
<div class="modal fade" id="articuloModal" tabindex="-1" aria-modal="true" aria-labelledby="articuloModalTitle">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="articuloModalTitle">Seleccionar Artículo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control fact-form-control" id="buscarArticulo"
                           placeholder="Buscar por código, descripción o código de barras...">
                </div>
                <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                    <table class="table table-hover table-sm mb-0 fact-table" id="articulosTable">
                        <thead class="table-light sticky-top">
                            <tr><th>Código</th><th>Descripción</th><th>IVA</th><th>Precio</th></tr>
                        </thead>
                        <tbody id="articulosBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Guardar y enviar a VeriFACTU -->
<div class="modal fade" id="guardarEnviarModal" tabindex="-1" aria-modal="true" aria-labelledby="guardarEnviarModalTitle">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="guardarEnviarModalTitle">
                    <i class="bi bi-send me-2"></i>Guardar y enviar a VeriFACTU
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Se guardará la factura y se enviará a Hacienda automáticamente.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnConfirmarGuardarEnviar">
                    <i class="bi bi-send me-1"></i>Confirmar y enviar
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

ob_start();
?>
<script src="/SistemaGestionFacturas/assets/js/facturas/nuevo.js" defer></script>
<?php
$extra_js = ob_get_clean();

include __DIR__ . '/../layout/base.php';
