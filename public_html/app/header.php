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

$u = $u ?? current_user();

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

// Saudação dinâmica
$h = (int)date('H');
$min = (int)date('i');
$minutes = ($h * 60) + $min;

if ($minutes >= 0 && $minutes <= (12 * 60)) {
  $greeting = 'Ótimo Dia';
} elseif ($minutes >= (12 * 60 + 1) && $minutes <= (18 * 60)) {
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
        <a class="topbar__dropdown-trigger" href="#" id="adminTrigger">Administração</a>
        <div class="topbar__dropdown-menu" id="adminMenu">
          <?php foreach ($adminItems as $item): ?>
            <a class="topbar__dropdown-item" href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>">
              <span class="topbar__dropdown-icon"><?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?></span>
              <span class="topbar__dropdown-label"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

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

    <!-- ✅ Popper Coins agora igual aos outros links -->
    <a class="link<?= ($activePage === 'coins' ? ' link--active' : '') ?>" href="/coins.php" style="margin-left:8px;">
      <span aria-hidden="true">🪙</span>
      Popper Coins
    </a>
  </div>

  <div class="topbar__right">
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

<style>
/* Saudação (mais fina / estilizada, sem negrito) */
.topbar__right{
  display:flex;
  align-items:center;
  gap:12px;
}
.topbar__greeting{
  color: rgba(255,255,255,.88);
  font-weight: 500;
  font-size: 13px;
  letter-spacing: .2px;
  white-space: nowrap;
}
.topbar__greeting-name{
  color: rgba(255,255,255,.95);
  font-weight: 400;
}
@media (max-width: 720px){
  .topbar__greeting{ display:none; }
}

/* Perfil */
.profile{position:relative;margin-left:10px;}
.profile__btn{
  border:0;
  background:transparent;
  padding:0;
  cursor:pointer;
  display:inline-flex;
  align-items:center;
}
.profile__avatar{
  width:38px;height:38px;border-radius:999px;object-fit:cover;
  border:1px solid rgba(255,255,255,.22);
}
.profile__fallback{
  width:38px;height:38px;border-radius:999px;
  display:inline-flex;align-items:center;justify-content:center;
  font-weight:900;font-size:12px;letter-spacing:.5px;
  color:rgba(255,255,255,.92);
  border:1px solid rgba(255,255,255,.22);
  background:rgba(255,255,255,.10);
}
.profile__menu{
  position:absolute;right:0;top:46px;
  min-width:240px;
  background:#0f172a;
  border:1px solid rgba(255,255,255,.14);
  border-radius:14px;
  box-shadow:0 12px 28px rgba(0,0,0,.35);
  padding:8px;
  display:none;
  z-index:9999;
}
.profile__header{
  padding:10px 10px 8px 10px;
  border-bottom:1px solid rgba(255,255,255,.12);
  margin-bottom:6px;
}
.profile__name{font-weight:900;color:rgba(255,255,255,.95);font-size:14px;}
.profile__email{opacity:.8;font-size:12px;margin-top:2px;color:rgba(255,255,255,.85);}
.profile__item{
  display:flex;
  padding:10px 10px;
  border-radius:10px;
  text-decoration:none;
  color:rgba(255,255,255,.92);
  font-weight:700;
  font-size:13px;
}
.profile__item:hover{background:rgba(255,255,255,.10);}
.profile__item--danger{color:#ffb4b4;}
.profile__item--danger:hover{background:rgba(255,80,80,.14);}

/* Abre no hover (desktop) */
.profile:hover .profile__menu{display:block;}
/* Abre no foco (teclado) */
.profile:focus-within .profile__menu{display:block;}
</style>

<script>
// Dropdown do perfil (abre/fecha no toque mobile)
(function(){
  var trigger = document.getElementById('profileTrigger');
  var menu = document.getElementById('profileMenu');
  if (!trigger || !menu) return;

  document.addEventListener('click', function(e){
    if (!trigger.contains(e.target) && !menu.contains(e.target)) {
      menu.style.display = 'none';
      trigger.setAttribute('aria-expanded', 'false');
    }
  });

  trigger.addEventListener('click', function(e){
    e.preventDefault();
    var isOpen = menu.style.display === 'block';
    menu.style.display = isOpen ? 'none' : 'block';
    trigger.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
  });
})();
</script>