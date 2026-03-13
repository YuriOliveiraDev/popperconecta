<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/permissions.php';
require_once __DIR__ . '/../app/poppers_coins.php';

require_admin_perm('admin.rh');

$u = current_user();
$activePage = 'coins';

$usersForBatch = [];
try {
  $stmt = db()->query("
    SELECT id, name, email, role
    FROM users
    WHERE is_active = 1
    ORDER BY name ASC
  ");
  $usersForBatch = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $usersForBatch = [];
}

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

$success = '';
$error = '';

try {
  $campaignOptions = db()->query("
    SELECT id, name, description, coins, category
    FROM popper_coin_campaigns
    WHERE is_active = 1
    ORDER BY name ASC
  ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $campaignOptions = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $db = db();

  try {
    $selectedUsers = $_POST['user_ids'] ?? [];
    $amount = (int) ($_POST['amount'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $reason = trim((string) ($_POST['reason'] ?? ''));
    $campaignId = (int) ($_POST['campaign_id'] ?? 0);

    if (!is_array($selectedUsers) || !$selectedUsers) {
      throw new Exception('Selecione pelo menos um usuário.');
    }

    $userIds = array_values(array_unique(array_map('intval', $selectedUsers)));
    $userIds = array_values(array_filter($userIds, fn($id) => $id > 0));

    if (!$userIds) {
      throw new Exception('Nenhum usuário válido foi selecionado.');
    }

    if (count($userIds) > 200) {
      throw new Exception('Selecione no máximo 200 usuários por lançamento.');
    }

    if (!in_array($action, ['grant', 'revoke', 'redeem', 'adjust'], true)) {
      throw new Exception('Ação inválida.');
    }

    if ($campaignId > 0) {
      $stmt = $db->prepare("
        SELECT id, name, description, coins
        FROM popper_coin_campaigns
        WHERE id = ? AND is_active = 1
        LIMIT 1
      ");
      $stmt->execute([$campaignId]);
      $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$campaign) {
        throw new Exception('Campanha inválida ou inativa.');
      }

      $amount = (int) ($campaign['coins'] ?? 0);
      $reason = trim((string) ($campaign['description'] ?? ''));

      if ($reason === '') {
        $reason = (string) ($campaign['name'] ?? 'Campanha');
      }
    }

    if ($amount === 0) {
      throw new Exception('Informe uma quantidade diferente de zero.');
    }

    if (in_array($action, ['revoke', 'redeem'], true) && $amount > 0) {
      $amount = -$amount;
    }

    $db->beginTransaction();

    foreach ($userIds as $uid) {
      apply_ledger_no_tx(
        (int) $uid,
        $amount,
        $action,
        $reason !== '' ? $reason : null,
        (int) $u['id']
      );
    }

    $db->commit();
    $success = 'Lançamento registrado com sucesso para ' . count($userIds) . ' usuário(s).';
  } catch (Throwable $e) {
    if ($db->inTransaction()) {
      $db->rollBack();
    }
    $error = 'Erro: ' . $e->getMessage();
  }
}

$userOptions = db()->query("
  SELECT id, name, email
  FROM users
  WHERE is_active = 1
  ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$rows = db()->query("
  SELECT
    u.id,
    u.name,
    u.email,
    u.setor,
    u.hierarquia,
    u.role,
    COALESCE(w.balance, 0) AS balance
  FROM users u
  LEFT JOIN popper_coin_wallets w ON w.user_id = u.id
  WHERE u.is_active = 1
  ORDER BY balance DESC, u.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$ledger = db()->query("
  SELECT
    l.id,
    l.user_id,
    l.amount,
    l.action_type,
    l.reason,
    l.created_at,
    u.name AS user_name,
    a.name AS admin_name
  FROM popper_coin_ledger l
  JOIN users u ON u.id = l.user_id
  JOIN users a ON a.id = l.created_by
  ORDER BY l.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$actionLabels = [
  'grant' => 'Adicionar',
  'revoke' => 'Remover',
  'redeem' => 'Resgate',
  'adjust' => 'Ajuste',
];

$totalUsers = count($rows);
$totalLedger = count($ledger);
$totalPositive = 0;
$totalNegative = 0;

foreach ($rows as $r) {
  $bal = (int) ($r['balance'] ?? 0);
  if ($bal >= 0) {
    $totalPositive += $bal;
  } else {
    $totalNegative += abs($bal);
  }
}
?>
<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>RH · Popper Coins — <?= htmlspecialchars((string) APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/../assets/css/base.css') ?>" />
  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/../assets/css/users.css') ?>" />
  <link rel="stylesheet"
    href="/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../assets/css/dashboard.css') ?>" />
  <link rel="stylesheet"
    href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/../assets/css/dropdowns.css') ?>" />
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(__DIR__ . '/../assets/css/header.css') ?>" />
  <link rel="stylesheet" href="/assets/css/rh_coins.css?v=<?= filemtime(__DIR__ . '/../assets/css/rh_coins.css') ?>" />
</head>

<body class="page">

  <?php require_once __DIR__ . '/../app/header.php'; ?>

  <main class="container rh-coins-page">
    <div class="coins-page-head">
      <div>
        <h1 class="coins-page-title">Popper Coins · Lançamentos</h1>
        <p class="coins-page-subtitle">Gerencie créditos, ajustes, remoções e lançamentos por campanhas.</p>
      </div>

      <div class="coins-page-actions">
        <a class="btn-modern btn-modern--accent" href="/admin/rh_campaigns.php">
          <span class="btn-modern__icon">↗</span>
          Campanhas
        </a>
      </div>
    </div>

    <?php if ($success): ?>
      <div class="alert alert--ok alert--purple"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <section class="coins-summary-grid">
      <div class="coins-summary-card coins-summary-card--hero">
        <div class="coins-summary-card__head">
          <h3 class="coins-summary-card__title">Resumo do módulo</h3>
          <span class="coins-summary-card__pill">RH / Admin</span>
        </div>

        <div class="coins-summary-card__value">
          <strong><?= number_format($totalUsers, 0, ',', '.') ?></strong>
          <span>usuários ativos</span>
        </div>

        <div class="coins-summary-card__meta">
          <span>Responsável:</span>
          <strong><?= htmlspecialchars((string) ($u['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
      </div>

      <div class="coins-mini-stats">
        <div class="coins-mini-stat">
          <span class="coins-mini-stat__label">Campanhas ativas</span>
          <strong class="coins-mini-stat__value"><?= count($campaignOptions) ?></strong>
        </div>

        <div class="coins-mini-stat">
          <span class="coins-mini-stat__label">Histórico total</span>
          <strong class="coins-mini-stat__value"><?= number_format($totalLedger, 0, ',', '.') ?></strong>
        </div>

        <div class="coins-mini-stat">
          <span class="coins-mini-stat__label">Saldo positivo</span>
          <strong class="coins-mini-stat__value"><?= number_format($totalPositive, 0, ',', '.') ?></strong>
        </div>

        <div class="coins-mini-stat">
          <span class="coins-mini-stat__label">Saldo negativo</span>
          <strong class="coins-mini-stat__value"><?= number_format($totalNegative, 0, ',', '.') ?></strong>
        </div>
      </div>
    </section>

    <section class="coins-main-grid">
      <div class="coins-card coins-launch-card">
        <div class="coins-card__head">
          <h3 class="coins-card__title">Novo lançamento</h3>
          <span class="coins-card__badge">Campanha opcional</span>
        </div>
        <form method="post" class="coins-form" id="coinLaunchForm" autocomplete="off">
          <div class="coins-form-grid">
            <label class="coins-field coins-field--full">
              <span class="coins-field__label">Campanha (opcional)</span>
              <select class="coins-field__control" name="campaign_id" id="campaign_id">
                <option value="">Nenhuma campanha (manual)</option>
                <?php foreach ($campaignOptions as $c): ?>
                  <option value="<?= (int) $c['id'] ?>"
                    data-name="<?= htmlspecialchars((string) $c['name'], ENT_QUOTES, 'UTF-8') ?>"
                    data-description="<?= htmlspecialchars((string) ($c['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    data-coins="<?= (int) $c['coins'] ?>"
                    data-category="<?= htmlspecialchars((string) ($c['category'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars((string) $c['name'], ENT_QUOTES, 'UTF-8') ?> —
                    <?= number_format((int) $c['coins'], 0, ',', '.') ?> coins
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="coins-field__hint">
                Se selecionar uma campanha, a quantidade e o motivo serão preenchidos automaticamente.
              </div>
            </label>

            <label class="coins-field">
              <span class="coins-field__label">Ação</span>
              <select class="coins-field__control" name="action" id="action" required>
                <option value="grant">Adicionar</option>
                <option value="revoke">Remover</option>
                <option value="redeem">Resgate</option>
                <option value="adjust">Ajuste</option>
              </select>
            </label>

            <label class="coins-field">
              <span class="coins-field__label">Quantidade</span>
              <input class="coins-field__control" name="amount" id="amount" type="number" step="1" min="1" required />

            </label>

            <label class="coins-field coins-field--full">
              <span class="coins-field__label">Motivo</span>
              <input class="coins-field__control" name="reason" id="reason" type="text" maxlength="255" />
            </label>

            <div class="coins-field coins-field--full">
              <span class="coins-field__label">Usuários</span>

              <div class="multi-user-box">
                <button type="button" class="btn-modern btn-modern--ghost" id="openUsersModal">
                  Selecionar usuários
                </button>

                <div class="multi-user-summary" id="selectedUsersSummary">
                  Nenhum usuário selecionado.
                </div>
              </div>

              <div id="selectedUsersHidden"></div>
            </div>
          </div>

          <div class="launch-preview" id="launchPreview">
            <div class="launch-preview__label">Prévia do lançamento</div>
            <div class="launch-preview__box">
              <div class="launch-preview__top">
                <strong id="previewUsers">Nenhum usuário selecionado</strong>
                <span class="launch-preview__pill" id="previewAction">Adicionar</span>
              </div>

              <div class="launch-preview__value" id="previewAmount">0 coins</div>

              <div class="launch-preview__reason" id="previewReason">Sem motivo informado.</div>

              <div class="launch-preview__meta">
                <span class="launch-preview__chip" id="previewCampaign">Manual</span>
                <span class="launch-preview__chip" id="previewTotalUsers">0 usuário(s)</span>
              </div>
            </div>
          </div>

          <div class="coins-form-actions">
            <button class="btn-modern btn-modern--ghost" type="reset" id="resetLaunchForm">Limpar</button>
            <button class="btn-modern btn-modern--accent" type="submit">Salvar lançamento</button>
          </div>
        </form>
      </div>

      <div class="coins-card coins-balance-card">
        <div class="coins-card__head">
          <h3 class="coins-card__title">Saldos dos usuários</h3>
          <span class="coins-card__badge">Maior para menor</span>
        </div>

        <div class="table-topbar">
          <input type="text" id="balanceSearch" class="table-search" placeholder="Buscar usuário ou setor..." />
        </div>

        <div class="balances-panel" id="balancesTable">
          <div class="balances-panel__head">
            <div>Usuário</div>
            <div>Saldo</div>
          </div>

          <div class="balances-list">
            <?php foreach ($rows as $r): ?>
              <?php $bal = (int) $r['balance']; ?>
              <div class="balance-row"
                data-search="<?= htmlspecialchars(mb_strtolower((string) $r['name'], 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>">
                <div class="balance-row__user" title="<?= htmlspecialchars((string) $r['name'], ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars((string) $r['name'], ENT_QUOTES, 'UTF-8') ?>
                </div>

                <div class="balance-row__value">
                  <span class="pill <?= $bal >= 0 ? 'pill--pos' : 'pill--neg' ?>">
                    <?= number_format($bal, 0, ',', '.') ?>
                  </span>
                </div>
              </div>
            <?php endforeach; ?>

            <?php if (!$rows): ?>
              <div class="balance-empty">Sem usuários.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <section class="coins-card coins-card--mt">
      <div class="coins-card__head">
        <h3 class="coins-card__title">Últimos lançamentos</h3>
        <span class="coins-card__badge">Histórico global</span>
      </div>

      <div class="table-topbar">
        <input type="text" id="ledgerSearch" class="table-search"
          placeholder="Buscar por usuário, motivo ou admin..." />
      </div>

      <div class="ledger-scroll">
        <div class="table-wrap ledger-wrap">
          <table class="table" id="ledgerTable">
            <thead>
              <thead>
                <tr>
                  <th>Data</th>
                  <th>Usuário</th>
                  <th>Ação</th>
                  <th class="right">Valor</th>
                  <th>Motivo</th>
                </tr>
              </thead>
            </thead>
            <tbody>
              <?php foreach ($ledger as $l): ?>
                <?php $amt = (int) $l['amount']; ?>
                <tr
                  data-search="<?= htmlspecialchars(mb_strtolower((string) $l['user_name'] . ' ' . (string) ($l['reason'] ?? ''), 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>">
                  <td><?= htmlspecialchars((string) $l['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string) $l['user_name'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td>
                    <?= htmlspecialchars($actionLabels[(string) $l['action_type']] ?? (string) $l['action_type'], ENT_QUOTES, 'UTF-8') ?>
                  </td>
                  <td class="right">
                    <span class="pill <?= $amt >= 0 ? 'pill--pos' : 'pill--neg' ?>">
                      <?= number_format($amt, 0, ',', '.') ?>
                    </span>
                  </td>
                  <td><?= htmlspecialchars((string) ($l['reason'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$ledger): ?>
                <tr>
                  <td colspan="5" class="muted">Nenhum lançamento.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </main>

  <div class="modal-backdrop" id="usersModal" hidden>
    <div class="modal-card modal-card--lg" role="dialog" aria-modal="true" aria-labelledby="usersModalTitle">
      <div class="modal-head">
        <h3 id="usersModalTitle">Selecionar usuários</h3>
        <button type="button" class="modal-close" id="closeUsersModal" aria-label="Fechar">×</button>
      </div>

      <div class="modal-body">
        <div class="modal-toolbar">
          <input type="text" id="userModalSearch" class="table-search"
            placeholder="Buscar por nome, e-mail ou cargo..." />

          <div class="modal-toolbar-actions">
            <button type="button" class="btn-modern btn-modern--ghost" id="checkAllUsers">Marcar visíveis</button>
            <button type="button" class="btn-modern btn-modern--ghost" id="uncheckAllUsers">Limpar seleção</button>
          </div>
        </div>

        <div class="user-picker-list" id="userPickerList">
          <?php foreach ($usersForBatch as $usr): ?>
            <label class="user-picker-item"
              data-search="<?= htmlspecialchars(mb_strtolower((string) $usr['name'] . ' ' . (string) $usr['email'] . ' ' . (string) $usr['role'], 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>">
              <input type="checkbox" class="user-picker-check" value="<?= (int) $usr['id'] ?>"
                data-name="<?= htmlspecialchars((string) $usr['name'], ENT_QUOTES, 'UTF-8') ?>" />
              <span class="user-picker-item__content">
                <strong><?= htmlspecialchars((string) $usr['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                <small>
                  <?= htmlspecialchars((string) $usr['email'], ENT_QUOTES, 'UTF-8') ?> ·
                  <?= htmlspecialchars((string) $usr['role'], ENT_QUOTES, 'UTF-8') ?>
                </small>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn-modern btn-modern--ghost" id="cancelUsersModal">Cancelar</button>
        <button type="button" class="btn-modern btn-modern--accent" id="applyUsersSelection">Aplicar seleção</button>
      </div>
    </div>
  </div>

  <?php require_once __DIR__ . '/../app/footer.php'; ?>

  <script src="/assets/js/rh_coins.js?v=<?= filemtime(__DIR__ . '/../assets/js/rh_coins.js') ?>" defer></script>
  <script src="/assets/js/header.js?v=<?= filemtime(__DIR__ . '/../assets/js/header.js') ?>" defer></script>
  <script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/../assets/js/dropdowns.js') ?>" defer></script>
</body>

</html>