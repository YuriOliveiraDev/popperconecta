<?php
declare(strict_types=1);
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_login();

$u = current_user();
$dashboards = db()->query("SELECT slug, name, icon FROM dashboards WHERE is_active=TRUE ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);

// Página atual (para o link de Métricas já abrir no setor padrão)
$current_dash = 'executivo';
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Início — <?= htmlspecialchars(APP_NAME) ?></title>
  <link rel="stylesheet" href="/assets/css/users.css" />
  <link rel="stylesheet" href="/assets/css/dashboard.css" />
</head>
<body class="page">
<header class="topbar">
  <div class="topbar__left">
    <strong class="brand"><?= htmlspecialchars(APP_NAME) ?></strong>
    <span class="muted">Bem-vindo, <?= htmlspecialchars($u['name']) ?></span>

    <?php if (($u['role'] ?? '') === 'admin'): ?>
      <div class="topbar__dropdown" style="margin-left:12px;">
        <a class="topbar__dropdown-trigger" href="#" id="adminTrigger">Administração</a>
        <div class="topbar__dropdown-menu" id="adminMenu">
          <a class="topbar__dropdown-item" href="/admin/users.php">
            <span class="topbar__dropdown-icon">👥</span>
            <span class="topbar__dropdown-label">Usuários</span>
          </a>
          <a class="topbar__dropdown-item" href="/admin/metrics.php?dash=<?= htmlspecialchars($current_dash) ?>">
            <span class="topbar__dropdown-icon">🧮</span>
            <span class="topbar__dropdown-label">Métricas</span>
          </a>
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

        <?php foreach ($dashboards as $dash): ?>
          <?php
            $slug = (string)$dash['slug'];
            if ($slug === 'executivo' || $slug === 'financeiro') continue;
          ?>
          <a class="topbar__dropdown-item" href="/<?= htmlspecialchars($slug) ?>.php">
            <span class="topbar__dropdown-icon"><?= htmlspecialchars($dash['icon'] ?? '📊') ?></span>
            <span class="topbar__dropdown-label"><?= htmlspecialchars($dash['name']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <a class="link" href="/logout.php">Sair</a>
</header>

<main class="container">
  <h2 class="page-title">Início</h2>
  <div class="card">
    <p class="muted" style="margin:0;">Página inicial (em branco por enquanto).</p>
  </div>
</main>

<script>
  function attachDropdown(triggerId, menuId){
    const trigger = document.getElementById(triggerId);
    const menu = document.getElementById(menuId);
    let t = null;
    if (!trigger || !menu) return;

    trigger.addEventListener('mouseenter', () => { clearTimeout(t); trigger.classList.add('is-open'); menu.classList.add('is-open'); });
    trigger.addEventListener('mouseleave', () => { t = setTimeout(() => { trigger.classList.remove('is-open'); menu.classList.remove('is-open'); }, 150); });
    menu.addEventListener('mouseenter', () => clearTimeout(t));
    menu.addEventListener('mouseleave', () => { t = setTimeout(() => { trigger.classList.remove('is-open'); menu.classList.remove('is-open'); }, 150); });

    document.addEventListener('click', (e) => {
      if (!trigger.contains(e.target) && !menu.contains(e.target)) {
        trigger.classList.remove('is-open'); menu.classList.remove('is-open');
      }
    });

    trigger.addEventListener('click', (e) => {
      e.preventDefault();
      trigger.classList.toggle('is-open');
      menu.classList.toggle('is-open');
    });
  }

  attachDropdown('adminTrigger', 'adminMenu');
  attachDropdown('dashTrigger', 'dashMenu');
</script>
</body>
</html>