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
(function () {
  "use strict";

  const closeAll = (except = null) => {
    document.querySelectorAll(".topbar__dropdown.is-open").forEach(d => {
      if (except && d === except) return;
      d.classList.remove("is-open");
      const t = d.querySelector(".topbar__dropdown-trigger");
      if (t) t.setAttribute("aria-expanded", "false");

      // fecha submenus internos também
      d.querySelectorAll(".topbar__dropdown-group.is-open").forEach(g => {
        g.classList.remove("is-open");
        const btn = g.querySelector(".topbar__dropdown-item--group");
        if (btn) btn.setAttribute("aria-expanded", "false");
      });
    });
  };

  // =========
  // DROPDOWNS PRINCIPAIS
  // =========
  const dropdowns = document.querySelectorAll(".topbar__dropdown");
  dropdowns.forEach(drop => {
    const trigger = drop.querySelector(".topbar__dropdown-trigger");
    const menu = drop.querySelector(".topbar__dropdown-menu");
    if (!trigger || !menu) return;

    let openTimer = null;
    let closeTimer = null;

    const open = () => {
      clearTimeout(closeTimer);
      closeAll(drop);
      drop.classList.add("is-open");
      trigger.setAttribute("aria-expanded", "true");
    };

    const close = () => {
      clearTimeout(openTimer);
      drop.classList.remove("is-open");
      trigger.setAttribute("aria-expanded", "false");

      // também fecha submenus
      drop.querySelectorAll(".topbar__dropdown-group.is-open").forEach(g => {
        g.classList.remove("is-open");
        const btn = g.querySelector(".topbar__dropdown-item--group");
        if (btn) btn.setAttribute("aria-expanded", "false");
      });
    };

    // Hover com delay (evita flicker)
    drop.addEventListener("mouseenter", () => {
      clearTimeout(closeTimer);
      openTimer = setTimeout(open, 80);
    });

    drop.addEventListener("mouseleave", () => {
      clearTimeout(openTimer);
      closeTimer = setTimeout(close, 160);
    });

    // Click (mobile + acessibilidade)
    trigger.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();
      const isOpen = drop.classList.contains("is-open");
      if (isOpen) close();
      else open();
    });

    // ESC fecha
    drop.addEventListener("keydown", (e) => {
      if (e.key === "Escape") close();
    });
  });

  // =========
  // SUBMENUS (itens com data-submenu)
  // =========
  document.querySelectorAll(".topbar__dropdown-group[data-submenu]").forEach(group => {
    const btn = group.querySelector(".topbar__dropdown-item--group");
    const submenu = group.querySelector(".topbar__dropdown-submenu");
    if (!btn || !submenu) return;

    let closeTimer = null;

    const openSub = () => {
      clearTimeout(closeTimer);

      // fecha irmãos
      const parentMenu = group.closest(".topbar__dropdown-menu") || group.parentElement;
      if (parentMenu) {
        parentMenu.querySelectorAll(".topbar__dropdown-group.is-open").forEach(g => {
          if (g !== group) {
            g.classList.remove("is-open");
            const b = g.querySelector(".topbar__dropdown-item--group");
            if (b) b.setAttribute("aria-expanded", "false");
          }
        });
      }

      group.classList.add("is-open");
      btn.setAttribute("aria-expanded", "true");
    };

    const closeSub = () => {
      closeTimer = setTimeout(() => {
        group.classList.remove("is-open");
        btn.setAttribute("aria-expanded", "false");
      }, 160);
    };

    // Hover (desktop)
    group.addEventListener("mouseenter", openSub);
    group.addEventListener("mouseleave", closeSub);

    // Click (mobile)
    btn.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();

      const isOpen = group.classList.contains("is-open");
      if (isOpen) {
        group.classList.remove("is-open");
        btn.setAttribute("aria-expanded", "false");
      } else {
        openSub();
      }
    });
  });

  // Click fora fecha tudo
  document.addEventListener("click", () => closeAll(null));

  // Scroll / resize fecha (evita menus “flutuando” fora do lugar)
  window.addEventListener("scroll", () => closeAll(null), { passive: true });
  window.addEventListener("resize", () => closeAll(null), { passive: true });
})();

// =========================================================
// MOBILE MENU (cria botão ☰ Menu via JS, sem alterar o PHP)
// =========================================================
(function () {
  "use strict";

  function isMobile() {
    return window.matchMedia("(max-width: 720px)").matches;
  }

  function getTopbar() {
    return document.querySelector("header.topbar--site");
  }

  function ensureMobileButton() {
    const topbar = getTopbar();
    if (!topbar) return;

    // se já existe, ok
    if (topbar.querySelector(".mobileMenuBtn")) return;

    // cria botão
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "mobileMenuBtn";
    btn.setAttribute("aria-expanded", "false");
    btn.setAttribute("aria-label", "Abrir menu");
    btn.innerHTML = "☰ <span>Menu</span>";

    // coloca no começo da left (antes do brand)
    const left = topbar.querySelector(".topbar__left");
    if (!left) return;
    left.insertBefore(btn, left.firstChild);

    btn.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      const open = topbar.classList.toggle("is-mobile-open");
      btn.setAttribute("aria-expanded", open ? "true" : "false");

      // quando abrir, fecha dropdowns abertos pra evitar bagunça
      if (open) {
        document.querySelectorAll(".topbar__dropdown.is-open").forEach(d => d.classList.remove("is-open"));
        document.querySelectorAll(".topbar__dropdown-group.is-open").forEach(g => g.classList.remove("is-open"));
      }
    });

    // clique fora fecha o menu mobile
    document.addEventListener("click", function (e) {
      if (!isMobile()) return;
      if (!topbar.classList.contains("is-mobile-open")) return;
      if (topbar.contains(e.target)) return;

      topbar.classList.remove("is-mobile-open");
      btn.setAttribute("aria-expanded", "false");
    });

    // ESC fecha
    document.addEventListener("keydown", function (e) {
      if (e.key !== "Escape") return;
      if (!topbar.classList.contains("is-mobile-open")) return;

      topbar.classList.remove("is-mobile-open");
      btn.setAttribute("aria-expanded", "false");
    });
  }

  function sync() {
    const topbar = getTopbar();
    if (!topbar) return;

    if (isMobile()) {
      ensureMobileButton();
    } else {
      // ao sair do mobile, fecha menu e reseta aria
      topbar.classList.remove("is-mobile-open");
      const btn = topbar.querySelector(".mobileMenuBtn");
      if (btn) btn.setAttribute("aria-expanded", "false");
    }
  }

  // inicial
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", sync);
  } else {
    sync();
  }

  // re-sincroniza em resize/orientation
  window.addEventListener("resize", sync, { passive: true });
})();