<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/ollama_config.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, [], 'Non autorisé');
}
$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';
switch ($action) {
    case 'add':
        $data        = json_decode(file_get_contents('php://input'), true);
        $description = trim($data['description'] ?? '');
        $mealType    = trim($data['type'] ?? 'collation');

        if (!$description) jsonResponse(false, [], 'Description manquante');
        $prompt = "Estime les valeurs nutritionnelles pour : \"$description\". " ."Réponds UNIQUEMENT avec ce JSON (entiers, pas de virgules) :\n" ."{\"kcal\":0,\"protein\":0,\"carbs\":0,\"fat\":0}";
        $aiReply = callOllama("Tu es un expert en nutrition. Tu fournis des estimations précises en JSON uniquement, en français.", $prompt);
        $kcal = $protein = $carbs = $fat = null;
        if ($aiReply) {
            preg_match('/\{[\s\S]*?\}/', $aiReply, $matches);
            if ($matches) {
                $macros  = json_decode($matches[0], true);
                $kcal    = isset($macros['kcal'])    ? (int)$macros['kcal']    : null;
                $protein = isset($macros['protein']) ? (int)$macros['protein'] : null;
                $carbs   = isset($macros['carbs'])   ? (int)$macros['carbs']   : null;
                $fat     = isset($macros['fat'])      ? (int)$macros['fat']    : null;
            }
        }
        if ($kcal === null) {
            $qty     = 100;
            if (preg_match('/(\d+)\s*g/', $description, $m)) {
                $qty = (int)$m[1];
            }
            $kcal    = (int)($qty * 1.5);
            $protein = (int)($qty * 0.15);
            $carbs   = (int)($qty * 0.20);
            $fat     = (int)($qty * 0.05);
        }
        try {
            $pdo->beginTransaction();
            $today = date('Y-m-d');
            $stmt = $pdo->prepare("SELECT id FROM daily_logs WHERE user_id = ? AND log_date = ?");
            $stmt->execute([$userId, $today]);
            $log = $stmt->fetch();
            if (!$log) {
                $stmt = $pdo->prepare("INSERT INTO daily_logs (user_id, log_date, total_kcal, total_protein_g, total_carbs_g, total_fat_g, status) VALUES (?, ?, 0, 0, 0, 0, 'pending')");
                $stmt->execute([$userId, $today]);
                $logId = $pdo->lastInsertId();
            } else {
                $logId = $log['id'];
            }
            $stmt = $pdo->prepare("INSERT INTO meals (log_id, user_id, meal_type, food_description, kcal, protein_g, carbs_g, fat_g, estimated_by_ai) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$logId, $userId, $mealType, $description, $kcal, $protein, $carbs, $fat]);
            $mealId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("UPDATE daily_logs SET total_kcal      = total_kcal + ?,total_protein_g = total_protein_g + ?,total_carbs_g   = total_carbs_g + ?,total_fat_g     = total_fat_g + ? WHERE id = ?");
            $stmt->execute([$kcal, $protein, $carbs, $fat, $logId]);
            $pdo->commit();
            $stmt = $pdo->prepare("SELECT * FROM meals WHERE id = ?");
            $stmt->execute([$mealId]);
            $newMeal = $stmt->fetch();
            jsonResponse(true, ['new_meal' => $newMeal,'ai_used'  => $aiReply !== null]);

        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, [], $e->getMessage());
        }
        break;
    case 'analyze':
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("SELECT * FROM daily_logs WHERE user_id = ? AND log_date = ?");
        $stmt->execute([$userId, $today]);
        $log = $stmt->fetch();
        if (!$log) jsonResponse(false, [], "Aucun repas enregistré aujourd'hui");
        $stmt = $pdo->prepare("SELECT * FROM nutrition_targets WHERE user_id = ? ORDER BY calculated_at DESC LIMIT 1");
        $stmt->execute([$userId]);
        $targets = $stmt->fetch();
        if (!$targets) jsonResponse(false, [], 'Objectifs nutritionnels non trouvés');
        $kcal_pct = $targets['tdee_kcal'] > 0
            ? ($log['total_kcal'] / $targets['tdee_kcal']) * 100
            : 0;

        $fat_pct = $log['total_kcal'] > 0
            ? ($log['total_fat_g'] * 9 / $log['total_kcal']) * 100
            : 0;
        $status  = 'Suffisant';
        $title   = 'Alimentation équilibrée';

        if ($kcal_pct < 80) {
            $status = 'Insuffisant';
            $title  = 'Apport calorique trop faible';
        } elseif ($kcal_pct > 115) {
            $status = 'Excessif';
            $title  = 'Apport calorique excessif';
        } elseif ($fat_pct > 35) {
            $status = 'Trop gras';
            $title  = 'Alimentation trop riche en graisses';
        }

        $summary = sprintf(
            "Vous avez consommé %d kcal sur un objectif de %d kcal (%.0f%%). Protéines : %dg / %dg | Glucides : %dg / %dg | Lipides : %dg / %dg.",
            $log['total_kcal'],   $targets['tdee_kcal'],  $kcal_pct,
            $log['total_protein_g'], $targets['protein_g'],
            $log['total_carbs_g'],   $targets['carbs_g'],
            $log['total_fat_g'],     $targets['fat_g']
        );

        $tips = [
            'Mangez plus de légumes verts à chaque repas',
            'Buvez au moins 2L d\'eau par jour',
            'Évitez les sucres raffinés en soirée'
        ];
        if ($kcal_pct < 80)  $tips[] = 'Ajoutez une collation riche en protéines (ex: yaourt grec, œuf)';
        if ($fat_pct > 35)   $tips[] = 'Réduisez les fritures et préférez les cuissons vapeur ou au four';
        if ($kcal_pct > 115) $tips[] = 'Réduisez les portions et évitez les calories liquides (sodas, jus)';

        $tipsStr = implode(';', $tips);

        $stmt = $pdo->prepare("INSERT INTO ai_analyses (log_id, user_id, status, title, summary, tips) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$log['id'], $userId, $status, $title, $summary, $tipsStr]);

        $stmt = $pdo->prepare("UPDATE daily_logs SET status = ? WHERE id = ?");
        $stmt->execute([$status, $log['id']]);

        jsonResponse(true, ['analysis' => [
            'status'  => $status,
            'title'   => $title,
            'summary' => $summary,
            'tips'    => $tips
        ]]);
        break;

    default:
        jsonResponse(false, [], 'Action inconnue');
        break;
}
?>
