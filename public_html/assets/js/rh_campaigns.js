document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('campaignCreateForm');
  const searchInput = document.getElementById('campaignSearch');
  const sortSelect = document.getElementById('campaignSort');
  const filterButtons = document.querySelectorAll('.campaign-filter');
  const grid = document.getElementById('campaignGrid');
  const empty = document.getElementById('campaignEmpty');

  const nameInput = document.getElementById('name');
  const descriptionInput = document.getElementById('description');
  const coinsInput = document.getElementById('coins');
  const categoryInput = document.getElementById('category');
  const activeInput = document.getElementById('is_active');
  const resetBtn = document.getElementById('resetCampaignForm');

  const previewName = document.getElementById('previewName');
  const previewDescription = document.getElementById('previewDescription');
  const previewCoins = document.getElementById('previewCoins');
  const previewCategory = document.getElementById('previewCategory');
  const previewStatus = document.getElementById('previewStatus');

  const openModalBtn = document.getElementById('openCampaignModal');
  const modal = document.getElementById('campaignModal');
  const closeModalBtn = document.getElementById('closeCampaignModal');
  const cancelModalBtn = document.getElementById('cancelCampaignModal');
  const confirmModalBtn = document.getElementById('confirmCampaignModal');

  let activeFilter = 'all';

  function norm(text) {
    return String(text || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim();
  }

  function formatCoins(value) {
    const num = Number(value || 0);
    if (!Number.isFinite(num) || num <= 0) return '0 coins';
    return num.toLocaleString('pt-BR') + ' coins';
  }

  function updatePreview() {
    if (previewName) {
      previewName.textContent = (nameInput?.value || '').trim() || 'Nova campanha';
    }

    if (previewDescription) {
      previewDescription.textContent = (descriptionInput?.value || '').trim() || 'A descrição aparecerá aqui.';
    }

    if (previewCoins) {
      previewCoins.textContent = formatCoins(coinsInput?.value || 0);
    }

    if (previewCategory) {
      previewCategory.textContent = (categoryInput?.value || '').trim() || 'Sem categoria';
    }

    if (previewStatus) {
      previewStatus.textContent = activeInput?.checked ? 'Ativa' : 'Inativa';
    }
  }

  function updateCharCounters() {
    document.querySelectorAll('.char-count[data-for]').forEach(function (counter) {
      const fieldId = counter.getAttribute('data-for');
      const field = document.getElementById(fieldId);
      if (!field) return;

      const max = Number(field.getAttribute('maxlength') || 0);
      const len = String(field.value || '').length;
      counter.textContent = max > 0 ? `${len}/${max}` : String(len);
      counter.classList.toggle('is-limit', max > 0 && len >= max);
    });
  }

  function applyFilters() {
    if (!grid) return;

    const query = norm(searchInput ? searchInput.value : '');
    const sort = sortSelect ? sortSelect.value : 'default';
    const cards = Array.from(grid.querySelectorAll('.campaign-item'));

    cards.forEach(function (card) {
      const title = norm(card.dataset.title || '');
      const category = norm(card.dataset.category || '');
      const state = card.dataset.filterState || 'all';

      let visible = true;

      if (query && !title.includes(query) && !category.includes(query)) {
        visible = false;
      }

      if (activeFilter !== 'all' && state !== activeFilter) {
        visible = false;
      }

      card.style.display = visible ? '' : 'none';
    });

    const visibleCards = cards.filter(card => card.style.display !== 'none');

    visibleCards.sort(function (a, b) {
      const coinsA = Number(a.dataset.coins || 0);
      const coinsB = Number(b.dataset.coins || 0);
      const orderA = Number(a.dataset.order || 0);
      const orderB = Number(b.dataset.order || 0);
      const titleA = norm(a.dataset.title || '');
      const titleB = norm(b.dataset.title || '');

      switch (sort) {
        case 'coins_asc':
          return coinsA - coinsB || orderA - orderB;
        case 'coins_desc':
          return coinsB - coinsA || orderA - orderB;
        case 'name_asc':
          return titleA.localeCompare(titleB, 'pt-BR') || orderA - orderB;
        default:
          return orderA - orderB;
      }
    });

    visibleCards.forEach(card => grid.appendChild(card));

    if (empty) {
      empty.hidden = visibleCards.length > 0;
    }
  }

  function openModal() {
    if (!modal) return;
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    if (!modal) return;
    modal.hidden = true;
    document.body.style.overflow = '';
  }

  if (nameInput) nameInput.addEventListener('input', updatePreview);
  if (descriptionInput) descriptionInput.addEventListener('input', function () {
    updatePreview();
    updateCharCounters();
  });
  if (coinsInput) coinsInput.addEventListener('input', updatePreview);
  if (categoryInput) categoryInput.addEventListener('input', updatePreview);
  if (activeInput) activeInput.addEventListener('change', updatePreview);

  if (resetBtn) {
    resetBtn.addEventListener('click', function () {
      window.setTimeout(function () {
        updatePreview();
        updateCharCounters();
      }, 0);
    });
  }

  if (form) {
    form.addEventListener('submit', function () {
      const btn = form.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Salvando...';
      }
    });
  }

  if (searchInput) {
    searchInput.addEventListener('input', applyFilters);
    searchInput.addEventListener('keyup', applyFilters);
    searchInput.addEventListener('search', applyFilters);
  }

  if (sortSelect) {
    sortSelect.addEventListener('change', applyFilters);
  }

  filterButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      filterButtons.forEach(b => b.classList.remove('is-active'));
      btn.classList.add('is-active');
      activeFilter = btn.dataset.filter || 'all';
      applyFilters();
    });
  });

  document.querySelectorAll('.js-confirm-delete').forEach(function (formEl) {
    formEl.addEventListener('submit', function (e) {
      if (!window.confirm('Deseja realmente excluir esta campanha?')) {
        e.preventDefault();
      }
    });
  });

  document.querySelectorAll('.js-confirm-toggle').forEach(function (formEl) {
    formEl.addEventListener('submit', function (e) {
      if (!window.confirm('Deseja alterar o status desta campanha?')) {
        e.preventDefault();
      }
    });
  });

  if (openModalBtn) {
    openModalBtn.addEventListener('click', openModal);
  }

  if (closeModalBtn) {
    closeModalBtn.addEventListener('click', closeModal);
  }

  if (cancelModalBtn) {
    cancelModalBtn.addEventListener('click', closeModal);
  }

  if (confirmModalBtn) {
    confirmModalBtn.addEventListener('click', function () {
      closeModal();
      if (nameInput) {
        nameInput.focus();
        nameInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });
  }

  if (modal) {
    modal.addEventListener('click', function (e) {
      if (e.target === modal) {
        closeModal();
      }
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal && !modal.hidden) {
      closeModal();
    }
  });

  updatePreview();
  updateCharCounters();
  applyFilters();
});