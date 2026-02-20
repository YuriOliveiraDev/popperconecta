<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Cria uma notificação para um usuário.
 */
function notify_user(int $userId, string $type, string $title, ?string $message = null, ?string $link = null): void {
  $stmt = db()->prepare("
    INSERT INTO notifications (user_id, type, title, message, link)
    VALUES (?, ?, ?, ?, ?)
  ");
  $stmt->execute([$userId, $type, $title, $message, $link]);
}

/**
 * Retorna contador de não lidas.
 */
function notifications_unread_count(int $userId): int {
  $stmt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
  $stmt->execute([$userId]);
  return (int)$stmt->fetchColumn();
}

/**
 * Retorna últimas notificações.
 */
function notifications_latest(int $userId, int $limit = 10): array {
  $limit = max(1, min(30, $limit));
  $stmt = db()->prepare("
    SELECT id, type, title, message, link, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT $limit
  ");
  $stmt->execute([$userId]);
  return (array)$stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Marca uma notificação como lida.
 */
function notifications_mark_read(int $userId, int $notifId): void {
  $stmt = db()->prepare("
    UPDATE notifications
    SET is_read = 1, read_at = NOW()
    WHERE id = ? AND user_id = ?
  ");
  $stmt->execute([$notifId, $userId]);
}

/**
 * Marca todas como lidas.
 */
function notifications_mark_all_read(int $userId): void {
  $stmt = db()->prepare("
    UPDATE notifications
    SET is_read = 1, read_at = NOW()
    WHERE user_id = ? AND is_read = 0
  ");
  $stmt->execute([$userId]);
}