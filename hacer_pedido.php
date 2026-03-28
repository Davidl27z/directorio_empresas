<?php
session_start();
require 'config/db.php';
require_once 'config/empresa_contacto.php';
require_once 'config/pedido_contacto.php';
include 'templates/header.php';

empresa_ensure_contact_schema($pdo);

if (!isset($_SESSION['user_id'])) {
    // Guardar URL de retorno para después del login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: auth/login.php?msg=" . urlencode("Debes iniciar sesión para hacer un pedido"));
    exit;
}

// Obtener datos del usuario que hace el pedido
$stmt = $pdo->prepare("SELECT nombre, email FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$usuario = $stmt->fetch();

$empresa_id = $_GET['empresa_id'] ?? 0;

// Verificar que hay productos de esta empresa en el carrito
if (!isset($_SESSION['carrito']) || empty($_SESSION['carrito'])) {
    header("Location: carrito.php");
    exit;
}

$productos_empresa = $_SESSION['carrito'][$empresa_id] ?? [];

if (empty($productos_empresa)) {
    header("Location: carrito.php");
    exit;
}

// Calcular total
$total = 0;
foreach ($productos_empresa as $item) {
    $total += $item['precio'] * $item['cantidad'];
}

// Obtener info de la empresa
$stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
$stmt->execute([$empresa_id]);
$empresa = $stmt->fetch();

if (!$empresa) {
    die("Empresa no encontrada");
}

$metodos_disponibles = empresa_deserialize_contact_methods($empresa['medios_contacto_pedido'] ?? '');

// Procesar pedido
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $metodo_contacto = $_POST['metodo_contacto'] ?? '';
    $celular = trim($_POST['celular'] ?? '');
    $ubicacion = trim($_POST['ubicacion'] ?? '');
    $notas = trim($_POST['notas'] ?? '');

    // Validar datos de contacto obligatorios
    if (!$metodo_contacto || !$celular || !$ubicacion) {
        $error = 'Debes completar método de contacto, celular y ubicación.';
    } elseif (!in_array($metodo_contacto, $metodos_disponibles, true)) {
        $error = 'La empresa no ofrece ese medio de contacto para pedidos.';
    } else {
        $contacto_adicional = '';
        switch ($metodo_contacto) {
            case 'whatsapp':
                $contacto_adicional = trim($_POST['telefono_whatsapp'] ?? '');
                if ($contacto_adicional === '') {
                    $error = 'Debes indicar un numero de WhatsApp para este pedido.';
                }
                break;
            case 'formulario':
                $telefono_formulario = trim($_POST['telefono_formulario'] ?? '');
                $email_formulario = trim($_POST['email_formulario'] ?? '');
                $contacto_adicional = $telefono_formulario . ' | ' . $email_formulario;
                if ($telefono_formulario === '' || $email_formulario === '') {
                    $error = 'Debes completar telefono y email para este pedido.';
                }
                break;
            case 'correo':
                $contacto_adicional = trim($_POST['email_correo'] ?? '');
                if ($contacto_adicional === '') {
                    $error = 'Debes indicar un email para este pedido.';
                }
                break;
        }

        if (empty($error) && $metodo_contacto === 'correo' && !filter_var($contacto_adicional, FILTER_VALIDATE_EMAIL)) {
            $error = 'El email de contacto no es valido.';
        }

        if (empty($error) && $metodo_contacto === 'formulario' && !filter_var($email_formulario, FILTER_VALIDATE_EMAIL)) {
            $error = 'El email del formulario no es valido.';
        }

        $detalles_contacto = pedido_build_contact_details($usuario, $metodo_contacto, $celular, $ubicacion, $_POST);

        // Insertar pedido
        if (empty($error)) {
            $stmt = $pdo->prepare("INSERT INTO pedidos (usuario_id, empresa_id, total, metodo_contacto, detalles_contacto, notas) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $empresa_id, $total, $metodo_contacto, $detalles_contacto, $notas]);
            $pedido_id = $pdo->lastInsertId();

            // Insertar items del pedido
            $stmt = $pdo->prepare("INSERT INTO pedido_items (pedido_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
            foreach ($productos_empresa as $item) {
                $stmt->execute([$pedido_id, $item['id'], $item['cantidad'], $item['precio']]);
            }

            // Limpiar carrito de esta empresa
            unset($_SESSION['carrito'][$empresa_id]);

            // Notificar por correo a la empresa (siempre que tenga email configurado)
            if (!empty($empresa['email'])) {
                $subject = "Nuevo pedido #{$pedido_id} desde Directorio";
                $body = "Tienes un nuevo pedido de {$usuario['nombre']} ({$usuario['email']}).\n" .
                        "Telefono cliente: {$celular}\n" .
                        "Ubicacion: {$ubicacion}\n" .
                        "Metodo contacto: {$metodo_contacto}\n" .
                        "Total: $ " . number_format($total, 0, ',', '.') . "\n\n" .
                        "Acceder al panel para ver detalles: http://{$_SERVER['HTTP_HOST']}/directorio_empresas/user/dashboard.php";
                @mail($empresa['email'], $subject, $body, "From: no-reply@{$_SERVER['HTTP_HOST']}");
            }

            // Redirigir a página de confirmación
            header("Location: pedido_confirmado.php?pedido_id=$pedido_id");
            exit;
        }
    }
}


?>

<div class="container mt-4">
    <h2>Hacer Pedido - <?= htmlspecialchars($empresa['nombre']) ?></h2>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5>Resumen del Pedido</h5>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos_empresa as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['nombre']) ?></td>
                                    <td><?= $item['cantidad'] ?></td>
                                    <td>$ <?= number_format($item['precio'], 0, ',', '.') ?></td>
                                    <td>$ <?= number_format($item['precio'] * $item['cantidad'], 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3">Total:</th>
                                <th>$ <?= number_format($total, 0, ',', '.') ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5>Información de Contacto</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <?php if (empty($metodos_disponibles)): ?>
                        <div class="alert alert-warning">Esta empresa todavia no configuro medios de contacto para recibir pedidos.</div>
                    <?php else: ?>
                    <p>Elige como quieres que la empresa te contacte para coordinar el pago y la entrega.</p>
                    <p class="text-muted small mb-3">Opciones disponibles: <?= htmlspecialchars(implode(', ', array_map('pedido_contact_method_label', $metodos_disponibles))) ?></p>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($usuario['nombre']) ?>" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?= htmlspecialchars($usuario['email']) ?>" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Celular</label>
                            <input type="tel" name="celular" class="form-control" value="<?= htmlspecialchars($_POST['celular'] ?? '') ?>" placeholder="Ej: +549123456789" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ubicación (dirección de entrega)</label>
                            <input type="text" name="ubicacion" class="form-control" value="<?= htmlspecialchars($_POST['ubicacion'] ?? '') ?>" placeholder="Ej: Calle Falsa 123, Ciudad" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Método de Contacto:</label>
                            <select name="metodo_contacto" id="metodo_contacto" class="form-select" required onchange="mostrarCamposContacto()">
                                <option value="">Seleccionar...</option>
                                <?php foreach ($metodos_disponibles as $metodoDisponible): ?>
                                    <option value="<?= htmlspecialchars($metodoDisponible) ?>" <?= (isset($_POST['metodo_contacto']) && $_POST['metodo_contacto'] === $metodoDisponible) ? 'selected' : '' ?>><?= htmlspecialchars(pedido_contact_method_label($metodoDisponible)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="campos_contacto" style="display: none;">
                            <!-- WhatsApp -->
                            <div id="whatsapp_fields" style="display: none;">
                                <label class="form-label">Número de WhatsApp:</label>
                                <input type="tel" name="telefono_whatsapp" class="form-control" value="<?= htmlspecialchars($_POST['telefono_whatsapp'] ?? '') ?>" placeholder="Ej: +549123456789">
                            </div>

                            <!-- Formulario -->
                            <div id="formulario_fields" style="display: none;">
                                <label class="form-label">Teléfono:</label>
                                <input type="tel" name="telefono_formulario" class="form-control mb-2" value="<?= htmlspecialchars($_POST['telefono_formulario'] ?? '') ?>" placeholder="Ej: +549123456789">
                                <label class="form-label">Email:</label>
                                <input type="email" name="email_formulario" class="form-control" value="<?= htmlspecialchars($_POST['email_formulario'] ?? '') ?>" placeholder="tu@email.com">
                            </div>

                            <!-- Correo -->
                            <div id="correo_fields" style="display: none;">
                                <label class="form-label">Email:</label>
                                <input type="email" name="email_correo" class="form-control" value="<?= htmlspecialchars($_POST['email_correo'] ?? '') ?>" placeholder="tu@email.com">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notas adicionales (opcional):</label>
                            <textarea name="notas" class="form-control" rows="3" placeholder="Instrucciones especiales, direccion de entrega, etc."><?= htmlspecialchars($_POST['notas'] ?? '') ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-success w-100">Confirmar Pedido</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function mostrarCamposContacto() {
    const metodoSelect = document.getElementById('metodo_contacto');
    if (!metodoSelect) {
        return;
    }

    const metodo = metodoSelect.value;
    const campos = document.getElementById('campos_contacto');
    const whatsapp = document.getElementById('whatsapp_fields');
    const formulario = document.getElementById('formulario_fields');
    const correo = document.getElementById('correo_fields');

    campos.style.display = metodo ? 'block' : 'none';
    whatsapp.style.display = metodo === 'whatsapp' ? 'block' : 'none';
    formulario.style.display = metodo === 'formulario' ? 'block' : 'none';
    correo.style.display = metodo === 'correo' ? 'block' : 'none';
}

mostrarCamposContacto();
</script>

<?php include 'templates/footer.php'; ?>