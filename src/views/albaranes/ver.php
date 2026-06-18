<?php
/**
 * Ver Albarán
 */

$codigo = $_GET['codigo'] ?? '';
$codigo = is_string($codigo) ? trim($codigo) : '';

if ($codigo === '') {
    header('Location: /SistemaGestionFacturas/src/views/albaranes/listado.php');
    exit;
}

$fact_page_title    = 'Albarán';
$fact_page_subtitle = 'Detalle del documento de entrega';
$fact_active_menu   = 'albaranes';

ob_start();
?>

<div class="row">
    <div class="col-lg-8">

        <!-- Datos del documento -->
        <div class="card mb-4 fact-card">
            <div class="card-header d-flex justify-content-between align-items-center fact-card-header">
                <span><i class="bi bi-file-earmark-text me-2"></i>Documento</span>
            </div>
            <div class="card-body" id="albaranData" aria-live="polite">
                <div class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-secondary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cliente -->
        <div class="card mb-4 fact-card">
            <div class="card-header fact-card-header">
                <span><i class="bi bi-person me-2"></i>Cliente</span>
            </div>
            <div class="card-body" id="clientePanel" aria-live="polite">
                <div class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-secondary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Líneas -->
        <div class="card mb-4 fact-card">
            <div class="card-header fact-card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-ul me-2"></i>Líneas de albarán</span>
                <small class="text-muted" id="lineasCount"></small>
            </div>
            <div class="card-body p-0 fact-card-body" style="padding:0 !important;">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 fact-table" id="lineasTable">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Artículo</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-end">Precio</th>
                                <th class="text-end">Dto.%</th>
                                <th class="text-end">Tipo IVA</th>
                                <th class="text-end">IVA</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody id="lineasBody" aria-live="polite">
                            <tr>
                                <td colspan="7" class="text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-secondary" role="status">
                                        <span class="visually-hidden">Cargando...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div id="lineasPaginacion"
                     class="d-flex justify-content-between align-items-center px-3 py-2 border-top"
                     style="display:none !important;"></div>
            </div>
        </div>

    </div>

    <div class="col-lg-4">

        <!-- Totales -->
        <div class="card mb-4 fact-card">
            <div class="card-header fact-card-header">
                <span><i class="bi bi-calculator me-2"></i>Totales</span>
            </div>
            <div class="card-body" id="totalesPanel" aria-live="polite">
                <div class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-secondary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Acciones -->
        <div class="card mb-4 fact-card">
            <div class="card-header fact-card-header">
                <span><i class="bi bi-gear me-2"></i>Acciones</span>
            </div>
            <div class="card-body fact-card-body d-flex flex-wrap gap-2">
                <a id="btnEditar"
                   href="/SistemaGestionFacturas/src/views/albaranes/editar.php?codigo=<?= htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') ?>"
                   class="btn btn-sm btn-outline-primary fact-btn">
                    <i class="bi bi-pencil me-1"></i>Editar
                </a>

                <button type="button" id="btnFacturar"
                    onclick="window.location='/SistemaGestionFacturas/src/views/albaranes/facturar.php?albaranes[]=<?= urlencode($codigo) ?>'"
                    class="btn btn-sm btn-primary fact-btn">
                    <i class="bi bi-file-earmark-check me-1"></i>Facturar
                </button>

                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-danger dropdown-toggle fact-btn"
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="verPdf(); return false;">
                            <i class="bi bi-eye me-2"></i>Ver en pantalla</a></li>
                        <li><a class="dropdown-item" href="#" onclick="descargarPdf(); return false;">
                            <i class="bi bi-download me-2"></i>Descargar</a></li>
                    </ul>
                </div>

                <button type="button" class="btn btn-sm btn-outline-success fact-btn"
                        onclick="enviarEmail(); return false;">
                    <i class="bi bi-envelope me-1"></i>Enviar por email
                </button>

                <button type="button" class="btn btn-sm btn-outline-secondary fact-btn"
                    onclick="abrirModalGuardarPlantilla()">
                    <i class="bi bi-layout-text-window me-1"></i>Guardar como plantilla
                </button>

                <a href="/SistemaGestionFacturas/src/views/albaranes/listado.php"
                   class="btn btn-sm btn-outline-secondary fact-btn">
                    <i class="bi bi-arrow-left me-1"></i>Volver
                </a>
            </div>
        </div>

    </div>
</div>

<!-- Modal Guardar Plantilla -->
<div class="modal fade" id="guardarPlantillaModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Guardar como plantilla</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label fact-form-label">Nombre de la plantilla</label>
                <input type="text" class="form-control fact-form-control" id="inputNombrePlantilla"
                    placeholder="Ej: Pedido mensual cliente X">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnConfirmarPlantilla">
                    <i class="bi bi-floppy me-1"></i>Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

ob_start();
?>
<div id="app-data" data-codigo="<?= htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') ?>" hidden></div>
<script src="/SistemaGestionFacturas/assets/js/Albaranes/ver.js" defer></script>
<?php
$extra_js = ob_get_clean();

include __DIR__ . '/../layout/base.php';
