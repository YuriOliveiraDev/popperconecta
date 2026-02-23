<script>
(function(){
  const input = document.getElementById('profile_photo');
  const nameEl = document.getElementById('profilePhotoName');
  const img = document.getElementById('profilePhotoImg');
  const placeholder = document.getElementById('profilePhotoPlaceholder');
  const removeCb = document.getElementById('remove_photo');

  if (!input || !nameEl || !img) return;

  input.addEventListener('change', function(){
    const f = input.files && input.files[0] ? input.files[0] : null;

    nameEl.textContent = f ? f.name : 'Nenhum arquivo selecionado';

    if (!f) return;

    // se marcou "remover foto", desmarca automaticamente ao escolher novo arquivo
    if (removeCb) removeCb.checked = false;

    const url = URL.createObjectURL(f);
    img.src = url;
    img.style.display = 'block';
    if (placeholder) placeholder.style.display = 'none';

    // libera memória quando trocar de arquivo novamente
    img.onload = () => URL.revokeObjectURL(url);
  });
})();
</script>