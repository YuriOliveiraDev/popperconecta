(function () {
  const nav = document.querySelector('[data-mobile-homenav]');
  const toggle = document.querySelector('[data-mobile-nav-toggle]');
  const backdrop = document.querySelector('.mobile-homenav-backdrop');
  const closeButtons = document.querySelectorAll('[data-mobile-nav-close]');

  if (!nav || !toggle || !backdrop) {
    return;
  }

  function setOpen(state) {
    nav.classList.toggle('is-open', state);
    nav.setAttribute('aria-hidden', state ? 'false' : 'true');
    toggle.setAttribute('aria-expanded', state ? 'true' : 'false');
    backdrop.hidden = !state;
    document.body.classList.toggle('mobile-home-nav-open', state);
    document.body.style.overflow = state ? 'hidden' : '';
  }

  toggle.addEventListener('click', function () {
    setOpen(!nav.classList.contains('is-open'));
  });

  closeButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      setOpen(false);
    });
  });

  nav.querySelectorAll('a').forEach(function (link) {
    link.addEventListener('click', function () {
      setOpen(false);
    });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      setOpen(false);
    }
  });

  window.addEventListener('resize', function () {
    if (window.innerWidth > 860) {
      setOpen(false);
    }
  });
})();
