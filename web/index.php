<?php
// =============================================================
// Console de supervision VoIP — comptes (prépayé/postpayé) + CDR
// Identifiants lus depuis les variables d'environnement (voir
// config/.env.example) — ne jamais coder les mots de passe en dur.
// =============================================================

function env_or($name, $default) {
    $v = getenv($name);
    return $v !== false ? $v : $default;
}

function pg($dbname, $user, $pass) {
    return new PDO("pgsql:host=localhost;port=5432;dbname={$dbname}", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
}

$error = null;
$comptes = [];
$cdr = [];

try {
    $dbBilling = pg(
        env_or('BILLING_DB_NAME', 'a2billing'),
        env_or('BILLING_DB_USER', 'a2billing_user'),
        env_or('BILLING_DB_PASSWORD', 'CHANGE_ME_billing_password')
    );
    $comptes = $dbBilling->query("SELECT extension, mode, solde, dette, cout_par_minute FROM comptes ORDER BY extension")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Connexion a2billing impossible : " . $e->getMessage();
}

try {
    $dbCdr = pg(
        env_or('CDR_DB_NAME', 'asterisk_cdr'),
        env_or('CDR_DB_USER', 'asterisk_cdr'),
        env_or('CDR_DB_PASSWORD', 'CHANGE_ME_cdr_password')
    );
    $cdr = $dbCdr->query("SELECT calldate, src, dst, duration, billsec, disposition FROM cdr ORDER BY calldate DESC LIMIT 25")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = ($error ? $error . " / " : "") . "Connexion asterisk_cdr impossible : " . $e->getMessage();
}

$appelsActifs = count(array_filter($cdr, fn($c) => strtotime($c['calldate']) > time() - 30));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Console VoIP — Supervision</title>
<style>
  :root{
    --bg:#0B0F14; --panel:#121821; --panel-border:#1F2833;
    --text:#DCE3EA; --text-dim:#7C8896; --accent:#4FD8C4;
    --warn:#F0A868; --danger:#E8697A;
    --mono: 'IBM Plex Mono', 'JetBrains Mono', 'Courier New', monospace;
    --sans: 'IBM Plex Sans', 'Segoe UI', system-ui, sans-serif;
  }
  * { box-sizing:border-box; }
  body{
    margin:0;
    background: radial-gradient(circle at 15% 0%, rgba(79,216,196,0.06), transparent 40%), var(--bg);
    color:var(--text); font-family:var(--sans); padding:32px 24px 64px;
  }
  .wrap{ max-width:1080px; margin:0 auto; }
  header{ display:flex; align-items:baseline; justify-content:space-between; border-bottom:1px solid var(--panel-border); padding-bottom:20px; margin-bottom:28px; flex-wrap:wrap; gap:12px; }
  .eyebrow{ font-family:var(--mono); font-size:11px; letter-spacing:0.16em; text-transform:uppercase; color:var(--text-dim); }
  h1{ font-size:22px; font-weight:600; margin:2px 0 0; letter-spacing:-0.01em; }
  .live{ display:flex; align-items:center; gap:8px; font-family:var(--mono); font-size:13px; color:var(--accent); }
  .dot{ width:8px; height:8px; border-radius:50%; background:var(--accent); animation:pulse 2s infinite; }
  @keyframes pulse{ 0%{ box-shadow:0 0 0 0 rgba(79,216,196,0.55);} 70%{ box-shadow:0 0 0 9px rgba(79,216,196,0);} 100%{ box-shadow:0 0 0 0 rgba(79,216,196,0);} }
  .error-banner{ background:rgba(232,105,122,0.1); border:1px solid rgba(232,105,122,0.35); color:var(--danger); padding:12px 16px; border-radius:8px; font-family:var(--mono); font-size:12.5px; margin-bottom:24px; }
  .section-title{ font-family:var(--mono); font-size:11px; letter-spacing:0.14em; text-transform:uppercase; color:var(--text-dim); margin:0 0 12px; }
  .accounts{ display:grid; grid-template-columns:repeat(auto-fit, minmax(220px,1fr)); gap:14px; margin-bottom:36px; }
  .card{ background:var(--panel); border:1px solid var(--panel-border); border-radius:10px; padding:18px 18px 16px; }
  .card .ext{ font-family:var(--mono); font-size:13px; color:var(--text-dim); display:flex; justify-content:space-between; align-items:center; }
  .badge{ font-size:10px; letter-spacing:0.06em; text-transform:uppercase; padding:2px 7px; border-radius:99px; font-family:var(--mono); }
  .badge.prepaid{ background:rgba(79,216,196,0.12); color:var(--accent); }
  .badge.postpaid{ background:rgba(240,168,104,0.14); color:var(--warn); }
  .amount{ font-family:var(--mono); font-size:28px; font-weight:600; margin:10px 0 2px; font-variant-numeric:tabular-nums; }
  .amount.ok{ color:var(--text); }
  .amount.debt{ color:var(--warn); }
  .amount-label{ font-size:11.5px; color:var(--text-dim); }
  .rate{ margin-top:10px; padding-top:10px; border-top:1px solid var(--panel-border); font-size:11.5px; color:var(--text-dim); font-family:var(--mono); }
  table{ width:100%; border-collapse:collapse; }
  .table-wrap{ background:var(--panel); border:1px solid var(--panel-border); border-radius:10px; overflow:hidden; }
  thead th{ text-align:left; font-family:var(--mono); font-size:10.5px; letter-spacing:0.08em; text-transform:uppercase; color:var(--text-dim); padding:12px 16px; border-bottom:1px solid var(--panel-border); }
  tbody td{ padding:11px 16px; font-size:13.5px; border-bottom:1px solid rgba(31,40,51,0.6); font-family:var(--mono); }
  tbody tr:last-child td{ border-bottom:none; }
  tbody tr:hover{ background:rgba(79,216,196,0.04); }
  .status{ font-size:10.5px; padding:2px 8px; border-radius:99px; letter-spacing:0.04em; }
  .status.answered{ background:rgba(79,216,196,0.12); color:var(--accent); }
  .status.other{ background:rgba(124,136,150,0.14); color:var(--text-dim); }
  .dur{ color:var(--text-dim); }
  .empty{ padding:28px; text-align:center; color:var(--text-dim); font-family:var(--mono); font-size:13px; }
  footer{ margin-top:40px; text-align:center; color:var(--text-dim); font-family:var(--mono); font-size:11px; }
</style>
</head>
<body>
<div class="wrap">
  <header>
    <div>
      <div class="eyebrow">Projet VoIP — Asterisk</div>
      <h1>Console de supervision</h1>
    </div>
    <div class="live"><span class="dot"></span> <?= $appelsActifs ?> appel(s) récent(s) &middot; actualisation auto</div>
  </header>

  <?php if ($error): ?>
    <div class="error-banner">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="section-title">Comptes</div>
  <div class="accounts">
    <?php if (empty($comptes)): ?>
      <div class="card empty">Aucun compte trouvé.</div>
    <?php endif; ?>
    <?php foreach ($comptes as $c): ?>
      <div class="card">
        <div class="ext">
          <span><?= htmlspecialchars($c['extension']) ?></span>
          <span class="badge <?= $c['mode'] === 'prepaid' ? 'prepaid' : 'postpaid' ?>">
            <?= $c['mode'] === 'prepaid' ? 'Prépayé' : 'Postpayé' ?>
          </span>
        </div>
        <?php if ($c['mode'] === 'prepaid'): ?>
          <div class="amount ok"><?= number_format($c['solde'], 2, ',', ' ') ?> Ar</div>
          <div class="amount-label">Solde disponible</div>
        <?php else: ?>
          <div class="amount debt"><?= number_format($c['dette'], 2, ',', ' ') ?> Ar</div>
          <div class="amount-label">Dette cumulée</div>
        <?php endif; ?>
        <div class="rate">Tarif : <?= number_format($c['cout_par_minute'], 2) ?> Ar / min</div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="section-title">Derniers appels (CDR)</div>
  <div class="table-wrap">
    <?php if (empty($cdr)): ?>
      <div class="empty">Aucun appel enregistré pour l'instant.</div>
    <?php else: ?>
    <table>
      <thead><tr><th>Date / heure</th><th>Source</th><th>Destination</th><th>Durée</th><th>Statut</th></tr></thead>
      <tbody>
        <?php foreach ($cdr as $c): ?>
        <tr>
          <td><?= htmlspecialchars(date('d/m H:i:s', strtotime($c['calldate']))) ?></td>
          <td><?= htmlspecialchars($c['src']) ?></td>
          <td><?= htmlspecialchars($c['dst']) ?></td>
          <td class="dur"><?= intval($c['billsec']) ?>s</td>
          <td><span class="status <?= $c['disposition'] === 'ANSWERED' ? 'answered' : 'other' ?>"><?= htmlspecialchars($c['disposition']) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <footer>Console VoIP &middot; actualisation toutes les 5 secondes</footer>
</div>
<script>setTimeout(() => window.location.reload(), 5000);</script>
</body>
</html>
