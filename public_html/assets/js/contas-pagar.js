document.addEventListener('DOMContentLoaded', function() {
    const modalOverlay = document.getElementById('modal-overlay');
    const modalTitle = document.getElementById('modal-title');
    const modalTableBody = document.getElementById('modal-table-body');
    const modalTotal = document.getElementById('modal-total');
    const modalClose = document.getElementById('modal-close');
    const fornOverlay = document.getElementById('modal-forn-overlay');
const fornTitle = document.getElementById('modal-forn-title');
const fornBody = document.getElementById('modal-forn-body');
const fornTotal = document.getElementById('modal-forn-total');
const fornClose = document.getElementById('modal-forn-close');
    
    // Formatar valor em reais
    function formatMoney(value) {
        return 'R$ ' + value.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    // Abrir modal
    function openModal(data) {
        modalTitle.textContent = data.centro;
        modalTableBody.innerHTML = '';
        
        if (data.fornecedores.length === 0) {
            modalTableBody.innerHTML = '<tr><td colspan="4" class="modal-empty">Nenhum fornecedor encontrado</td></tr>';
        } else {
            data.fornecedores.forEach(function(f) {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${f.nome}</td>
                    <td style="text-align:center;">${f.qtd}</td>
                    <td class="valor">${formatMoney(f.total)}</td>
                    <td class="percent">${f.percent}%</td>
                `;
                modalTableBody.appendChild(row);
            });
        }
        
        modalTotal.textContent = formatMoney(data.total);
        modalOverlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    
    // Fechar modal
    function closeModal() {
        modalOverlay.classList.remove('show');
        document.body.style.overflow = '';
    }
    
    // Event listeners para abrir modal
    document.querySelectorAll('.centro-custo-item').forEach(function(item) {
        item.addEventListener('click', function() {
            const data = JSON.parse(this.getAttribute('data-centro'));
            openModal(data);
        });
    });

    function closeFornecedorModal() {
  fornOverlay.classList.remove('show');
  document.body.style.overflow = '';
}

function openFornecedorModal(data) {
  fornTitle.textContent = data.fornecedor || 'Fornecedor';
  fornBody.innerHTML = '';

  const titulos = Array.isArray(data.titulos) ? data.titulos : [];
  let total = 0;

  if (titulos.length === 0) {
    fornBody.innerHTML = '<tr><td colspan="10" class="modal-empty">Nenhum título encontrado.</td></tr>';
  } else {
    titulos.forEach((t) => {
      const v = Number(t.valor || 0);
      total += v;

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${t.filial || ''}</td>
        <td>${t.emissao || ''}</td>
        <td>${t.vencimento || ''}</td>
        <td>${t.centro || ''}</td>
        <td>${t.prefixo || ''}</td>
        <td>${t.numero || ''}</td>
        <td>${t.parcela || ''}</td>
        <td>${t.tipo || ''}</td>
        <td title="${(t.historico || '').replaceAll('"','&quot;')}">${t.historico || ''}</td>
        <td class="valor">${formatMoney(v)}</td>
      `;
      fornBody.appendChild(tr);
    });
  }

  fornTotal.textContent = formatMoney(total);
  fornOverlay.classList.add('show');
  // Clique no ranking de fornecedores
document.querySelectorAll('.fornecedor-item').forEach(function(item) {
  item.addEventListener('click', function() {
    const raw = this.getAttribute('data-fornecedor') || '{}';
    const data = JSON.parse(raw);
    openFornecedorModal(data);
  });
});

// Fechar
fornClose.addEventListener('click', closeFornecedorModal);

fornOverlay.addEventListener('click', function(e) {
  if (e.target === fornOverlay) closeFornecedorModal();
});

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape' && fornOverlay.classList.contains('show')) {
    closeFornecedorModal();
  }
});
  document.body.style.overflow = 'hidden';
}
    
    // Fechar ao clicar no X
    modalClose.addEventListener('click', closeModal);
    
    // Fechar ao clicar fora do modal
    modalOverlay.addEventListener('click', function(e) {
        if (e.target === modalOverlay) {
            closeModal();
        }
    });
    
    // Fechar com tecla ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modalOverlay.classList.contains('show')) {
            closeModal();
        }
    });
});