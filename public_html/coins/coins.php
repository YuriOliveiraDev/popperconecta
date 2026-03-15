<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_login();

date_default_timezone_set('America/Sao_Paulo');

/**
 * ===============================
 * TRADUÇÕES UI (EN → PT-BR)
 * ===============================
 */
function t_status(string $status): string
{
  $s = strtolower(trim($status));
  return match ($s) {
    'pending'   => 'Pendente',
    'approved'  => 'Aprovado',
    'rejected'  => 'Recusado',
    'cancelled' => 'Cancelado',
    default     => ($s !== '' ? mb_convert_case($s, MB_CASE_TITLE, 'UTF-8') : '—'),
  };
}

function t_action(string $action): string
{
  $a = strtolower(trim($action));
  return match ($a) {
    'earn'    => 'Ganho',
    'spend'   => 'Gasto',
    'hold'    => 'Reserva',
    'release' => 'Liberação',
    'refund'  => 'Estorno',
    default   => ($a !== '' ? mb_convert_case($a, MB_CASE_TITLE, 'UTF-8') : '—'),
  };
}

function t_approval_action(string $action): string
{
  $a = strtolower(trim($action));
  return match ($a) {
    'approve' => 'Aprovou',
    'reject'  => 'Recusou',
    'cancel'  => 'Cancelou',
    'create'  => 'Criou',
    default   => ($a !== '' ? mb_convert_case($a, MB_CASE_TITLE, 'UTF-8') : '—'),
  };
}

function h(mixed $value): string
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function fmt_dt(?string $value): string
{
  if (!$value) {
    return '—';
  }

  $ts = strtotime($value);
  if ($ts === false) {
    return (string) $value;
  }

  return date('d/m/Y H:i', $ts);
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
$userId = (int) ($u['id'] ?? 0);
$activePage = 'coins';
$page_title = 'Popper Coins';
$html_class = 'page coins-page';

/**
 * ===============================
 * DASHBOARDS NO HEADER
 * ===============================
 */
try {
  $dashboards = db()->query("
    SELECT slug, name, icon
    FROM dashboards
    WHERE is_active = TRUE
    ORDER BY sort_order ASC
  ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}

$current_dash = $_GET['dash'] ?? 'executivo';
$success = '';
$error = '';

if (isset($_GET['ok'])) {
  $success = 'Pedido enviado.';
}
if (isset($_GET['err'])) {
  $error = 'Erro: ' . (string) $_GET['err'];
}

/**
 * ===============================
 * ASSETS DA PÁGINA
 * ===============================
 */
$extra_css = [
  '/assets/css/base.css?v=' . @filemtime(APP_ROOT . '/assets/css/base.css'),
  '/assets/css/coins.css?v=' . @filemtime(APP_ROOT . '/assets/css/coins.css'),
  '/assets/css/header.css?v=' . @filemtime(APP_ROOT . '/assets/css/header.css'),

  ];

/**
 * ===============================
 * TOKEN ANTI-DUPLICAÇÃO
 * ===============================
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
if (empty($_SESSION['redeem_token'])) {
  $_SESSION['redeem_token'] = bin2hex(random_bytes(16));
}
$redeemToken = (string) $_SESSION['redeem_token'];

/**
 * ===============================
 * HELPERS
 * ===============================
 */
function ensure_wallet_int(int $userId): void
{
  $stmt = db()->prepare("
    INSERT IGNORE INTO popper_coin_wallets (user_id, balance)
    VALUES (?, 0)
  ");
  $stmt->execute([$userId]);
}

function apply_ledger_no_tx(int $userId, int $amount, string $type, ?string $reason, int $actorId): void
{
  ensure_wallet_int($userId);

  $stmt = db()->prepare("
    INSERT INTO popper_coin_ledger (user_id, amount, action_type, reason, created_by)
    VALUES (?, ?, ?, ?, ?)
  ");
  $stmt->execute([$userId, $amount, $type, $reason, $actorId]);

  $stmt = db()->prepare("
    UPDATE popper_coin_wallets
    SET balance = balance + ?
    WHERE user_id = ?
  ");
  $stmt->execute([$amount, $userId]);
}

/**
 * ===============================
 * PROCESSA RESGATE
 * ===============================
 */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['redeem_reward_id'])) {
  $db = db();

  try {
    $rewardId = (int) ($_POST['redeem_reward_id'] ?? 0);
    $userNote = trim((string) ($_POST['user_note'] ?? ''));
    $token = (string) ($_POST['redeem_token'] ?? '');

    if ($rewardId <= 0) {
      throw new Exception('Recompensa inválida.');
    }

    if ($token === '' || empty($_SESSION['redeem_token']) || !hash_equals($_SESSION['redeem_token'], $token)) {
      throw new Exception('Requisição inválida ou repetida.');
    }

    unset($_SESSION['redeem_token']);

    $db->beginTransaction();

    $stmt = $db->prepare("
      SELECT COUNT(*)
      FROM popper_coin_redemptions
      WHERE user_id = ? AND reward_id = ? AND status = 'pending'
      FOR UPDATE
    ");
    $stmt->execute([$userId, $rewardId]);

    if ((int) $stmt->fetchColumn() > 0) {
      throw new Exception('Você já tem um pedido pendente para esta recompensa.');
    }

    $stmt = $db->prepare("
      SELECT id, title, cost, inventory, is_active
      FROM popper_coin_rewards
      WHERE id = ?
      FOR UPDATE
    ");
    $stmt->execute([$rewardId]);
    $rw = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rw) {
      throw new Exception('Recompensa não encontrada.');
    }

    if ((int) $rw['is_active'] !== 1) {
      throw new Exception('Recompensa indisponível.');
    }

    $qty = 1;
    $inventory = (int) ($rw['inventory'] ?? 0);
    if ($inventory < $qty) {
      throw new Exception('Sem inventário suficiente.');
    }

    $cost = (int) ($rw['cost'] ?? 0);
    if ($cost <= 0) {
      throw new Exception('Custo inválido.');
    }

    $title = (string) ($rw['title'] ?? '');

    ensure_wallet_int($userId);

    $stmt = $db->prepare("
      SELECT balance
      FROM popper_coin_wallets
      WHERE user_id = ?
      FOR UPDATE
    ");
    $stmt->execute([$userId]);
    $balanceCheck = (int) ($stmt->fetchColumn() ?? 0);

    if ($balanceCheck < $cost) {
      throw new Exception('Saldo insuficiente.');
    }

    apply_ledger_no_tx($userId, -abs($cost), 'hold', 'Resgate solicitado (pendente): ' . $title, $userId);

    $stmt = $db->prepare("
      UPDATE popper_coin_rewards
      SET inventory = inventory - ?
      WHERE id = ?
    ");
    $stmt->execute([$qty, $rewardId]);

    $stmt = $db->prepare("
      INSERT INTO popper_coin_redemptions (user_id, reward_id, cost, qty, status, user_note, created_at)
      VALUES (?, ?, ?, ?, 'pending', ?, NOW())
    ");
    $stmt->execute([$userId, $rewardId, $cost, $qty, ($userNote !== '' ? $userNote : null)]);

    $rhUsers = $db->query("
      SELECT id
      FROM users
      WHERE role IN ('rh', 'admin')
    ")->fetchAll(PDO::FETCH_ASSOC);

    if ($rhUsers) {
      $stmtN = $db->prepare("
        INSERT INTO notifications (user_id, type, title, message, link)
        VALUES (?, 'coins_redeem_requested', 'Novo pedido de resgate', ?, '/rh_redemptions.php')
      ");

      $msg = (string) ($u['name'] ?? 'Usuário') . ' solicitou "' . $title . '" (' . $cost . ' coins).';

      foreach ($rhUsers as $rh) {
        $stmtN->execute([(int) $rh['id'], $msg]);
      }
    }

    $db->commit();

    $_SESSION['redeem_token'] = bin2hex(random_bytes(16));
    header('Location: /coins.php?ok=redeem');
    exit;
  } catch (Throwable $e) {
    if ($db->inTransaction()) {
      $db->rollBack();
    }

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
$stmt = db()->prepare("
  SELECT COALESCE(balance, 0)
  FROM popper_coin_wallets
  WHERE user_id = ?
");
$stmt->execute([$userId]);
$balance = (int) ($stmt->fetchColumn() ?? 0);

// Seus pedidos
$stmt = db()->prepare("
  SELECT
    r.id,
    r.status,
    r.cost,
    r.qty,
    r.user_note,
    r.created_at,
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
  LIMIT 10
");
$stmt->execute([$userId]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Histórico de aprovações
$approvalLogs = [];
try {
  $stmt = db()->prepare("
    SELECT
      l.created_at,
      l.action,
      l.status,
      l.note,
      l.approved_by_name,
      rw.title AS reward_title
    FROM approval_logs l
    JOIN popper_coin_redemptions r
      ON r.id = l.entity_id
     AND l.entity_type = 'coins_redemption'
    JOIN popper_coin_rewards rw
      ON rw.id = r.reward_id
    WHERE r.user_id = ?
    ORDER BY l.created_at DESC
    LIMIT 10
  ");
  $stmt->execute([$userId]);
  $approvalLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $approvalLogs = [];
}

$totalRequests = count($requests);
$totalPending = 0;
$totalApproved = 0;
$totalRejected = 0;

foreach ($requests as $r) {
  $st = strtolower((string) ($r['status'] ?? ''));

  if ($st === 'pending') {
    $totalPending++;
  }
  if ($st === 'approved') {
    $totalApproved++;
  }
  if ($st === 'rejected') {
    $totalRejected++;
  }
}

require_once APP_ROOT . '/app/layout/header.php';
?>

<main class="container coins">
  <div class="pc-header">
    <h1 class="pc-title">Popper Coins</h1>
    <p class="pc-subtitle">Acompanhe seu saldo, pedidos, extrato e aprovações.</p>
  </div>

  <?php if ($success): ?>
    <div class="alert alert--ok"><?= h($success) ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
  <?php endif; ?>

  <div class="pc-container">
    <section class="pc-summary-grid">
      <div class="pc-card pc-card--balance">
        <div class="pc-balance-head">
          <h3 class="pc-balance-title">Seu saldo</h3>
          <span class="pc-balance-pill">Popper Coins</span>
        </div>

        <div class="pc-balance-value">
          <div class="pc-balance-num"><?= number_format($balance, 0, ',', '.') ?></div>
          <div class="pc-balance-unit">coins</div>
        </div>

        <div class="pc-balance-foot">
          <span class="pc-balance-label">Usuário</span>
          <span class="pc-balance-user"><?= h((string) ($u['name'] ?? '')) ?></span>
        </div>
      </div>

      <div class="pc-mini-stats">
        <div class="pc-mini-stat">
          <span class="pc-mini-stat__label">Pedidos</span>
          <strong class="pc-mini-stat__value"><?= $totalRequests ?></strong>
        </div>
        <div class="pc-mini-stat">
          <span class="pc-mini-stat__label">Pendentes</span>
          <strong class="pc-mini-stat__value"><?= $totalPending ?></strong>
        </div>
        <div class="pc-mini-stat">
          <span class="pc-mini-stat__label">Aprovados</span>
          <strong class="pc-mini-stat__value"><?= $totalApproved ?></strong>
        </div>
        <div class="pc-mini-stat">
          <span class="pc-mini-stat__label">Recusados</span>
          <strong class="pc-mini-stat__value"><?= $totalRejected ?></strong>
        </div>
      </div>
    </section>

    <div class="pc-grid pc-grid--top">
      <section class="pc-card pc-card--requests">
        <div class="pc-card-head">
          <h3 class="pc-card-title">Seus pedidos</h3>
          <span class="pc-card-badge">Últimos 30</span>
        </div>

        <div class="pc-tools">
          <input type="text" class="pc-search" id="searchRequests" placeholder="Buscar em pedidos..." />
          <select class="pc-select" id="filterRequestsStatus">
            <option value="all">Todos os status</option>
            <option value="pending">Pendentes</option>
            <option value="approved">Aprovados</option>
            <option value="rejected">Recusados</option>
            <option value="cancelled">Cancelados</option>
          </select>
        </div>

        <div class="table-wrap pc-table-scroll">
          <table class="table">
            <thead>
              <tr>
                <th>Data</th>
                <th>Recompensa</th>
                <th class="right">Custo</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="requestsTableBody">
              <?php if (!$requests): ?>
                <tr>
                  <td colspan="4" class="muted">Nenhum pedido ainda.</td>
                </tr>
              <?php endif; ?>

              <?php
              $shownRequests = 0;
              foreach ($requests as $r):
                $shownRequests++;
                $st = (string) $r['status'];
                $pill = $st === 'approved'
                  ? 'pill--approved'
                  : ($st === 'rejected' ? 'pill--rejected' : 'pill--pending');
              ?>
                <tr
                  data-status="<?= h(strtolower($st)) ?>"
                  data-search="<?= h(mb_strtolower((string) $r['reward_title'] . ' ' . (string) $r['created_at'] . ' ' . t_status($st), 'UTF-8')) ?>"
                >
                  <td><?= h(fmt_dt((string) $r['created_at'])) ?></td>
                  <td><?= h((string) $r['reward_title']) ?></td>
                  <td class="right"><?= (int) $r['cost'] ?></td>
                  <td>
                    <span class="pill <?= h($pill) ?>">
                      <?= h(t_status($st)) ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>

              <?php for ($i = $shownRequests; $i < 10; $i++): ?>
                <tr class="row-empty">
                  <td>&nbsp;</td>
                  <td></td>
                  <td></td>
                  <td></td>
                </tr>
              <?php endfor; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="pc-card pc-card--ledger">
        <div class="pc-card-head">
          <h3 class="pc-card-title">Extrato</h3>
          <span class="pc-card-badge">Últimos 10</span>
        </div>

        <div class="pc-tools">
          <input type="text" class="pc-search" id="searchLedger" placeholder="Buscar no extrato..." />
        </div>

        <div class="table-wrap pc-table-scroll">
          <table class="table">
            <thead>
              <tr>
                <th>Data</th>
                <th>Ação</th>
                <th>Motivo</th>
                <th class="right">Valor</th>
              </tr>
            </thead>
            <tbody id="ledgerTableBody">
              <?php if (!$entries): ?>
                <tr>
                  <td colspan="4" class="muted">Sem lançamentos.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($entries as $e): ?>
                  <tr data-search="<?= h(mb_strtolower((string) $e['created_at'] . ' ' . t_action((string) $e['action_type']) . ' ' . (string) ($e['reason'] ?? ''), 'UTF-8')) ?>">
                    <td><?= h(fmt_dt((string) $e['created_at'])) ?></td>
                    <td><?= h(t_action((string) $e['action_type'])) ?></td>
                    <td><?= h((string) ($e['reason'] ?? '—')) ?></td>
                    <td class="right"><?= (int) $e['amount'] ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <section class="pc-card pc-card--approvals">
      <div class="pc-card-head">
        <h3 class="pc-card-title">Histórico de aprovações</h3>
        <span class="pc-card-badge">Últimos 10</span>
      </div>

      <div class="pc-tools">
        <input type="text" class="pc-search" id="searchApprovals" placeholder="Buscar no histórico..." />
      </div>

      <div class="table-wrap pc-table-scroll">
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
          <tbody id="approvalsTableBody">
            <?php if (empty($approvalLogs)): ?>
              <tr>
                <td colspan="6" class="muted">Sem ações registradas.</td>
              </tr>
            <?php endif; ?>

            <?php
            $shownApprovals = 0;
            foreach ($approvalLogs as $l):
              $shownApprovals++;
            ?>
              <tr data-search="<?= h(
                mb_strtolower(
                  (string) ($l['created_at'] ?? '') . ' ' .
                  (string) ($l['reward_title'] ?? '') . ' ' .
                  t_approval_action((string) ($l['action'] ?? '')) . ' ' .
                  t_status((string) ($l['status'] ?? '')) . ' ' .
                  (string) ($l['approved_by_name'] ?? '') . ' ' .
                  (string) ($l['note'] ?? ''),
                  'UTF-8'
                )
              ) ?>">
                <td><?= h(fmt_dt((string) ($l['created_at'] ?? ''))) ?></td>
                <td><?= h((string) ($l['reward_title'] ?? '')) ?></td>
                <td><?= h(t_approval_action((string) ($l['action'] ?? ''))) ?></td>
                <td><?= h(t_status((string) ($l['status'] ?? ''))) ?></td>
                <td><?= h((string) ($l['approved_by_name'] ?? '—')) ?></td>
                <td><?= h((string) ($l['note'] ?? '—')) ?></td>
              </tr>
            <?php endforeach; ?>

            <?php for ($i = $shownApprovals; $i < 10; $i++): ?>
              <tr class="row-empty">
                <td>&nbsp;</td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
              </tr>
            <?php endfor; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
  function norm(text) {
    return String(text || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim();
  }

  function filterRows(config) {
    const input = document.getElementById(config.inputId);
    const tbody = document.getElementById(config.tbodyId);
    if (!input || !tbody) return;

    const apply = function () {
      const q = norm(input.value);
      const rows = Array.from(tbody.querySelectorAll('tr'));

      rows.forEach(function (row) {
        if (row.querySelector('.muted')) return;
        if (row.classList.contains('row-empty')) return;

        const hay = norm(row.dataset.search || row.textContent || '');
        let visible = !q || hay.includes(q);

        if (visible && typeof config.extraCheck === 'function') {
          visible = config.extraCheck(row);
        }

        row.style.display = visible ? '' : 'none';
      });
    };

    input.addEventListener('input', apply);
    apply();
  }

  const statusFilter = document.getElementById('filterRequestsStatus');
  const requestsInput = document.getElementById('searchRequests');
  const requestsBody = document.getElementById('requestsTableBody');

  function applyRequestsFilter() {
    if (!requestsBody) return;

    const q = norm(requestsInput ? requestsInput.value : '');
    const st = statusFilter ? statusFilter.value : 'all';
    const rows = Array.from(requestsBody.querySelectorAll('tr'));

    rows.forEach(function (row) {
      if (row.querySelector('.muted')) return;
      if (row.classList.contains('row-empty')) return;

      const hay = norm(row.dataset.search || row.textContent || '');
      const rowStatus = String(row.dataset.status || 'all');

      let visible = !q || hay.includes(q);

      if (visible && st !== 'all' && rowStatus !== st) {
        visible = false;
      }

      row.style.display = visible ? '' : 'none';
    });
  }

  if (requestsInput) requestsInput.addEventListener('input', applyRequestsFilter);
  if (statusFilter) statusFilter.addEventListener('change', applyRequestsFilter);
  applyRequestsFilter();

  filterRows({ inputId: 'searchLedger', tbodyId: 'ledgerTableBody' });
  filterRows({ inputId: 'searchApprovals', tbodyId: 'approvalsTableBody' });
});
</script>

<script src="/assets/js/header.js?v=<?= @filemtime(APP_ROOT . '/assets/js/header.js') ?>"></script>

<?php require_once APP_ROOT . '/app/layout/footer.php'; ?>