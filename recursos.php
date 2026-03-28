<?php
session_start();
require 'config/db.php';
require_once 'config/pyme_recursos.php';

pyme_recursos_ensure_schema($pdo);

$page_title = 'Recursos para tu Negocio - Directorio de Empresas';
$page_description = 'Descubre programas de apoyo y recursos para fortalecer y desarrollar tu empresa.';
$page_keywords = 'recursos PYMES, programas de apoyo, financiamiento, capacitación, emprendimiento';

include 'templates/header.php';

$recursos = pyme_recursos_obtener_todos($pdo);
?>

<div class="container py-5">
    <div class="mb-5">
        <h1 class="display-4 fw-bold">Recursos para tu Negocio</h1>
        <p class="lead text-muted">Programas de apoyo para fortalecer y desarrollar tu empresa</p>
    </div>

    <?php if (empty($recursos)): ?>
        <div class="alert alert-info text-center py-5">
            <h5>No hay recursos disponibles en este momento</h5>
            <p class="text-muted">Vuelve pronto para conocer nuestros programas de apoyo</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($recursos as $recurso): ?>
                <div class="col-lg-6">
                    <div class="card h-100 shadow-sm border-0 recurso-card">
                        <div class="card-body">
                            <h3 class="card-title text-primary mb-3"><?= htmlspecialchars($recurso['titulo']) ?></h3>
                            
                            <p class="card-text text-muted mb-3"><?= htmlspecialchars($recurso['descripcion']) ?></p>

                            <?php if (!empty($recurso['dirigido_a'])): ?>
                                <div class="mb-3">
                                    <strong class="text-dark">Dirigido a:</strong>
                                    <p class="mb-0 text-muted"><?= htmlspecialchars($recurso['dirigido_a']) ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($recurso['beneficios'])): ?>
                                <div class="mb-3">
                                    <strong class="text-dark">Beneficios:</strong>
                                    <ul class="list-unstyled ms-2 text-muted">
                                        <?php
                                        $beneficios_array = array_filter(
                                            array_map('trim', explode("\n", $recurso['beneficios'])),
                                            fn($item) => !empty($item)
                                        );
                                        foreach ($beneficios_array as $beneficio): 
                                        ?>
                                            <li class="mb-1">• <?= htmlspecialchars($beneficio) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($recurso['enlace_url'])): ?>
                                <div class="mt-4">
                                    <a href="<?= htmlspecialchars($recurso['enlace_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm">
                                        Ver programa →
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-transparent border-top-0">
                            <small class="text-muted">Publicado: <?= date('d/m/Y', strtotime($recurso['fecha_creacion'])) ?></small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.recurso-card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.recurso-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}
</style>

<?php include 'templates/footer.php'; ?>
