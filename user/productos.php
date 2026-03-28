<?php
session_start();
require '../config/db.php';
include '../templates/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Obtener empresas del usuario
$stmt = $pdo->prepare("SELECT * FROM empresas WHERE usuario_id = ? AND aprobada = TRUE");
$stmt->execute([$_SESSION['user_id']]);
$empresas = $stmt->fetchAll();

$empresa_id = $_GET['empresa_id'] ?? ($empresas[0]['id'] ?? null);

if (!$empresa_id) {
    die("No tienes empresas aprobadas para gestionar productos.");
}

// Obtener productos de la empresa
$stmt = $pdo->prepare("SELECT * FROM productos WHERE empresa_id = ? ORDER BY fecha_creacion DESC");
$stmt->execute([$empresa_id]);
$productos = $stmt->fetchAll();
?>

<div class="container mt-4">
    <h2>Gestión de Productos</h2>

    <!-- Selector de empresa -->
    <form method="GET" class="mb-4">
        <label>Seleccionar Empresa:</label>
        <select name="empresa_id" onchange="this.form.submit()" class="form-select">
            <?php foreach ($empresas as $emp): ?>
                <option value="<?= $emp['id'] ?>" <?= $emp['id'] == $empresa_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($emp['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <!-- Botón agregar producto -->
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#modalProducto">Agregar Producto</button>

    <!-- Lista de productos -->
    <div class="row">
        <?php foreach ($productos as $prod): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <img src="../uploads/productos/<?= $prod['imagen'] ?: 'default.png' ?>" class="card-img-top" alt="<?= htmlspecialchars($prod['nombre']) ?>" style="height: 200px; object-fit: cover;">
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($prod['nombre']) ?></h5>
                        <p class="card-text text-truncate"><?= htmlspecialchars($prod['descripcion']) ?></p>
                        <p class="text-success fw-bold">$<?= number_format($prod['precio'], 2) ?></p>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-warning" onclick="editarProducto(<?= $prod['id'] ?>)">Editar</button>
                            <button class="btn btn-sm btn-danger" onclick="eliminarProducto(<?= $prod['id'] ?>)">Eliminar</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal para agregar/editar producto -->
<div class="modal fade" id="modalProducto" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formProducto" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="empresa_id" value="<?= $empresa_id ?>">
                    <input type="hidden" name="producto_id" id="producto_id">
                    <div class="mb-3">
                        <label>Nombre:</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Descripción:</label>
                        <textarea name="descripcion" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label>Precio:</label>
                        <input type="number" name="precio" step="0.01" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Imagen:</label>
                        <input type="file" name="imagen" class="form-control" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editarProducto(id) {
    // Cargar datos del producto y mostrar modal
    fetch(`api_productos.php?action=get&id=${id}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('producto_id').value = data.id;
            document.querySelector('[name=nombre]').value = data.nombre;
            document.querySelector('[name=descripcion]').value = data.descripcion;
            document.querySelector('[name=precio]').value = data.precio;
            new bootstrap.Modal(document.getElementById('modalProducto')).show();
        });
}

function eliminarProducto(id) {
    if (confirm('¿Eliminar producto?')) {
        fetch('api_productos.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=delete&id=${id}`
        }).then(() => location.reload());
    }
}

document.getElementById('formProducto').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'save');

    fetch('api_productos.php', {
        method: 'POST',
        body: formData
    }).then(() => location.reload());
});
</script>

<?php include '../templates/footer.php'; ?>