(function () {
  function brl(v) {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL'
    }).format(Number(v) || 0);
  }

  function pct(v) {
    return new Intl.NumberFormat('pt-BR', {
      minimumFractionDigits: 1,
      maximumFractionDigits: 1
    }).format(Number(v) || 0) + '%';
  }

  function safeText(id, text) {
    var el = document.getElementById(id);
    if (el) {
      el.textContent = text;
    }
  }

  const kpiCacheKey = 'index2:hero-kpis:v1';
  const kpiCacheTtl = 5 * 60 * 1000;

  function readKpiCache(allowExpired) {
    try {
      const raw = localStorage.getItem(kpiCacheKey);
      if (!raw) return null;

      const cached = JSON.parse(raw);
      if (!cached || !cached.savedAt || !cached.data) return null;
      if (!allowExpired && (Date.now() - Number(cached.savedAt)) > kpiCacheTtl) return null;

      return cached.data;
    } catch (err) {
      console.warn(err);
      return null;
    }
  }

  function writeKpiCache(data) {
    try {
      localStorage.setItem(kpiCacheKey, JSON.stringify({
        savedAt: Date.now(),
        data: data
      }));
    } catch (err) {
      console.warn(err);
    }
  }

  function renderHeroKpis(data) {
    const v = data && data.values ? data.values : null;
    if (!v) {
      throw new Error('Payload inválido');
    }

    var hojeTotal = Number(v.hoje_total || 0);
    var mesTotal = Number(v.mes_total || 0);
    var hojePct = mesTotal > 0 ? (hojeTotal / mesTotal) * 100 : 0;

    safeText('hero-kpi-mes', brl(v.mes_total));
    safeText('hero-kpi-meta', brl(v.meta_mes));
    safeText('hero-kpi-ating', pct((Number(v.atingimento_mes_pct) || 0) * 100));
    safeText('hero-kpi-hoje', brl(hojeTotal));

    safeText('hero-kpi-mes-meta', 'Atualizado: ' + (data.updated_at || '--'));
    safeText('hero-kpi-meta-meta', 'Meta comercial vigente');
    safeText('hero-kpi-ating-meta', 'Comparado ao mês atual');
    safeText(
      'hero-kpi-hoje-meta',
      mesTotal > 0
        ? pct(hojePct) + ' do faturamento do mês'
        : 'Sem base mensal para comparar'
    );
  }

  async function loadHeroKpis() {
    const cached = readKpiCache(true);
    const freshCached = readKpiCache(false);
    let renderedFromCache = false;

    if (cached) {
      renderHeroKpis(cached);
      renderedFromCache = true;
    }

    if (freshCached) {
      return;
    }

    try {
      const response = await fetch('/api/dashboard/dashboard-data.php?dash=executivo', {
        cache: 'no-store'
      });

      if (!response.ok) {
        throw new Error('Falha ao carregar indicadores');
      }

      const data = await response.json();
      renderHeroKpis(data);
      writeKpiCache(data);
    } catch (err) {
      console.warn(err);
      if (renderedFromCache) {
        return;
      }

      safeText('hero-kpi-mes-meta', 'Não foi possível carregar');
      safeText('hero-kpi-meta-meta', 'Não foi possível carregar');
      safeText('hero-kpi-ating-meta', 'Não foi possível carregar');
      safeText('hero-kpi-hoje-meta', 'Não foi possível carregar');
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadHeroKpis, { once: true });
  } else {
    loadHeroKpis();
  }
})();
