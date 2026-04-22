<?php
<<<<<<< HEAD
/**
 * check_db.php — Vérification complète de la base de données
 * Ouvrir : http://localhost/take_health/check_db.php
 */
require_once __DIR__ . '/config/db.php';

// Tables requises et nombre minimum de colonnes attendues
$requiredTables = [
    'users'             => ['id','prenom','nom','email','password_hash'],
    'profiles'          => ['id','user_id','age','height_cm','weight_kg','gender','goal','bmi'],
    'nutrition_targets' => ['id','user_id','tdee_kcal','protein_g','carbs_g','fat_g'],
    'daily_logs'        => ['id','user_id','log_date','total_kcal','status'],
    'meals'             => ['id','log_id','user_id','meal_type','food_description','kcal'],
    'food_items'        => ['id','name','category','kcal_per_portion','protein_g','carbs_g','fat_g'],
    'ai_chat_history'   => ['id','user_id','role','message'],
    'ai_reports'        => ['id','user_id','type','content'],
    'ai_analyses'       => ['id','log_id','user_id','status'],
];

// Récupérer toutes les tables existantes
$existingTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

// Statistiques de lignes pour chaque table
$stats = [];
foreach ($existingTables as $t) {
    try {
        $stats[$t] = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    } catch (Exception $e) {
        $stats[$t] = '?';
    }
}

$allOk = true;
=======

require_once __DIR__ . '/config/ollama_config.php';

$curlOk = function_exists('curl_init');

$ollamaUp   = false;
$ollamaErr  = '';
$ollamaReply = null;

if ($curlOk) {
    $reply = callOllama(
        "Tu es un assistant. Réponds en une seule phrase courte.",
        "Dis bonjour en français."
    );
    if ($reply !== null) {
        $ollamaUp    = true;
        $ollamaReply = $reply;
    } else {
        $ollamaErr = "Ollama ne répond pas sur " . OLLAMA_URL;
    }
} else {
    $ollamaErr = "L'extension PHP cURL n'est pas activée.";
}

require_once __DIR__ . '/config/db.php';
$dbOk  = false;
$dbErr = '';
try {
    $pdo->query("SELECT 1");
    $tables   = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $required = ['users','profiles','nutrition_targets','daily_logs','meals','ai_chat_history','ai_reports','ai_analyses'];
    $missing  = array_diff($required, $tables);
    if (empty($missing)) {
        $dbOk = true;
    } else {
        $dbErr = "Tables manquantes : " . implode(', ', $missing) . ". → Ouvrir /config/setup_db.php";
    }
} catch (Exception $e) {
    $dbErr = $e->getMessage();
}
>>>>>>> 214b8a03c8d26049b35984f743df4f369ace9f21
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<<<<<<< HEAD
  <meta charset="UTF-8">
  <title>Vérification BDD — Take Health</title>
  <style>
    * { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'Segoe UI',sans-serif; background:#0f172a; color:#e2e8f0; padding:30px 20px; max-width:720px; margin:0 auto; }
    h1   { color:#38bdf8; margin-bottom:6px; font-size:1.3rem; }
    .sub { color:#64748b; font-size:0.85rem; margin-bottom:24px; }
    table{ width:100%; border-collapse:collapse; }
    th   { text-align:left; padding:8px 12px; font-size:0.75rem; text-transform:uppercase; letter-spacing:1px; color:#64748b; border-bottom:1px solid #1e293b; }
    td   { padding:10px 12px; border-bottom:1px solid #1e293b; font-size:0.9rem; }
    tr:hover td { background:#1e293b; }
    .ok  { color:#4ade80; }
    .err { color:#f87171; }
    .warn{ color:#fb923c; }
    .badge-ok  { background:#14532d; color:#4ade80; padding:2px 8px; border-radius:20px; font-size:0.75rem; }
    .badge-err { background:#450a0a; color:#f87171; padding:2px 8px; border-radius:20px; font-size:0.75rem; }
    .badge-warn{ background:#431407; color:#fb923c; padding:2px 8px; border-radius:20px; font-size:0.75rem; }
    .alert { padding:16px 20px; border-radius:10px; margin-top:24px; }
    .alert-ok    { background:#14532d; border:1px solid #4ade80; }
    .alert-err   { background:#450a0a; border:1px solid #f87171; }
    a { color:#38bdf8; text-decoration:none; }
    a:hover { text-decoration:underline; }
    code { background:#0f172a; padding:2px 6px; border-radius:4px; font-size:0.82rem; color:#a3e635; }
    .cols { font-size:0.78rem; color:#475569; }
  </style>
</head>
<body>
  <h1>🗄️ État de la base de données</h1>
  <div class="sub">Base : <code>healthcare</code> · <?= count($existingTables) ?> tables trouvées · <?= date('d/m/Y H:i') ?></div>

  <table>
    <thead>
      <tr>
        <th>Table</th>
        <th>Statut</th>
        <th>Lignes</th>
        <th>Colonnes requises manquantes</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($requiredTables as $table => $requiredCols): ?>
      <?php
        $exists = in_array($table, $existingTables);
        $missing = [];
        if ($exists) {
            $existingCols = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_COLUMN);
            $missing = array_diff($requiredCols, $existingCols);
        }
        $status = !$exists ? 'missing' : (!empty($missing) ? 'warn' : 'ok');
        if ($status !== 'ok') $allOk = false;
      ?>
      <tr>
        <td><strong><?= htmlspecialchars($table) ?></strong></td>
        <td>
          <?php if ($status === 'ok'): ?>
            <span class="badge-ok">✓ OK</span>
          <?php elseif ($status === 'warn'): ?>
            <span class="badge-warn">⚠ Incomplet</span>
          <?php else: ?>
            <span class="badge-err">✗ Manquante</span>
          <?php endif; ?>
        </td>
        <td><?= isset($stats[$table]) ? number_format($stats[$table]) : '—' ?></td>
        <td class="cols">
          <?= empty($missing) ? ($exists ? '<span style="color:#64748b">—</span>' : '<span class="err">Table absente</span>') : '<span class="warn">' . implode(', ', $missing) . '</span>' ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($allOk): ?>
    <div class="alert alert-ok" style="margin-top:20px">
      <strong class="ok">✅ Base de données complète et opérationnelle</strong><br>
      <div style="margin-top:10px;font-size:0.85rem">
        <a href="/take_health/">→ Accéder à l'application</a> &nbsp;|&nbsp;
        <a href="/take_health/check_ollama.php">→ Tester Ollama</a>
      </div>
    </div>
  <?php else: ?>
    <div class="alert alert-err" style="margin-top:20px">
      <strong class="err">❌ Des tables sont manquantes ou incomplètes</strong><br>
      <div style="margin-top:10px;font-size:0.85rem">
        <a href="/take_health/config/setup_db.php">→ Cliquer ici pour créer/compléter les tables</a>
      </div>
    </div>
  <?php endif; ?>
=======
    <meta charset="UTF-8">
    <title>Diagnostic — Take Health</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; padding: 40px 20px; }
        h1   { color: #38bdf8; margin-bottom: 30px; font-size: 1.5rem; }
        .card { background: #1e293b; border-radius: 12px; padding: 20px; margin-bottom: 16px; display: flex; align-items: flex-start; gap: 16px; }
        .icon { font-size: 1.8rem; flex-shrink:0; }
        .label { font-size: 0.85rem; color: #94a3b8; margin-bottom: 4px; }
        .val { font-size: 1rem; font-weight: 600; }
        .ok  { color: #4ade80; }
        .err { color: #f87171; }
        .warn { color: #fb923c; }
        .code { background: #0f172a; border-radius: 6px; padding: 10px 14px; font-family: monospace; font-size: 0.85rem; margin-top: 8px; color: #a3e635; line-height: 1.6; }
        h2   { color: #38bdf8; font-size: 1rem; margin: 30px 0 12px; }
        a    { color: #38bdf8; }
    </style>
</head>
<body>
    <h1> Diagnostic — Take Health / Ollama</h1>

    <div class="card">
        <div class="icon"><?= $curlOk ? 'ok' : 'err' ?></div>
        <div>
            <div class="label">Extension PHP cURL</div>
            <div class="val <?= $curlOk ? 'ok' : 'err' ?>">
                <?= $curlOk ? 'Disponible' : 'Non disponible — activer dans php.ini' ?>
            </div>
            <?php if (!$curlOk): ?>
                <div class="code">
                    Dans php.ini : décommenter extension=curl<br>
                    Ensuite redémarrer Apache dans XAMPP.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="icon"><?= $ollamaUp ? 'ok' : 'err' ?></div>
        <div>
            <div class="label">Ollama (<?= OLLAMA_MODEL ?>) — <?= OLLAMA_URL ?></div>
            <?php if ($ollamaUp): ?>
                <div class="val ok"> En ligne et fonctionnel</div>
                <div class="code">Réponse test : "<?= htmlspecialchars(substr($ollamaReply, 0, 120)) ?>"</div>
            <?php else: ?>
                <div class="val err"> Hors ligne</div>
                <div class="code"><?= htmlspecialchars($ollamaErr) ?></div>
                <div class="code" style="margin-top:8px">
                    Pour lancer Ollama :<br>
                    1. Installer depuis https://ollama.com<br>
                    2. CMD : <strong>ollama pull llama3</strong><br>
                    3. CMD : <strong>ollama serve</strong> (si pas démarré auto)<br>
                    4. Recharger cette page
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="icon"><?= $dbOk ? 'ok' : 'err' ?></div>
        <div>
            <div class="label">Base de données MySQL (healthcare)</div>
            <?php if ($dbOk): ?>
                <div class="val ok"> Toutes les tables présentes</div>
            <?php else: ?>
                <div class="val err"> Problème détecté</div>
                <div class="code"><?= htmlspecialchars($dbErr) ?></div>
                <div style="margin-top:8px">
                    <a href="/take_health/config/setup_db.php">→ Cliquer ici pour créer les tables automatiquement</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($ollamaUp && $dbOk && $curlOk): ?>
        <div class="card" style="border: 1px solid #4ade80">
            <div class="icon">ok</div>
            <div>
                <div class="val ok">Tout est opérationnel !</div>
                <div style="margin-top:8px">
                    <a href="/take_health/">→ Retour à l'application</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
>>>>>>> 214b8a03c8d26049b35984f743df4f369ace9f21
</body>
</html>
