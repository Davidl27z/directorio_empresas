<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$id = $_GET['id'] ?? 0;
$usuario_id = $_SESSION['user_id'];

// Verificar que la empresa sea del usuario
$stmt = $pdo->prepare("DELETE FROM empresas WHERE id = ? AND usuario_id = ?");
$stmt->execute([$id, $usuario_id]);

header("Location: dashboard.php?msg=" . urlencode("✅ Empresa eliminada"));
exit;
?>