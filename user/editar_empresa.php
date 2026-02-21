<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$id = $_GET['id'] ?? 0;
$usuario_id = $_SESSION['user_id'];

// Verificar que la empresa sea del usuario
$stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ? AND usuario_id = ?");
$stmt->execute([$id, $usuario_id]);
$empresa = $stmt->fetch();

if (!$empresa) {
    die("Empresa no encontrada o no tienes permiso para editarla.");
}

// Obtener categorías
$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nombre")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $categoria_id = $_POST['categoria_id'];
    $direccion = $_POST['direccion'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];
    $website = $_POST['website'];
    
    // Si edita, vuelve a estado pendiente (para que el admin la revise de nuevo)
    $stmt = $pdo->prepare("UPDATE empresas SET nombre=?, descripcion=?, categoria_id=?, direccion=?, telefono=?, email=?, website=?, aprobada=0 WHERE id=?");
    $stmt->execute([$nombre, $descripcion, $categoria_id, $direccion, $telefono, $email, $website, $id]);
    
    header("Location: dashboard.php?msg=" . urlencode("✅ Empresa actualizada. Esperando aprobación."));
    exit;
}

include '../templates/header.php';
?>

<h2>Editar Empresa</h2>
<p class="text-warning">⚠️ Al editar, deberá ser aprobada nuevamente por el admin.</p>

<form method="POST" class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Nombre</label>
        <input type="text" name="nombre" class="form-control" value="<?= $empresa['nombre'] ?>" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Categoría</label>
        <select name="categoria_id" class="form-select" required>
            <?php foreach($categorias as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $empresa['categoria_id'] ? 'selected' : '' ?>>
                    <?= $cat['nombre'] ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12">
        <label class="form-label">Descripción</label>
        <textarea name="descripcion" class="form-control" rows="3"><?= $empresa['descripcion'] ?></textarea>
    </div>
    <div class="col-md-4">
        <label class="form-label">Dirección</label>
        <input type="text" name="direccion" class="form-control" value="<?= $empresa['direccion'] ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Teléfono</label>
        <input type="text" name="telefono" class="form-control" value="<?= $empresa['telefono'] ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Website</label>
        <input type="text" name="website" class="form-control" value="<?= $empresa['website'] ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= $empresa['email'] ?>">
    </div>
    <div class="col-12">
        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<?php include '../templates/footer.php'; ?>