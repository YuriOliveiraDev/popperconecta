const carousel = document.getElementById('mainCarousel');
const viewport = document.querySelector('#mainCarousel .carousel__viewport');
const track = document.getElementById('track');
const prev = document.getElementById('prevBtn');
const next = document.getElementById('nextBtn');
const dotsEl = document.getElementById('dots');

let index = 0;
let timer = null;

const AUTOPLAY_MS = 30000;

function totalSlides(){
  return track ? track.querySelectorAll('.slide').length : 0;
}

function pageWidth(){
  if (!viewport) return 0;
  return viewport.getBoundingClientRect().width;
}

function clampIndex(){
  const total = totalSlides();
  if (total <= 0) { index = 0; return; }
  index = Math.max(0, Math.min(total - 1, index));
}

function render(){
  if (!track || !viewport) return;

  clampIndex();

  const w = pageWidth();
  if (!w) return;

  track.style.transform = `translateX(${-index * w}px)`;
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
    });

    dotsEl.appendChild(b);
  }

  setActiveDot();
}

function setActiveDot(){
  if (!dotsEl) return;
  const dots = dotsEl.querySelectorAll('.carousel__dot');
  dots.forEach((d, i) => d.classList.toggle('is-active', i === index));
}

function goPrev(){
  index = Math.max(0, index - 1);
  render();
  restartAutoplay();
}

function goNext(){
  const total = totalSlides();
  if (total <= 0) return;

  index = (index + 1) % total; // loop infinito
  render();
  restartAutoplay();
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

prev?.addEventListener('click', goPrev);
next?.addEventListener('click', goNext);

window.addEventListener('resize', render);
window.addEventListener('load', () => {
  buildDots();
  render();
  startAutoplay();
});

// Se slides mudarem (ex.: você adiciona insight server-side ou muda comunicados), reconstrói dots
function refreshAll(){
  buildDots();
  clampIndex();
  render();
  startAutoplay();
}

// API pública (se precisar chamar de outro script)
window.carouselRefresh = refreshAll;
window.carouselGoTo = (i) => { index = Number(i) || 0; render(); restartAutoplay(); };
window.carouselGetIndex = () => index;

// Inicialização defensiva
setTimeout(refreshAll, 50);

// (Opcional) pausa autoplay quando mouse está em cima do carrossel
carousel?.addEventListener('mouseenter', stopAutoplay);
carousel?.addEventListener('mouseleave', startAutoplay);