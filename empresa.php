<?php
session_start();
require 'config/db.php';
include 'templates/header.php';

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT e.*, c.nombre as categoria_nombre 
    FROM empresas e 
    LEFT JOIN categorias c ON e.categoria_id = c.id 
    WHERE e.id = ?
");
$stmt->execute([$id]);
$empresa = $stmt->fetch();

if (!$empresa) {
    die("Empresa no encontrada");
}
?>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-body text-center">
                <?php if($empresa['logo']): ?>
                    <img src="uploads/logos/<?= $empresa['logo'] ?>" class="img-fluid mb-3" style="max-height: 200px;" alt="<?= $empresa['nombre'] ?>">
                <?php else: ?>
                    <div class="bg-secondary text-white p-5 mb-3">Sin logo</div>
                <?php endif; ?>
                
                <h3><?= $empresa['nombre'] ?></h3>
                <span class="badge bg-primary"><?= $empresa['categoria_nombre'] ?></span>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4>Información de Contacto</h4>
            </div>
            <div class="card-body">
                <h5>Descripción</h5>
                <p><?= nl2br($empresa['descripcion']) ?></p>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>📍 Dirección:</strong><br> <?= $empresa['direccion'] ?? 'No disponible' ?></p>
                        <p><strong>📞 Teléfono:</strong><br> <?= $empresa['telefono'] ?? 'No disponible' ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>📧 Email:</strong><br> <?= $empresa['email'] ?? 'No disponible' ?></p>
                        <p><strong>🌐 Website:</strong><br> 
                            <?php if($empresa['website']): ?>
                                <a href="<?= $empresa['website'] ?>" target="_blank"><?= $empresa['website'] ?></a>
                            <?php else: ?>
                                No disponible
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <div class="mt-3">
                    <a href="categoria.php?id=<?= $empresa['categoria_id'] ?>" class="btn btn-outline-secondary">← Volver a <?= $empresa['categoria_nombre'] ?></a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>