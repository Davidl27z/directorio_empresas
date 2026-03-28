<?php
session_start();
require 'config/db.php';
include 'templates/header.php';

$id = $_GET['id'] ?? 0;

// Obtener categoría
$stmt = $pdo->prepare("SELECT * FROM categorias WHERE id = ?");
$stmt->execute([$id]);
$categoria = $stmt->fetch();

if (!$categoria) {
    die("Categoría no encontrada");
}

// SEO Variables
$page_title = htmlspecialchars($categoria['nombre']) . ' - Directorio de Empresas';
$page_description = 'Explora empresas en la categoría ' . htmlspecialchars($categoria['nombre']) . '. Encuentra servicios locales en nuestro directorio.';
$page_keywords = htmlspecialchars($categoria['nombre']) . ', empresas, directorio, servicios';

// Obtener empresas de esa categoría (solo aprobadas)
$stmt = $pdo->prepare("SELECT * FROM empresas WHERE categoria_id = ? AND aprobada = 1 ORDER BY nombre");
$stmt->execute([$id]);
$empresas = $stmt->fetchAll();
?>

<h1 class="my-4"><?= htmlspecialchars($categoria['nombre']) ?></h1>
<p>Empresas encontradas: <?= count($empresas) ?></p>

<?php if(count($empresas) > 0): ?>
    <div class="row">
        <?php if (!isset($base_url)) $base_url = ''; ?>
        <?php foreach($empresas as $emp): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <?php if($emp['logo']): ?>
                        <?php if (!isset($base_url)) $base_url = ''; ?>
                        <img src="<?= $base_url ?>/uploads/logos/<?= $emp['logo'] ?>" class="card-img-top p-3" style="max-height: 150px; object-fit: contain;" alt="<?= $emp['nombre'] ?>">
                    <?php endif; ?>
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($emp['nombre']) ?></h5>
                    <p class="card-text text-muted"><?= htmlspecialchars(substr($emp['descripcion'], 0, 80)) ?>...</p>
                        <?php if($emp['telefono']): ?>
                            <p class="mb-1"><small>📞 <?= $emp['telefono'] ?></small></p>
                        <?php endif; ?>
                        <?php if($emp['direccion']): ?>
                            <p class="mb-1"><small>📍 <?= $emp['direccion'] ?></small></p>
                        <?php endif; ?>
                        <a href="<?= $base_url ?>/empresa.php?id=<?= $emp['id'] ?>" class="btn btn-primary btn-sm mt-2">Ver Detalles</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="alert alert-info">No hay empresas en esta categoría todavía.</div>
<?php endif; ?>

<a href="<?= $base_url ?>/index.php" class="btn btn-secondary">← Volver al inicio</a>

<?php include 'templates/footer.php'; ?>