<?php
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_login();

$u = current_user();
$activePage = 'comex';

// Dropdown "Dashboards" no header
try {
  $dashboards = db()
    ->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")
    ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}

// (Se seu header usar isso, ótimo. Se não usar, o JS abaixo resolve.)
$body_class = 'comex-importacoes';

require_once __DIR__ . '/app/header.php';
?>

<!-- ✅ CSS (mantém seu padrão com filemtime) -->
<link rel="stylesheet" href="/assets/css/loader.css?v=<?= @filemtime(__DIR__ . '/assets/css/loader.css') ?: time() ?>">
<link rel="stylesheet" href="/assets/css/base.css?v=<?= @filemtime(__DIR__ . '/assets/css/base.css') ?: time() ?>">
<link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= @filemtime(__DIR__ . '/assets/css/dropdowns.css') ?: time() ?>">
<link rel="stylesheet" href="/assets/css/header.css?v=<?= @filemtime(__DIR__ . '/assets/css/header.css') ?: time() ?>">
<link rel="stylesheet" href="/assets/css/importacoes.css?v=<?= @filemtime(__DIR__ . '/assets/css/importacoes.css') ?: time() ?>">

<script>
  // ✅ garante que o escopo do CSS funcione SEM depender do header.php
  document.documentElement.classList.add('comex-importacoes');
  if (document.body) document.body.classList.add('comex-importacoes');
</script>

<main class="container comex comex-importacoes">
  <header class="page__head">
    <div class="page__title">
      <h1>Importações (COMEX)</h1>
      <P>ETD = Saída do porto de origem (China). </P>
      <P>ETA = Chegada no porto de destino Paranagua ou Santos. </P>
    </div>

    <div class="page__actions">
      <button class="btn btn--ghost" id="btnReload" type="button" title="Recarregar">⟳</button>
      <button class="btn btn--primary" id="btnForce" type="button" title="Recarregar ignorando cache">⚡</button>
    </div>
  </header>

  <section class="toolbar">
    <div class="toolbar__left">
      <div class="field">
        <label for="q">Buscar</label>
        <input id="q" type="search" placeholder="Ex.: PO02.2024, NEON, LATAS…" autocomplete="off"/>
      </div>

      <div class="field">
        <label for="fase">Fase</label>
        <select id="fase"><option value="">Todas</option></select>
      </div>

      <div class="field">
        <label for="ordem">Ordenar</label>
        <select id="ordem">
          <option value="eta_asc">ETA (menor → maior)</option>
          <option value="eta_desc">ETA (maior → menor)</option>
          <option value="etd_asc">ETD (menor → maior)</option>
          <option value="etd_desc">ETD (maior → menor)</option>
          <option value="nome_asc">Nome (A → Z)</option>
          <option value="nome_desc">Nome (Z → A)</option>
        </select>
      </div>
    </div>

    <div class="toolbar__right">
      <div class="chips">
        <button class="chip is-active" data-range="all" type="button">Todas</button>
        <button class="chip" data-range="next30" type="button">Próx. 30 dias (ETA)</button>
        <button class="chip" data-range="late" type="button">Atrasadas (ETA)</button>
      </div>
    </div>
  </section>

  <section class="kpis" id="kpis">
    <div class="kpi">
      <div class="kpi__label">Total</div>
      <div class="kpi__value" id="kTotal">—</div>
    </div>
    <div class="kpi">
      <div class="kpi__label">Próx. 30 dias</div>
      <div class="kpi__value" id="kNext30">—</div>
    </div>
    <div class="kpi">
      <div class="kpi__label">Atrasadas</div>
      <div class="kpi__value" id="kLate">—</div>
    </div>
    <div class="kpi">
      <div class="kpi__label">Última atualização</div>
      <div class="kpi__value kpi__value--small" id="kUpdated">—</div>
    </div>
  </section>

  <section class="content">
    <div class="board" id="board" aria-label="Quadro de status"></div>

    <div class="empty" id="empty" hidden>
      <div class="empty__title">Nada por aqui</div>
      <div class="empty__sub">Tente ajustar filtros ou busca.</div>
    </div>
  </section>

  <!-- Modal -->
  <div class="comex-modal" id="modal" aria-hidden="true">
    <div class="modal__backdrop" data-close="1"></div>

    <div class="modal__card" role="dialog" aria-modal="true" aria-labelledby="mTitle" tabindex="-1">
      <div class="modal__head">
        <div>
          <div class="modal__kicker">Detalhes</div>
          <div class="modal__title" id="mTitle">—</div>
        </div>
        <button class="iconbtn" type="button" data-close="1" aria-label="Fechar">✕</button>
      </div>

      <div class="modal__body">
        <div class="detailgrid">
          <div class="detail">
            <div class="detail__label">Fase</div>
            <div class="detail__value" id="mFase">—</div>
          </div>
          <div class="detail">
            <div class="detail__label">ETD</div>
            <div class="detail__value" id="mETD">—</div>
          </div>
          <div class="detail">
            <div class="detail__label">ETA</div>
            <div class="detail__value" id="mETA">—</div>
          </div>
          <div class="detail">
            <div class="detail__label">Card ID</div>
            <div class="detail__value mono" id="mId">—</div>
          </div>
        </div>
      </div>

      <div class="modal__foot">
        <button class="btn btn--ghost" type="button" data-close="1">Fechar</button>
      </div>
    </div>
  </div>
</main>

<script>
  window.COMEX_API_URL = "/api/comex-importacoes.php";
</script>

<!-- ✅ ordem ok -->
<script src="/assets/js/loader.js?v=<?= @filemtime(__DIR__ . '/assets/js/loader.js') ?: time() ?>"></script>
<script src="/assets/js/comex-importacoes.js?v=<?= @filemtime(__DIR__ . '/assets/js/comex-importacoes.js') ?: time() ?>"></script>
<script src="/assets/js/header.js?v=<?= @filemtime(__DIR__ . '/assets/js/header.js') ?: time() ?>"></script>
<script src="/assets/js/dropdowns.js?v=<?= @filemtime(__DIR__ . '/assets/js/dropdowns.js') ?: time() ?>"></script>

<script>
/**
 * Modal UX pack:
 * - ESC fecha
 * - click fora fecha
 * - focus trap (TAB)
 * - trava scroll body
 * - animação tipo spring
 * - deep link ?id=PO01 (abre e mantém URL)
 */

(function(){
  const modal = document.getElementById('modal');
  const card  = modal?.querySelector('.modal__card');
  if (!modal || !card) return;

  const focusableSelector = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])'
  ].join(',');

  let lastActive = null;
  let isOpen = false;

  function lockBodyScroll(){
    const sbw = window.innerWidth - document.documentElement.clientWidth;
    document.body.dataset.modalScrollLock = '1';
    document.body.style.overflow = 'hidden';
    if (sbw > 0) document.body.style.paddingRight = sbw + 'px';
  }

  function unlockBodyScroll(){
    if (document.body.dataset.modalScrollLock !== '1') return;
    delete document.body.dataset.modalScrollLock;
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
  }

  function setAria(open){
    modal.setAttribute('aria-hidden', open ? 'false' : 'true');
  }

  // easing com cara de spring (aproximação boa sem lib)
  const springOpenEasing  = 'cubic-bezier(0.16, 1, 0.3, 1)';
  const springCloseEasing = 'cubic-bezier(0.7, 0, 0.84, 0)';

  function animateOpen(){
    // garante display/estado CSS
    modal.classList.add('is-open');

    // anima backdrop + card
    const backdrop = modal.querySelector('.modal__backdrop');
    if (backdrop){
      backdrop.animate(
        [{ opacity: 0 }, { opacity: 1 }],
        { duration: 220, easing: springOpenEasing, fill: 'forwards' }
      );
    }

    card.animate(
      [
        { transform: 'translateY(18px) scale(0.97)', opacity: 0 },
        { transform: 'translateY(0px) scale(1)', opacity: 1 }
      ],
      { duration: 280, easing: springOpenEasing, fill: 'forwards' }
    );
  }

  function animateClose(){
    const backdrop = modal.querySelector('.modal__backdrop');

    const a1 = backdrop ? backdrop.animate(
      [{ opacity: 1 }, { opacity: 0 }],
      { duration: 180, easing: springCloseEasing, fill: 'forwards' }
    ) : null;

    const a2 = card.animate(
      [
        { transform: 'translateY(0px) scale(1)', opacity: 1 },
        { transform: 'translateY(10px) scale(0.985)', opacity: 0 }
      ],
      { duration: 200, easing: springCloseEasing, fill: 'forwards' }
    );

    const done = () => {
      modal.classList.remove('is-open');
    };

    if (a2) a2.onfinish = done;
    else done();
  }

  function updateUrl(id){
    const url = new URL(window.location.href);
    if (id) url.searchParams.set('id', id);
    else url.searchParams.delete('id');
    history.replaceState({}, '', url.toString());
  }

  function trapFocus(e){
    if (!isOpen || e.key !== 'Tab') return;

    const focusables = Array.from(card.querySelectorAll(focusableSelector))
      .filter(el => el.offsetParent !== null);

    if (!focusables.length) {
      e.preventDefault();
      card.focus();
      return;
    }

    const first = focusables[0];
    const last  = focusables[focusables.length - 1];

    if (e.shiftKey && document.activeElement === first){
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last){
      e.preventDefault();
      first.focus();
    }
  }

  function openModalWithItem(item){
    // Preenche com dados do item (compatível com diferentes chaves)
    const id   = item?.id ?? item?.card_id ?? item?.codigo ?? item?.po ?? '';
    const nome = item?.nome ?? item?.title ?? item?.processo ?? item?.descricao ?? 'Processo';

    document.getElementById('mTitle').textContent = nome;
    document.getElementById('mFase').textContent  = item?.fase ?? item?.status ?? '-';
    document.getElementById('mETD').textContent   = item?.etd ?? item?.ETD ?? '-';
    document.getElementById('mETA').textContent   = item?.eta ?? item?.ETA ?? '-';
    document.getElementById('mId').textContent    = id || '-';

    lastActive = document.activeElement;
    isOpen = true;

    setAria(true);
    lockBodyScroll();
    animateOpen();

    // focus inicial
    setTimeout(() => {
      const closeBtn = card.querySelector('[data-close]');
      (closeBtn || card).focus();
    }, 0);

    updateUrl(id || null);
  }

  function closeModal(){
    if (!isOpen) return;
    isOpen = false;

    setAria(false);
    animateClose();
    unlockBodyScroll();
    updateUrl(null);

    // devolve foco
    setTimeout(() => {
      try { lastActive?.focus?.(); } catch (e) {}
      lastActive = null;
    }, 0);
  }

  // click fora (backdrop) + botões data-close
  modal.addEventListener('click', (e) => {
    const t = e.target;
    if (t && t.dataset && t.dataset.close) {
      closeModal();
      return;
    }
    // clicou no backdrop (fora do card)
    if (t === modal) closeModal();
  });

  // ESC + focus trap
  document.addEventListener('keydown', (e) => {
    if (!isOpen) return;
    if (e.key === 'Escape') {
      e.preventDefault();
      closeModal();
      return;
    }
    trapFocus(e);
  });

  // API global pro comex-importacoes.js usar, sem você mexer nele se quiser
  window.COMEX_MODAL = {
    open: openModalWithItem,
    close: closeModal,
  };

  // ✅ deep link ?id=PO01 (abre depois que a lista renderizar)
  function tryOpenFromDeepLink(){
    const url = new URL(window.location.href);
    const wanted = (url.searchParams.get('id') || '').trim();
    if (!wanted) return;

    // tenta achar no DOM primeiro (caso seu JS já renderize e coloque data-id)
    const el = document.querySelector(`[data-id="${CSS.escape(wanted)}"]`);
    if (el) { el.click(); return; }

    // tenta pegar do comex-importacoes.js se ele expõe dados
    // Se não expõe, faz fallback: abre com dados mínimos
    openModalWithItem({ id: wanted, nome: wanted, fase: '-', etd: '-', eta: '-' });
  }

  // roda quando a página terminar de carregar + pequeno delay (pra board renderizar)
  window.addEventListener('load', () => setTimeout(tryOpenFromDeepLink, 450));
})();
</script>

<?php require_once __DIR__ . '/app/footer.php'; ?>