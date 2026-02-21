<?php
session_start();
require '../config/db.php';

// Verificar que sea admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    die("Acceso denegado");
}

include '../templates/header.php';
?>

<h1>Panel de Administrador</h1>

<div class="row mt-4">
    <div class="col-md-4">
        <div class="card text-white bg-primary mb-3">
            <div class="card-body">
                <h5 class="card-title">Empresas</h5>
                <?php
                $count = $pdo->query("SELECT COUNT(*) FROM empresas")->fetchColumn();
                echo "<h2>$count</h2>";
                ?>
                <a href="empresas.php" class="btn btn-light btn-sm">Gestionar</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-success mb-3">
            <div class="card-body">
                <h5 class="card-title">Usuarios</h5>
                <?php
                $count = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
                echo "<h2>$count</h2>";
                ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-warning mb-3">
            <div class="card-body">
                <h5 class="card-title">Categorías</h5>
                <?php
                $count = $pdo->query("SELECT COUNT(*) FROM categorias")->fetchColumn();
                echo "<h2>$count</h2>";
                ?>
                <a href="categorias.php" class="btn btn-light btn-sm">Gestionar</a>
            </div>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>