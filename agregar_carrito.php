<?php
session_start();
require 'config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$producto_id = $_POST['producto_id'] ?? 0;
$cantidad = $_POST['cantidad'] ?? 1;

// Verificar que el producto existe
$stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ? AND disponible = TRUE");
$stmt->execute([$producto_id]);
$producto = $stmt->fetch();

if (!$producto) {
    echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
    exit;
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado', 'redirect' => 'auth/login.php']);
    exit;
}

// Inicializar carrito si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Agregar producto al carrito (agrupado por empresa)
$empresa_id = $producto['empresa_id'];
if (!isset($_SESSION['carrito'][$empresa_id])) {
    $_SESSION['carrito'][$empresa_id] = [];
}

// Verificar si el producto ya está en el carrito
$producto_encontrado = false;
foreach ($_SESSION['carrito'][$empresa_id] as &$item) {
    if ($item['id'] == $producto_id) {
        $item['cantidad'] += $cantidad;
        $producto_encontrado = true;
        break;
    }
}

// Si no está, agregarlo
if (!$producto_encontrado) {
    $_SESSION['carrito'][$empresa_id][] = [
        'id' => $producto_id,
        'nombre' => $producto['nombre'],
        'precio' => $producto['precio'],
        'cantidad' => $cantidad,
        'imagen' => $producto['imagen']
    ];
}

$cartCount = 0;
foreach ($_SESSION['carrito'] as $productosEmpresa) {
    if (!is_array($productosEmpresa)) {
        continue;
    }
    foreach ($productosEmpresa as $item) {
        $cartCount += (int) ($item['cantidad'] ?? 0);
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Producto agregado al carrito',
    'cart_count' => $cartCount,
]);
?>