(function () {
  const state = {
    payload: null,
    clientes: [],
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

  function buildQuery(force = false) {
    const params = new URLSearchParams();

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

      return {
        ...cli,
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
      row.maior_atraso_dias = Math.max(row.maior_atraso_dias, toNumber(cli.maior_atraso_dias));
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

    insights.push(`A inadimplência total representa <strong>${pct(pctSobreFat)}%</strong> do faturamento considerado no período.`);
    insights.push(`Os <strong>10 maiores inadimplentes</strong> concentram <strong>${pct(top10?.pct || 0)}%</strong> do saldo vencido.`);
    
    if (faixaDominante && faixaDominante.valor > 0) {
      insights.push(
        `A faixa de atraso <strong>${esc(faixaDominante.faixa)} dias</strong> concentra o maior volume: <strong>${brl(faixaDominante.valor)}</strong>.`
      );
    }

    if (vendedorTop) {
      insights.push(
        `O vendedor com maior carteira inadimplente é <strong>${esc(vendedorTop.nome)}</strong>, com <strong>${brl(vendedorTop.inad_total)}</strong>.`
      );
    }

    if (supervisorTop) {
      insights.push(
        `O supervisor com maior exposição é <strong>${esc(supervisorTop.nome)}</strong>, com <strong>${brl(supervisorTop.inad_total)}</strong>.`
      );
    }

    insights.push(
      `Existem <strong>${num(riscoAlto.length)}</strong> clientes em risco alto/crítico, sendo <strong>${num(criticos.length)}</strong> em nível crítico.`
    );

    if (clientesComFatEAltoRisco.length > 0) {
      insights.push(
        `<strong>${num(clientesComFatEAltoRisco.length)}</strong> clientes faturaram no período e possuem atraso superior a 90 dias, exigindo atenção comercial e financeira.`
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
    renderInsights(clientes);
    renderTopInad(data.top_inadimplentes?.length ? decorateClientes(data.top_inadimplentes) : clientes.slice().sort((a, b) => b.inad_total - a.inad_total).slice(0, 50));
    renderTopFat(data.top_faturados?.length ? decorateClientes(data.top_faturados) : clientes.slice().sort((a, b) => b.faturado_periodo - a.faturado_periodo).slice(0, 50));
    renderConcentracao(clientes);
    renderAging(clientes);
    renderRankingCarteira("#rankingVendedor", buildCarteiraRanking(clientes, "vendedor_codigo", "vendedor_nome"));
    renderRankingCarteira("#rankingSupervisor", buildCarteiraRanking(clientes, "supervisor_codigo", "supervisor_nome"));

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
    const ticket = clientesInad > 0 ? totalInad / clientesInad : toNumber(kpis.ticket_medio_inadimplencia);
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

    el.innerHTML = insights
      .map((item) => `<div class="insight-item">${item}</div>`)
      .join("");
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
      <button type="button" class="ranking-item ranking-item--click" data-key="${esc(cli.cliente_key)}">
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
                class="ranking-item ranking-item--fat ${esc(riskClass(pctInad, inad, cli.maior_atraso_dias))}"
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
        `Cliente ${cli.cliente || "-"} / Loja ${cli.loja || "0001"} • Inadimplente: ${brl(cli.inad_total)} • Faturado: ${brl(cli.faturado_periodo)}`;
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

  document.addEventListener("click", (e) => {
    const sortTh = e.target.closest(".th-sort");
    if (sortTh) {
      const field = sortTh.dataset.sort;

      if (state.sortTable.field === field) {
        state.sortTable.dir = state.sortTable.dir === "asc" ? "desc" : "asc";
      } else {
        state.sortTable.field = field;
        state.sortTable.dir =
          field === "nome" ||
          field === "vendedor_nome" ||
          field === "supervisor_nome"
            ? "asc"
            : "desc";
      }

      rerenderTableOnly();
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
      loadData(true).catch(handleLoadError);
      return;
    }

    if (e.target.id === "btnApplyTable") {
      rerenderTableOnly();
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

  function handleLoadError(err) {
    console.error(err);
    if ($("#updatedAt")) {
      $("#updatedAt").textContent = "Erro ao carregar dados";
    }
  }

  loadData().catch(handleLoadError);
})();