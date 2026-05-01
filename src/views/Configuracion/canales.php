<?php
/**
 * Configuración - Canales
 * Sistema de Gestión de Facturas / VeriFACTU
 */

$fact_page_title    = 'Canales';
$fact_page_subtitle = 'Gestión de canales';
$fact_active_menu   = 'canales';

ob_start();
?>

<!-- Acciones -->
<div class="fact-list-actions">
    <button type="button" class="btn btn-primary btn-sm fact-btn" id="btnNuevoCanal">
        <i class="bi bi-plus-lg me-1"></i>Nuevo canal
    </button>
</div>

<!-- Card de Canales -->
<div class="card fact-card">

    <div class="card-header fact-card-header">
        <span><i class="bi bi-diagram-3 me-2"></i>Canales</span>
    </div>

    <div class="card-body p-0 fact-card-body" style="padding:0!important;">
        <div class="table-responsive">
            <table class="table table-hover mb-0 fact-table" id="canalesTable">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Descripción</th>
                        <th class="text-center">Tipo</th>
                        <th class="text-center">Defecto</th>
                        <th>Cliente facturación</th>
                        <th class="text-center">Prioridad</th>
                        <th>Departamento</th>
                        <th class="text-center" style="width:100px;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="canalesTableBody">
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

<!-- Modal Canales - Crear / Editar -->
<div class="modal fade" id="canalModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="canalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header fact-card-header">
                <h5 class="modal-title" id="canalModalLabel">
                    <i class="bi bi-diagram-3 me-2"></i><span id="modalTitulo">Nuevo canal</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form id="canalForm" novalidate>

                <div class="modal-body fact-card-body">

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fact-form-label" for="inputCodigo">Código *</label>
                            <input type="text"
                                   id="inputCodigo"
                                   name="Codigo"
                                   class="form-control fact-form-control text-uppercase"
                                   maxlength="4"
                                   required>
                            <div class="form-text">Identificador único del canal.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fact-form-label" for="inputDescripcion">Descripción *</label>
                            <input type="text"
                                   id="inputDescripcion"
                                   name="Descripcion"
                                   class="form-control fact-form-control"
                                   maxlength="255"
                                   required>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label fact-form-label" for="inputColor">Color</label>
                            <input type="number"
                                   id="inputColor"
                                   name="Color"
                                   class="form-control fact-form-control"
                                   min="0">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fact-form-label" for="inputClienteFacturacion">Cliente facturación</label>
                            <input type="text"
                                   id="inputClienteFacturacion"
                                   name="Id_Cliente_Facturacion"
                                   class="form-control fact-form-control"
                                   maxlength="12">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label fact-form-label" for="inputDireccionFacturacion">Dirección de facturación</label>
                            <textarea id="inputDireccionFacturacion"
                                      name="Direccion_Facturacion"
                                      class="form-control fact-form-control"
                                      rows="2"></textarea>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label fact-form-label" for="inputPorcentajeAntieconomico">% Antieconómico</label>
                            <input type="number"
                                   id="inputPorcentajeAntieconomico"
                                   name="Porcentaje_Antieconomico"
                                   class="form-control fact-form-control"
                                   min="0" max="100">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fact-form-label" for="inputPrioridad">Prioridad</label>
                            <input type="number"
                                   id="inputPrioridad"
                                   name="Prioridad"
                                   class="form-control fact-form-control"
                                   min="0">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fact-form-label" for="inputTipoPrioridad">Tipo prioridad</label>
                            <input type="text"
                                   id="inputTipoPrioridad"
                                   name="Tipo_Prioridad"
                                   class="form-control fact-form-control text-uppercase"
                                   maxlength="4">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fact-form-label" for="inputDepartamento">Departamento</label>
                            <input type="text"
                                   id="inputDepartamento"
                                   name="Departamento"
                                   class="form-control fact-form-control text-uppercase"
                                   maxlength="10">
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-6 col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="checkTicket" name="Ticket" value="S">
                                <label class="form-check-label fact-form-label" for="checkTicket">Canal de tickets</label>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="checkDefecto" name="Facturacion_Defecto" value="S">
                                <label class="form-check-label fact-form-label" for="checkDefecto">Canal por defecto</label>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="checkCobrarFranquicia" name="Cobrar_Franquicia" value="1">
                                <label class="form-check-label fact-form-label" for="checkCobrarFranquicia">Cobrar franquicia</label>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="checkComputable" name="Computable" value="1">
                                <label class="form-check-label fact-form-label" for="checkComputable">Computable</label>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="modal-footer fact-card-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary fact-btn" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-sm btn-primary fact-btn" id="btnGuardar">
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
<script src="/SistemaGestionFacturas/assets/js/Configuracion/canales.js" defer></script>
<?php
$extra_js = ob_get_clean();

include __DIR__ . '/../layout/base.php';
