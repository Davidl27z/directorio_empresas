<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    die("Acceso denegado");
}

$msg = '';

// Aprobar empresa
if (isset($_GET['aprobar'])) {
    $id = $_GET['aprobar'];
    $stmt = $pdo->prepare("UPDATE empresas SET aprobada = 1 WHERE id = ?");
    $stmt->execute([$id]);
    $msg = "✅ Empresa aprobada";
}

// Rechazar empresa
if (isset($_GET['rechazar'])) {
    $id = $_GET['rechazar'];
    $stmt = $pdo->prepare("DELETE FROM empresas WHERE id = ?");
    $stmt->execute([$id]);
    $msg = "❌ Empresa rechazada y eliminada";
}

// Eliminar empresa
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $stmt = $pdo->prepare("DELETE FROM empresas WHERE id = ?");
    $stmt->execute([$id]);
    $msg = "✅ Empresa eliminada";
}

// Obtener empresas con categoría
$sql = "SELECT e.*, c.nombre as categoria_nombre, u.nombre as usuario_nombre 
        FROM empresas e 
        LEFT JOIN categorias c ON e.categoria_id = c.id
        LEFT JOIN usuarios u ON e.usuario_id = u.id
        ORDER BY e.id DESC";
$empresas = $pdo->query($sql)->fetchAll();

$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nombre")->fetchAll();

// Agregar empresa (solo admin)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar'])) {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $categoria_id = $_POST['categoria_id'];
    $direccion = $_POST['direccion'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];
    $website = $_POST['website'];
    
    // Las empresas del admin van aprobadas directamente
    $stmt = $pdo->prepare("INSERT INTO empresas (nombre, descripcion, categoria_id, direccion, telefono, email, website, aprobada) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
    $stmt->execute([$nombre, $descripcion, $categoria_id, $direccion, $telefono, $email, $website]);
    $msg = "✅ Empresa agregada por el admin";
}

include '../templates/header.php';
?>

<h1>Gestión de Empresas</h1>

<?php if($msg): ?>
    <div class="alert alert-success"><?= $msg ?></div>
<?php endif; ?>

<!-- Empresas Pendientes de Aprobación -->
<?php 
$pendientes = array_filter($empresas, function($e) { return !$e['aprobada']; });
if (count($pendientes) > 0): 
?>
<div class="card mb-4 border-warning">
    <div class="card-header bg-warning text-dark">
        <h5>⏳ Empresas Pendientes de Aprobación (<?= count($pendientes) ?>)</h5>
    </div>
    <div class="card-body">
        <table class="table table-warning">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Categoría</th>
                    <th>Usuario</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($pendientes as $emp): ?>
                    <tr>
                        <td><?= $emp['nombre'] ?></td>
                        <td><?= $emp['categoria_nombre'] ?? 'Sin categoría' ?></td>
                        <td><?= $emp['usuario_nombre'] ?? 'Sin usuario' ?></td>
                        <td>
                            <a href="?aprobar=<?= $emp['id'] ?>" class="btn btn-success btn-sm">✅ Aprobar</a>
                            <a href="?rechazar=<?= $emp['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Rechazar y eliminar?')">❌ Rechazar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Formulario para agregar empresa (solo admin) -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5>➕ Agregar Nueva Empresa (Admin)</h5>
    </div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Nombre</label>
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

<!-- Lista de empresas aprobadas -->
<h3>Empresas Aprobadas (<?= count(array_filter($empresas, function($e) { return $e['aprobada']; })) ?>)</h3>

<table class="table table-bordered table-hover">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Categoría</th>
            <th>Usuario</th>
            <th>Teléfono</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $aprobadas = array_filter($empresas, function($e) { return $e['aprobada']; });
        foreach($aprobadas as $emp): 
        ?>
            <tr>
                <td><?= $emp['id'] ?></td>
                <td><?= $emp['nombre'] ?></td>
                <td><?= $emp['categoria_nombre'] ?? 'Sin categoría' ?></td>
                <td><?= $emp['usuario_nombre'] ?? 'Admin' ?></td>
                <td><?= $emp['telefono'] ?? '-' ?></td>
                <td>
                    <a href="?eliminar=<?= $emp['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar esta empresa?')">Eliminar</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if(count($aprobadas) == 0): ?>
    <div class="alert alert-warning">No hay empresas aprobadas.</div>
<?php endif; ?>

<a href="dashboard.php" class="btn btn-secondary">← Volver al Panel</a>

<?php include '../templates/footer.php'; ?>