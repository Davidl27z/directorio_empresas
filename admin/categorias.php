<?php
require '../config/db.php';
require_once '../config/csrf.php';

// Genera un slug único comprobando la tabla y añadiendo sufijos si hace falta
function make_unique_slug($pdo, $baseSlug, $excludeId = null) {
    $slug = $baseSlug;
    $i = 1;
    while (true) {
        if ($excludeId) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categorias WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $excludeId]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categorias WHERE slug = ?");
            $stmt->execute([$slug]);
        }
        $count = (int) $stmt->fetchColumn();
        if ($count === 0) return $slug;
        $i++;
        $slug = $baseSlug . '-' . $i;
    }
}

// Verificar que sea admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    die("Acceso denegado. Solo administradores.");
}

$msg = '';

// Agregar categoría
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!csrf_validate($token)) {
        $msg = "❌ Token CSRF inválido";
    } else {
        $nombre = trim($_POST['nombre']);
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $nombre)));
        // Asegurar slug único antes de insertar
        $baseSlug = $slug ?: 'categoria';
        $slug = make_unique_slug($pdo, $baseSlug);
        // Intentar procesar imagen (opcional)
        require_once '../config/upload.php';
        $imagen = null;
        $upload = secure_upload('imagen', __DIR__ . '/../uploads/categorias');
        if ($upload['ok']) {
            $imagen = $upload['filename'];
        } elseif (($upload['code'] ?? '') !== 'no_file') {
            $msg = '❌ Error al subir imagen: ' . ($upload['msg'] ?? 'Error');
        }

        if (isset($msg) && strpos($msg, '❌') === 0) {
            // error, no insertar
        } else {
            $stmt = $pdo->prepare("INSERT INTO categorias (nombre, slug, imagen) VALUES (?, ?, ?)");
            $stmt->execute([$nombre, $slug, $imagen]);
            $msg = "✅ Categoría agregada correctamente";
        }
    }
}

// Editar categoría via POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar'])) {
    $token = $_POST['csrf_token'] ?? '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!csrf_validate($token)) {
        $msg = "❌ Token CSRF inválido";
    } else {
        $nombre = trim($_POST['nombre']);
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $nombre)));
        $baseSlug = $slug ?: 'categoria';
        $slug = make_unique_slug($pdo, $baseSlug, $id);

        // Procesar imagen nueva (opcional)
        require_once '../config/upload.php';
        $imagen = null;
        $upload = secure_upload('imagen', __DIR__ . '/../uploads/categorias');
        if ($upload['ok']) {
            $imagen = $upload['filename'];
            // borrar imagen anterior
            $stmt = $pdo->prepare("SELECT imagen FROM categorias WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row && !empty($row['imagen'])) {
                $old = __DIR__ . '/../uploads/categorias/' . $row['imagen'];
                if (is_file($old)) @unlink($old);
            }
        } elseif (($upload['code'] ?? '') !== 'no_file') {
            $msg = '❌ Error al subir imagen: ' . ($upload['msg'] ?? 'Error');
        }

        if (isset($msg) && strpos($msg, '❌') === 0) {
            // error, no actualizar
        } else {
            if ($imagen) {
                $stmt = $pdo->prepare("UPDATE categorias SET nombre = ?, slug = ?, imagen = ? WHERE id = ?");
                $stmt->execute([$nombre, $slug, $imagen, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE categorias SET nombre = ?, slug = ? WHERE id = ?");
                $stmt->execute([$nombre, $slug, $id]);
            }
            $msg = "✅ Categoría actualizada";
        }
    }
}

// Eliminar categoría via POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar'])) {
    $token = $_POST['csrf_token'] ?? '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!csrf_validate($token)) {
        $msg = "❌ Token CSRF inválido";
    } else {
        // Verificar empresas asociadas
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM empresas WHERE categoria_id = ?");
        $countStmt->execute([$id]);
        $count = (int) $countStmt->fetchColumn();
        if ($count > 0) {
            $msg = "❌ No se puede eliminar esta categoría: tiene {$count} empresas asociadas.";
        } else {
            // obtener imagen para eliminar archivo si existe
            $stmt = $pdo->prepare("SELECT imagen FROM categorias WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row && !empty($row['imagen'])) {
                $file = __DIR__ . '/../uploads/categorias/' . $row['imagen'];
                if (is_file($file)) @unlink($file);
            }
            $stmt = $pdo->prepare("DELETE FROM categorias WHERE id = ?");
            $stmt->execute([$id]);
            $msg = "✅ Categoría eliminada";
        }
    }
}

// Obtener categorías (incluye cuántas empresas tiene cada una)
$categorias = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM empresas e WHERE e.categoria_id = c.id) AS empresas_count FROM categorias c ORDER BY c.nombre")->fetchAll();

$editCat = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM categorias WHERE id = ?");
    $stmt->execute([$id]);
    $editCat = $stmt->fetch();
}

include '../templates/header.php';
?>

<div class="row">
    <?php include 'sidebar.php'; ?>
    <div class="col-lg-9">
        <h1>Gestión de Categorías</h1>

<?php if($msg): ?>
    <div class="alert <?= strpos($msg, '❌') === 0 ? 'alert-danger' : 'alert-success' ?>"><?= $msg ?></div>
<?php endif; ?>

<?php if ($editCat): ?>
    <div class="card mb-4 border-warning">
        <div class="card-header bg-warning text-dark">
            <h5>✏️ Editar Categoría</h5>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= $editCat['id'] ?>">
                <div class="col-md-6">
                    <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($editCat['nombre']) ?>" required>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center gap-2">
                        <img src="<?= $base_url ?? '..' ?>/uploads/categorias/<?= htmlspecialchars($editCat['imagen'] ?: 'default.png') ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:6px;" onerror="this.src='<?= $base_url ?? '..' ?>/uploads/categorias/default.png'">
                        <input type="file" name="imagen" class="form-control" accept="image/*">
                    </div>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" name="editar" class="btn btn-warning w-100">Actualizar</button>
                    <a href="categorias.php" class="btn btn-secondary w-100">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header bg-success text-white">
        <h5>➕ Agregar Nueva Categoría</h5>
    </div>
    <div class="card-body">
        <form method="POST" class="row g-3" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <div class="col-md-6">
                <input type="text" name="nombre" class="form-control" placeholder="Nombre de la categoría" required>
            </div>
            <div class="col-md-4">
                <input type="file" name="imagen" class="form-control" accept="image/*">
            </div>
            <div class="col-md-2">
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
            <th>Empresas</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($categorias as $cat): ?>
            <tr>
                <td><?= $cat['id'] ?></td>
                <td>
                    <?php $img = $cat['imagen'] ?? null; ?>
                    <div style="display:flex;align-items:center;gap:.6rem;">
                        <img src="<?= $base_url ?? '..' ?>/uploads/categorias/<?= htmlspecialchars($img ?: 'default.png') ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:6px;" onerror="this.src='<?= $base_url ?? '..' ?>/uploads/categorias/default.png'">
                        <span><?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </td>
                <td><code><?= $cat['slug'] ?></code></td>
                <td class="text-center"><?= $cat['empresas_count'] ?></td>
                <td>
                    <a href="categorias.php?edit=<?= $cat['id'] ?>" class="btn btn-outline-primary btn-sm me-1">Editar</a>
                    <form method="POST" style="display:inline" onsubmit="return confirm('¿Estás seguro?')">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                        <button type="submit" name="eliminar" class="btn btn-danger btn-sm" <?= $cat['empresas_count'] > 0 ? 'disabled title="No se puede eliminar con empresas asociadas"' : '' ?>>Eliminar</button>
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

<?php include '../templates/footer.php'; ?>