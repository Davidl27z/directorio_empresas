<?php
session_start();
require '../config/db.php';
require_once '../config/csrf.php';

if (!isset($_SESSION['user_id'])) {
    if (!isset($base_url)) $base_url = '..';
    header("Location: " . $base_url . "/auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validar CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!csrf_validate($token)) {
        $msg = "❌ Token CSRF inválido.";
    } else {
        $nombre = trim($_POST['nombre']);
        $descripcion = trim($_POST['descripcion']);
        $categoria_id = (int)($_POST['categoria_id'] ?? 0);
        $direccion = trim($_POST['direccion']);
        $telefono = trim($_POST['telefono']);
        $email = trim($_POST['email']);
        $website = trim($_POST['website']);
        $usuario_id = $_SESSION['user_id'];
        
        // Validar email y website opcionales
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = "❌ Email no válido";
        } elseif ($website && !filter_var($website, FILTER_VALIDATE_URL)) {
            $msg = "❌ Website no válido";
        } else {
            // Manejo de logo (opcional)
            require_once '../config/upload.php';
            $logoFilename = null;
            $upload = secure_upload('logo', __DIR__ . '/../uploads/logos');
            if ($upload['ok']) {
                $logoFilename = $upload['filename'];
            } elseif ($upload['code'] ?? '' !== 'no_file') {
                // Si hay error distinto a no_file, lo guardamos en msg y no insertamos
                $msg = '❌ Error al subir logo: ' . ($upload['msg'] ?? 'Error');
            }

            if (isset($msg) && strpos($msg, '❌') === 0) {
                // noop: fall through to redirect with error
            } else {
                // Por defecto aprobada = 0 (pendiente)
                $stmt = $pdo->prepare("INSERT INTO empresas (nombre, descripcion, categoria_id, direccion, telefono, email, website, usuario_id, aprobada, logo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)");
                $stmt->execute([$nombre, $descripcion, $categoria_id, $direccion, $telefono, $email, $website, $usuario_id, $logoFilename]);

                $msg = "✅ Empresa enviada correctamente. Está pendiente de aprobación por el admin.";
            }
        }
    }
}

if (!isset($base_url)) $base_url = '..';
header("Location: " . $base_url . "/user/dashboard.php?msg=" . urlencode($msg));
exit;
?>