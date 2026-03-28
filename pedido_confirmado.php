<?php
session_start();
require 'config/db.php';
require_once 'config/empresa_contacto.php';
require_once 'config/pedido_contacto.php';
include 'templates/header.php';

empresa_ensure_contact_schema($pdo);

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

$pedido_id = $_GET['pedido_id'] ?? 0;

// Obtener pedido
$stmt = $pdo->prepare("
    SELECT p.*, e.nombre as empresa_nombre, e.telefono, e.email, e.website, e.whatsapp, e.instagram
    FROM pedidos p
    JOIN empresas e ON p.empresa_id = e.id
    WHERE p.id = ? AND p.usuario_id = ?
");
$stmt->execute([$pedido_id, $_SESSION['user_id']]);
$pedido = $stmt->fetch();

if (!$pedido) {
    die("Pedido no encontrado");
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

$contactoPedido = pedido_decode_contact_details($pedido['detalles_contacto'] ?? '');
$empresaWhatsappUrl = empresa_whatsapp_url($pedido['whatsapp'] ?? '', 'Hola, quiero consultar sobre mi pedido #' . $pedido_id . '.');
$empresaInstagramUrl = empresa_instagram_url($pedido['instagram'] ?? '');
?>

<div class="container mt-4">
    <div class="alert alert-success">
        <h4>✅ Pedido Confirmado</h4>
        <p>Tu pedido #<?= $pedido_id ?> ha sido registrado exitosamente.</p>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5>Detalles del Pedido</h5>
                </div>
                <div class="card-body">
                    <p><strong>Empresa:</strong> <?= htmlspecialchars($pedido['empresa_nombre']) ?></p>
                    <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) ?></p>
                    <p><strong>Método de Contacto:</strong> 
                        <?= htmlspecialchars(pedido_contact_method_label($pedido['metodo_contacto'])) ?>
                    </p>
                    <p><strong>Detalles de Contacto:</strong> <?= htmlspecialchars(pedido_contact_detail_text($contactoPedido)) ?></p>
                    <?php if ($pedido['notas']): ?>
                        <p><strong>Notas:</strong> <?= nl2br(htmlspecialchars($pedido['notas'])) ?></p>
                    <?php endif; ?>

                    <hr>
                    <h6>Productos:</h6>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['producto_nombre']) ?></td>
                                    <td><?= $item['cantidad'] ?></td>
                                    <td>$ <?= number_format($item['precio_unitario'], 0, ',', '.') ?></td>
                                    <td>$ <?= number_format($item['precio_unitario'] * $item['cantidad'], 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3">Total:</th>
                                <th>$ <?= number_format($pedido['total'], 0, ',', '.') ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5>¿Qué sigue?</h5>
                </div>
                <div class="card-body">
                    <p>La empresa te contactará pronto a través del método que elegiste para coordinar:</p>
                    <ul>
                        <li>💰 Forma de pago</li>
                        <li>📍 Lugar de entrega</li>
                        <li>⏰ Fecha y hora</li>
                    </ul>

                    <hr>
                    <h6>Contacto de la Empresa:</h6>
                    <?php if ($pedido['telefono']): ?>
                        <p>📞 <strong>Teléfono:</strong> <?= htmlspecialchars($pedido['telefono']) ?></p>
                    <?php endif; ?>
                    <?php if ($pedido['email']): ?>
                        <p>📧 <strong>Email:</strong> <?= htmlspecialchars($pedido['email']) ?></p>
                    <?php endif; ?>
                    <?php if ($pedido['website']): ?>
                        <p>🌐 <strong>Website:</strong> <a href="<?= htmlspecialchars($pedido['website']) ?>" target="_blank">Visitar</a></p>
                    <?php endif; ?>
                    <?php if ($empresaWhatsappUrl): ?>
                        <p>📱 <strong>WhatsApp:</strong> <?= htmlspecialchars($pedido['whatsapp']) ?></p>
                    <?php endif; ?>
                    <?php if ($empresaInstagramUrl): ?>
                        <p>📸 <strong>Instagram:</strong> <a href="<?= htmlspecialchars($empresaInstagramUrl) ?>" target="_blank" rel="noopener noreferrer">@<?= htmlspecialchars(empresa_normalize_instagram($pedido['instagram'])) ?></a></p>
                    <?php endif; ?>

                    <div class="mt-3">
                        <?php if ($empresaWhatsappUrl): ?>
                            <a href="<?= htmlspecialchars($empresaWhatsappUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-success">WhatsApp</a>
                        <?php endif; ?>
                        <a href="index.php" class="btn btn-primary">Seguir Comprando</a>
                        <a href="user/dashboard.php" class="btn btn-outline-secondary">Mi Panel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>