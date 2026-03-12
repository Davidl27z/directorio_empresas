<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
require 'config/db.php';
include 'templates/header.php';
?>
<?php
// Base path for assets/links (asegura que funcione desde cualquier carpeta)
$basePath = isset($base_url) && $base_url !== '' ? rtrim($base_url, '/') : '.';
?>

<!-- Buscador Principal -->
<div class="p-5 mb-4 bg-light rounded-3 text-center">
    <h1 class="display-5 fw-bold">Encuentra la mejor empresa</h1>
    <p class="col-md-8 fs-4 mx-auto">Explora nuestro directorio de empresas locales</p>
    <form action="buscar.php" method="GET" class="d-flex gap-2 justify-content-center">
        <input type="text" name="q" class="form-control w-50" placeholder="&iquest;Qu&eacute; buscas? ej: Restaurant, Taller...">
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

<?php include 'templates/footer.php'; ?>