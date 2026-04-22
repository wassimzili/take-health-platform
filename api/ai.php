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
} catch (Exception $e) { }
$targets = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM nutrition_targets WHERE user_id = ? ORDER BY calculated_at DESC LIMIT 1");
    $stmt->execute([$userId]);
    $targets = $stmt->fetch() ?: null;
} catch (Exception $e) { }
if ($profile && $targets) {
    $goalLabel = match($profile['goal'] ?? 'maintain') {'lose'  => 'perdre du poids','gain'  => 'prendre du muscle',default => 'maintenir le poids'};
    $userContext = sprintf(
        "Profil : %d ans, %dcm, %.1fkg, IMC %.1f, objectif: %s, TDEE: %d kcal/j (protéines: %dg, glucides: %dg, lipides: %dg).%s",
        (int)($profile['age'] ?? 0),
        (int)($profile['height_cm'] ?? 0),
        (float)($profile['weight_kg'] ?? 0),
        (float)($profile['bmi'] ?? 0),
        $goalLabel,
        (int)($targets['tdee_kcal'] ?? 0),
        (int)($targets['protein_g'] ?? 0),
        (int)($targets['carbs_g'] ?? 0),
        (int)($targets['fat_g'] ?? 0),
        !empty($profile['medical_notes']) ? ' Notes médicales: ' . $profile['medical_notes'] . '.' : ''
    );
} elseif ($profile) {
    $userContext = sprintf("Profil de base : %d ans, %.1fkg, objectif: %s.",
        (int)($profile['age'] ?? 25),
        (float)($profile['weight_kg'] ?? 70),
        $profile['goal'] ?? 'maintenir le poids'
    );
} else {
    $userContext = "Profil non disponible, donne des conseils généraux équilibrés.";
}
$baseSystem = "Tu es un coach nutritionnel expert et bienveillant. Tu réponds UNIQUEMENT en français, de manière concise et pratique (3-5 phrases max). " . $userContext;
switch ($action) {
    case 'scan_photo':
        jsonResponse(true, ['food'    => 'Salade César au poulet','kcal'    => 450,'protein' => 25,'carbs'   => 15,'fat'     => 30,'note'    => 'Estimation simulée — scan visuel non disponible en mode local']);
        break;
    case 'chat':
        $data    = json_decode(file_get_contents('php://input'), true);
        $message = trim($data['message'] ?? '');
        if (!$message) jsonResponse(false, [], 'Message vide');
        $stmt = $pdo->prepare("INSERT INTO ai_chat_history (user_id, role, message) VALUES (?, 'user', ?)");
        $stmt->execute([$userId, $message]);
        $stmt = $pdo->prepare("SELECT role, message FROM ai_chat_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([$userId]);
        $history = array_reverse($stmt->fetchAll());
        $historyText = '';
        foreach ($history as $h) {
            $historyText .= ($h['role'] === 'user' ? "Utilisateur" : "Coach");
            $historyText .= ": {$h['message']}";
        }
        $system  = $baseSystem . "Historique récent de la conversation :" . $historyText;
        $reply   = callOllama($system, $message);
        if ($reply === null) {
            $reply = "Le service IA est actuellement hors ligne. Assurez-vous qu'Ollama est démarré (lancez 'ollama serve' dans votre terminal).";
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
    case 'predict_deficiencies':
        $period = in_array((int)($_GET['period'] ?? 7), [7, 30]) ? (int)$_GET['period'] : 7;
        $stmt = $pdo->prepare("SELECT log_date, total_kcal, total_protein_g, total_carbs_g, total_fat_g FROM daily_logs WHERE user_id = ? AND log_date >= DATE_SUB(CURDATE(), INTERVAL {$period} DAY) ORDER BY log_date DESC");
        $stmt->execute([$userId]);
        $logs = $stmt->fetchAll();
        if (empty($logs)) {
            $logsText = "Aucun repas enregistré sur les {$period} derniers jours.";
        } else {
            $logsText = "Données des {$period} derniers jours :\n";
            foreach ($logs as $log) {
                $logsText .= "- {$log['log_date']}: {$log['total_kcal']} kcal, protéines {$log['total_protein_g']}g, glucides {$log['total_carbs_g']}g, lipides {$log['total_fat_g']}g\n";
            }
        }
        $prompt = "Basé sur ces données nutritionnelles et le profil de l'utilisateur, identifie 3 carences nutritionnelles potentielles. " ."Pour chaque carence, fournis EXACTEMENT ce format JSON (tableau de 3 objets) :\n" ."[{\"nutrient\":\"Nom\",\"risk\":\"Faible|Moyen|Élevé\",\"trend\":\"En baisse|Stable|En hausse\",\"advice\":\"Conseil pratique\"}]\n" ."Réponds UNIQUEMENT avec le JSON, sans texte avant ou après.\n\n" . $logsText;
        $reply = callOllama($baseSystem, $prompt);
        $deficiencies = null;
        if ($reply) {
            preg_match('/\[[\s\S]*\]/', $reply, $matches);
            if ($matches) {
                $deficiencies = json_decode($matches[0], true);
            }
        }
        if (!$deficiencies) {
            $deficiencies = [['nutrient' => 'Fer',        'risk' => 'Moyen',  'trend' => 'En baisse', 'advice' => 'Consommez plus de lentilles et d\'épinards.'],['nutrient' => 'Vitamine D', 'risk' => 'Élevé',  'trend' => 'Stable',    'advice' => 'Une exposition au soleil ou des compléments sont suggérés.'],['nutrient' => 'Magnésium',  'risk' => 'Faible', 'trend' => 'En hausse', 'advice' => 'Continuez avec les amandes et le chocolat noir.']];
        }
        $stmt = $pdo->prepare("INSERT INTO ai_reports (user_id, type, content) VALUES (?, 'deficiency', ?)");
        $stmt->execute([$userId, json_encode($deficiencies)]);
        jsonResponse(true, ['data' => $deficiencies,'ai_used' => $reply !== null]);
        break;

    case 'generate_menu':
        $prompt = "Génère un plan de repas équilibré pour 7 jours (Lundi à Dimanche), adapté au profil de l'utilisateur. " ."Chaque jour doit avoir Petit-déjeuner, Déjeuner et Dîner. " ."Réponds UNIQUEMENT avec ce format JSON :\n" ."{\"Lundi\":{\"Petit-déjeuner\":\"...\",\"Déjeuner\":\"...\",\"Dîner\":\"...\"},...}\n" ."Sois précis sur les aliments et les quantités approximatives.";
        $reply = callOllama($baseSystem, $prompt);
        $menu = null;
        if ($reply) {
            preg_match('/\{[\s\S]*\}/', $reply, $matches);
            if ($matches) {
                $menu = json_decode($matches[0], true);
            }
        }
        if (!$menu) {
            $menu = [
                'Lundi'    => ['Petit-déjeuner' => 'Avoine aux baies',        'Déjeuner' => 'Poulet quinoa 200g',    'Dîner' => 'Poisson vapeur + brocolis'],
                'Mardi'    => ['Petit-déjeuner' => 'Œufs brouillés + toast',  'Déjeuner' => 'Salade de thon',        'Dîner' => 'Lentilles corail + riz'],
                'Mercredi' => ['Petit-déjeuner' => 'Smoothie vert + amandes', 'Déjeuner' => 'Pâtes complètes 150g', 'Dîner' => 'Tofu sauté + légumes'],
                'Jeudi'    => ['Petit-déjeuner' => 'Yaourt grec + fruits',    'Déjeuner' => 'Wrap dinde + avocat',   'Dîner' => 'Soupe de légumes'],
                'Vendredi' => ['Petit-déjeuner' => 'Pain complet + avocat',   'Déjeuner' => 'Riz sauté crevettes',  'Dîner' => 'Omelette + salade'],
                'Samedi'   => ['Petit-déjeuner' => 'Pancakes banane 3 pcs',   'Déjeuner' => 'Burger maison sain',   'Dîner' => 'Salade composée'],
                'Dimanche' => ['Petit-déjeuner' => 'Brunch œufs et légumes',  'Déjeuner' => 'Poulet rôti 250g',     'Dîner' => 'Légumes grillés + quinoa']
            ];
        }
        $stmt = $pdo->prepare("INSERT INTO ai_reports (user_id, type, content) VALUES (?, 'menu', ?)");
        $stmt->execute([$userId, json_encode($menu)]);
        jsonResponse(true, ['menu' => $menu,'ai_used' => $reply !== null]);
        break;
    default:
        jsonResponse(false, [], 'Action inconnue');
        break;
}
?>
