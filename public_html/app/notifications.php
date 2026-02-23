<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Cria uma notificação para um usuário.
 */
function notify_user(
  int $userId,
  string $type,
  string $title,
  ?string $message = null,
  ?string $link = null,
  ?string $module = null
): void {
  if (is_string($link)) {
    $link = trim($link);
    if ($link === '') $link = null;
  }

  $stmt = db()->prepare("
    INSERT INTO notifications (user_id, type, module, title, message, link)
    VALUES (?, ?, ?, ?, ?, ?)
  ");
  $stmt->execute([$userId, $type, $module, $title, $message, $link]);
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

function user_allowed_notification_modules(array $u): array {
  $mods = ['geral']; // sempre

  if (user_can('rh', $u) || user_can('rh_view', $u) || user_can('admin_rh', $u)) $mods[] = 'rh';
  if (user_can('financeiro', $u) || user_can('financeiro_view', $u) || user_can('admin_financeiro', $u)) $mods[] = 'financeiro';
  if (user_can('comercial', $u) || user_can('comercial_view', $u) || user_can('admin_comercial', $u)) $mods[] = 'comercial';

  // Se existir uma permissão “admin geral”, libera tudo
  if (user_can('admin', $u) || user_can('superadmin', $u)) $mods = ['*'];

  return array_values(array_unique($mods));
}
function notifications_unread_count_for_user(array $u): int {
  if (!isset($u['id'])) return 0;
  $userId = (int)$u['id'];

  $mods = user_allowed_notification_modules($u);
  if ($mods === ['*']) {
    $stmt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
  }

  $in = implode(',', array_fill(0, count($mods), '?'));
  $sql = "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0 AND (module IS NULL OR module IN ($in))";
  $stmt = db()->prepare($sql);
  $stmt->execute(array_merge([$userId], $mods));
  return (int)$stmt->fetchColumn();
}

function notifications_latest_for_user(array $u, int $limit = 10): array {
  if (!isset($u['id'])) return [];
  $userId = (int)$u['id'];

  $limit = max(1, min(30, $limit));
  $mods = user_allowed_notification_modules($u);

  if ($mods === ['*']) {
    $stmt = db()->prepare("
      SELECT id, type, module, title, message, link, is_read, created_at
      FROM notifications
      WHERE user_id = ?
      ORDER BY id DESC
      LIMIT $limit
    ");
    $stmt->execute([$userId]);
    return (array)$stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  $in = implode(',', array_fill(0, count($mods), '?'));
  $sql = "
    SELECT id, type, module, title, message, link, is_read, created_at
    FROM notifications
    WHERE user_id = ?
      AND (module IS NULL OR module IN ($in))
    ORDER BY id DESC
    LIMIT $limit
  ";
  $stmt = db()->prepare($sql);
  $stmt->execute(array_merge([$userId], $mods));
  return (array)$stmt->fetchAll(PDO::FETCH_ASSOC);
}