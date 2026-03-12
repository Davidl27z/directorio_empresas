<?php
session_start();
require '../config/db.php';
session_destroy();
if (!isset($base_url)) $base_url = '';
header("Location: " . $base_url . "/index.php");
exit;
?>