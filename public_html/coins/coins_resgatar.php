<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/app/integrations/pipefy-rh.php';

require_login();

date_default_timezone_set('America/Sao_Paulo');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$u = current_user();
$userId = (int) ($u['id'] ?? 0);

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
$activePage = 'coins_resgatar';
$page_title = 'Loja Popper Coins';
$html_class = 'page coins-page coins-resgatar-page';

$success = '';
$error = '';

if (isset($_GET['ok'])) {
    $success = 'Pedido enviado com sucesso.';
}
if (isset($_GET['err'])) {
    $error = 'Erro: ' . (string) $_GET['err'];
}

$extra_css = [
    '/assets/css/base.css?v=' . @filemtime(APP_ROOT . '/assets/css/base.css'),
    '/assets/css/header.css?v=' . @filemtime(APP_ROOT . '/assets/css/header.css'),
    '/assets/css/coins_resgatar.css?v=' . @filemtime(APP_ROOT . '/assets/css/coins_resgatar.css'),
];

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['redeem_token'])) {
    $_SESSION['redeem_token'] = bin2hex(random_bytes(16));
}
$redeemToken = (string) $_SESSION['redeem_token'];

function ensure_wallet_int(int $userId): void
{
    $stmt = db()->prepare("
        INSERT IGNORE INTO popper_coin_wallets (user_id, balance)
        VALUES (?, 0)
    ");
    $stmt->execute([$userId]);
}

function apply_ledger_no_tx(int $userId, int $amount, string $type, ?string $reason, int $actorId): void
{
    ensure_wallet_int($userId);

    $stmt = db()->prepare("
        INSERT INTO popper_coin_ledger (user_id, amount, action_type, reason, created_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $amount, $type, $reason, $actorId]);

    $stmt = db()->prepare("
        UPDATE popper_coin_wallets
        SET balance = balance + ?
        WHERE user_id = ?
    ");
    $stmt->execute([$amount, $userId]);
}

// processa resgate
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['redeem_reward_id'])) {
    try {
        $rewardId = (int) ($_POST['redeem_reward_id'] ?? 0);
        $userNote = trim((string) ($_POST['user_note'] ?? ''));
        $token = (string) ($_POST['token'] ?? '');

        if ($rewardId <= 0) {
            throw new Exception('Recompensa inválida.');
        }

        if ($token === '' || empty($_SESSION['redeem_token']) || !hash_equals($_SESSION['redeem_token'], $token)) {
            throw new Exception('Requisição inválida ou repetida.');
        }

        unset($_SESSION['redeem_token']);

        $db = db();
        $db->beginTransaction();

        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM popper_coin_redemptions
            WHERE user_id = ? AND reward_id = ? AND status = 'pending'
            FOR UPDATE
        ");
        $stmt->execute([$userId, $rewardId]);

        if ((int) $stmt->fetchColumn() > 0) {
            throw new Exception('Você já tem um pedido pendente para esta recompensa.');
        }

        $stmt = $db->prepare("
            SELECT id, title, cost, inventory, is_active
            FROM popper_coin_rewards
            WHERE id = ?
            FOR UPDATE
        ");
        $stmt->execute([$rewardId]);
        $rw = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rw) {
            throw new Exception('Recompensa não encontrada.');
        }
        if ((int) $rw['is_active'] !== 1) {
            throw new Exception('Recompensa indisponível.');
        }

        $qty = 1;
        $inventory = (int) ($rw['inventory'] ?? 0);
        if ($inventory < $qty) {
            throw new Exception('Sem inventário suficiente.');
        }

        $cost = (int) ($rw['cost'] ?? 0);
        if ($cost <= 0) {
            throw new Exception('Custo inválido.');
        }

        $title = trim((string) ($rw['title'] ?? ''));
        if ($title === '') {
            $title = 'Recompensa';
        }

        ensure_wallet_int($userId);

        $stmt = $db->prepare("
            SELECT balance
            FROM popper_coin_wallets
            WHERE user_id = ?
            FOR UPDATE
        ");
        $stmt->execute([$userId]);
        $balanceCheck = (int) ($stmt->fetchColumn() ?? 0);

        if ($balanceCheck < $cost) {
            throw new Exception('Saldo insuficiente.');
        }

        apply_ledger_no_tx(
            $userId,
            -abs($cost),
            'hold',
            'Resgate solicitado (pendente): ' . $title,
            $userId
        );

        $stmt = $db->prepare("
            UPDATE popper_coin_rewards
            SET inventory = inventory - ?
            WHERE id = ?
        ");
        $stmt->execute([$qty, $rewardId]);

        $stmt = $db->prepare("
            INSERT INTO popper_coin_redemptions (user_id, reward_id, cost, qty, status, user_note, created_at)
            VALUES (?, ?, ?, ?, 'pending', ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $rewardId,
            $cost,
            $qty,
            ($userNote !== '' ? $userNote : null),
        ]);

        $solicitanteReal = trim((string) ($u['name'] ?? ''));
        $emailSolicitante = trim((string) ($u['email'] ?? ''));
        $setorSolicitante = trim((string) ($u['setor'] ?? ''));
        $dataHoraSolicitacao = date('d/m/Y H:i');

        $itemSolicitado = $title;
        $quantidadeSolicitada = $qty;
        $custoSolicitado = $cost;

        $tituloPipefy = 'Troca Popper Coins - ' . ($solicitanteReal !== '' ? $solicitanteReal : 'Colaborador') . ' - ' . $itemSolicitado;
        $descricaoPipefy = 'Resgate de PopperCoins PopperConecta';

        $infoAdicionaisPipefy =
            "Solicitante: " . ($solicitanteReal !== '' ? $solicitanteReal : '-') . "\n" .
            "Email: " . ($emailSolicitante !== '' ? $emailSolicitante : '-') . "\n" .
            "Setor: " . ($setorSolicitante !== '' ? $setorSolicitante : '-') . "\n" .
            "Item solicitado: " . ($itemSolicitado !== '' ? $itemSolicitado : '-') . "\n" .
            "Quantidade: " . $quantidadeSolicitada . "\n" .
            "Custo: " . $custoSolicitado . " Popper Coins\n" .
            "Data/Hora da solicitação: " . $dataHoraSolicitacao . "\n";

        try {
            pipefy_create_rh_redemption_card([
                'title' => $tituloPipefy,
                'descricao' => $descricaoPipefy,
                'informacoes_adicionais' => $infoAdicionaisPipefy,
            ]);
        } catch (Throwable $e) {
            error_log('[PIPEFY_RH_REDEMPTION] ' . $e->getMessage());
        }

        $rhUsers = db()->query("
            SELECT id
            FROM users
            WHERE role IN ('rh', 'admin')
        ")->fetchAll(PDO::FETCH_ASSOC);

        if ($rhUsers) {
            $stmtN = db()->prepare("
                INSERT INTO notifications (user_id, type, title, message, link)
                VALUES (?, 'coins_redeem_requested', 'Novo pedido de resgate', ?, '/rh_redemptions.php')
            ");

            $msg = (string) ($u['name'] ?? 'Usuário') . ' solicitou "' . $title . '" (' . $cost . ' coins).';

            foreach ($rhUsers as $rh) {
                $stmtN->execute([(int) $rh['id'], $msg]);
            }
        }

        $db->commit();

        $_SESSION['redeem_token'] = bin2hex(random_bytes(16));
        header('Location: /coins/coins_resgatar.php?ok=redeem');
        exit;
    } catch (Throwable $e) {
        try {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
        } catch (Throwable $ignore) {
        }

        $_SESSION['redeem_token'] = bin2hex(random_bytes(16));
        header('Location: /coins/coins_resgatar.php?err=' . urlencode($e->getMessage()));
        exit;
    }
}

ensure_wallet_int($userId);

$stmt = db()->prepare("
    SELECT COALESCE(balance, 0)
    FROM popper_coin_wallets
    WHERE user_id = ?
");
$stmt->execute([$userId]);
$balance = (int) ($stmt->fetchColumn() ?? 0);

$rewards = db()->query("
    SELECT id, title, description, cost, inventory, image_url
    FROM popper_coin_rewards
    WHERE is_active = 1
    ORDER BY sort_order ASC, id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$totalRewards = count($rewards);
$totalAvailable = 0;
$totalRedeemable = 0;
$totalOutOfStock = 0;

foreach ($rewards as $item) {
    $inv = (int) ($item['inventory'] ?? 0);
    $itemCost = (int) ($item['cost'] ?? 0);

    if ($inv > 0) {
        $totalAvailable++;
    } else {
        $totalOutOfStock++;
    }

    if ($inv > 0 && $balance >= $itemCost) {
        $totalRedeemable++;
    }
}

require_once APP_ROOT . '/app/layout/header.php';
?>

<main class="container coins">
  <div class="pc-header">
    <h1 class="pc-title" style="padding-top:10px;">Loja Popper Coins</h1>
    <p class="pc-subtitle">Escolha suas recompensas e solicite seu resgate.</p>
  </div>

  <?php if ($success): ?>
    <div class="alert alert--ok"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <div class="pc-container">
    <section class="pc-summary-grid">
      <div class="pc-card pc-card--balance">
        <div class="pc-balance-head">
          <h3 class="pc-balance-title">Seu saldo</h3>
          <span class="pc-balance-pill">Popper Coins</span>
        </div>

        <div class="pc-balance-value">
          <div class="pc-balance-num"><?= number_format($balance, 0, ',', '.') ?></div>
          <div class="pc-balance-unit">coins</div>
        </div>

        <div class="pc-balance-foot">
          <span class="pc-balance-label">Usuário</span>
          <span class="pc-balance-user"><?= htmlspecialchars((string) ($u['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      </div>

      <div class="pc-mini-stats">
        <div class="pc-mini-stat">
          <span class="pc-mini-stat__label">Recompensas</span>
          <strong class="pc-mini-stat__value"><?= $totalRewards ?></strong>
        </div>
        <div class="pc-mini-stat">
          <span class="pc-mini-stat__label">Disponíveis</span>
          <strong class="pc-mini-stat__value"><?= $totalAvailable ?></strong>
        </div>
        <div class="pc-mini-stat">
          <span class="pc-mini-stat__label">Resgatáveis</span>
          <strong class="pc-mini-stat__value"><?= $totalRedeemable ?></strong>
        </div>
        <div class="pc-mini-stat">
          <span class="pc-mini-stat__label">Sem estoque</span>
          <strong class="pc-mini-stat__value"><?= $totalOutOfStock ?></strong>
        </div>
      </div>
    </section>

    <section class="pc-card">
      <div class="pc-card-head">
        <h3 class="pc-card-title">Catálogo de recompensas</h3>
        <span class="pc-card-badge">Ativas</span>
      </div>

      <p class="muted pc-info-text">
        O saldo e o inventário serão bloqueados temporariamente até a decisão do RH.
      </p>

      <div class="reward-toolbar">
        <div class="reward-search-wrap">
          <input type="text" id="rewardSearch" class="reward-search" placeholder="Buscar recompensa..." />
        </div>

        <div class="reward-filter-group">
          <button type="button" class="reward-filter is-active" data-filter="all">Todas</button>
          <button type="button" class="reward-filter" data-filter="redeemable">Resgatáveis</button>
          <button type="button" class="reward-filter" data-filter="out">Sem estoque</button>
          <button type="button" class="reward-filter" data-filter="insufficient">Saldo insuficiente</button>
        </div>

        <div class="reward-sort-wrap">
          <select id="rewardSort" class="reward-sort">
            <option value="default">Ordenar: padrão</option>
            <option value="cost_asc">Menor custo</option>
            <option value="cost_desc">Maior custo</option>
            <option value="name_asc">Nome A-Z</option>
          </select>
        </div>
      </div>

      <?php if (!$rewards): ?>
        <div class="muted">Nenhuma recompensa cadastrada.</div>
      <?php else: ?>
        <div class="reward-grid" id="rewardGrid">
          <?php foreach ($rewards as $index => $rw): ?>
            <?php
            $inv = (int) ($rw['inventory'] ?? 0);
            $itemCost = (int) ($rw['cost'] ?? 0);
            $disabled = ($inv <= 0 || $balance < $itemCost);

            $rewardTitle = (string) ($rw['title'] ?? '');
            $desc = trim((string) ($rw['description'] ?? ''));
            $img = trim((string) ($rw['image_url'] ?? ''));

            $hint = '';
            $statusLabel = 'Disponível';
            $statusClass = 'is-available';

            if ($inv <= 0) {
                $hint = 'Indisponível no momento.';
                $statusLabel = 'Sem estoque';
                $statusClass = 'is-out';
            } elseif ($balance < $itemCost) {
                $hint = 'Saldo insuficiente.';
                $statusLabel = 'Saldo insuficiente';
                $statusClass = 'is-insufficient';
            } else {
                $statusLabel = 'Pode resgatar';
                $statusClass = 'is-redeemable';
            }
            ?>

            <article
              class="reward-card<?= $disabled ? ' is-disabled' : '' ?>"
              data-title="<?= htmlspecialchars($rewardTitle, ENT_QUOTES, 'UTF-8') ?>"
              data-cost="<?= $itemCost ?>"
              data-stock="<?= $inv ?>"
              data-order="<?= $index ?>"
              data-filter-state="<?= $inv <= 0 ? 'out' : (($balance < $itemCost) ? 'insufficient' : 'redeemable') ?>"
            >
              <div class="reward-media">
                <?php if ($img !== ''): ?>
                  <img
                    src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>"
                    alt="<?= htmlspecialchars($rewardTitle, ENT_QUOTES, 'UTF-8') ?>"
                    loading="lazy">
                <?php else: ?>
                  <div class="reward-fallback" aria-hidden="true">🎁</div>
                <?php endif; ?>

                <span class="reward-badge <?= $statusClass ?>">
                  <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                </span>
              </div>

              <div class="reward-body">
                <div class="reward-title"><?= htmlspecialchars($rewardTitle, ENT_QUOTES, 'UTF-8') ?></div>

                <?php if ($desc !== ''): ?>
                  <div class="reward-desc"><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></div>
                <?php else: ?>
                  <div class="reward-desc muted">Sem descrição.</div>
                <?php endif; ?>

                <div class="reward-meta">
                  <div class="reward-price">
                    <span class="reward-price-num"><?= $itemCost ?></span>
                    <span class="reward-price-unit">coins</span>
                  </div>

                  <div class="reward-stock">
                    Estoque: <strong><?= $inv ?></strong>
                  </div>
                </div>

                <form method="post" class="reward-form">
                  <input type="hidden" name="redeem_reward_id" value="<?= (int) $rw['id'] ?>">
                  <input type="hidden" name="token" value="<?= htmlspecialchars($redeemToken, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="user_note" value="">

                  <div class="reward-hint <?= $hint !== '' ? 'is-on' : '' ?>">
                    <?= htmlspecialchars($hint !== '' ? $hint : ' ', ENT_QUOTES, 'UTF-8') ?>
                  </div>

                  <button
                    class="btn btn--primary reward-btn"
                    type="submit"
                    <?= $disabled ? 'disabled' : '' ?>
                    onclick="return confirm('Solicitar resgate? Saldo e inventário serão bloqueados até decisão do RH.');">
                    Resgatar
                  </button>
                </form>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <div id="rewardEmpty" class="reward-empty" hidden>
          Nenhuma recompensa encontrada para o filtro atual.
        </div>
      <?php endif; ?>
    </section>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const forms = document.querySelectorAll('.pc-container form[method="post"]');
  const searchInput = document.getElementById('rewardSearch');
  const sortSelect = document.getElementById('rewardSort');
  const filterButtons = document.querySelectorAll('.reward-filter');
  const grid = document.getElementById('rewardGrid');
  const empty = document.getElementById('rewardEmpty');

  let activeFilter = 'all';

  function norm(text) {
    return String(text || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim();
  }

  forms.forEach(function (form) {
    form.addEventListener('submit', function () {
      const btn = form.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Enviando...';
      }
    });
  });

  function applyCatalogFilters() {
    if (!grid) return;

    const query = norm(searchInput ? searchInput.value : '');
    const sort = sortSelect ? sortSelect.value : 'default';
    const cards = Array.from(grid.querySelectorAll('.reward-card'));

    cards.forEach(function (card) {
      const title = norm(card.dataset.title || '');
      const state = card.dataset.filterState || 'all';

      let visible = true;

      if (query && !title.includes(query)) {
        visible = false;
      }

      if (activeFilter !== 'all' && state !== activeFilter) {
        visible = false;
      }

      card.style.display = visible ? '' : 'none';
    });

    const visibleCards = cards.filter(card => card.style.display !== 'none');

    visibleCards.sort(function (a, b) {
      const costA = Number(a.dataset.cost || 0);
      const costB = Number(b.dataset.cost || 0);
      const orderA = Number(a.dataset.order || 0);
      const orderB = Number(b.dataset.order || 0);
      const titleA = norm(a.dataset.title || '');
      const titleB = norm(b.dataset.title || '');

      switch (sort) {
        case 'cost_asc':
          return costA - costB || orderA - orderB;
        case 'cost_desc':
          return costB - costA || orderA - orderB;
        case 'name_asc':
          return titleA.localeCompare(titleB, 'pt-BR') || orderA - orderB;
        default:
          return orderA - orderB;
      }
    });

    visibleCards.forEach(card => grid.appendChild(card));

    if (empty) {
      empty.hidden = visibleCards.length > 0;
    }
  }

  if (searchInput) {
    searchInput.addEventListener('input', applyCatalogFilters);
    searchInput.addEventListener('keyup', applyCatalogFilters);
    searchInput.addEventListener('search', applyCatalogFilters);
  }

  if (sortSelect) {
    sortSelect.addEventListener('change', applyCatalogFilters);
  }

  filterButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      filterButtons.forEach(b => b.classList.remove('is-active'));
      btn.classList.add('is-active');
      activeFilter = btn.dataset.filter || 'all';
      applyCatalogFilters();
    });
  });

  applyCatalogFilters();
});
</script>

<script src="/assets/js/header.js?v=<?= @filemtime(APP_ROOT . '/assets/js/header.js') ?>"></script>
<script src="/assets/js/dropdowns.js?v=<?= @filemtime(APP_ROOT . '/assets/js/dropdowns.js') ?>"></script>

<?php require_once APP_ROOT . '/app/layout/footer.php'; ?>