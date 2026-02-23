document.addEventListener('DOMContentLoaded', function () {
  function bindPinnedDropdown(wrapId, triggerId, menuId) {
    var wrap = document.getElementById(wrapId);
    var trigger = document.getElementById(triggerId);
    var menu = document.getElementById(menuId);
    if (!wrap || !trigger || !menu) return null;

    var pinned = false;
    var closeT = null;

    function setOpen(state) {
      if (state) {
        wrap.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
      } else {
        wrap.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
      }
    }

    function open() {
      if (closeT) { clearTimeout(closeT); closeT = null; }
      setOpen(true);
    }

    function scheduleClose() {
      if (pinned) return;
      if (closeT) clearTimeout(closeT);
      closeT = setTimeout(function () { setOpen(false); }, 220);
    }

    // Hover no desktop (igual perfil)
    var isDesktop = window.matchMedia('(hover:hover) and (pointer:fine)').matches;
    if (isDesktop) {
      wrap.addEventListener('mouseenter', open);
      wrap.addEventListener('mouseleave', scheduleClose);
      menu.addEventListener('mouseenter', open);
      menu.addEventListener('mouseleave', scheduleClose);
    }

    // Clique fixa/solta
    trigger.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      pinned = !pinned;
      if (pinned) open();
      else scheduleClose();
    });

    // Clique fora fecha
    document.addEventListener('click', function (e) {
      if (!wrap.contains(e.target)) {
        pinned = false;
        setOpen(false);
      }
    });

    // ESC fecha
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        pinned = false;
        setOpen(false);
      }
    });

    return { wrap: wrap, menu: menu };
  }

  // PERFIL
  bindPinnedDropdown('profileWrap', 'profileTrigger', 'profileMenu');

  // NOTIF
  var notif = bindPinnedDropdown('notifWrap', 'notifTrigger', 'notifMenu');

  // NOTIF: marcar lidas
  if (notif) {
    var wrap = notif.wrap;

    var markAll = document.getElementById('notifMarkAll');
    if (markAll) {
      markAll.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        fetch('/notifications_read.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'all=1'
        }).then(function () {
          var badge = wrap.querySelector('.notif__badge');
          if (badge) badge.remove();
          wrap.querySelectorAll('.notif__item.is-unread').forEach(function (item) {
            item.classList.remove('is-unread');
          });
        });
      });
    }

    wrap.querySelectorAll('.notif__item[data-id]').forEach(function (a) {
      a.addEventListener('click', function () {
        var id = a.getAttribute('data-id');
        if (!id) return;
        navigator.sendBeacon('/notifications_read.php', 'id=' + encodeURIComponent(id));
      });
    });
  }
});