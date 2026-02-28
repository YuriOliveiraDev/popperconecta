<?php
declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/notifications.php';

require_login();

/**
 * ===============================
 * TRADUÇÕES UI (EN → PT-BR)
 * ===============================
 */

function t_status(string $status): string {
  $s = strtolower(trim($status));
  return match ($s) {
    'pending'   => 'Pendente',
    'approved'  => 'Aprovado',
    'rejected'  => 'Recusado',
    'cancelled' => 'Cancelado',
    default     => ($s !== '' ? mb_convert_case($s, MB_CASE_TITLE, 'UTF-8') : '—'),
  };
}

function t_action(string $action): string {
  $a = strtolower(trim($action));
  return match ($a) {
    'earn'     => 'Ganho',
    'spend'    => 'Gasto',
    'hold'     => 'Reserva',
    'release'  => 'Liberação',
    'refund'   => 'Estorno',
    default    => ($a !== '' ? mb_convert_case($a, MB_CASE_TITLE, 'UTF-8') : '—'),
  };
}

function t_approval_action(string $action): string {
  $a = strtolower(trim($action));
  return match ($a) {
    'approve' => 'Aprovou',
    'reject'  => 'Recusou',
    'cancel'  => 'Cancelou',
    'create'  => 'Criou',
    default   => ($a !== '' ? mb_convert_case($a, MB_CASE_TITLE, 'UTF-8') : '—'),
  };
}

/**
 * ===============================
 * HEADERS (NO CACHE)
 * ===============================
 */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$u = current_user();
$userId = (int)($u['id'] ?? 0);

// Dashboards no header (dropdown)
try {
  $dashboards = db()->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}

$current_dash = $_GET['dash'] ?? 'executivo';
$activePage = 'coins';

$success = '';
$error = '';
if (isset($_GET['ok']))  $success = 'Pedido enviado.';
if (isset($_GET['err'])) $error   = 'Erro: ' . (string)$_GET['err'];

/**
 * ✅ Sessão para token anti-duplicação
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
if (empty($_SESSION['redeem_token'])) {
  $_SESSION['redeem_token'] = bin2hex(random_bytes(16));
}
$redeemToken = (string)$_SESSION['redeem_token'];

/**
 * ===============================
 * WALLET / LEDGER HELPERS
 * ===============================
 */
function ensure_wallet_int(int $userId): void {
  $stmt = db()->prepare("INSERT IGNORE INTO popper_coin_wallets (user_id, balance) VALUES (?, 0)");
  $stmt->execute([$userId]);
}

function apply_ledger_no_tx(int $userId, int $amount, string $type, ?string $reason, int $actorId): void {
  ensure_wallet_int($userId);

  $stmt = db()->prepare("
    INSERT INTO popper_coin_ledger (user_id, amount, action_type, reason, created_by)
    VALUES (?, ?, ?, ?, ?)
  ");
  $stmt->execute([$userId, $amount, $type, $reason, $actorId]);

  $stmt = db()->prepare("UPDATE popper_coin_wallets SET balance = balance + ? WHERE user_id = ?");
  $stmt->execute([$amount, $userId]);
}

/**
 * ===============================
 * ✅ PROCESSA RESGATE (PRG)
 * ===============================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redeem_reward_id'])) {
  try {
    $rewardId = (int)($_POST['redeem_reward_id'] ?? 0);
    $userNote = trim((string)($_POST['user_note'] ?? ''));
    $token    = (string)($_POST['redeem_token'] ?? '');

    if ($rewardId <= 0) throw new Exception('Recompensa inválida.');

    // valida token anti-duplicação
    if ($token === '' || empty($_SESSION['redeem_token']) || !hash_equals($_SESSION['redeem_token'], $token)) {
      throw new Exception('Requisição inválida ou repetida.');
    }
    unset($_SESSION['redeem_token']); // consome token

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
    $balanceCheck = (int)($stmt->fetchColumn() ?? 0);

    if ($balanceCheck < $cost) throw new Exception('Saldo insuficiente.');

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

/**
 * ===============================
 * DADOS DA PÁGINA
 * ===============================
 */
ensure_wallet_int($userId);

// Saldo
$stmt = db()->prepare("SELECT COALESCE(balance, 0) FROM popper_coin_wallets WHERE user_id=?");
$stmt->execute([$userId]);
$balance = (int)($stmt->fetchColumn() ?? 0);

// Recompensas (com inventário) — (se você usa em modal / cards, já fica aqui)
$rewards = db()->query("
  SELECT id, title, description, cost, inventory
  FROM popper_coin_rewards
  WHERE is_active = 1
  ORDER BY sort_order ASC, id ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Seus pedidos (últimos 30)
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

// Extrato (últimos 10)
$stmt = db()->prepare("
  SELECT id, amount, action_type, reason, created_at
  FROM popper_coin_ledger
  WHERE user_id = ?
  ORDER BY id DESC
  LIMIT 10
");
$stmt->execute([$userId]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Histórico de aprovações (últimos 10)
$approvalLogs = [];
try {
  $stmt = db()->prepare("
    SELECT l.created_at, l.action, l.status, l.note, l.approved_by_name,
           rw.title AS reward_title
    FROM approval_logs l
    JOIN popper_coin_redemptions r ON r.id = l.entity_id AND l.entity_type = 'coins_redemption'
    JOIN popper_coin_rewards rw ON rw.id = r.reward_id
    WHERE r.user_id = ?
    ORDER BY l.created_at DESC
    LIMIT 10
  ");
  $stmt->execute([$userId]);
  $approvalLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $approvalLogs = [];
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Popper Coins — <?= htmlspecialchars((string)APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/assets/css/base.css') ?>" />
  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/assets/css/dashboard.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/assets/css/dropdowns.css') ?>" />
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(__DIR__ . '/assets/css/header.css') ?>" />
  <link rel="stylesheet" href="/assets/css/coins.css?v=<?= filemtime(__DIR__ . '/assets/css/coins.css') ?>" />
</head>
<body class="page">

<?php require_once __DIR__ . '/app/header.php'; ?>

<main class="container coins">
  <div class="pc-header">
    <h1 class="pc-title">Popper Coins</h1>
    <p class="pc-subtitle">Acompanhe seus pedidos.</p>
  </div>

  <?php if ($success): ?>
    <div class="alert alert--ok"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <div class="pc-container">
    <div class="pc-grid">
      <!-- SALDO -->
      <div class="pc-card pc-card--balance">
        <div class="pc-balance-head">
          <h3 class="pc-balance-title">Seu saldo</h3>
        </div>

        <div class="pc-balance-value">
          <div class="pc-balance-num"><?= number_format((int)$balance, 0, ',', '.') ?></div>
          <div class="pc-balance-unit">coins</div>
        </div>

        <div class="pc-balance-foot">
          <span class="pc-balance-label">Usuário</span>
          <span class="pc-balance-user"><?= htmlspecialchars((string)($u['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      </div>

      <!-- SEUS PEDIDOS -->
      <div class="pc-card pc-card--requests">
        <div class="pc-card-head">
          <h3 class="pc-card-title">Seus pedidos</h3>
          <span class="pc-card-badge">Últimos 30</span>
        </div>

        <div class="table-wrap pc-table-scroll pc-table-scroll--requests">
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
                    <td>
                      <span class="pill <?= $pill ?>">
                        <?= htmlspecialchars(t_status($st), ENT_QUOTES, 'UTF-8') ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- EXTRATO -->
    <div class="pc-card">
      <div class="pc-card-head">
        <h3 class="pc-card-title">Extrato</h3>
        <span class="pc-card-badge">Últimos 10</span>
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
                  <td><?= htmlspecialchars(t_action((string)$e['action_type']), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string)($e['reason'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="right"><?= (int)$e['amount'] ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- HISTÓRICO DE APROVAÇÕES -->
    <div class="pc-card">
      <div class="pc-card-head">
        <h3 class="pc-card-title">Histórico de aprovações</h3>
        <span class="pc-card-badge">Últimos 10</span>
      </div>

      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Data</th>
              <th>Recompensa</th>
              <th>Ação</th>
              <th>Status</th>
              <th>Por</th>
              <th>Obs.</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($approvalLogs)): ?>
              <tr><td colspan="6" class="muted">Sem ações registradas.</td></tr>
            <?php else: ?>
              <?php foreach ($approvalLogs as $l): ?>
                <tr>
                  <td><?= htmlspecialchars((string)($l['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string)($l['reward_title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars(t_approval_action((string)($l['action'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars(t_status((string)($l['status'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string)($l['approved_by_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string)($l['note'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</main>

<?php require_once __DIR__ . '/app/footer.php'; ?>

<script src="/assets/js/header.js?v=<?= filemtime(__DIR__ . '/assets/js/header.js') ?>"></script>
<script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdowns.js') ?>"></script>
<script src="/assets/js/coins.js?v=<?= filemtime(__DIR__ . '/assets/js/coins.js') ?>"></script>
</body>
</html>