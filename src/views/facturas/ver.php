<?php
declare(strict_types=1);

$codigo = $_GET['codigo'] ?? '';
$codigo = is_string($codigo) ? trim($codigo) : '';

if ($codigo === '') {
    header('Location: /SistemaGestionFacturas/src/views/facturas/listado.php');
    exit;
}

$fact_page_title    = 'Factura';
$fact_page_subtitle = 'Detalle del documento';
$fact_active_menu   = 'facturas';

ob_start();
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4 fact-card">
            <div class="card-header d-flex justify-content-between align-items-center fact-card-header">
                <span><i class="bi bi-file-earmark-text me-2"></i>Documento</span>
            </div>
            <div class="card-body" id="facturaData" aria-live="polite">
                <div class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-secondary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4 fact-card" id="verifactuCard">
            <div class="card-header d-flex justify-content-between align-items-center fact-card-header">
                <span><i class="bi bi-shield-check me-2"></i>Verifactu</span>
                <span class="badge bg-secondary" id="verifactuBadge">Cargando...</span>
            </div>
            <div class="card-body" id="verifactuPanel" aria-live="polite">
                <div class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-secondary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4 fact-card" id="pagoCard">
            <div class="card-header fact-card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-cash-coin me-2"></i>Pago</span>
                <span class="badge bg-secondary" id="pagoBadge">Cargando...</span>
            </div>
            <div class="card-body" id="pagoPanel" aria-live="polite">
                <div class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-secondary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4 fact-card">
            <div class="card-header fact-card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-ul me-2"></i>Líneas de Factura</span>
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
                                <th class="text-end">IVA</th>
                                <th class="text-end" id="thCalifRE">Calif.</th>
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

        <div class="card mb-4 fact-card">
            <div class="card-header fact-card-header">
                <span><i class="bi bi-gear me-2"></i>Acciones</span>
            </div>
            <div class="card-body fact-card-body">
                <div class="d-flex flex-wrap gap-2">
                    <button id="btnVerifactu" onclick="firmarYEnviar()" class="btn btn-sm btn-outline-primary fact-btn">
                        <i class="bi bi-send me-1"></i>Enviar a Hacienda
                    </button>

                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle fact-btn"
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-filetype-xml me-1"></i>XML
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="descargarXml('sin-firma'); return false;">
                                <i class="bi bi-file-earmark me-2"></i>Sin firma</a></li>
                            <li><a class="dropdown-item" href="#" onclick="descargarXml('firmado'); return false;">
                                <i class="bi bi-shield-check me-2"></i>Firmado (XAdES)</a></li>
                        </ul>
                    </div>

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
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" onclick="enviarEmail(); return false;">
                                <i class="bi bi-envelope me-2"></i>Enviar por email</a></li>
                        </ul>
                    </div>

                    <a href="/SistemaGestionFacturas/src/views/facturas/listado.php"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Volver
                    </a>
                </div>
                <div id="accionesMsg" style="display:none;"></div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

ob_start();
?>
<div id="app-data" data-codigo="<?= htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') ?>" hidden></div>
<script src="/SistemaGestionFacturas/assets/js/facturas/ver.js" defer></script>
<?php
$extra_js = ob_get_clean();

include __DIR__ . '/../layout/base.php';
