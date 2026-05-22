<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario'])) {
    header('Location: /SistemaGestionFacturas/src/views/login.php');
    exit;
}
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($fact_page_title ?? 'Sistema de Facturación', ENT_QUOTES, 'UTF-8') ?> · VeriFACTU</title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Select2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    <!-- CSS propio -->
    <link rel="stylesheet" href="/SistemaGestionFacturas/assets/css/styles.main.css">

    <style>
        :root {
            --fact-primary: #97b06b;
            --fact-primary-dark: #7a9455;
            --fact-sidebar-bg: #232323;
            --fact-sidebar-text: #e0e0e0;
        }
        body { background: #f5f5f5; }

        /* Sidebar */
        #sidebar {
            width: 230px; min-height: 100vh;
            background: var(--fact-sidebar-bg);
            color: var(--fact-sidebar-text);
            position: fixed; top: 0; left: 0; z-index: 100;
            display: flex; flex-direction: column;
        }
        #sidebar .sidebar-brand {
            padding: 1.25rem 1rem;
            font-size: 1rem; font-weight: 700;
            color: var(--fact-primary);
            border-bottom: 1px solid #333;
        }
        #sidebar .nav-link {
            color: var(--fact-sidebar-text);
            padding: .6rem 1rem;
            border-radius: 6px; margin: 2px 8px;
            display: flex; align-items: center; gap: .5rem;
            font-size: .9rem;
        }
        #sidebar .nav-link:hover,
        #sidebar .nav-link.active {
            background: var(--fact-primary);
            color: #fff;
        }
        #sidebar .nav-section {
            font-size: .7rem; text-transform: uppercase;
            letter-spacing: .08em; color: #888;
            padding: 1rem 1rem .25rem;
        }

        /* Main content */
        #main-content {
            margin-left: 230px;
            min-height: 100vh;
            display: flex; flex-direction: column;
        }
        #topbar {
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
            padding: .75rem 1.5rem;
            display: flex; align-items: center; justify-content: space-between;
        }
        #topbar .page-heading h4 { margin: 0; font-size: 1.1rem; font-weight: 600; }
        #topbar .page-heading small { color: #888; font-size: .82rem; }
        #content-area { padding: 1.5rem; flex: 1; }

        /* Card styles usados en los views */
        .fact-card { border: 1px solid #e8e8e8; border-radius: 8px; }
        .fact-card-header {
            background: #fff; border-bottom: 1px solid #e8e8e8;
            padding: .75rem 1rem; font-weight: 600; font-size: .9rem;
        }
        .fact-card-body { padding: 1rem; }
        .fact-card-footer {
            background: #fafafa; border-top: 1px solid #e8e8e8;
            padding: .75rem 1rem;
        }
        .fact-form-label { font-size: .82rem; font-weight: 500; margin-bottom: .25rem; }
        .fact-form-control, .fact-form-select { font-size: .88rem; }
        .fact-btn { font-size: .85rem; }
        .fact-btn-collapse { padding: .15rem .5rem; }
        .fact-table { font-size: .85rem; }
        .fact-table th { font-weight: 600; font-size: .8rem; }
        .fact-tr-hover { cursor: pointer; }
        .fact-tr-hover:hover { background: #f8f8f8; }
        .fact-pagination .page-link { font-size: .82rem; }
        .fact-list-actions { margin-bottom: 1rem; display: flex; align-items: center; flex-wrap: wrap; gap: .5rem; }
        .fact-badge {
            display: inline-block; padding: .2em .6em;
            border-radius: 4px; font-size: .75rem; font-weight: 600;
        }
        .fact-badge-factura       { background: #e3f0ff; color: #0d6efd; }
        .fact-badge-simplificada  { background: #e8f5e9; color: #2e7d32; }
        .fact-badge-rectificativa { background: #fff3e0; color: #e65100; }
        .fact-badge-recapitulativa{ background: #f3e5f5; color: #7b1fa2; }
        .fact-badge-borrador      { background: #eeeeee; color: #555; }
        .fact-badge-abierta       { background: #fff9c4; color: #827717; }
        .fact-badge-cerrada       { background: #e8f5e9; color: #2e7d32; }
        .fact-btn-group { gap: .25rem; }

        /* albr- clases usadas en recapitulativa */
        .albr-card { border: 1px solid #e8e8e8; border-radius: 8px; }
        .albr-card-header {
            background: #fff; border-bottom: 1px solid #e8e8e8;
            padding: .75rem 1rem; font-weight: 600; font-size: .9rem;
        }

        /* Loading overlay */
        #loading-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.3); z-index: 9999;
            align-items: center; justify-content: center;
        }
        #loading-overlay.active { display: flex; }
        #loading-overlay .spinner-box {
            background: #fff; padding: 1.5rem 2rem; border-radius: 10px;
            display: flex; align-items: center; gap: 1rem;
        }

        /* Toast notifications */
        #toast-container {
            position: fixed; top: 1.5rem; left: 50%; transform: translateX(-50%);
            z-index: 10000; display: flex; flex-direction: column; gap: .6rem;
            align-items: center; pointer-events: none;
        }
        #toast-container > div { pointer-events: auto; }
        #toast-container .toast-danger {
            background: #dc3545 !important;
            border: 2px solid #8b1c26;
            color: #fff !important;
            font-weight: 600;
            font-size: 1rem !important;
            padding: .9rem 1.4rem !important;
            min-width: 360px;
            max-width: 560px !important;
            box-shadow: 0 6px 22px rgba(220, 53, 69, .45) !important;
            animation: toastShake .35s ease;
        }
        @keyframes toastShake {
            0%, 100% { transform: translateX(0); }
            25%      { transform: translateX(-6px); }
            75%      { transform: translateX(6px); }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<nav id="sidebar">
    <div class="sidebar-brand">
        <i class="bi bi-receipt me-2"></i>VeriFACTU
    </div>
    <ul class="nav flex-column mt-2">
        <div class="nav-section">Facturación</div>
        <li class="nav-item">
            <a class="nav-link <?= ($fact_active_menu ?? '') === 'facturas' ? 'active' : '' ?>"
               href="/SistemaGestionFacturas/src/views/facturas/listado.php">
                <i class="bi bi-file-earmark-text"></i> Facturas
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($fact_active_menu ?? '') === 'albaranes' ? 'active' : '' ?>"
               href="/SistemaGestionFacturas/src/views/albaranes/listado.php">
                <i class="bi bi-clipboard2-check"></i> Albaranes
            </a>
        </li>
        <div class="nav-section">Verifactu</div>
        <li class="nav-item">
            <a class="nav-link <?= ($fact_active_menu ?? '') === 'verifactu' ? 'active' : '' ?>"
               href="/SistemaGestionFacturas/src/views/Verifactu/listado.php">
                <i class="bi bi-shield-check"></i> Registros
            </a>
        </li>
            <div class="nav-section">Gestión</div>
        <li class="nav-item">
            <a class="nav-link <?= ($fact_active_menu ?? '') === 'clientes' ? 'active' : '' ?>"
               href="/SistemaGestionFacturas/src/views/Gestion/clientes.php">
                <i class="bi bi-people"></i> Clientes
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($fact_active_menu ?? '') === 'productos' ? 'active' : '' ?>"
               href="/SistemaGestionFacturas/src/views/Gestion/productos.php">
                <i class="bi bi-box-seam"></i> Productos
            </a>
        </li>
        <?php if (($_SESSION['usuario'] ?? '') === 'admin'): ?>
        <li class="nav-item">
            <a class="nav-link <?= ($fact_active_menu ?? '') === 'usuarios' ? 'active' : '' ?>"
               href="/SistemaGestionFacturas/src/views/Gestion/usuarios.php">
                <i class="bi bi-person-gear"></i> Usuarios
            </a>
        </li>
        <?php endif; ?>
        <div class="nav-section">Configuración</div>
        <li class="nav-item">
            <a class="nav-link <?= ($fact_active_menu ?? '') === 'tiposiva' ? 'active' : '' ?>"
               href="/SistemaGestionFacturas/src/views/Configuracion/tiposiva.php">
                <i class="bi bi-percent"></i> Tipos IVA
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($fact_active_menu ?? '') === 'canales' ? 'active' : '' ?>"
               href="/SistemaGestionFacturas/src/views/Configuracion/canales.php">
                <i class="bi bi-diagram-3"></i> Canales
            </a>
        </li>
    </ul>
</nav>

<!-- Main content -->
<div id="main-content">
    <!-- Topbar -->
    <div id="topbar">
        <div class="page-heading">
            <h4><?= htmlspecialchars($fact_page_title ?? '', ENT_QUOTES, 'UTF-8') ?></h4>
            <?php if (!empty($fact_page_subtitle)): ?>
                <small><?= htmlspecialchars($fact_page_subtitle, ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted small"><?= date('d/m/Y') ?></span>
            <div class="d-flex align-items-center gap-2 ps-3" style="border-left:1px solid #e0e0e0;">
                <i class="bi bi-person-circle" style="font-size:1.1rem; color:#97b06b;"></i>
                <span style="font-size:.85rem; font-weight:600; color:#333;">
                    Bienvenido, <?= htmlspecialchars(ucfirst($_SESSION['usuario']), ENT_QUOTES, 'UTF-8') ?>
                </span>
                <a href="/SistemaGestionFacturas/src/views/logout.php"
                   class="btn btn-sm btn-outline-secondary ms-1"
                   style="font-size:.78rem; padding:.2rem .6rem;"
                   title="Cerrar sesión">
                    <i class="bi bi-box-arrow-right"></i> Salir
                </a>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div id="content-area">
        <?= $content ?? '' ?>
    </div>
</div>

<!-- Loading overlay -->
<div id="loading-overlay">
    <div class="spinner-box">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
        <span>Procesando...</span>
    </div>
</div>

<!-- Toast container -->
<div id="toast-container"></div>

<!-- Export indicator -->
<div id="exportIndicator" style="display:none; position:fixed; bottom:1.5rem; left:50%; transform:translateX(-50%);
     background:#232323; color:#fff; padding:.6rem 1.2rem; border-radius:6px; z-index:9998; font-size:.85rem;">
    <span class="spinner-border spinner-border-sm me-2"></span>Generando Excel...
</div>

<!-- Evita que el navegador restaure la página desde bfcache tras cerrar sesión -->
<script>
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) window.location.reload();
    });
</script>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Utilidades propias -->
<script src="/SistemaGestionFacturas/assets/js/app.js"></script>
<script src="/SistemaGestionFacturas/assets/js/form-utils.js"></script>
<script src="/SistemaGestionFacturas/assets/js/calculos.js"></script>
<!-- JS de la vista -->
<?= $extra_js ?? '' ?>

</body>
</html>
