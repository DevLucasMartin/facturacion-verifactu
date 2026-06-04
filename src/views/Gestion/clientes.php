<?php
/**
 * Gestión - Clientes
 * Sistema de Gestión de Facturas / VeriFACTU
 */

$fact_page_title    = 'Clientes';
$fact_page_subtitle = 'Gestión de clientes';
$fact_active_menu   = 'clientes';

ob_start();
?>

<!-- Acciones -->
<div class="fact-list-actions">
    <input type="text"
           id="clientesBuscar"
           class="form-control form-control-sm fact-form-control"
           placeholder="Buscar por nombre, NIF o código..."
           style="max-width:280px;">
    <button type="button" class="btn btn-primary btn-sm fact-btn" id="btnNuevoCliente">
        <i class="bi bi-plus-lg me-1"></i>Nuevo cliente
    </button>
</div>

<!-- Tabla de clientes -->
<div class="card fact-card">

    <div class="card-header fact-card-header">
        <span><i class="bi bi-people me-2"></i>Clientes</span>
        <span class="ms-auto text-muted" style="font-weight:400;font-size:.8rem;" id="clientesTotalLabel"></span>
    </div>

    <div class="card-body p-0 fact-card-body" style="padding:0!important;">
        <div class="table-responsive">
            <table class="table table-hover mb-0 fact-table" id="clientesTable">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>NIF</th>
                        <th>Forma de pago</th>
                        <th class="text-center">RE</th>
                        <th class="text-center">Activo</th>
                        <th class="text-center" style="width:110px;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="clientesTableBody">
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            Cargando...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginación -->
    <div class="card-footer fact-card-footer d-flex align-items-center justify-content-between" id="clientesPaginacion" style="display:none;">
        <small class="text-muted" id="clientesPagInfo"></small>
        <nav>
            <ul class="pagination pagination-sm mb-0 fact-pagination" id="clientesPagLinks"></ul>
        </nav>
    </div>

</div>

<!-- Modal Nuevo Cliente -->
<div class="modal fade" id="nuevoClienteModal" tabindex="-1" aria-modal="true" aria-labelledby="nuevoClienteModalTitle">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="nuevoClienteModalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="nuevoClienteMsg" class="alert alert-info d-none mb-3"></div>
                <form id="nuevoClienteForm">
                    <div class="row g-3">
                        <div class="col-md-3" id="ncCodigoCol">
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
                                   placeholder="12345678A" oninput="cli_validarNIF(this)">
                            <div class="invalid-feedback" id="ncNifError"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fact-form-label">Forma de pago</label>
                            <select class="form-select fact-form-select" id="ncFormaPago" name="ncFormaPago"></select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fact-form-label">RE %</label>
                            <input type="number" class="form-control fact-form-control" id="ncRE" name="ncRE"
                                   min="0" max="100" step="0.01" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fact-form-label">Email facturación</label>
                            <input type="email" class="form-control fact-form-control" id="ncEmail" name="ncEmail"
                                   placeholder="facturacion@empresa.com">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fact-form-label">Dirección</label>
                            <input type="text" class="form-control fact-form-control" id="ncDireccion" name="ncDireccion"
                                   placeholder="Calle, número, piso...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fact-form-label">Código postal</label>
                            <input type="text" class="form-control fact-form-control" id="ncCodigoPostal" name="ncCodigoPostal"
                                   placeholder="28001" maxlength="10">
                        </div>
                        <div class="col-12">
                            <label class="form-label fact-form-label">Teléfonos</label>
                            <div id="ncTelefonosLista"></div>
                            <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="ncAddTelefono" onclick="cli_addTelefono()">
                                <i class="bi bi-plus-lg me-1"></i>Añadir teléfono
                            </button>
                        </div>
                    </div>
                    <hr class="my-3">
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary fact-btn" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary fact-btn" id="ncBtnGuardar">
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
<script src="/SistemaGestionFacturas/assets/js/Gestion/clientes.js" defer></script>
<?php
$extra_js = ob_get_clean();

include __DIR__ . '/../layout/base.php';
