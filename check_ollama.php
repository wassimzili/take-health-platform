<?php

require_once __DIR__ . '/config/ollama_config.php';

$curlOk = function_exists('curl_init');

$ollamaUp  = false;
$ollamaErr = '';
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
$dbOk = false;
$dbErr = '';
try {
    $pdo->query("SELECT 1");
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $required = ['users','profiles','nutrition_targets','daily_logs','meals','ai_chat_history','ai_reports','ai_analyses'];
    $missing = array_diff($required, $tables);
    if (empty($missing)) {
        $dbOk = true;
    } else {
        $dbErr = "Tables manquantes : " . implode(', ', $missing) . ". → Ouvrir /config/setup_db.php";
    }
} catch (Exception $e) {
    $dbErr = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
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
      <div class="val <?= $curlOk ? 'ok' : 'err' ?>"><?= $curlOk ? 'Disponible' : 'Non disponible — activer dans php.ini' ?></div>
      <?php if (!$curlOk): ?>
        <div class="code">Dans php.ini : décommenter extension=curl<br>Ensuite redémarrer Apache dans XAMPP.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Test Ollama -->
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
        <div style="margin-top:8px"><a href="/take_health/config/setup_db.php">→ Cliquer ici pour créer les tables automatiquement</a></div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($ollamaUp && $dbOk && $curlOk): ?>
    <div class="card" style="border: 1px solid #4ade80">
      <div class="icon">🎉</div>
      <div>
        <div class="val ok">Tout est opérationnel !</div>
        <div style="margin-top:8px"><a href="/take_health/">→ Retour à l'application</a></div>
      </div>
    </div>
  <?php endif; ?>
</body>
</html>
