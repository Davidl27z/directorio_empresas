<?php
session_start();
require '../config/db.php';

// Verificar que sea admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    die("Acceso denegado. Solo administradores.");
}

$msg = '';

// Eliminar empresa
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $stmt = $pdo->prepare("DELETE FROM empresas WHERE id = ?");
    $stmt->execute([$id]);
    $msg = "✅ Empresa eliminada";
}

// Obtener empresas con categoría
$sql = "SELECT e.*, c.nombre as categoria_nombre 
        FROM empresas e 
        LEFT JOIN categorias c ON e.categoria_id = c.id 
        ORDER BY e.id DESC";
$empresas = $pdo->query($sql)->fetchAll();

// Obtener categorías para el formulario
$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nombre")->fetchAll();

// Agregar empresa
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar'])) {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $categoria_id = $_POST['categoria_id'];
    $direccion = $_POST['direccion'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];
    $website = $_POST['website'];
    
    $stmt = $pdo->prepare("INSERT INTO empresas (nombre, descripcion, categoria_id, direccion, telefono, email, website) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$nombre, $descripcion, $categoria_id, $direccion, $telefono, $email, $website]);
    $msg = "✅ Empresa agregada correctamente";
}

include '../templates/header.php';
?>

<h1>Gestión de Empresas</h1>

<?php if($msg): ?>
    <div class="alert alert-success"><?= $msg ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5>➕ Agregar Nueva Empresa</h5>
    </div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Nombre de la empresa</label>
                <input type="text" name="nombre" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Categoría</label>
                <select name="categoria_id" class="form-select" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= $cat['nombre'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Descripción</label>
                <textarea name="descripcion" class="form-control" rows="3"></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">Dirección</label>
                <input type="text" name="direccion" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Teléfono</label>
                <input type="text" name="telefono" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Website</label>
                <input type="text" name="website" class="form-control" placeholder="https://...">
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control">
            </div>
            <div class="col-12">
                <button type="submit" name="agregar" class="btn btn-primary">Agregar Empresa</button>
            </div>
        </form>
    </div>
</div>

<h3>Empresas Registradas (<?= count($empresas) ?>)</h3>

<table class="table table-bordered table-hover">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Categoría</th>
            <th>Teléfono</th>
            <th>Email</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($empresas as $emp): ?>
            <tr>
                <td><?= $emp['id'] ?></td>
                <td><?= $emp['nombre'] ?></td>
                <td><?= $emp['categoria_nombre'] ?? 'Sin categoría' ?></td>
                <td><?= $emp['telefono'] ?? '-' ?></td>
                <td><?= $emp['email'] ?? '-' ?></td>
                <td>
                    <a href="?eliminar=<?= $emp['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar esta empresa?')">Eliminar</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if(count($empresas) == 0): ?>
    <div class="alert alert-warning">No hay empresas registradas.</div>
<?php endif; ?>

<a href="dashboard.php" class="btn btn-secondary">← Volver al Panel</a>

<?php include '../templates/footer.php'; ?>