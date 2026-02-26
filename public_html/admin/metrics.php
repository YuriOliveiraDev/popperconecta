<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/config-totvs.php';

require_admin();

/* =========================================================
   COMPAT
========================================================= */
if (!function_exists('array_is_list')) {
  function array_is_list(array $array): bool {
    if ($array === []) return true;
    return array_keys($array) === range(0, count($array) - 1);
  }
}

/* =========================================================
   CONTEXTO (header.php / permissões / dashboard selecionado)
========================================================= */
$u = current_user();
$activePage = 'admin';

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

$current_dash = $dashboard_slug; // usado pelo header

$error = '';

/* =========================================================
   HELPERS
========================================================= */
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
  $type = (string)($r['metric_type'] ?? 'money');

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

/* =========================================================
   REGRAS: CAMPOS MANUAIS (somente estes são editáveis)
========================================================= */
$manualKeysByDash = [
  'executivo' => [
    'meta_ano',
    'meta_mes',
    // se você quiser voltar com dias úteis manuais, adicione aqui:
    // 'dias_uteis_trabalhar',
    // 'dias_uteis_trabalhados',
  ],
  'financeiro' => [
    'faturado_dia',
    'contas_pagar_dia',
  ],
];
$manualKeys = array_flip($manualKeysByDash[$dashboard_slug] ?? []);

/* =========================================================
   HISTÓRICO DIÁRIO (executivo)
========================================================= */
$daily_date = (string)($_POST['daily_date'] ?? date('Y-m-d'));
$daily_faturado_raw = (string)($_POST['daily_faturado'] ?? '');
$daily_agendado_raw = (string)($_POST['daily_agendado'] ?? '');

$dailyRow = null;
$dailyOk = false;
$dailyMsg = '';

if ($dashboard_slug === 'executivo') {
  try {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $daily_date)) {
      $stmtD = db()->prepare('
        SELECT dash_slug, ref_date, faturado_dia, agendado_hoje, updated_at
        FROM dashboard_daily
        WHERE dash_slug=? AND ref_date=?
        LIMIT 1
      ');
      $stmtD->execute([$dashboard_slug, $daily_date]);
      $dailyRow = $stmtD->fetch(PDO::FETCH_ASSOC);
    }
  } catch (Throwable $e) {
    // silencioso
  }
}

/* =========================================================
   POST HANDLERS
   - save_daily: salva dashboard_daily
   - default: salva SOMENTE campos manuais em metrics
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dashboard_slug === 'executivo' && (($_POST['daily_action'] ?? '') === 'save_daily')) {
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

    $stmtD = db()->prepare('
      SELECT dash_slug, ref_date, faturado_dia, agendado_hoje, updated_at
      FROM dashboard_daily
      WHERE dash_slug=? AND ref_date=?
      LIMIT 1
    ');
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
  try {
    $stmt = db()->prepare('
      UPDATE metrics
      SET metric_value_num=?, metric_value_text=?
      WHERE dashboard_slug=? AND metric_key=?
    ');

    foreach (($_POST['m'] ?? []) as $key => $posted) {
      $keyStr = (string)$key;

      // ✅ salva SOMENTE manuais
      if (!isset($manualKeys[$keyStr])) continue;

      $type = (string)($posted['type'] ?? 'money');
      $raw  = (string)($posted['value'] ?? '');

      [$num, $txt] = normalize_value($type, $raw);
      $stmt->execute([$num, $txt, $dashboard_slug, $keyStr]);
    }

    header('Location: /admin/metrics.php?dash=' . urlencode($dashboard_slug));
    exit;
  } catch (Throwable $e) {
    $error = 'Erro ao salvar: ' . $e->getMessage();
  }
}

/* =========================================================
   LOAD METRICS (render)
========================================================= */
$stmt = db()->prepare('
  SELECT id, metric_key, metric_label, metric_type, metric_value_num, metric_value_text
  FROM metrics
  WHERE dashboard_slug=?
  ORDER BY id ASC
');
$stmt->execute([$dashboard_slug]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$groups = ($dashboard_slug === 'financeiro')
  ? ['Configuração (manual)' => ['faturado_dia', 'contas_pagar_dia']]
  : ['Configuração (manual)' => ['meta_ano', 'meta_mes']];

$byKey = [];
foreach ($rows as $r) $byKey[(string)$r['metric_key']] = $r;

$ordered = [];
foreach ($groups as $gName => $keys) {
  foreach ($keys as $k) {
    if (isset($byKey[$k])) $ordered[$gName][] = $byKey[$k];
  }
}

$dashboardName = ($dashboard_slug === 'executivo') ? 'Faturamento' : 'Financeiro';

$dailyFatInput = '';
$dailyAgInput = '';
$dailyUpdatedAt = '';

if ($dashboard_slug === 'executivo' && is_array($dailyRow)) {
  $dailyFatInput = 'R$ ' . number_format((float)($dailyRow['faturado_dia'] ?? 0), 2, ',', '.');
  $dailyAgInput  = 'R$ ' . number_format((float)($dailyRow['agendado_hoje'] ?? 0), 2, ',', '.');
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
      <!-- Prévia automática (igual dashboard) -->
      <section class="group" style="margin-bottom:18px;">
        <div class="group__title">Prévia do Dashboard (automático)</div>
        <div class="hint" style="margin:0 0 10px 0;">Valores do TOTVS + cálculos automáticos (igual ao dashboard).</div>

        <table id="previewTable" style="width:100%;border-collapse:collapse;">
          <tbody></tbody>
        </table>

        <div style="margin-top:10px;display:flex;gap:10px;align-items:center;">
          <button class="btn btn--secondary" type="button" id="btnPreviewForce">Atualizar agora (forçar TOTVS)</button>
          <span id="previewStatus"></span>
        </div>
      </section>

      <!-- Histórico diário manual -->
      <section class="group" style="margin-bottom:18px;">
        </form>
      </section>
    <?php endif; ?>

    <!-- Formulário: somente campos manuais -->
    <form method="post" class="form metrics-form">
      <?php foreach ($ordered as $groupName => $items): ?>
        <section class="group">
          <div class="group__title"><?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?></div>

          <?php foreach ($items as $r): ?>
            <?php
              $key = (string)$r['metric_key'];
              $type = (string)$r['metric_type'];

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

              <input class="field__control metric-input"
                     id="m_<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                     name="m[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>][value]"
                     value="<?= htmlspecialchars(format_for_input($r), ENT_QUOTES, 'UTF-8') ?>"
                     placeholder="<?= htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') ?>"
                     autocomplete="off"
                     inputmode="<?= ($type === 'text') ? 'text' : 'decimal' ?>"
                     data-type="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" />

              <input type="hidden"
                     name="m[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>][type]"
                     value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" />
            </div>
          <?php endforeach; ?>
        </section>
      <?php endforeach; ?>

      <div class="hint">Dica: digite valores como <strong>R$ 4.000.000,00</strong> ou <strong>18</strong>. Ao pressionar <strong>Tab</strong>, ele formata automaticamente.</div>
      <button class="btn btn--primary" type="submit">Salvar Alterações</button>
    </form>
  </div>
</main>

<?php require_once __DIR__ . '/../app/footer.php'; ?>

<script>
  window.METRICS_DASH = <?= json_encode($dashboard_slug, JSON_UNESCAPED_UNICODE) ?>;
</script>

<?php if ($dashboard_slug === 'executivo'): ?>
<script>
(function(){
  const dash = <?= json_encode($dashboard_slug, JSON_UNESCAPED_UNICODE) ?>;

  const brl = (v) => new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format(Number(v||0));
  const pct = (v) => new Intl.NumberFormat('pt-BR',{style:'percent',maximumFractionDigits:0}).format(Number(v||0));

  async function loadPreview(force=false){
  const st = document.getElementById('previewStatus');
  if (st) st.textContent = force ? 'Forçando TOTVS...' : 'Carregando...';

  try {
    const url = `/api/dashboard-data.php?dash=${encodeURIComponent(dash)}${force ? '&force=1' : ''}`;

    // timeout (10s)
    const ctrl = new AbortController();
    const t = setTimeout(() => ctrl.abort(), 10000);

    const res = await fetch(url, { cache: 'no-store', signal: ctrl.signal });
    clearTimeout(t);

    if (!res.ok) throw new Error(`HTTP ${res.status}`);

    const payload = await res.json();
    const v = payload.values || {};

    const brlFmt = (x) => brl(x ?? 0);
    const pctFmt = (x) => pct(x ?? 0);

    const fatHoje = Number(v.hoje_faturado ?? 0);
const agHoje  = Number(v.hoje_agendado ?? 0);
const totalHoje = (fatHoje + agHoje) || Number(v.hoje_total ?? 0);

const fatMes = Number(v.mes_faturado ?? 0);
const agMes  = Number(v.mes_agendado ?? 0);
const totalMes = (fatMes + agMes) || Number(v.realizado_ate_hoje ?? 0);

const rows = [
  ['Atualizado em', payload.updated_at || '—'],

  ['Hoje (faturado)', brlFmt(fatHoje)],
  ['Hoje (agendado)', brlFmt(agHoje)],
  ['Hoje (total)', brlFmt(totalHoje)],

  ['Mês (faturado)', brlFmt(fatMes)],
  ['Mês (agendado)', brlFmt(agMes)],
  ['Mês (total até hoje)', brlFmt(totalMes)],

  ['Realizado anual acumulado', brlFmt(v.realizado_ano_acum)],
  ['Meta do mês', brlFmt(v.meta_mes)],
  ['Falta para meta do mês', brlFmt(v.falta_meta_mes)],
  ['Atingimento do mês', pctFmt(v.atingimento_mes_pct)],
  ['Dias úteis', `${Number(v.dias_uteis_trabalhados||0)} / ${Number(v.dias_uteis_trabalhar||0)}`],
  ['Meta por dia útil', brlFmt(v.meta_dia_util)],
  ['Realizado por dia útil', brlFmt(v.realizado_dia_util)],
  ['Vai bater meta?', v.vai_bater_meta ?? '—'],
  ['Projeção de fechamento', brlFmt(v.fechar_em)],
  ['Equivale a', pctFmt(v.equivale_pct)]
];

    const tbody = document.querySelector('#previewTable tbody');
    if (tbody) {
      tbody.innerHTML = '';
      rows.forEach(([k,val])=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0">${k}</td>
          <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600">${val}</td>
        `;
        tbody.appendChild(tr);
      });
    }

    if (st) st.textContent = 'OK ✅';
  } catch (err) {
    console.error(err);
    if (st) st.textContent = `Erro ❌ ${err?.message || err}`;
  }
}

  loadPreview(false);

  const btn = document.getElementById('btnPreviewForce');
  if (btn) btn.addEventListener('click', () => loadPreview(true));
})();
</script>
<?php endif; ?>

<script src="/assets/js/header.js?v=<?= filemtime(__DIR__ . '/../assets/js/header.js') ?>"></script>
<script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/../assets/js/dropdowns.js') ?>"></script>
<script src="/assets/js/metrics.js?v=<?= filemtime(__DIR__ . '/../assets/js/metrics.js') ?>"></script>

</body>
</html>