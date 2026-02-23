// NOTIF: abre no hover (desktop), clique fixa, não pisca ao mover para o menu
(function(){
  var wrap = document.getElementById('notifWrap');
  var trigger = document.getElementById('notifTrigger');
  var menu = document.getElementById('notifMenu');
  if (!wrap || !trigger || !menu) return;

  var pinned = false;
  var closeT = null;

  function setOpen(state){
    if (state) {
      wrap.classList.add('is-open');
      trigger.setAttribute('aria-expanded', 'true');
    } else {
      wrap.classList.remove('is-open');
      trigger.setAttribute('aria-expanded', 'false');
    }
  }

  function open(){
    if (closeT) { clearTimeout(closeT); closeT = null; }
    setOpen(true);
  }

  function close(){
    if (pinned) return; // se clicou, fica fixo até clicar fora/esc
    setOpen(false);
  }

  function scheduleClose(){
    if (pinned) return;
    if (closeT) clearTimeout(closeT);
    closeT = setTimeout(close, 220);
  }

  // Hover no DESKTOP: abre e não fecha ao entrar no menu
  var isDesktop = window.matchMedia('(hover:hover) and (pointer:fine)').matches;
  if (isDesktop) {
    // entra no sino/container => abre
    trigger.addEventListener('mouseenter', open);
    // sai do container => agenda fechar
    wrap.addEventListener('mouseleave', scheduleClose);
    // entra no menu => cancela fechar (fica aberto)
    menu.addEventListener('mouseenter', open);
    // sai do menu => agenda fechar
    menu.addEventListener('mouseleave', scheduleClose);
  }

  // Clique: fixa/solta (desktop e mobile)
  trigger.addEventListener('click', function(e){
    e.preventDefault();
    e.stopPropagation();

    pinned = !pinned; // alterna o estado
    if (pinned) open();
    else close(); // solta e deixa fechar pelo comportamento normal
  });

  // Clicar fora: fecha e tira o pinned
  document.addEventListener('click', function(e){
    if (!wrap.contains(e.target)) {
      pinned = false;
      close();
    }
  });

  // ESC: fecha e tira o pinned
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
      pinned = false;
      close();
    }
  });

  // Marcar todas como lidas (AJAX)
  var markAll = document.getElementById('notifMarkAll');
  if (markAll) {
    markAll.addEventListener('click', function(e){
      e.preventDefault();
      e.stopPropagation();

      fetch('/notifications_read.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'all=1'
      }).then(function(){
        var badge = wrap.querySelector('.notif__badge');
        if (badge) badge.remove();
        wrap.querySelectorAll('.notif__item.is-unread').forEach(function(item){
          item.classList.remove('is-unread');
        });
      });
    });
  }

  // Marcar individual como lida (sem travar o link)
  wrap.querySelectorAll('.notif__item[data-id]').forEach(function(a){
    a.addEventListener('click', function(){
      var id = a.getAttribute('data-id');
      if (!id) return;
      navigator.sendBeacon('/notifications_read.php', 'id=' + encodeURIComponent(id));
    });
  });
})();