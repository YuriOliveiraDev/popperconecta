(function () {
  const state = {
    payload: null,
    clientes: [],
    vendedores080: [],
    chart: null,
    selectedPreset: "6m",
    loaderCount: 0,
    totals: {
      inad: 0,
      faturado: 0,
      titulos: 0,
    },
    sortTable: {
      field: "inad_total",
      dir: "desc",
    },
  };

  const $ = (sel) => document.querySelector(sel);

  const FAIXAS = ["1-30", "31-60", "61-90", "91-180", "180+"];

  function brl(v) {
    return new Intl.NumberFormat("pt-BR", {
      style: "currency",
      currency: "BRL",
    }).format(Number(v || 0));
  }

  function pct(v, decimals = 2) {
    return new Intl.NumberFormat("pt-BR", {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    }).format(Number(v || 0));
  }

  function num(v) {
    return new Intl.NumberFormat("pt-BR").format(Number(v || 0));
  }

  function esc(str) {
    return String(str ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function toNumber(v) {
    const n = Number(v || 0);
    return Number.isFinite(n) ? n : 0;
  }

  function limparCodigoNome(valor) {
    const txt = String(valor || "").trim();
    if (!txt) return "-";
    return txt.replace(/^\d+\s*[-|>]\s*/u, "").trim() || txt;
  }

  function onlyNameOrCode(nome, codigo) {
    const n = limparCodigoNome(nome || "");
    if (n && n !== "-") return n;
    return String(codigo || "-").trim() || "-";
  }

  function sum(arr, selector) {
    return (arr || []).reduce((acc, item) => acc + toNumber(selector(item)), 0);
  }

  function getMaiorFaixa(titulos) {
    const pesos = {
      "1-30": 1,
      "31-60": 2,
      "61-90": 3,
      "91-180": 4,
      "180+": 5,
    };

    let maior = "";
    let peso = 0;

    (Array.isArray(titulos) ? titulos : []).forEach((t) => {
      const faixa = String(t.faixa_atraso || "").trim();
      const p = pesos[faixa] || 0;
      if (p > peso) {
        peso = p;
        maior = faixa;
      }
    });

    return maior || "-";
  }

  function getRiskMeta(cli) {
    const pctInad = toNumber(cli.indice_inadimplencia_pct);
    const inad = toNumber(cli.inad_total);
    const atraso = toNumber(cli.maior_atraso_dias);

    let label = "Baixo";
    let score = 1;
    let className = "risk-low";

    if (pctInad <= 0 && inad <= 0) {
      return { label: "Sem risco", score: 0, className: "risk-none" };
    }

    if (atraso > 180 || inad >= 100000 || pctInad >= 30) {
      label = "Crítico";
      score = 4;
      className = "risk-critical";
    } else if (atraso > 90 || inad >= 50000 || pctInad >= 15) {
      label = "Alto";
      score = 3;
      className = "risk-high";
    } else if (atraso > 30 || inad >= 10000 || pctInad >= 5) {
      label = "Médio";
      score = 2;
      className = "risk-medium";
    }

    return { label, score, className };
  }

  function riskClass(pctValue, inadValue, atrasoDias = 0) {
    return getRiskMeta({
      indice_inadimplencia_pct: pctValue,
      inad_total: inadValue,
      maior_atraso_dias: atrasoDias,
    }).className;
  }

  const LOADER_DELAY_MS = 120;
  const LOADER_MIN_MS = 350;

  let _loaderTimer = null;
  let _loaderShownAt = 0;

  function loaderOpen(title, sub) {
    state.loaderCount++;

    const api = window.PopperLoading;
    if (!api || typeof api.show !== "function") return;

    if (_loaderTimer) {
      clearTimeout(_loaderTimer);
      _loaderTimer = null;
    }

    _loaderShownAt = 0;

    _loaderTimer = setTimeout(() => {
      _loaderTimer = null;
      _loaderShownAt = Date.now();
      api.show(title || "Carregando…", sub || "Buscando dados");
    }, LOADER_DELAY_MS);
  }

  function loaderClose() {
    state.loaderCount = Math.max(0, state.loaderCount - 1);
    if (state.loaderCount > 0) return;

    const api = window.PopperLoading;
    if (!api || typeof api.hide !== "function") return;

    if (_loaderTimer) {
      clearTimeout(_loaderTimer);
      _loaderTimer = null;
      return;
    }

    if (_loaderShownAt) {
      const elapsed = Date.now() - _loaderShownAt;
      const wait = Math.max(0, LOADER_MIN_MS - elapsed);

      setTimeout(() => api.hide(), wait);
      _loaderShownAt = 0;
      return;
    }

    api.hide();
  }

  async function waitForLoader(maxMs = 800) {
    const start = Date.now();

    while (Date.now() - start < maxMs) {
      if (window.PopperLoading && typeof window.PopperLoading.show === "function") {
        return true;
      }
      await new Promise((r) => setTimeout(r, 25));
    }

    return false;
  }

  async function withLoader(fn, opts = {}) {
    await waitForLoader(800);
    loaderOpen(opts.title || "Carregando…", opts.sub || "Buscando dados da API");

    try {
      return await fn();
    } finally {
      loaderClose();
    }
  }

  async function loadVendedores080(force = false) {
    if (state.vendedores080.length && !force) return state.vendedores080;

    const resp = await fetch("/api/totvs_vendedores_080.php", {
      headers: { Accept: "application/json" },
      cache: "no-store",
    });

    const text = await resp.text();

    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      console.error("Resposta inválida da 000080:", text);
      throw new Error("A API de vendedores 000080 não retornou JSON válido.");
    }

    if (!resp.ok || !data.ok) {
      throw new Error(data?.message || "Erro ao carregar vendedores 000080.");
    }

    state.vendedores080 = Array.isArray(data.items) ? data.items : [];
    return state.vendedores080;
  }

  function getGroupByLabel(groupBy) {
    switch (String(groupBy || "")) {
      case "day":
        return "Diário";
      case "week":
        return "Semanal";
      case "month":
        return "Mensal";
      default:
        return "-";
    }
  }

  function getLastVariation(hist) {
    if (!Array.isArray(hist) || hist.length < 2) {
      return null;
    }

    for (let i = hist.length - 1; i >= 0; i--) {
      const item = hist[i];
      if (item && item.variacao_pct !== null && item.variacao_pct !== undefined) {
        return Number(item.variacao_pct);
      }
    }

    return null;
  }

  function destroyTrendChart() {
    if (state.chart) {
      state.chart.destroy();
      state.chart = null;
    }
  }

  function setActivePresetButton(preset) {
    document.querySelectorAll(".btn-preset").forEach((btn) => {
      btn.classList.toggle("is-active", btn.dataset.preset === preset);
    });
  }

  function syncPresetFromPayload(data) {
    const preset = data?.filtros_aplicados?.preset || state.selectedPreset || "6m";
    state.selectedPreset = preset;
    setActivePresetButton(preset);
  }

  if (typeof Chart !== "undefined" && window.ChartDataLabels) {
    Chart.register(window.ChartDataLabels);
  }

  function renderTrendChart(data) {
    const canvas = document.getElementById("inadTrendChart");
    if (!canvas || typeof Chart === "undefined") {
      return;
    }

    const historico = Array.isArray(data?.historico_inadimplencia)
      ? data.historico_inadimplencia
      : [];

    const subtitle = document.getElementById("chartSubtitle");
    const currentValue = document.getElementById("chartCurrentValue");
    const variation = document.getElementById("chartVariation");
    const grouping = document.getElementById("chartGrouping");

    if (!historico.length) {
      destroyTrendChart();

      if (subtitle) subtitle.textContent = "Sem dados para o período selecionado.";
      if (currentValue) currentValue.textContent = brl(0);
      if (variation) variation.textContent = "0,00%";
      if (grouping) {
        grouping.textContent = getGroupByLabel(data?.filtros_aplicados?.group_by);
      }

      return;
    }

    const labels = historico.map((item) => item.label || item.periodo || "");
    const valores = historico.map((item) => Number(item.inad_total || 0));
    const ultimo = historico[historico.length - 1] || {};
    const variacaoPct = getLastVariation(historico);
    const groupBy = data?.filtros_aplicados?.group_by || "-";

    let acumulado = [];
    if (groupBy === "month") {
      let soma = 0;
      acumulado = valores.map((valor) => {
        soma += Number(valor || 0);
        return soma;
      });
    }

    if (subtitle) {
      subtitle.textContent =
        groupBy === "month"
          ? "Evolução mensal da inadimplência com visão acumulada."
          : `Acompanhamento da inadimplência por período (${getGroupByLabel(groupBy)}).`;
    }

    if (currentValue) {
      currentValue.textContent = brl(ultimo.inad_total || 0);
    }

    if (variation) {
      variation.textContent =
        variacaoPct === null
          ? "-"
          : `${variacaoPct >= 0 ? "+" : ""}${pct(variacaoPct)}%`;
    }

    if (grouping) {
      grouping.textContent =
        groupBy === "month" ? "Mensal + acumulado" : getGroupByLabel(groupBy);
    }

    destroyTrendChart();

    const datasets = [
      {
        label: "Inadimplência mensal",
        data: valores,
        tension: 0.35,
        fill: false,
        borderWidth: 3,
        pointRadius: 4,
        pointHoverRadius: 6,
        pointHitRadius: 12,
      },
    ];

    if (groupBy === "month") {
      datasets.push({
        label: "Acumulado",
        data: acumulado,
        tension: 0.35,
        fill: false,
        borderWidth: 2,
        borderDash: [4, 4],
        pointRadius: 0,
        pointHoverRadius: 4,
        pointHitRadius: 10,
      });
    }

    state.chart = new Chart(canvas, {
      type: "line",
      data: {
        labels,
        datasets,
      },
      plugins: window.ChartDataLabels ? [window.ChartDataLabels] : [],
      options: {
        responsive: true,
        maintainAspectRatio: false,
        clip: false,
        interaction: {
          mode: "index",
          intersect: false,
        },
        layout: {
          padding: {
            top: 28,
            right: 12,
            bottom: 0,
            left: 8,
          },
        },
        plugins: {
          legend: {
            display: false,
          },
          datalabels: {
            display(context) {
              return context.datasetIndex === 0;
            },
            clamp: true,
            clip: false,
            align: "top",
            anchor: "end",
            offset: 8,
            color: "#111827",
            font: {
              weight: "700",
              size: 10,
            },
            formatter(value) {
              const n = Number(value || 0);
              return brl(n);
            },
          },
          tooltip: {
            backgroundColor: "#111827",
            titleColor: "#ffffff",
            bodyColor: "#ffffff",
            borderColor: "rgba(255,255,255,0.08)",
            borderWidth: 1,
            padding: 10,
            displayColors: true,
            callbacks: {
              label(context) {
                return `${context.dataset.label}: ${brl(context.raw || 0)}`;
              },
            },
          },
        },
        scales: {
          x: {
            grid: {
              display: false,
              drawBorder: false,
            },
            ticks: {
              maxRotation: 0,
              autoSkip: true,
              maxTicksLimit: 8,
            },
          },
          y: {
            beginAtZero: true,
            suggestedMax: Math.max(...(groupBy === "month" ? acumulado : valores)) * 1.15,
            grid: {
              color: "rgba(148, 163, 184, 0.18)",
              drawBorder: false,
            },
            ticks: {
              stepSize: 100000,
              padding: 8,
              callback(value) {
                const n = Number(value || 0);
                return `R$ ${(n / 1000).toFixed(0)} mil`;
              },
            },
          },
        },
        elements: {
          line: {
            capBezierPoints: true,
          },
        },
      },
    });
  }

  function buildQuery(force = false) {
    const params = new URLSearchParams();

    params.set("preset", state.selectedPreset || "6m");
    params.set("group_by", "auto");

    if (force) params.set("force", "1");

    const dateFrom = $("#filterDateFrom")?.value?.trim();
    const dateTo = $("#filterDateTo")?.value?.trim();
    const vendedor = $("#filterVendedor")?.value?.trim();
    const supervisor = $("#filterSupervisor")?.value?.trim();
    const faixa = $("#filterFaixa")?.value?.trim();
    const valorMin = $("#filterValorMin")?.value?.trim();

    if (dateFrom) params.set("date_from", dateFrom);
    if (dateTo) params.set("date_to", dateTo);
    if (vendedor) params.set("vendedor", vendedor);
    if (supervisor) params.set("supervisor", supervisor);
    if (faixa) params.set("faixa_atraso", faixa);
    if (valorMin) params.set("valor_min", valorMin);

    const diasAtraso = $("#filterDiasAtraso")?.value?.trim();

    if (diasAtraso !== "" && diasAtraso !== null) {
      params.set("dias_min_atraso", diasAtraso);
    }

    return params.toString();
  }

  function getTableFilters() {
    return {
      busca: ($("#filtroBusca")?.value?.trim() || "").toLowerCase(),
      vendedor: ($("#filtroVendedorTabela")?.value?.trim() || "").toLowerCase(),
      supervisor: ($("#filtroSupervisorTabela")?.value?.trim() || "").toLowerCase(),
      faixa: $("#filtroFaixaTabela")?.value?.trim() || "",
      valorMin: toNumber($("#filtroValorMinTabela")?.value || 0),
      status: ($("#filtroStatusTabela")?.value?.trim() || "").toLowerCase(),
    };
  }

  function decorateClientes(clientes) {
    const totalInad = sum(clientes, (c) => c.inad_total);

    return (clientes || []).map((cli) => {
      const inad = toNumber(cli.inad_total);
      const fat = toNumber(cli.faturado_periodo);
      const risk = getRiskMeta(cli);
      const clienteKey = cli.cliente_key || `${cli.cliente || ""}|${cli.loja || ""}`;

      return {
        ...cli,
        cliente_key: clienteKey,
        participacao_total_pct: totalInad > 0 ? (inad / totalInad) * 100 : 0,
        faixa_principal: getMaiorFaixa(cli.titulos),
        risk_label: risk.label,
        risk_score: risk.score,
        risk_class: risk.className,
        faturado_periodo: fat,
        inad_total: inad,
        indice_inadimplencia_pct: toNumber(cli.indice_inadimplencia_pct),
        inad_qtd_titulos: toNumber(cli.inad_qtd_titulos),
        maior_atraso_dias: toNumber(cli.maior_atraso_dias),
      };
    });
  }

  async function loadData(force = false) {
    return withLoader(async () => {
      const qs = buildQuery(force);
      const url = `/api/inadimplentes-data.php${qs ? `?${qs}` : ""}`;

      const resp = await fetch(url, { cache: "no-store" });
      const text = await resp.text();

      let data;
      try {
        data = JSON.parse(text);
      } catch (e) {
        console.error("Resposta inválida da API:", text);
        throw new Error("A API não retornou JSON válido.");
      }

      if (!resp.ok || data.success === false) {
        console.error(data);
        throw new Error(data.message || `Erro HTTP ${resp.status}`);
      }

      const clientesRaw = Array.isArray(data.clientes) ? data.clientes : [];
      state.clientes = decorateClientes(clientesRaw);
      state.payload = { ...data, clientes: state.clientes };

      syncPresetFromPayload(state.payload);
      render(state.payload);
    });
  }

  function fillSelect(selector, items, firstLabel = "Todos") {
    const el = document.querySelector(selector);
    if (!el) return;

    const current = el.value;

    el.innerHTML =
      `<option value="">${firstLabel}</option>` +
      (items || [])
        .map((item) => {
          if (typeof item === "object" && item !== null) {
            return `<option value="${esc(item.value)}">${esc(item.label)}</option>`;
          }

          return `<option value="${esc(item)}">${esc(item)}</option>`;
        })
        .join("");

    if (current) el.value = current;
  }

  function buildAgingData(clientes) {
    const map = {
      "1-30": { faixa: "1-30", valor: 0, titulos: 0, clientes: new Set() },
      "31-60": { faixa: "31-60", valor: 0, titulos: 0, clientes: new Set() },
      "61-90": { faixa: "61-90", valor: 0, titulos: 0, clientes: new Set() },
      "91-180": { faixa: "91-180", valor: 0, titulos: 0, clientes: new Set() },
      "180+": { faixa: "180+", valor: 0, titulos: 0, clientes: new Set() },
    };

    (clientes || []).forEach((cli) => {
      const key = cli.cliente_key || `${cli.cliente || ""}-${cli.loja || ""}`;
      const titulos = Array.isArray(cli.titulos) ? cli.titulos : [];

      titulos.forEach((t) => {
        const faixa = String(t.faixa_atraso || "").trim();
        if (!map[faixa]) return;

        map[faixa].valor += toNumber(t.saldo || t.valor || 0);
        map[faixa].titulos += 1;
        map[faixa].clientes.add(key);
      });
    });

    return FAIXAS.map((faixa) => ({
      faixa,
      valor: map[faixa].valor,
      titulos: map[faixa].titulos,
      clientes: map[faixa].clientes.size,
    }));
  }

  function buildCarteiraRanking(clientes, groupKeyName, groupLabelName) {
    const map = new Map();

    (clientes || []).forEach((cli) => {
      const code = String(cli[groupKeyName] || "").trim();
      const rawName = String(cli[groupLabelName] || "").trim();
      const displayName = onlyNameOrCode(rawName, code);
      const key = code || displayName || "SEM-CADASTRO";

      if (!map.has(key)) {
        map.set(key, {
          key,
          code,
          nome: displayName,
          inad_total: 0,
          faturado_periodo: 0,
          clientes: 0,
          titulos: 0,
          maior_atraso_dias: 0,
        });
      }

      const row = map.get(key);
      row.inad_total += toNumber(cli.inad_total);
      row.faturado_periodo += toNumber(cli.faturado_periodo);
      row.clientes += 1;
      row.titulos += toNumber(cli.inad_qtd_titulos);
      row.maior_atraso_dias = Math.max(
        row.maior_atraso_dias,
        toNumber(cli.maior_atraso_dias)
      );
    });

    return [...map.values()]
      .map((row) => ({
        ...row,
        indice_inadimplencia_pct:
          row.faturado_periodo > 0 ? (row.inad_total / row.faturado_periodo) * 100 : 0,
        risk_class: riskClass(
          row.faturado_periodo > 0 ? (row.inad_total / row.faturado_periodo) * 100 : 0,
          row.inad_total,
          row.maior_atraso_dias
        ),
      }))
      .sort((a, b) => b.inad_total - a.inad_total)
      .slice(0, 10);
  }

  function buildConcentration(clientes) {
    const sorted = [...(clientes || [])].sort((a, b) => b.inad_total - a.inad_total);
    const total = sum(sorted, (c) => c.inad_total);

    const calc = (n) => {
      const subtotal = sum(sorted.slice(0, n), (c) => c.inad_total);
      return {
        n,
        subtotal,
        pct: total > 0 ? (subtotal / total) * 100 : 0,
      };
    };

    return [calc(5), calc(10), calc(20)];
  }

  function buildInsights(clientes) {
    const insights = [];
    const totalInad = sum(clientes, (c) => c.inad_total);
    const totalFat = sum(clientes, (c) => c.faturado_periodo);
    const pctSobreFat = totalFat > 0 ? (totalInad / totalFat) * 100 : 0;
    const top10 = buildConcentration(clientes)[1];
    const aging = buildAgingData(clientes);
    const maioresFaixas = [...aging].sort((a, b) => b.valor - a.valor);
    const faixaDominante = maioresFaixas[0];
    const vendedorTop = buildCarteiraRanking(clientes, "vendedor_codigo", "vendedor_nome")[0];
    const supervisorTop = buildCarteiraRanking(clientes, "supervisor_codigo", "supervisor_nome")[0];
    const riscoAlto = clientes.filter((c) => c.risk_score >= 3);
    const criticos = clientes.filter((c) => c.risk_score >= 4);
    const clientesComFatEAltoRisco = clientes.filter(
      (c) => toNumber(c.faturado_periodo) > 0 && toNumber(c.maior_atraso_dias) > 90
    );

    insights.push(
      `A inadimplência total representa <strong>${pct(pctSobreFat)}%</strong> do faturamento considerado no período.`
    );
    insights.push(
      `Os <strong>10 maiores inadimplentes</strong> concentram <strong>${pct(
        top10?.pct || 0
      )}%</strong> do saldo vencido.`
    );

    if (faixaDominante && faixaDominante.valor > 0) {
      insights.push(
        `A faixa de atraso <strong>${esc(
          faixaDominante.faixa
        )} dias</strong> concentra o maior volume: <strong>${brl(
          faixaDominante.valor
        )}</strong>.`
      );
    }

    if (vendedorTop) {
      insights.push(
        `O vendedor com maior carteira inadimplente é <strong>${esc(
          vendedorTop.nome
        )}</strong>, com <strong>${brl(vendedorTop.inad_total)}</strong>.`
      );
    }

    if (supervisorTop) {
      insights.push(
        `O supervisor com maior exposição é <strong>${esc(
          supervisorTop.nome
        )}</strong>, com <strong>${brl(supervisorTop.inad_total)}</strong>.`
      );
    }

    insights.push(
      `Existem <strong>${num(riscoAlto.length)}</strong> clientes em risco alto/crítico, sendo <strong>${num(
        criticos.length
      )}</strong> em nível crítico.`
    );

    if (clientesComFatEAltoRisco.length > 0) {
      insights.push(
        `<strong>${num(
          clientesComFatEAltoRisco.length
        )}</strong> clientes faturaram no período e possuem atraso superior a 90 dias, exigindo atenção comercial e financeira.`
      );
    }

    return insights.slice(0, 6);
  }

  function render(data) {
    const clientes = Array.isArray(data.clientes) ? data.clientes : [];

    state.totals = {
      inad: sum(clientes, (c) => c.inad_total),
      faturado: sum(clientes, (c) => c.faturado_periodo),
      titulos: sum(clientes, (c) => c.inad_qtd_titulos),
    };

    renderKpis(data.kpis || {}, clientes);
    renderTrendChart(data);
    renderInsights(clientes);
    renderTopInad(
      data.top_inadimplentes?.length
        ? decorateClientes(data.top_inadimplentes)
        : clientes.slice().sort((a, b) => b.inad_total - a.inad_total).slice(0, 50)
    );
    renderTopFat(
      data.top_faturados?.length
        ? decorateClientes(data.top_faturados)
        : clientes.slice().sort((a, b) => b.faturado_periodo - a.faturado_periodo).slice(0, 50)
    );
    renderConcentracao(clientes);
    renderAging(clientes);
    renderRankingCarteira(
      "#rankingVendedor",
      buildCarteiraRanking(clientes, "vendedor_codigo", "vendedor_nome")
    );
    renderRankingCarteira(
      "#rankingSupervisor",
      buildCarteiraRanking(clientes, "supervisor_codigo", "supervisor_nome")
    );

    const clientesTabela = filtrarClientesTabela(clientes);
    renderClientes(clientesTabela);

    if ($("#updatedAt")) {
      $("#updatedAt").textContent = `Atualizado em ${data.updated_at || "-"}`;
    }

    fillSelect("#filterVendedor", data.options?.vendedores || [], "Todos");
    fillSelect("#filterSupervisor", data.options?.supervisores || [], "Todos");
  }

  function renderKpis(kpis, clientes) {
    const totalInad = sum(clientes, (c) => c.inad_total) || toNumber(kpis.total_inadimplente);
    const totalFat = sum(clientes, (c) => c.faturado_periodo);
    const totalTitulos = sum(clientes, (c) => c.inad_qtd_titulos) || toNumber(kpis.total_titulos);
    const clientesInad = clientes.length || toNumber(kpis.clientes_inadimplentes);
    const ticket =
      clientesInad > 0 ? totalInad / clientesInad : toNumber(kpis.ticket_medio_inadimplencia);
    const mediaDias =
      clientesInad > 0
        ? clientes.reduce((acc, cli) => acc + toNumber(cli.maior_atraso_dias), 0) / clientesInad
        : toNumber(kpis.media_dias_atraso);
    const pctSobreFat = totalFat > 0 ? (totalInad / totalFat) * 100 : 0;
    const top10 = buildConcentration(clientes)[1];
    const riscoAlto = clientes.filter((c) => c.risk_score >= 3).length;

    if ($("#kpiTotalInad")) $("#kpiTotalInad").textContent = brl(totalInad);
    if ($("#kpiClientesInad")) $("#kpiClientesInad").textContent = num(clientesInad);
    if ($("#kpiTitulos")) $("#kpiTitulos").textContent = num(totalTitulos);
    if ($("#kpiTicket")) $("#kpiTicket").textContent = brl(ticket);
    if ($("#kpiDias")) $("#kpiDias").textContent = `${num(Math.round(mediaDias))} dias`;
    if ($("#kpiPctSobreFat")) $("#kpiPctSobreFat").textContent = `${pct(pctSobreFat)}%`;
    if ($("#kpiTop10Pct")) $("#kpiTop10Pct").textContent = `${pct(top10?.pct || 0)}%`;
    if ($("#kpiRiscoAlto")) $("#kpiRiscoAlto").textContent = num(riscoAlto);
  }

  function renderInsights(clientes) {
    const el = $("#insightsList");
    if (!el) return;

    const insights = buildInsights(clientes);
    if (!insights.length) {
      el.innerHTML = `<div class="empty">Nenhum insight disponível.</div>`;
      return;
    }

    el.innerHTML = insights.map((item) => `<div class="insight-item">${item}</div>`).join("");
  }

  function renderTopInad(items) {
    const el = $("#topInadList");
    if (!el) return;

    if (!items.length) {
      el.innerHTML = `<div class="empty">Nenhum cliente inadimplente encontrado.</div>`;
      return;
    }

    el.innerHTML = items
      .sort((a, b) => b.inad_total - a.inad_total)
      .slice(0, 50)
      .map(
        (cli, idx) => `
      <button type="button" class="ranking-item ranking-item--click" data-key="${esc(
          cli.cliente_key
        )}">
        <div class="ranking-left">
          <span class="ranking-pos">${idx + 1}</span>
          <div>
            <strong>${esc(cli.nome || "Cliente sem nome")}</strong>
            <small>Cliente ${esc(cli.cliente || "-")} / Loja ${esc(cli.loja || "0001")}</small>
          </div>
        </div>
        <div class="ranking-right">
          <strong>${brl(cli.inad_total)}</strong>
          <small>${num(cli.inad_qtd_titulos)} títulos</small>
        </div>
      </button>
    `
      )
      .join("");
  }

  function renderTopFat(items) {
    const el = $("#topFatList");
    if (!el) return;

    if (!items.length) {
      el.innerHTML = `<div class="empty">Nenhum faturamento encontrado.</div>`;
      return;
    }

    el.innerHTML = items
      .sort((a, b) => b.faturado_periodo - a.faturado_periodo)
      .slice(0, 50)
      .map((cli, idx) => {
        const pctInad = toNumber(cli.indice_inadimplencia_pct);
        const inad = toNumber(cli.inad_total);
        const fat = toNumber(cli.faturado_periodo);
        const pedidos = toNumber(cli.qtd_pedidos || 0);

        const nomeCliente =
          cli.nome && cli.nome !== "CLIENTE NÃO IDENTIFICADO"
            ? cli.nome
            : `Cliente ${cli.cliente || "-"}`;

        return `
        <button type="button"
                class="ranking-item ranking-item--fat ${esc(
          riskClass(pctInad, inad, cli.maior_atraso_dias)
        )}"
                data-key="${esc(cli.cliente_key)}">
          <div class="ranking-left">
            <span class="ranking-pos">${idx + 1}</span>
            <div>
              <strong>${esc(nomeCliente)}</strong>
              <div class="fat-extra">
                <span class="fat-chip">Inad.: ${brl(inad)}</span>
                <span class="fat-chip fat-chip--pct">% Inad.: ${pct(pctInad)}%</span>
                <span class="fat-chip">${esc(getRiskMeta(cli).label)}</span>
              </div>
            </div>
          </div>

          <div class="ranking-right">
            <strong>${brl(fat)}</strong>
            <small>${num(pedidos)} pedidos</small>
          </div>
        </button>
      `;
      })
      .join("");
  }

  function renderConcentracao(clientes) {
    const el = $("#concentracaoList");
    if (!el) return;

    const itens = buildConcentration(clientes);

    el.innerHTML = itens
      .map(
        (item) => `
        <div class="metric-item">
          <div class="metric-main">
            <strong>Top ${item.n}</strong>
            <span>${brl(item.subtotal)}</span>
          </div>
          <div class="progress-line">
            <span style="width:${Math.max(0, Math.min(100, item.pct))}%;"></span>
          </div>
          <small>${pct(item.pct)}% do total inadimplente</small>
        </div>
      `
      )
      .join("");
  }

  function renderAging(clientes) {
    const el = $("#agingList");
    if (!el) return;

    const items = buildAgingData(clientes);
    const total = sum(items, (i) => i.valor);

    if (!items.length || total <= 0) {
      el.innerHTML = `<div class="empty">Nenhuma faixa de atraso encontrada.</div>`;
      return;
    }

    el.innerHTML = items
      .map((item) => {
        const percent = total > 0 ? (item.valor / total) * 100 : 0;
        return `
          <div class="aging-item">
            <div class="aging-head">
              <strong>${esc(item.faixa)} dias</strong>
              <span>${pct(percent)}%</span>
            </div>
            <div class="progress-line">
              <span style="width:${Math.max(0, Math.min(100, percent))}%;"></span>
            </div>
            <div class="aging-meta">
              <small>${brl(item.valor)}</small>
              <small>${num(item.titulos)} títulos • ${num(item.clientes)} clientes</small>
            </div>
          </div>
        `;
      })
      .join("");
  }

  function renderRankingCarteira(selector, items) {
    const el = $(selector);
    if (!el) return;

    if (!items.length) {
      el.innerHTML = `<div class="empty">Nenhum dado encontrado.</div>`;
      return;
    }

    el.innerHTML = items
      .map(
        (item, idx) => `
        <div class="ranking-item ${esc(item.risk_class)}">
          <div class="ranking-left">
            <span class="ranking-pos">${idx + 1}</span>
            <div>
              <strong>${esc(item.nome || "-")}</strong>
              <small>${num(item.clientes)} clientes • ${num(item.titulos)} títulos</small>
            </div>
          </div>
          <div class="ranking-right">
            <strong>${brl(item.inad_total)}</strong>
            <small>${pct(item.indice_inadimplencia_pct)}%</small>
          </div>
        </div>
      `
      )
      .join("");
  }

  function compareValues(a, b, field) {
    const numFields = [
      "inad_total",
      "faturado_periodo",
      "indice_inadimplencia_pct",
      "participacao_total_pct",
      "inad_qtd_titulos",
      "maior_atraso_dias",
      "risk_score",
    ];

    if (numFields.includes(field)) {
      return toNumber(a?.[field]) - toNumber(b?.[field]);
    }

    const va = String(a?.[field] || "").toLowerCase().trim();
    const vb = String(b?.[field] || "").toLowerCase().trim();

    return va.localeCompare(vb, "pt-BR");
  }

  function sortClientes(items) {
    const arr = [...(items || [])];
    const { field, dir } = state.sortTable;

    arr.sort((a, b) => {
      const cmp = compareValues(a, b, field);
      return dir === "asc" ? cmp : -cmp;
    });

    return arr;
  }

  function updateSortIndicators() {
    document.querySelectorAll(".th-sort").forEach((th) => {
      const field = th.dataset.sort;
      const indicator = th.querySelector(".sort-indicator");
      if (!indicator) return;

      th.classList.remove("is-active");
      indicator.textContent = "";

      if (field === state.sortTable.field) {
        th.classList.add("is-active");
        indicator.textContent = state.sortTable.dir === "asc" ? "▲" : "▼";
      }
    });
  }

  function renderClientes(items) {
    const tbody = $("#clientesTable");
    if (!tbody) return;

    const ordenados = sortClientes(items);

    if (!ordenados.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="11" class="td-empty">Nenhum cliente encontrado com os filtros aplicados.</td>
        </tr>
      `;
      updateSortIndicators();
      return;
    }

    tbody.innerHTML = ordenados
      .map((cli) => {
        const risk = getRiskMeta(cli);
        return `
        <tr>
          <td title="${esc(cli.nome || "Cliente sem nome")}">${esc(cli.nome || "Cliente sem nome")}</td>
          <td title="${esc(onlyNameOrCode(cli.vendedor_nome, cli.vendedor_codigo))}">
            ${esc(onlyNameOrCode(cli.vendedor_nome, cli.vendedor_codigo))}
          </td>
          <td title="${esc(onlyNameOrCode(cli.supervisor_nome, cli.supervisor_codigo))}">
            ${esc(onlyNameOrCode(cli.supervisor_nome, cli.supervisor_codigo))}
          </td>
          <td>${brl(cli.inad_total)}</td>
          <td>${brl(cli.faturado_periodo)}</td>
          <td>${pct(cli.indice_inadimplencia_pct)}%</td>
          <td>${pct(cli.participacao_total_pct)}%</td>
          <td>${num(cli.inad_qtd_titulos)}</td>
          <td>${num(cli.maior_atraso_dias)} dias</td>
          <td>
            <span class="risk-badge ${esc(risk.className)}">${esc(risk.label)}</span>
          </td>
          <td>
            <button type="button" class="btn-detail" data-key="${esc(cli.cliente_key)}">Ver títulos</button>
          </td>
        </tr>
      `;
      })
      .join("");

    updateSortIndicators();
  }

  function openCliente(clienteKey) {
    const cli = state.clientes.find((c) => c.cliente_key === clienteKey);
    if (!cli) return;

    const risk = getRiskMeta(cli);

    if ($("#modalClienteNome")) {
      $("#modalClienteNome").textContent = cli.nome || "Cliente";
    }

    if ($("#modalClienteResumo")) {
      $("#modalClienteResumo").textContent =
        `Cliente ${cli.cliente || "-"} / Loja ${cli.loja || "0001"} • Inadimplente: ${brl(
          cli.inad_total
        )} • Faturado: ${brl(cli.faturado_periodo)}`;
    }

    if ($("#modalSummary")) {
      $("#modalSummary").innerHTML = `
        <div class="modal-summary-grid">
          <div class="mini-kpi">
            <span>Status</span>
            <strong>${esc(risk.label)}</strong>
          </div>
          <div class="mini-kpi">
            <span>% inad.</span>
            <strong>${pct(cli.indice_inadimplencia_pct)}%</strong>
          </div>
          <div class="mini-kpi">
            <span>Títulos</span>
            <strong>${num(cli.inad_qtd_titulos)}</strong>
          </div>
          <div class="mini-kpi">
            <span>Maior atraso</span>
            <strong>${num(cli.maior_atraso_dias)} dias</strong>
          </div>
          <div class="mini-kpi">
            <span>Faixa principal</span>
            <strong>${esc(cli.faixa_principal || "-")}</strong>
          </div>
          <div class="mini-kpi">
            <span>Participação</span>
            <strong>${pct(cli.participacao_total_pct)}%</strong>
          </div>
        </div>
      `;
    }

    const tbody = $("#modalTitulosBody");
    const titulos = Array.isArray(cli.titulos) ? cli.titulos : [];

    if (tbody) {
      if (!titulos.length) {
        tbody.innerHTML = `
          <tr>
            <td colspan="9" class="td-empty">Nenhum título encontrado para este cliente.</td>
          </tr>
        `;
      } else {
        tbody.innerHTML = titulos
          .sort((a, b) => toNumber(b.dias_atraso) - toNumber(a.dias_atraso))
          .map(
            (t) => `
          <tr>
            <td>${esc(t.titulo_composto || "-")}</td>
            <td>${esc(t.tipo || "-")}</td>
            <td>${esc(t.emissao_fmt || "-")}</td>
            <td>${esc(t.vencto_fmt || "-")}</td>
            <td>${num(t.dias_atraso)} dias</td>
            <td>${esc(t.faixa_atraso || "-")}</td>
            <td>${brl(t.valor)}</td>
            <td>${brl(t.saldo)}</td>
            <td>${esc(t.forma_pagamento || "-")}</td>
          </tr>
        `
          )
          .join("");
      }
    }

    const modal = $("#modalCliente");
    if (!modal) return;

    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
  }

  function closeModal() {
    const modal = $("#modalCliente");
    if (!modal) return;

    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
  }

  function clearTopFilters() {
    [
      "#filterDateFrom",
      "#filterDateTo",
      "#filterVendedor",
      "#filterSupervisor",
      "#filterFaixa",
      "#filterValorMin",
    ].forEach((sel) => {
      const el = $(sel);
      if (el) el.value = "";
    });
  }

  function resetPresetToDefault() {
    state.selectedPreset = "6m";
    setActivePresetButton("6m");
  }

  function filtrarClientesTabela(items) {
    const filtros = getTableFilters();

    return (items || []).filter((cli) => {
      const inad = toNumber(cli.inad_total);
      const vendedorNome = String(cli.vendedor_nome || "").toLowerCase();
      const vendedorCod = String(cli.vendedor_codigo || "").toLowerCase();
      const supervisorNome = String(cli.supervisor_nome || "").toLowerCase();
      const supervisorCod = String(cli.supervisor_codigo || "").toLowerCase();
      const status = String(cli.risk_label || "").toLowerCase();

      const haystack = [
        cli.nome || "",
        cli.cliente || "",
        cli.cnpj || "",
        cli.vendedor_nome || "",
        cli.vendedor_codigo || "",
        cli.supervisor_nome || "",
        cli.supervisor_codigo || "",
      ]
        .join(" ")
        .toLowerCase();

      if (filtros.valorMin > 0 && inad < filtros.valorMin) return false;
      if (filtros.busca && !haystack.includes(filtros.busca)) return false;

      if (
        filtros.vendedor &&
        !vendedorNome.includes(filtros.vendedor) &&
        !vendedorCod.includes(filtros.vendedor)
      ) {
        return false;
      }

      if (
        filtros.supervisor &&
        !supervisorNome.includes(filtros.supervisor) &&
        !supervisorCod.includes(filtros.supervisor)
      ) {
        return false;
      }

      if (filtros.status && status !== filtros.status) {
        return false;
      }

      if (filtros.faixa) {
        const titulos = Array.isArray(cli.titulos) ? cli.titulos : [];
        const okFaixa = titulos.some(
          (t) => String(t.faixa_atraso || "") === filtros.faixa
        );

        if (!okFaixa) return false;
      }

      return true;
    });
  }

  function clearTableFilters() {
    [
      "#filtroBusca",
      "#filtroVendedorTabela",
      "#filtroSupervisorTabela",
      "#filtroFaixaTabela",
      "#filtroValorMinTabela",
      "#filtroStatusTabela",
    ].forEach((sel) => {
      const el = $(sel);
      if (el) el.value = "";
    });
  }

  function rerenderTableOnly() {
    const clientesTabela = filtrarClientesTabela(state.clientes);
    renderClientes(clientesTabela);
  }

  function handleLoadError(err) {
    console.error(err);
    destroyTrendChart();

    if ($("#updatedAt")) {
      $("#updatedAt").textContent = "Erro ao carregar dados";
    }

    const subtitle = document.getElementById("chartSubtitle");
    if (subtitle) {
      subtitle.textContent = "Erro ao carregar tendência da inadimplência.";
    }
  }

  function parseEmails(value) {
    return String(value || "")
      .split(/[;,]/)
      .map((v) => v.trim())
      .filter(Boolean);
  }

  function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || "").trim());
  }

  function getClientesParaAviso() {
    return filtrarClientesTabela(state.clientes || [])
      .slice()
      .sort((a, b) => toNumber(b.inad_total) - toNumber(a.inad_total));
  }

  function limparNomePessoa(texto) {
    return String(texto || "")
      .replace(/^\d+\s*[-=>]\s*/g, "")
      .replace(/^\d+\s+/g, "")
      .trim();
  }

  function pegarPrimeirosDoisNomes(texto) {
    const partes = limparNomePessoa(texto).split(/\s+/).filter(Boolean);
    if (!partes.length) return "";
    return partes.slice(0, 2).join(" ");
  }

  function getCodigoVendedorAtual() {
    const clientes = getClientesParaAviso();

    const codigos = [
      ...new Set(
        clientes
          .map((c) => String(c.vendedor_codigo || c.E1_VEND1 || "").trim())
          .filter(Boolean)
      ),
    ];

    if (codigos.length === 1) {
      return codigos[0];
    }

    return "";
  }

  function getVendedor080Atual() {
    const codigo = getCodigoVendedorAtual();
    if (!codigo) return null;

    return (
      (state.vendedores080 || []).find(
        (v) => String(v.codigo || "").trim() === codigo
      ) || null
    );
  }

  function getNomeVendedorContexto() {
    const vendedor080 = getVendedor080Atual();
    if (vendedor080?.nome) {
      return pegarPrimeirosDoisNomes(vendedor080.nome) || limparNomePessoa(vendedor080.nome);
    }

    const clientes = getClientesParaAviso();

    const nomesUnicos = [
      ...new Set(
        clientes
          .map((c) => limparNomePessoa(c.vendedor_nome || c.vendedor || ""))
          .filter(Boolean)
      ),
    ];

    if (nomesUnicos.length === 1) {
      return pegarPrimeirosDoisNomes(nomesUnicos[0]);
    }

    const filtroDigitado = ($("#filterVendedor")?.value || "").trim();
    if (filtroDigitado) {
      return pegarPrimeirosDoisNomes(filtroDigitado) || filtroDigitado;
    }

    return "Carteira geral";
  }

  function getContextoEmail() {
    return getNomeVendedorContexto();
  }

  function getEmailVendedorAtual() {
    const vendedor080 = getVendedor080Atual();
    return String(vendedor080?.email || "").trim();
  }

  function getSupervisorAtual() {
    const clientes = getClientesParaAviso();

    const supervisores = [
      ...new Map(
        clientes
          .map((c) => {
            const codigo = String(c.supervisor_codigo || "").trim();
            const nome = limparNomePessoa(c.supervisor_nome || "");
            if (!codigo && !nome) return null;

            return [
              codigo || nome,
              {
                codigo,
                nome,
              },
            ];
          })
          .filter(Boolean)
      ).values(),
    ];

    if (supervisores.length === 1) {
      return supervisores[0];
    }

    const filtroSupervisor = ($("#filterSupervisor")?.value || "").trim();
    if (filtroSupervisor) {
      return {
        codigo: "",
        nome: filtroSupervisor,
      };
    }

    return {
      codigo: "",
      nome: "",
    };
  }

  function getSupervisorEmailMap() {
    return {
      "000119": "dulvano.barcelos@popper.com.br",
      "000111": "nathan.mattos@popper.com.br",
      "000115": "luiza.bechtloff@popper.com.br",
      "000001": "demetrio.chaim@popper.com.br",
    };
  }

  function getSupervisorEmailAtual() {
    const supervisor = getSupervisorAtual();
    const codigo = String(supervisor?.codigo || "").trim();

    if (!codigo) return "";

    const mapa = getSupervisorEmailMap();
    return String(mapa[codigo] || "").trim();
  }

  function autoPreencherEnviarPara() {
    const inputPara = $("#emailDestinatario");
    if (!inputPara) return;

    const email = getEmailVendedorAtual();
    inputPara.value = email || "";
  }

  function getEmailsCcPadrao() {
    const emails = [
      "paulo.machado@popper.com.br",
      "giuliana.paulino@popper.com.br",
      "tiago.legnani@tufflog.com.br",
      "yasmim.santos@tufflog.com.br",
    ];

    const emailSupervisor = getSupervisorEmailAtual();
    if (emailSupervisor) {
      emails.push(emailSupervisor);
    }

    return [...new Set(
      emails
        .map((v) => String(v || "").trim())
        .filter(Boolean)
    )];
  }

  function autoPreencherCc() {
    const inputCc = $("#emailCc");
    if (!inputCc) return;

    const ccPadrao = getEmailsCcPadrao();
    inputCc.value = ccPadrao.join("; ");
  }

  function buildEmailSubject(clientes) {
    const vendedor = getContextoEmail();
    return `Aviso de inadimplência - ${vendedor}: ${num(clientes.length)} cliente(s)`;
  }

  function buildEmailHtml(clientes, mensagemExtra = "") {
    const totalInad = sum(clientes, (c) => c.inad_total);
    const totalTitulos = sum(clientes, (c) => c.inad_qtd_titulos);
    const vendedor = getContextoEmail();

    const linhas = clientes
      .map(
        (cli) => `
      <tr>
        <td style="padding:12px 10px; border-bottom:1px solid #e5e7eb; color:#1f2937; vertical-align:top;">
          ${esc(cli.cliente || "-")}
        </td>
        <td style="padding:12px 10px; border-bottom:1px solid #e5e7eb; color:#1f2937; vertical-align:top; font-weight:600;">
          ${esc(cli.nome || "-")}
        </td>
        <td style="padding:12px 10px; border-bottom:1px solid #e5e7eb; color:#1f2937; vertical-align:top;">
          ${esc(onlyNameOrCode(cli.vendedor_nome, cli.vendedor_codigo))}
        </td>
        <td style="padding:12px 10px; border-bottom:1px solid #e5e7eb; color:#1f2937; vertical-align:top;">
          ${esc(onlyNameOrCode(cli.supervisor_nome, cli.supervisor_codigo))}
        </td>
        <td style="padding:12px 10px; border-bottom:1px solid #e5e7eb; color:#b91c1c; vertical-align:top; font-weight:700; white-space:nowrap;">
          ${brl(cli.inad_total)}
        </td>
        <td style="padding:12px 10px; border-bottom:1px solid #e5e7eb; color:#1f2937; vertical-align:top; text-align:center;">
          ${num(cli.inad_qtd_titulos)}
        </td>
        <td style="padding:12px 10px; border-bottom:1px solid #e5e7eb; color:#1f2937; vertical-align:top; white-space:nowrap;">
          ${num(cli.maior_atraso_dias)} dias
        </td>
      </tr>
    `
      )
      .join("");

    const extra = String(mensagemExtra || "").trim()
      ? `
      <div style="margin:0 0 18px; padding:14px 16px; background:#fff7ed; border:1px solid #fed7aa; border-radius:12px; color:#9a3412;">
        ${esc(mensagemExtra).replace(/\n/g, "<br>")}
      </div>
    `
      : "";

    return `
  <div style="margin:0; padding:24px 12px; background:#f4f6fb;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0">
      <tr>
        <td align="center">
          <table width="1120" cellpadding="0" cellspacing="0" border="0" style="width:1120px; max-width:1120px; background:#ffffff; border-radius:16px; overflow:hidden;">
            <tr>
              <td style="background:#f8fafc; border-bottom:1px solid #e5e7eb; padding:20px 24px;">
                <table width="100%">
                  <tr>
                    <td style="vertical-align:middle;">
                      <div style="font-size:12px; color:#6b7280; margin-bottom:6px;">
                        COMUNICADO AUTOMÁTICO • INADIMPLÊNCIA
                      </div>

                      <div style="font-size:22px; font-weight:800; color:#111827;">
                        Aviso de inadimplência
                      </div>

                      <div style="margin-top:6px; font-size:14px; color:#4b5563;">
                        Carteira vinculada a <strong>${esc(vendedor)}</strong>
                      </div>
                    </td>

                    <td align="right" style="vertical-align:middle;">
                      <img src="https://popperconecta.com.br/assets/img/logo.png" alt="Popper Conecta" style="max-height:48px;">
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

            <tr>
              <td style="padding:24px;">
                <p style="margin:0 0 12px; color:#374151;">Olá,</p>

                <p style="margin:0 0 12px; color:#374151;">
                  Segue abaixo a relação de <strong>clientes inadimplentes</strong> vinculados à carteira de
                  <strong>${esc(vendedor)}</strong>.
                </p>

                <p style="margin:0 0 20px; color:#374151;">
                  Pedimos a gentileza de verificar os casos listados e seguir com as tratativas necessárias.
                </p>

                ${extra}

                <table width="100%" style="margin-bottom:20px;">
                  <tr>
                    <td style="padding:10px;">
                      <div style="border:1px solid #e5e7eb; border-radius:12px; padding:14px;">
                        <div style="font-size:12px; color:#6b7280;">Clientes</div>
                        <div style="font-size:22px; font-weight:800;">${num(clientes.length)}</div>
                      </div>
                    </td>

                    <td style="padding:10px;">
                      <div style="border:1px solid #fecaca; background:#fff7f7; border-radius:12px; padding:14px;">
                        <div style="font-size:12px; color:#7f1d1d;">Total inadimplente</div>
                        <div style="font-size:22px; font-weight:800; color:#b91c1c;">
                          ${brl(totalInad)}
                        </div>
                      </div>
                    </td>

                    <td style="padding:10px;">
                      <div style="border:1px solid #e5e7eb; border-radius:12px; padding:14px;">
                        <div style="font-size:12px; color:#6b7280;">Títulos</div>
                        <div style="font-size:22px; font-weight:800;">${num(totalTitulos)}</div>
                      </div>
                    </td>
                  </tr>
                </table>

                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                  <thead>
                    <tr style="background:#f3f4f6;">
                      <th style="padding:10px; border:1px solid #e5e7eb;">Código</th>
                      <th style="padding:10px; border:1px solid #e5e7eb;">Cliente</th>
                      <th style="padding:10px; border:1px solid #e5e7eb;">Vendedor</th>
                      <th style="padding:10px; border:1px solid #e5e7eb;">Supervisor</th>
                      <th style="padding:10px; border:1px solid #e5e7eb;">Inadimplência</th>
                      <th style="padding:10px; border:1px solid #e5e7eb;">Títulos</th>
                      <th style="padding:10px; border:1px solid #e5e7eb;">Maior atraso</th>
                    </tr>
                  </thead>
                  <tbody>
                    ${linhas ||
      `
                      <tr>
                        <td colspan="7" style="padding:18px; text-align:center; color:#6b7280; border:1px solid #e5e7eb;">
                          Nenhum cliente inadimplente encontrado para o filtro selecionado.
                        </td>
                      </tr>
                    `
      }
                  </tbody>
                </table>

                <p style="margin-top:20px; font-size:13px; color:#6b7280;">
                  Em caso de dúvidas, responda este e-mail.
                </p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </div>
`;
  }

  function setAssuntoGerado(clientes) {
    const input = $("#emailAssunto");
    if (!input) return;

    const assunto = buildEmailSubject(clientes);
    input.value = assunto;
    input.setAttribute("value", assunto);
  }

  function refreshEmailPreview() {
    autoPreencherEnviarPara();
    autoPreencherCc();

    const clientes = getClientesParaAviso();
    const mensagemExtra = $("#emailMensagemExtra")?.value || "";
    const html = buildEmailHtml(clientes, mensagemExtra);

    if ($("#emailPreviewHtml")) {
      $("#emailPreviewHtml").innerHTML = html;
    }

    setAssuntoGerado(clientes);

    if ($("#emailQtdClientes")) {
      $("#emailQtdClientes").textContent = num(clientes.length);
    }

    if ($("#emailTotalInad")) {
      $("#emailTotalInad").textContent = brl(sum(clientes, (c) => c.inad_total));
    }

    if ($("#emailQtdTitulos")) {
      $("#emailQtdTitulos").textContent = num(sum(clientes, (c) => c.inad_qtd_titulos));
    }

    if ($("#modalAvisoResumo")) {
      const supervisor = getSupervisorAtual();
      const supervisorNome = supervisor?.nome || supervisor?.codigo || "Não definido";

      $("#modalAvisoResumo").textContent =
        `Vendedor: ${getContextoEmail()} • Supervisor: ${supervisorNome} • ${num(clientes.length)} cliente(s) encontrados`;
    }

    return { clientes, html };
  }

  async function openEmailModal() {
    try {
      if (!state.vendedores080.length) {
        await loadVendedores080();
      }
    } catch (err) {
      console.error(err);
    }

    refreshEmailPreview();

    const modal = $("#modalAvisoEmail");
    if (!modal) return;

    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
  }

  function closeEmailModal() {
    const modal = $("#modalAvisoEmail");
    if (!modal) return;

    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
  }

  async function enviarAvisoEmail() {
    const para = parseEmails($("#emailDestinatario")?.value || "");
    const cc = parseEmails($("#emailCc")?.value || "");
    const assunto = ($("#emailAssunto")?.value || "").trim();

    if (!para.length) {
      alert("Informe pelo menos um destinatário no campo Para.");
      return;
    }

    const invalidos = [...para, ...cc].filter((email) => !isValidEmail(email));
    if (invalidos.length) {
      alert(`Existem e-mails inválidos: ${invalidos.join(", ")}`);
      return;
    }

    const { clientes, html } = refreshEmailPreview();

    if (!clientes.length) {
      alert("Nenhum cliente inadimplente encontrado para envio.");
      return;
    }

    const supervisorAtual = getSupervisorAtual();
    const supervisorEmailAtual = getSupervisorEmailAtual();

    const payload = {
      para,
      cc,
      assunto,
      html,
      supervisor_codigo: supervisorAtual?.codigo || "",
      supervisor_nome: supervisorAtual?.nome || "",
      supervisor_email: supervisorEmailAtual || "",
      clientes: clientes.map((cli) => ({
        cliente: cli.cliente,
        loja: cli.loja,
        nome: cli.nome,
        vendedor_codigo: cli.vendedor_codigo,
        vendedor_nome: cli.vendedor_nome,
        supervisor_codigo: cli.supervisor_codigo,
        supervisor_nome: cli.supervisor_nome,
        inad_total: toNumber(cli.inad_total),
        inad_qtd_titulos: toNumber(cli.inad_qtd_titulos),
        maior_atraso_dias: toNumber(cli.maior_atraso_dias),
      })),
      filtros: {
        vendedor: $("#filterVendedor")?.value || "",
        supervisor: $("#filterSupervisor")?.value || "",
        faixa: $("#filterFaixa")?.value || "",
        valor_min: $("#filterValorMin")?.value || "",
        date_from: $("#filterDateFrom")?.value || "",
        date_to: $("#filterDateTo")?.value || "",
      },
    };

    await withLoader(
      async () => {
        const resp = await fetch("/api/inadimplencia_enviar_email.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify(payload),
        });

        const rawText = await resp.text();

        let data;
        try {
          data = JSON.parse(rawText);
        } catch (e) {
          console.error("Resposta bruta do servidor:", rawText);
          throw new Error("O servidor não retornou um JSON válido. Verifique o PHP do endpoint.");
        }

        if (!resp.ok || !data.ok) {
          throw new Error(data?.message || "Falha ao enviar e-mail.");
        }

        alert("E-mail enviado com sucesso.");
        closeEmailModal();
      },
      {
        title: "Enviando aviso…",
        sub: "Preparando e disparando e-mail",
      }
    ).catch((err) => {
      alert(err.message || "Erro ao enviar e-mail.");
    });
  }

  document.addEventListener("click", (e) => {
    const sortTh = e.target.closest(".th-sort");
    if (sortTh) {
      const field = sortTh.dataset.sort;

      if (state.sortTable.field === field) {
        state.sortTable.dir = state.sortTable.dir === "asc" ? "desc" : "asc";
      } else {
        state.sortTable.field = field;
        state.sortTable.dir =
          field === "nome" || field === "vendedor_nome" || field === "supervisor_nome"
            ? "asc"
            : "desc";
      }

      rerenderTableOnly();
      return;
    }

    const presetBtn = e.target.closest(".btn-preset");
    if (presetBtn) {
      const preset = presetBtn.dataset.preset || "6m";
      state.selectedPreset = preset;
      setActivePresetButton(preset);
      loadData(true).catch(handleLoadError);
      return;
    }

    const detailBtn = e.target.closest(".btn-detail");
    if (detailBtn) {
      openCliente(detailBtn.dataset.key);
      return;
    }

    const rankingBtn = e.target.closest(".ranking-item--click, .ranking-item--fat");
    if (rankingBtn) {
      openCliente(rankingBtn.dataset.key);
      return;
    }

    if (e.target.id === "modalClose" || e.target.id === "modalBackdrop") {
      closeModal();
      return;
    }

    if (e.target.id === "btnRefresh") {
      loadData(true).catch(handleLoadError);
      return;
    }

    if (e.target.id === "btnApplyFilters") {
      loadData(true).catch(handleLoadError);
      return;
    }

    if (e.target.id === "btnClearFilters") {
      clearTopFilters();
      resetPresetToDefault();
      loadData(true).catch(handleLoadError);
      return;
    }

    if (e.target.id === "btnApplyTable") {
      loadData(true).catch(handleLoadError);
      return;
    }

    if (e.target.id === "btnClearTable") {
      clearTableFilters();
      rerenderTableOnly();
      return;
    }
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      closeModal();
      closeEmailModal();
      return;
    }

    if (e.key === "Enter") {
      const active = document.activeElement;
      if (
        active &&
        [
          "filtroBusca",
          "filtroVendedorTabela",
          "filtroSupervisorTabela",
          "filtroValorMinTabela",
          "filtroFaixaTabela",
          "filtroStatusTabela",
        ].includes(active.id)
      ) {
        rerenderTableOnly();
      }
    }
  });

  $("#btnOpenEmailModal")?.addEventListener("click", openEmailModal);
  $("#btnCloseEmailModal")?.addEventListener("click", closeEmailModal);
  $("#btnPreviewEmail")?.addEventListener("click", refreshEmailPreview);
  $("#btnEnviarEmailAviso")?.addEventListener("click", enviarAvisoEmail);

  document.querySelectorAll("[data-close-email-modal]").forEach((el) => {
    el.addEventListener("click", closeEmailModal);
  });

  [
    "#emailMensagemExtra",
    "#filterVendedor",
    "#filterSupervisor",
    "#filterFaixa",
    "#filterValorMin",
    "#filterDateFrom",
    "#filterDateTo",
  ].forEach((sel) => {
    $(sel)?.addEventListener("change", async () => {
      try {
        if (!state.vendedores080.length) {
          await loadVendedores080();
        }
      } catch (err) {
        console.error(err);
      }
      refreshEmailPreview();
    });
  });
  let filtroTabelaTimer = null;

  function triggerFiltroTabela() {
    clearTimeout(filtroTabelaTimer);
    filtroTabelaTimer = setTimeout(() => {
      rerenderTableOnly();
    }, 180);
  }

  [
    "#filtroBusca",
    "#filtroFaixaTabela",
    "#filtroValorMinTabela",
    "#filtroStatusTabela",
  ].forEach((sel) => {
    const el = $(sel);
    if (!el) return;

    el.addEventListener("input", triggerFiltroTabela);
    el.addEventListener("change", triggerFiltroTabela);
  });
  setActivePresetButton(state.selectedPreset);

  Promise.allSettled([loadVendedores080(), loadData()]).then((results) => {
    const dataResult = results[1];
    if (dataResult?.status === "rejected") {
      handleLoadError(dataResult.reason);
    }
  });

})();
function isNotebookView() {
  return window.innerWidth <= 1366;
}

function truncateText(text, max) {
  const value = String(text || '').trim();
  if (!value) return '-';
  if (!isNotebookView()) return value;
  return value.length > max ? value.slice(0, max).trim() + '…' : value;
}

function formatCurrencyShort(value) {
  const n = Number(value || 0);

  if (!isNotebookView()) {
    return formatCurrency(n); // mantém sua função atual normal
  }

  if (Math.abs(n) >= 1000000) {
    return 'R$ ' + (n / 1000000).toFixed(1).replace('.', ',') + ' mi';
  }

  if (Math.abs(n) >= 1000) {
    return 'R$ ' + (n / 1000).toFixed(1).replace('.', ',') + ' mil';
  }

  return 'R$ ' + n.toFixed(0).replace('.', ',');
}

function formatPercentShort(value) {
  const n = Number(value || 0);

  if (!isNotebookView()) {
    return formatPercent(n); // mantém sua função atual normal
  }

  return n.toFixed(1).replace('.', ',') + '%';
}

function formatDaysShort(value) {
  const n = Number(value || 0);
  return isNotebookView() ? `${n}d` : `${n} dias`;
}
// 🔥 FILTRO EM TEMPO REAL (live)
[
  "#filtroBusca",
  "#filtroVendedorTabela",
  "#filtroSupervisorTabela",
  "#filtroFaixaTabela",
  "#filtroValorMinTabela",
  "#filtroStatusTabela"
].forEach((sel) => {
  const el = document.querySelector(sel);
  if (!el) return;

  // Para digitação
  el.addEventListener("input", () => {
    rerenderTableOnly();
  });

  // Para select/dropdown
  el.addEventListener("change", () => {
    rerenderTableOnly();
  });
});