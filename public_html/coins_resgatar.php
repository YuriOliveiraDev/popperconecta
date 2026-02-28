<?php
declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';

require_login();

require_once __DIR__ . '/app/notifications.php';


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
$activePage = 'coins_resgatar'; // Novo activePage para o submenu

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
    $token = (string)($_POST['token'] ?? '');

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
    $stmt = db()->prepare("SELECT balance FROM popper_coin_wallets WHERE user_id = ? FOR UPDATE");
    $stmt->execute([$userId]);
    $balanceCheck = (int)($stmt->fetchColumn() ?? 0);

    if ($balanceCheck < $cost) throw new Exception('Saldo insuficiente.');

    // segura saldo (desconto temporário)
    apply_ledger_no_tx($userId, -abs($cost), 'hold', 'Resgate solicitado (pendente): ' . $title, $userId);

    // segura inventário
    $stmt = db()->prepare("UPDATE popper_coin_rewards SET inventory = inventory - ? WHERE id = ?");
    $stmt->execute([$qty, $rewardId]);

    // cria pedido
    $stmt = db()->prepare("
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
    header('Location: /coins_resgatar.php?ok=redeem');
    exit;
  } catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    $_SESSION['redeem_token'] = bin2hex(random_bytes(16));
    header('Location: /coins_resgatar.php?err=' . urlencode($e->getMessage()));
    exit;
  }
}

ensure_wallet_int($userId);

// Saldo
$stmt = db()->prepare("SELECT COALESCE(balance, 0) FROM popper_coin_wallets WHERE user_id=?");
$stmt->execute([$userId]);
$balance = (int)($stmt->fetchColumn() ?? 0);

// Recompensas (com inventário e imagem)
$rewards = db()->query("
  SELECT id, title, description, cost, inventory, image_url
  FROM popper_coin_rewards
  WHERE is_active = 1
  ORDER BY sort_order ASC, id ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Resgatar Popper Coins — <?= htmlspecialchars((string)APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/assets/css/base.css') ?>" />
  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/assets/css/dashboard.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/assets/css/dropdowns.css') ?>" />
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(__DIR__ . '/assets/css/header.css') ?>" />
  <link rel="stylesheet" href="/assets/css/coins.css?v=<?= filemtime(__DIR__ . '/assets/css/coins.css') ?>" />

  <style>
    /* Ajustes para alinhar cards */
    .reward-card {
      display: flex;
      flex-direction: column;
      min-height: 420px; /* Altura mínima fixa */
      justify-content: space-between;
    }

    .reward-body {
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .reward-meta {
      margin-bottom: 12px;
    }

    .reward-form {
      margin-top: auto;
    }

    .reward-hint {
      min-height: 18px; /* Reserva espaço sempre */
      margin: 0 0 10px 0;
      font-size: 12px;
      font-weight: 700;
      color: rgba(239,68,68,.95);
      line-height: 18px;
    }

    .reward-hint:not(.is-on) {
      color: transparent; /* Espaço reservado sem texto */
    }
  </style>
</head>
<body class="page">

<?php require_once __DIR__ . '/app/header.php'; ?>

<main class="container coins">
  <div class="pc-header">
    <h1 class="pc-title">Resgatar Popper Coins</h1>
    <p class="pc-subtitle">Escolha suas recompensas e solicite resgate.</p>
  </div>

  <?php if ($success): ?><div class="alert alert--ok"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

  <div class="pc-container">
    <!-- SALDO (para referência) -->
    <div class="pc-card pc-card--balance">
      <div class="pc-balance-head">
        <h3 class="pc-balance-title">Seu saldo</h3>
        <span class="pc-balance-pill">Popper Coins</span>
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

    <!-- CATÁLOGO DE RECOMPENSAS (CARDS ESTILO E-COMMERCE) -->
    <div class="pc-card">
      <div class="pc-card-head">
        <h3 class="pc-card-title">Catálogo de recompensas</h3>
        <span class="pc-card-badge">Ativas</span>
      </div>
      <p class="muted" style="margin:0 0 16px 0;">O saldo e o inventário serão bloqueados temporariamente até decisão do RH.</p>

      <?php if (!$rewards): ?>
        <div class="muted">Nenhuma recompensa cadastrada.</div>
      <?php else: ?>
        <div class="reward-grid">
          <?php foreach ($rewards as $rw): ?>
            <?php
              $inv = (int)($rw['inventory'] ?? 0);
              $cost = (int)($rw['cost'] ?? 0);
              $disabled = ($inv <= 0 || $balance < $cost);

              $title = (string)($rw['title'] ?? '');
              $desc = trim((string)($rw['description'] ?? ''));
              $img = trim((string)($rw['image_url'] ?? ''));

              $hint = '';
              if ($inv <= 0) $hint = 'Indisponível no momento.';
              elseif ($balance < $cost) $hint = 'Saldo insuficiente.';
            ?>

            <div class="reward-card<?= $disabled ? ' is-disabled' : '' ?>">
              <div class="reward-media">
                <?php if ($img !== ''): ?>
                  <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                <?php else: ?>
                  <div class="reward-fallback" aria-hidden="true">🎁</div>
                <?php endif; ?>
              </div>

              <div class="reward-body">
                <div class="reward-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></div>

                <?php if ($desc !== ''): ?>
                  <div class="reward-desc"><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></div>
                <?php else: ?>
                  <div class="reward-desc muted">Sem descrição.</div>
                <?php endif; ?>

                <div class="reward-meta">
                  <div class="reward-price">
                    <span class="reward-price-num"><?= (int)$cost ?></span>
                    <span class="reward-price-unit">coins</span>
                  </div>

                  <div class="reward-stock">
                    Estoque: <strong><?= (int)$inv ?></strong>
                  </div>
                </div>

                <form method="post" class="reward-form">
                  <input type="hidden" name="redeem_reward_id" value="<?= (int)$rw['id'] ?>" />
                  <input type="hidden" name="token" value="<?= htmlspecialchars($redeemToken, ENT_QUOTES, 'UTF-8') ?>" />

                  <div class="reward-hint <?= $hint !== '' ? 'is-on' : '' ?>">
                    <?= htmlspecialchars($hint !== '' ? $hint : ' ', ENT_QUOTES, 'UTF-8') ?>
                  </div>

                  <button class="btn btn--primary reward-btn"
                          type="submit"
                          <?= $disabled ? 'disabled' : '' ?>
                          onclick="return confirm('Solicitar resgate? Saldo e inventário serão bloqueados até decisão do RH.');">
                    Resgatar
                  </button>

                  <!-- Obs desativado: campo hidden vazio -->
                  <input type="hidden" name="user_note" value="" />
                </form>
              </div>
            </div>

          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/app/footer.php'; ?>

<script src="/assets/js/header.js?v=<?= filemtime(__DIR__ . '/assets/js/header.js') ?>"></script>
<script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdowns.js') ?>"></script>
<script src="/assets/js/coins.js?v=<?= filemtime(__DIR__ . '/assets/js/coins.js') ?>"></script>
</body>
</html>