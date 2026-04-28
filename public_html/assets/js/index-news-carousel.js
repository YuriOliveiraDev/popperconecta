(function () {
  const viewer = document.querySelector('[data-news-viewer]');
  if (!viewer) return;

  const pagesNode = viewer.querySelector('[data-news-pages]');
  const image = viewer.querySelector('[data-news-image]');
  const message = viewer.querySelector('[data-news-message]');
  const prevBtn = viewer.querySelector('[data-news-prev]');
  const nextBtn = viewer.querySelector('[data-news-next]');
  const dots = viewer.querySelector('[data-news-dots]');
  const stage = viewer.querySelector('.news-preview__stage');

  let pages = [];

  try {
    pages = JSON.parse(pagesNode ? pagesNode.textContent : '[]');
  } catch (error) {
    console.warn(error);
  }

  if (!Array.isArray(pages) || pages.length === 0) {
    if (message) {
      message.textContent = 'Nenhuma imagem disponível para exibição.';
      message.hidden = false;
    }
    if (prevBtn) prevBtn.disabled = true;
    if (nextBtn) nextBtn.disabled = true;
    return;
  }

  let pageIndex = 0;
  let isAnimating = false;
  let autoplayPaused = false;
  let autoplayTimer = null;
  const autoplayDelay = 4200;

  function setMessage(text, visible) {
    if (!message) return;
    message.textContent = text;
    message.hidden = !visible;
  }

  function updateDots() {
    if (!dots) return;
    dots.innerHTML = '';
    pages.forEach((_, index) => {
      const dot = document.createElement('span');
      dot.className = 'news-preview__dot' + (index === pageIndex ? ' is-active' : '');
      dots.appendChild(dot);
    });
  }

  function updateControls() {
    if (prevBtn) prevBtn.disabled = isAnimating || pages.length <= 1;
    if (nextBtn) nextBtn.disabled = isAnimating || pages.length <= 1;
  }

  function clearAutoplay() {
    if (autoplayTimer !== null) {
      window.clearTimeout(autoplayTimer);
      autoplayTimer = null;
    }
  }

  function scheduleAutoplay() {
    clearAutoplay();
    if (autoplayPaused || pages.length <= 1) return;
    autoplayTimer = window.setTimeout(function () {
      goTo(pageIndex + 1, 'next', false);
    }, autoplayDelay);
  }

  function preload(src) {
    return new Promise(function (resolve, reject) {
      const img = new Image();
      img.onload = function () { resolve(img); };
      img.onerror = reject;
      img.src = src;
    });
  }

  async function goTo(targetIndex, direction, pauseAuto) {
    if (isAnimating || pages.length === 0) return;

    if (pauseAuto) {
      autoplayPaused = true;
      clearAutoplay();
      window.setTimeout(function () {
        autoplayPaused = false;
        scheduleAutoplay();
      }, 9000);
    }

    const normalizedIndex = (targetIndex + pages.length) % pages.length;
    const nextSrc = pages[normalizedIndex];

    isAnimating = true;
    updateControls();
    setMessage('Carregando edição...', true);

    try {
      await preload(nextSrc);

      const currentSlide = document.createElement('img');
      currentSlide.src = image.src || pages[pageIndex];
      currentSlide.className = 'news-preview__slide news-preview__slide--current';

      const nextSlide = document.createElement('img');
      nextSlide.src = nextSrc;
      nextSlide.className =
        'news-preview__slide news-preview__slide--next ' +
        (direction === 'prev'
          ? 'news-preview__slide--from-left'
          : 'news-preview__slide--from-right');

      stage.appendChild(currentSlide);
      stage.appendChild(nextSlide);

      image.style.visibility = 'hidden';

      requestAnimationFrame(function () {
        requestAnimationFrame(function () {
          currentSlide.classList.add(
            direction === 'prev'
              ? 'news-preview__slide--to-right'
              : 'news-preview__slide--to-left'
          );
          nextSlide.classList.add('news-preview__slide--to-center');
        });
      });

      window.setTimeout(function () {
        image.src = nextSrc;
        image.style.visibility = '';
        currentSlide.remove();
        nextSlide.remove();

        pageIndex = normalizedIndex;
        isAnimating = false;
        updateControls();
        updateDots();
        setMessage('', false);
        scheduleAutoplay();
      }, 470);
    } catch (error) {
      console.warn(error);
      isAnimating = false;
      updateControls();
      setMessage('Não foi possível carregar esta página.', true);
    }
  }

  if (prevBtn) {
    prevBtn.addEventListener('click', function () {
      goTo(pageIndex - 1, 'prev', true);
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', function () {
      goTo(pageIndex + 1, 'next', true);
    });
  }

  viewer.addEventListener('mouseenter', function () {
    autoplayPaused = true;
    clearAutoplay();
  });

  viewer.addEventListener('mouseleave', function () {
    autoplayPaused = false;
    scheduleAutoplay();
  });

  image.addEventListener('load', function () {
    setMessage('', false);
  });

  image.src = pages[0];
  updateDots();
  updateControls();
  setMessage('', false);
  scheduleAutoplay();
})();
