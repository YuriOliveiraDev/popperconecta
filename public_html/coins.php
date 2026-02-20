<?php
declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/notifications.php';
require_login();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$u = current_user();
$userId = (int)($u['id'] ?? 0);

// Dashboards no header (se você usa dropdown)
try {
  $dashboards = db()->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}

$current_dash = $_GET['dash'] ?? 'executivo';
$activePage = 'coins';

$success = '';
$error = '';
if (isset($_GET['ok'])) $success = 'Pedido enviado.';
if (isset($_GET['err'])) $error = 'Erro: ' . (string)$_GET['err'];

// ✅ Sessão para token anti-duplicação
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
if (empty($_SESSION['redeem_token'])) {
  $_SESSION['redeem_token'] = bin2hex(random_bytes(16));
}
$redeemToken = (string)$_SESSION['redeem_token'];

function ensure_wallet_int(int $userId): void {
  $stmt = db()->prepare("INSERT IGNORE INTO popper_coin_wallets (user_id, balance) VALUES (?, 0)");
  $stmt->execute([$userId]);
}

function apply_ledger_no_tx(int $userId, int $amount, string $type, ?string $reason, int $actorId): void {
  ensure_wallet_int($userId);
  $stmt = db()->prepare("INSERT INTO popper_coin_ledger (user_id, amount, action_type, reason, created_by) VALUES (?, ?, ?, ?, ?)");
  $stmt->execute([$userId, $amount, $type, $reason, $actorId]);

  $stmt = db()->prepare("UPDATE popper_coin_wallets SET balance = balance + ? WHERE user_id = ?");
  $stmt->execute([$amount, $userId]);
}

// ✅ Processa resgate (PRG: depois redireciona)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redeem_reward_id'])) {
  try {
    $rewardId = (int)($_POST['redeem_reward_id'] ?? 0);
    $userNote = trim((string)($_POST['user_note'] ?? ''));
    $token = (string)($_POST['redeem_token'] ?? '');

    if ($rewardId <= 0) throw new Exception('Recompensa inválida.');

    // valida token
    if ($token === '' || empty($_SESSION['redeem_token']) || !hash_equals($_SESSION['redeem_token'], $token)) {
      throw new Exception('Requisição inválida ou repetida.');
    }
    unset($_SESSION['redeem_token']); // gasta token

    $db = db();
    $db->beginTransaction();

    // impede duplicar pedido pendente igual
    $stmt = $db->prepare("
      SELECT COUNT(*)
      FROM popper_coin_redemptions
      WHERE user_id = ? AND reward_id = ? AND status = 'pending'
      FOR UPDATE
    ");
    $stmt->execute([$userId, $rewardId]);
    if ((int)$stmt->fetchColumn() > 0) {
      throw new Exception('Você já tem um pedido pendente para esta recompensa.');
    }

    // trava reward
    $stmt = $db->prepare("SELECT id, title, cost, inventory, is_active FROM popper_coin_rewards WHERE id = ? FOR UPDATE");
    $stmt->execute([$rewardId]);
    $rw = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rw) throw new Exception('Recompensa não encontrada.');
    if ((int)$rw['is_active'] !== 1) throw new Exception('Recompensa indisponível.');

    $qty = 1;
    $inventory = (int)($rw['inventory'] ?? 0);
    if ($inventory < $qty) throw new Exception('Sem inventário suficiente.');

    $cost = (int)($rw['cost'] ?? 0);
    if ($cost <= 0) throw new Exception('Custo inválido.');

    $title = (string)($rw['title'] ?? '');

    // trava wallet
    ensure_wallet_int($userId);
    $stmt = $db->prepare("SELECT balance FROM popper_coin_wallets WHERE user_id = ? FOR UPDATE");
    $stmt->execute([$userId]);
    $balance = (int)($stmt->fetchColumn() ?? 0);

    if ($balance < $cost) throw new Exception('Saldo insuficiente.');

    // segura saldo (desconto temporário)
    apply_ledger_no_tx($userId, -abs($cost), 'hold', 'Resgate solicitado (pendente): ' . $title, $userId);

    // segura inventário
    $stmt = $db->prepare("UPDATE popper_coin_rewards SET inventory = inventory - ? WHERE id = ?");
    $stmt->execute([$qty, $rewardId]);

    // cria pedido
    $stmt = $db->prepare("
      INSERT INTO popper_coin_redemptions (user_id, reward_id, cost, qty, status, user_note, created_at)
      VALUES (?, ?, ?, ?, 'pending', ?, NOW())
    ");
    $stmt->execute([$userId, $rewardId, $cost, $qty, ($userNote !== '' ? $userNote : null)]);

    // ✅ NOTIFICA RH/ADMIN
    $rhUsers = db()->query("SELECT id FROM users WHERE role IN ('rh','admin')")->fetchAll(PDO::FETCH_ASSOC);
    if ($rhUsers) {
      $stmtN = db()->prepare("
        INSERT INTO notifications (user_id, type, title, message, link)
        VALUES (?, 'coins_redeem_requested', 'Novo pedido de resgate', ?, '/rh_redemptions.php')
      ");
      $msg = (string)($u['name'] ?? 'Usuário') . ' solicitou "' . $title . '" (' . $cost . ' coins).';
      foreach ($rhUsers as $rh) {
        $stmtN->execute([(int)$rh['id'], $msg]);
      }
    }

    $db->commit();

    // novo token e redireciona (PRG)
    $_SESSION['redeem_token'] = bin2hex(random_bytes(16));
    header('Location: /coins.php?ok=redeem');
    exit;
  } catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    $_SESSION['redeem_token'] = bin2hex(random_bytes(16));
    header('Location: /coins.php?err=' . urlencode($e->getMessage()));
    exit;
  }
}

ensure_wallet_int($userId);

// Saldo
$stmt = db()->prepare("SELECT COALESCE(balance, 0) FROM popper_coin_wallets WHERE user_id=?");
$stmt->execute([$userId]);
$balance = (int)($stmt->fetchColumn() ?? 0);

// Recompensas (com inventário)
$rewards = db()->query("
  SELECT id, title, description, cost, inventory
  FROM popper_coin_rewards
  WHERE is_active = 1
  ORDER BY sort_order ASC, id ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Seus pedidos
$stmt = db()->prepare("
  SELECT r.id, r.status, r.cost, r.qty, r.user_note, r.created_at,
         rw.title AS reward_title
  FROM popper_coin_redemptions r
  JOIN popper_coin_rewards rw ON rw.id = r.reward_id
  WHERE r.user_id = ?
  ORDER BY r.id DESC
  LIMIT 30
");
$stmt->execute([$userId]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Extrato
$stmt = db()->prepare("
  SELECT id, amount, action_type, reason, created_at
  FROM popper_coin_ledger
  WHERE user_id = ?
  ORDER BY id DESC
  LIMIT 50
");
$stmt->execute([$userId]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Popper Coins — <?= htmlspecialchars((string)APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/assets/css/dashboard.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/assets/css/dropdowns.css') ?>" />

  <style>
    .pc-container{display:grid;grid-template-columns:1fr;gap:24px;max-width:1400px;margin:0 auto;}
    .pc-header{text-align:center;margin-bottom:24px;}
    .pc-title{font-size:2.2rem;font-weight:900;color:var(--ink);margin:0;}
    .pc-subtitle{font-size:1.05rem;color:var(--muted);margin:8px 0 0;}

    .pc-card{
      background:var(--card);
      border:1px solid rgba(15,23,42,0.08);
      border-radius:16px;
      box-shadow:0 4px 20px rgba(15,23,42,0.06);
      padding:24px;
    }

    .pc-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;}
    @media (max-width:900px){.pc-grid{grid-template-columns:1fr;}}

    .pc-card--balance{
      min-height:200px;
      display:flex;
      flex-direction:column;
      justify-content:center;
      text-align:center;
      padding:32px 24px;
    }

    .pc-balance{display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:12px;}
    .pc-balance__icon{font-size:2.5rem;line-height:1;}
    .pc-balance__num{font-size:2.6rem;font-weight:950;letter-spacing:-1px;color:var(--accent,#5c2d91);}
    .pc-balance__meta{color:var(--muted);font-size:.9rem;}

    .table-wrap{overflow-x:auto;border-radius:12px;}
    .table{width:100%;border-collapse:separate;border-spacing:0;}
    .table th,.table td{padding:14px 16px;text-align:left;border-bottom:1px solid rgba(15,23,42,0.06);}
    .table thead th{font-weight:800;text-transform:uppercase;font-size:11px;color:var(--muted);background:rgba(15,23,42,0.02);}
    .table tbody tr:hover{background:rgba(92,44,140,0.04);}
    .table .right{text-align:right;}

    .pc-form{display:flex;gap:12px;align-items:center;justify-content:flex-end;flex-wrap:wrap;}
    .pc-form input{flex:1;min-width:180px;padding:8px 12px;border:1px solid rgba(15,23,42,0.12);border-radius:8px;font-size:14px;}
    .pc-form button{padding:8px 16px;border-radius:8px;font-weight:800;}
    .btn:disabled{opacity:.55;cursor:not-allowed;}

    .pill{display:inline-flex;align-items:center;padding:6px 12px;border-radius:999px;font-weight:800;font-size:12px;border:1px solid rgba(15,23,42,0.1);}
    .pill--pending{background:rgba(245,158,11,0.12);color:#92400e;}
    .pill--approved{background:rgba(22,163,74,0.12);color:#166534;}
    .pill--rejected{background:rgba(220,38,38,0.12);color:#991b1b;}

    @media (max-width:768px){
      .pc-title{font-size:1.8rem;}
      .pc-card--balance{min-height:170px;}
      .pc-balance__icon{font-size:2rem;}
      .pc-balance__num{font-size:2.2rem;}
      .pc-form{flex-direction:column;align-items:stretch;}
      .pc-form input{min-width:auto;}
    }
  </style>
</head>
<body class="page">

<?php require_once __DIR__ . '/app/header.php'; ?>

<main class="container">
  <div class="pc-header">
    <h1 class="pc-title">Popper Coins</h1>
    <p class="pc-subtitle">Solicite recompensas e acompanhe seus pedidos.</p>
  </div>

  <?php if ($success): ?><div class="alert alert--ok"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

  <div class="pc-container">
    <div class="pc-grid">
      <div class="pc-card pc-card--balance">
        <h3 style="margin:0 0 8px 0;">Seu saldo</h3>
        <div class="pc-balance">
          <span class="pc-balance__icon" aria-hidden="true">🪙</span>
          <div class="pc-balance__num"><?= (int)$balance ?> coins</div>
        </div>
        <div class="pc-balance__meta">Usuário: <?= htmlspecialchars((string)($u['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
      </div>

      <div class="pc-card">
        <h3 style="margin:0 0 8px 0;">Seus pedidos (últimos 30)</h3>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Data</th>
                <th>Recompensa</th>
                <th class="right">Custo</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$requests): ?>
                <tr><td colspan="4" class="muted">Nenhum pedido ainda.</td></tr>
              <?php else: ?>
                <?php foreach ($requests as $r): ?>
                  <?php
                    $st = (string)$r['status'];
                    $pill = $st === 'approved' ? 'pill--approved' : ($st === 'rejected' ? 'pill--rejected' : 'pill--pending');
                  ?>
                  <tr>
                    <td><?= htmlspecialchars((string)$r['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$r['reward_title'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="right"><?= (int)$r['cost'] ?></td>
                    <td><span class="pill <?= $pill ?>"><?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?></span></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="pc-card">
      <h3 style="margin:0 0 8px 0;">Catálogo de recompensas</h3>
      <p class="muted" style="margin:0 0 16px 0;">O saldo e o inventário serão bloqueados temporariamente até decisão do RH.</p>

      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Recompensa</th>
              <th class="right">Custo</th>
              <th class="right">Inventário</th>
              <th>Descrição</th>
              <th class="right">Solicitar</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rewards): ?>
              <tr><td colspan="5" class="muted">Nenhuma recompensa cadastrada.</td></tr>
            <?php else: ?>
              <?php foreach ($rewards as $rw): ?>
                <?php
                  $inv = (int)($rw['inventory'] ?? 0);
                  $cost = (int)($rw['cost'] ?? 0);
                  $disabled = ($inv <= 0 || $balance < $cost);
                ?>
                <tr>
                  <td><?= htmlspecialchars((string)$rw['title'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="right"><?= $cost ?></td>
                  <td class="right"><?= $inv ?></td>
                  <td><?= htmlspecialchars((string)($rw['description'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="right">
                    <form method="post" class="pc-form">
                      <input type="hidden" name="redeem_reward_id" value="<?= (int)$rw['id'] ?>" />
                      <input type="hidden" name="redeem_token" value="<?= htmlspecialchars($redeemToken, ENT_QUOTES, 'UTF-8') ?>" />
                      <input type="text" name="user_note" placeholder="Obs. (opcional)" />
                      <button class="btn btn--primary" type="submit" <?= $disabled ? 'disabled' : '' ?> onclick="return confirm('Solicitar resgate? Saldo e inventário serão bloqueados até decisão do RH.');">
                        Solicitar
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="pc-card">
      <h3 style="margin:0 0 8px 0;">Extrato (últimos 50)</h3>

      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Data</th>
              <th>Ação</th>
              <th>Motivo</th>
              <th class="right">Valor</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$entries): ?>
              <tr><td colspan="4" class="muted">Sem lançamentos.</td></tr>
            <?php else: ?>
              <?php foreach ($entries as $e): ?>
                <tr>
                  <td><?= htmlspecialchars((string)$e['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string)$e['action_type'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string)($e['reason'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="right"><?= (int)$e['amount'] ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdowns.js') ?>"></script>
<script>
  // Bloqueio de duplo clique (somente formulários desta página)
  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.pc-container form[method="post"]').forEach(function(form){
      form.addEventListener('submit', function(){
        var btn = form.querySelector('button[type="submit"]');
        if (btn) {
          btn.disabled = true;
          btn.textContent = 'Enviando...';
        }
      });
    });
  });
</script>
</body>
</html>