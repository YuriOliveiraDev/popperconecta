
document.addEventListener('DOMContentLoaded', function () {
  const input = document.getElementById('campaignSearch');
  const grid = document.getElementById('campaignGrid');
  const count = document.getElementById('campaignCount');

  function norm(text) {
    return String(text || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim();
  }

  function applyFilter() {
    if (!input || !grid) return;

    const q = norm(input.value);
    const cards = Array.from(grid.querySelectorAll('.campaign-card-page'));
    let visibleCount = 0;

    cards.forEach(function (card) {
      const hay = norm(card.dataset.search || card.textContent || '');
      const visible = !q || hay.includes(q);

      card.classList.toggle('hidden-by-search', !visible);

      if (visible) visibleCount++;
    });

    if (count) {
      count.textContent = String(visibleCount);
    }
  }

  if (input) {
    input.addEventListener('input', applyFilter);
  }

  applyFilter();
});
