<?php
declare(strict_types=1);

/**
 * /app/header.php
 * Header padrão com perfil (foto/ícone + dropdown).
 */

if (!function_exists('current_user')) {
  require_once __DIR__ . '/auth.php';
}
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/notifications.php';

$u = $u ?? current_user();

$notifUnread = 0;
$notifItems = [];

if (is_array($u) && isset($u['id'])) {
  $uid = (int)$u['id'];
  $notifUnread = notifications_unread_count_for_user($u);
  $notifItems = notifications_latest_for_user($u, 5);
}

$userName = is_array($u) && isset($u['name']) && is_string($u['name']) && $u['name'] !== '' ? $u['name'] : 'usuário';
$userEmail = is_array($u) && isset($u['email']) && is_string($u['email']) ? $u['email'] : '';
$userRole = is_array($u) && isset($u['role']) && is_string($u['role']) ? $u['role'] : '';
$userSetor = is_array($u) && isset($u['setor']) && is_string($u['setor']) ? $u['setor'] : '';
$userHierarquia = is_array($u) && isset($u['hierarquia']) && is_string($u['hierarquia']) ? $u['hierarquia'] : '';

$current_dash = $current_dash ?? 'executivo';
$activePage = $activePage ?? '';

// Foto (profile_photo_path)
$avatarUrl = '';
if (is_array($u) && isset($u['profile_photo_path']) && is_string($u['profile_photo_path']) && $u['profile_photo_path'] !== '') {
  $avatarUrl = trim($u['profile_photo_path']);
}

// Itens do Admin conforme permissões
$adminItems = [];
if (is_array($u)) {
  foreach (PERMISSION_CATALOG as $perm => $meta) {
    if (user_can($perm, $u)) {
      $adminItems[] = [
        'url' => (string)($meta['url'] ?? '#'),
        'label' => (string)($meta['label'] ?? $perm),
        'icon' => (string)($meta['icon'] ?? '⚙️'),
      ];
    }
  }
}

// Iniciais para fallback
$initials = 'U';
if ($userName !== '') {
  $parts = preg_split('/\s+/', trim($userName));
  if (is_array($parts) && count($parts) > 0) {
    $first = strtoupper(substr((string)$parts[0], 0, 1));
    $last = (count($parts) > 1) ? strtoupper(substr((string)$parts[count($parts)-1], 0, 1)) : '';
    $initials = $first . $last;
  }
}

// Saudação dinâmica (SP)
date_default_timezone_set('America/Sao_Paulo');

$h = (int)date('H');
if ($h >= 5 && $h < 12) {
  $greeting = 'Ótimo Dia';
} elseif ($h >= 12 && $h < 18) {
  $greeting = 'Ótima Tarde';
} else {
  $greeting = 'Ótima Noite';
}
?>
<header class="topbar topbar--site">
  <div class="topbar__left">
    <a class="brand" href="/index.php" style="text-decoration:none;">
      <?= htmlspecialchars((string)APP_NAME, ENT_QUOTES, 'UTF-8') ?>
    </a>

    <a class="link<?= ($activePage === 'home' ? ' link--active' : '') ?>" href="/index.php" style="margin-left:12px;">
      Início
    </a>

    <?php if (!empty($adminItems)): ?>
      <div class="topbar__dropdown" style="margin-left:12px;">
        <a class="topbar__dropdown-trigger" href="#" id="adminTrigger" aria-haspopup="true" aria-expanded="false">Administração</a>
        <div class="topbar__dropdown-menu" id="adminMenu" role="menu">
          <?php foreach ($adminItems as $item): ?>
            <a class="topbar__dropdown-item" href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>">
              <span class="topbar__dropdown-icon"><?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?></span>
              <span class="topbar__dropdown-label"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- DASHBOARD (com submenus Comercial e Financeiro) -->
    <div class="topbar__dropdown" style="margin-left:8px;" id="dashWrap">
      <a class="topbar__dropdown-trigger" href="#" id="dashTrigger" aria-haspopup="true" aria-expanded="false">Dashboard</a>

      <div class="topbar__dropdown-menu" id="dashMenu" role="menu" aria-label="Dashboard">
        <!-- Grupo: Comercial -->
        <div class="topbar__dropdown-group" data-submenu>
          <button class="topbar__dropdown-item topbar__dropdown-item--group" type="button" aria-haspopup="true" aria-expanded="false">
            <span class="topbar__dropdown-icon">📈</span>
            <span class="topbar__dropdown-label">Comercial</span>
            <span class="topbar__dropdown-caret" aria-hidden="true">›</span>
          </button>

          <div class="topbar__dropdown-submenu" role="menu" aria-label="Comercial">
            <a class="topbar__dropdown-item" href="/dashboard.php">
              <span class="topbar__dropdown-icon">📊</span>
              <span class="topbar__dropdown-label">Faturamento</span>
            </a>

            <a class="topbar__dropdown-item" href="/dashboard-executivo.php">
              <span class="topbar__dropdown-icon">📌</span>
              <span class="topbar__dropdown-label">Executivo</span>
            </a>

            <a class="topbar__dropdown-item" href="/insight_comercial.php">
              <span class="topbar__dropdown-icon">💡</span>
              <span class="topbar__dropdown-label">Insight</span>
            </a>
            <a class="topbar__dropdown-item" href="/clientes.php">
              <span class="topbar__dropdown-icon">💡</span>
              <span class="topbar__dropdown-label">Clientes</span>
            </a>

            <?php if (isset($dashboards) && is_array($dashboards)): ?>
              <?php foreach ($dashboards as $dash): ?>
                <?php
                  $slug = isset($dash['slug']) ? (string)$dash['slug'] : '';
                  if ($slug === '' || $slug === 'executivo' || $slug === 'financeiro') continue;

                  // evita duplicar fixos
                  if (in_array($slug, ['dashboard', 'dashboard-executivo', 'insight_comercial', 'insight-comercial'], true)) continue;

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

        <!-- Grupo: Financeiro -->
        <div class="topbar__dropdown-group" data-submenu>
          <button class="topbar__dropdown-item topbar__dropdown-item--group" type="button" aria-haspopup="true" aria-expanded="false">
            <span class="topbar__dropdown-icon">💰</span>
            <span class="topbar__dropdown-label">Financeiro</span>
            <span class="topbar__dropdown-caret" aria-hidden="true">›</span>
          </button>

          <div class="topbar__dropdown-submenu" role="menu" aria-label="Financeiro">
            <a class="topbar__dropdown-item" href="/admin/dashboardContasP.php">
              <span class="topbar__dropdown-icon">🧾</span>
              <span class="topbar__dropdown-label">Contas a Pagar</span>
            </a>
          </div>
        </div>
         <div class="topbar__dropdown-group" data-submenu>
          <button class="topbar__dropdown-item topbar__dropdown-item--group" type="button" aria-haspopup="true" aria-expanded="false">
            <span class="topbar__dropdown-icon">💰</span>
            <span class="topbar__dropdown-label">Comex</span>
            <span class="topbar__dropdown-caret" aria-hidden="true">›</span>
          </button>

          <div class="topbar__dropdown-submenu" role="menu" aria-label="Financeiro">
            <a class="topbar__dropdown-item" href="/importacoes.php">
              <span class="topbar__dropdown-icon">🧾</span>
              <span class="topbar__dropdown-label">Importações</span>
            </a>
          </div>
        </div>
      </div>
    </div>

    <div class="topbar__dropdown" style="margin-left:8px;">
      <a class="topbar__dropdown-trigger<?= ($activePage === 'coins' ? ' link--active' : '') ?>" href="/coins.php" id="coinsTrigger" aria-haspopup="true" aria-expanded="false">Popper Coins</a>
      <div class="topbar__dropdown-menu" id="coinsMenu" role="menu">
        <a class="topbar__dropdown-item" href="/coins.php">
          <span class="topbar__dropdown-label">Meus Poppercoins</span>
        </a>
        <a class="topbar__dropdown-item" href="/coins_resgatar.php">
          <span class="topbar__dropdown-label">Resgatar</span>
        </a>
        <a class="topbar__dropdown-item" href="/ranking.php">
          <span class="topbar__dropdown-label">Ranking</span>
        </a>
      </div>
    </div>
  </div>

  <div class="topbar__right">
    <div class="notif" id="notifWrap">
      <button class="notif__btn" type="button" id="notifTrigger" aria-haspopup="true" aria-expanded="false" title="Notificações">
        <span class="notif__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
            <path fill="currentColor" d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 1 0-14 0v5l-2 2v1h18v-1l-2-2Z" />
          </svg>
        </span>

        <?php if ($notifUnread > 0): ?>
          <span class="notif__badge"><?= $notifUnread > 99 ? '99+' : (int)$notifUnread ?></span>
        <?php endif; ?>
      </button>

      <div class="notif__menu" id="notifMenu" role="menu" aria-label="Notificações">
        <div class="notif__header">
          <div class="notif__title">Notificações</div>
          <button type="button" class="notif__markall" id="notifMarkAll">Marcar todas</button>
        </div>

        <?php if (!$notifItems): ?>
          <div class="notif__empty">Sem notificações.</div>
        <?php else: ?>
          <?php foreach ($notifItems as $n): ?>
            <?php
              $unread = ((int)($n['is_read'] ?? 0) === 0);

              $href = trim((string)($n['link'] ?? ''));

              if ($href === '' || $href === '#') {
                $href = '#';
                $isClickable = false;
              } else {
                $isClickable = true;

                if (preg_match('/^https?:\/\//i', $href)) {
                  // externo
                } else {
                  $href = preg_replace('#^(\./|\.\./)+#', '', $href);

                  if (strpos($href, '/admin/') === 0) {
                    // ok
                  } elseif (strpos($href, '/') === 0) {
                    $href = '/admin' . $href;
                  } else {
                    $href = '/admin/' . $href;
                  }
                }
              }
            ?>
            <a class="notif__item<?= $unread ? ' is-unread' : '' ?><?= $isClickable ? '' : ' is-disabled' ?>"
               href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
               <?= $isClickable ? '' : 'aria-disabled="true" tabindex="-1" onclick="return false;"' ?>
               data-id="<?= (int)($n['id'] ?? 0) ?>">
              <div class="notif__item-title"><?= htmlspecialchars((string)($n['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
              <?php if (!empty($n['message'])): ?>
                <div class="notif__item-msg"><?= htmlspecialchars((string)$n['message'], ENT_QUOTES, 'UTF-8') ?></div>
              <?php endif; ?>
              <div class="notif__item-date"><?= htmlspecialchars((string)($n['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="topbar__greeting" aria-label="Saudação">
      <?= htmlspecialchars($greeting, ENT_QUOTES, 'UTF-8') ?><span class="topbar__greeting-name">, <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>!</span>
    </div>

    <div class="profile" id="profileWrap">
      <button class="profile__btn" type="button" id="profileTrigger" aria-haspopup="true" aria-expanded="false">
        <?php if ($avatarUrl !== ''): ?>
          <img class="profile__avatar" src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Foto de perfil" />
        <?php else: ?>
          <span class="profile__fallback" aria-hidden="true">
            <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
          </span>
        <?php endif; ?>
      </button>

      <div class="profile__menu" id="profileMenu" role="menu" aria-label="Menu do usuário">
        <div class="profile__header">
          <div class="profile__name"><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></div>
          <?php if ($userEmail !== ''): ?>
            <div class="profile__email"><?= htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>
        </div>

        <a class="profile__item" href="/me.php" role="menuitem">Meus dados</a>
        <a class="profile__item profile__item--danger" href="/logout.php" role="menuitem">Sair</a>
      </div>
    </div>
  </div>
</header>