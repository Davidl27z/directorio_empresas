<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=directorio_db;charset=utf8mb4','root','');
$cols = $pdo->query('SHOW COLUMNS FROM usuarios')->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo $c['Field'] . ' (' . $c['Type'] . ')\n';
}
