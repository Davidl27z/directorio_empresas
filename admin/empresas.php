<?php
require '../config/db.php';
require_once '../config/csrf.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    die("Acceso denegado");
}

// Definir base_url tempranamente
if (!isset($base_url)) {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $parts = array_filter(explode('/', trim($scriptDir, '/')));
    $depth = max(0, count($parts) - 1);
    $base_url = $depth === 0 ? '.' : str_repeat('../', $depth);
}

$msg = '';

// Manejar acciones por POST (aprobar, rechazar, eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action) {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $token = $_POST['csrf_token'] ?? '';
        if (!csrf_validate($token)) {
            $msg = "❌ Token CSRF inválido";
        } else {
            if ($action === 'aprobar' && $id) {
                $stmt = $pdo->prepare("UPDATE empresas SET aprobada = 1 WHERE id = ?");
                $stmt->execute([$id]);
                $msg = "✅ Empresa aprobada";
            } elseif ($action === 'rechazar' && $id) {
                // eliminar logo si existe
                $s = $pdo->prepare("SELECT logo FROM empresas WHERE id = ?");
                $s->execute([$id]);
                $r = $s->fetch();
                if ($r && !empty($r['logo'])) {
                    $file = __DIR__ . '/../uploads/logos/' . $r['logo'];
                    if (is_file($file)) @unlink($file);
                }
                $stmt = $pdo->prepare("DELETE FROM empresas WHERE id = ?");
                $stmt->execute([$id]);
                $msg = "❌ Empresa rechazada y eliminada";
            } elseif ($action === 'eliminar' && $id) {
                // eliminar logo si existe
                $s = $pdo->prepare("SELECT logo FROM empresas WHERE id = ?");
                $s->execute([$id]);
                $r = $s->fetch();
                if ($r && !empty($r['logo'])) {
                    $file = __DIR__ . '/../uploads/logos/' . $r['logo'];
                    if (is_file($file)) @unlink($file);
                }
                $stmt = $pdo->prepare("DELETE FROM empresas WHERE id = ?");
                $stmt->execute([$id]);
                $msg = "✅ Empresa eliminada";
            }
        }
    }
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
    $token = $_POST['csrf_token'] ?? '';
    if (!csrf_validate($token)) {
        $msg = "❌ Token CSRF inválido";
    } else {
        $nombre = trim($_POST['nombre']);
        $descripcion = trim($_POST['descripcion']);
        $categoria_id = (int)($_POST['categoria_id'] ?? 0);
        $direccion = trim($_POST['direccion']);
        $telefono = trim($_POST['telefono']);
        $email = trim($_POST['email']);
        $website = trim($_POST['website']);

        // Validar email y website
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = "❌ Email no válido";
        } elseif ($website && !filter_var($website, FILTER_VALIDATE_URL)) {
            $msg = "❌ Website no válido";
        } else {
            $stmt = $pdo->prepare("INSERT INTO empresas (nombre, descripcion, categoria_id, direccion, telefono, email, website, aprobada) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$nombre, $descripcion, $categoria_id, $direccion, $telefono, $email, $website]);
            $msg = "✅ Empresa agregada por el admin";
        }
    }
}

include '../templates/header.php';
?>

<div class="row">
    <?php include 'sidebar.php'; ?>
    <div class="col-lg-9">
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
                                <a href="<?= $base_url ?>/empresa.php?id=<?= $emp['id'] ?>" target="_blank" class="btn btn-info btn-sm">👁️ Ver Página</a>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                                    <input type="hidden" name="action" value="aprobar">
                                    <button type="submit" class="btn btn-success btn-sm">✅ Aprobar</button>
                                </form>
                                <form method="POST" style="display:inline" onsubmit="return confirm('¿Rechazar y eliminar?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                                    <input type="hidden" name="action" value="rechazar">
                                    <button type="submit" class="btn btn-danger btn-sm">❌ Rechazar</button>
                                </form>
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
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
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
                    <a href="<?= $base_url ?>/empresa.php?id=<?= $emp['id'] ?>" target="_blank" class="btn btn-info btn-sm">👁️ Ver Página</a>
                    <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar esta empresa?')">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                        <input type="hidden" name="action" value="eliminar">
                        <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if(count($aprobadas) == 0): ?>
    <div class="alert alert-warning">No hay empresas aprobadas.</div>
<?php endif; ?>

<a href="<?= $base_url ?>/admin/dashboard.php" class="btn btn-secondary">← Volver al Panel</a>

    </div>
</div>

<?php include '../templates/footer.php'; ?>