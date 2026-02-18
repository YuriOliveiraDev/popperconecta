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
  <title>Dashboard — <?= htmlspecialchars(APP_NAME) ?></title>
  <link rel="stylesheet" href="/assets/css/users.css" />
  <link rel="stylesheet" href="/assets/css/dashboard.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</head>
<body class="page">
  <header class="topbar">
    <div class="topbar__left">
      <strong class="brand"><?= htmlspecialchars(APP_NAME) ?></strong>
      <span class="muted">Bem-vindo, <?= htmlspecialchars($u['name']) ?></span>
      <?php if (($u['role'] ?? '') === 'admin'): ?>
        <a class="link" href="/admin/users.php" style="margin-left:12px;">Usuários</a>
        <a class="link" href="/admin/metrics.php" style="margin-left:12px;">Métricas</a>
      <?php endif; ?>
    </div>
    <a class="link" href="/logout.php">Sair</a>
  </header>

  <main class="container">
    <h2>Início</h2>

    <section class="carousel">
      <div class="carousel__track" id="track">
        <article class="slide"><h3>Comunicado123</h3><p>Bem-vindo ao Popper Conecta.</p></article>
        <article class="slide"><h3>Indicador</h3><p>Card 2 (placeholder)</p></article>
        <article class="slide"><h3>Status</h3><p>TOTVS: futuro endpoint /api/kpis</p></article>
      </div>

      <div class="carousel__controls">
        <button type="button" id="prev">Anterior</button>
        <button type="button" id="next">Próximo</button>
      </div>
    </section>

    <h2 class="page-title">Métricas de Desempenho</h2>

    <section class="dashboard-grid">
      <!-- KPIs -->
      <div class="kpi-card">
        <span class="kpi-label">Meta do mês</span>
        <strong class="kpi-value" id="kpi-meta-mes">R$ 0,00</strong>
        <span class="kpi-trend" id="kpi-meta-trend"></span>
        <div class="kpi-detail">Realizado: <span id="kpi-realizado-mes">R$ 0,00</span> · Falta: <span id="kpi-falta-mes">R$ 0,00</span></div>
      </div>

      <div class="kpi-card">
        <span class="kpi-label">Meta do ano</span>
        <strong class="kpi-value" id="kpi-meta-ano">R$ 0,00</strong>
        <span class="kpi-trend" id="kpi-ano-trend"></span>
        <div class="kpi-detail">Realizado (acum.): <span id="kpi-realizado-ano">R$ 0,00</span> · Falta: <span id="kpi-falta-ano">R$ 0,00</span></div>
      </div>

      <div class="kpi-card">
        <span class="kpi-label">Ritmo de dia útil</span>
        <strong class="kpi-value" id="kpi-ritmo">R$ 0,00</strong>
        <span class="kpi-trend" id="kpi-ritmo-trend"></span>
        <div class="kpi-detail">Meta/dia útil: <span id="kpi-meta-dia">R$ 0,00</span> · A faturar/dia: <span id="kpi-a-faturar">R$ 0,00</span></div>
      </div>

      <div class="kpi-card">
        <span class="kpi-label">Deveria ter até hoje</span>
        <strong class="kpi-value" id="kpi-deveria">R$ 0,00</strong>
        <span class="kpi-trend" id="kpi-deveria-trend"></span>
        <div class="kpi-detail">Atingimento (mês): <span id="kpi-atingimento">0%</span></div>
      </div>

      <div class="kpi-card">
        <span class="kpi-label">Dias úteis</span>
        <strong class="kpi-value" id="kpi-dias">0 / 0</strong>
        <span class="kpi-trend" id="kpi-dias-trend"></span>
        <div class="kpi-detail">Produtividade (%): <span id="kpi-produtividade">0%</span></div>
      </div>

      <!-- Gráficos -->
      <div class="chart-card grid-col-span-2">
        <h3 class="chart-title" id="titleProgress">Progresso (Mês e Ano)</h3>
        <div class="chart-box">
          <canvas id="salesExpensesChart"></canvas>
        </div>
      </div>

      <div class="chart-card">
        <h3 class="chart-title" id="titlePace">Ritmo (Dia útil)</h3>
        <div class="chart-box">
          <canvas id="salesBySectorChart"></canvas>
        </div>
      </div>

      <!-- Tabela -->
      <div class="data-table-card grid-col-span-3">
        <h3 class="table-title">Detalhamento (indicador → valor)</h3>
        <div class="table-wrap">
          <table class="table" id="topProductsTable">
            <thead>
              <tr>
                <th>Indicador</th>
                <th class="right">Valor</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </section>

    <section class="expandable-section">
      <h2 class="page-title">Relatórios Detalhados</h2>
      <div class="card card--mt">
        <h3 class="card__title">Relatório de RH</h3>
        <p class="card__subtitle">Dados de funcionários, turnover, etc.</p>
        <button class="btn btn--primary btn--small">Ver Detalhes</button>
      </div>
      <div class="card card--mt">
        <h3 class="card__title">Relatório Financeiro</h3>
        <p class="card__subtitle">Fluxo de caixa, balanço, DRE.</p>
        <button class="btn btn--primary btn--small">Ver Detalhes</button>
      </div>
    </section>
  </main>

  <script src="/assets/js/carousel.js"></script>

  <script>
    const brl = new Intl.NumberFormat('pt-BR', { style:'currency', currency:'BRL' });
    const pct0 = new Intl.NumberFormat('pt-BR', { style:'percent', maximumFractionDigits: 0 });

    function setText(id, text){
      const el = document.getElementById(id);
      if (el) el.textContent = text;
    }

    function num(v){ return (typeof v === 'number' && isFinite(v)) ? v : 0; }

    function refMesAnoFromUpdatedAt(updatedAt){
      // updatedAt vem como "dd/mm/YYYY, HH:MM" (do seu endpoint)
      // se não vier, usa data do navegador
      try{
        if (typeof updatedAt === 'string' && updatedAt.includes('/')) {
          const [dPart] = updatedAt.split(',');
          const [dd, mm, yyyy] = dPart.trim().split('/').map(x => parseInt(x, 10));
          if (dd && mm && yyyy) return { month: mm - 1, year: yyyy };
        }
      } catch(e){}
      const d = new Date();
      return { month: d.getMonth(), year: d.getFullYear() };
    }

    function monthLabel(m){
      const meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
      return meses[m] || '';
    }

    function buildRefLabels(updatedAt){
      const ref = refMesAnoFromUpdatedAt(updatedAt);
      return {
        mesAno: `${monthLabel(ref.month)} / ${ref.year}`,
        ano: String(ref.year)
      };
    }

    // Charts (cria 1x e atualiza)
    let chartProgress = null;
    let chartPace = null;

    function makeValueLabelPlugin(){
      return {
        id: 'valueLabelPlugin',
        afterDatasetsDraw(chart){
          const { ctx } = chart;
          ctx.save();
          ctx.font = '12px system-ui, -apple-system, Segoe UI, Roboto, Arial';
          ctx.fillStyle = 'rgba(15,23,42,.85)';

          chart.data.datasets.forEach((dataset, datasetIndex) => {
            const meta = chart.getDatasetMeta(datasetIndex);
            if (meta.hidden) return;

            meta.data.forEach((bar, i) => {
              const val = dataset.data[i];
              if (val == null) return;

              const label = brl.format(val);
              const x = bar.x;
              const y = bar.y - 8;

              ctx.textAlign = 'center';
              ctx.fillText(label, x, y);
            });
          });

          ctx.restore();
        }
      };
    }

    function ensureCharts(updatedAt){
      const ref = buildRefLabels(updatedAt);
      const valueLabelPlugin = makeValueLabelPlugin();

      if (!chartProgress){
        chartProgress = new Chart(document.getElementById('salesExpensesChart'), {
          type: 'bar',
          data: {
            labels: ['Mês', 'Ano'],
            datasets: [
              { label: 'Realizado', data: [0,0], backgroundColor: 'rgba(92, 44, 140, 0.85)', borderRadius: 10 },
              { label: 'Meta', data: [0,0], backgroundColor: 'rgba(172, 204, 54, 0.75)', borderRadius: 10 }
            ]
          },
          options: {
            responsive:true,
            maintainAspectRatio:false,
            animation:false,
            plugins: {
              legend: {
                display: true,
                position: 'bottom',
                labels: { usePointStyle: true }
              },
              tooltip: {
                callbacks: {
                  title: (items) => {
                    const base = items?.[0]?.label || '';
                    return base === 'Mês' ? `Referência: ${ref.mesAno}` : `Referência: ${ref.ano}`;
                  },
                  label: (ctx) => `${ctx.dataset.label}: ${brl.format(ctx.raw)}`
                }
              }
            },
            scales: {
              y: { ticks: { callback: (v)=> brl.format(v) } }
            }
          },
          plugins: [valueLabelPlugin]
        });
      }

      if (!chartPace){
        chartPace = new Chart(document.getElementById('salesBySectorChart'), {
          type: 'bar',
          data: {
            labels: ['Meta/dia útil', 'Realizado/dia útil'],
            datasets: [
              {
                label: `Ritmo (R$/dia) — ${ref.mesAno}`,
                data: [0,0],
                backgroundColor: ['rgba(245,158,11,.85)','rgba(22,163,74,.85)'],
                borderRadius: 10
              }
            ]
          },
          options: {
            responsive:true,
            maintainAspectRatio:false,
            animation:false,
            plugins: {
              legend: {
                display: true,
                position: 'bottom',
                labels: { usePointStyle: true }
              },
              tooltip: {
                callbacks: {
                  title: () => `Referência: ${ref.mesAno}`,
                  label: (ctx) => `${ctx.label}: ${brl.format(ctx.raw)}`
                }
              }
            },
            scales: {
              y: { ticks: { callback: (v)=> brl.format(v) } }
            }
          },
          plugins: [valueLabelPlugin]
        });
      }
    }

    function renderFromValues(payload){
      const v = payload.values || {};
      const updatedAt = payload.updated_at || '—';
      const ref = buildRefLabels(updatedAt);

      // Títulos com referência
      setText('titleProgress', `Progresso (Mês e Ano) — ${ref.mesAno}`);
      setText('titlePace', `Ritmo (Dia útil) — ${ref.mesAno}`);

      // KPIs (alinhado com as chaves da API)
      setText('kpi-meta-mes', brl.format(num(v.meta_mes)));
      setText('kpi-realizado-mes', brl.format(num(v.realizado_ate_hoje)));
      setText('kpi-falta-mes', brl.format(num(v.falta_meta_mes)));

      setText('kpi-meta-ano', brl.format(num(v.meta_ano)));
      setText('kpi-realizado-ano', brl.format(num(v.realizado_ano_acum)));
      setText('kpi-falta-ano', brl.format(num(v.falta_meta_ano)));

      setText('kpi-ritmo', brl.format(num(v.realizado_dia_util)));
      setText('kpi-meta-dia', brl.format(num(v.meta_dia_util)));
      setText('kpi-a-faturar', brl.format(num(v.a_faturar_dia_util)));

      setText('kpi-deveria', brl.format(num(v.deveria_ate_hoje)));
      setText('kpi-atingimento', pct0.format(num(v.atingimento_mes_pct)));

      setText('kpi-dias', `${num(v.dias_uteis_trabalhados)} / ${num(v.dias_uteis_trabalhar)}`);
      setText('kpi-produtividade', pct0.format(num(v.realizado_dia_util_pct)));

      // Trends
      setText('kpi-meta-trend', `Atualizado: ${updatedAt}`);
      setText('kpi-ano-trend', `Atualizado: ${updatedAt}`);
      setText('kpi-ritmo-trend', `Atualizado: ${updatedAt}`);
      setText('kpi-deveria-trend', `Atualizado: ${updatedAt}`);
      setText('kpi-dias-trend', `Atualizado: ${updatedAt}`);

      // Charts
      ensureCharts(updatedAt);

      // Atualiza dados (com as chaves corretas)
      chartProgress.data.datasets[0].data = [num(v.realizado_ate_hoje), num(v.realizado_ano_acum)];
      chartProgress.data.datasets[1].data = [num(v.meta_mes), num(v.meta_ano)];
      chartProgress.update('none');

      // Atualiza label do dataset do ritmo com referência
      chartPace.data.datasets[0].label = `Ritmo (R$/dia) — ${ref.mesAno}`;
      chartPace.data.datasets[0].data = [num(v.meta_dia_util), num(v.realizado_dia_util)];
      chartPace.update('none');

      // Tabela (Indicador → Valor)
      const tbody = document.getElementById('topProductsTable')?.querySelector('tbody');
      if (tbody){
        const rows = [
          ['Meta do ano', brl.format(num(v.meta_ano))],
          ['Falta para atingir a meta do ano', brl.format(num(v.falta_meta_ano))],
          ['Meta do mês', brl.format(num(v.meta_mes))],
          ['Realizado até hoje (faturado + agendado)', brl.format(num(v.realizado_ate_hoje))],
          ['Falta para atingir a meta do mês', brl.format(num(v.falta_meta_mes))],
          ['Quanto já atingimos (faturado + agendado)', pct0.format(num(v.atingimento_mes_pct))],
          ['Quanto deveria ter até hoje', brl.format(num(v.deveria_ate_hoje))],
          ['Realizado anual acumulado até hoje', brl.format(num(v.realizado_ano_acum))],
          ['Meta por dia útil', brl.format(num(v.meta_dia_util))],
          ['A faturar por dia útil', brl.format(num(v.a_faturar_dia_util))],
          ['Realizado por dia útil', brl.format(num(v.realizado_dia_util))],
          ['Realizado por dia útil (%)', pct0.format(num(v.realizado_dia_util_pct))],
          ['Dias úteis a trabalhar', String(num(v.dias_uteis_trabalhar))],
          ['Dias úteis trabalhados', String(num(v.dias_uteis_trabalhados))],
          ['Se continuar assim vamos bater a meta?', (v.vai_bater_meta ?? '—')],
          ['Se continuar assim vamos fechar em quanto?', brl.format(num(v.fechar_em))],
          ['Equivale a', pct0.format(num(v.equivale_pct))]
        ];

        tbody.innerHTML = '';
        rows.forEach(([k,val]) => {
          const tr = document.createElement('tr');
          tr.innerHTML = `<td>${k}</td><td class="right">${val}</td>`;
          tbody.appendChild(tr);
        });
      }
    }

    async function refresh(){
      const res = await fetch('/api/dashboard-data.php', { cache: 'no-store' });
      const payload = await res.json();
      renderFromValues(payload);
    }

    refresh();
    setInterval(refresh, 5000);
  </script>
</body>
</html>