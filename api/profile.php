<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();
if (!isset($_SESSION['user_id'])) jsonResponse(false, [], 'Non autorisé');
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
$stmt->execute([$userId]);
$profile = $stmt->fetch();
$stmt = $pdo->prepare("SELECT * FROM nutrition_targets WHERE user_id = ? ORDER BY calculated_at DESC LIMIT 1");
$stmt->execute([$userId]);
$targets = $stmt->fetch();
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT m.* FROM meals m JOIN daily_logs l ON m.log_id = l.id WHERE l.user_id = ? AND l.log_date = ? ORDER BY m.added_at DESC");
$stmt->execute([$userId, $today]);
$meals = $stmt->fetchAll();
jsonResponse(true, ['user_name' => $_SESSION['user_name'],'profile' => $profile,'targets' => $targets,'meals' => $meals]);
?>