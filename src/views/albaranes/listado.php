<?php
/**
 * Listado de Albaranes
 */

$fact_page_title    = 'Albaranes';
$fact_page_subtitle = 'Gestión de documentos de entrega';
$fact_active_menu   = 'albaranes';

ob_start();
?>

<!-- Filtros -->
<div class="card mb-4 fact-card">
    <div class="card-header d-flex justify-content-between align-items-center fact-card-header">
        <span><i class="bi bi-funnel me-2"></i>Filtros</span>
        <button class="btn btn-sm btn-outline-secondary fact-btn-collapse"
            type="button" data-bs-toggle="collapse" data-bs-target="#filtrosCollapse">
            <i class="bi bi-chevron-down"></i>
        </button>
    </div>
    <div class="collapse show" id="filtrosCollapse">
        <div class="card-body fact-card-body">
            <form id="filtrosForm">
                <!-- FILA 1 -->
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fact-form-label">Código</label>
                        <select name="codigo" class="form-select form-select-sm fact-form-select" id="filtroCodigo">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fact-form-label">Canal</label>
                        <select name="id_canal" class="form-select form-select-sm fact-form-select" id="filtroCanal">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fact-form-label">Cliente</label>
                        <select name="id_cliente" class="form-select form-select-sm fact-form-select" id="filtroCliente">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fact-form-label">Estado</label>
                        <select name="facturado" class="form-select form-select-sm fact-form-select" id="filtroEstado">
                            <option value="">Todos</option>
                            <option value="N">No facturado</option>
                            <option value="S">Facturado</option>
                        </select>
                    </div>
                </div>
                <!-- FILA 2 -->
                <div class="row g-3 mt-0">
                    <div class="col-md-3">
                        <label class="form-label fact-form-label">Cobrado</label>
                        <select name="cobrado" id="filtroCobrado" class="form-select form-select-sm fact-form-select">
                            <option value="">Todos</option>
                            <option value="S">Cobrado</option>
                            <option value="N">Pendiente</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fact-form-label">Desde</label>
                        <input type="date" name="fecha_desde" class="form-control form-control-sm fact-form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fact-form-label">Hasta</label>
                        <input type="date" name="fecha_hasta" class="form-control form-control-sm fact-form-control">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-sm fact-btn w-100">
                            <i class="bi bi-search me-1"></i>Buscar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="fact-list-actions d-flex flex-wrap gap-2 mb-3">
    <a href="/SistemaGestionFacturas/src/views/albaranes/nuevo.php"
       class="btn btn-primary btn-sm fact-btn">
        <i class="bi bi-plus-lg me-1"></i>Nuevo albarán
    </a>
    <button type="button" class="btn btn-outline-secondary btn-sm fact-btn"
        onclick="abrirModalPlantillas()">
        <i class="bi bi-layout-text-window me-1"></i>Plantillas
    </button>
    <button type="button" class="btn btn-success btn-sm fact-btn"
        id="btnExportarExcel" title="Exportar a Excel">
        <i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel
    </button>
    <button type="button" class="btn btn-warning btn-sm fact-btn"
        id="btnFacturarSeleccionados"
        style="display:none !important;"
        onclick="facturarSeleccionados()">
        <i class="bi bi-file-earmark-check me-1"></i>Facturar
        (<span id="numSeleccionados">0</span>)
    </button>
    <div id="exportIndicator" style="display:none;" class="align-self-center">
        <span class="spinner-border spinner-border-sm text-success me-1"></span>
        <small class="text-muted">Generando Excel...</small>
    </div>
</div>

<!-- LISTADO -->
<div class="card fact-card">
    <div class="card-header d-flex justify-content-between align-items-center fact-card-header">
        <span><i class="bi bi-list me-2"></i>Resultados</span>
    </div>
    <div class="card-body p-0 fact-card-body" style="padding:0!important;">
        <div class="table-responsive">
            <table class="table table-hover mb-0 fact-table" id="albaranesTable">
                <thead>
                    <tr>
                        <th class="text-center" style="width:40px;">
                            <input type="checkbox" class="form-check-input" id="checkAll"
                                onchange="toggleCheckAll(this)">
                        </th>
                        <th>Código</th>
                        <th>Fecha</th>
                        <th>Canal</th>
                        <th>Cliente</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Facturado</th>
                        <th class="text-center" style="width:110px;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="albaranesTableBody">
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            Cargando albaranes...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div id="mensaje-buscando" style="display:none; text-align:center; padding:20px;">
        <i class="bi bi-lightbulb"></i> Por favor, rellena al menos un filtro y pulsa
        <strong>Buscar</strong> para mostrar resultados.
    </div>

    <div class="card-footer fact-card-footer">
        <div class="d-flex justify-content-between align-items-center">
            <div class="text-muted small" id="paginationInfo">Mostrando 0 de 0 registros</div>
            <nav>
                <ul class="pagination pagination-sm mb-0 fact-pagination" id="paginationNav"></ul>
            </nav>
        </div>
    </div>
</div>

<!-- Modal Plantillas -->
<div class="modal fade" id="plantillasModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-layout-text-window me-2"></i>Plantillas de albarán</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control fact-form-control" id="buscadorPlantillas"
                        placeholder="Buscar plantilla...">
                </div>
                <div class="table-responsive" style="max-height:400px; overflow-y:auto;">
                    <table class="table table-hover table-sm mb-0 fact-table">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Nombre</th>
                                <th>Canal</th>
                                <th>Cliente</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="plantillasBody">
                            <tr>
                                <td colspan="4" class="text-center text-muted">Cargando...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Confirmar Eliminar Plantilla -->
<div class="modal fade" id="confirmarEliminarModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar eliminación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Está seguro de que desea eliminar esta plantilla?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger btn-sm" id="btnConfirmarEliminar">Eliminar</button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

ob_start();
?>
<script src="/SistemaGestionFacturas/assets/js/Albaranes/listado.js" defer></script>
<?php
$extra_js = ob_get_clean();

include __DIR__ . '/../layout/base.php';
