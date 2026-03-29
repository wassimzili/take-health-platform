<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/ollama_config.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? '';

$profile = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch() ?: null;
} catch (Exception $e) {}

$targets = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM nutrition_targets WHERE user_id = ? ORDER BY calculated_at DESC LIMIT 1");
    $stmt->execute([$userId]);
    $targets = $stmt->fetch() ?: null;
} catch (Exception $e) {}

if ($profile && $targets) {
    $goalLabel = match($profile['goal'] ?? 'maintain') {'lose'  => 'perdre du poids','gain'  => 'prendre du muscle',default => 'maintenir le poids'};
    $userContext = sprintf("Profil : %d ans, %dcm, %.1fkg, IMC %.1f, objectif: %s, " ."TDEE: %d kcal/j (protéines: %dg, glucides: %dg, lipides: %dg).%s",
        (int)($profile['age'] ?? 0),
        (int)($profile['height_cm'] ?? 0),
        (float)($profile['weight_kg'] ?? 0),
        (float)($profile['bmi'] ?? 0),
        $goalLabel,
        (int)($targets['tdee_kcal'] ?? 0),
        (int)($targets['protein_g'] ?? 0),
        (int)($targets['carbs_g'] ?? 0),
        (int)($targets['fat_g'] ?? 0),
        !empty($profile['medical_notes'])
            ? ' Notes médicales: ' . $profile['medical_notes'] . '.' : '');
} else {
    $userContext = "Profil non disponible, donne des conseils généraux équilibrés.";
}

$baseSystem = "Tu es un coach nutritionnel expert et bienveillant. " ."Tu réponds UNIQUEMENT en français, de manière concise et pratique " ."(3-5 phrases max). " . $userContext;
switch ($action) {

    case 'chat':
        $data    = json_decode(file_get_contents('php://input'), true);
        $message = trim($data['message'] ?? '');
        if (!$message) jsonResponse(false, [], 'Message vide');

        $stmt = $pdo->prepare("INSERT INTO ai_chat_history (user_id, role, message)VALUES (?, 'user', ?)");
        $stmt->execute([$userId, $message]);

        $stmt = $pdo->prepare("SELECT role, message FROM ai_chat_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([$userId]);
        $history = array_reverse($stmt->fetchAll());

        $historyText = '';
        foreach ($history as $h) {
            $historyText .= ($h['role']==='user' ? "Utilisateur" : "Coach");
            $historyText .= ": {$h['message']}
            ";
        }

        $system = $baseSystem . "Historique récent :" . $historyText;
        $reply  = callOllama($system, $message);

        if ($reply === null) {
            $reply = "Le service IA est hors ligne. " ."Lancez 'ollama serve' dans votre terminal.";
        }

        $stmt = $pdo->prepare("INSERT INTO ai_chat_history (user_id, role, message) VALUES (?, 'bot', ?)");
        $stmt->execute([$userId, $reply]);
        jsonResponse(true, ['reply' => $reply]);
        break;

    case 'get_chat_history':
        $stmt = $pdo->prepare("SELECT role, message, created_at FROM ai_chat_history WHERE user_id = ? ORDER BY created_at ASC");
        $stmt->execute([$userId]);
        jsonResponse(true, ['history' => $stmt->fetchAll()]);
        break;

    default:
        jsonResponse(false, [], 'Action inconnue');
        break;
}
?>
