(function(){
  const overlay = document.getElementById('politeia-reading-plan-overlay');
  const openBtn = document.getElementById('politeia-open-reading-plan');
  if (!overlay || !openBtn) return;

  const GOALS = [
    { id: 'complete_books', title: 'Terminar uno o más libros', description: 'Finalizar obras específicas en un tiempo determinado.' },
    { id: 'more_pages', title: 'Leer más páginas por sesión', description: 'Aumentar tu velocidad o profundidad de lectura.' },
    { id: 'more_days', title: 'Aumentar días de lectura', description: 'Crear un hábito más frecuente durante la semana.' },
    { id: 'consistency', title: 'Leer de forma más constante', description: 'Mantener un ritmo estable durante un período largo.' },
  ];

  const PAGE_RANGES = ['<100', '200~', '400~', '600~', '1000~'];
  const WEEK_DAYS = [
    { id: 0, label: 'Lun' },
    { id: 1, label: 'Mar' },
    { id: 2, label: 'Mié' },
    { id: 3, label: 'Jue' },
    { id: 4, label: 'Vie' },
    { id: 5, label: 'Sáb' },
    { id: 6, label: 'Dom' },
  ];
  const PERIODS = [
    { id: '2w', label: '2 semanas', weeks: 2 },
    { id: '1m', label: '1 mes', weeks: 4 },
    { id: '3m', label: '3 meses', weeks: 12 },
    { id: 'custom', label: 'Personalizado', weeks: 0 },
  ];

  const state = {
    step: 1,
    baselineIndex: 0,
    subStep: 0,
    goals: [],
    baselines: {},
    books: [],
    availability: { days: [], sessionType: 'medium' },
    period: null,
    customWeeks: null,
  };

  const bodyByStep = (step) => document.querySelector(`[data-step-body="${step}"]`);

  const showStep = (step) => {
    document.querySelectorAll('[data-step]').forEach((el) => {
      el.hidden = el.dataset.step != step;
    });
    state.step = step;
    renderCurrentStep();
    syncNavDisable();
  };

  const closeModal = () => {
    overlay.hidden = true;
    document.body.style.overflow = '';
  };

  const openModal = () => {
    overlay.hidden = false;
    document.body.style.overflow = 'hidden';
    state.step = 1;
    state.baselineIndex = 0;
    state.subStep = 0;
    renderCurrentStep();
    syncNavDisable();
    showStep(1);
  };

  const validateStep = (step) => {
    if (step === 1) return state.goals.length > 0;
    if (step === 2) {
      const goalId = state.goals[state.baselineIndex];
      const baseline = state.baselines[goalId];
      if (!baseline || !baseline.value) return false;
      if (goalId === 'complete_books' && state.subStep === 1) {
        const count = parseInt(baseline.value, 10) || 0;
        const slots = count === 4 ? 4 : count;
        return Array.isArray(baseline.details) && baseline.details.length >= slots && baseline.details.every(Boolean);
      }
      return true;
    }
    if (step === 3) return state.books.length > 0;
    if (step === 4) return state.availability.days.length > 0;
    if (step === 5) return !!state.period && (state.period !== 'custom' || state.customWeeks);
    return true;
  };

  const syncNavDisable = () => {
    document.querySelectorAll('[data-step]').forEach((section) => {
      const step = parseInt(section.dataset.step, 10);
      const nextBtn = section.querySelector('.politeia-next');
      const submitBtn = section.querySelector('.politeia-submit');
      const valid = validateStep(step);
      if (nextBtn) nextBtn.disabled = !valid;
      if (submitBtn) submitBtn.disabled = !valid;
    });
  };

  const nextStep = () => {
    if (!validateStep(state.step)) return;
    if (state.step === 2) {
      const goalId = state.goals[state.baselineIndex];
      if (goalId === 'complete_books') {
        if (state.subStep === 0) {
          const val = parseInt(state.baselines[goalId]?.value, 10) || 0;
          if (val > 0) {
            state.subStep = 1;
            renderCurrentStep();
            syncNavDisable();
            return;
          }
        }
      }
      if (state.baselineIndex < state.goals.length - 1) {
        state.baselineIndex += 1;
        state.subStep = 0;
        renderCurrentStep();
        syncNavDisable();
        return;
      }
    }
    const next = Math.min(state.step + 1, 5);
    showStep(next);
  };

  const prevStep = () => {
    if (state.step === 2) {
      if (state.subStep === 1) {
        state.subStep = 0;
        renderCurrentStep();
        syncNavDisable();
        return;
      }
      if (state.baselineIndex > 0) {
        state.baselineIndex -= 1;
        state.subStep = state.goals[state.baselineIndex] === 'complete_books' ? 1 : 0;
        renderCurrentStep();
        syncNavDisable();
        return;
      }
      showStep(1);
      return;
    }
    const prev = Math.max(state.step - 1, 1);
    showStep(prev);
  };

  const submitPlan = () => {
    if (!window.PoliteiaReadingPlan) return;
    if (!validateStep(state.step)) return;
    const payload = buildReadingPlanPayload(state);
    fetch(window.PoliteiaReadingPlan.restUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': window.PoliteiaReadingPlan.nonce,
      },
      body: JSON.stringify(payload),
    })
      .then((res) => {
        if (!res.ok) throw new Error('Failed to create plan');
        return res.json();
      })
      .then(() => {
        alert('Tu plan de lectura ha comenzado');
        closeModal();
      })
      .catch((err) => {
        console.error(err);
        alert('No pudimos crear tu plan. Intenta nuevamente.');
      });
  };

  function renderStep1() {
    const root = bodyByStep(1);
    if (!root) return;
    const cards = GOALS.map((goal) => {
      const active = state.goals.includes(goal.id) ? 'is-active' : '';
      return `<button type="button" class="politeia-card ${active}" data-goal="${goal.id}">
        <div class="politeia-card-title">${goal.title}</div>
        <div class="politeia-card-desc">${goal.description}</div>
      </button>`;
    }).join('');
    root.innerHTML = `
      <div class="politeia-grid">${cards}</div>
      <p class="politeia-help">Selecciona uno o más objetivos.</p>
    `;
    root.querySelectorAll('[data-goal]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-goal');
        const exists = state.goals.includes(id);
        state.goals = exists ? state.goals.filter((g) => g !== id) : [...state.goals, id];
        state.baselineIndex = 0;
        state.subStep = 0;
        renderStep1();
        syncNavDisable();
      });
    });
  }

  function renderStep2() {
    const root = bodyByStep(2);
    if (!root) return;
    if (!state.goals.length) {
      root.innerHTML = '<div class="politeia-empty">Primero elige tus metas.</div>';
      return;
    }
    const goalId = state.goals[state.baselineIndex];
    const goal = GOALS.find((g) => g.id === goalId);
    const baseline = state.baselines[goalId] || {};

    if (goalId === 'complete_books' && state.subStep === 1) {
      const count = parseInt(baseline.value, 10) || 0;
      const slots = count === 4 ? 4 : count;
      const chips = Array.from({ length: slots }).map((_, idx) => {
        const val = baseline.details?.[idx] || '';
        const buttons = PAGE_RANGES.map((r) => {
          const active = val === r ? 'is-active' : '';
          return `<button type="button" class="politeia-pill ${active}" data-range="${r}" data-detail-index="${idx}">${r}</button>`;
        }).join('');
        return `<div class="politeia-card">
          <div class="politeia-card-title">Libro #${idx + 1}</div>
          <div class="politeia-inline-list">${buttons}</div>
        </div>`;
      }).join('');
      root.innerHTML = `
        <div class="politeia-list" style="gap:12px;">
          ${chips || '<div class="politeia-empty">No hay libros para detallar.</div>'}
        </div>
        <p class="politeia-help">Selecciona el rango de páginas para cada libro.</p>
      `;
      root.querySelectorAll('[data-range]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const idx = parseInt(btn.getAttribute('data-detail-index'), 10);
          const range = btn.getAttribute('data-range');
          const details = baseline.details ? [...baseline.details] : [];
          details[idx] = range;
          state.baselines[goalId] = { ...baseline, details };
          renderStep2();
          syncNavDisable();
        });
      });
      return;
    }

    if (goalId === 'complete_books') {
      const options = ['0', '1', '2', '3', '4+'].map((num) => {
        const active = baseline.value === num ? 'is-active' : '';
        return `<button type="button" class="politeia-card ${active}" data-complete-count="${num}">
          <div class="politeia-card-title">${num} libros</div>
          <div class="politeia-card-desc">Leídos el último año</div>
        </button>`;
      }).join('');
      root.innerHTML = `
        <div class="politeia-grid">${options}</div>
        <p class="politeia-help">Cuéntanos cuántos libros finalizaste.</p>
      `;
      root.querySelectorAll('[data-complete-count]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const val = btn.getAttribute('data-complete-count');
          state.baselines[goalId] = { ...baseline, value: val, details: [] };
          if (val !== '0') state.subStep = 1; else state.subStep = 0;
          renderStep2();
          syncNavDisable();
        });
      });
      return;
    }

    const questionMap = {
      more_pages: { q: 'En una sesión típica, ¿cuántas páginas sueles leer?', opts: ['<10', '10–20', '20–40', '40+'] },
      more_days: { q: '¿Cuántos días a la semana lees actualmente?', opts: ['0–1', '2–3', '4–5', '6–7'] },
      consistency: { q: '¿Cuántas semanas leíste al menos una vez el último mes?', opts: ['0', '1', '2', '3', '4'] },
    };
    const config = questionMap[goalId] || { q: '', opts: [] };
    const pills = config.opts.map((opt) => {
      const active = baseline.value === opt ? 'is-active' : '';
      return `<button type="button" class="politeia-pill ${active}" data-baseline-opt="${opt}">${opt}</button>`;
    }).join('');
    root.innerHTML = `
      <div class="politeia-list" style="gap:12px;">
        <div>
          <div class="politeia-card-title">${goal?.title || ''}</div>
          <p class="politeia-card-desc">${config.q}</p>
        </div>
        <div class="politeia-inline-list">${pills}</div>
      </div>
    `;
    root.querySelectorAll('[data-baseline-opt]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const val = btn.getAttribute('data-baseline-opt');
        state.baselines[goalId] = { ...baseline, value: val };
        renderStep2();
        syncNavDisable();
      });
    });
  }

  function renderStep3() {
    const root = bodyByStep(3);
    if (!root) return;
    const list = state.books.map((book) => {
      return `<div class="politeia-card">
        <div class="politeia-card-title">${book.title}</div>
        <div class="politeia-card-desc">${book.pages} páginas</div>
        <button type="button" class="politeia-pill" data-remove-book="${book.id}">Eliminar</button>
      </div>`;
    }).join('');
    root.innerHTML = `
      <div class="politeia-list" style="gap:12px;">
        <div class="politeia-input-row">
          <input type="text" class="politeia-field" id="politeia-book-title" placeholder="Título del libro" />
          <input type="number" class="politeia-field" id="politeia-book-pages" placeholder="Páginas" />
          <button type="button" class="politeia-pill" id="politeia-add-book">Agregar</button>
        </div>
        <div class="politeia-list" style="gap:8px;">
          ${list || '<div class="politeia-empty">No has agregado libros aún.</div>'}
        </div>
      </div>
    `;
    root.querySelector('#politeia-add-book')?.addEventListener('click', () => {
      const title = root.querySelector('#politeia-book-title')?.value?.trim() || '';
      const pages = parseInt(root.querySelector('#politeia-book-pages')?.value, 10) || 0;
      if (!title || !pages) return;
      state.books = [...state.books, { id: Date.now(), title, pages }];
      renderStep3();
      syncNavDisable();
    });
    root.querySelectorAll('[data-remove-book]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = parseInt(btn.getAttribute('data-remove-book'), 10);
        state.books = state.books.filter((b) => b.id !== id);
        renderStep3();
        syncNavDisable();
      });
    });
  }

  function renderStep4() {
    const root = bodyByStep(4);
    if (!root) return;
    const days = WEEK_DAYS.map((d) => {
      const active = state.availability.days.includes(d.id) ? 'is-active' : '';
      return `<button type="button" class="politeia-pill ${active}" data-day="${d.id}">${d.label}</button>`;
    }).join('');
    const sessions = ['short', 'medium', 'long'].map((s) => {
      const active = state.availability.sessionType === s ? 'is-active' : '';
      const label = s === 'short' ? 'Sprints' : s === 'medium' ? 'Equilibrado' : 'Inmersión';
      return `<button type="button" class="politeia-pill ${active}" data-session="${s}">${label}</button>`;
    }).join('');
    root.innerHTML = `
      <div class="politeia-list" style="gap:12px;">
        <div>
          <div class="politeia-card-title">Días disponibles</div>
          <div class="politeia-inline-list">${days}</div>
        </div>
        <div>
          <div class="politeia-card-title">Ritmo de sesión</div>
          <div class="politeia-inline-list">${sessions}</div>
        </div>
      </div>
    `;
    root.querySelectorAll('[data-day]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = parseInt(btn.getAttribute('data-day'), 10);
        const exists = state.availability.days.includes(id);
        state.availability.days = exists ? state.availability.days.filter((d) => d !== id) : [...state.availability.days, id];
        renderStep4();
        syncNavDisable();
      });
    });
    root.querySelectorAll('[data-session]').forEach((btn) => {
      btn.addEventListener('click', () => {
        state.availability.sessionType = btn.getAttribute('data-session');
        renderStep4();
        syncNavDisable();
      });
    });
  }

  function renderStep5() {
    const root = bodyByStep(5);
    if (!root) return;
    const buttons = PERIODS.map((p) => {
      const active = state.period === p.id ? 'is-active' : '';
      return `<button type="button" class="politeia-card ${active}" data-period="${p.id}">
        <div class="politeia-card-title">${p.label}</div>
      </button>`;
    }).join('');
    const custom = state.period === 'custom' ? `
      <div class="politeia-input-row" style="margin-top:12px;">
        <input type="number" class="politeia-field" id="politeia-custom-weeks" placeholder="Número de semanas" value="${state.customWeeks || ''}" />
      </div>
    ` : '';
    root.innerHTML = `
      <div class="politeia-grid">${buttons}</div>
      ${custom}
    `;
    root.querySelectorAll('[data-period]').forEach((btn) => {
      btn.addEventListener('click', () => {
        state.period = btn.getAttribute('data-period');
        if (state.period !== 'custom') state.customWeeks = null;
        renderStep5();
        syncNavDisable();
      });
    });
    const input = root.querySelector('#politeia-custom-weeks');
    if (input) {
      input.addEventListener('input', (e) => {
        state.customWeeks = e.target.value;
        syncNavDisable();
      });
    }
  }

  function renderCurrentStep() {
    if (state.step === 1) renderStep1();
    if (state.step === 2) renderStep2();
    if (state.step === 3) renderStep3();
    if (state.step === 4) renderStep4();
    if (state.step === 5) renderStep5();
  }

  openBtn.addEventListener('click', openModal);
  overlay.querySelector('.politeia-modal-close')?.addEventListener('click', closeModal);

  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeModal();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !overlay.hidden) {
      closeModal();
    }
  });

  overlay.querySelectorAll('.politeia-next').forEach((btn) => {
    btn.addEventListener('click', nextStep);
  });

  overlay.querySelectorAll('.politeia-prev').forEach((btn) => {
    btn.addEventListener('click', prevStep);
  });

  overlay.querySelector('.politeia-submit')?.addEventListener('click', submitPlan);

  // Initial render
  renderCurrentStep();
  syncNavDisable();

  /**
   * Build REST payload from form state (no side effects).
   * @param {object} formData
   * @returns {{plan: object, goals: Array, planned_sessions: Array, baselines: object}}
   */
  function buildReadingPlanPayload(formData) {
    const plan = {
      name: 'Reading Plan',
      plan_type: 'custom',
      period: formData.period || 'custom',
    };

    // Helpers to derive numeric targets
    const mapPagesRange = (val) => {
      if (!val) return 0;
      if (val === '<10') return 5;
      if (val === '10–20') return 15;
      if (val === '20–40') return 30;
      if (val === '40+') return 40;
      const n = parseInt(val, 10);
      return Number.isFinite(n) ? n : 0;
    };

    const goals = (formData.goals || []).map((goalId) => {
      const baseline = formData.baselines?.[goalId] || {};
      let metric = '';
      let target = 0;

      switch (goalId) {
        case 'complete_books':
          metric = 'books_per_year';
          if (baseline.value === '4+') target = 4;
          else target = parseInt(baseline.value, 10) || 0;
          break;
        case 'more_days':
          metric = 'days_per_week';
          target = Array.isArray(formData.availability?.days) ? formData.availability.days.length : 0;
          break;
        case 'more_pages':
          metric = 'pages_per_session';
          target = mapPagesRange(baseline.value);
          break;
        case 'consistency':
          metric = 'weeks_active_month';
          target = parseInt(baseline.value, 10) || 0;
          break;
        default:
          metric = 'custom_metric';
          target = 0;
      }

      return {
        goal_kind: goalId,
        metric,
        target_value: target,
        period: plan.period,
        book_id: null,
        subject_id: null,
      };
    });

    // Flatten baselines to simple key/value strings
    const baselines = {};
    Object.entries(formData.baselines || {}).forEach(([key, val]) => {
      if (!val) return;
      // Flatten each baseline into simple strings
      const parts = [];
      if (val.value !== undefined && val.value !== null) {
        parts.push(String(val.value));
      }
      if (Array.isArray(val.details)) {
        parts.push(val.details.join(','));
      }
      baselines[key] = parts.join('|');
    });

    // Minimal planned sessions generation based on availability + period
    const planned_sessions = [];
    if (Array.isArray(formData.availability?.days) && formData.availability.days.length) {
      const weeks = (() => {
        const selected = PERIODS.find((p) => p.id === formData.period);
        if (selected && selected.weeks) return selected.weeks;
        const custom = parseInt(formData.customWeeks, 10);
        if (Number.isFinite(custom) && custom > 0) return custom;
        return 1;
      })();
      const daysPerWeek = Math.min(formData.availability.days.length, 2); // cap to 2 sessions/week
      let sessionCount = weeks * daysPerWeek;
      if (sessionCount < 1) sessionCount = 1;
      const today = new Date();
      for (let i = 0; i < sessionCount; i++) {
        const start = new Date(today);
        start.setDate(today.getDate() + i * 3); // spread sessions ~every 3 days
        const end = new Date(start);
        end.setHours(end.getHours() + 1);
        planned_sessions.push({
          planned_start_datetime: start.toISOString(),
          planned_end_datetime: end.toISOString(),
          status: 'planned',
        });
      }
    }

    return { plan, goals, planned_sessions, baselines };
  }
})();
