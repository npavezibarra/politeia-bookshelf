(function(){
  const overlay = document.getElementById('politeia-reading-plan-overlay');
  const openBtn = document.getElementById('politeia-open-reading-plan');
  if (!overlay || !openBtn) return;

  const qs = (selector) => overlay.querySelector(selector);
  const RP = window.PoliteiaReadingPlan || {};
  const PREFILL_BOOK = RP.prefillBook || null;
  const STRINGS = RP.strings || {};
  const t = (key, fallback) => (STRINGS && STRINGS[key]) ? STRINGS[key] : fallback;
  const format = (key, fallback, value, value2) => {
    const text = t(key, fallback);
    if (typeof value2 !== 'undefined') {
      return text
        .replace('%1$s', String(value))
        .replace('%2$s', String(value2))
        .replace('%1$d', String(value))
        .replace('%2$d', String(value2));
    }
    return text.replace('%s', String(value)).replace('%d', String(value));
  };

  const formContainer = qs('#form-container');
  const summaryContainer = qs('#summary-container');
  const stepContent = qs('#step-content');
  const calendarGrid = qs('#calendar-grid');
  const listView = qs('#list-view');

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

  const assetState = {
    loading: null,
    loaded: false,
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

  const PAGE_RANGES_MAP = { "<100": 80, "200~": 200, "400~": 400, "600~": 600, "1000~": 1000 };
  const EXIGENCIA_SESSIONS = { liviano: 3, mediano: 3, exigente: 5, intenso: 7 };

  const HABIT_INTENSITY_CONFIG = {
    liviano: { time: 15, label: t('intensity_light', 'LIGHT'), reason: t('intensity_light_reason', 'It\'s the "magic number" for making progress without it feeling like a burden.') },
    mediano: { time: 30, label: t('intensity_balanced', 'BALANCED'), reason: t('intensity_balanced_reason', 'It lets you finish a full chapter, creating a real sense of achievement.') },
    intenso: { time: 60, label: t('intensity_intense', 'INTENSE'), reason: t('intensity_intense_reason', 'Ideal for those who want reading to be a central part of their identity.') },
  };

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
        exigencia: null,
      },
      calculatedPlan: { pps: 0, durationWeeks: 0, sessions: [], type: 'ccl' },
      currentMonthOffset: 0,
      currentViewMode: 'calendar',
      listCurrentPage: 0,
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
      renderStepExigencia();
    }

    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }
  }

  function updateProgressBar() {
    const progress = qs('#progress-bar');
    if (!progress) return;
    const bars = progress.children;
    Array.from(bars).forEach((div, i) => {
      div.style.backgroundColor = i < state.mainStep ? '#C79F32' : '#F5F5F5';
    });
  }

  function renderStepGoals() {
    stepContent.innerHTML = `
      <div class="space-y-6 step-transition h-full flex flex-col justify-center">
        <div class="text-center mb-8">
          <h2 class="text-2xl font-medium text-black uppercase tracking-tight">${t('goal_prompt', 'What goal do you want to achieve?')}</h2>
          <p class="text-sm font-medium">${t('goal_subtitle', 'Select your primary goal')}</p>
        </div>
        <div class="grid grid-cols-1 gap-4 w-full">
          ${GOALS_DEF.map((goal) => {
            const sel = state.formData.goals.includes(goal.id);
            return `<button type="button" id="goal-${goal.id}" data-goal="${goal.id}" class="w-full p-6 text-left border-2 rounded-custom transition-all ${
              sel
                ? 'border-[#C79F32] bg-[#F5F5F5] ring-2 ring-[#C79F32]'
                : 'border-[#A8A8A8] bg-[#FEFEFF] hover:border-[#C79F32]'
            }">
              <div class="flex items-start gap-4">
                <i data-lucide="${goal.icon}" class="w-8 h-8 ${sel ? 'text-[#C79F32]' : 'text-[#A8A8A8]'}"></i>
                <div>
                  <h3 id="goal-title-${goal.id}" class="font-medium text-black uppercase text-sm">${goal.title}</h3>
                  <p id="goal-desc-${goal.id}" class="text-xs mt-1 font-medium leading-relaxed">${goal.description}</p>
                </div>
              </div>
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
          <div class="space-y-8 text-center step-transition">
            <span class="text-[#C79F32] font-medium text-[10px] uppercase tracking-widest">${t('baseline_label', 'Baseline')}</span>
            <h2 class="text-2xl font-medium text-black mt-2 uppercase">${t('baseline_books_year', 'How many books did you finish in the last year?')}</h2>
            <div class="grid grid-cols-5 gap-3">
              ${['0', '1', '2', '3', '4+'].map((n) => `
                <button type="button" id="baseline-${gid}-${toId(n)}" data-baseline="${n}" class="aspect-square rounded-custom border-2 font-medium text-xl transition-all ${
                  state.formData.baselines[gid]?.value === n
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
          <div class="space-y-6 step-transition">
            <div class="text-center">
              <h2 class="text-xl font-medium text-black uppercase">${t('baseline_book_pages', 'How many pages did the book have?')}</h2>
            </div>
            <div class="space-y-4 max-h-[360px] overflow-y-auto pr-2 custom-scrollbar">
              ${Array.from({ length: slots }).map((_, i) => `
                <div class="p-5 bg-[#F5F5F5] rounded-custom border border-[#A8A8A8]">
                  <p class="text-[10px] font-medium text-[#A8A8A8] mb-3 uppercase tracking-widest">${format('book_number', 'Book #%d', i + 1)}</p>
                  <div class="flex flex-wrap gap-2">
                    ${Object.keys(PAGE_RANGES_MAP).map((r) => `
                      <button type="button" id="book-detail-${i}-${toId(r)}" data-book-detail="${r}" data-book-index="${i}" class="px-3 py-2 rounded-custom text-[10px] font-medium border-2 transition-all uppercase text-black ${
                        state.formData.baselines[gid]?.details?.[i] === r
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
      if (state.subStep === 0) {
        stepContent.innerHTML = `
          <div class="space-y-8 text-center step-transition">
            <span class="text-[#C79F32] font-medium text-[10px] uppercase tracking-widest">${t('baseline_frequency', 'Baseline Frequency')}</span>
            <h2 class="text-2xl font-medium text-black mt-2 uppercase">${t('baseline_sessions_month', 'How many reading sessions did you have in the last month?')}</h2>
            <div class="max-w-xs mx-auto mt-10">
              <input type="number" id="habit-sessions-input" value="${state.formData.baselines[gid]?.value || ''}" data-habit-sessions placeholder="${t('example_sessions', 'e.g. 8')}" class="w-full text-center text-4xl font-bold p-6 bg-[#F5F5F5] border-2 border-[#A8A8A8] rounded-custom outline-none focus:border-[#C79F32] transition-colors" />
              <button type="button" id="habit-confirm-freq" class="w-full mt-6 bg-[#C79F32] text-black py-4 rounded-custom font-bold uppercase text-[10px] tracking-widest ${state.formData.baselines[gid]?.value ? '' : 'opacity-30 pointer-events-none'}">${t('confirm_continue', 'Confirm and Continue')}</button>
            </div>
          </div>`;

        const input = stepContent.querySelector('#habit-sessions-input');
        const confirmBtn = stepContent.querySelector('#habit-confirm-freq');
        if (input) {
          input.addEventListener('input', (e) => {
            state.formData.baselines[gid] = { ...state.formData.baselines[gid], value: e.target.value };
            const hasValue = !!e.target.value;
            if (confirmBtn) {
              confirmBtn.classList.toggle('opacity-30', !hasValue);
              confirmBtn.classList.toggle('pointer-events-none', !hasValue);
            }
          });
        }
        if (confirmBtn) {
          confirmBtn.addEventListener('click', () => goToNext());
        }
      } else if (state.subStep === 1) {
        const currentTime = state.formData.baselines[gid]?.time || '';
        stepContent.innerHTML = `
          <div class="space-y-8 text-center step-transition">
            <span class="text-[#C79F32] font-medium text-[10px] uppercase tracking-widest">${t('minimum_session', 'Minimum Session')}</span>
            <h2 class="text-2xl font-medium text-black mt-2 uppercase">${t('average_session_time', 'On average, how much time did you spend per session?')}</h2>
            <div class="grid grid-cols-2 gap-3 mt-6">
              ${[15, 30, 45, 60].map((m) => `
                <button type="button" id="habit-time-${m}" data-habit-time="${m}" class="p-5 border-2 rounded-custom transition-all ${
                  parseInt(currentTime, 10) === m
                    ? 'border-[#C79F32] bg-[#F5F5F5] text-[#C79F32]'
                    : 'border-[#A8A8A8] bg-white text-[#A8A8A8] hover:border-[#C79F32]'
                }"><span class="block text-2xl font-bold">${m}</span><span class="text-[10px] font-medium uppercase tracking-widest">${t('minutes_label', 'minutes')}</span></button>`).join('')}
            </div>
            <div class="mt-6 flex flex-col items-center">
              <div class="flex items-center justify-center space-x-3">
                <span class="text-[10px] font-bold text-[#A8A8A8] uppercase tracking-widest">${t('or_other', 'or other:')}</span>
                <input type="number" id="habit-time-custom" value="${![15, 30, 45, 60].includes(parseInt(currentTime, 10)) ? currentTime : ''}" data-habit-time-custom placeholder="${t('minutes_placeholder', '20')}" class="w-20 text-center text-lg font-bold p-2 bg-[#F5F5F5] border-2 border-[#A8A8A8] rounded-custom outline-none focus:border-[#C79F32]" />
                <span class="text-[10px] font-bold text-[#A8A8A8] uppercase tracking-widest">${t('minutes_short', 'min')}</span>
              </div>
              <button type="button" id="habit-confirm-time" class="w-full mt-4 bg-[#C79F32] text-black py-3 rounded-custom font-bold uppercase text-[10px] tracking-widest ${state.formData.baselines[gid]?.time ? '' : 'opacity-30 pointer-events-none'}">${t('confirm', 'Confirm')}</button>
            </div>
          </div>`;

        stepContent.querySelectorAll('[data-habit-time]').forEach((btn) => {
          btn.addEventListener('click', () => {
            const val = btn.getAttribute('data-habit-time');
            state.formData.baselines.form_habit = { ...state.formData.baselines.form_habit, time: val };
            goToNext();
          });
        });
        const customInput = stepContent.querySelector('#habit-time-custom');
        const confirmBtn = stepContent.querySelector('#habit-confirm-time');
        if (customInput) {
          customInput.addEventListener('input', (e) => {
            state.formData.baselines.form_habit = { ...state.formData.baselines.form_habit, time: e.target.value };
            const hasValue = !!e.target.value;
            if (confirmBtn) {
              confirmBtn.classList.toggle('opacity-30', !hasValue);
              confirmBtn.classList.toggle('pointer-events-none', !hasValue);
            }
          });
        }
        if (confirmBtn) {
          confirmBtn.addEventListener('click', () => goToNext());
        }
      } else if (state.subStep === 2) {
        const currentIntensity = state.formData.baselines[gid]?.intensity || '';
        stepContent.innerHTML = `
          <div class="space-y-6 text-center step-transition">
            <span class="text-[#C79F32] font-medium text-[10px] uppercase tracking-widest">${t('daily_ambition', 'Daily Ambition')}</span>
            <h2 class="text-2xl font-medium text-black mt-2 uppercase">${t('habit_intensity_prompt', 'How intense do you want this habit to be?')}</h2>
            <div class="grid grid-cols-1 gap-4 mt-6">
              ${Object.keys(HABIT_INTENSITY_CONFIG).map((key) => {
                const config = HABIT_INTENSITY_CONFIG[key];
                return `
                  <button type="button" id="habit-intensity-${key}" data-habit-intensity="${key}" class="p-5 border-2 text-left rounded-custom transition-all ${
                    currentIntensity === key
                      ? 'border-[#C79F32] bg-[#F5F5F5] ring-2 ring-[#C79F32]'
                      : 'border-[#A8A8A8] bg-white hover:border-[#C79F32]'
                  }">
                    <div class="flex justify-between items-center mb-1">
                      <h3 class="font-bold text-black uppercase text-sm">${config.label}</h3>
                      <span class="text-[10px] font-black text-[#C79F32] bg-[#C79F32]/10 px-2 py-1 rounded">${format('minutes_per_day', '%s MIN / DAY', config.time)}</span>
                    </div>
                    <p class="text-[10px] text-black/50 font-medium leading-relaxed italic">${config.reason}</p>
                  </button>`;
              }).join('')}
            </div>
          </div>`;

        stepContent.querySelectorAll('[data-habit-intensity]').forEach((btn) => {
          btn.addEventListener('click', () => {
            const val = btn.getAttribute('data-habit-intensity');
            state.formData.baselines.form_habit = { ...state.formData.baselines.form_habit, intensity: val };
            calculateHabitPlan();
          });
        });
      }
    }
  }

  function renderStepBooks() {
    const activeBook = state.formData.books && state.formData.books.length ? state.formData.books[0] : null;
    const hasBook = !!activeBook;
    stepContent.innerHTML = `
      <div class="space-y-6 step-transition">
        <div class="text-center mb-6"><h2 class="text-2xl font-medium text-black uppercase tracking-tight">${t('book_prompt', 'Which book do you want to read now?')}</h2></div>
        <div class="reading-plan-book-display">
          <div class="book-placeholder">
            <div id="book-display" class="book-inner text-[#A8A8A8] ${hasBook ? 'is-filled' : ''} ${activeBook?.cover ? 'has-cover' : ''}">
              <img id="book-cover" class="book-cover${activeBook?.cover ? '' : ' hidden'}" alt="" src="${activeBook?.cover ? activeBook.cover : ''}">
              <div id="placeholder-content" class="flex flex-column items-center flex-col${hasBook ? ' hidden' : ''}">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mb-2 opacity-50">
                  <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                  <path d="M8 7h6"/><path d="M8 11h8"/>
                </svg>
                <span class="text-[10px] font-bold uppercase tracking-[0.2em]">${t('your_book', 'Your Book')}</span>
              </div>
              <div id="filled-content" class="w-full h-full flex flex-col justify-center${hasBook ? '' : ' hidden'}">
                <div id="display-title" class="book-title-display"></div>
                <div class="w-8 h-px bg-[#C79F32] mx-auto my-3"></div>
                <div id="display-author" class="book-author-display"></div>
              </div>
            </div>
          </div>
          ${hasBook ? `
            <button type="button" id="remove-book-current" class="book-remove" aria-label="${t('remove_book', 'Remove book')}">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 6h18"></path>
                <path d="M8 6V4h8v2"></path>
                <path d="M6 6l1 14h10l1-14"></path>
                <path d="M10 11v6"></path>
                <path d="M14 11v6"></path>
              </svg>
            </button>
          ` : ''}
          <div id="book-meta-info" class="text-center${hasBook ? '' : ' hidden'}">
            <div id="meta-text" class="text-xs font-medium text-black uppercase tracking-tight leading-relaxed"></div>
          </div>
        </div>
        ${hasBook ? `
          <button type="button" id="next-step" class="w-full bg-black text-[#C79F32] py-4 rounded-custom hover:opacity-90 transition-all flex items-center justify-center gap-2 text-[10px] font-bold uppercase tracking-widest">
            ${t('next', 'Next')}
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="m9 18 6-6-6-6"/>
            </svg>
          </button>
        ` : `
          <div class="bg-[#F5F5F5] p-6 rounded-custom border border-[#A8A8A8] space-y-4">
            <div class="relative">
              <input id="new-book-title" type="text" placeholder="${t('book_title', 'Book title')}" autocomplete="off" class="w-full p-3 border border-[#A8A8A8] rounded-custom outline-none text-sm bg-white font-medium focus:ring-1 focus:ring-[#C79F32] transition-all">
              <div id="reading-plan-title-suggestions" class="prs-add-book__suggestions" aria-hidden="true"></div>
            </div>
            <div class="reading-plan-book-row">
              <div class="reading-plan-author">
                <input id="new-book-author" type="text" placeholder="${t('author', 'Author')}" class="w-full p-3 border border-[#A8A8A8] rounded-custom outline-none text-sm bg-white font-medium focus:ring-1 focus:ring-[#C79F32] transition-all">
              </div>
              <div class="reading-plan-pages">
                <input id="new-book-pages" type="number" placeholder="${t('pages', 'Pages')}" class="w-full p-3 border border-[#A8A8A8] rounded-custom outline-none text-sm bg-white font-medium focus:ring-1 focus:ring-[#C79F32] transition-all">
              </div>
            </div>
            <div class="space-y-3">
              <button type="button" id="add-book" class="w-full bg-[#C79F32] text-black py-4 rounded-custom hover:brightness-95 transition-all flex items-center justify-center gap-2 text-[10px] font-bold uppercase tracking-widest">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M5 12h14"></path>
                  <path d="M12 5v14"></path>
                </svg>
                ${t('add_book', 'Add book')}
              </button>
            </div>
          </div>
        `}
      </div>`;

    const displayContainer = stepContent.querySelector('#book-display');
    const displayTitle = stepContent.querySelector('#display-title');
    const displayAuthor = stepContent.querySelector('#display-author');
    const metaText = stepContent.querySelector('#meta-text');
    const coverImage = stepContent.querySelector('#book-cover');

    if (hasBook && activeBook) {
      if (displayTitle) displayTitle.textContent = activeBook.title || '';
      if (displayAuthor) displayAuthor.textContent = activeBook.author || t('unknown_author', 'Unknown author');
      if (metaText) metaText.innerHTML = `${activeBook.title || ''} ${t('by_label', 'by')} ${activeBook.author || t('unknown_author', 'Unknown author')} <br> ${activeBook.pages || ''} ${t('pages_label', 'pages')}`;
      if (coverImage && activeBook.cover) {
        coverImage.src = activeBook.cover;
        coverImage.classList.remove('hidden');
        if (displayContainer) displayContainer.classList.add('has-cover');
      }
    }

    const titleInput = stepContent.querySelector('#new-book-title');
    const authorInput = stepContent.querySelector('#new-book-author');
    const pagesInput = stepContent.querySelector('#new-book-pages');
    const suggestions = stepContent.querySelector('#reading-plan-title-suggestions');

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
    if (!hasBook && addButton) {
      addButton.addEventListener('click', () => {
        const t = titleInput?.value?.trim();
        const a = authorInput?.value?.trim();
        const p = parseInt(pagesInput?.value, 10);
        const cover = titleInput?.dataset?.cover ? titleInput.dataset.cover : '';
        if (t && a && p) {
          state.formData.books = [{ id: Date.now(), title: t, author: a, pages: p, cover }];
          render();
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
        render();
      });
    }
  }

  function renderStepExigencia() {
    const intensityStyles = {
      mediano: 'exigencia-dot--mediano',
      exigente: 'exigencia-dot--exigente',
      intenso: 'exigencia-dot--intenso',
    };
    const intensityLabels = {
      mediano: t('intensity_balanced_label', 'Balanced'),
      exigente: t('intensity_challenging_label', 'Challenging'),
      intenso: t('intensity_intense_label', 'Intense'),
    };

    stepContent.innerHTML = `
      <div class="exigencia-slide step-transition">
        <div class="text-center mb-6"><h2 class="text-2xl font-medium text-black uppercase tracking-tight">${t('intensity_prompt', 'What intensity level do you want?')}</h2></div>
        <div class="exigencia-grid">
          ${['mediano', 'exigente', 'intenso'].map((k) => `
            <button type="button" id="exigencia-${k}" data-exigencia="${k}" class="exigencia-card ${state.formData.exigencia === k ? 'is-selected' : ''}">
              <div class="exigencia-dot ${intensityStyles[k]}"></div>
              <h3 class="exigencia-title">${intensityLabels[k] || k}</h3>
              <p class="exigencia-meta">${format('sessions_per_week', '%d sessions<br>per week', EXIGENCIA_SESSIONS[k])}</p>
            </button>`).join('')}
        </div>
      </div>`;

    stepContent.querySelectorAll('[data-exigencia]').forEach((btn) => {
      btn.addEventListener('click', () => {
        state.formData.exigencia = btn.getAttribute('data-exigencia');
        goToNext();
      });
    });
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
      } else if (gid === 'form_habit' && state.subStep < 2) {
        state.subStep += 1;
      } else {
        state.mainStep = 2;
        state.isBaselineActive = false;
      }
    } else if (state.mainStep < 3) {
      state.mainStep += 1;
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
    const intensityKey = data.baselines.form_habit.intensity;
    const dailyMinutes = HABIT_INTENSITY_CONFIG[intensityKey].time;
    const pps = Math.ceil(dailyMinutes / BENCHMARK_MIN_PER_PAGE);
    const totalDays = 42;
    const sessionsPerWeek = 6;
    const totalSessions = Math.ceil((totalDays / 7) * sessionsPerWeek);

    state.calculatedPlan = {
      pps,
      durationWeeks: totalDays / 7,
      sessions: generateHabitSessions(totalSessions, sessionsPerWeek),
      type: 'habit',
    };

    qs('#propuesta-tipo-label').innerText = t('habit_plan_title', 'HABIT FORMATION PROPOSAL');
    qs('#propuesta-plan-titulo').innerText = format('habit_plan_of', 'HABIT OF %s MIN / DAY', dailyMinutes);
    qs('#propuesta-sub-label').innerText = t('habit_cycle_label', 'CONSOLIDATION CYCLE (42 DAYS)');
    qs('#propuesta-carga').innerText = format('estimated_load', 'Estimated Load: %s PAGES / SESSION', pps);
    qs('#propuesta-duracion').innerText = format('cycle_duration_weeks', 'Cycle duration: %s WEEKS', Math.round(totalDays / 7));

    formContainer.classList.add('hidden');
    summaryContainer.classList.remove('hidden');
    state.currentMonthOffset = 0;
    state.listCurrentPage = 0;
    renderCalendar();
  }

  function calculateCCLPlan() {
    const data = state.formData;
    const targetBook = data.books[0];
    let avgPages = 100;
    const bBooks = data.baselines.complete_books;
    if (bBooks && bBooks.value !== '0') {
      const sum = (bBooks.details || []).reduce((acc, r) => acc + PAGE_RANGES_MAP[r], 0);
      avgPages = sum / (bBooks.details?.length || 1);
    }
    const pps = Math.ceil(Math.max((avgPages / 10) * K_GROWTH, MIN_PPS));
    const sessionsPerWeek = EXIGENCIA_SESSIONS[data.exigencia];
    const totalSessions = Math.ceil(targetBook.pages / pps);
    const totalWeeks = Math.ceil(totalSessions / sessionsPerWeek);

    state.calculatedPlan = {
      pps,
      durationWeeks: totalWeeks,
      sessions: generateSessions(totalSessions, sessionsPerWeek),
      type: 'ccl',
      totalPages: targetBook.pages,
    };

    qs('#propuesta-tipo-label').innerText = t('realistic_plan', 'REALISTIC READING PLAN');
    qs('#propuesta-plan-titulo').innerText = targetBook.title;
    qs('#propuesta-sub-label').innerText = t('monthly_plan', 'MONTHLY PLAN');
    qs('#propuesta-carga').innerText = format('suggested_load', 'Suggested Load: %s PAGES / SESSION', pps);
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
    if (baselines.form_habit?.time) {
      metrics.minutes_per_session = String(baselines.form_habit.time);
    }
    if (baselines.form_habit?.intensity) {
      metrics.habit_intensity = String(baselines.form_habit.intensity);
    }
    return metrics;
  }

  function buildGoals() {
    const goals = [];
    const selectedGoals = state.formData.goals || [];
    const activeBook = state.formData.books && state.formData.books.length ? state.formData.books[0] : null;

    if (selectedGoals.includes('complete_books')) {
      const totalPages = parseInt(activeBook?.pages, 10) || state.calculatedPlan.totalPages || 0;
      const targetValue = totalPages > 0 ? totalPages : (parseInt(state.calculatedPlan.pps, 10) || 0);
      const metric = totalPages > 0 ? 'pages_total' : 'pages_per_session';
      const period = totalPages > 0 ? 'plan' : 'session';
      if (targetValue > 0) {
        goals.push({
          goal_kind: 'complete_books',
          metric,
          target_value: targetValue,
          period,
          book_id: activeBook?.bookId || null,
        });
      }
    }

    if (selectedGoals.includes('form_habit')) {
      const sessionsPerWeek = EXIGENCIA_SESSIONS[state.formData.exigencia] || 0;
      if (sessionsPerWeek > 0) {
        goals.push({
          goal_kind: 'form_habit',
          metric: 'sessions_per_week',
          target_value: sessionsPerWeek,
          period: 'week',
        });
      }
    }

    return goals;
  }

  function buildPlannedSessions() {
    const sessions = state.calculatedPlan.sessions ? state.calculatedPlan.sessions.slice() : [];
    sessions.sort((a, b) => a.date - b.date);
    const activeBook = state.formData.books && state.formData.books.length ? state.formData.books[0] : null;
    const totalPages = parseInt(activeBook?.pages, 10) || state.calculatedPlan.totalPages || 0;
    const pps = parseInt(state.calculatedPlan.pps, 10) || 0;

    return sessions.map((session, idx) => {
      const planned = {
        planned_start_datetime: formatDateTime(session.date, 0, 0),
        planned_end_datetime: formatDateTime(session.date, 0, 30),
        planned_start_page: null,
        planned_end_page: null,
      };

      if (totalPages > 0 && pps > 0) {
        const startPage = (idx * pps) + 1;
        const endPage = Math.min(startPage + pps - 1, totalPages);
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
      const totalPages = state.calculatedPlan.totalPages || state.formData.books[0]?.pages || 0;
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
      const totalPages = state.calculatedPlan.totalPages || state.formData.books[0]?.pages || 0;
      const sessionCount = state.calculatedPlan.sessions.length || 1;
      state.calculatedPlan.pps = Math.ceil(totalPages / sessionCount);
      updateCargaLabel();
    }
  }

  function renderCalendar() {
    const viewDate = new Date(TODAY.getFullYear(), TODAY.getMonth() + state.currentMonthOffset, 1);
    qs('#propuesta-mes-label').innerText = `${MONTH_NAMES[viewDate.getMonth()]} ${viewDate.getFullYear()}`;
    qs('#calendar-prev-month').classList.toggle('disabled', state.currentMonthOffset <= 0);
    calendarGrid.innerHTML = '';
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
        mark.draggable = true;
        mark.innerText = sess.order;
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
        mark.addEventListener('dragstart', (e) => {
          if (e.target && e.target.classList.contains('session-remove')) {
            e.preventDefault();
            return;
          }
          e.dataTransfer.setData('sessionDate', sess.date.toISOString());
          mark.classList.add('opacity-40');
        });
        mark.addEventListener('dragend', () => mark.classList.remove('opacity-40'));
        cell.appendChild(mark);
      }

      if (!isPast) {
        cell.addEventListener('dragover', (e) => {
          e.preventDefault();
          if (!state.calculatedPlan.sessions.find((s) => s.date.toDateString() === cellDate.toDateString())) {
            cell.classList.add('drag-over');
          }
        });
        cell.addEventListener('dragleave', () => cell.classList.remove('drag-over'));
        cell.addEventListener('drop', (e) => {
          e.preventDefault();
          cell.classList.remove('drag-over');
          const originIso = e.dataTransfer.getData('sessionDate');
          if (originIso) {
            const sessIdx = state.calculatedPlan.sessions.findIndex((s) => s.date.toISOString() === originIso);
            if (sessIdx > -1) {
              state.calculatedPlan.sessions[sessIdx].date = new Date(cellDate);
              renderCalendar();
              if (state.currentViewMode === 'list') renderList();
            }
          }
        });
        if (!sess) {
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
    pageSessions.forEach((s) => {
      const monthName = MONTH_NAMES[s.date.getMonth()];
      listView.innerHTML += `
        <div class="flex items-center justify-between p-3 bg-white border border-[#A8A8A8] rounded-custom shadow-sm mb-2 step-transition">
          <div class="flex items-center space-x-3">
            <div class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-medium text-black bg-[#C79F32]">${s.order}</div>
            <span class="text-xs font-medium text-black uppercase tracking-tight">${t('reading_session', 'Reading Session')}</span>
          </div>
          <span class="text-[10px] font-medium text-black opacity-60 uppercase tracking-tighter">${s.date.getDate()} ${monthName}</span>
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
    const planName = activeBook?.title || t('plan_title_default', 'Plan Title');
    const payload = {
      name: planName,
      plan_type: state.calculatedPlan.type || 'custom',
      status: 'accepted',
      goals,
      baselines,
      planned_sessions: buildPlannedSessions(),
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
      .then(() => {
        if (successEl) {
          successEl.textContent = t('plan_created', 'Plan created successfully.');
          successEl.classList.remove('hidden');
        }
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
