(function () {
  'use strict';

  const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
  const pct0 = new Intl.NumberFormat('pt-BR', { style: 'percent', maximumFractionDigits: 0 });
function clampMin(v, min) { return v < min ? min : v; }
function int(v) { v = Number(v); return Number.isFinite(v) ? Math.trunc(v) : 0; }
  function setText(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
  }

  function num(v) { return (typeof v === 'number' && isFinite(v)) ? v : 0; }

  function refMesAnoFromUpdatedAt(updatedAt) {
    try {
      if (typeof updatedAt === 'string' && updatedAt.includes('/')) {
        const parts = updatedAt.split(',');
        const dPart = parts[0] || '';
        const d = dPart.trim().split('/').map(x => parseInt(x, 10));
        const dd = d[0], mm = d[1], yyyy = d[2];
        if (dd && mm && yyyy) return { month: mm - 1, year: yyyy };
      }
    } catch (e) { }
    const d = new Date();
    return { month: d.getMonth(), year: d.getFullYear() };
  }

  function monthLabel(m) {
    const meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    return meses[m] || '';
  }

  function buildRefLabels(updatedAt) {
    const ref = refMesAnoFromUpdatedAt(updatedAt);
    return { mesAno: `${monthLabel(ref.month)} / ${ref.year}`, ano: String(ref.year) };
  }

  let chartProgressMonth = null;
  let chartProgressYear = null;
  let chartPace = null;

  function makeValueLabelPlugin() {
    return {
      id: 'valueLabelPlugin',
      afterDatasetsDraw(chart) {
        const { ctx, chartArea } = chart;
        if (!chartArea) return;

        ctx.save();
        ctx.font = '12px system-ui, -apple-system, Segoe UI, Roboto, Arial';
        ctx.fillStyle = 'rgba(15,23,42,.90)';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'bottom';

        chart.data.datasets.forEach((dataset, datasetIndex) => {
          const meta = chart.getDatasetMeta(datasetIndex);
          if (meta.hidden) return;

          meta.data.forEach((bar, i) => {
            const val = dataset.data[i];
            if (val == null) return;

            const label = brl.format(val);

            let y = bar.y - 10;
            const minY = chartArea.top + 20;
            if (y < minY) y = minY;

            ctx.fillText(label, bar.x, y);
          });
        });

        ctx.restore();
      }
    };
  }

  function ensureCharts(updatedAt) {
    const ref = buildRefLabels(updatedAt);
    const valueLabelPlugin = makeValueLabelPlugin();

    if (!chartProgressMonth) {
      chartProgressMonth = new Chart(document.getElementById('salesExpensesChartMonth'), {
        type: 'bar',
        data: {
          labels: ['Mês'],
          datasets: [
            { label: 'Realizado', data: [0], backgroundColor: 'rgba(92, 44, 140, 0.85)', borderRadius: 10 },
            { label: 'Meta', data: [0], backgroundColor: 'rgba(172, 204, 54, 0.75)', borderRadius: 10 }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: false,
          layout: { padding: { top: 32 } },
          plugins: {
            legend: { display: true, position: 'bottom', labels: { usePointStyle: true } },
            tooltip: {
              callbacks: {
                title: () => `Referência: ${ref.mesAno}`,
                label: (ctx) => `${ctx.dataset.label}: ${brl.format(ctx.raw)}`
              }
            }
          },
          scales: {
            y: { beginAtZero: true, grace: '20%', ticks: { callback: (v) => brl.format(v) } }
          }
        },
        plugins: [valueLabelPlugin]
      });
    }

    if (!chartProgressYear) {
      chartProgressYear = new Chart(document.getElementById('salesExpensesChartYear'), {
        type: 'bar',
        data: {
          labels: ['Ano'],
          datasets: [
            { label: 'Realizado', data: [0], backgroundColor: 'rgba(92, 44, 140, 0.85)', borderRadius: 10 },
            { label: 'Meta', data: [0], backgroundColor: 'rgba(172, 204, 54, 0.75)', borderRadius: 10 }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: false,
          layout: { padding: { top: 32 } },
          plugins: {
            legend: { display: true, position: 'bottom', labels: { usePointStyle: true } },
            tooltip: {
              callbacks: {
                title: () => `Referência: ${ref.ano}`,
                label: (ctx) => `${ctx.dataset.label}: ${brl.format(ctx.raw)}`
              }
            }
          },
          scales: {
            y: { beginAtZero: true, grace: '20%', ticks: { callback: (v) => brl.format(v) } }
          }
        },
        plugins: [valueLabelPlugin]
      });
    }

    if (!chartPace) {
      chartPace = new Chart(document.getElementById('salesBySectorChart'), {
        type: 'bar',
        data: {
          labels: ['Meta/dia útil', 'Realizado/dia útil'],
          datasets: [
            {
              label: `Ritmo (R$/dia) — ${ref.mesAno}`,
              data: [0, 0],
              backgroundColor: ['rgba(245,158,11,.85)', 'rgba(22,163,74,.85)'],
              borderRadius: 10
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: false,
          layout: { padding: { top: 32 } },
          plugins: {
            legend: { display: true, position: 'bottom', labels: { usePointStyle: true } },
            tooltip: {
              callbacks: {
                title: () => `Referência: ${ref.mesAno}`,
                label: (ctx) => `${ctx.label}: ${brl.format(ctx.raw)}`
              }
            }
          },
          scales: {
            y: { beginAtZero: true, grace: '20%', ticks: { callback: (v) => brl.format(v) } }
          }
        },
        plugins: [valueLabelPlugin]
      });
    }
  }

  function renderFromValues(payload) {
    const v = payload.values || {};
    const updatedAt = payload.updated_at || '—';
    const ref = buildRefLabels(updatedAt);

    setText('titleProgressMonth', `Progresso (Mês) — ${ref.mesAno}`);
    setText('titleProgressYear', `Progresso (Ano) — ${ref.ano}`);
    setText('titlePace', `Ritmo (Dia útil) — ${ref.mesAno}`);

    // Mês
    setText('kpi-meta-mes', brl.format(num(v.meta_mes)));
    setText('kpi-realizado-mes', brl.format(num(v.realizado_ate_hoje)));
    setText('kpi-falta-mes', brl.format(num(v.falta_meta_mes)));
    setText('kpi-mes-atual', brl.format(num(v.realizado_ate_hoje)));
    // Ano
    setText('kpi-meta-ano', brl.format(num(v.meta_ano)));
    setText('kpi-realizado-ano', brl.format(num(v.realizado_ano_acum)));
    setText('kpi-falta-ano', brl.format(num(v.falta_meta_ano)));

    // Ritmo (mantém ids existentes)
    setText('kpi-ritmo', brl.format(num(v.realizado_dia_util)));

    // ✅ AGORA: "kpi-meta-dia" vira o número principal (meta dinâmica)
    // (antes era meta_dia_util; agora será a_faturar_dia_util)
    setText('kpi-meta-dia', brl.format(num(v.a_faturar_dia_util)));

    // ✅ e "kpi-a-faturar" passa a mostrar a meta fixa (teórica), em menor destaque (mesmo id)
    setText('kpi-a-faturar', brl.format(num(v.meta_dia_util)));

    // Deveria / Atingimento
    setText('kpi-deveria', brl.format(num(v.deveria_ate_hoje)));
    setText('kpi-atingimento', pct0.format(num(v.atingimento_mes_pct)));

    // Dias
    setText('kpi-dias', `${num(v.dias_uteis_trabalhados)} / ${num(v.dias_uteis_trabalhar)}`);
    setText('kpi-produtividade', pct0.format(num(v.realizado_dia_util_pct)));

    // Trends
    setText('kpi-meta-trend', `Atualizado: ${updatedAt}`);
    setText('kpi-ano-trend', `Atualizado: ${updatedAt}`);
    setText('kpi-ritmo-trend', `Atualizado: ${updatedAt}`);
    setText('kpi-deveria-trend', `Atualizado: ${updatedAt}`);
    setText('kpi-dias-trend', `Atualizado: ${updatedAt}`);

    // Projeção
    setText('kpi-projecao-mes', brl.format(num(v.fechar_em)));
    setText('kpi-projecao-mes-trend', `Proj: ${pct0.format(num(v.equivale_pct))} • Atualizado: ${updatedAt}`);

    ensureCharts(updatedAt);

    chartProgressMonth.data.datasets[0].data = [num(v.realizado_ate_hoje)];
    chartProgressMonth.data.datasets[1].data = [num(v.meta_mes)];
    chartProgressMonth.update('none');

    chartProgressYear.data.datasets[0].data = [num(v.realizado_ano_acum)];
    chartProgressYear.data.datasets[1].data = [num(v.meta_ano)];
    chartProgressYear.update('none');

    // ⚠️ Mantém o gráfico como estava (meta fixa x realizado)
    chartPace.data.datasets[0].label = `Ritmo (R$/dia) — ${ref.mesAno}`;
    chartPace.data.datasets[0].data = [num(v.meta_dia_util), num(v.realizado_dia_util)];
    chartPace.update('none');

    const tbody = document.getElementById('topProductsTable')?.querySelector('tbody');
    if (tbody) {
      const diasRest = Math.max(0, num(v.dias_uteis_trabalhar) - num(v.dias_uteis_trabalhados));
      const rows = [
        ['Meta do ano', brl.format(num(v.meta_ano))],
        ['Falta para atingir a meta do ano', brl.format(num(v.falta_meta_ano))],
        ['Meta do mês', brl.format(num(v.meta_mes))],
        ['Realizado até hoje (mês)', brl.format(num(v.realizado_ate_hoje))],
        ['Falta para atingir a meta do mês', brl.format(num(v.falta_meta_mes))],
        ['Quanto já atingimos (mês)', pct0.format(num(v.atingimento_mes_pct))],
        ['Quanto deveria ter até hoje', brl.format(num(v.deveria_ate_hoje))],
        ['Realizado anual acumulado até hoje', brl.format(num(v.realizado_ano_acum))],

        // ✅ Destaque conceitual no detalhamento
        ['Meta fixa por dia útil (teórica)', brl.format(num(v.meta_dia_util))],
        ['Meta necessária por dia útil (dinâmica)', brl.format(num(v.a_faturar_dia_util))],
        ['Dias úteis restantes', String(diasRest)],

        ['Realizado por dia útil', brl.format(num(v.realizado_dia_util))],
        ['Realizado por dia útil (%)', pct0.format(num(v.realizado_dia_util_pct))],
        ['Dias úteis a trabalhar', String(num(v.dias_uteis_trabalhar))],
        ['Dias úteis trabalhados', String(num(v.dias_uteis_trabalhados))],
        ['Se continuar assim vamos bater a meta?', (v.vai_bater_meta ?? '—')],
        ['Se continuar assim vamos fechar em quanto?', brl.format(num(v.fechar_em))],
        ['Equivale a', pct0.format(num(v.equivale_pct))]
      ];

      tbody.innerHTML = '';
      rows.forEach(([k, val]) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${k}</td><td class="right">${val}</td>`;
        tbody.appendChild(tr);
      });
    }
  }

  // --- Diário (HOJE)
  function renderDailyToday(basePayload, dailyPayload) {
  const v = basePayload.values || {};
  const updatedAt = basePayload.updated_at || '—';

  // HOJE (vem do dashboard-data.php)
  const totalHoje = num(v.hoje_total);

  setText('kpi-hoje-total', brl.format(totalHoje));
  setText('kpi-hoje-fat', brl.format(totalHoje)); // por enquanto sem split
  setText('kpi-hoje-ag', brl.format(0));
  setText('kpi-hoje-trend', `Atualizado: ${updatedAt}`);

  // ---- META DO DIA (teórica) e GAP hoje ----
  const diasTotais = int(v.dias_uteis_trabalhar);
  const diasPassados = int(v.dias_uteis_trabalhados);
  const diasRestantes = clampMin(diasTotais - diasPassados, 0);

  const metaMes = num(v.meta_mes);
  const realizadoMes = num(v.realizado_ate_hoje);

  const metaDiaTeorica = (diasTotais > 0) ? (metaMes / diasTotais) : 0;
  const gapHoje = totalHoje - metaDiaTeorica;

  // Mantém seus campos atuais (se você ainda usa eles em algum card)
  setText('kpi-meta-hoje', brl.format(metaDiaTeorica));
  setText('kpi-gap-hoje', brl.format(gapHoje));
  setText('kpi-meta-hoje-trend', gapHoje >= 0 ? 'Acima da meta do dia' : 'Abaixo da meta do dia');

  // ---- META NECESSÁRIA POR DIA (DINÂMICA) ----
  const faltaMes = Math.max(0, metaMes - realizadoMes);
  const metaDiaDinamica = (diasRestantes > 0) ? (faltaMes / diasRestantes) : 0;

  // Card novo (principal)
  setText('metaDinamica', brl.format(metaDiaDinamica));

  // Subtexto (ex.: "Faltam 2 dias úteis • Restante no mês: R$ ...")
  const labelDias = diasRestantes === 1 ? 'dia útil' : 'dias úteis';
  setText('metaRestante', `Faltam ${diasRestantes} ${labelDias} • Restante no mês: ${brl.format(faltaMes)}`);

  // Linhas pequenas (mantém as infos que você pediu em letras menores)
  setText('metaTeorica', `Meta do dia (teórica): ${brl.format(metaDiaTeorica)} • ${gapHoje >= 0 ? 'Acima' : 'Abaixo'} da meta do dia`);
  setText('gapHoje', `Gap hoje: ${brl.format(gapHoje)}`);
}
  async function refresh(forceTotvs = false) {
    const dash = (window.DASH_CURRENT || 'executivo');
    const url = `/api/dashboard-data.php?dash=${encodeURIComponent(dash)}${forceTotvs ? '&force=1' : ''}`;

    const res = await fetch(url, { cache: 'no-store' });
    const payload = await res.json();

    renderFromValues(payload);
    renderDailyToday(payload); // ✅ HOJE vem do payload (totvs)
  }

/* =========================
   AUTO REFRESH DASHBOARD
   ========================= */

// ✅ 1) Carrega rápido usando cache (sem force)
refresh(false);

// ✅ 2) Atualiza em background a cada 10 min (força TOTVS)
setInterval(() => {
  refresh(true);
}, 10 * 60 * 1000);

  // ===== botão manual
  const btn = document.getElementById('btnForceTotvs');
  if (btn) {
    btn.addEventListener('click', async () => {
      const st = document.getElementById('forceStatus');

      btn.classList.add('is-loading');
      if (st) { st.textContent = 'Atualizando...'; st.className = 'is-loading'; }

      try {
        await refresh(true); // ignora cache
        if (st) { st.textContent = 'Atualizado'; st.className = 'is-ok'; }
      } catch (e) {
        if (st) { st.textContent = 'Falhou'; st.className = 'is-error'; }
      } finally {
        btn.classList.remove('is-loading');
      }
    });
  }
})();