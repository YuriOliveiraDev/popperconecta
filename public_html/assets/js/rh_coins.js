document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('coinLaunchForm');

  const actionSelect = document.getElementById('action');
  const campaignSelect = document.getElementById('campaign_id');
  const amountInput = document.getElementById('amount');
  const reasonInput = document.getElementById('reason');
  const resetBtn = document.getElementById('resetLaunchForm');

  const previewUsers = document.getElementById('previewUsers');
  const previewAction = document.getElementById('previewAction');
  const previewAmount = document.getElementById('previewAmount');
  const previewReason = document.getElementById('previewReason');
  const previewCampaign = document.getElementById('previewCampaign');
  const previewTotalUsers = document.getElementById('previewTotalUsers');

  const selectedUsersSummary = document.getElementById('selectedUsersSummary');
  const selectedUsersHidden = document.getElementById('selectedUsersHidden');

  const usersModal = document.getElementById('usersModal');
  const openUsersModal = document.getElementById('openUsersModal');
  const closeUsersModal = document.getElementById('closeUsersModal');
  const cancelUsersModal = document.getElementById('cancelUsersModal');
  const applyUsersSelection = document.getElementById('applyUsersSelection');
  const userModalSearch = document.getElementById('userModalSearch');
  const checkAllUsers = document.getElementById('checkAllUsers');
  const uncheckAllUsers = document.getElementById('uncheckAllUsers');
  const userPickerList = document.getElementById('userPickerList');

  const ledgerSearch = document.getElementById('ledgerSearch');

  const balanceSearch = document.getElementById('balanceSearch');
  const balanceRows = document.querySelectorAll('#balancesTable .balance-row');

  if (balanceSearch) {
    balanceSearch.addEventListener('input', function () {
      const term = this.value.trim().toLowerCase();

      balanceRows.forEach((row) => {
        const haystack = (row.dataset.search || '').toLowerCase();
        row.style.display = haystack.includes(term) ? '' : 'none';
      });
    });
  }


  let selectedUsers = [];



  function formatCoins(value) {
    const num = Number(value || 0);
    if (!Number.isFinite(num)) return '0 coins';
    return num.toLocaleString('pt-BR') + ' coins';
  }

  function actionLabel(value) {
    switch (value) {
      case 'grant': return 'Adicionar';
      case 'revoke': return 'Remover';
      case 'redeem': return 'Resgate';
      case 'adjust': return 'Ajuste';
      default: return 'Ação';
    }
  }

  function normalize(text) {
    return String(text || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim();
  }

  function syncCampaignFields() {
    const opt = campaignSelect?.options[campaignSelect.selectedIndex];

    if (!opt || !opt.value) {
      amountInput.readOnly = false;
      reasonInput.readOnly = false;
      return;
    }

    const name = opt.dataset.name || '';
    const description = opt.dataset.description || '';
    const coins = opt.dataset.coins || '';

    amountInput.value = coins;
    reasonInput.value = description.trim() !== '' ? description : name;
    amountInput.readOnly = true;
    reasonInput.readOnly = true;
  }

  function rebuildHiddenInputs() {
    selectedUsersHidden.innerHTML = '';

    selectedUsers.forEach(function (user) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'user_ids[]';
      input.value = user.id;
      selectedUsersHidden.appendChild(input);
    });
  }

  function updateSelectedUsersSummary() {
    if (!selectedUsers.length) {
      selectedUsersSummary.textContent = 'Nenhum usuário selecionado.';
      return;
    }

    const names = selectedUsers.slice(0, 5).map(u => u.name);
    const extra = selectedUsers.length > 5 ? ` +${selectedUsers.length - 5}` : '';
    selectedUsersSummary.textContent = `${selectedUsers.length} usuário(s): ${names.join(', ')}${extra}`;
  }

  function updatePreview() {
    previewAction.textContent = actionLabel(actionSelect?.value || '');

    previewAmount.textContent = formatCoins(amountInput?.value || 0);

    previewReason.textContent = reasonInput?.value?.trim() || 'Sem motivo informado.';

    if (campaignSelect && campaignSelect.value) {
      const opt = campaignSelect.options[campaignSelect.selectedIndex];
      previewCampaign.textContent = opt ? opt.textContent : 'Campanha';
    } else {
      previewCampaign.textContent = 'Manual';
    }

    previewTotalUsers.textContent = `${selectedUsers.length} usuário(s)`;

    if (!selectedUsers.length) {
      previewUsers.textContent = 'Nenhum usuário selecionado';
    } else if (selectedUsers.length === 1) {
      previewUsers.textContent = selectedUsers[0].name;
    } else {
      previewUsers.textContent = `${selectedUsers.length} usuários selecionados`;
    }
  }

  function openModal() {
    if (!usersModal) return;
    usersModal.hidden = false;
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    if (!usersModal) return;
    usersModal.hidden = true;
    document.body.style.overflow = '';
  }

  function applyModalFilter() {
    const query = normalize(userModalSearch?.value || '');
    const items = userPickerList?.querySelectorAll('.user-picker-item') || [];

    items.forEach(function (item) {
      const hay = normalize(item.dataset.search || '');
      item.style.display = !query || hay.includes(query) ? '' : 'none';
    });
  }

  function syncModalChecksFromSelection() {
    const checks = document.querySelectorAll('.user-picker-check');
    checks.forEach(function (check) {
      const exists = selectedUsers.some(u => String(u.id) === String(check.value));
      check.checked = exists;
    });
  }

  function saveModalSelection() {
    const checks = document.querySelectorAll('.user-picker-check:checked');
    selectedUsers = Array.from(checks).map(function (check) {
      return {
        id: check.value,
        name: check.dataset.name || 'Usuário'
      };
    });

    rebuildHiddenInputs();
    updateSelectedUsersSummary();
    updatePreview();
    closeModal();
  }

  function bindTableFilter(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    input.addEventListener('input', function () {
      const query = normalize(input.value);
      const rows = table.querySelectorAll('tbody tr[data-search]');

      rows.forEach(function (row) {
        const hay = normalize(row.dataset.search || '');
        row.style.display = !query || hay.includes(query) ? '' : 'none';
      });
    });
  }

  campaignSelect?.addEventListener('change', function () {
    syncCampaignFields();
    updatePreview();
  });

  [actionSelect, amountInput, reasonInput].forEach(function (el) {
    el?.addEventListener('input', updatePreview);
    el?.addEventListener('change', updatePreview);
  });

  openUsersModal?.addEventListener('click', function () {
    syncModalChecksFromSelection();
    openModal();
  });

  closeUsersModal?.addEventListener('click', closeModal);
  cancelUsersModal?.addEventListener('click', closeModal);
  applyUsersSelection?.addEventListener('click', saveModalSelection);

  usersModal?.addEventListener('click', function (e) {
    if (e.target === usersModal) {
      closeModal();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && usersModal && !usersModal.hidden) {
      closeModal();
    }
  });

  userModalSearch?.addEventListener('input', applyModalFilter);

  checkAllUsers?.addEventListener('click', function () {
    const visibleChecks = Array.from(document.querySelectorAll('.user-picker-item'))
      .filter(item => item.style.display !== 'none')
      .map(item => item.querySelector('.user-picker-check'));

    visibleChecks.forEach(check => {
      if (check) check.checked = true;
    });
  });

  uncheckAllUsers?.addEventListener('click', function () {
    document.querySelectorAll('.user-picker-check').forEach(function (check) {
      check.checked = false;
    });
  });

  resetBtn?.addEventListener('click', function () {
    window.setTimeout(function () {
      selectedUsers = [];
      rebuildHiddenInputs();
      updateSelectedUsersSummary();
      if (campaignSelect) {
        campaignSelect.value = '';
      }
      amountInput.readOnly = false;
      reasonInput.readOnly = false;
      updatePreview();
    }, 0);
  });

  form?.addEventListener('submit', function (e) {
    if (!selectedUsers.length) {
      e.preventDefault();
      alert('Selecione pelo menos um usuário.');
      return;
    }

    const btn = form.querySelector('button[type="submit"].btn-modern--accent');
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Salvando...';
    }
  });

  bindTableFilter('balanceSearch', 'balancesTable');
  bindTableFilter('ledgerSearch', 'ledgerTable');

  updateSelectedUsersSummary();
  updatePreview();
});

const usersModal = document.getElementById('usersModal');
const openUsersModal = document.getElementById('openUsersModal');
const closeUsersModal = document.getElementById('closeUsersModal');
const cancelUsersModal = document.getElementById('cancelUsersModal');
const applyUsersSelection = document.getElementById('applyUsersSelection');

function showUsersModal() {
  if (!usersModal) return;
  usersModal.hidden = false;
  usersModal.classList.add('is-open');
  document.body.classList.add('modal-open');
}

function hideUsersModal() {
  if (!usersModal) return;
  usersModal.classList.remove('is-open');
  usersModal.hidden = true;
  document.body.classList.remove('modal-open');
}

openUsersModal?.addEventListener('click', showUsersModal);
closeUsersModal?.addEventListener('click', hideUsersModal);
cancelUsersModal?.addEventListener('click', hideUsersModal);
applyUsersSelection?.addEventListener('click', hideUsersModal);

usersModal?.addEventListener('click', (e) => {
  if (e.target === usersModal) {
    hideUsersModal();
  }
});

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && usersModal && !usersModal.hidden) {
    hideUsersModal();
  }
});