<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_admin();

// ✅ Essencial para o header.php funcionar
$u = current_user();
$activePage = 'admin';

// ✅ Dropdown "Dashboards" no header
try {
  $dashboards = db()
    ->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")
    ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}

$allowedDash = ['executivo', 'financeiro'];
$dashboard_slug = $_GET['dash'] ?? 'executivo';
if (!in_array($dashboard_slug, $allowedDash, true)) $dashboard_slug = 'executivo';

// Para o header.php montar o link correto de /admin/metrics.php?dash=...
$current_dash = $dashboard_slug;

$success = '';
$error = '';

// ------------------------------
// Helpers existentes
// ------------------------------
function parse_ptbr_number(string $s): ?float {
  $s = trim($s);
  if ($s === '') return null;
  $s = str_replace(["\xc2\xa0", " "], "", $s);
  $s = str_replace("R$", "", $s);
  $s = str_replace(".", "", $s);
  $s = str_replace(",", ".", $s);
  $s = str_replace("%", "", $s);
  return is_numeric($s) ? (float)$s : null;
}

function normalize_value(string $type, ?string $raw): array {
  $raw = $raw ?? '';
  $rawTrim = trim($raw);

  if ($type === 'text') {
    if ($rawTrim === '') return [null, null];
    $t = strtoupper($rawTrim);
    if ($t === 'NAO') $t = 'NÃO';
    return [null, $t];
  }

  $n = parse_ptbr_number($rawTrim);
  if ($n === null) return [null, null];

  if ($type === 'int') return [(float)((int)round($n)), null];

  if ($type === 'percent') {
    $val = ($n > 1.5) ? ($n / 100.0) : $n;
    return [$val, null];
  }

  return [$n, null]; // money
}

function format_for_input(array $r): string {
  $type = (string)$r['metric_type'];

  if ($type === 'text') return (string)($r['metric_value_text'] ?? '');

  if ($r['metric_value_num'] === null) return '';
  $n = (float)$r['metric_value_num'];

  if ($type === 'int') return (string)((int)round($n));

  if ($type === 'percent') {
    $pct = $n * 100.0;
    $hasDecimal = abs($pct - round($pct)) > 1e-9;
    $out = number_format($pct, $hasDecimal ? 2 : 0, ',', '.');
    $out = rtrim(rtrim($out, '0'), ',');
    return $out . '%';
  }

  return 'R$ ' . number_format($n, 2, ',', '.');
}

// ------------------------------
// NOVO: Diário (tabela dashboard_daily)
// ------------------------------
$daily_date = (string)($_POST['daily_date'] ?? date('Y-m-d'));
$daily_faturado_raw = (string)($_POST['daily_faturado'] ?? '');
$daily_agendado_raw = (string)($_POST['daily_agendado'] ?? '');

$dailyRow = null;
$dailyOk = false;
$dailyMsg = '';

if ($dashboard_slug === 'executivo') {
  // Carrega o registro do dia (para preencher o form)
  try {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $daily_date)) {
      $stmtD = db()->prepare('SELECT dash_slug, ref_date, faturado_dia, agendado_hoje, updated_at FROM dashboard_daily WHERE dash_slug=? AND ref_date=? LIMIT 1');
      $stmtD->execute([$dashboard_slug, $daily_date]);
      $dailyRow = $stmtD->fetch(PDO::FETCH_ASSOC);
    }
  } catch (Throwable $e) {
    // silencioso: não derruba a página de métricas
  }
}

// ------------------------------
// Campos calculados: somente no executivo
// ------------------------------
$computedKeysExecutivo = [
  'falta_meta_ano' => true,
  'falta_meta_mes' => true,
  'atingimento_mes_pct' => true,
  'deveria_ate_hoje' => true,
  'meta_dia_util' => true,
  'a_faturar_dia_util' => true,
  'realizado_dia_util' => true,
  'realizado_dia_util_pct' => true,
  'vai_bater_meta' => true,
  'fechar_em' => true,
  'equivale_pct' => true,
];
$computedKeys = ($dashboard_slug === 'executivo') ? $computedKeysExecutivo : [];

// ------------------------------
// POST: pode salvar 2 coisas:
// 1) daily_action = 'save_daily'  -> salva histórico diário
// 2) normal (sem daily_action)    -> salva tabela metrics (como já é hoje)
// ------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($dashboard_slug === 'executivo') && (($_POST['daily_action'] ?? '') === 'save_daily')) {
  // Salvar diário
  try {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $daily_date)) {
      throw new Exception('Data inválida.');
    }

    $fat = parse_ptbr_number($daily_faturado_raw);
    $agd = parse_ptbr_number($daily_agendado_raw);

    if ($fat === null) $fat = 0.0;
    if ($agd === null) $agd = 0.0;

    $uid = (int)($u['id'] ?? 0);

    $stmtS = db()->prepare('
      INSERT INTO dashboard_daily (dash_slug, ref_date, faturado_dia, agendado_hoje, updated_by)
      VALUES (?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        faturado_dia=VALUES(faturado_dia),
        agendado_hoje=VALUES(agendado_hoje),
        updated_by=VALUES(updated_by),
        updated_at=CURRENT_TIMESTAMP
    ');
    $stmtS->execute([$dashboard_slug, $daily_date, $fat, $agd, $uid]);

    // Recarrega para mostrar valores salvos
    $stmtD = db()->prepare('SELECT dash_slug, ref_date, faturado_dia, agendado_hoje, updated_at FROM dashboard_daily WHERE dash_slug=? AND ref_date=? LIMIT 1');
    $stmtD->execute([$dashboard_slug, $daily_date]);
    $dailyRow = $stmtD->fetch(PDO::FETCH_ASSOC);

    $dailyOk = true;
    $dailyMsg = 'Dia salvo com sucesso.';
  } catch (Throwable $e) {
    $dailyOk = false;
    $dailyMsg = 'Erro ao salvar dia: ' . $e->getMessage();
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['daily_action'] ?? '') !== 'save_daily')) {
  // Salvar métricas (seu comportamento atual)
  try {
    $stmt = db()->prepare('UPDATE metrics SET metric_value_num=?, metric_value_text=? WHERE dashboard_slug=? AND metric_key=?');

    foreach (($_POST['m'] ?? []) as $key => $posted) {
      $keyStr = (string)$key;
      if (isset($computedKeys[$keyStr])) continue;

      $type = (string)($posted['type'] ?? 'money');
      $raw  = (string)($posted['value'] ?? '');

      [$num, $txt] = normalize_value($type, $raw);
      $stmt->execute([$num, $txt, $dashboard_slug, $keyStr]);
    }

    header('Location: /index.php');
    exit;

  } catch (Throwable $e) {
    $error = 'Erro ao salvar: ' . $e->getMessage();
  }
}

// ------------------------------
// Carrega métricas
// ------------------------------
$stmt = db()->prepare('SELECT id, metric_key, metric_label, metric_type, metric_value_num, metric_value_text FROM metrics WHERE dashboard_slug=? ORDER BY id ASC');
$stmt->execute([$dashboard_slug]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Grupos por dashboard
if ($dashboard_slug === 'financeiro') {
  $groups = [
    'Financeiro' => [
      'faturado_dia',
      'contas_pagar_dia',
    ]
  ];
} else {
  $groups = [
    'Ano' => [
      'meta_ano',
      'falta_meta_ano',
      'realizado_ano_acum',
    ],
    'Mês' => [
      'meta_mes',
      'realizado_ate_hoje',
      'falta_meta_mes',
      'atingimento_mes_pct',
      'deveria_ate_hoje',
    ],
    'Ritmo (dia útil)' => [
      'meta_dia_util',
      'a_faturar_dia_util',
      'realizado_dia_util',
      'realizado_dia_util_pct',
    ],
    'Dias úteis' => [
      'dias_uteis_trabalhar',
      'dias_uteis_trabalhados',
    ],
    'Projeções' => [
      'vai_bater_meta',
      'fechar_em',
      'equivale_pct',
    ],
  ];
}

// index por key
$byKey = [];
foreach ($rows as $r) $byKey[(string)$r['metric_key']] = $r;

// monta lista final em ordem
$ordered = [];
foreach ($groups as $gName => $keys) {
  foreach ($keys as $k) {
    if (isset($byKey[$k])) $ordered[$gName][] = $byKey[$k];
  }
}

$dashboardName = $dashboard_slug === 'executivo' ? 'Faturamento' : 'Financeiro';

// Formata valores do diário para input
$dailyFatInput = '';
$dailyAgInput = '';
$dailyUpdatedAt = '';

if ($dashboard_slug === 'executivo' && is_array($dailyRow)) {
  $dailyFatInput = 'R$ ' . number_format((float)($dailyRow['faturado_dia'] ?? 0), 2, ',', '.');
  $dailyAgInput = 'R$ ' . number_format((float)($dailyRow['agendado_hoje'] ?? 0), 2, ',', '.');
  $dailyUpdatedAt = (string)($dailyRow['updated_at'] ?? '');
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Métricas de <?= htmlspecialchars($dashboardName, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars((string)APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/../assets/css/base.css') ?>" />
  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/../assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/../assets/css/dropdowns.css') ?>" />
  <link rel="stylesheet" href="/assets/css/metrics.css?v=<?= filemtime(__DIR__ . '/../assets/css/metrics.css') ?>" />
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(__DIR__ . '/../assets/css/header.css') ?>" />
</head>
<body class="page">

<?php require_once __DIR__ . '/../app/header.php'; ?>

<main class="container metrics metrics--fullwidth metrics--two-col">
  <h2 class="page-title">Configuração de Métricas de <?= htmlspecialchars($dashboardName, ENT_QUOTES, 'UTF-8') ?></h2>

  <nav class="tabs">
    <a class="tab <?= $dashboard_slug==='executivo'?'is-active':'' ?>" href="/admin/metrics.php?dash=executivo">Faturamento</a>
    <a class="tab <?= $dashboard_slug==='financeiro'?'is-active':'' ?>" href="/admin/metrics.php?dash=financeiro">Financeiro</a>
  </nav>

  <div class="card metrics-card">
    <?php if ($error): ?>
      <div class="alert alert--error">❌ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($dashboard_slug === 'executivo'): ?>
      <!-- ✅ NOVO BLOCO: Histórico diário (não existia no seu HTML) -->
      <div class="group" style="margin-bottom:18px;">
        <div class="group__title">Histórico diário (manual)</div>

        <?php if ($dailyMsg !== ''): ?>
          <div class="alert <?= $dailyOk ? 'alert--ok' : 'alert--error' ?>">
            <?= htmlspecialchars($dailyMsg, ENT_QUOTES, 'UTF-8') ?>
          </div>
        <?php endif; ?>

        <form method="post" class="form" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;align-items:end;">
          <input type="hidden" name="daily_action" value="save_daily">

          <div class="field" style="grid-column:1 / -1;">
            <label class="field__label" for="daily_date">Data</label>
            <input class="field__control" id="daily_date" name="daily_date" type="date"
                   value="<?= htmlspecialchars($daily_date, ENT_QUOTES, 'UTF-8') ?>" />
            <?php if ($dailyUpdatedAt !== ''): ?>
              <div class="field__hint">Última atualização: <?= htmlspecialchars($dailyUpdatedAt, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
          </div>

          <div class="field">
            <label class="field__label" for="daily_faturado">Faturado do dia</label>
            <input class="field__control" id="daily_faturado" name="daily_faturado" type="text"
                   placeholder="R$ 0,00"
                   value="<?= htmlspecialchars($dailyFatInput, ENT_QUOTES, 'UTF-8') ?>" />
          </div>

          <div class="field">
            <label class="field__label" for="daily_agendado">Agendado para faturar hoje</label>
            <input class="field__control" id="daily_agendado" name="daily_agendado" type="text"
                   placeholder="R$ 0,00"
                   value="<?= htmlspecialchars($dailyAgInput, ENT_QUOTES, 'UTF-8') ?>" />
          </div>

          <button class="btn btn--secondary" type="submit" style="grid-column:1 / -1;">Salvar dia</button>

          <div class="hint" style="grid-column:1 / -1;margin:0;">
            Dica: você pode preencher só um dos campos. Se deixar vazio, considera <strong>0</strong>.
          </div>
        </form>
      </div>
    <?php endif; ?>

    <!-- ✅ Seu formulário original (permanece) -->
    <form method="post" class="form metrics-form">
      <?php foreach ($ordered as $groupName => $items): ?>
        <div class="group">
          <div class="group__title"><?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?></div>

          <?php foreach ($items as $r): ?>
            <?php
              $key = (string)$r['metric_key'];
              $isComputed = isset($computedKeys[$key]);
              $type = (string)$r['metric_type'];
              $isVaiBater = ($key === 'vai_bater_meta');

              $placeholder = match($type) {
                'money' => 'Ex: R$ 4.000.000,00',
                'percent' => 'Ex: 59%',
                'int' => 'Ex: 18',
                'text' => '',
                default => ''
              };
            ?>
            <div class="field">
              <label class="field__label" for="m_<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars((string)$r['metric_label'], ENT_QUOTES, 'UTF-8') ?>
              </label>

              <?php if ($isVaiBater): ?>
                <input class="field__control is-computed metric-input"
                       id="m_<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                       name="m[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>][value]"
                       value="<?= htmlspecialchars((string)($r['metric_value_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                       data-type="text"
                       placeholder="<?= htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') ?>"
                       readonly />
                <input type="hidden" name="m[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>][type]" value="text" />
                <div class="field__hint">Calculado automaticamente</div>
              <?php else: ?>
                <input class="field__control metric-input <?= $isComputed ? 'is-computed' : '' ?>"
                       id="m_<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                       name="m[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>][value]"
                       value="<?= htmlspecialchars(format_for_input($r), ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="<?= htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') ?>"
                       autocomplete="off"
                       inputmode="<?= ($type === 'text') ? 'text' : 'decimal' ?>"
                       data-type="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"
                       <?= $isComputed ? 'readonly' : '' ?> />
                <input type="hidden" name="m[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>][type]" value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" />
                <?php if ($isComputed): ?>
                  <div class="field__hint">Calculado automaticamente</div>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>

      <div class="hint">Dica: digite valores como <strong>R$ 4.000.000,00</strong> ou <strong>59%</strong>. Ao pressionar <strong>Tab</strong>, ele formata automaticamente.</div>

      <button class="btn btn--primary" type="submit">Salvar Alterações</button>
    </form>
  </div>
</main>

<?php require_once __DIR__ . '/../app/footer.php'; ?>

<script>
  window.METRICS_DASH = <?= json_encode($dashboard_slug, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/assets/js/header.js?v=<?= filemtime(__DIR__ . '/../assets/js/header.js') ?>"></script>
<script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/../assets/js/dropdowns.js') ?>"></script>
<script src="/assets/js/metrics.js?v=<?= filemtime(__DIR__ . '/../assets/js/metrics.js') ?>"></script>

</body>
</html>