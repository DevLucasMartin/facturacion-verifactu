<?php
/**
 * Gestión - Usuarios
 */

if (session_status() === PHP_SESSION_NONE) session_start();
if (($_SESSION['usuario'] ?? '') !== 'admin') {
    header('Location: /SistemaGestionFacturas/src/views/facturas/listado.php');
    exit;
}

$fact_page_title    = 'Usuarios';
$fact_page_subtitle = 'Gestión de usuarios del sistema';
$fact_active_menu   = 'usuarios';

ob_start();
?>

<!-- Acciones -->
<div class="fact-list-actions">
    <input type="text"
           id="usuariosBuscar"
           class="form-control form-control-sm fact-form-control"
           placeholder="Buscar usuario..."
           style="max-width:260px;">
    <button type="button" class="btn btn-primary btn-sm fact-btn" id="btnNuevoUsuario">
        <i class="bi bi-plus-lg me-1"></i>Nuevo usuario
    </button>
</div>

<!-- Tabla -->
<div class="card fact-card">
    <div class="card-header fact-card-header d-flex align-items-center">
        <span><i class="bi bi-person-gear me-2"></i>Usuarios</span>
        <span class="ms-auto text-muted" style="font-weight:400;font-size:.8rem;" id="usuariosTotalLabel"></span>
    </div>
    <div class="card-body p-0 fact-card-body" style="padding:0!important;">
        <div class="table-responsive">
            <table class="table table-hover mb-0 fact-table" id="usuariosTable">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th class="text-center" style="width:140px;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="usuariosTableBody">
                    <tr>
                        <td colspan="2" class="text-center py-4 text-muted">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            Cargando...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal crear / modificar -->
<div class="modal fade" id="usuarioModal" tabindex="-1" aria-modal="true" aria-labelledby="usuarioModalTitle">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="usuarioModalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="usuarioMsg" class="alert d-none mb-3"></div>
                <form id="usuarioForm">
                    <div class="mb-3" id="wrapUsuarioNombre">
                        <label class="form-label fact-form-label">Usuario <span class="text-danger">*</span></label>
                        <input type="text" class="form-control fact-form-control" id="uUsuario" name="uUsuario"
                               placeholder="Ej: operador1" maxlength="50"
                               autocomplete="username">
                        <div class="invalid-feedback" id="uUsuarioError"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fact-form-label" id="uPassLabel">Contraseña <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control fact-form-control" id="uContrasena" name="uContrasena"
                                   placeholder="Contraseña" autocomplete="new-password">
                            <button type="button" class="btn btn-outline-secondary" id="btnTogglePass" tabindex="-1">
                                <i class="bi bi-eye" id="iconTogglePass"></i>
                            </button>
                        </div>
                        <div class="form-text text-muted" style="font-size:.78rem;">
                            Mínimo 1 mayúscula y 1 número.
                        </div>
                        <div class="invalid-feedback d-block text-danger" id="uPassError" style="display:none;font-size:.82rem;"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fact-form-label">Repetir contraseña <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control fact-form-control" id="uContrasena2" name="uContrasena2"
                                   placeholder="Repite la contraseña" autocomplete="new-password">
                            <button type="button" class="btn btn-outline-secondary" id="btnTogglePass2" tabindex="-1">
                                <i class="bi bi-eye" id="iconTogglePass2"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback d-block text-danger" id="uPass2Error" style="display:none;font-size:.82rem;"></div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-outline-secondary fact-btn" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary fact-btn" id="uBtnGuardar">
                            <i class="bi bi-check-lg me-1"></i>Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal confirmar eliminar -->
<div class="modal fade" id="eliminarUsuarioModal" tabindex="-1" aria-modal="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-trash me-2 text-danger"></i>Eliminar usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">¿Seguro que quieres eliminar al usuario <strong id="eliminarUsuarioNombre"></strong>?</p>
                <p class="text-muted small mt-1 mb-0">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm fact-btn" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger btn-sm fact-btn" id="btnConfirmarEliminar">
                    <i class="bi bi-trash me-1"></i>Eliminar
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

ob_start();
?>
<script src="/SistemaGestionFacturas/assets/js/Gestion/usuarios.js" defer></script>
<?php
$extra_js = ob_get_clean();

include __DIR__ . '/../layout/base.php';
