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

  function normalizeTitle(title) {
    if (!title) return '';
    return title
      .toLowerCase()
      .replace(/[\u2018\u2019\u201C\u201D"'`]/g, '')
      .replace(/[^\p{L}\p{N}\s]/gu, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function titlesMatchExact(a, b) {
    return normalizeTitle(a) === normalizeTitle(b);
  }

  function titleSimilarity(a, b) {
    const normA = normalizeTitle(a);
    const normB = normalizeTitle(b);
    if (!normA || !normB) return 0;
    const wordsA = new Set(normA.split(' '));
    const wordsB = new Set(normB.split(' '));
    if (!wordsA.size || !wordsB.size) return 0;
    let overlap = 0;
    wordsA.forEach((word) => {
      if (wordsB.has(word)) {
        overlap += 1;
      }
    });
    return overlap / Math.max(wordsA.size, wordsB.size);
  }

  function languageFromEdition(entry) {
    if (!entry) return '';
    const languages = Array.isArray(entry.languages) ? entry.languages : [];
    for (const candidate of languages) {
      if (candidate) {
        if (typeof candidate === 'string') {
          const normalized = normalizeLanguageCode(candidate);
          if (normalized) return normalized;
        } else if (candidate && typeof candidate === 'object') {
          const normalized = normalizeLanguageCode(candidate.key || candidate.id || '');
          if (normalized) return normalized;
        }
      }
    }
    return '';
  }

  function collectEditionCovers(editions, preferredLanguage) {
    const seen = new Set();
    const prioritized = [];
    const fallback = [];

    editions.forEach((entry) => {
      if (!entry || !Array.isArray(entry.covers)) {
        return;
      }
      const editionLang = languageFromEdition(entry);
      const matchesPreferred = preferredLanguage && editionLang
        ? editionLang === preferredLanguage
        : false;

      entry.covers.forEach((coverId) => {
        if (typeof coverId !== 'number' || seen.has(coverId)) {
          return;
        }
        const item = {
          id: coverId,
          url: `https://covers.openlibrary.org/b/id/${coverId}-L.jpg`,
          language: editionLang || '',
        };
        seen.add(coverId);
        if (matchesPreferred) {
          prioritized.push(item);
        } else {
          fallback.push(item);
        }
      });
    });

    return prioritized.concat(fallback).slice(0, 3);
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
  let searchPrevFocus = null;
  let searchKeydownListener = null;

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

  async function fetchWorkAndEditions(title, author, language) {
    const cleanTitle = (title || '').trim();
    if (!cleanTitle) return [];

    const lang = normalizeLanguageCode(language);
    const params = {
      title: cleanTitle,
      limit: '10'
    };
    if (author) {
      params.author = author;
    }
    if (lang) {
      params.language = lang;
    }

    let docs = [];
    try {
      docs = await fetchOpenLibrary(params);
    } catch (error) {
      console.error('[PRS] open library search failed', error);
      return [];
    }

    if (!docs.length) {
      return [];
    }

    const normalizedAuthor = (author || '').toLowerCase();
    const exactTitleLang = [];
    const exactTitleAuthor = [];
    const partialLang = [];
    const fallback = [];

    docs.forEach((doc, index) => {
      if (!doc || typeof doc !== 'object') {
        return;
      }
      const workKey = typeof doc.key === 'string' ? doc.key : '';
      if (!workKey || !workKey.startsWith('/works/')) {
        return;
      }

      const docTitle = doc.title || '';
      const docLanguages = Array.isArray(doc.language)
        ? doc.language.map((code) => normalizeLanguageCode(code)).filter(Boolean)
        : [];
      const hasLanguage = lang && docLanguages.includes(lang);
      const docAuthors = Array.isArray(doc.author_name)
        ? doc.author_name.map((name) => (name || '').toLowerCase())
        : [];
      const hasAuthor = normalizedAuthor
        ? docAuthors.some((name) => name.includes(normalizedAuthor))
        : false;
      const isExact = titlesMatchExact(cleanTitle, docTitle);
      const similarity = titleSimilarity(cleanTitle, docTitle);

      const bucketItem = {
        doc,
        index,
      };

      if (isExact && hasLanguage) {
        exactTitleLang.push(bucketItem);
      } else if (isExact && hasAuthor) {
        exactTitleAuthor.push(bucketItem);
      } else if (similarity >= 0.6 && hasLanguage) {
        partialLang.push(bucketItem);
      } else {
        fallback.push(bucketItem);
      }
    });

    const orderedBuckets = [exactTitleLang, exactTitleAuthor, partialLang, fallback];
    let chosenDoc = null;
    for (const bucket of orderedBuckets) {
      if (bucket.length) {
        bucket.sort((a, b) => a.index - b.index);
        chosenDoc = bucket[0].doc;
        break;
      }
    }

    if (!chosenDoc) {
      return [];
    }

    const workKey = chosenDoc.key;

    let editions = [];
    try {
      const editionResp = await fetch(`https://openlibrary.org${workKey}/editions.json?limit=10`);
      if (!editionResp.ok) {
        throw new Error('editions_failed');
      }
      const editionData = await editionResp.json();
      editions = Array.isArray(editionData?.entries) ? editionData.entries : [];
    } catch (error) {
      console.error('[PRS] open library editions fetch failed', error);
      return [];
    }

    if (!editions.length) {
      return [];
    }

    return collectEditionCovers(editions, lang);
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
      const results = await fetchWorkAndEditions(title, author, language);
      setSearchLoadingState(false);
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
      setSearchLoadingState(false);
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
