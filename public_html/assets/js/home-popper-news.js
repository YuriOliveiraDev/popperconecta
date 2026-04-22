(function () {
  'use strict';

  function brl(value) {
    const n = Number(value || 0);
    return n.toLocaleString('pt-BR', {
      style: 'currency',
      currency: 'BRL'
    });
  }

  function pct(value) {
    const n = Number(value || 0);
    return n.toLocaleString('pt-BR', {
      minimumFractionDigits: 1,
      maximumFractionDigits: 1
    }) + '%';
  }

  async function loadKpis() {
    try {
      const resp = await fetch('/api/home/kpis.php', {
        headers: { 'Accept': 'application/json' }
      });

      if (!resp.ok) {
        throw new Error('Falha ao carregar KPIs');
      }

      const data = await resp.json();

      document.getElementById('kpi-faturamento').textContent = brl(data.faturamento_mes);
      document.getElementById('kpi-meta').textContent = brl(data.meta_mes);
      document.getElementById('kpi-atingimento').textContent = pct(data.atingimento_pct);
      document.getElementById('kpi-inadimplencia').textContent = brl(data.inadimplencia_total);

      document.getElementById('kpi-faturamento-meta').textContent =
        (data.faturamento_variacao_txt || 'Comparativo atualizado');

      document.getElementById('kpi-meta-meta').textContent =
        (data.meta_descricao || 'Meta do mês atual');

      document.getElementById('kpi-atingimento-meta').textContent =
        (data.atingimento_descricao || 'Em relação à meta do período');

      document.getElementById('kpi-inadimplencia-meta').textContent =
        (data.inadimplencia_descricao || '% sobre faturamento do período');

    } catch (err) {
      console.error(err);
      document.getElementById('kpi-faturamento-meta').textContent = 'Não foi possível carregar';
      document.getElementById('kpi-meta-meta').textContent = 'Não foi possível carregar';
      document.getElementById('kpi-atingimento-meta').textContent = 'Não foi possível carregar';
      document.getElementById('kpi-inadimplencia-meta').textContent = 'Não foi possível carregar';
    }
  }

  async function loadAniversariantes() {
    try {
      const resp = await fetch('/api/home/aniversariantes.php', {
        headers: { 'Accept': 'application/json' }
      });

      if (!resp.ok) {
        return;
      }

      const data = await resp.json();
      if (!Array.isArray(data) || !data.length) return;

      const wrap = document.getElementById('birthday-list');
      wrap.innerHTML = '';

      data.forEach(item => {
        const nome = String(item.nome || '').trim();
        const dataNiver = String(item.data || '').trim();
        const sigla = nome
          .split(/\s+/)
          .filter(Boolean)
          .slice(0, 2)
          .map(p => p[0].toUpperCase())
          .join('');

        const row = document.createElement('div');
        row.className = 'birthday-item';
        row.innerHTML = `
          <div class="birthday-item__avatar">${sigla || '--'}</div>
          <div>
            <strong>${escapeHtml(nome)}</strong>
            <span>${escapeHtml(dataNiver)}</span>
          </div>
        `;
        wrap.appendChild(row);
      });

    } catch (err) {
      console.error(err);
    }
  }

  function escapeHtml(str) {
    return String(str)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  document.addEventListener('DOMContentLoaded', function () {
    loadKpis();
    loadAniversariantes();
  });
})();