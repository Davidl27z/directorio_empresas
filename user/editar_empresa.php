<?php
session_start();
require '../config/db.php';
require_once '../config/csrf.php';
require_once '../config/empresa_contacto.php';

empresa_ensure_contact_schema($pdo);

if (!isset($_SESSION['user_id'])) {
    if (!isset($base_url)) $base_url = '..';
    header("Location: " . $base_url . "/auth/login.php");
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
    // CSRF check
    $token = $_POST['csrf_token'] ?? '';
    if (!csrf_validate($token)) {
        die('Token CSRF inválido');
    }

    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $categoria_id = (int)($_POST['categoria_id'] ?? 0);
    $direccion = trim($_POST['direccion']);
    $telefono = trim($_POST['telefono']);
    $email = trim($_POST['email']);
    $website = empresa_normalize_website($_POST['website'] ?? '');
    $instagram = empresa_normalize_instagram($_POST['instagram'] ?? '');
    $tiktok = empresa_normalize_tiktok($_POST['tiktok'] ?? '');
    $facebook = empresa_normalize_facebook($_POST['facebook'] ?? '');
    $whatsapp = empresa_normalize_whatsapp($_POST['whatsapp'] ?? '');
    $medios_contacto = empresa_normalize_contact_methods($_POST['medios_contacto'] ?? []);

    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die('Email no válido');
    }
    if ($website && !filter_var($website, FILTER_VALIDATE_URL)) {
        die('Website no válido');
    }
    if ($whatsapp !== '' && strlen(empresa_whatsapp_digits($whatsapp)) < 8) {
        die('WhatsApp no válido');
    }
    if (empty($medios_contacto)) {
        die('Debes seleccionar al menos un medio de contacto para pedidos');
    }

    // Manejo de logo (opcional)
    require_once '../config/upload.php';
    $logoFilename = null;
    $upload = secure_upload('logo', __DIR__ . '/../uploads/logos');
    if ($upload['ok']) {
        $logoFilename = $upload['filename'];
        // eliminar logo anterior si existía
        if (!empty($empresa['logo'])) {
            $old = __DIR__ . '/../uploads/logos/' . $empresa['logo'];
            if (is_file($old)) @unlink($old);
        }
    } elseif (($upload['code'] ?? '') !== 'no_file') {
        die('Error al subir logo: ' . ($upload['msg'] ?? 'Error'));
    }

    // Si edita, vuelve a estado pendiente (para que el admin la revise de nuevo)
    if ($logoFilename) {
        $stmt = $pdo->prepare("UPDATE empresas SET nombre=?, descripcion=?, categoria_id=?, direccion=?, telefono=?, email=?, website=?, instagram=?, tiktok=?, facebook=?, whatsapp=?, medios_contacto_pedido=?, aprobada=0, logo=? WHERE id=?");
        $stmt->execute([$nombre, $descripcion, $categoria_id, $direccion, $telefono, $email, $website, $instagram, $tiktok, $facebook, $whatsapp, empresa_serialize_contact_methods($medios_contacto), $logoFilename, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE empresas SET nombre=?, descripcion=?, categoria_id=?, direccion=?, telefono=?, email=?, website=?, instagram=?, tiktok=?, facebook=?, whatsapp=?, medios_contacto_pedido=?, aprobada=0 WHERE id=?");
        $stmt->execute([$nombre, $descripcion, $categoria_id, $direccion, $telefono, $email, $website, $instagram, $tiktok, $facebook, $whatsapp, empresa_serialize_contact_methods($medios_contacto), $id]);
    }

    if (!isset($base_url)) $base_url = '..';
    header("Location: " . $base_url . "/user/dashboard.php?msg=" . urlencode("✅ Empresa actualizada. Esperando aprobación."));
    exit;
}

include '../templates/header.php';

$mediosSeleccionados = empresa_deserialize_contact_methods($empresa['medios_contacto_pedido'] ?? '');
?>

<h2>Editar Empresa</h2>

<div class="alert alert-info d-flex align-items-center gap-2">
    <span>Estado Actual:</span>
    <?php if($empresa['aprobada']): ?>
        <span class="badge bg-success">✅ Publicada</span>
    <?php else: ?>
        <span class="badge bg-warning text-dark">🔄 En Revisión</span>
    <?php endif; ?>
</div>

<div class="alert alert-warning">
    <strong>Nota:</strong> Los cambios que realices aquí serán revisados nuevamente por el administrador antes de ser publicados.
</div>

<form method="POST" class="row g-3" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
    <div class="col-md-6">
        <label class="form-label">Nombre</label>
        <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($empresa['nombre']) ?>" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Categoría</label>
        <select name="categoria_id" class="form-select" required>
            <?php foreach($categorias as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $empresa['categoria_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12">
        <label class="form-label">Descripción</label>
        <textarea name="descripcion" class="form-control" rows="3"><?= htmlspecialchars($empresa['descripcion']) ?></textarea>
    </div>
    <div class="col-md-4">
        <label class="form-label">Dirección</label>
        <input type="text" name="direccion" class="form-control" value="<?= htmlspecialchars($empresa['direccion']) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Teléfono</label>
        <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($empresa['telefono']) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Website</label>
        <input type="text" name="website" class="form-control" value="<?= htmlspecialchars($empresa['website']) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($empresa['email']) ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Instagram</label>
        <input type="text" name="instagram" class="form-control" value="<?= htmlspecialchars($empresa['instagram'] ?? '') ?>" placeholder="usuario o URL">
    </div>
    <div class="col-md-3">
        <label class="form-label">TikTok</label>
        <input type="text" name="tiktok" class="form-control" value="<?= htmlspecialchars($empresa['tiktok'] ?? '') ?>" placeholder="usuario o URL">
    </div>
    <div class="col-md-3">
        <label class="form-label">Facebook</label>
        <input type="text" name="facebook" class="form-control" value="<?= htmlspecialchars($empresa['facebook'] ?? '') ?>" placeholder="pagina o URL">
    </div>
    <div class="col-md-3">
        <label class="form-label">WhatsApp</label>
        <input type="text" name="whatsapp" class="form-control" value="<?= htmlspecialchars($empresa['whatsapp'] ?? '') ?>" placeholder="+549123456789">
    </div>
    <div class="col-12">
        <label class="form-label d-block">Medios de contacto para pedidos</label>
        <?php foreach (empresa_contact_method_labels() as $valor => $etiqueta): ?>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="medios_contacto[]" value="<?= $valor ?>" id="medio_<?= $valor ?>" <?= in_array($valor, $mediosSeleccionados, true) ? 'checked' : '' ?>>
                <label class="form-check-label" for="medio_<?= $valor ?>"><?= htmlspecialchars($etiqueta) ?></label>
            </div>
        <?php endforeach; ?>
        <div class="form-text">El cliente solo podrá elegir entre estos medios al hacer un pedido.</div>
    </div>
    <div class="col-12">
        <div class="mb-3">
            <label class="form-label">Logo (opcional)</label>
            <input type="file" name="logo" class="form-control" accept="image/*">
        </div>
        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        <?php if (!isset($base_url)) $base_url = '..'; ?>
        <a href="<?= $base_url ?>/user/dashboard.php" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<?php include '../templates/footer.php'; ?>