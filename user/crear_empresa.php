<?php
session_start();
require '../config/db.php';
require_once '../config/csrf.php';
require_once '../config/empresa_contacto.php';

empresa_ensure_contact_schema($pdo);

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
        $website = empresa_normalize_website($_POST['website'] ?? '');
        $instagram = empresa_normalize_instagram($_POST['instagram'] ?? '');
        $tiktok = empresa_normalize_tiktok($_POST['tiktok'] ?? '');
        $facebook = empresa_normalize_facebook($_POST['facebook'] ?? '');
        $whatsapp = empresa_normalize_whatsapp($_POST['whatsapp'] ?? '');
        $medios_contacto = empresa_normalize_contact_methods($_POST['medios_contacto'] ?? []);
        $usuario_id = $_SESSION['user_id'];
        
        // Validar email y website opcionales
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = "❌ Email no válido";
        } elseif ($website && !filter_var($website, FILTER_VALIDATE_URL)) {
            $msg = "❌ Website no válido";
        } elseif ($whatsapp !== '' && strlen(empresa_whatsapp_digits($whatsapp)) < 8) {
            $msg = "❌ WhatsApp no válido";
        } elseif (empty($medios_contacto)) {
            $msg = "❌ Debes seleccionar al menos un medio de contacto para pedidos";
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
                $stmt = $pdo->prepare("INSERT INTO empresas (nombre, descripcion, categoria_id, direccion, telefono, email, website, instagram, tiktok, facebook, whatsapp, medios_contacto_pedido, usuario_id, aprobada, logo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)");
                $stmt->execute([$nombre, $descripcion, $categoria_id, $direccion, $telefono, $email, $website, $instagram, $tiktok, $facebook, $whatsapp, empresa_serialize_contact_methods($medios_contacto), $usuario_id, $logoFilename]);

                $msg = "✅ Empresa enviada correctamente. Está pendiente de aprobación por el admin.";
            }
        }
    }
}

if (!isset($base_url)) $base_url = '..';
header("Location: " . $base_url . "/user/dashboard.php?msg=" . urlencode($msg));
exit;
?>