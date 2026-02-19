/* assets/js/dropdowns.js */
(function () {
  function attachDropdown(triggerId, menuId) {
    const trigger = document.getElementById(triggerId);
    const menu = document.getElementById(menuId);
    let t = null;

    if (!trigger || !menu) return;

    trigger.addEventListener('mouseenter', () => {
      clearTimeout(t);
      trigger.classList.add('is-open');
      menu.classList.add('is-open');
    });

    trigger.addEventListener('mouseleave', () => {
      t = setTimeout(() => {
        trigger.classList.remove('is-open');
        menu.classList.remove('is-open');
      }, 150);
    });

    menu.addEventListener('mouseenter', () => clearTimeout(t));
    menu.addEventListener('mouseleave', () => {
      t = setTimeout(() => {
        trigger.classList.remove('is-open');
        menu.classList.remove('is-open');
      }, 150);
    });

    document.addEventListener('click', (e) => {
      if (!trigger.contains(e.target) && !menu.contains(e.target)) {
        trigger.classList.remove('is-open');
        menu.classList.remove('is-open');
      }
    });

    trigger.addEventListener('click', (e) => {
      e.preventDefault();
      trigger.classList.toggle('is-open');
      menu.classList.toggle('is-open');
    });
  }

  // expõe uma função global para caso você queira usar em outros lugares
  window.initTopbarDropdowns = function () {
    attachDropdown('adminTrigger', 'adminMenu');
    attachDropdown('dashTrigger', 'dashMenu');
  };

  // auto-init quando o DOM estiver pronto
  document.addEventListener('DOMContentLoaded', function () {
    window.initTopbarDropdowns();
  });
})();