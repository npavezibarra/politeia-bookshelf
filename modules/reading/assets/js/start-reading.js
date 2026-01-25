/* global PRS_SR */
const prsStartReadingInit = (options = {}) => {
  const ctx = options.data || PRS_SR;
  if (typeof ctx === 'undefined') return;
  const root = options.root || document;
  const $ = (s) => root.querySelector(s);
  const STRINGS = ctx.strings || {};
  const text = (key, fallback) => (STRINGS && STRINGS[key]) ? STRINGS[key] : fallback;
  const format = (key, fallback, value) =>
    text(key, fallback).replace('%d', String(value));

  // Inputs / vistas
  const $startPage    = $('#prs-sr-start-page');
  const $startView    = $('#prs-sr-start-page-view');
  const $chapter      = $('#prs-sr-chapter');
  const $chapterView  = $('#prs-sr-chapter-view');

  // Timer y acciones
  const $timer        = $('#prs-sr-timer');
  const $rowActions   = root.querySelector('#prs-sr-row-actions');
  const $startBtn     = $('#prs-sr-start');
  const $stopBtn      = $('#prs-sr-stop');
  const $clockPath    = root.querySelector('#prs-sr-progress');
  const $clockWrap    = root.querySelector('.prs-sr-clock');
  const $stardustCanvas = root.querySelector('.prs-sr-stardust');

  // End/Save
  const $rowEnd       = $('#prs-sr-row-end');
  const $endPage      = $('#prs-sr-end-page');
  const $endError     = $('#prs-sr-end-error');
  const $rowSave      = $('#prs-sr-row-save');
  const $saveBtn      = $('#prs-sr-save');

  // Flash + wrapper formulario
  const $flash        = $('#prs-sr-flash');
  const $flashPages   = $('#prs-sr-flash-pages');
  const $flashTime    = $('#prs-sr-flash-time');
  const $formWrap     = $('#prs-sr-formwrap');
  const existingBookId = $formWrap?.dataset.prsBookId || '';
  const currentBookId = typeof ctx?.book_id !== 'undefined' ? String(ctx.book_id) : '';
  if ($formWrap && $formWrap.dataset.prsInit === '1' && existingBookId === currentBookId) return;

  let flashHideTimer  = null;

  const ensureFlashDatasetDefaults = () => {
    if (!$flash) return;
    if (typeof ctx?.book_id !== 'undefined') {
      $flash.dataset.bookId = String(ctx.book_id);
    }
    if (typeof ctx?.user_id !== 'undefined') {
      $flash.dataset.userId = String(ctx.user_id);
    }
    if (typeof $flash.dataset.sessionId === 'undefined') {
      $flash.dataset.sessionId = '';
    }
  };

  const updateFlashSessionId = (id) => {
    if (!$flash) return;
    $flash.dataset.sessionId = id ? String(id) : '';
  };

  ensureFlashDatasetDefaults();

  // Aviso falta de pages
  const $rowNeedsPages = root.querySelector('#prs-sr-row-needs-pages');

  // helpers de tiempo
  let t0 = 0, raf = 0;
  let clockStartSeconds = null;
  const pad = (n) => String(n).padStart(2, '0');
  const hms = (ms) => {
    const s = Math.floor(ms / 1000);
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const r = s % 60;
    return `${pad(h)}:${pad(m)}:${pad(r)}`;
  };
  const secondsInHour = (date) => (date.getMinutes() * 60) + date.getSeconds();
  const calculatePiePath = (startSeconds, currentSeconds) => {
    if (startSeconds === null || currentSeconds === null) return '';
    let diff = currentSeconds - startSeconds;
    if (diff < 0) diff += 3600;
    if (diff <= 0) return '';
    if (diff >= 3599) return 'M 100 100 m -100 0 a 100 100 0 1 0 200 0 a 100 100 0 1 0 -200 0';
    const startAngle = (startSeconds / 3600) * 360 - 90;
    const endAngle = (currentSeconds / 3600) * 360 - 90;
    const startRad = (startAngle * Math.PI) / 180;
    const endRad = (endAngle * Math.PI) / 180;
    const x1 = 100 + 100 * Math.cos(startRad);
    const y1 = 100 + 100 * Math.sin(startRad);
    const x2 = 100 + 100 * Math.cos(endRad);
    const y2 = 100 + 100 * Math.sin(endRad);
    const largeArcFlag = diff > 1800 ? 1 : 0;
    return `M 100 100 L ${x1} ${y1} A 100 100 0 ${largeArcFlag} 1 ${x2} ${y2} Z`;
  };
  const updateClock = () => {
    if (!$clockPath || clockStartSeconds === null) return;
    const currentSeconds = secondsInHour(new Date());
    $clockPath.setAttribute('d', calculatePiePath(clockStartSeconds, currentSeconds));
  };
  const tick = () => {
    if ($timer) $timer.textContent = hms(Date.now() - t0);
    updateClock();
    raf = requestAnimationFrame(tick);
  };
  const startTimer = () => { t0 = Date.now(); cancelAnimationFrame(raf); raf = requestAnimationFrame(tick); };
  const stopTimer  = () => { cancelAnimationFrame(raf); raf = 0; return Math.floor((Date.now() - t0) / 1000); };

  const stardustState = {
    running: false,
    raf: 0,
    particles: [],
  };

  const stardustSettings = {
    speed: 1.6,
    spawnRate: 0.5,
    burstChance: 0.025,
    spreadFactor: 0.4,
    beige: '245, 245, 220',
  };

  if ($stardustCanvas) {
    window.addEventListener('resize', () => {
      if (stardustState.running) {
        sizeStardustCanvas();
      }
    });
  }

  const sizeStardustCanvas = () => {
    if (!$stardustCanvas || !$clockWrap) return;
    $stardustCanvas.width = $clockWrap.clientWidth;
    $stardustCanvas.height = $clockWrap.clientHeight;
  };

  const getBiasedY = (height) => {
    const centerY = height / 2;
    const spread = height * stardustSettings.spreadFactor;
    const bias = (Math.random() - 0.5) * (Math.random() * spread);
    return centerY + bias;
  };

  const updateStardust = () => {
    if (!stardustState.running || !$stardustCanvas) return;
    const ctx2d = $stardustCanvas.getContext('2d');
    if (!ctx2d) return;
    const width = $stardustCanvas.width;
    const height = $stardustCanvas.height;
    ctx2d.clearRect(0, 0, width, height);

    if (Math.random() < stardustSettings.spawnRate) {
      stardustState.particles.push({
        x: width,
        y: getBiasedY(height),
        speed: stardustSettings.speed + Math.random() * (stardustSettings.speed * 0.25),
        opacity: 0,
        hasBurst: false,
        size: 1,
        fragment: false,
        vy: 0,
      });
    }

    for (let i = stardustState.particles.length - 1; i >= 0; i -= 1) {
      const p = stardustState.particles[i];
      p.x -= p.speed;
      p.y += p.vy;
      const centerX = width / 2;
      const distFromCenter = Math.abs(p.x - centerX);
      const maxDist = width / 2;
      p.opacity = Math.max(0, 1 - (distFromCenter / maxDist));

      if (!p.fragment && !p.hasBurst && p.x < width * 0.55 && p.x > width * 0.45) {
        if (Math.random() < stardustSettings.burstChance) {
          p.hasBurst = true;
          const fragments = 4 + Math.floor(Math.random() * 6);
          for (let j = 0; j < fragments; j += 1) {
            stardustState.particles.push({
              x: p.x,
              y: p.y,
              speed: p.speed * (0.9 + Math.random() * 0.3),
              opacity: 0,
              hasBurst: true,
              size: 1,
              fragment: true,
              vy: (Math.random() - 0.5) * 1.2,
            });
          }
        }
      }

      ctx2d.fillStyle = `rgba(${stardustSettings.beige}, ${p.opacity})`;
      ctx2d.fillRect(Math.floor(p.x), Math.floor(p.y), p.size, p.size);

      if (p.x < -10) {
        stardustState.particles.splice(i, 1);
      }
    }

    stardustState.raf = requestAnimationFrame(updateStardust);
  };

  const startStardust = () => {
    if (!$stardustCanvas || stardustState.running) return;
    sizeStardustCanvas();
    stardustState.running = true;
    stardustState.raf = requestAnimationFrame(updateStardust);
  };

  const stopStardust = () => {
    if (!stardustState.running) return;
    stardustState.running = false;
    if (stardustState.raf) cancelAnimationFrame(stardustState.raf);
    stardustState.raf = 0;
    stardustState.particles = [];
    if ($stardustCanvas) {
      const ctx2d = $stardustCanvas.getContext('2d');
      if (ctx2d) ctx2d.clearRect(0, 0, $stardustCanvas.width, $stardustCanvas.height);
    }
  };

  // util UI
  const setText   = (el, t) => { if (el) el.textContent = t || ''; };
  const toggle    = (el, show) => { if (el) el.style.display = show ? '' : 'none'; };
  const toggleRow = (row, show) => { if (row) row.style.display = show ? '' : 'none'; };

  // Validaciones de campos
  function validStart() {
    const v = Number($startPage?.value || 0);
    return Number.isInteger(v) && v > 0;
  }
  function validEnd() {
    const s = Number($startPage?.value || 0);
    const e = Number($endPage?.value || 0);
    return Number.isInteger(e) && e >= s && e > 0;
  }
  function updateEndError() {
    if (!$endError) return;
    const s = Number($startPage?.value || 0);
    const eRaw = ($endPage?.value || '').trim();
    const e = Number(eRaw || 0);
    if (!eRaw) {
      $endError.style.display = 'none';
      return;
    }
    if (Number.isInteger(e) && e > 0 && e < s) {
      $endError.style.display = 'block';
    } else {
      $endError.style.display = 'none';
    }
  }

  // Bloqueo por estado de posesión
  const BLOCKED = new Set(['borrowed', 'lost', 'sold']);
  const $owningSelect = root.querySelector('#owning-status-select'); // si existe en la misma página

  function statusValue() {
    const v = ($owningSelect && $owningSelect.value)
      ? String($owningSelect.value).trim()
      : (ctx.owning_status || 'in_shelf');
    return v;
  }
  function canStartByStatus() {
    return !BLOCKED.has(statusValue());
  }

  // Bloqueo por Pages
  function hasPages() {
    return Number(ctx.total_pages || 0) > 0;
  }

  // Mensajes de tooltip en Start
  function applyStartTitle() {
    let title = '';
    if (!hasPages()) title = text('tooltip_pages_required', 'Set total Pages for this book before starting a session.');
    else if (!canStartByStatus()) title = text('tooltip_not_owned', 'You cannot start a session: the book is not in your possession (Borrowed, Lost or Sold).');
    if ($startBtn) {
      if (title) $startBtn.title = title;
      else $startBtn.removeAttribute('title');
    }
  }

  function updateStartEnabled() {
    const ok = hasPages() && canStartByStatus() && validStart();
    if ($startBtn) {
      $startBtn.disabled = !ok;
      $startBtn.setAttribute('aria-disabled', $startBtn.disabled ? 'true' : 'false');
    }
    const needsPages = !hasPages();
    toggleRow($rowNeedsPages, needsPages);
    if (needsPages) {
      toggle($startBtn, false);
    } else if ($startBtn && $rowActions && $rowActions.style.display !== 'none' && (!$stopBtn || $stopBtn.style.display === 'none')) {
      toggle($startBtn, true);
    }
    applyStartTitle();
  }

  const isFlashVisible = () => {
    if (!$flash) return false;
    return $flash.style.display !== 'none';
  };

  function resetFlashPanels() {
    if (!$flash) return;
    const summary = $flash.querySelector('#prs-sr-summary');
    const notePanel = $flash.querySelector('#prs-note-panel');
    if (summary && notePanel) {
      summary.style.display = '';
      notePanel.style.display = 'none';
    }
  }

  function cancelFlashAutoHide() {
    if (flashHideTimer) {
      window.clearTimeout(flashHideTimer);
      flashHideTimer = null;
    }
  }

  function scheduleFlashAutoHide() {
    cancelFlashAutoHide();
  }

  // Flash helpers (igualar dimensiones y mostrar)
  function hideFlash() {
    cancelFlashAutoHide();
    resetFlashPanels();
    if ($flash) {
      $flash.style.display = 'none';
    }
    if ($formWrap) $formWrap.style.display = '';
    const lastEl = root.querySelector('[data-role="sr-last"]');
    if (lastEl) lastEl.style.display = '';
    document.dispatchEvent(new CustomEvent('prs-sr-flash:reset'));
  }
  function showFlash(pagesText, timeText, ms = 4200) {
    if ($flash && $formWrap) {
      const inner = $flash.querySelector('.prs-sr-flash-inner');
      if (inner) {
        const h = $formWrap.offsetHeight;
        if (h) inner.style.minHeight = `${h}px`;
      }
      resetFlashPanels();
      document.dispatchEvent(new CustomEvent('prs-sr-flash:reset'));
      setText($flashPages, pagesText);
      setText($flashTime, timeText);
      $flash.style.display = 'block';
      $formWrap.style.display = 'none';
      const lastEl = root.querySelector('[data-role="sr-last"]');
      if (lastEl) lastEl.style.display = 'none';
      scheduleFlashAutoHide(ms);
    }
  }

  document.addEventListener('prs-sr-flash:openNote', () => {
    cancelFlashAutoHide();
  });

  document.addEventListener('prs-sr-flash:closeNote', () => {
    if (!isFlashVisible()) return;
  });

  document.addEventListener('prs-sr-flash:showNoteForSession', (event) => {
    if (!$flash) return;

    ensureFlashDatasetDefaults();

    const detail = event?.detail || {};
    if (typeof detail.bookId !== 'undefined' && detail.bookId !== null) {
      $flash.dataset.bookId = String(detail.bookId);
    }
    if (typeof detail.userId !== 'undefined' && detail.userId !== null) {
      $flash.dataset.userId = String(detail.userId);
    }
    updateFlashSessionId(detail.sessionId || '');

    resetFlashPanels();
    document.dispatchEvent(new CustomEvent('prs-sr-flash:reset'));
    cancelFlashAutoHide();

    const inner = $flash.querySelector('.prs-sr-flash-inner');
    let referenceHeight = 0;
    if ($formWrap) {
      referenceHeight = $formWrap.offsetHeight;
      $formWrap.style.display = 'none';
    }
    if (!referenceHeight && inner) {
      referenceHeight = inner.offsetHeight;
    }
    if (inner && referenceHeight) {
      inner.style.minHeight = `${referenceHeight}px`;
    }

    setText($flashPages, '—');
    setText($flashTime, '—');

    $flash.style.display = 'block';

    document.dispatchEvent(new CustomEvent('prs-sr-flash:showNoteEditor', { detail }));
  });

  // Estados UI
  function setIdle() {
    toggle($startPage, true);   toggle($startView, false);
    toggle($chapter, true);     toggle($chapterView, false);
    toggleRow($rowActions, true);
    toggle($startBtn, true);    toggle($stopBtn, false);
    toggleRow($rowEnd, false);  toggleRow($rowSave, false);
    if ($clockPath) $clockPath.setAttribute('d', '');
    clockStartSeconds = null;
    toggle($clockWrap, false);
    stopStardust();
    const hideDuringRun = root.querySelectorAll('[data-role="sr-field"], [data-role="sr-last"]');
    hideDuringRun.forEach((el) => {
      el.style.display = '';
    });
    updateStartEnabled();
  }
  function setRunning() {
    hideFlash();
    toggle($startPage, false);  toggle($startView, true);
    toggle($chapter, false);    toggle($chapterView, true);
    toggleRow($rowActions, true);
    toggle($startBtn, false);   toggle($stopBtn, true);
    toggleRow($rowEnd, false);  toggleRow($rowSave, false);
    toggle($clockWrap, true);
    startStardust();
    const hideDuringRun = root.querySelectorAll('[data-role="sr-field"], [data-role="sr-last"]');
    hideDuringRun.forEach((el) => {
      el.style.display = 'none';
    });
    if ($clockPath && clockStartSeconds === null) {
      clockStartSeconds = secondsInHour(new Date());
      updateClock();
    }
  }
  function setStopped() {
    toggle($startBtn, false);   toggle($stopBtn, false);
    toggleRow($rowActions, false);
    toggleRow($rowEnd, true);   toggleRow($rowSave, true);
    toggle($clockWrap, false);
    stopStardust();
    const hideDuringRun = root.querySelectorAll('[data-role="sr-field"], [data-role="sr-last"]');
    hideDuringRun.forEach((el) => {
      el.style.display = 'none';
    });
    if ($saveBtn) $saveBtn.disabled = !validEnd();
  }

  // Eventos de inputs
  $startPage?.addEventListener('input', updateStartEnabled);
  $endPage?.addEventListener('input',   () => {
    updateEndError();
    if ($saveBtn) $saveBtn.disabled = !validEnd();
  });
  $owningSelect?.addEventListener('change', updateStartEnabled);

  // Si el usuario guarda "Pages" en el panel del libro (mismo DOM)
  const $pagesSave = root.querySelector('#pages-save');
  const $pagesInput = root.querySelector('#pages-input');
  if ($pagesSave && $pagesInput) {
    $pagesSave.addEventListener('click', () => {
      const v = Number($pagesInput.value || 0);
      if (Number.isInteger(v) && v > 0) {
        ctx.total_pages = v;  // actualiza cache local
        updateStartEnabled();
      }
    });
  }

  // API
  const ACTION_START = (ctx?.actions?.start) || 'prs_start_reading';
  const ACTION_SAVE  = (ctx?.actions?.save)  || 'prs_save_reading';

  async function api(action, payload) {
    const fallback = (window.PRS_BOOK && typeof window.PRS_BOOK === 'object') ? window.PRS_BOOK : {};
    const nonce = ctx?.nonce || fallback.reading_nonce || fallback.nonce || '';
    const ajaxUrl = ctx?.ajax_url || fallback.ajax_url || '';
    const userId = ctx?.user_id || fallback.user_id || '';
    const bookId = ctx?.book_id || fallback.book_id || '';

    const body = new URLSearchParams({
      action,
      nonce,
      user_id: userId,
      book_id: bookId,
      ...payload
    });
    const r = await fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body
    });
    let out; try { out = await r.json(); } catch { out = { success:false, message:'bad_json' }; }
    return out;
  }

  // === NUEVO: mantener y enviar session_id ===
  let sessionId = null;
  let durationSec = 0;

  // Start
  $startBtn?.addEventListener('click', async (e) => {
    e.preventDefault();
    if ($startBtn.disabled || !hasPages() || !canStartByStatus() || !validStart()) {
      updateStartEnabled();
      return;
    }
    sessionId = null; // reset
    const startPageVal = Number($startPage.value);
    const chapterVal   = ($chapter?.value || '').trim();

    setText($startView, String(startPageVal));
    setText($chapterView, chapterVal || '—');

    clockStartSeconds = secondsInHour(new Date());
    updateClock();
    setRunning();
    startTimer();

    try {
      const out = await api(ACTION_START, { start_page: startPageVal, chapter_name: chapterVal });
      if (out?.success) {
        // <<< guardar session_id del backend
        sessionId = out?.data?.session_id ?? null;
      } else {
        // Revertimos si backend bloquea (doble seguridad)
        stopTimer();
        setIdle();
        console.error('Start reading error', out);
        if (out?.message === 'bad_nonce' || out?.data?.message === 'bad_nonce') {
          alert(text('alert_session_expired', 'Session expired. Please refresh the page and try again.'));
        } else if (out?.message === 'pages_required' || out?.data?.message === 'pages_required') {
          alert(text('alert_pages_required', 'You must set total Pages to start a session.'));
        }
      }
    } catch (err) {
      console.error(err);
      stopTimer();
      setIdle();
      alert(text('alert_start_network', 'Network error while starting the session.'));
    }
  });

  // Stop
  $stopBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    durationSec = stopTimer();
    setStopped();
    if ($endPage) {
      $endPage.value = '';
      updateEndError();
      if ($saveBtn) $saveBtn.disabled = !validEnd();
    }
  });

  // Save
  $saveBtn?.addEventListener('click', async (e) => {
    e.preventDefault();
    if (!validEnd()) {
      alert(text('alert_end_page_required', 'Please enter an ending page before saving.'));
      return;
    }
    $saveBtn.disabled = true;

    try {
      const start = Number($startPage.value);
      const end   = Number($endPage.value);
      const chapterVal = ($chapter?.value || '').trim();

      const out = await api(ACTION_SAVE, {
        session_id: sessionId || '', // <<< enviar session_id para actualizar placeholder
        start_page: start,
        end_page: end,
        chapter_name: chapterVal,
        duration_sec: durationSec
      });

      if (out?.success) {
        const savedSessionId = out?.data?.session_id ?? sessionId ?? null;
        ensureFlashDatasetDefaults();
        updateFlashSessionId(savedSessionId);

        // Limpiar sessionId: la sesión quedó cerrada
        sessionId = null;

        // Actualiza “Last session page” en la UI
        const lastNode = root.querySelector('.prs-sr-last strong');
        if (lastNode) lastNode.textContent = String(end);

        // Prepara próxima sesión: start = end
        if ($startPage) $startPage.value = String(end);

        // Fancy texts
        const pages = Math.max(0, end - start);
        const pagesTxt = (pages === 1)
          ? text('pages_single', '1 page')
          : format('pages_multiple', '%d pages', pages);
        const mins  = Math.round(durationSec / 60);
        const minsTxt = durationSec < 60
          ? text('minutes_under_one', 'less than a minute')
          : (mins === 1
            ? text('minutes_single', '1 minute')
            : format('minutes_multiple', '%d minutes', mins));

        // Dejar UI lista y mostrar flash
        setIdle();
        showFlash(pagesTxt, minsTxt, 4200);
      } else {
        console.error('Save reading error', out);
        $saveBtn.disabled = false;
        if (out?.message === 'bad_nonce' || out?.data?.message === 'bad_nonce') {
          alert(text('alert_session_expired', 'Session expired. Please refresh the page and try again.'));
        } else {
          alert(text('alert_save_failed', 'Could not save the session.'));
        }
      }
    } catch (err) {
      console.error(err);
      $saveBtn.disabled = false;
      alert(text('alert_save_network', 'Network error while saving the session.'));
    }
  });

  // Estado inicial
  setIdle();
  if (existingBookId && existingBookId !== currentBookId && $startPage) {
    $startPage.value = '';
  }
  if (ctx.last_end_page && !$startPage.value) {
    $startPage.value = ctx.last_end_page;
  } else if (ctx.default_start_page && !$startPage.value) {
    $startPage.value = ctx.default_start_page;
  } else if (!$startPage.value) {
    $startPage.value = '1';
  }
  updateStartEnabled();
  if ($formWrap) {
    $formWrap.dataset.prsInit = '1';
    if (currentBookId) {
      $formWrap.dataset.prsBookId = currentBookId;
    }
  }
};

const prsStartReadingRun = () => {
  prsStartReadingInit();
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', prsStartReadingRun);
} else {
  prsStartReadingRun();
}

window.prsStartReadingInit = prsStartReadingInit;
