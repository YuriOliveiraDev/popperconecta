(function () {
    'use strict';

    const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
    const pct0 = new Intl.NumberFormat('pt-BR', { style: 'percent', maximumFractionDigits: 0 });

    const TOP_N = 50;
    const CURRENT_YM = (() => {
        const now = new Date();
        return now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
    })();

    let chartProgressMonth = null;
    let chartProgressYear = null;
    let chartPace = null;
    let chartDiario = null;

    let _topScrollRAFProdutos = null;
    let _topScrollRAFClientes = null;

    function num(v) {
        const n = Number(v);
        return Number.isFinite(n) ? n : 0;
    }

    function int(v) {
        return Math.trunc(num(v));
    }

    function clampMin(v, min) {
        return v < min ? min : v;
    }

    function setText(id, text) {
        const el = document.getElementById(id);
        if (el) el.textContent = text;
    }

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
        } catch (_) { }
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

    function escapeHtml(str) {
        return String(str ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function asNumber(v) {
        const n = Number(v);
        return Number.isFinite(n) ? n : 0;
    }

    function moneyBR(v) {
        return asNumber(v).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        });
    }

    function normalizeEntries(input) {
        if (!input) return [];

        if (Array.isArray(input)) {
            return input
                .map(it => {
                    if (Array.isArray(it)) return [it[0], it[1]];
                    if (it && typeof it === 'object') {
                        return [it.name ?? it.label ?? it.key ?? it.cliente, it.value ?? it.val ?? it.total ?? it.valor];
                    }
                    return [null, null];
                })
                .filter(([n]) => n != null && String(n).trim() !== '');
        }

        if (typeof input === 'object') return Object.entries(input);
        return [];
    }

    function renderTopList(containerId, badgeId, input) {
        const wrap = document.getElementById(containerId);
        if (!wrap) return;

        const entries = normalizeEntries(input)
            .map(([name, val]) => [String(name), asNumber(val)])
            .filter(([name]) => name.trim() !== '')
            .sort((a, b) => b[1] - a[1])
            .slice(0, 50); // ✅ força top 50 no front

        const total = entries.reduce((acc, [, v]) => acc + v, 0);
        const max = entries.length ? entries[0][1] : 0;

        const badge = document.getElementById(badgeId);
        if (badge) {
            badge.textContent = entries.length
                ? `Top ${entries.length}`
                : '—';
        }

        wrap.innerHTML = '';

        if (!entries.length) {
            wrap.innerHTML = '<div style="opacity:.7;padding:8px 6px;">Sem dados</div>';
            return;
        }

        for (let idx = 0; idx < entries.length; idx++) {
            const [name, val] = entries[idx];
            const rank = idx + 1;
            const pct = total > 0 ? (val / total) * 100 : 0;
            const width = max > 0 ? (val / max) * 100 : 0;

            const row = document.createElement('div');
            row.className = 'top-item';

            row.innerHTML = `
      <div class="top-rank">${rank}</div>
      <div class="top-main">
        <div class="top-name" title="${escapeHtml(name)}">${escapeHtml(name)}</div>
        <div class="top-subline">${pct.toFixed(1)}%</div>
        <div class="top-bar"><i style="width:${width.toFixed(1)}%"></i></div>
      </div>
      <div class="top-val">${moneyBR(val)}</div>
    `;

            wrap.appendChild(row);
        }
    }
    function stopTopAutoScroll() {
        if (_topScrollRAFProdutos) {
            cancelAnimationFrame(_topScrollRAFProdutos);
            _topScrollRAFProdutos = null;
        }
        if (_topScrollRAFClientes) {
            cancelAnimationFrame(_topScrollRAFClientes);
            _topScrollRAFClientes = null;
        }
    }

    function autoScrollTopList(containerId, speed = 0.35) {
        const el = document.getElementById(containerId);
        if (!el) return null;

        const item = el.querySelector('.top-item');
        if (!item) return null;

        let direction = 1;
        let pos = 0;

        function step() {
            const realMax = Math.max(0, el.scrollHeight - el.clientHeight);
            if (realMax <= 0) {
                const rafId = requestAnimationFrame(step);
                if (containerId === 'listTopProdutos') _topScrollRAFProdutos = rafId;
                if (containerId === 'listTopClientes') _topScrollRAFClientes = rafId;
                return;
            }

            pos += direction * speed;

            if (pos >= realMax) {
                pos = realMax;
                direction = -1;
            }

            if (pos <= 0) {
                pos = 0;
                direction = 1;
            }

            el.scrollTop = pos;

            const rafId = requestAnimationFrame(step);
            if (containerId === 'listTopProdutos') {
                _topScrollRAFProdutos = rafId;
            } else if (containerId === 'listTopClientes') {
                _topScrollRAFClientes = rafId;
            }
        }

        return requestAnimationFrame(step);
    }

    function startTopAutoScroll() {
        stopTopAutoScroll();
        _topScrollRAFProdutos = autoScrollTopList('listTopProdutos', 0.45);
        _topScrollRAFClientes = autoScrollTopList('listTopClientes', 0.50);
    }

    function startTopAutoScroll() {
        stopTopAutoScroll();
        _topScrollRAFProdutos = autoScrollTopList('listTopProdutos', 0.30);
        _topScrollRAFClientes = autoScrollTopList('listTopClientes', 0.35);
    }

    function restartTopAutoScrollDelayed(delay = 1200) {
        stopTopAutoScroll();
        setTimeout(startTopAutoScroll, delay);
    }

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
                        const y = Math.max(bar.y - 10, chartArea.top + 24);

                        let line1 = brl.format(raw);
                        let line2 = '';

                        if (dataset.label === 'Realizado') {
                            const metaDataset = chart.data.datasets.find(d => d.label === 'Meta');
                            const metaVal = Number(metaDataset?.data?.[i] ?? 0);
                            const pct = metaVal > 0 ? (raw / metaVal) : 0;
                            line2 = pct0.format(pct);
                        }

                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';

                        ctx.fillStyle = 'rgba(51, 65, 85, 0.98)';
                        ctx.font = '700 18px Inter, system-ui, sans-serif';
                        ctx.fillText(line1, x, y);

                        if (line2) {
                            ctx.fillStyle = 'rgba(92, 44, 140, 0.98)';
                            ctx.font = '800 16px Inter, system-ui, sans-serif';
                            ctx.fillText(line2, x, y - 20);
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
                    layout: { padding: { top: 64, right: 10, bottom: 10, left: 10 } },
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
                        }
                    },
                    scales: {
                        x: {
                            offset: true,
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
                    layout: { padding: { top: 64, right: 10, bottom: 10, left: 10 } },
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
                        }
                    },
                    scales: {
                        x: {
                            offset: true,
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
                    layout: { padding: { top: 52, right: 10, bottom: 10, left: 10 } },
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
                        }
                    },
                    scales: {
                        x: {
                            offset: true,
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
                }
            });
        }
    }

    function renderFromValues(payload) {
        const v = payload.values || {};
        const updatedAt = payload.updated_at || '—';
        const ref = buildRefLabels(updatedAt);

        const fatMes = num(v.mes_faturado);
        const imMes = num(v.mes_im ?? v.mes_agendado);
        const agMes = num(v.mes_ag);
        const totalMes = num(v.mes_total) || (fatMes + imMes) || num(v.realizado_ate_hoje);

        const fatHoje = num(v.hoje_faturado);
        const imHoje = num(v.hoje_im ?? v.hoje_agendado);
        const agHoje = num(v.hoje_ag);
        const totalHoje = num(v.hoje_total) || (fatHoje + imHoje);

        const metaMes = num(v.meta_mes);
        const deveriaHoje = num(v.deveria_ate_hoje);
        const projMes = num(v.fechar_em);
        const equivalePct = num(v.equivale_pct);
        const atingMesPct = num(v.atingimento_mes_pct);

        const diasTrab = int(v.dias_uteis_trabalhados);
        const diasTotal = int(v.dias_uteis_trabalhar);
        const diasRest = clampMin(diasTotal - diasTrab, 0);

        const faltaMes = Math.max(0, metaMes - totalMes);
        const metaDiaTeorica = num(v.meta_dia_util);
        const metaDiaDinamica = num(v.a_faturar_dia_util);

        setText('titleProgressMonth', `Progresso (Mês) — ${ref.mesAno}`);
        setText('titleProgressYear', `Progresso (Ano) — ${ref.ano}`);
        setText('titlePace', `Meta dinâmica do dia × Realizado hoje — ${ref.mesAno}`);

        setText('tv-meta', brl.format(metaMes));
        setText('tv-realizado', brl.format(totalMes));
        setText('tv-falta', brl.format(faltaMes));

        setText('tv-mes', brl.format(totalMes));
        setText('tv-mes-fat', brl.format(fatMes));
        setText('tv-mes-im', brl.format(imMes));
        setText('tv-mes-ag', brl.format(agMes));
        setText('tv-dias', `${diasTrab} / ${diasTotal}`);
        setText('tv-prod', pct0.format(num(v.realizado_dia_util_pct)));

        setText('tv-deveria', brl.format(deveriaHoje));
        setText('tv-ating', pct0.format(atingMesPct));

        setText('tv-projecao', brl.format(projMes));
        setText('tv-proj-pct', pct0.format(equivalePct));

        setText('tv-hoje', brl.format(totalHoje));
        setText('tv-hoje-fat', brl.format(fatHoje));
        setText('tv-hoje-im', brl.format(imHoje));
        setText('tv-hoje-ag', brl.format(agHoje));

        setText('tv-meta-dia', brl.format(metaDiaDinamica));
        setText('tv-dias-rest', String(diasRest));
        setText('tv-restante', brl.format(faltaMes));
        setText('tv-meta-teo', brl.format(metaDiaTeorica));

        const gapHoje = totalHoje - metaDiaTeorica;
        const gapEl = document.getElementById('tv-gap');
        if (gapEl) {
            gapEl.textContent = gapHoje >= 0 ? 'Acima da meta do dia' : 'Abaixo da meta do dia';
            gapEl.className = gapHoje >= 0 ? 'kpi-green' : 'kpi-red';
        }

        setText('tv-updated', updatedAt);
        setText('tv-updated-footer', updatedAt);

        const metaBar = document.getElementById('meta-bar');
        const metaPct = document.getElementById('meta-pct');
        if (metaBar) {
            const pct = Math.max(0, Math.min(atingMesPct * 100, 100));
            metaBar.style.width = pct + '%';
            if (metaPct) metaPct.textContent = Math.round(atingMesPct * 100) + '%';
        }

        const metaDiaBar = document.getElementById('meta-dia-bar');
        const metaDiaPct = document.getElementById('meta-dia-pct');
        if (metaDiaBar) {
            const pct = metaDiaDinamica > 0 ? Math.max(0, Math.min((totalHoje / metaDiaDinamica) * 100, 100)) : 0;
            metaDiaBar.style.width = pct + '%';
            if (metaDiaPct) metaDiaPct.textContent = Math.round(pct) + '%';
        }

        ensureCharts(updatedAt);

        if (chartProgressMonth) {
            chartProgressMonth.data.datasets[0].data = [totalMes];
            chartProgressMonth.data.datasets[1].data = [metaMes];
            chartProgressMonth.update('none');
        }

        if (chartProgressYear) {
            chartProgressYear.data.datasets[0].data = [num(v.realizado_ano_acum)];
            chartProgressYear.data.datasets[1].data = [num(v.meta_ano)];
            chartProgressYear.update('none');
        }

        if (chartPace) {
            chartPace.data.datasets[0].label = `Dia — ${ref.mesAno}`;
            chartPace.data.datasets[0].data = [metaDiaDinamica, totalHoje];
            chartPace.update('none');
        }
    }

    function renderDailyChart(payload) {
        if (!payload || !payload.diario_mes) return;

        const labels = Object.keys(payload.diario_mes).sort((a, b) => Number(a) - Number(b));
        const valores = Object.keys(payload.diario_mes)
            .sort((a, b) => Number(a) - Number(b))
            .map(k => Number(payload.diario_mes[k] || 0));

        const canvas = document.getElementById('chartDiario');
        if (!canvas) return;

        if (chartDiario) {
            chartDiario.destroy();
        }

        chartDiario = new Chart(canvas, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Faturamento',
                    data: valores,
                    borderColor: '#5c2c8c',
                    backgroundColor: 'rgba(92,44,140,.12)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#5c2c8c',
                    pointBorderColor: '#5c2c8c'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                layout: { padding: { top: 52, right: 8, left: 8, bottom: 8 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => brl.format(ctx.raw || 0)
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (v) => brl.format(v)
                        }
                    }
                }
            },
            plugins: [{
                id: 'dailyPointLabels',
                afterDatasetsDraw(chart) {
                    const { ctx, chartArea } = chart;
                    if (!chartArea) return;

                    const meta = chart.getDatasetMeta(0);
                    const dataset = chart.data.datasets[0];
                    if (!meta || !dataset) return;

                    ctx.save();
                    ctx.textBaseline = 'bottom';
                    ctx.fillStyle = '#1f2937';
                    ctx.font = '700 13px Inter, system-ui, sans-serif';

                    const padX = 10;

                    meta.data.forEach((point, i) => {
                        const raw = Number(dataset.data[i] ?? 0);
                        if (!Number.isFinite(raw)) return;

                        const text = brl.format(raw);
                        const x = point.x;
                        const y = Math.max(point.y - 10, chartArea.top + 16);

                        const isFirst = i === 0;
                        const isLast = i === meta.data.length - 1;

                        if (isFirst) {
                            ctx.textAlign = 'left';
                            ctx.fillText(text, Math.max(chartArea.left + padX, x - 2), y);
                            return;
                        }

                        if (isLast) {
                            ctx.textAlign = 'right';
                            ctx.fillText(text, Math.min(chartArea.right - padX, x + 2), y);
                            return;
                        }

                        ctx.textAlign = 'center';
                        ctx.fillText(text, x, y);
                    });

                    ctx.restore();
                }
            }]
        });

        const now = new Date();
        const mes = now.toLocaleString('pt-BR', { month: 'short' });
        const ano = now.getFullYear();
        setText('ttlChart', `Faturamento Diário (${mes}/${ano})`);
    }

    async function fetchJson(url) {
        const res = await fetch(url, { cache: 'no-store' });
        if (!res.ok) {
            const txt = await res.text().catch(() => '');
            throw new Error(`HTTP ${res.status} ${res.statusText}${txt ? ' — ' + txt.slice(0, 160) : ''}`);
        }
        return await res.json();
    }

    async function loadTops() {
        try {
            const [prodPayload, cliPayload] = await Promise.all([
                fetchJson(`/api/dashboard-executivo-save.php?ym=${CURRENT_YM}`),
                fetchJson(`/api/clientes_insights.php?ym=${CURRENT_YM}`)
            ]);

            setText('topsUpdated', prodPayload?.updated_at || cliPayload?.updated_at || '--');

            renderTopList(
                'listTopProdutos',
                'badgeTopProdutos',
                prodPayload?.top_produtos || null
            );

            const top50 = cliPayload?.ranking?.top50 || [];
            const clientesAsEntries = Array.isArray(top50)
                ? top50.map(x => [x.cliente, x.valor])
                : null;

            renderTopList(
                'listTopClientes',
                'badgeTopClientes',
                clientesAsEntries
            );

            restartTopAutoScrollDelayed(1200);
        } catch (e) {
            console.error('Erro ao carregar tops:', e);
            renderTopList('listTopProdutos', 'badgeTopProdutos', null);
            renderTopList('listTopClientes', 'badgeTopClientes', null);
        }
    }

    async function loadDailyChart() {
        try {
            const payload = await fetchJson(`/api/dashboard-executivo-save.php?ym=${CURRENT_YM}`);
            renderDailyChart(payload);
        } catch (e) {
            console.error('Erro ao carregar gráfico diário:', e);
        }
    }

    function refreshChartSizes() {
        [chartProgressMonth, chartProgressYear, chartPace, chartDiario].forEach((chart) => {
            if (chart) {
                chart.resize();
                chart.update('none');
            }
        });
    }

    async function refresh(forceTotvs = false) {
        const dash = (window.DASH_CURRENT || 'executivo');
        const url = `/api/dashboard-data.php?dash=${encodeURIComponent(dash)}${forceTotvs ? '&force=1' : ''}`;

        try {
            const payload = await fetchJson(url);
            renderFromValues(payload);
            await Promise.allSettled([
                loadDailyChart(),
                loadTops()
            ]);
            return payload;
        } catch (e) {
            console.error('Erro ao carregar dashboard:', e);
            throw e;
        }
    }

    refresh(false).catch(() => { });

    setInterval(() => {
        refresh(true).catch(() => { });
    }, 10 * 60 * 1000);

    window.addEventListener('load', () => {
        setTimeout(() => {
            refreshChartSizes();
            startTopAutoScroll();
        }, 1200);
    });

    window.addEventListener('resize', refreshChartSizes);
    window.addEventListener('orientationchange', () => {
        setTimeout(refreshChartSizes, 200);
    });

    if (location.hostname === 'localhost' || location.hostname === '127.0.0.1') {
        window.refreshDashboard = refresh;
    }
})();