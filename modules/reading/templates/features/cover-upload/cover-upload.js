(function () {
  // Helpers
  const el = (t, cls) => {
    const e = document.createElement(t);
    if (cls) e.className = cls;
    return e;
  };

  function getContext() {
    const g = (window.PRS_BOOK || {});
    return {
      user_book_id: g.user_book_id || 0,
      book_id: g.book_id || 0
    };
  }

  function getBookDetails() {
    const g = (window.PRS_BOOK || {});
    return {
      title: g.title || '',
      author: g.author || '',
      language: g.language || ''
    };
  }

  function normalizeLanguageCode(code) {
    if (!code || typeof code !== 'string') return '';
    let normalized = code.trim().toLowerCase();
    if (!normalized) return '';
    normalized = normalized.replace(/^\/?languages\//, '');
    normalized = normalized.replace(/^\/?lang\//, '');
    normalized = normalized.replace(/_/g, '-');

    if (normalized.length === 2) {
      return normalized;
    }

    const map = {
      eng: 'en',
      spa: 'es',
      esl: 'es',
      fre: 'fr',
      fra: 'fr',
      por: 'pt',
      ger: 'de',
      deu: 'de',
      ita: 'it',
      cat: 'ca',
      glg: 'gl',
    };

    if (map[normalized]) {
      return map[normalized];
    }

    if (normalized.length > 2) {
      return normalized.slice(0, 2);
    }

    return normalized;
  }

  function resolveBookLanguage(details) {
    const metaCode = normalizeLanguageCode(details.language);
    if (metaCode) return metaCode;
    return '';
  }

  // ====== Upload & crop modal ======
  const MIN_CROP_SIZE = 40;

  let modal, stage, imgEl, saveBtn, cancelBtn, fileInput, statusEl, placeholderEl, cropArea;
  let handles = [];
  let naturalW = 0;
  let naturalH = 0;
  let imageBounds = null;
  let isDraggingCrop = false;
  let isResizingCrop = false;
  let activeHandle = null;
  let startX = 0;
  let startY = 0;
  let startLeft = 0;
  let startTop = 0;
  let startWidth = 0;
  let startHeight = 0;
  let lastLoadedFileType = '';

  function openModal() {
    if (modal) {
      modal.remove();
      modal = null;
    }

    modal = el('div', 'prs-cover-modal');

    let panel = null;
    const template = document.getElementById('prs-cover-modal-template');
    if (template && 'content' in template) {
      const fragment = template.content.cloneNode(true);
      const found = fragment.querySelector('.prs-cover-modal__content');
      if (found) {
        panel = found;
        modal.appendChild(panel);
      }
    }

    if (!panel) {
      panel = el('div', 'prs-cover-modal__content');
      panel.innerHTML = `
        <div class="prs-cover-modal__title">Upload Book Cover</div>

        <div class="prs-cover-modal__grid">
          <div class="prs-crop-wrap" id="drag-drop-area">
            <div id="cropStage" class="prs-crop-stage" title="Drop JPEG or PNG file here">
              <div id="cropPlaceholder" class="prs-crop-placeholder">
                <svg class="prs-upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M4 14.9V8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3.1" />
                  <path d="M16 16l-4-4-4 4" />
                  <path d="M12 12v9" />
                </svg>
                <p>Drag JPEG or PNG here (220x350 Preview)</p>
                <span>or click upload</span>
              </div>
              <img id="previewImage" src="" alt="Book Cover Preview" style="display:none;">
              <div id="cropArea" class="prs-crop-area" style="display:none;">
                <div class="resize-handle corner nw"></div>
                <div class="resize-handle corner ne"></div>
                <div class="resize-handle corner sw"></div>
                <div class="resize-handle corner se"></div>
                <div class="resize-handle side n"></div>
                <div class="resize-handle side s"></div>
                <div class="resize-handle side e"></div>
                <div class="resize-handle side w"></div>
              </div>
            </div>
          </div>

          <div class="prs-cover-controls" id="upload-settings-setting">

            <div class="prs-file-input">
              <input type="file" id="fileInput" accept="image/jpeg, image/png" class="prs-hidden-input">
              <label for="fileInput" class="prs-btn prs-btn--ghost">Choose File</label>
            </div>

            <div class="prs-crop-controls">
              <p class="prs-crop-instructions">Drag or resize the selection on the image to crop.</p>
            </div>

            <span id="statusMessage" class="prs-cover-status">Awaiting file upload.</span>

            <div class="prs-btn-group">
              <button class="prs-btn prs-btn--ghost" type="button" id="prs-cover-cancel">Cancel</button>
              <button class="prs-btn" type="button" id="prs-cover-save">Save</button>
            </div>
          </div>
        </div>
      `;
      modal.appendChild(panel);
    }

    stage = panel.querySelector('#cropStage');
    placeholderEl = panel.querySelector('#cropPlaceholder');
    fileInput = panel.querySelector('#fileInput');
    statusEl = panel.querySelector('#statusMessage');
    cancelBtn = panel.querySelector('#prs-cover-cancel');
    saveBtn = panel.querySelector('#prs-cover-save');
    imgEl = panel.querySelector('#previewImage');
    cropArea = panel.querySelector('#cropArea');
    handles = cropArea ? Array.from(cropArea.querySelectorAll('.resize-handle')) : [];
    hideCropOverlay();
    setupCropEvents();
    document.body.appendChild(modal);

    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeModal();
    });
    cancelBtn.addEventListener('click', closeModal);
    saveBtn.addEventListener('click', onSaveCrop);

    if (statusEl) {
      setStatus(statusEl.textContent || 'Awaiting file upload.');
    }

    document.dispatchEvent(new CustomEvent('prsCoverModal:ready', {
      detail: {
        stage,
        placeholder: placeholderEl,
        status: statusEl,
        fileInput,
        previewImage: imgEl,
        cropArea,
        onFiles: handleFiles,
      }
    }));
  }

  function closeModal() {
    if (modal) modal.remove();
    modal = null;
    stage = null;
    imgEl = null;
    saveBtn = null;
    cancelBtn = null;
    fileInput = null;
    statusEl = null;
    placeholderEl = null;
    cropArea = null;
    handles = [];
    naturalW = 0;
    naturalH = 0;
    imageBounds = null;
    isDraggingCrop = false;
    isResizingCrop = false;
    activeHandle = null;
    lastLoadedFileType = '';
  }

  function resetStatusClasses() {
    if (!statusEl) return;
    statusEl.className = 'prs-cover-status';
    statusEl.style.color = '#6b7280';
  }

  function setStatus(txt, color) {
    if (!statusEl) return;
    resetStatusClasses();
    if (color) {
      statusEl.style.color = color;
    }
    statusEl.textContent = txt || '';
  }

  function hideCropOverlay() {
    if (cropArea) {
      cropArea.style.display = 'none';
    }
    if (placeholderEl) {
      placeholderEl.style.opacity = '1';
      placeholderEl.style.pointerEvents = 'auto';
    }
    onCropDragEnd();
    onCropResizeEnd();
  }

  function showCropOverlay() {
    if (placeholderEl) {
      placeholderEl.style.opacity = '0';
      placeholderEl.style.pointerEvents = 'none';
    }
    if (cropArea) {
      cropArea.style.display = 'block';
    }
  }

  function updateImageBounds() {
    if (!stage || !imgEl) {
      imageBounds = null;
      return;
    }

    const stageRect = stage.getBoundingClientRect();
    const imageRect = imgEl.getBoundingClientRect();

    if (!imageRect.width || !imageRect.height) {
      imageBounds = null;
      return;
    }

    const visibleLeft = Math.max(stageRect.left, imageRect.left);
    const visibleTop = Math.max(stageRect.top, imageRect.top);
    const visibleRight = Math.min(stageRect.right, imageRect.right);
    const visibleBottom = Math.min(stageRect.bottom, imageRect.bottom);

    if (visibleRight <= visibleLeft || visibleBottom <= visibleTop) {
      imageBounds = null;
      return;
    }

    imageBounds = {
      left: visibleLeft - stageRect.left,
      top: visibleTop - stageRect.top,
      right: visibleRight - stageRect.left,
      bottom: visibleBottom - stageRect.top,
      width: visibleRight - visibleLeft,
      height: visibleBottom - visibleTop,
    };
  }

  function initializeCropArea() {
    if (!cropArea || !imgEl) {
      hideCropOverlay();
      return;
    }

    updateImageBounds();

    if (!imageBounds) {
      hideCropOverlay();
      return;
    }

    const maxWidth = imageBounds.width;
    const maxHeight = imageBounds.height;
    const width = Math.min(maxWidth, Math.max(MIN_CROP_SIZE, maxWidth * 0.8));
    const height = Math.min(maxHeight, Math.max(MIN_CROP_SIZE, maxHeight * 0.8));
    const left = imageBounds.left + (imageBounds.width - width) / 2;
    const top = imageBounds.top + (imageBounds.height - height) / 2;

    cropArea.style.left = `${left}px`;
    cropArea.style.top = `${top}px`;
    cropArea.style.width = `${width}px`;
    cropArea.style.height = `${height}px`;

    showCropOverlay();
    isDraggingCrop = false;
    isResizingCrop = false;
    activeHandle = null;
  }

  function setupCropEvents() {
    if (!cropArea) {
      return;
    }

    cropArea.addEventListener('mousedown', onCropDragStart);
    cropArea.addEventListener('touchstart', onCropDragStart, { passive: false });

    handles.forEach((handle) => {
      handle.addEventListener('mousedown', onCropResizeStart);
      handle.addEventListener('touchstart', onCropResizeStart, { passive: false });
    });
  }

  function getPointerPosition(event) {
    if (event.touches && event.touches[0]) {
      return {
        clientX: event.touches[0].clientX,
        clientY: event.touches[0].clientY,
      };
    }
    return {
      clientX: event.clientX,
      clientY: event.clientY,
    };
  }

  function onCropDragStart(event) {
    if (!cropArea || !stage) {
      return;
    }
    if (event.target && event.target.classList.contains('resize-handle')) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    updateImageBounds();
    if (!imageBounds) {
      return;
    }

    const point = getPointerPosition(event);
    startX = point.clientX;
    startY = point.clientY;
    startLeft = cropArea.offsetLeft;
    startTop = cropArea.offsetTop;
    isDraggingCrop = true;

    document.addEventListener('mousemove', onCropDragMove);
    document.addEventListener('mouseup', onCropDragEnd);
    document.addEventListener('touchmove', onCropDragMove, { passive: false });
    document.addEventListener('touchend', onCropDragEnd);
  }

  function onCropDragMove(event) {
    if (!isDraggingCrop || !cropArea || !imageBounds) {
      return;
    }

    event.preventDefault();

    const point = getPointerPosition(event);
    const dx = point.clientX - startX;
    const dy = point.clientY - startY;

    let newLeft = startLeft + dx;
    let newTop = startTop + dy;

    const maxLeft = imageBounds.right - cropArea.offsetWidth;
    const maxTop = imageBounds.bottom - cropArea.offsetHeight;

    newLeft = Math.min(Math.max(newLeft, imageBounds.left), maxLeft);
    newTop = Math.min(Math.max(newTop, imageBounds.top), maxTop);

    cropArea.style.left = `${newLeft}px`;
    cropArea.style.top = `${newTop}px`;
  }

  function onCropDragEnd() {
    document.removeEventListener('mousemove', onCropDragMove);
    document.removeEventListener('mouseup', onCropDragEnd);
    document.removeEventListener('touchmove', onCropDragMove);
    document.removeEventListener('touchend', onCropDragEnd);
    isDraggingCrop = false;
  }

  function onCropResizeStart(event) {
    if (!cropArea) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    updateImageBounds();
    if (!imageBounds) {
      return;
    }

    const point = getPointerPosition(event);
    startX = point.clientX;
    startY = point.clientY;
    startLeft = cropArea.offsetLeft;
    startTop = cropArea.offsetTop;
    startWidth = cropArea.offsetWidth;
    startHeight = cropArea.offsetHeight;
    activeHandle = event.target;
    isResizingCrop = true;

    document.addEventListener('mousemove', onCropResizeMove);
    document.addEventListener('mouseup', onCropResizeEnd);
    document.addEventListener('touchmove', onCropResizeMove, { passive: false });
    document.addEventListener('touchend', onCropResizeEnd);
  }

  function onCropResizeMove(event) {
    if (!isResizingCrop || !cropArea || !activeHandle || !imageBounds) {
      return;
    }

    event.preventDefault();

    const point = getPointerPosition(event);
    const dx = point.clientX - startX;
    const dy = point.clientY - startY;

    let left = startLeft;
    let top = startTop;
    let right = startLeft + startWidth;
    let bottom = startTop + startHeight;

    const classList = activeHandle.classList;
    const resizeNorth = classList.contains('n') || classList.contains('nw') || classList.contains('ne');
    const resizeSouth = classList.contains('s') || classList.contains('sw') || classList.contains('se');
    const resizeWest = classList.contains('w') || classList.contains('nw') || classList.contains('sw');
    const resizeEast = classList.contains('e') || classList.contains('ne') || classList.contains('se');

    if (resizeWest) {
      left = startLeft + dx;
    }
    if (resizeEast) {
      right = startLeft + startWidth + dx;
    }
    if (resizeNorth) {
      top = startTop + dy;
    }
    if (resizeSouth) {
      bottom = startTop + startHeight + dy;
    }

    const minWidth = Math.min(MIN_CROP_SIZE, imageBounds.width);
    const minHeight = Math.min(MIN_CROP_SIZE, imageBounds.height);

    if (left < imageBounds.left) {
      left = imageBounds.left;
    }
    if (right > imageBounds.right) {
      right = imageBounds.right;
    }
    if (top < imageBounds.top) {
      top = imageBounds.top;
    }
    if (bottom > imageBounds.bottom) {
      bottom = imageBounds.bottom;
    }

    if (right - left < minWidth) {
      if (resizeWest && !resizeEast) {
        left = right - minWidth;
      } else if (resizeEast && !resizeWest) {
        right = left + minWidth;
      } else {
        const centerX = (left + right) / 2;
        left = centerX - minWidth / 2;
        right = centerX + minWidth / 2;
      }
      if (left < imageBounds.left) {
        left = imageBounds.left;
        right = left + minWidth;
      }
      if (right > imageBounds.right) {
        right = imageBounds.right;
        left = right - minWidth;
      }
    }

    if (bottom - top < minHeight) {
      if (resizeNorth && !resizeSouth) {
        top = bottom - minHeight;
      } else if (resizeSouth && !resizeNorth) {
        bottom = top + minHeight;
      } else {
        const centerY = (top + bottom) / 2;
        top = centerY - minHeight / 2;
        bottom = centerY + minHeight / 2;
      }
      if (top < imageBounds.top) {
        top = imageBounds.top;
        bottom = top + minHeight;
      }
      if (bottom > imageBounds.bottom) {
        bottom = imageBounds.bottom;
        top = bottom - minHeight;
      }
    }

    let newWidth = right - left;
    let newHeight = bottom - top;

    newWidth = Math.max(minWidth, Math.min(newWidth, imageBounds.width));
    newHeight = Math.max(minHeight, Math.min(newHeight, imageBounds.height));

    const maxLeft = imageBounds.right - newWidth;
    const maxTop = imageBounds.bottom - newHeight;

    left = Math.min(Math.max(left, imageBounds.left), maxLeft);
    top = Math.min(Math.max(top, imageBounds.top), maxTop);

    cropArea.style.left = `${left}px`;
    cropArea.style.top = `${top}px`;
    cropArea.style.width = `${newWidth}px`;
    cropArea.style.height = `${newHeight}px`;
  }

  function onCropResizeEnd() {
    document.removeEventListener('mousemove', onCropResizeMove);
    document.removeEventListener('mouseup', onCropResizeEnd);
    document.removeEventListener('touchmove', onCropResizeMove);
    document.removeEventListener('touchend', onCropResizeEnd);
    isResizingCrop = false;
    activeHandle = null;
  }

  function getCropSourceRect() {
    if (!imgEl || !cropArea) {
      return null;
    }

    const imageRect = imgEl.getBoundingClientRect();
    const cropRect = cropArea.getBoundingClientRect();

    if (!imageRect.width || !imageRect.height) {
      return null;
    }

    const scaleX = naturalW / imageRect.width;
    const scaleY = naturalH / imageRect.height;

    let sx = (cropRect.left - imageRect.left) * scaleX;
    let sy = (cropRect.top - imageRect.top) * scaleY;
    let sw = cropRect.width * scaleX;
    let sh = cropRect.height * scaleY;

    sx = Math.max(0, Math.min(sx, naturalW));
    sy = Math.max(0, Math.min(sy, naturalH));

    const maxWidth = naturalW - sx;
    const maxHeight = naturalH - sy;

    sw = Math.max(1, Math.min(sw, maxWidth));
    sh = Math.max(1, Math.min(sh, maxHeight));

    let roundedSx = Math.max(0, Math.min(Math.round(sx), Math.max(0, naturalW - 1)));
    let roundedSy = Math.max(0, Math.min(Math.round(sy), Math.max(0, naturalH - 1)));
    let roundedSw = Math.max(1, Math.round(sw));
    let roundedSh = Math.max(1, Math.round(sh));

    const maxRoundedWidth = Math.max(1, naturalW - roundedSx);
    const maxRoundedHeight = Math.max(1, naturalH - roundedSy);

    roundedSw = Math.max(1, Math.min(roundedSw, maxRoundedWidth));
    roundedSh = Math.max(1, Math.min(roundedSh, maxRoundedHeight));

    if (roundedSw <= 0 || roundedSh <= 0) {
      return null;
    }

    return {
      sx: roundedSx,
      sy: roundedSy,
      sw: roundedSw,
      sh: roundedSh,
    };
  }

  function handleFiles(fileList) {
    if (!fileList || !fileList.length) {
      return;
    }

    const file = fileList[0];
    const allowed = ['image/jpeg', 'image/png'];

    if (stage) {
      stage.classList.remove('error');
      stage.classList.remove('drag-active');
    }

    resetStatusClasses();

    if (!allowed.includes(file.type)) {
      if (stage) {
        stage.classList.add('error');
      }
      setStatus('Error: Only JPEG and PNG images are accepted.', '#ef4444');
      if (imgEl) {
        imgEl.style.display = 'none';
        imgEl.removeAttribute('src');
        imgEl.style.transform = 'translate(-50%, -50%) scale(1)';
      }
      hideCropOverlay();
      naturalW = 0;
      naturalH = 0;
      imageBounds = null;
      isDraggingCrop = false;
      isResizingCrop = false;
      activeHandle = null;
      lastLoadedFileType = '';
      return;
    }

    lastLoadedFileType = file.type || '';
    const reader = new FileReader();
    reader.onload = (event) => {
      if (!event.target) return;
      const dataUrl = event.target.result;
      loadIntoStage(dataUrl, () => {
        const sizeKb = (file.size / 1024).toFixed(1);
        setStatus(`File loaded: ${file.name} (${sizeKb} KB)`, '#16a34a');
      });
    };
    reader.readAsDataURL(file);
  }

  function loadIntoStage(dataUrl, onReady) {
    if (!imgEl) return;

    imgEl.onload = () => {
      naturalW = imgEl.naturalWidth;
      naturalH = imgEl.naturalHeight;
      if (placeholderEl) {
        placeholderEl.style.opacity = '0';
        placeholderEl.style.pointerEvents = 'none';
      }

      imgEl.style.display = 'block';
      imgEl.style.transform = 'translate(-50%, -50%) scale(1)';

      requestAnimationFrame(() => {
        initializeCropArea();
        if (typeof onReady === 'function') {
          onReady();
        }
      });
    };
    imgEl.src = dataUrl;
  }

  function updateCoverAttribution(source) {
    const wrap = document.getElementById('prs-cover-attribution-wrap');
    const link = document.getElementById('prs-cover-attribution');
    if (!wrap || !link) {
      return;
    }

    if (source) {
      link.href = source;
      wrap.classList.remove('is-hidden');
      link.classList.remove('is-hidden');
      wrap.setAttribute('aria-hidden', 'false');
      link.setAttribute('aria-hidden', 'false');
    } else {
      wrap.classList.add('is-hidden');
      link.classList.add('is-hidden');
      wrap.setAttribute('aria-hidden', 'true');
      link.setAttribute('aria-hidden', 'true');
      link.removeAttribute('href');
    }
  }

  function replaceCover(src, bustCache, source) {
    if (!src) return;
    const frame = document.getElementById('prs-cover-frame');
    if (!frame) return;

    const placeholder = document.getElementById('prs-cover-placeholder');
    const figure = document.getElementById('prs-book-cover-figure');
    let transferredActions = null;
    if (placeholder) {
      transferredActions = placeholder.querySelector('.prs-cover-actions');
    }
    if (placeholder && placeholder.parentNode) {
      placeholder.parentNode.removeChild(placeholder);
    }

    let img = document.getElementById('prs-cover-img');
    if (!img) {
      img = document.createElement('img');
      img.id = 'prs-cover-img';
      img.className = 'prs-cover-img';
      if (figure) {
        figure.appendChild(img);
      } else {
        frame.appendChild(img);
      }
    } else if (figure && !figure.contains(img)) {
      figure.appendChild(img);
    }

    let finalSrc = src;
    if (bustCache) {
      finalSrc = `${src}${src.indexOf('?') >= 0 ? '&' : '?'}t=${Date.now()}`;
    }
    img.src = finalSrc;
    const { title } = getBookDetails();
    if (title) img.alt = title;

    let overlay = frame.querySelector('.prs-cover-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = 'prs-cover-overlay';
      if (figure && figure.nextSibling) {
        frame.insertBefore(overlay, figure.nextSibling);
      } else {
        frame.appendChild(overlay);
      }
    }

    if (transferredActions) {
      const existingActions = overlay.querySelector('.prs-cover-actions');
      if (existingActions && existingActions !== transferredActions && existingActions.parentNode) {
        existingActions.parentNode.removeChild(existingActions);
      }
      overlay.appendChild(transferredActions);
    }

    frame.classList.add('has-image');
    frame.setAttribute('data-cover-state', 'image');
    if (typeof source === 'string') {
      updateCoverAttribution(source);
    }
    if (window.PRS_BOOK) {
      window.PRS_BOOK.cover_url = src;
      window.PRS_BOOK.cover_source = typeof source === 'string' ? source : '';
    }
  }

  function getUploadConfig() {
    const coverConfig = window.PRS_COVER || {};
    const ajaxUrl = window.PRS_SAVE_URL
      || coverConfig.saveUrl
      || coverConfig.ajax
      || (window.prs_cover_data && window.prs_cover_data.ajaxurl)
      || window.ajaxurl
      || '';
    const globalNonce = (typeof window.PRS_NONCE === 'string' && window.PRS_NONCE) || '';
    const cropNonce = (typeof window.PRS_COVER_CROP_NONCE === 'string' && window.PRS_COVER_CROP_NONCE)
      || coverConfig.cropNonce
      || coverConfig.nonce
      || (window.prs_cover_data && window.prs_cover_data.nonce)
      || coverConfig.saveNonce
      || globalNonce
      || '';
    const postId = window.PRS_POST_ID
      || coverConfig.postId
      || 0;

    return { ajaxUrl, nonce: cropNonce, postId };
  }

  async function uploadCroppedCover({ dataURL, mime, postId }) {
    const { ajaxUrl, nonce, postId: fallbackPostId } = getUploadConfig();
    const targetPostId = typeof postId !== 'undefined' ? postId : fallbackPostId;

    if (!ajaxUrl) {
      throw new Error('Upload unavailable');
    }
    if (!nonce) {
      throw new Error('Missing nonce');
    }
    if (!dataURL) {
      throw new Error('Missing image data');
    }

    const payload = new URLSearchParams({
      action: 'prs_cover_save_crop',
      _wpnonce: nonce,
      nonce,
      data: dataURL,
      image: dataURL,
      mime: mime || 'image/png',
    });

    const numericPostId = parseInt(targetPostId, 10);
    if (!Number.isNaN(numericPostId)) {
      payload.append('post_id', String(numericPostId));
    }

    const { user_book_id, book_id } = getContext();
    if (user_book_id) {
      payload.append('user_book_id', String(user_book_id));
    }
    if (book_id) {
      payload.append('book_id', String(book_id));
    }

    if (window.console && console.log) {
      console.log('Posting cover upload to:', ajaxUrl);
      console.log('Action:', payload.get('action'));
      console.log('Nonce:', payload.get('_wpnonce') || payload.get('nonce'));
      console.log('User Book ID:', payload.get('user_book_id'));
      console.log('Book ID:', payload.get('book_id'));
    }

    const response = await fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: payload,
    });

    const json = await response.json().catch(() => null);

    if (!response.ok || !json) {
      throw new Error('Upload failed');
    }

    if (!json.success) {
      throw new Error(json?.data?.message || json?.message || 'Upload failed');
    }

    return json.data || {};
  }

  function onSaveCrop() {
    if (!imgEl || naturalW <= 0 || naturalH <= 0) {
      setStatus('Choose an image', '#ef4444');
      return;
    }

    const cropRect = getCropSourceRect();
    if (!cropRect) {
      setStatus('Adjust the crop area before saving.', '#ef4444');
      return;
    }

    const canvas = document.createElement('canvas');
    canvas.width = cropRect.sw;
    canvas.height = cropRect.sh;

    const ctx = canvas.getContext('2d');
    if (!ctx) {
      setStatus('Render error', '#ef4444');
      return;
    }

    const sourceImage = new Image();
    sourceImage.onload = async () => {
      ctx.drawImage(
        sourceImage,
        cropRect.sx,
        cropRect.sy,
        cropRect.sw,
        cropRect.sh,
        0,
        0,
        cropRect.sw,
        cropRect.sh
      );

      const preferredMime = (lastLoadedFileType === 'image/jpeg' || lastLoadedFileType === 'image/png')
        ? lastLoadedFileType
        : 'image/png';

      let dataURL;
      try {
        if (preferredMime === 'image/jpeg') {
          dataURL = canvas.toDataURL(preferredMime, 0.92);
        } else {
          dataURL = canvas.toDataURL(preferredMime);
        }
      } catch (encodingError) {
        console.error('[PRS] cover encode error', encodingError);
        setStatus('Render error', '#ef4444');
        return;
      }

      setStatus('Saving…');

      try {
        const payload = await uploadCroppedCover({
          dataURL,
          mime: preferredMime,
        });

        const coverUrl = (payload && (payload.url || payload.src)) || '';
        if (!coverUrl) {
          setStatus('Error', '#ef4444');
          return;
        }

        setStatus('Saved', '#16a34a');
        replaceCover(coverUrl, true, '');
        closeModal();
      } catch (error) {
        const message = error?.message || 'Error';
        setStatus(message, '#ef4444');
        console.error('[PRS] cover save error', error);
      }
    };

    sourceImage.onerror = () => {
      setStatus('Render error', '#ef4444');
    };

    sourceImage.src = imgEl.src;
  }

  // ====== Search modal ======
  let searchModal;
  let searchMessageEl;
  let searchGridEl;
  let searchSetBtn;
  let selectedSearchOption = null;
  let searchPrevFocus = null;
  let searchKeydownListener = null;

  function getAjaxConfig() {
    const config = window.prs_cover_data || {};
    const ajaxUrl = config.ajaxurl || window.ajaxurl || (window.PRS_COVER && PRS_COVER.ajax) || '';
    const nonce = config.nonce || '';
    return { ajaxUrl, nonce };
  }

  function getSearchFocusableElements() {
    if (!searchModal) return [];
    const selectors = [
      'button',
      '[href]',
      'input',
      'select',
      'textarea',
      '[tabindex]:not([tabindex="-1"])'
    ];
    return Array.from(searchModal.querySelectorAll(selectors.join(','))).filter((node) => {
      if (!(node instanceof HTMLElement)) return false;
      if (node.hasAttribute('disabled')) return false;
      if (node.getAttribute('aria-hidden') === 'true') return false;
      return true;
    });
  }

  function setSearchLoadingState(isLoading) {
    if (isLoading) {
      document.body.classList.add('prs-cover-search--loading');
    } else {
      document.body.classList.remove('prs-cover-search--loading');
    }
  }

  function openSearchModal() {
    closeSearchModal();

    searchPrevFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;

    searchModal = el('div', 'prs-cover-search-modal');
    const panel = el('div', 'prs-cover-search-modal__content');
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-modal', 'true');
    panel.setAttribute('aria-labelledby', 'prs-cover-search-modal-title');
    panel.setAttribute('tabindex', '-1');

    const title = el('h2', 'prs-cover-search-modal__title');
    title.id = 'prs-cover-search-modal-title';
    title.textContent = 'Select a Cover';

    searchMessageEl = el('p', 'prs-cover-search-modal__message');
    searchMessageEl.textContent = '';

    searchGridEl = el('div', 'prs-cover-search-modal__grid prs-cover-grid');
    searchGridEl.id = 'prs-cover-options';

    const footer = el('div', 'prs-cover-search-modal__footer');
    const cancel = el('button', 'prs-btn prs-btn--ghost');
    cancel.type = 'button';
    cancel.textContent = 'Cancel';
    searchSetBtn = el('button', 'prs-btn prs-cover-set');
    searchSetBtn.type = 'button';
    searchSetBtn.textContent = 'Set Cover';
    searchSetBtn.disabled = true;
    const { book_id } = getContext();
    if (book_id) {
      searchSetBtn.dataset.bookId = book_id;
    } else if (searchSetBtn.dataset) {
      delete searchSetBtn.dataset.bookId;
    }
    footer.append(cancel, searchSetBtn);

    panel.append(title, searchMessageEl, searchGridEl, footer);
    searchModal.appendChild(panel);
    document.body.appendChild(searchModal);

    selectedSearchOption = null;

    searchModal.addEventListener('click', (e) => {
      if (e.target === searchModal) closeSearchModal();
    });
    cancel.addEventListener('click', closeSearchModal);
    searchSetBtn.addEventListener('click', onSearchSetCover);

    panel.focus();

    searchKeydownListener = (event) => {
      if (!searchModal) return;
      if (event.key === 'Escape') {
        event.preventDefault();
        closeSearchModal();
        return;
      }
      if (event.key === 'Tab') {
        const focusable = getSearchFocusableElements();
        if (!focusable.length) {
          event.preventDefault();
          return;
        }
        const currentIndex = focusable.indexOf(document.activeElement);
        let nextIndex = currentIndex;
        if (event.shiftKey) {
          nextIndex = currentIndex <= 0 ? focusable.length - 1 : currentIndex - 1;
        } else {
          nextIndex = currentIndex === focusable.length - 1 ? 0 : currentIndex + 1;
        }
        event.preventDefault();
        focusable[nextIndex].focus();
      }
    };

    document.addEventListener('keydown', searchKeydownListener, true);
  }

  function closeSearchModal() {
    if (searchModal) {
      searchModal.remove();
    }
    searchModal = null;
    searchMessageEl = null;
    searchGridEl = null;
    searchSetBtn = null;
    selectedSearchOption = null;
    setSearchLoadingState(false);
    if (searchKeydownListener) {
      document.removeEventListener('keydown', searchKeydownListener, true);
    }
    searchKeydownListener = null;
    if (searchPrevFocus && typeof searchPrevFocus.focus === 'function') {
      searchPrevFocus.focus();
    }
    searchPrevFocus = null;
  }

  function setSearchMessage(message) {
    if (searchMessageEl) {
      searchMessageEl.textContent = message || '';
    }
  }

  function renderSearchResults(items, preferredLanguage) {
    if (!searchGridEl) return;
    searchGridEl.innerHTML = '';
    selectedSearchOption = null;
    if (searchSetBtn) {
      searchSetBtn.disabled = true;
      searchSetBtn.textContent = 'Set Cover';
    }

    if (!items.length) {
      const empty = el('div', 'prs-cover-search-modal__empty');
      empty.textContent = 'No covers found. You can upload your own image instead.';
      searchGridEl.appendChild(empty);
      return;
    }

    const rendered = [];
    items.forEach((item) => {
      if (!item || !item.url) return;
      const option = el('div', 'prs-cover-option');
      option.dataset.coverUrl = item.url;
      if (item.source) option.dataset.sourceLink = item.source;
      if (item.language) option.dataset.language = item.language;
      option.setAttribute('role', 'button');
      option.tabIndex = 0;
      option.setAttribute('aria-pressed', 'false');

      const frame = el('div', 'prs-cover-frame');
      const img = el('img');
      img.src = item.url;
      img.alt = item.title ? `Cover for ${item.title}` : 'Book cover option';
      img.loading = 'lazy';
      frame.appendChild(img);

      if (item.language) {
        const badge = el('span', 'prs-cover-search-modal__lang');
        badge.textContent = item.language.toUpperCase();
        if (preferredLanguage && item.language.toLowerCase() !== preferredLanguage.toLowerCase()) {
          badge.classList.add('is-mismatch');
        }
        frame.appendChild(badge);
      }

      option.appendChild(frame);

      if (item.title || item.author) {
        const meta = el('div', 'prs-cover-meta');
        if (item.title) {
          const title = el('span', 'prs-cover-title');
          title.textContent = item.title;
          meta.appendChild(title);
        }
        if (item.author) {
          const author = el('span', 'prs-cover-author');
          author.textContent = item.author;
          meta.appendChild(author);
        }
        option.appendChild(meta);
      }

      const attribution = el('a', 'prs-cover-attribution');
      attribution.textContent = 'View on Google Books';
      attribution.target = '_blank';
      attribution.rel = 'noopener noreferrer';
      if (item.source) {
        attribution.href = item.source;
        attribution.setAttribute('aria-hidden', 'false');
      } else {
        attribution.classList.add('is-hidden');
        attribution.setAttribute('aria-hidden', 'true');
      }
      option.appendChild(attribution);

      const handleSelect = (event) => {
        if (event) {
          const target = event.target;
          if (target instanceof HTMLElement && target.closest('.prs-cover-attribution')) {
            return;
          }
          event.preventDefault();
        }
        selectSearchResult(item, option);
      };

      option.addEventListener('click', handleSelect);
      option.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar' || event.key === 'Space') {
          handleSelect(event);
        }
      });

      searchGridEl.appendChild(option);
      rendered.push({ item, option });
    });

    if (rendered.length === 1) {
      const only = rendered[0];
      selectSearchResult(only.item, only.option);
    }
  }

  function selectSearchResult(option, node) {
    const dataset = (node && node.dataset) || {};
    selectedSearchOption = {
      url: option?.url || dataset.coverUrl || '',
      source: option?.source || dataset.sourceLink || '',
      language: option?.language || dataset.language || '',
      title: option?.title || ''
    };
    if (searchGridEl) {
      Array.from(searchGridEl.children).forEach((child) => {
        const isMatch = child === node;
        child.classList.toggle('is-selected', isMatch);
        if (child instanceof HTMLElement) {
          child.setAttribute('aria-pressed', isMatch ? 'true' : 'false');
        }
      });
    }
    if (searchSetBtn) {
      searchSetBtn.disabled = false;
    }
    setSearchMessage('Click “Set Cover” to use the selected image.');
  }

  document.addEventListener('DOMContentLoaded', () => {
    const initialSource = (window.PRS_BOOK && typeof window.PRS_BOOK.cover_source === 'string')
      ? window.PRS_BOOK.cover_source
      : '';
    updateCoverAttribution(initialSource || '');
  });

  async function fetchGoogleCovers(title, author, language) {
    const body = new URLSearchParams({
      action: 'prs_cover_search_google',
      nonce: (window.PRS_COVER && PRS_COVER.searchNonce) || '',
      title,
      author: author || '',
      language: language || ''
    });

    const response = await fetch((window.PRS_COVER && PRS_COVER.ajax) || '', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      credentials: 'same-origin',
      body
    });

    const out = await response.json();
    if (!out || !out.success) {
      const message = (out && out.data && out.data.message) || out?.message || 'search_failed';
      const error = new Error(message);
      error.code = message;
      throw error;
    }

    const items = Array.isArray(out.data?.items) ? out.data.items : [];
    return items.slice(0, 3).map((item) => ({
      url: item.url || '',
      language: item.language || '',
      source: item.source || '',
      title: item.title || '',
      author: item.author || ''
    })).filter((item) => !!item.url);
  }

  async function handleSearchClick() {
    openSearchModal();
    setSearchLoadingState(true);
    setSearchMessage('Searching for covers…');
    renderSearchResults([]);

    const details = getBookDetails();
    const { title, author } = details;
    if (!title) {
      setSearchLoadingState(false);
      setSearchMessage('No book title available. Add a title to search or upload a cover manually.');
      return;
    }

    try {
      const language = resolveBookLanguage(details);
      const results = await fetchGoogleCovers(title, author, language);
      setSearchLoadingState(false);
      if (!results.length) {
        setSearchMessage('No covers found. You can upload your own image instead.');
        return;
      }
      renderSearchResults(results, language);
      if (results.length === 1) {
        setSearchMessage('Only one cover found. Click “Set Cover” to confirm.');
      } else if (language) {
        setSearchMessage(`Select a cover below and click “Set Cover”. Showing ${language.toUpperCase()} results when possible.`);
      } else {
        setSearchMessage('Select a cover below and click “Set Cover”.');
      }
    } catch (error) {
      console.error('[PRS] cover search error', error);
      setSearchLoadingState(false);
      const msg = error?.code || error?.message;
      if (msg === 'missing_api_key') {
        setSearchMessage('Google Books API key is missing. Add it in the plugin settings.');
      } else if (msg === 'no_results') {
        setSearchMessage('No covers found. You can upload your own image instead.');
      } else {
        setSearchMessage('There was an error searching for covers. Please try again later.');
      }
    }
  }

  function onSearchSetCover() {
    if (!selectedSearchOption || !selectedSearchOption.url) return;
    if (!searchSetBtn) return;

    const originalText = searchSetBtn.textContent;
    searchSetBtn.disabled = true;
    searchSetBtn.textContent = 'Saving…';
    setSearchMessage('Saving selected cover…');

    const ajax = getAjaxConfig();
    const bookId = parseInt(searchSetBtn.dataset.bookId || getContext().book_id || 0, 10);

    if (!ajax.ajaxUrl || !bookId) {
      setSearchMessage('Could not save the selected cover. Please try again.');
      searchSetBtn.disabled = false;
      searchSetBtn.textContent = originalText;
      return;
    }

    const payload = {
      action: 'prs_save_cover_url',
      nonce: ajax.nonce,
      book_id: bookId,
      cover_url: selectedSearchOption.url,
      cover_source: selectedSearchOption.source || ''
    };

    const jq = window.jQuery;

    if (!jq || typeof jq.ajax !== 'function') {
      searchSetBtn.disabled = false;
      searchSetBtn.textContent = originalText;
      setSearchMessage('Could not save the selected cover. Please try again.');
      return;
    }

    console.log('Attempting to save cover:', {
      coverUrl: payload.cover_url,
      coverSource: payload.cover_source,
      bookId
    });

    jq.ajax({
      url: ajax.ajaxUrl,
      method: 'POST',
      dataType: 'json',
      data: payload,
    }).done((res) => {
      console.log('AJAX response:', res);
      if (!res || !res.success) {
        const errorMessage = res && res.data ? String(res.data) : 'Could not save the selected cover. Please try again.';
        setSearchMessage(errorMessage);
        searchSetBtn.disabled = false;
        searchSetBtn.textContent = originalText;
        return;
      }

      const data = res.data || {};
      const src = data.src || selectedSearchOption.url;
      const sourceLink = data.source || selectedSearchOption.source || '';
      replaceCover(src, false, sourceLink);
      if (window.PRS_BOOK) {
        window.PRS_BOOK.cover_url = src;
        window.PRS_BOOK.cover_source = sourceLink;
      }
      if (searchSetBtn) {
        searchSetBtn.disabled = false;
        searchSetBtn.textContent = originalText;
      }
      closeSearchModal();
    }).fail((xhr, status, error) => {
      console.error('AJAX ERROR:', { xhr, status, error });
      const responseText = xhr && typeof xhr.responseText === 'string' ? xhr.responseText : '';
      if (responseText) {
        console.error('Server Response:', responseText);
      }
      const responseJSON = xhr && xhr.responseJSON ? xhr.responseJSON : null;
      const message = responseJSON && responseJSON.data ? responseJSON.data : (responseText || 'Server error. Please try again later.');
      setSearchMessage(String(message));
      searchSetBtn.disabled = false;
      searchSetBtn.textContent = originalText;
    });
  }

  // ====== Event bindings ======
  document.addEventListener('click', (event) => {
    const uploadBtn = event.target.closest('#prs-cover-open');
    if (uploadBtn) {
      event.preventDefault();
      openModal();
      return;
    }

    const searchBtn = event.target.closest('#prs-cover-search');
    if (searchBtn) {
      event.preventDefault();
      handleSearchClick();
    }
  });
})();
