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

  // ✅ Principal de HOJE = Faturado + IM (igual card Hoje / carousel)
  function hojePrincipal(v) {
    const fatHoje = num(v.hoje_faturado);
    const imHoje = num(v.hoje_im ?? v.hoje_agendado);
    return num(v.hoje_total) || (fatHoje + imHoje);
  }

  function refMesAnoFromUpdatedAt(updatedAt) {
    try {
      if (typeof updatedAt === 'string' && updatedAt.includes('/')) {
        const parts = updatedAt.split(',');
        const dPart = parts[0] || '';
        const d = dPart.trim().split('/').map(x => parseInt(x, 10));
        const dd = d[0], mm = d[1], yyyy = d[2];
        if (dd && mm && yyyy) return { month: mm - 1, year: yyyy };
      }
    } catch (e) { /* noop */ }
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

  // =========================
  // LOADER
  // =========================
  const LOADER_DELAY_MS = 120;
  const LOADER_MIN_MS = 350;

  let _loaderTimer = null;
  let _loaderShownAt = 0;

  function loaderOpen(title, sub) {
    const api = window.PopperLoading;
    if (!api || typeof api.show !== 'function') return;

    if (_loaderTimer) { clearTimeout(_loaderTimer); _loaderTimer = null; }
    _loaderShownAt = 0;

    _loaderTimer = setTimeout(() => {
      _loaderTimer = null;
      _loaderShownAt = Date.now();
      api.show(title || 'Carregando…', sub || 'Buscando dados');
    }, LOADER_DELAY_MS);
  }

  function loaderClose() {
    const api = window.PopperLoading;
    if (!api || typeof api.hide !== 'function') return;

    if (_loaderTimer) { clearTimeout(_loaderTimer); _loaderTimer = null; return; }

    if (_loaderShownAt) {
      const elapsed = Date.now() - _loaderShownAt;
      const wait = Math.max(0, LOADER_MIN_MS - elapsed);
      setTimeout(() => api.hide(), wait);
      _loaderShownAt = 0;
      return;
    }

    api.hide();
  }

  async function waitForLoader(maxMs = 600) {
    const start = Date.now();
    while (Date.now() - start < maxMs) {
      if (window.PopperLoading && typeof window.PopperLoading.show === 'function') return true;
      await new Promise(r => setTimeout(r, 25));
    }
    return false;
  }

  let chartProgressMonth = null;
  let chartProgressYear = null;
  let chartPace = null;


  function makeTopLabelsPlugin() {
    return {
      id: 'topLabelsPlugin',
      afterDatasetsDraw(chart) {
        const { ctx, chartArea } = chart;
        if (!chartArea) return;

        ctx.save();

        chart.data.datasets.forEach((dataset, datasetIndex) => {
          const meta = chart.getDatasetMeta(datasetIndex);
          if (meta.hidden) return;

          meta.data.forEach((bar, i) => {
            const raw = Number(dataset.data[i] ?? 0);
            if (!Number.isFinite(raw)) return;

            const x = bar.x;
            const y = Math.max(bar.y - 8, chartArea.top + 14);

            let line1 = brl.format(raw);
            let line2 = '';

            // % apenas nas barras "Realizado"
            if (dataset.label === 'Realizado') {
              const metaDataset = chart.data.datasets.find(d => d.label === 'Meta');
              const metaVal = Number(metaDataset?.data?.[i] ?? 0);
              const pct = metaVal > 0 ? (raw / metaVal) : 0;
              line2 = pct0.format(pct);
            }

            ctx.textAlign = 'center';
            ctx.fillStyle = 'rgba(51, 65, 85, 0.95)';

            // linha 1 = valor em R$
            ctx.font = '600 12px Inter, system-ui, sans-serif';
            ctx.textBaseline = 'bottom';
            ctx.fillText(line1, x, y);

            // linha 2 = percentual (somente realizado)
            if (line2) {
              ctx.font = '700 11px Inter, system-ui, sans-serif';
              ctx.fillStyle = 'rgba(92, 44, 140, 0.95)';
              ctx.fillText(line2, x, y - 14);
            }
          });
        });

        ctx.restore();
      }
    };
  }
  function ensureCharts(updatedAt) {
    const ref = buildRefLabels(updatedAt);
    const topLabelsPlugin = makeTopLabelsPlugin();

    const elMonth = document.getElementById('salesExpensesChartMonth');
    const elYear = document.getElementById('salesExpensesChartYear');
    const elPace = document.getElementById('salesBySectorChart');

    if (!chartProgressMonth && elMonth) {
      chartProgressMonth = new Chart(elMonth, {
        type: 'bar',
        data: {
          labels: ['Mês'],
          datasets: [
            {
              label: 'Realizado',
              data: [0],
              backgroundColor: 'rgba(92, 44, 140, 0.85)',
              borderRadius: 10,
              borderSkipped: false,
              categoryPercentage: 0.55,
              barPercentage: 0.72,
              maxBarThickness: 88
            },
            {
              label: 'Meta',
              data: [0],
              backgroundColor: 'rgba(172, 204, 54, 0.75)',
              borderRadius: 10,
              borderSkipped: false,
              categoryPercentage: 0.55,
              barPercentage: 0.72,
              maxBarThickness: 88
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: false,
          devicePixelRatio: 2,
          layout: { padding: { top: 42, right: 10, bottom: 10, left: 10 } },
          plugins: {
            legend: {
              display: true,
              position: 'bottom',
              labels: {
                usePointStyle: true,
                boxWidth: 12,
                padding: 16
              }
            },
            tooltip: {
              callbacks: {
                title: () => `Referência: ${ref.mesAno}`,
                label: (ctx) => `${ctx.dataset.label}: ${brl.format(ctx.raw)}`
              }
            },
            datalabels: {
              display: false
            }
          },
          scales: {
            x: {
              offset: true,
              stacked: false,
              grid: { display: false },
              ticks: {
                color: '#475569',
                font: { size: 14, weight: '600' }
              }
            },
            y: {
              beginAtZero: true,
              grace: '22%',
              ticks: {
                callback: (v) => brl.format(v),
                color: '#64748b',
                font: { size: 12 }
              },
              grid: {
                color: 'rgba(148,163,184,.18)'
              }
            }
          }
        },
        plugins: [topLabelsPlugin]
      });
    }

    if (!chartProgressYear && elYear) {
      chartProgressYear = new Chart(elYear, {
        type: 'bar',
        data: {
          labels: ['Ano'],
          datasets: [
            {
              label: 'Realizado',
              data: [0],
              backgroundColor: 'rgba(92, 44, 140, 0.85)',
              borderRadius: 10,
              borderSkipped: false,
              categoryPercentage: 0.55,
              barPercentage: 0.72,
              maxBarThickness: 88
            },
            {
              label: 'Meta',
              data: [0],
              backgroundColor: 'rgba(172, 204, 54, 0.75)',
              borderRadius: 10,
              borderSkipped: false,
              categoryPercentage: 0.55,
              barPercentage: 0.72,
              maxBarThickness: 88
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: false,
          devicePixelRatio: 2,
          layout: { padding: { top: 42, right: 10, bottom: 10, left: 10 } },
          plugins: {
            legend: {
              display: true,
              position: 'bottom',
              labels: {
                usePointStyle: true,
                boxWidth: 12,
                padding: 16
              }
            },
            tooltip: {
              callbacks: {
                title: () => `Referência: ${ref.ano}`,
                label: (ctx) => `${ctx.dataset.label}: ${brl.format(ctx.raw)}`
              }
            },
            datalabels: {
              display: false
            }
          },
          scales: {
            x: {
              offset: true,
              stacked: false,
              grid: { display: false },
              ticks: {
                color: '#475569',
                font: { size: 14, weight: '600' }
              }
            },
            y: {
              beginAtZero: true,
              grace: '22%',
              ticks: {
                callback: (v) => brl.format(v),
                color: '#64748b',
                font: { size: 12 }
              },
              grid: {
                color: 'rgba(148,163,184,.18)'
              }
            }
          }
        },
        plugins: [topLabelsPlugin]
      });
    }

    if (!chartPace && elPace) {
      chartPace = new Chart(elPace, {
        type: 'bar',
        data: {
          labels: ['Meta dinâmica do dia', 'Realizado hoje (Fat + IM)'],
          datasets: [
            {
              label: `Dia — ${ref.mesAno}`,
              data: [0, 0],
              backgroundColor: ['rgba(245,158,11,.85)', 'rgba(22,163,74,.85)'],
              borderRadius: 10,
              borderSkipped: false,
              categoryPercentage: 0.58,
              barPercentage: 0.62,
              maxBarThickness: 92
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: false,
          devicePixelRatio: 2,
          layout: { padding: { top: 28, right: 10, bottom: 10, left: 10 } },
          plugins: {
            legend: {
              display: true,
              position: 'bottom',
              labels: {
                usePointStyle: true,
                boxWidth: 12,
                padding: 16
              }
            },
            tooltip: {
              callbacks: {
                title: () => `Referência: ${ref.mesAno}`,
                label: (ctx) => `${ctx.label}: ${brl.format(ctx.raw)}`
              }
            },
            datalabels: {
              display: false
            }
          },
          scales: {
            x: {
              offset: true,
              stacked: false,
              grid: { display: false },
              ticks: {
                color: '#475569',
                font: { size: 13, weight: '600' },
                maxRotation: 0,
                minRotation: 0
              }
            },
            y: {
              beginAtZero: true,
              grace: '18%',
              ticks: {
                callback: (v) => brl.format(v),
                color: '#64748b',
                font: { size: 12 }
              },
              grid: {
                color: 'rgba(148,163,184,.18)'
              }
            }
          }
        },
        plugins: [topLabelsPlugin]
      });
    }
  }

  function renderFromValues(payload) {
    const v = payload.values || {};
    const updatedAt = payload.updated_at || '—';
    const ref = buildRefLabels(updatedAt);

    // ✅ principal do mês = mes_total (FATURADO + IM)
    const fatMes = num(v.mes_faturado);
    const imMes = num(v.mes_im ?? v.mes_agendado);
    const agMes = num(v.mes_ag);
    const totalMes = num(v.mes_total) || (fatMes + imMes) || num(v.realizado_ate_hoje);

    // ✅ principal HOJE
    const totalHoje = hojePrincipal(v);

    setText('titleProgressMonth', `Progresso (Mês) — ${ref.mesAno}`);
    setText('titleProgressYear', `Progresso (Ano) — ${ref.ano}`);
    setText('titlePace', `Meta dinâmica do dia × Realizado hoje — ${ref.mesAno}`);

    setText('kpi-meta-mes', brl.format(num(v.meta_mes)));
    setText('kpi-realizado-mes', brl.format(totalMes));
    setText('kpi-falta-mes', brl.format(num(v.falta_meta_mes)));

    setText('kpi-mes-atual', brl.format(totalMes));
    setText('kpi-mes-fat', brl.format(fatMes));
    setText('kpi-mes-ag', brl.format(imMes));   // IMEDIATO
    setText('kpi-mes-ag2', brl.format(agMes));   // AG

    setText('kpi-meta-ano', brl.format(num(v.meta_ano)));
    setText('kpi-realizado-ano', brl.format(num(v.realizado_ano_acum)));
    setText('kpi-falta-ano', brl.format(num(v.falta_meta_ano)));

    // ✅ "ritmo" no KPI vira HOJE (igual seu pedido de referência)
    setText('kpi-ritmo', brl.format(totalHoje));

    // mantém esses KPIs como você já tinha
    setText('kpi-meta-dia', brl.format(num(v.a_faturar_dia_util))); // dinâmica
    setText('kpi-a-faturar', brl.format(num(v.meta_dia_util)));      // teórica

    setText('kpi-deveria', brl.format(num(v.deveria_ate_hoje)));
    setText('kpi-atingimento', pct0.format(num(v.atingimento_mes_pct)));

    setText('kpi-dias', `${num(v.dias_uteis_trabalhados)} / ${num(v.dias_uteis_trabalhar)}`);
    setText('kpi-produtividade', pct0.format(num(v.realizado_dia_util_pct)));

    setText('kpi-meta-trend', `Atualizado: ${updatedAt}`);
    setText('kpi-ano-trend', `Atualizado: ${updatedAt}`);
    setText('kpi-ritmo-trend', `Atualizado: ${updatedAt}`);
    setText('kpi-deveria-trend', `Atualizado: ${updatedAt}`);
    setText('kpi-dias-trend', `Atualizado: ${updatedAt}`);

    setText('kpi-projecao-mes', brl.format(num(v.fechar_em)));
    setText('kpi-projecao-mes-trend', `Proj: ${pct0.format(num(v.equivale_pct))} • Atualizado: ${updatedAt}`);

    ensureCharts(updatedAt);

    if (chartProgressMonth) {
      chartProgressMonth.data.datasets[0].data = [totalMes];
      chartProgressMonth.data.datasets[1].data = [num(v.meta_mes)];
      chartProgressMonth.update('none');
    }

    if (chartProgressYear) {
      chartProgressYear.data.datasets[0].data = [num(v.realizado_ano_acum)];
      chartProgressYear.data.datasets[1].data = [num(v.meta_ano)];
      chartProgressYear.update('none');
    }

    // ✅ Gráfico do "dia": Meta dinâmica vs Realizado HOJE
    if (chartPace) {
      chartPace.data.datasets[0].label = `Dia — ${ref.mesAno}`;
      chartPace.data.datasets[0].data = [num(v.a_faturar_dia_util), totalHoje];
      chartPace.update('none');
    }

    // =========================
    // DETALHAMENTO (tabela indicador → valor)
    // =========================
    const tbody = document.getElementById('topProductsTable')?.querySelector('tbody');
    if (tbody) {
      const diasRest = Math.max(0, num(v.dias_uteis_trabalhar) - num(v.dias_uteis_trabalhados));

      const fatMes2 = num(v.mes_faturado);
      const imMes2 = num(v.mes_im ?? v.mes_agendado);
      const agMes2 = num(v.mes_ag);
      const totalMes2 = num(v.mes_total) || (fatMes2 + imMes2) || num(v.realizado_ate_hoje);

      const fatHoje2 = num(v.hoje_faturado);
      const imHoje2 = num(v.hoje_im ?? v.hoje_agendado);
      const agHoje2 = num(v.hoje_ag);
      const totalHoje2 = num(v.hoje_total) || (fatHoje2 + imHoje2);

      const rows = [
        ['Meta do ano', brl.format(num(v.meta_ano))],
        ['Realizado ano acumulado', brl.format(num(v.realizado_ano_acum))],
        ['Falta para meta do ano', brl.format(num(v.falta_meta_ano))],

        ['Meta do mês', brl.format(num(v.meta_mes))],
        ['Realizado (mês) — principal (Faturado + IM)', brl.format(totalMes2)],
        ['Faturado no mês', brl.format(fatMes2)],
        ['Agendado IMEDIATO no mês', brl.format(imMes2)],
        ['Agendado AG no mês', brl.format(agMes2)],

        ['Hoje — principal (Faturado + IM)', brl.format(totalHoje2)],
        ['Faturado hoje', brl.format(fatHoje2)],
        ['Agendado IMEDIATO p/ hoje', brl.format(imHoje2)],
        ['Agendado AG p/ hoje', brl.format(agHoje2)],

        ['Deveria ter até hoje', brl.format(num(v.deveria_ate_hoje))],
        ['Atingimento (mês)', pct0.format(num(v.atingimento_mes_pct))],

        ['Meta fixa por dia útil (teórica)', brl.format(num(v.meta_dia_util))],
        ['Meta necessária por dia útil (dinâmica)', brl.format(num(v.a_faturar_dia_util))],
        ['Realizado hoje (Fat + IM)', brl.format(totalHoje2)],
        ['Realizado por dia útil (média do mês)', brl.format(num(v.realizado_dia_util))],
        ['Produtividade (dia útil)', pct0.format(num(v.realizado_dia_util_pct))],

        ['Dias úteis (trabalhados / total)', `${num(v.dias_uteis_trabalhados)} / ${num(v.dias_uteis_trabalhar)}`],
        ['Dias úteis restantes', String(diasRest)],

        ['Projeção de fechamento (mês)', brl.format(num(v.fechar_em))],
        ['Equivale a (projeção/meta)', pct0.format(num(v.equivale_pct))],
        ['Vai bater a meta?', (v.vai_bater_meta ?? '—')],
      ];

      tbody.innerHTML = '';
      rows.forEach(([k, val]) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${k}</td><td class="right">${val}</td>`;
        tbody.appendChild(tr);
      });
    }
  }

  function renderDailyToday(basePayload) {
    const v = basePayload.values || {};
    const updatedAt = basePayload.updated_at || '—';

    const fatHoje = num(v.hoje_faturado);
    const imHoje = num(v.hoje_im ?? v.hoje_agendado);
    const agHoje = num(v.hoje_ag);
    const totalHoje = num(v.hoje_total) || (fatHoje + imHoje);

    setText('kpi-hoje-total', brl.format(totalHoje));
    setText('kpi-hoje-fat', brl.format(fatHoje));
    setText('kpi-hoje-ag', brl.format(imHoje));  // IM
    setText('kpi-hoje-ag2', brl.format(agHoje));  // AG
    setText('kpi-hoje-trend', `Atualizado: ${updatedAt}`);

    const diasTotais = int(v.dias_uteis_trabalhar);
    const diasPassados = int(v.dias_uteis_trabalhados);
    const diasRestantes = clampMin(diasTotais - diasPassados, 0);

    const metaMes = num(v.meta_mes);
    const realizadoMes = num(v.realizado_ate_hoje);

    const metaDiaTeorica = (diasTotais > 0) ? (metaMes / diasTotais) : 0;
    const faltaMes = Math.max(0, metaMes - realizadoMes);
    const metaDiaDinamica = (diasRestantes > 0) ? (faltaMes / diasRestantes) : 0;

    // Gap de HOJE agora comparando com a meta dinâmica
    const gapHoje = totalHoje - metaDiaDinamica;

    setText('kpi-meta-hoje', brl.format(metaDiaDinamica));
    setText('kpi-gap-hoje', brl.format(gapHoje));
    setText('kpi-meta-hoje-trend', gapHoje >= 0 ? 'Acima da meta necessária do dia' : 'Abaixo da meta necessária do dia');

    setText('metaDinamica', brl.format(metaDiaDinamica));

    const labelDias = diasRestantes === 1 ? 'dia útil' : 'dias úteis';
    setText('metaRestante', `Faltam ${diasRestantes} ${labelDias} • Restante no mês: ${brl.format(faltaMes)}`);

    setText('metaTeorica', `Meta do dia (teórica): ${brl.format(metaDiaTeorica)}`);
    setText('gapHoje', `Gap hoje: ${brl.format(gapHoje)}`);
  }

  async function fetchJson(url) {
    const res = await fetch(url, { cache: 'no-store' });
    if (!res.ok) {
      const txt = await res.text().catch(() => '');
      throw new Error(`HTTP ${res.status} ${res.statusText}${txt ? ' — ' + txt.slice(0, 160) : ''}`);
    }
    return await res.json();
  }

  async function refresh(forceTotvs = false, opts = {}) {
    const dash = (window.DASH_CURRENT || 'executivo');
    const url = `/api/dashboard-data.php?dash=${encodeURIComponent(dash)}${forceTotvs ? '&force=1' : ''}`;

    const showLoader = opts.showLoader !== false;
    const title = forceTotvs ? 'Atualizando TOTVS…' : 'Carregando métricas…';
    const sub = opts.sub || 'Buscando dados da API';

    if (showLoader) {
      await waitForLoader(800);
      loaderOpen(title, sub);
    }

    try {
      const payload = await fetchJson(url);

      renderFromValues(payload);
      renderDailyToday(payload);

      window.dispatchEvent(new CustomEvent('dash:ready'));

      if (showLoader) loaderClose();
      return payload;
    } catch (e) {
      console.error(e);
      if (showLoader) loaderClose();
      window.PopperLoading?.error?.('Não consegui carregar os dados. Tente novamente.');
      window.dispatchEvent(new CustomEvent('dash:error', { detail: { message: 'Falha ao carregar os dados do dashboard' } }));
      throw e;
    }
  }

  refresh(false).catch(() => { });

  setInterval(() => {
    refresh(true).catch(() => { });
  }, 10 * 60 * 1000);

  const btn = document.getElementById('btnForceTotvs');
  if (btn) {
    btn.addEventListener('click', async () => {
      const st = document.getElementById('forceStatus');

      btn.classList.add('is-loading');
      if (st) { st.textContent = 'Atualizando...'; st.className = 'is-loading'; }

      await waitForLoader(800);
      loaderOpen('Atualizando TOTVS…', 'Forçando leitura no Protheus');

      try {
        await refresh(true, { showLoader: false });
        if (st) { st.textContent = 'Atualizado'; st.className = 'is-ok'; }
      } catch (e) {
        if (st) { st.textContent = 'Falhou'; st.className = 'is-error'; }
      } finally {
        loaderClose();
        btn.classList.remove('is-loading');
      }
    });
  }

  if (location.hostname === 'localhost' || location.hostname === '127.0.0.1') {
    window.refreshDashboard = refresh;
  }
  function refreshChartSizes() {
    [chartProgressMonth, chartProgressYear, chartPace].forEach((chart) => {
      if (chart) {
        chart.resize();
        chart.update('none');
      }
    });
  }

  window.addEventListener('load', () => {
    setTimeout(refreshChartSizes, 300);
  });

  window.addEventListener('resize', () => {
    refreshChartSizes();
  });

  window.addEventListener('orientationchange', () => {
    setTimeout(refreshChartSizes, 200);
  });
})();