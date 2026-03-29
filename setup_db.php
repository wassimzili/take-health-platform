<?php
require_once __DIR__ . '/db.php';
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
$tables = [
    "CREATE TABLE IF NOT EXISTS `users` (
        `id`            INT AUTO_INCREMENT PRIMARY KEY,
        `prenom`        VARCHAR(50)  NOT NULL,
        `nom`           VARCHAR(50)  NOT NULL,
        `email`         VARCHAR(100) NOT NULL UNIQUE,
        `password_hash` VARCHAR(255) NOT NULL,
        `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `profiles` (
        `id`              INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`         INT NOT NULL UNIQUE,
        `age`             TINYINT UNSIGNED NOT NULL,
        `height_cm`       SMALLINT UNSIGNED NOT NULL,
        `weight_kg`       DECIMAL(5,2) NOT NULL,
        `gender`          ENUM('male','female') NOT NULL,
        `activity_factor` DECIMAL(4,3) NOT NULL DEFAULT 1.55,
        `goal`            ENUM('maintain','lose','gain') NOT NULL DEFAULT 'maintain',
        `medical_notes`   TEXT,
        `bmi`             DECIMAL(4,1),
        `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `nutrition_targets` (
        `id`            INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`       INT NOT NULL,
        `tdee_kcal`     SMALLINT UNSIGNED NOT NULL,
        `protein_g`     SMALLINT UNSIGNED NOT NULL,
        `carbs_g`       SMALLINT UNSIGNED NOT NULL,
        `fat_g`         SMALLINT UNSIGNED NOT NULL,
        `calculated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `daily_logs` (
        `id`              INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`         INT NOT NULL,
        `log_date`        DATE NOT NULL,
        `total_kcal`      SMALLINT UNSIGNED DEFAULT 0,
        `total_protein_g` SMALLINT UNSIGNED DEFAULT 0,
        `total_carbs_g`   SMALLINT UNSIGNED DEFAULT 0,
        `total_fat_g`     SMALLINT UNSIGNED DEFAULT 0,
        `status`          VARCHAR(30) DEFAULT 'pending',
        UNIQUE KEY `unique_user_date` (`user_id`, `log_date`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `meals` (
        `id`               INT AUTO_INCREMENT PRIMARY KEY,
        `log_id`           INT NOT NULL,
        `user_id`          INT NOT NULL,
        `meal_type`        VARCHAR(50) NOT NULL,
        `food_description` TEXT NOT NULL,
        `kcal`             SMALLINT UNSIGNED DEFAULT 0,
        `protein_g`        SMALLINT UNSIGNED DEFAULT 0,
        `carbs_g`          SMALLINT UNSIGNED DEFAULT 0,
        `fat_g`            SMALLINT UNSIGNED DEFAULT 0,
        `estimated_by_ai`  TINYINT(1) DEFAULT 0,
        `added_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`log_id`)  REFERENCES `daily_logs`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `food_items` (
        `id`               INT AUTO_INCREMENT PRIMARY KEY,
        `name`             VARCHAR(100) NOT NULL,
        `category`         VARCHAR(50)  NOT NULL COMMENT 'ex: petit-dejeuner, dejeuner, diner, collation',
        `portion_size`     VARCHAR(30)  DEFAULT '100g',
        `kcal_per_portion` SMALLINT UNSIGNED NOT NULL,
        `protein_g`        DECIMAL(6,2) DEFAULT 0,
        `carbs_g`          DECIMAL(6,2) DEFAULT 0,
        `fat_g`            DECIMAL(6,2) DEFAULT 0,
        `fiber_g`          DECIMAL(6,2) DEFAULT 0,
        `vitamins`         VARCHAR(150) DEFAULT NULL COMMENT 'Vitamines principales',
        `minerals`         VARCHAR(150) DEFAULT NULL COMMENT 'Minéraux principaux',
        `is_healthy`       TINYINT(1) DEFAULT 1,
        `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_category` (`category`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `ai_chat_history` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`    INT NOT NULL,
        `role`       ENUM('user','bot') NOT NULL,
        `message`    TEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_user_created` (`user_id`, `created_at`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `ai_reports` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`    INT NOT NULL,
        `type`       ENUM('deficiency','menu') NOT NULL,
        `content`    LONGTEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `ai_analyses` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `log_id`     INT NOT NULL,
        `user_id`    INT NOT NULL,
        `status`     VARCHAR(30) NOT NULL,
        `title`      VARCHAR(200),
        `summary`    TEXT,
        `tips`       TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`log_id`)  REFERENCES `daily_logs`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
$foodItems = [
    ['Œuf brouillé',         'petit-dejeuner', '100g', 155, 13.6, 1.1,  11.0, 0.0,  'Vitamine D, B12', 'Fer, Choline'],
    ['Avoine nature',         'petit-dejeuner', '100g', 389, 16.9, 66.3,  6.9, 10.6, 'Vitamine B1',     'Manganèse, Phosphore'],
    ['Pain complet',          'petit-dejeuner', '100g', 265,  8.7, 49.2,  3.3,  6.3, 'Vitamines B',     'Fer, Magnésium'],
    ['Yaourt grec nature',    'petit-dejeuner', '100g',  59, 10.0,  3.3,  0.4,  0.0, 'Vitamine B12',    'Calcium, Probiotiques'],
    ['Fromage blanc 0%',      'petit-dejeuner', '100g',  45,  8.0,  4.0,  0.2,  0.0, 'Vitamine B2',     'Calcium, Phosphore'],
    ['Banane',                'petit-dejeuner', '100g',  89,  1.1, 22.8,  0.3,  2.6, 'Vitamine B6, C',  'Potassium, Magnésium'],
    ['Lait demi-écrémé',      'petit-dejeuner', '200ml', 95,  6.8, 10.0,  3.5,  0.0, 'Vitamine D, B12', 'Calcium, Potassium'],
    ['Flocons d\'avoine',     'petit-dejeuner', '80g',  312, 10.7, 55.7,  5.1,  8.0, 'Vitamine B1',     'Fer, Zinc'],
    ['Poulet grillé',         'dejeuner',       '150g', 248, 46.5,  0.0,  5.4,  0.0, 'Vitamine B6, B3', 'Phosphore, Sélénium'],
    ['Lentilles cuites',      'dejeuner',       '150g', 173, 13.5, 30.0,  0.6,  7.9, 'Acide folique',   'Fer, Potassium'],
    ['Riz complet cuit',      'dejeuner',       '150g', 166,  3.9, 34.6,  1.5,  1.8, 'Vitamine B1',     'Magnésium, Phosphore'],
    ['Brocoli vapeur',        'dejeuner',       '150g',  51,  4.2,  6.6,  0.6,  2.6, 'Vitamine C, K',   'Calcium, Potassium'],
    ['Saumon grillé',         'dejeuner',       '150g', 309, 33.2,  0.0, 19.5,  0.0, 'Vitamine D, B12', 'Oméga-3, Sélénium'],
    ['Tofu ferme',            'dejeuner',       '150g', 144, 17.3,  3.5,  8.7,  0.3, 'Isoflavones',     'Calcium, Magnésium'],
    ['Quinoa cuit',           'dejeuner',       '150g', 180,  6.7, 31.5,  2.9,  2.8, 'Vitamine B2',     'Manganèse, Phosphore'],
    ['Épinards sautés',       'dejeuner',       '100g',  23,  2.9,  3.6,  0.4,  2.2, 'Vitamine K, A',   'Fer, Calcium'],
    ['Thon en boîte',         'dejeuner',       '100g', 132, 28.0,  0.0,  1.5,  0.0, 'Vitamine D, B12', 'Sélénium, Iode'],
    ['Dinde émincée',         'dejeuner',       '150g', 228, 43.5,  0.0,  5.1,  0.0, 'Vitamine B6',     'Phosphore, Sélénium'],
    ['Pâtes complètes cuites','dejeuner',       '200g', 280, 11.0, 54.0,  1.4,  6.0, 'Vitamines B',     'Fer, Magnésium'],
    ['Patate douce cuite',    'dejeuner',       '150g', 130,  2.3, 30.9,  0.1,  3.8, 'Vitamine A, C',   'Potassium, Manganèse'],
    ['Amandes',               'collation',      '30g',  173,  6.4,  4.5, 15.0,  2.1, 'Vitamine E',      'Magnésium, Calcium'],
    ['Pomme',                 'collation',      '150g',  78,  0.4, 20.7,  0.2,  3.6, 'Vitamine C',      'Potassium'],
    ['Noix',                  'collation',      '30g',  196,  4.6,  3.9, 19.6,  2.0, 'Vitamine E',      'Oméga-3, Magnésium'],
    ['Cottage cheese',        'collation',      '100g',  98, 11.1,  3.4,  4.3,  0.0, 'Vitamine B12',    'Calcium, Sélénium'],
    ['Houmous',               'collation',      '80g',  176,  7.7, 12.3, 10.1,  3.6, 'Vitamine B6',     'Fer, Folate'],
    ['Chocolat noir 70%',     'collation',      '30g',  172,  2.5, 13.1, 12.0,  2.7, 'Vitamine E',      'Magnésium, Fer'],
    ['Kiwi',                  'collation',      '100g',  61,  1.1, 14.7,  0.5,  3.0, 'Vitamine C, K',   'Potassium, Folate'],
];
$errors = [];
$created = [];
$skipped = [];
foreach ($tables as $sql) {
    try {
        $pdo->exec($sql);
        preg_match('/`(\w+)`/', $sql, $m);
        $tableName = $m[1] ?? '?';
        $created[] = $tableName;
    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
    }
}
$foodsInserted = 0;
try {
    $count = $pdo->query("SELECT COUNT(*) FROM food_items")->fetchColumn();
    if ($count == 0) {
        $stmt = $pdo->prepare("INSERT INTO food_items
            (name, category, portion_size, kcal_per_portion, protein_g, carbs_g, fat_g, fiber_g, vitamins, minerals, is_healthy)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        foreach ($foodItems as $f) {
            $stmt->execute($f);
            $foodsInserted++;
        }
    } else {
        $skipped[] = "food_items ($count aliments déjà présents)";
    }
} catch (PDOException $e) {
    $errors[] = "Seed aliments : " . $e->getMessage();
}
$pdo->exec("SET FOREIGN_KEY_CHECKS=1");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Setup BDD — Take Health</title>
  <style>
    * { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'Segoe UI',sans-serif; background:#0f172a; color:#e2e8f0; padding:40px 20px; max-width:700px; margin:0 auto; }
    h1   { color:#38bdf8; margin-bottom:24px; font-size:1.4rem; }
    h2   { color:#94a3b8; font-size:0.85rem; text-transform:uppercase; letter-spacing:1px; margin:24px 0 10px; }
    .row { display:flex; align-items:center; gap:10px; padding:8px 14px; border-radius:8px; margin-bottom:6px; background:#1e293b; }
    .ok  { color:#4ade80; }
    .err { color:#f87171; }
    .info{ color:#93c5fd; }
    .badge { font-size:0.8rem; background:#0f172a; padding:2px 8px; border-radius:20px; }
    .alert { background:#1e293b; border-left:4px solid #4ade80; padding:16px 20px; border-radius:8px; margin-top:24px; }
    .alert.error { border-color:#f87171; }
    a { color:#38bdf8; }
    code { background:#0f172a; padding:2px 6px; border-radius:4px; font-size:0.85rem; color:#a3e635; }
  </style>
</head>
<body>
  <h1> Initialisation de la base de données</h1>

  <?php if (!empty($errors)): ?>
    <div class="alert error">
      <strong>❌ Erreurs :</strong><br>
      <?php foreach ($errors as $e): ?>
        <div style="margin-top:6px;font-size:0.85rem;color:#f87171"><?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <h2> Tables créées</h2>
  <?php foreach ($created as $t): ?>
    <div class="row"><span class="ok">ok</span><span><?= htmlspecialchars($t) ?></span></div>
  <?php endforeach; ?>

  <?php if ($foodsInserted > 0): ?>
    <h2> Aliments insérés</h2>
    <div class="row"><span class="ok">ok</span><span><strong><?= $foodsInserted ?></strong> aliments ajoutés dans <code>food_items</code></span></div>
  <?php endif; ?>

  <?php if (!empty($skipped)): ?>
    <h2> Ignorés (déjà présents)</h2>
    <?php foreach ($skipped as $s): ?>
      <div class="row"><span class="info">→</span><span><?= htmlspecialchars($s) ?></span></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if (empty($errors)): ?>
    <div class="alert">
      <strong> Base de données prête </strong><br><br>
      <?php if ($foodsInserted > 0): ?>
        <span style="color:#94a3b8"><?= $foodsInserted ?> aliments de référence disponibles.</span><br><br>
      <?php endif; ?>
      <a href="/take_health/">→ Accéder à l'application</a> &nbsp;|&nbsp;
      <a href="/take_health/check_ollama.php">→ Tester la connexion Ollama</a>
    </div>
  <?php endif; ?>
</body>
</html>
