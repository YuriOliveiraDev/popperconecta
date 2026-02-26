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
    if (pinned) return;
    setOpen(false);
  }

  function scheduleClose(){
    if (pinned) return;
    if (closeT) clearTimeout(closeT);
    closeT = setTimeout(close, 220);
  }

  var isDesktop = window.matchMedia('(hover:hover) and (pointer:fine)').matches;
  if (isDesktop) {
    trigger.addEventListener('mouseenter', open);
    wrap.addEventListener('mouseleave', scheduleClose);
    menu.addEventListener('mouseenter', open);
    menu.addEventListener('mouseleave', scheduleClose);
  }

  trigger.addEventListener('click', function(e){
    e.preventDefault();
    e.stopPropagation();

    pinned = !pinned;
    if (pinned) open();
    else { pinned = false; setOpen(false); }
  });

  document.addEventListener('click', function(e){
    if (!wrap.contains(e.target)) {
      pinned = false;
      setOpen(false);
    }
  });

  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
      pinned = false;
      setOpen(false);
    }
  });

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

  wrap.querySelectorAll('.notif__item[data-id]').forEach(function(a){
    a.addEventListener('click', function(){
      var id = a.getAttribute('data-id');
      if (!id) return;
      navigator.sendBeacon('/notifications_read.php', 'id=' + encodeURIComponent(id));
    });
  });
})();


// DASHBOARD: hover no desktop (CSS), clique no mobile (JS)
document.addEventListener('DOMContentLoaded', function () {
  var dashWrap = document.getElementById('dashWrap');
  var dashTrigger = document.getElementById('dashTrigger');
  var dashMenu = document.getElementById('dashMenu');
  if (!dashWrap || !dashTrigger || !dashMenu) return;

  var groups = dashMenu.querySelectorAll('[data-submenu]');

  function isMobile(){
    return window.matchMedia('(max-width: 720px)').matches;
  }

  function closeAllGroups(){
    groups.forEach(function(g){
      g.classList.remove('is-open');
      var b = g.querySelector('.topbar__dropdown-item--group');
      if (b) b.setAttribute('aria-expanded', 'false');
    });
  }

  function closeDash(){
    dashWrap.classList.remove('is-open');
    dashTrigger.setAttribute('aria-expanded', 'false');
    closeAllGroups();
  }

  function openDash(){
    dashWrap.classList.add('is-open');
    dashTrigger.setAttribute('aria-expanded', 'true');
  }

  // Clique no "Dashboard" abre/fecha (mantém comportamento padrão)
  dashTrigger.addEventListener('click', function(e){
    e.preventDefault();
    e.stopPropagation();

    var isOpen = dashWrap.classList.contains('is-open');
    if (isOpen) closeDash();
    else openDash();
  });

  // Mobile: clique nos grupos abre/fecha submenu
  groups.forEach(function(group){
    var btn = group.querySelector('.topbar__dropdown-item--group');
    if (!btn) return;

    btn.addEventListener('click', function(e){
      if (!isMobile()) return; // desktop: hover CSS cuida

      e.preventDefault();
      e.stopPropagation();

      var open = group.classList.contains('is-open');
      closeAllGroups();

      if (!open) {
        group.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
      }
    });
  });

  // clicar fora fecha dashboard
  document.addEventListener('click', function(e){
    if (!dashWrap.contains(e.target)) {
      closeDash();
    }
  });

  // ESC fecha dashboard
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') closeDash();
  });

  // clique dentro do menu não fecha pelo clique global
  dashMenu.addEventListener('click', function(e){
    e.stopPropagation();
  });
});