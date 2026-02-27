
/* =========================================================
   CLIENTES DASHBOARD — JS COMPLETO (Top50 + ABC + Matriz)
   ========================================================= */

const $ = (id) => document.getElementById(id);

// ---------- format helpers ----------
function brl(v){
  const n = Number(v || 0);
  return n.toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
}
function pct(v, digits=0){
  const n = Number(v || 0) * 100;
  return n.toLocaleString('pt-BR',{maximumFractionDigits:digits}) + '%';
}
function nfmt(v, digits=0){
  const n = Number(v || 0);
  return n.toLocaleString('pt-BR',{maximumFractionDigits:digits});
}
function compact(v){
  const n = Number(v||0);
  const abs = Math.abs(n);
  if (abs >= 1e9) return (n/1e9).toFixed(1).replace('.',',')+'B';
  if (abs >= 1e6) return (n/1e6).toFixed(1).replace('.',',')+'M';
  if (abs >= 1e3) return (n/1e3).toFixed(1).replace('.',',')+'k';
  return n.toFixed(0);
}
function esc(s){
  return String(s ?? '').replace(/[&<>"']/g, (m)=>({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
  }[m]));
}

// ---------- UI helpers ----------
function setErr(msg){
  const el = $('err');
  if (!el) return;
  el.style.display = msg ? 'block' : 'none';
  el.textContent = msg || '';
}
function setActiveMonth(ym){
  ACTIVE_YM = ym;
  document.querySelectorAll('#monthBar .pill').forEach(b=>{
    b.classList.toggle('is-active', b.dataset.ym === ym);
  });
}

// ---------- list render ----------
function renderRankList(elId, items, valueKey='valor', labelKey='cliente', limit=50, onClick){
  const el = $(elId);
  if (!el) return;

  if (!items || !items.length){
    el.innerHTML = `<div style="padding:12px;color:rgba(15,23,42,.55);font-weight:900">Sem dados</div>`;
    return;
  }

  el.innerHTML = '';
  items.slice(0, limit).forEach((it, i)=>{
    const nome = String(it[labelKey] ?? '');
    const val = Number(it[valueKey] ?? 0);

    const row = document.createElement('div');
    row.className = 'rankItem';
    row.innerHTML = `
      <div class="rankLeft">
        <div class="rankPos">#${i+1}</div>
        <div class="rankName" title="${esc(nome)}">${esc(nome.length>36 ? nome.slice(0,36)+'…' : nome)}</div>
      </div>
      <div class="rankValue">${brl(val)}</div>
    `;

    if (typeof onClick === 'function'){
      row.style.cursor = 'pointer';
      row.addEventListener('click', ()=> onClick(it));
    }

    el.appendChild(row);
  });
}

function renderScoreList(elId, items, limit=20, onClick){
  const el = $(elId);
  if (!el) return;

  const norm = (s)=>{
    const n = Number(s ?? 0);
    return n > 1 ? (n/100) : n; // 0..1 ou 0..100
  };

  const mapped = (items || []).map(x=>({
    key: x.key,
    cliente: x.cliente,
    score_pct: norm(x.score ?? x.valor ?? 0) * 100
  }));

  if (!mapped.length){
    el.innerHTML = `<div style="padding:12px;color:rgba(15,23,42,.55);font-weight:900">Sem dados</div>`;
    return;
  }

  el.innerHTML = '';
  mapped.slice(0, limit).forEach((it,i)=>{
    const nome = String(it.cliente ?? '');
    const val = Number(it.score_pct ?? 0);

    const row = document.createElement('div');
    row.className = 'rankItem';
    row.innerHTML = `
      <div class="rankLeft">
        <div class="rankPos">#${i+1}</div>
        <div class="rankName" title="${esc(nome)}">${esc(nome.length>36 ? nome.slice(0,36)+'…' : nome)}</div>
      </div>
      <div class="rankValue">${val.toFixed(0)}%</div>
    `;

    if (typeof onClick === 'function'){
      row.style.cursor = 'pointer';
      row.addEventListener('click', ()=> onClick(it));
    }

    el.appendChild(row);
  });
}

// ---------- canvas helpers ----------
function canvasCtx(c){
  const dpr = window.devicePixelRatio || 1;
  const rect = c.getBoundingClientRect();
  c.width  = Math.max(1, Math.floor(rect.width  * dpr));
  c.height = Math.max(1, Math.floor(rect.height * dpr));
  const ctx = c.getContext('2d');
  ctx.setTransform(dpr,0,0,dpr,0,0);
  ctx.clearRect(0,0,c.clientWidth,c.clientHeight);
  return ctx;
}
function font(ctx, size=12, w=900){
  ctx.font = `${w} ${size}px system-ui, -apple-system, Segoe UI, Roboto, Arial`;
}
function drawAxes(ctx, box, yMax, yTicks=5){
  const {x,y,w,h} = box;

  ctx.save();
  ctx.strokeStyle = 'rgba(15,23,42,.08)';
  ctx.fillStyle   = 'rgba(15,23,42,.55)';
  font(ctx, 11, 900);

  for(let i=0;i<=yTicks;i++){
    const t  = i/yTicks;
    const yy = y + h - (h*t);
    ctx.beginPath(); ctx.moveTo(x,yy); ctx.lineTo(x+w,yy); ctx.stroke();

    const val = yMax * t;
    ctx.fillText(compact(val), x-36, yy+4);
  }

  ctx.strokeStyle = 'rgba(15,23,42,.14)';
  ctx.beginPath(); ctx.moveTo(x,y); ctx.lineTo(x,y+h); ctx.lineTo(x+w,y+h); ctx.stroke();

  ctx.restore();
}
function vbarChart(canvas, items, valueKey, labelKey, colorFn, yTicks=5){
  const ctx = canvasCtx(canvas);
  const W = canvas.clientWidth, H = canvas.clientHeight;

  const padL=54, padR=16, padT=14, padB=34;

  if (!items || !items.length){
    ctx.fillStyle='rgba(15,23,42,.55)'; font(ctx,13,900);
    ctx.fillText('Sem dados',14,28);
    return;
  }

  const vals = items.map(x=>Number(x[valueKey]||0));
  const maxV = Math.max(...vals) || 1;

  const plot = {x:padL, y:padT, w:W-padL-padR, h:H-padT-padB};
  drawAxes(ctx, plot, maxV, yTicks);

  const n = items.length;
  const gap = 10;
  const barW = Math.max(10, (plot.w - gap*(n-1))/n);

  items.forEach((it,i)=>{
    const v  = Number(it[valueKey]||0);
    const bh = plot.h*(v/maxV);
    const x  = plot.x + i*(barW+gap);
    const y  = plot.y + plot.h - bh;

    ctx.fillStyle = colorFn ? colorFn(it,v) : 'rgba(92,44,140,.80)';
    ctx.beginPath();
    ctx.roundRect(x,y,barW,bh,10);
    ctx.fill();

    ctx.fillStyle='rgba(15,23,42,.55)';
    font(ctx,10,900);
    const lab = String(it[labelKey] ?? '');
    ctx.fillText(lab.length>6 ? lab.slice(0,6)+'…' : lab, x, plot.y+plot.h+18);
  });
}
function lineChart(canvas, labels, values){
  const ctx = canvasCtx(canvas);
  const W = canvas.clientWidth, H = canvas.clientHeight;

  const padL=54, padR=16, padT=14, padB=34;

  if (!values || !values.length){
    ctx.fillStyle='rgba(15,23,42,.55)'; font(ctx,13,900);
    ctx.fillText('Sem dados',14,28);
    return;
  }

  const vals = values.map(v=>Number(v||0));
  const maxV = Math.max(...vals) || 1;

  const plot = {x:padL, y:padT, w:W-padL-padR, h:H-padT-padB};
  drawAxes(ctx, plot, maxV, 5);

  const n = vals.length;
  const step = (n>1) ? (plot.w/(n-1)) : 0;

  ctx.save();
  ctx.strokeStyle='rgba(92,44,140,.90)';
  ctx.lineWidth=2.5;
  ctx.beginPath();
  vals.forEach((v,i)=>{
    const x = plot.x + i*step;
    const y = plot.y + plot.h - (plot.h*(v/maxV));
    if(i===0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
  });
  ctx.stroke();

  ctx.fillStyle='rgba(92,44,140,.95)';
  vals.forEach((v,i)=>{
    const x = plot.x + i*step;
    const y = plot.y + plot.h - (plot.h*(v/maxV));
    ctx.beginPath(); ctx.arc(x,y,3,0,Math.PI*2); ctx.fill();
  });
  ctx.restore();

  if (labels && labels.length){
    ctx.save();
    ctx.fillStyle='rgba(15,23,42,.55)';
    font(ctx,10,900);
    const every = Math.max(1, Math.floor(n/8));
    for(let i=0;i<n;i+=every){
      const x = plot.x + i*step;
      ctx.fillText(String(labels[i]??''), x-6, plot.y+plot.h+18);
    }
    ctx.restore();
  }
}

// ---------- ABC (Pareto) + tooltip ----------
function paretoChart(canvas, items){
  const ctx = canvasCtx(canvas);
  const W = canvas.clientWidth, H = canvas.clientHeight;

  // padR maior pra NÃO cortar 0%..100%
  const padL=54, padR=56, padT=14, padB=34;

  if (!items || !items.length){
    ctx.fillStyle='rgba(15,23,42,.55)'; font(ctx,13,900);
    ctx.fillText('Sem dados',14,28);
    canvas._paretoHit = null;
    return;
  }

  const vals = items.map(x=>Number(x.valor||0));
  const total = vals.reduce((a,b)=>a+b,0) || 1;
  const maxV  = Math.max(...vals) || 1;

  const plot = {x:padL, y:padT, w:W-padL-padR, h:H-padT-padB};
  drawAxes(ctx, plot, maxV, 4);

  const n = items.length;
  const gap = 6;
  const barW = Math.max(8, (plot.w - gap*(n-1))/n);

  let cum = 0;
  const pts = [];

  for(let i=0;i<n;i++){
    const v = vals[i];
    cum += v;
    const cumPct = cum/total;

    const bh = plot.h*(v/maxV);
    const x = plot.x + i*(barW+gap);
    const y = plot.y + plot.h - bh;

    ctx.fillStyle='rgba(92,44,140,.70)';
    ctx.beginPath(); ctx.roundRect(x,y,barW,bh,8); ctx.fill();

    pts.push({ x: x+barW/2, y: plot.y + plot.h - plot.h*cumPct });
  }

  // eixo direito %
  ctx.save();
  ctx.strokeStyle='rgba(15,23,42,.14)';
  ctx.beginPath(); ctx.moveTo(plot.x+plot.w, plot.y); ctx.lineTo(plot.x+plot.w, plot.y+plot.h); ctx.stroke();

  ctx.fillStyle='rgba(15,23,42,.55)';
  font(ctx,11,900);
  for(let i=0;i<=5;i++){
    const t=i/5;
    const yy = plot.y + plot.h - plot.h*t;
    ctx.fillText(Math.round(t*100)+'%', plot.x+plot.w+8, yy+4);
  }
  ctx.restore();

  // linha acumulada
  ctx.save();
  ctx.strokeStyle='rgba(15,23,42,.78)';
  ctx.lineWidth=2;
  ctx.beginPath();
  pts.forEach((p,i)=>{ if(i===0) ctx.moveTo(p.x,p.y); else ctx.lineTo(p.x,p.y); });
  ctx.stroke();
  ctx.fillStyle='rgba(15,23,42,.85)';
  pts.forEach(p=>{ ctx.beginPath(); ctx.arc(p.x,p.y,3,0,Math.PI*2); ctx.fill(); });
  ctx.restore();

  // linhas 80/95
  const ref = (pp,label)=>{
    const yy = plot.y + plot.h - plot.h*pp;
    ctx.save();
    ctx.setLineDash([6,6]);
    ctx.strokeStyle='rgba(15,23,42,.18)';
    ctx.beginPath(); ctx.moveTo(plot.x,yy); ctx.lineTo(plot.x+plot.w,yy); ctx.stroke();
    ctx.setLineDash([]);
    ctx.fillStyle='rgba(15,23,42,.55)';
    font(ctx,10,900);
    ctx.fillText(label, plot.x+6, yy-6);
    ctx.restore();
  };
  ref(0.80,'80% (A)');
  ref(0.95,'95% (A+B)');

  // hitbox info pro tooltip
  canvas._paretoHit = { plot, n, barW, gap, items };
}

function enableParetoTooltip(canvas){
  const tip = document.getElementById('canvasTip');
  if (!canvas || !tip) return;

  canvas.onmousemove = (e)=>{
    const hit = canvas._paretoHit;
    if (!hit) { tip.style.display='none'; return; }

    const rect = canvas.getBoundingClientRect();
    const mx = e.clientX - rect.left;
    const my = e.clientY - rect.top;

    const { plot, n, barW, gap, items } = hit;

    if (mx < plot.x || mx > plot.x+plot.w || my < plot.y || my > plot.y+plot.h){
      tip.style.display='none'; return;
    }

    const full = barW + gap;
    const idx = Math.floor((mx - plot.x)/full);
    if (idx < 0 || idx >= n){
      tip.style.display='none'; return;
    }

    const it = items[idx];
    tip.innerHTML =
      `<div style="font-weight:1000">${esc(it.cliente ?? '')}</div>`+
      `<div style="opacity:.92;margin-top:4px">Faturamento: ${brl(it.valor ?? 0)}</div>`;

    // posiciona e não deixa sair da tela
    tip.style.display='block';
    tip.style.left = (e.clientX + 14) + 'px';
    tip.style.top  = (e.clientY + 14) + 'px';

    const maxX = window.innerWidth - tip.offsetWidth - 14;
    const maxY = window.innerHeight - tip.offsetHeight - 14;
    tip.style.left = Math.min(parseInt(tip.style.left,10), maxX) + 'px';
    tip.style.top  = Math.min(parseInt(tip.style.top,10), maxY) + 'px';
  };

  canvas.onmouseleave = ()=>{ tip.style.display='none'; };
}

// ---------- Matriz BCG (Faturamento x Margem) ----------
function matrixChart(canvas, items, selectedKey=''){
  const ctx = canvasCtx(canvas);
  const tip = document.getElementById('canvasTip');

  const W = canvas.clientWidth;
  const H = canvas.clientHeight;

  if (!items || !items.length){
    ctx.fillStyle='rgba(15,23,42,.55)'; font(ctx,13,900);
    ctx.fillText('Sem dados',14,28);
    canvas._matrixHit = null;
    return;
  }

  // pads
  const pad = { l:64, r:42, t:22, b:46 };
  const plot = { x:pad.l, y:pad.t, w:W-pad.l-pad.r, h:H-pad.t-pad.b };

  // max faturamento
  const maxFat = Math.max(...items.map(i=>Number(i.valor||0))) || 1;

  // margens: usa faixa executiva (-20%..+40%) p/ espalhar
  const minMarg = -0.20;
  const maxMarg =  0.40;

  const clamp = (v,a,b)=> Math.max(a, Math.min(b, v));

  const xScale = (v)=>{
    const vv = Math.max(0, Number(v||0));
    return plot.x + plot.w * (Math.log10(vv+1)/Math.log10(maxFat+1));
  };
  const yScale = (m)=>{
    const mm = clamp(Number(m||0), minMarg, maxMarg);
    const norm = (mm - minMarg) / (maxMarg - minMarg);
    return plot.y + plot.h - plot.h*norm;
  };

  // medias para quadrantes (fat media e margem media reais)
  const avgFat  = items.reduce((a,b)=>a+Number(b.valor||0),0) / items.length;
  const avgMarg = items.reduce((a,b)=>a+Number(b.margem_pct||0),0) / items.length;

  const midX = xScale(avgFat);
  const midY = yScale(avgMarg);

  // fundo e borda
  ctx.save();
  ctx.fillStyle='#fff';
  ctx.fillRect(0,0,W,H);

  ctx.strokeStyle='rgba(15,23,42,.14)';
  ctx.strokeRect(plot.x, plot.y, plot.w, plot.h);

  ctx.setLineDash([6,6]);
  ctx.strokeStyle='rgba(15,23,42,.18)';
  ctx.beginPath(); ctx.moveTo(midX, plot.y); ctx.lineTo(midX, plot.y+plot.h); ctx.stroke();
  ctx.beginPath(); ctx.moveTo(plot.x, midY); ctx.lineTo(plot.x+plot.w, midY); ctx.stroke();
  ctx.setLineDash([]);

  // eixos
  ctx.fillStyle='rgba(15,23,42,.55)';
  font(ctx,11,900);
  ctx.fillText('Margem % ↑', plot.x-52, plot.y-6);
  ctx.fillText('Faturamento →', plot.x+plot.w-96, plot.y+plot.h+32);

  // legenda
  const legend = [
    {label:'⭐ Estratégico', color:'rgba(34,197,94,.90)'},
    {label:'💰 Volume',     color:'rgba(245,158,11,.90)'},
    {label:'💎 Potencial',  color:'rgba(59,130,246,.90)'},
    {label:'⚠ Risco',      color:'rgba(239,68,68,.90)'},
  ];
  let lx = plot.x + 10, ly = plot.y + 10;
  legend.forEach(it=>{
    ctx.fillStyle = it.color;
    ctx.beginPath(); ctx.arc(lx,ly,5,0,Math.PI*2); ctx.fill();
    ctx.fillStyle='rgba(15,23,42,.65)';
    font(ctx,11,900);
    ctx.fillText(it.label, lx+10, ly+4);
    ly += 16;
  });

  // pontos + hit
  const points = [];
  const logNorm = (v)=> (Math.log10(Math.max(0,Number(v||0))+1) / Math.log10(maxFat+1));

  items.forEach(it=>{
    const valor = Number(it.valor||0);
    const marg  = Number(it.margem_pct||0);

    const x = xScale(valor);
    const y = yScale(marg);

    // cor por quadrante (usando medias reais)
    const highFat  = valor >= avgFat;
    const highMarg = marg  >= avgMarg;

    let color = 'rgba(239,68,68,.85)';        // risco
    if (highFat && highMarg) color = 'rgba(34,197,94,.85)';       // estratégico
    else if (highFat && !highMarg) color = 'rgba(245,158,11,.85)';// volume
    else if (!highFat && highMarg) color = 'rgba(59,130,246,.85)';// potencial

    const s = 4 + logNorm(valor)*14;
    const isSel = selectedKey && String(it.key||'') === String(selectedKey);

    ctx.save();
    if (selectedKey && !isSel) ctx.globalAlpha = 0.35;

    ctx.fillStyle = color;
    ctx.beginPath(); ctx.arc(x,y,s,0,Math.PI*2); ctx.fill();

    if (isSel){
      ctx.globalAlpha = 1;
      ctx.lineWidth = 3;
      ctx.strokeStyle = 'rgba(15,23,42,.85)';
      ctx.beginPath(); ctx.arc(x,y,s+1.5,0,Math.PI*2); ctx.stroke();
    }
    ctx.restore();

    points.push({ x, y, r: s+6, it });
  });

  ctx.restore();
  canvas._matrixHit = points;

  // tooltip
  if (!tip) return;

  canvas.onmousemove = (e)=>{
    const rect = canvas.getBoundingClientRect();
    const mx = e.clientX - rect.left;
    const my = e.clientY - rect.top;

    const pts = canvas._matrixHit || [];
    let hit = null;

    for (let i=0;i<pts.length;i++){
      const p = pts[i];
      const dx = mx - p.x;
      const dy = my - p.y;
      if (dx*dx + dy*dy <= p.r*p.r){ hit = p.it; break; }
    }

    if (!hit){ tip.style.display='none'; return; }

    const nome = String(hit.cliente ?? '');
    const v = Number(hit.valor ?? 0);
    const m = Number(hit.margem_pct ?? 0);

    tip.innerHTML =
      `<div style="font-weight:1000">${esc(nome)}</div>`+
      `<div style="opacity:.92;margin-top:4px">Faturamento: ${brl(v)} <span style="opacity:.7">•</span> Margem: ${(m*100).toFixed(1)}%</div>`;

    tip.style.display = 'block';
    tip.style.left = (e.clientX + 14) + 'px';
    tip.style.top  = (e.clientY + 14) + 'px';

    const maxX = window.innerWidth - tip.offsetWidth - 14;
    const maxY = window.innerHeight - tip.offsetHeight - 14;
    tip.style.left = Math.min(parseInt(tip.style.left,10), maxX) + 'px';
    tip.style.top  = Math.min(parseInt(tip.style.top,10), maxY) + 'px';
  };

  canvas.onmouseleave = ()=>{ tip.style.display='none'; };
}

// ---------- DATA ----------
let DATA = null;
let ACTIVE_YM = null;

// ---------- load / render ----------
async function load(force=false){
  $('wrap')?.classList.add('loading');
  setErr('');

  const qs = new URLSearchParams();
  qs.set('ym', ACTIVE_YM || '');
  if (force) qs.set('force','1');

  const url = '../api/clientes_insights.php?' + qs.toString();

  try{
    const r = await fetch(url, { cache:'no-store' });
    if (!r.ok) throw new Error('HTTP ' + r.status);

    const j = await r.json();
    DATA = j;

    $('updatedAt') && ($('updatedAt').textContent = j.updated_at || '--/--/---- --:--');

    const range = j.meta?.range_mes || [];
    $('periodo') && ($('periodo').textContent = (range.length===2) ? (range[0]+' até '+range[1]) : (ACTIVE_YM || '--'));

    $('cacheInfo') && ($('cacheInfo').textContent = (j.meta?.forced ? 'MISS (force)' : 'HIT/10min'));

    // dropdown usa top50 se existir
    const sel = $('clienteSelect');
    if (sel){
      const prev = sel.value;
      const baseTop = (j.ranking?.top50?.length ? j.ranking.top50 : (j.ranking?.top10 || []));

      sel.innerHTML = '<option value="">Cliente (Top 50)</option>';
      baseTop.forEach(c=>{
        const opt = document.createElement('option');
        opt.value = c.key;
        opt.textContent = `${c.cliente} (${brl(c.valor)})`;
        sel.appendChild(opt);
      });

      if (prev && Array.from(sel.options).some(o=>o.value===prev)) sel.value = prev;
      if (!sel.value && baseTop?.[0]?.key) sel.value = baseTop[0].key;
    }

    render();
  }catch(e){
    setErr('Erro ao carregar: ' + (e?.message || e));
  }finally{
    $('wrap')?.classList.remove('loading');
  }
}

function render(){
  if (!DATA) return;

  const k = DATA.kpis || {};

  $('kpiClientes') && ($('kpiClientes').textContent = nfmt(k.clientes_ativos || 0));
  $('kpiTop3')     && ($('kpiTop3').textContent     = 'Top 3: ' + pct(k.top3_pct || 0, 0));

  $('kpiTicket')   && ($('kpiTicket').textContent   = brl(k.ticket_medio || 0));
  $('kpiPedidos')  && ($('kpiPedidos').textContent  = 'NFs: ' + nfmt(k.pedidos_mes || 0));

  $('kpiMargCli')  && ($('kpiMargCli').textContent  = brl(k.margem_media_cliente || 0));
  $('kpiMargPct')  && ($('kpiMargPct').textContent  = 'Margem %: ' + pct(k.margem_pct_media || 0, 1));

  $('kpiDesc')     && ($('kpiDesc').textContent     = pct(k.desconto_medio || 0, 1));
  $('kpiAlert')    && ($('kpiAlert').textContent    = DATA.insight?.alerta || 'Sem alerta');

  const selKey = $('clienteSelect')?.value || '';

  // Insight
  const ins = (DATA.insight?.por_cliente && selKey) ? DATA.insight.por_cliente[selKey] : null;
  if (ins && ins.text){
    $('insTag')  && ($('insTag').textContent  = ins.tag || '📊 Insight');
    $('insText') && ($('insText').innerHTML   = esc(ins.text));
    $('insHint') && ($('insHint').textContent = 'Base: crescimento • margem • desconto • inatividade');
  } else {
    $('insTag')  && ($('insTag').textContent  = '🧠 Insight');
    $('insText') && ($('insText').textContent = 'Selecione um cliente no dropdown para ver insights automáticos.');
    $('insHint') && ($('insHint').textContent = 'Selecione um cliente');
  }

  $('rankMeta') && ($('rankMeta').textContent = 'Total mês: ' + brl(DATA.kpis?.faturamento_mes || 0));

  // base top (ranking)
  const rankingBase = (DATA.ranking?.top50?.length ? DATA.ranking.top50 : (DATA.ranking?.top10 || []));
  const ranking = rankingBase.map(x=>({ key:x.key, cliente:x.cliente, valor:x.valor }));

  // lista top50
  renderRankList('topClientesList', ranking, 'valor', 'cliente', 50, (it)=>{
    const sel = $('clienteSelect');
    if (sel && it.key){
      sel.value = it.key;
      render();
    }
  });

  // ABC
  const abc = (DATA.abc?.items || []).slice(0, 20).map(x=>({ cliente:x.cliente, valor:x.valor }));
  const cABC = $('cABC');
  if (cABC){
    paretoChart(cABC, abc);
    enableParetoTooltip(cABC);
  }

  // Evolução
  const evo = (DATA.evolucao?.[selKey] || {labels:[], valores:[]});
  $('evoMeta') && ($('evoMeta').textContent = selKey ? ('Cliente: ' + (ranking.find(r=>r.key===selKey)?.cliente || '')) : '—');
  $('cEvo') && lineChart($('cEvo'), evo.labels || [], evo.valores || []);

  // Margem (top10)
  const marg = (DATA.margem?.top10 || []).map(x=>({ cliente:x.cliente, valor:x.margem_pct }));
  $('cMargem') && vbarChart($('cMargem'), marg, 'valor', 'cliente',
    (it,v)=> (v>=0.25?'rgba(34,197,94,.72)':(v>=0.12?'rgba(245,158,11,.78)':'rgba(239,68,68,.78)')),
    5
  );

  // Frequência
  const bins = (DATA.frequencia?.bins || []);
  $('cFreq') && vbarChart($('cFreq'), bins.map(b=>({ label:b.label, valor:b.count })), 'valor','label',
    ()=> 'rgba(15,23,42,.78)',
    4
  );

  // Score list
  const scoreBase = (DATA.score?.top50?.length ? DATA.score.top50 : (DATA.score?.top10 || []));
  renderScoreList('scoreClientesList', scoreBase, 20, (it)=>{
    const sel = $('clienteSelect');
    if (sel && it.key){
      sel.value = it.key;
      render();
    }
  });

  // MATRIZ (Top50) — precisa existir <canvas id="cMatrix">
  const cMatrix = $('cMatrix');
  if (cMatrix){
    // margem base: top50 se existir (ideal), senão top10
    const margBase = (DATA.margem?.top50?.length ? DATA.margem.top50 : (DATA.margem?.top10 || []));

    const matrixData = ranking.map(c=>({
      key: c.key,
      cliente: c.cliente,
      valor: Number(c.valor || 0),
      margem_pct: Number((margBase.find(m=>m.key===c.key)?.margem_pct) || 0)
    }));

    matrixChart(cMatrix, matrixData, selKey);
  }
}

// ---------- init ----------
(function init(){
  const params = new URLSearchParams(location.search);
  const ym = params.get('ym');
  const firstBtn = document.querySelector('#monthBar .pill');

  if (ym && /^\d{4}-\d{2}$/.test(ym)) setActiveMonth(ym);
  else if (firstBtn?.dataset?.ym) setActiveMonth(firstBtn.dataset.ym);
  else setActiveMonth(new Date().toISOString().slice(0,7));

  document.querySelectorAll('#monthBar .pill').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      setActiveMonth(btn.dataset.ym);
      load(false);

      const url = new URL(location.href);
      url.searchParams.set('ym', btn.dataset.ym);
      history.replaceState({}, '', url.toString());
    });
  });

  $('btnRefresh')?.addEventListener('click', ()=> load(true));
  $('clienteSelect')?.addEventListener('change', ()=> render());

  window.addEventListener('resize', ()=>{ if (DATA) render(); });

  load(false);
  setInterval(()=> load(false), 10*60*1000);
})();
