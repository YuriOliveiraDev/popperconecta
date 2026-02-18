const track = document.getElementById('track');
const prev = document.getElementById('prev');
const next = document.getElementById('next');

let index = 0;

function slideWidth() {
  const first = track.querySelector('.slide');
  if (!first) return 0;
  const styles = window.getComputedStyle(track);
  const gap = parseInt(styles.columnGap || styles.gap || '0', 10);
  return first.getBoundingClientRect().width + gap;
}

function render() {
  const x = index * slideWidth();
  track.style.transform = `translateX(${-x}px)`;
}

prev?.addEventListener('click', () => {
  index = Math.max(0, index - 1);
  render();
});

next?.addEventListener('click', () => {
  const total = track.querySelectorAll('.slide').length;
  index = Math.min(total - 1, index + 1);
  render();
});

window.addEventListener('resize', render);
render();