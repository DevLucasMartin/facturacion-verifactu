<?php
/**
 * Listado de Facturas
 * Sistema de Gestión de Facturas / VeriFACTU
 */

$fact_page_title    = 'Facturas';
$fact_page_subtitle = 'Gestión de documentos de venta';
$fact_active_menu   = 'facturas';

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
                    <div class="col-md-4">
                        <label class="form-label fact-form-label">Código</label>
                        <select name="codigo" class="form-select form-select-sm fact-form-select" id="filtroCodigo">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fact-form-label">Canal</label>
                        <select name="id_canal" class="form-select form-select-sm fact-form-select" id="filtroCanal">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fact-form-label">Cliente</label>
                        <select name="id_cliente" class="form-select form-select-sm fact-form-select" id="filtroCliente">
                            <option value="">Todos</option>
                        </select>
                    </div>
                </div>
                <!-- FILA 2 -->
                <div class="row g-3 mt-0">
                    <div class="col-md-3">
                        <label class="form-label fact-form-label">Tipo</label>
                        <select name="tipo_documento" id="filtroTipo" class="form-select form-select-sm fact-form-select">
                            <option value="">Todos</option>
                            <option value="FACTURA">Factura</option>
                            <option value="SIMPLIFICADA">Simplificada</option>
                            <option value="RECTIFICATIVA">Rectificativa</option>
                            <option value="RECAPITULATIVA">Recapitulativa</option>
                        </select>
                    </div>
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
                </div>
                <!-- BOTÓN -->
                <div class="row mt-3">
                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary btn-sm fact-btn">
                            <i class="bi bi-search"></i> Buscar
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
        <i class="bi bi-plus-lg me-1"></i>Nuevo documento
    </a>
    <a href="/SistemaGestionFacturas/src/views/facturas/recapitulativa.php"
       class="btn btn-outline-primary btn-sm fact-btn ms-2">
        <i class="bi bi-collection me-1"></i>Recapitulativa
    </a>
    <button type="button" class="btn btn-success btn-sm fact-btn ms-2"
        id="btnExportarExcel" title="Exportar a Excel">
        <i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel
    </button>
    <div id="exportIndicator" style="display:none;" class="align-self-center ms-2">
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
            <table class="table table-hover mb-0 fact-table" id="facturasTable">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Cliente</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Cobrado</th>
                        <th class="text-center">Verifactu</th>
                        <th class="text-center" style="width:120px;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="facturasTableBody">
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            Cargando facturas...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div id="mensaje-buscando" style="display:none;text-align:center;padding:20px;">
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

<?php
$content = ob_get_clean();

ob_start();
?>
<script src="/SistemaGestionFacturas/assets/js/Facturas/listado.js" defer></script>
<?php
$extra_js = ob_get_clean();

include __DIR__ . '/../layout/base.php';
