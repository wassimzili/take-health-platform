<?php
function calculateBMI($height_cm, $weight_kg) {
    if ($height_cm <= 0) return 0;
    $height_m = $height_cm / 100;
    return round($weight_kg / ($height_m ** 2), 1);
}
function calculateTargets($age, $height, $weight, $gender, $activity, $goal) {
    $bmr = $gender === 'male'
        ? 10 * $weight + 6.25 * $height - 5 * $age + 5
        : 10 * $weight + 6.25 * $height - 5 * $age - 161;
    $tdee = $bmr * $activity;
    if ($goal === 'lose') $tdee -= 400;
    if ($goal === 'gain') $tdee += 300;
    $tdee    = round($tdee);
    $protein = round($weight * 1.8);
    $fat     = round($tdee * 0.25 / 9);
    $carbs   = round(($tdee - $protein*4 - $fat*9) / 4);
    return [
        'tdee_kcal' => $tdee,
        'protein_g' => $protein,
        'carbs_g'   => $carbs,
        'fat_g'     => $fat
    ];
}
function jsonResponse(bool $success, array $data = [], ?string $error = null): void {
    header('Content-Type: application/json');
    $resp = ['success' => $success];
    if ($error !== null) $resp['error'] = $error;
    echo json_encode(array_merge($resp, $data), JSON_UNESCAPED_UNICODE);
    exit;
}
?>