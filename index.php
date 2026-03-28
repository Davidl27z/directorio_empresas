<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
require 'config/db.php';
require_once 'config/usuario_interacciones.php';
require_once 'config/ui_icons.php';

usuario_interacciones_ensure_schema($pdo);

// SEO Variables
$page_title = 'Directorio de Empresas - Encuentra la mejor empresa';
$page_description = 'Explora empresas y descubre productos destacados en nuestra vitrina digital.';
$page_keywords = 'directorio empresas, vitrina digital, productos destacados, servicios locales';

$sort = $_GET['sort'] ?? 'relevantes';
$allowedSorts = ['relevantes', 'likes', 'resenas'];
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'relevantes';
}

$sortLabels = [
    'relevantes' => 'Relevantes',
    'likes' => 'Mas likes',
    'resenas' => 'Mas reseñas',
];

$orderBy = "((COALESCE(l.like_count, 0) * 3) + (COALESCE(r.review_count, 0) * 4) + (COALESCE(r.avg_rating, 0) * 2)) DESC, p.id DESC";
if ($sort === 'likes') {
    $orderBy = "COALESCE(l.like_count, 0) DESC, COALESCE(r.review_count, 0) DESC, p.id DESC";
} elseif ($sort === 'resenas') {
    $orderBy = "COALESCE(r.review_count, 0) DESC, COALESCE(r.avg_rating, 0) DESC, COALESCE(l.like_count, 0) DESC, p.id DESC";
}

$sqlProductosDestacados = "
    SELECT p.*, e.nombre AS empresa_nombre, e.id AS empresa_id, e.logo AS empresa_logo,
           c.nombre AS categoria_nombre,
           COALESCE(l.like_count, 0) AS likes,
           COALESCE(g.saved_count, 0) AS guardados,
           COALESCE(r.review_count, 0) AS resenas,
           COALESCE(r.avg_rating, 0) AS promedio
    FROM productos p
    INNER JOIN empresas e ON e.id = p.empresa_id
    LEFT JOIN categorias c ON c.id = e.categoria_id
    LEFT JOIN (
        SELECT producto_id, COUNT(*) AS like_count
        FROM producto_likes
        GROUP BY producto_id
    ) l ON l.producto_id = p.id
    LEFT JOIN (
        SELECT producto_id, COUNT(*) AS saved_count
        FROM producto_guardados
        GROUP BY producto_id
    ) g ON g.producto_id = p.id
    LEFT JOIN (
        SELECT producto_id, COUNT(*) AS review_count, AVG(calificacion) AS avg_rating
        FROM producto_resenas
        GROUP BY producto_id
    ) r ON r.producto_id = p.id
    WHERE p.disponible = TRUE AND e.aprobada = 1
    ORDER BY {$orderBy}
    LIMIT 12
";

$stmtProductosDestacados = $pdo->query($sqlProductosDestacados);
$productosDestacados = $stmtProductosDestacados->fetchAll(PDO::FETCH_ASSOC);

$productoIds = array_values(array_filter(array_map(static function ($producto) {
    return isset($producto['id']) ? (int) $producto['id'] : 0;
}, $productosDestacados)));

$productFlags = [];
if (!empty($productoIds) && isset($_SESSION['user_id'], $_SESSION['user_role']) && $_SESSION['user_role'] === 'cliente') {
    $productFlags = producto_user_flags_by_ids($pdo, (int) $_SESSION['user_id'], $productoIds);
}

include 'templates/header.php';
?>
<?php
// Base path for assets/links (asegura que funcione desde cualquier carpeta)
$basePath = isset($base_url) && $base_url !== '' ? rtrim($base_url, '/') : '.';
?>

<!-- Buscador Principal -->
<div class="p-5 mb-4 bg-light rounded-3 text-center">
    <h1 class="display-5 fw-bold">Encuentra la mejor empresa</h1>
    <p class="col-md-8 fs-4 mx-auto">Explora nuestro directorio de empresas locales y descubre productos destacados</p>
    <form action="buscar.php" method="GET" class="d-flex gap-2 justify-content-center">
        <input type="text" name="q" class="form-control w-50" placeholder="&iquest;Qu&eacute; buscas? ej: Restaurant, Taller, Hamburguesa...">
        <button type="submit" class="btn btn-primary btn-lg">Buscar</button>
    </form>
</div>

<!-- Vitrina de Categor&iacute;as -->
<h2 class="section-title">Explorar por Categor&iacute;a</h2>
<div class="category-carousel-container">
    <button class="carousel-nav prev" type="button" aria-label="Anterior categor&iacute;a">‹</button>
    <div class="category-carousel" id="categoryCarousel">
        <?php
        $stmt = $pdo->query("SELECT * FROM categorias ORDER BY nombre");
        $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($categorias) === 0): ?>
            <div class="alert alert-warning w-100 text-center mb-0">Aún no hay categorías disponibles.</div>
        <?php else:
            foreach ($categorias as $cat): ?>
                <div class="category-carousel-item">
                    <a href="<?= $basePath ?>/categoria.php?id=<?= $cat['id'] ?>" class="text-decoration-none" aria-label="Ver <?= htmlspecialchars($cat['nombre'], ENT_QUOTES) ?>">
                        <div class="card h-100 category-card">
                            <div class="card-body text-center">
                                <img src="<?= $basePath ?>/uploads/categorias/<?= $cat['imagen'] ?: 'default.png' ?>" 
                                     alt="<?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?>" 
                                     class="img-fluid mb-2 category-img"
                                     onerror="this.src='<?= $basePath ?>/uploads/categorias/default.png'">
                                <h5 class="card-title text-dark"><?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?></h5>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <button class="carousel-nav next" type="button" aria-label="Siguiente categoría">›</button>
</div>

<section class="product-showcase-section mb-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
        <div>
            <h2 class="section-title mt-0 mb-1">Vitrina Digital</h2>
            <p class="text-muted mb-0">Productos publicados por las empresas del directorio.</p>
        </div>
        <div class="product-filter-pills">
            <?php foreach ($sortLabels as $sortKey => $sortLabel): ?>
                <a href="<?= $basePath ?>/index.php?sort=<?= urlencode($sortKey) ?>" class="product-filter-pill <?= $sort === $sortKey ? 'active' : '' ?>"><?= htmlspecialchars($sortLabel) ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!empty($productosDestacados)): ?>
        <div class="row g-4">
            <?php foreach ($productosDestacados as $producto): ?>
                <?php
                $productId = (int) $producto['id'];
                $flags = $productFlags[$productId] ?? ['liked' => false, 'saved' => false];
                $esCliente = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'cliente';
                ?>
                <div class="col-md-6 col-xl-4">
                    <article class="card product-showcase-card h-100 overflow-hidden">
                        <div class="product-showcase-image-wrap">
                            <img src="<?= $basePath ?>/uploads/productos/<?= $producto['imagen'] ?: 'default.png' ?>"
                                 alt="<?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                                 class="product-showcase-image"
                                 onerror="this.src='<?= $basePath ?>/uploads/productos/default.png'">
                            <span class="badge rounded-pill text-bg-light product-showcase-category"><?= htmlspecialchars($producto['categoria_nombre'] ?: 'General', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                <div>
                                    <h5 class="card-title mb-1"><?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?></h5>
                                    <p class="small text-muted mb-0">Por <a href="<?= $basePath ?>/empresa.php?id=<?= (int) $producto['empresa_id'] ?>"><?= htmlspecialchars($producto['empresa_nombre'], ENT_QUOTES, 'UTF-8') ?></a></p>
                                </div>
                                <strong class="text-success">$ <?= number_format($producto['precio'], 0, ',', '.') ?></strong>
                            </div>

                            <p class="card-text small product-showcase-description"><?= htmlspecialchars(substr((string) $producto['descripcion'], 0, 150), ENT_QUOTES, 'UTF-8') ?></p>

                            <div class="product-showcase-stats mb-3">
                                <span><?= ui_icon('like', 'stat-icon') ?> <strong id="likes-count-<?= $productId ?>"><?= (int) $producto['likes'] ?></strong></span>
                                <span><?= ui_icon('save', 'stat-icon') ?> <strong id="saves-count-<?= $productId ?>"><?= (int) $producto['guardados'] ?></strong></span>
                                <span><?= ui_icon('star', 'stat-icon') ?> <strong><?= number_format((float) $producto['promedio'], 1) ?></strong> (<?= (int) $producto['resenas'] ?>)</span>
                            </div>

                            <div class="mt-auto d-flex flex-wrap gap-2">
                                <a href="<?= $basePath ?>/empresa.php?id=<?= (int) $producto['empresa_id'] ?>" class="btn btn-sm btn-outline-secondary">Ver empresa</a>
                                <button class="btn btn-sm btn-primary" onclick="agregarAlCarrito(<?= $productId ?>, '<?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?>', <?= $producto['precio'] ?>, this)">Agregar</button>
                            </div>

                            <?php if ($esCliente): ?>
                                <div class="d-flex gap-2 mt-3">
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
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">Todavia no hay productos publicados para mostrar en la vitrina.</div>
    <?php endif; ?>
</section>

<?php include 'templates/footer.php'; ?>