<?php
declare(strict_types=1);
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';

require_login();

$u = current_user();
$activePage = 'home';

// Buscar comunicados ativos (inclui imagem_path)
$stmt = db()->prepare('SELECT titulo, conteudo, imagem_path FROM comunicados WHERE ativo = TRUE ORDER BY ordem ASC, id ASC');
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

  <style>
    /* ✅ FULL-BLEED: garante 100% viewport mesmo com wrappers max-width */
    html, body { width: 100%; max-width: 100%; overflow-x: hidden; }

    /* alguns layouts colocam padding no .page */
    .page { padding-left: 0 !important; padding-right: 0 !important; }

    /* Classe full-bleed: escapa de QUALQUER container */
    .full-bleed {
      width: 100vw !important;
      max-width: 100vw !important;
      margin-left: calc(50% - 50vw) !important;
      margin-right: calc(50% - 50vw) !important;
    }

    /* Carrossel full width real */
    .carousel.carousel--full {
      border-radius: 0;
      padding: 0;
    }
    .carousel.carousel--full .carousel__viewport { border-radius: 0; }

    /* Imagem grande (sem frame) */
    .slide--image { padding: 0 !important; }
    .slide--image .slide__inner{
      padding: 0 !important;
      background: transparent !important;
      border: 0 !important;
      box-shadow: none !important;
      max-width: 100% !important;
    }
    .slide__img-full{
      width: 100%;
      height: min(560px, 72vh);
      object-fit: cover;
      display: block;
      border-radius: 0;
      margin: 0;
    }

    /* Texto estilo documento */
    .slide--text { padding: 0 !important; display: block !important; }
    .slide--text .slide__doc{
      width: 100%;
      max-width: 1400px;
      margin: 16px auto 56px; /* deixa espaço pros dots */
      background: #fff;
      border-radius: 14px;
      padding: 28px 30px;
      box-shadow: 0 4px 16px rgba(0,0,0,.06);
    }
    .doc__title{
      font-family: "Segoe UI", Arial, sans-serif;
      font-size: 22px;
      font-weight: 800;
      color: #0f172a;
      margin: 0 0 14px 0;
    }
    .doc__body{
      font-family: "Segoe UI", Arial, sans-serif;
      font-size: 16px;
      font-weight: 400;
      color: #111827;
      line-height: 1.7;
    }
    .doc__body br{ content:""; display:block; margin: 10px 0; }

    @media (max-width: 768px){
      .slide__img-full{ height: 42vh; }
      .slide--text .slide__doc{
        max-width: 100%;
        margin: 12px 12px 56px;
        padding: 18px 16px;
        border-radius: 12px;
      }
      .doc__title{ font-size: 18px; }
      .doc__body{ font-size: 15px; }
    }
  </style>
</head>
<body class="page">

<?php require_once __DIR__ . '/app/header.php'; ?>

<main>
  <div class="container">
    <h2 class="page-title">Comunicados</h2>
  </div>

  <!-- ✅ full-bleed ESCAPA de qualquer wrapper -->
  <section class="carousel carousel--full full-bleed" id="mainCarousel">
    <button class="carousel__arrow carousel__arrow--prev" type="button" id="prevBtn" aria-label="Anterior">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>

    <div class="carousel__viewport">
      <div class="carousel__track" id="track">
        <?php if (empty($comunicados)): ?>
          <article class="slide slide--text">
            <div class="slide__doc">
              <div class="doc__title">Bem-vindo</div>
              <div class="doc__body">Fique atento aos novos comunicados da Popper aqui.</div>
            </div>
          </article>
        <?php else: ?>
          <?php foreach ($comunicados as $c): ?>
            <?php
              $img = trim((string)($c['imagem_path'] ?? ''));
              $titulo = trim((string)($c['titulo'] ?? ''));
              $conteudo = trim((string)($c['conteudo'] ?? ''));
              $hasImage = ($img !== '');
              $hasText = ($titulo !== '' || $conteudo !== '');
            ?>

            <?php if ($hasImage): ?>
              <article class="slide slide--image">
                <div class="slide__inner">
                  <img class="slide__img-full" src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" alt="Comunicado">
                </div>
              </article>
            <?php else: ?>
              <article class="slide slide--text">
                <div class="slide__doc">
                  <?php if ($titulo !== ''): ?>
                    <div class="doc__title"><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></div>
                  <?php endif; ?>

                  <?php if ($conteudo !== ''): ?>
                    <?php $conteudoSafe = nl2br(htmlspecialchars($conteudo, ENT_QUOTES, 'UTF-8')); ?>
                    <div class="doc__body"><?= $conteudoSafe ?></div>
                  <?php endif; ?>

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

    <button class="carousel__arrow carousel__arrow--next" type="button" id="nextBtn" aria-label="Próximo">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>

    <div class="carousel__dots" id="dots" aria-label="Indicadores do carrossel"></div>
  </section>

  <div class="container">
    <div class="card" style="margin-top:16px;">
      <p class="muted" style="margin:0;">Página inicial.</p>
    </div>
  </div>
</main>

<script>
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