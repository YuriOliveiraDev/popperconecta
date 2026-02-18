<?php
declare(strict_types=1);
require_once __DIR__ . '/app/auth.php';
require_login();

$u = current_user();
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Financeiro — <?= htmlspecialchars(APP_NAME) ?></title>

  <link rel="stylesheet" href="/assets/css/users.css" />
  <link rel="stylesheet" href="/assets/css/dashboard.css" />
</head>
<body class="page">
  <header class="topbar">
    <div class="topbar__left">
      <strong class="brand"><?= htmlspecialchars(APP_NAME) ?></strong>
      <span class="muted">Financeiro · Bem-vindo, <?= htmlspecialchars($u['name']) ?></span>

      <a class="link" href="/dashboard.php" style="margin-left:12px;">Faturamento</a>
      <a class="link" href="/financeiro.php" style="margin-left:12px; font-weight:800;">Financeiro</a>

      <?php if (($u['role'] ?? '') === 'admin'): ?>
        <a class="link" href="/admin/users.php" style="margin-left:12px;">Usuários</a>
        <a class="link" href="/admin/metrics.php?dash=financeiro" style="margin-left:12px;">Métricas</a>
      <?php endif; ?>
    </div>

    <a class="link" href="/logout.php">Sair</a>
  </header>

  <main class="container">
    <h2 class="page-title">Financeiro (teste)</h2>

    <section class="dashboard-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
      <div class="kpi-card">
        <span class="kpi-label">Faturado no dia</span>
        <strong class="kpi-value" id="kpi-faturado-dia">R$ 0,00</strong>
        <span class="kpi-trend" id="kpi-fin-updated"></span>
      </div>

      <div class="kpi-card">
        <span class="kpi-label">Contas a pagar no dia</span>
        <strong class="kpi-value" id="kpi-contas-pagar-dia">R$ 0,00</strong>
        <span class="kpi-trend"></span>
      </div>

      <div class="data-table-card grid-col-span-2">
        <h3 class="table-title">Detalhamento</h3>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Indicador</th>
                <th class="right">Valor</th>
              </tr>
            </thead>
            <tbody id="finTableBody"></tbody>
          </table>
        </div>
      </div>
    </section>
  </main>

  <script>
    const brl = new Intl.NumberFormat('pt-BR', { style:'currency', currency:'BRL' });

    function setText(id, text){
      const el = document.getElementById(id);
      if (el) el.textContent = text;
    }
    function num(v){ return (typeof v === 'number' && isFinite(v)) ? v : 0; }

    function render(payload){
      const v = payload.values || {};
      const updatedAt = payload.updated_at || '—';

      const faturado = num(v.faturado_dia);
      const contas = num(v.contas_pagar_dia);

      setText('kpi-faturado-dia', brl.format(faturado));
      setText('kpi-contas-pagar-dia', brl.format(contas));
      setText('kpi-fin-updated', `Atualizado: ${updatedAt}`);

      const tbody = document.getElementById('finTableBody');
      if (tbody){
        tbody.innerHTML = '';
        const rows = [
          ['Faturado no dia', brl.format(faturado)],
          ['Contas a pagar no dia', brl.format(contas)],
        ];
        rows.forEach(([k,val]) => {
          const tr = document.createElement('tr');
          tr.innerHTML = `<td>${k}</td><td class="right">${val}</td>`;
          tbody.appendChild(tr);
        });
      }
    }

    async function refresh(){
      const res = await fetch('/api/dashboard-data.php?dash=financeiro', { cache: 'no-store' });
      const payload = await res.json();
      render(payload);
    }

    refresh();
    setInterval(refresh, 5000);
  </script>
</body>
</html>