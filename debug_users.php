<?php
require_once 'config/db.php';
$stmt = $pdo->query("SELECT id, email, is_admin FROM users");
print_r($stmt->fetchAll());
