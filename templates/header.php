<?php
// Force UTF-8 output and internal encoding
if (!ini_get('default_charset') || strtolower(ini_get('default_charset')) !== 'utf-8') {
    ini_set('default_charset', 'UTF-8');
}
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
    <?php if(isset($_SESSION['user_id'])): ?>
        <?php if(isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link fw-bold" href="<?= $base_url ?>/admin/dashboard.php">📊 Admin</a>
            </li>
        <?php else: ?>
            <li class="nav-item">
                <a class="nav-link fw-bold" href="<?= $base_url ?>/user/dashboard.php">👤 Mi Panel</a>
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
    <main class="container py-4">