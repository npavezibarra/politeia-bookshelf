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
    normalized = normalized.replace(/[^a-z]/g, '');
    if (!normalized) return '';
    if (normalized.length === 3) return normalized;
    // Handle unexpected formats like `eng-US` or `en`.
    if (normalized.length === 2) {
      const map = {
        en: 'eng',
        es: 'spa',
        fr: 'fre',
        pt: 'por',
        de: 'ger',
        it: 'ita',
        ca: 'cat',
        gl: 'glg',
      };
      return map[normalized] || '';
    }
    if (normalized.length > 3) {
      return normalized.slice(0, 3);
    }
    return normalized;
  }

  function detectLanguageFromTitle(title) {
    if (!title || typeof title !== 'string') {
      return '';
    }

    const lower = title.toLowerCase();
    const checks = [
      { code: 'spa', pattern: /[áéíóúñü¿¡]/i },
      { code: 'fre', pattern: /[àâçéèêëîïôûùüÿœæ]/i },
      { code: 'por', pattern: /[ãõáâàéêíóôúç]/i },
      { code: 'ita', pattern: /[àèéìíîòóùú]/i },
      { code: 'ger', pattern: /[äöüß]/i },
      { code: 'cat', pattern: /[àèéíïòóú·]/i },
      { code: 'glg', pattern: /[áéíóúñ]/i },
    ];

    for (const { code, pattern } of checks) {
      if (pattern.test(lower)) {
        return code;
      }
    }

    // Default fallback.
    return 'eng';
  }

  function resolveBookLanguage(details) {
    const metaCode = normalizeLanguageCode(details.language);
    if (metaCode) return metaCode;
    const inferred = detectLanguageFromTitle(details.title || '');
    return normalizeLanguageCode(inferred) || 'eng';
  }

  function createTitleVariants(title) {
    const variants = new Set();
    const clean = (title || '').trim();
    if (!clean) return [];

    variants.add(clean);

    const separators = [':', '-', '—', '('];
    separators.forEach((sep) => {
      if (clean.includes(sep)) {
        const part = clean.split(sep)[0].trim();
        if (part.length > 3) {
          variants.add(part);
        }
      }
    });

    // Remove extra whitespace and punctuation for broader searches.
    const simplified = clean.replace(/["'“”‘’]/g, '').trim();
    if (simplified && simplified !== clean) {
      variants.add(simplified);
    }

    return Array.from(variants);
  }

  function languageMatches(doc, language) {
    if (!language) return true;
    const docLangs = Array.isArray(doc?.language) ? doc.language : [];
    if (!docLangs.length) return true;
    return docLangs.some((code) => normalizeLanguageCode(code) === language);
  }

  function buildCoverFromDoc(doc) {
    if (!doc || typeof doc !== 'object') return null;

    if (typeof doc.cover_i === 'number') {
      return {
        id: `cover:${doc.cover_i}`,
        url: `https://covers.openlibrary.org/b/id/${doc.cover_i}-L.jpg`,
      };
    }

    if (Array.isArray(doc.isbn) && doc.isbn.length) {
      const isbn = doc.isbn.find((value) => typeof value === 'string' && value.trim());
      if (isbn) {
        return {
          id: `isbn:${isbn}`,
          url: `https://covers.openlibrary.org/b/isbn/${encodeURIComponent(isbn)}-L.jpg`,
        };
      }
    }

    if (Array.isArray(doc.oclc) && doc.oclc.length) {
      const oclc = doc.oclc.find((value) => typeof value === 'string' && value.trim());
      if (oclc) {
        return {
          id: `oclc:${oclc}`,
          url: `https://covers.openlibrary.org/b/oclc/${encodeURIComponent(oclc)}-L.jpg`,
        };
      }
    }

    return null;
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
  let selectedSearchOption = null;

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

    selectedSearchOption = null;

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
    selectedSearchOption = null;
  }

  function setSearchMessage(message) {
    if (searchMessageEl) {
      searchMessageEl.textContent = message || '';
    }
  }

  function renderSearchResults(items) {
    if (!searchGridEl) return;
    searchGridEl.innerHTML = '';
    selectedSearchOption = null;
    if (searchSetBtn) {
      searchSetBtn.disabled = true;
      searchSetBtn.textContent = 'Set Cover';
    }

    items.forEach((item) => {
      if (!item || !item.url) return;
      const thumb = el('button', 'prs-cover-search-modal__thumb');
      thumb.type = 'button';
      const img = el('img');
      img.src = item.url;
      img.alt = 'Book cover option';
      thumb.appendChild(img);
      if (item.language) {
        const badge = el('span', 'prs-cover-search-modal__lang');
        badge.textContent = item.language.toUpperCase();
        thumb.appendChild(badge);
      }
      thumb.addEventListener('click', () => selectSearchResult(item, thumb));
      searchGridEl.appendChild(thumb);
    });
  }

  function selectSearchResult(option, node) {
    selectedSearchOption = option;
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

  async function fetchOpenLibrary(params) {
    const urlParams = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
      if (value) {
        urlParams.append(key, value);
      }
    });
    urlParams.set('limit', params.limit || '5');

    const response = await fetch(`https://openlibrary.org/search.json?${urlParams.toString()}`);
    if (!response.ok) {
      throw new Error('search_failed');
    }
    const data = await response.json();
    return Array.isArray(data?.docs) ? data.docs : [];
  }

  async function fetchCovers(title, author, language) {
    const cleanTitle = (title || '').trim();
    if (!cleanTitle) return [];

    const lang = normalizeLanguageCode(language);
    const variants = createTitleVariants(cleanTitle);
    const attempts = [];

    const baseParams = (queryTitle, withLang) => {
      const params = { title: queryTitle, limit: '8' };
      if (author) {
        params.author = author;
      }
      if (withLang && lang) {
        params.language = lang;
      }
      return params;
    };

    // Priority 1: exact title with language.
    if (lang) {
      attempts.push({ params: baseParams(cleanTitle, true), priority: 1 });
    }

    // Priority 2: exact title without language.
    attempts.push({ params: baseParams(cleanTitle, false), priority: lang ? 2 : 1 });

    // Priority 3: partial matches with language.
    if (lang && variants.length > 1) {
      variants
        .filter((variant) => variant !== cleanTitle)
        .forEach((variant, index) => {
          attempts.push({ params: baseParams(variant, true), priority: 3 + index / 10 });
        });
    }

    // Priority 4: partial matches without language.
    variants
      .filter((variant) => variant !== cleanTitle)
      .forEach((variant, index) => {
        attempts.push({ params: baseParams(variant, false), priority: 4 + index / 10 });
      });

    const collected = [];
    const seen = new Set();

    for (const attempt of attempts) {
      try {
        const docs = await fetchOpenLibrary(attempt.params);
        docs.forEach((doc, docIndex) => {
          if (!languageMatches(doc, attempt.params.language || '')) {
            return;
          }
          const cover = buildCoverFromDoc(doc);
          if (!cover) return;
          if (seen.has(cover.id)) return;
          seen.add(cover.id);
          collected.push({
            url: cover.url,
            id: cover.id,
            priority: attempt.priority,
            order: docIndex,
            source: 'openlibrary',
            language: attempt.params.language || '',
          });
        });
      } catch (error) {
        console.error('[PRS] open library search failed', error);
      }

      if (collected.length >= 3) {
        break;
      }
    }

    const sorted = collected
      .sort((a, b) => {
        if (a.priority !== b.priority) {
          return a.priority - b.priority;
        }
        return a.order - b.order;
      })
      .slice(0, 3);

    return sorted;
  }

  async function handleSearchClick() {
    openSearchModal();
    setSearchMessage('Searching for covers…');
    renderSearchResults([]);

    const details = getBookDetails();
    const { title, author } = details;
    if (!title) {
      setSearchMessage('No book title available. Add a title to search or upload a cover manually.');
      return;
    }

    try {
      const language = resolveBookLanguage(details);
      const results = await fetchCovers(title, author, language);
      if (!results.length) {
        setSearchMessage('No covers found. You can upload your own image instead.');
        return;
      }
      renderSearchResults(results);
      if (language) {
        setSearchMessage(`Select a cover below and click “Set Cover”. Showing ${language.toUpperCase()} editions when possible.`);
      } else {
        setSearchMessage('Select a cover below and click “Set Cover”.');
      }
    } catch (error) {
      console.error('[PRS] cover search error', error);
      setSearchMessage('There was an error searching for covers. Please try again later.');
    }
  }

  async function onSearchSetCover() {
    if (!selectedSearchOption || !selectedSearchOption.url) return;
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
      image_url: selectedSearchOption.url
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

      const src = out.data.src || selectedSearchOption.url;
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
