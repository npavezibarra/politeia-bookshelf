(function () {
  const overlay = document.getElementById('politeia-reading-plan-overlay');
  const openBtn = document.getElementById('politeia-open-reading-plan');
  if (!overlay || !openBtn) return;

  const qs = (selector) => overlay.querySelector(selector);
  const RP = window.PoliteiaReadingPlan || {};
  const PREFILL_BOOK = RP.prefillBook || null;
  const STRINGS = RP.strings || {};
  const t = (key, fallback) => (STRINGS && STRINGS[key]) ? STRINGS[key] : fallback;
  const format = (key, fallback, value, value2, value3, value4) => {
    const text = t(key, fallback);
    if (typeof value2 !== 'undefined') {
      const values = [value, value2, value3, value4];
      return values.reduce((result, item, idx) => {
        if (typeof item === 'undefined') return result;
        const pos = idx + 1;
        return result
          .replace(`%${pos}$s`, String(item))
          .replace(`%${pos}$d`, String(item));
      }, text);
    }
    return text.replace('%s', String(value)).replace('%d', String(value));
  };
  const escapeHtml = (value) => String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
  const roundTo = (value, decimals) => {
    const factor = Math.pow(10, decimals);
    return Math.round((Number(value) + Number.EPSILON) * factor) / factor;
  };
  const isDataUrl = (value) => typeof value === 'string' && value.startsWith('data:image');
  const isHttpUrl = (value) => typeof value === 'string' && /^https?:\/\//i.test(value);

  const formContainer = qs('#form-container');
  const summaryContainer = qs('#summary-container');
  const stepContent = qs('#step-content');
  const calendarGrid = qs('#calendar-grid');
  const listView = qs('#list-view');
  const successPanel = qs('#reading-plan-success-panel');
  const successTitle = qs('#reading-plan-success-title');
  const successNext = qs('#reading-plan-success-next');
  const successNote = qs('#reading-plan-success-note');
  const successStartBtn = qs('#reading-plan-start-session');
  const sessionModal = qs('#reading-plan-session-modal');
  const sessionModalClose = qs('.reading-plan-session-modal__close');
  const sessionContent = qs('#reading-plan-session-content');

  let suggestionTimer = null;
  let suggestionController = null;
  let suggestionListenerAttached = false;
  let closeSuggestions = null;

  const toId = (value) => String(value)
    .replace('<', 'lt-')
    .replace('~', 'plus-')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

  const normalizeKey = (value) => String(value || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

  const ACTIVE_PLANS = Array.isArray(RP.activePlans) ? RP.activePlans : [];
  if (ACTIVE_PLANS.length) {
    const debugList = ACTIVE_PLANS.map((item) => ({
      plan_id: item.plan_id || null,
      normalized_title: item.normalized_title || normalizeKey(item.title || ''),
    }));
    console.log('[ReadingPlan] Active plans:', debugList);
  }
  const splitAuthorList = (value) => String(value || '')
    .split(',')
    .map((item) => normalizeKey(item))
    .filter(Boolean);

  const hasActivePlanLocal = (title, author) => {
    if (!title || !author) return false;
    const tKey = normalizeKey(title);
    const aKey = normalizeKey(author);
    if (!tKey || !aKey) return false;
    return ACTIVE_PLANS.some((item) => {
      if (!item) return false;
      const itemTitle = item.normalized_title || item.title || '';
      if (normalizeKey(itemTitle) !== tKey) return false;
      return true;
    });
  };

  const pad2 = (value) => String(value).padStart(2, '0');
  const formatDateTime = (date, hours, minutes) => {
    const d = new Date(date);
    d.setHours(hours, minutes, 0, 0);
    return [
      d.getFullYear(),
      pad2(d.getMonth() + 1),
      pad2(d.getDate()),
    ].join('-') + ' ' + [pad2(d.getHours()), pad2(d.getMinutes()), '00'].join(':');
  };
  const formatDateTimeSeconds = (date, hours, minutes, seconds) => {
    const d = new Date(date);
    d.setHours(hours, minutes, seconds, 0);
    return [
      d.getFullYear(),
      pad2(d.getMonth() + 1),
      pad2(d.getDate()),
    ].join('-') + ' ' + [pad2(d.getHours()), pad2(d.getMinutes()), pad2(d.getSeconds())].join(':');
  };

  const formatSessionDate = (date) => {
    if (!date) return t('next_session_tbd', 'To be scheduled');
    const monthName = MONTH_NAMES[date.getMonth()];
    return `${date.getDate()} ${monthName} ${date.getFullYear()}`;
  };

  const assetState = {
    loading: null,
    loaded: false,
  };

  const recorderState = {
    loading: null,
    bookId: null,
    planId: null,
  };

  const createBookRecord = (payload) => {
    if (!RP.bookCreateUrl) return Promise.reject(new Error('missing_endpoint'));
    return fetch(RP.bookCreateUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': RP.nonce,
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    })
      .then(async (res) => {
        let data = {};
        let parseFailed = false;
        try {
          data = await res.json();
        } catch (err) {
          parseFailed = true;
          data = {};
        }
        if (!res.ok) {
          const err = new Error(data.error || 'request_failed');
          err.code = data.error || 'request_failed';
          throw err;
        }
        if (!data.success && !parseFailed) {
          console.warn('[ReadingPlan] Book create response missing success flag.', data);
        }
        return data.data || {};
      });
  };

  const createBookRecordAjax = (payload) => {
    const config = RP.bookCreateAjax || {};
    if (!config.ajaxUrl || !config.nonce) return Promise.reject(new Error('missing_ajax'));
    const formData = new FormData();
    formData.append('action', 'prs_reading_plan_add_book');
    formData.append('nonce', config.nonce);
    formData.append('title', payload.title || '');
    formData.append('author', payload.author || '');
    formData.append('pages', payload.pages ? String(payload.pages) : '');
    if (payload.cover_url) {
      formData.append('cover_url', payload.cover_url);
    }

    return fetch(config.ajaxUrl, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
    })
      .then(async (res) => {
        let data = {};
        try {
          data = await res.json();
        } catch (err) {
          data = {};
        }
        if (!res.ok || !data.success) {
          const err = new Error((data && data.error) || 'request_failed');
          err.code = (data && data.error) || 'request_failed';
          throw err;
        }
        return data.data || {};
      });
  };

  const saveCoverDataUrl = (dataUrl, mime, bookId, userBookId) => {
    const config = RP.coverUpload || {};
    if (!config.ajaxUrl || !config.nonce) return Promise.reject(new Error('missing_cover_config'));
    const formData = new FormData();
    formData.append('action', 'prs_save_cropped_cover');
    formData.append('_wpnonce', config.nonce);
    formData.append('book_id', String(bookId));
    formData.append('user_book_id', String(userBookId));
    formData.append('mime', mime || 'image/png');
    formData.append('data', dataUrl);

    return fetch(config.ajaxUrl, {
      method: 'POST',
      body: formData,
    })
      .then((res) => res.ok ? res.json() : null)
      .then((data) => {
        if (!data || !data.success || !data.data || !data.data.url) {
          throw new Error('cover_save_failed');
        }
        return data.data.url;
      });
  };

  const checkActivePlan = (title, author) => {
    const config = RP.bookCheckAjax || {};
    if (!config.ajaxUrl || !config.nonce) return Promise.resolve(false);
    const formData = new FormData();
    formData.append('action', 'prs_reading_plan_check_active');
    formData.append('nonce', config.nonce);
    formData.append('title', title || '');
    formData.append('author', author || '');

    return fetch(config.ajaxUrl, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
    })
      .then((res) => res.ok ? res.json() : null)
      .then((data) => {
        if (!data || !data.success || !data.data) return false;
        return !!data.data.active;
      })
      .catch(() => false);
  };

  const ensureScript = (id, src) => new Promise((resolve, reject) => {
    const existing = document.getElementById(id);
    if (existing) {
      if (existing.dataset.loaded === 'true') {
        resolve();
        return;
      }
      existing.addEventListener('load', resolve, { once: true });
      existing.addEventListener('error', reject, { once: true });
      return;
    }
    const script = document.createElement('script');
    script.id = id;
    script.src = src;
    script.async = true;
    script.onload = () => {
      script.dataset.loaded = 'true';
      console.log('[ReadingPlan] Script loaded:', id, src);
      resolve();
    };
    script.onerror = (err) => {
      console.error('[ReadingPlan] Script failed:', id, src, err);
      reject(err);
    };
    document.head.appendChild(script);
  });

  const ensureStyles = (id, href) => {
    if (document.getElementById(id)) return;
    const link = document.createElement('link');
    link.id = id;
    link.rel = 'stylesheet';
    link.href = href;
    document.head.appendChild(link);
    console.log('[ReadingPlan] Stylesheet added:', id, href);
  };

  const loadExternalAssets = () => {
    if (assetState.loaded) return Promise.resolve();
    if (assetState.loading) return assetState.loading;

    ensureStyles(
      'politeia-poppins',
      'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap'
    );

    assetState.loading = Promise.all([
      ensureScript('politeia-lucide', 'https://unpkg.com/lucide@latest'),
    ])
      .then(() => {
        assetState.loaded = true;
        const avatar = document.querySelector('.comment-author img');
        if (avatar) {
          console.log('[ReadingPlan] Avatar display after load:', getComputedStyle(avatar).display);
        } else {
          console.log('[ReadingPlan] Avatar not found for display check');
        }
      })
      .catch((err) => {
        console.warn('[ReadingPlan] External assets failed to load.', err);
      });

    return assetState.loading;
  };

  // --- CONFIGURACION DEL MODELO ---
  const TODAY = new Date();
  const K_GROWTH = 1.15;
  const MIN_PPS = 15;
  const SESSIONS_PER_PAGE = 10;
  const BENCHMARK_MIN_PER_PAGE = 1.5;
  const HABIT_CHALLENGE_DAYS = 48;

  const PAGE_RANGES_MAP = { "<100": 80, "200~": 200, "400~": 400, "600~": 600, "1000~": 1000 };

  const HABIT_INTENSITY_CONFIG = {
    light: {
      label: t('habit_light_label', 'Light'),
      startMin: 15,
      endMin: 30,
      startPg: 3,
      endPg: 10,
    },
    intense: {
      label: t('habit_intense_label', 'Intense'),
      startMin: 30,
      endMin: 60,
      startPg: 15,
      endPg: 30,
    },
  };

  // Update with database values if available
  if (RP.intensityConfig) {
    if (RP.intensityConfig.light) {
      HABIT_INTENSITY_CONFIG.light.startMin = RP.intensityConfig.light.start_minutes || HABIT_INTENSITY_CONFIG.light.startMin;
      HABIT_INTENSITY_CONFIG.light.endMin = RP.intensityConfig.light.end_minutes || HABIT_INTENSITY_CONFIG.light.endMin;
      HABIT_INTENSITY_CONFIG.light.startPg = RP.intensityConfig.light.start_pages || HABIT_INTENSITY_CONFIG.light.startPg;
      HABIT_INTENSITY_CONFIG.light.endPg = RP.intensityConfig.light.end_pages || HABIT_INTENSITY_CONFIG.light.endPg;
    }
    if (RP.intensityConfig.intense) {
      HABIT_INTENSITY_CONFIG.intense.startMin = RP.intensityConfig.intense.start_minutes || HABIT_INTENSITY_CONFIG.intense.startMin;
      HABIT_INTENSITY_CONFIG.intense.endMin = RP.intensityConfig.intense.end_minutes || HABIT_INTENSITY_CONFIG.intense.endMin;
      HABIT_INTENSITY_CONFIG.intense.startPg = RP.intensityConfig.intense.start_pages || HABIT_INTENSITY_CONFIG.intense.startPg;
      HABIT_INTENSITY_CONFIG.intense.endPg = RP.intensityConfig.intense.end_pages || HABIT_INTENSITY_CONFIG.intense.endPg;
    }
  }

  const MONTH_NAMES = (STRINGS.month_names && STRINGS.month_names.length)
    ? STRINGS.month_names
    : [
      'JANUARY',
      'FEBRUARY',
      'MARCH',
      'APRIL',
      'MAY',
      'JUNE',
      'JULY',
      'AUGUST',
      'SEPTEMBER',
      'OCTOBER',
      'NOVEMBER',
      'DECEMBER',
    ];

  const GOALS_DEF = [
    { id: 'complete_books', title: t('goal_complete_title', 'Finish a book'), description: t('goal_complete_desc', 'Finish specific books within a set time frame.'), icon: 'book-open' },
    { id: 'form_habit', title: t('goal_habit_title', 'Build a habit'), description: t('goal_habit_desc', 'Increase the frequency and consistency of your reading.'), icon: 'calendar' },
  ];

  const normalizePrefillBook = (data) => {
    if (!data || (!data.title && !data.bookId && !data.userBookId)) return null;
    const parsedPages = parseInt(data.pages, 10);
    return {
      id: data.bookId || Date.now(),
      bookId: data.bookId || null,
      userBookId: data.userBookId || null,
      title: data.title || '',
      author: data.author || '',
      pages: Number.isFinite(parsedPages) ? parsedPages : 0,
      cover: data.cover || '',
    };
  };

  const getInitialState = () => {
    const prefillBook = normalizePrefillBook(PREFILL_BOOK);
    return {
      mainStep: 1,
      isBaselineActive: false,
      baselineIndex: 0,
      subStep: 0,
      formData: {
        goals: [],
        baselines: {},
        books: prefillBook ? [prefillBook] : [],
        startPage: 1,
        pages_per_session: null,
        sessions_per_week: null,
        bookError: '',
        bookErrorLink: false,
        bookHasActivePlan: false,
      },
      calculatedPlan: { pps: 0, durationWeeks: 0, sessions: [], type: 'ccl' },
      currentMonthOffset: 0,
      currentViewMode: 'calendar',
      listCurrentPage: 0,
      acceptedPlanId: null,
      draggingHabitStart: false,
    };
  };

  let state = getInitialState();

  function render() {
    if (!stepContent) return;
    stepContent.innerHTML = '';
    updateProgressBar();

    if (state.mainStep === 1) {
      if (!state.isBaselineActive) renderStepGoals();
      else renderStepBaseline();
    } else if (state.mainStep === 2) {
      renderStepBooks();
    } else if (state.mainStep === 3) {
      renderStepStrategy();
    }

    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }
  }

  function updateProgressBar() {
    const progress = qs('#progress-dots');
    if (!progress) return;
    const dots = progress.children;
    Array.from(dots).forEach((dot, i) => {
      dot.classList.toggle('is-active', i < state.mainStep);
    });
  }

  function renderStepGoals() {
    stepContent.innerHTML = `
      <div class="goal-slide step-transition">
        <svg width="0" height="0" class="habit-defs" aria-hidden="true">
          <defs>
            <linearGradient id="gold-grad" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" style="stop-color:#8A6B1E;stop-opacity:1" />
              <stop offset="50%" style="stop-color:#C79F32;stop-opacity:1" />
              <stop offset="100%" style="stop-color:#E9D18A;stop-opacity:1" />
            </linearGradient>
          </defs>
        </svg>
        <h2 class="goal-title">${t('goal_prompt', 'What goal do you want to achieve?')}</h2>
        <p class="goal-subtitle">${t('goal_subtitle', 'Select your primary goal to begin.')}</p>
        <div id="goal-option-group" class="goal-option-group">
          ${GOALS_DEF.map((goal) => {
      const icon = goal.id === 'form_habit'
        ? `<svg width="50" height="50" viewBox="0 -960 960 960" fill="url(#gold-grad)" aria-hidden="true">
                  <path d="M80-80v-240h240v240H80Zm280 0v-240h240v240H360Zm280 0v-240h240v240H640ZM80-360v-240h240v240H80Zm280 0v-240h240v240H360Zm280 0v-240h240v240H640ZM80-640v-240h520v240H80Zm560 0v-240h240v240H640ZM240-240Zm200 0h80-80Zm280 0ZM240-440v-80 80Zm240-40Zm240 40v-80 80Zm0-280ZM160-160h80v-80h-80v80Zm280 0h80v-80h-80v80Zm280 0h80v-80h-80v80ZM160-440h80v-80h-80v80Zm280 0h80v-80h-80v80Zm280 0h80v-80h-80v80Zm0-280h80v-80h-80v80Z"/>
                </svg>`
        : `<svg width="50" height="50" viewBox="0 -960 960 960" fill="url(#gold-grad)" aria-hidden="true">
                  <path d="M270-80q-45 0-77.5-30.5T160-186v-558q0-38 23.5-68t61.5-38l395-78v640l-379 76q-9 2-15 9.5t-6 16.5q0 11 9 18.5t21 7.5h450v-640h80v720H270Zm90-233 200-39v-478l-200 39v478Zm-80 16v-478l-15 3q-11 2-18 9.5t-7 18.5v457q5-2 10.5-3.5T261-293l19-4Zm-40-472v482-482Z"/>
                </svg>`;
      return `<button type="button" id="goal-${goal.id}" data-goal="${goal.id}" class="goal-option-card">
              <div class="goal-icon-circle">${icon}</div>
              <span id="goal-title-${goal.id}" class="goal-option-text">${goal.title}</span>
              <p id="goal-desc-${goal.id}" class="goal-option-desc">${goal.description}</p>
            </button>`;
    }).join('')}
        </div>
      </div>`;

    stepContent.querySelectorAll('[data-goal]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-goal');
        state.formData.goals = [id];
        goToNext();
      });
    });
  }

  function renderStepBaseline() {
    const gid = state.formData.goals[0];

    if (gid === 'complete_books') {
      if (state.subStep === 0) {
        stepContent.innerHTML = `
          <div id="finish_book_slide_books_finished" class="finish_book_slide space-y-8 text-center step-transition">
            <span class="text-[#C79F32] font-medium text-[10px] uppercase tracking-widest">${t('baseline_label', 'Baseline')}</span>
            <h2 id="finish_book_baseline_title" class="text-2xl font-medium text-black mt-2 uppercase">${t('baseline_books_year', 'How many books did you finish in the last year?')}</h2>
            <div id="finish_book_baseline_options" class="grid grid-cols-5 gap-3">
              ${['0', '1', '2', '3', '4+'].map((n) => `
                <button type="button" id="finish_book_baseline_${toId(n)}" data-baseline="${n}" class="finish_book_baseline_btn aspect-square rounded-custom border-2 font-medium text-xl transition-all ${state.formData.baselines[gid]?.value === n
            ? 'bg-[#C79F32] border-[#C79F32] text-black'
            : 'border-[#A8A8A8] bg-[#F5F5F5] text-[#A8A8A8]'
          }">${n}</button>`).join('')}
            </div>
          </div>`;

        stepContent.querySelectorAll('[data-baseline]').forEach((btn) => {
          btn.addEventListener('click', () => {
            const val = btn.getAttribute('data-baseline');
            state.formData.baselines[gid] = { ...state.formData.baselines[gid], value: val };
            goToNext();
          });
        });
      } else {
        const count = parseInt(state.formData.baselines[gid]?.value, 10) || 0;
        const slots = count >= 4 ? 4 : count;
        stepContent.innerHTML = `
          <div id="finish_book_slide_book_pages" class="finish_book_slide space-y-6 step-transition">
            <div class="text-center">
              <h2 id="finish_book_pages_title" class="text-xl font-medium text-black uppercase">${t('baseline_book_pages', 'How many pages did the book have?')}</h2>
            </div>
            <div id="finish_book_details_container" class="space-y-4 max-h-[360px] overflow-y-auto pr-2 custom-scrollbar">
              ${Array.from({ length: slots }).map((_, i) => `
                <div class="finish_book_detail_card p-5 bg-[#F5F5F5] rounded-custom border border-[#A8A8A8]">
                  <p class="text-[10px] font-medium text-[#A8A8A8] mb-3 uppercase tracking-widest">${format('book_number', 'Book #%d', i + 1)}</p>
                  <div class="flex flex-wrap gap-2">
                    ${Object.keys(PAGE_RANGES_MAP).map((r) => `
                      <button type="button" id="finish_book_book_${i}_${toId(r)}" data-book-detail="${r}" data-book-index="${i}" class="finish_book_page_btn px-3 py-2 rounded-custom text-[10px] font-medium border-2 transition-all uppercase text-black ${state.formData.baselines[gid]?.details?.[i] === r
            ? 'bg-[#C79F32] border-[#C79F32] text-black'
            : 'bg-white border-[#A8A8A8]'
          }">${r}</button>`).join('')}
                  </div>
                </div>`).join('')}
            </div>
          </div>`;

        stepContent.querySelectorAll('[data-book-detail]').forEach((btn) => {
          btn.addEventListener('click', () => {
            const idx = parseInt(btn.getAttribute('data-book-index'), 10);
            const range = btn.getAttribute('data-book-detail');
            if (!state.formData.baselines[gid].details) state.formData.baselines[gid].details = [];
            state.formData.baselines[gid].details[idx] = range;
            const filled = state.formData.baselines[gid].details.filter(Boolean).length;
            if (filled === slots) {
              setTimeout(goToNext, 300);
            } else {
              render();
            }
          });
        });
      }
      return;
    }

    if (gid === 'form_habit') {
      const habitDefs = `
        <svg width="0" height="0" class="habit-defs" aria-hidden="true">
          <defs>
            <linearGradient id="gold-grad" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" style="stop-color:#8A6B1E;stop-opacity:1" />
              <stop offset="50%" style="stop-color:#C79F32;stop-opacity:1" />
              <stop offset="100%" style="stop-color:#E9D18A;stop-opacity:1" />
            </linearGradient>
          </defs>
        </svg>`;
      if (state.subStep === 0) {
        stepContent.innerHTML = `
          <div id="form_habit_slide_intro" class="form_habit_slide habit-slide habit-slide--intro step-transition">
            ${habitDefs}
            <div class="habit-icon">
              <svg width="80" height="80" viewBox="0 -960 960 960" fill="url(#gold-grad)" aria-hidden="true">
                <path d="M80-80v-240h240v240H80Zm280 0v-240h240v240H360Zm280 0v-240h240v240H640ZM80-360v-240h240v240H80Zm280 0v-240h240v240H360Zm280 0v-240h240v240H640ZM80-640v-240h520v240H80Zm560 0v-240h240v240H640ZM240-240Zm200 0h80-80Zm280 0ZM240-440v-80 80Zm240-40Zm240 40v-80 80Zm0-280ZM160-160h80v-80h-80v80Zm280 0h80v-80h-80v80Zm280 0h80v-80h-80v80ZM160-440h80v-80h-80v80Zm280 0h80v-80h-80v80Zm280 0h80v-80h-80v80Zm0-280h80v-80h-80v80Z"/>
              </svg>
            </div>
            <h2 id="form_habit_intro_title" class="habit-title habit-highlight">${t('habit_step1_title', 'In 48 days you will complete sessions')}</h2>
            <p id="form_habit_intro_subtitle" class="habit-subtitle">${t('habit_step1_body', 'One session every day for 48 straight days.')}</p>
            <button type="button" id="form_habit_intro_next_btn" class="habit-next-btn">${t('habit_step1_cta', 'Got it!')}</button>
          </div>`;
      } else if (state.subStep === 1) {
        stepContent.innerHTML = `
          <div id="form_habit_slide_progress" class="form_habit_slide habit-slide habit-slide--progress step-transition">
            ${habitDefs}
            <h2 id="form_habit_progress_title" class="habit-title">${t('habit_step2_title', 'Progressive growth')}</h2>
            <p id="form_habit_progress_subtitle" class="habit-subtitle">${t('habit_step2_body', 'The <span class="habit-highlight">session time</span> and the <span class="habit-highlight">number of pages</span> will increase gradually to challenge you.')}</p>
            <div id="form_habit_progress_graph" class="progression-graph habit-graph">
              <svg class="graph-svg" viewBox="0 0 500 250" preserveAspectRatio="none">
                <path class="path-line" d="M75,200 C150,200 175,125 250,125 C325,125 350,50 425,50" stroke="url(#gold-grad)" stroke-width="3" fill="none" stroke-linecap="round" />
              </svg>
              <div class="habit-step habit-step--start">
                <div class="habit-step-icon">
                  <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="url(#gold-grad)" stroke-width="2.2">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                  </svg>
                </div>
                <span class="habit-step-label">${t('habit_graph_step1_label', '15 min')}</span>
                <span class="habit-step-sublabel">${t('habit_graph_step1_sublabel', '5 pages')}</span>
              </div>
              <div class="habit-step habit-step--mid">
                <div class="habit-step-icon">
                  <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="url(#gold-grad)" stroke-width="2.2">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                  </svg>
                </div>
                <span class="habit-step-label">${t('habit_graph_step2_label', '18 min')}</span>
                <span class="habit-step-sublabel">${t('habit_graph_step2_sublabel', '6 pages')}</span>
              </div>
              <div class="habit-step habit-step--end">
                <div class="habit-step-icon">
                  <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="url(#gold-grad)" stroke-width="2.2">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                  </svg>
                </div>
                <span class="habit-step-label">${t('habit_graph_step3_label', '25 min')}</span>
                <span class="habit-step-sublabel">${t('habit_graph_step3_sublabel', '10 pages')}</span>
              </div>
            </div>
            <button type="button" id="form_habit_progress_next_btn" class="habit-next-btn">${t('habit_step2_cta', 'Next')}</button>
          </div>`;
      } else if (state.subStep === 2) {
        stepContent.innerHTML = `
          <div id="form_habit_slide_consistency" class="form_habit_slide habit-slide habit-slide--consistency step-transition">
            ${habitDefs}
            <div class="habit-icon">
              <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="url(#gold-grad)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                <path d="m9 12 2 2 4-4"></path>
              </svg>
            </div>
            <h2 id="form_habit_consistency_title" class="habit-title">${t('habit_step3_title', 'Consistency is everything')}</h2>
            <p id="form_habit_consistency_subtitle" class="habit-subtitle">${t('habit_step3_body', 'Missing one session is a warning. Missing <span class="habit-highlight">2 sessions</span> ends the plan.')}</p>
            <div class="habit-fail-label">
              <svg class="habit-fail-icon" viewBox="0 -960 960 960" fill="currentColor" aria-hidden="true">
                <path d="m40-120 440-760 440 760H40Zm138-80h604L480-720 178-200Zm302-40q17 0 28.5-11.5T520-280q0-17-11.5-28.5T480-320q-17 0-28.5 11.5T440-280q0 17 11.5 28.5T480-240Zm-40-120h80v-200h-80v200Zm40-100Z"/>
              </svg>
              <span>${t('habit_fail_label', 'Plan Failed')}</span>
            </div>
            <div id="form_habit_fail_sequence" class="habit-fail-seq" aria-hidden="true">
              <div class="habit-fail-step habit-fail-step--1">1</div>
              <div class="habit-fail-step habit-fail-step--2">2</div>
              <div class="habit-fail-step habit-fail-step--3">3</div>
            </div>
            <button type="button" id="form_habit_consistency_next_btn" class="habit-next-btn">${t('habit_step3_cta', 'Got it!')}</button>
          </div>`;
      } else if (state.subStep === 3) {
        stepContent.innerHTML = `
          <div id="form_habit_slide_library" class="form_habit_slide habit-slide habit-slide--library step-transition">
            ${habitDefs}
            <div class="habit-icon">
              <svg width="80" height="80" viewBox="0 -960 960 960" fill="url(#gold-grad)" aria-hidden="true">
                <path d="M120-440v-320q0-33 23.5-56.5T200-840h240v400H120Zm240-80Zm160-320h240q33 0 56.5 23.5T840-760v160H520v-240Zm0 720v-400h320v320q0 33-23.5 56.5T760-120H520ZM120-360h320v240H200q-33 0-56.5-23.5T120-200v-160Zm240 80Zm240-400Zm0 240Zm-400-80h160v-240H200v240Zm400-160h160v-80H600v80Zm0 240v240h160v-240H600ZM200-280v80h160v-80H200Z"/>
              </svg>
            </div>
            <h2 id="form_habit_library_title" class="habit-title">${t('habit_step4_title', 'Your library, your rules')}</h2>
            <p id="form_habit_library_subtitle" class="habit-subtitle">${t('habit_step4_body', 'Any reading session from any book in <span class="habit-highlight">My Library</span> that meets the system targets counts automatically.')}</p>
            <button type="button" id="form_habit_library_next_btn" class="habit-next-btn">${t('habit_step4_cta', 'Got it!')}</button>
          </div>`;
      } else if (state.subStep === 4) {
        const currentIntensity = state.formData.baselines[gid]?.intensity || '';
        const intensityDesc = (key) => {
          if (key === 'intense') return t('habit_intensity_intense_desc', 'We start at 30m and 15pg. We finish at 60m and 30pg.');
          return t('habit_intensity_light_desc', 'We start at 15m and 3pg. We finish at 30m and 10pg minimum.');
        };
        stepContent.innerHTML = `
          <div id="form_habit_slide_intensity" class="form_habit_slide habit-slide habit-slide--intensity step-transition">
            ${habitDefs}
            <h2 id="form_habit_intensity_title" class="habit-title">${t('habit_step5_title', 'Select intensity')}</h2>
            <div id="form_habit_intensity_group" class="habit-option-group">
              ${Object.keys(HABIT_INTENSITY_CONFIG).map((key) => {
          const config = HABIT_INTENSITY_CONFIG[key];
          const icon = key === 'intense'
            ? `<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="url(#gold-grad)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path>
                    </svg>`
            : `<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="url(#gold-grad)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M20.24 12.24a6 6 0 0 0-8.49-8.49L5 10.5V19h8.5z"></path>
                      <line x1="16" y1="8" x2="2" y2="22"></line>
                    </svg>`;
          return `
                  <button type="button" id="form_habit_intensity_${key}" data-habit-intensity="${key}" class="form_habit_intensity_card habit-option-card habit-option-card--intensity${currentIntensity === key ? ' is-selected' : ''}">
                    <span class="icon-circle-wrapper">${icon}</span>
                    <span class="habit-option-text habit-highlight">${config.label}</span>
                    <p class="intensity-desc">${intensityDesc(key)}</p>
                  </button>`;
        }).join('')}
            </div>
          </div>`;
      } else if (state.subStep === 5) {
        const startDateValue = state.formData.baselines.form_habit?.start_date;
        stepContent.innerHTML = `
          <div id="form_habit_slide_start_date" class="form_habit_slide habit-slide habit-slide--start step-transition">
            ${habitDefs}
            <p id="form_habit_start_small_text" class="habit-small-text">${t('habit_step6_small', 'Are you ready to dedicate the next 48 days to reading?')}</p>
            <h2 id="form_habit_start_title" class="habit-title-large">${t('habit_step6_title', 'Which day do you want to start?')}</h2>
            <div class="date-picker-grid" id="form_habit_date_picker"></div>
            <button type="button" id="form_habit_start_next_btn" class="habit-next-btn">${t('habit_step6_cta', 'Continue')}</button>
          </div>`;

        const picker = stepContent.querySelector('#form_habit_date_picker');
        if (picker) {
          const dayNames = [
            t('habit_day_sun', 'Sun'),
            t('habit_day_mon', 'Mon'),
            t('habit_day_tue', 'Tue'),
            t('habit_day_wed', 'Wed'),
            t('habit_day_thu', 'Thu'),
            t('habit_day_fri', 'Fri'),
            t('habit_day_sat', 'Sat'),
          ];
          const today = new Date();
          const selectedDate = startDateValue ? new Date(`${startDateValue}T00:00:00`) : today;
          const selectedKey = [
            selectedDate.getFullYear(),
            pad2(selectedDate.getMonth() + 1),
            pad2(selectedDate.getDate()),
          ].join('-');
          picker.innerHTML = '';
          for (let i = 0; i < 21; i += 1) {
            const targetDate = new Date(today);
            targetDate.setDate(today.getDate() + i);
            const key = [
              targetDate.getFullYear(),
              pad2(targetDate.getMonth() + 1),
              pad2(targetDate.getDate()),
            ].join('-');
            const cell = document.createElement('button');
            cell.type = 'button';
            cell.className = `date-cell${key === selectedKey ? ' selected' : ''}`;
            const dayName = document.createElement('span');
            dayName.className = 'day-name';
            dayName.textContent = i === 0 ? t('habit_day_today', 'Today') : dayNames[targetDate.getDay()];
            const dayNum = document.createElement('span');
            dayNum.className = 'day-num';
            dayNum.textContent = String(targetDate.getDate());
            cell.appendChild(dayName);
            cell.appendChild(dayNum);
            cell.addEventListener('click', () => {
              picker.querySelectorAll('.date-cell').forEach((item) => item.classList.remove('selected'));
              cell.classList.add('selected');
              state.formData.baselines.form_habit = state.formData.baselines.form_habit || {};
              state.formData.baselines.form_habit.start_date = key;
            });
            picker.appendChild(cell);
          }
        }
      }

      const nextBtn = stepContent.querySelector('.habit-next-btn');
      if (nextBtn && state.subStep < 4) {
        nextBtn.addEventListener('click', () => goToNext());
      } else if (nextBtn && state.subStep === 5) {
        nextBtn.addEventListener('click', () => goToNext());
      }

      stepContent.querySelectorAll('[data-habit-intensity]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const val = btn.getAttribute('data-habit-intensity');
          const config = HABIT_INTENSITY_CONFIG[val];
          state.formData.baselines.form_habit = {
            intensity: val,
            start_minutes: config?.startMin || 0,
            end_minutes: config?.endMin || 0,
            start_pages: config?.startPg || 0,
            end_pages: config?.endPg || 0,
            start_date: state.formData.baselines.form_habit?.start_date || undefined,
          };
          goToNext();
        });
      });

      if (state.subStep === 1) {
        const graph = stepContent.querySelector('.habit-graph');
        if (graph) {
          graph.classList.remove('is-animating');
          graph.offsetHeight;
          graph.classList.add('is-animating');
        }
      }
    }
  }

  function renderStepBooks() {
    const activeBook = state.formData.books && state.formData.books.length ? state.formData.books[0] : null;
    const hasBook = !!activeBook;
    const startPageValue = parseInt(state.formData.startPage, 10) || 1;
    const safeTitle = hasBook ? escapeHtml(activeBook?.title || '') : '';
    const safeAuthor = hasBook ? escapeHtml(activeBook?.author || '') : '';
    const safePages = hasBook ? escapeHtml(activeBook?.pages || '') : '';
    const coverBlock = hasBook && !activeBook?.cover ? `
        <div id="cover-upload-area" class="prs-cover-upload" role="button" tabindex="0" aria-label="${t('cover_upload_cta', 'upload cover')}">
          <input type="file" id="cover-file-input" class="prs-cover-input" accept="image/*" />
          <div id="cover-default" class="prs-cover-default">
            <p class="prs-cover-hint">${t('cover_drop_label', 'drag drop book cover')}</p>
            <button type="button" class="prs-cover-btn">${t('cover_upload_cta', 'upload cover')}</button>
            <p class="prs-cover-note">${t('cover_format_label', 'JPG or PNG')}</p>
          </div>
          <div id="cover-preview" class="prs-cover-preview" hidden>
            <img id="cover-image" alt="${t('cover_preview_alt', 'Book cover preview')}" />
            <div class="prs-cover-overlay">
              <button id="cover-remove" type="button" class="prs-cover-remove" aria-label="${t('cover_remove_label', 'Remove cover')}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="18" y1="6" x2="6" y2="18"></line>
                  <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
              </button>
              <span class="prs-cover-change">${t('cover_change_label', 'Change Cover')}</span>
            </div>
          </div>
        </div>
      ` : `
        <div class="book-cover-frame">
          <img class="book-cover-thumb" alt="${safeTitle}" src="${activeBook?.cover || ''}">
        </div>
      `;

    const bookDisplay = hasBook ? `
      <div class="reading-plan-book-display book-summary-row">
        <button type="button" id="remove-book-current" class="book-remove book-remove--corner" aria-label="${t('remove_book', 'Remove book')}">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 6h18"></path>
            <path d="M8 6V4h8v2"></path>
            <path d="M6 6l1 14h10l1-14"></path>
            <path d="M10 11v6"></path>
            <path d="M14 11v6"></path>
          </svg>
        </button>
        <div id="book_info_section" class="book-summary-details">
          <div class="book-summary-title">${safeTitle}</div>
          <div class="book-summary-author">${t('by_label', 'by')} ${safeAuthor || escapeHtml(t('unknown_author', 'Unknown author'))}</div>
          <div class="book-summary-pages">${safePages} ${escapeHtml(t('pages_label', 'pages'))}</div>
        </div>
        <div id="book_cover_section">
          ${coverBlock}
        </div>
      </div>` : '';
    const startingPageInput = hasBook ? `
      <div class="w-full max-w-xs mx-auto">
        <input
          id="starting-page-input"
          type="number"
          min="1"
          value="${startPageValue}"
          aria-label="${t('start_page_question', 'What page does the book content start on?')}"
          class="input-field w-full"
        />
      </div>
    ` : '';
    const addBookForm = `
      <div class="bg-[#F5F5F5] p-6 rounded-custom border border-[#A8A8A8] space-y-4">
        <div class="relative">
          <input id="new-book-title" type="text" placeholder="${t('book_title', 'Book title')}" autocomplete="off" class="input-field w-full p-3 border border-[#A8A8A8] rounded-custom outline-none text-sm bg-white font-medium focus:ring-1 focus:ring-[#C79F32] transition-all">
          <div id="reading-plan-title-suggestions" class="prs-add-book__suggestions" aria-hidden="true"></div>
        </div>
        <div class="space-y-4">
          <input id="new-book-author" type="text" placeholder="${t('author', 'Author')}" class="input-field w-full p-3 border border-[#A8A8A8] rounded-custom outline-none text-sm bg-white font-medium focus:ring-1 focus:ring-[#C79F32] transition-all">
          <input id="new-book-pages" type="number" placeholder="${t('pages', 'Pages')}" class="input-field w-full p-3 border border-[#A8A8A8] rounded-custom outline-none text-sm bg-white font-medium focus:ring-1 focus:ring-[#C79F32] transition-all">
        </div>
        <div class="space-y-3">
          <button type="button" id="add-book" class="w-full bg-[#C79F32] text-black py-4 rounded-custom hover:brightness-95 transition-all flex items-center justify-center gap-2 text-[10px] font-bold uppercase tracking-widest gold-gradient-text">
            + ${t('add_book', 'Add book')}
          </button>
        </div>
      </div>
    `;
    const nextButton = `
      <button type="button" id="next-step" class="w-full bg-black text-[#C79F32] py-4 rounded-custom hover:opacity-90 transition-all flex items-center justify-center gap-2 text-[10px] font-bold uppercase tracking-widest gold-gradient-text">
        ${t('next', 'Next')}
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="url(#gold-grad)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="m9 18 6-6-6-6"/>
        </svg>
      </button>
    `;
    const errorMessage = state.formData.bookError || '';
    const showErrorLink = state.formData.bookErrorLink && RP.myPlansUrl;
    const errorLink = showErrorLink
      ? `<a class="reading-plan-book-link" href="${RP.myPlansUrl}">${t('book_active_plan_link', 'Go to my plans')}</a>`
      : '';
    const errorBlock = `
      <p id="reading-plan-book-error" class="reading-plan-book-error${errorMessage ? '' : ' hidden'}">
        ${errorMessage ? errorMessage : ''}${errorLink}
      </p>
    `;
    stepContent.innerHTML = `
      <div class="space-y-6 step-transition">
        <svg width="0" height="0" style="position: absolute;">
          <defs>
            <linearGradient id="gold-grad" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" style="stop-color:#8A6B1E;stop-opacity:1" />
              <stop offset="50%" style="stop-color:#C79F32;stop-opacity:1" />
              <stop offset="100%" style="stop-color:#E9D18A;stop-opacity:1" />
            </linearGradient>
          </defs>
        </svg>
        <div class="text-center mb-6">
          <h2 class="text-2xl font-medium text-black uppercase tracking-tight">${hasBook ? t('start_page_question', 'What page does the book content start on?') : t('book_prompt', 'Which book do you want to read now?')}</h2>
          ${errorBlock}
        </div>
        ${hasBook ? startingPageInput : addBookForm}
        ${bookDisplay}
        ${hasBook ? nextButton : ''}
      </div>`;

    const coverUploadArea = stepContent.querySelector('#cover-upload-area');
    const coverFileInput = stepContent.querySelector('#cover-file-input');
    const coverDefault = stepContent.querySelector('#cover-default');
    const coverPreview = stepContent.querySelector('#cover-preview');
    const coverImageEl = stepContent.querySelector('#cover-image');
    const coverRemoveBtn = stepContent.querySelector('#cover-remove');

    if (coverUploadArea && coverFileInput) {
      const showPreview = (src) => {
        if (coverImageEl) coverImageEl.src = src;
        if (coverPreview) coverPreview.hidden = false;
        if (coverDefault) coverDefault.classList.add('hidden');
        coverUploadArea.classList.add('has-image');
      };

      const clearPreview = () => {
        if (coverImageEl) coverImageEl.src = '';
        if (coverPreview) coverPreview.hidden = true;
        if (coverDefault) coverDefault.classList.remove('hidden');
        coverUploadArea.classList.remove('has-image');
        if (coverFileInput) coverFileInput.value = '';
      };

      const applyFile = (file) => {
        if (!file || !file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = (event) => {
          const src = event.target?.result;
          if (!src) return;
          showPreview(src);
          const activeBook = state.formData.books[0];
          if (activeBook) {
            activeBook.cover = src;
            if (activeBook.bookId && activeBook.userBookId) {
              saveCoverDataUrl(src, file.type, activeBook.bookId, activeBook.userBookId)
                .then((url) => {
                  activeBook.cover = url;
                  if (coverImageEl) coverImageEl.src = url;
                })
                .catch(() => {
                  console.warn('[ReadingPlan] Cover upload failed.');
                });
            }
          }
        };
        reader.readAsDataURL(file);
      };

      coverUploadArea.addEventListener('click', () => coverFileInput.click());
      coverUploadArea.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          coverFileInput.click();
        }
      });
      coverFileInput.addEventListener('change', (event) => {
        const file = event.target.files[0];
        applyFile(file);
      });
      coverUploadArea.addEventListener('dragover', (event) => {
        event.preventDefault();
        coverUploadArea.classList.add('is-dragging');
      });
      coverUploadArea.addEventListener('dragleave', () => coverUploadArea.classList.remove('is-dragging'));
      coverUploadArea.addEventListener('drop', (event) => {
        event.preventDefault();
        coverUploadArea.classList.remove('is-dragging');
        const file = event.dataTransfer.files[0];
        applyFile(file);
      });
      if (coverRemoveBtn) {
        coverRemoveBtn.addEventListener('click', (event) => {
          event.stopPropagation();
          clearPreview();
          if (state.formData.books[0]) {
            state.formData.books[0].cover = '';
          }
        });
      }
    }

    const titleInput = stepContent.querySelector('#new-book-title');
    const authorInput = stepContent.querySelector('#new-book-author');
    const pagesInput = stepContent.querySelector('#new-book-pages');
    const startPageInput = stepContent.querySelector('#starting-page-input');
    const suggestions = stepContent.querySelector('#reading-plan-title-suggestions');
    const bookError = stepContent.querySelector('#reading-plan-book-error');
    let activePlanTimer = null;

    const updateBookError = (message, withLink) => {
      state.formData.bookError = message || '';
      state.formData.bookErrorLink = !!withLink;
      state.formData.bookHasActivePlan = !!withLink;
      if (!bookError) return;
      if (message) {
        const linkHtml = withLink && RP.myPlansUrl
          ? ` <a class="reading-plan-book-link" href="${RP.myPlansUrl}">${t('book_active_plan_link', 'Go to my plans')}</a>`
          : '';
        bookError.innerHTML = `${message}${linkHtml}`;
        bookError.classList.remove('hidden');
      } else {
        bookError.textContent = '';
        bookError.classList.add('hidden');
      }
    };

    const setAddButtonDisabled = (isDisabled) => {
      if (!addButton) return;
      addButton.disabled = isDisabled;
      addButton.classList.toggle('opacity-60', isDisabled);
      addButton.classList.toggle('pointer-events-none', isDisabled);
    };

    const clearBookError = () => {
      state.formData.bookError = '';
      state.formData.bookErrorLink = false;
      state.formData.bookHasActivePlan = false;
      if (bookError) {
        bookError.textContent = '';
        bookError.classList.add('hidden');
      }
      setAddButtonDisabled(false);
    };

    if (!hasBook && titleInput && suggestions) {
      let lastSuggestionItems = [];
      const clearSuggestions = () => {
        suggestions.innerHTML = '';
        suggestions.classList.remove('is-visible');
        suggestions.setAttribute('aria-hidden', 'true');
      };

      const selectSuggestion = (item) => {
        titleInput.value = item.title || '';
        if (authorInput) authorInput.value = item.author || '';
        titleInput.dataset.cover = item.cover || '';
        if (pagesInput) {
          let pagesValue = item.pages || '';
          if (!pagesValue) {
            const matchKey = `${(item.title || '').toLowerCase()}|${(item.author || '').toLowerCase()}`;
            const fallback = lastSuggestionItems.find((candidate) => {
              const key = `${(candidate.title || '').toLowerCase()}|${(candidate.author || '').toLowerCase()}`;
              return key === matchKey && candidate.pages;
            });
            if (fallback && fallback.pages) {
              pagesValue = fallback.pages;
            }
          }
          if (pagesValue) pagesInput.value = pagesValue;
        }
        clearSuggestions();
        scheduleActivePlanCheck();
        updateAddBookVisibility();
      };

      const renderSuggestions = (items) => {
        if (!items.length) {
          clearSuggestions();
          return;
        }
        lastSuggestionItems = items.slice();
        suggestions.innerHTML = '';
        items.forEach((item) => {
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'prs-add-book__suggestion';
          const titleLine = document.createElement('div');
          titleLine.className = 'prs-add-book__suggestion-title';
          titleLine.textContent = item.title || '';
          const authorLine = document.createElement('div');
          authorLine.className = 'prs-add-book__suggestion-author';
          authorLine.textContent = item.author || '';
          button.appendChild(titleLine);
          if (item.author) button.appendChild(authorLine);
          button.addEventListener('click', () => selectSuggestion(item));
          suggestions.appendChild(button);
        });
        suggestions.classList.add('is-visible');
        suggestions.setAttribute('aria-hidden', 'false');
      };

      const fetchCanonicalSuggestions = (query, controller) => {
        const config = window.PoliteiaReadingPlan && window.PoliteiaReadingPlan.autocomplete ? window.PoliteiaReadingPlan.autocomplete : null;
        if (!config || !config.ajaxUrl || !config.nonce) return Promise.resolve([]);
        const params = new URLSearchParams();
        params.append('action', 'prs_canonical_title_search');
        params.append('nonce', config.nonce);
        params.append('query', query);
        const fetchOptions = {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: params.toString(),
        };
        if (controller) fetchOptions.signal = controller.signal;
        return fetch(config.ajaxUrl, fetchOptions)
          .then((res) => res.ok ? res.json() : null)
          .then((data) => {
            const items = data && data.items ? data.items : [];
            return items.map((item) => ({
              title: item.title || '',
              author: (item.authors && item.authors.length) ? item.authors.join(', ') : '',
              pages: item.pages ? String(item.pages) : '',
              cover: item.cover || '',
              source: 'canonical',
            })).filter((item) => item.title);
          })
          .catch((err) => {
            if (err && err.name === 'AbortError') throw err;
            return [];
          });
      };

      const fetchGoogleSuggestions = (query, controller) => {
        const url = 'https://www.googleapis.com/books/v1/volumes?' + [
          'q=' + encodeURIComponent('intitle:' + query),
          'maxResults=6',
          'printType=books',
          'orderBy=relevance',
          'fields=items(volumeInfo/title,volumeInfo/authors,volumeInfo/pageCount,volumeInfo/imageLinks)',
        ].join('&');
        const fetchOptions = {};
        if (controller) fetchOptions.signal = controller.signal;
        return fetch(url, fetchOptions)
          .then((res) => res.ok ? res.json() : null)
          .then((data) => {
            const items = data && data.items ? data.items : [];
            return items.map((doc) => {
              const info = doc.volumeInfo || {};
              const title = info.title ? String(info.title).trim() : '';
              const authors = Array.isArray(info.authors) ? info.authors.filter(Boolean) : [];
              return {
                title,
                author: authors.join(', '),
                pages: info.pageCount ? String(info.pageCount) : '',
                cover: info.imageLinks && info.imageLinks.thumbnail ? info.imageLinks.thumbnail : '',
                source: 'googlebooks',
              };
            }).filter((item) => item.title);
          })
          .catch((err) => {
            if (err && err.name === 'AbortError') throw err;
            return [];
          });
      };

      const fetchOpenLibrarySuggestions = (query, controller) => {
        const url = 'https://openlibrary.org/search.json?' + [
          'title=' + encodeURIComponent(query),
          'limit=6',
          'fields=title,author_name,number_of_pages_median,cover_i',
        ].join('&');
        const fetchOptions = {};
        if (controller) fetchOptions.signal = controller.signal;
        return fetch(url, fetchOptions)
          .then((res) => res.ok ? res.json() : null)
          .then((data) => {
            const docs = data && data.docs ? data.docs : [];
            return docs.map((doc) => {
              const title = doc.title ? String(doc.title).trim() : '';
              const authors = Array.isArray(doc.author_name) ? doc.author_name.filter(Boolean) : [];
              const coverId = doc.cover_i ? String(doc.cover_i).trim() : '';
              return {
                title,
                author: authors.join(', '),
                pages: doc.number_of_pages_median ? String(doc.number_of_pages_median) : '',
                cover: coverId ? `https://covers.openlibrary.org/b/id/${coverId}-M.jpg` : '',
                source: 'openlibrary',
              };
            }).filter((item) => item.title);
          })
          .catch((err) => {
            if (err && err.name === 'AbortError') throw err;
            return [];
          });
      };

      const runSuggestions = (query) => {
        if (!query || query.length < 3) {
          clearSuggestions();
          return;
        }
        if (suggestionController) suggestionController.abort();
        suggestionController = new AbortController();
        Promise.all([
          fetchCanonicalSuggestions(query, suggestionController),
          fetchGoogleSuggestions(query, suggestionController),
          fetchOpenLibrarySuggestions(query, suggestionController),
        ])
          .then(([canonicalItems, googleItems, openItems]) => {
            const combined = []
              .concat(canonicalItems || [])
              .concat(googleItems || [])
              .concat(openItems || []);
            const seen = new Set();
            const unique = combined.filter((item) => {
              const key = `${item.title}|${item.author}`;
              if (seen.has(key)) return false;
              seen.add(key);
              return true;
            });
            renderSuggestions(unique.slice(0, 8));
          })
          .catch((err) => {
            if (err && err.name === 'AbortError') return;
            clearSuggestions();
          });
      };

      titleInput.addEventListener('input', (e) => {
        const query = e.target.value.trim();
        clearBookError();
        titleInput.dataset.cover = '';
        if (suggestionTimer) clearTimeout(suggestionTimer);
        suggestionTimer = setTimeout(() => runSuggestions(query), 250);
      });

      titleInput.addEventListener('focus', (e) => {
        if (e.target.value.trim().length >= 3) {
          runSuggestions(e.target.value.trim());
        }
      });

      closeSuggestions = clearSuggestions;
      if (!suggestionListenerAttached) {
        suggestionListenerAttached = true;
        document.addEventListener('click', (event) => {
          if (!event.target.closest('#new-book-title') && !event.target.closest('#reading-plan-title-suggestions')) {
            if (typeof closeSuggestions === 'function') {
              closeSuggestions();
            }
          }
        });
      }
    }

    const addButton = stepContent.querySelector('#add-book');
    const updateAddBookVisibility = () => {
      if (!addButton || !titleInput || !authorInput || !pagesInput) return;
      const titleValue = titleInput.value.trim();
      const authorValue = authorInput.value.trim();
      const pagesValue = parseInt(pagesInput.value, 10);
      const hasFields = !!titleValue && !!authorValue && !!pagesValue;
      const shouldShow = hasFields && !state.formData.bookHasActivePlan;
      addButton.classList.toggle('hidden', !shouldShow);
    };

    const scheduleActivePlanCheck = () => {
      if (!titleInput || !authorInput) return;
      const titleValue = titleInput.value.trim();
      const authorValue = authorInput.value.trim();
      if (!titleValue || !authorValue) {
        updateBookError('', false);
        updateAddBookVisibility();
        return;
      }
      if (hasActivePlanLocal(titleValue, authorValue)) {
        updateBookError(
          t('book_active_plan_notice', 'You already have an active plan for this book.'),
          true
        );
        setAddButtonDisabled(true);
        updateAddBookVisibility();
        return;
      }
      if (activePlanTimer) clearTimeout(activePlanTimer);
      activePlanTimer = setTimeout(() => {
        checkActivePlan(titleValue, authorValue)
          .then((active) => {
            if (active) {
              updateBookError(
                t('book_active_plan_notice', 'You already have an active plan for this book.'),
                true
              );
              setAddButtonDisabled(true);
            } else {
              updateBookError('', false);
            }
            updateAddBookVisibility();
          });
      }, 200);
    };

    if (!hasBook && addButton) {
      updateAddBookVisibility();
      addButton.addEventListener('click', () => {
        const titleValue = titleInput?.value?.trim();
        const authorValue = authorInput?.value?.trim();
        const pagesValue = parseInt(pagesInput?.value, 10);
        const cover = titleInput?.dataset?.cover ? titleInput.dataset.cover : '';
        const coverUrl = isHttpUrl(cover) ? cover : '';
        if (state.formData.bookErrorLink) {
          updateBookError(
            t('book_active_plan_notice', 'You already have an active plan for this book.'),
            true
          );
          return;
        }
        clearBookError();
        const missingFields = [];
        if (!titleValue) missingFields.push(titleInput);
        if (!authorValue) missingFields.push(authorInput);
        if (!pagesValue) missingFields.push(pagesInput);
        if (missingFields.length) {
          missingFields.forEach((field) => {
            if (!field) return;
            field.classList.remove('input-shake');
            void field.offsetWidth;
            field.classList.add('input-shake');
          });
          return;
        }
        if (titleValue && authorValue && pagesValue) {
          addButton.disabled = true;
          const payload = { title: titleValue, author: authorValue, pages: pagesValue, cover_url: coverUrl };
          createBookRecord(payload)
            .catch((err) => {
              if (err && err.code === 'active_plan') {
                throw err;
              }
              return createBookRecordAjax(payload);
            })
            .then((data) => {
              const bookId = data.book_id || null;
              const userBookId = data.user_book_id || null;
              const resolvedCover = data.cover_url || cover;
              state.formData.books = [{
                id: bookId || Date.now(),
                bookId,
                userBookId,
                title: titleValue,
                author: authorValue,
                pages: pagesValue,
                cover: resolvedCover,
              }];
              // Only set default startPage if not already set
              if (!state.formData.startPage || state.formData.startPage < 1) {
                state.formData.startPage = 1;
              }
              state.formData.bookError = '';
              render();
            })
            .catch((err) => {
              if (err && err.code === 'active_plan') {
                updateBookError(
                  t('book_active_plan_notice', 'You already have an active plan for this book.'),
                  true
                );
                setAddButtonDisabled(true);
                return;
              }
              [titleInput, authorInput, pagesInput].forEach((field) => {
                if (!field) return;
                field.classList.remove('input-shake');
                void field.offsetWidth;
                field.classList.add('input-shake');
              });
            })
            .finally(() => {
              if (addButton) addButton.disabled = false;
            });
        }
      });
    }

    const nextBtn = stepContent.querySelector('#next-step');
    if (nextBtn) {
      nextBtn.addEventListener('click', () => goToNext());
    }

    const removeBtn = stepContent.querySelector('#remove-book-current');
    if (removeBtn) {
      removeBtn.addEventListener('click', () => {
        state.formData.books = [];
        state.formData.startPage = 1;
        state.formData.bookError = '';
        state.formData.bookErrorLink = false;
        render();
      });
    }

    if (startPageInput) {
      startPageInput.addEventListener('input', (e) => {
        const activeBook = state.formData.books && state.formData.books.length ? state.formData.books[0] : null;
        const maxPages = parseInt(activeBook?.pages, 10) || 1;
        let value = parseInt(e.target.value, 10);
        if (!Number.isFinite(value) || value < 1) value = 1;
        if (value > maxPages) value = maxPages;
        state.formData.startPage = value;
        startPageInput.value = value;
      });
    }

    if (authorInput) {
      authorInput.addEventListener('input', () => {
        clearBookError();
        scheduleActivePlanCheck();
        updateAddBookVisibility();
      });
    }
    if (pagesInput) {
      pagesInput.addEventListener('input', () => {
        clearBookError();
        updateAddBookVisibility();
      });
    }
    if (titleInput) {
      titleInput.addEventListener('input', () => {
        if (titleInput.value.trim() === '') {
          if (authorInput) authorInput.value = '';
          if (pagesInput) pagesInput.value = '';
          clearBookError();
        }
        updateAddBookVisibility();
      });
      titleInput.addEventListener('blur', scheduleActivePlanCheck);
    }
    if (authorInput) {
      authorInput.addEventListener('blur', scheduleActivePlanCheck);
    }

    if (titleInput && authorInput) {
      const hasPrefill = titleInput.value.trim() && authorInput.value.trim();
      if (hasPrefill) {
        scheduleActivePlanCheck();
        updateAddBookVisibility();
      }
    }
  }

  function renderStepStrategy() {
    const subStep = state.subStep || 0;

    // Sub-step 0: Pages per session
    if (subStep === 0) {
      const pagesOptions = RP.pagesPerSessionOptions || [15, 30, 60];
      const currentPages = state.formData.pages_per_session;

      const getDesc = (pages) => {
        if (pages === 15) return t('pages_per_session_15_desc', 'Light reading load');
        if (pages === 30) return t('pages_per_session_30_desc', 'Moderate reading load');
        if (pages === 60) return t('pages_per_session_60_desc', 'Intensive reading load');
        return `${pages} ${t('pages_label_short', 'pages')}`;
      };

      stepContent.innerHTML = `
        <div id="finish_book_slide_pages_per_session" class="finish_book_slide pages-per-session-slide step-transition">
          <svg width="0" height="0" style="position: absolute;">
            <defs>
              <linearGradient id="gold-grad" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" style="stop-color:#8A6B1E;stop-opacity:1" />
                <stop offset="50%" style="stop-color:#C79F32;stop-opacity:1" />
                <stop offset="100%" style="stop-color:#E9D18A;stop-opacity:1" />
              </linearGradient>
            </defs>
          </svg>
          <div class="text-center mb-6">
            <h2 id="finish_book_pages_per_session_title" class="habit-title">${t('pages_per_session_prompt', 'How many pages do you want to read per session?')}</h2>
          </div>
          <div id="finish_book_pages_per_session_group" class="habit-option-group">
            ${pagesOptions.map((pages) => `
              <button type="button" 
                      id="finish_book_pages_${pages}" 
                      data-pages-per-session="${pages}" 
                      class="pages-per-session-card habit-option-card ${currentPages === pages ? 'is-selected' : ''}">
                <div class="icon-circle-wrapper">
                  <svg width="44" height="44" viewBox="0 -960 960 960" fill="url(#gold-grad)" aria-hidden="true">
                    <path d="M270-80q-45 0-77.5-30.5T160-186v-558q0-38 23.5-68t61.5-38l395-78v640l-379 76q-9 2-15 9.5t-6 16.5q0 11 9 18.5t21 7.5h450v-640h80v720H270Zm90-233 200-39v-478l-200 39v478Zm-80 16v-478l-15 3q-11 2-18 9.5t-7 18.5v457q5-2 10.5-3.5T261-293l19-4Zm-40-472v482-482Z"/>
                  </svg>
                </div>
                <span class="habit-highlight" style="font-size: 1.3rem; margin-bottom: 4px;">${pages} ${t('pages_label_short', 'pages')}</span>
                <p class="intensity-desc">${getDesc(pages)}</p>
              </button>`).join('')}
          </div>
        </div>`;

      stepContent.querySelectorAll('[data-pages-per-session]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const pages = parseInt(btn.getAttribute('data-pages-per-session'));
          state.formData.pages_per_session = pages;
          state.subStep = 1;
          render();
        });
      });
    }
    // Sub-step 1: Sessions per week
    else if (subStep === 1) {
      const sessionsOptions = RP.sessionsPerWeekOptions || [3, 5, 7];
      const currentSessions = state.formData.sessions_per_week;

      const getDesc = (sessions) => {
        if (sessions === 3) return t('sessions_per_week_3_desc', '3 days per week');
        if (sessions === 5) return t('sessions_per_week_5_desc', '5 days per week');
        if (sessions === 7) return t('sessions_per_week_7_desc', 'Daily reading');
        return `${sessions} ${t('days_label', 'days')}`;
      };

      stepContent.innerHTML = `
        <div id="finish_book_slide_sessions_per_week" class="finish_book_slide sessions-per-week-slide step-transition">
          <svg width="0" height="0" style="position: absolute;">
            <defs>
              <linearGradient id="gold-grad" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" style="stop-color:#8A6B1E;stop-opacity:1" />
                <stop offset="50%" style="stop-color:#C79F32;stop-opacity:1" />
                <stop offset="100%" style="stop-color:#E9D18A;stop-opacity:1" />
              </linearGradient>
            </defs>
          </svg>
          <div class="text-center mb-6">
            <h2 id="finish_book_sessions_per_week_title" class="habit-title">${t('sessions_per_week_prompt', 'How many days per week do you want to read?')}</h2>
          </div>
          <div id="finish_book_sessions_per_week_group" class="habit-option-group">
            ${sessionsOptions.map((sessions) => `
              <button type="button" 
                      id="finish_book_sessions_${sessions}" 
                      data-sessions-per-week="${sessions}" 
                      class="sessions-per-week-card habit-option-card ${currentSessions === sessions ? 'is-selected' : ''}">
                <div class="icon-circle-wrapper">
                  <svg width="44" height="44" viewBox="0 -960 960 960" fill="url(#gold-grad)" aria-hidden="true">
                    <path d="M80-80v-240h240v240H80Zm280 0v-240h240v240H360Zm280 0v-240h240v240H640ZM80-360v-240h240v240H80Zm280 0v-240h240v240H360Zm280 0v-240h240v240H640ZM80-640v-240h520v240H80Zm560 0v-240h240v240H640Z"/>
                  </svg>
                </div>
                <span class="habit-highlight" style="font-size: 1.3rem; margin-bottom: 4px;">${sessions} ${t('days_label', 'days')}</span>
                <p class="intensity-desc">${getDesc(sessions)}</p>
              </button>`).join('')}
          </div>
        </div>`;

      stepContent.querySelectorAll('[data-sessions-per-week]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const sessions = parseInt(btn.getAttribute('data-sessions-per-week'));
          state.formData.sessions_per_week = sessions;
          goToNext();
        });
      });
    }
  }

  function goToNext() {
    const gid = state.formData.goals[0];
    if (state.mainStep === 1) {
      if (!state.isBaselineActive) {
        if (gid === 'complete_books') {
          state.formData.baselines.complete_books = { value: '0', details: [] };
          state.mainStep = 2;
          state.isBaselineActive = false;
          state.subStep = 0;
          render();
          return;
        }
        state.isBaselineActive = true;
        state.subStep = 0;
      } else if (gid === 'complete_books' && state.subStep === 0) {
        if (parseInt(state.formData.baselines[gid]?.value, 10) > 0) {
          state.subStep = 1;
        } else {
          state.mainStep = 2;
          state.isBaselineActive = false;
        }
      } else if (gid === 'form_habit' && state.subStep < 5) {
        state.subStep += 1;
      } else if (gid === 'form_habit' && state.subStep === 5) {
        state.isBaselineActive = false;
        calculatePlan();
        return;
      } else {
        state.mainStep = 2;
        state.isBaselineActive = false;
      }
    } else if (state.mainStep === 2) {
      state.mainStep = 3;
      state.subStep = 0; // Reset substep for strategy selection
    } else if (state.mainStep === 3) {
      // Strategy step has substeps (0: pages_per_session, 1: sessions_per_week)
      // When sessions_per_week is selected, goToNext is called and we calculate
      calculatePlan();
      return;
    } else {
      calculatePlan();
      return;
    }
    render();
  }

  function calculatePlan() {
    const gid = state.formData.goals[0];
    if (gid === 'complete_books') calculateCCLPlan();
    else calculateHabitPlan();
  }

  function calculateHabitPlan() {
    const data = state.formData;
    const intensityKey = data.baselines.form_habit?.intensity || 'light';
    const config = HABIT_INTENSITY_CONFIG[intensityKey] || HABIT_INTENSITY_CONFIG.light;
    const startDateValue = data.baselines.form_habit?.start_date;
    const baseDate = startDateValue ? new Date(`${startDateValue}T00:00:00`) : new Date(TODAY);
    const totalDays = HABIT_CHALLENGE_DAYS;
    const steps = Math.max(1, totalDays - 1);
    const minutesStep = (config.endMin - config.startMin) / steps;
    const pagesStep = (config.endPg - config.startPg) / steps;

    const sessions = Array.from({ length: totalDays }).map((_, i) => {
      const date = new Date(baseDate);
      date.setDate(baseDate.getDate() + i);
      const expectedMinutes = i === steps
        ? config.endMin
        : roundTo(config.startMin + (i * minutesStep), 2);
      const expectedPages = i === steps
        ? config.endPg
        : roundTo(config.startPg + (i * pagesStep), 2);
      return {
        date,
        order: i + 1,
        expectedMinutes,
        expectedPages,
      };
    });

    state.calculatedPlan = {
      durationDays: totalDays,
      sessions,
      type: 'habit',
      habitConfig: {
        intensity: intensityKey,
        startMin: config.startMin,
        endMin: config.endMin,
        startPg: config.startPg,
        endPg: config.endPg,
      },
    };

    qs('#propuesta-tipo-label').innerText = t('habit_48_title', '48-DAY HABIT CHALLENGE');
    qs('#propuesta-plan-titulo').innerText = format('habit_intensity_label', 'Intensity: %s', config.label);
    qs('#propuesta-sub-label').innerText = t('habit_growth_label', 'Daily targets grow linearly.');
    qs('#propuesta-carga').innerText = format(
      'habit_range_label',
      'Targets: %1$s–%2$s min / %3$s–%4$s pages',
      config.startMin,
      config.endMin,
      config.startPg,
      config.endPg
    );
    qs('#propuesta-duracion').innerText = format('habit_duration_days', 'Duration: %s days', totalDays);

    formContainer.classList.add('hidden');
    summaryContainer.classList.remove('hidden');
    state.currentMonthOffset = (baseDate.getFullYear() - TODAY.getFullYear()) * 12 + (baseDate.getMonth() - TODAY.getMonth());
    state.listCurrentPage = 0;
    renderCalendar();
  }

  function calculateCCLPlan() {
    const data = state.formData;
    const targetBook = data.books[0];
    const startingPage = getStartingPage(targetBook);
    const totalPages = parseInt(targetBook?.pages, 10) || 0;

    // Simple, direct calculation based on user's selections
    const pagesToRead = totalPages - (startingPage - 1);
    const pagesPerSession = data.pages_per_session || 30; // User's selection
    const sessionsPerWeek = data.sessions_per_week || 5;  // User's selection

    const totalSessions = pagesToRead > 0 ? Math.ceil(pagesToRead / pagesPerSession) : 0;
    const totalWeeks = totalSessions > 0 ? Math.ceil(totalSessions / sessionsPerWeek) : 0;

    state.calculatedPlan = {
      pps: pagesPerSession,
      durationWeeks: totalWeeks,
      sessions: generateSessions(totalSessions, sessionsPerWeek),
      type: 'ccl',
      totalPages: pagesToRead,
    };

    qs('#propuesta-tipo-label').innerText = t('realistic_plan', 'REALISTIC READING PLAN');
    qs('#propuesta-plan-titulo').innerText = targetBook.title;
    qs('#propuesta-sub-label').innerText = t('monthly_plan', 'MONTHLY PLAN');
    qs('#propuesta-carga').innerText = format('suggested_load', 'Suggested Load: %s PAGES / SESSION', pagesPerSession);
    qs('#propuesta-duracion').innerText = format('estimated_duration', 'Estimated duration: %s weeks', totalWeeks);

    formContainer.classList.add('hidden');
    summaryContainer.classList.remove('hidden');
    state.currentMonthOffset = 0;
    state.listCurrentPage = 0;
    renderCalendar();
  }

  function generateHabitSessions(total, perWeek) {
    const sessions = [];
    const dayPattern = [0, 1, 2, 3, 4, 5];
    let i = 0;
    while (sessions.length < total) {
      const weekNum = Math.floor(i / perWeek);
      const dayOffset = (weekNum * 7) + dayPattern[i % perWeek];
      const date = new Date(TODAY);
      date.setDate(TODAY.getDate() + dayOffset);
      if (date >= TODAY) {
        sessions.push({ date, order: sessions.length + 1 });
      }
      i += 1;
    }
    return sessions;
  }

  function generateSessions(total, perWeek) {
    const sessions = [];
    const patterns = {
      2: [0, 3],
      3: [0, 2, 4],
      4: [0, 2, 4, 6],
      5: [0, 1, 2, 4, 5],
      6: [0, 1, 2, 3, 4, 5],
      7: [0, 1, 2, 3, 4, 5, 6],
    };
    const myPattern = patterns[perWeek];
    let i = 0;
    while (sessions.length < total) {
      const weekNum = Math.floor(i / perWeek);
      const dayOffset = (weekNum * 7) + myPattern[i % perWeek];
      const date = new Date(TODAY);
      date.setDate(TODAY.getDate() + dayOffset);
      if (date >= TODAY) {
        sessions.push({ date, order: sessions.length + 1 });
      }
      i += 1;
    }
    return sessions;
  }

  function normalizeSessionOrder() {
    const sorted = state.calculatedPlan.sessions.slice().sort((a, b) => a.date - b.date);
    sorted.forEach((session, idx) => {
      session.order = idx + 1;
    });
    state.calculatedPlan.sessions = sorted;
  }

  function buildBaselineMetrics() {
    const metrics = {};
    const baselines = state.formData.baselines || {};
    if (baselines.complete_books?.value) {
      metrics.books_per_year = String(baselines.complete_books.value);
    }
    if (baselines.complete_books?.details?.length) {
      metrics.book_pages_ranges = JSON.stringify(baselines.complete_books.details);
    }
    if (baselines.form_habit?.value) {
      metrics.sessions_per_month = String(baselines.form_habit.value);
    }
    if (baselines.form_habit?.intensity) {
      metrics.habit_intensity = String(baselines.form_habit.intensity);
    }
    if (baselines.form_habit?.start_minutes) {
      metrics.habit_start_minutes = String(baselines.form_habit.start_minutes);
    }
    if (baselines.form_habit?.end_minutes) {
      metrics.habit_end_minutes = String(baselines.form_habit.end_minutes);
    }
    if (baselines.form_habit?.start_pages) {
      metrics.habit_start_pages = String(baselines.form_habit.start_pages);
    }
    if (baselines.form_habit?.end_pages) {
      metrics.habit_end_pages = String(baselines.form_habit.end_pages);
    }
    return metrics;
  }

  function buildGoals() {
    const goals = [];
    const selectedGoals = state.formData.goals || [];
    const activeBook = state.formData.books && state.formData.books.length ? state.formData.books[0] : null;

    if (selectedGoals.includes('complete_books')) {
      const startingPage = getStartingPage(activeBook);
      const bookTotalPages = parseInt(activeBook?.pages, 10) || 0;
      // Calculate pages to read in the plan (not book's total pages)
      const pagesToRead = bookTotalPages > 0 ? (bookTotalPages - (startingPage - 1)) : 0;
      const targetValue = pagesToRead > 0 ? pagesToRead : (parseInt(state.calculatedPlan.pps, 10) || 0);
      const metric = pagesToRead > 0 ? 'pages_total' : 'pages_per_session';
      const period = pagesToRead > 0 ? 'plan' : 'session';
      if (targetValue > 0) {
        goals.push({
          goal_kind: 'complete_books',
          metric,
          target_value: targetValue,
          period,
          book_id: activeBook?.bookId || null,
          starting_page: startingPage,
        });
      }
    }

    if (selectedGoals.includes('form_habit')) {
      const habitData = state.formData.baselines?.form_habit || {};
      const targetValue = parseInt(habitData.end_minutes, 10) || parseInt(habitData.end_pages, 10) || 0;
      if (targetValue > 0) {
        goals.push({
          goal_kind: 'habit',
          metric: 'daily_threshold',
          target_value: targetValue,
          period: 'day',
          book_id: null,
        });
      }
    }

    return goals;
  }

  function buildPlannedSessions() {
    const sessions = state.calculatedPlan.sessions ? state.calculatedPlan.sessions.slice() : [];
    sessions.sort((a, b) => a.date - b.date);
    const activeBook = state.formData.books && state.formData.books.length ? state.formData.books[0] : null;
    const totalPages = getEffectiveTotalPages(activeBook);
    const pps = parseInt(state.calculatedPlan.pps, 10) || 0;
    const startingPage = getStartingPage(activeBook);
    const finalPage = startingPage + Math.max(totalPages - 1, 0);

    return sessions.map((session, idx) => {
      const planned = {
        planned_start_datetime: formatDateTime(session.date, 0, 0),
        planned_end_datetime: formatDateTime(session.date, 0, 30),
        planned_start_page: null,
        planned_end_page: null,
        expected_number_of_pages: null,
        expected_duration_minutes: null,
      };

      if (state.calculatedPlan.type === 'habit') {
        planned.planned_end_datetime = formatDateTimeSeconds(session.date, 23, 59, 59);
        planned.expected_number_of_pages = session.expectedPages || null;
        planned.expected_duration_minutes = session.expectedMinutes || null;
        return planned;
      }

      if (totalPages > 0 && pps > 0) {
        const startPage = startingPage + (idx * pps);
        const endPage = Math.min(startPage + pps - 1, finalPage);
        planned.planned_start_page = startPage;
        planned.planned_end_page = endPage;
      }

      return planned;
    });
  }

  function updateCargaLabel() {
    if (state.calculatedPlan.type === 'ccl') {
      qs('#propuesta-carga').innerText = format('suggested_load', 'Suggested Load: %s PAGES / SESSION', state.calculatedPlan.pps);
    } else {
      qs('#propuesta-carga').innerText = format('estimated_load', 'Estimated Load: %s PAGES / SESSION', state.calculatedPlan.pps);
    }
  }

  function removeSessionByDate(targetDate) {
    const target = new Date(targetDate);
    state.calculatedPlan.sessions = state.calculatedPlan.sessions.filter(
      (session) => session.date.toDateString() !== target.toDateString()
    );
    normalizeSessionOrder();
    if (state.calculatedPlan.type === 'ccl') {
      const totalPages = getEffectiveTotalPages(state.formData.books[0]);
      const sessionCount = state.calculatedPlan.sessions.length || 1;
      state.calculatedPlan.pps = Math.ceil(totalPages / sessionCount);
      updateCargaLabel();
    }
  }

  function addSessionAtDate(targetDate) {
    const target = new Date(targetDate);
    const exists = state.calculatedPlan.sessions.some(
      (session) => session.date.toDateString() === target.toDateString()
    );
    if (exists) return;
    state.calculatedPlan.sessions.push({ date: target, order: 0 });
    normalizeSessionOrder();
    if (state.calculatedPlan.type === 'ccl') {
      const totalPages = getEffectiveTotalPages(state.formData.books[0]);
      const sessionCount = state.calculatedPlan.sessions.length || 1;
      state.calculatedPlan.pps = Math.ceil(totalPages / sessionCount);
      updateCargaLabel();
    }
  }

  function getStartingPage(activeBook) {
    const totalPages = parseInt(activeBook?.pages, 10) || 0;
    const rawStart = parseInt(state.formData.startPage, 10) || 1;
    if (totalPages < 1) return 1;
    return Math.min(Math.max(rawStart, 1), totalPages);
  }

  function getEffectivePages(activeBook, startPage) {
    const totalPages = parseInt(activeBook?.pages, 10) || 0;
    if (totalPages < 1) return 0;
    return Math.max(0, totalPages - (startPage - 1));
  }

  function getEffectiveTotalPages(activeBook) {
    if (state.calculatedPlan.totalPages) {
      return state.calculatedPlan.totalPages;
    }
    const startPage = getStartingPage(activeBook);
    return getEffectivePages(activeBook, startPage);
  }

  function isHabitPlan() {
    return state.calculatedPlan.type === 'habit';
  }

  function setHabitStartDate(targetDate) {
    const base = new Date(targetDate);
    base.setHours(0, 0, 0, 0);
    const sessions = state.calculatedPlan.sessions.slice().sort((a, b) => a.order - b.order);
    sessions.forEach((session, idx) => {
      const date = new Date(base);
      date.setDate(base.getDate() + idx);
      session.date = date;
      session.order = idx + 1;
    });
    state.calculatedPlan.sessions = sessions;
    state.formData.baselines.form_habit = state.formData.baselines.form_habit || {};
    state.formData.baselines.form_habit.start_date = base.toISOString().slice(0, 10);
  }

  function startHabitMagnet() {
    const marks = calendarGrid.querySelectorAll('.selected-mark');
    marks.forEach((mark) => {
      if (mark.dataset.order !== '1') mark.classList.add('magnetized');
    });
  }

  function stopHabitMagnet() {
    const marks = calendarGrid.querySelectorAll('.selected-mark.magnetized');
    marks.forEach((mark) => mark.classList.remove('magnetized'));
  }

  function renderCalendar() {
    const viewDate = new Date(TODAY.getFullYear(), TODAY.getMonth() + state.currentMonthOffset, 1);
    qs('#propuesta-mes-label').innerText = `${MONTH_NAMES[viewDate.getMonth()]} ${viewDate.getFullYear()}`;
    qs('#calendar-prev-month').classList.toggle('disabled', state.currentMonthOffset <= 0);
    calendarGrid.innerHTML = '';
    const habitMode = isHabitPlan();
    const daysInMonth = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0).getDate();
    const startOffset = (viewDate.getDay() + 6) % 7;
    for (let i = 0; i < startOffset; i++) {
      const emptyCell = document.createElement('div');
      emptyCell.className = 'h-14 opacity-0';
      calendarGrid.appendChild(emptyCell);
    }
    for (let d = 1; d <= daysInMonth; d++) {
      const cellDate = new Date(viewDate.getFullYear(), viewDate.getMonth(), d);
      const cell = document.createElement('div');
      cell.className = 'day-cell relative h-14 rounded-custom flex items-center justify-center group';
      const isPast = cellDate < TODAY && cellDate.toDateString() !== TODAY.toDateString();
      cell.innerHTML = `<span class="absolute top-1 left-1 text-[9px] font-medium ${isPast ? 'opacity-10' : 'opacity-30'}">${d}</span>`;
      if (isPast) cell.classList.add('bg-gray-50', 'opacity-50');

      const sess = state.calculatedPlan.sessions.find((s) => s.date.toDateString() === cellDate.toDateString());
      if (sess) {
        const mark = document.createElement('div');
        mark.className = 'selected-mark w-8 h-8 rounded-full flex items-center justify-center text-black font-medium text-xs';
        mark.dataset.order = String(sess.order);
        mark.draggable = !habitMode || sess.order === 1;
        mark.innerText = sess.order;
        if (!habitMode) {
          const removeBtn = document.createElement('button');
          removeBtn.type = 'button';
          removeBtn.className = 'session-remove';
          removeBtn.setAttribute('aria-label', t('remove_session', 'Remove session'));
          removeBtn.innerText = '×';
          removeBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            removeSessionByDate(sess.date);
            renderCalendar();
            if (state.currentViewMode === 'list') renderList();
          });
          mark.appendChild(removeBtn);
        }
        mark.addEventListener('dragstart', (e) => {
          if (e.target && e.target.classList.contains('session-remove')) {
            e.preventDefault();
            return;
          }
          if (habitMode && sess.order !== 1) {
            e.preventDefault();
            return;
          }
          e.dataTransfer.setData('sessionDate', sess.date.toISOString());
          e.dataTransfer.setData('sessionOrder', String(sess.order));
          mark.classList.add('opacity-40');
          if (habitMode && sess.order === 1) {
            state.draggingHabitStart = true;
            startHabitMagnet();
          }
        });
        mark.addEventListener('dragend', () => {
          mark.classList.remove('opacity-40');
          if (state.draggingHabitStart) {
            state.draggingHabitStart = false;
            stopHabitMagnet();
          }
        });
        cell.appendChild(mark);
      }

      if (!isPast) {
        cell.addEventListener('dragover', (e) => {
          e.preventDefault();
          if (habitMode && state.draggingHabitStart) {
            cell.classList.add('drag-over');
            return;
          }
          if (!state.calculatedPlan.sessions.find((s) => s.date.toDateString() === cellDate.toDateString())) {
            cell.classList.add('drag-over');
          }
        });
        cell.addEventListener('dragleave', () => cell.classList.remove('drag-over'));
        cell.addEventListener('drop', (e) => {
          e.preventDefault();
          cell.classList.remove('drag-over');
          const originIso = e.dataTransfer.getData('sessionDate');
          const originOrder = parseInt(e.dataTransfer.getData('sessionOrder'), 10);
          if (originIso) {
            if (habitMode && originOrder === 1) {
              setHabitStartDate(cellDate);
              state.draggingHabitStart = false;
              stopHabitMagnet();
              renderCalendar();
              if (state.currentViewMode === 'list') renderList();
              return;
            }
            const sessIdx = state.calculatedPlan.sessions.findIndex((s) => s.date.toISOString() === originIso);
            if (sessIdx > -1) {
              state.calculatedPlan.sessions[sessIdx].date = new Date(cellDate);
              renderCalendar();
              if (state.currentViewMode === 'list') renderList();
            }
          }
        });
        if (!sess && !habitMode) {
          let hoverTimer = null;
          cell.addEventListener('mouseenter', () => {
            hoverTimer = setTimeout(() => {
              if (cell.querySelector('.session-add')) return;
              const addBtn = document.createElement('button');
              addBtn.type = 'button';
              addBtn.className = 'session-add';
              addBtn.setAttribute('aria-label', t('add_session', 'Add session'));
              addBtn.textContent = '+';
              addBtn.addEventListener('click', (event) => {
                event.stopPropagation();
                addSessionAtDate(cellDate);
                renderCalendar();
                if (state.currentViewMode === 'list') renderList();
              });
              cell.appendChild(addBtn);
            }, 500);
          });
          cell.addEventListener('mouseleave', () => {
            if (hoverTimer) {
              clearTimeout(hoverTimer);
              hoverTimer = null;
            }
            const addBtn = cell.querySelector('.session-add');
            if (addBtn) addBtn.remove();
          });
        }
      }

      calendarGrid.appendChild(cell);
    }
    updateHeight();
  }

  function renderList() {
    listView.innerHTML = '';
    const allSorted = Array.from(state.calculatedPlan.sessions).sort((a, b) => a.date - b.date);
    const totalSessions = allSorted.length;
    const pagination = qs('#list-pagination');

    if (totalSessions === 0) {
      listView.innerHTML = `<p class="text-center text-[10px] uppercase font-medium opacity-40 py-8 tracking-widest">${t('no_sessions', 'No sessions')}</p>`;
      pagination.classList.add('hidden');
      return;
    }

    const totalPages = Math.ceil(totalSessions / SESSIONS_PER_PAGE);
    pagination.classList.remove('hidden');
    qs('#list-page-info').innerText = format('list_page_label', '%1$s / %2$s', state.listCurrentPage + 1, totalPages);
    qs('#list-prev-page').classList.toggle('disabled', state.listCurrentPage <= 0);
    qs('#list-next-page').classList.toggle('disabled', state.listCurrentPage >= totalPages - 1);

    const startIdx = state.listCurrentPage * SESSIONS_PER_PAGE;
    const pageSessions = allSorted.slice(startIdx, startIdx + SESSIONS_PER_PAGE);
    const isHabit = state.calculatedPlan.type === 'habit';
    pageSessions.forEach((s) => {
      const monthName = MONTH_NAMES[s.date.getMonth()];
      const expectedMinutes = typeof s.expectedMinutes === 'number' ? Math.ceil(s.expectedMinutes) : null;
      const expectedPages = typeof s.expectedPages === 'number' ? Math.ceil(s.expectedPages) : null;
      const habitLabel = expectedMinutes !== null && expectedPages !== null
        ? format('habit_session_meta', '%1$s min / %2$s pages', expectedMinutes, expectedPages)
        : t('reading_session', 'Reading Session');
      const habitDate = `${monthName} ${s.date.getDate()}`;
      const habitDetail = expectedMinutes !== null && expectedPages !== null
        ? format('habit_session_meta', '%1$s pages / %2$s min', expectedPages, expectedMinutes)
        : t('reading_session', 'Reading Session');
      const leftLabel = isHabit
        ? `${habitDate}: ${habitDetail}`
        : t('reading_session', 'Reading Session');
      listView.innerHTML += `
        <div class="flex items-center justify-between p-3 bg-white border border-[#A8A8A8] rounded-custom shadow-sm mb-2 step-transition">
          <div class="flex items-center space-x-3">
            <div class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-medium text-black bg-[#C79F32]">${s.order}</div>
            <span class="text-xs font-medium text-black uppercase tracking-tight">${leftLabel}</span>
          </div>
          <span class="text-[10px] font-medium text-black opacity-60 uppercase tracking-tighter">${isHabit ? '' : `${s.date.getDate()} ${monthName}`}</span>
        </div>`;
    });
    updateHeight();
  }

  function updateHeight() {
    const active = state.currentViewMode === 'calendar' ? qs('#calendar-view-wrapper') : qs('#list-view-wrapper');
    setTimeout(() => {
      const container = qs('#main-view-container');
      if (container) container.style.height = `${active.offsetHeight}px`;
    }, 50);
  }

  function resetForm() {
    state = getInitialState();
    formContainer.classList.remove('hidden');
    summaryContainer.classList.add('hidden');
    const successEl = qs('#reading-plan-success');
    const errorEl = qs('#reading-plan-error');
    const acceptBtn = qs('#accept-button');
    if (summaryContainer) summaryContainer.classList.remove('is-success');
    if (successPanel) successPanel.classList.add('hidden');
    if (successTitle) successTitle.textContent = '';
    if (successNext) successNext.textContent = '';
    if (successNote) successNote.classList.add('hidden');
    if (successStartBtn) {
      successStartBtn.disabled = false;
      successStartBtn.setAttribute('aria-disabled', 'false');
    }
    if (sessionContent) sessionContent.innerHTML = '';
    recorderState.loading = null;
    recorderState.bookId = null;
    recorderState.planId = null;
    closeSessionModal();
    if (successEl) successEl.classList.add('hidden');
    if (errorEl) errorEl.classList.add('hidden');
    if (acceptBtn) {
      acceptBtn.disabled = false;
      acceptBtn.classList.remove('opacity-60', 'pointer-events-none');
    }
    render();
  }

  const closeModal = () => {
    overlay.hidden = true;
    document.body.style.overflow = '';
  };

  const openModal = () => {
    overlay.hidden = false;
    document.body.style.overflow = 'hidden';
    resetForm();
  };

  const loadSessionRecorder = (bookId, planId) => {
    if (!bookId || !RP.sessionRecorderUrl) {
      return Promise.reject(new Error('missing_book'));
    }
    if (recorderState.loading) return recorderState.loading;
    if (recorderState.bookId === bookId && recorderState.planId === planId && sessionContent && sessionContent.innerHTML.trim()) {
      return Promise.resolve();
    }
    const url = new URL(RP.sessionRecorderUrl, window.location.origin);
    url.searchParams.set('book_id', bookId);
    if (planId) {
      url.searchParams.set('plan_id', planId);
    }
    recorderState.loading = fetch(url.toString(), {
      headers: {
        'X-WP-Nonce': RP.nonce,
      },
    })
      .then((res) => res.ok ? res.json() : Promise.reject(new Error('request_failed')))
      .then((payload) => {
        if (!payload || !payload.success || !payload.data) {
          throw new Error('request_failed');
        }
        const recorderHtml = payload.data.html || '';
        const recorderData = payload.data.prs_sr || null;
        if (sessionContent) sessionContent.innerHTML = recorderHtml;
        if (recorderData) {
          window.PRS_SR = recorderData;
        }
        if (typeof window.prsStartReadingInit === 'function') {
          window.prsStartReadingInit({ root: sessionModal || document, data: recorderData || window.PRS_SR });
        }
        recorderState.bookId = bookId;
        recorderState.planId = planId;
      })
      .finally(() => {
        recorderState.loading = null;
      });
    return recorderState.loading;
  };

  const closeSessionModal = () => {
    if (!sessionModal) return;
    sessionModal.classList.remove('is-active');
    sessionModal.setAttribute('aria-hidden', 'true');
  };

  if (sessionModalClose) {
    sessionModalClose.addEventListener('click', closeSessionModal);
  }

  if (sessionModal) {
    sessionModal.addEventListener('click', (event) => {
      if (event.target === sessionModal) {
        closeSessionModal();
      }
    });
  }

  openBtn.addEventListener('click', () => {
    loadExternalAssets().then(openModal);
  });
  qs('.politeia-modal-close')?.addEventListener('click', closeModal);

  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeModal();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !overlay.hidden) closeModal();
  });

  qs('#toggle-calendar')?.addEventListener('click', () => {
    state.currentViewMode = 'calendar';
    qs('#toggle-calendar').classList.add('active');
    qs('#toggle-list').classList.remove('active');
    qs('#calendar-view-wrapper').classList.replace('view-hidden', 'view-visible');
    qs('#list-view-wrapper').classList.replace('view-visible', 'view-hidden');
    qs('#calendar-nav-controls').classList.remove('hidden');
    qs('#list-pagination').classList.add('hidden');
    renderCalendar();
  });

  qs('#toggle-list')?.addEventListener('click', () => {
    state.currentViewMode = 'list';
    qs('#toggle-list').classList.add('active');
    qs('#toggle-calendar').classList.remove('active');
    qs('#list-view-wrapper').classList.replace('view-hidden', 'view-visible');
    qs('#calendar-view-wrapper').classList.replace('view-visible', 'view-hidden');
    qs('#calendar-nav-controls').classList.add('hidden');
    renderList();
  });

  qs('#list-prev-page')?.addEventListener('click', () => {
    if (state.listCurrentPage > 0) {
      state.listCurrentPage -= 1;
      renderList();
    }
  });

  qs('#list-next-page')?.addEventListener('click', () => {
    const totalPages = Math.ceil(state.calculatedPlan.sessions.length / SESSIONS_PER_PAGE);
    if (state.listCurrentPage < totalPages - 1) {
      state.listCurrentPage += 1;
      renderList();
    }
  });

  qs('#calendar-prev-month')?.addEventListener('click', () => {
    if (state.currentMonthOffset > 0) {
      state.currentMonthOffset -= 1;
      renderCalendar();
    }
  });

  qs('#calendar-next-month')?.addEventListener('click', () => {
    state.currentMonthOffset += 1;
    renderCalendar();
  });

  qs('#adjust-btn')?.addEventListener('click', () => {
    summaryContainer.classList.add('hidden');
    formContainer.classList.remove('hidden');
    state.mainStep = 1;
    state.isBaselineActive = false;
    render();
  });

  if (successStartBtn) {
    successStartBtn.addEventListener('click', () => {
      const activeBook = state.formData.books && state.formData.books.length ? state.formData.books[0] : null;
      const bookId = activeBook?.bookId;
      const planId = state.acceptedPlanId || null;
      if (!bookId) {
        if (successNote) {
          successNote.textContent = t('session_recorder_unavailable', 'Session recorder is not available for this book.');
          successNote.classList.remove('hidden');
        }
        return;
      }
      if (successNote) successNote.classList.add('hidden');
      successStartBtn.disabled = true;
      loadSessionRecorder(bookId, planId)
        .then(() => {
          if (sessionModal) {
            sessionModal.classList.add('is-active');
            sessionModal.setAttribute('aria-hidden', 'false');
          }
        })
        .catch(() => {
          if (successNote) {
            successNote.textContent = t('session_recorder_unavailable', 'Session recorder is not available for this book.');
            successNote.classList.remove('hidden');
          }
        })
        .finally(() => {
          successStartBtn.disabled = false;
        });
    });
  }

  qs('#accept-button')?.addEventListener('click', () => {
    const successEl = qs('#reading-plan-success');
    const errorEl = qs('#reading-plan-error');
    const acceptBtn = qs('#accept-button');
    if (!acceptBtn || acceptBtn.disabled) return;

    const goals = buildGoals();
    const baselines = buildBaselineMetrics();
    if (!goals.length || !Object.keys(baselines).length) {
      if (errorEl) {
        errorEl.textContent = t('plan_create_failed', 'Could not create the plan. Please try again.');
        errorEl.classList.remove('hidden');
      }
      if (successEl) successEl.classList.add('hidden');
      return;
    }

    const activeBook = state.formData.books && state.formData.books.length ? state.formData.books[0] : null;
    const isHabitPlan = state.calculatedPlan.type === 'habit';
    const planName = isHabitPlan
      ? t('habit_plan_name', '48-Day Habit Challenge')
      : (activeBook?.title || t('plan_title_default', 'Plan Title'));
    const payload = {
      name: planName,
      plan_type: state.calculatedPlan.type || 'custom',
      status: 'accepted',
      goals,
      baselines,
      planned_sessions: buildPlannedSessions(),
      pages_per_session: state.formData.pages_per_session || null,
      sessions_per_week: state.formData.sessions_per_week || null,
    };

    acceptBtn.disabled = true;
    acceptBtn.classList.add('opacity-60', 'pointer-events-none');
    if (errorEl) errorEl.classList.add('hidden');

    fetch(RP.restUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': RP.nonce,
      },
      body: JSON.stringify(payload),
    })
      .then(async (res) => {
        let data = {};
        try {
          data = await res.json();
        } catch (err) {
          data = {};
        }
        if (!res.ok || !data.success) {
          throw new Error(data.error || 'request_failed');
        }
        return data;
      })
      .then((data) => {
        if (successEl) successEl.classList.add('hidden');
        const bookTitle = activeBook?.title || t('plan_title_default', 'Plan Title');
        const sessionsSorted = Array.from(state.calculatedPlan.sessions || []).sort((a, b) => a.date - b.date);
        const nextSession = sessionsSorted.length ? sessionsSorted[0].date : null;
        if (successTitle) {
          successTitle.textContent = isHabitPlan
            ? t('habit_accepted_message', 'Congratulations! You have started your 48-day habit challenge.')
            : format('plan_accepted_message', 'Congratulations! You have accepted your reading plan for "%s".', bookTitle);
        }
        if (successNext) {
          successNext.textContent = format('next_session_message', 'Your next reading session is %s.', formatSessionDate(nextSession));
        }
        if (successNote) successNote.classList.add('hidden');
        if (successStartBtn) {
          const hasBookId = !isHabitPlan && !!activeBook?.bookId;
          successStartBtn.disabled = !hasBookId;
          successStartBtn.setAttribute('aria-disabled', hasBookId ? 'false' : 'true');
        }
        state.acceptedPlanId = data?.plan_id || null;
        if (summaryContainer) summaryContainer.classList.add('is-success');
        if (successPanel) successPanel.classList.remove('hidden');
      })
      .catch(() => {
        if (errorEl) {
          errorEl.textContent = t('plan_create_failed', 'Could not create the plan. Please try again.');
          errorEl.classList.remove('hidden');
        }
        acceptBtn.disabled = false;
        acceptBtn.classList.remove('opacity-60', 'pointer-events-none');
      });
  });

  resetForm();
})();
