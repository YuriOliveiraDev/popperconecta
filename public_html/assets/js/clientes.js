/* =========================================================
   CLIENTES DASHBOARD — JS COMPLETO
   Top50 + ABC + Evolução + Margem + Matriz
   + mês em ordem fixa
   + default no mês mais recente
   + loader integrado
   ========================================================= */

(function () {
  'use strict';

  // =========================================================
  // HELPERS BASE
  // =========================================================
  const $ = (id) => document.getElementById(id);

  let DATA = null;
  let ACTIVE_YM = null;
  let AUTO_REFRESH_TIMER = null;
  let RESIZE_TIMER = null;

  function shortLabel(s, max = 10) {
    const txt = String(s ?? '').trim();
    if (txt.length <= max) return txt;
    return txt.slice(0, max) + '…';
  }

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (m) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[m]));
  }

  function brl(v) {
    const n = Number(v || 0);
    return n.toLocaleString('pt-BR', {
      style: 'currency',
      currency: 'BRL'
    });
  }

  function pct(v, digits = 0) {
    const n = Number(v || 0) * 100;
    return n.toLocaleString('pt-BR', {
      minimumFractionDigits: digits,
      maximumFractionDigits: digits
    }) + '%';
  }

  function nfmt(v, digits = 0) {
    const n = Number(v || 0);
    return n.toLocaleString('pt-BR', {
      minimumFractionDigits: digits,
      maximumFractionDigits: digits
    });
  }

  function compact(v) {
    const n = Number(v || 0);
    const abs = Math.abs(n);
    if (abs >= 1e9) return (n / 1e9).toFixed(1).replace('.', ',') + 'B';
    if (abs >= 1e6) return (n / 1e6).toFixed(1).replace('.', ',') + 'M';
    if (abs >= 1e3) return (n / 1e3).toFixed(1).replace('.', ',') + 'k';
    return n.toFixed(0);
  }

  function debounce(fn, delay = 180) {
    return function (...args) {
      clearTimeout(RESIZE_TIMER);
      RESIZE_TIMER = setTimeout(() => fn.apply(this, args), delay);
    };
  }

  function isInitialLoad() {
    return !DATA;
  }

  // =========================================================
  // LOADER
  // =========================================================
  function loaderShow(title, sub) {
    if (window.PopperLoading && typeof window.PopperLoading.show === 'function') {
      window.PopperLoading.show(title || 'Carregando…', sub || 'Buscando dados');
    }
  }

  function loaderHide() {
    if (window.PopperLoading && typeof window.PopperLoading.hide === 'function') {
      window.PopperLoading.hide();
    }
  }

  // =========================================================
  // UI HELPERS
  // =========================================================
  function setErr(msg) {
    const el = $('err');
    if (!el) return;
    el.style.display = msg ? 'block' : 'none';
    el.textContent = msg || '';
  }

  function setActiveMonth(ym) {
    ACTIVE_YM = ym;
    document.querySelectorAll('#monthBar .pill').forEach((b) => {
      b.classList.toggle('is-active', b.dataset.ym === ym);
    });
  }

  function sortMonthPillsAsc() {
    const bar = $('monthBar');
    if (!bar) return;

    const pills = Array.from(bar.querySelectorAll('.pill'));
    pills.sort((a, b) => String(a.dataset.ym || '').localeCompare(String(b.dataset.ym || '')));
    pills.forEach((p) => bar.appendChild(p));
  }

  function buildMarginMap() {
    const map = new Map();

    const sources = [
      ...(DATA?.margem?.top50 || []),
      ...(DATA?.margem?.top10 || []),
      ...(DATA?.ranking?.top50 || []),
      ...(DATA?.ranking?.top10 || [])
    ];

    sources.forEach((it) => {
      if (!it?.key) return;

      if (it.margem_pct != null && !Number.isNaN(Number(it.margem_pct))) {
        map.set(String(it.key), Number(it.margem_pct));
      }
    });

    return map;
  }

  function getCurrentYmSaoPaulo() {
    const parts = new Intl.DateTimeFormat('en-CA', {
      timeZone: 'America/Sao_Paulo',
      year: 'numeric',
      month: '2-digit'
    }).formatToParts(new Date());

    const year = parts.find((p) => p.type === 'year')?.value || '0000';
    const month = parts.find((p) => p.type === 'month')?.value || '01';
    return `${year}-${month}`;
  }

  // =========================================================
  // TOOLTIP
  // =========================================================
  function hideTip() {
    const tip = $('canvasTip');
    if (tip) tip.style.display = 'none';
  }

  function placeTip(tip, clientX, clientY) {
    const gap = 12;

    tip.style.display = 'block';

    let left = clientX + gap;
    let top = clientY + gap;

    const tipW = tip.offsetWidth;
    const tipH = tip.offsetHeight;

    if (left + tipW > window.innerWidth - 8) {
      left = clientX - tipW - gap;
    }
    if (top + tipH > window.innerHeight - 8) {
      top = clientY - tipH - gap;
    }

    if (left < 8) left = 8;
    if (top < 8) top = 8;

    tip.style.left = left + 'px';
    tip.style.top = top + 'px';
  }

  function enableBarTooltip(canvas, formatter) {
    const tip = $('canvasTip');
    if (!canvas || !tip) return;

    canvas.onmousemove = (e) => {
      const rect = canvas.getBoundingClientRect();
      const mx = e.clientX - rect.left;
      const my = e.clientY - rect.top;

      const hits = canvas._barHits || [];
      let hit = null;

      for (const h of hits) {
        if (mx >= h.x && mx <= h.x + h.w && my >= h.y && my <= h.y + h.h) {
          hit = h.item;
          break;
        }
      }

      if (!hit) {
        hideTip();
        return;
      }

      tip.innerHTML = typeof formatter === 'function'
        ? formatter(hit)
        : `<div style="font-weight:1000">${esc(hit.cliente || hit.label || '')}</div>`;

      placeTip(tip, e.clientX, e.clientY);
    };

    canvas.onmouseleave = hideTip;
  }

  function enableParetoTooltip(canvas) {
    const tip = $('canvasTip');
    if (!canvas || !tip) return;

    canvas.onmousemove = (e) => {
      const hit = canvas._paretoHit;
      if (!hit) {
        hideTip();
        return;
      }

      const rect = canvas.getBoundingClientRect();
      const mx = e.clientX - rect.left;
      const my = e.clientY - rect.top;

      const { plot, n, barW, gap, items } = hit;

      if (mx < plot.x || mx > plot.x + plot.w || my < plot.y || my > plot.y + plot.h) {
        hideTip();
        return;
      }

      const full = barW + gap;
      const idx = Math.floor((mx - plot.x) / full);

      if (idx < 0 || idx >= n) {
        hideTip();
        return;
      }

      const it = items[idx];
      tip.innerHTML =
        `<div style="font-weight:1000">${esc(it.cliente ?? '')}</div>` +
        `<div style="opacity:.92;margin-top:4px">Faturamento: ${brl(it.valor ?? 0)}</div>`;

      placeTip(tip, e.clientX, e.clientY);
    };

    canvas.onmouseleave = hideTip;
  }

  // =========================================================
  // CANVAS HELPERS
  // =========================================================
  function canvasCtx(c) {
    const dpr = window.devicePixelRatio || 1;
    const rect = c.getBoundingClientRect();

    c.width = Math.max(1, Math.floor(rect.width * dpr));
    c.height = Math.max(1, Math.floor(rect.height * dpr));

    const ctx = c.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, c.clientWidth, c.clientHeight);

    return ctx;
  }

  function font(ctx, size = 12, weight = 900) {
    ctx.font = `${weight} ${size}px system-ui, -apple-system, Segoe UI, Roboto, Arial`;
  }

  function roundRect(ctx, x, y, w, h, r = 10) {
    const rr = Math.min(r, w / 2, h / 2);
    ctx.beginPath();
    ctx.moveTo(x + rr, y);
    ctx.arcTo(x + w, y, x + w, y + h, rr);
    ctx.arcTo(x + w, y + h, x, y + h, rr);
    ctx.arcTo(x, y + h, x, y, rr);
    ctx.arcTo(x, y, x + w, y, rr);
    ctx.closePath();
  }

  function drawAxes(ctx, box, yMax, yTicks = 5) {
    const { x, y, w, h } = box;

    ctx.save();
    ctx.strokeStyle = 'rgba(15,23,42,.08)';
    ctx.fillStyle = 'rgba(15,23,42,.55)';
    font(ctx, 11, 900);

    for (let i = 0; i <= yTicks; i++) {
      const t = i / yTicks;
      const yy = y + h - (h * t);

      ctx.beginPath();
      ctx.moveTo(x, yy);
      ctx.lineTo(x + w, yy);
      ctx.stroke();

      const val = yMax * t;
      ctx.fillText(compact(val), x - 40, yy + 4);
    }

    ctx.strokeStyle = 'rgba(15,23,42,.14)';
    ctx.beginPath();
    ctx.moveTo(x, y);
    ctx.lineTo(x, y + h);
    ctx.lineTo(x + w, y + h);
    ctx.stroke();

    ctx.restore();
  }

  // =========================================================
  // RENDERS LISTA
  // =========================================================
  function renderRankList(elId, items, valueKey = 'valor', labelKey = 'cliente', limit = 50, onClick) {
    const el = $(elId);
    if (!el) return;

    if (!items || !items.length) {
      el.innerHTML = `<div style="padding:12px;color:rgba(15,23,42,.55);font-weight:900">Sem dados</div>`;
      return;
    }

    el.innerHTML = '';

    items.slice(0, limit).forEach((it, i) => {
      const nome = String(it[labelKey] ?? '');
      const val = Number(it[valueKey] ?? 0);

      const row = document.createElement('div');
      row.className = 'rankItem';
      row.innerHTML = `
        <div class="rankLeft">
          <div class="rankPos">${i + 1}</div>
          <div class="rankName" title="${esc(nome)}">${esc(nome)}</div>
        </div>
        <div class="rankValue">${brl(val)}</div>
      `;

      if (typeof onClick === 'function') {
        row.style.cursor = 'pointer';
        row.addEventListener('click', () => onClick(it));
      }

      el.appendChild(row);
    });
  }

  // =========================================================
  // CHARTS
  // =========================================================
  function vbarChart(canvas, items, valueKey, labelKey, colorFn, yTicks = 5, opts = {}) {
    const ctx = canvasCtx(canvas);
    const W = canvas.clientWidth;
    const H = canvas.clientHeight;

    const padL = opts.padL ?? 56;
    const padR = opts.padR ?? 18;
    const padT = opts.padT ?? 14;
    const padB = opts.padB ?? 68;

    if (!items || !items.length) {
      ctx.fillStyle = 'rgba(15,23,42,.55)';
      font(ctx, 13, 900);
      ctx.fillText('Sem dados', 14, 28);
      canvas._barHits = [];
      return;
    }

    const vals = items.map((x) => Number(x[valueKey] || 0));
    const maxV = Math.max(...vals, 0) || 1;

    const plot = { x: padL, y: padT, w: W - padL - padR, h: H - padT - padB };
    drawAxes(ctx, plot, maxV, yTicks);

    const n = items.length;
    const gap = opts.gap ?? 10;
    const barW = Math.max(12, (plot.w - gap * (n - 1)) / n);

    const hits = [];

    items.forEach((it, i) => {
      const v = Number(it[valueKey] || 0);
      const bh = plot.h * (v / maxV);
      const x = plot.x + i * (barW + gap);
      const y = plot.y + plot.h - bh;

      ctx.fillStyle = colorFn ? colorFn(it, v) : 'rgba(92,44,140,.80)';
      roundRect(ctx, x, y, barW, Math.max(4, bh), 10);
      ctx.fill();

      hits.push({ x, y, w: barW, h: Math.max(4, bh), item: it });
    });

    ctx.save();
    ctx.fillStyle = 'rgba(15,23,42,.62)';
    font(ctx, 10, 900);

    items.forEach((it, i) => {
      const lab = String(it[labelKey] ?? '');
      const x = plot.x + i * (barW + gap) + barW / 2;
      const y = plot.y + plot.h + 14;

      ctx.save();
      ctx.translate(x, y);
      ctx.rotate(-0.55);
      ctx.fillText(shortLabel(lab, 18), 0, 0);
      ctx.restore();
    });

    ctx.restore();

    canvas._barHits = hits;
  }

  function lineChart(canvas, labels, values) {
    const ctx = canvasCtx(canvas);
    const W = canvas.clientWidth;
    const H = canvas.clientHeight;

    const padL = 54;
    const padR = 16;
    const padT = 14;
    const padB = 34;

    if (!values || !values.length) {
      ctx.fillStyle = 'rgba(15,23,42,.55)';
      font(ctx, 13, 900);
      ctx.fillText('Sem dados', 14, 28);
      return;
    }

    const vals = values.map((v) => Number(v || 0));
    const maxV = Math.max(...vals) || 1;
    const plot = { x: padL, y: padT, w: W - padL - padR, h: H - padT - padB };

    drawAxes(ctx, plot, maxV, 5);

    const n = vals.length;
    const step = n > 1 ? (plot.w / (n - 1)) : 0;

    ctx.save();
    ctx.strokeStyle = 'rgba(92,44,140,.90)';
    ctx.lineWidth = 2.5;
    ctx.beginPath();

    vals.forEach((v, i) => {
      const x = plot.x + i * step;
      const y = plot.y + plot.h - (plot.h * (v / maxV));
      if (i === 0) ctx.moveTo(x, y);
      else ctx.lineTo(x, y);
    });

    ctx.stroke();

    ctx.fillStyle = 'rgba(92,44,140,.95)';
    vals.forEach((v, i) => {
      const x = plot.x + i * step;
      const y = plot.y + plot.h - (plot.h * (v / maxV));
      ctx.beginPath();
      ctx.arc(x, y, 3, 0, Math.PI * 2);
      ctx.fill();
    });

    ctx.restore();

    if (labels && labels.length) {
      ctx.save();
      ctx.fillStyle = 'rgba(15,23,42,.55)';
      font(ctx, 10, 900);

      const every = Math.max(1, Math.floor(n / 8));
      for (let i = 0; i < n; i += every) {
        const x = plot.x + i * step;
        ctx.fillText(String(labels[i] ?? ''), x - 6, plot.y + plot.h + 18);
      }

      ctx.restore();
    }
  }

  function paretoChart(canvas, items) {
    const ctx = canvasCtx(canvas);
    const W = canvas.clientWidth;
    const H = canvas.clientHeight;

    const padL = 54;
    const padR = 56;
    const padT = 14;
    const padB = 34;

    if (!items || !items.length) {
      ctx.fillStyle = 'rgba(15,23,42,.55)';
      font(ctx, 13, 900);
      ctx.fillText('Sem dados', 14, 28);
      canvas._paretoHit = null;
      return;
    }

    const vals = items.map((x) => Number(x.valor || 0));
    const total = vals.reduce((a, b) => a + b, 0) || 1;
    const maxV = Math.max(...vals) || 1;

    const plot = { x: padL, y: padT, w: W - padL - padR, h: H - padT - padB };
    drawAxes(ctx, plot, maxV, 4);

    const n = items.length;
    const gap = 6;
    const barW = Math.max(8, (plot.w - gap * (n - 1)) / n);

    let cum = 0;
    const pts = [];

    for (let i = 0; i < n; i++) {
      const v = vals[i];
      cum += v;
      const cumPct = cum / total;

      const bh = plot.h * (v / maxV);
      const x = plot.x + i * (barW + gap);
      const y = plot.y + plot.h - bh;

      ctx.fillStyle = 'rgba(92,44,140,.70)';
      roundRect(ctx, x, y, barW, Math.max(4, bh), 8);
      ctx.fill();

      pts.push({
        x: x + barW / 2,
        y: plot.y + plot.h - plot.h * cumPct
      });
    }

    ctx.save();
    ctx.strokeStyle = 'rgba(15,23,42,.14)';
    ctx.beginPath();
    ctx.moveTo(plot.x + plot.w, plot.y);
    ctx.lineTo(plot.x + plot.w, plot.y + plot.h);
    ctx.stroke();

    ctx.fillStyle = 'rgba(15,23,42,.55)';
    font(ctx, 11, 900);
    for (let i = 0; i <= 5; i++) {
      const t = i / 5;
      const yy = plot.y + plot.h - plot.h * t;
      ctx.fillText(Math.round(t * 100) + '%', plot.x + plot.w + 8, yy + 4);
    }
    ctx.restore();

    ctx.save();
    ctx.strokeStyle = 'rgba(15,23,42,.78)';
    ctx.lineWidth = 2;
    ctx.beginPath();

    pts.forEach((p, i) => {
      if (i === 0) ctx.moveTo(p.x, p.y);
      else ctx.lineTo(p.x, p.y);
    });

    ctx.stroke();

    ctx.fillStyle = 'rgba(15,23,42,.85)';
    pts.forEach((p) => {
      ctx.beginPath();
      ctx.arc(p.x, p.y, 3, 0, Math.PI * 2);
      ctx.fill();
    });
    ctx.restore();

    const ref = (pp, label) => {
      const yy = plot.y + plot.h - plot.h * pp;
      ctx.save();
      ctx.setLineDash([6, 6]);
      ctx.strokeStyle = 'rgba(15,23,42,.18)';
      ctx.beginPath();
      ctx.moveTo(plot.x, yy);
      ctx.lineTo(plot.x + plot.w, yy);
      ctx.stroke();
      ctx.setLineDash([]);
      ctx.fillStyle = 'rgba(15,23,42,.55)';
      font(ctx, 10, 900);
      ctx.fillText(label, plot.x + 6, yy - 6);
      ctx.restore();
    };

    ref(0.80, '80% (A)');
    ref(0.95, '95% (A+B)');

    canvas._paretoHit = { plot, n, barW, gap, items };
  }

  function matrixChart(canvas, items, selectedKey = '') {
    const ctx = canvasCtx(canvas);
    const tip = $('canvasTip');

    const W = canvas.clientWidth;
    const H = canvas.clientHeight;

    if (!items || !items.length) {
      ctx.fillStyle = 'rgba(15,23,42,.55)';
      font(ctx, 13, 900);
      ctx.fillText('Sem dados', 14, 28);
      canvas._matrixHit = null;
      return;
    }

    const clean = items.filter((i) =>
      Number(i.valor || 0) > 0 &&
      i.margem_pct !== null &&
      Number.isFinite(Number(i.margem_pct))
    );

    if (!clean.length) {
      ctx.fillStyle = 'rgba(15,23,42,.55)';
      font(ctx, 13, 900);
      ctx.fillText('Sem dados válidos para a matriz', 14, 28);
      canvas._matrixHit = null;
      return;
    }

    const pad = { l: 68, r: 46, t: 24, b: 48 };
    const plot = { x: pad.l, y: pad.t, w: W - pad.l - pad.r, h: H - pad.t - pad.b };

    const maxFat = Math.max(...clean.map((i) => Number(i.valor || 0)), 1);
    const allMargins = clean.map((i) => Number(i.margem_pct || 0));
    const minMarg = Math.min(-0.05, ...allMargins);
    const maxMarg = Math.max(0.35, ...allMargins);

    const clamp = (v, a, b) => Math.max(a, Math.min(b, v));

    const xScale = (v) => {
      const vv = Math.max(0, Number(v || 0));
      return plot.x + plot.w * (Math.log10(vv + 1) / Math.log10(maxFat + 1));
    };

    const yScale = (m) => {
      const mm = clamp(Number(m || 0), minMarg, maxMarg);
      const norm = (mm - minMarg) / (maxMarg - minMarg || 1);
      return plot.y + plot.h - plot.h * norm;
    };

    const avgFat = clean.reduce((a, b) => a + Number(b.valor || 0), 0) / clean.length;
    const avgMarg = clean.reduce((a, b) => a + Number(b.margem_pct || 0), 0) / clean.length;

    const midX = xScale(avgFat);
    const midY = yScale(avgMarg);

    ctx.save();

    const bg = ctx.createLinearGradient(0, 0, 0, H);
    bg.addColorStop(0, '#ffffff');
    bg.addColorStop(1, '#fbfcff');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);

    ctx.strokeStyle = 'rgba(15,23,42,.10)';
    ctx.strokeRect(plot.x, plot.y, plot.w, plot.h);

    ctx.setLineDash([6, 6]);
    ctx.strokeStyle = 'rgba(15,23,42,.16)';

    ctx.beginPath();
    ctx.moveTo(midX, plot.y);
    ctx.lineTo(midX, plot.y + plot.h);
    ctx.stroke();

    ctx.beginPath();
    ctx.moveTo(plot.x, midY);
    ctx.lineTo(plot.x + plot.w, midY);
    ctx.stroke();

    ctx.setLineDash([]);

    ctx.fillStyle = 'rgba(15,23,42,.58)';
    font(ctx, 11, 900);
    ctx.fillText('Margem % ↑', plot.x - 56, plot.y - 8);
    ctx.fillText('Faturamento →', plot.x + plot.w - 96, plot.y + plot.h + 30);

    const legend = [
      { label: 'Estratégico', color: 'rgba(34,197,94,.88)' },
      { label: 'Volume', color: 'rgba(245,158,11,.88)' },
      { label: 'Potencial', color: 'rgba(59,130,246,.88)' },
      { label: 'Risco', color: 'rgba(239,68,68,.88)' }
    ];

    let lx = plot.x + 12;
    let ly = plot.y + 14;

    legend.forEach((item) => {
      ctx.fillStyle = item.color;
      ctx.beginPath();
      ctx.arc(lx, ly, 5, 0, Math.PI * 2);
      ctx.fill();

      ctx.fillStyle = 'rgba(15,23,42,.62)';
      font(ctx, 11, 900);
      ctx.fillText(item.label, lx + 12, ly + 4);
      ly += 18;
    });

    const points = [];
    const logNorm = (v) => (Math.log10(Math.max(0, Number(v || 0)) + 1) / Math.log10(maxFat + 1));

    clean.forEach((it) => {
      const valor = Number(it.valor || 0);
      const marg = Number(it.margem_pct || 0);

      const x = xScale(valor);
      const y = yScale(marg);

      const highFat = valor >= avgFat;
      const highMarg = marg >= avgMarg;

      let color = 'rgba(239,68,68,.85)';
      if (highFat && highMarg) color = 'rgba(34,197,94,.85)';
      else if (highFat && !highMarg) color = 'rgba(245,158,11,.85)';
      else if (!highFat && highMarg) color = 'rgba(59,130,246,.85)';

      const s = 5 + logNorm(valor) * 13;
      const isSel = selectedKey && String(it.key || '') === String(selectedKey);

      ctx.save();

      if (selectedKey && !isSel) ctx.globalAlpha = 0.30;

      ctx.fillStyle = color;
      ctx.beginPath();
      ctx.arc(x, y, s, 0, Math.PI * 2);
      ctx.fill();

      if (isSel) {
        ctx.globalAlpha = 1;
        ctx.lineWidth = 3;
        ctx.strokeStyle = 'rgba(15,23,42,.88)';
        ctx.beginPath();
        ctx.arc(x, y, s + 2, 0, Math.PI * 2);
        ctx.stroke();
      }

      ctx.restore();

      points.push({ x, y, r: s + 6, it });
    });

    ctx.restore();

    canvas._matrixHit = points;

    if (!tip) return;

    canvas.onmousemove = (e) => {
      const rect = canvas.getBoundingClientRect();
      const mx = e.clientX - rect.left;
      const my = e.clientY - rect.top;

      const pts = canvas._matrixHit || [];
      let hit = null;

      for (let i = 0; i < pts.length; i++) {
        const p = pts[i];
        const dx = mx - p.x;
        const dy = my - p.y;
        if ((dx * dx + dy * dy) <= (p.r * p.r)) {
          hit = p.it;
          break;
        }
      }

      if (!hit) {
        hideTip();
        return;
      }

      const nome = String(hit.cliente ?? '');
      const v = Number(hit.valor ?? 0);
      const m = Number(hit.margem_pct ?? 0);

      tip.innerHTML =
        `<div style="font-weight:1000">${esc(nome)}</div>` +
        `<div style="opacity:.92;margin-top:4px">Faturamento: ${brl(v)} <span style="opacity:.7">•</span> Margem: ${(m * 100).toFixed(1)}%</div>`;

      placeTip(tip, e.clientX, e.clientY);
    };

    canvas.onmouseleave = hideTip;
  }

  // =========================================================
  // EVOLUÇÃO
  // =========================================================
  function renderEvolucao(containerId, canvasId, labels, values) {
    const wrap = $(containerId);
    if (!wrap) return;

    const allValues = Array.isArray(values) ? values.map((v) => Number(v || 0)) : [];
    const hasPositive = allValues.some((v) => v > 0);

    if (!labels || !labels.length || !hasPositive) {
      wrap.innerHTML = `<div class="chart-empty">Este cliente não possui histórico suficiente de evolução de vendas no período selecionado.</div>`;
      return;
    }

    wrap.innerHTML = `<canvas class="chart" id="${canvasId}"></canvas>`;
    const newCanvas = $(canvasId);
    if (newCanvas) {
      lineChart(newCanvas, labels || [], allValues);
    }
  }

  // =========================================================
  // LOAD
  // =========================================================
  async function load(force = false) {
    $('wrap')?.classList.add('loading');
    setErr('');

    if (isInitialLoad()) {
      loaderShow('Carregando…', 'Montando dashboard de clientes');
    } else if (force) {
      loaderShow('Atualizando…', 'Forçando leitura (sem cache)');
    } else {
      loaderShow('Atualizando…', 'Aplicando cache e recalculando');
    }

    const qs = new URLSearchParams();
    qs.set('ym', ACTIVE_YM || '');
    if (force) qs.set('force', '1');

    const url = '../api/dashboard/clientes_insights.php?' + qs.toString();

    try {
      const r = await fetch(url, { cache: 'no-store' });
      if (!r.ok) throw new Error('HTTP ' + r.status);

      const j = await r.json();
      DATA = j;

      if ($('updatedAt')) $('updatedAt').textContent = j.updated_at || '--/--/---- --:--';

      const range = j.meta?.range_mes || [];
      if ($('periodo')) {
        $('periodo').textContent = (range.length === 2)
          ? (range[0] + ' até ' + range[1])
          : (ACTIVE_YM || '--');
      }

      const sel = $('clienteSelect');
      if (sel) {
        const prev = sel.value;
        const baseTop = j.ranking?.top50?.length ? j.ranking.top50 : (j.ranking?.top10 || []);

        sel.innerHTML = '<option value="">Cliente (Top 50)</option>';

        baseTop.forEach((c) => {
          const opt = document.createElement('option');
          opt.value = c.key;
          opt.textContent = `${c.cliente} (${brl(c.valor)})`;
          sel.appendChild(opt);
        });

        if (prev && Array.from(sel.options).some((o) => o.value === prev)) {
          sel.value = prev;
        }

        if (!sel.value && baseTop?.[0]?.key) {
          sel.value = baseTop[0].key;
        }
      }

      render();
    } catch (e) {
      console.error(e);
      setErr('Erro ao carregar: ' + (e?.message || e));

      if (window.PopperLoading?.error) {
        window.PopperLoading.error(e?.message || 'Falha ao carregar');
      }
    } finally {
      $('wrap')?.classList.remove('loading');
      setTimeout(() => loaderHide(), 180);
    }
  }

  // =========================================================
  // RENDER GERAL
  // =========================================================
  function render() {
    if (!DATA) return;

    const k = DATA.kpis || {};

    if ($('kpiClientes')) $('kpiClientes').textContent = nfmt(k.clientes_ativos || 0);
    if ($('kpiTop3')) $('kpiTop3').textContent = 'Top 3: ' + pct(k.top3_pct || 0, 0);

    if ($('kpiTicket')) $('kpiTicket').textContent = brl(k.ticket_medio || 0);
    if ($('kpiPedidos')) $('kpiPedidos').textContent = 'NFs: ' + nfmt(k.pedidos_mes || 0);

    if ($('kpiMargCli')) $('kpiMargCli').textContent = brl(k.margem_media_cliente || 0);
    if ($('kpiMargPct')) $('kpiMargPct').textContent = 'Margem %: ' + pct(k.margem_pct_media || 0, 1);

    if ($('kpiDesc')) $('kpiDesc').textContent = pct(k.desconto_medio || 0, 1);
    if ($('kpiAlert')) $('kpiAlert').textContent = DATA.insight?.alerta || 'Sem alerta';

    if ($('rankMeta')) $('rankMeta').textContent = 'Total mês: ' + brl(DATA.kpis?.faturamento_mes || 0);

    const selKey = $('clienteSelect')?.value || '';

    const rankingBase = DATA.ranking?.top50?.length ? DATA.ranking.top50 : (DATA.ranking?.top10 || []);
    const ranking = rankingBase.map((x) => ({
      key: x.key,
      cliente: x.cliente,
      valor: Number(x.valor || 0)
    }));

    renderRankList('topClientesList', ranking, 'valor', 'cliente', 50, (it) => {
      const sel = $('clienteSelect');
      if (sel && it.key) {
        sel.value = it.key;
        render();
      }
    });

    const abc = (DATA.abc?.items || []).slice(0, 20).map((x) => ({
      cliente: x.cliente,
      valor: Number(x.valor || 0)
    }));

    const cABC = $('cABC');
    if (cABC) {
      paretoChart(cABC, abc);
      enableParetoTooltip(cABC);
    }

    const evo = DATA.evolucao?.[selKey] || { labels: [], valores: [] };

    if ($('evoMeta')) {
      $('evoMeta').textContent = selKey
        ? ('Cliente: ' + (ranking.find((r) => String(r.key) === String(selKey))?.cliente || ''))
        : '—';
    }

    renderEvolucao('cEvoWrap', 'cEvo', evo.labels || [], evo.valores || []);

    const marg = (DATA.margem?.top10 || []).map((x) => ({
      key: x.key,
      cliente: x.cliente,
      valor: Number(x.margem_pct || 0)
    }));

    const cMargem = $('cMargem');
    if (cMargem) {
      vbarChart(
        cMargem,
        marg,
        'valor',
        'cliente',
        (it, v) => (v >= 0.25
          ? 'rgba(34,197,94,.72)'
          : (v >= 0.12 ? 'rgba(245,158,11,.78)' : 'rgba(239,68,68,.78)')),
        5,
        { padB: 84, gap: 12 }
      );

      enableBarTooltip(cMargem, (it) =>
        `<div style="font-weight:1000">${esc(it.cliente || '')}</div>
         <div style="opacity:.92;margin-top:4px">Margem: ${(Number(it.valor || 0) * 100).toFixed(1)}%</div>`
      );
    }

    const cMatrix = $('cMatrix');
    if (cMatrix) {
      const marginMap = buildMarginMap();

      const matrixData = ranking.map((c) => ({
        key: c.key,
        cliente: c.cliente,
        valor: Number(c.valor || 0),
        margem_pct: marginMap.has(String(c.key))
          ? Number(marginMap.get(String(c.key)))
          : null
      }));

      matrixChart(cMatrix, matrixData, selKey);
    }
  }

  // =========================================================
  // EVENTOS
  // =========================================================
  function bindMonthButtons() {
    document.querySelectorAll('#monthBar .pill').forEach((btn) => {
      btn.addEventListener('click', () => {
        const ym = btn.dataset.ym;
        if (!ym || ym === ACTIVE_YM) return;

        setActiveMonth(ym);

        const label = (btn.textContent || '').trim();
        loaderShow('Carregando…', 'Trocando para ' + (label || ym || 'mês selecionado'));

        const url = new URL(location.href);
        url.searchParams.set('ym', ym);
        history.replaceState({}, '', url.toString());

        load(false);
      });
    });
  }

  function bindRefreshButton() {
    $('btnRefresh')?.addEventListener('click', () => {
      loaderShow('Atualizando…', 'Forçando leitura (sem cache)');
      load(true);
    });
  }

  function bindClienteSelect() {
    $('clienteSelect')?.addEventListener('change', () => render());
  }

  function bindResize() {
    window.addEventListener('resize', debounce(() => {
      if (DATA) render();
    }, 180));
  }

  function setupAutoRefresh() {
    if (AUTO_REFRESH_TIMER) clearInterval(AUTO_REFRESH_TIMER);
    AUTO_REFRESH_TIMER = setInterval(() => load(false), 10 * 60 * 1000);
  }

  // =========================================================
  // INIT
  // =========================================================
  function init() {
    sortMonthPillsAsc();

    const params = new URLSearchParams(location.search);
    const ym = params.get('ym');
    const currentYm = getCurrentYmSaoPaulo();

    const existingMonths = Array.from(document.querySelectorAll('#monthBar .pill'))
      .map((b) => b.dataset.ym)
      .filter(Boolean);

    if (ym && existingMonths.includes(ym)) {
      setActiveMonth(ym);
    } else if (existingMonths.includes(currentYm)) {
      setActiveMonth(currentYm);
    } else if (existingMonths.length) {
      setActiveMonth(existingMonths[existingMonths.length - 1]);
    } else {
      setActiveMonth(currentYm);
    }

    bindMonthButtons();
    bindRefreshButton();
    bindClienteSelect();
    bindResize();
    setupAutoRefresh();

    load(false);
  }

  init();
})();