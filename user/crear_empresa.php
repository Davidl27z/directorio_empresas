<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $categoria_id = $_POST['categoria_id'];
    $direccion = $_POST['direccion'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];
    $website = $_POST['website'];
    $usuario_id = $_SESSION['user_id'];
    
    // Por defecto aprobada = 0 (pendiente)
    $stmt = $pdo->prepare("INSERT INTO empresas (nombre, descripcion, categoria_id, direccion, telefono, email, website, usuario_id, aprobada) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
    $stmt->execute([$nombre, $descripcion, $categoria_id, $direccion, $telefono, $email, $website, $usuario_id]);
    
    $msg = "✅ Empresa enviada correctamente. Está pendiente de aprobación por el admin.";
}

header("Location: dashboard.php?msg=" . urlencode($msg));
exit;
?>