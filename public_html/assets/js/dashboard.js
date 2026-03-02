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

    const elMonth = document.getElementById('salesExpensesChartMonth');
    const elYear = document.getElementById('salesExpensesChartYear');
    const elPace = document.getElementById('salesBySectorChart');

    if (!chartProgressMonth && elMonth) {
      chartProgressMonth = new Chart(elMonth, {
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
          scales: { y: { beginAtZero: true, grace: '20%', ticks: { callback: (v) => brl.format(v) } } }
        },
        plugins: [valueLabelPlugin]
      });
    }

    if (!chartProgressYear && elYear) {
      chartProgressYear = new Chart(elYear, {
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
          scales: { y: { beginAtZero: true, grace: '20%', ticks: { callback: (v) => brl.format(v) } } }
        },
        plugins: [valueLabelPlugin]
      });
    }

    if (!chartPace && elPace) {
      chartPace = new Chart(elPace, {
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
          scales: { y: { beginAtZero: true, grace: '20%', ticks: { callback: (v) => brl.format(v) } } }
        },
        plugins: [valueLabelPlugin]
      });
    }
  }

  function renderFromValues(payload) {
    const v = payload.values || {};
    const updatedAt = payload.updated_at || '—';
    const ref = buildRefLabels(updatedAt);

    // ✅ principal do mês = mes_total (FATURADO + IM)
    const fatMes = num(v.mes_faturado);
    const imMes  = num(v.mes_im ?? v.mes_agendado);
    const agMes  = num(v.mes_ag);
    const totalMes = num(v.mes_total) || (fatMes + imMes) || num(v.realizado_ate_hoje);

    setText('titleProgressMonth', `Progresso (Mês) — ${ref.mesAno}`);
    setText('titleProgressYear', `Progresso (Ano) — ${ref.ano}`);
    setText('titlePace', `Ritmo (Dia útil) — ${ref.mesAno}`);

    setText('kpi-meta-mes', brl.format(num(v.meta_mes)));
    setText('kpi-realizado-mes', brl.format(totalMes));
    setText('kpi-falta-mes', brl.format(num(v.falta_meta_mes)));

    setText('kpi-mes-atual', brl.format(totalMes));
    setText('kpi-mes-fat', brl.format(fatMes));
    setText('kpi-mes-ag', brl.format(imMes));   // IMEDIATO
    setText('kpi-mes-ag2', brl.format(agMes));  // AG

    setText('kpi-meta-ano', brl.format(num(v.meta_ano)));
    setText('kpi-realizado-ano', brl.format(num(v.realizado_ano_acum)));
    setText('kpi-falta-ano', brl.format(num(v.falta_meta_ano)));

    setText('kpi-ritmo', brl.format(num(v.realizado_dia_util)));

    setText('kpi-meta-dia', brl.format(num(v.a_faturar_dia_util)));
    setText('kpi-a-faturar', brl.format(num(v.meta_dia_util)));

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

    if (chartPace) {
      chartPace.data.datasets[0].label = `Ritmo (R$/dia) — ${ref.mesAno}`;
      chartPace.data.datasets[0].data = [num(v.meta_dia_util), num(v.realizado_dia_util)];
      chartPace.update('none');
    }

        // =========================
    // DETALHAMENTO (tabela indicador → valor)
    // =========================
    const tbody = document.getElementById('topProductsTable')?.querySelector('tbody');
    if (tbody) {
      const diasRest = Math.max(0, num(v.dias_uteis_trabalhar) - num(v.dias_uteis_trabalhados));

      const fatMes = num(v.mes_faturado);
      const imMes  = num(v.mes_im ?? v.mes_agendado);
      const agMes  = num(v.mes_ag);
      const totalMes = num(v.mes_total) || (fatMes + imMes) || num(v.realizado_ate_hoje);

      const fatHoje = num(v.hoje_faturado);
      const imHoje  = num(v.hoje_im ?? v.hoje_agendado);
      const agHoje  = num(v.hoje_ag);
      const totalHoje = num(v.hoje_total) || (fatHoje + imHoje);

      const rows = [
        ['Meta do ano', brl.format(num(v.meta_ano))],
        ['Realizado ano acumulado', brl.format(num(v.realizado_ano_acum))],
        ['Falta para meta do ano', brl.format(num(v.falta_meta_ano))],

        ['Meta do mês', brl.format(num(v.meta_mes))],
        ['Realizado (mês) — principal (Faturado + IM)', brl.format(totalMes)],
        ['Faturado no mês ', brl.format(fatMes)],
        ['Agendado IMEDIATO no mês ', brl.format(imMes)],
        ['Agendado AG no mês ', brl.format(agMes)],

        ['Hoje — principal (Faturado + IM)', brl.format(totalHoje)],
        ['Faturado hoje ', brl.format(fatHoje)],
        ['Agendado IMEDIATO p/ hoje ', brl.format(imHoje)],
        ['Agendado AG p/ hoje ', brl.format(agHoje)],

        ['Deveria ter até hoje', brl.format(num(v.deveria_ate_hoje))],
        ['Atingimento (mês)', pct0.format(num(v.atingimento_mes_pct))],

        ['Meta fixa por dia útil (teórica)', brl.format(num(v.meta_dia_util))],
        ['Meta necessária por dia útil (dinâmica)', brl.format(num(v.a_faturar_dia_util))],
        ['Realizado por dia útil', brl.format(num(v.realizado_dia_util))],
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

    // ✅ principal HOJE = hoje_total (FATURADO + IM pra hoje)
    const fatHoje = num(v.hoje_faturado);
    const imHoje  = num(v.hoje_im ?? v.hoje_agendado);
    const agHoje  = num(v.hoje_ag);
    const totalHoje = num(v.hoje_total) || (fatHoje + imHoje);

    setText('kpi-hoje-total', brl.format(totalHoje));
    setText('kpi-hoje-fat', brl.format(fatHoje));
    setText('kpi-hoje-ag', brl.format(imHoje));   // IM
    setText('kpi-hoje-ag2', brl.format(agHoje));  // AG
    setText('kpi-hoje-trend', `Atualizado: ${updatedAt}`);

    const diasTotais = int(v.dias_uteis_trabalhar);
    const diasPassados = int(v.dias_uteis_trabalhados);
    const diasRestantes = clampMin(diasTotais - diasPassados, 0);

    const metaMes = num(v.meta_mes);
    const realizadoMes = num(v.realizado_ate_hoje);

    const metaDiaTeorica = (diasTotais > 0) ? (metaMes / diasTotais) : 0;
    const gapHoje = totalHoje - metaDiaTeorica;

    setText('kpi-meta-hoje', brl.format(metaDiaTeorica));
    setText('kpi-gap-hoje', brl.format(gapHoje));
    setText('kpi-meta-hoje-trend', gapHoje >= 0 ? 'Acima da meta do dia' : 'Abaixo da meta do dia');

    const faltaMes = Math.max(0, metaMes - realizadoMes);
    const metaDiaDinamica = (diasRestantes > 0) ? (faltaMes / diasRestantes) : 0;

    setText('metaDinamica', brl.format(metaDiaDinamica));

    const labelDias = diasRestantes === 1 ? 'dia útil' : 'dias úteis';
    setText('metaRestante', `Faltam ${diasRestantes} ${labelDias} • Restante no mês: ${brl.format(faltaMes)}`);

    setText('metaTeorica', `Meta do dia (teórica): ${brl.format(metaDiaTeorica)} • ${gapHoje >= 0 ? 'Acima' : 'Abaixo'} da meta do dia`);
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

  refresh(false).catch(() => {});

  setInterval(() => {
    refresh(true).catch(() => {});
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
})();