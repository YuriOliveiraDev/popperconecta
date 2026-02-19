<?php
declare(strict_types=1);
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_login();

$u = current_user();

// Buscar comunicados ativos
$stmt = db()->prepare('SELECT titulo, conteudo FROM comunicados WHERE ativo = TRUE ORDER BY ordem ASC, id ASC');
$stmt->execute();
$comunicados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dashboards ativos (para o dropdown "Dashboards")
try {
  $dashboards = db()
    ->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")
    ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Início — <?= htmlspecialchars((string)APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/assets/css/dashboard.css') ?>" />
  <link rel="stylesheet" href="/assets/css/carousel.css?v=<?= filemtime(__DIR__ . '/assets/css/carousel.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/assets/css/dropdowns.css') ?>" />
</head>
<body class="page">

<?php require_once __DIR__ . '/app/header.php'; ?>

<main class="container">
  <h2 class="page-title">Comunicados</h2>

  <section class="carousel" id="mainCarousel">
    <button class="carousel__arrow carousel__arrow--prev" type="button" id="prevBtn" aria-label="Anterior">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>

    <div class="carousel__viewport">
      <div class="carousel__track" id="track">
        <?php if (empty($comunicados)): ?>
          <article class="slide">
            <div class="slide__inner">
              <h3>Bem-vindo</h3>
              <p>Fique atento aos novos comunicados da Popper aqui.</p>
            </div>
          </article>
        <?php else: ?>
          <?php foreach ($comunicados as $c): ?>
            <article class="slide">
              <div class="slide__inner">
                <h3><?= htmlspecialchars((string)$c['titulo'], ENT_QUOTES, 'UTF-8') ?></h3>
                <p><?= htmlspecialchars((string)$c['conteudo'], ENT_QUOTES, 'UTF-8') ?></p>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <button class="carousel__arrow carousel__arrow--next" type="button" id="nextBtn" aria-label="Próximo">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>

    <div class="carousel__dots" id="dots" aria-label="Indicadores do carrossel"></div>
  </section>

  <div class="card" style="margin-top:16px;">
    <p class="muted" style="margin:0;">Página inicial.</p>
  </div>
</main>

<script>
  // Carrossel (auto + setas + dots)
  const track = document.getElementById('track');
  const slides = Array.from(track.querySelectorAll('.slide'));
  const dotsWrap = document.getElementById('dots');
  const btnPrev = document.getElementById('prevBtn');
  const btnNext = document.getElementById('nextBtn');

  const total = slides.length;
  let index = 0;

  const AUTOPLAY_MS = 7000;
  let timer = null;

  function setTranslate(){
    track.style.transform = `translateX(${-index * 100}%)`;
  }

  function buildDots(){
    dotsWrap.innerHTML = '';
    for (let i = 0; i < total; i++){
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'carousel__dot' + (i === index ? ' is-active' : '');
      b.setAttribute('aria-label', `Ir para o slide ${i+1}`);
      b.addEventListener('click', () => { goTo(i); restartAutoplay(); });
      dotsWrap.appendChild(b);
    }
  }

  function updateDots(){
    const dots = dotsWrap.querySelectorAll('.carousel__dot');
    dots.forEach((d, i) => d.classList.toggle('is-active', i === index));
  }

  function goTo(i){
    index = (i + total) % total;
    setTranslate();
    updateDots();
  }

  function next(){ goTo(index + 1); }
  function prev(){ goTo(index - 1); }

  function startAutoplay(){
    stopAutoplay();
    timer = setInterval(() => next(), AUTOPLAY_MS);
  }

  function stopAutoplay(){
    if (timer) clearInterval(timer);
    timer = null;
  }

  function restartAutoplay(){ startAutoplay(); }

  btnNext.addEventListener('click', () => { next(); restartAutoplay(); });
  btnPrev.addEventListener('click', () => { prev(); restartAutoplay(); });

  const carousel = document.getElementById('mainCarousel');
  carousel.addEventListener('mouseenter', () => stopAutoplay());
  carousel.addEventListener('mouseleave', () => startAutoplay());

  buildDots();
  setTranslate();
  startAutoplay();
</script>

<script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdowns.js') ?>"></script>

</body>
</html>