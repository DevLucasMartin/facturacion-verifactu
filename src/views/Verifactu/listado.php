<?php
/**
 * Listado de Verifactu
 * Sistema de Gestión de Facturas / VeriFACTU
 */

$fact_page_title    = 'Verifactu';
$fact_page_subtitle = 'Facturación electrónica AEAT';
$fact_active_menu   = 'verifactu';

ob_start();
?>

<!-- Banner certificado — rellenado por cargarCertificado() -->
<div id="certExpiryBanner" class="alert alert-dismissible fade show mb-4 d-none" role="alert">
    <i class="bi bi-shield-exclamation me-2"></i>
    <span id="certExpiryMessage"></span>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
</div>

<!-- Tarjetas de estadísticas -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card fact-card">
            <div class="card-body fact-card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0 text-muted">Total Registros</h6>
                        <h2 class="mb-0" id="statTotal">-</h2>
                    </div>
                    <i class="bi bi-file-earmark-text fs-1" style="color: var(--fact-primary); opacity: 0.5;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card fact-card">
            <div class="card-body fact-card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0 text-muted">Verificados</h6>
                        <h2 class="mb-0 text-success" id="statEnviados">-</h2>
                    </div>
                    <i class="bi bi-check-circle fs-1 text-success opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card fact-card">
            <div class="card-body fact-card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0 text-muted">Pendientes</h6>
                        <h2 class="mb-0 text-warning" id="statPendientes">-</h2>
                    </div>
                    <i class="bi bi-clock fs-1 text-warning opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card fact-card">
            <div class="card-body fact-card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0 text-muted">Errores</h6>
                        <h2 class="mb-0 text-danger" id="statErrores">-</h2>
                    </div>
                    <i class="bi bi-exclamation-triangle fs-1 text-danger opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Acciones y filtros -->
<div class="card fact-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center fact-card-header">
        <span><i class="bi bi-gear me-2"></i>Acciones</span>
    </div>
    <div class="card-body fact-card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <select class="fact-form-select form-select form-select-sm" id="filtroEstado" onchange="aplicarFiltro()">
                    <option value="">Todos los estados</option>
                    <option value="PENDIENTE">Pendientes</option>
                    <option value="ENVIADO">Enviados</option>
                    <option value="ERROR">Errores</option>
                </select>
            </div>
            <div class="col-md-6">
                <select class="fact-form-select form-select form-select-sm" id="filtroTipo" onchange="aplicarFiltro()">
                    <option value="">Todos los tipos</option>
                    <option value="FACTURA">Facturas</option>
                    <option value="RECTIFICATIVA">Rectificativas</option>
                    <option value="SIMPLIFICADA">Simplificadas</option>
                    <option value="RECAPITULATIVA">Recapitulativas</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Listado -->
<div class="card fact-card">
    <div class="card-header d-flex justify-content-between align-items-center fact-card-header">
        <span><i class="bi bi-list me-2"></i>Registros de Facturación</span>
        <span class="badge bg-secondary" id="registrosCount">0 registros</span>
    </div>
    <div class="card-body p-0 fact-card-body" style="padding:0!important;">
        <div class="table-responsive">
            <table class="fact-table table mb-0" id="verifactuTable">
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th class="text-end">Total</th>
                        <th>Estado</th>
                        <th>CSV</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody id="registrosBody">
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
    <div class="card-footer fact-card-footer d-flex justify-content-between align-items-center">
        <span class="text-muted small" id="paginationInfo"></span>
        <nav>
            <ul class="pagination pagination-sm fact-pagination mb-0" id="paginationNav"></ul>
        </nav>
    </div>
</div>

<!-- Modal Detalles -->
<div class="modal fade" id="detallesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--fact-primary);">
                <h5 class="modal-title text-white">
                    <i class="bi bi-file-earmark-text me-2"></i>Detalles del Registro
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body fact-card-body" id="detallesContent">
                <div class="text-center py-4">
                    <div class="spinner-border" style="color: var(--fact-primary);" role="status"></div>
                </div>
            </div>
            <div class="modal-footer fact-card-footer">
                <button type="button" class="btn fact-btn btn-primary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

ob_start();
?>
<script src="/SistemaGestionFacturas/assets/js/Verifactu/listado.js" defer></script>
<?php
$extra_js = ob_get_clean();

include __DIR__ . '/../layout/base.php';
