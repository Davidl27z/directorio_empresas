<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get':
        $id = $_GET['id'];
        $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ? AND empresa_id IN (SELECT id FROM empresas WHERE usuario_id = ?)");
        $stmt->execute([$id, $_SESSION['user_id']]);
        echo json_encode($stmt->fetch());
        break;

    case 'save':
        $id = $_POST['producto_id'] ?? null;
        $empresa_id = $_POST['empresa_id'];
        $nombre = $_POST['nombre'];
        $descripcion = $_POST['descripcion'];
        $precio = $_POST['precio'];

        // Verificar que la empresa pertenece al usuario
        $stmt = $pdo->prepare("SELECT id FROM empresas WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$empresa_id, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            exit;
        }

        $imagen = '';
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
            $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
            $imagen = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['imagen']['tmp_name'], "../uploads/productos/$imagen");
        }

        if ($id) {
            // Update
            $stmt = $pdo->prepare("UPDATE productos SET nombre=?, descripcion=?, precio=?, imagen=IF(?!='', ?, imagen) WHERE id=?");
            $stmt->execute([$nombre, $descripcion, $precio, $imagen, $imagen, $id]);
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO productos (nombre, descripcion, precio, imagen, empresa_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nombre, $descripcion, $precio, $imagen, $empresa_id]);
        }
        break;

    case 'delete':
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ? AND empresa_id IN (SELECT id FROM empresas WHERE usuario_id = ?)");
        $stmt->execute([$id, $_SESSION['user_id']]);
        break;
}
?>