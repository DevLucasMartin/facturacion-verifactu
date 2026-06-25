<?php
session_start();

if (isset($_SESSION['usuario'])) {
    header('Location: /SistemaGestionFacturas/src/views/facturas/listado.php');
    exit;
}

require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/core/Csrf.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido. Recarga la página e inténtalo de nuevo.';
    } else {
    $user = trim($_POST['usuario'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    if ($user !== '' && $pass !== '') {
        $db  = Database::getInstance();
        $row = $db->fetch(
            'SELECT `Usuario` FROM `Usuarios` WHERE `Usuario` = ? AND `Contrasena` = SHA2(?, 256)',
            [$user, $pass]
        );
        if ($row) {
            $_SESSION['usuario'] = $row['Usuario'];
            header('Location: /SistemaGestionFacturas/src/views/facturas/listado.php');
            exit;
        }
    }

    $error = 'Usuario o contraseña incorrectos.';
    } // end else (CSRF válido)
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión · VeriFACTU</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Tipografía Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <style>
        :root {
            /* Paleta SaaS clara */
            --brand:        #4f46e5;   /* indigo-600 */
            --brand-hover:  #4338ca;   /* indigo-700 */
            --brand-soft:   #eef2ff;   /* indigo-50  */
            --brand-ring:   #c7d2fe;   /* indigo-200 */
            --bg:           #f8fafc;   /* slate-50   */
            --surface:      #ffffff;
            --border:       #e2e8f0;   /* slate-200  */
            --border-soft:  #f1f5f9;   /* slate-100  */
            --text:         #0f172a;   /* slate-900  */
            --text-muted:   #64748b;   /* slate-500  */
            --radius:       12px;
            --shadow-sm:    0 1px 2px rgba(15,23,42,.04), 0 1px 3px rgba(15,23,42,.06);
            --shadow-md:    0 4px 12px rgba(15,23,42,.06), 0 2px 4px rgba(15,23,42,.04);
        }
        * { -webkit-font-smoothing: antialiased; }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            background: var(--surface);
            overflow: hidden;
        }
        .login-header {
            padding: 2rem 1.5rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid var(--border-soft);
        }
        .login-header .brand-mark {
            width: 52px; height: 52px; margin: 0 auto;
            border-radius: 14px;
            background: var(--brand); color: #fff;
            display: grid; place-items: center;
            font-size: 1.6rem;
            box-shadow: var(--shadow-sm);
        }
        .login-header h5 {
            margin: .85rem 0 .2rem;
            font-weight: 700;
            font-size: 1.15rem;
            letter-spacing: -.01em;
            color: var(--text);
        }
        .login-header small {
            color: var(--text-muted);
            font-size: .82rem;
        }
        .login-body {
            padding: 1.75rem 1.5rem;
        }
        .btn-login {
            background: var(--brand);
            border-color: var(--brand);
            color: #fff;
            font-weight: 600;
            font-size: .9rem;
            border-radius: 9px;
            box-shadow: var(--shadow-sm);
        }
        .btn-login:hover {
            background: var(--brand-hover);
            border-color: var(--brand-hover);
            color: #fff;
        }
        .form-label { font-size: .8rem; font-weight: 600; color: var(--text-muted); }
        .form-control {
            font-size: .9rem;
            border-color: var(--border);
            border-radius: 9px;
            padding-top: .45rem; padding-bottom: .45rem;
        }
        .form-control:focus {
            border-color: var(--brand-ring);
            box-shadow: 0 0 0 3px rgba(79,70,229,.12);
        }
        .input-group-text {
            background: var(--bg);
            border-color: var(--border);
            color: var(--text-muted);
            border-radius: 9px 0 0 9px;
        }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-header">
        <div class="brand-mark"><i class="bi bi-receipt-cutoff"></i></div>
        <h5>VeriFACTU</h5>
        <small>Sistema de Gestión de Facturas</small>
    </div>
    <div class="login-body">
        <h6 class="mb-4 text-center text-muted" style="font-size:.9rem;">Introduce tus credenciales para continuar</h6>

        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 py-2" style="font-size:.85rem;">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3">
                <label for="usuario" class="form-label">Usuario</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="usuario" name="usuario"
                           value="<?= htmlspecialchars($_POST['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Usuario" autofocus required>
                </div>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="Contraseña" required>
                </div>
            </div>
            <button type="submit" class="btn btn-login w-100">
                <i class="bi bi-box-arrow-in-right me-1"></i> Entrar
            </button>
        </form>
    </div>
</div>
</body>
</html>
