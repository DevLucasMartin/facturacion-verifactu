<?php
/**
 * Configuración - Tipos de IVA
 * Sistema de Gestión de Facturas / VeriFACTU
 */

$fact_page_title    = 'Tipos de IVA';
$fact_page_subtitle = 'Gestión de tipos de IVA';
$fact_active_menu   = 'tiposiva';

ob_start();
?>

<!-- Acciones -->
<div class="fact-list-actions">
    <button type="button" class="btn btn-primary btn-sm fact-btn" id="btnNuevoIVA">
        <i class="bi bi-plus-lg me-1"></i>Nuevo tipo IVA
    </button>
</div>

<!-- Card de IVA -->
<div class="card fact-card">

    <div class="card-header fact-card-header">
        <span><i class="bi bi-percent me-2"></i>Tipos de IVA</span>
    </div>

    <div class="card-body p-0 fact-card-body" style="padding:0!important;">
        <div class="table-responsive">
            <table class="table table-hover mb-0 fact-table" id="ivaTable">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Descripción</th>
                        <th class="text-end">% IVA</th>
                        <th class="text-end">% RE</th>
                        <th>Territorio</th>
                        <th class="text-center">Orden</th>
                        <th class="text-center">Activo</th>
                        <th class="text-center" style="width:100px;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="ivaTableBody">
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            Cargando...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modal IVA - Crear / Editar -->
<div class="modal fade" id="ivaModal" tabindex="-1" aria-labelledby="ivaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header fact-card-header">
                <h5 class="modal-title" id="ivaModalLabel">
                    <i class="bi bi-percent me-2"></i><span id="ivaTitulo">Nuevo tipo IVA</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form id="ivaForm" novalidate>

                <div class="modal-body fact-card-body">

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label fact-form-label" for="ivaInputCodigo">Código *</label>
                            <input type="text"
                                   id="ivaInputCodigo"
                                   name="Codigo"
                                   class="form-control fact-form-control text-uppercase"
                                   maxlength="4"
                                   required>
                            <div class="form-text">Identificador único.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fact-form-label" for="ivaInputDescripcion">Descripción *</label>
                            <input type="text"
                                   id="ivaInputDescripcion"
                                   name="Descripcion"
                                   class="form-control fact-form-control"
                                   maxlength="255"
                                   required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fact-form-label" for="ivaInputCodigoVerifactu">Código Verifactu</label>
                            <input type="text"
                                   id="ivaInputCodigoVerifactu"
                                   name="Codigo_Verifactu"
                                   class="form-control fact-form-control"
                                   maxlength="20">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label fact-form-label" for="ivaInputIVA">% IVA</label>
                            <input type="number"
                                   id="ivaInputIVA"
                                   name="IVA"
                                   class="form-control fact-form-control"
                                   step="0.01" min="0" max="100"
                                   value="0">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fact-form-label" for="ivaInputRE">% RE</label>
                            <input type="number"
                                   id="ivaInputRE"
                                   name="RE"
                                   class="form-control fact-form-control"
                                   step="0.01" min="0" max="100"
                                   value="0">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fact-form-label" for="ivaInputTerritorio">Territorio</label>
                            <select id="ivaInputTerritorio"
                                    name="Tipo_Territorio"
                                    class="form-select fact-form-select">
                                <option value="">— Sin especificar —</option>
                                <option value="PENINSULAR">Peninsular</option>
                                <option value="CANARIAS">Canarias (IGIC)</option>
                                <option value="CEUTA_MELILLA">Ceuta / Melilla (IPSI)</option>
                                <option value="ESPECIAL">Especial</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fact-form-label" for="ivaInputOrden">Orden</label>
                            <input type="number"
                                   id="ivaInputOrden"
                                   name="Orden"
                                   class="form-control fact-form-control"
                                   min="0"
                                   value="0">
                        </div>
                    </div>

                    <hr class="my-2">
                    <p class="fact-form-label mb-2" style="font-size:.8rem;opacity:.7;">Cuentas contables</p>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label fact-form-label" for="ivaInputCuentaIVASoportado">IVA Soportado</label>
                            <input type="text"
                                   id="ivaInputCuentaIVASoportado"
                                   name="Cuenta_IVA_Soportado"
                                   class="form-control fact-form-control"
                                   maxlength="20">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fact-form-label" for="ivaInputCuentaIVARepercutido">IVA Repercutido</label>
                            <input type="text"
                                   id="ivaInputCuentaIVARepercutido"
                                   name="Cuenta_IVA_Repercutido"
                                   class="form-control fact-form-control"
                                   maxlength="20">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fact-form-label" for="ivaInputCuentaRESoportado">RE Soportado</label>
                            <input type="text"
                                   id="ivaInputCuentaRESoportado"
                                   name="Cuenta_RE_Soportado"
                                   class="form-control fact-form-control"
                                   maxlength="20">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fact-form-label" for="ivaInputCuentaRERepercutido">RE Repercutido</label>
                            <input type="text"
                                   id="ivaInputCuentaRERepercutido"
                                   name="Cuenta_RE_Repercutido"
                                   class="form-control fact-form-control"
                                   maxlength="20">
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-auto">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="ivaCheckActivo" name="Activo" value="S" checked>
                                <label class="form-check-label fact-form-label" for="ivaCheckActivo">Activo</label>
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="ivaCheckActualizado" name="Actualizado" value="1" checked>
                                <label class="form-check-label fact-form-label" for="ivaCheckActualizado">Actualizado</label>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="modal-footer fact-card-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary fact-btn" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-sm btn-primary fact-btn" id="ivaBtnGuardar">
                        <i class="bi bi-floppy me-1"></i>Guardar
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

ob_start();
?>
<script src="/SistemaGestionFacturas/assets/js/Configuracion/tiposiva.js" defer></script>
<?php
$extra_js = ob_get_clean();

include __DIR__ . '/../layout/base.php';
