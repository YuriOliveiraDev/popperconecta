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
$activePage = 'coins';

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

function h(?string $value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Campanhas Popper Coins — <?= h((string)APP_NAME) ?></title>

  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/assets/css/base.css') ?>" />
  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/assets/css/dashboard.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/assets/css/dropdowns.css') ?>" />
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(__DIR__ . '/assets/css/header.css') ?>" />
  <link rel="stylesheet" href="/assets/css/coins.css?v=<?= filemtime(__DIR__ . '/assets/css/coins.css') ?>" />

  <style>
    .coins-page{
      max-width: 1400px;
      margin: 0 auto;
      padding: 24px 0 40px;
    }

    .coins-page__header{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:16px;
      flex-wrap:wrap;
      margin-bottom:24px;
    }

    .coins-page__title{
      margin:0;
      font-size:2rem;
      font-weight:900;
      letter-spacing:-.03em;
      color:#0f172a;
      padding-top: 20px;
    }

    .coins-page__subtitle{
      margin:8px 0 0;
      color:#64748b;
      font-size:1rem;
    }

    .coins-page__actions{
      display:flex;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
      margin-left:auto;
    }
    .pc-title {
        
    }
    .btn-page{
      appearance:none;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      min-height:44px;
      padding:0 16px;
      border-radius:14px;
      border:1px solid rgba(15,23,42,.10);
      background:#fff;
      color:#0f172a;
      text-decoration:none;
      font-size:13px;
      font-weight:900;
      box-shadow:0 10px 24px rgba(15,23,42,.06);
      transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }

    .btn-page:hover{
      transform:translateY(-2px);
      border-color:rgba(92,44,140,.24);
      box-shadow:0 16px 30px rgba(15,23,42,.10);
    }

    .btn-page--primary{
      color:#fff;
      border-color:rgba(92,44,140,.18);
      background:linear-gradient(135deg, #5c2c8c, #7b3db4);
      box-shadow:0 12px 28px rgba(92,44,140,.18);
    }

    .campaign-toolbar{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:14px;
      flex-wrap:wrap;
      margin-bottom:20px;
    }

    .campaign-search{
      width:min(420px, 100%);
      height:46px;
      padding:0 14px;
      border:1px solid rgba(15,23,42,.12);
      border-radius:14px;
      background:#fff;
      color:#0f172a;
      outline:none;
      box-shadow:0 8px 20px rgba(15,23,42,.04);
    }

    .campaign-search:focus{
      border-color:rgba(92,44,140,.45);
      box-shadow:0 0 0 4px rgba(92,44,140,.12);
    }

    .campaign-count{
      font-size:13px;
      font-weight:800;
      color:#64748b;
    }

    .campaign-grid-page{
      display:grid;
      grid-template-columns:repeat(3, minmax(0, 1fr));
      gap:18px;
    }

    .campaign-card-page{
      background:#fff;
      border:1px solid rgba(15,23,42,.08);
      border-radius:20px;
      padding:18px;
      box-shadow:0 12px 28px rgba(15,23,42,.06);
      display:flex;
      flex-direction:column;
      gap:14px;
      transition:transform .16s ease, box-shadow .16s ease, border-color .16s ease;
    }

    .campaign-card-page:hover{
      transform:translateY(-3px);
      border-color:rgba(92,44,140,.20);
      box-shadow:0 18px 34px rgba(15,23,42,.10);
    }

    .campaign-card-page__head{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:12px;
    }

    .campaign-card-page__title{
      margin:0;
      font-size:18px;
      font-weight:900;
      line-height:1.3;
      color:#0f172a;
    }

    .campaign-card-page__category{
      display:inline-flex;
      align-items:center;
      min-height:30px;
      padding:0 10px;
      border-radius:999px;
      background:rgba(92,44,140,.10);
      border:1px solid rgba(92,44,140,.18);
      color:#5c2c8c;
      font-size:11px;
      font-weight:900;
      white-space:nowrap;
    }

    .campaign-card-page__desc{
      margin:0;
      font-size:14px;
      line-height:1.6;
      color:rgba(15,23,42,.72);
      flex:1;
    }

    .campaign-card-page__footer{
      display:flex;
      align-items:flex-end;
      justify-content:space-between;
      gap:14px;
      padding-top:14px;
      border-top:1px solid rgba(15,23,42,.06);
    }

    .campaign-card-page__coins{
      display:flex;
      align-items:baseline;
      gap:6px;
    }

    .campaign-card-page__coins-num{
      font-size:30px;
      font-weight:950;
      line-height:1;
      letter-spacing:-.03em;
      color:#5c2c8c;
    }

    .campaign-card-page__coins-label{
      font-size:12px;
      font-weight:900;
      color:rgba(15,23,42,.58);
    }

    .campaign-card-page__date{
      font-size:12px;
      color:#64748b;
      text-align:right;
    }

    .campaign-empty-page{
      padding:34px 20px;
      text-align:center;
      color:#64748b;
      border:1px dashed rgba(15,23,42,.12);
      border-radius:20px;
      background:rgba(15,23,42,.02);
      font-weight:700;
    }

    .hidden-by-search{
      display:none !important;
    }

    @media (max-width: 1100px){
      .campaign-grid-page{
        grid-template-columns:repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 700px){
      .coins-page{
        padding-top:18px;
      }

      .coins-page__title{
        font-size:1.6rem;
      }

      .coins-page__actions{
        width:100%;
        margin-left:0;
      }

      .btn-page{
        width:100%;
      }

      .campaign-search{
        width:100%;
      }

      .campaign-grid-page{
        grid-template-columns:1fr;
      }

      .campaign-card-page__head,
      .campaign-card-page__footer{
        flex-direction:column;
        align-items:flex-start;
      }

      .campaign-card-page__date{
        text-align:left;
      }
    }
  </style>
</head>
<body class="page">

<?php require_once __DIR__ . '/app/header.php'; ?>

<main class="container coins-page">
  <div class="coins-page__header">
    <div>
      <h1 class="coins-page__title">Campanhas Popper Coins</h1>
      <p class="coins-page__subtitle">
        Consulte as campanhas ativas cadastradas e a quantidade de coins por campanha.
      </p>
    </div>

    <div class="coins-page__actions">
      <a href="/coins.php" class="btn-page btn-page--primary">Meu saldo</a>
    </div>
  </div>

  <div class="campaign-toolbar">
    <input
      type="text"
      id="campaignSearch"
      class="campaign-search"
      placeholder="Buscar campanha por nome, descrição ou categoria..."
    />
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
          $campaignName = trim((string)($campaign['name'] ?? ''));
          $campaignDesc = trim((string)($campaign['description'] ?? ''));
          $campaignCoins = (int)($campaign['coins'] ?? 0);
          $campaignCategory = trim((string)($campaign['category'] ?? ''));
          $campaignCreatedAt = (string)($campaign['created_at'] ?? '');

          $searchBase = mb_strtolower(
            $campaignName . ' ' . $campaignDesc . ' ' . $campaignCategory . ' ' . $campaignCoins,
            'UTF-8'
          );
        ?>
        <article
          class="campaign-card-page"
          data-search="<?= h($searchBase) ?>"
        >
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

<?php require_once __DIR__ . '/app/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const input = document.getElementById('campaignSearch');
  const grid = document.getElementById('campaignGrid');
  const count = document.getElementById('campaignCount');

  function norm(text) {
    return String(text || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim();
  }

  function applyFilter() {
    if (!input || !grid) return;

    const q = norm(input.value);
    const cards = Array.from(grid.querySelectorAll('.campaign-card-page'));
    let visibleCount = 0;

    cards.forEach(function (card) {
      const hay = norm(card.dataset.search || card.textContent || '');
      const visible = !q || hay.includes(q);

      card.classList.toggle('hidden-by-search', !visible);

      if (visible) visibleCount++;
    });

    if (count) {
      count.textContent = String(visibleCount);
    }
  }

  if (input) {
    input.addEventListener('input', applyFilter);
  }

  applyFilter();
});
</script>

<script src="/assets/js/header.js?v=<?= filemtime(__DIR__ . '/assets/js/header.js') ?>"></script>
<script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdowns.js') ?>"></script>
</body>
</html>