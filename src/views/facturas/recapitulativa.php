<?php
/**
 * Factura Recapitulativa
 * Sistema de Gestión de Facturas / VeriFACTU
 */

$fact_page_title    = 'Recapitulativa';
$fact_page_subtitle = 'Generar factura recapitulativa desde albaranes';
$fact_active_menu   = 'facturas';

ob_start();
?>

<div class="row">
    <div class="col-lg-12">

        <!-- Filtros -->
        <div class="card mb-4 fact-card">
            <div class="card-header d-flex justify-content-between align-items-center fact-card-header">
                <span><i class="bi bi-funnel me-2"></i>Filtros</span>
                <button class="btn btn-sm btn-outline-secondary fact-btn-collapse"
                    type="button" data-bs-toggle="collapse" data-bs-target="#filtrosRecapCollapse">
                    <i class="bi bi-chevron-down"></i>
                </button>
            </div>
            <div class="collapse show" id="filtrosRecapCollapse">
                <div class="card-body fact-card-body">
                    <form id="filtrosFormRecap">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fact-form-label">Cliente</label>
                                <select id="filtroClienteRecap" name="id_cliente"
                                    class="form-select form-select-sm fact-form-select">
                                    <option value="">Todos</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fact-form-label">Desde</label>
                                <input type="date" id="filtroDesdeRecap" name="fecha_desde"
                                    class="form-control form-control-sm fact-form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fact-form-label">Hasta</label>
                                <input type="date" id="filtroHastaRecap" name="fecha_hasta"
                                    class="form-control form-control-sm fact-form-control">
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12 d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary btn-sm fact-btn">
                                    <i class="bi bi-search me-1"></i>Buscar albaranes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Albaranes -->
        <div class="card fact-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center fact-card-header">
                <span><i class="bi bi-list me-2"></i>Albaranes pendientes de facturación</span>
                <button type="button" id="btnSeleccionarTodo"
                    class="btn btn-sm btn-outline-secondary fact-btn">
                    <i class="bi bi-check2-all me-1"></i>Seleccionar / deseleccionar todo
                </button>
            </div>
            <div class="card-body p-0 fact-card-body" style="padding:0!important;">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 fact-table" id="albaranesTable">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:50px;">
                                    <i class="bi bi-check2-square"></i>
                                </th>
                                <th>Código</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Serie</th>
                                <th class="text-end">Total</th>
                                <th>Ref. Cliente</th>
                            </tr>
                        </thead>
                        <tbody id="albaranesBody">
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    Aplica filtros y pulsa <strong>Buscar albaranes</strong>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer fact-card-footer">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted small" id="paginationInfoRecap">Mostrando 0 de 0 registros</div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0 fact-pagination" id="paginationNavRecap"></ul>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Generar recapitulativa -->
        <div class="card fact-card">
            <div class="card-header fact-card-header">
                <span><i class="bi bi-collection me-2"></i>Generar factura recapitulativa</span>
            </div>
            <div class="card-body fact-card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fact-form-label">Fecha de la factura</label>
                        <input type="date" id="fechaRecap" name="fecha"
                            class="form-control form-control-sm fact-form-control"
                            value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-4">
                        <p class="mb-1 text-muted small">Selección actual</p>
                        <p class="mb-0 fw-semibold" id="resumenSeleccion">Ningún albarán seleccionado</p>
                    </div>
                    <div class="col-md-4 d-flex gap-2 justify-content-end">
                        <a href="/SistemaGestionFacturas/src/views/facturas/listado.php"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Volver
                        </a>
                        <button type="button" id="btnGenerarRecapitulativa"
                            class="btn btn-sm btn-primary fact-btn" disabled>
                            <i class="bi bi-collection me-1"></i>Generar recapitulativa
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php
$content = ob_get_clean();

ob_start();
?>
<script src="/SistemaGestionFacturas/assets/js/Facturas/recapitulativa.js" defer></script>
<?php
$extra_js = ob_get_clean();

include __DIR__ . '/../layout/base.php';
