<?php
require 'config/db.php';
$rows = $pdo->query('SHOW VARIABLES LIKE "character_set_%"')->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo $r['Variable_name'] . '=' . $r['Value'] . "\n";
}
echo "\n";
$r = $pdo->query('SHOW CREATE TABLE categorias')->fetch(PDO::FETCH_ASSOC);
echo $r['Create Table'];
