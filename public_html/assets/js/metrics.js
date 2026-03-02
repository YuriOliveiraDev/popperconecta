/* =========================
   metrics.js (Admin -> Métricas) + Loader + Preview + Ajustes
   ========================= */
(function () {
  'use strict';

  const fmtBRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
  const fmtINT = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 0 });
  const DASH = window.METRICS_DASH || 'executivo';

  // ---------- Loader helpers ----------
  function loaderShow(title, sub) {
    if (window.PopperLoading && typeof window.PopperLoading.show === 'function') {
      window.PopperLoading.show(title || 'Carregando…', sub || 'Aguarde');
    }
  }
  function loaderHide() {
    if (window.PopperLoading && typeof window.PopperLoading.hide === 'function') {
      window.PopperLoading.hide();
    }
  }

  // Loader inicial (UX)
  loaderShow('Carregando…', 'Abrindo métricas');
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => setTimeout(loaderHide, 120), { once: true });
  } else {
    setTimeout(loaderHide, 120);
  }
  window.addEventListener('pageshow', (e) => { if (e.persisted) loaderHide(); });

  // ---------- Parsing / Formatting ----------
  function parsePtBrToNumber(raw) {
    if (raw == null) return null;
    let s = String(raw).trim();
    if (!s) return null;

    s = s.replace(/\s/g, '');
    s = s.replace('R$', '');
    s = s.replace(/%/g, '');
    s = s.replace(/\./g, '');
    s = s.replace(/,/g, '.');

    const n = Number(s);
    return Number.isFinite(n) ? n : null;
  }

  function formatPercent(n) {
    const frac = (n > 1.5) ? (n / 100) : n;
    const pct = frac * 100;
    const hasDecimal = Math.abs(pct - Math.round(pct)) > 1e-9;
    const out = pct.toLocaleString('pt-BR', {
      minimumFractionDigits: hasDecimal ? 2 : 0,
      maximumFractionDigits: hasDecimal ? 2 : 0
    });
    return out + '%';
  }

  function formatInputValue(input) {
    const type = input.dataset.type || 'money';
    if (type === 'text') return;

    const n = parsePtBrToNumber(input.value);
    if (n === null) { input.value = ''; return; }

    if (type === 'int') { input.value = fmtINT.format(Math.round(n)); return; }
    if (type === 'percent') { input.value = formatPercent(n); return; }

    input.value = fmtBRL.format(n);
  }

  function unformatForEditing(input) {
    const type = input.dataset.type || 'money';
    if (type === 'text') return;

    const n = parsePtBrToNumber(input.value);
    if (n === null) return;

    if (type === 'int') { input.value = String(Math.round(n)); return; }

    if (type === 'percent') {
      const frac = (n > 1.5) ? (n / 100) : n;
      const pct = frac * 100;
      const hasDecimal = Math.abs(pct - Math.round(pct)) > 1e-9;
      input.value = pct.toLocaleString('pt-BR', {
        minimumFractionDigits: hasDecimal ? 2 : 0,
        maximumFractionDigits: hasDecimal ? 2 : 0
      });
      return;
    }

    input.value = n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  // Eventos de formatação
  document.querySelectorAll('.metric-input').forEach((input) => {
    input.addEventListener('keydown', (e) => { if (e.key === 'Tab') formatInputValue(input); });
    input.addEventListener('blur', () => formatInputValue(input));
    input.addEventListener('focus', () => unformatForEditing(input));
  });

  // ---------- Loader ao submit ----------
  const metricsForm = document.getElementById('metricsForm');
  if (metricsForm) {
    metricsForm.addEventListener('submit', () => {
      loaderShow('Salvando…', 'Gravando métricas no sistema');
    });
  }

  const ajusteForm = document.getElementById('ajusteForm');
  if (ajusteForm) {
    ajusteForm.addEventListener('submit', () => {
      loaderShow('Salvando…', 'Adicionando ajuste manual');
    });
  }

  document.querySelectorAll('form[data-ajuste-delete]').forEach((f) => {
    f.addEventListener('submit', () => {
      loaderShow('Removendo…', 'Excluindo ajuste manual');
    });
  });

  const btnPreviewForce = document.getElementById('btnPreviewForce');
  if (btnPreviewForce) {
    btnPreviewForce.addEventListener('click', () => {
      loaderShow('Atualizando…', 'Forçando leitura do TOTVS');
    });
  }

  // ---------- Recalcular campos computados no executivo (se existirem inputs) ----------
  if (DASH === 'executivo') {
    function recalculate() {
      const metaAno = parsePtBrToNumber(document.getElementById('m_meta_ano')?.value) || 0;
      const realAno = parsePtBrToNumber(document.getElementById('m_realizado_ano_acum')?.value) || 0;
      const metaMes = parsePtBrToNumber(document.getElementById('m_meta_mes')?.value) || 0;
      const realMes = parsePtBrToNumber(document.getElementById('m_realizado_ate_hoje')?.value) || 0;
      const dTotal = parsePtBrToNumber(document.getElementById('m_dias_uteis_trabalhar')?.value) || 1;
      const dPass = parsePtBrToNumber(document.getElementById('m_dias_uteis_trabalhados')?.value) || 0;

      const faltaAno = Math.max(0, metaAno - realAno);
      const faltaMes = Math.max(0, metaMes - realMes);
      const ating = metaMes > 0 ? (realMes / metaMes) : 0;

      const metaDia = metaMes / Math.max(1, dTotal);
      const deveria = metaDia * dPass;

      const realDia = dPass > 0 ? (realMes / dPass) : 0;
      const prod = metaDia > 0 ? (realDia / metaDia) : 0;

      const diasRest = Math.max(1, dTotal - dPass);
      const aFaturar = faltaMes / diasRest;

      const proj = realDia * dTotal;
      const equiv = metaMes > 0 ? (proj / metaMes) : 0;
      const vaiBater = proj >= metaMes ? 'SIM' : 'NÃO';

      const set = (id, val, type) => {
        const el = document.getElementById(id);
        if (!el) return;
        if (type === 'money') el.value = fmtBRL.format(val);
        else if (type === 'percent') el.value = formatPercent(val);
        else el.value = String(val);
      };

      set('m_falta_meta_ano', faltaAno, 'money');
      set('m_falta_meta_mes', faltaMes, 'money');
      set('m_atingimento_mes_pct', ating, 'percent');
      set('m_deveria_ate_hoje', deveria, 'money');
      set('m_meta_dia_util', metaDia, 'money');
      set('m_a_faturar_dia_util', aFaturar, 'money');
      set('m_realizado_dia_util', realDia, 'money');
      set('m_realizado_dia_util_pct', prod, 'percent');
      set('m_fechar_em', proj, 'money');
      set('m_equivale_pct', equiv, 'percent');

      const vb = document.getElementById('m_vai_bater_meta');
      if (vb) vb.value = vaiBater;
    }

    document.querySelectorAll('.metric-input:not(.is-computed)').forEach((input) => {
      input.addEventListener('input', recalculate);
    });

    recalculate();
  }
})();