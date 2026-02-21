<?php
session_start();
require 'config/db.php';
include 'templates/header.php';

$busqueda = $_GET['q'] ?? '';

$sql = "SELECT e.*, c.nombre as categoria 
        FROM empresas e 
        LEFT JOIN categorias c ON e.categoria_id = c.id 
        WHERE e.nombre LIKE ? OR e.descripcion LIKE ?";
        
$stmt = $pdo->prepare($sql);
$stmt->execute(["%$busqueda%", "%$busqueda%"]);
$empresas = $stmt->fetchAll();
?>

<h2>Resultados para: "<?= htmlspecialchars($busqueda) ?>"</h2>

<?php if(count($empresas) > 0): ?>
    <div class="row">
        <?php foreach($empresas as $emp): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title"><?= $emp['nombre'] ?></h5>
                        <span class="badge bg-secondary"><?= $emp['categoria'] ?></span>
                        <p class="card-text mt-2"><?= substr($emp['descripcion'], 0, 100) ?>...</p>
                        <a href="empresa.php?id=<?= $emp['id'] ?>" class="btn btn-sm btn-primary">Ver详情</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="alert alert-warning">No se encontraron empresas.</div>
<?php endif; ?>

<?php include 'templates/footer.php'; ?>