(function () {
  const body = document.body;

  function closeModal(modal) {
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    body.style.overflow = '';
  }

  function openModal(modal) {
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    body.style.overflow = 'hidden';
  }

  document.querySelectorAll('[data-modal-target]').forEach((button) => {
    button.addEventListener('click', function () {
      openModal(document.getElementById(button.getAttribute('data-modal-target')));
    });
  });

  document.querySelectorAll('[data-modal-close]').forEach((button) => {
    button.addEventListener('click', function () {
      closeModal(button.closest('.modal'));
    });
  });

  document.querySelectorAll('.modal').forEach((modal) => {
    modal.addEventListener('click', function (event) {
      if (event.target === modal) closeModal(modal);
    });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('.modal.is-open').forEach(closeModal);
  });

  document.querySelectorAll('[data-repeater-add]').forEach((button) => {
    button.addEventListener('click', function () {
      const listId = button.getAttribute('data-repeater-add');
      const list = document.getElementById(listId);
      const template = document.getElementById(listId + '-template');
      if (!list || !template) return;
      list.appendChild(template.content.cloneNode(true));
    });
  });

  document.addEventListener('click', function (event) {
    const removeBtn = event.target.closest('[data-repeater-remove]');
    if (!removeBtn) return;
    const item = removeBtn.closest('[data-repeater-item]');
    if (item) item.remove();
  });
})();
