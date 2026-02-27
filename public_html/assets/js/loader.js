(function(){
  'use strict';

  // =========================
  // PATH AUTO-DETECT (robusto)
  // =========================
  // pega a URL do próprio loader.js carregado pelo browser
  // ex: http://localhost/assets/js/loader.js?v=123
  // vira: http://localhost
  const SCRIPT_SRC = (document.currentScript && document.currentScript.src) ? document.currentScript.src : '';
  const BASE = SCRIPT_SRC ? SCRIPT_SRC.split('/assets/js/')[0] : '';

  // ✅ fontes das imagens (ajuste nomes se precisar)
  const VIP_CENTER_SRC = (BASE || '') + '/assets/img/vip.png';
  const VIP_RING_SRC   = (BASE || '') + '/assets/img/vip-texto.png';

  // =========================
  // HTML (mantém IDs)
  // =========================
const HTML = `
<div id="popperLoading" class="popper-loading popper-loading--minimal" aria-live="polite" aria-busy="false">
  <div class="vip-loader" aria-hidden="true">
    <img class="vip-loader__ring"   src="${VIP_RING_SRC}"   alt="">
    <img class="vip-loader__center" src="${VIP_CENTER_SRC}" alt="">
  </div>
</div>`;

  // =========================
  // garante / substitui loader
  // =========================
  function ensure(){
    let root = document.getElementById('popperLoading');

    // cria versão nova em memória
    const tmp = document.createElement('div');
    tmp.innerHTML = HTML.trim();
    const fresh = tmp.firstElementChild;

    if (!fresh) return null;

    // ✅ se já existia loader antigo (SVG), substitui o conteúdo pelo novo
    if (root) {
      root.className = fresh.className;
      root.setAttribute('aria-live', fresh.getAttribute('aria-live') || 'polite');
      root.innerHTML = fresh.innerHTML;
      return root;
    }

    // injeta no body
    if (!document.body) return null;
    document.body.appendChild(fresh);
    return fresh;
  }

  function setText(title, sub){
    const t = document.getElementById('popperLoadingTitle');
    const s = document.getElementById('popperLoadingSub');
    if (t && typeof title === 'string') t.textContent = title;
    if (s && typeof sub === 'string') s.textContent = sub;
  }

  // valida se imagens carregaram e mostra fallback se não
  function validateImages(){
    const root = document.getElementById('popperLoading');
    if (!root) return;

    const ring = root.querySelector('.vip-loader__ring');
    const center = root.querySelector('.vip-loader__center');
    const fallback = root.querySelector('.vip-fallback');

    function ok(img){
      return !!(img && img.complete && img.naturalWidth > 0);
    }

    const showFallback = !(ok(ring) && ok(center));
    if (fallback) fallback.style.display = showFallback ? 'block' : 'none';

    // se ainda não terminou, escuta load/error uma vez
    if (ring && !ok(ring)) {
      ring.addEventListener('load',  () => validateImages(), { once:true });
      ring.addEventListener('error', () => validateImages(), { once:true });
    }
    if (center && !ok(center)) {
      center.addEventListener('load',  () => validateImages(), { once:true });
      center.addEventListener('error', () => validateImages(), { once:true });
    }
  }

  function open(title, sub){
    const root = ensure();
    if (!root) return;

    setText(title ?? 'Carregando…', sub ?? 'Buscando dados');
    root.classList.add('is-open');
    root.setAttribute('aria-busy', 'true');

    validateImages();
  }

  function close(){
    const root = document.getElementById('popperLoading') || ensure();
    if (!root) return;

    root.classList.remove('is-open');
    root.setAttribute('aria-busy', 'false');
  }

  // API global (igual a sua)
  window.PopperLoading = {
    show: open,
    hide: close,
    setText,
    error(msg){
      open('Falha ao carregar', msg || 'Tente novamente');
    }
  };

  // init
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => { ensure(); validateImages(); }, { once:true });
  } else {
    ensure(); validateImages();
  }
})();