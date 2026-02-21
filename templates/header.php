<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Directorio de Empresas</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">🏢 MiDirector</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
    <li class="nav-item">
        <a class="nav-link" href="index.php">Inicio</a>
    </li>
    <?php if(isset($_SESSION['user_id'])): ?>
        <?php if(isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link fw-bold" href="admin/dashboard.php">📊 Admin</a>
            </li>
        <?php else: ?>
            <li class="nav-item">
                <a class="nav-link fw-bold" href="user/dashboard.php">👤 Mi Panel</a>
            </li>
        <?php endif; ?>
        <li class="nav-item">
            <a class="nav-link" href="auth/logout.php">Cerrar Sesión</a>
        </li>
    <?php else: ?>
        <li class="nav-item">
            <a class="nav-link" href="auth/login.php">Login</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="auth/register.php">Registrarse</a>
        </li>
    <?php endif; ?>
</ul>
            </div>
        </div>
    </nav>
    <main class="container py-4">