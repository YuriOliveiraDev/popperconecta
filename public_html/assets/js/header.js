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
      if (closeT) {
        clearTimeout(closeT);
        closeT = null;
      }
      setOpen(true);
    }

    function scheduleClose() {
      if (pinned) return;
      if (closeT) clearTimeout(closeT);
      closeT = setTimeout(function () {
        setOpen(false);
      }, 220);
    }

    var isDesktop = window.matchMedia('(hover:hover) and (pointer:fine)').matches;

    if (isDesktop) {
      wrap.addEventListener('mouseenter', open);
      wrap.addEventListener('mouseleave', scheduleClose);
      menu.addEventListener('mouseenter', open);
      menu.addEventListener('mouseleave', scheduleClose);
    }

    trigger.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      pinned = !pinned;
      if (pinned) {
        open();
      } else {
        scheduleClose();
      }
    });

    document.addEventListener('click', function (e) {
      if (!wrap.contains(e.target)) {
        pinned = false;
        setOpen(false);
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        pinned = false;
        setOpen(false);
      }
    });

    return {
      wrap: wrap,
      trigger: trigger,
      menu: menu,
      open: open,
      close: function () {
        pinned = false;
        setOpen(false);
      }
    };
  }

  var profile = bindPinnedDropdown('profileWrap', 'profileTrigger', 'profileMenu');
  var notif = bindPinnedDropdown('notifWrap', 'notifTrigger', 'notifMenu');

  if (notif) {
    var wrap = notif.wrap;
    var markAll = document.getElementById('notifMarkAll');

    if (markAll) {
      markAll.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        fetch('/notifications_read.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: 'all=1'
        })
        .then(function (res) {
          return res.text();
        })
        .then(function () {
          var badge = wrap.querySelector('.notif__badge');
          if (badge) badge.remove();

          wrap.querySelectorAll('.notif__item.is-unread').forEach(function (item) {
            item.classList.remove('is-unread');
          });
        })
        .catch(function (err) {
          console.error('Erro ao marcar notificações:', err);
        });
      });
    }

    wrap.querySelectorAll('.notif__item[data-id]').forEach(function (a) {
      a.addEventListener('click', function () {
        var id = a.getAttribute('data-id');
        if (!id) return;

        try {
          navigator.sendBeacon('/notifications_read.php', 'id=' + encodeURIComponent(id));
        } catch (e) {
          fetch('/notifications_read.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'id=' + encodeURIComponent(id)
          }).catch(function () {});
        }
      });
    });
  }

  var logo = document.getElementById('popperTvLogo');
  if (logo) {
    logo.classList.add('is-ready');
  }
});