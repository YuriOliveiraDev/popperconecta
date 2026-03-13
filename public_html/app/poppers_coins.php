<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function ensure_wallet(int $userId): void
{
    $stmt = db()->prepare("
        INSERT IGNORE INTO popper_coin_wallets (user_id, balance)
        VALUES (?, 0)
    ");
    $stmt->execute([$userId]);
}

/**
 * Aplica lançamento SEM abrir transação.
 * Ideal para uso em lotes/multiusuários, quando a transação já foi aberta fora.
 */
function apply_ledger_no_tx(int $userId, int $amount, string $type, ?string $reason, int $adminId): void
{
    ensure_wallet($userId);

    $stmt = db()->prepare("
        INSERT INTO popper_coin_ledger
            (user_id, amount, action_type, reason, created_by)
        VALUES
            (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $amount, $type, $reason, $adminId]);

    $stmt = db()->prepare("
        UPDATE popper_coin_wallets
        SET balance = balance + ?
        WHERE user_id = ?
    ");
    $stmt->execute([$amount, $userId]);
}

/**
 * Aplica lançamento com transação própria.
 * Ideal para uso unitário.
 */
function apply_ledger(int $userId, int $amount, string $type, ?string $reason, int $adminId): void
{
    $pdo = db();

    $startedHere = false;

    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $startedHere = true;
    }

    try {
        apply_ledger_no_tx($userId, $amount, $type, $reason, $adminId);

        if ($startedHere && $pdo->inTransaction()) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($startedHere && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}