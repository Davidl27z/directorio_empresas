<?php
// Force UTF-8 output and internal encoding
if (!ini_get('default_charset') || strtolower(ini_get('default_charset')) !== 'utf-8') {
    ini_set('default_charset', 'UTF-8');
}
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/ui_icons.php';
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
    mb_http_output('UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Directorio de Empresas'; ?></title>
    <meta name="description" content="<?php echo isset($page_description) ? htmlspecialchars($page_description) : 'Encuentra la mejor empresa en nuestro directorio de empresas locales.'; ?>">
    <meta name="keywords" content="<?php echo isset($page_keywords) ? htmlspecialchars($page_keywords) : 'empresas, directorio, locales, servicios'; ?>">
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Directorio de Empresas'; ?>">
    <meta property="og:description" content="<?php echo isset($page_description) ? htmlspecialchars($page_description) : 'Encuentra la mejor empresa en nuestro directorio de empresas locales.'; ?>">
    <meta property="og:image" content="<?php echo isset($og_image) ? htmlspecialchars($og_image) : ''; ?>">
    <meta property="og:url" content="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">
    <meta property="og:type" content="website">
    <link rel="canonical" href="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">
    <!-- Bootstrap CSS (local fallback) -->
    <link rel="stylesheet" href="<?= $base_url ?>/css/bootstrap.min.css">
    <?php
    if (!isset($base_url) || $base_url === '') {
        // Calcular ruta relativa hacia la carpeta raíz del proyecto (donde está index.php)
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        $parts = array_filter(explode('/', trim($scriptDir, '/')));
        $depth = max(0, count($parts) - 1);
        $base_url = $depth === 0 ? '.' : str_repeat('../', $depth);
    }
    ?>
    <link rel="stylesheet" href="<?= $base_url ?>/css/styles.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?= $base_url ?>/index.php">MiDirector</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
    <li class="nav-item">
        <a class="nav-link" href="<?= $base_url ?>/index.php">Inicio</a>
    </li>
    <?php
    $carrito_count = 0;
    if (isset($_SESSION['carrito']) && is_array($_SESSION['carrito'])) {
        foreach ($_SESSION['carrito'] as $productosEmpresa) {
            if (!is_array($productosEmpresa)) {
                continue;
            }
            foreach ($productosEmpresa as $item) {
                $carrito_count += (int) ($item['cantidad'] ?? 0);
            }
        }
    }
    ?>
    <?php if(isset($_SESSION['user_id']) && isset($_SESSION['user_role'])): ?>
        <?php if($_SESSION['user_role'] === 'cliente'): ?>
            <li class="nav-item">
                <a class="nav-link position-relative d-inline-flex align-items-center gap-2" href="<?= $base_url ?>/carrito.php">
                    <?= ui_icon('cart', 'navbar-icon') ?>
                    <span>Carrito</span>
                    <span id="cart-counter-badge" class="badge bg-danger position-absolute top-0 start-100 translate-middle <?= $carrito_count > 0 ? '' : 'd-none' ?>" data-count="<?= $carrito_count ?>">
                        <?= $carrito_count ?>
                    </span>
                </a>
            </li>
        <?php elseif($_SESSION['user_role'] === 'empresa'): ?>
            <li class="nav-item">
                <a class="nav-link" href="<?= $base_url ?>/recursos.php">Recursos</a>
            </li>
        <?php endif; ?>
    <?php endif; ?>
    <?php if(isset($_SESSION['user_id'])): ?>
        <?php if(isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link fw-bold d-inline-flex align-items-center gap-2" href="<?= $base_url ?>/admin/dashboard.php"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg> <span>Admin</span></a>
            </li>
        <?php else: ?>
            <li class="nav-item">
                <a class="nav-link fw-bold d-inline-flex align-items-center gap-2" href="<?= $base_url ?>/user/dashboard.php<?= (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'empresa') ? '/resumen' : '' ?>"><?= ui_icon('user-panel', 'navbar-icon') ?><span>Mi Panel</span></a>
            </li>
        <?php endif; ?>
        <li class="nav-item">
            <a class="nav-link" href="<?= $base_url ?>/auth/logout.php">Cerrar Sesión</a>
        </li>
    <?php else: ?>
        <li class="nav-item">
            <a class="nav-link" href="<?= $base_url ?>/auth/login.php">Login</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?= $base_url ?>/auth/register.php">Registrarse</a>
        </li>
    <?php endif; ?>
</ul>
            </div>
        </div>
    </nav>
    <script>
        window.APP_BASE_URL = <?= json_encode($base_url) ?>;
        window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
    </script>
    <main class="container py-4">