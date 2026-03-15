(function () {
  function bindPhotoPreview(config) {
    const input = document.getElementById(config.inputId);
    const nameEl = document.getElementById(config.nameId);
    const img = document.getElementById(config.imgId);
    const placeholder = document.getElementById(config.placeholderId);
    const removeCb = config.removeSelector ? document.querySelector(config.removeSelector) : null;

    if (!input || !nameEl || !img) return;

    function showPlaceholder() {
      img.style.display = 'none';
      img.removeAttribute('src');
      if (placeholder) placeholder.style.display = '';
    }

    function showImage(src) {
      img.src = src;
      img.style.display = '';
      if (placeholder) placeholder.style.display = 'none';
    }

    input.addEventListener('change', function () {
      const file = input.files && input.files[0] ? input.files[0] : null;

      nameEl.textContent = file ? file.name : 'Nenhum arquivo selecionado';

      if (!file) return;

      if (removeCb) removeCb.checked = false;

      const url = URL.createObjectURL(file);
      showImage(url);

      img.onload = function () {
        URL.revokeObjectURL(url);
      };
    });

    if (removeCb) {
      removeCb.addEventListener('change', function () {
        if (!removeCb.checked) return;

        input.value = '';
        nameEl.textContent = 'Nenhum arquivo selecionado';
        showPlaceholder();
      });
    }
  }

  bindPhotoPreview({
    inputId: 'profile_photo',
    nameId: 'profilePhotoName',
    imgId: 'profilePhotoImg',
    placeholderId: 'profilePhotoPlaceholder',
    removeSelector: '#remove_photo'
  });

  bindPhotoPreview({
    inputId: 'userProfilePhoto',
    nameId: 'userProfilePhotoName',
    imgId: 'userPhotoPreviewImg',
    placeholderId: 'userPhotoEmoji',
    removeSelector: 'input[name="remove_photo"]'
  });
})();