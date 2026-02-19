<?php
declare(strict_types=1);

/**
 * /app/header.php
 * Header reutilizável (baseado no dashboard.php).
 *
 * Requisitos:
 * - A página que inclui já deve ter chamado require_login() ou require_admin().
 * - Pode (opcional) definir $dashboards antes do include para listar dashboards extras.
 * - Pode (opcional) definir $current_dash para o dashboard atual (padrão: 'executivo').
 * - Pode (opcional) definir $activePage para destacar o menu ativo (ex.: 'home', 'dashboard', 'financeiro').
 */

if (!function_exists('current_user')) {
  require_once __DIR__ . '/auth.php';
}

$u = $u ?? current_user(); // se a página não definiu $u, pega aqui
$userName = is_array($u) && isset($u['name']) && is_string($u['name']) && $u['name'] !== '' ? $u['name'] : 'usuário';
$userRole = is_array($u) && isset($u['role']) && is_string($u['role']) ? $u['role'] : '';
$current_dash = $current_dash ?? 'executivo'; // padrão para métricas
$activePage = $activePage ?? ''; // padrão: nenhuma página ativa
?>
<header class="topbar">
  <div class="topbar__left">
    <a class="brand" href="/index.php" style="text-decoration:none;">
      <?= htmlspecialchars((string)APP_NAME, ENT_QUOTES, 'UTF-8') ?>
    </a>
    <span class="muted">Bem-vindo, <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></span>

    <!-- Menu Início -->
    <a class="link<?= ($activePage === 'home' ? ' link--active' : '') ?>" href="/index.php" style="margin-left:12px;">Início</a>

    <?php if ($userRole === 'admin'): ?>
      <!-- Administração (dropdown) -->
      <div class="topbar__dropdown" style="margin-left:12px;">
        <a class="topbar__dropdown-trigger" href="#" id="adminTrigger">Administração</a>
        <div class="topbar__dropdown-menu" id="adminMenu">
          <a class="topbar__dropdown-item" href="/admin/users.php">
            <span class="topbar__dropdown-icon">👥</span>
            <span class="topbar__dropdown-label">Usuários</span>
          </a>
          <a class="topbar__dropdown-item" href="/admin/metrics.php?dash=<?= htmlspecialchars($current_dash, ENT_QUOTES, 'UTF-8') ?>">
            <span class="topbar__dropdown-icon">🧮</span>
            <span class="topbar__dropdown-label">Métricas</span>
          </a>
          <a class="topbar__dropdown-item" href="/admin/comunicados.php">
            <span class="topbar__dropdown-icon">📢</span>
            <span class="topbar__dropdown-label">Comunicados</span>
          </a>
          <a class="topbar__dropdown-item" href="/admin/rh.php">
            <span class="topbar__dropdown-icon">🧑‍💼</span>
            <span class="topbar__dropdown-label">RH</span>
          </a>
        </div>
      </div>
    <?php endif; ?>

    <!-- Dashboards (dropdown com links para páginas separadas) -->
    <div class="topbar__dropdown" style="margin-left:8px;">
      <a class="topbar__dropdown-trigger" href="#" id="dashTrigger">Dashboards</a>
      <div class="topbar__dropdown-menu" id="dashMenu">
        <a class="topbar__dropdown-item" href="/dashboard.php">
          <span class="topbar__dropdown-icon">📊</span>
          <span class="topbar__dropdown-label">Faturamento</span>
        </a>
        <a class="topbar__dropdown-item" href="/financeiro.php">
          <span class="topbar__dropdown-icon">💰</span>
          <span class="topbar__dropdown-label">Financeiro</span>
        </a>
        <?php if (isset($dashboards) && is_array($dashboards)): ?>
          <?php foreach ($dashboards as $dash): ?>
            <?php
              $slug = isset($dash['slug']) ? (string)$dash['slug'] : '';
              if ($slug === '' || $slug === 'executivo' || $slug === 'financeiro') continue;
              $name = isset($dash['name']) ? (string)$dash['name'] : $slug;
              $icon = isset($dash['icon']) ? (string)$dash['icon'] : '📊';
            ?>
            <a class="topbar__dropdown-item" href="/<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>.php">
              <span class="topbar__dropdown-icon"><?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?></span>
              <span class="topbar__dropdown-label"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Popper Coins (link direto separado) -->
    <a class="topbar__nav-trigger" href="/coins.php" style="margin-left:8px;">
  <span aria-hidden="true">🪙</span>
  Popper Coins
</a>
  </div>

  <a class="link" href="/logout.php">Sair</a>
</header>

<style>
/* Link direto na topbar (Popper Coins) */
.topbar__navlink {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 10px;
  text-decoration: none;
  font-weight: 800;
  font-size: 13px;
  color: rgba(255, 255, 255, .92);
  border: 1px solid rgba(255, 255, 255, .14);
  background: rgba(255, 255, 255, .10);
  transition: .15s ease;
}
.topbar__navlink:hover {
  background: rgba(255, 255, 255, .16);
  border-color: rgba(255, 255, 255, .20);
}
</style>