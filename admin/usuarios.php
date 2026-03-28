<?php
session_start();
require '../config/db.php';
require_once '../config/csrf.php';

// Verificar que sea admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    die("Acceso denegado");
}

$msg = '';

// Acciones: editar / eliminar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $token = $_POST['csrf_token'] ?? '';

    if (!csrf_validate($token)) {
        $msg = "❌ Token CSRF inválido";
    } else {
        if ($action === 'eliminar' && $id) {
            // No permitir borrar al mismo admin que está logueado
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $id) {
                $msg = "❌ No puedes eliminar tu propia cuenta.";
            } else {
                // Evitar eliminar el último admin
                $adminCount = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'")->fetchColumn();
                $isAdmin = $pdo->prepare("SELECT rol FROM usuarios WHERE id = ?");
                $isAdmin->execute([$id]);
                $rol = $isAdmin->fetchColumn();
                if ($rol === 'admin' && $adminCount <= 1) {
                    $msg = "❌ No puedes eliminar al único administrador.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
                    $stmt->execute([$id]);
                    $msg = "✅ Usuario eliminado";
                }
            }
        } elseif ($action === 'guardar' && $id) {
            $nombre = trim($_POST['nombre']);
            $email = trim($_POST['email']);
            $rol = in_array($_POST['rol'] ?? '', ['admin', 'cliente', 'empresa']) ? $_POST['rol'] : 'cliente';

            // Evitar que se retire el último admin
            if ($_SESSION['user_id'] == $id && $rol !== 'admin') {
                $msg = "❌ No puedes quitarte el rol de administrador.";
            } else {
                // Verificar que no se quite el último admin
                $adminCount = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'")->fetchColumn();
                $isAdmin = $pdo->prepare("SELECT rol FROM usuarios WHERE id = ?");
                $isAdmin->execute([$id]);
                if ($isAdmin->fetchColumn() === 'admin' && $adminCount <= 1 && $rol !== 'admin') {
                    $msg = "❌ No puedes cambiar el rol del único administrador.";
                }
            }

            if (!$msg) {
                $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol = ? WHERE id = ?");
                $stmt->execute([$nombre, $email, $rol, $id]);
                $msg = "✅ Usuario actualizado";
            }
        }
    }
}

// Cargar datos para el formulario de edición si corresponde
$editUser = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT id, nombre, email, rol FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $editUser = $stmt->fetch();
}

$users = $pdo->query("SELECT id, nombre, email, rol, created_at FROM usuarios ORDER BY id DESC")->fetchAll();

include '../templates/header.php';
?>

<div class="row">
    <?php include 'sidebar.php'; ?>
    <div class="col-lg-9">

<h1>Gestión de Usuarios</h1>

<?php if($msg): ?>
    <div class="alert <?= strpos($msg, '❌') === 0 ? 'alert-danger' : 'alert-success' ?>"><?= $msg ?></div>
<?php endif; ?>

<?php if ($editUser): ?>
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">
            <h5>✏️ Editar Usuario</h5>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= $editUser['id'] ?>">
                <input type="hidden" name="action" value="guardar">
                <div class="col-md-4">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($editUser['nombre']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($editUser['email']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Rol</label>
                    <select name="rol" class="form-select">
                        <option value="cliente" <?= $editUser['rol'] === 'cliente' ? 'selected' : '' ?>>Cliente</option>
                        <option value="empresa" <?= $editUser['rol'] === 'empresa' ? 'selected' : '' ?>>Empresa</option>
                        <option value="admin" <?= $editUser['rol'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<p>Desde aquí puedes ver los usuarios registrados y su rol.</p>

<table class="table table-striped table-hover">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Email</th>
            <th>Rol</th>
            <th>Creado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $adminCount = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'")->fetchColumn();
        foreach ($users as $user):
            $canDelete = !(isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user['id']);
            $isOnlyAdmin = $user['rol'] === 'admin' && $adminCount <= 1;
        ?>
            <tr>
                <td><?= $user['id'] ?></td>
                <td><?= htmlspecialchars($user['nombre']) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td>
                    <?php
                    $rol_nombres = [
                        'cliente' => 'Cliente',
                        'empresa' => 'Empresa',
                        'admin' => 'Administrador'
                    ];
                    echo htmlspecialchars($rol_nombres[$user['rol']] ?? $user['rol']);
                    ?>
                </td>
                <td><?= htmlspecialchars($user['created_at'] ?? '') ?></td>
                <td>
                    <a href="usuarios.php?edit=<?= $user['id'] ?>" class="btn btn-outline-primary btn-sm me-1">Editar</a>
                    <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar este usuario?')">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                        <input type="hidden" name="action" value="eliminar">
                        <button type="submit" class="btn btn-danger btn-sm" <?= (!$canDelete || $isOnlyAdmin) ? 'disabled title="No permitido"' : '' ?>>Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if (!isset($base_url)) $base_url = '..'; ?>
<a href="<?= $base_url ?>/admin/dashboard.php" class="btn btn-secondary">← Volver al Panel</a>

    </div>
</div>

<?php include '../templates/footer.php';
