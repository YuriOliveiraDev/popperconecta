/* =========================
   rh_rewards.js (Admin / RH Rewards)
   ========================= */

function toggleSaveButton() {
  const form = document.getElementById('rewardForm');
  const idField = form ? form.querySelector('input[name="id"]') : null;
  const saveBtn = document.getElementById('saveBtn');

  if (!idField || !saveBtn) return;

  const id = String(idField.value || '').trim();
  const isEditing = (id !== '' && Number(id) > 0);

  // Mantém "Salvar" sempre visível, só troca o texto
  saveBtn.textContent = isEditing ? 'Atualizar' : 'Criar';
}

function resetRewardForm(){
  const f = document.getElementById('rewardForm');
  if (!f) return;

  const idEl = f.querySelector('input[name="id"]');
  const titleEl = f.querySelector('input[name="title"]');
  const descEl = f.querySelector('input[name="description"]');
  const costEl = f.querySelector('input[name="cost"]');
  const invEl = f.querySelector('input[name="inventory"]');
  const sortEl = f.querySelector('input[name="sort_order"]');
  const activeEl = f.querySelector('input[name="is_active"]');

  if (idEl) idEl.value = '';
  if (titleEl) titleEl.value = '';
  if (descEl) descEl.value = '';
  if (costEl) costEl.value = '';
  if (invEl) invEl.value = '0';
  if (sortEl) sortEl.value = '0';
  if (activeEl) activeEl.checked = true;

  toggleSaveButton();

  if (titleEl) titleEl.focus();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function editReward(id,title,description,cost,inventory,sort_order,is_active){
  const f = document.getElementById('rewardForm');
  if (!f) return;

  f.querySelector('input[name="id"]').value = id;
  f.querySelector('input[name="title"]').value = title;
  f.querySelector('input[name="description"]').value = description;
  f.querySelector('input[name="cost"]').value = cost;
  f.querySelector('input[name="inventory"]').value = inventory;
  f.querySelector('input[name="sort_order"]').value = sort_order;
  f.querySelector('input[name="is_active"]').checked = (Number(is_active) === 1);

  toggleSaveButton();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.addEventListener('DOMContentLoaded', () => {
  // estado inicial do botão
  toggleSaveButton();

  // botão Novo (funciona mesmo sem onclick inline)
  const newBtn = document.getElementById('newBtn');
  if (newBtn) {
    newBtn.addEventListener('click', (e) => {
      e.preventDefault();
      resetRewardForm();
    });
  }
});