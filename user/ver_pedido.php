<?php
session_start();
require '../config/db.php';
require_once '../config/csrf.php';
require_once '../config/pedido_contacto.php';
require_once '../config/empresa_contacto.php';
require_once '../config/usuario_interacciones.php';
require_once '../config/pedido_notificaciones.php';
include '../templates/header.php';

empresa_ensure_contact_schema($pdo);
usuario_interacciones_ensure_schema($pdo);
pedido_notification_ensure_schema($pdo);

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Crear tabla de chat si no existe
$pdo->exec("CREATE TABLE IF NOT EXISTS pedido_mensajes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    usuario_id INT NOT NULL,
    mensaje TEXT NOT NULL,
    creado_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
)");

$pedido_id = $_GET['id'] ?? 0;

// Verificar si el usuario puede ver el pedido (empresa dueña o cliente comprador)
$stmt = $pdo->prepare("
    SELECT p.*, e.nombre as empresa_nombre, e.usuario_id as empresa_dueno,
           u.nombre as cliente_nombre, u.email as cliente_email
    FROM pedidos p
    JOIN empresas e ON p.empresa_id = e.id
    JOIN usuarios u ON p.usuario_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch();

if (!$pedido) {
    die("Pedido no encontrado");
}

// Verificar permisos
// Permitir a la empresa ver el pedido si es dueña de la empresa asociada
$puede_ver = false;
if ($_SESSION['user_role'] === 'empresa') {
    // Verificar si el usuario es dueño de la empresa asociada al pedido
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM empresas WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$pedido['empresa_id'], $_SESSION['user_id']]);
    if ($stmt->fetchColumn() > 0) {
        $puede_ver = true;
    }
}
if ($_SESSION['user_role'] === 'cliente' && $pedido['usuario_id'] == $_SESSION['user_id']) {
    $puede_ver = true;
}
if (!$puede_ver) {
    die("No tienes permisos para ver este pedido");
}

$esClienteComprador = $_SESSION['user_role'] === 'cliente' && $pedido['usuario_id'] == $_SESSION['user_id'];
$esEmpresaPropietaria = $_SESSION['user_role'] === 'empresa' && $pedido['empresa_dueno'] == $_SESSION['user_id'];
$reviewError = '';
$reviewSuccess = '';

if (isset($_GET['review']) && $_GET['review'] === 'ok') {
    $reviewSuccess = 'Reseña guardada correctamente.';
}

if ($esEmpresaPropietaria && (int) ($pedido['visto_empresa'] ?? 0) === 0) {
    $stmtMarcarVisto = $pdo->prepare("UPDATE pedidos SET visto_empresa = 1 WHERE id = ?");
    $stmtMarcarVisto->execute([$pedido_id]);
    $pedido['visto_empresa'] = 1;
}

// Obtener items del pedido
$stmt = $pdo->prepare("
    SELECT pi.*, pr.nombre as producto_nombre
    FROM pedido_items pi
    JOIN productos pr ON pi.producto_id = pr.id
    WHERE pi.pedido_id = ?
");
$stmt->execute([$pedido_id]);
$items = $stmt->fetchAll();

$resenasPorItem = [];
$stmt = $pdo->prepare("SELECT * FROM producto_resenas WHERE pedido_id = ?");
$stmt->execute([$pedido_id]);
foreach ($stmt->fetchAll() as $resena) {
    $resenasPorItem[(int) $resena['pedido_item_id']] = $resena;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && (($_POST['accion'] ?? '') === 'crear_resena')) {
    $token = $_POST['csrf_token'] ?? '';
    if (!csrf_validate($token)) {
        die('Token CSRF invalido');
    }

    if (!$esClienteComprador) {
        $reviewError = 'Solo el cliente que hizo el pedido puede reseñar.';
    } elseif (($pedido['estado'] ?? '') !== 'completado') {
        $reviewError = 'Solo puedes reseñar cuando la empresa marque el pedido como completado.';
    } else {
        $pedidoItemId = (int) ($_POST['pedido_item_id'] ?? 0);
        $calificacion = (int) ($_POST['calificacion'] ?? 0);
        $comentario = trim((string) ($_POST['comentario'] ?? ''));

        if ($pedidoItemId <= 0 || $calificacion < 1 || $calificacion > 5) {
            $reviewError = 'Debes seleccionar una calificacion valida de 1 a 5.';
        } else {
            $itemValidoStmt = $pdo->prepare('SELECT id, producto_id FROM pedido_items WHERE id = ? AND pedido_id = ?');
            $itemValidoStmt->execute([$pedidoItemId, $pedido_id]);
            $itemValido = $itemValidoStmt->fetch();

            if (!$itemValido) {
                $reviewError = 'El producto seleccionado no pertenece a este pedido.';
            } elseif (isset($resenasPorItem[$pedidoItemId])) {
                $reviewError = 'Este producto ya fue reseñado en este pedido.';
            } else {
                $insertStmt = $pdo->prepare('INSERT INTO producto_resenas (pedido_item_id, pedido_id, producto_id, usuario_id, empresa_id, calificacion, comentario) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $insertStmt->execute([
                    $pedidoItemId,
                    (int) $pedido_id,
                    (int) $itemValido['producto_id'],
                    (int) $_SESSION['user_id'],
                    (int) $pedido['empresa_id'],
                    $calificacion,
                    $comentario !== '' ? $comentario : null,
                ]);

                header("Location: ver_pedido.php?id={$pedido_id}&review=ok");
                exit;
            }
        }
    }
}

// Enviar mensaje de chat
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nuevo_mensaje'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!csrf_validate($token)) {
        die('Token CSRF invalido');
    }

    $nuevo_mensaje = trim($_POST['nuevo_mensaje']);
    if ($nuevo_mensaje !== '') {
        $stmt = $pdo->prepare("INSERT INTO pedido_mensajes (pedido_id, usuario_id, mensaje) VALUES (?, ?, ?)");
        $stmt->execute([$pedido_id, $_SESSION['user_id'], $nuevo_mensaje]);
    }
    header("Location: ver_pedido.php?id={$pedido_id}");
    exit;
}

// Actualizar estado si se envía POST
$estadosPermitidos = ['pendiente', 'procesando', 'completado', 'cancelado'];
$estadoActualizado = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['estado'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!csrf_validate($token)) {
        die('Token CSRF invalido');
    }

    if ($esEmpresaPropietaria) {
        $nuevo_estado = $_POST['estado'];
        if (in_array($nuevo_estado, $estadosPermitidos, true)) {
            $stmt = $pdo->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
            $stmt->execute([$nuevo_estado, $pedido_id]);
            $pedido['estado'] = $nuevo_estado;
            $estadoActualizado = true;
        }
    }
}

$contactoPedido = pedido_decode_contact_details($pedido['detalles_contacto'] ?? '');
$clienteInfo = $contactoPedido['cliente'] ?? [];
$detalleInfo = $contactoPedido['detalle'] ?? [];
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5>Pedido #<?= $pedido['id'] ?> - Detalles</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Información del Cliente</h6>
                            <p><strong>Nombre:</strong> <?= htmlspecialchars($clienteInfo['nombre'] ?: $pedido['cliente_nombre']) ?></p>
                            <p><strong>Email:</strong> <?= htmlspecialchars($clienteInfo['email'] ?: $pedido['cliente_email']) ?></p>
                            <p><strong>Celular:</strong> <?= htmlspecialchars($clienteInfo['celular'] ?? 'No disponible') ?></p>
                            <p><strong>Ubicacion:</strong> <?= htmlspecialchars($clienteInfo['ubicacion'] ?? 'No disponible') ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Información del Pedido</h6>
                            <p><strong>Empresa:</strong> <?= htmlspecialchars($pedido['empresa_nombre']) ?></p>
                            <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) ?></p>
                            <p><strong>Método de Contacto:</strong> <?= htmlspecialchars(pedido_contact_method_label($pedido['metodo_contacto'])) ?></p>
                            <p><strong>Detalles de Contacto:</strong> <?= htmlspecialchars(pedido_contact_detail_text($contactoPedido)) ?></p>
                        </div>
                    </div>

                    <?php if ($pedido['notas']): ?>
                        <div class="mb-3">
                            <h6>Notas del Cliente</h6>
                            <div class="alert alert-info">
                                <?= nl2br(htmlspecialchars($pedido['notas'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <h6>Productos</h6>
                    <?php if ($reviewSuccess !== ''): ?>
                        <div class="alert alert-success py-2"><?= htmlspecialchars($reviewSuccess) ?></div>
                    <?php endif; ?>
                    <?php if ($reviewError !== ''): ?>
                        <div class="alert alert-danger py-2"><?= htmlspecialchars($reviewError) ?></div>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Precio Unit.</th>
                                    <th>Subtotal</th>
                                    <?php if ($esClienteComprador): ?>
                                        <th>Reseña</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <?php $resenaItem = $resenasPorItem[(int) $item['id']] ?? null; ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['producto_nombre']) ?></td>
                                        <td><?= $item['cantidad'] ?></td>
                                        <td>$ <?= number_format($item['precio_unitario'], 0, ',', '.') ?></td>
                                        <td>$<?= number_format($item['precio_unitario'] * $item['cantidad'], 2) ?></td>
                                        <?php if ($esClienteComprador): ?>
                                            <td>
                                                <?php if ($resenaItem): ?>
                                                    <span class="badge bg-success">Reseñado: <?= str_repeat('★', (int) $resenaItem['calificacion']) ?></span>
                                                <?php elseif (($pedido['estado'] ?? '') === 'completado'): ?>
                                                    <span class="badge bg-warning text-dark">Pendiente de reseña</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Se habilita al completar</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3">Total:</th>
                                    <th>$<?= number_format($pedido['total'], 2) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <?php if ($esClienteComprador): ?>
                        <hr>
                        <h6>Reseñar productos del pedido</h6>
                        <?php if (($pedido['estado'] ?? '') !== 'completado'): ?>
                            <div class="alert alert-info mb-0">Podras reseñar cada producto cuando la empresa marque este pedido como completado.</div>
                        <?php else: ?>
                            <?php
                            $itemsPendientesResena = array_values(array_filter($items, static function ($item) use ($resenasPorItem) {
                                return !isset($resenasPorItem[(int) $item['id']]);
                            }));
                            ?>
                            <?php if (empty($itemsPendientesResena)): ?>
                                <div class="alert alert-success mb-0">Ya reseñaste todos los productos de este pedido.</div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($itemsPendientesResena as $itemPendiente): ?>
                                        <div class="col-md-6 mb-3">
                                            <form method="POST" class="border rounded p-3 h-100">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                                <input type="hidden" name="accion" value="crear_resena">
                                                <input type="hidden" name="pedido_item_id" value="<?= (int) $itemPendiente['id'] ?>">
                                                <h6 class="mb-2"><?= htmlspecialchars($itemPendiente['producto_nombre']) ?></h6>
                                                <div class="mb-2">
                                                    <label class="form-label">Calificacion</label>
                                                    <select class="form-select form-select-sm" name="calificacion" required>
                                                        <option value="">Seleccionar...</option>
                                                        <option value="5">5 - Excelente</option>
                                                        <option value="4">4 - Muy bueno</option>
                                                        <option value="3">3 - Bueno</option>
                                                        <option value="2">2 - Regular</option>
                                                        <option value="1">1 - Malo</option>
                                                    </select>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Comentario (opcional)</label>
                                                    <textarea class="form-control form-control-sm" name="comentario" rows="3" maxlength="1000" placeholder="Comparte tu experiencia..."></textarea>
                                                </div>
                                                <button type="submit" class="btn btn-sm btn-primary">Publicar reseña</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header bg-warning text-dark">
                    <h5>Estado del Pedido</h5>
                </div>
                <div class="card-body">
                    <?php if ($estadoActualizado): ?>
                        <div class="alert alert-success py-2">Estado actualizado.</div>
                        <?php if (($pedido['estado'] ?? '') === 'completado'): ?>
                            <div class="alert alert-info py-2">El cliente ya puede reseñar los productos de este pedido.</div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($esEmpresaPropietaria): ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <div class="mb-3">
                                <label>Estado Actual:</label>
                                <select name="estado" class="form-select" onchange="this.form.submit()">
                                    <option value="pendiente" <?= $pedido['estado'] == 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                    <option value="procesando" <?= $pedido['estado'] == 'procesando' ? 'selected' : '' ?>>Procesando</option>
                                    <option value="completado" <?= $pedido['estado'] == 'completado' ? 'selected' : '' ?>>Completado</option>
                                    <option value="cancelado" <?= $pedido['estado'] == 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                                </select>
                            </div>
                        </form>
                    <?php else: ?>
                        <p><strong>Estado actual:</strong> <?= htmlspecialchars(ucfirst($pedido['estado'])) ?></p>
                        <p class="text-muted small mb-0">Solo la empresa puede modificar el estado del pedido.</p>
                    <?php endif; ?>

                    <hr>
                    <h6>Contacto con Cliente</h6>
                    <?php
                    if ($pedido['metodo_contacto'] == 'whatsapp') {
                        $whatsappCliente = $detalleInfo['whatsapp'] ?? '';
                        $whatsappClienteUrl = empresa_whatsapp_url($whatsappCliente);
                        echo '<p>📱 <strong>WhatsApp:</strong> ' . htmlspecialchars($whatsappCliente ?: 'No disponible') . '</p>';
                        if ($whatsappClienteUrl) {
                            echo '<a href="' . htmlspecialchars($whatsappClienteUrl) . '" target="_blank" rel="noopener noreferrer" class="btn btn-success btn-sm">Enviar WhatsApp</a>';
                        }
                    } elseif ($pedido['metodo_contacto'] == 'correo') {
                        $emailCliente = $detalleInfo['email'] ?? '';
                        echo '<p>📧 <strong>Email:</strong> ' . htmlspecialchars($emailCliente ?: 'No disponible') . '</p>';
                        if ($emailCliente !== '') {
                            echo '<a href="mailto:' . htmlspecialchars($emailCliente) . '" class="btn btn-primary btn-sm">Enviar Email</a>';
                        }
                    } else {
                        $telefonoCliente = $detalleInfo['telefono'] ?? '';
                        $emailCliente = $detalleInfo['email'] ?? '';
                        echo '<p>📞 <strong>Telefono:</strong> ' . htmlspecialchars($telefonoCliente ?: 'No disponible') . '</p>';
                        echo '<p>📧 <strong>Email:</strong> ' . htmlspecialchars($emailCliente ?: 'No disponible') . '</p>';
                        if ($telefonoCliente !== '') {
                            echo '<a href="tel:' . htmlspecialchars($telefonoCliente) . '" class="btn btn-info btn-sm me-2">Llamar</a>';
                        }
                        if ($emailCliente !== '') {
                            echo '<a href="mailto:' . htmlspecialchars($emailCliente) . '" class="btn btn-primary btn-sm">Email</a>';
                        }
                    }
                    ?>

                    <hr>
                    <h6>Chat Cliente - Empresa</h6>
                    <div class="chat-box" style="max-height: 250px; overflow-y: auto; background: #f8f9fa; padding: 10px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px;">
                        <?php
                        $chatStmt = $pdo->prepare("SELECT pm.*, u.nombre as autor_nombre, u.rol as autor_rol FROM pedido_mensajes pm JOIN usuarios u ON pm.usuario_id = u.id WHERE pm.pedido_id = ? ORDER BY pm.creado_at ASC");
                        $chatStmt->execute([$pedido_id]);
                        $chatMessages = $chatStmt->fetchAll();
                        if (empty($chatMessages)) {
                            echo '<p class="text-muted">No hay mensajes aún, inicia la conversación.</p>';
                        } else {
                            foreach ($chatMessages as $msg) {
                                $badge = $msg['autor_rol'] === 'empresa' ? 'secondary' : 'primary';
                                echo '<div class="mb-2"><small class="text-muted">' . htmlspecialchars($msg['autor_nombre']) . ' (' . htmlspecialchars($msg['autor_rol']) . ') • ' . date('d/m/Y H:i', strtotime($msg['creado_at'])) . '</small>'; 
                                echo '<div class="p-2 mt-1 rounded bg-' . $badge . ' text-white">' . nl2br(htmlspecialchars($msg['mensaje'])) . '</div></div>';
                            }
                        }
                        ?>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <div class="mb-2">
                            <textarea name="nuevo_mensaje" class="form-control" rows="3" placeholder="Escribe aquí tu mensaje..."></textarea>
                        </div>
                        <button class="btn btn-success btn-sm w-100" type="submit">Enviar Mensaje</button>
                    </form>

                    <hr>
                    <div class="mt-3">
                        <a href="dashboard.php" class="btn btn-secondary">← Volver al Panel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>