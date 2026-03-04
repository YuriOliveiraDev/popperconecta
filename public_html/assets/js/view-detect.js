(function () {
  // Critério: celular "de verdade" costuma ter viewport pequena
  // Ajuste o corte como preferir (900 é um bom padrão para dashboards)
  var w = Math.min(window.innerWidth || 0, window.innerHeight || 0);
  var isSmall = w > 0 && w < 900;

  // Heurística extra: se for TV/controle, normalmente hover none e pointer coarse
  // (não bloqueia TV só por ser coarse — apenas evita falsos positivos)
  var isTVLike = false;
  try {
    isTVLike = window.matchMedia('(hover: none)').matches && window.matchMedia('(pointer: coarse)').matches && (Math.max(innerWidth, innerHeight) >= 900);
  } catch (e) {}

  var val = (isSmall && !isTVLike) ? 'mobile' : 'ok';

  // Cookie simples (30 dias)
  document.cookie = "pc_view=" + val + "; path=/; max-age=" + (60 * 60 * 24 * 30) + "; samesite=Lax";
})();