<?php
session_start();
require '../config/db.php';
require_once '../config/csrf.php';

if (!isset($_SESSION['user_id'])) {
    if (!isset($base_url)) $base_url = '..';
    header("Location: " . $base_url . "/auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Método no permitido');
}

$token = $_POST['csrf_token'] ?? '';
if (!csrf_validate($token)) {
    die('Token CSRF inválido');
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$usuario_id = $_SESSION['user_id'];

// Verificar que la empresa sea del usuario y eliminar
$s = $pdo->prepare("SELECT logo FROM empresas WHERE id = ? AND usuario_id = ?");
$s->execute([$id, $usuario_id]);
$r = $s->fetch();
if ($r && !empty($r['logo'])) {
    $file = __DIR__ . '/../uploads/logos/' . $r['logo'];
    if (is_file($file)) @unlink($file);
}
$stmt = $pdo->prepare("DELETE FROM empresas WHERE id = ? AND usuario_id = ?");
$stmt->execute([$id, $usuario_id]);

if (!isset($base_url)) $base_url = '..';
header("Location: " . $base_url . "/user/dashboard.php?msg=" . urlencode("✅ Empresa eliminada"));
exit;
?>