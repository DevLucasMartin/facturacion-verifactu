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
    <style>
        :root {
            --fact-primary: #97b06b;
            --fact-primary-dark: #7a9455;
        }
        body {
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            border: 1px solid #e8e8e8;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,.08);
            background: #fff;
            overflow: hidden;
        }
        .login-header {
            background: #232323;
            padding: 2rem 1.5rem 1.5rem;
            text-align: center;
            color: #fff;
        }
        .login-header .brand-icon {
            font-size: 2.5rem;
            color: var(--fact-primary);
        }
        .login-header h5 {
            margin: .5rem 0 .25rem;
            font-weight: 700;
            font-size: 1.15rem;
            color: var(--fact-primary);
        }
        .login-header small {
            color: #aaa;
            font-size: .82rem;
        }
        .login-body {
            padding: 1.75rem 1.5rem;
        }
        .btn-login {
            background: var(--fact-primary);
            border-color: var(--fact-primary);
            color: #fff;
            font-weight: 600;
            font-size: .9rem;
        }
        .btn-login:hover {
            background: var(--fact-primary-dark);
            border-color: var(--fact-primary-dark);
            color: #fff;
        }
        .form-label { font-size: .85rem; font-weight: 500; }
        .form-control { font-size: .9rem; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-header">
        <div class="brand-icon"><i class="bi bi-receipt"></i></div>
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
