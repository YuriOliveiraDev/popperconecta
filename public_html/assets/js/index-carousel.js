// FIX altura real para Android / TV Box / Fullscreen
(function setRealVH(){

  function apply(){
    const vh = window.innerHeight * 0.01;

    document.documentElement.style.setProperty('--vh', vh + 'px');
    document.documentElement.style.setProperty('--vhpx', window.innerHeight + 'px');
  }

  apply();

  window.addEventListener('resize', apply);
  window.addEventListener('orientationchange', apply);

  document.addEventListener('fullscreenchange', () => {
    setTimeout(apply, 60);
  });

  document.addEventListener('webkitfullscreenchange', () => {
    setTimeout(apply, 60);
  });

})();

// ✅ Força altura real do iframe (resolve TV Box que ignora 100vh/dvh)
(function forceIframeSize(){
  const frame = document.getElementById('frame');
  if (!frame) return;

  const apply = () => {
    const h = Math.max(1, window.innerHeight || document.documentElement.clientHeight || 0);
    const w = Math.max(1, window.innerWidth  || document.documentElement.clientWidth  || 0);

    frame.style.height = h + 'px';
    frame.style.width  = w + 'px';

    // evita “barrinha” por arredondamento/subpixel
    frame.style.transform = 'translateZ(0)';
  };

  apply();
  window.addEventListener('resize', apply);
  window.addEventListener('orientationchange', apply);

  // fullscreen em TVs muda dimensão sem resize às vezes
  document.addEventListener('fullscreenchange', () => setTimeout(apply, 50));
  document.addEventListener('webkitfullscreenchange', () => setTimeout(apply, 50));

  // alguns devices estabilizam depois
  setTimeout(apply, 120);
  setTimeout(apply, 500);
})();
/* global window, document */

const carousel = document.getElementById('mainCarousel');
const viewport = document.querySelector('#mainCarousel .carousel__viewport');
const track = document.getElementById('track');
const prev = document.getElementById('prevBtn');
const next = document.getElementById('nextBtn');
const dotsEl = document.getElementById('dots');

// ✅ detecta se está dentro de iframe (modo TV/Tablet shell)
const IN_IFRAME = (() => {
  try { return window.self !== window.top; } catch { return true; }
})();

let index = 0;
let timer = null;

const AUTOPLAY_MS = 2000;

function totalSlides(){
  return track ? track.querySelectorAll('.slide').length : 0;
}

function pageWidth(){
  if (!viewport) return 0;
  return Math.round(viewport.getBoundingClientRect().width);
}

function clampIndex(){
  const total = totalSlides();
  if (total <= 0) { index = 0; return; }
  index = Math.max(0, Math.min(total - 1, index));
}

function setActiveDot(){
  if (!dotsEl) return;
  const dots = dotsEl.querySelectorAll('.carousel__dot');
  dots.forEach((d, i) => d.classList.toggle('is-active', i === index));
}

function render(){
  if (!track || !viewport) return;

  clampIndex();

  const w = pageWidth();
  // Se a largura ainda não está pronta, agenda novo render (evita travar dots/posição)
  if (!w) {
    requestAnimationFrame(render);
    return;
  }

  const x = Math.round(index * w);
  track.style.transform = `translate3d(${-x}px,0,0)`;

  setActiveDot();
}

function buildDots(){
  if (!dotsEl) return;

  const total = totalSlides();
  dotsEl.innerHTML = '';
  if (total <= 1) return;

  for (let i = 0; i < total; i++) {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'carousel__dot';
    b.setAttribute('aria-label', `Ir para o slide ${i + 1}`);
    b.dataset.index = String(i);

    b.addEventListener('click', () => {
      index = i;
      render();
      restartAutoplay();
      wakeFsButton();
    });

    dotsEl.appendChild(b);
  }

  setActiveDot();
}

function stopAutoplay(){
  if (timer) window.clearInterval(timer);
  timer = null;
}

function startAutoplay(){
  stopAutoplay();
  const total = totalSlides();
  if (total <= 1) return;

  timer = window.setInterval(() => {
    const totalNow = totalSlides();
    if (totalNow <= 1) return;
    index = (index + 1) % totalNow;
    render();
  }, AUTOPLAY_MS);
}

function restartAutoplay(){
  startAutoplay();
}

function goPrev(){
  index = Math.max(0, index - 1);
  render();
  restartAutoplay();
  wakeFsButton();
}

function goNext(){
  const total = totalSlides();
  if (total <= 0) return;

  index = (index + 1) % total;
  render();
  restartAutoplay();
  wakeFsButton();
}

prev?.addEventListener('click', goPrev);
next?.addEventListener('click', goNext);

// Em alguns devices (TV Box), fullscreen muda o viewport sem disparar resize
window.addEventListener('resize', () => {
  render();
  restartAutoplay();
});

window.addEventListener('load', () => {
  buildDots();
  render();
  startAutoplay();
});

// Se slides mudarem, reconstrói dots
function refreshAll(){
  buildDots();
  clampIndex();
  render();
  startAutoplay();
}

// API pública
window.carouselRefresh = refreshAll;
window.carouselGoTo = (i) => { index = Number(i) || 0; render(); restartAutoplay(); wakeFsButton(); };
window.carouselGetIndex = () => index;

// Inicialização defensiva
setTimeout(refreshAll, 50);

// pausa autoplay SOMENTE em device com mouse
(() => {
  if (!carousel) return;

  const canHover = window.matchMedia && window.matchMedia('(hover:hover) and (pointer:fine)').matches;

  if (canHover) {
    carousel.addEventListener('pointerenter', stopAutoplay);
    carousel.addEventListener('pointerleave', startAutoplay);
  }
})();

/* =========================================================
   FULLSCREEN
   ✅ DESLIGA EM IFRAME (TV/Tablet shell cuida disso)
   ========================================================= */

// controle do "sumir" do botão fullscreen após 10s
let __fsBtnRef = null;
let __fsIdleT = null;

function wakeFsButton(){
  if (!__fsBtnRef) return;
  __fsBtnRef.classList.remove('is-idle');
  if (__fsIdleT) window.clearTimeout(__fsIdleT);
  __fsIdleT = window.setTimeout(() => {
    __fsBtnRef && __fsBtnRef.classList.add('is-idle');
  }, 10000);
}

(function(){
  if (IN_IFRAME) return; // ✅ TV/Tablet: fullscreen é responsabilidade do /tv/index.php

  const fsBtn = document.getElementById('fullscreenBtn');
  if (!fsBtn) return;

  __fsBtnRef = fsBtn;

  const fsTarget = document.documentElement;

  function isFs(){
    return !!(document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement);
  }

  function reqFs(el){
    const fn = el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen;
    if (!fn) throw new Error('Fullscreen API não suportada');
    return fn.call(el);
  }

  function exitFs(){
    const fn = document.exitFullscreen || document.webkitExitFullscreen || document.msExitFullscreen;
    if (!fn) return;
    return fn.call(document);
  }

  function setIcon(){
    const svg = fsBtn.querySelector('svg');
    if (!svg) return;

    svg.innerHTML = isFs()
      ? `<path d="M9 4H4v5M15 4h5v5M9 20H4v-5M15 20h5v-5"
           stroke="currentColor" stroke-width="2"
           stroke-linecap="round" stroke-linejoin="round"/>`
      : `<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"
           stroke="currentColor" stroke-width="2"
           stroke-linecap="round" stroke-linejoin="round"/>`;
  }

  async function toggleFs(){
    try{
      if (isFs()) await exitFs();
      else await reqFs(fsTarget);
    } catch (e) {
      console.warn('Fullscreen falhou:', e);
    } finally {
      setIcon();
    }
  }

  function onFsChange(){
    setIcon();
    requestAnimationFrame(() => { render(); restartAutoplay(); });
    setTimeout(() => { render(); restartAutoplay(); }, 120);
    wakeFsButton();
  }

  fsBtn.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    toggleFs();
    wakeFsButton();
  });

  ['mousemove','pointermove','keydown','touchstart','click'].forEach((ev) => {
    document.addEventListener(ev, wakeFsButton, { passive: true });
  });

  document.addEventListener('fullscreenchange', onFsChange);
  document.addEventListener('webkitfullscreenchange', onFsChange);
  document.addEventListener('MSFullscreenChange', onFsChange);

  setIcon();
  wakeFsButton();
})();
/* =========================================================
   AUTO-UPDATE (tempo real via polling)
   - atualiza slides em fullscreen sem reload
   ========================================================= */
(() => {
  const FEED_URL = '/api/comunicados-feed.php';
  const POLL_MS = 8000;

  let currentVersion = null;
  let inFlight = false;

  function esc(s){
    return String(s ?? '')
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;')
      .replace(/'/g,'&#039;');
  }

  function buildSlideHTML(item){
    const id = Number(item.id) || 0;
    const titulo = esc(item.titulo || '');
    const conteudo = esc(item.conteudo || '');
    const img = item.imagem_path ? esc(item.imagem_path) : '';

    // ✅ adapte este HTML ao seu layout real do slide
    return `
      <div class="slide" data-id="${id}">
        ${img ? `<img class="slide__img" src="${img}" alt="">` : ''}
        <div class="slide__content">
          ${titulo ? `<h3 class="slide__title">${titulo}</h3>` : ''}
          ${conteudo ? `<p class="slide__text">${conteudo}</p>` : ''}
        </div>
      </div>
    `;
  }

  function getCurrentSlideId(){
    try{
      const slides = track?.querySelectorAll('.slide');
      if (!slides || !slides.length) return null;
      const el = slides[index];
      const id = el?.getAttribute('data-id');
      return id ? String(id) : null;
    } catch {
      return null;
    }
  }

  function goToSlideById(id){
    if (!id || !track) return false;
    const slides = Array.from(track.querySelectorAll('.slide'));
    const idx = slides.findIndex(s => String(s.getAttribute('data-id')) === String(id));
    if (idx >= 0) {
      index = idx;
      return true;
    }
    return false;
  }

  async function poll(){
    if (inFlight) return;
    inFlight = true;

    try{
      const res = await fetch(`${FEED_URL}?_=${Date.now()}`, {
        cache: 'no-store',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });

      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (!data || !data.ok) return;

      // primeira vez só “aprende” a versão
      if (currentVersion === null) {
        currentVersion = data.version || '';
        return;
      }

      // se não mudou, não faz nada
      if ((data.version || '') === currentVersion) return;

      // mudou: aplica atualização
      const keepId = getCurrentSlideId();

      const items = Array.isArray(data.items) ? data.items : [];
      if (track) {
        track.innerHTML = items.map(buildSlideHTML).join('');
      }

      // tenta manter o slide atual por ID
      const kept = keepId ? goToSlideById(keepId) : false;
      if (!kept) index = 0;

      currentVersion = data.version || '';

      // reconstrói dots e recalcula transform/autoplay
      if (window.carouselRefresh) window.carouselRefresh();

    } catch (e){
      // em TV Box é melhor só logar baixo e seguir
      console.warn('carousel feed poll falhou:', e);
    } finally {
      inFlight = false;
    }
  }

  // start
  setInterval(poll, POLL_MS);
  setTimeout(poll, 1200);
})();