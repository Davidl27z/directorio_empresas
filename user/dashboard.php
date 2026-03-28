<?php
session_start();
require '../config/db.php';
require_once '../config/csrf.php';
require_once '../config/empresa_contacto.php';
require_once '../config/usuario_interacciones.php';
require_once '../config/ui_icons.php';
require_once '../config/pedido_notificaciones.php';
require_once '../config/empresa_metricas.php';

empresa_ensure_contact_schema($pdo);
usuario_interacciones_ensure_schema($pdo);
pedido_notification_ensure_schema($pdo);
empresa_metricas_ensure_schema($pdo);

// Verificar que esté logueado
if (!isset($_SESSION['user_id'])) {
    if (!isset($base_url)) $base_url = '..';
    header("Location: " . $base_url . "/auth/login.php");
    exit;
}

$usuario_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

$dashboardPathInfo = trim((string) ($_SERVER['PATH_INFO'] ?? ''), '/');
$dashboardPathSegments = $dashboardPathInfo !== '' ? explode('/', $dashboardPathInfo) : [];
$dashboardRequestedSection = $dashboardPathSegments[0] ?? ($_GET['seccion'] ?? 'resumen');

// Contenido diferente según el rol
if ($user_role === 'cliente') {
    // Para clientes: mostrar pedidos realizados
    $stmt = $pdo->prepare("
        SELECT p.*, e.nombre as empresa_nombre, e.logo as empresa_logo
        FROM pedidos p
        LEFT JOIN empresas e ON p.empresa_id = e.id
        WHERE p.usuario_id = ?
        ORDER BY p.fecha_pedido DESC
    ");
    $stmt->execute([$usuario_id]);
    $mis_pedidos = $stmt->fetchAll();

    // Estadísticas del cliente
    $total_pedidos = count($mis_pedidos);
    $pedidos_pendientes = count(array_filter($mis_pedidos, function($p) { return $p['estado'] === 'pendiente'; }));

    $stmt = $pdo->prepare("\n        SELECT pl.*, p.nombre as producto_nombre, p.precio as producto_precio, p.imagen as producto_imagen,\n               e.id as empresa_id, e.nombre as empresa_nombre\n        FROM producto_likes pl\n        INNER JOIN productos p ON p.id = pl.producto_id\n        INNER JOIN empresas e ON e.id = p.empresa_id\n        WHERE pl.usuario_id = ?\n        ORDER BY pl.created_at DESC\n    ");
    $stmt->execute([$usuario_id]);
    $mis_likes = $stmt->fetchAll();

    $stmt = $pdo->prepare("\n        SELECT pg.*, p.nombre as producto_nombre, p.precio as producto_precio, p.imagen as producto_imagen,\n               e.id as empresa_id, e.nombre as empresa_nombre\n        FROM producto_guardados pg\n        INNER JOIN productos p ON p.id = pg.producto_id\n        INNER JOIN empresas e ON e.id = p.empresa_id\n        WHERE pg.usuario_id = ?\n        ORDER BY pg.created_at DESC\n    ");
    $stmt->execute([$usuario_id]);
    $mis_guardados = $stmt->fetchAll();

    $stmt = $pdo->prepare("\n        SELECT p.id AS producto_id, p.nombre AS producto_nombre, p.precio AS producto_precio, p.imagen AS producto_imagen,\n               e.id AS empresa_id, e.nombre AS empresa_nombre,\n               MAX(pd.fecha_pedido) AS ultima_compra, COUNT(*) AS veces_pedido\n        FROM pedidos pd\n        INNER JOIN pedido_items pi ON pi.pedido_id = pd.id\n        INNER JOIN productos p ON p.id = pi.producto_id\n        INNER JOIN empresas e ON e.id = p.empresa_id\n        WHERE pd.usuario_id = ? AND pd.estado <> 'cancelado'\n        GROUP BY p.id, p.nombre, p.precio, p.imagen, e.id, e.nombre\n        ORDER BY ultima_compra DESC\n    ");
    $stmt->execute([$usuario_id]);
    $productos_pedidos = $stmt->fetchAll();

    $total_likes = count($mis_likes);
    $total_guardados = count($mis_guardados);
    $total_productos_pedidos = count($productos_pedidos);

    $seccionesCliente = ['resumen', 'pedidos', 'likes', 'guardados', 'ya-pedidos'];
    $clienteSeccion = $dashboardRequestedSection;
    if (!in_array($clienteSeccion, $seccionesCliente, true)) {
        $clienteSeccion = 'resumen';
    }

    $dashboardSectionUrls = [
        'resumen' => $base_url . '/user/dashboard.php/resumen',
        'pedidos' => $base_url . '/user/dashboard.php/pedidos',
        'likes' => $base_url . '/user/dashboard.php/likes',
        'guardados' => $base_url . '/user/dashboard.php/guardados',
        'ya-pedidos' => $base_url . '/user/dashboard.php/ya-pedidos',
    ];

} else {
    // Para empresas: mostrar empresas y productos
    $stmt = $pdo->prepare("SELECT * FROM empresas WHERE usuario_id = ? ORDER BY id DESC");
    $stmt->execute([$usuario_id]);
    $mis_empresas = $stmt->fetchAll();

    // Obtener pedidos recibidos (para empresas)
    $stmt = $pdo->prepare("
        SELECT p.*, u.nombre as cliente_nombre, e.nombre as empresa_nombre
        FROM pedidos p
        LEFT JOIN usuarios u ON p.usuario_id = u.id
        LEFT JOIN empresas e ON p.empresa_id = e.id
        WHERE p.empresa_id IN (SELECT id FROM empresas WHERE usuario_id = ?)
        ORDER BY p.fecha_pedido DESC
    ");
    $stmt->execute([$usuario_id]);
    $pedidos_recibidos = $stmt->fetchAll();

    // Obtener categorías para el formulario
    $categorias = $pdo->query("SELECT * FROM categorias ORDER BY nombre")->fetchAll();

    $empresaIds = array_values(array_filter(array_map('intval', array_column($mis_empresas, 'id'))));
    $empresaIdsPlaceholders = !empty($empresaIds) ? implode(',', array_fill(0, count($empresaIds), '?')) : '';

    $seccionesEmpresa = ['resumen', 'ventas', 'analitica'];
    $empresaSeccion = $dashboardRequestedSection;
    if (!in_array($empresaSeccion, $seccionesEmpresa, true)) {
        $empresaSeccion = 'resumen';
    }

    $analyticsChartType = $_GET['analytics_chart'] ?? 'bar';
    $allowedAnalyticsChartTypes = ['bar', 'pie', 'doughnut'];
    if (!in_array($analyticsChartType, $allowedAnalyticsChartTypes, true)) {
        $analyticsChartType = 'bar';
    }

    $totalPedidosNoLeidos = 0;
    $totalVisitas = 0;
    $visitantesUnicos = 0;
    $compradoresUnicos = 0;
    $noConvirtieron = 0;
    $tasaConversion = 0.0;
    $topProductosLikes = [];
    $topProductosResenas = [];
    $topProductosGuardados = [];

    $dashboardEmpresaUrls = [
        'resumen' => $base_url . '/user/dashboard.php/resumen',
        'ventas' => $base_url . '/user/dashboard.php/ventas',
        'analitica' => $base_url . '/user/dashboard.php/analitica',
    ];

    if (!empty($empresaIds)) {
        $stmtNoLeidos = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE empresa_id IN ($empresaIdsPlaceholders) AND visto_empresa = 0");
        $stmtNoLeidos->execute($empresaIds);
        $totalPedidosNoLeidos = (int) $stmtNoLeidos->fetchColumn();

        if ($empresaSeccion === 'ventas' && $totalPedidosNoLeidos > 0) {
            $stmtMarcarLeidos = $pdo->prepare("UPDATE pedidos SET visto_empresa = 1 WHERE empresa_id IN ($empresaIdsPlaceholders) AND visto_empresa = 0");
            $stmtMarcarLeidos->execute($empresaIds);
            $totalPedidosNoLeidos = 0;
        }

        $stmtTopLikes = $pdo->prepare("\n            SELECT p.id, p.nombre, COUNT(pl.id) AS total\n            FROM productos p\n            LEFT JOIN producto_likes pl ON pl.producto_id = p.id\n            WHERE p.empresa_id IN ($empresaIdsPlaceholders)\n            GROUP BY p.id, p.nombre\n            ORDER BY total DESC, p.nombre ASC\n            LIMIT 7\n        ");
        $stmtTopLikes->execute($empresaIds);
        $topProductosLikes = $stmtTopLikes->fetchAll();

        $stmtTopResenas = $pdo->prepare("\n            SELECT p.id, p.nombre, COUNT(pr.id) AS total, COALESCE(AVG(pr.calificacion), 0) AS promedio\n            FROM productos p\n            LEFT JOIN producto_resenas pr ON pr.producto_id = p.id\n            WHERE p.empresa_id IN ($empresaIdsPlaceholders)\n            GROUP BY p.id, p.nombre\n            ORDER BY total DESC, promedio DESC, p.nombre ASC\n            LIMIT 7\n        ");
        $stmtTopResenas->execute($empresaIds);
        $topProductosResenas = $stmtTopResenas->fetchAll();

        $stmtTopGuardados = $pdo->prepare("\n            SELECT p.id, p.nombre, COUNT(pg.id) AS total\n            FROM productos p\n            LEFT JOIN producto_guardados pg ON pg.producto_id = p.id\n            WHERE p.empresa_id IN ($empresaIdsPlaceholders)\n            GROUP BY p.id, p.nombre\n            ORDER BY total DESC, p.nombre ASC\n            LIMIT 7\n        ");
        $stmtTopGuardados->execute($empresaIds);
        $topProductosGuardados = $stmtTopGuardados->fetchAll();

        $stmtVisitas = $pdo->prepare("SELECT COUNT(*) FROM empresa_visitas WHERE empresa_id IN ($empresaIdsPlaceholders)");
        $stmtVisitas->execute($empresaIds);
        $totalVisitas = (int) $stmtVisitas->fetchColumn();

        $stmtVisitantesUnicos = $pdo->prepare("\n            SELECT COUNT(DISTINCT IF(usuario_id IS NULL, CONCAT('s:', session_key), CONCAT('u:', usuario_id)))\n            FROM empresa_visitas\n            WHERE empresa_id IN ($empresaIdsPlaceholders)\n        ");
        $stmtVisitantesUnicos->execute($empresaIds);
        $visitantesUnicos = (int) $stmtVisitantesUnicos->fetchColumn();

        $stmtCompradoresUnicos = $pdo->prepare("\n            SELECT COUNT(DISTINCT usuario_id)\n            FROM pedidos\n            WHERE empresa_id IN ($empresaIdsPlaceholders) AND usuario_id IS NOT NULL\n        ");
        $stmtCompradoresUnicos->execute($empresaIds);
        $compradoresUnicos = (int) $stmtCompradoresUnicos->fetchColumn();

        $noConvirtieron = max(0, $visitantesUnicos - $compradoresUnicos);
        $tasaConversion = $visitantesUnicos > 0 ? ($compradoresUnicos / $visitantesUnicos) * 100 : 0.0;
    }

    $totalEmpresas = count($mis_empresas);
    $totalVentas = count($pedidos_recibidos);
    $ventasCompletadas = count(array_filter($pedidos_recibidos, static function ($pedido) {
        return ($pedido['estado'] ?? '') === 'completado';
    }));
}

include '../templates/header.php';
?>

<h1 class="d-flex align-items-center gap-2"><?= ui_icon('user-panel', 'panel-title-icon') ?> <span>Mi Panel de Usuario</span></h1>
<p>Bienvenido, <?= htmlspecialchars($_SESSION['user_name']) ?> <span class="badge bg-<?= $user_role === 'cliente' ? 'info' : 'success' ?>"><?= ucfirst($user_role) ?></span></p>


<?php if ($user_role === 'cliente'): ?>
    <!-- DASHBOARD PARA CLIENTES -->
    <div class="row mt-4">
        <aside class="col-lg-3 mb-3">
            <div class="card sticky-top" style="top: 1rem;">
                <div class="card-header bg-dark text-white">
                    <h6 class="mb-0">Panel Cliente</h6>
                </div>
                <div class="list-group list-group-flush">
                    <a href="<?= htmlspecialchars($dashboardSectionUrls['resumen']) ?>" class="list-group-item list-group-item-action <?= $clienteSeccion === 'resumen' ? 'active' : '' ?>" style="display: flex; align-items: center; gap: 8px;"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 9v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V9"></path><path d="M9 22V12h6v10M2 10.6L12 2l10 8.6"></path></svg> Resumen</a>
                    <a href="<?= htmlspecialchars($dashboardSectionUrls['pedidos']) ?>" class="list-group-item list-group-item-action <?= $clienteSeccion === 'pedidos' ? 'active' : '' ?>" style="display: flex; align-items: center; gap: 8px;"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 12H16c-.7 2-2 3-4 3s-3.3-1-4-3H2.5"></path><path d="M5.5 5.1L2 12v6c0 1.1.9 2 2 2h16a2 2 0 002-2v-6l-3.4-6.9A2 2 0 0016.8 4H7.2a2 2 0 00-1.8 1.1z"></path></svg> Mis Pedidos</a>
                    <a href="<?= htmlspecialchars($dashboardSectionUrls['likes']) ?>" class="list-group-item list-group-item-action <?= $clienteSeccion === 'likes' ? 'active' : '' ?>"><?= ui_icon('like', 'sidebar-icon') ?> Mis Likes</a>
                    <a href="<?= htmlspecialchars($dashboardSectionUrls['guardados']) ?>" class="list-group-item list-group-item-action <?= $clienteSeccion === 'guardados' ? 'active' : '' ?>"><?= ui_icon('save', 'sidebar-icon') ?> Mis Guardados</a>
                    <a href="<?= htmlspecialchars($dashboardSectionUrls['ya-pedidos']) ?>" class="list-group-item list-group-item-action <?= $clienteSeccion === 'ya-pedidos' ? 'active' : '' ?>">🧾 Ya Pedidos</a>
                </div>
            </div>
        </aside>

        <div class="col-lg-9">
            <?php if ($clienteSeccion === 'resumen'): ?>
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-primary"><?= $total_pedidos ?></h3>
                                <p class="text-muted">Total Pedidos</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-warning"><?= $pedidos_pendientes ?></h3>
                                <p class="text-muted">Pedidos Pendientes</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-danger"><?= $total_likes ?></h3>
                                <p class="text-muted">Me Gusta</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-secondary"><?= $total_guardados ?></h3>
                                <p class="text-muted">Guardados</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-success"><?= $total_productos_pedidos ?></h3>
                                <p class="text-muted">Productos Ya Pedidos</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($clienteSeccion === 'pedidos'): ?>
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5>📦 Mis Pedidos</h5>
                    </div>
                    <div class="card-body">
                        <?php if(count($mis_pedidos) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Empresa</th>
                                            <th>Fecha</th>
                                            <th>Total</th>
                                            <th>Estado</th>
                                            <th>Método Contacto</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($mis_pedidos as $pedido): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if($pedido['empresa_logo']): ?>
                                                            <img src="<?= $base_url ?>/uploads/logos/<?= $pedido['empresa_logo'] ?>" class="rounded-circle me-2" style="width: 30px; height: 30px; object-fit: cover;" alt="">
                                                        <?php endif; ?>
                                                        <?= htmlspecialchars($pedido['empresa_nombre']) ?>
                                                    </div>
                                                </td>
                                                <td><?= date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) ?></td>
                                                <td>$ <?= number_format($pedido['total'], 0, ',', '.') ?></td>
                                                <td>
                                                    <?php
                                                    $estado_clases = [
                                                        'pendiente' => 'warning',
                                                        'procesando' => 'info',
                                                        'completado' => 'success',
                                                        'cancelado' => 'danger'
                                                    ];
                                                    $estado_iconos = [
                                                        'pendiente' => '⏳',
                                                        'procesando' => '🔄',
                                                        'completado' => '✅',
                                                        'cancelado' => '❌'
                                                    ];
                                                    ?>
                                                    <span class="badge bg-<?= $estado_clases[$pedido['estado']] ?>">
                                                        <?= $estado_iconos[$pedido['estado']] ?> <?= ucfirst($pedido['estado']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $metodo_iconos = [
                                                        'whatsapp' => '📱 WhatsApp',
                                                        'formulario' => '📧 Email',
                                                        'correo' => '✉️ Correo'
                                                    ];
                                                    echo $metodo_iconos[$pedido['metodo_contacto']] ?? $pedido['metodo_contacto'];
                                                    ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="verDetallePedido(<?= $pedido['id'] ?>)">
                                                        Ver Detalle
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <h4 class="text-muted">No has realizado ningún pedido todavía</h4>
                                <p class="text-muted">¡Explora las empresas y comienza a comprar!</p>
                                <a href="<?= $base_url ?>/index.php" class="btn btn-primary">Explorar Empresas</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($clienteSeccion === 'likes'): ?>
                <div class="card mt-4">
                    <div class="card-header bg-danger text-white">
                        <h5><?= ui_icon('like', 'sidebar-icon') ?> Productos con Me Gusta</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($mis_likes)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Empresa</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($mis_likes as $like): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <img src="<?= $base_url ?>/uploads/productos/<?= $like['producto_imagen'] ?: 'default.png' ?>" style="width:40px;height:40px;object-fit:cover;border-radius:6px;" alt="">
                                                        <span><?= htmlspecialchars($like['producto_nombre']) ?></span>
                                                    </div>
                                                </td>
                                                <td><a href="<?= $base_url ?>/empresa.php?id=<?= (int) $like['empresa_id'] ?>"><?= htmlspecialchars($like['empresa_nombre']) ?></a></td>
                                                <td><?= date('d/m/Y H:i', strtotime($like['created_at'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">Todavia no diste me gusta a ningun producto.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($clienteSeccion === 'guardados'): ?>
                <div class="card mt-4">
                    <div class="card-header bg-secondary text-white">
                        <h5><?= ui_icon('save', 'sidebar-icon') ?> Productos Guardados</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($mis_guardados)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Empresa</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($mis_guardados as $guardado): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <img src="<?= $base_url ?>/uploads/productos/<?= $guardado['producto_imagen'] ?: 'default.png' ?>" style="width:40px;height:40px;object-fit:cover;border-radius:6px;" alt="">
                                                        <span><?= htmlspecialchars($guardado['producto_nombre']) ?></span>
                                                    </div>
                                                </td>
                                                <td><a href="<?= $base_url ?>/empresa.php?id=<?= (int) $guardado['empresa_id'] ?>"><?= htmlspecialchars($guardado['empresa_nombre']) ?></a></td>
                                                <td><?= date('d/m/Y H:i', strtotime($guardado['created_at'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No tienes productos guardados aun.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($clienteSeccion === 'ya-pedidos'): ?>
                <div class="card mt-4">
                    <div class="card-header bg-success text-white">
                        <h5>🧾 Productos Ya Pedidos</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($productos_pedidos)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Empresa</th>
                                            <th>Ultima compra</th>
                                            <th>Veces pedido</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($productos_pedidos as $productoPedido): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <img src="<?= $base_url ?>/uploads/productos/<?= $productoPedido['producto_imagen'] ?: 'default.png' ?>" style="width:44px;height:44px;object-fit:cover;border-radius:6px;" alt="">
                                                        <span><?= htmlspecialchars($productoPedido['producto_nombre']) ?></span>
                                                    </div>
                                                </td>
                                                <td><a href="<?= $base_url ?>/empresa.php?id=<?= (int) $productoPedido['empresa_id'] ?>"><?= htmlspecialchars($productoPedido['empresa_nombre']) ?></a></td>
                                                <td><?= date('d/m/Y H:i', strtotime($productoPedido['ultima_compra'])) ?></td>
                                                <td><span class="badge bg-success"><?= (int) $productoPedido['veces_pedido'] ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">Aun no tienes historial de productos pedidos.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>


<?php else: ?>
    <div class="row mt-4">
        <aside class="col-lg-3 mb-3">
            <div class="card sticky-top" style="top: 1rem;">
                <div class="card-header bg-dark text-white">
                    <h6 class="mb-0">Panel Empresa</h6>
                </div>
                <div class="list-group list-group-flush">
                    <a href="<?= htmlspecialchars($dashboardEmpresaUrls['resumen']) ?>" class="list-group-item list-group-item-action <?= $empresaSeccion === 'resumen' ? 'active' : '' ?>" style="display: flex; align-items: center; gap: 8px;"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 9v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V9"></path><path d="M9 22V12h6v10M2 10.6L12 2l10 8.6"></path></svg> Mi Panel</a>
                    <a href="<?= htmlspecialchars($dashboardEmpresaUrls['ventas']) ?>" class="list-group-item list-group-item-action <?= $empresaSeccion === 'ventas' ? 'active' : '' ?> d-flex justify-content-between align-items-center">
                        <span style="display: flex; align-items: center; gap: 8px;"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 12H16c-.7 2-2 3-4 3s-3.3-1-4-3H2.5"></path><path d="M5.5 5.1L2 12v6c0 1.1.9 2 2 2h16a2 2 0 002-2v-6l-3.4-6.9A2 2 0 0016.8 4H7.2a2 2 0 00-1.8 1.1z"></path></svg> Ventas</span>
                        <?php if ($totalPedidosNoLeidos > 0): ?>
                            <span class="badge bg-danger rounded-pill"><?= $totalPedidosNoLeidos ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="<?= htmlspecialchars($dashboardEmpresaUrls['analitica']) ?>" class="list-group-item list-group-item-action <?= $empresaSeccion === 'analitica' ? 'active' : '' ?>" style="display: flex; align-items: center; gap: 8px;"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"/></svg> Analítica</a>
                </div>
            </div>
        </aside>

        <div class="col-lg-9">
            <?php if ($empresaSeccion === 'resumen'): ?>
                <?php if ($totalPedidosNoLeidos > 0): ?>
                    <div class="alert alert-warning d-flex justify-content-between align-items-center">
                        <span>Tienes <strong><?= $totalPedidosNoLeidos ?></strong> pedido(s) nuevo(s) sin revisar.</span>
                        <a class="btn btn-sm btn-dark" href="<?= htmlspecialchars($dashboardEmpresaUrls['ventas']) ?>">Ir a Ventas</a>
                    </div>
                <?php endif; ?>
                <div class="row">
                    <div class="col-md-8 mb-4">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5>Mis Empresas</h5>
                            </div>
                            <div class="card-body">
                                <?php if(count($mis_empresas) > 0): ?>
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Nombre</th>
                                                <th>Estado</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($mis_empresas as $emp): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($emp['nombre']) ?></td>
                                                    <td>
                                                        <?php if($emp['aprobada']): ?>
                                                            <span class="badge bg-success">Aprobada</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">Pendiente</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!isset($base_url)) $base_url = '..'; ?>
                                                        <a href="<?= $base_url ?>/user/editar_empresa.php?id=<?= $emp['id'] ?>" class="btn btn-sm btn-primary">Editar</a>
                                                        <?php if($emp['aprobada']): ?>
                                                            <a href="<?= $base_url ?>/user/productos.php?empresa_id=<?= $emp['id'] ?>" class="btn btn-sm btn-info">Productos</a>
                                                        <?php endif; ?>
                                                        <form method="POST" action="<?= $base_url ?>/user/eliminar_empresa.php" style="display:inline" onsubmit="return confirm('¿Eliminar?')">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                                            <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p class="text-muted mb-0">No has creado ninguna empresa todavía.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5>Agregar Empresa</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!isset($base_url)) $base_url = '..'; ?>
                                <form method="POST" action="<?= $base_url ?>/user/crear_empresa.php" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <div class="mb-2">
                                        <label class="form-label">Nombre</label>
                                        <input type="text" name="nombre" class="form-control" required>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Categoría</label>
                                        <select name="categoria_id" class="form-select" required>
                                            <option value="">Seleccionar...</option>
                                            <?php foreach($categorias as $cat): ?>
                                                <option value="<?= $cat['id'] ?>"><?= $cat['nombre'] ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Descripción</label>
                                        <textarea name="descripcion" class="form-control" rows="2"></textarea>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Dirección</label>
                                        <input type="text" name="direccion" class="form-control">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Teléfono</label>
                                        <input type="text" name="telefono" class="form-control">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Instagram</label>
                                        <input type="text" name="instagram" class="form-control" placeholder="usuario o URL">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">TikTok</label>
                                        <input type="text" name="tiktok" class="form-control" placeholder="usuario o URL">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Facebook</label>
                                        <input type="text" name="facebook" class="form-control" placeholder="pagina o URL">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">WhatsApp</label>
                                        <input type="text" name="whatsapp" class="form-control" placeholder="+549123456789">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Website</label>
                                        <input type="text" name="website" class="form-control" placeholder="https://...">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label d-block">Medios de contacto para pedidos</label>
                                        <?php foreach (empresa_contact_method_labels() as $valor => $etiqueta): ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="medios_contacto[]" value="<?= $valor ?>" id="nuevo_medio_<?= $valor ?>" <?= $valor === 'whatsapp' ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="nuevo_medio_<?= $valor ?>"><?= htmlspecialchars($etiqueta) ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                        <div class="form-text">Selecciona al menos un medio para coordinar pedidos.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Logo (opcional)</label>
                                        <input type="file" name="logo" class="form-control" accept="image/*">
                                    </div>
                                    <button type="submit" class="btn btn-success w-100">Enviar para Aprobación</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($empresaSeccion === 'ventas'): ?>
                <div class="card mt-2">
                    <div class="card-header bg-warning text-dark">
                        <h5>Ventas Recibidas</h5>
                    </div>
                    <div class="card-body">
                        <?php if(count($pedidos_recibidos) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Cliente</th>
                                            <th>Empresa</th>
                                            <th>Total</th>
                                            <th>Método</th>
                                            <th>Estado</th>
                                            <th>Fecha</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($pedidos_recibidos as $ped): ?>
                                            <tr>
                                                <td>#<?= $ped['id'] ?></td>
                                                <td><?= htmlspecialchars($ped['cliente_nombre']) ?></td>
                                                <td><?= htmlspecialchars($ped['empresa_nombre']) ?></td>
                                                <td>$ <?= number_format($ped['total'], 0, ',', '.') ?></td>
                                                <td>
                                                    <?php
                                                    switch ($ped['metodo_contacto']) {
                                                        case 'whatsapp': echo 'WhatsApp'; break;
                                                        case 'formulario': echo 'Tel + Email'; break;
                                                        case 'correo': echo 'Email'; break;
                                                        default: echo htmlspecialchars($ped['metodo_contacto']);
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $ped['estado'] == 'pendiente' ? 'warning' : 'success' ?>">
                                                        <?= ucfirst($ped['estado']) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('d/m/Y H:i', strtotime($ped['fecha_pedido'])) ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="verPedido(<?= $ped['id'] ?>)">Ver Detalles</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">Aún no has recibido ventas.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($empresaSeccion === 'analitica'): ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <h3 class="text-primary"><?= $totalEmpresas ?></h3>
                                <p class="text-muted mb-0">Empresas creadas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <h3 class="text-warning"><?= $totalVentas ?></h3>
                                <p class="text-muted mb-0">Ventas registradas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <h3 class="text-success"><?= $ventasCompletadas ?></h3>
                                <p class="text-muted mb-0">Ventas completadas</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <h4 class="text-info"><?= $totalVisitas ?></h4>
                                <p class="text-muted mb-0">Visitas totales</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <h4 class="text-primary"><?= $visitantesUnicos ?></h4>
                                <p class="text-muted mb-0">Visitantes únicos</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <h4 class="text-success"><?= $compradoresUnicos ?></h4>
                                <p class="text-muted mb-0">Visitantes que pidieron</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <h4 class="text-danger"><?= $noConvirtieron ?></h4>
                                <p class="text-muted mb-0">Visitantes sin pedido</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-info text-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h5 class="mb-0">Analitica de Productos y Conversión</h5>
                        <form method="GET" action="<?= htmlspecialchars($dashboardEmpresaUrls['analitica']) ?>" class="d-flex align-items-center gap-2">
                            <label for="analytics_chart" class="form-label mb-0 text-white">Tipo de grafica</label>
                            <select name="analytics_chart" id="analytics_chart" class="form-select form-select-sm" style="width: 150px;" onchange="this.form.submit()">
                                <option value="bar" <?= $analyticsChartType === 'bar' ? 'selected' : '' ?>>Barras</option>
                                <option value="pie" <?= $analyticsChartType === 'pie' ? 'selected' : '' ?>>Torta</option>
                                <option value="doughnut" <?= $analyticsChartType === 'doughnut' ? 'selected' : '' ?>>Dona</option>
                            </select>
                        </form>
                    </div>
                    <div class="card-body">
                        <p class="mb-3">Tasa de conversión estimada: <strong><?= number_format($tasaConversion, 1) ?>%</strong></p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <h6>Productos con más likes</h6>
                                <?php if (!empty($topProductosLikes)): ?>
                                    <ul class="list-group mb-3">
                                        <?php foreach ($topProductosLikes as $row): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><?= htmlspecialchars($row['nombre']) ?></span>
                                                <span class="badge bg-danger rounded-pill"><?= (int) $row['total'] ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-muted">Aun no hay likes en tus productos.</p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h6>Productos con más reseñas</h6>
                                <?php if (!empty($topProductosResenas)): ?>
                                    <ul class="list-group mb-3">
                                        <?php foreach ($topProductosResenas as $row): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><?= htmlspecialchars($row['nombre']) ?></span>
                                                <span class="badge bg-primary rounded-pill"><?= (int) $row['total'] ?> (<?= number_format((float) $row['promedio'], 1) ?>)</span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-muted">Aun no hay reseñas en tus productos.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-12">
                                <h6>Productos más guardados</h6>
                                <?php if (!empty($topProductosGuardados)): ?>
                                    <ul class="list-group mb-0">
                                        <?php foreach ($topProductosGuardados as $row): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><?= htmlspecialchars($row['nombre']) ?></span>
                                                <span class="badge bg-secondary rounded-pill"><?= (int) $row['total'] ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-muted mb-0">Aun no hay guardados en tus productos.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php
                        $chartMap = [];
                        foreach ($topProductosLikes as $row) {
                            $nombre = (string) ($row['nombre'] ?? 'Producto');
                            if (!isset($chartMap[$nombre])) {
                                $chartMap[$nombre] = ['likes' => 0, 'resenas' => 0, 'guardados' => 0];
                            }
                            $chartMap[$nombre]['likes'] = (int) ($row['total'] ?? 0);
                        }
                        foreach ($topProductosResenas as $row) {
                            $nombre = (string) ($row['nombre'] ?? 'Producto');
                            if (!isset($chartMap[$nombre])) {
                                $chartMap[$nombre] = ['likes' => 0, 'resenas' => 0, 'guardados' => 0];
                            }
                            $chartMap[$nombre]['resenas'] = (int) ($row['total'] ?? 0);
                        }
                        foreach ($topProductosGuardados as $row) {
                            $nombre = (string) ($row['nombre'] ?? 'Producto');
                            if (!isset($chartMap[$nombre])) {
                                $chartMap[$nombre] = ['likes' => 0, 'resenas' => 0, 'guardados' => 0];
                            }
                            $chartMap[$nombre]['guardados'] = (int) ($row['total'] ?? 0);
                        }

                        uasort($chartMap, static function ($a, $b) {
                            $scoreA = ((int) $a['likes']) + ((int) $a['resenas']) + ((int) $a['guardados']);
                            $scoreB = ((int) $b['likes']) + ((int) $b['resenas']) + ((int) $b['guardados']);
                            return $scoreB <=> $scoreA;
                        });
                        $chartMap = array_slice($chartMap, 0, 7, true);

                        $chartLabels = array_keys($chartMap);
                        $chartLikesData = [];
                        $chartReviewsData = [];
                        $chartSavedData = [];
                        $chartEngagementData = [];
                        foreach ($chartMap as $row) {
                            $likes = (int) $row['likes'];
                            $resenas = (int) $row['resenas'];
                            $guardados = (int) $row['guardados'];
                            $chartLikesData[] = $likes;
                            $chartReviewsData[] = $resenas;
                            $chartSavedData[] = $guardados;
                            $chartEngagementData[] = $likes + $resenas + $guardados;
                        }
                        ?>

                        <?php if (!empty($chartLabels)): ?>
                            <hr>
                            <h6>Comparativo visual (Likes, Reseñas y Guardados)</h6>
                            <div style="height: 340px; position: relative;">
                                <canvas id="empresaAnalyticsChart"></canvas>
                            </div>
                            <?php $require_chartjs = true; ?>
                            <script>
                            (function() {
                                const labels = <?= json_encode($chartLabels) ?>;
                                const likesData = <?= json_encode($chartLikesData) ?>;
                                const reviewsData = <?= json_encode($chartReviewsData) ?>;
                                const savedData = <?= json_encode($chartSavedData) ?>;
                                const engagementData = <?= json_encode($chartEngagementData) ?>;
                                const chartType = <?= json_encode($analyticsChartType) ?>;

                                function drawChart() {
                                    if (typeof Chart === 'undefined') {
                                        return;
                                    }
                                    const canvas = document.getElementById('empresaAnalyticsChart');
                                    if (!canvas) {
                                        return;
                                    }
                                    const ctx = canvas.getContext('2d');
                                    if (window._charts && window._charts['empresaAnalyticsChart']) {
                                        try { window._charts['empresaAnalyticsChart'].destroy(); } catch (e) {}
                                    }
                                    window._charts = window._charts || {};

                                    const baseConfig = {
                                        type: chartType,
                                        data: {
                                            labels: labels,
                                            datasets: [
                                                {
                                                    label: 'Likes',
                                                    data: likesData,
                                                    backgroundColor: 'rgba(220, 53, 69, 0.75)',
                                                    borderColor: 'rgba(220, 53, 69, 1)',
                                                    borderWidth: 1
                                                },
                                                {
                                                    label: 'Reseñas',
                                                    data: reviewsData,
                                                    backgroundColor: 'rgba(13, 110, 253, 0.75)',
                                                    borderColor: 'rgba(13, 110, 253, 1)',
                                                    borderWidth: 1
                                                },
                                                {
                                                    label: 'Guardados',
                                                    data: savedData,
                                                    backgroundColor: 'rgba(108, 117, 125, 0.75)',
                                                    borderColor: 'rgba(108, 117, 125, 1)',
                                                    borderWidth: 1
                                                }
                                            ]
                                        },
                                        options: {
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            plugins: {
                                                legend: { position: 'top' }
                                            },
                                            scales: {
                                                y: {
                                                    beginAtZero: true,
                                                    ticks: {
                                                        precision: 0
                                                    }
                                                }
                                            }
                                        }
                                    };

                                    if (chartType === 'pie' || chartType === 'doughnut') {
                                        baseConfig.data.datasets = [{
                                            label: 'Interacciones totales por producto',
                                            data: engagementData,
                                            backgroundColor: [
                                                '#0d6efd', '#dc3545', '#ffc107', '#198754', '#6f42c1', '#fd7e14', '#20c997'
                                            ],
                                            borderWidth: 1
                                        }];
                                        delete baseConfig.options.scales;
                                    }

                                    window._charts['empresaAnalyticsChart'] = new Chart(ctx, baseConfig);
                                }

                                if (document.readyState === 'loading') {
                                    document.addEventListener('DOMContentLoaded', drawChart);
                                } else {
                                    drawChart();
                                }
                            })();
                            </script>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<script>
function verPedido(pedidoId) {
    // Abrir página de detalles del pedido con la ruta correcta
    const baseUrl = window.APP_BASE_URL || '..';
    window.open(baseUrl + '/user/ver_pedido.php?id=' + pedidoId, '_blank');
}

function verDetallePedido(pedidoId) {
    // Para clientes: mostrar detalles del pedido
    const baseUrl = window.APP_BASE_URL || '..';
    window.open(baseUrl + '/user/ver_pedido.php?id=' + pedidoId, '_blank');
}
</script>

<?php include '../templates/footer.php'; ?>