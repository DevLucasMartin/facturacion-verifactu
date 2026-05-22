<?php
/**
 * Gestión - Productos
 * Sistema de Gestión de Facturas / VeriFACTU
 */

$fact_page_title    = 'Productos';
$fact_page_subtitle = 'Gestión de artículos y productos';
$fact_active_menu   = 'productos';

ob_start();
?>

<!-- Acciones -->
<div class="fact-list-actions">
    <input type="text"
           id="productosBuscar"
           class="form-control form-control-sm fact-form-control"
           placeholder="Buscar por código, descripción o código de barras..."
           style="max-width:320px;">
    <button type="button" class="btn btn-primary btn-sm fact-btn" id="btnNuevoProducto">
        <i class="bi bi-plus-lg me-1"></i>Nuevo producto
    </button>
</div>

<!-- Tabla de productos -->
<div class="card fact-card">

    <div class="card-header fact-card-header">
        <span><i class="bi bi-box-seam me-2"></i>Productos</span>
        <span class="ms-auto text-muted" style="font-weight:400;font-size:.8rem;" id="productosTotalLabel"></span>
    </div>

    <div class="card-body p-0 fact-card-body" style="padding:0!important;">
        <div class="table-responsive">
            <table class="table table-hover mb-0 fact-table" id="productosTable">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Descripción</th>
                        <th>Cód. Barras</th>
                        <th>Tipo IVA</th>
                        <th class="text-end">Precio venta</th>
                        <th class="text-end">Dto %</th>
                        <th class="text-center">Activo</th>
                        <th class="text-center" style="width:110px;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="productosTableBody">
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            Cargando...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginación -->
    <div class="card-footer fact-card-footer d-flex align-items-center justify-content-between" id="productosPaginacion" style="display:none;">
        <small class="text-muted" id="productosPagInfo"></small>
        <nav>
            <ul class="pagination pagination-sm mb-0 fact-pagination" id="productosPagLinks"></ul>
        </nav>
    </div>

</div>

<!-- Modal Nuevo / Editar Producto -->
<div class="modal fade" id="productoModal" tabindex="-1" aria-modal="true" aria-labelledby="productoModalTitle">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productoModalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="productoMsg" class="alert alert-info d-none mb-3"></div>
                <form id="productoForm">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fact-form-label">Código <span class="text-danger">*</span></label>
                            <input type="text" class="form-control fact-form-control" id="pCodigo" name="pCodigo"
                                   placeholder="ART001" maxlength="30"
                                   oninput="prod_validarCodigo(this)">
                            <div class="invalid-feedback" id="pCodigoError"></div>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label fact-form-label">Descripción <span class="text-danger">*</span></label>
                            <input type="text" class="form-control fact-form-control" id="pDescripcion" name="pDescripcion"
                                   placeholder="Nombre del producto" maxlength="150">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fact-form-label">Modelo</label>
                            <input type="text" class="form-control fact-form-control" id="pModelo" name="pModelo"
                                   placeholder="Referencia del fabricante" maxlength="60">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fact-form-label">Código de barras</label>
                            <input type="text" class="form-control fact-form-control" id="pCodigoBarras" name="pCodigoBarras"
                                   placeholder="EAN13 / UPC..." maxlength="30">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fact-form-label">Tipo IVA <span class="text-danger">*</span></label>
                            <select class="form-select fact-form-select" id="pTipoIVA" name="pTipoIVA"></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fact-form-label">Precio venta 1 <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control fact-form-control" id="pPrecio1" name="pPrecio1"
                                       min="0" step="0.0001" value="0">
                                <span class="input-group-text">€</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fact-form-label">Precio venta 2</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control fact-form-control" id="pPrecio2" name="pPrecio2"
                                       min="0" step="0.0001" value="0">
                                <span class="input-group-text">€</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fact-form-label">Precio venta 3</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control fact-form-control" id="pPrecio3" name="pPrecio3"
                                       min="0" step="0.0001" value="0">
                                <span class="input-group-text">€</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fact-form-label">Descuento %</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control fact-form-control" id="pDescuento" name="pDescuento"
                                       min="0" max="100" step="0.01" value="0">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-md-12 d-flex align-items-center gap-2 mt-1" id="pActivoWrapper" style="display:none!important;">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="pActivo" name="pActivo" checked>
                                <label class="form-check-label fact-form-label" for="pActivo">Activo</label>
                            </div>
                        </div>
                    </div>
                    <hr class="my-3">
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary fact-btn" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary fact-btn" id="pBtnGuardar">
                            <i class="bi bi-check-lg me-1"></i>Guardar producto
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
<script src="/SistemaGestionFacturas/assets/js/Gestion/productos.js" defer></script>
<?php
$extra_js = ob_get_clean();

include __DIR__ . '/../layout/base.php';
