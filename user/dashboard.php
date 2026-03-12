<?php
session_start();
require '../config/db.php';
require_once '../config/csrf.php';

// Verificar que esté logueado
if (!isset($_SESSION['user_id'])) {
    if (!isset($base_url)) $base_url = '..';
    header("Location: " . $base_url . "/auth/login.php");
    exit;
}

$usuario_id = $_SESSION['user_id'];

// Obtener empresas del usuario
$stmt = $pdo->prepare("SELECT * FROM empresas WHERE usuario_id = ? ORDER BY id DESC");
$stmt->execute([$usuario_id]);
$mis_empresas = $stmt->fetchAll();

// Obtener categorías para el formulario
$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nombre")->fetchAll();

include '../templates/header.php';
?>

<h1>Mi Panel de Usuario</h1>
<p>Bienvenido, <?= htmlspecialchars($_SESSION['user_name']) ?></p>

<div class="row mt-4">
    <!-- Mis Empresas -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5>Mis Empresas</h5>
            </div>
            <div class="card-body">
                <?php if(count($mis_empresas) > 0): ?>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($mis_empresas as $emp): ?>
                                <tr>
                                    <td><?= htmlspecialchars($emp['nombre']) ?></td>
                                    <td>
                                        <?php if($emp['aprobada']): ?>
                                            <span class="badge bg-success">✅ Aprobada</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">⏳ Pendiente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!isset($base_url)) $base_url = '..'; ?>
                                        <a href="<?= $base_url ?>/user/editar_empresa.php?id=<?= $emp['id'] ?>" class="btn btn-sm btn-primary">Editar</a>
                                        <form method="POST" action="<?= $base_url ?>/user/eliminar_empresa.php" style="display:inline" onsubmit="return confirm('¿Eliminar?')">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-muted">No has creado ninguna empresa todavía.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Agregar Empresa -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5>➕ Agregar Empresa</h5>
            </div>
            <div class="card-body">
                <?php if (!isset($base_url)) $base_url = '..'; ?>
                <form method="POST" action="<?= $base_url ?>/user/crear_empresa.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <div class="mb-2">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Categoría</label>
                        <select name="categoria_id" class="form-select" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach($categorias as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= $cat['nombre'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="direccion" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Website</label>
                        <input type="text" name="website" class="form-control" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Logo (opcional)</label>
                        <input type="file" name="logo" class="form-control" accept="image/*">
                    </div>
                    <button type="submit" class="btn btn-success w-100">Enviar para Aprobación</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>