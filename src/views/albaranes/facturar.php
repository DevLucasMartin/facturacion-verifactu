<?php
/**
 * Facturar Albaranes
 */

$albaranes = $_GET['albaranes'] ?? [];

if (empty($albaranes) || !is_array($albaranes)) {
    header('Location: /SistemaGestionFacturas/src/views/albaranes/listado.php');
    exit;
}

// Sanitizar códigos
$albaranes = array_map(function($c) {
    return is_string($c) ? trim($c) : '';
}, $albaranes);
$albaranes = array_filter($albaranes, fn($c) => $c !== '');
$albaranes = array_values($albaranes);

if (empty($albaranes)) {
    header('Location: /SistemaGestionFacturas/src/views/albaranes/listado.php');
    exit;
}

$fact_page_title    = 'Facturar albaranes';
$fact_page_subtitle = 'Crear factura desde albaranes';
$fact_active_menu   = 'albaranes';

ob_start();
?>

<form id="facturarForm" method="POST">

    <div class="row">
        <!-- Columna principal -->
        <div class="col-lg-8">

            <!-- Datos de la factura -->
            <div class="card mb-4 fact-card">
                <div class="card-header fact-card-header">
                    <span><i class="bi bi-file-earmark-text me-2"></i>Datos de la Factura</span>
                </div>
                <div class="card-body fact-card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fact-form-label">Tipo de Documento *</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="Tipo_Documento"
                                        id="tipoFactura" value="FACTURA" checked>
                                    <label class="form-check-label" for="tipoFactura">Factura</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="Tipo_Documento"
                                        id="tipoSimplificada" value="SIMPLIFICADA">
                                    <label class="form-check-label" for="tipoSimplificada">Simplificada</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fact-form-label">Canal *</label>
                            <select name="Id_Canal" id="selectCanal" class="form-select fact-form-select" required>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fact-form-label">Fecha *</label>
                            <input type="date" class="form-control fact-form-control" name="Fecha"
                                value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-12">
                            <label class="form-label fact-form-label">Observaciones</label>
                            <textarea class="form-control fact-form-control" name="Observaciones" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Destinatario (solo simplificada) -->
            <div class="card mb-4 fact-card" id="destinatarioSimplificada" style="display:none;">
                <div class="card-header fact-card-header">
                    <span><i class="bi bi-person-check me-2"></i>Destinatario (Simplificada)</span>
                </div>
                <div class="card-body fact-card-body">
                    <div class="mb-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="destinatario_tipo"
                                id="destParticular" value="particular" checked>
                            <label class="form-check-label" for="destParticular">Particular</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="destinatario_tipo"
                                id="destEmpresa" value="empresa">
                            <label class="form-check-label" for="destEmpresa">Empresa / Profesional</label>
                        </div>
                    </div>
                    <div id="avisoSimplificada" class="alert alert-warning mb-0" style="display:none;">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <span id="avisoSimplificadaTexto"></span>
                    </div>
                </div>
            </div>

            <!-- Cliente -->
            <div class="card mb-4 fact-card">
                <div class="card-header fact-card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-person me-2"></i>Cliente de la factura</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary fact-btn"
                        onclick="seleccionarCliente()">
                        <i class="bi bi-search me-1"></i>Buscar cliente
                    </button>
                </div>
                <div class="card-body fact-card-body">
                    <input type="hidden" name="Id_Cliente" id="inputIdCliente">

                    <div id="clienteVacio" class="text-muted text-center py-3">
                        <i class="bi bi-person-plus fs-1 d-block mb-2"></i>
                        <div class="position-relative d-inline-block">
                            <button type="button" id="clienteDisplayBox"
                                class="btn btn-outline-secondary btn-sm"
                                title="Ver clientes de los albaranes">
                                <i class="bi bi-people me-1"></i>Clientes de los albaranes
                            </button>
                            <div id="clienteAlbaranesDropdown"
                                style="display:none; position:absolute; top:100%; left:0; z-index:1000;
                                       background:#fff; border:1px solid #dee2e6; border-radius:4px;
                                       min-width:220px; box-shadow:0 2px 8px rgba(0,0,0,.15);">
                            </div>
                        </div>
                    </div>

                    <div id="clienteInfo" style="display:none;">
                        <dl class="row mb-0">
                            <dt class="col-sm-3">Nombre:</dt>
                            <dd class="col-sm-9" id="clienteNombre"></dd>
                            <dt class="col-sm-3">NIF:</dt>
                            <dd class="col-sm-9" id="clienteNIF"></dd>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- NIF exportación -->
            <div class="card mb-4 fact-card d-none" id="nifExportacionSection">
                <div class="card-header fact-card-header">
                    <span><i class="bi bi-globe me-2"></i>Datos de exportación</span>
                </div>
                <div class="card-body fact-card-body">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-6">
                            <label class="form-label fact-form-label">NIF del destinatario de exportación</label>
                            <input type="text" class="form-control fact-form-control" id="inputNifExportacion"
                                oninput="cambiarNifExportacion(this.value)"
                                placeholder="NIF / VAT Number">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="checkNifVacio"
                                    onchange="cambiarNifExportacionVacio(this.checked)">
                                <label class="form-check-label" for="checkNifVacio">
                                    Sin NIF (destinatario sin identificación fiscal)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Albaranes y líneas -->
            <div class="card mb-4 fact-card">
                <div class="card-header fact-card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-check me-2"></i>Líneas a facturar</span>
                    <div class="d-flex gap-2 align-items-center">
                        <small class="text-muted" id="resumenLineas">Ninguna línea seleccionada.</small>
                        <button type="button" class="btn btn-outline-secondary btn-sm fact-btn"
                            id="btnSeleccionarTodas"
                            onclick="toggleTodasGlobal()" disabled>
                            <i class="bi bi-check2-all me-1"></i>Seleccionar todas
                        </button>
                    </div>
                </div>
                <div class="card-body fact-card-body p-0" style="padding:0 !important;">
                    <div id="albaranesContainer">
                        <div class="text-center py-4 text-muted">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            Cargando albaranes...
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Columna lateral -->
        <div class="col-lg-4">
            <!-- Totales -->
            <div class="card mb-4 fact-card">
                <div class="card-header fact-card-header">
                    <span><i class="bi bi-calculator me-2"></i>Totales</span>
                </div>
                <div class="card-body fact-card-body">
                    <div class="mb-3">
                        <label class="form-label fact-form-label mb-1">Forma de Pago</label>
                        <select name="Id_Forma_Pago" id="selectFormaPago" class="form-select form-select-sm fact-form-select">
                            <option value="">Elige la forma de pago...</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <label class="form-label fact-form-label mb-1">Dto. Especial %</label>
                            <input type="number" class="form-control form-control-sm fact-form-control"
                                name="Descuento_Especial" value="0" min="0" max="100" step="any" placeholder="0.00%">
                        </div>
                        <div class="col-4">
                            <label class="form-label fact-form-label mb-1">Dto. Comercial %</label>
                            <input type="number" class="form-control form-control-sm fact-form-control"
                                name="Descuento_Comercial" value="0" min="0" max="100" step="any" placeholder="0.00%">
                        </div>
                        <div class="col-4">
                            <label class="form-label fact-form-label mb-1">Dto. Pronto Pago %</label>
                            <input type="number" class="form-control form-control-sm fact-form-control"
                                name="Descuento_PP" value="0" min="0" max="100" step="any" placeholder="0.00%">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4 fact-card">
                <div class="card-header fact-card-header">
                    <span><i class="bi bi-gear me-2"></i>Acciones</span>
                </div>
                <div class="card-body fact-card-body">
                    <button type="submit" class="btn btn-primary fact-btn w-100 mb-2" id="btnFacturar">
                        <i class="bi bi-file-earmark-check me-1"></i>Facturar y Enviar a Verifactu
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

<?php
$content = ob_get_clean();

ob_start();
?>
<div id="app-data"
    data-codigos="<?= htmlspecialchars(json_encode($albaranes), ENT_QUOTES, 'UTF-8') ?>"
    hidden></div>
<script src="/SistemaGestionFacturas/assets/js/Albaranes/facturar.js" defer></script>
<?php
$extra_js = ob_get_clean();

include __DIR__ . '/../layout/base.php';
