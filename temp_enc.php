<?php
require 'config/db.php';
$stmt = $pdo->query('SELECT nombre FROM categorias LIMIT 5');
foreach ($stmt as $row) {
    $name = $row['nombre'];
    echo "Original: $name\n";
    echo "Hex: " . bin2hex($name) . "\n";
    echo "UTF8: " . mb_convert_encoding($name, 'UTF-8', 'UTF-8') . "\n";
    echo "Latin1->UTF8: " . mb_convert_encoding($name, 'UTF-8', 'ISO-8859-1') . "\n";
    echo "UTF8->Latin1: " . mb_convert_encoding($name, 'ISO-8859-1', 'UTF-8') . "\n";
    echo "\n";
}
