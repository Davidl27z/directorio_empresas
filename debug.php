<?php
// Mostrar todos los errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Probar conexión
echo "1. Verificando conexión...<br>";
try {
    $pdo = new PDO("mysql:host=localhost;dbname=directorio_db;charset=utf8", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Conexión a BD exitosa<br>";
} catch (PDOException $e) {
    die("❌ Error de conexión: " . $e->getMessage());
}

// Probar consulta
echo "2. Consultando categorías...<br>";
$stmt = $pdo->query("SELECT * FROM categorias");
$cats = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "✅ Categorías encontradas: " . count($cats) . "<br>";
?>