<?php
require 'config/db.php';
$stmt = $pdo->query('SELECT id, nombre, imagen FROM categorias');
foreach ($stmt as $r) {
    echo $r['id'] . ' | ' . $r['nombre'] . ' | ' . ($r['imagen'] ?? '(null)') . "\n";
}
