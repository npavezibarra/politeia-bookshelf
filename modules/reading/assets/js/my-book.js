/* global PRS_BOOK, PRS_SESS */

/**
 * Utilidades
 */
(function () {
  "use strict";

  // ---------- Helpers ----------
  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  function setText(el, txt) { if (el) el.textContent = txt; }
  function show(el) { if (el) el.style.display = ""; }
  function hide(el) { if (el) el.style.display = "none"; }

  function setStatus(el, msg, ok = true, ttl = 2000) {
    if (!el) return;
    el.textContent = msg || "";
    el.style.color = ok ? "#2f6b2f" : "#b00020";
    if (ttl > 0) {
      setTimeout(() => { el.textContent = ""; }, ttl);
    }
  }

  function ajaxPost(url, data) {
    return fetch(url, {
      method: "POST",
      body: data,
      credentials: "same-origin",
    }).then(r => r.json());
  }

  function num(val, defVal = 0) {
    const n = parseInt(val, 10);
    return Number.isFinite(n) ? n : defVal;
  }

  function setupCoverPlaceholder() {
    const placeholder = qs("#prs-cover-placeholder");
    if (!placeholder) return;

    const hasCover = qs("#prs-book-cover-figure img");
    if (hasCover) return;

    const titleEl = qs(".prs-book-title__text") || qs(".prs-book-title");
    const authorEl = qs(".prs-book-author");

    const localizedTitle = (window.PRS_BOOK && typeof PRS_BOOK.title === "string") ? PRS_BOOK.title : "";
    const localizedAuthor = (window.PRS_BOOK && typeof PRS_BOOK.author === "string") ? PRS_BOOK.author : "";

    const titleText = titleEl && titleEl.textContent ? titleEl.textContent.trim() : localizedTitle.trim();
    const authorText = authorEl && authorEl.textContent ? authorEl.textContent.trim() : localizedAuthor.trim();

    const placeholderTitle = titleText || "Untitled Book";
    const placeholderAuthor = authorText || "Unknown Author";

    placeholder.innerHTML = "";

    const titleNode = document.createElement("h3");
    titleNode.textContent = placeholderTitle;

    const authorNode = document.createElement("p");
    authorNode.textContent = placeholderAuthor;

    placeholder.appendChild(titleNode);
    placeholder.appendChild(authorNode);
  }

  // ---------- Edición: Pages ----------
  function setupPages() {
    const wrap = qs("#fld-pages");
    if (!wrap || !window.PRS_BOOK) return;

    const view = qs("#pages-view", wrap);
    const editBtn = qs("#pages-edit", wrap);
    const form = qs("#pages-form", wrap);
    const input = qs("#pages-input", wrap);
    const saveBtn = qs("#pages-save", wrap);
    const cancelBtn = qs("#pages-cancel", wrap);
    const status = qs("#pages-status", wrap);

    if (editBtn) editBtn.addEventListener("click", (e) => {
      e.preventDefault();
      hide(editBtn);
      show(form);
      input.focus();
      input.select();
    });

    if (cancelBtn) cancelBtn.addEventListener("click", () => {
      show(editBtn);
      hide(form);
      setStatus(status, "", true, 0);
    });

    if (saveBtn) saveBtn.addEventListener("click", () => {
      const pages = Math.max(0, num(input.value, 0));
      const fd = new FormData();
      fd.append("action", "prs_update_user_book_meta");
      fd.append("nonce", PRS_BOOK.nonce);
      fd.append("user_book_id", String(PRS_BOOK.user_book_id));
      fd.append("pages", String(pages));

      ajaxPost(PRS_BOOK.ajax_url, fd)
        .then(json => {
          if (!json || !json.success) throw json;
          setText(view, pages > 0 ? String(pages) : "—");
          setStatus(status, "Saved.", true);
          // cerrar editor
          show(editBtn);
          hide(form);
        })
        .catch(err => {
          const msg = (err && err.data && err.data.message) ? err.data.message : "Error saving.";
          setStatus(status, msg, false, 4000);
        });
    });
  }

  function setupLibraryPagesInlineEdit() {
    const table = qs("#prs-library");
    if (!table) return;

    const nonceField = qs("#prs_update_user_book_nonce");
    const ajaxUrl = (window.PRS_LIBRARY && PRS_LIBRARY.ajax_url) ||
      (window.PRS_BOOK && PRS_BOOK.ajax_url) ||
      (typeof window.ajaxurl === "string" ? window.ajaxurl : "");

    if (!nonceField || !nonceField.value || !ajaxUrl) {
      return;
    }

    const messages = (window.PRS_LIBRARY && PRS_LIBRARY.messages) || {};
    const msgInvalid = messages.invalid || "Please enter a valid number of pages.";
    const msgTooSmall = messages.too_small || "Please enter a number greater than zero.";
    const msgSaveError = messages.error || "There was an error saving the number of pages.";

    function wrapFor(el) {
      return el ? el.closest(".prs-library__pages") : null;
    }

    function clearError(wrap) {
      if (!wrap) return;
      wrap.classList.remove("prs-library__pages--error");
      const err = qs(".prs-library__pages-error", wrap);
      setText(err, "");
    }

    function showError(wrap, msg) {
      if (!wrap) return;
      wrap.classList.add("prs-library__pages--error");
      const err = qs(".prs-library__pages-error", wrap);
      setText(err, msg);
    }

    function openEditor(wrap) {
      if (!wrap) return;
      clearError(wrap);
      wrap.classList.remove("prs-library__pages--saving");
      wrap.classList.add("prs-library__pages--editing");
      const input = qs(".prs-library__pages-input", wrap);
      if (input) {
        input.disabled = false;
        input.value = wrap.dataset.pages || "";
        setTimeout(() => {
          input.focus();
          input.select && input.select();
        }, 0);
      }
    }

    function closeEditor(wrap) {
      if (!wrap) return;
      wrap.classList.remove("prs-library__pages--editing");
    }

    function saveValue(wrap, input) {
      if (!wrap || !input) return;

      clearError(wrap);

      const row = wrap.closest("tr[data-user-book-id]");
      const userBookId = row ? num(row.getAttribute("data-user-book-id"), 0) : 0;
      if (!userBookId) return;

      const raw = (input.value || "").trim();
      if (raw !== "" && !/^[0-9]+$/.test(raw)) {
        showError(wrap, msgInvalid);
        return;
      }

      let pagesValue = "";
      if (raw !== "") {
        pagesValue = parseInt(raw, 10);
        if (!Number.isFinite(pagesValue) || pagesValue < 1) {
          showError(wrap, msgTooSmall);
          return;
        }
      }

      input.disabled = true;
      wrap.classList.add("prs-library__pages--saving");

      const fd = new FormData();
      fd.append("action", "prs_update_user_book_meta");
      fd.append("user_book_id", String(userBookId));
      fd.append("pages", pagesValue === "" ? "" : String(pagesValue));
      fd.append("prs_update_user_book_nonce", nonceField.value);

      ajaxPost(ajaxUrl, fd)
        .then(json => {
          if (!json || !json.success) throw json;

          const newDisplay = pagesValue === "" ? "" : String(pagesValue);
          const valueEl = qs(".prs-library__pages-value", wrap);
          setText(valueEl, newDisplay);
          wrap.dataset.pages = pagesValue === "" ? "" : String(pagesValue);
          input.value = wrap.dataset.pages || "";
          closeEditor(wrap);
        })
        .catch(err => {
          const msg = (err && err.data && err.data.message) ? err.data.message : msgSaveError;
          showError(wrap, msg);
        })
        .then(() => {
          wrap.classList.remove("prs-library__pages--saving");
          input.disabled = false;
        });
    }

    table.addEventListener("click", (event) => {
      const editBtn = event.target.closest(".prs-library__pages-edit");
      if (!editBtn) return;
      const wrap = wrapFor(editBtn);
      if (!wrap) return;
      event.preventDefault();
      openEditor(wrap);
    });

    table.addEventListener("keydown", (event) => {
      const input = event.target.closest(".prs-library__pages-input");
      if (!input) return;

      const wrap = wrapFor(input);
      if (!wrap) return;

      if (event.key === "Enter") {
        event.preventDefault();
        saveValue(wrap, input);
      } else if (event.key === "Escape") {
        event.preventDefault();
        input.value = wrap.dataset.pages || "";
        clearError(wrap);
        closeEditor(wrap);
      }
    });

    table.addEventListener("input", (event) => {
      const input = event.target.closest(".prs-library__pages-input");
      if (!input) return;
      const wrap = wrapFor(input);
      clearError(wrap);
    });
  }

  // ---------- Edición: Purchase Date ----------
  function setupPurchaseDate() {
    const wrap = qs("#fld-purchase-date");
    if (!wrap || !window.PRS_BOOK) return;

    const view = qs("#purchase-date-view", wrap);
    const editBtn = qs("#purchase-date-edit", wrap);
    const form = qs("#purchase-date-form", wrap);
    const input = qs("#purchase-date-input", wrap);
    const saveBtn = qs("#purchase-date-save", wrap);
    const cancelBtn = qs("#purchase-date-cancel", wrap);
    const status = qs("#purchase-date-status", wrap);

    if (editBtn) editBtn.addEventListener("click", (e) => {
      e.preventDefault();
      hide(editBtn);
      show(form);
      input.showPicker && input.showPicker();
    });

    if (cancelBtn) cancelBtn.addEventListener("click", () => {
      show(editBtn);
      hide(form);
      setStatus(status, "", true, 0);
    });

    if (saveBtn) saveBtn.addEventListener("click", () => {
      const dateVal = (input.value || "").trim(); // YYYY-MM-DD or empty
      const fd = new FormData();
      fd.append("action", "prs_update_user_book_meta");
      fd.append("nonce", PRS_BOOK.nonce);
      fd.append("user_book_id", String(PRS_BOOK.user_book_id));
      fd.append("purchase_date", dateVal);

      ajaxPost(PRS_BOOK.ajax_url, fd)
        .then(json => {
          if (!json || !json.success) throw json;
          setText(view, dateVal ? dateVal : "—");
          setStatus(status, "Saved.", true);
          show(editBtn);
          hide(form);
        })
        .catch(err => {
          const msg = (err && err.data && err.data.message) ? err.data.message : "Error saving date.";
          setStatus(status, msg, false, 4000);
        });
    });
  }

  // ---------- Edición: Purchase Channel + Place ----------
  function setupPurchaseChannel() {
    const wrap = qs("#fld-purchase-channel");
    if (!wrap || !window.PRS_BOOK) return;

    const view = qs("#purchase-channel-view", wrap);
    const editBtn = qs("#purchase-channel-edit", wrap);
    const form = qs("#purchase-channel-form", wrap);
    const select = qs("#purchase-channel-select", wrap);
    const place = qs("#purchase-place-input", wrap);
    const saveBtn = qs("#purchase-channel-save", wrap);
    const cancelBtn = qs("#purchase-channel-cancel", wrap);
    const status = qs("#purchase-channel-status", wrap);

    function adjustPlaceVisibility() {
      if (!place) return;
      const v = (select.value || "").trim();
      place.style.display = v ? "inline-block" : "none";
    }

    if (editBtn) editBtn.addEventListener("click", (e) => {
      e.preventDefault();
      hide(editBtn);
      show(form);
      adjustPlaceVisibility();
      select.focus();
    });

    if (cancelBtn) cancelBtn.addEventListener("click", () => {
      show(editBtn);
      hide(form);
      setStatus(status, "", true, 0);
    });

    if (select) select.addEventListener("change", adjustPlaceVisibility);

    if (saveBtn) saveBtn.addEventListener("click", () => {
      const channel = (select.value || "").trim(); // "online" | "store" | ""
      const placeVal = (place && place.value || "").trim();

      const fd = new FormData();
      fd.append("action", "prs_update_user_book_meta");
      fd.append("nonce", PRS_BOOK.nonce);
      fd.append("user_book_id", String(PRS_BOOK.user_book_id));
      fd.append("purchase_channel", channel);
      fd.append("purchase_place", placeVal);

      ajaxPost(PRS_BOOK.ajax_url, fd)
        .then(json => {
          if (!json || !json.success) throw json;
          let label = "—";
          if (channel) {
            label = channel.charAt(0).toUpperCase() + channel.slice(1);
            if (placeVal) label += " — " + placeVal;
          }
          setText(view, label);
          setStatus(status, "Saved.", true);
          show(editBtn);
          hide(form);
        })
        .catch(err => {
          const msg = (err && err.data && err.data.message) ? err.data.message : "Error saving channel.";
          setStatus(status, msg, false, 4000);
        });
    });
  }

  // ---------- Rating (stars) ----------
  function setupRating() {
    const wrap = qs("#fld-user-rating");
    if (!wrap || !window.PRS_BOOK) return;

    const stars = qsa("#prs-user-rating .prs-star", wrap);
    const status = qs("#rating-status", wrap);

    function paint(upTo) {
      stars.forEach((btn, i) => {
        const on = (i + 1) <= upTo;
        btn.classList.toggle("is-active", on);
        btn.setAttribute("aria-checked", on ? "true" : "false");
      });
    }

    stars.forEach((btn, idx) => {
      btn.addEventListener("click", () => {
        const val = idx + 1;
        const fd = new FormData();
        fd.append("action", "prs_update_user_book_meta");
        fd.append("nonce", PRS_BOOK.nonce);
        fd.append("user_book_id", String(PRS_BOOK.user_book_id));
        fd.append("rating", String(val));

        ajaxPost(PRS_BOOK.ajax_url, fd)
          .then(json => {
            if (!json || !json.success) throw json;
            paint(val);
            setStatus(status, "Saved.", true);
          })
          .catch(err => {
            const msg = (err && err.data && err.data.message) ? err.data.message : "Error saving rating.";
            setStatus(status, msg, false, 4000);
          });
      });
    });
  }

  // ---------- Type of book ----------
  function setupTypeBook() {
    const wrap = qs("#fld-user-rating");
    if (!wrap || !window.PRS_BOOK) return;

    const select = qs("#prs-type-book", wrap);
    const status = qs("#type-book-status", wrap);

    if (!select) return;

    select.addEventListener("change", () => {
      const val = (select.value || "").trim();
      const fd = new FormData();
      fd.append("action", "prs_update_user_book_meta");
      fd.append("nonce", PRS_BOOK.nonce);
      fd.append("user_book_id", String(PRS_BOOK.user_book_id));
      fd.append("type_book", val);

      ajaxPost(PRS_BOOK.ajax_url, fd)
        .then(json => {
          if (!json || !json.success) throw json;
          setStatus(status, "Saved.", true);
          PRS_BOOK.type_book = val;
          document.dispatchEvent(new CustomEvent("prs:type-book-changed", { detail: { type: val } }));
        })
        .catch(err => {
          const msg = (err && err.data && err.data.message) ? err.data.message : "Error saving format.";
          setStatus(status, msg, false, 4000);
        });
    });
  }

  // ---------- Reading Status ----------
  function setupReadingStatus() {
    const wrap = qs("#fld-reading-status");
    if (!wrap || !window.PRS_BOOK) return;

    const select = qs("#reading-status-select", wrap);
    const status = qs("#reading-status-status", wrap);

    if (!select) return;

    select.addEventListener("change", () => {
      const val = (select.value || "not_started").trim();
      const fd = new FormData();
      fd.append("action", "prs_update_user_book_meta");
      fd.append("nonce", PRS_BOOK.nonce);
      fd.append("user_book_id", String(PRS_BOOK.user_book_id));
      fd.append("reading_status", val);

      ajaxPost(PRS_BOOK.ajax_url, fd)
        .then(json => {
          if (!json || !json.success) throw json;
          setStatus(status, "Saved.", true);
        })
        .catch(err => {
          const msg = (err && err.data && err.data.message) ? err.data.message : "Error updating status.";
          setStatus(status, msg, false, 4000);
        });
    });
  }

  // ---------- Owning Status + Return to shelf + Contact ----------
  function setupOwningStatus() {
    const wrap = qs("#fld-owning-status");
    if (!wrap || !window.PRS_BOOK) return;

    const select = qs("#owning-status-select", wrap);
    const status = qs("#owning-status-status", wrap);
    const returnBtn = qs("#owning-return-shelf", wrap);
    const derivedText = qs("#derived-location-text", wrap);
    const contactForm = qs("#owning-contact-form", wrap);
    const contactName = qs("#owning-contact-name", wrap);
    const contactEmail = qs("#owning-contact-email", wrap);
    const contactSave = qs("#owning-contact-save", wrap);
    const contactStatus = qs("#owning-contact-status", wrap);
    const contactView = qs("#owning-contact-view", wrap);
    const note = qs("#owning-status-note", wrap);

    function isDigitalType() {
      const raw = (window.PRS_BOOK && typeof PRS_BOOK.type_book !== "undefined") ? PRS_BOOK.type_book : "";
      return String(raw || "").trim().toLowerCase() === "d";
    }

    function updateDerived(val) {
      const locked = isDigitalType();
      const inShelf = !val; // NULL/'' => In Shelf
      setText(derivedText, inShelf ? "In Shelf" : "Not In Shelf");
      // botón "Mark as returned" visible solo si borrowed/borrowing
      const showReturn = (!locked) && (val === "borrowed" || val === "borrowing");
      if (returnBtn) {
        returnBtn.style.display = showReturn ? "" : "none";
        returnBtn.disabled = locked;
      }

      // contacto requerido si borrowed/borrowing/sold y faltan datos => mostramos form
      const needsContact = (!locked) && (val === "borrowed" || val === "borrowing" || val === "sold");
      if (contactForm) {
        if (needsContact) {
          show(contactForm);
        } else {
          hide(contactForm);
        }
      }

      if (contactName) contactName.disabled = locked;
      if (contactEmail) contactEmail.disabled = locked;
      if (contactSave) contactSave.disabled = locked;
    }

    function applyTypeLock() {
      const locked = isDigitalType();
      if (select) {
        select.disabled = locked;
        if (locked) {
          select.setAttribute("aria-disabled", "true");
        } else {
          select.removeAttribute("aria-disabled");
        }
        select.classList.toggle("is-disabled", locked);
      }
      if (note) {
        note.style.display = locked ? "" : "none";
      }
    }

    function postOwning(val) {
      if (isDigitalType()) {
        return Promise.resolve();
      }
      const fd = new FormData();
      fd.append("action", "prs_update_user_book_meta");
      fd.append("nonce", PRS_BOOK.nonce);
      fd.append("user_book_id", String(PRS_BOOK.user_book_id));
      fd.append("owning_status", val); // "" => volver a In Shelf

      return ajaxPost(PRS_BOOK.ajax_url, fd)
        .then(json => {
          if (!json || !json.success) throw json;
          setStatus(status, "Saved.", true);
          updateDerived(val);
        })
        .catch(err => {
          const msg = (err && err.data && err.data.message) ? err.data.message : "Error updating owning status.";
          setStatus(status, msg, false, 4000);
        });
    }

    if (select) {
      updateDerived(select.value || "");
      applyTypeLock();
      select.addEventListener("change", () => {
        if (select.disabled) {
          return;
        }
        const val = (select.value || "").trim(); // "", borrowed, borrowing, sold, lost
        postOwning(val);
      });
    }

    if (returnBtn) {
      returnBtn.addEventListener("click", () => {
        if (returnBtn.disabled) {
          return;
        }
        // Volver a In Shelf
        select && (select.value = "");
        postOwning("");
      });
    }

    if (contactSave) {
      contactSave.addEventListener("click", () => {
        if (isDigitalType()) {
          return;
        }
        const name = (contactName && contactName.value || "").trim();
        const email = (contactEmail && contactEmail.value || "").trim();

        const fd = new FormData();
        fd.append("action", "prs_update_user_book_meta");
        fd.append("nonce", PRS_BOOK.nonce);
        fd.append("user_book_id", String(PRS_BOOK.user_book_id));
        fd.append("counterparty_name", name);
        fd.append("counterparty_email", email);

        // No cambiamos owning_status aquí para no alterar el flujo,
        // solo guardamos contacto (la clase actualiza el loan abierto si aplica).
        ajaxPost(PRS_BOOK.ajax_url, fd)
          .then(json => {
            if (!json || !json.success) throw json;
            setStatus(contactStatus, "Saved.", true);
            // Actualiza la vista compacta (no tenemos la fecha del loan, así que solo nombre/email)
            let v = "";
            if (name) v += name;
            if (email) v += (v ? " · " : "") + email;
            if (contactView) contactView.textContent = v;
          })
          .catch(err => {
            const msg = (err && err.data && err.data.message) ? err.data.message : "Error saving contact.";
            setStatus(contactStatus, msg, false, 4000);
          });
      });
    }

    document.addEventListener("prs:type-book-changed", () => {
      const val = select ? (select.value || "") : "";
      updateDerived(val);
      applyTypeLock();
    });
  }

  // ---------- Sesiones: render parcial + paginación + SORTING ----------
  function setupSessionsAjax() {
    if (!window.PRS_SESS) return;
    const box = qs("#prs-sessions-table");
    if (!box) return;

    // --- NEW: Keep track of sorting state ---
    let currentOrderby = 'start_time';
    let currentOrder = 'desc';

    function loadSessions(page, orderby, order) {
      const p = num(page, 1);
      // Use state variables if new values are not provided
      const ob = orderby || currentOrderby;
      const o = order || currentOrder;

      const fd = new FormData();
      fd.append("action", "prs_render_sessions");
      fd.append("nonce", PRS_SESS.nonce);
      fd.append("book_id", String(PRS_SESS.book_id));
      fd.append("paged", String(p));
      // --- NEW: Send sorting data with the request ---
      fd.append("orderby", ob);
      fd.append("order", o);

      box.innerHTML = "<p>Loading…</p>";

      ajaxPost(PRS_SESS.ajax_url, fd)
        .then(json => {
          if (!json || !json.success) throw json;
          box.innerHTML = json.data && json.data.html ? json.data.html : "";

          // Update the URL (without reloading) to reflect the current page
          try {
            const url = new URL(window.location.href);
            url.searchParams.set(PRS_SESS.param, String(json.data.paged || 1));
            window.history.replaceState({}, "", url.toString());
          } catch (e) { /* noop */ }
        })
        .catch(() => {
          box.innerHTML = "<p>Error loading sessions.</p>";
        });
    }

    // Initial render using the page number from the URL if present
    const initialPage = num(box.getAttribute("data-initial-paged"), 1);
    loadSessions(initialPage);

    // --- NEW: A single event listener for both pagination and sorting ---
    box.addEventListener("click", function (e) {
      // Handle pagination clicks
      const pageLink = e.target.closest("a.prs-sess-link");
      if (pageLink) {
        e.preventDefault();
        const page = num(pageLink.getAttribute("data-page"), 1);
        loadSessions(page);
        return; // Stop further processing
      }
      
      // Handle sorting clicks
      const sortHeader = e.target.closest("th.prs-sortable");
      if(sortHeader) {
        e.preventDefault();
        const newOrderby = sortHeader.getAttribute('data-sort');
        
        if (newOrderby === currentOrderby) {
          // If it's the same column, just flip the direction
          currentOrder = (currentOrder === 'desc') ? 'asc' : 'desc';
        } else {
          // If it's a new column, set it and default to descending
          currentOrderby = newOrderby;
          currentOrder = 'desc';
        }
        
        // Fetch the first page with the new sorting applied
        loadSessions(1, currentOrderby, currentOrder);
      }
    });
  }

  // ---------- Session recorder modal ----------
  function setupSessionRecorderModal() {
    const trigger = qs("#prs-session-recorder-open");
    const modal = qs("#prs-session-modal");
    if (!trigger || !modal) return;

    const closeBtn = qs("#prs-session-recorder-close", modal);

    function handleKeydown(event) {
      if (event.key === "Escape") {
        event.preventDefault();
        close();
      }
    }

    function open() {
      modal.classList.add("is-active");
      trigger.setAttribute("aria-expanded", "true");
      modal.setAttribute("aria-hidden", "false");
      document.addEventListener("keydown", handleKeydown);
      if (closeBtn) {
        setTimeout(() => closeBtn.focus(), 0);
      }
    }

    function close() {
      modal.classList.remove("is-active");
      trigger.setAttribute("aria-expanded", "false");
      modal.setAttribute("aria-hidden", "true");
      document.removeEventListener("keydown", handleKeydown);
      setTimeout(() => trigger.focus(), 0);
    }

    trigger.addEventListener("click", (event) => {
      event.preventDefault();
      open();
    });

    if (closeBtn) {
      closeBtn.addEventListener("click", (event) => {
        event.preventDefault();
        close();
      });
    }

    modal.addEventListener("click", (event) => {
      if (event.target === modal) {
        close();
      }
    });
  }


  // ---------- Library filter dashboard ----------
  function setupLibraryFilterDashboard() {
    const filterBtn = qs(".prs-library__filter-btn");
    const overlay = qs("#prs-filter-overlay");
    const dashboard = qs("#prs-filter-dashboard");
    const form = qs("#prs-filter-form", dashboard);
    const tbody = qs("#prs-library tbody");

    if (!filterBtn || !overlay || !dashboard || !form || !tbody) {
      return;
    }

    const owningSelect = qs("#prs-filter-owning-status", dashboard);
    const readingSelect = qs("#prs-filter-reading-status", dashboard);
    const progressMinInput = qs("#prs-filter-progress-min", dashboard);
    const progressMaxInput = qs("#prs-filter-progress-max", dashboard);
    const orderSelect = qs("#prs-filter-order", dashboard);
    const resetBtn = qs("#prs-filter-reset", dashboard);
    const closeBtn = qs("#prs-filter-close", dashboard);

    const storageKey = "PRS_LIBRARY_FILTERS";
    const defaultState = {
      owning: "",
      reading: "",
      min: 0,
      max: 100,
      order: "title_asc",
    };

    let lastFocused = null;

    function clampProgress(val, fallback) {
      return Math.min(100, Math.max(0, num(val, fallback)));
    }

    function normalizeState(raw) {
      const normalized = Object.assign({}, defaultState);
      if (raw && typeof raw === "object") {
        if (typeof raw.owning === "string") normalized.owning = raw.owning;
        if (typeof raw.reading === "string") normalized.reading = raw.reading;
        if (typeof raw.order === "string") normalized.order = raw.order;
        normalized.min = clampProgress(raw.min, defaultState.min);
        normalized.max = clampProgress(raw.max, defaultState.max);
      }

      if (normalized.min > normalized.max) {
        const swap = normalized.min;
        normalized.min = normalized.max;
        normalized.max = swap;
      }

      return normalized;
    }

    function updateRangeDisplay(input) {
      if (!input) return;
      const span = dashboard.querySelector('[data-display-for="' + input.id + '"]');
      if (span) {
        span.textContent = clampProgress(input.value, input.id === "prs-filter-progress-max" ? 100 : 0) + "%";
      }
    }

    function setSelectValue(select, value) {
      if (!select) return;
      const values = Array.prototype.map.call(select.options, option => option.value);
      select.value = values.indexOf(value) !== -1 ? value : "";
    }

    function applyInputs(state) {
      setSelectValue(owningSelect, state.owning);
      setSelectValue(readingSelect, state.reading);
      setSelectValue(orderSelect, state.order);
      if (progressMinInput) {
        progressMinInput.value = String(state.min);
        updateRangeDisplay(progressMinInput);
      }
      if (progressMaxInput) {
        progressMaxInput.value = String(state.max);
        updateRangeDisplay(progressMaxInput);
      }
    }

    function getStateFromInputs() {
      return {
        owning: owningSelect ? owningSelect.value : "",
        reading: readingSelect ? readingSelect.value : "",
        min: progressMinInput ? num(progressMinInput.value, defaultState.min) : defaultState.min,
        max: progressMaxInput ? num(progressMaxInput.value, defaultState.max) : defaultState.max,
        order: orderSelect ? orderSelect.value : defaultState.order,
      };
    }

    function compareText(a, b, attr, asc) {
      const aVal = (a.getAttribute(attr) || "").toLocaleLowerCase();
      const bVal = (b.getAttribute(attr) || "").toLocaleLowerCase();
      const result = aVal.localeCompare(bVal, undefined, { sensitivity: "base" });
      return asc ? result : -result;
    }

    function compareNumber(a, b, attr, asc) {
      const aVal = clampProgress(a.getAttribute(attr), 0);
      const bVal = clampProgress(b.getAttribute(attr), 0);
      if (aVal === bVal) return 0;
      return asc ? (aVal - bVal) : (bVal - aVal);
    }

    function reorderRows(rows, order) {
      const sorted = rows.slice();
      switch (order) {
        case "title_desc":
          sorted.sort((a, b) => compareText(a, b, "data-title", false));
          break;
        case "author_asc":
          sorted.sort((a, b) => compareText(a, b, "data-author", true));
          break;
        case "author_desc":
          sorted.sort((a, b) => compareText(a, b, "data-author", false));
          break;
        case "progress_asc":
          sorted.sort((a, b) => compareNumber(a, b, "data-progress", true));
          break;
        case "progress_desc":
          sorted.sort((a, b) => compareNumber(a, b, "data-progress", false));
          break;
        case "title_asc":
        default:
          sorted.sort((a, b) => compareText(a, b, "data-title", true));
          break;
      }

      sorted.forEach(row => tbody.appendChild(row));
      return sorted;
    }

    function applyFilters(state) {
      const normalized = normalizeState(state);
      const rows = qsa("tr[data-user-book-id]", tbody);
      if (!rows.length) {
        return normalized;
      }

      const sortedRows = reorderRows(rows, normalized.order);
      const owningValue = (normalized.owning || "").toLocaleLowerCase();
      const readingValue = (normalized.reading || "").toLocaleLowerCase();

      sortedRows.forEach(row => {
        const owning = (row.getAttribute("data-owning-status") || "").toLocaleLowerCase();
        const reading = (row.getAttribute("data-reading-status") || "").toLocaleLowerCase();
        const progress = clampProgress(row.getAttribute("data-progress"), 0);
        const owningMatches = !owningValue || owning === owningValue;
        const readingMatches = !readingValue || reading === readingValue;
        const progressMatches = progress >= normalized.min && progress <= normalized.max;
        row.style.display = (owningMatches && readingMatches && progressMatches) ? "" : "none";
      });

      return normalized;
    }

    function saveState(state) {
      try {
        window.localStorage.setItem(storageKey, JSON.stringify(state));
      } catch (err) {
        // ignore storage errors
      }
    }

    function loadState() {
      try {
        const raw = window.localStorage.getItem(storageKey);
        if (!raw) return null;
        const parsed = JSON.parse(raw);
        return normalizeState(parsed);
      } catch (err) {
        return null;
      }
    }

    function clearState() {
      try {
        window.localStorage.removeItem(storageKey);
      } catch (err) {
        // ignore
      }
    }

    function handleKeydown(event) {
      if (event.key === "Escape") {
        event.preventDefault();
        closeDashboard();
      }
    }

    function openDashboard() {
      lastFocused = document.activeElement;
      overlay.classList.add("is-active");
      overlay.removeAttribute("hidden");
      dashboard.classList.add("is-active");
      dashboard.removeAttribute("hidden");
      dashboard.setAttribute("aria-hidden", "false");
      filterBtn.setAttribute("aria-expanded", "true");
      document.body.classList.add("prs-filter-open");
      document.addEventListener("keydown", handleKeydown);
      const firstFocusable = dashboard.querySelector("select, input, button");
      if (firstFocusable) {
        setTimeout(() => firstFocusable.focus(), 0);
      }
    }

    function closeDashboard() {
      overlay.classList.remove("is-active");
      overlay.setAttribute("hidden", "hidden");
      dashboard.classList.remove("is-active");
      dashboard.setAttribute("hidden", "hidden");
      dashboard.setAttribute("aria-hidden", "true");
      filterBtn.setAttribute("aria-expanded", "false");
      document.body.classList.remove("prs-filter-open");
      document.removeEventListener("keydown", handleKeydown);
      if (lastFocused && typeof lastFocused.focus === "function") {
        setTimeout(() => lastFocused.focus(), 0);
      }
    }

    function applyAndSave(state, shouldClose) {
      const normalized = normalizeState(state);
      applyInputs(normalized);
      applyFilters(normalized);
      saveState(normalized);
      if (shouldClose) {
        closeDashboard();
      }
    }

    filterBtn.addEventListener("click", (event) => {
      event.preventDefault();
      openDashboard();
    });

    if (closeBtn) {
      closeBtn.addEventListener("click", (event) => {
        event.preventDefault();
        closeDashboard();
      });
    }

    overlay.addEventListener("click", () => {
      closeDashboard();
    });

    form.addEventListener("submit", (event) => {
      event.preventDefault();
      applyAndSave(getStateFromInputs(), true);
    });

    if (resetBtn) {
      resetBtn.addEventListener("click", (event) => {
        event.preventDefault();
        clearState();
        applyInputs(defaultState);
        applyFilters(defaultState);
      });
    }

    [progressMinInput, progressMaxInput].forEach((input) => {
      if (!input) return;
      input.addEventListener("input", () => updateRangeDisplay(input));
    });

    const savedState = loadState();
    if (savedState) {
      applyInputs(savedState);
      applyFilters(savedState);
    } else {
      applyInputs(defaultState);
      applyFilters(defaultState);
    }
  }


  // ---------- Boot ----------
  document.addEventListener("DOMContentLoaded", function () {
    setupCoverPlaceholder();
    setupPages();
    setupLibraryPagesInlineEdit();
    setupPurchaseDate();
    setupPurchaseChannel();
    setupRating();
    setupTypeBook();
    setupReadingStatus();
    setupOwningStatus();
    setupSessionsAjax();
    setupSessionRecorderModal();
    setupLibraryFilterDashboard();
  });
})();
