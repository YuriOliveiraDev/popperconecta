<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_login();

date_default_timezone_set('America/Sao_Paulo');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$u = current_user();
$activePage = 'coins';
$page_title = 'Campanhas Popper Coins';
$html_class = 'page coins-campaigns-page';

// Dashboards no header
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

function h(?string $value): string
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$campaigns = [];
try {
  $stmt = db()->query("
        SELECT id, name, description, coins, category, created_at
        FROM popper_coin_campaigns
        WHERE is_active = 1
        ORDER BY created_at DESC, name ASC
    ");
  $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $campaigns = [];
}

$extra_css = [
  '/assets/css/base.css?v=' . @filemtime(APP_ROOT . '/assets/css/base.css'),
  '/assets/css/coins_campanhas.css?v=' . @filemtime(APP_ROOT . '/assets/css/dcoins_campanhas.css'),
  '/assets/css/header.css?v=' . @filemtime(APP_ROOT . '/assets/css/header.css'),
];

require_once APP_ROOT . '/app/layout/header.php';
?>



<main class="container coins-page">
  <div class="coins-page__header">
    <div>
      <h1 class="coins-page__title">Campanhas Popper Coins</h1>
      <p class="coins-page__subtitle">
        Consulte as campanhas ativas cadastradas e a quantidade de coins por campanha.
      </p>
    </div>

    <div class="coins-page__actions">
      <a href="/coins/coins.php" class="btn-page btn-page--primary">Meu saldo</a>
    </div>
  </div>

  <div class="campaign-toolbar">
    <input type="text" id="campaignSearch" class="campaign-search"
      placeholder="Buscar campanha por nome, descrição ou categoria..." />
    <div class="campaign-count">
      Total de campanhas: <strong id="campaignCount"><?= count($campaigns) ?></strong>
    </div>
  </div>

  <?php if (!$campaigns): ?>
    <div class="campaign-empty-page">
      Nenhuma campanha ativa disponível no momento.
    </div>
  <?php else: ?>
    <div class="campaign-grid-page" id="campaignGrid">
      <?php foreach ($campaigns as $campaign): ?>
        <?php
        $campaignName = trim((string) ($campaign['name'] ?? ''));
        $campaignDesc = trim((string) ($campaign['description'] ?? ''));
        $campaignCoins = (int) ($campaign['coins'] ?? 0);
        $campaignCategory = trim((string) ($campaign['category'] ?? ''));
        $campaignCreatedAt = (string) ($campaign['created_at'] ?? '');

        $searchBase = mb_strtolower(
          $campaignName . ' ' . $campaignDesc . ' ' . $campaignCategory . ' ' . $campaignCoins,
          'UTF-8'
        );
        ?>
        <article class="campaign-card-page" data-search="<?= h($searchBase) ?>">
          <div class="campaign-card-page__head">
            <h2 class="campaign-card-page__title">
              <?= h($campaignName !== '' ? $campaignName : 'Campanha') ?>
            </h2>

            <span class="campaign-card-page__category">
              <?= h($campaignCategory !== '' ? $campaignCategory : 'Sem categoria') ?>
            </span>
          </div>

          <p class="campaign-card-page__desc">
            <?= h($campaignDesc !== '' ? $campaignDesc : 'Sem descrição cadastrada.') ?>
          </p>

          <div class="campaign-card-page__footer">
            <div class="campaign-card-page__coins">
              <span class="campaign-card-page__coins-num"><?= number_format($campaignCoins, 0, ',', '.') ?></span>
              <span class="campaign-card-page__coins-label">coins</span>
            </div>

            <div class="campaign-card-page__date">
              Cadastro:
              <strong><?= $campaignCreatedAt !== '' ? date('d/m/Y', strtotime($campaignCreatedAt)) : '—' ?></strong>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
<script src="/assets/js/header.js?v=<?= @filemtime(APP_ROOT . '/assets/js/header.js') ?>"></script>
<script src="/assets/js/coins_campanhas.js?v=<?= @filemtime(APP_ROOT . '/assets/js/coins_campanhas.js') ?>"></script>

<?php require_once APP_ROOT . '/app/layout/footer.php'; ?>