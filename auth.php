<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();
$action = $_GET['action'] ?? '';
switch ($action) {
    //Inscription
    case 'register':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) jsonResponse(false, [], 'No data');
        try {
            $pdo->beginTransaction();
            //compte
            $stmt = $pdo->prepare("INSERT INTO users (prenom, nom, email, password_hash)VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $data['prenom'],
                $data['nom'],
                strtolower($data['email']),
                password_hash($data['pass'], PASSWORD_DEFAULT)
            ]);
            $userId = $pdo->lastInsertId();
            //Calculer
            $bmi     = calculateBMI($data['height'], $data['weight']);
            $targets = calculateTargets($data['age'], $data['height'], $data['weight'],$data['gender'], $data['activity'], $data['goal']);
            //Enregistrer
            $stmt = $pdo->prepare(      "INSERT INTO profiles (user_id, age, height_cm, weight_kg, gender,activity_factor, goal, medical_notes, bmi)VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $userId,
                $data['age'],
                $data['height'],
                $data['weight'],
                $data['gender'],
                $data['activity'],
                $data['goal'],
                $data['medical'] ?? '',
                $bmi
            ]);
            //Enregistrer les objectifs
            $stmt = $pdo->prepare("INSERT INTO nutrition_targets (user_id, tdee_kcal, protein_g, carbs_g, fat_g) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $userId,
                $targets['tdee_kcal'],
                $targets['protein_g'],
                $targets['carbs_g'],
                $targets['fat_g']
            ]);
            $pdo->commit();
            jsonResponse(true);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, [], $e->getMessage());
        }
        break;
    //Connexion
    case 'login':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([strtolower($data['email'])]);
        $user = $stmt->fetch();
        if ($user && password_verify($data['pass'], $user['password_hash'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['prenom'];
            jsonResponse(true, ['user' => ['id' => $user['id'], 'prenom' => $user['prenom']]]);
        } else {
            jsonResponse(false, [], 'Identifiants incorrects');
        }
        break;
    //deconnexion
    case 'logout':
        session_destroy();
        jsonResponse(true);
        break;
    default:
        jsonResponse(false, [], 'Action inconnue');
        break;
}
?>
