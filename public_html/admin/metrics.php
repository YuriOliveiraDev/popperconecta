<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_admin();

$allowedDash = ['executivo', 'financeiro'];
$dashboard_slug = $_GET['dash'] ?? 'executivo';
if (!in_array($dashboard_slug, $allowedDash, true)) $dashboard_slug = 'executivo';

$success = '';
$error = '';

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

// Campos calculados: somente no executivo
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    // ✅ volta para página inicial após salvar
    header('Location: /index.php');
    exit;

  } catch (Throwable $e) {
    $error = 'Erro ao salvar: ' . $e->getMessage();
  }
}

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
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Métricas — <?= htmlspecialchars(APP_NAME) ?></title>
  <link rel="stylesheet" href="/assets/css/users.css" />
  <link rel="stylesheet" href="/assets/css/dashboard.css" />
  <style>
    .tabs{display:flex;gap:10px;margin-bottom:20px;border-bottom:1px solid rgba(15,23,42,.1);padding-bottom:12px}
    .tab{padding:8px 16px;border-radius:999px;text-decoration:none;color:var(--muted);font-weight:700;font-size:13px;border:1px solid transparent;transition:.15s}
    .tab:hover{background:rgba(15,23,42,.05)}
    .tab.is-active{background:rgba(92,44,140,1);color:#fff;border-color:rgba(92,44,140,1)}
    .group{margin-top:14px}
    .group__title{margin:24px 0 12px;font-size:12px;font-weight:800;text-transform:uppercase;color:var(--muted);letter-spacing:.5px}
    .hint{color:var(--muted);font-size:12px;margin-top:6px}
    .field__control.is-computed{background:rgba(15,23,42,.04);color:rgba(15,23,42,.6);cursor:not-allowed}
    .field__hint{font-size:12px;color:var(--muted);margin-top:6px}
  </style>
</head>
<body class="page">

<header class="topbar">
  <div class="topbar__left">
    <strong class="brand"><?= htmlspecialchars(APP_NAME) ?></strong>
    <span class="muted">Admin · Métricas</span>

    <!-- Administração (dropdown) -->
    <div class="topbar__dropdown" style="margin-left:12px;">
      <a class="topbar__dropdown-trigger" href="#" id="adminTrigger">Administração</a>
      <div class="topbar__dropdown-menu" id="adminMenu">
        <a class="topbar__dropdown-item" href="/admin/users.php">
          <span class="topbar__dropdown-icon">👥</span>
          <span class="topbar__dropdown-label">Usuários</span>
        </a>
        <a class="topbar__dropdown-item" href="/admin/metrics.php?dash=<?= htmlspecialchars($dashboard_slug) ?>">
          <span class="topbar__dropdown-icon">🧮</span>
          <span class="topbar__dropdown-label">Métricas</span>
        </a>
      </div>
    </div>

    <!-- Dashboards (dropdown) -->
    <div class="topbar__dropdown" style="margin-left:8px;">
      <a class="topbar__dropdown-trigger" href="#" id="dashTrigger">Dashboards</a>
      <div class="topbar__dropdown-menu" id="dashMenu">
        <a class="topbar__dropdown-item" href="/dashboard.php">
          <span class="topbar__dropdown-icon">📊</span>
          <span class="topbar__dropdown-label">Faturamento</span>
        </a>
        <a class="topbar__dropdown-item" href="/financeiro.php">
          <span class="topbar__dropdown-icon">💰</span>
          <span class="topbar__dropdown-label">Financeiro</span>
        </a>
      </div>
    </div>
  </div>

  <a class="link" href="/index.php">← Início</a>
</header>

<main class="container">
  <h2 class="page-title">Configuração de Métricas</h2>

  <nav class="tabs">
    <a class="tab <?= $dashboard_slug==='executivo'?'is-active':'' ?>" href="/admin/metrics.php?dash=executivo">Faturamento</a>
    <a class="tab <?= $dashboard_slug==='financeiro'?'is-active':'' ?>" href="/admin/metrics.php?dash=financeiro">Financeiro</a>
  </nav>

  <div class="card">
    <?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post" class="form">
      <?php foreach ($ordered as $groupName => $items): ?>
        <div class="group">
          <div class="group__title"><?= htmlspecialchars($groupName) ?></div>

          <?php foreach ($items as $r): ?>
            <?php
              $key = (string)$r['metric_key'];
              $isComputed = isset($computedKeys[$key]);
              $type = (string)$r['metric_type'];
              $isVaiBater = ($key === 'vai_bater_meta');
            ?>
            <div class="field">
              <label class="field__label" for="m_<?= htmlspecialchars($key) ?>">
                <?= htmlspecialchars((string)$r['metric_label']) ?>
              </label>

              <?php if ($isVaiBater): ?>
                <input class="field__control is-computed metric-input"
                       id="m_<?= htmlspecialchars($key) ?>"
                       name="m[<?= htmlspecialchars($key) ?>][value]"
                       value="<?= htmlspecialchars((string)($r['metric_value_text'] ?? '')) ?>"
                       data-type="text"
                       readonly />
                <input type="hidden" name="m[<?= htmlspecialchars($key) ?>][type]" value="text" />
                <div class="field__hint">Calculado automaticamente</div>
              <?php else: ?>
                <input class="field__control metric-input <?= $isComputed ? 'is-computed' : '' ?>"
                       id="m_<?= htmlspecialchars($key) ?>"
                       name="m[<?= htmlspecialchars($key) ?>][value]"
                       value="<?= htmlspecialchars(format_for_input($r)) ?>"
                       autocomplete="off"
                       inputmode="<?= ($type === 'text') ? 'text' : 'decimal' ?>"
                       data-type="<?= htmlspecialchars($type) ?>"
                       data-key="<?= htmlspecialchars($key) ?>"
                       <?= $isComputed ? 'readonly' : '' ?> />
                <input type="hidden" name="m[<?= htmlspecialchars($key) ?>][type]" value="<?= htmlspecialchars($type) ?>" />
                <?php if ($isComputed): ?>
                  <div class="field__hint">Calculado automaticamente</div>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>

      <div class="hint">Dica: digite valores como <strong>R$ 4.000.000,00</strong> ou <strong>59%</strong>. Ao pressionar <strong>Tab</strong>, ele formata automaticamente.</div>

      <button class="btn btn--primary" type="submit" style="margin-top:16px;">Salvar</button>
    </form>
  </div>
</main>

<script>
  // Dropdown (hover + click)
  function attachDropdown(triggerId, menuId){
    const trigger = document.getElementById(triggerId);
    const menu = document.getElementById(menuId);
    let t = null;
    if (!trigger || !menu) return;

    trigger.addEventListener('mouseenter', () => {
      clearTimeout(t);
      trigger.classList.add('is-open');
      menu.classList.add('is-open');
    });

    trigger.addEventListener('mouseleave', () => {
      t = setTimeout(() => {
        trigger.classList.remove('is-open');
        menu.classList.remove('is-open');
      }, 150);
    });

    menu.addEventListener('mouseenter', () => clearTimeout(t));
    menu.addEventListener('mouseleave', () => {
      t = setTimeout(() => {
        trigger.classList.remove('is-open');
        menu.classList.remove('is-open');
      }, 150);
    });

    document.addEventListener('click', (e) => {
      if (!trigger.contains(e.target) && !menu.contains(e.target)) {
        trigger.classList.remove('is-open');
        menu.classList.remove('is-open');
      }
    });

    trigger.addEventListener('click', (e) => {
      e.preventDefault();
      trigger.classList.toggle('is-open');
      menu.classList.toggle('is-open');
    });
  }

  attachDropdown('adminTrigger', 'adminMenu');
  attachDropdown('dashTrigger', 'dashMenu');
</script>

<script>
  const fmtBRL = new Intl.NumberFormat('pt-BR', { style:'currency', currency:'BRL' });
  const fmtINT = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 0 });

  function parsePtBrToNumber(raw){
    if (raw == null) return null;
    let s = String(raw).trim();
    if (!s) return null;

    s = s.replace(/\s/g, '');
    s = s.replace('R$', '');
    s = s.replace(/%/g, '');
    s = s.replace(/\./g, '');
    s = s.replace(/,/g, '.');

    const n = Number(s);
    return Number.isFinite(n) ? n : null;
  }

  function formatPercent(n){
    const frac = (n > 1.5) ? (n / 100) : n;
    const pct = frac * 100;
    const hasDecimal = Math.abs(pct - Math.round(pct)) > 1e-9;
    const out = pct.toLocaleString('pt-BR', {
      minimumFractionDigits: hasDecimal ? 2 : 0,
      maximumFractionDigits: hasDecimal ? 2 : 0
    });
    return out + '%';
  }

  function formatInputValue(input){
    const type = input.dataset.type || 'money';
    if (type === 'text') return;

    const n = parsePtBrToNumber(input.value);
    if (n === null) { input.value = ''; return; }

    if (type === 'int') { input.value = fmtINT.format(Math.round(n)); return; }
    if (type === 'percent') { input.value = formatPercent(n); return; }

    input.value = fmtBRL.format(n);
  }

  function unformatForEditing(input){
    const type = input.dataset.type || 'money';
    if (type === 'text') return;

    const n = parsePtBrToNumber(input.value);
    if (n === null) return;

    if (type === 'int') { input.value = String(Math.round(n)); return; }

    if (type === 'percent') {
      const frac = (n > 1.5) ? (n / 100) : n;
      const pct = frac * 100;
      const hasDecimal = Math.abs(pct - Math.round(pct)) > 1e-9;
      input.value = pct.toLocaleString('pt-BR', {
        minimumFractionDigits: hasDecimal ? 2 : 0,
        maximumFractionDigits: hasDecimal ? 2 : 0
      });
      return;
    }

    input.value = n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  // ✅ Tab sempre formata (mais confiável do que só blur)
  document.querySelectorAll('.metric-input').forEach((input) => {
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Tab') formatInputValue(input);
    });
    input.addEventListener('blur', () => formatInputValue(input));
    input.addEventListener('focus', () => unformatForEditing(input));
  });

  // Cálculos em tempo real (somente no executivo)
  const DASH = "<?= htmlspecialchars($dashboard_slug) ?>";
  if (DASH === 'executivo') {
    function recalculate(){
      const metaAno = parsePtBrToNumber(document.getElementById('m_meta_ano')?.value) || 0;
      const realAno = parsePtBrToNumber(document.getElementById('m_realizado_ano_acum')?.value) || 0;
      const metaMes = parsePtBrToNumber(document.getElementById('m_meta_mes')?.value) || 0;
      const realMes = parsePtBrToNumber(document.getElementById('m_realizado_ate_hoje')?.value) || 0;
      const dTotal = parsePtBrToNumber(document.getElementById('m_dias_uteis_trabalhar')?.value) || 1;
      const dPass = parsePtBrToNumber(document.getElementById('m_dias_uteis_trabalhados')?.value) || 0;

      const faltaAno = Math.max(0, metaAno - realAno);
      const faltaMes = Math.max(0, metaMes - realMes);
      const ating = metaMes > 0 ? (realMes / metaMes) : 0;

      const metaDia = metaMes / Math.max(1, dTotal);
      const deveria = metaDia * dPass;

      const realDia = dPass > 0 ? (realMes / dPass) : 0;
      const prod = metaDia > 0 ? (realDia / metaDia) : 0;

      const diasRest = Math.max(1, dTotal - dPass);
      const aFaturar = faltaMes / diasRest;

      const proj = realDia * dTotal;
      const equiv = metaMes > 0 ? (proj / metaMes) : 0;
      const vaiBater = proj >= metaMes ? 'SIM' : 'NÃO';

      const set = (id, val, type) => {
        const el = document.getElementById(id);
        if (!el) return;
        if (type === 'money') el.value = fmtBRL.format(val);
        else if (type === 'percent') el.value = formatPercent(val);
        else el.value = String(val);
      };

      set('m_falta_meta_ano', faltaAno, 'money');
      set('m_falta_meta_mes', faltaMes, 'money');
      set('m_atingimento_mes_pct', ating, 'percent');
      set('m_deveria_ate_hoje', deveria, 'money');
      set('m_meta_dia_util', metaDia, 'money');
      set('m_a_faturar_dia_util', aFaturar, 'money');
      set('m_realizado_dia_util', realDia, 'money');
      set('m_realizado_dia_util_pct', prod, 'percent');
      set('m_fechar_em', proj, 'money');
      set('m_equivale_pct', equiv, 'percent');

      const vb = document.getElementById('m_vai_bater_meta');
      if (vb) vb.value = vaiBater;
    }

    document.querySelectorAll('.metric-input:not(.is-computed)').forEach((input) => {
      input.addEventListener('input', recalculate);
    });

    recalculate();
  }
</script>

</body>
</html>