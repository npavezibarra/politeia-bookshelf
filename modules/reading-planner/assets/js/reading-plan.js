(function(){
  const overlay = document.getElementById('politeia-reading-plan-overlay');
  const openBtn = document.getElementById('politeia-open-reading-plan');
  if (!overlay || !openBtn) return;

  const qs = (selector) => overlay.querySelector(selector);

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
  const EXIGENCIA_SESSIONS = { liviano: 2, mediano: 4, exigente: 6, intenso: 7 };

  const HABIT_INTENSITY_CONFIG = {
    liviano: { time: 15, label: 'LIVIANO', reason: 'Es el "número mágico" para ver avances sin que se sienta como una carga.' },
    mediano: { time: 30, label: 'MEDIANO', reason: 'Permite terminar un capítulo completo, generando una sensación real de logro.' },
    intenso: { time: 60, label: 'INTENSO', reason: 'Ideal para quienes quieren que la lectura sea una parte central de su identidad.' },
  };

  const MONTH_NAMES = [
    'ENERO',
    'FEBRERO',
    'MARZO',
    'ABRIL',
    'MAYO',
    'JUNIO',
    'JULIO',
    'AGOSTO',
    'SEPTIEMBRE',
    'OCTUBRE',
    'NOVIEMBRE',
    'DICIEMBRE',
  ];

  const GOALS_DEF = [
    { id: 'complete_books', title: 'Terminar un libro', description: 'Finalizar obras específicas en un tiempo determinado.', icon: 'book-open' },
    { id: 'form_habit', title: 'Formar hábito', description: 'Aumentar la frecuencia y constancia de tus lecturas.', icon: 'calendar' },
  ];

  const getInitialState = () => ({
    mainStep: 1,
    isBaselineActive: false,
    baselineIndex: 0,
    subStep: 0,
    formData: {
      goals: [],
      baselines: {},
      books: [],
      exigencia: null,
    },
    calculatedPlan: { pps: 0, durationWeeks: 0, sessions: [], type: 'ccl' },
    currentMonthOffset: 0,
    currentViewMode: 'calendar',
    listCurrentPage: 0,
  });

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
      <div class="space-y-6 step-transition">
        <div class="text-center mb-8">
          <h2 class="text-2xl font-medium text-black uppercase tracking-tight">¿Qué objetivo quieres conseguir?</h2>
          <p class="text-sm font-medium">Selecciona tu meta principal</p>
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
            <span class="text-[#C79F32] font-medium text-[10px] uppercase tracking-widest">Diagnóstico</span>
            <h2 class="text-2xl font-medium text-black mt-2 uppercase">¿Cuántos libros terminaste el último año?</h2>
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
              <h2 class="text-xl font-medium text-black uppercase">¿Cuántas páginas tenía el libro?</h2>
            </div>
            <div class="space-y-4 max-h-[360px] overflow-y-auto pr-2 custom-scrollbar">
              ${Array.from({ length: slots }).map((_, i) => `
                <div class="p-5 bg-[#F5F5F5] rounded-custom border border-[#A8A8A8]">
                  <p class="text-[10px] font-medium text-[#A8A8A8] mb-3 uppercase tracking-widest">Libro #${i + 1}</p>
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
            <span class="text-[#C79F32] font-medium text-[10px] uppercase tracking-widest">Frecuencia Base</span>
            <h2 class="text-2xl font-medium text-black mt-2 uppercase">¿Cuántas sesiones de lectura tuviste este último mes?</h2>
            <div class="max-w-xs mx-auto mt-10">
              <input type="number" id="habit-sessions-input" value="${state.formData.baselines[gid]?.value || ''}" data-habit-sessions placeholder="Ej: 8" class="w-full text-center text-4xl font-bold p-6 bg-[#F5F5F5] border-2 border-[#A8A8A8] rounded-custom outline-none focus:border-[#C79F32] transition-colors" />
              <button type="button" id="habit-confirm-freq" class="w-full mt-6 bg-[#C79F32] text-black py-4 rounded-custom font-bold uppercase text-[10px] tracking-widest ${state.formData.baselines[gid]?.value ? '' : 'opacity-30 pointer-events-none'}">Confirmar y Seguir</button>
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
            <span class="text-[#C79F32] font-medium text-[10px] uppercase tracking-widest">Unidad Mínima</span>
            <h2 class="text-2xl font-medium text-black mt-2 uppercase">¿Cuánto tiempo dedicaste, en promedio, a cada sesión?</h2>
            <div class="grid grid-cols-2 gap-3 mt-6">
              ${[15, 30, 45, 60].map((m) => `
                <button type="button" id="habit-time-${m}" data-habit-time="${m}" class="p-5 border-2 rounded-custom transition-all ${
                  parseInt(currentTime, 10) === m
                    ? 'border-[#C79F32] bg-[#F5F5F5] text-[#C79F32]'
                    : 'border-[#A8A8A8] bg-white text-[#A8A8A8] hover:border-[#C79F32]'
                }"><span class="block text-2xl font-bold">${m}</span><span class="text-[10px] font-medium uppercase tracking-widest">minutos</span></button>`).join('')}
            </div>
            <div class="mt-6 flex flex-col items-center">
              <div class="flex items-center justify-center space-x-3">
                <span class="text-[10px] font-bold text-[#A8A8A8] uppercase tracking-widest">u otro:</span>
                <input type="number" id="habit-time-custom" value="${![15, 30, 45, 60].includes(parseInt(currentTime, 10)) ? currentTime : ''}" data-habit-time-custom placeholder="20" class="w-20 text-center text-lg font-bold p-2 bg-[#F5F5F5] border-2 border-[#A8A8A8] rounded-custom outline-none focus:border-[#C79F32]" />
                <span class="text-[10px] font-bold text-[#A8A8A8] uppercase tracking-widest">min</span>
              </div>
              <button type="button" id="habit-confirm-time" class="w-full mt-4 bg-[#C79F32] text-black py-3 rounded-custom font-bold uppercase text-[10px] tracking-widest ${state.formData.baselines[gid]?.time ? '' : 'opacity-30 pointer-events-none'}">Confirmar</button>
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
            <span class="text-[#C79F32] font-medium text-[10px] uppercase tracking-widest">Ambición Diaria</span>
            <h2 class="text-2xl font-medium text-black mt-2 uppercase">¿Qué tan intenso quieres que sea este hábito?</h2>
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
                      <span class="text-[10px] font-black text-[#C79F32] bg-[#C79F32]/10 px-2 py-1 rounded">${config.time} MIN / DÍA</span>
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
        <div class="text-center mb-6"><h2 class="text-2xl font-medium text-black uppercase tracking-tight">¿Qué libro quieres leer ahora?</h2></div>
        <div class="reading-plan-book-display">
          <div class="book-placeholder">
            <div id="book-display" class="book-inner text-[#A8A8A8] ${hasBook ? 'is-filled' : ''} ${activeBook?.cover ? 'has-cover' : ''}">
              <img id="book-cover" class="book-cover${activeBook?.cover ? '' : ' hidden'}" alt="" src="${activeBook?.cover ? activeBook.cover : ''}">
              <div id="placeholder-content" class="flex flex-column items-center flex-col${hasBook ? ' hidden' : ''}">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mb-2 opacity-50">
                  <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                  <path d="M8 7h6"/><path d="M8 11h8"/>
                </svg>
                <span class="text-[10px] font-bold uppercase tracking-[0.2em]">Tu Libro</span>
              </div>
              <div id="filled-content" class="w-full h-full flex flex-col justify-center${hasBook ? '' : ' hidden'}">
                <div id="display-title" class="book-title-display"></div>
                <div class="w-8 h-px bg-[#C79F32] mx-auto my-3"></div>
                <div id="display-author" class="book-author-display"></div>
              </div>
            </div>
          </div>
          ${hasBook ? `
            <button type="button" id="remove-book-current" class="book-remove" aria-label="Eliminar libro">
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
            Siguiente
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="m9 18 6-6-6-6"/>
            </svg>
          </button>
        ` : `
          <div class="bg-[#F5F5F5] p-6 rounded-custom border border-[#A8A8A8] space-y-4">
            <div class="relative">
              <input id="new-book-title" type="text" placeholder="Título del libro" autocomplete="off" class="w-full p-3 border border-[#A8A8A8] rounded-custom outline-none text-sm bg-white font-medium focus:ring-1 focus:ring-[#C79F32] transition-all">
              <div id="reading-plan-title-suggestions" class="prs-add-book__suggestions" aria-hidden="true"></div>
            </div>
            <div class="reading-plan-book-row">
              <div class="reading-plan-author">
                <input id="new-book-author" type="text" placeholder="Autor" class="w-full p-3 border border-[#A8A8A8] rounded-custom outline-none text-sm bg-white font-medium focus:ring-1 focus:ring-[#C79F32] transition-all">
              </div>
              <div class="reading-plan-pages">
                <input id="new-book-pages" type="number" placeholder="Págs" class="w-full p-3 border border-[#A8A8A8] rounded-custom outline-none text-sm bg-white font-medium focus:ring-1 focus:ring-[#C79F32] transition-all">
              </div>
            </div>
            <div class="space-y-3">
              <button type="button" id="add-book" class="w-full bg-[#C79F32] text-black py-4 rounded-custom hover:brightness-95 transition-all flex items-center justify-center gap-2 text-[10px] font-bold uppercase tracking-widest">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M5 12h14"></path>
                  <path d="M12 5v14"></path>
                </svg>
                Añadir libro
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
      if (displayAuthor) displayAuthor.textContent = activeBook.author || 'Autor Desconocido';
      if (metaText) metaText.innerHTML = `${activeBook.title || ''} by ${activeBook.author || 'Autor'} <br> ${activeBook.pages || ''} páginas`;
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

    stepContent.innerHTML = `
      <div class="exigencia-slide step-transition">
        <div class="text-center mb-6"><h2 class="text-2xl font-medium text-black uppercase tracking-tight">¿Qué nivel de exigencia quieres?</h2></div>
        <div class="exigencia-grid">
          ${['mediano', 'exigente', 'intenso'].map((k) => `
            <button type="button" id="exigencia-${k}" data-exigencia="${k}" class="exigencia-card ${state.formData.exigencia === k ? 'is-selected' : ''}">
              <div class="exigencia-dot ${intensityStyles[k]}"></div>
              <h3 class="exigencia-title">${k}</h3>
              <p class="exigencia-meta">${EXIGENCIA_SESSIONS[k]} sesiones<br>por semana</p>
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

    qs('#propuesta-tipo-label').innerText = 'PROPUESTA DE FORMACIÓN DE HÁBITO';
    qs('#propuesta-plan-titulo').innerText = `HÁBITO DE ${dailyMinutes} MIN / DÍA`;
    qs('#propuesta-sub-label').innerText = 'CICLO DE CONSOLIDACIÓN (42 DÍAS)';
    qs('#propuesta-carga').innerText = `Carga Estimada: ${pps} PÁGINAS / SESIÓN`;
    qs('#propuesta-duracion').innerText = 'Duración del ciclo: 6 SEMANAS';

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

    qs('#propuesta-tipo-label').innerText = 'PROPUESTA DE LECTURA REALISTA';
    qs('#propuesta-plan-titulo').innerText = targetBook.title;
    qs('#propuesta-sub-label').innerText = 'PLANIFICACIÓN MENSUAL';
    qs('#propuesta-carga').innerText = `Carga Sugerida: ${pps} PÁGINAS / SESIÓN`;
    qs('#propuesta-duracion').innerText = `Duración estimada: ${totalWeeks} semanas`;

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
    const patterns = { 2: [0, 3], 4: [0, 2, 4, 6], 6: [0, 1, 2, 3, 4, 5], 7: [0, 1, 2, 3, 4, 5, 6] };
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

  function updateCargaLabel() {
    if (state.calculatedPlan.type === 'ccl') {
      qs('#propuesta-carga').innerText = `Carga Sugerida: ${state.calculatedPlan.pps} PÁGINAS / SESIÓN`;
    } else {
      qs('#propuesta-carga').innerText = `Carga Estimada: ${state.calculatedPlan.pps} PÁGINAS / SESIÓN`;
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
        removeBtn.setAttribute('aria-label', 'Eliminar sesión');
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
              addBtn.setAttribute('aria-label', 'Añadir sesión');
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
      listView.innerHTML = '<p class="text-center text-[10px] uppercase font-medium opacity-40 py-8 tracking-widest">Sin sesiones</p>';
      pagination.classList.add('hidden');
      return;
    }

    const totalPages = Math.ceil(totalSessions / SESSIONS_PER_PAGE);
    pagination.classList.remove('hidden');
    qs('#list-page-info').innerText = `${state.listCurrentPage + 1} / ${totalPages}`;
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
            <span class="text-xs font-medium text-black uppercase tracking-tight">Sesión de Lectura</span>
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

  resetForm();
})();
