<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';

require_admin_perm('admin.rh');

$u = current_user();
$activePage = 'admin';

try {
  $dashboards = db()
    ->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")
    ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}

$success = '';
$error = '';

if (isset($_GET['success'])) {
  if ($_GET['success'] === 'create') {
    $success = 'Campanha cadastrada com sucesso.';
  } elseif ($_GET['success'] === 'toggle') {
    $success = 'Status da campanha atualizado.';
  } elseif ($_GET['success'] === 'delete') {
    $success = 'Campanha removida com sucesso.';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  try {
    if ($action === 'create') {
      $name = trim((string) ($_POST['name'] ?? ''));
      $description = trim((string) ($_POST['description'] ?? ''));
      $coins = (int) ($_POST['coins'] ?? 0);
      $category = trim((string) ($_POST['category'] ?? ''));
      $isActive = isset($_POST['is_active']) ? 1 : 0;

      if ($name === '') {
        throw new RuntimeException('Informe o nome da campanha.');
      }

      if ($coins <= 0) {
        throw new RuntimeException('Informe uma quantidade válida de coins.');
      }

      $stmt = db()->prepare("
        INSERT INTO popper_coin_campaigns (name, description, coins, category, is_active)
        VALUES (?, ?, ?, ?, ?)
      ");
      $stmt->execute([$name, $description ?: null, $coins, $category ?: null, $isActive]);

      header('Location: ' . $_SERVER['PHP_SELF'] . '?success=create');
      exit;
    }

    if ($action === 'toggle') {
      $id = (int) ($_POST['id'] ?? 0);

      if ($id <= 0) {
        throw new RuntimeException('ID inválido para alterar status.');
      }

      $stmt = db()->prepare("
        UPDATE popper_coin_campaigns
        SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END
        WHERE id = ?
      ");
      $stmt->execute([$id]);

      header('Location: ' . $_SERVER['PHP_SELF'] . '?success=toggle');
      exit;
    }

    if ($action === 'delete') {
      $id = (int) ($_POST['id'] ?? 0);

      if ($id <= 0) {
        throw new RuntimeException('ID inválido para exclusão.');
      }

      $stmt = db()->prepare("DELETE FROM popper_coin_campaigns WHERE id = ?");
      $stmt->execute([$id]);

      header('Location: ' . $_SERVER['PHP_SELF'] . '?success=delete');
      exit;
    }
  } catch (Throwable $e) {
    $error = $e->getMessage();
  }
}
$campaigns = db()->query("
  SELECT id, name, description, coins, category, is_active, created_at
  FROM popper_coin_campaigns
  ORDER BY is_active DESC, name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$totalCampaigns = count($campaigns);
$totalActive = 0;
$totalInactive = 0;
$totalCoins = 0;

foreach ($campaigns as $item) {
  $coins = (int) ($item['coins'] ?? 0);
  $active = (int) ($item['is_active'] ?? 0);

  $totalCoins += $coins;

  if ($active === 1) {
    $totalActive++;
  } else {
    $totalInactive++;
  }
}
?>
<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Campanhas Popper Coins — <?= htmlspecialchars((string) APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(APP_ROOT . '/assets/css/base.css') ?>" />
  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(APP_ROOT . '/assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(APP_ROOT . '/assets/css/dashboard.css') ?>" />
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(APP_ROOT . '/assets/css/header.css') ?>" />
  <link rel="stylesheet"
    href="/assets/css/rh_campaigns.css?v=<?= filemtime(APP_ROOT . '/assets/css/rh_campaigns.css') ?>" />
</head>

<body class="page">

  <?php require_once APP_ROOT . '/app/layout/header.php'; ?>

  <main class="container campaign-page">
    <div class="campaign-header">
      <div>
        <h1 class="campaign-title">Campanhas Popper Coins</h1>
        <p class="campaign-subtitle">Cadastre campanhas com valor fixo para facilitar lançamentos individuais e em
          massa.</p>
      </div>
    </div>

    <?php if ($success): ?>
      <div class="alert alert--ok"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <section class="campaign-summary-grid">
      <div class="campaign-balance-card">
        <div class="campaign-balance-head">
          <h3 class="campaign-balance-title">Resumo de campanhas</h3>
          <span class="campaign-balance-pill">Admin RH</span>
        </div>

        <div class="campaign-balance-value">
          <div class="campaign-balance-num"><?= number_format($totalCampaigns, 0, ',', '.') ?></div>
          <div class="campaign-balance-unit">campanhas</div>
        </div>

        <div class="campaign-balance-foot">
          <span class="campaign-balance-label">Responsável</span>
          <span
            class="campaign-balance-user"><?= htmlspecialchars((string) ($u['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      </div>

      <div class="campaign-mini-stats">
        <div class="campaign-mini-stat">
          <span class="campaign-mini-stat__label">Ativas</span>
          <strong class="campaign-mini-stat__value"><?= $totalActive ?></strong>
        </div>

        <div class="campaign-mini-stat">
          <span class="campaign-mini-stat__label">Inativas</span>
          <strong class="campaign-mini-stat__value"><?= $totalInactive ?></strong>
        </div>

        <div class="campaign-mini-stat">
          <span class="campaign-mini-stat__label">Coins somadas</span>
          <strong class="campaign-mini-stat__value"><?= number_format($totalCoins, 0, ',', '.') ?></strong>
        </div>

        <div class="campaign-mini-stat">
          <span class="campaign-mini-stat__label">Média por campanha</span>
          <strong class="campaign-mini-stat__value">
            <?= $totalCampaigns > 0 ? number_format((int) floor($totalCoins / $totalCampaigns), 0, ',', '.') : 0 ?>
          </strong>
        </div>
      </div>
    </section>

    <div class="campaign-layout">
      <section class="campaign-card campaign-form-card">
        <div class="campaign-card-head">
          <h3 class="campaign-card-title">Cadastrar campanha</h3>
          <span class="campaign-card-badge">Novo cadastro</span>
        </div>

        <p class="campaign-info-text">
          Defina um nome, uma descrição e a quantidade fixa de Popper Coins que será aplicada nos lançamentos.
        </p>

        <form method="post" class="campaign-form" id="campaignCreateForm">
          <input type="hidden" name="action" value="create" />

          <div class="form-grid">
            <div class="form-field form-field--full">
              <label for="name">Nome da campanha</label>
              <input type="text" name="name" id="name" maxlength="150" placeholder="Ex.: Curso mensal" required />
            </div>

            <div class="form-field form-field--full">
              <label for="description">Descrição</label>
              <textarea name="description" id="description" rows="4" maxlength="255"
                placeholder="Ex.: Participação no curso mensal de desenvolvimento interno."></textarea>
              <div class="field-meta">
                <span class="field-hint">Opcional, mas recomendado.</span>
                <span class="char-count" data-for="description">0/255</span>
              </div>
            </div>

            <div class="form-field">
              <label for="coins">Quantidade de coins</label>
              <input type="number" name="coins" id="coins" min="1" step="1" placeholder="1000" required />
            </div>

            <div class="form-field">
              <label for="category">Categoria</label>
              <input type="text" name="category" id="category" maxlength="100" placeholder="Ex.: Treinamento" />
            </div>

            <div class="form-field form-field--full">
              <label class="switch-field">
                <input type="checkbox" name="is_active" id="is_active" checked />
                <span class="switch-ui"></span>
                <span class="switch-text">Campanha ativa para uso imediato</span>
              </label>
            </div>
          </div>

          <div class="campaign-preview" id="campaignPreview">
            <div class="campaign-preview__label">Prévia rápida</div>
            <div class="campaign-preview__box">
              <div class="campaign-preview__title" id="previewName">Nova campanha</div>
              <div class="campaign-preview__desc" id="previewDescription">A descrição aparecerá aqui.</div>
              <div class="campaign-preview__meta">
                <span class="campaign-chip" id="previewCategory">Sem categoria</span>
                <span class="campaign-chip campaign-chip--coins" id="previewCoins">0 coins</span>
                <span class="campaign-chip campaign-chip--status" id="previewStatus">Ativa</span>
              </div>
            </div>
          </div>

          <div class="campaign-form-actions">
            <button type="reset" class="btn-modern btn-modern--ghost" id="resetCampaignForm">
              Limpar
            </button>

            <button type="submit" class="btn-modern btn-modern--accent">
              Salvar campanha
            </button>
          </div>
        </form>
      </section>

      <section class="campaign-card campaign-list-card">
        <div class="campaign-card-head">
          <h3 class="campaign-card-title">Campanhas cadastradas</h3>
          <span class="campaign-card-badge"><?= $totalCampaigns ?> total</span>
        </div>

        <div class="campaign-toolbar">
          <div class="campaign-search-wrap">
            <input type="text" id="campaignSearch" class="campaign-search" placeholder="Buscar campanha..." />
          </div>

          <div class="campaign-filter-group">
            <button type="button" class="campaign-filter is-active" data-filter="all">Todas</button>
            <button type="button" class="campaign-filter" data-filter="active">Ativas</button>
            <button type="button" class="campaign-filter" data-filter="inactive">Inativas</button>
          </div>

          <div class="campaign-sort-wrap">
            <select id="campaignSort" class="campaign-sort">
              <option value="default">Ordenar: padrão</option>
              <option value="coins_asc">Menor valor</option>
              <option value="coins_desc">Maior valor</option>
              <option value="name_asc">Nome A-Z</option>
            </select>
          </div>
        </div>

        <?php if (!$campaigns): ?>
          <div class="campaign-empty-static">Nenhuma campanha cadastrada até o momento.</div>
        <?php else: ?>
          <div class="campaign-grid" id="campaignGrid">
            <?php foreach ($campaigns as $index => $item): ?>
              <?php
              $id = (int) ($item['id'] ?? 0);
              $name = (string) ($item['name'] ?? '');
              $description = trim((string) ($item['description'] ?? ''));
              $coins = (int) ($item['coins'] ?? 0);
              $category = trim((string) ($item['category'] ?? ''));
              $isActive = (int) ($item['is_active'] ?? 0) === 1;
              $createdAt = (string) ($item['created_at'] ?? '');

              $filterState = $isActive ? 'active' : 'inactive';
              $badgeLabel = $isActive ? 'Ativa' : 'Inativa';
              $badgeClass = $isActive ? 'is-active' : 'is-inactive';
              ?>
              <article class="campaign-item" data-title="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                data-category="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>" data-coins="<?= $coins ?>"
                data-order="<?= $index ?>" data-filter-state="<?= $filterState ?>">
                <div class="campaign-item-head">
                  <div>
                    <h4 class="campaign-item-title"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h4>
                    <div class="campaign-item-sub">
                      <?= $category !== '' ? htmlspecialchars($category, ENT_QUOTES, 'UTF-8') : 'Sem categoria' ?>
                    </div>
                  </div>

                  <span class="campaign-item-badge <?= $badgeClass ?>">
                    <?= htmlspecialchars($badgeLabel, ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </div>

                <div class="campaign-item-body">
                  <p class="campaign-item-desc">
                    <?= $description !== '' ? htmlspecialchars($description, ENT_QUOTES, 'UTF-8') : 'Sem descrição cadastrada.' ?>
                  </p>

                  <div class="campaign-item-meta">
                    <div class="campaign-item-coins">
                      <span class="campaign-item-coins__num"><?= number_format($coins, 0, ',', '.') ?></span>
                      <span class="campaign-item-coins__unit">coins</span>
                    </div>

                    <div class="campaign-item-date">
                      Cadastro:
                      <strong><?= $createdAt !== '' ? date('d/m/Y', strtotime($createdAt)) : '—' ?></strong>
                    </div>
                  </div>
                </div>

                <div class="campaign-item-actions">
                  <form method="post" class="inline-form js-confirm-toggle">
                    <input type="hidden" name="action" value="toggle" />
                    <input type="hidden" name="id" value="<?= $id ?>" />

                    <button type="submit" class="btn-modern btn-modern--ghost">
                      <?= $isActive ? 'Inativar' : 'Ativar' ?>
                    </button>
                  </form>

                  <form method="post" class="inline-form js-confirm-delete">
                    <input type="hidden" name="action" value="delete" />
                    <input type="hidden" name="id" value="<?= $id ?>" />

                    <button type="submit" class="btn-modern btn-modern--danger">
                      Excluir
                    </button>
                  </form>
                </div>
              </article>
            <?php endforeach; ?>
          </div>

          <div id="campaignEmpty" class="campaign-empty" hidden>
            Nenhuma campanha encontrada para o filtro atual.
          </div>
        <?php endif; ?>
      </section>
    </div>
  </main>

  <div class="modal-backdrop" id="campaignModal" hidden>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="campaignModalTitle">
      <div class="modal-head">
        <h3 id="campaignModalTitle">Nova campanha</h3>
        <button type="button" class="modal-close" id="closeCampaignModal" aria-label="Fechar">×</button>
      </div>

      <div class="modal-body">
        <p class="modal-text">
          Clique em <strong>Continuar</strong> para ir direto ao formulário de cadastro.
        </p>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn-modern btn-modern--ghost" id="cancelCampaignModal">Cancelar</button>
        <button type="button" class="btn-modern btn-modern--accent" id="confirmCampaignModal">Continuar</button>
      </div>
    </div>
  </div>

  <?php require_once APP_ROOT . '/app/layout/footer.php'; ?>

  <script src="/assets/js/rh_campaigns.js?v=<?= filemtime(APP_ROOT . '/assets/js/rh_campaigns.js') ?>" defer></script>
  <script src="/assets/js/header.js?v=<?= filemtime(APP_ROOT . '/assets/js/header.js') ?>" defer></script>
  <script src="/assets/js/dropdowns.js?v=<?= filemtime(APP_ROOT . '/assets/js/dropdowns.js') ?>" defer></script>
</body>

</html>