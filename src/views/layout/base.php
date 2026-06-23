<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario'])) {
    header('Location: /SistemaGestionFacturas/src/views/login.php');
    exit;
}
require_once __DIR__ . '/../../core/Csrf.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($fact_page_title ?? 'Sistema de Facturación', ENT_QUOTES, 'UTF-8') ?> · VeriFACTU</title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Select2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/css/select2-bootstrap-5-theme.min.css">
    <!-- Tipografía Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <!-- CSS propio -->
    <link rel="stylesheet" href="/SistemaGestionFacturas/assets/css/styles.main.css">

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
            /* Compatibilidad con vistas antiguas */
            --fact-primary: var(--brand);
            --fact-primary-dark: var(--brand-hover);
        }

        * { -webkit-font-smoothing: antialiased; }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            font-size: .9rem;
        }

        /* ===== Sidebar (claro) ===== */
        #sidebar {
            width: 248px; min-height: 100vh;
            background: var(--surface);
            border-right: 1px solid var(--border);
            position: fixed; top: 0; left: 0; z-index: 100;
            display: flex; flex-direction: column;
        }
        #sidebar .sidebar-brand {
            padding: 1.15rem 1.25rem;
            font-size: 1.05rem; font-weight: 700;
            color: var(--text);
            display: flex; align-items: center; gap: .6rem;
            border-bottom: 1px solid var(--border-soft);
        }
        #sidebar .sidebar-brand .brand-mark {
            width: 30px; height: 30px; border-radius: 8px;
            background: var(--brand); color: #fff;
            display: grid; place-items: center; font-size: 1rem;
            box-shadow: var(--shadow-sm);
        }
        #sidebar .nav-link {
            color: var(--text-muted);
            padding: .55rem .75rem;
            border-radius: 9px; margin: 1px 12px;
            display: flex; align-items: center; gap: .65rem;
            font-size: .875rem; font-weight: 500;
            transition: background .12s ease, color .12s ease;
        }
        #sidebar .nav-link i { font-size: 1.05rem; }
        #sidebar .nav-link:hover {
            background: var(--border-soft);
            color: var(--text);
        }
        #sidebar .nav-link.active {
            background: var(--brand-soft);
            color: var(--brand);
            font-weight: 600;
        }
        #sidebar .nav-section {
            font-size: .68rem; text-transform: uppercase;
            letter-spacing: .07em; color: #94a3b8; font-weight: 600;
            padding: 1.1rem 1.5rem .35rem;
        }

        /* ===== Main content ===== */
        #main-content {
            margin-left: 248px;
            min-height: 100vh;
            display: flex; flex-direction: column;
        }
        #topbar {
            background: rgba(255,255,255,.85);
            backdrop-filter: saturate(180%) blur(8px);
            border-bottom: 1px solid var(--border);
            padding: .85rem 1.75rem;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 50;
        }
        #topbar .page-heading h4 { margin: 0; font-size: 1.15rem; font-weight: 700; letter-spacing: -.01em; }
        #topbar .page-heading small { color: var(--text-muted); font-size: .82rem; }
        #content-area { padding: 1.75rem; flex: 1; max-width: 1320px; width: 100%; }

        /* ===== Cards (usadas por todas las vistas) ===== */
        .fact-card {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--surface);
            box-shadow: var(--shadow-sm);
        }
        .fact-card-header {
            background: transparent; border-bottom: 1px solid var(--border-soft);
            padding: .9rem 1.15rem; font-weight: 600; font-size: .9rem;
            color: var(--text);
        }
        .fact-card-body { padding: 1.15rem; }
        .fact-card-footer {
            background: transparent; border-top: 1px solid var(--border-soft);
            padding: .8rem 1.15rem;
        }
        .fact-form-label { font-size: .8rem; font-weight: 600; color: var(--text-muted); margin-bottom: .3rem; }
        .fact-form-control, .fact-form-select { font-size: .875rem; }
        .form-control, .form-select {
            border-color: var(--border); border-radius: 9px;
            padding-top: .45rem; padding-bottom: .45rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--brand-ring);
            box-shadow: 0 0 0 3px rgba(79,70,229,.12);
        }

        /* ===== Botones ===== */
        .btn { border-radius: 9px; font-weight: 500; }
        .fact-btn { font-size: .85rem; }
        .fact-btn-collapse { padding: .15rem .5rem; border: 0; background: transparent; color: var(--text-muted); }
        .fact-btn-collapse:hover { color: var(--text); }
        .btn-primary {
            --bs-btn-bg: var(--brand); --bs-btn-border-color: var(--brand);
            --bs-btn-hover-bg: var(--brand-hover); --bs-btn-hover-border-color: var(--brand-hover);
            --bs-btn-active-bg: var(--brand-hover); --bs-btn-active-border-color: var(--brand-hover);
            box-shadow: var(--shadow-sm);
        }
        .btn-outline-primary {
            --bs-btn-color: var(--brand); --bs-btn-border-color: var(--brand-ring);
            --bs-btn-hover-bg: var(--brand-soft); --bs-btn-hover-color: var(--brand);
            --bs-btn-hover-border-color: var(--brand);
        }

        /* ===== Tabla ===== */
        .fact-table { font-size: .875rem; color: var(--text); margin-bottom: 0; }
        .fact-table thead th {
            font-weight: 600; font-size: .72rem; text-transform: uppercase;
            letter-spacing: .04em; color: var(--text-muted);
            background: var(--bg); border-bottom: 1px solid var(--border);
            padding: .7rem 1rem;
        }
        .fact-table tbody td { padding: .8rem 1rem; vertical-align: middle; border-color: var(--border-soft); }
        .fact-table tbody tr:last-child td { border-bottom: 0; }
        .fact-tr-hover { cursor: pointer; transition: background .1s ease; }
        .fact-tr-hover:hover { background: var(--brand-soft); }

        /* ===== Paginación ===== */
        .fact-pagination .page-link {
            font-size: .82rem; color: var(--text-muted);
            border-color: var(--border); border-radius: 8px !important;
            margin: 0 2px;
        }
        .fact-pagination .page-item.active .page-link {
            background: var(--brand); border-color: var(--brand); color: #fff;
        }
        .fact-pagination .page-link:hover { background: var(--brand-soft); color: var(--brand); }

        .fact-list-actions { margin-bottom: 1rem; display: flex; align-items: center; flex-wrap: wrap; gap: .5rem; }

        /* ===== Badges (pill) ===== */
        .badge { font-weight: 600; padding: .4em .7em; border-radius: 999px; letter-spacing: .01em; }
        .fact-badge {
            display: inline-block; padding: .25em .65em;
            border-radius: 999px; font-size: .72rem; font-weight: 600;
        }
        .fact-badge-factura       { background: var(--brand-soft); color: var(--brand); }
        .fact-badge-simplificada  { background: #e8f5e9; color: #2e7d32; }
        .fact-badge-rectificativa { background: #fff3e0; color: #e65100; }
        .fact-badge-recapitulativa{ background: #f3e5f5; color: #7b1fa2; }
        .fact-badge-borrador      { background: #f1f5f9; color: #475569; }
        .fact-badge-abierta       { background: #fff9c4; color: #827717; }
        .fact-badge-cerrada       { background: #e8f5e9; color: #2e7d32; }
        .fact-btn-group { gap: .25rem; }

        /* ===== Select2 acorde al tema ===== */
        .select2-container--bootstrap-5 .select2-selection {
            border-color: var(--border) !important; border-radius: 9px !important;
        }
        .select2-container--bootstrap-5.select2-container--focus .select2-selection {
            border-color: var(--brand-ring) !important;
            box-shadow: 0 0 0 3px rgba(79,70,229,.12) !important;
        }

        /* albr- clases usadas en recapitulativa */
        .albr-card { border: 1px solid var(--border); border-radius: var(--radius); background: var(--surface); box-shadow: var(--shadow-sm); }
        .albr-card-header {
            background: transparent; border-bottom: 1px solid var(--border-soft);
            padding: .9rem 1.15rem; font-weight: 600; font-size: .9rem;
        }

        /* Loading overlay */
        #loading-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(15,23,42,.35); z-index: 9999;
            align-items: center; justify-content: center;
        }
        #loading-overlay.active { display: flex; }
        #loading-overlay .spinner-box {
            background: #fff; padding: 1.5rem 2rem; border-radius: var(--radius);
            display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-md);
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

        /* Responsive: colapsar sidebar en pantallas pequeñas */
        @media (max-width: 992px) {
            #sidebar { transform: translateX(-100%); transition: transform .2s ease; }
            #main-content { margin-left: 0; }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<nav id="sidebar">
    <div class="sidebar-brand">
        <span class="brand-mark"><i class="bi bi-receipt-cutoff"></i></span>
        VeriFACTU
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
        <div class="d-flex align-items-center gap-3">
            <button id="sidebarToggle" class="btn btn-sm btn-outline-secondary d-lg-none" type="button"
                    style="border-color:var(--border);">
                <i class="bi bi-list"></i>
            </button>
            <div class="page-heading">
                <h4><?= htmlspecialchars($fact_page_title ?? '', ENT_QUOTES, 'UTF-8') ?></h4>
                <?php if (!empty($fact_page_subtitle)): ?>
                    <small><?= htmlspecialchars($fact_page_subtitle, ENT_QUOTES, 'UTF-8') ?></small>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted small d-none d-md-inline"><i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y') ?></span>
            <div class="d-flex align-items-center gap-2 ps-3" style="border-left:1px solid var(--border);">
                <span class="rounded-circle d-grid" style="width:32px;height:32px;background:var(--brand-soft);color:var(--brand);place-items:center;font-weight:700;font-size:.8rem;">
                    <?= htmlspecialchars(strtoupper(substr($_SESSION['usuario'], 0, 1)), ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span style="font-size:.85rem; font-weight:600; color:var(--text);">
                    <?= htmlspecialchars(ucfirst($_SESSION['usuario']), ENT_QUOTES, 'UTF-8') ?>
                </span>
                <a href="/SistemaGestionFacturas/src/views/logout.php"
                   class="btn btn-sm btn-outline-secondary ms-1"
                   style="font-size:.78rem; padding:.25rem .65rem; border-color:var(--border); color:var(--text-muted);"
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
     background:var(--text); color:#fff; padding:.6rem 1.2rem; border-radius:9px; z-index:9998; font-size:.85rem; box-shadow:var(--shadow-md);">
    <span class="spinner-border spinner-border-sm me-2"></span>Generando Excel...
</div>

<!-- Evita que el navegador restaure la página desde bfcache tras cerrar sesión -->
<script>
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) window.location.reload();
    });
    // Toggle del sidebar en móvil
    document.addEventListener('DOMContentLoaded', function () {
        var t = document.getElementById('sidebarToggle');
        var sb = document.getElementById('sidebar');
        if (t && sb) {
            t.addEventListener('click', function () {
                sb.style.transform = sb.style.transform === 'translateX(0px)' ? 'translateX(-100%)' : 'translateX(0px)';
            });
        }
    });
</script>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Utilidades propias -->
<script src="/SistemaGestionFacturas/assets/js/app.js"></script>
<script src="/SistemaGestionFacturas/assets/js/form-utils.js"></script>
<script src="/SistemaGestionFacturas/assets/js/calculos.js"></script>
<!-- JS de la vista -->
<?= $extra_js ?? '' ?>

</body>
</html>
