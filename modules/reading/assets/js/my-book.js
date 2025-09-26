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


  // ---------- Boot ----------
  document.addEventListener("DOMContentLoaded", function () {
    setupPages();
    setupLibraryPagesInlineEdit();
    setupPurchaseDate();
    setupPurchaseChannel();
    setupRating();
    setupTypeBook();
    setupReadingStatus();
    setupOwningStatus();
    setupSessionsAjax();
  });
})();