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
      author: g.author || ''
    };
  }

  // ====== Upload & crop modal ======
  const STAGE_W = 280;
  const STAGE_H = 450;

  let modal, stage, imgEl, slider, saveBtn, cancelBtn, fileInput, statusEl;
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
    const panel = el('div', 'prs-cover-modal__content');

    const title = el('div', 'prs-cover-modal__title');
    title.textContent = 'Upload Book Cover';

    const topBar = el('div', 'prs-cover-modal__topbar');
    fileInput = el('input');
    fileInput.type = 'file';
    fileInput.accept = 'image/*';
    topBar.appendChild(fileInput);

    const wrap = el('div', 'prs-crop-wrap');
    stage = el('div', 'prs-crop-stage');
    stage.style.width = `${STAGE_W}px`;
    stage.style.height = `${STAGE_H}px`;
    wrap.appendChild(stage);

    const controls = el('div', 'prs-crop-controls');
    const label = el('span', 'prs-crop-label');
    label.textContent = 'Zoom';
    slider = el('input', 'prs-crop-slider');
    slider.type = 'range';
    slider.min = '1';
    slider.max = '4';
    slider.step = '0.01';
    slider.value = '1';
    controls.appendChild(label);
    controls.appendChild(slider);

    const footer = el('div', 'prs-cover-modal__footer');
    statusEl = el('span', 'prs-cover-status');
    cancelBtn = el('button', 'prs-btn prs-btn--ghost');
    cancelBtn.type = 'button';
    cancelBtn.textContent = 'Cancel';
    saveBtn = el('button', 'prs-btn');
    saveBtn.type = 'button';
    saveBtn.textContent = 'Save';
    footer.append(statusEl, cancelBtn, saveBtn);

    panel.append(title, topBar, wrap, controls, footer);
    modal.appendChild(panel);
    document.body.appendChild(modal);

    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeModal();
    });
    cancelBtn.addEventListener('click', closeModal);
    fileInput.addEventListener('change', onPickFile);
    slider.addEventListener('input', onZoomChange);
    saveBtn.addEventListener('click', onSave);
  }

  function closeModal() {
    if (modal) modal.remove();
    modal = null;
    imgEl = null;
    naturalW = 0;
    naturalH = 0;
    scale = 1;
    minScale = 1;
  }

  function setStatus(txt) {
    if (statusEl) statusEl.textContent = txt || '';
  }

  function onPickFile(event) {
    const file = event.target.files && event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => loadIntoStage(reader.result);
    reader.readAsDataURL(file);
  }

  function loadIntoStage(dataUrl) {
    stage.innerHTML = '';
    imgEl = el('img', 'prs-crop-img');
    imgEl.onload = () => {
      naturalW = imgEl.naturalWidth;
      naturalH = imgEl.naturalHeight;
      const sX = STAGE_W / naturalW;
      const sY = STAGE_H / naturalH;
      minScale = Math.max(sX, sY);
      scale = minScale;
      slider.value = '1';
      applyTransform();
    };
    imgEl.src = dataUrl;
    stage.appendChild(imgEl);
  }

  function applyTransform() {
    if (!imgEl) return;
    imgEl.style.width = `${naturalW}px`;
    imgEl.style.height = `${naturalH}px`;
    imgEl.style.transformOrigin = 'center center';
    imgEl.style.transform = `translate(-50%, -50%) scale(${scale})`;
  }

  function onZoomChange() {
    const value = parseFloat(slider.value || '1');
    scale = minScale * value;
    applyTransform();
  }

  function replaceCover(src, bustCache) {
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
  }

  function onSave() {
    if (!imgEl) {
      setStatus('Choose an image');
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
        setStatus('Render error');
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
          setStatus(out?.data?.message || out?.message || 'Error');
          return;
        }

        setStatus('Saved');
        const src = out.data.src;
        replaceCover(src, true);
        if (window.PRS_BOOK) {
          window.PRS_BOOK.cover_url = '';
        }
        closeModal();
      } catch (error) {
        setStatus('Error');
        console.error('[PRS] cover save error', error);
      }
    }, 'image/jpeg', 0.9);
  }

  // ====== Search modal ======
  let searchModal;
  let searchMessageEl;
  let searchGridEl;
  let searchSetBtn;
  let selectedSearchUrl = '';

  function openSearchModal() {
    closeSearchModal();

    searchModal = el('div', 'prs-cover-search-modal');
    const panel = el('div', 'prs-cover-search-modal__content');

    const title = el('h2', 'prs-cover-search-modal__title');
    title.textContent = 'Select a Cover';

    searchMessageEl = el('p', 'prs-cover-search-modal__message');
    searchMessageEl.textContent = '';

    searchGridEl = el('div', 'prs-cover-search-modal__grid');

    const footer = el('div', 'prs-cover-search-modal__footer');
    const cancel = el('button', 'prs-btn prs-btn--ghost');
    cancel.type = 'button';
    cancel.textContent = 'Cancel';
    searchSetBtn = el('button', 'prs-btn');
    searchSetBtn.type = 'button';
    searchSetBtn.textContent = 'Set Cover';
    searchSetBtn.disabled = true;
    footer.append(cancel, searchSetBtn);

    panel.append(title, searchMessageEl, searchGridEl, footer);
    searchModal.appendChild(panel);
    document.body.appendChild(searchModal);

    selectedSearchUrl = '';

    searchModal.addEventListener('click', (e) => {
      if (e.target === searchModal) closeSearchModal();
    });
    cancel.addEventListener('click', closeSearchModal);
    searchSetBtn.addEventListener('click', onSearchSetCover);
  }

  function closeSearchModal() {
    if (searchModal) searchModal.remove();
    searchModal = null;
    searchMessageEl = null;
    searchGridEl = null;
    searchSetBtn = null;
    selectedSearchUrl = '';
  }

  function setSearchMessage(message) {
    if (searchMessageEl) {
      searchMessageEl.textContent = message || '';
    }
  }

  function renderSearchResults(items) {
    if (!searchGridEl) return;
    searchGridEl.innerHTML = '';
    selectedSearchUrl = '';
    if (searchSetBtn) {
      searchSetBtn.disabled = true;
      searchSetBtn.textContent = 'Set Cover';
    }

    items.slice(0, 3).forEach((url) => {
      const thumb = el('button', 'prs-cover-search-modal__thumb');
      thumb.type = 'button';
      const img = el('img');
      img.src = url;
      img.alt = 'Book cover option';
      thumb.appendChild(img);
      thumb.addEventListener('click', () => selectSearchResult(url, thumb));
      searchGridEl.appendChild(thumb);
    });
  }

  function selectSearchResult(url, node) {
    selectedSearchUrl = url;
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

  async function fetchCovers(title, author) {
    const params = new URLSearchParams({ title });
    if (author) params.append('author', author);
    params.append('limit', '8');

    const response = await fetch(`https://openlibrary.org/search.json?${params.toString()}`);
    if (!response.ok) {
      throw new Error('search_failed');
    }
    const data = await response.json();
    const docs = Array.isArray(data?.docs) ? data.docs : [];
    const results = [];
    const seen = new Set();

    for (const doc of docs) {
      let url = '';
      if (doc && typeof doc.cover_i === 'number') {
        url = `https://covers.openlibrary.org/b/id/${doc.cover_i}-L.jpg`;
      } else if (doc && Array.isArray(doc.isbn)) {
        const isbn = doc.isbn.find((v) => typeof v === 'string' && v.trim());
        if (isbn) {
          url = `https://covers.openlibrary.org/b/isbn/${encodeURIComponent(isbn)}-L.jpg`;
        }
      }

      if (!url || seen.has(url)) continue;
      seen.add(url);
      results.push(url);
      if (results.length >= 3) break;
    }

    return results;
  }

  async function handleSearchClick() {
    openSearchModal();
    setSearchMessage('Searching for covers…');
    renderSearchResults([]);

    const { title, author } = getBookDetails();
    if (!title) {
      setSearchMessage('No book title available. Add a title to search or upload a cover manually.');
      return;
    }

    try {
      const results = await fetchCovers(title, author);
      if (!results.length) {
        setSearchMessage('No covers found. You can upload your own image instead.');
        return;
      }
      renderSearchResults(results);
      setSearchMessage('Select a cover below and click “Set Cover”.');
    } catch (error) {
      console.error('[PRS] cover search error', error);
      setSearchMessage('There was an error searching for covers. Please try again later.');
    }
  }

  async function onSearchSetCover() {
    if (!selectedSearchUrl) return;
    if (!searchSetBtn) return;

    const originalText = searchSetBtn.textContent;
    searchSetBtn.disabled = true;
    searchSetBtn.textContent = 'Saving…';
    setSearchMessage('Saving selected cover…');

    const { user_book_id, book_id } = getContext();
    const body = new URLSearchParams({
      action: 'prs_cover_save_external',
      nonce: (window.PRS_COVER && PRS_COVER.externalNonce) || '',
      user_book_id,
      book_id,
      image_url: selectedSearchUrl
    });

    try {
      const resp = await fetch((window.PRS_COVER && PRS_COVER.ajax) || '', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        credentials: 'same-origin',
        body
      });
      const out = await resp.json();
      if (!out || !out.success || !out.data) {
        throw new Error(out?.data?.message || out?.message || 'save_failed');
      }

      const src = out.data.src || selectedSearchUrl;
      replaceCover(src, false);
      if (window.PRS_BOOK) {
        window.PRS_BOOK.cover_url = src;
      }
      closeSearchModal();
    } catch (error) {
      console.error('[PRS] cover save external error', error);
      setSearchMessage('Could not save the selected cover. Please try again.');
      if (searchSetBtn) {
        searchSetBtn.disabled = false;
        searchSetBtn.textContent = originalText;
      }
      return;
    }

    if (searchSetBtn) {
      searchSetBtn.textContent = originalText;
    }
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
