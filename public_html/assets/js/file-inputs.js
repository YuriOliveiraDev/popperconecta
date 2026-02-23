(function () {
  'use strict';

  function isImage(file) {
    return !!file && typeof file.type === 'string' && file.type.startsWith('image/');
  }

  function setupInput(input) {
    const nameSel = input.getAttribute('data-file-name');
    const previewSel = input.getAttribute('data-file-preview');

    const nameEl = nameSel ? document.querySelector(nameSel) : null;
    const previewImg = previewSel ? document.querySelector(previewSel) : null;
    const previewWrap = previewImg ? previewImg.closest('.file-preview') : null;

    if (!nameEl) return;

    function hidePreview() {
      if (previewWrap) previewWrap.classList.remove('is-on');
      if (previewImg) {
        const oldUrl = previewImg.getAttribute('data-object-url');
        if (oldUrl) URL.revokeObjectURL(oldUrl);
        previewImg.removeAttribute('data-object-url');
        previewImg.removeAttribute('src');
      }
    }

    function showPreview(file) {
      if (!previewWrap || !previewImg) return;

      if (!file || !isImage(file)) {
        hidePreview();
        return;
      }

      const oldUrl = previewImg.getAttribute('data-object-url');
      if (oldUrl) URL.revokeObjectURL(oldUrl);

      const url = URL.createObjectURL(file);
      previewImg.src = url;
      previewImg.setAttribute('data-object-url', url);
      previewWrap.classList.add('is-on');
    }

    function render() {
      const file = input.files && input.files[0] ? input.files[0] : null;
      nameEl.textContent = file ? file.name : 'Nenhum arquivo selecionado';
      showPreview(file);
      if (!file) hidePreview();
    }

    input.addEventListener('change', render);
    render();
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input.file-input[type="file"]').forEach(setupInput);
  });
})();

(function () {
  'use strict';

  const backdrop = document.getElementById('confirmBackdrop');
  if (!backdrop) return;

  const textEl = document.getElementById('confirmText');
  const btnOk = document.getElementById('confirmOk');
  const btnCancel = document.getElementById('confirmCancel');
  const btnClose = document.getElementById('confirmClose');

  let pendingForm = null;

  function openModal(message, form) {
    pendingForm = form;
    textEl.textContent = message || 'Deseja confirmar esta ação?';
    backdrop.classList.add('is-on');
    backdrop.setAttribute('aria-hidden', 'false');
    btnCancel.focus();
  }

  function closeModal() {
    pendingForm = null;
    backdrop.classList.remove('is-on');
    backdrop.setAttribute('aria-hidden', 'true');
  }

  // Intercepta forms com data-confirm
  document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;

    const msg = form.getAttribute('data-confirm');
    if (!msg) return;

    e.preventDefault();
    openModal(msg, form);
  });

  btnOk.addEventListener('click', function () {
    if (pendingForm) pendingForm.submit();
  });

  btnCancel.addEventListener('click', closeModal);
  btnClose.addEventListener('click', closeModal);

  // Fecha clicando fora
  backdrop.addEventListener('click', function (e) {
    if (e.target === backdrop) closeModal();
  });

  // ESC fecha
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && backdrop.classList.contains('is-on')) closeModal();
  });
})();