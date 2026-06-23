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
<!-- Barra de acciones -->
<div class="fact-list-actions justify-content-end mb-3">
    <a href="/SistemaGestionFacturas/src/views/albaranes/nuevo.php"
       class="btn btn-primary btn-sm fact-btn">
        <i class="bi bi-plus-lg me-1"></i>Nuevo documento
    </a>
    <a href="/SistemaGestionFacturas/src/views/facturas/recapitulativa.php"
       class="btn btn-outline-primary btn-sm fact-btn">
        <i class="bi bi-collection me-1"></i>Recapitulativa
    </a>
    <button type="button" class="btn btn-outline-secondary btn-sm fact-btn"
        id="btnExportarExcel" title="Exportar a Excel">
        <i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel
    </button>
</div>

<!-- LISTADO -->
<div class="card fact-card">
    <!-- ===== Toolbar de filtros (barra horizontal) ===== -->
    <div class="fact-filter-bar">
        <form id="filtrosForm" class="d-flex flex-wrap align-items-end gap-2">
            <div class="fb-field">
                <label class="form-label fact-form-label">Código</label>
                <select name="codigo" class="form-select form-select-sm fact-form-select" id="filtroCodigo">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="fb-field">
                <label class="form-label fact-form-label">Canal</label>
                <select name="id_canal" class="form-select form-select-sm fact-form-select" id="filtroCanal">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="fb-field fb-field-wide">
                <label class="form-label fact-form-label">Cliente</label>
                <select name="id_cliente" class="form-select form-select-sm fact-form-select" id="filtroCliente">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="fb-field">
                <label class="form-label fact-form-label">Tipo</label>
                <select name="tipo_documento" id="filtroTipo" class="form-select form-select-sm fact-form-select">
                    <option value="">Todos</option>
                    <option value="FACTURA">Factura</option>
                    <option value="SIMPLIFICADA">Simplificada</option>
                    <option value="RECTIFICATIVA">Rectificativa</option>
                    <option value="RECAPITULATIVA">Recapitulativa</option>
                </select>
            </div>
            <div class="fb-field">
                <label class="form-label fact-form-label">Cobrado</label>
                <select name="cobrado" id="filtroCobrado" class="form-select form-select-sm fact-form-select">
                    <option value="">Todos</option>
                    <option value="S">Cobrado</option>
                    <option value="N">Pendiente</option>
                </select>
            </div>
            <div class="fb-field fb-field-date">
                <label class="form-label fact-form-label">Desde</label>
                <input type="date" name="fecha_desde" class="form-control form-control-sm fact-form-control">
            </div>
            <div class="fb-field fb-field-date">
                <label class="form-label fact-form-label">Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control form-control-sm fact-form-control">
            </div>
            <div class="fb-field fb-field-btn">
                <button type="submit" class="btn btn-primary btn-sm fact-btn">
                    <i class="bi bi-search me-1"></i>Buscar
                </button>
            </div>
        </form>
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
                        <td colspan="8" class="text-center py-5 text-muted">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            Cargando facturas...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div id="mensaje-buscando" style="display:none;text-align:center;padding:28px;" class="text-muted">
        <i class="bi bi-lightbulb fs-4 d-block mb-2"></i>
        Rellena al menos un filtro y pulsa <strong>Buscar</strong> para mostrar resultados.
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

<style>
    /* Toolbar horizontal de filtros sobre la tabla */
    .fact-filter-bar {
        padding: .9rem 1.15rem;
        border-bottom: 1px solid var(--border-soft);
        background: var(--bg);
        border-top-left-radius: var(--radius);
        border-top-right-radius: var(--radius);
    }
    .fact-filter-bar .fb-field { flex: 1 1 150px; min-width: 130px; }
    .fact-filter-bar .fb-field-wide { flex: 2 1 200px; }
    .fact-filter-bar .fb-field-date { flex: 0 0 150px; min-width: 130px; }
    .fact-filter-bar .fb-field-btn { flex: 0 0 auto; }
    /* Que Select2 ocupe el ancho del campo dentro de la toolbar */
    .fact-filter-bar .select2-container { width: 100% !important; }
</style>

<?php
$content = ob_get_clean();

ob_start();
?>
<script src="/SistemaGestionFacturas/assets/js/Facturas/listado.js" defer></script>
<?php
$extra_js = ob_get_clean();

include __DIR__ . '/../layout/base.php';
