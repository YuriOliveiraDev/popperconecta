(function () {
  'use strict';

  const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

  function setText(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
  }

  function num(v) { return (typeof v === 'number' && isFinite(v)) ? v : 0; }

  function render(payload) {
    const v = payload.values || {};
    const updatedAt = payload.updated_at || '—';

    const faturado = num(v.faturado_dia);
    const contas = num(v.contas_pagar_dia);

    setText('kpi-faturado-dia', brl.format(faturado));
    setText('kpi-contas-pagar-dia', brl.format(contas));
    setText('kpi-fin-updated', `Atualizado: ${updatedAt}`);

    const tbody = document.getElementById('finTableBody');
    if (tbody) {
      tbody.innerHTML = '';
      const rows = [
        ['Faturado no dia', brl.format(faturado)],
        ['Contas a pagar no dia', brl.format(contas)],
      ];
      rows.forEach(([k, val]) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${k}</td><td class="right">${val}</td>`;
        tbody.appendChild(tr);
      });
    }
  }

  async function refresh() {
    const dash = (window.DASH_CURRENT || 'financeiro');
    const res = await fetch(`/api/dashboard/dashboard-data.php?dash=${encodeURIComponent(dash)}`, { cache: 'no-store' });
    const payload = await res.json();
    render(payload);
  }

  refresh();
  setInterval(refresh, 5000);
})();