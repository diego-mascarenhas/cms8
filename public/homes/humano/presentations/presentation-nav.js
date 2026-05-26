const slides = [...document.querySelectorAll('.slide')];
let cur = 0;
const total = slides.length;
const bar = document.querySelector('#bar i');
const count = document.querySelector('#count');

function fit() {
  const s = Math.min(innerWidth / 1280, innerHeight / 720);
  document.querySelector('#stage').style.transform = 'translate(-50%, -50%) scale(' + s + ')';
}

function show(n) {
  cur = Math.max(0, Math.min(total - 1, n));
  slides.forEach((slide, i) => slide.classList.toggle('active', i === cur));
  bar.style.width = ((cur + 1) / total * 100) + '%';
  count.innerHTML = '<b>' + String(cur + 1).padStart(2, '0') + '</b> / ' + String(total).padStart(2, '0');
}

function next() {
  show(cur + 1);
}

function prev() {
  show(cur - 1);
}

addEventListener('keydown', (e) => {
  if (['ArrowRight', 'PageDown', ' '].includes(e.key)) {
    e.preventDefault();
    next();
  } else if (['ArrowLeft', 'PageUp'].includes(e.key)) {
    e.preventDefault();
    prev();
  } else if (e.key === 'Home') {
    show(0);
  } else if (e.key === 'End') {
    show(total - 1);
  } else if (e.key === 'f' || e.key === 'F') {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen();
    } else {
      document.exitFullscreen();
    }
  }
});

addEventListener('resize', fit);

let sx = 0;
addEventListener('touchstart', (e) => {
  sx = e.touches[0].clientX;
}, { passive: true });

addEventListener('touchend', (e) => {
  const dx = e.changedTouches[0].clientX - sx;
  if (dx < -46) {
    next();
  } else if (dx > 46) {
    prev();
  }
});

fit();
show(0);
