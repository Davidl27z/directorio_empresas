<?php
session_start();
require 'config/db.php';

if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'empresa') {
    header('Location: user/dashboard.php');
    exit;
}

include 'templates/header.php';

// Inicializar carrito si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Actualizar cantidad de producto
if (isset($_POST['actualizar'])) {
    $producto_id = (int)$_POST['producto_id'];
    $cantidad = max(1, (int)$_POST['cantidad']);

    // Buscar el producto en el carrito y actualizar cantidad
    foreach ($_SESSION['carrito'] as $empresa_id => &$productos) {
        foreach ($productos as &$item) {
            if ($item['id'] == $producto_id) {
                $item['cantidad'] = $cantidad;
                break 2;
            }
        }
    }
    header("Location: carrito.php");
    exit;
}

// Eliminar producto del carrito
if (isset($_GET['eliminar'])) {
    $producto_id = (int)$_GET['eliminar'];

    // Buscar y eliminar el producto
    foreach ($_SESSION['carrito'] as $empresa_id => &$productos) {
        foreach ($productos as $key => $item) {
            if ($item['id'] == $producto_id) {
                unset($productos[$key]);
                // Si la empresa no tiene más productos, eliminar la empresa del carrito
                if (empty($productos)) {
                    unset($_SESSION['carrito'][$empresa_id]);
                }
                break 2;
            }
        }
    }
    header("Location: carrito.php");
    exit;
}

// Calcular totales por empresa
$totales_por_empresa = [];
foreach ($_SESSION['carrito'] as $empresa_id => $productos) {
    // Obtener nombre de la empresa
    $stmt = $pdo->prepare("SELECT nombre FROM empresas WHERE id = ?");
    $stmt->execute([$empresa_id]);
    $empresa = $stmt->fetch();

    if ($empresa) {
        $total = 0;
        foreach ($productos as $item) {
            $total += $item['precio'] * $item['cantidad'];
        }

        $totales_por_empresa[$empresa_id] = [
            'empresa_nombre' => $empresa['nombre'],
            'items' => $productos,
            'total' => $total
        ];
    }
}
?>
?>

<div class="container mt-4">
    <h2>🛒 Mi Carrito de Compras</h2>

    <?php if (empty($_SESSION['carrito'])): ?>
        <div class="alert alert-info">
            Tu carrito está vacío. <a href="index.php">Explorar empresas</a>
        </div>
    <?php else: ?>
        <?php foreach ($totales_por_empresa as $empresa_id => $empresa_data): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5>Productos de: <?= htmlspecialchars($empresa_data['empresa_nombre']) ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Precio</th>
                                    <th>Cantidad</th>
                                    <th>Subtotal</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($empresa_data['items'] as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="uploads/productos/<?= $item['imagen'] ?: 'default.png' ?>" alt="<?= htmlspecialchars($item['nombre']) ?>" style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px;">
                                                <?= htmlspecialchars($item['nombre']) ?>
                                            </div>
                                        </td>
                                        <td>$ <?= number_format($item['precio'], 0, ',', '.') ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="producto_id" value="<?= $item['id'] ?>">
                                                <input type="number" name="cantidad" value="<?= $item['cantidad'] ?>" min="1" style="width: 60px;" onchange="this.form.submit()">
                                                <input type="hidden" name="actualizar" value="1">
                                            </form>
                                        </td>
                                        <td>$ <?= number_format($item['precio'] * $item['cantidad'], 0, ',', '.') ?></td>
                                        <td>
                                            <a href="?eliminar=<?= $item['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar producto?')">Eliminar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3">Total para <?= htmlspecialchars($empresa_data['empresa_nombre']) ?>:</th>
                                    <th>$ <?= number_format($empresa_data['total'], 0, ',', '.') ?></th>
                                    <th>
                                        <a href="hacer_pedido.php?empresa_id=<?= $empresa_id ?>" class="btn btn-success">Hacer Pedido</a>
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include 'templates/footer.php'; ?>