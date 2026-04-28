<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';
require_once APP_ROOT . '/app/config/config-totvs.php';

require_admin_perm('admin.metrics');

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

$current_dash = $dashboard_slug;

$error = '';
// =========================
// FLASH (mensagem 1x, não fica no F5)
// =========================
function flash_set(string $type, string $msg): void {
  if (session_status() === PHP_SESSION_NONE) session_start();
  $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function flash_get(): ?array {
  if (session_status() === PHP_SESSION_NONE) session_start();
  if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) return null;
  $f = $_SESSION['flash'];
  unset($_SESSION['flash']); // ✅ some após exibir 1x
  return $f;
}
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

  return [$n, null];
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

function brl(float $v): string {
  return 'R$ ' . number_format($v, 2, ',', '.');
}

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function parse_ptbr_date_to_iso(string $raw, int $defaultYear): ?string {
  $value = trim(mb_strtolower($raw, 'UTF-8'));
  if ($value === '') return null;

  if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $m)) {
    $day = (int)$m[1];
    $month = (int)$m[2];
    $year = (int)$m[3];
    return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
  }

  if (preg_match('/^(\d{1,2})\/(\d{1,2})$/', $value, $m)) {
    $day = (int)$m[1];
    $month = (int)$m[2];
    return checkdate($month, $day, $defaultYear) ? sprintf('%04d-%02d-%02d', $defaultYear, $month, $day) : null;
  }

  if (preg_match('/^(\d{1,2})\/([a-zç]{3,})$/u', $value, $m)) {
    $months = [
      'jan' => 1, 'fev' => 2, 'mar' => 3, 'abr' => 4, 'mai' => 5, 'jun' => 6,
      'jul' => 7, 'ago' => 8, 'set' => 9, 'out' => 10, 'nov' => 11, 'dez' => 12,
    ];
    $day = (int)$m[1];
    $monthKey = substr($m[2], 0, 3);
    $month = $months[$monthKey] ?? null;
    if ($month === null) return null;
    return checkdate($month, $day, $defaultYear) ? sprintf('%04d-%02d-%02d', $defaultYear, $month, $day) : null;
  }

  return null;
}

function pick_bulk_amount_index(array $amounts, string $strategy): ?int {
  if ($amounts === []) return null;

  return match ($strategy) {
    'second' => isset($amounts[1]) ? 1 : 0,
    'third' => isset($amounts[2]) ? 2 : array_key_last($amounts),
    'last' => array_key_last($amounts),
    default => 0,
  };
}

function parse_bulk_ajustes_payload(string $raw, string $strategy, int $defaultYear): array {
  $normalized = str_replace(["\r\n", "\r", "\t"], "\n", $raw);
  $lines = array_values(array_filter(array_map(
    static fn(string $line): string => trim($line),
    explode("\n", $normalized)
  ), static fn(string $line): bool => $line !== ''));

  $blocks = [];
  $current = [];

  foreach ($lines as $line) {
    if (parse_ptbr_date_to_iso($line, $defaultYear) !== null && $current !== []) {
      $blocks[] = $current;
      $current = [$line];
      continue;
    }
    $current[] = $line;
  }
  if ($current !== []) $blocks[] = $current;

  $items = [];
  $errors = [];

  foreach ($blocks as $idx => $block) {
    $label = 'Bloco ' . ($idx + 1);
    $dateIso = parse_ptbr_date_to_iso((string)($block[0] ?? ''), $defaultYear);
    if ($dateIso === null) {
      $errors[] = $label . ': data inválida ou ausente em "' . (string)($block[0] ?? '') . '".';
      continue;
    }

    $orderCode = trim((string)($block[1] ?? ''));
    $client = trim((string)($block[2] ?? ''));
    $seller = trim((string)($block[3] ?? ''));

    $amountCandidates = [];
    $amountRawLines = [];
    foreach (array_slice($block, 4) as $line) {
      $value = parse_ptbr_number($line);
      if ($value === null) continue;
      $amountCandidates[] = $value;
      $amountRawLines[] = $line;
    }

    $pickedIdx = pick_bulk_amount_index($amountCandidates, $strategy);
    if ($pickedIdx === null || !isset($amountCandidates[$pickedIdx])) {
      $errors[] = $label . ': nenhum valor numérico utilizável encontrado.';
      continue;
    }

    $value = (float)$amountCandidates[$pickedIdx];
    if (abs($value) < 0.00001) {
      $errors[] = $label . ': valor selecionado não pode ser zero.';
      continue;
    }

    $parts = array_values(array_filter([$orderCode, $client, $seller], static fn(string $v): bool => $v !== ''));
    $motivo = $parts !== [] ? 'Importação em massa: ' . implode(' | ', $parts) : 'Importação em massa';

    $items[] = [
      'ref_date' => $dateIso,
      'order_code' => $orderCode,
      'client' => $client,
      'seller' => $seller,
      'amount' => $value,
      'amount_raw' => (string)$amountRawLines[$pickedIdx],
      'amount_candidates' => $amountRawLines,
      'motivo' => $motivo,
    ];
  }

  return [$items, $errors];
}

/* =========================================================
   REGRAS: CAMPOS MANUAIS (somente estes são editáveis)
========================================================= */
$manualKeysByDash = [
  'executivo' => ['meta_ano', 'meta_mes'],
  'financeiro' => ['faturado_dia', 'contas_pagar_dia'],
];
$manualKeys = array_flip($manualKeysByDash[$dashboard_slug] ?? []);

/* =========================================================
   AJUSTE MANUAL DE FATURAMENTO (executivo)
   - add_ajuste
   - del_ajuste (soft delete)
========================================================= */
$ajOk = false;
$ajMsg = '';
$bulkPreviewItems = [];
$bulkPreviewErrors = [];
$bulkImportRaw = '';
$bulkImportStrategy = 'first';
$bulkImportYear = (int)date('Y');

if (
  $_SERVER['REQUEST_METHOD'] === 'POST' &&
  $dashboard_slug === 'executivo' &&
  (($_POST['aj_action'] ?? '') === 'preview_bulk_ajustes')
) {
  $bulkImportRaw = trim((string)($_POST['aj_bulk_raw'] ?? ''));
  $bulkImportStrategy = (string)($_POST['aj_bulk_value_strategy'] ?? 'first');
  $bulkImportYear = (int)($_POST['aj_bulk_year'] ?? date('Y'));

  if (!in_array($bulkImportStrategy, ['first', 'second', 'third', 'last'], true)) {
    $bulkImportStrategy = 'first';
  }
  if ($bulkImportYear < 2020 || $bulkImportYear > 2100) {
    $bulkImportYear = (int)date('Y');
  }

  if ($bulkImportRaw === '') {
    $bulkPreviewErrors[] = 'Cole os dados antes de gerar a pré-visualização.';
  } else {
    [$bulkPreviewItems, $bulkPreviewErrors] = parse_bulk_ajustes_payload($bulkImportRaw, $bulkImportStrategy, $bulkImportYear);
    if ($bulkPreviewItems === [] && $bulkPreviewErrors === []) {
      $bulkPreviewErrors[] = 'Nenhum registro válido foi encontrado no bloco colado.';
    }
  }
}

if (
  $_SERVER['REQUEST_METHOD'] === 'POST' &&
  $dashboard_slug === 'executivo' &&
  (($_POST['aj_action'] ?? '') === 'confirm_bulk_ajustes')
) {
  try {
    $uid = (int)($u['id'] ?? 0);
    $payload = (string)($_POST['aj_bulk_payload'] ?? '');
    if ($payload === '') throw new Exception('Pré-visualização ausente. Gere a lista novamente.');

    $decoded = json_decode(base64_decode($payload, true) ?: '', true);
    if (!is_array($decoded) || $decoded === []) {
      throw new Exception('Carga inválida para importação em massa.');
    }

    $stmtBulk = db()->prepare('
      INSERT INTO dashboard_faturamento_ajustes (dash_slug, ref_date, valor, motivo, created_by)
      VALUES (?, ?, ?, ?, ?)
    ');

    $inserted = 0;
    foreach ($decoded as $row) {
      $ref = (string)($row['ref_date'] ?? '');
      $amount = (float)($row['amount'] ?? 0);
      $motivo = trim((string)($row['motivo'] ?? 'Importação em massa'));

      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ref)) continue;
      if (abs($amount) < 0.00001) continue;

      $stmtBulk->execute([$dashboard_slug, $ref, $amount, $motivo, $uid]);
      $inserted++;
    }

    if ($inserted <= 0) throw new Exception('Nenhum ajuste válido foi importado.');
    flash_set('success', $inserted . ' ajuste(s) importado(s) em massa.');
  } catch (Throwable $e) {
    flash_set('error', 'Erro ao importar em massa: ' . $e->getMessage());
  }

  header('Location: /admin/metrics.php?dash=' . urlencode($dashboard_slug));
  exit;
}

if (
  $_SERVER['REQUEST_METHOD'] === 'POST' &&
  $dashboard_slug === 'executivo' &&
  (($_POST['aj_action'] ?? '') === 'add_ajuste')
) {
  try {
    $ref = (string)($_POST['aj_date'] ?? '');
    $rawVal = (string)($_POST['aj_valor'] ?? '');
    $motivo = trim((string)($_POST['aj_motivo'] ?? ''));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ref)) throw new Exception('Data inválida.');
    $val = parse_ptbr_number($rawVal);
    if ($val === null) throw new Exception('Valor inválido.');
    if (abs($val) < 0.00001) throw new Exception('Valor não pode ser zero.');

    $uid = (int)($u['id'] ?? 0);

    $stmtA = db()->prepare('
      INSERT INTO dashboard_faturamento_ajustes (dash_slug, ref_date, valor, motivo, created_by)
      VALUES (?, ?, ?, ?, ?)
    ');
    $stmtA->execute([$dashboard_slug, $ref, $val, $motivo !== '' ? $motivo : null, $uid]);

    flash_set('success', 'Ajuste adicionado.');
  } catch (Throwable $e) {
    flash_set('error', 'Erro ao adicionar ajuste: ' . $e->getMessage());
  }

  header('Location: /admin/metrics.php?dash=' . urlencode($dashboard_slug));
  exit;
}

if (
  $_SERVER['REQUEST_METHOD'] === 'POST' &&
  $dashboard_slug === 'executivo' &&
  (($_POST['aj_action'] ?? '') === 'del_ajuste')
) {
  try {
    $id = (int)($_POST['aj_id'] ?? 0);
    if ($id <= 0) throw new Exception('ID inválido.');

    $stmtD = db()->prepare('
      UPDATE dashboard_faturamento_ajustes
      SET is_active = 0
      WHERE id = ? AND dash_slug = ?
      LIMIT 1
    ');
    $stmtD->execute([$id, $dashboard_slug]);

    flash_set('success', 'Ajuste removido.');
  } catch (Throwable $e) {
    flash_set('error', 'Erro ao remover ajuste: ' . $e->getMessage());
  }

  header('Location: /admin/metrics.php?dash=' . urlencode($dashboard_slug));
  exit;
}
/* =========================================================
   LISTA AJUSTES (executivo)
========================================================= */
$ajustes = [];
if ($dashboard_slug === 'executivo') {
  try {
    $stmtL = db()->prepare('
      SELECT a.id, a.ref_date, a.valor, a.motivo, a.created_at, u.name as user_name
      FROM dashboard_faturamento_ajustes a
      LEFT JOIN users u ON u.id = a.created_by
      WHERE a.dash_slug = ? AND a.is_active = 1
      ORDER BY a.ref_date DESC, a.id DESC
      LIMIT 200
    ');
    $stmtL->execute([$dashboard_slug]);
    $ajustes = $stmtL->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    $ajustes = [];
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

/* =========================================================
   POST HANDLER (metrics manual)
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['daily_action'] ?? '') !== 'save_daily') && (($_POST['aj_action'] ?? '') === '')) {
  try {
    $stmtUp = db()->prepare('
      UPDATE metrics
      SET metric_value_num=?, metric_value_text=?
      WHERE dashboard_slug=? AND metric_key=?
    ');

    foreach (($_POST['m'] ?? []) as $key => $posted) {
      $keyStr = (string)$key;
      if (!isset($manualKeys[$keyStr])) continue;

      $type = (string)($posted['type'] ?? 'money');
      $raw  = (string)($posted['value'] ?? '');

      [$num, $txt] = normalize_value($type, $raw);
      $stmtUp->execute([$num, $txt, $dashboard_slug, $keyStr]);
    }

    header('Location: /admin/metrics.php?dash=' . urlencode($dashboard_slug));
    exit;
  } catch (Throwable $e) {
    $error = 'Erro ao salvar: ' . $e->getMessage();
  }
}
$flash = flash_get();
$dashboardName = ($dashboard_slug === 'executivo') ? 'Faturamento' : 'Financeiro';
?>

<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Métricas de <?= h($dashboardName) ?> — <?= h((string)APP_NAME) ?></title>

<link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(APP_ROOT . '/assets/css/base.css') ?>" />
<link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(APP_ROOT . '/assets/css/users.css') ?>" />
<link rel="stylesheet" href="/assets/css/metrics.css?v=<?= filemtime(APP_ROOT . '/assets/css/metrics.css') ?>" />
<link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(APP_ROOT . '/assets/css/header.css') ?>" />
<link rel="stylesheet" href="/assets/css/loader.css?v=<?= filemtime(APP_ROOT . '/assets/css/loader.css') ?>" /></head>

<body class="page metrics">
  <?php $layout_embed = true; require_once APP_ROOT . '/app/layout/header.php'; ?>

  <main class="container metrics metrics--fullwidth metrics--two-col">
    <h2 class="page-title">Configuração de Métricas de <?= h($dashboardName) ?></h2>

    <nav class="tabs">
      <a class="tab <?= $dashboard_slug === 'executivo' ? 'is-active' : '' ?>"
        href="/admin/metrics.php?dash=executivo">Faturamento</a>
      <a class="tab <?= $dashboard_slug === 'financeiro' ? 'is-active' : '' ?>"
        href="/admin/metrics.php?dash=financeiro">Financeiro</a>
    </nav>

    <div class="card metrics-card">
      <?php if ($error): ?>
        <div class="alert alert--error">❌ <?= h($error) ?></div>
      <?php endif; ?>

      <?php if ($dashboard_slug === 'executivo'): ?>
        <!-- Prévia automática
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
        </section>-->

        <!-- Ajuste manual -->
        <section class="group" style="margin-bottom:18px;">
          <div class="group__title">Ajuste manual de faturamento (soma no dashboard)</div>
          <div class="hint" style="margin:0 0 10px 0;">
            Use <strong>valor positivo</strong> para somar ou <strong>negativo</strong> para subtrair. O ajuste entra no dia escolhido e soma no mês/ano.
          </div>

<?php if (!empty($flash['msg'])): ?>
  <?php $ft = ($flash['type'] ?? 'info'); ?>
  <div class="metrics-alert metrics-alert--<?= h($ft) ?>" role="status" data-alert>
    <span class="metrics-alert__icon"><?= $ft === 'success' ? '✅' : ($ft === 'error' ? '❌' : 'ℹ️') ?></span>
    <div class="metrics-alert__text"><?= h((string)$flash['msg']) ?></div>
    <button class="metrics-alert__close" type="button" aria-label="Fechar" data-alert-close>×</button>
  </div>
<?php endif; ?>

          <form method="post" id="ajusteForm" style="display:grid;grid-template-columns: 180px 220px 1fr auto;gap:10px;align-items:end;">
            <input type="hidden" name="aj_action" value="add_ajuste" />

            <div class="field" style="margin:0;">
              <label class="field__label" for="aj_date">Data</label>
              <input class="field__control" id="aj_date" name="aj_date" type="date"
                value="<?= h(date('Y-m-d')) ?>" />
            </div>

            <div class="field" style="margin:0;">
              <label class="field__label" for="aj_valor">Valor (R$)</label>
              <input class="field__control metric-input" id="aj_valor" name="aj_valor"
                placeholder="Ex: R$ 1.234,56 (ou -123,45)"
                autocomplete="off" inputmode="decimal" data-type="money" />
            </div>

            <div class="field" style="margin:0;">
              <label class="field__label" for="aj_motivo">Motivo (opcional)</label>
              <input class="field__control" id="aj_motivo" name="aj_motivo"
                placeholder="Ex: venda fora do TOTVS / ajuste fechamento" />
            </div>

            <button class="btn btn--primary" type="submit">Adicionar</button>
          </form>

          <div class="metrics-bulk-import">
            <div class="metrics-bulk-import__head">
              <div>
                <div class="group__title" style="font-size:13px;margin-bottom:6px;border-bottom:none;padding-bottom:0;">Importação em massa</div>
                <div class="hint" style="margin:0;">
                  Cole o bloco vindo do Excel, gere a pré-visualização e confirme antes de gravar.
                </div>
              </div>
            </div>

            <form method="post" class="metrics-bulk-form" id="bulkAjusteForm">
              <input type="hidden" name="aj_action" value="preview_bulk_ajustes" />

              <div class="field metrics-bulk-form__raw">
                <label class="field__label" for="aj_bulk_raw">Bloco colado</label>
                <textarea
                  class="field__control metrics-bulk-textarea"
                  id="aj_bulk_raw"
                  name="aj_bulk_raw"
                  placeholder="Ex: 22/abr&#10;20930&#10;EMBAFEST COMERCIO&#10;LUIS LIMA&#10;8.095,28&#10;7.291,35&#10;6.549,26"><?= h($bulkImportRaw) ?></textarea>
              </div>

              <div class="field">
                <label class="field__label" for="aj_bulk_value_strategy">Valor a usar de cada bloco</label>
                <select class="field__control" id="aj_bulk_value_strategy" name="aj_bulk_value_strategy">
                  <option value="first" <?= $bulkImportStrategy === 'first' ? 'selected' : '' ?>>1º valor numérico</option>
                  <option value="second" <?= $bulkImportStrategy === 'second' ? 'selected' : '' ?>>2º valor numérico</option>
                  <option value="third" <?= $bulkImportStrategy === 'third' ? 'selected' : '' ?>>3º valor numérico</option>
                  <option value="last" <?= $bulkImportStrategy === 'last' ? 'selected' : '' ?>>Último valor numérico</option>
                </select>
              </div>

              <div class="field">
                <label class="field__label" for="aj_bulk_year">Ano padrão para datas sem ano</label>
                <input class="field__control" id="aj_bulk_year" name="aj_bulk_year" type="number" min="2020" max="2100"
                  value="<?= h((string)$bulkImportYear) ?>" />
              </div>

              <button class="btn btn--primary" type="submit">Gerar pré-visualização</button>
            </form>

            <?php if ($bulkPreviewErrors !== []): ?>
              <div class="metrics-bulk-errors">
                <?php foreach ($bulkPreviewErrors as $msg): ?>
                  <div class="metrics-alert metrics-alert--error" role="alert">
                    <span class="metrics-alert__icon">❌</span>
                    <div class="metrics-alert__text"><?= h($msg) ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if ($bulkPreviewItems !== []): ?>
              <div class="metrics-bulk-preview">
                <div class="group__title" style="font-size:13px;margin-bottom:8px;border-bottom:none;padding-bottom:0;">
                  Pré-visualização (<?= (int)count($bulkPreviewItems) ?> registro(s))
                </div>

                <div class="metrics-bulk-table-wrap">
                  <table class="metrics-bulk-table">
                    <thead>
                      <tr>
                        <th>Data</th>
                        <th>Pedido</th>
                        <th>Cliente</th>
                        <th>Vendedor</th>
                        <th>Valor usado</th>
                        <th>Valores lidos</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($bulkPreviewItems as $row): ?>
                        <tr>
                          <td><?= h(date('d/m/Y', strtotime((string)$row['ref_date']))) ?></td>
                          <td><?= h((string)$row['order_code']) ?></td>
                          <td><?= h((string)$row['client']) ?></td>
                          <td><?= h((string)$row['seller']) ?></td>
                          <td style="font-weight:800;"><?= h(brl((float)$row['amount'])) ?></td>
                          <td><?= h(implode(' | ', (array)$row['amount_candidates'])) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

                <form method="post" class="metrics-bulk-confirm" data-bulk-confirm>
                  <input type="hidden" name="aj_action" value="confirm_bulk_ajustes" />
                  <input type="hidden" name="aj_bulk_payload" value="<?= h(base64_encode(json_encode($bulkPreviewItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) ?>" />
                  <button class="btn btn--primary" type="submit">Confirmar e importar</button>
                </form>
              </div>
            <?php endif; ?>
          </div>

          <div style="margin-top:14px;">
            <div class="group__title" style="font-size:13px;margin-bottom:8px;">Histórico</div>

            <table style="width:100%;border-collapse:collapse;">
              <thead>
                <tr>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid #e2e8f0;">Data</th>
                  <th style="text-align:right;padding:8px;border-bottom:1px solid #e2e8f0;">Valor</th>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid #e2e8f0;">Motivo</th>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid #e2e8f0;">Por</th>
                  <th style="text-align:right;padding:8px;border-bottom:1px solid #e2e8f0;">Ação</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($ajustes)): ?>
                  <tr><td colspan="5" style="padding:10px;color:#64748b;">Sem ajustes.</td></tr>
                <?php else: ?>
                  <?php foreach ($ajustes as $a): ?>
                    <?php
                      $d = (string)($a['ref_date'] ?? '');
                      $v = (float)($a['valor'] ?? 0);
                      $mot = (string)($a['motivo'] ?? '');
                      $un = (string)($a['user_name'] ?? '');
                    ?>
                    <tr>
                      <td style="padding:8px;border-bottom:1px solid #f1f5f9;"><?= h(date('d/m/Y', strtotime($d))) ?></td>
                      <td style="padding:8px;border-bottom:1px solid #f1f5f9;text-align:right;font-weight:700;">
                        <?= brl($v) ?>
                      </td>
                      <td style="padding:8px;border-bottom:1px solid #f1f5f9;"><?= h($mot) ?></td>
                      <td style="padding:8px;border-bottom:1px solid #f1f5f9;"><?= h($un) ?></td>
                      <td style="padding:8px;border-bottom:1px solid #f1f5f9;text-align:right;">
                        <form method="post" data-ajuste-delete onsubmit="return confirm('Remover este ajuste?');" style="display:inline;">
                          <input type="hidden" name="aj_action" value="del_ajuste" />
                          <input type="hidden" name="aj_id" value="<?= (int)$a['id'] ?>" />
                          <button class="btn btn--secondary" type="submit">Excluir</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>

      <!-- Formulário: somente campos manuais -->
      <form method="post" class="form metrics-form" id="metricsForm" data-metrics-form>
        <?php foreach ($ordered as $groupName => $items): ?>
          <section class="group">
            <div class="group__title"><?= h($groupName) ?></div>

            <?php foreach ($items as $r): ?>
              <?php
                $key = (string)$r['metric_key'];
                $type = (string)$r['metric_type'];

                $placeholder = match ($type) {
                  'money' => 'Ex: R$ 4.000.000,00',
                  'percent' => 'Ex: 59%',
                  'int' => 'Ex: 18',
                  'text' => '',
                  default => ''
                };
              ?>
              <div class="field">
                <label class="field__label" for="m_<?= h($key) ?>">
                  <?= h((string)$r['metric_label']) ?>
                </label>

                <input class="field__control metric-input"
                  id="m_<?= h($key) ?>"
                  name="m[<?= h($key) ?>][value]"
                  value="<?= h(format_for_input($r)) ?>"
                  placeholder="<?= h($placeholder) ?>"
                  autocomplete="off"
                  inputmode="<?= ($type === 'text') ? 'text' : 'decimal' ?>"
                  data-type="<?= h($type) ?>" />

                <input type="hidden"
                  name="m[<?= h($key) ?>][type]"
                  value="<?= h($type) ?>" />
              </div>
            <?php endforeach; ?>
          </section>
        <?php endforeach; ?>

        <div class="hint">
          Dica: digite valores como <strong>R$ 4.000.000,00</strong> ou <strong>18</strong>.
          Ao pressionar <strong>Tab</strong>, ele formata automaticamente.
        </div>

        <button class="btn btn--primary" type="submit">Salvar Alterações</button>
      </form>
    </div>
  </main>

  <?php require_once APP_ROOT . '/app/layout/footer.php'; ?>

  <script>
    window.METRICS_DASH = <?= json_encode($dashboard_slug, JSON_UNESCAPED_UNICODE) ?>;
  </script>

  <!-- Loader ANTES -->
<script src="/assets/js/loader.js?v=<?= filemtime(APP_ROOT . '/assets/js/loader.js') ?>" defer></script>
  <?php if ($dashboard_slug === 'executivo'): ?>
    <script>
      (function () {
        const dash = <?= json_encode($dashboard_slug, JSON_UNESCAPED_UNICODE) ?>;

        const brl = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(v || 0));
        const pct = (v) => new Intl.NumberFormat('pt-BR', { style: 'percent', maximumFractionDigits: 0 }).format(Number(v || 0));

        function loaderShow(title, sub){
          if (window.PopperLoading && typeof window.PopperLoading.show === 'function'){
            window.PopperLoading.show(title || 'Carregando…', sub || 'Aguarde');
          }
        }
        function loaderHide(){
          if (window.PopperLoading && typeof window.PopperLoading.hide === 'function'){
            window.PopperLoading.hide();
          }
        }

        let PREVIEW_CTRL = null;
        let PREVIEW_SEQ = 0;

        async function loadPreview(force = false) {
          const st = document.getElementById('previewStatus');
          if (st) st.textContent = force ? 'Forçando TOTVS...' : 'Carregando...';

          loaderShow(
            force ? 'Atualizando…' : 'Carregando…',
            force ? 'Forçando leitura do TOTVS' : 'Carregando prévia do dashboard'
          );

          try { PREVIEW_CTRL?.abort(); } catch(_) {}
          PREVIEW_CTRL = new AbortController();
          const mySeq = ++PREVIEW_SEQ;

          try {
            const url = `/api/dashboard/dashboard-data.php?dash=${encodeURIComponent(dash)}${force ? '&force=1' : ''}`;
            const timeoutMs = 15000;
            const t = setTimeout(() => { try { PREVIEW_CTRL.abort(); } catch(_) {} }, timeoutMs);

            const res = await fetch(url, { cache: 'no-store', signal: PREVIEW_CTRL.signal });
            clearTimeout(t);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const payload = await res.json();
            if (mySeq !== PREVIEW_SEQ) return;

            const v = payload.values || {};

            const fatHoje = Number(v.hoje_faturado ?? 0);
            const imHoje  = Number(v.hoje_im ?? 0);
            const agHoje  = Number(v.hoje_ag ?? 0);

            const fatMes  = Number(v.mes_faturado ?? 0);
            const imMes   = Number(v.mes_im ?? 0);
            const agMes   = Number(v.mes_ag ?? 0);

            const totalHoje = Number(v.hoje_total ?? (fatHoje + imHoje));
            const totalMes  = Number(v.mes_total ?? (fatMes + imMes));

            const rows = [
              ['Atualizado em', payload.updated_at || '—'],

              ['Hoje (faturado)', brl(fatHoje)],
              ['Hoje (imediato)', brl(imHoje)],
              ['Hoje (agendado)', brl(agHoje)],
              ['Hoje (total)', brl(totalHoje)],

              ['Mês (faturado)', brl(fatMes)],
              ['Mês (imediato)', brl(imMes)],
              ['Mês (agendado)', brl(agMes)],
              ['Mês (total até hoje)', brl(totalMes)],

              ['Realizado anual acumulado', brl(Number(v.realizado_ano_acum ?? 0))],
              ['Meta do mês', brl(Number(v.meta_mes ?? 0))],
              ['Falta para meta do mês', brl(Number(v.falta_meta_mes ?? 0))],
              ['Atingimento do mês', pct(Number(v.atingimento_mes_pct ?? 0))],
              ['Dias úteis', `${Number(v.dias_uteis_trabalhados || 0)} / ${Number(v.dias_uteis_trabalhar || 0)}`],
              ['Meta por dia útil', brl(Number(v.meta_dia_util ?? 0))],
              ['Realizado por dia útil', brl(Number(v.realizado_dia_util ?? 0))],
              ['Vai bater meta?', v.vai_bater_meta ?? '—'],
              ['Projeção de fechamento', brl(Number(v.fechar_em ?? 0))],
              ['Equivale a', pct(Number(v.equivale_pct ?? 0))]
            ];

            const tbody = document.querySelector('#previewTable tbody');
            if (tbody) {
              tbody.innerHTML = '';
              rows.forEach(([k, val]) => {
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
            const isAbort =
              err?.name === 'AbortError' ||
              String(err?.message || '').toLowerCase().includes('aborted');

            if (isAbort) {
              if (st) st.textContent = 'Cancelado (nova atualização em andamento)…';
              return;
            }

            console.error(err);
            if (st) st.textContent = `Erro ❌ ${err?.message || err}`;
            if (window.PopperLoading?.error) window.PopperLoading.error(err?.message || 'Falha ao carregar');
          } finally {
            if (mySeq === PREVIEW_SEQ) setTimeout(loaderHide, 120);
          }
        }

        loadPreview(false);

        const btn = document.getElementById('btnPreviewForce');
        if (btn) btn.addEventListener('click', () => loadPreview(true));
      })();
    </script>
  <?php endif; ?>

<script src="/assets/js/header.js?v=<?= filemtime(APP_ROOT . '/assets/js/header.js') ?>" defer></script>
<script src="/assets/js/dropdowns.js?v=<?= filemtime(APP_ROOT . '/assets/js/dropdowns.js') ?>" defer></script>
<script src="/assets/js/metrics.js?v=<?= filemtime(APP_ROOT . '/assets/js/metrics.js') ?>" defer></script></body>
</html>
