<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_admin();

$u = current_user();

// Dashboards para o header
try {
  $dashboards = db()->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}

$success = '';
$error = '';

function ensure_wallet(int $userId): void {
  $stmt = db()->prepare("INSERT IGNORE INTO popper_coin_wallets (user_id, balance) VALUES (?, 0)");
  $stmt->execute([$userId]);
}

function apply_ledger(int $userId, int $amount, string $type, ?string $reason, int $adminId): void {
  ensure_wallet($userId);

  db()->beginTransaction();
  try {
    $stmt = db()->prepare("INSERT INTO popper_coin_ledger (user_id, amount, action_type, reason, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $amount, $type, $reason, $adminId]);

    $stmt = db()->prepare("UPDATE popper_coin_wallets SET balance = balance + ? WHERE user_id = ?");
    $stmt->execute([$amount, $userId]);

    db()->commit();
  } catch (Throwable $e) {
    db()->rollBack();
    throw $e;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $userId = (int)($_POST['user_id'] ?? 0);
    $amount = (int)($_POST['amount'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    $reason = trim((string)($_POST['reason'] ?? ''));

    if ($userId <= 0) throw new Exception('Selecione um usuário.');
    if (!in_array($action, ['grant','revoke','redeem','adjust'], true)) throw new Exception('Ação inválida.');
    if ($amount === 0) throw new Exception('Informe uma quantidade diferente de zero.');

    // Normalização: revoke e redeem normalmente são negativos
    if (in_array($action, ['revoke','redeem'], true) && $amount > 0) {
      $amount = -$amount;
    }

    apply_ledger($userId, $amount, $action, $reason !== '' ? $reason : null, (int)$u['id']);
    $success = 'Lançamento registrado com sucesso.';
  } catch (Throwable $e) {
    $error = 'Erro: ' . $e->getMessage();
  }
}

// Lista usuários com saldo
$rows = db()->query("
  SELECT u.id, u.name, u.email, u.setor, u.hierarquia, u.role,
         COALESCE(w.balance, 0) AS balance
  FROM users u
  LEFT JOIN popper_coin_wallets w ON w.user_id = u.id
  ORDER BY u.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Últimos lançamentos (auditoria)
$ledger = db()->query("
  SELECT l.id, l.user_id, l.amount, l.action_type, l.reason, l.created_at,
         u.name AS user_name,
         a.name AS admin_name
  FROM popper_coin_ledger l
  JOIN users u ON u.id = l.user_id
  JOIN users a ON a.id = l.created_by
  ORDER BY l.id DESC
  LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>RH · Popper Coins — <?= htmlspecialchars((string)APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/../assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../assets/css/dashboard.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/../assets/css/dropdowns.css') ?>" />

  <style>
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:20px}
    @media(max-width:900px){.grid2{grid-template-columns:1fr}}
    .small{font-size:12px;color:var(--muted)}
    .pill{display:inline-block;padding:4px 10px;border-radius:999px;font-weight:800;font-size:12px}
    .pill--pos{background:rgba(22,163,74,.12);color:#166534}
    .pill--neg{background:rgba(220,38,38,.12);color:#991b1b}
  </style>
</head>
<body class="page">

<?php require_once __DIR__ . '/../app/header.php'; ?>

<main class="container">
  <h2 class="page-title">Popper Coins</h2>

  <?php if ($success): ?><div class="alert alert--ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <section class="grid2">
    <div class="card">
      <div class="card__header">
        <h3 class="card__title">Novo lançamento</h3>
        <p class="card__subtitle">Adicionar, remover, resgatar ou ajustar coins.</p>
      </div>

      <form method="post" class="form" autocomplete="off">
        <label class="field">
          <span class="field__label">Usuário</span>
          <select class="field__control" name="user_id" required>
            <option value="">Selecione...</option>
            <?php foreach ($rows as $r): ?>
              <option value="<?= (int)$r['id'] ?>">
                <?= htmlspecialchars((string)$r['name'], ENT_QUOTES, 'UTF-8') ?> — saldo: <?= (int)$r['balance'] ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="field">
          <span class="field__label">Ação</span>
          <select class="field__control" name="action" required>
            <option value="grant">Adicionar (grant)</option>
            <option value="revoke">Remover (revoke)</option>
            <option value="redeem">Resgate (redeem)</option>
            <option value="adjust">Ajuste (adjust)</option>
          </select>
        </label>

        <label class="field">
          <span class="field__label">Quantidade</span>
          <input class="field__control" name="amount" type="number" step="1" required />
          <div class="small">Use número positivo. “Remover/Resgate” vira negativo automaticamente.</div>
        </label>

        <label class="field">
          <span class="field__label">Motivo (opcional)</span>
          <input class="field__control" name="reason" type="text" maxlength="255" />
        </label>

        <button class="btn btn--primary" type="submit">Salvar lançamento</button>
      </form>
    </div>

    <div class="card">
      <div class="card__header">
        <h3 class="card__title">Saldos (todos os usuários)</h3>
        <p class="card__subtitle">Visão geral rápida.</p>
      </div>

      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Usuário</th>
              <th>Setor</th>
              <th class="right">Saldo</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <?php $bal = (int)$r['balance']; ?>
              <tr>
                <td>
                  <?= htmlspecialchars((string)$r['name'], ENT_QUOTES, 'UTF-8') ?>
                  <div class="small"><?= htmlspecialchars((string)$r['email'], ENT_QUOTES, 'UTF-8') ?></div>
                </td>
                <td><?= htmlspecialchars((string)($r['setor'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="right">
                  <span class="pill <?= $bal >= 0 ? 'pill--pos' : 'pill--neg' ?>"><?= $bal ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="card card--mt">
    <div class="card__header">
      <h3 class="card__title">Últimos lançamentos</h3>
      <p class="card__subtitle">Auditoria (últimos 50).</p>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Data</th>
            <th>Usuário</th>
            <th>Ação</th>
            <th class="right">Valor</th>
            <th>Motivo</th>
            <th>Admin</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ledger as $l): ?>
            <?php $amt = (int)$l['amount']; ?>
            <tr>
              <td><?= htmlspecialchars((string)$l['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)$l['user_name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)$l['action_type'], ENT_QUOTES, 'UTF-8') ?></td>
              <td class="right"><?= $amt ?></td>
              <td><?= htmlspecialchars((string)($l['reason'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)$l['admin_name'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/../assets/js/dropdowns.js') ?>"></script>
</body>
</html>