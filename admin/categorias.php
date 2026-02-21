<?php
session_start();
require '../config/db.php';

// Verificar que sea admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    die("Acceso denegado. Solo administradores.");
}

$msg = '';

// Agregar categoría
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar'])) {
    $nombre = $_POST['nombre'];
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $nombre)));
    
    $stmt = $pdo->prepare("INSERT INTO categorias (nombre, slug) VALUES (?, ?)");
    $stmt->execute([$nombre, $slug]);
    $msg = "✅ Categoría agregada correctamente";
}

// Eliminar categoría
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $stmt = $pdo->prepare("DELETE FROM categorias WHERE id = ?");
    $stmt->execute([$id]);
    $msg = "✅ Categoría eliminada";
}

// Obtener categorías
$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nombre")->fetchAll();

include '../templates/header.php';
?>

<h1>Gestión de Categorías</h1>

<?php if($msg): ?>
    <div class="alert alert-success"><?= $msg ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header bg-success text-white">
        <h5>➕ Agregar Nueva Categoría</h5>
    </div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <div class="col-md-8">
                <input type="text" name="nombre" class="form-control" placeholder="Nombre de la categoría" required>
            </div>
            <div class="col-md-4">
                <button type="submit" name="agregar" class="btn btn-success w-100">Agregar</button>
            </div>
        </form>
    </div>
</div>

<table class="table table-striped table-hover">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Slug</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($categorias as $cat): ?>
            <tr>
                <td><?= $cat['id'] ?></td>
                <td><?= $cat['nombre'] ?></td>
                <td><code><?= $cat['slug'] ?></code></td>
                <td>
                    <a href="?eliminar=<?= $cat['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro?')">Eliminar</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<a href="dashboard.php" class="btn btn-secondary">← Volver al Panel</a>

<?php include '../templates/footer.php'; ?>