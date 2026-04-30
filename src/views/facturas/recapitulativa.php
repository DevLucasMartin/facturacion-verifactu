<?php
/**
 * Factura Recapitulativa
 * Sistema de Gestión de Facturas / VeriFACTU
 */

$fact_page_title    = 'Recapitulativa';
$fact_page_subtitle = 'Generar factura recapitulativa de simplificadas';
$fact_active_menu   = 'facturas';

ob_start();
?>

<form id="recapForm">
<div class="row">
    <div class="col-lg-8">

        <!-- Filtros simplificadas -->
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
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fact-form-label">Canal</label>
                            <select id="selectCanal" name="Id_Canal"
                                class="form-select form-select-sm fact-form-select">
                                <option value="">Selecciona canal...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fact-form-label">Año</label>
                            <select id="selectAnio" name="anio"
                                class="form-select form-select-sm fact-form-select">
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fact-form-label">Mes</label>
                            <select id="selectMes" name="mes"
                                class="form-select form-select-sm fact-form-select">
                                <option value="">Selecciona mes...</option>
                                <option value="01">Enero</option>
                                <option value="02">Febrero</option>
                                <option value="03">Marzo</option>
                                <option value="04">Abril</option>
                                <option value="05">Mayo</option>
                                <option value="06">Junio</option>
                                <option value="07">Julio</option>
                                <option value="08">Agosto</option>
                                <option value="09">Septiembre</option>
                                <option value="10">Octubre</option>
                                <option value="11">Noviembre</option>
                                <option value="12">Diciembre</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Facturas simplificadas -->
        <div class="card fact-card mb-4">
            <div class="card-header fact-card-header">
                <span><i class="bi bi-list me-2"></i>Facturas simplificadas pendientes</span>
            </div>
            <div class="card-body p-0 fact-card-body" style="padding:0!important;">
                <div id="simplificadasContainer">
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-funnel fs-2 d-block mb-2"></i>
                        Selecciona canal, año y mes para cargar las simplificadas pendientes.
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="col-lg-4">

        <!-- Datos de la recapitulativa -->
        <div class="card fact-card mb-4">
            <div class="card-header fact-card-header">
                <span><i class="bi bi-collection me-2"></i>Datos de la recapitulativa</span>
            </div>
            <div class="card-body fact-card-body">
                <div class="mb-3">
                    <label class="form-label fact-form-label">Cliente</label>
                    <select id="selectCliente" name="Id_Cliente"
                        class="form-select form-select-sm fact-form-select" style="width:100%">
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fact-form-label">Fecha</label>
                    <input type="date" name="Fecha"
                        class="form-control form-control-sm fact-form-control"
                        value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fact-form-label">Forma de pago</label>
                    <select id="selectFormaPago" name="Id_Forma_Pago"
                        class="form-select form-select-sm fact-form-select">
                        <option value="">Selecciona forma de pago...</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fact-form-label">Observaciones</label>
                    <textarea name="Observaciones" rows="3"
                        class="form-control form-control-sm fact-form-control"
                        placeholder="Opcional..."></textarea>
                </div>
            </div>
        </div>

        <!-- Resumen y acción -->
        <div class="card fact-card">
            <div class="card-body fact-card-body">
                <p class="mb-2 text-muted small">Selección actual</p>
                <p class="mb-3 fw-semibold" id="resumenRecap">No hay facturas seleccionadas.</p>
                <div class="d-grid gap-2">
                    <button type="submit" id="btnCrear"
                        class="btn btn-success fact-btn" disabled>
                        <i class="bi bi-send me-1"></i>Crear y enviar a VeriFACTU
                    </button>
                    <a href="/SistemaGestionFacturas/src/views/facturas/listado.php"
                       class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Volver
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>
</form>

<?php
$content = ob_get_clean();

ob_start();
?>
<script src="/SistemaGestionFacturas/assets/js/Facturas/recapitulativa.js" defer></script>
<?php
$extra_js = ob_get_clean();

include __DIR__ . '/../layout/base.php';
