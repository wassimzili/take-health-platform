<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();

if (!isset($_SESSION['is_admin']) || ($_SESSION['is_admin'] != true && $_SESSION['is_admin'] != 1)) {
    jsonResponse(false, [], 'Accès refusé');
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_users':
        try {
            $stmt = $pdo->query("SELECT u.id, u.prenom, u.nom, u.email, u.created_at, p.bmi, p.goal 
                                FROM users u 
                                LEFT JOIN profiles p ON u.id = p.user_id 
                                WHERE u.is_admin = 0");
            $users = $stmt->fetchAll();
            jsonResponse(true, ['users' => $users]);
        } catch (Exception $e) {
            jsonResponse(false, [], $e->getMessage());
        }
        break;

    case 'get_user_history':
        $userId = $_GET['user_id'] ?? null;
        if (!$userId) jsonResponse(false, [], 'User ID missing');
        try {
            // Get last 15 days of meals
            $stmt = $pdo->prepare("SELECT m.*, dl.log_date 
                                  FROM meals m 
                                  JOIN daily_logs dl ON m.log_id = dl.id 
                                  WHERE m.user_id = ? 
                                  AND dl.log_date >= DATE_SUB(CURDATE(), INTERVAL 15 DAY)
                                  ORDER BY dl.log_date DESC, m.added_at DESC");
            $stmt->execute([$userId]);
            $meals = $stmt->fetchAll();
            jsonResponse(true, ['meals' => $meals]);
        } catch (Exception $e) {
            jsonResponse(false, [], $e->getMessage());
        }
        break;

    case 'delete_user':
        $userId = $_GET['user_id'] ?? null;
        if (!$userId) jsonResponse(false, [], 'User ID missing');
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND is_admin = 0");
            $stmt->execute([$userId]);
            jsonResponse(true);
        } catch (Exception $e) {
            jsonResponse(false, [], $e->getMessage());
        }
        break;

    default:
        jsonResponse(false, [], 'Action inconnue');
        break;
}
