/* =========================
   comunicados.js (Admin / Comunicados)
   ========================= */

function editFromButton(btn) {
  var id = btn.getAttribute('data-id');
  var titulo = btn.getAttribute('data-titulo') || '';
  var conteudo = btn.getAttribute('data-conteudo') || '';
  var imagem = btn.getAttribute('data-imagem') || '';
  var ordem = btn.getAttribute('data-ordem') || '0';
  var ativo = btn.getAttribute('data-ativo') || '1';

  document.querySelector('input[name="id"]').value = id;
  document.querySelector('input[name="titulo"]').value = titulo;
  document.querySelector('textarea[name="conteudo"]').value = conteudo;
  document.querySelector('input[name="ordem"]').value = ordem;

  // ativo agora é hidden (mantém o estado do comunicado)
  document.querySelector('input[name="ativo"]').value = (ativo === '1' ? '1' : '0');

  // remove_imagem hidden volta ao padrão 0
  document.querySelector('input[name="remove_imagem"]').value = '0';

  var formTitle = document.getElementById('formTitle');
  if (formTitle) formTitle.textContent = 'Editar Comunicado';

  var saveBtn = document.getElementById('saveBtn');
  if (saveBtn) saveBtn.textContent = 'Atualizar';

  var cancel = document.getElementById('cancelEditBtn');
  if (cancel) cancel.style.display = 'inline-flex';

  var removeBtn = document.getElementById('removeImgBtn');
  if (removeBtn) {
    // só mostra se existe imagem
    removeBtn.style.display = (imagem !== '' ? 'inline-flex' : 'none');
    removeBtn.dataset.hasImage = (imagem !== '' ? '1' : '0');
  }

  // rola até o formulário (melhor UX)
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

(function(){
  var cancel = document.getElementById('cancelEditBtn');
  var removeBtn = document.getElementById('removeImgBtn');
  if (cancel) {
    cancel.addEventListener('click', function(){
      document.querySelector('input[name="id"]').value = '';
      document.querySelector('input[name="titulo"]').value = '';
      document.querySelector('textarea[name="conteudo"]').value = '';
      document.querySelector('input[name="ordem"]').value = 0;

      document.querySelector('input[name="ativo"]').value = '1';
      document.querySelector('input[name="remove_imagem"]').value = '0';

      var formTitle = document.getElementById('formTitle');
      if (formTitle) formTitle.textContent = 'Adicionar Comunicado';

      var saveBtn = document.getElementById('saveBtn');
      if (saveBtn) saveBtn.textContent = 'Salvar';

      cancel.style.display = 'none';

      if (removeBtn) removeBtn.style.display = 'none';
    });
  }

  if (removeBtn) {
    removeBtn.addEventListener('click', function(){
      // marca hidden para remover a imagem ao salvar
      document.querySelector('input[name="remove_imagem"]').value = '1';
      removeBtn.style.display = 'none';
      alert('Imagem marcada para remoção. Clique em "Atualizar" para confirmar.');
    });
  }
})();

(function(){
  var input = document.getElementById('imagem');
  var nameEl = document.getElementById('imagemName');

  if (!input || !nameEl) return;

  input.addEventListener('change', function(){
    var file = input.files && input.files[0] ? input.files[0] : null;
    nameEl.textContent = file ? file.name : 'Nenhum arquivo selecionado';
  });
})();
