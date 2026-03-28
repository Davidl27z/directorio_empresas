<?php
session_start();
require '../config/db.php';

// Verificar que sea admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    die("Acceso denegado");
}

$require_chartjs = true;
include '../templates/header.php';
?>

<h1 style="display: flex; align-items: center; gap: 12px;"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg> Panel de Administrador</h1>

<div class="row mt-4">
    <?php include 'sidebar.php'; ?>
    <div class="col-lg-9">
        <div class="row">
            <div class="col-md-4">
                <div class="card mb-3 h-100">
                    <div class="card-body d-flex flex-column justify-content-between">
                        <div>
                            <h5 class="card-title">Empresas</h5>
                            <?php
                            $count = $pdo->query("SELECT COUNT(*) FROM empresas")->fetchColumn();
                            echo "<h2>$count</h2>";
                            ?>
                        </div>
                        <?php if (!isset($base_url)) $base_url = '..'; ?>
                        <a href="<?= $base_url ?>/admin/empresas.php" class="btn btn-secondary btn-sm mt-3">Gestionar</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card mb-3 h-100">
                    <div class="card-body d-flex flex-column justify-content-between">
                        <div>
                            <h5 class="card-title">Usuarios</h5>
                            <?php
                            $count = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
                            echo "<h2>$count</h2>";
                            ?>
                        </div>
                        <a href="<?= $base_url ?>/admin/usuarios.php" class="btn btn-secondary btn-sm mt-3">Gestionar</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card mb-3 h-100">
                    <div class="card-body d-flex flex-column justify-content-between">
                        <div>
                            <h5 class="card-title">Categorías</h5>
                            <?php
                            $count = $pdo->query("SELECT COUNT(*) FROM categorias")->fetchColumn();
                            echo "<h2>$count</h2>";
                            ?>
                        </div>
                        <a href="<?= $base_url ?>/admin/categorias.php" class="btn btn-secondary btn-sm mt-3">Gestionar</a>
                    </div>
                </div>
            </div>
        </div>

        <?php
        // Estadísticas adicionales: empresas por categoría y por estado
        $totalEmpresas = $pdo->query("SELECT COUNT(*) FROM empresas")->fetchColumn();
        $aprobadasCount = $pdo->query("SELECT COUNT(*) FROM empresas WHERE aprobada = 1")->fetchColumn();
        $pendientesCount = $pdo->query("SELECT COUNT(*) FROM empresas WHERE aprobada = 0")->fetchColumn();

// Obtener por categoría: total, aprobadas y pendientes
$byCategory = $pdo->query("SELECT c.id, c.nombre, COUNT(e.id) AS total, SUM(e.aprobada = 1) AS aprobadas, SUM(e.aprobada = 0) AS pendientes FROM categorias c LEFT JOIN empresas e ON e.categoria_id = c.id GROUP BY c.id ORDER BY total DESC")->fetchAll();
?>
<?php
// Helper: generar un avatar SVG como data URI para categorías (sin subir archivos)
function svg_data_uri($name, $id = 0) {
    $initial = strtoupper(mb_substr(trim($name ?: 'S'), 0, 1));
    $colors = ['#6f42c1','#0d6efd','#20c997','#fd7e14','#e83e8c','#0dcaf0','#198754'];
    $color = $colors[$id % count($colors)];
    $bg = $color;
    $fg = '#ffffff';
    $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='120' height='120'>".
           "<rect width='100%' height='100%' fill='".$bg."'/>".
           "<text x='50%' y='50%' dy='.35em' font-family='Arial,Helvetica,sans-serif' font-size='54' fill='".$fg."' text-anchor='middle'>".htmlspecialchars($initial)."</text>".
           "</svg>";
    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

?>

<!-- Sección de estadísticas -->
<div class="row mt-4">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5>Empresas por Categoría</h5>
            </div>
            <div class="card-body">
                <hr />
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Categoría</th>
                            <th class="text-end">Empresas</th>
                            <th class="text-end">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($byCategory as $cat): ?>
                        <?php $percent = $totalEmpresas ? round(($cat['total'] / $totalEmpresas) * 100, 1) : 0; ?>
                        <tr>
                            <td>
                                <div class="category-row">
                                    <img src="<?= svg_data_uri($cat['nombre'], (int)$cat['id']) ?>" alt="" class="category-img" />
                                    <span class="category-name"><?= htmlspecialchars($cat['nombre'] ?: 'Sin categoría') ?></span>
                                </div>
                            </td>
                            <td class="text-end"><?= $cat['total'] ?></td>
                            <td class="text-end"><?= $percent ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5>Resumen Rápido</h5>
            </div>
            <div class="card-body text-center">
                <div style="max-width:100%;height:200px;max-height:200px;">
                    <canvas id="categoriesChart" width="400" height="200" style="width:100%;height:200px;display:block;" aria-label="Empresas por categoría"></canvas>
                </div>
                <hr />
                <p><strong>Total de empresas:</strong> <?= $totalEmpresas ?></p>
                <p><strong>Aprobadas:</strong> <?= $aprobadasCount ?></p>
                <p><strong>Pendientes:</strong> <?= $pendientesCount ?></p>
                <a href="<?= $base_url ?>/admin/empresas.php" class="btn btn-primary btn-sm">Ver todas las empresas</a>
            </div>
        </div>
    </div>
</div>

    </div>
</div>

<?php
// Preparar arrays para JS
$labels = array_map(function($c){ return $c['nombre'] ?: 'Sin categoría'; }, $byCategory ?: []);
$totals = array_map(function($c){ return (int)$c['total']; }, $byCategory ?: []);
$aprobadas = array_map(function($c){ return (int)$c['aprobadas']; }, $byCategory ?: []);
$pendientes = array_map(function($c){ return (int)$c['pendientes']; }, $byCategory ?: []);
$percents = array_map(function($c) use ($totalEmpresas){ return $totalEmpresas ? round(($c['total'] / $totalEmpresas) * 100,1) : 0; }, $byCategory ?: []);
?>

<script>
// Data for chart initialized by main.js helper
const _labels = <?= json_encode($labels) ?>;
const _aprobadas = <?= json_encode($aprobadas) ?>;
const _pendientes = <?= json_encode($pendientes) ?>;

document.addEventListener('DOMContentLoaded', function(){
    console.debug('[dashboard] DOMContentLoaded — attempting chart init');
    function startChartWhenReady(attemptsLeft) {
        attemptsLeft = typeof attemptsLeft === 'number' ? attemptsLeft : 20;
        if (typeof initStackedBarChart === 'function') {
            initStackedBarChart({
                canvasId: 'categoriesChart',
                labels: _labels,
                datasets: [
                    { label: 'Aprobadas', data: _aprobadas, backgroundColor: 'rgba(30, 64, 175, 0.85)' },
                    { label: 'Pendientes', data: _pendientes, backgroundColor: 'rgba(101, 77, 49, 0.85)' }
                ],
                height: 220
            });
            return;
        }
        if (attemptsLeft <= 0) return;
        setTimeout(function(){ startChartWhenReady(attemptsLeft - 1); }, 150);
    }
    startChartWhenReady();
});
</script>

<?php include '../templates/footer.php'; ?>