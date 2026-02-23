(function () {
  'use strict';

  const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
  const pct0 = new Intl.NumberFormat('pt-BR', { style: 'percent', maximumFractionDigits: 0 });

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

            // posição padrão: um pouco acima da barra
            let y = bar.y - 10; // aumentei para -10 para mais espaço

            // impede o label de “invadir” o topo do chartArea (aumentei para +20)
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
          layout: { padding: { top: 32 } }, // aumentei para 32
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
            y: {
              beginAtZero: true,
              grace: '20%', // aumentei para 20%
              ticks: { callback: (v) => brl.format(v) }
            }
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
          layout: { padding: { top: 32 } }, // aumentei para 32
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
            y: {
              beginAtZero: true,
              grace: '20%', // aumentei para 20%
              ticks: { callback: (v) => brl.format(v) }
            }
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
            { label: `Ritmo (R$/dia) — ${ref.mesAno}`, data: [0, 0], backgroundColor: ['rgba(245,158,11,.85)', 'rgba(22,163,74,.85)'], borderRadius: 10 }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: false,
          layout: { padding: { top: 32 } }, // aumentei para 32
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
            y: {
              beginAtZero: true,
              grace: '20%', // aumentei para 20%
              ticks: { callback: (v) => brl.format(v) }
            }
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

    setText('kpi-meta-trend', `Atualizado: ${updatedAt}`);
    setText('kpi-ano-trend', `Atualizado: ${updatedAt}`);
    setText('kpi-ritmo-trend', `Atualizado: ${updatedAt}`);
    setText('kpi-deveria-trend', `Atualizado: ${updatedAt}`);
    setText('kpi-dias-trend', `Atualizado: ${updatedAt}`);

    // --- Novo 6º card: Projeção de fechamento do mês
    setText('kpi-projecao-mes', brl.format(num(v.fechar_em)));
    setText('kpi-projecao-mes-trend', `Proj: ${pct0.format(num(v.equivale_pct))} • Atualizado: ${updatedAt}`);

    ensureCharts(updatedAt);

    chartProgressMonth.data.datasets[0].data = [num(v.realizado_ate_hoje)];
    chartProgressMonth.data.datasets[1].data = [num(v.meta_mes)];
    chartProgressMonth.update('none');

    chartProgressYear.data.datasets[0].data = [num(v.realizado_ano_acum)];
    chartProgressYear.data.datasets[1].data = [num(v.meta_ano)];
    chartProgressYear.update('none');

    chartPace.data.datasets[0].label = `Ritmo (R$/dia) — ${ref.mesAno}`;
    chartPace.data.datasets[0].data = [num(v.meta_dia_util), num(v.realizado_dia_util)];
    chartPace.update('none');

    const tbody = document.getElementById('topProductsTable')?.querySelector('tbody');
    if (tbody) {
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
      rows.forEach(([k, val]) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${k}</td><td class="right">${val}</td>`;
        tbody.appendChild(tr);
      });
    }
  }

  // --- Nova função: renderizar dados diários
  function renderDailyToday(basePayload, dailyPayload) {
    const v = basePayload.values || {};
    const updatedAt = basePayload.updated_at || '—';

    const t = (dailyPayload && dailyPayload.today) ? dailyPayload.today : null;
    const faturadoDia = num(t ? Number(t.faturado_dia) : 0);
    const agendadoHoje = num(t ? Number(t.agendado_hoje) : 0);

    const totalHoje = faturadoDia + agendadoHoje;

    const metaDia = (num(v.dias_uteis_trabalhar) > 0)
      ? (num(v.meta_mes) / num(v.dias_uteis_trabalhar))
      : 0;

    const gapHoje = totalHoje - metaDia;

    setText('kpi-hoje-total', brl.format(totalHoje));
    setText('kpi-hoje-fat', brl.format(faturadoDia));
    setText('kpi-hoje-ag', brl.format(agendadoHoje));
    setText('kpi-hoje-trend', `Atualizado: ${updatedAt}`);

    setText('kpi-meta-hoje', brl.format(metaDia));
    setText('kpi-gap-hoje', brl.format(gapHoje));
    setText('kpi-meta-hoje-trend', gapHoje >= 0 ? 'Acima da meta do dia' : 'Abaixo da meta do dia');
  }

  async function refresh() {
    const dash = (window.DASH_CURRENT || 'executivo');

    const res = await fetch(`/api/dashboard-data.php?dash=${encodeURIComponent(dash)}`, { cache: 'no-store' });
    const payload = await res.json();
    renderFromValues(payload);

    // Diário - hoje
    const r2 = await fetch(`/api/dashboard-daily-today.php?dash=${encodeURIComponent(dash)}`, { cache: 'no-store' });
    const daily = await r2.json();
    renderDailyToday(payload, daily);
  }

  refresh();
  setInterval(refresh, 5000);
})();