/* =========================
   coins.js (Popper Coins)
   ========================= */

// Bloqueio de duplo clique (somente formulários desta página)
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.pc-container form[method="post"]').forEach(function(form) {
    form.addEventListener('submit', function() {
      var btn = form.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Enviando...';
      }
    });
  });
});