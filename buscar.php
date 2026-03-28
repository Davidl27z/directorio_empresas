<?php
session_start();
require 'config/db.php';
require_once 'config/usuario_interacciones.php';
require_once 'config/ui_icons.php';

usuario_interacciones_ensure_schema($pdo);

$busqueda = trim($_GET['q'] ?? '');

$page_title = 'Buscar - Directorio de Empresas';
$page_description = 'Busca empresas y productos dentro del directorio.';
include 'templates/header.php';

$empresas = [];
$productos = [];
$productCounts = [];
$productFlags = [];

if ($busqueda !== '') {
    $likeSearch = '%' . $busqueda . '%';

    $sql = "SELECT e.*, c.nombre as categoria 
            FROM empresas e 
            LEFT JOIN categorias c ON e.categoria_id = c.id 
            WHERE (e.nombre LIKE ? OR e.descripcion LIKE ? OR c.nombre LIKE ?) AND e.aprobada = 1
            ORDER BY e.nombre";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$likeSearch, $likeSearch, $likeSearch]);
    $empresas = $stmt->fetchAll();

    $sqlProductos = "
        SELECT p.*, e.nombre AS empresa_nombre, e.id AS empresa_id, e.logo AS empresa_logo, c.nombre AS categoria_nombre
        FROM productos p
        INNER JOIN empresas e ON e.id = p.empresa_id
        LEFT JOIN categorias c ON c.id = e.categoria_id
        WHERE p.disponible = TRUE AND e.aprobada = 1
          AND (
            p.nombre LIKE ? OR
            p.descripcion LIKE ? OR
            e.nombre LIKE ? OR
            c.nombre LIKE ?
          )
        ORDER BY p.nombre
    ";
    $stmt = $pdo->prepare($sqlProductos);
    $stmt->execute([$likeSearch, $likeSearch, $likeSearch, $likeSearch]);
    $productos = $stmt->fetchAll();

    $productoIds = array_values(array_filter(array_map(static function ($producto) {
        return isset($producto['id']) ? (int) $producto['id'] : 0;
    }, $productos)));

    if (!empty($productoIds)) {
        $productCounts = producto_counts_by_ids($pdo, $productoIds);
        if (isset($_SESSION['user_id'], $_SESSION['user_role']) && $_SESSION['user_role'] === 'cliente') {
            $productFlags = producto_user_flags_by_ids($pdo, (int) $_SESSION['user_id'], $productoIds);
        }
    }
}
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="mb-1">Resultados de búsqueda</h2>
        <p class="text-muted mb-0">Consulta: "<?= htmlspecialchars($busqueda) ?>"</p>
    </div>
    <form action="buscar.php" method="GET" class="d-flex gap-2 w-100" style="max-width: 520px;">
        <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Busca empresas, productos o categorías">
        <button type="submit" class="btn btn-primary">Buscar</button>
    </form>
</div>

<?php if ($busqueda === ''): ?>
    <div class="alert alert-info">Escribe un término para buscar empresas y productos.</div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h3 class="text-primary mb-1"><?= count($empresas) ?></h3>
                    <p class="text-muted mb-0">Empresas encontradas</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h3 class="text-success mb-1"><?= count($productos) ?></h3>
                    <p class="text-muted mb-0">Productos encontrados</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Empresas</h5>
        </div>
        <div class="card-body">
            <?php if(count($empresas) > 0): ?>
                <div class="row">
                    <?php foreach($empresas as $emp): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($emp['nombre']) ?></h5>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($emp['categoria']) ?></span>
                                    <p class="card-text mt-2"><?= htmlspecialchars(substr((string) $emp['descripcion'], 0, 100)) ?>...</p>
                                    <a href="<?= $base_url ?>/empresa.php?id=<?= $emp['id'] ?>" class="btn btn-sm btn-primary">Ver detalles</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mb-0">No se encontraron empresas con ese término.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">Productos</h5>
        </div>
        <div class="card-body">
            <?php if(count($productos) > 0): ?>
                <div class="row">
                    <?php foreach($productos as $producto): ?>
                        <?php
                        $productId = (int) $producto['id'];
                        $stats = $productCounts[$productId] ?? ['likes' => 0, 'guardados' => 0, 'resenas' => 0, 'promedio' => 0.0];
                        $flags = $productFlags[$productId] ?? ['liked' => false, 'saved' => false];
                        $esCliente = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'cliente';
                        ?>
                        <div class="col-md-6 col-xl-4 mb-4">
                            <div class="card h-100">
                                <img src="<?= $base_url ?>/uploads/productos/<?= $producto['imagen'] ?: 'default.png' ?>" class="card-img-top" alt="<?= htmlspecialchars($producto['nombre']) ?>" style="height: 190px; object-fit: cover;">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title mb-1"><?= htmlspecialchars($producto['nombre']) ?></h5>
                                    <p class="small text-muted mb-2">
                                        En <a href="<?= $base_url ?>/empresa.php?id=<?= (int) $producto['empresa_id'] ?>"><?= htmlspecialchars($producto['empresa_nombre']) ?></a>
                                    </p>
                                    <p class="text-success fw-bold mb-2">$ <?= number_format($producto['precio'], 0, ',', '.') ?></p>
                                    <p class="card-text small"><?= htmlspecialchars(substr((string) $producto['descripcion'], 0, 120)) ?></p>
                                    <p class="small text-muted mt-auto mb-2 product-meta-stats">
                                        <span><?= ui_icon('like', 'stat-icon') ?> <span id="likes-count-<?= $productId ?>"><?= (int) $stats['likes'] ?></span></span>
                                        <span><?= ui_icon('save', 'stat-icon') ?> <span id="saves-count-<?= $productId ?>"><?= (int) $stats['guardados'] ?></span></span>
                                        <span><?= ui_icon('star', 'stat-icon') ?> <?= number_format((float) $stats['promedio'], 1) ?> (<?= (int) $stats['resenas'] ?>)</span>
                                    </p>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="<?= $base_url ?>/empresa.php?id=<?= (int) $producto['empresa_id'] ?>" class="btn btn-sm btn-outline-primary">Ver empresa</a>
                                        <button class="btn btn-sm btn-primary" onclick="agregarAlCarrito(<?= $productId ?>, '<?= htmlspecialchars($producto['nombre']) ?>', <?= $producto['precio'] ?>, this)">Agregar</button>
                                    </div>
                                    <?php if ($esCliente): ?>
                                        <div class="d-flex gap-2 mt-2">
                                            <button
                                                type="button"
                                                class="btn btn-sm <?= $flags['liked'] ? 'btn-danger' : 'btn-outline-danger' ?>"
                                                data-toggle-product-interaction
                                                data-action="toggle_like"
                                                data-product-id="<?= $productId ?>"
                                            ><?= $flags['liked'] ? 'Quitar me gusta' : 'Me gusta' ?></button>
                                            <button
                                                type="button"
                                                class="btn btn-sm <?= $flags['saved'] ? 'btn-warning' : 'btn-outline-warning' ?>"
                                                data-toggle-product-interaction
                                                data-action="toggle_save"
                                                data-product-id="<?= $productId ?>"
                                            ><?= $flags['saved'] ? 'Guardado' : 'Guardar' ?></button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mb-0">No se encontraron productos con ese término.</div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include 'templates/footer.php'; ?>