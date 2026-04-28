(function () {
  const form = document.querySelector('.auth-form');
  if (!form) return;

  const btn = form.querySelector('.auth-btn');
  let submitted = false;

  form.addEventListener('submit', function (e) {
    if (submitted) return;
    e.preventDefault();
    submitted = true;

    document.body.classList.add('is-leaving');

    if (btn) {
      btn.classList.add('is-loading');
      btn.disabled = true;
    }

    if (window.PopperLoading && typeof window.PopperLoading.show === 'function') {
      window.PopperLoading.show('Entrando…', 'Validando acesso');
    }

    setTimeout(() => form.submit(), 1000);
  });
})();
