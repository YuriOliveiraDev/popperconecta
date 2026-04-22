(function () {
  const OUTPUT_SIZE = 512;
  const STYLE_ID = 'popper-photo-cropper-style';

  function ensureStyles() {
    if (document.getElementById(STYLE_ID)) return;

    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = `
      .photo-cropper-overlay {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 22px;
        background: rgba(22, 18, 34, 0.56);
        backdrop-filter: blur(8px);
      }

      .photo-cropper-modal {
        width: min(620px, 100%);
        max-height: min(92vh, 760px);
        overflow: auto;
        border-radius: 28px;
        background: #ffffff;
        box-shadow: 0 30px 80px rgba(35, 20, 53, 0.28);
        padding: 24px;
        color: #231b35;
        font-family: inherit;
      }

      .photo-cropper-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 18px;
      }

      .photo-cropper-title {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 800;
        color: #231b35;
      }

      .photo-cropper-subtitle {
        margin: 6px 0 0;
        color: #746986;
        font-size: 0.92rem;
        line-height: 1.45;
      }

      .photo-cropper-close {
        width: 38px;
        height: 38px;
        border: 1px solid #eee7f4;
        border-radius: 999px;
        background: #fff;
        color: #5f2785;
        cursor: pointer;
        font-size: 1.35rem;
        line-height: 1;
      }

      .photo-cropper-stage {
        display: grid;
        place-items: center;
        padding: 18px;
        border: 1px solid #f0e8f5;
        border-radius: 24px;
        background:
          radial-gradient(circle at 20% 15%, rgba(160, 219, 20, 0.12), transparent 26%),
          linear-gradient(135deg, #fbf8fd, #ffffff);
      }

      .photo-cropper-canvas-wrap {
        width: min(360px, 78vw);
        aspect-ratio: 1;
        border-radius: 50%;
        overflow: hidden;
        box-shadow: 0 18px 45px rgba(45, 30, 68, 0.18);
        background: #f7f3fa;
      }

      .photo-cropper-canvas {
        width: 100%;
        height: 100%;
        display: block;
      }

      .photo-cropper-controls {
        display: grid;
        gap: 14px;
        margin-top: 18px;
      }

      .photo-cropper-control label {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 7px;
        color: #41354f;
        font-weight: 700;
        font-size: 0.9rem;
      }

      .photo-cropper-control input[type="range"] {
        width: 100%;
        accent-color: #64258b;
      }

      .photo-cropper-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 22px;
      }

      .photo-cropper-btn {
        border: 0;
        border-radius: 14px;
        padding: 12px 18px;
        font-weight: 800;
        cursor: pointer;
      }

      .photo-cropper-btn--ghost {
        background: #f3edf7;
        color: #4d3b5d;
      }

      .photo-cropper-btn--primary {
        background: #64258b;
        color: #ffffff;
        box-shadow: 0 12px 26px rgba(100, 37, 139, 0.22);
      }

      @media (max-width: 560px) {
        .photo-cropper-overlay {
          align-items: flex-end;
          padding: 10px;
        }

        .photo-cropper-modal {
          border-radius: 24px 24px 18px 18px;
          padding: 18px;
        }

        .photo-cropper-actions {
          flex-direction: column-reverse;
        }

        .photo-cropper-btn {
          width: 100%;
        }
      }
    `;
    document.head.appendChild(style);
  }

  function sanitizeName(name) {
    const base = (name || 'foto-perfil').replace(/\.[^.]+$/, '');
    return base
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-zA-Z0-9_-]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .toLowerCase() || 'foto-perfil';
  }

  function loadImage(file) {
    return new Promise((resolve, reject) => {
      const image = new Image();
      const url = URL.createObjectURL(file);

      image.onload = () => {
        URL.revokeObjectURL(url);
        resolve(image);
      };

      image.onerror = () => {
        URL.revokeObjectURL(url);
        reject(new Error('Nao foi possivel carregar a imagem selecionada.'));
      };

      image.src = url;
    });
  }

  function createModal() {
    ensureStyles();

    const overlay = document.createElement('div');
    overlay.className = 'photo-cropper-overlay';
    overlay.innerHTML = `
      <div class="photo-cropper-modal" role="dialog" aria-modal="true" aria-labelledby="photoCropperTitle">
        <div class="photo-cropper-head">
          <div>
            <h2 class="photo-cropper-title" id="photoCropperTitle">Ajustar foto do perfil</h2>
            <p class="photo-cropper-subtitle">Centralize o rosto, ajuste o zoom e aplique. A foto sera salva em formato quadrado.</p>
          </div>
          <button type="button" class="photo-cropper-close" aria-label="Fechar">x</button>
        </div>
        <div class="photo-cropper-stage">
          <div class="photo-cropper-canvas-wrap">
            <canvas class="photo-cropper-canvas" width="${OUTPUT_SIZE}" height="${OUTPUT_SIZE}"></canvas>
          </div>
        </div>
        <div class="photo-cropper-controls">
          <div class="photo-cropper-control">
            <label for="photoCropperZoom">Zoom <span data-value="zoom">100%</span></label>
            <input id="photoCropperZoom" type="range" min="1" max="3" step="0.01" value="1">
          </div>
          <div class="photo-cropper-control">
            <label for="photoCropperX">Horizontal <span data-value="x">0</span></label>
            <input id="photoCropperX" type="range" min="-100" max="100" step="1" value="0">
          </div>
          <div class="photo-cropper-control">
            <label for="photoCropperY">Vertical <span data-value="y">0</span></label>
            <input id="photoCropperY" type="range" min="-100" max="100" step="1" value="0">
          </div>
        </div>
        <div class="photo-cropper-actions">
          <button type="button" class="photo-cropper-btn photo-cropper-btn--ghost" data-action="cancel">Cancelar</button>
          <button type="button" class="photo-cropper-btn photo-cropper-btn--primary" data-action="apply">Aplicar foto</button>
        </div>
      </div>
    `;

    document.body.appendChild(overlay);

    return {
      overlay,
      canvas: overlay.querySelector('canvas'),
      zoom: overlay.querySelector('#photoCropperZoom'),
      x: overlay.querySelector('#photoCropperX'),
      y: overlay.querySelector('#photoCropperY'),
      zoomValue: overlay.querySelector('[data-value="zoom"]'),
      xValue: overlay.querySelector('[data-value="x"]'),
      yValue: overlay.querySelector('[data-value="y"]'),
      close: overlay.querySelector('.photo-cropper-close'),
      cancel: overlay.querySelector('[data-action="cancel"]'),
      apply: overlay.querySelector('[data-action="apply"]')
    };
  }

  function drawCrop(canvas, image, state) {
    const context = canvas.getContext('2d');
    const baseScale = Math.max(OUTPUT_SIZE / image.naturalWidth, OUTPUT_SIZE / image.naturalHeight);
    const scale = baseScale * state.zoom;
    const drawWidth = image.naturalWidth * scale;
    const drawHeight = image.naturalHeight * scale;
    const maxX = Math.max(0, (drawWidth - OUTPUT_SIZE) / 2);
    const maxY = Math.max(0, (drawHeight - OUTPUT_SIZE) / 2);
    const dx = (OUTPUT_SIZE - drawWidth) / 2 + (state.x / 100) * maxX;
    const dy = (OUTPUT_SIZE - drawHeight) / 2 + (state.y / 100) * maxY;

    context.clearRect(0, 0, OUTPUT_SIZE, OUTPUT_SIZE);
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, OUTPUT_SIZE, OUTPUT_SIZE);
    context.drawImage(image, dx, dy, drawWidth, drawHeight);
  }

  function canvasToBlob(canvas) {
    return new Promise((resolve) => {
      canvas.toBlob(resolve, 'image/jpeg', 0.9);
    });
  }

  function setFileOnInput(input, file) {
    if (typeof DataTransfer === 'undefined') return false;

    const transfer = new DataTransfer();
    transfer.items.add(file);
    input.files = transfer.files;
    return true;
  }

  function showPlaceholder(img, placeholder) {
    img.style.display = 'none';
    img.removeAttribute('src');
    if (placeholder) placeholder.style.display = '';
  }

  function showImage(img, placeholder, src) {
    img.src = src;
    img.style.display = '';
    if (placeholder) placeholder.style.display = 'none';
  }

  async function openCropper(file) {
    const image = await loadImage(file);
    const modal = createModal();
    const state = { zoom: 1, x: 0, y: 0 };

    function redraw() {
      state.zoom = Number(modal.zoom.value);
      state.x = Number(modal.x.value);
      state.y = Number(modal.y.value);
      modal.zoomValue.textContent = Math.round(state.zoom * 100) + '%';
      modal.xValue.textContent = String(state.x);
      modal.yValue.textContent = String(state.y);
      drawCrop(modal.canvas, image, state);
    }

    redraw();
    [modal.zoom, modal.x, modal.y].forEach((control) => {
      control.addEventListener('input', redraw);
    });

    return new Promise((resolve) => {
      function close(result) {
        modal.overlay.remove();
        resolve(result);
      }

      modal.close.addEventListener('click', () => close(null));
      modal.cancel.addEventListener('click', () => close(null));
      modal.overlay.addEventListener('click', (event) => {
        if (event.target === modal.overlay) close(null);
      });

      document.addEventListener('keydown', function onKeydown(event) {
        if (event.key !== 'Escape') return;
        document.removeEventListener('keydown', onKeydown);
        close(null);
      });

      modal.apply.addEventListener('click', async () => {
        modal.apply.disabled = true;
        modal.apply.textContent = 'Aplicando...';

        const blob = await canvasToBlob(modal.canvas);
        if (!blob) {
          close(null);
          return;
        }

        const fileName = sanitizeName(file.name) + '-perfil.jpg';
        close(new File([blob], fileName, { type: 'image/jpeg' }));
      });
    });
  }

  function bind(config) {
    const input = document.getElementById(config.inputId);
    const nameEl = document.getElementById(config.nameId);
    const img = document.getElementById(config.imgId);
    const placeholder = document.getElementById(config.placeholderId);
    const removeCb = config.removeSelector ? document.querySelector(config.removeSelector) : null;

    if (!input || !img) return;

    input.addEventListener('change', async function () {
      const originalSrc = img.getAttribute('src');
      const originalImgDisplay = img.style.display;
      const originalPlaceholderDisplay = placeholder ? placeholder.style.display : '';
      const file = input.files && input.files[0] ? input.files[0] : null;

      if (nameEl) {
        nameEl.textContent = file ? file.name : 'Nenhum arquivo selecionado';
      }

      if (!file) return;

      if (!file.type || !file.type.startsWith('image/')) {
        return;
      }

      if (removeCb) removeCb.checked = false;

      try {
        const croppedFile = await openCropper(file);

        if (!croppedFile) {
          input.value = '';
          if (nameEl) nameEl.textContent = 'Nenhum arquivo selecionado';

          if (originalSrc) {
            img.src = originalSrc;
            img.style.display = originalImgDisplay;
            if (placeholder) placeholder.style.display = originalPlaceholderDisplay;
          } else {
            showPlaceholder(img, placeholder);
          }
          return;
        }

        const changed = setFileOnInput(input, croppedFile);
        if (nameEl) nameEl.textContent = croppedFile.name;

        const previewUrl = URL.createObjectURL(croppedFile);
        showImage(img, placeholder, previewUrl);
        img.onload = function () {
          URL.revokeObjectURL(previewUrl);
        };

        if (!changed) {
          console.warn('Navegador nao permitiu substituir o arquivo pelo recorte.');
        }
      } catch (error) {
        console.warn(error);
        const fallbackUrl = URL.createObjectURL(file);
        showImage(img, placeholder, fallbackUrl);
        img.onload = function () {
          URL.revokeObjectURL(fallbackUrl);
        };
      }
    });

    if (removeCb) {
      removeCb.addEventListener('change', function () {
        if (!removeCb.checked) return;

        input.value = '';
        if (nameEl) nameEl.textContent = 'Nenhum arquivo selecionado';
        showPlaceholder(img, placeholder);
      });
    }
  }

  window.PopperPhotoCropper = { bind };
})();
