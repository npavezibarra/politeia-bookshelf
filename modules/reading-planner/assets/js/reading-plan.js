(function(){
  const overlay = document.getElementById('politeia-reading-plan-overlay');
  const openBtn = document.getElementById('politeia-open-reading-plan');
  if (!overlay || !openBtn) return;

  const qs = (selector) => overlay.querySelector(selector);

  const formContainer = qs('#form-container');
  const summaryContainer = qs('#summary-container');
  const stepContent = qs('#step-content');
  const nextBtn = qs('#next-btn');
  const backBtn = qs('#back-btn');
  const calendarGrid = qs('#calendar-grid');
  const listView = qs('#list-view');

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
  const TODAY = new Date(2026, 0, 6);
  const K_GROWTH = 1.15;
  const MIN_PPS = 15;
  const SESSIONS_PER_PAGE = 10;
  const BENCHMARK_MIN_PER_PAGE = 1.5;

  const PAGE_RANGES_MAP = { "<100": 80, "200~": 200, "400~": 400, "600~": 600, "1000~": 1000 };
  const EXIGENCIA_SESSIONS = { liviano: 2, mediano: 4, exigente: 6, intenso: 7 };

  const HABIT_INTENSITY_CONFIG = {
    liviano: { time: 15, label: 'LIVIANO', reason: 'Es el "numero magico" para ver avances sin que se sienta como una carga.' },
    mediano: { time: 30, label: 'MEDIANO', reason: 'Permite terminar un capitulo completo, generando una sensacion real de logro.' },
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
    { id: 'complete_books', title: 'Terminar un libro', description: 'Finalizar obras especificas en un tiempo determinado.', icon: 'book-open' },
    { id: 'form_habit', title: 'Formar habito', description: 'Aumentar la frecuencia y constancia de tus lecturas.', icon: 'calendar' },
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
    if (backBtn) backBtn.classList.toggle('hidden', state.mainStep === 1 && !state.isBaselineActive);
    updateProgressBar();
    const stepBadge = qs('#step-badge');
    if (stepBadge) stepBadge.innerText = `Seccion ${state.mainStep} de 3`;

    if (state.mainStep === 1) {
      if (!state.isBaselineActive) renderStepGoals();
      else renderStepBaseline();
    } else if (state.mainStep === 2) {
      renderStepBooks();
    } else if (state.mainStep === 3) {
      renderStepExigencia();
    }

    if (nextBtn) {
      nextBtn.disabled = !isStepValid();
      const label = nextBtn.querySelector('span');
      if (label) {
        label.innerText =
          state.mainStep === 3 ||
          (state.formData.goals[0] === 'form_habit' && state.subStep === 2)
            ? 'Ver Propuesta'
            : 'Siguiente';
      }
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
          <h2 class="text-2xl font-medium text-black uppercase tracking-tight">¿Que objetivo quieres conseguir?</h2>
          <p class="text-sm font-medium">Selecciona tu meta principal</p>
        </div>
        <div class="grid grid-cols-1 gap-4 w-full">
          ${GOALS_DEF.map((goal) => {
            const sel = state.formData.goals.includes(goal.id);
            return `<button type="button" data-goal="${goal.id}" class="w-full p-6 text-left border-2 rounded-custom transition-all ${
              sel
                ? 'border-[#C79F32] bg-[#F5F5F5] ring-2 ring-[#C79F32]'
                : 'border-[#A8A8A8] bg-[#FEFEFF] hover:border-[#C79F32]'
            }">
              <div class="flex items-start gap-4">
                <i data-lucide="${goal.icon}" class="w-8 h-8 ${sel ? 'text-[#C79F32]' : 'text-[#A8A8A8]'}"></i>
                <div>
                  <h3 class="font-medium text-black uppercase text-sm">${goal.title}</h3>
                  <p class="text-xs mt-1 font-medium leading-relaxed">${goal.description}</p>
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
        render();
      });
    });
  }

  function renderStepBaseline() {
    const gid = state.formData.goals[0];

    if (gid === 'complete_books') {
      if (state.subStep === 0) {
        stepContent.innerHTML = `
          <div class="space-y-8 text-center step-transition">
            <span class="text-[#C79F32] font-medium text-[10px] uppercase tracking-widest">Diagnostico</span>
            <h2 class="text-2xl font-medium text-black mt-2 uppercase">¿Cuantos libros terminaste el ultimo ano?</h2>
            <div class="grid grid-cols-5 gap-3">
              ${['0', '1', '2', '3', '4+'].map((n) => `
                <button type="button" data-baseline="${n}" class="aspect-square rounded-custom border-2 font-medium text-xl transition-all ${
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
            render();
          });
        });
      } else {
        const count = parseInt(state.formData.baselines[gid]?.value, 10) || 0;
        const slots = count >= 4 ? 4 : count;
        stepContent.innerHTML = `
          <div class="space-y-6 step-transition">
            <div class="text-center">
              <h2 class="text-xl font-medium text-black uppercase">¿Que extension tenian esos libros?</h2>
            </div>
            <div class="space-y-4 max-h-[360px] overflow-y-auto pr-2 custom-scrollbar">
              ${Array.from({ length: slots }).map((_, i) => `
                <div class="p-5 bg-[#F5F5F5] rounded-custom border border-[#A8A8A8]">
                  <p class="text-[10px] font-medium text-[#A8A8A8] mb-3 uppercase tracking-widest">Libro #${i + 1}</p>
                  <div class="flex flex-wrap gap-2">
                    ${Object.keys(PAGE_RANGES_MAP).map((r) => `
                      <button type="button" data-book-detail="${r}" data-book-index="${i}" class="px-3 py-2 rounded-custom text-[10px] font-medium border-2 transition-all uppercase ${
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
            render();
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
            <h2 class="text-2xl font-medium text-black mt-2 uppercase">¿Cuantas sesiones de lectura tuviste este ultimo mes?</h2>
            <div class="max-w-xs mx-auto mt-10">
              <input type="number" value="${state.formData.baselines[gid]?.value || ''}" data-habit-sessions placeholder="Ej: 8" class="w-full text-center text-4xl font-bold p-6 bg-[#F5F5F5] border-2 border-[#A8A8A8] rounded-custom outline-none focus:border-[#C79F32] transition-colors" />
              <p class="text-xs text-[#A8A8A8] font-bold uppercase mt-4 tracking-widest">Sesiones Realizadas</p>
            </div>
          </div>`;

        const input = stepContent.querySelector('[data-habit-sessions]');
        if (input) {
          input.addEventListener('input', (e) => {
            state.formData.baselines[gid] = { ...state.formData.baselines[gid], value: e.target.value };
            render();
          });
        }
      } else if (state.subStep === 1) {
        const currentTime = state.formData.baselines[gid]?.time || '';
        stepContent.innerHTML = `
          <div class="space-y-8 text-center step-transition">
            <span class="text-[#C79F32] font-medium text-[10px] uppercase tracking-widest">Unidad Minima</span>
            <h2 class="text-2xl font-medium text-black mt-2 uppercase">¿Cuanto tiempo dedicaste, en promedio, a cada sesion?</h2>
            <div class="grid grid-cols-2 gap-3 mt-6">
              ${[15, 30, 45, 60].map((m) => `
                <button type="button" data-habit-time="${m}" class="p-5 border-2 rounded-custom transition-all ${
                  parseInt(currentTime, 10) === m
                    ? 'border-[#C79F32] bg-[#F5F5F5] text-[#C79F32]'
                    : 'border-[#A8A8A8] bg-white text-[#A8A8A8] hover:border-[#C79F32]'
                }"><span class="block text-2xl font-bold">${m}</span><span class="text-[10px] font-medium uppercase tracking-widest">minutos</span></button>`).join('')}
            </div>
            <div class="mt-6 flex items-center justify-center space-x-3">
              <span class="text-[10px] font-bold text-[#A8A8A8] uppercase tracking-widest">u otro:</span>
              <input type="number" value="${![15, 30, 45, 60].includes(parseInt(currentTime, 10)) ? currentTime : ''}" data-habit-time-custom placeholder="20" class="w-20 text-center text-lg font-bold p-2 bg-[#F5F5F5] border-2 border-[#A8A8A8] rounded-custom outline-none focus:border-[#C79F32]" />
              <span class="text-[10px] font-bold text-[#A8A8A8] uppercase tracking-widest">min</span>
            </div>
          </div>`;

        stepContent.querySelectorAll('[data-habit-time]').forEach((btn) => {
          btn.addEventListener('click', () => {
            const val = btn.getAttribute('data-habit-time');
            state.formData.baselines.form_habit = { ...state.formData.baselines.form_habit, time: val };
            render();
          });
        });
        const customInput = stepContent.querySelector('[data-habit-time-custom]');
        if (customInput) {
          customInput.addEventListener('input', (e) => {
            state.formData.baselines.form_habit = { ...state.formData.baselines.form_habit, time: e.target.value };
            render();
          });
        }
      } else if (state.subStep === 2) {
        const currentIntensity = state.formData.baselines[gid]?.intensity || '';
        stepContent.innerHTML = `
          <div class="space-y-6 text-center step-transition">
            <span class="text-[#C79F32] font-medium text-[10px] uppercase tracking-widest">Ambicion Diaria</span>
            <h2 class="text-2xl font-medium text-black mt-2 uppercase">¿Que tan intenso quieres que sea este habito?</h2>
            <div class="grid grid-cols-1 gap-4 mt-6">
              ${Object.keys(HABIT_INTENSITY_CONFIG).map((key) => {
                const config = HABIT_INTENSITY_CONFIG[key];
                return `
                  <button type="button" data-habit-intensity="${key}" class="p-5 border-2 text-left rounded-custom transition-all ${
                    currentIntensity === key
                      ? 'border-[#C79F32] bg-[#F5F5F5] ring-2 ring-[#C79F32]'
                      : 'border-[#A8A8A8] bg-white hover:border-[#C79F32]'
                  }">
                    <div class="flex justify-between items-center mb-1">
                      <h3 class="font-bold text-black uppercase text-sm">${config.label}</h3>
                      <span class="text-[10px] font-black text-[#C79F32] bg-[#C79F32]/10 px-2 py-1 rounded">${config.time} MIN / DIA</span>
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
            render();
          });
        });
      }
    }
  }

  function renderStepBooks() {
    stepContent.innerHTML = `
      <div class="space-y-6 step-transition">
        <div class="text-center mb-6"><h2 class="text-2xl font-medium text-black uppercase tracking-tight">¿Que libro quieres leer ahora?</h2></div>
        <div class="bg-[#F5F5F5] p-6 rounded-custom border border-[#A8A8A8] space-y-4">
          <input id="new-book-title" type="text" placeholder="Titulo del libro" class="w-full p-3 border border-[#A8A8A8] rounded-custom outline-none text-sm bg-white font-medium">
          <div class="flex gap-2">
            <input id="new-book-pages" type="number" placeholder="Numero de paginas" class="flex-1 p-3 border border-[#A8A8A8] rounded-custom outline-none text-sm bg-white font-medium">
            <select id="new-book-complexity" class="w-36 p-3 border border-[#A8A8A8] rounded-custom text-xs bg-white outline-none font-medium">
              <option value="1.0">Ficcion</option>
              <option value="1.25">No Ficcion</option>
              <option value="1.5">Tecnico</option>
            </select>
          </div>
          <button type="button" id="add-book" class="w-full bg-[#C79F32] text-black py-3 rounded-custom hover:opacity-90 flex items-center justify-center gap-2 text-xs font-medium uppercase tracking-widest">
            <i data-lucide="plus" class="w-4 h-4"></i> Anadir libro
          </button>
        </div>
        <div id="book-list-container" class="space-y-2 max-h-32 overflow-y-auto pr-2 custom-scrollbar">
          ${state.formData.books.map((b) => `
            <div class="flex items-center justify-between p-4 bg-white border border-[#A8A8A8] rounded-custom">
              <div class="flex items-center gap-3">
                <i data-lucide="book-marked" class="text-[#C79F32] w-5 h-5"></i>
                <div>
                  <p class="text-sm font-medium text-black uppercase tracking-tighter">${b.title}</p>
                  <p class="text-[9px] text-[#A8A8A8] font-bold uppercase">${b.pages} paginas</p>
                </div>
              </div>
              <button type="button" data-remove-book="${b.id}" class="text-[#FF8C00] p-2 hover:bg-[#F5F5F5] rounded-full transition-colors">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
              </button>
            </div>`).join('')}
        </div>
      </div>`;

    const addButton = stepContent.querySelector('#add-book');
    if (addButton) {
      addButton.addEventListener('click', () => {
        const t = stepContent.querySelector('#new-book-title')?.value?.trim();
        const p = parseInt(stepContent.querySelector('#new-book-pages')?.value, 10);
        const c = stepContent.querySelector('#new-book-complexity')?.value;
        if (t && p) {
          state.formData.books.push({ id: Date.now(), title: t, pages: p, complexity: parseFloat(c) });
          render();
        }
      });
    }

    stepContent.querySelectorAll('[data-remove-book]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = parseInt(btn.getAttribute('data-remove-book'), 10);
        state.formData.books = state.formData.books.filter((b) => b.id !== id);
        render();
      });
    });
  }

  function renderStepExigencia() {
    stepContent.innerHTML = `
      <div class="space-y-8 step-transition">
        <div class="text-center mb-8"><h2 class="text-2xl font-medium text-black uppercase tracking-tight">¿Que nivel de exigencia quieres?</h2></div>
        <div class="grid grid-cols-1 gap-4">
          ${Object.keys(EXIGENCIA_SESSIONS).map((k) => `
            <button type="button" data-exigencia="${k}" class="w-full p-6 text-center border-2 rounded-custom transition-all ${
              state.formData.exigencia === k
                ? 'border-[#C79F32] bg-[#F5F5F5] ring-2 ring-[#C79F32]'
                : 'border-[#A8A8A8] bg-white hover:border-[#C79F32]'
            }">
              <h3 class="font-medium text-black uppercase text-lg tracking-wide">${k}</h3>
              <p class="text-xs font-medium mt-1 uppercase tracking-widest text-black/40">${EXIGENCIA_SESSIONS[k]} sesiones por semana</p>
            </button>`).join('')}
        </div>
      </div>`;

    stepContent.querySelectorAll('[data-exigencia]').forEach((btn) => {
      btn.addEventListener('click', () => {
        state.formData.exigencia = btn.getAttribute('data-exigencia');
        render();
      });
    });
  }

  function isStepValid() {
    const d = state.formData;
    const gid = d.goals[0];
    if (state.mainStep === 1) {
      if (!state.isBaselineActive) return d.goals.length > 0;
      const b = d.baselines[gid];
      if (!b?.value) return false;
      if (gid === 'complete_books' && state.subStep === 1) {
        const target = parseInt(b.value, 10) >= 4 ? 4 : parseInt(b.value, 10);
        return (b.details?.length >= target) && b.details.every((item) => item);
      }
      if (gid === 'form_habit') {
        if (state.subStep === 0) return !isNaN(parseInt(b.value, 10)) && parseInt(b.value, 10) >= 0;
        if (state.subStep === 1) return !isNaN(parseInt(b.time, 10)) && parseInt(b.time, 10) > 0;
        if (state.subStep === 2) return !!b.intensity;
      }
      return true;
    }
    if (state.mainStep === 2) return d.books.length > 0;
    if (state.mainStep === 3) return !!d.exigencia;
    return true;
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

    qs('#propuesta-tipo-label').innerText = 'PROPUESTA DE FORMACION DE HABITO';
    qs('#propuesta-plan-titulo').innerText = `HABITO DE ${dailyMinutes} MIN / DIA`;
    qs('#propuesta-sub-label').innerText = 'CICLO DE CONSOLIDACION (42 DIAS)';
    qs('#propuesta-carga').innerText = `Carga Estimada: ${pps} PAGINAS / SESION`;
    qs('#propuesta-duracion').innerText = 'Duracion del ciclo: 6 SEMANAS';

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
    };

    qs('#propuesta-tipo-label').innerText = 'PROPUESTA DE LECTURA REALISTA';
    qs('#propuesta-plan-titulo').innerText = targetBook.title;
    qs('#propuesta-sub-label').innerText = 'PLANIFICACION MENSUAL';
    qs('#propuesta-carga').innerText = `Carga Sugerida: ${pps} PAGINAS / SESION`;
    qs('#propuesta-duracion').innerText = `Duracion estimada: ${totalWeeks} semanas`;

    formContainer.classList.add('hidden');
    summaryContainer.classList.remove('hidden');
    state.currentMonthOffset = 0;
    state.listCurrentPage = 0;
    renderCalendar();
  }

  function generateHabitSessions(total, perWeek) {
    const sessions = [];
    const dayPattern = [1, 2, 3, 4, 5, 6];
    for (let i = 0; i < total; i++) {
      const weekNum = Math.floor(i / perWeek);
      const dayOffset = (weekNum * 7) + dayPattern[i % perWeek];
      const date = new Date(TODAY);
      date.setDate(TODAY.getDate() + dayOffset);
      if (date >= TODAY) sessions.push({ date, order: i + 1 });
      else total++;
    }
    return sessions;
  }

  function generateSessions(total, perWeek) {
    const sessions = [];
    const patterns = { 2: [1, 4], 4: [1, 3, 5, 0], 6: [1, 2, 3, 4, 5, 6], 7: [1, 2, 3, 4, 5, 6, 0] };
    const myPattern = patterns[perWeek];
    for (let i = 0; i < total; i++) {
      const weekNum = Math.floor(i / perWeek);
      const dayOffset = (weekNum * 7) + myPattern[i % perWeek];
      const date = new Date(TODAY);
      date.setDate(TODAY.getDate() + dayOffset);
      if (date >= TODAY) sessions.push({ date, order: i + 1 });
      else total++;
    }
    return sessions;
  }

  function renderCalendar() {
    const viewDate = new Date(TODAY.getFullYear(), TODAY.getMonth() + state.currentMonthOffset, 1);
    qs('#propuesta-mes-label').innerText = `${MONTH_NAMES[viewDate.getMonth()]} ${viewDate.getFullYear()}`;
    qs('#calendar-prev-month').classList.toggle('disabled', state.currentMonthOffset <= 0);
    calendarGrid.innerHTML = '';
    const daysInMonth = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0).getDate();
    const startOffset = viewDate.getDay();
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
        mark.addEventListener('dragstart', (e) => {
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
            <span class="text-xs font-medium text-black uppercase tracking-tight">Sesion de Lectura</span>
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

  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      const gid = state.formData.goals[0];
      if (state.mainStep === 1) {
        if (!state.isBaselineActive) {
          state.isBaselineActive = true;
          state.subStep = 0;
        } else if (gid === 'complete_books' && state.subStep === 0 && parseInt(state.formData.baselines[gid]?.value, 10) > 0) {
          state.subStep = 1;
        } else if (gid === 'form_habit' && state.subStep < 2) {
          state.subStep += 1;
        } else if (gid === 'form_habit' && state.subStep === 2) {
          calculateHabitPlan();
        } else {
          state.mainStep = 2;
          state.isBaselineActive = false;
        }
      } else if (state.mainStep < 3) {
        state.mainStep += 1;
      } else {
        calculateCCLPlan();
      }
      render();
    });
  }

  if (backBtn) {
    backBtn.addEventListener('click', () => {
      if (state.mainStep === 1) {
        if (state.subStep > 0) state.subStep -= 1;
        else state.isBaselineActive = false;
      } else {
        state.mainStep -= 1;
      }
      render();
    });
  }

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
