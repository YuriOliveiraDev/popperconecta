<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';

function log_approval_action(
  string $entityType,
  int $entityId,
  string $action,
  string $status,
  ?string $note = null
): void {
  $u = current_user();
  $userId = (int)($u['id'] ?? 0);
  $userName = (string)($u['name'] ?? 'Usuário');

  $stmt = db()->prepare(
    'INSERT INTO approval_logs (entity_type, entity_id, action, status, note, approved_by_user_id, approved_by_name)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
  );
  $stmt->execute([$entityType, $entityId, $action, $status, $note, $userId, $userName]);
}