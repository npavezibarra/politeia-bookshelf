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
    return 'en';
  }

  // ====== Upload & crop modal ======
  const STAGE_W = 220;
  const STAGE_H = 350;

  let modal, stage, imgEl, slider, saveBtn, cancelBtn, fileInput, statusEl, placeholderEl;
  let naturalW = 0;
  let naturalH = 0;
  let scale = 1;
  let minScale = 1;

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
            </div>
          </div>

          <div class="prs-cover-controls" id="upload-settings-setting">

            <div class="prs-file-input">
              <input type="file" id="fileInput" accept="image/jpeg, image/png" class="prs-hidden-input">
              <label for="fileInput" class="prs-btn prs-btn--ghost">Choose File</label>
            </div>

            <div class="prs-crop-controls">
              <div class="prs-zoom-group">
                <input type="range" id="zoomSlider" min="1" max="4" step="0.01" value="1" disabled>
                <label for="zoomSlider" class="prs-zoom-label">Zoom (1x – 4x)</label>
              </div>
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
    slider = panel.querySelector('#zoomSlider');
    statusEl = panel.querySelector('#statusMessage');
    cancelBtn = panel.querySelector('#prs-cover-cancel');
    saveBtn = panel.querySelector('#prs-cover-save');
    imgEl = panel.querySelector('#previewImage');
    document.body.appendChild(modal);

    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeModal();
    });
    cancelBtn.addEventListener('click', closeModal);
    saveBtn.addEventListener('click', onSave);

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
        zoomSlider: slider,
        onFiles: handleFiles,
        onZoomChange,
      }
    }));
  }

  function closeModal() {
    if (modal) modal.remove();
    modal = null;
    stage = null;
    imgEl = null;
    slider = null;
    saveBtn = null;
    cancelBtn = null;
    fileInput = null;
    statusEl = null;
    placeholderEl = null;
    naturalW = 0;
    naturalH = 0;
    scale = 1;
    minScale = 1;
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
      }
      if (placeholderEl) {
        placeholderEl.style.opacity = '1';
        placeholderEl.style.pointerEvents = 'auto';
      }
      if (slider) {
        slider.disabled = true;
        slider.value = '1';
      }
      if (imgEl) {
        imgEl.style.transform = 'scale(1)';
      }
      naturalW = 0;
      naturalH = 0;
      scale = 1;
      minScale = 1;
      return;
    }

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
      const sX = STAGE_W / naturalW;
      const sY = STAGE_H / naturalH;
      minScale = Math.min(sX, sY);
      scale = minScale;

      if (slider) {
        slider.disabled = false;
        slider.value = '1';
      }

      updatePreviewTransform();

      if (placeholderEl) {
        placeholderEl.style.opacity = '0';
        placeholderEl.style.pointerEvents = 'none';
      }

      imgEl.style.display = 'block';

      if (typeof onReady === 'function') {
        onReady();
      }
    };
    imgEl.src = dataUrl;
  }

  function updatePreviewTransform() {
    if (!imgEl || !slider) return;
    const zoomValue = parseFloat(slider.value || '1');
    imgEl.style.transform = `scale(${zoomValue})`;
    scale = minScale * zoomValue;
  }

  function onZoomChange() {
    updatePreviewTransform();
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
    if (placeholder && placeholder.parentNode) {
      placeholder.parentNode.removeChild(placeholder);
    }

    let img = document.getElementById('prs-cover-img');
    if (!img) {
      img = document.createElement('img');
      img.id = 'prs-cover-img';
      img.className = 'prs-cover-img';
      frame.appendChild(img);
    }

    let finalSrc = src;
    if (bustCache) {
      finalSrc = `${src}${src.indexOf('?') >= 0 ? '&' : '?'}t=${Date.now()}`;
    }
    img.src = finalSrc;
    const { title } = getBookDetails();
    if (title) img.alt = title;

    frame.classList.add('has-image');
    if (typeof source === 'string') {
      updateCoverAttribution(source);
    }
  }

  function onSave() {
    if (!imgEl || naturalW <= 0 || naturalH <= 0) {
      setStatus('Choose an image', '#ef4444');
      return;
    }

    const W = (window.PRS_COVER && PRS_COVER.coverWidth) || 240;
    const H = (window.PRS_COVER && PRS_COVER.coverHeight) || 450;
    const canvas = document.createElement('canvas');
    canvas.width = W;
    canvas.height = H;
    const ctx = canvas.getContext('2d');

    const value = parseFloat(slider.value || '1');
    const realScale = minScale * value;
    const viewW = STAGE_W / realScale;
    const viewH = STAGE_H / realScale;
    const sx = (naturalW / 2) - (viewW / 2);
    const sy = (naturalH / 2) - (viewH / 2);

    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(imgEl, sx, sy, viewW, viewH, 0, 0, W, H);

    canvas.toBlob(async (blob) => {
      if (!blob) {
        setStatus('Render error', '#ef4444');
        return;
      }
      setStatus('Saving…');

      const dataUrl = await new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.readAsDataURL(blob);
      });

      const { user_book_id, book_id } = getContext();
      const body = new URLSearchParams({
        action: 'prs_cover_save_crop',
        nonce: (window.PRS_COVER && PRS_COVER.nonce) || '',
        user_book_id,
        book_id,
        image: dataUrl
      });

      try {
        const resp = await fetch((window.PRS_COVER && PRS_COVER.ajax) || '', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          credentials: 'same-origin',
          body
        });
        const out = await resp.json();
        if (!out || !out.success) {
          setStatus(out?.data?.message || out?.message || 'Error', '#ef4444');
          return;
        }

        setStatus('Saved', '#16a34a');
        const src = out.data.src;
        replaceCover(src, true, '');
        if (window.PRS_BOOK) {
          window.PRS_BOOK.cover_url = '';
          window.PRS_BOOK.cover_source = '';
        }
        closeModal();
      } catch (error) {
        setStatus('Error', '#ef4444');
        console.error('[PRS] cover save error', error);
      }
    }, 'image/jpeg', 0.9);
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

    searchGridEl = el('div', 'prs-cover-search-modal__grid');

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

    items.forEach((item) => {
      if (!item || !item.url) return;
      const option = el('div', 'prs-cover-option');
      option.dataset.coverUrl = item.url;
      if (item.source) option.dataset.sourceLink = item.source;
      if (item.language) option.dataset.language = item.language;

      const selectBtn = el('button', 'prs-cover-option__select');
      selectBtn.type = 'button';
      const img = el('img');
      img.src = item.url;
      img.alt = item.title ? `Cover for ${item.title}` : 'Book cover option';
      img.loading = 'lazy';
      selectBtn.appendChild(img);

      if (item.language) {
        const badge = el('span', 'prs-cover-search-modal__lang');
        badge.textContent = item.language.toUpperCase();
        if (preferredLanguage && item.language.toLowerCase() !== preferredLanguage.toLowerCase()) {
          badge.classList.add('is-mismatch');
        }
        selectBtn.appendChild(badge);
      }

      selectBtn.addEventListener('click', (event) => {
        event.preventDefault();
        selectSearchResult(item, option);
      });

      option.appendChild(selectBtn);

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

      searchGridEl.appendChild(option);
    });
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
        child.classList.toggle('is-selected', child === node);
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
      title: item.title || ''
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
      if (language) {
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
