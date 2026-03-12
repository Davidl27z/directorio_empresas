<?php
require 'config/db.php';
$stmt = $pdo->query("SELECT id, nombre FROM categorias WHERE nombre LIKE '%Constru%' OR nombre LIKE '%Educaci%' LIMIT 10");
foreach ($stmt as $row) {
    $name = $row['nombre'];
    echo "ID: {$row['id']}\n";
    echo "Nombre: $name\n";
    echo "Hex: " . bin2hex($name) . "\n";
    echo "UTF8 decode: " . utf8_decode($name) . "\n";
    echo "Latin1->UTF8: " . mb_convert_encoding($name, 'UTF-8', 'ISO-8859-1') . "\n";
    echo "\n";
}
