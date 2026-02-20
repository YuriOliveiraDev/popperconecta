<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_admin();

$u = current_user();

// header dashboards
try {
  $dashboards = db()->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}

$success = '';
$error = '';

function ensure_wallet_int(int $userId): void {
  $stmt = db()->prepare("INSERT IGNORE INTO popper_coin_wallets (user_id, balance) VALUES (?, 0)");
  $stmt->execute([$userId]);
}

/**
 * ✅ Ledger SEM transação interna.
 * A transação deve ser controlada por quem chama (approve/reject).
 */
function apply_ledger_no_tx(int $userId, int $amount, string $type, ?string $reason, int $actorId): void {
  ensure_wallet_int($userId);

  $stmt = db()->prepare("INSERT INTO popper_coin_ledger (user_id, amount, action_type, reason, created_by) VALUES (?, ?, ?, ?, ?)");
  $stmt->execute([$userId, $amount, $type, $reason, $actorId]);

  $stmt = db()->prepare("UPDATE popper_coin_wallets SET balance = balance + ? WHERE user_id = ?");
  $stmt->execute([$amount, $userId]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    $adminNote = trim((string)($_POST['admin_note'] ?? ''));

    if ($id <= 0) throw new Exception('Pedido inválido.');
    if (!in_array($action, ['approve','reject'], true)) throw new Exception('Ação inválida.');

    $db = db();
    $db->beginTransaction();
    try {
      // Lock do pedido
      $stmt = $db->prepare("
        SELECT r.id, r.user_id, r.reward_id, r.cost, r.qty, r.status,
               rw.title, rw.inventory
        FROM popper_coin_redemptions r
        JOIN popper_coin_rewards rw ON rw.id = r.reward_id
        WHERE r.id = ?
        FOR UPDATE
      ");
      $stmt->execute([$id]);
      $r = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$r) throw new Exception('Pedido não encontrado.');
      if ((string)$r['status'] !== 'pending') throw new Exception('Pedido já foi decidido.');

      $userId = (int)$r['user_id'];
      $rewardId = (int)$r['reward_id'];
      $cost = (int)$r['cost'];
      $qty = (int)($r['qty'] ?? 1);
      if ($qty <= 0) $qty = 1;

      $rewardTitle = (string)$r['title'];

      if ($action === 'approve') {
        // ✅ Aprovar: apenas marca aprovado. (saldo e inventário já foram “segurados” no pedido)
        $stmt = $db->prepare("
          UPDATE popper_coin_redemptions
          SET status='approved', admin_note=?, decided_by=?, decided_at=NOW()
          WHERE id=?
        ");
        $stmt->execute([$adminNote !== '' ? $adminNote : null, (int)$u['id'], $id]);

        $db->commit();
        $success = 'Pedido aprovado.';
      } else {
        // ✅ Negar: devolve saldo e devolve inventário
        // 1) devolve saldo (ledger +)
        $reason = 'Reembolso (pedido negado): ' . $rewardTitle;
        apply_ledger_no_tx($userId, abs($cost), 'refund', $reason, (int)$u['id']);

        // 2) devolve inventário
        $stmt = $db->prepare("UPDATE popper_coin_rewards SET inventory = inventory + ? WHERE id=?");
        $stmt->execute([$qty, $rewardId]);

        // 3) marca rejeitado
        $stmt = $db->prepare("
          UPDATE popper_coin_redemptions
          SET status='rejected', admin_note=?, decided_by=?, decided_at=NOW()
          WHERE id=?
        ");
        $stmt->execute([$adminNote !== '' ? $adminNote : null, (int)$u['id'], $id]);

        $db->commit();
        $success = 'Pedido negado e saldo/inventário devolvidos.';
      }
    } catch (Throwable $e) {
      $db->rollBack();
      throw $e;
    }
  } catch (Throwable $e) {
    $error = 'Erro: ' . $e->getMessage();
  }
}

$pending = db()->query("
  SELECT r.id, r.created_at, r.user_note, r.cost, r.qty, r.status,
         u.name AS user_name, u.email,
         rw.title AS reward_title
  FROM popper_coin_redemptions r
  JOIN users u ON u.id = r.user_id
  JOIN popper_coin_rewards rw ON rw.id = r.reward_id
  WHERE r.status = 'pending'
  ORDER BY r.id DESC
  LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>RH · Aprovações (Coins) — <?= htmlspecialchars((string)APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/../assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../assets/css/dashboard.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/../assets/css/dropdowns.css') ?>" />
</head>
<body class="page">

<?php require_once __DIR__ . '/../app/header.php'; ?>

<main class="container">
  <h2 class="page-title">RH · Aprovações de Resgate (Popper Coins)</h2>

  <?php if ($success): ?><div class="alert alert--ok"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

  <div class="card">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Data</th>
            <th>Usuário</th>
            <th>Recompensa</th>
            <th class="right">Custo</th>
            <th class="right">Qtd</th>
            <th>Obs. usuário</th>
            <th class="right">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$pending): ?>
            <tr><td colspan="7" class="muted">Sem pendências.</td></tr>
          <?php else: ?>
            <?php foreach ($pending as $p): ?>
              <tr>
                <td><?= htmlspecialchars((string)$p['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <?= htmlspecialchars((string)$p['user_name'], ENT_QUOTES, 'UTF-8') ?>
                  <div class="muted"><?= htmlspecialchars((string)$p['email'], ENT_QUOTES, 'UTF-8') ?></div>
                </td>
                <td><?= htmlspecialchars((string)$p['reward_title'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="right"><?= (int)$p['cost'] ?></td>
                <td class="right"><?= (int)($p['qty'] ?? 1) ?></td>
                <td><?= htmlspecialchars((string)($p['user_note'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="right">
                  <form method="post" style="display:inline-block;min-width:340px">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>" />
                    <input type="text" name="admin_note" placeholder="Obs. RH (opcional)" style="max-width:190px" />
                    <button class="btn btn--primary" type="submit" name="action" value="approve" onclick="return confirm('Aprovar pedido?');">Aprovar</button>
                    <button class="btn btn--danger" type="submit" name="action" value="reject" onclick="return confirm('Negar e devolver saldo/inventário?');">Negar</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/../assets/js/dropdowns.js') ?>"></script>
</body>
</html>