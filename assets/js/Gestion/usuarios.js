const USR_API = (() => {
    const p   = window.location.pathname;
    const idx = p.indexOf('/SistemaGestionFacturas/');
    const base = idx >= 0 ? p.substring(0, idx) + '/SistemaGestionFacturas' : '/SistemaGestionFacturas';
    return base + '/src/api/usuarios.php';
})();

let usr_modal         = null;
let usr_modalEliminar = null;
let usr_modoEdicion   = false;
let usr_usuarioEdicion = null;
let usr_busqTimer     = null;
let usr_pendEliminar  = null;

$(document).ready(function () {
    usr_modal         = new bootstrap.Modal(document.getElementById('usuarioModal'));
    usr_modalEliminar = new bootstrap.Modal(document.getElementById('eliminarUsuarioModal'));

    cargarUsuarios();

    $('#usuariosBuscar').on('input', function () {
        clearTimeout(usr_busqTimer);
        usr_busqTimer = setTimeout(function () {
            cargarUsuarios($('#usuariosBuscar').val().trim());
        }, 350);
    });

    $('#btnNuevoUsuario').on('click', abrirNuevoUsuario);

    $('#usuarioForm').on('submit', function (e) {
        e.preventDefault();
        guardarUsuario();
    });

    $('#btnTogglePassActual').on('click', function () {
        usr_togglePass('uContrasenaActual', 'iconTogglePassActual');
    });
    $('#btnTogglePass').on('click', function () {
        usr_togglePass('uContrasena', 'iconTogglePass');
    });
    $('#btnTogglePass2').on('click', function () {
        usr_togglePass('uContrasena2', 'iconTogglePass2');
    });

    $('#btnConfirmarEliminar').on('click', function () {
        if (!usr_pendEliminar) return;
        eliminarUsuario(usr_pendEliminar);
    });
});

function abrirNuevoUsuario() {
    usr_modoEdicion    = false;
    usr_usuarioEdicion = null;

    $('#usuarioModalTitle').text('Nuevo usuario');
    $('#usuarioMsg').addClass('d-none').text('');
    $('#usuarioForm')[0].reset();
    usr_resetPassFields();

    $('#wrapUsuarioNombre').show();
    $('#uUsuarioLabel').html('Usuario <span class="text-danger">*</span>');
    $('#uUsuario').prop('readonly', false).removeClass('is-invalid is-valid').val('').attr('placeholder', 'Ej: operador1');
    $('#uUsuarioError').text('');

    $('#wrapPassActual').addClass('d-none');
    $('#uPassLabel').html('Contraseña <span class="text-danger">*</span>');
    $('#uPassHint').show();
    $('#uContrasena').attr('placeholder', 'Contraseña');
    $('#uContrasena2').attr('placeholder', 'Repite la contraseña').closest('.mb-3').show();

    usr_modal.show();
}

function abrirModificarUsuario(usuario) {
    usr_modoEdicion    = true;
    usr_usuarioEdicion = usuario;

    $('#usuarioModalTitle').text('Editar usuario — ' + usr_esc(usuario));
    $('#usuarioMsg').addClass('d-none').text('');
    $('#usuarioForm')[0].reset();
    usr_resetPassFields();

    // Campo nombre: pre-relleno y editable
    $('#wrapUsuarioNombre').show();
    $('#uUsuarioLabel').html('Nombre de usuario');
    $('#uUsuario').prop('readonly', false).removeClass('is-invalid is-valid').val(usuario).attr('placeholder', 'Nombre de usuario');
    $('#uUsuarioError').text('');

    // Contraseña actual: se pide siempre antes de la nueva en modo edición
    $('#wrapPassActual').removeClass('d-none');
    $('#uContrasenaActual').val('').removeClass('is-invalid');
    $('#uPassActualError').hide().text('');

    $('#uPassLabel').html('Nueva contraseña <span class="text-muted fw-normal" style="font-size:.85em;">(opcional)</span>');
    $('#uPassHint').show();
    $('#uContrasena').attr('placeholder', 'Dejar vacío para no cambiarla');
    $('#uContrasena2').attr('placeholder', 'Repite la nueva contraseña').closest('.mb-3').show();

    usr_modal.show();
}

function abrirEliminarUsuario(usuario) {
    usr_pendEliminar = usuario;
    $('#eliminarUsuarioNombre').text(usuario);
    usr_modalEliminar.show();
}

function guardarUsuario() {
    if (usr_modoEdicion) {
        guardarEdicion();
    } else {
        guardarNuevo();
    }
}

function guardarNuevo() {
    const nombre = $('#uUsuario').val().trim();
    const pass   = $('#uContrasena').val();
    const pass2  = $('#uContrasena2').val();

    if (nombre === '') {
        $('#uUsuario').addClass('is-invalid');
        $('#uUsuarioError').text('El nombre de usuario es obligatorio.');
        return;
    }
    if (!/^[A-Za-z0-9_]{1,50}$/.test(nombre)) {
        $('#uUsuario').addClass('is-invalid');
        $('#uUsuarioError').text('Solo letras, números y guion bajo (máx. 50).');
        return;
    }
    $('#uUsuario').removeClass('is-invalid');

    const errPass = usr_validarPassword(pass);
    if (errPass) {
        $('#uPassError').show().text(errPass);
        $('#uContrasena').addClass('is-invalid');
        return;
    }
    $('#uPassError').hide().text('');
    $('#uContrasena').removeClass('is-invalid');

    if (pass !== pass2) {
        $('#uPass2Error').show().text('Las contraseñas no coinciden.');
        $('#uContrasena2').addClass('is-invalid');
        return;
    }
    $('#uPass2Error').hide().text('');
    $('#uContrasena2').removeClass('is-invalid');

    $('#uBtnGuardar').prop('disabled', true);

    App.api(USR_API, {
        method: 'POST',
        data: JSON.stringify({ Usuario: nombre, Contrasena: pass }),
        contentType: 'application/json'
    })
    .done(function () {
        usr_mostrarMsg('success', `Usuario "${usr_esc(nombre)}" creado correctamente.`);
        setTimeout(function () { usr_modal.hide(); cargarUsuarios(); }, 800);
    })
    .fail(function (xhr) {
        const msg = xhr.responseJSON?.message || 'Error al crear el usuario.';
        if (msg.includes('ya existe')) {
            $('#uUsuario').addClass('is-invalid');
            $('#uUsuarioError').text('Este usuario ya existe.');
        } else {
            usr_mostrarMsg('danger', msg);
        }
    })
    .always(function () { $('#uBtnGuardar').prop('disabled', false); });
}

function guardarEdicion() {
    const nuevoNombre  = $('#uUsuario').val().trim();
    const passActual   = $('#uContrasenaActual').val();
    const passNueva    = $('#uContrasena').val();
    const passNueva2   = $('#uContrasena2').val();

    const cambiarNombre = nuevoNombre !== '' && nuevoNombre !== usr_usuarioEdicion;
    const cambiarPass   = passNueva !== '';

    // Validar nombre si ha cambiado
    if (cambiarNombre && !/^[A-Za-z0-9_]{1,50}$/.test(nuevoNombre)) {
        $('#uUsuario').addClass('is-invalid');
        $('#uUsuarioError').text('Solo letras, números y guion bajo (máx. 50).');
        return;
    }
    $('#uUsuario').removeClass('is-invalid');
    $('#uUsuarioError').text('');

    if (!cambiarNombre && !cambiarPass) {
        usr_mostrarMsg('warning', 'No has realizado ningún cambio.');
        return;
    }

    // Validar contraseña nueva si se ha rellenado
    if (cambiarPass) {
        const errPass = usr_validarPassword(passNueva);
        if (errPass) {
            $('#uPassError').show().text(errPass);
            $('#uContrasena').addClass('is-invalid');
            return;
        }
        $('#uPassError').hide().text('');
        $('#uContrasena').removeClass('is-invalid');

        if (passNueva !== passNueva2) {
            $('#uPass2Error').show().text('Las contraseñas no coinciden.');
            $('#uContrasena2').addClass('is-invalid');
            return;
        }
        $('#uPass2Error').hide().text('');
        $('#uContrasena2').removeClass('is-invalid');

        if (passActual === '') {
            $('#uContrasenaActual').addClass('is-invalid');
            $('#uPassActualError').show().text('Debes introducir tu contraseña actual para poder cambiarla.');
            $('#wrapPassActual').removeClass('d-none');
            return;
        }
        $('#uContrasenaActual').removeClass('is-invalid');
        $('#uPassActualError').hide().text('');
    }

    const payload = {};
    if (cambiarNombre) payload.NuevoUsuario     = nuevoNombre;
    if (cambiarPass)   payload.ContrasenaActual = passActual;
    if (cambiarPass)   payload.Contrasena       = passNueva;

    $('#uBtnGuardar').prop('disabled', true);

    App.api(`${USR_API}/${encodeURIComponent(usr_usuarioEdicion)}`, {
        method: 'PUT',
        data: JSON.stringify(payload),
        contentType: 'application/json'
    })
    .done(function (resp) {
        usr_mostrarMsg('success', resp.message || 'Cambios guardados correctamente.');
        setTimeout(function () { usr_modal.hide(); cargarUsuarios(); }, 800);
    })
    .fail(function (xhr) {
        const msg = xhr.responseJSON?.message || 'Error al guardar los cambios.';
        if (msg.includes('contraseña actual')) {
            $('#uContrasenaActual').addClass('is-invalid');
            $('#uPassActualError').show().text(msg);
            $('#wrapPassActual').removeClass('d-none');
        } else if (msg.includes('nombre') || msg.includes('ya existe')) {
            $('#uUsuario').addClass('is-invalid');
            $('#uUsuarioError').text(msg);
        } else {
            usr_mostrarMsg('danger', msg);
        }
    })
    .always(function () { $('#uBtnGuardar').prop('disabled', false); });
}

function eliminarUsuario(usuario) {
    $('#btnConfirmarEliminar').prop('disabled', true);
    App.api(`${USR_API}/${encodeURIComponent(usuario)}`, { method: 'DELETE' })
        .done(function () {
            usr_modalEliminar.hide();
            App.notify('Usuario eliminado correctamente.', 'success');
            cargarUsuarios();
        })
        .fail(function (xhr) {
            usr_modalEliminar.hide();
            App.notify(xhr.responseJSON?.message || 'Error al eliminar el usuario.', 'danger');
        })
        .always(function () { $('#btnConfirmarEliminar').prop('disabled', false); });
}

function cargarUsuarios(q) {
    const url = q && q.length >= 1 ? `${USR_API}?q=${encodeURIComponent(q)}` : USR_API;
    App.api(url)
        .done(function (response) {
            const items = response.data || [];
            $('#usuariosTotalLabel').text(items.length + ' usuario' + (items.length !== 1 ? 's' : ''));
            renderUsuarios(items);
        })
        .fail(function () {
            $('#usuariosTableBody').html(`
                <tr>
                    <td colspan="2" class="text-center py-4 text-danger">
                        <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
                        Error al cargar los usuarios
                    </td>
                </tr>`);
        });
}

function renderUsuarios(items) {
    if (!items.length) {
        $('#usuariosTableBody').html(`
            <tr>
                <td colspan="2" class="text-center py-4 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    No se encontraron usuarios
                </td>
            </tr>`);
        return;
    }

    $('#usuariosTableBody').html(items.map(function (u) {
        return `
            <tr class="fact-tr-hover">
                <td>
                    <i class="bi bi-person-circle me-2 text-secondary"></i>
                    <strong>${usr_esc(u.Usuario)}</strong>
                </td>
                <td class="text-center">
                    <div class="d-inline-flex gap-1">
                        <button class="btn btn-sm btn-outline-primary fact-btn"
                                title="Editar"
                                onclick="abrirModificarUsuario('${usr_esc(u.Usuario)}')">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger fact-btn"
                                title="Eliminar"
                                onclick="abrirEliminarUsuario('${usr_esc(u.Usuario)}')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>`;
    }).join(''));
}

function usr_validarPassword(pass) {
    if (pass === '')          return 'La contraseña no puede estar vacía.';
    if (!/[A-Z]/.test(pass)) return 'La contraseña debe contener al menos una mayúscula.';
    if (!/[0-9]/.test(pass)) return 'La contraseña debe contener al menos un número.';
    return null;
}

function usr_resetPassFields() {
    $('#uContrasenaActual').val('').attr('type', 'password').removeClass('is-invalid');
    $('#uContrasena').val('').attr('type', 'password').removeClass('is-invalid');
    $('#uContrasena2').val('').attr('type', 'password').removeClass('is-invalid');
    $('#iconTogglePassActual').attr('class', 'bi bi-eye');
    $('#iconTogglePass').attr('class', 'bi bi-eye');
    $('#iconTogglePass2').attr('class', 'bi bi-eye');
    $('#uPassActualError').hide().text('');
    $('#uPassError').hide().text('');
    $('#uPass2Error').hide().text('');
}

function usr_togglePass(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

function usr_mostrarMsg(tipo, texto) {
    $('#usuarioMsg')
        .removeClass('d-none alert-success alert-danger alert-warning alert-info')
        .addClass('alert alert-' + tipo)
        .text(texto);
}

function usr_esc(text) {
    return String(text ?? '')
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;').replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
