/* =========================
   users.js (Admin / Usuários)
   ========================= */

(function () {
  function arm(id) {
    var el = document.getElementById(id);
    if (!el) return;
    try { el.value = ''; } catch (e) {}
    var unfreeze = function () {
      if (el.hasAttribute('readonly')) el.removeAttribute('readonly');
      el.removeEventListener('focus', unfreeze);
      el.removeEventListener('pointerdown', unfreeze);
      el.removeEventListener('keydown', unfreeze);
    };
    el.addEventListener('focus', unfreeze, { once: true });
    el.addEventListener('pointerdown', unfreeze, { once: true });
    el.addEventListener('keydown', unfreeze, { once: true });
  }
  arm('adm_email');
  arm('adm_pass');
})();

(function(){
  var input = document.getElementById('profile_photo');
  var nameEl = document.getElementById('profilePhotoName');
  if (!input || !nameEl) return;

  input.addEventListener('change', function(){
    var f = input.files && input.files[0] ? input.files[0] : null;
    nameEl.textContent = f ? f.name : 'Nenhum arquivo selecionado';
  });
})();

(function () {
  function bindFileInput(inputId, nameId, chipId) {
    const input = document.getElementById(inputId);
    const nameEl = document.getElementById(nameId);
    const chipEl = chipId ? document.getElementById(chipId) : null;
    if (!input || !nameEl) return;

    function render() {
      const file = input.files && input.files[0] ? input.files[0] : null;
      nameEl.textContent = file ? file.name : 'Nenhum arquivo selecionado';
      if (chipEl) chipEl.style.display = file ? 'inline-flex' : 'none';
    }

    input.addEventListener('change', render);
    render();
  }

  // Exemplo: Recompensas
  bindFileInput('rewardImage', 'rewardImageName', 'rewardImageChip');

  // Exemplo: Foto de perfil (se quiser padronizar também)
  // bindFileInput('profile_photo', 'profilePhotoName', null);
})();
(function(){
  const input = document.getElementById('userProfilePhoto');
  const img = document.getElementById('userPhotoPreviewImg');
  const emoji = document.getElementById('userPhotoEmoji');
  const fileNameEl = document.getElementById('userProfilePhotoName');

  if (!input || !img || !emoji) return;

  function showEmoji(){
    img.style.display = 'none';
    img.removeAttribute('src');
    emoji.style.display = '';
  }

  function showImg(src){
    img.src = src;
    img.style.display = '';
    emoji.style.display = 'none';
  }

  input.addEventListener('change', function(){
    const file = input.files && input.files[0] ? input.files[0] : null;
    if (!file) return;

    if (fileNameEl) fileNameEl.textContent = file.name;

    const url = URL.createObjectURL(file);
    showImg(url);

    img.onload = () => URL.revokeObjectURL(url);
  });
})();
