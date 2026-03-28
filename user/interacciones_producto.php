<?php
session_start();
require '../config/db.php';
require_once '../config/csrf.php';
require_once '../config/usuario_interacciones.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesion.']);
    exit;
}

if (($_SESSION['user_role'] ?? '') !== 'cliente') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Solo clientes pueden realizar esta accion.']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!csrf_validate($token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalido.']);
    exit;
}

$action = $_POST['action'] ?? '';
$productoId = (int) ($_POST['producto_id'] ?? 0);
$usuarioId = (int) $_SESSION['user_id'];

if ($productoId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Producto invalido.']);
    exit;
}

usuario_interacciones_ensure_schema($pdo);

$stmt = $pdo->prepare('SELECT id FROM productos WHERE id = ? AND disponible = TRUE');
$stmt->execute([$productoId]);
if (!$stmt->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Producto no disponible.']);
    exit;
}

function toggle_user_product_table(PDO $pdo, string $table, int $usuarioId, int $productoId): bool
{
    $existsStmt = $pdo->prepare("SELECT id FROM {$table} WHERE usuario_id = ? AND producto_id = ?");
    $existsStmt->execute([$usuarioId, $productoId]);
    $recordId = $existsStmt->fetchColumn();

    if ($recordId) {
        $deleteStmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ?");
        $deleteStmt->execute([$recordId]);
        return false;
    }

    $insertStmt = $pdo->prepare("INSERT INTO {$table} (usuario_id, producto_id) VALUES (?, ?)");
    $insertStmt->execute([$usuarioId, $productoId]);
    return true;
}

try {
    if ($action === 'toggle_like') {
        $active = toggle_user_product_table($pdo, 'producto_likes', $usuarioId, $productoId);
    } elseif ($action === 'toggle_save') {
        $active = toggle_user_product_table($pdo, 'producto_guardados', $usuarioId, $productoId);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Accion no soportada.']);
        exit;
    }

    $likesStmt = $pdo->prepare('SELECT COUNT(*) FROM producto_likes WHERE producto_id = ?');
    $likesStmt->execute([$productoId]);
    $likes = (int) $likesStmt->fetchColumn();

    $savedStmt = $pdo->prepare('SELECT COUNT(*) FROM producto_guardados WHERE producto_id = ?');
    $savedStmt->execute([$productoId]);
    $guardados = (int) $savedStmt->fetchColumn();

    $userLikesStmt = $pdo->prepare('SELECT COUNT(*) FROM producto_likes WHERE usuario_id = ?');
    $userLikesStmt->execute([$usuarioId]);
    $userLikes = (int) $userLikesStmt->fetchColumn();

    $userSavedStmt = $pdo->prepare('SELECT COUNT(*) FROM producto_guardados WHERE usuario_id = ?');
    $userSavedStmt->execute([$usuarioId]);
    $userGuardados = (int) $userSavedStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'active' => $active,
        'likes' => $likes,
        'guardados' => $guardados,
        'user_likes' => $userLikes,
        'user_guardados' => $userGuardados,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo completar la accion.']);
}
