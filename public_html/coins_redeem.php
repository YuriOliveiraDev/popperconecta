<?php
declare(strict_types=1);
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';

require_login();
$u = current_user();
$userId = (int)($u['id'] ?? 0);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$rewardId = (int)($_POST['reward_id'] ?? 0);
$userNote = trim((string)($_POST['user_note'] ?? ''));
$token = (string)($_POST['redeem_token'] ?? '');

function ensure_wallet_int(int $userId): void {
  $stmt = db()->prepare("INSERT IGNORE INTO popper_coin_wallets (user_id, balance) VALUES (?, 0)");
  $stmt->execute([$userId]);
}

/**
 * Ledger SEM transação interna (para funcionar dentro da transação principal)
 */
function apply_ledger_no_tx(int $userId, int $amount, string $type, ?string $reason, int $actorId): void {
  ensure_wallet_int($userId);

  $stmt = db()->prepare("INSERT INTO popper_coin_ledger (user_id, amount, action_type, reason, created_by) VALUES (?, ?, ?, ?, ?)");
  $stmt->execute([$userId, $amount, $type, $reason, $actorId]);

  $stmt = db()->prepare("UPDATE popper_coin_wallets SET balance = balance + ? WHERE user_id = ?");
  $stmt->execute([$amount, $userId]);
}

try {
  if ($rewardId <= 0) throw new Exception('Recompensa inválida.');

  // ✅ valida token anti-duplicação
  if ($token === '' || empty($_SESSION['redeem_token']) || !hash_equals((string)$_SESSION['redeem_token'], $token)) {
    throw new Exception('Requisição inválida ou repetida. Atualize a página e tente novamente.');
  }
  // “gasta” token
  unset($_SESSION['redeem_token']);

  $db = db();
  $db->beginTransaction();

  // ✅ impede duplicar pedido pendente igual
  $stmt = $db->prepare("
    SELECT COUNT(*)
    FROM popper_coin_redemptions
    WHERE user_id = ? AND reward_id = ? AND status = 'pending'
    FOR UPDATE
  ");
  $stmt->execute([$userId, $rewardId]);
  if ((int)$stmt->fetchColumn() > 0) {
    throw new Exception('Você já tem um pedido pendente para esta recompensa.');
  }

  // trava reward
  $stmt = $db->prepare("SELECT id, title, cost, inventory, is_active FROM popper_coin_rewards WHERE id = ? FOR UPDATE");
  $stmt->execute([$rewardId]);
  $rw = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$rw) throw new Exception('Recompensa não encontrada.');
  if ((int)$rw['is_active'] !== 1) throw new Exception('Recompensa indisponível.');

  $qty = 1;
  $inventory = (int)($rw['inventory'] ?? 0);
  if ($inventory < $qty) throw new Exception('Sem inventário suficiente.');

  $cost = (int)($rw['cost'] ?? 0);
  if ($cost <= 0) throw new Exception('Custo inválido.');

  $title = (string)($rw['title'] ?? '');

  // trava wallet
  ensure_wallet_int($userId);

  $stmt = $db->prepare("SELECT balance FROM popper_coin_wallets WHERE user_id = ? FOR UPDATE");
  $stmt->execute([$userId]);
  $balance = (int)($stmt->fetchColumn() ?? 0);

  if ($balance < $cost) throw new Exception('Saldo insuficiente.');

  // ✅ segura saldo (desconto temporário)
  apply_ledger_no_tx($userId, -abs($cost), 'hold', 'Resgate solicitado (pendente): ' . $title, $userId);

  // ✅ segura inventário
  $stmt = $db->prepare("UPDATE popper_coin_rewards SET inventory = inventory - ? WHERE id = ?");
  $stmt->execute([$qty, $rewardId]);

  // ✅ cria pedido
  $stmt = $db->prepare("
    INSERT INTO popper_coin_redemptions (user_id, reward_id, cost, qty, status, user_note, created_at)
    VALUES (?, ?, ?, ?, 'pending', ?, NOW())
  ");
  $stmt->execute([$userId, $rewardId, $cost, $qty, ($userNote !== '' ? $userNote : null)]);

  $db->commit();

  // novo token para próxima tentativa
  $_SESSION['redeem_token'] = bin2hex(random_bytes(16));

  header('Location: /coins.php?ok=redeem');
  exit;

} catch (Throwable $e) {
  // tenta desfazer transação
  try {
    if (db()->inTransaction()) db()->rollBack();
  } catch (Throwable $ignore) {}

  // reemite token para permitir tentar novamente
  $_SESSION['redeem_token'] = bin2hex(random_bytes(16));

  // log server-side
  error_log('[coins_redeem.php] ' . $e->getMessage());

  header('Location: /coins.php?err=' . urlencode($e->getMessage()));
  exit;
}