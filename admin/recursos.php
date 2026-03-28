<?php
session_start();
require '../config/db.php';
require_once '../config/csrf.php';
require_once '../config/pyme_recursos.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    die("Acceso denegado");
}

pyme_recursos_ensure_schema($pdo);

$msg = '';

// Manejar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';
    
    if (!csrf_validate($token)) {
        $msg = "Token CSRF inválido";
    } else {
        if ($action === 'crear') {
            $titulo = trim($_POST['titulo'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $beneficios = trim($_POST['beneficios'] ?? '');
            $dirigido_a = trim($_POST['dirigido_a'] ?? '');
            $enlace_url = trim($_POST['enlace_url'] ?? '');

            if (empty($titulo)) {
                $msg = "El título es requerido";
            } elseif (empty($descripcion)) {
                $msg = "La descripción es requerida";
            } else {
                if (pyme_recursos_crear($pdo, $titulo, $descripcion, $beneficios, $dirigido_a, $enlace_url)) {
                    $msg = "Recurso creado exitosamente";
                } else {
                    $msg = "Error al crear el recurso";
                }
            }
        } elseif ($action === 'actualizar') {
            $id = (int)($_POST['id'] ?? 0);
            $titulo = trim($_POST['titulo'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $beneficios = trim($_POST['beneficios'] ?? '');
            $dirigido_a = trim($_POST['dirigido_a'] ?? '');
            $enlace_url = trim($_POST['enlace_url'] ?? '');

            if ($id && !empty($titulo) && !empty($descripcion)) {
                if (pyme_recursos_actualizar($pdo, $id, $titulo, $descripcion, $beneficios, $dirigido_a, $enlace_url)) {
                    $msg = "Recurso actualizado exitosamente";
                } else {
                    $msg = "Error al actualizar el recurso";
                }
            } else {
                $msg = "Datos inválidos";
            }
        } elseif ($action === 'eliminar') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id && pyme_recursos_eliminar($pdo, $id)) {
                $msg = "Recurso eliminado exitosamente";
            } else {
                $msg = "Error al eliminar el recurso";
            }
        } elseif ($action === 'cambiar_estado') {
            $id = (int)($_POST['id'] ?? 0);
            $estado = (int)($_POST['estado'] ?? 0);
            if ($id && pyme_recursos_cambiar_estado($pdo, $id, $estado)) {
                $msg = "Estado actualizado";
            } else {
                $msg = "Error al actualizar el estado";
            }
        }
    }
}

$recursos = pyme_recursos_obtener_admin($pdo);
$editando = null;
if (isset($_GET['editar'])) {
    $id = (int)$_GET['editar'];
    $editando = pyme_recursos_obtener_por_id($pdo, $id);
}

include '../templates/header.php';
?>

<div class="row">
    <?php include 'sidebar.php'; ?>
    <div class="col-lg-9">
        <h1 class="mb-4">Gestión de Recursos PYMES</h1>

        <?php if ($msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Formulario para crear/editar recurso -->
        <div class="card mb-5">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><?= $editando ? 'Editar Recurso' : 'Crear Nuevo Recurso' ?></h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="<?= $editando ? 'actualizar' : 'crear' ?>">
                    <?php if ($editando): ?>
                        <input type="hidden" name="id" value="<?= $editando['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="titulo" class="form-label">Título del Programa</label>
                        <input type="text" class="form-control" id="titulo" name="titulo" 
                               value="<?= $editando ? htmlspecialchars($editando['titulo']) : '' ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" 
                                  rows="3" required><?= $editando ? htmlspecialchars($editando['descripcion']) : '' ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="dirigido_a" class="form-label">Dirigido a</label>
                        <input type="text" class="form-control" id="dirigido_a" name="dirigido_a" 
                               placeholder="Ej: Micro y pequeñas empresas"
                               value="<?= $editando ? htmlspecialchars($editando['dirigido_a']) : '' ?>">
                    </div>

                    <div class="mb-3">
                        <label for="beneficios" class="form-label">Beneficios (uno por línea)</label>
                        <textarea class="form-control" id="beneficios" name="beneficios" 
                                  rows="4" placeholder="Formación gratuita&#10;Mentorías&#10;Herramientas digitales"><?= $editando ? htmlspecialchars($editando['beneficios']) : '' ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="enlace_url" class="form-label">Enlace del Programa (URL)</label>
                        <input type="url" class="form-control" id="enlace_url" name="enlace_url" 
                               placeholder="https://www.ejemplo.com"
                               value="<?= $editando ? htmlspecialchars($editando['enlace_url']) : '' ?>">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <?= $editando ? 'Actualizar Recurso' : 'Crear Recurso' ?>
                        </button>
                        <?php if ($editando): ?>
                            <a href="<?= isset($base_url) && $base_url !== '' ? rtrim($base_url, '/') : '..' ?>/admin/recursos.php" class="btn btn-secondary">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Listado de recursos -->
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">Recursos Publicados (<?= count($recursos) ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recursos)): ?>
                    <div class="alert alert-info">No hay recursos publicados aún</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Título</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recursos as $recurso): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($recurso['titulo']) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars(substr($recurso['descripcion'], 0, 60)) ?>...</small>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                                <input type="hidden" name="id" value="<?= $recurso['id'] ?>">
                                                <input type="hidden" name="action" value="cambiar_estado">
                                                <select name="estado" class="form-select form-select-sm" onchange="this.form.submit()">
                                                    <option value="1" <?= $recurso['estado'] == 1 ? 'selected' : '' ?>>Activo</option>
                                                    <option value="0" <?= $recurso['estado'] == 0 ? 'selected' : '' ?>>Inactivo</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td>
                                            <small><?= date('d/m/Y', strtotime($recurso['fecha_creacion'])) ?></small>
                                        </td>
                                        <td>
                                            <a href="?editar=<?= $recurso['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar este recurso?')">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                                <input type="hidden" name="id" value="<?= $recurso['id'] ?>">
                                                <input type="hidden" name="action" value="eliminar">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>
