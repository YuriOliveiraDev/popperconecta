<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/permissions.php';
require_once __DIR__ . '/../app/poppers_coins.php';
require_admin_perm('admin.rh');

$u = current_user();

// ✅ para o header marcar Popper Coins como ativo
$activePage = 'coins';

// Dashboards para o header
try {
  $dashboards = db()->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}

$success = '';
$error = '';

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

/**
 * ✅ Para o SELECT do formulário: lista leve (não precisa do saldo aqui)
 * (assim não pesa e não precisa carregar tudo ordenado por saldo)
 */
$userOptions = db()->query("
  SELECT id, name, email
  FROM users
  ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/**
 * ✅ Saldos: TODO MUNDO, ordenado por maior saldo (e com scroll no front)
 */
$rows = db()->query("
  SELECT u.id, u.name, u.email, u.setor, u.hierarquia, u.role,
         COALESCE(w.balance, 0) AS balance
  FROM users u
  LEFT JOIN popper_coin_wallets w ON w.user_id = u.id
  ORDER BY balance DESC, u.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/**
 * ✅ Lançamentos: todos (com scroll no front)
 * Obs: pode ficar muito grande no futuro; se isso crescer demais,
 * aí a gente troca para paginação/“carregar mais”.
 */
$ledger = db()->query("
  SELECT l.id, l.user_id, l.amount, l.action_type, l.reason, l.created_at,
         u.name AS user_name,
         a.name AS admin_name
  FROM popper_coin_ledger l
  JOIN users u ON u.id = l.user_id
  JOIN users a ON a.id = l.created_by
  ORDER BY l.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$actionLabels = [
  'grant'  => 'Adicionar',
  'revoke' => 'Remover',
  'redeem' => 'Resgate',
  'adjust' => 'Ajuste',
];
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>RH · Popper Coins — <?= htmlspecialchars((string)APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/../assets/css/base.css') ?>" />
  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/../assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../assets/css/dashboard.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/../assets/css/dropdowns.css') ?>" />
  <link rel="stylesheet" href="/assets/css/rh_coins.css?v=<?= filemtime(__DIR__ . '/../assets/css/rh_coins.css') ?>" />
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(__DIR__ . '/../assets/css/header.css') ?>" />

  <style>
    /* ✅ viewport de ~10 linhas (ajustável) */
    .balances-scroll{
      max-height: 520px; /* ~10 linhas dependendo do seu CSS de tabela */
      overflow-y: auto;
      border: 1px solid rgba(15,23,42,0.10);
      border-radius: 12px;
      background:#fff;
    }
    .balances-scroll thead th{
      position: sticky;
      top: 0;
      background: #fff;
      z-index: 2;
    }

    .ledger-scroll{
      max-height: 520px;
      overflow-y: auto;
      border-top: 1px solid rgba(15,23,42,0.06);
    }
    .ledger-scroll thead th{
      position: sticky;
      top: 0;
      background: #fff;
      z-index: 2;
    }
  </style>
</head>
<body class="page">

<?php require_once __DIR__ . '/../app/header.php'; ?>

<main class="container rh-coins">
  <h2 class="page-title">Popper Coins</h2>

  <?php if ($success): ?>
    <div class="alert alert--ok alert--purple"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

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
            <?php foreach ($userOptions as $r): ?>
              <option value="<?= (int)$r['id'] ?>">
                <?= htmlspecialchars((string)$r['name'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars((string)$r['email'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="field">
          <span class="field__label">Ação</span>
          <select class="field__control" name="action" required>
            <option value="grant">Adicionar</option>
            <option value="revoke">Remover</option>
            <option value="redeem">Resgate</option>
            <option value="adjust">Ajuste</option>
          </select>
        </label>

        <label class="field">
          <span class="field__label">Quantidade</span>
          <input class="field__control" name="amount" type="number" step="1" required />
          <div class="small">Use número positivo. "Remover/Resgate" vira negativo automaticamente.</div>
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
        <p class="card__subtitle">Ordenado do maior para o menor (rolagem para ver mais).</p>
      </div>

      <div class="balances-scroll">
        <div class="table-wrap">
          <table class="table balances-table">
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
                    <span class="truncate"><?= htmlspecialchars((string)$r['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="small truncate"><?= htmlspecialchars((string)$r['email'], ENT_QUOTES, 'UTF-8') ?></span>
                  </td>
                  <td><span class="truncate"><?= htmlspecialchars((string)($r['setor'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span></td>
                  <td class="right">
                    <span class="pill <?= $bal >= 0 ? 'pill--pos' : 'pill--neg' ?>"><?= $bal ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$rows): ?>
                <tr><td colspan="3" class="muted">Sem usuários.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>

  <section class="card card--mt">
    <div class="card__header">
      <h3 class="card__title">Últimos lançamentos</h3>
      <p class="card__subtitle">Histórico global (rolagem para ver mais).</p>
    </div>

    <div class="ledger-scroll">
      <div class="table-wrap ledger-wrap">
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
                <td>
                  <?php
                    $type = (string)$l['action_type'];
                    echo htmlspecialchars($actionLabels[$type] ?? $type, ENT_QUOTES, 'UTF-8');
                  ?>
                </td>
                <td class="right">
                  <span class="pill <?= $amt >= 0 ? 'pill--pos' : 'pill--neg' ?>"><?= $amt ?></span>
                </td>
                <td><?= htmlspecialchars((string)($l['reason'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)$l['admin_name'], ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$ledger): ?>
              <tr><td colspan="6" class="muted">Nenhum lançamento.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/../app/footer.php'; ?>

<script src="/assets/js/header.js?v=<?= filemtime(__DIR__ . '/../assets/js/header.js') ?>"></script>
<script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/../assets/js/dropdowns.js') ?>"></script>
</body>
</html>