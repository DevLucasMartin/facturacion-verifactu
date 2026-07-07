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

<!-- KPIs: barra segmentada única -->
<div class="vf-statbar mb-4">
    <div class="vf-seg" data-tone="brand">
        <span class="vf-seg-value" id="statTotal">-</span>
        <span class="vf-seg-label">Total Registros</span>
    </div>
    <div class="vf-seg" data-tone="success">
        <span class="vf-seg-value" id="statEnviados">-</span>
        <span class="vf-seg-label">Verificados</span>
    </div>
    <div class="vf-seg" data-tone="warning">
        <span class="vf-seg-value" id="statPendientes">-</span>
        <span class="vf-seg-label">Pendientes</span>
    </div>
    <div class="vf-seg" data-tone="danger">
        <span class="vf-seg-value" id="statErrores">-</span>
        <span class="vf-seg-label">Errores</span>
    </div>
</div>

<!-- Listado -->
<div class="card fact-card">
    <div class="card-header fact-card-header vf-list-header">
        <div class="vf-list-title">
            <i class="bi bi-shield-check me-2 text-muted"></i>Registros de Facturación
            <span class="badge fact-badge-factura ms-2" id="registrosCount">0 registros</span>
        </div>
        <div class="vf-list-filters">
            <button type="button" class="btn btn-warning btn-sm fact-btn" id="btnProcesarCola"
                    title="Enviar a Hacienda las facturas pendientes en cola" disabled>
                <i class="bi bi-cloud-arrow-up me-1"></i>Procesar cola
                <span class="badge bg-light text-dark ms-1" id="colaCount">0</span>
            </button>
            <select class="fact-form-select form-select form-select-sm" id="filtroEstado" onchange="aplicarFiltro()">
                <option value="">Todos los estados</option>
                <option value="PENDIENTE">Pendientes</option>
                <option value="ENVIADO">Enviados</option>
                <option value="ERROR">Errores</option>
            </select>
            <select class="fact-form-select form-select form-select-sm" id="filtroTipo" onchange="aplicarFiltro()">
                <option value="">Todos los tipos</option>
                <option value="FACTURA">Facturas</option>
                <option value="RECTIFICATIVA">Rectificativas</option>
                <option value="SIMPLIFICADA">Simplificadas</option>
                <option value="RECAPITULATIVA">Recapitulativas</option>
            </select>
        </div>
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

<style>
    /* ===== KPIs: barra segmentada única ===== */
    .vf-statbar {
        display: flex;
        background: var(--surface); border: 1px solid var(--border);
        border-radius: var(--radius); box-shadow: var(--shadow-sm);
        overflow: hidden;
    }
    .vf-seg {
        flex: 1 1 0; min-width: 0;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: .15rem; padding: 1.1rem .75rem; text-align: center;
        border-left: 1px solid var(--border-soft); position: relative;
    }
    .vf-seg:first-child { border-left: 0; }
    .vf-seg::before {
        content: ""; position: absolute; top: 0; left: 0; right: 0; height: 3px;
        background: var(--seg-color, var(--brand));
    }
    .vf-seg-value { font-size: 1.85rem; font-weight: 700; color: var(--seg-color, var(--text)); letter-spacing: -.02em; line-height: 1; }
    .vf-seg-label { font-size: .78rem; font-weight: 600; color: var(--text-muted); }
    .vf-seg[data-tone="brand"]   { --seg-color: var(--brand); }
    .vf-seg[data-tone="success"] { --seg-color: #16a34a; }
    .vf-seg[data-tone="warning"] { --seg-color: #d97706; }
    .vf-seg[data-tone="danger"]  { --seg-color: #dc2626; }
    @media (max-width: 560px) {
        .vf-statbar { flex-wrap: wrap; }
        .vf-seg { flex: 1 1 50%; border-top: 1px solid var(--border-soft); }
    }

    /* ===== Cabecera de la tabla con filtros integrados ===== */
    .vf-list-header {
        display: flex; flex-wrap: wrap; gap: .75rem;
        align-items: center; justify-content: space-between;
    }
    .vf-list-title { font-weight: 600; display: flex; align-items: center; }
    /* Botón + filtros SIEMPRE en una fila horizontal. Los form-select de
       Bootstrap son width:100% por defecto (por eso se apilaban): los fijamos. */
    .vf-list-filters {
        display: flex; flex-wrap: nowrap; align-items: center; gap: .5rem;
    }
    .vf-list-filters > * { flex: 0 0 auto; }
    .vf-list-filters .form-select { width: auto; min-width: 160px; }
    .vf-list-filters .btn { white-space: nowrap; }
    @media (max-width: 640px) {
        .vf-list-filters { flex-wrap: wrap; }
    }
</style>

<?php
$content = ob_get_clean();

ob_start();
?>
<?php $vfJs = __DIR__ . '/../../../assets/js/Verifactu/listado.js'; $vfVer = is_file($vfJs) ? filemtime($vfJs) : time(); ?>
<script src="/SistemaGestionFacturas/assets/js/Verifactu/listado.js?v=<?= $vfVer ?>" defer></script>
<?php
$extra_js = ob_get_clean();

include __DIR__ . '/../layout/base.php';
