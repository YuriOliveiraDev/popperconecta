<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_admin();

$u = current_user();

// Dropdown "Dashboards" no header
try {
  $dashboards = db()
    ->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")
    ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}

$current_dash = 'executivo';
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>RH — <?= htmlspecialchars((string)APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/../assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../assets/css/dashboard.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/../assets/css/dropdowns.css') ?>" />

  <style>
    .rh-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
    @media(max-width:1000px){.rh-grid{grid-template-columns:1fr}}

    .rh-card{
      background:var(--card);
      border:1px solid rgba(15,23,42,.10);
      border-radius:14px;
      box-shadow:0 10px 28px rgba(15,23,42,.06);
      padding:18px;
      display:flex;
      flex-direction:column;
      gap:10px;
      transition:transform .15s ease, box-shadow .15s ease, border-color .15s ease;
    }
    .rh-card:hover{
      transform:translateY(-2px);
      box-shadow:0 14px 34px rgba(15,23,42,.10);
      border-color:rgba(92,44,140,.25);
    }

    .rh-head{display:flex;align-items:center;justify-content:space-between;gap:12px}
    .rh-badge{
      font-size:12px;
      font-weight:800;
      letter-spacing:.3px;
      padding:6px 10px;
      border-radius:999px;
      background:rgba(92,44,140,.10);
      color:rgba(92,44,140,1);
      border:1px solid rgba(92,44,140,.18);
      white-space:nowrap;
    }

    .rh-title{font-size:16px;font-weight:900;margin:0;color:var(--ink)}
    .rh-sub{margin:0;color:var(--muted);font-size:13px;line-height:1.35}
    .rh-actions{margin-top:6px;display:flex;gap:10px;flex-wrap:wrap}

    /* Botão moderno (não depende do btn--primary) */
    .btn-modern{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:8px;
      padding:10px 14px;
      border-radius:10px;
      border:1px solid rgba(15,23,42,.12);
      background:#fff;
      color:rgba(15,23,42,.92);
      text-decoration:none;
      font-weight:800;
      font-size:13px;
      transition:background .15s ease,border-color .15s ease,transform .05s ease,box-shadow .15s ease;
      box-shadow:0 6px 16px rgba(15,23,42,.06);
    }
    .btn-modern:hover{
      background:rgba(92,44,140,.06);
      border-color:rgba(92,44,140,.22);
      box-shadow:0 10px 20px rgba(15,23,42,.08);
    }
    .btn-modern:active{transform:translateY(1px)}
    .btn-modern__icon{font-size:14px;line-height:1}

    /* Variante roxa discreta */
    .btn-modern--accent{
      background:rgba(92,44,140,1);
      border-color:rgba(92,44,140,1);
      color:#fff;
      box-shadow:0 10px 22px rgba(92,44,140,.22);
    }
    .btn-modern--accent:hover{
      background:rgba(80,36,120,1);
      border-color:rgba(80,36,120,1);
    }
  </style>
</head>
<body class="page">

<?php require_once __DIR__ . '/../app/header.php'; ?>

<main class="container">
  <h2 class="page-title">RH</h2>

  <section class="rh-grid">
    <div class="rh-card">
      <div class="rh-head">
        <h3 class="rh-title">Popper Coins · Lançamentos</h3>
        <span class="rh-badge">Admin</span>
      </div>
      <p class="rh-sub">Adicionar, remover, ajustar e registrar lançamentos no saldo dos usuários.</p>
      <div class="rh-actions">
        <a class="btn-modern btn-modern--accent" href="/admin/rh_coins.php">
          <span class="btn-modern__icon">↗</span>
          Acessar
        </a>
      </div>
    </div>

    <div class="rh-card">
      <div class="rh-head">
        <h3 class="rh-title">Popper Coins · Recompensas</h3>
        <span class="rh-badge">Catálogo</span>
      </div>
      <p class="rh-sub">Cadastrar/editar recompensas e custos do catálogo (o que o usuário pode resgatar).</p>
      <div class="rh-actions">
        <a class="btn-modern btn-modern--accent" href="/admin/rh_rewards.php">
          <span class="btn-modern__icon">↗</span>
          Acessar
        </a>
      </div>
    </div>

    <div class="rh-card">
      <div class="rh-head">
        <h3 class="rh-title">Popper Coins · Aprovações</h3>
        <span class="rh-badge">Pendências</span>
      </div>
      <p class="rh-sub">Aprovar ou negar resgates pendentes. Ao aprovar, o sistema debita as coins.</p>
      <div class="rh-actions">
        <a class="btn-modern btn-modern--accent" href="/admin/rh_redemptions.php">
          <span class="btn-modern__icon">↗</span>
          Acessar
        </a>
      </div>
    </div>
  </section>
</main>

<script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/../assets/js/dropdowns.js') ?>"></script>
</body>
</html>