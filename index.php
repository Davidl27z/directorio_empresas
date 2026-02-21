<?php
session_start();
require 'config/db.php';
include 'templates/header.php';
?>

<!-- Buscador Principal -->
<div class="p-5 mb-4 bg-light rounded-3 text-center">
    <h1 class="display-5 fw-bold">Encuentra la mejor empresa</h1>
    <p class="col-md-8 fs-4 mx-auto">Explora nuestro directorio de empresas locales</p>
    <form action="buscar.php" method="GET" class="d-flex gap-2 justify-content-center">
        <input type="text" name="q" class="form-control w-50" placeholder="¿Qué buscas? ej: Restaurant, Taller...">
        <button type="submit" class="btn btn-primary btn-lg">Buscar</button>
    </form>
</div>

<!-- Vitrina de Categorías -->
<h2 class="mb-4">Explorar por Categoría</h2>
<div class="row">
    <?php
    $stmt = $pdo->query("SELECT * FROM categorias ORDER BY nombre");
    while ($cat = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
        <div class="col-md-3 col-sm-6 mb-4">
            <a href="categoria.php?id=<?= $cat['id'] ?>" class="text-decoration-none">
                <div class="card h-100 shadow-sm category-card">
                    <div class="card-body text-center">
                        <!-- Imagen o icono de categoría -->
                        <img src="uploads/categorias/<?= $cat['imagen'] ?? 'default.png' ?>" 
                             alt="<?= $cat['nombre'] ?>" 
                             class="img-fluid mb-2 category-img">
                        <h5 class="card-title text-dark"><?= $cat['nombre'] ?></h5>
                    </div>
                </div>
            </a>
        </div>
    <?php endwhile; ?>
</div>

<?php include 'templates/footer.php'; ?>