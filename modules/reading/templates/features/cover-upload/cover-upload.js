(function () {
  // Fuente de verdad para user_book_id / book_id
  function getContext() {
    const g = (window.PRS_BOOK || {});
    return {
      user_book_id: g.user_book_id || 0,
      book_id: g.book_id || 0
    };
  }

  function getAjaxUrl() {
    return (window.PRS_COVER && PRS_COVER.ajax) || '';
  }

  function getFetchNonce() {
    if (window.PRS_BOOK && window.PRS_BOOK.fetchNonce) {
      return window.PRS_BOOK.fetchNonce;
    }
    return (window.PRS_COVER && PRS_COVER.fetchNonce) || '';
  }

  // Helpers
  const $ = (s, r = document) => r.querySelector(s);
  const el = (t, cls) => { const e = document.createElement(t); if (cls) e.className = cls; return e; };

  function setOverlayStatus(text, isError = false) {
    const status = document.getElementById('prs-cover-status');
    if (!status) return;
    status.textContent = text || '';
    status.classList.toggle('is-error', !!isError);
  }

  function ensureCoverImage(url) {
    if (!url) return;
    const frame = document.getElementById('prs-cover-frame');
    if (!frame) return;

    let img = document.getElementById('prs-cover-img');
    const placeholder = document.getElementById('prs-cover-placeholder');

    if (!img && placeholder && placeholder.tagName === 'IMG') {
      img = placeholder;
      img.id = 'prs-cover-img';
    }

    if (img) {
      img.removeAttribute('srcset');
      img.removeAttribute('sizes');
      img.removeAttribute('data-src');
      img.removeAttribute('data-srcset');
      img.src = url;
    } else {
      img = document.createElement('img');
      img.id = 'prs-cover-img';
      img.className = 'prs-cover-img';
      img.alt = '';
      img.src = url;
      img.removeAttribute('srcset');
      img.removeAttribute('sizes');
      frame.appendChild(img);
    }

    if (placeholder && placeholder !== img && placeholder.parentNode) {
      placeholder.parentNode.removeChild(placeholder);
    }

    img.classList.remove('prs-cover-img--placeholder');

    frame.classList.add('has-image');
  }

  // Estado de la imagen en el stage
  const STAGE_W = 280; // más pequeño => cabe sin scroll
  const STAGE_H = 450;

  let modal, stage, imgEl, slider, saveBtn, cancelBtn, fileInput, statusEl;
  let naturalW = 0, naturalH = 0;
  let scale = 1, minScale = 1;

  function openModal() {
    if (modal) { modal.remove(); modal = null; }

    // Overlay
    modal = el('div', 'prs-cover-modal');
    const panel = el('div', 'prs-cover-modal__content');

    const title = el('div', 'prs-cover-modal__title');
    title.textContent = 'Upload Book Cover';

    // Controles
    const topBar = el('div', 'prs-cover-modal__topbar');
    fileInput = el('input');
    fileInput.type = 'file';
    fileInput.accept = 'image/*';
    topBar.appendChild(fileInput);

    // Stage centrado
    const wrap = el('div', 'prs-crop-wrap'); // centra horizontalmente
    stage = el('div', 'prs-crop-stage');
    stage.style.width = STAGE_W + 'px';
    stage.style.height = STAGE_H + 'px';
    wrap.appendChild(stage);

    // Slider visible sin scroll
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

    // Footer
    const footer = el('div', 'prs-cover-modal__footer');
    statusEl = el('span', 'prs-cover-status');
    cancelBtn = el('button', 'prs-btn prs-btn--ghost');
    cancelBtn.textContent = 'Cancel';
    saveBtn = el('button', 'prs-btn');
    saveBtn.textContent = 'Save';
    footer.append(statusEl, cancelBtn, saveBtn);

    // Armar
    panel.append(title, topBar, wrap, controls, footer);
    modal.appendChild(panel);
    document.body.appendChild(modal);

    // Eventos
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
    imgEl = null; naturalW = naturalH = 0; scale = minScale = 1;
  }

  function setStatus(txt) { if (statusEl) statusEl.textContent = txt || ''; }

  function onPickFile(e) {
    const f = e.target.files && e.target.files[0];
    if (!f) return;
    const reader = new FileReader();
    reader.onload = () => loadIntoStage(reader.result);
    reader.readAsDataURL(f);
  }

  function loadIntoStage(dataUrl) {
    stage.innerHTML = '';
    imgEl = el('img', 'prs-crop-img');
    imgEl.onload = () => {
      naturalW = imgEl.naturalWidth;
      naturalH = imgEl.naturalHeight;

      // Calcular minScale para cubrir stage (sin barras negras)
      const sX = STAGE_W / naturalW;
      const sY = STAGE_H / naturalH;
      minScale = Math.max(sX, sY);
      scale = minScale;             // iniciar centrado y a "cover"
      slider.value = '1';           // 1 => minScale
      applyTransform();
    };
    imgEl.src = dataUrl;
    stage.appendChild(imgEl);
  }

  function applyTransform() {
    if (!imgEl) return;
    // transform-origin: center; escalamos desde el centro
    imgEl.style.width = naturalW + 'px';
    imgEl.style.height = naturalH + 'px';
    imgEl.style.transformOrigin = 'center center';
    imgEl.style.transform = `translate(-50%, -50%) scale(${scale})`;
  }

  function onZoomChange() {
    // rango visual 1..4 => escala real = minScale * value
    const v = parseFloat(slider.value || '1');
    scale = minScale * v;
    applyTransform();
  }

  function onSave() {
    if (!imgEl) { setStatus('Choose an image'); return; }

    // Pintar a 240x450
    const W = (window.PRS_COVER && PRS_COVER.coverWidth)  || 240;
    const H = (window.PRS_COVER && PRS_COVER.coverHeight) || 450;
    const canvas = document.createElement('canvas');
    canvas.width = W; canvas.height = H;
    const ctx = canvas.getContext('2d');

    // Escala real que estamos usando (natural -> stage cover)
    // minScale cubre el stage. Luego multiplicamos por slider (v).
    const v = parseFloat(slider.value || '1');
    const realScale = minScale * v;

    // Centro del stage (en coord del stage)
    const cx = STAGE_W / 2;
    const cy = STAGE_H / 2;

    // Area visible del stage en coordenadas de la imagen original:
    // El img está centrado en el stage con translate(-50%, -50%), así que:
    const viewW = STAGE_W / realScale;
    const viewH = STAGE_H / realScale;
    const sx = (naturalW / 2) - (viewW / 2);
    const sy = (naturalH / 2) - (viewH / 2);

    // Dibujar en canvas con recorte (centrado)
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(
      imgEl,
      sx, sy, viewW, viewH,  // recorte fuente
      0, 0, W, H             // destino (portada)
    );

    canvas.toBlob(async (blob) => {
      if (!blob) { setStatus('Render error'); return; }
      setStatus('Saving…');

      const dataUrl = await new Promise(res => {
        const r = new FileReader();
        r.onload = () => res(r.result);
        r.readAsDataURL(blob);
      });

      const { user_book_id, book_id } = getContext();
      const body = new URLSearchParams({
        action: 'prs_cover_save_crop',
        nonce: (window.PRS_COVER && PRS_COVER.nonce) || '',
        user_book_id, book_id,
        image: dataUrl
      });

      try {
        const resp = await fetch(getAjaxUrl(), {
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

        // Reemplazar la portada del front
        const src = out.data.src;
        const bust = src + (src.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
        ensureCoverImage(bust);

        closeModal();
      } catch (e) {
        setStatus('Error');
        console.error('[PRS] cover save error', e);
      }
    }, 'image/jpeg', 0.9);
  }

  // Abrir modal
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('#prs-cover-open');
    if (!btn) return;
    e.preventDefault();
    openModal();
  });

  async function onFetchRemote(button) {
    const { user_book_id, book_id } = getContext();
    if (!user_book_id || !book_id) {
      setOverlayStatus('Missing book context', true);
      return;
    }

    const nonce = getFetchNonce();
    if (!nonce) {
      setOverlayStatus('Security token missing', true);
      return;
    }

    button.disabled = true;
    setOverlayStatus('Fetching…', false);

    const body = new URLSearchParams({
      action: 'prs_cover_fetch_remote',
      user_book_id,
      book_id,
      _ajax_nonce: nonce
    });

    try {
      const resp = await fetch(getAjaxUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        credentials: 'same-origin',
        body
      });

      const text = await resp.text();
      let out = null;
      try {
        out = JSON.parse(text);
      } catch (parseErr) {
        throw new Error('Unexpected response');
      }

      if (!resp.ok || !out || !out.success) {
        const msg = out && out.data && out.data.message ? out.data.message : (out && out.message) ? out.message : 'No cover found';
        throw new Error(msg);
      }

      const data = out.data || {};
      if (!data.url) {
        throw new Error('No cover found');
      }

      const bust = data.url + (data.url.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
      ensureCoverImage(bust);

      const statusType = data.status === 'alternate' ? 'alternate' : 'found';

      let provider = 'the selected provider';
      if (data.source === 'open_library') {
        provider = 'Open Library';
      } else if (data.source === 'google_books') {
        provider = 'Google Books';
      }

      const statusMessage = statusType === 'alternate'
        ? `Found another cover from ${provider}.`
        : `Cover found from ${provider}.`;

      setOverlayStatus(statusMessage, false);
    } catch (error) {
      console.error('[PRS] cover fetch error', error);
      setOverlayStatus(error.message || 'Error fetching cover', true);
    } finally {
      button.disabled = false;
    }
  }

  document.addEventListener('click', (e) => {
    const fetchBtn = e.target.closest('#prs-cover-fetch');
    if (!fetchBtn) return;
    e.preventDefault();
    onFetchRemote(fetchBtn);
  });
})();
