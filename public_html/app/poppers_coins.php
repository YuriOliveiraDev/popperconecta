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

function apply_ledger(int $userId, int $amount, string $type, ?string $reason, int $adminId): void
{
    ensure_wallet($userId);

    db()->beginTransaction();

    try {
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

        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}