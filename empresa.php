<?php
session_start();
require 'config/db.php';
require_once 'config/empresa_contacto.php';
require_once 'config/usuario_interacciones.php';
require_once 'config/ui_icons.php';
require_once 'config/empresa_metricas.php';
include 'templates/header.php';

empresa_ensure_contact_schema($pdo);
usuario_interacciones_ensure_schema($pdo);
empresa_metricas_ensure_schema($pdo);

$id = $_GET['id'] ?? 0;
$es_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

// Admin puede ver cualquier empresa, clientes solo las aprobadas
$sql = "
    SELECT e.*, c.nombre as categoria_nombre 
    FROM empresas e 
    LEFT JOIN categorias c ON e.categoria_id = c.id 
    WHERE e.id = ?";

if (!$es_admin) {
    $sql .= " AND e.aprobada = 1";
}

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$empresa = $stmt->fetch();

if (!$empresa) {
    die("Empresa no encontrada");
}

empresa_metricas_registrar_visita($pdo, (int) $empresa['id']);

// SEO Variables
$page_title = htmlspecialchars($empresa['nombre']) . ' - Directorio de Empresas';
$page_description = substr(htmlspecialchars($empresa['descripcion']), 0, 160);
$page_keywords = htmlspecialchars($empresa['categoria_nombre']) . ', ' . htmlspecialchars($empresa['nombre']) . ', servicios locales';
$og_image = $base_url . '/uploads/logos/' . $empresa['logo'];

// Obtener productos de la empresa
$stmt = $pdo->prepare("SELECT * FROM productos WHERE empresa_id = ? AND disponible = TRUE ORDER BY nombre");
$stmt->execute([$id]);
$productos = $stmt->fetchAll();

$productoIds = array_values(array_filter(array_map(static function ($producto) {
    return isset($producto['id']) ? (int) $producto['id'] : 0;
}, $productos)));

$usuarioActualId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$esCliente = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'cliente';

$productCounts = producto_counts_by_ids($pdo, $productoIds);
$productFlags = $esCliente ? producto_user_flags_by_ids($pdo, $usuarioActualId, $productoIds) : [];

$productosComprados = [];
if ($esCliente && !empty($productoIds)) {
    $placeholders = implode(',', array_fill(0, count($productoIds), '?'));
    $sqlComprados = "
        SELECT DISTINCT pi.producto_id
        FROM pedido_items pi
        INNER JOIN pedidos p ON p.id = pi.pedido_id
        WHERE p.usuario_id = ? AND p.estado = 'completado' AND pi.producto_id IN ($placeholders)
    ";
    $stmtComprados = $pdo->prepare($sqlComprados);
    $stmtComprados->execute(array_merge([$usuarioActualId], $productoIds));
    $productosComprados = array_fill_keys(array_map('intval', $stmtComprados->fetchAll(PDO::FETCH_COLUMN)), true);
}

$instagramUrl = empresa_instagram_url($empresa['instagram'] ?? '');
$tiktokUrl = empresa_tiktok_url($empresa['tiktok'] ?? '');
$facebookUrl = empresa_facebook_url($empresa['facebook'] ?? '');
$whatsappUrl = empresa_whatsapp_url($empresa['whatsapp'] ?? '', 'Hola, vi tu empresa en el directorio y quiero mas informacion.');
?>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-body text-center">
                <?php if($empresa['logo']): ?>
                    <?php if (!isset($base_url)) $base_url = ''; ?>
                    <img src="<?= $base_url ?>/uploads/logos/<?= $empresa['logo'] ?>" class="img-fluid mb-3" style="max-height: 200px;" alt="<?= $empresa['nombre'] ?>">
                <?php else: ?>
                    <div class="bg-secondary text-white p-5 mb-3">Sin logo</div>
                <?php endif; ?>
                
                <div class="d-flex align-items-center gap-2 mb-3">
                    <h3 class="mb-0"><?= htmlspecialchars($empresa['nombre']) ?></h3>
                    <?php if($es_admin && !$empresa['aprobada']): ?>
                        <span class="badge bg-warning text-dark">🔄 En Revisión</span>
                    <?php endif; ?>
                </div>
                <span class="badge bg-primary"><?= htmlspecialchars($empresa['categoria_nombre']) ?></span>
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
                <p><?= nl2br(htmlspecialchars($empresa['descripcion'])) ?></p>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>📍 Dirección:</strong><br> <?= htmlspecialchars($empresa['direccion'] ?? 'No disponible') ?></p>
                        <p><strong>📞 Teléfono:</strong><br> <?= htmlspecialchars($empresa['telefono'] ?? 'No disponible') ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>📧 Email:</strong><br> <?= htmlspecialchars($empresa['email'] ?? 'No disponible') ?></p>
                        <p><strong>🌐 Website:</strong><br> 
                            <?php if($empresa['website']): ?>
                                <a href="<?= htmlspecialchars($empresa['website']) ?>" target="_blank"><?= htmlspecialchars($empresa['website']) ?></a>
                            <?php else: ?>
                                No disponible
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <?php if ($instagramUrl || $tiktokUrl || $facebookUrl || $whatsappUrl): ?>
                    <hr>
                    <h5>Redes y contacto</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <?php if ($instagramUrl): ?>
                            <a href="<?= htmlspecialchars($instagramUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-danger d-inline-flex align-items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M16.98 0a6.9 6.9 0 0 1 5.08 1.98A6.94 6.94 0 0 1 24 7.02v9.96c0 2.08-.68 3.87-1.98 5.13A7.14 7.14 0 0 1 16.94 24H7.06a7.06 7.06 0 0 1-5.03-1.89A6.96 6.96 0 0 1 0 16.94V7.02C0 2.8 2.8 0 7.02 0h9.96zm.05 2.23H7.06c-1.45 0-2.7.43-3.53 1.25a4.82 4.82 0 0 0-1.3 3.54v9.92c0 1.5.43 2.7 1.3 3.58a5 5 0 0 0 3.53 1.25h9.88a5 5 0 0 0 3.53-1.25 4.73 4.73 0 0 0 1.4-3.54V7.02a5 5 0 0 0-1.3-3.49 4.82 4.82 0 0 0-3.54-1.3zM12 5.76c3.39 0 6.2 2.8 6.2 6.2a6.2 6.2 0 0 1-12.4 0 6.2 6.2 0 0 1 6.2-6.2zm0 2.22a3.99 3.99 0 0 0-3.97 3.97A3.99 3.99 0 0 0 12 15.92a3.99 3.99 0 0 0 3.97-3.97A3.99 3.99 0 0 0 12 7.98zm6.44-3.77a1.4 1.4 0 1 1 0 2.8 1.4 1.4 0 0 1 0-2.8z"/></svg>
                                <span>Instagram</span>
                            </a>
                        <?php endif; ?>
                        <?php if ($tiktokUrl): ?>
                            <a href="<?= htmlspecialchars($tiktokUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-dark d-inline-flex align-items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0c6.6274 0 12 5.3726 12 12s-5.3726 12-12 12S0 18.6274 0 12 5.3726 0 12 0Zm3.1623 4h-2.7508v10.9209a2.3324 2.3324 0 0 1-3.0455 2.2209 2.3324 2.3324 0 0 1 1.4129-4.4459V9.8862a5.0812 5.0812 0 0 0-5.7481 5.5912 5.0805 5.0805 0 0 0 3.802 4.3668 5.0818 5.0818 0 0 0 5.423-2.0286c.5899-.8501.9062-1.86.9065-2.8947V9.3345A6.5666 6.5666 0 0 0 19 10.5614V7.83a3.796 3.796 0 0 1-2.0944-.6295 3.8188 3.8188 0 0 1-1.6852-2.5075 3.7856 3.7856 0 0 1-.058-.693Z" /></svg>
                                <span>TikTok</span>
                            </a>
                        <?php endif; ?>
                        <?php if ($facebookUrl): ?>
                            <a href="<?= htmlspecialchars($facebookUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary d-inline-flex align-items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M16.98 0a6.9 6.9 0 0 1 5.08 1.98A6.94 6.94 0 0 1 24 7.02v9.96c0 2.08-.68 3.87-1.98 5.13A7.14 7.14 0 0 1 16.94 24H7.06a7.06 7.06 0 0 1-5.03-1.89A6.96 6.96 0 0 1 0 16.94V7.02C0 2.8 2.8 0 7.02 0h9.96zm.05 2.23H7.06c-1.45 0-2.7.43-3.53 1.25a4.82 4.82 0 0 0-1.3 3.54v9.92c0 1.5.43 2.7 1.3 3.58a5 5 0 0 0 3.53 1.25h9.88a5 5 0 0 0 3.53-1.25 4.73 4.73 0 0 0 1.4-3.54V7.02a5 5 0 0 0-1.3-3.49 4.82 4.82 0 0 0-3.54-1.3zM12 5.76c3.39 0 6.2 2.8 6.2 6.2a6.2 6.2 0 0 1-12.4 0 6.2 6.2 0 0 1 6.2-6.2zm0 2.22a3.99 3.99 0 0 0-3.97 3.97A3.99 3.99 0 0 0 12 15.92a3.99 3.99 0 0 0 3.97-3.97A3.99 3.99 0 0 0 12 7.98zm6.44-3.77a1.4 1.4 0 1 1 0 2.8 1.4 1.4 0 0 1 0-2.8z"/></svg>
                                <span>Facebook</span>
                            </a>
                        <?php endif; ?>
                        <?php if ($whatsappUrl): ?>
                            <a href="<?= htmlspecialchars($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-success d-inline-flex align-items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M16.98 0a6.9 6.9 0 0 1 5.08 1.98A6.94 6.94 0 0 1 24 7.02v9.96c0 2.08-.68 3.87-1.98 5.13A7.14 7.14 0 0 1 16.94 24H7.06a7.06 7.06 0 0 1-5.03-1.89A6.96 6.96 0 0 1 0 16.94V7.02C0 2.8 2.8 0 7.02 0h9.96zm.05 2.23H7.06c-1.45 0-2.7.43-3.53 1.25a4.82 4.82 0 0 0-1.3 3.54v9.92c0 1.5.43 2.7 1.3 3.58a5 5 0 0 0 3.53 1.25h9.88a5 5 0 0 0 3.53-1.25 4.73 4.73 0 0 0 1.4-3.54V7.02a5 5 0 0 0-1.3-3.49 4.82 4.82 0 0 0-3.54-1.3zM12 5.76c3.39 0 6.2 2.8 6.2 6.2a6.2 6.2 0 0 1-12.4 0 6.2 6.2 0 0 1 6.2-6.2zm0 2.22a3.99 3.99 0 0 0-3.97 3.97A3.99 3.99 0 0 0 12 15.92a3.99 3.99 0 0 0 3.97-3.97A3.99 3.99 0 0 0 12 7.98zm6.44-3.77a1.4 1.4 0 1 1 0 2.8 1.4 1.4 0 0 1 0-2.8z"/></svg>
                                <span>WhatsApp</span>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="mt-3">
                    <?php if (!isset($base_url)) $base_url = ''; ?>
                    <a href="<?= $base_url ?>/categoria.php?id=<?= $empresa['categoria_id'] ?>" class="btn btn-outline-secondary">← Volver a <?= $empresa['categoria_nombre'] ?></a>
                </div>
            </div>
        </div>

        <!-- Productos de la empresa -->
        <?php if(count($productos) > 0): ?>
        <div class="card mt-4">
            <div class="card-header bg-success text-white">
                <h4>🛒 Productos Disponibles</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach($productos as $prod): ?>
                        <?php
                        $productId = (int) $prod['id'];
                        $stats = $productCounts[$productId] ?? ['likes' => 0, 'guardados' => 0, 'resenas' => 0, 'promedio' => 0.0];
                        $flags = $productFlags[$productId] ?? ['liked' => false, 'saved' => false];
                        $yaComprado = isset($productosComprados[$productId]);
                        ?>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="row g-0">
                                    <div class="col-md-4">
                                        <img src="<?= $base_url ?>/uploads/productos/<?= $prod['imagen'] ?: 'default.png' ?>" class="img-fluid rounded-start" alt="<?= htmlspecialchars($prod['nombre']) ?>" style="height: 100px; object-fit: cover;">
                                    </div>
                                    <div class="col-md-8">
                                        <div class="card-body">
                                            <h6 class="card-title"><?= htmlspecialchars($prod['nombre']) ?></h6>
                                            <p class="card-text small text-truncate"><?= htmlspecialchars($prod['descripcion']) ?></p>
                                            <p class="text-success fw-bold">$ <?= number_format($prod['precio'], 0, ',', '.') ?></p>
                                            <p class="small text-muted mb-2 product-meta-stats">
                                                <span><?= ui_icon('like', 'stat-icon') ?> <span id="likes-count-<?= $productId ?>"><?= (int) $stats['likes'] ?></span></span>
                                                <span><?= ui_icon('save', 'stat-icon') ?> <span id="saves-count-<?= $productId ?>"><?= (int) $stats['guardados'] ?></span></span>
                                                <span><?= ui_icon('star', 'stat-icon') ?> <?= number_format((float) $stats['promedio'], 1) ?> (<?= (int) $stats['resenas'] ?>)</span>
                                            </p>
                                            <?php if ($yaComprado): ?>
                                                <span class="badge bg-success mb-2">Ya lo pediste</span>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-primary" onclick="agregarAlCarrito(<?= $prod['id'] ?>, '<?= htmlspecialchars($prod['nombre']) ?>', <?= $prod['precio'] ?>, this)">Agregar al Carrito</button>
                                            <?php if ($esCliente): ?>
                                                <div class="mt-2 d-flex gap-2">
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
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3">
                    <a href="<?= $base_url ?>/carrito.php" class="btn btn-success">Ver Carrito 🛒</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'templates/footer.php'; ?>