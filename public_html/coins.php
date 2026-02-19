<?php
declare(strict_types=1);
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_login();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$u = current_user();

// Dashboards no header (se você usa dropdown)
try {
  $dashboards = db()->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}

$current_dash = $_GET['dash'] ?? 'executivo'; // só para o header montar links de métricas (quando admin)

$success = '';
$error = '';

function ensure_wallet(unsigned_int $userId): void {} // (não existe no PHP)
function ensure_wallet_int(int $userId): void {
  // MySQL: INSERT IGNORE funciona bem aqui
  $stmt = db()->prepare("INSERT IGNORE INTO popper_coin_wallets (user_id, balance) VALUES (?, 0)");
  $stmt->execute([$userId]);
}

$userId = (int)($u['id'] ?? 0);
ensure_wallet_int($userId);

// Resgatar (cria pedido pendente)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redeem_reward_id'])) {
  try {
    $rewardId = (int)($_POST['redeem_reward_id'] ?? 0);
    $note = trim((string)($_POST['user_note'] ?? ''));

    if ($rewardId <= 0) throw new Exception('Recompensa inválida.');

    // Recompensa ativa
    $stmt = db()->prepare("SELECT id, title, cost, is_active FROM popper_coin_rewards WHERE id=?");
    $stmt->execute([$rewardId]);
    $reward = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$reward) throw new Exception('Recompensa não encontrada.');
    if ((int)$reward['is_active'] !== 1) throw new Exception('Recompensa indisponível.');

    // Saldo
    $stmt = db()->prepare("SELECT balance FROM popper_coin_wallets WHERE user_id=?");
    $stmt->execute([$userId]);
    $balance = (int)($stmt->fetchColumn() ?? 0);

    $cost = (int)$reward['cost'];
    if ($cost <= 0) throw new Exception('Custo inválido.');
    if ($balance < $cost) throw new Exception('Saldo insuficiente para resgatar esta recompensa.');

    // Cria pedido pendente (não debita ainda)
    $stmt = db()->prepare("
      INSERT INTO popper_coin_redemptions (user_id, reward_id, cost, status, user_note)
      VALUES (?, ?, ?, 'pending', ?)
    ");
    $stmt->execute([$userId, $rewardId, $cost, ($note !== '' ? $note : null)]);

    $success = 'Pedido de resgate enviado para aprovação do RH.';
  } catch (Throwable $e) {
    $error = 'Erro: ' . $e->getMessage();
  }
}

// Dados para tela
$stmt = db()->prepare("SELECT COALESCE(balance, 0) FROM popper_coin_wallets WHERE user_id=?");
$stmt->execute([$userId]);
$balance = (int)($stmt->fetchColumn() ?? 0);

$rewards = db()->query("
  SELECT id, title, description, cost
  FROM popper_coin_rewards
  WHERE is_active = 1
  ORDER BY sort_order ASC, id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$pending = db()->prepare("
  SELECT r.id, r.status, r.cost, r.created_at, rw.title
  FROM popper_coin_redemptions r
  JOIN popper_coin_rewards rw ON rw.id = r.reward_id
  WHERE r.user_id = ?
  ORDER BY r.id DESC
  LIMIT 30
");
$pending->execute([$userId]);
$requests = $pending->fetchAll(PDO::FETCH_ASSOC);

$ledger = db()->prepare("
  SELECT id, amount, action_type, reason, created_at
  FROM popper_coin_ledger
  WHERE user_id = ?
  ORDER BY id DESC
  LIMIT 50
");
$ledger->execute([$userId]);
$entries = $ledger->fetchAll(PDO::FETCH_ASSOC);
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
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:20px}
    @media(max-width:900px){.grid2{grid-template-columns:1fr}}
    .pill{display:inline-block;padding:4px 10px;border-radius:999px;font-weight:800;font-size:12px}
    .pill--pending{background:rgba(245,158,11,.14);color:#92400e}
    .pill--approved{background:rgba(22,163,74,.12);color:#166534}
    .pill--rejected{background:rgba(220,38,38,.12);color:#991b1b}
    .muted2{color:var(--muted);font-size:12px;margin-top:6px}
    .coins-balance{font-size:34px;font-weight:900;letter-spacing:-.5px}
  </style>
</head>
<body class="page">

<?php require_once __DIR__ . '/app/header.php'; ?>

<main class="container">
  <h2 class="page-title">Popper Coins</h2>

  <?php if ($success): ?><div class="alert alert--ok"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

  <section class="grid2">
    <div class="card">
      <div class="card__header">
        <h3 class="card__title">Seu saldo</h3>
        <p class="card__subtitle">Coins disponíveis para resgate.</p>
      </div>
      <div class="coins-balance"><?= (int)$balance ?> coins</div>
      <div class="muted2">Usuário: <?= htmlspecialchars((string)($u['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
    </div>

    <div class="card">
      <div class="card__header">
        <h3 class="card__title">Seus pedidos (últimos 30)</h3>
        <p class="card__subtitle">Resgates pendentes/aprovados/negados.</p>
      </div>

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
                  <td><?= htmlspecialchars((string)$r['title'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="right"><?= (int)$r['cost'] ?></td>
                  <td><span class="pill <?= $pill ?>"><?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?></span></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="card card--mt">
    <div class="card__header">
      <h3 class="card__title">Catálogo de recompensas</h3>
      <p class="card__subtitle">Escolha uma recompensa e envie para aprovação do RH.</p>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Recompensa</th>
            <th class="right">Custo</th>
            <th>Descrição</th>
            <th class="right">Ação</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rewards): ?>
            <tr><td colspan="4" class="muted">Nenhuma recompensa cadastrada.</td></tr>
          <?php else: ?>
            <?php foreach ($rewards as $rw): ?>
              <tr>
                <td><?= htmlspecialchars((string)$rw['title'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="right"><?= (int)$rw['cost'] ?></td>
                <td><?= htmlspecialchars((string)($rw['description'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="right">
                  <form method="post" style="display:inline">
                    <input type="hidden" name="redeem_reward_id" value="<?= (int)$rw['id'] ?>" />
                    <input type="text" name="user_note" placeholder="Obs. (opcional)" style="max-width:220px" />
                    <button class="btn btn--primary" type="submit">Resgatar</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card card--mt">
    <div class="card__header">
      <h3 class="card__title">Extrato (últimos 50)</h3>
      <p class="card__subtitle">Histórico de lançamentos (coins recebidas e debitadas).</p>
    </div>

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
  </section>

</main>

<script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdowns.js') ?>"></script>
</body>
</html>