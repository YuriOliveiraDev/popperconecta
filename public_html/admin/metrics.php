<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_admin();

$dashboard_slug = $_GET['dash'] ?? 'executivo';
$success = '';
$error = '';

function parse_ptbr_number(string $s): ?float {
  $s = trim($s);
  if ($s === '') return null;
  $s = str_replace(["\xc2\xa0", " "], "", $s); // remove espaços e nbsp
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

  if ($type === 'int') {
    return [(float)((int)round($n)), null];
  }

  if ($type === 'percent') {
    // aceita 59 / 59% / 0.59 / 1.058
    $val = ($n > 1.5) ? ($n / 100.0) : $n;
    return [$val, null];
  }

  // money
  return [$n, null];
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
    // remove zeros finais em pt-br (ex.: 59,00 -> 59)
    $out = rtrim(rtrim($out, '0'), ',');
    return $out . '%';
  }

  // money
  return 'R$ ' . number_format($n, 2, ',', '.');
}

// --- Campos calculados (não editáveis) ---
$computedKeys = [
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $stmt = db()->prepare('UPDATE metrics SET metric_value_num=?, metric_value_text=? WHERE dashboard_slug=? AND metric_key=?');

    foreach (($_POST['m'] ?? []) as $key => $posted) {
      $keyStr = (string)$key;
      if (isset($computedKeys[$keyStr])) continue; // ignora calculados

      $type = (string)($posted['type'] ?? 'money');
      $raw  = (string)($posted['value'] ?? '');

      [$num, $txt] = normalize_value($type, $raw);
      $stmt->execute([$num, $txt, $dashboard_slug, $keyStr]);
    }

    $success = 'Métricas atualizadas.';
  } catch (Throwable $e) {
    $error = 'Erro ao salvar: ' . $e->getMessage();
  }
}

$stmt = db()->prepare('SELECT id, metric_key, metric_label, metric_type, metric_value_num, metric_value_text FROM metrics WHERE dashboard_slug=? ORDER BY id ASC');
$stmt->execute([$dashboard_slug]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Organiza por grupos ---
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

// adiciona “sobras”
$knownKeys = [];
foreach ($groups as $keys) foreach ($keys as $k) $knownKeys[$k] = true;

$extras = [];
foreach ($rows as $r) {
  $k = (string)$r['metric_key'];
  if (!isset($knownKeys[$k])) $extras[] = $r;
}

?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Métricas — <?= htmlspecialchars(APP_NAME) ?></title>
  <link rel="stylesheet" href="/assets/css/users.css" />
  <style>
    .group{ margin-top: 14px; }
    .group__title{
      margin: 18px 0 10px;
      font-size: 14px;
      letter-spacing: .2px;
      color: var(--muted);
      font-weight: 800;
      text-transform: uppercase;
    }
    .hint{ color: var(--muted); font-size: 12px; margin-top: 6px; }
    .field__control.is-computed{
      background: rgba(15,23,42,.06);
      border-color: rgba(15,23,42,.10);
      color: rgba(15,23,42,.70);
    }
    .field__hint{
      font-size: 12px;
      color: var(--muted);
      margin-top: 6px;
    }
  </style>
</head>
<body class="page">
<header class="topbar">
  <div class="topbar__left">
    <strong class="brand"><?= htmlspecialchars(APP_NAME) ?></strong>
    <span class="muted">Admin · Métricas (<?= htmlspecialchars($dashboard_slug) ?>)</span>
  </div>
  <a class="link" href="/dashboard.php">← Voltar</a>
</header>

<main class="container">
  <h2 class="page-title">Editar métricas</h2>

  <div class="card">
    <?php if ($success): ?><div class="alert alert--ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post" class="form">
      <?php foreach ($ordered as $groupName => $items): ?>
        <div class="group">
          <div class="group__title"><?= htmlspecialchars($groupName) ?></div>

          <?php foreach ($items as $r): ?>
            <div class="field">
              <label class="field__label" for="m_<?= htmlspecialchars($r['metric_key']) ?>">
                <?= htmlspecialchars($r['metric_label']) ?>
              </label>

              <?php $isComputed = isset($computedKeys[(string)$r['metric_key']]); ?>

              <?php if ((string)$r['metric_key'] === 'vai_bater_meta'): ?>
                <input class="field__control is-computed"
                       id="m_<?= htmlspecialchars($r['metric_key']) ?>"
                       name="m[<?= htmlspecialchars($r['metric_key']) ?>][value]"
                       value="<?= htmlspecialchars((string)($r['metric_value_text'] ?? '')) ?>"
                       readonly />
                <input type="hidden" name="m[<?= htmlspecialchars($r['metric_key']) ?>][type]" value="text" />
                <div class="field__hint">Calculado automaticamente</div>

              <?php else: ?>
                <input class="field__control metric-input <?= $isComputed ? 'is-computed' : '' ?>"
                       id="m_<?= htmlspecialchars($r['metric_key']) ?>"
                       name="m[<?= htmlspecialchars($r['metric_key']) ?>][value]"
                       value="<?= htmlspecialchars(format_for_input($r)) ?>"
                       autocomplete="off"
                       inputmode="<?= ($r['metric_type'] === 'text') ? 'text' : 'decimal' ?>"
                       data-type="<?= htmlspecialchars($r['metric_type']) ?>"
                       data-key="<?= htmlspecialchars($r['metric_key']) ?>"
                       <?= $isComputed ? 'readonly' : '' ?> />

                <input type="hidden" name="m[<?= htmlspecialchars($r['metric_key']) ?>][type]" value="<?= htmlspecialchars($r['metric_type']) ?>" />

                <?php if ($isComputed): ?>
                  <div class="field__hint">Calculado automaticamente</div>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>

      <div class="hint">Dica: digite valores como <strong>R$ 4.000.000,00</strong> ou <strong>59%</strong>. Ao sair do campo (Tab), ele formata automaticamente.</div>

      <button class="btn btn--primary" type="submit">Salvar</button>
    </form>
  </div>
</main>

<script>
  const fmtBRL = new Intl.NumberFormat('pt-BR', { style:'currency', currency:'BRL' });
  const fmtINT = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 0 });
  const fmtPCT = new Intl.NumberFormat('pt-BR', { style:'percent', maximumFractionDigits: 2 });

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

  // --- CÁLCULOS EM TEMPO REAL ---
  function recalculate(){
    // Pega valores atuais dos campos editáveis
    const metaAno = parsePtBrToNumber(document.getElementById('m_meta_ano')?.value) || 0;
    const realizadoAno = parsePtBrToNumber(document.getElementById('m_realizado_ano_acum')?.value) || 0;
    const metaMes = parsePtBrToNumber(document.getElementById('m_meta_mes')?.value) || 0;
    const realizadoMes = parsePtBrToNumber(document.getElementById('m_realizado_ate_hoje')?.value) || 0;
    const diasTotais = parsePtBrToNumber(document.getElementById('m_dias_uteis_trabalhar')?.value) || 1;
    const diasPassados = parsePtBrToNumber(document.getElementById('m_dias_uteis_trabalhados')?.value) || 0;

    // Cálculos
    const faltaAno = Math.max(0, metaAno - realizadoAno);
    const faltaMes = Math.max(0, metaMes - realizadoMes);
    const atingimentoPct = metaMes > 0 ? (realizadoMes / metaMes) : 0;
    const deveriaTerHoje = (metaMes / Math.max(1, diasTotais)) * diasPassados;
    const metaDiaUtil = metaMes / Math.max(1, diasTotais);
    const diasRestantes = Math.max(1, diasTotais - diasPassados);
    const aFaturarDia = faltaMes / diasRestantes;
    const realizadoDiaUtil = diasPassados > 0 ? (realizadoMes / diasPassados) : 0;
    const produtividadePct = metaDiaUtil > 0 ? (realizadoDiaUtil / metaDiaUtil) : 0;
    const projecaoFechamento = realizadoDiaUtil * diasTotais;
    const equivalePct = metaMes > 0 ? (projecaoFechamento / metaMes) : 0;
    const vaiBater = projecaoFechamento >= metaMes ? 'SIM' : 'NÃO';

    // Atualiza campos calculados
    const setValue = (id, val, type) => {
      const el = document.getElementById(id);
      if (!el) return;
      if (type === 'money') el.value = fmtBRL.format(val);
      else if (type === 'percent') el.value = formatPercent(val);
      else if (type === 'int') el.value = fmtINT.format(Math.round(val));
      else el.value = val;
    };

    setValue('m_falta_meta_ano', faltaAno, 'money');
    setValue('m_falta_meta_mes', faltaMes, 'money');
    setValue('m_atingimento_mes_pct', atingimentoPct, 'percent');
    setValue('m_deveria_ate_hoje', deveriaTerHoje, 'money');
    setValue('m_meta_dia_util', metaDiaUtil, 'money');
    setValue('m_a_faturar_dia_util', aFaturarDia, 'money');
    setValue('m_realizado_dia_util', realizadoDiaUtil, 'money');
    setValue('m_realizado_dia_util_pct', produtividadePct, 'percent');
    setValue('m_vai_bater_meta', vaiBater, 'text');
    setValue('m_fechar_em', projecaoFechamento, 'money');
    setValue('m_equivale_pct', equivalePct, 'percent');
  }

  // Listeners para recalcular em tempo real
  document.querySelectorAll('.metric-input:not(.is-computed)').forEach((input) => {
    input.addEventListener('input', recalculate);
  });

  // Formatação ao sair do campo
  document.querySelectorAll('.metric-input').forEach((input) => {
    input.addEventListener('blur', () => {
      const type = input.dataset.type || 'money';
      const raw = input.value;

      if (type === 'text') return;

      const n = parsePtBrToNumber(raw);
      if (n === null) { input.value = ''; return; }

      if (type === 'int') { input.value = fmtINT.format(Math.round(n)); return; }
      if (type === 'percent') { input.value = formatPercent(n); return; }

      input.value = fmtBRL.format(n);
    });

    input.addEventListener('focus', () => {
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
    });
  });

  // Calcula inicial
  recalculate();
</script>
</body>
</html>