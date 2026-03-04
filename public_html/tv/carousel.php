<?php
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';

require_login();

/* anti-cache forte (TV Box / kiosk) */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function h(?string $s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* mesmos comunicados do index */
$stmt = db()->prepare('
  SELECT id, titulo, conteudo, imagem_path
  FROM comunicados
  WHERE ativo = TRUE
  ORDER BY ordem ASC, id ASC
');
$stmt->execute();
$comunicados = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title>TV — Comunicados</title>

  <!-- CSS do carousel (você pode manter o mesmo) -->
  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/../assets/css/base.css') ?>" />
  <link rel="stylesheet" href="/assets/css/carousel.css?v=<?= filemtime(__DIR__ . '/../assets/css/carousel.css') ?>" />

  <style>
    :root{ --vh: 1vh; }

    html, body {
      height: 100%;
      margin: 0;
      background: #000;
      overflow: hidden;
    }

    /* pagina só do carousel */
    main, .carousel, .carousel__viewport { height: 100%; }

    /* viewport real (TV box) */
    .carousel__viewport{
      height: calc(var(--vh) * 100);
      overflow: hidden;
    }

    /* garante 1 slide por tela e evita “quebra” */
    .carousel__track{ display:flex; height:100%; will-change: transform; }
    .slide{ flex: 0 0 100%; width:100%; height:100%; min-width:100%; }

    .slide--image .slide__inner{ width:100%; height:100%; }
    .slide__img-full{
      width:100%;
      height:100%;
      display:block;
      object-fit: contain;
      background:#000;
    }

    /* remove setas/dots se quiser “limpo” */
    .carousel__arrow, .carousel__dots { display:none !important; }

    /* botão fullscreen escondido (entra sozinho) */
    .carousel__fullscreen{ display:none !important; }
  </style>
</head>

<body>
<main>
  <section class="carousel carousel--full full-bleed" id="mainCarousel">

    <div class="carousel__viewport">
      <div class="carousel__track" id="track">
        <?php if (empty($comunicados)): ?>
          <article class="slide slide--text" data-id="0">
            <div class="slide__doc">
              <div class="doc__title">Bem-vindo</div>
              <div class="doc__body">Fique atento aos novos comunicados da Popper aqui.</div>
            </div>
          </article>
        <?php else: ?>
          <?php foreach ($comunicados as $c): ?>
            <?php
              $id = (int)($c['id'] ?? 0);
              $img = trim((string)($c['imagem_path'] ?? ''));
              $titulo = trim((string)($c['titulo'] ?? ''));
              $conteudo = trim((string)($c['conteudo'] ?? ''));
              $hasImage = ($img !== '');
              $hasText = ($titulo !== '' || $conteudo !== '');
            ?>

            <?php if ($hasImage): ?>
              <article class="slide slide--image" data-id="<?= $id ?>">
                <div class="slide__inner">
                  <img class="slide__img-full" src="<?= h($img) ?>" alt="Comunicado">
                </div>
              </article>
            <?php else: ?>
              <article class="slide slide--text" data-id="<?= $id ?>">
                <div class="slide__doc">
                  <?php if ($titulo !== ''): ?><div class="doc__title"><?= h($titulo) ?></div><?php endif; ?>
                  <?php if ($conteudo !== ''): ?><div class="doc__body"><?= nl2br(h($conteudo)) ?></div><?php endif; ?>

                  <?php if (!$hasText): ?>
                    <div class="doc__title">Comunicado</div>
                    <div class="doc__body">Sem conteúdo.</div>
                  <?php endif; ?>
                </div>
              </article>
            <?php endif; ?>

          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Mantém o container dos dots só para o JS não quebrar -->
    <div class="carousel__dots" id="dots" aria-label="Indicadores do carrossel"></div>
  </section>
</main>

<!-- JS do carrossel (use UM só arquivo) -->
<script src="/assets/js/index-carousel.js?v=<?= filemtime(__DIR__ . '/../assets/js/index-carousel.js') ?>"></script>

<!-- 1) VH real (TV Box) -->
<script>
(function setRealVh(){
  const apply = () => {
    const vh = (window.innerHeight || document.documentElement.clientHeight || 0) * 0.01;
    document.documentElement.style.setProperty('--vh', `${vh}px`);
  };
  apply();
  window.addEventListener('resize', apply);
  window.addEventListener('orientationchange', apply);
  document.addEventListener('fullscreenchange', apply);
  document.addEventListener('webkitfullscreenchange', apply);
})();
</script>

<!-- 2) FULLSCREEN automático (kiosk) -->
<script>
(function autoFullscreen(){
  const target = document.documentElement;

  function isFs(){
    return !!(document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement);
  }

  function reqFs(el){
    const fn = el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen;
    if (!fn) return;
    try { return fn.call(el); } catch(e) { /* ignore */ }
  }

  // tenta algumas vezes (TV Box costuma bloquear na 1ª)
  let tries = 0;
  const tick = () => {
    tries++;
    if (!isFs()) reqFs(target);
    if (tries < 6 && !isFs()) setTimeout(tick, 900);
  };

  // primeira tentativa assim que carregar
  window.addEventListener('load', () => setTimeout(tick, 300));

  // fallback: primeiro toque/click libera fullscreen
  ['click','touchstart','keydown'].forEach(ev => {
    document.addEventListener(ev, () => { if (!isFs()) reqFs(target); }, { once:true, passive:true });
  });
})();
</script>

<!-- 3) AUTO-UPDATE via feed (mantém fullscreen) -->
<script>
(function(){
  const FEED_URL = '/api/comunicados-feed.php';
  const POLL_MS = 8000;

  const trackEl = document.getElementById('track');
  if (!trackEl) return;

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
    const img = (item.imagem_path || '').trim();
    const titulo = (item.titulo || '').trim();
    const conteudo = (item.conteudo || '').trim();
    const hasImage = img !== '';
    const hasText = (titulo !== '' || conteudo !== '');

    if (hasImage) {
      return `
        <article class="slide slide--image" data-id="${id}">
          <div class="slide__inner">
            <img class="slide__img-full" src="${esc(img)}" alt="Comunicado">
          </div>
        </article>
      `;
    }

    if (!hasText) {
      return `
        <article class="slide slide--text" data-id="${id}">
          <div class="slide__doc">
            <div class="doc__title">Comunicado</div>
            <div class="doc__body">Sem conteúdo.</div>
          </div>
        </article>
      `;
    }

    const body = conteudo ? esc(conteudo).replace(/\n/g,'<br>') : '';

    return `
      <article class="slide slide--text" data-id="${id}">
        <div class="slide__doc">
          ${titulo ? `<div class="doc__title">${esc(titulo)}</div>` : ''}
          ${body ? `<div class="doc__body">${body}</div>` : ''}
        </div>
      </article>
    `;
  }

  function getCurrentSlideId(){
    try{
      if (!window.carouselGetIndex) return null;
      const idx = Number(window.carouselGetIndex()) || 0;
      const slides = trackEl.querySelectorAll('.slide');
      const el = slides[idx];
      const id = el && el.getAttribute('data-id');
      return id ? String(id) : null;
    }catch(e){ return null; }
  }

  function goToSlideById(id){
    try{
      if (!id || !window.carouselGoTo) return false;
      const slides = Array.from(trackEl.querySelectorAll('.slide'));
      const idx = slides.findIndex(s => String(s.getAttribute('data-id')) === String(id));
      if (idx >= 0) { window.carouselGoTo(idx); return true; }
      return false;
    }catch(e){ return false; }
  }

  function refreshAfterImages(){
    const imgs = Array.from(trackEl.querySelectorAll('img'));
    if (!imgs.length) {
      window.carouselRefresh && window.carouselRefresh();
      return;
    }

    let left = imgs.length;
    const done = () => {
      left--;
      if (left <= 0) {
        window.carouselRefresh && window.carouselRefresh();
        requestAnimationFrame(() => window.carouselRefresh && window.carouselRefresh());
        setTimeout(() => window.carouselRefresh && window.carouselRefresh(), 120);
      }
    };

    imgs.forEach(img => {
      if (img.complete) return done();
      img.addEventListener('load', done, { once:true });
      img.addEventListener('error', done, { once:true });
    });
  }

  async function poll(){
    if (inFlight) return;
    inFlight = true;

    try{
      const res = await fetch(`${FEED_URL}?_=${Date.now()}`, {
        cache: 'no-store',
        credentials: 'same-origin',
        headers: { 'Accept':'application/json' }
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      const data = await res.json();
      if (!data || !data.ok) return;

      if (currentVersion === null) {
        currentVersion = data.version || '';
        return;
      }

      if ((data.version || '') === currentVersion) return;

      const keepId = getCurrentSlideId();
      const items = Array.isArray(data.items) ? data.items : [];

      trackEl.innerHTML = items.length
        ? items.map(buildSlideHTML).join('')
        : `
          <article class="slide slide--text" data-id="0">
            <div class="slide__doc">
              <div class="doc__title">Bem-vindo</div>
              <div class="doc__body">Fique atento aos novos comunicados da Popper aqui.</div>
            </div>
          </article>
        `;

      currentVersion = data.version || '';

      refreshAfterImages();

      // tenta manter slide atual por ID
      setTimeout(() => {
        if (keepId) {
          const ok = goToSlideById(keepId);
          if (!ok && window.carouselGoTo) window.carouselGoTo(0);
        } else if (window.carouselGoTo) {
          window.carouselGoTo(0);
        }
      }, 150);

    } catch(e){
      console.warn('feed poll falhou:', e);
    } finally {
      inFlight = false;
    }
  }

  setInterval(poll, POLL_MS);
  setTimeout(poll, 1200);
})();
</script>

</body>
</html>