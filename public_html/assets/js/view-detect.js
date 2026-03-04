(function () {
  function setCookie(val) {
    document.cookie = "pc_view=" + val + "; path=/; max-age=" + (60 * 60 * 24 * 7) + "; samesite=Lax";
  }

  function detect() {
    var w = window.innerWidth || 0;
    var h = window.innerHeight || 0;

    // Se a maior dimensão é grande, não é celular (bom pra TV/monitor)
    var bigScreen = Math.max(w, h) >= 900;

    // celular de verdade costuma ter menor dimensão < 900
    var smallSide = Math.min(w, h) > 0 && Math.min(w, h) < 900;

    // Heurística: se está “grande”, força OK
    var val = (!bigScreen && smallSide) ? 'mobile' : 'ok';
    setCookie(val);
  }

  // roda agora e depois de “assentar”
  detect();
  setTimeout(detect, 700);
  setTimeout(detect, 1500);

  // se mudar resolução/zoom/fullscreen, atualiza
  window.addEventListener('resize', function () {
    clearTimeout(window.__pcT);
    window.__pcT = setTimeout(detect, 250);
  });
})();