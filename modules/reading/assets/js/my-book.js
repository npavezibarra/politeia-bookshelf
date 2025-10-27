/* global PRS_BOOK, PRS_SESS, jQuery */

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

  function getNormalizedOwningValue(select) {
    if (!select) return "";
    let value = "";
    if (typeof select.value !== "undefined") {
      value = String(select.value || "").trim();
    }
    if (!value) {
      const dataCurrent = select.getAttribute("data-current-value")
        || (select.dataset ? select.dataset.currentValue : "");
      if (dataCurrent) {
        value = String(dataCurrent).trim();
      }
    }
    if (!value) {
      const dataStored = select.getAttribute("data-stored-status")
        || (select.dataset ? select.dataset.storedStatus : "");
      if (dataStored) {
        value = String(dataStored).trim();
      }
    }
    return value || "in_shelf";
  }

  function findRelatedReadingSelect(owningSelect) {
    if (!owningSelect) return null;
    const row = owningSelect.closest && owningSelect.closest("tr");
    if (row) {
      const rowReading = row.querySelector(".reading-status-select");
      if (rowReading) {
        return rowReading;
      }
    }
    return document.getElementById("reading-status-select");
  }

  function toggleReadingStatusLock(owningSelect) {
    if (!owningSelect) return;
    const readingSelect = findRelatedReadingSelect(owningSelect);
    if (!readingSelect) return;

    const owningValue = getNormalizedOwningValue(owningSelect);
    const shouldDisable = owningValue === "borrowing" || owningValue === "borrowed";
    const disabledText = readingSelect.getAttribute("data-disabled-text")
      || "Disabled while this book is being borrowed.";

    if (shouldDisable) {
      if (!readingSelect.disabled) {
        readingSelect.disabled = true;
      }
      readingSelect.classList.add("is-disabled");
      readingSelect.setAttribute("aria-disabled", "true");
      if (disabledText) {
        readingSelect.title = disabledText;
      }
    } else {
      if (readingSelect.disabled) {
        readingSelect.disabled = false;
      }
      readingSelect.classList.remove("is-disabled");
      readingSelect.setAttribute("aria-disabled", "false");
      readingSelect.title = "";
    }
  }

  function toggleReadingStatusLockForAll() {
    qsa(".owning-status-select").forEach(toggleReadingStatusLock);
    const singleSelect = document.getElementById("owning-status-select");
    if (singleSelect) {
      toggleReadingStatusLock(singleSelect);
    }
  }

  function normalizeOwningState(value) {
    const raw = (value || "").trim();
    if (!raw || raw === "in_shelf") {
      return "in_shelf";
    }
    return raw;
  }

  const POLITEIA_TRANSITIONS = {
    in_shelf: ["", "in_shelf", "borrowing", "sold", "lost"],
    borrowing: ["", "in_shelf", "borrowing", "sold", "lost"],
    borrowed: ["", "in_shelf", "borrowed"],
    sold: ["sold"],
    lost: ["", "in_shelf", "lost"],
  };

  function filterOwningOptions(selectEl, currentState) {
    if (!selectEl) return;
    const normalized = normalizeOwningState(currentState);
    const allowed = POLITEIA_TRANSITIONS[normalized] || [];
    const fallback = allowed.length ? allowed : [selectEl.value];

    selectEl.querySelectorAll("option").forEach(opt => {
      const value = (opt.value || "").trim();
      const isAllowed = fallback.includes(value);
      opt.disabled = !isAllowed;
      opt.style.display = isAllowed ? "" : "none";
    });
  }

  function applyOwningOptionFilters() {
    qsa(".owning-status-select").forEach(sel => {
      const current = sel.value
        || sel.getAttribute("data-current-value")
        || sel.getAttribute("data-stored-status")
        || "";
      filterOwningOptions(sel, current);
    });

    const singleSelect = document.getElementById("owning-status-select");
    if (singleSelect) {
      const currentState = getNormalizedOwningValue(singleSelect);
      filterOwningOptions(singleSelect, currentState);
    }
  }

  function setStatus(el, msg, ok = true, ttl = 2000) {
    if (!el) return;
    el.textContent = msg || "";
    el.style.color = ok ? "#2f6b2f" : "#b00020";
    if (ttl > 0) {
      setTimeout(() => { el.textContent = ""; }, ttl);
    }
  }

  function escapeHtml(str) {
    if (typeof str !== "string") return "";
    return str.replace(/[&<>"']/g, ch => {
      switch (ch) {
        case "&": return "&amp;";
        case "<": return "&lt;";
        case ">": return "&gt;";
        case '"': return "&quot;";
        case "'": return "&#39;";
        default: return ch;
      }
    });
  }

  function formatAuthorName(raw) {
    if (typeof raw !== "string") return "";

    const normalized = raw.replace(/\s+/g, " ").trim();
    if (!normalized) {
      return "";
    }

    if (/et al\.?$/i.test(normalized)) {
      return normalized;
    }

    const altDelimiters = [
      /\s+and\s+/i,
      /\s*&\s*/,
      /\s*\/\s*/,
      /\s*·\s*/,
      /\s*•\s*/,
      /\s*;\s*/,
    ];

    for (const delim of altDelimiters) {
      if (delim.test(normalized)) {
        const parts = normalized.split(delim).map(part => part.trim()).filter(Boolean);
        if (parts.length > 1) {
          return `${parts[0]} et al`;
        }
      }
    }

    if (normalized.includes(",")) {
      const parts = normalized.split(",").map(part => part.trim()).filter(Boolean);
      if (parts.length > 1) {
        const suffixPattern = /^(?:Jr|Sr|II|III|IV|V|VI|VII|VIII|IX|X)\.?$/i;
        let [firstPart, ...rest] = parts;
        let firstAuthor = firstPart;

        if (firstAuthor && !/\s/.test(firstAuthor) && rest.length) {
          const potentialGiven = rest[0];
          if (potentialGiven && /^(?:[A-Za-z\u00C0-\u017F]+(?:[\s-][A-Za-z\u00C0-\u017F.]+)*)$/.test(potentialGiven) && !suffixPattern.test(potentialGiven)) {
            firstAuthor = `${firstAuthor}, ${potentialGiven}`;
            rest = rest.slice(1);
          }
        }

        if (rest.length && suffixPattern.test(rest[0])) {
          firstAuthor = `${firstAuthor}, ${rest[0]}`;
          rest = rest.slice(1);
        }

        if (rest.length > 0) {
          return `${firstAuthor} et al`;
        }

        return firstAuthor;
      }
    }

    return normalized;
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
    const titlePlaceholder = qs("#prs-book-title-placeholder");
    const authorPlaceholder = qs("#prs-book-author-placeholder");
    if (!titlePlaceholder || !authorPlaceholder) return;

    const hasCover = qs("#prs-book-cover-figure img");
    if (hasCover) return;

    const domTitle = qs(".prs-book-title__text") || qs(".prs-book-title");
    const domAuthor = qs(".prs-book-author");

    const localizedTitle = (window.PRS_BOOK && typeof PRS_BOOK.title === "string") ? PRS_BOOK.title.trim() : "";
    const localizedAuthor = (window.PRS_BOOK && typeof PRS_BOOK.author === "string") ? PRS_BOOK.author.trim() : "";

    const sourceTitle = domTitle && domTitle.textContent ? domTitle.textContent.trim() : localizedTitle;
    const sourceAuthor = domAuthor && domAuthor.textContent ? domAuthor.textContent.trim() : localizedAuthor;

    if (sourceTitle) {
      titlePlaceholder.textContent = sourceTitle;
    }

    if (sourceAuthor) {
      const formattedAuthor = formatAuthorName(sourceAuthor);
      if (formattedAuthor) {
        authorPlaceholder.textContent = formattedAuthor;
      }
    }
  }

  // ---------- Edición: Pages ----------
  function setupPages() {
    const wrap = qs("#fld-pages");
    if (!wrap) return;

    const view = qs("#pages-view", wrap);
    const editBtn = qs("#pages-edit", wrap);
    const input = qs("#pages-input", wrap);
    const hint = qs("#pages-hint", wrap);

    if (!view || !editBtn || !input) {
      return;
    }

    const ajaxUrl = (typeof window.ajaxurl === "string" && window.ajaxurl)
      || (window.PRS_BOOK && PRS_BOOK.ajax_url)
      || "";
    const nonce = (window.PRS_BOOK && PRS_BOOK.nonce) || "";
    const bookId = (window.PRS_BOOK && PRS_BOOK.user_book_id) ? parseInt(PRS_BOOK.user_book_id, 10) : 0;
    const defaultHint = hint ? hint.textContent.trim() : "";

    function normalizeValue(raw) {
      const trimmed = (raw || "").trim();
      return trimmed === "—" ? "" : trimmed;
    }

    function displayValue(val) {
      return val ? String(val) : "—";
    }

    function openEditor() {
      view.style.display = "none";
      editBtn.style.display = "none";
      input.style.display = "inline-block";
      input.value = originalValue || "";
      if (hint) {
        hint.style.display = "none";
        hint.textContent = defaultHint;
      }
      setTimeout(() => {
        input.focus();
        input.select && input.select();
      }, 0);
    }

    function closeEditor() {
      view.style.display = "";
      editBtn.style.display = "";
      input.style.display = "none";
      if (hint) {
        hint.style.display = "none";
        hint.textContent = defaultHint;
      }
    }

    function setHint(message, autoHideDelay) {
      if (!hint) return;
      hint.textContent = message;
      hint.style.display = "block";
      if (autoHideDelay) {
        setTimeout(() => {
          hint.style.display = "none";
          hint.textContent = defaultHint;
        }, autoHideDelay);
      }
    }

    function toggleHintForChange() {
      if (!hint) return;
      const current = normalizeValue(input.value);
      if (current !== originalValue) {
        hint.textContent = defaultHint || "Press Enter to save";
        hint.style.display = "block";
      } else {
        hint.style.display = "none";
        hint.textContent = defaultHint;
      }
    }

    function handleError(msg) {
      setHint(msg || "Error saving pages.");
    }

    function saveValue(newValue) {
      if (!ajaxUrl || !bookId) {
        handleError("Error saving pages.");
        return;
      }

      const numeric = parseInt(newValue, 10);
      if (!Number.isFinite(numeric) || numeric < 1) {
        handleError("Please enter a number greater than zero.");
        return;
      }

      const stringValue = String(numeric);
      const payload = {
        action: "prs_update_pages",
        book_id: String(bookId),
        pages: stringValue,
      };
      if (nonce) {
        payload.nonce = nonce;
      }

      setHint("Saving...");
      input.disabled = true;

      const jq = window.jQuery;
      const onDone = (json) => {
        const resp = (json && typeof json === "object" && Object.prototype.hasOwnProperty.call(json, "success")) ? json : null;

        if (!resp || !resp.success) {
          const message = resp && resp.data && resp.data.message ? resp.data.message : "Error saving pages.";
          handleError(message);
          input.disabled = false;
          return;
        }

        const savedValue = resp && resp.data && resp.data.pages ? String(resp.data.pages) : stringValue;
        originalValue = savedValue;
        view.textContent = displayValue(originalValue);
        closeEditor();
        setHint("Saved!", 1200);
        input.disabled = false;
      };

      const onFail = () => {
        handleError("Error saving pages.");
        input.disabled = false;
      };

      if (jq && typeof jq.post === "function") {
        jq.post(ajaxUrl, payload)
          .done(onDone)
          .fail(onFail);
      } else {
        const fd = new FormData();
        Object.keys(payload).forEach(key => fd.append(key, payload[key]));
        ajaxPost(ajaxUrl, fd)
          .then(onDone)
          .catch(onFail);
      }
    }

    let originalValue = normalizeValue(view.textContent);
    if (!input.value) {
      input.value = originalValue || "";
    }

    editBtn.addEventListener("click", (e) => {
      e.preventDefault();
      openEditor();
    });

    input.addEventListener("input", () => {
      toggleHintForChange();
    });

    input.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        const candidate = normalizeValue(input.value);
        if (!candidate || candidate === originalValue) {
          return;
        }
        e.preventDefault();
        saveValue(candidate);
      } else if (e.key === "Escape") {
        e.preventDefault();
        input.value = originalValue || "";
        closeEditor();
      }
    });

    input.addEventListener("blur", () => {
      if (normalizeValue(input.value) === originalValue) {
        closeEditor();
      }
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
    const note = qs("#owning-status-note", wrap);
    const overlay = qs("#owning-overlay");
    const overlayTitle = qs("#owning-overlay-title");
    const nameInput = qs("#owning-overlay-name");
    const emailInput = qs("#owning-overlay-email");
    const confirmBtn = qs("#owning-overlay-confirm");
    const cancelBtn = qs("#owning-overlay-cancel");
    const overlayStatus = qs("#owning-overlay-status");
    const returnOverlay = qs("#return-overlay");
    const returnOverlayYes = qs("#return-overlay-yes");
    const returnOverlayNo = qs("#return-overlay-no");

    const ajaxUrl = (typeof window.ajaxurl === "string" && window.ajaxurl)
      || (window.PRS_BOOK && PRS_BOOK.ajax_url)
      || "";

    const savedNameAttr = wrap.getAttribute("data-contact-name") || "";
    const labelBorrowing = wrap.getAttribute("data-label-borrowing") || "Borrowing to:";
    const labelBorrowed = wrap.getAttribute("data-label-borrowed") || "Borrowed from:";
    const labelSold = wrap.getAttribute("data-label-sold") || "Sold to:";
    const labelLost = wrap.getAttribute("data-label-lost") || "Last borrowed to:";
    const labelUnknown = wrap.getAttribute("data-label-unknown") || "Unknown";
    const contactStatuses = ["borrowed", "borrowing", "sold"];

    let savedOwningStatus = select ? (select.value || "").trim() : "";
    let pendingStatus = "";
    let lastContactName = savedNameAttr;
    let loanDate = wrap.getAttribute("data-active-start") || "";

    const bookId = (typeof window.PRS_BOOK_ID === "number" && window.PRS_BOOK_ID)
      || (window.PRS_BOOK && parseInt(PRS_BOOK.book_id, 10))
      || 0;
    const userBookId = (typeof window.PRS_USER_BOOK_ID === "number" && window.PRS_USER_BOOK_ID)
      || (window.PRS_BOOK && parseInt(PRS_BOOK.user_book_id, 10))
      || 0;
    const owningNonce = (typeof window.PRS_NONCE === "string" && window.PRS_NONCE)
      || (window.PRS_BOOK && PRS_BOOK.owning_nonce)
      || "";

    function getStatusLabel(statusValue) {
      switch (statusValue) {
        case "borrowing":
          return labelBorrowing;
        case "borrowed":
          return labelBorrowed;
        case "sold":
          return labelSold;
        case "lost":
          return labelLost;
        default:
          return "";
      }
    }

    function computeStatusDescription(statusValue, contactName, options = {}) {
      const normalizedName = (contactName || "").trim() || labelUnknown;
      const label = getStatusLabel(statusValue);
      if (!label) {
        return { text: "" };
      }

      const allowRich = !!options.rich && contactStatuses.indexOf(statusValue) !== -1;
      const date = (options.date || "").trim();

      if (allowRich && date) {
        const safeLabel = escapeHtml(label);
        const safeName = escapeHtml(normalizedName);
        const safeDate = escapeHtml(date);
        return {
          html: `<strong>${safeLabel}</strong><br>${safeName}${safeDate ? `<br><small>${safeDate}</small>` : ""}`,
          text: `${label} ${normalizedName}`.trim(),
        };
      }

      return {
        text: `${label} ${normalizedName}`.trim(),
      };
    }

    function applyStatusDescription(statusValue, contactName, options = {}) {
      if (!status) return;
      const description = computeStatusDescription(statusValue, contactName, options);
      if (description.html) {
        status.innerHTML = description.html;
      } else {
        status.textContent = description.text || "";
      }
      if (!options.keepColor) {
        status.style.color = "";
      }
    }

    function setLoanDate(value) {
      loanDate = (value || "").trim();
      wrap.setAttribute("data-active-start", loanDate);
    }

    function openOverlayFor(statusValue, previousStateOverride) {
      if (!overlay) return;
      pendingStatus = statusValue;
      const priorState = normalizeOwningState(
        typeof previousStateOverride === "string"
          ? previousStateOverride
          : savedOwningStatus
      );
      if (overlayStatus) {
        overlayStatus.textContent = "";
        overlayStatus.style.color = "";
      }
      if (overlayTitle) {
        switch (statusValue) {
          case "borrowing":
            overlayTitle.textContent = labelBorrowing;
            break;
          case "borrowed":
            overlayTitle.textContent = labelBorrowed;
            break;
          case "sold":
            overlayTitle.textContent = labelSold;
            break;
          default:
            overlayTitle.textContent = labelBorrowing;
        }
      }
      if (statusValue === "sold" && priorState === "borrowing") {
        if (overlayTitle) {
          overlayTitle.textContent = "Borrowed person is buying this book:";
        }
        if (overlayStatus) {
          overlayStatus.textContent = "Confirm that the borrower is purchasing or compensating for the book.";
        }
      }
      if (nameInput) nameInput.value = "";
      if (emailInput) emailInput.value = "";
      overlay.style.display = "flex";
      setTimeout(() => {
        if (nameInput) {
          nameInput.focus();
        }
      }, 0);
    }

    function closeOverlay() {
      if (!overlay) return;
      overlay.style.display = "none";
    }

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
      if (overlay) {
        overlay.setAttribute("aria-hidden", locked ? "true" : "false");
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
          savedOwningStatus = val;
          toggleReadingStatusLock(select);
          filterOwningOptions(select, val);
          if (!val) {
            lastContactName = "";
            wrap.setAttribute("data-contact-name", "");
            wrap.setAttribute("data-contact-email", "");
            setLoanDate("");
            applyStatusDescription("", "");
          }
        })
        .catch(err => {
          const msg = (err && err.data && err.data.message) ? err.data.message : "Error updating owning status.";
          setStatus(status, msg, false, 4000);
          if (select) {
            select.value = savedOwningStatus;
          }
          updateDerived(savedOwningStatus);
          toggleReadingStatusLock(select);
          filterOwningOptions(select, savedOwningStatus);
        });
    }

    function saveOwningContact(statusValue, name, email, options) {
      const useOverlay = !options || options.fromOverlay !== false ? true : false;

      if (!ajaxUrl || !bookId || !userBookId) {
        console.warn("Missing owning overlay configuration.");
        return Promise.reject(new Error("configuration"));
      }

      const trimmedName = (name || "").trim();
      const trimmedEmail = (email || "").trim();
      const previousState = normalizeOwningState(
        options && typeof options.previousValue === "string"
          ? options.previousValue
          : savedOwningStatus
      );
      const nextState = normalizeOwningState(statusValue);
      const transactionType = previousState === "borrowing" && nextState === "sold"
        ? "bought_by_borrower"
        : "";

      if (useOverlay && overlayStatus) {
        overlayStatus.style.color = "";
        overlayStatus.textContent = "Saving...";
      } else if (!useOverlay && status) {
        status.style.color = "";
        status.textContent = "Saving...";
      }

      const rowEl = select.closest("tr");

      const body = new URLSearchParams({
        action: "save_owning_contact",
        book_id: String(bookId),
        user_book_id: String(userBookId),
        owning_status: statusValue,
        contact_name: trimmedName,
        contact_email: trimmedEmail,
        transaction_type: transactionType,
        nonce: owningNonce,
      });

      return fetch(ajaxUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        credentials: "same-origin",
        body,
      })
        .then(r => r.json())
        .then(res => {
          if (!res || !res.success) {
            throw res;
          }

          const payload = res.data || {};
          const nextName = typeof payload.counterparty_name === "string" ? payload.counterparty_name : trimmedName;
          const nextEmail = typeof payload.counterparty_email === "string" ? payload.counterparty_email : trimmedEmail;

          lastContactName = nextName || "";
          savedOwningStatus = statusValue;

          wrap.setAttribute("data-contact-name", lastContactName);
          wrap.setAttribute("data-contact-email", nextEmail || "");

          if (contactStatuses.indexOf(statusValue) !== -1) {
            const today = new Date().toISOString().split("T")[0];
            setLoanDate(today);
          } else {
            setLoanDate("");
          }

          updateDerived(statusValue);
          applyStatusDescription(statusValue, lastContactName, {
            rich: contactStatuses.indexOf(statusValue) !== -1 && !!loanDate,
            date: loanDate,
          });

          if (useOverlay && overlayStatus) {
            overlayStatus.style.color = "green";
            overlayStatus.textContent = (payload && payload.message) || "Saved successfully.";
            setTimeout(() => {
              overlayStatus.textContent = "";
            }, 2000);
            closeOverlay();
          } else if (!useOverlay && status) {
            status.style.color = "";
          }

          if (select) {
            select.value = statusValue;
          }
          toggleReadingStatusLock(select);
          filterOwningOptions(select, statusValue);

          return res;
        })
        .catch(err => {
          const msg = (err && err.data && err.data.message) ? err.data.message : "Error saving contact.";
          if (useOverlay && overlayStatus) {
            overlayStatus.style.color = "#b00020";
            overlayStatus.textContent = msg;
          } else if (status) {
            status.style.color = "#b00020";
            status.textContent = msg;
          }
          if (select) {
            select.value = savedOwningStatus;
          }
          updateDerived(savedOwningStatus);
          toggleReadingStatusLock(select);
          filterOwningOptions(select, savedOwningStatus);
          throw err;
        });
    }

    function markAsReturned() {
      if (!ajaxUrl || !bookId || !userBookId) {
        console.warn("Missing owning overlay configuration.");
        return Promise.reject(new Error("configuration"));
      }

      const body = new URLSearchParams({
        action: "mark_as_returned",
        book_id: String(bookId),
        user_book_id: String(userBookId),
        nonce: owningNonce,
      });

      if (status) {
        status.style.color = "";
        status.textContent = "Saving...";
      }
      if (returnBtn) {
        returnBtn.disabled = true;
      }

      return fetch(ajaxUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        credentials: "same-origin",
        body,
      })
        .then(r => r.json())
        .then(res => {
          if (!res || !res.success) {
            throw res;
          }

          savedOwningStatus = "";
          pendingStatus = "";
          lastContactName = "";
          wrap.setAttribute("data-contact-name", "");
          wrap.setAttribute("data-contact-email", "");
          setLoanDate("");
          updateDerived("");

          if (select) {
            select.value = "";
          }
          toggleReadingStatusLock(select);
          filterOwningOptions(select, "");
          if (returnBtn) {
            returnBtn.style.display = "none";
            returnBtn.disabled = false;
          }

          if (status) {
            const message = (res.data && res.data.message) ? res.data.message : "Book marked as returned.";
            status.style.color = "#2f6b2f";
            status.textContent = message;
          }

          return res;
        })
        .catch(err => {
          if (status) {
            const msg = (err && err.data && err.data.message) ? err.data.message : "Error updating.";
            status.style.color = "#b00020";
            status.textContent = msg;
          }
          if (returnBtn) {
            returnBtn.disabled = false;
          }
          filterOwningOptions(select, savedOwningStatus);
          throw err;
        });
    }

    if (select) {
      updateDerived(select.value || "");
      applyTypeLock();
      applyStatusDescription(savedOwningStatus, lastContactName, {
        rich: contactStatuses.indexOf(savedOwningStatus) !== -1 && !!loanDate,
        date: loanDate,
      });
      toggleReadingStatusLock(select);
      filterOwningOptions(select, savedOwningStatus);
      select.addEventListener("change", () => {
        if (select.disabled) {
          toggleReadingStatusLock(select);
          return;
        }
        const val = (select.value || "").trim(); // "", borrowed, borrowing, sold, lost
        const previousState = normalizeOwningState(savedOwningStatus);
        toggleReadingStatusLock(select);
        if (!val) {
          postOwning("").finally(() => {
            filterOwningOptions(select, "");
          });
          return;
        }

        if (val === "lost") {
          const fallbackName = lastContactName || labelUnknown;
          saveOwningContact("lost", fallbackName, "", { fromOverlay: false, previousValue: savedOwningStatus })
            .catch(() => {})
            .finally(() => {
              filterOwningOptions(select, savedOwningStatus);
            });
          return;
        }

        if (val === "borrowed" || val === "borrowing" || val === "sold") {
          openOverlayFor(val, previousState);
          return;
        }

        // Default: revert to saved value
        select.value = savedOwningStatus;
        toggleReadingStatusLock(select);
        filterOwningOptions(select, savedOwningStatus);
      });
    }

    function openReturnOverlay() {
      if (returnOverlay) {
        returnOverlay.style.display = "flex";
      } else {
        handleReturnConfirmation();
      }
    }

    function closeReturnOverlay() {
      if (returnOverlay) {
        returnOverlay.style.display = "none";
      }
    }

    function handleReturnConfirmation() {
      closeReturnOverlay();
      markAsReturned();
    }

    if (returnBtn) {
      returnBtn.addEventListener("click", () => {
        if (returnBtn.disabled) {
          return;
        }
        openReturnOverlay();
      });
    }

    if (returnOverlayNo) {
      returnOverlayNo.addEventListener("click", () => {
        closeReturnOverlay();
      });
    }

    if (returnOverlayYes) {
      returnOverlayYes.addEventListener("click", () => {
        handleReturnConfirmation();
      });
    }

    if (confirmBtn) {
      confirmBtn.addEventListener("click", () => {
        if (!pendingStatus) {
          closeOverlay();
          return;
        }

        const name = (nameInput && nameInput.value || "").trim();
        const email = (emailInput && emailInput.value || "").trim();

        if (!name || !email) {
          if (overlayStatus) {
            overlayStatus.style.color = "#b00020";
            overlayStatus.textContent = "Please enter both name and email.";
          }
          return;
        }

        saveOwningContact(pendingStatus, name, email, { previousValue: savedOwningStatus })
          .then(() => {
            pendingStatus = "";
          })
          .catch(() => {});
      });
    }

    if (cancelBtn) {
      cancelBtn.addEventListener("click", () => {
        closeOverlay();
        pendingStatus = "";
        if (select) {
          select.value = savedOwningStatus;
          toggleReadingStatusLock(select);
          filterOwningOptions(select, savedOwningStatus);
        }
      });
    }

    document.addEventListener("prs:type-book-changed", () => {
      const val = select ? (select.value || "") : "";
      updateDerived(val);
      applyTypeLock();
      applyStatusDescription(savedOwningStatus, lastContactName, {
        rich: contactStatuses.indexOf(savedOwningStatus) !== -1 && !!loanDate,
        date: loanDate,
      });
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


  // ---------- Library owning overlay ----------
  function setupLibraryOwningOverlay() {
    const selects = qsa(".owning-status-select");
    if (!selects.length) return;

    const overlay = qs("#owning-overlay");
    if (!overlay) return;

    const overlayTitle = qs("#owning-overlay-title");
    const nameInput = qs("#owning-overlay-name");
    const emailInput = qs("#owning-overlay-email");
    const confirmBtn = qs("#owning-overlay-confirm");
    const cancelBtn = qs("#owning-overlay-cancel");
    const statusMsg = qs("#owning-overlay-status");

    const owningConfig = (window.PRS_LIBRARY && PRS_LIBRARY.owning) || {};
    const owningLabels = owningConfig.labels || {};
    const owningMessages = owningConfig.messages || {};
    const nonce = owningConfig.nonce || (typeof window.PRS_NONCE === "string" ? window.PRS_NONCE : "");
    const ajaxUrl = (typeof window.ajaxurl === "string" && window.ajaxurl)
      || (window.PRS_LIBRARY && PRS_LIBRARY.ajax_url)
      || "";

    const msgMissingContact = owningMessages.missing || "Please enter both name and email.";
    const msgSaving = owningMessages.saving || "Saving...";
    const msgError = owningMessages.error || "Error saving contact.";
    const msgAlert = owningMessages.alert || msgError;

    const labelBorrowing = owningLabels.borrowing || "Borrowing to:";
    const labelBorrowed = owningLabels.borrowed || "Borrowed from:";
    const labelSold = owningLabels.sold || "Sold to:";
    const labelLost = owningLabels.lost || "Last borrowed to:";
    const labelLocation = owningLabels.location || "Location";
    const labelInShelf = owningLabels.in_shelf || "In Shelf";
    const labelNotInShelf = owningLabels.not_in_shelf || "Not In Shelf";
    const labelUnknown = owningLabels.unknown || "Unknown";

    let currentSelect = null;
    let currentStatus = "";
    let currentRowInfo = null;
    let previousValue = "";

    function requiresContact(status) {
      return status === "borrowed" || status === "borrowing" || status === "sold";
    }

    function getLabelFor(status) {
      switch (status) {
        case "borrowed":
          return labelBorrowed;
        case "borrowing":
          return labelBorrowing;
        case "sold":
          return labelSold;
        case "lost":
          return labelLost;
        default:
          return labelBorrowing;
      }
    }

    function normalizeStatus(value) {
      const trimmed = (value || "").trim();
      return trimmed === "" ? "in_shelf" : trimmed;
    }

    function clearOverlayMessage() {
      if (!statusMsg) return;
      statusMsg.style.color = "";
      statusMsg.textContent = "";
    }

    function closeOverlay() {
      overlay.style.display = "none";
      currentSelect = null;
      currentStatus = "";
      currentRowInfo = null;
      previousValue = "";
      clearOverlayMessage();
    }

    function openOverlay(select, status, rowInfo) {
      currentSelect = select;
      currentStatus = status;
      currentRowInfo = rowInfo || null;
      previousValue = select ? (select.dataset.currentValue || select.value || "") : "";
      clearOverlayMessage();

      if (overlayTitle) {
        overlayTitle.textContent = getLabelFor(status);
      }
      const priorState = normalizeStatus(previousValue || "");
      if (status === "sold" && priorState === "borrowing") {
        if (overlayTitle) {
          overlayTitle.textContent = "Borrowed person is buying this book:";
        }
        if (statusMsg) {
          statusMsg.style.color = "";
          statusMsg.textContent = "Confirm that the borrower is purchasing or compensating for the book.";
        }
      }
      if (nameInput) {
        nameInput.value = select ? (select.dataset.contactName || "") : "";
      }
      if (emailInput) {
        emailInput.value = select ? (select.dataset.contactEmail || "") : "";
      }

      overlay.style.display = "flex";
      setTimeout(() => {
        if (nameInput) {
          nameInput.focus();
        }
      }, 0);
    }

    function updateInfoElement(el, status, name, date) {
      if (!el) return;
      const normalizedStatus = (status || "").trim();
      const safeName = escapeHtml(name || "");
      const safeDate = date ? escapeHtml(date) : "";

      if (requiresContact(normalizedStatus)) {
        const label = escapeHtml(getLabelFor(normalizedStatus));
        const displayName = safeName || escapeHtml(labelUnknown);
        let html = label ? `<strong>${label}</strong>` : "";
        if (displayName) {
          html += (html ? "<br>" : "") + displayName;
        }
        if (safeDate) {
          html += `<br><small>${safeDate}</small>`;
        }
        el.innerHTML = html;
        return;
      }

      if (normalizedStatus === "lost") {
        const locationLine = `<strong>${escapeHtml(labelLocation)}</strong>: ${escapeHtml(labelNotInShelf)}`;
        const contactName = safeName || escapeHtml(labelUnknown);
        if (contactName) {
          const lostLabel = escapeHtml(labelLost);
          el.innerHTML = `${locationLine}<br><strong>${lostLabel}</strong> ${contactName}`;
        } else {
          el.innerHTML = locationLine;
        }
        return;
      }

      const locationLine = `<strong>${escapeHtml(labelLocation)}</strong>: ${escapeHtml(labelInShelf)}`;
      el.innerHTML = locationLine;
    }

    function finalizeSelect(select, storedStatus, meta = {}) {
      const uiValue = storedStatus ? storedStatus : "in_shelf";
      select.value = uiValue;
      select.dataset.currentValue = uiValue;
      select.dataset.storedStatus = storedStatus || "";

      if (typeof meta.contactName !== "undefined") {
        select.dataset.contactName = meta.contactName || "";
      }
      if (typeof meta.contactEmail !== "undefined") {
        select.dataset.contactEmail = meta.contactEmail || "";
      }
      if (typeof meta.activeStart !== "undefined") {
        select.dataset.activeStart = meta.activeStart || "";
      }

      toggleReadingStatusLock(select);
      filterOwningOptions(select, storedStatus || "");
    }

    function saveOwningContact(select, status, name, email, options = {}) {
      const previous = options.previousValue || select.dataset.currentValue || "";
      const bookId = parseInt(select.dataset.bookId || "", 10) || 0;
      const userBookId = parseInt(select.dataset.userBookId || "", 10) || 0;
      const normalizedStatus = status === "in_shelf" ? "" : (status || "");
      const trimmedName = (name || "").trim();
      const trimmedEmail = (email || "").trim();
      const previousState = normalizeStatus(previous);
      const transactionType = previousState === "borrowing" && normalizeStatus(status) === "sold"
        ? "bought_by_borrower"
        : "";
      const rowEl = select.closest("tr");

      if (!ajaxUrl || !nonce || !bookId || !userBookId) {
        console.warn("Missing owning overlay configuration.");
        select.value = previous;
        select.dataset.currentValue = previous;
        return Promise.reject(new Error("configuration"));
      }

      const fromOverlay = !!options.fromOverlay;
      if (fromOverlay && statusMsg) {
        statusMsg.style.color = "";
        statusMsg.textContent = msgSaving;
      }

      const body = new URLSearchParams({
        action: "save_owning_contact",
        book_id: String(bookId),
        user_book_id: String(userBookId),
        owning_status: normalizedStatus,
        contact_name: trimmedName,
        contact_email: trimmedEmail,
        transaction_type: transactionType,
        nonce,
      });

      const rowInfo = options.rowInfo || null;

      return fetch(ajaxUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        credentials: "same-origin",
        body,
      })
        .then(r => r.json())
        .then(res => {
          if (!res || !res.success) {
            throw res;
          }

          const payload = res.data || {};
          const savedStatus = typeof payload.owning_status === "string" ? payload.owning_status : normalizedStatus;
          const nextName = typeof payload.counterparty_name === "string" ? payload.counterparty_name : trimmedName;
          const nextEmail = typeof payload.counterparty_email === "string" ? payload.counterparty_email : trimmedEmail;
          const dateString = requiresContact(savedStatus)
            ? (options.dateString || select.dataset.activeStart || new Date().toISOString().split("T")[0])
            : "";

          finalizeSelect(select, savedStatus, {
            contactName: nextName,
            contactEmail: nextEmail,
            activeStart: requiresContact(savedStatus) ? dateString : "",
          });

          if (rowEl) {
            rowEl.setAttribute("data-owning-status", savedStatus ? savedStatus : "in_shelf");
          }

          updateInfoElement(rowInfo, savedStatus, nextName, dateString);

          if (fromOverlay && statusMsg) {
            const successMsg = (payload && payload.message) ? payload.message : "Saved successfully.";
            statusMsg.style.color = "#2f6b2f";
            statusMsg.textContent = successMsg;
            setTimeout(() => {
              if (statusMsg.textContent === successMsg) {
                statusMsg.textContent = "";
              }
            }, 2000);
          }

          closeOverlay();
          return res;
        })
        .catch(err => {
          if (fromOverlay && statusMsg) {
            statusMsg.style.color = "#b00020";
            statusMsg.textContent = (err && err.data && err.data.message) ? err.data.message : msgError;
          } else {
            window.alert((err && err.data && err.data.message) ? err.data.message : msgAlert);
          }

          select.value = previous;
          select.dataset.currentValue = previous;
          if (rowEl) {
            const fallbackStatus = select.dataset.storedStatus || "";
            rowEl.setAttribute("data-owning-status", fallbackStatus ? fallbackStatus : "in_shelf");
          }
          updateInfoElement(rowInfo, select.dataset.storedStatus || "", select.dataset.contactName || "", select.dataset.activeStart || "");
          toggleReadingStatusLock(select);
          filterOwningOptions(select, previous);
          return Promise.reject(err);
        });
    }

    selects.forEach(select => {
      if (select.dataset.libraryOwningBound === "1") {
        return;
      }
      select.dataset.libraryOwningBound = "1";

      if (!select.dataset.currentValue) {
        select.dataset.currentValue = normalizeStatus(select.value || "");
      }
      if (typeof select.dataset.storedStatus === "undefined") {
        const initialStored = select.value && select.value !== "in_shelf" ? select.value : "";
        select.dataset.storedStatus = initialStored;
      }
      if (typeof select.dataset.contactName === "undefined") {
        select.dataset.contactName = "";
      }
      if (typeof select.dataset.contactEmail === "undefined") {
        select.dataset.contactEmail = "";
      }
      if (typeof select.dataset.activeStart === "undefined") {
        select.dataset.activeStart = "";
      }

      const rowInfo = select.closest("tr") ? select.closest("tr").querySelector(".owning-status-info") : null;
      toggleReadingStatusLock(select);
      filterOwningOptions(select, select.dataset.currentValue || select.value || "");

      select.addEventListener("change", () => {
        if (select.disabled) {
          select.value = select.dataset.currentValue || select.value || "";
          toggleReadingStatusLock(select);
          filterOwningOptions(select, select.dataset.currentValue || "");
          return;
        }

        const rawValue = normalizeStatus(select.value);
        const previous = select.dataset.currentValue || normalizeStatus(select.value);
        toggleReadingStatusLock(select);
        filterOwningOptions(select, rawValue);

        if (requiresContact(rawValue)) {
          openOverlay(select, rawValue, rowInfo);
          return;
        }

        if (rawValue === "lost") {
          saveOwningContact(select, rawValue, select.dataset.contactName || "", select.dataset.contactEmail || "", {
            rowInfo,
            previousValue: previous,
            fromOverlay: false,
          }).catch(() => {});
          return;
        }

        if (rawValue === "in_shelf") {
          saveOwningContact(select, rawValue, "", "", {
            rowInfo,
            previousValue: previous,
            fromOverlay: false,
          }).catch(() => {});
          return;
        }

        select.value = previous;
        toggleReadingStatusLock(select);
      });
    });

    if (confirmBtn) {
      confirmBtn.addEventListener("click", () => {
        if (!currentSelect || !requiresContact(currentStatus)) {
          closeOverlay();
          return;
        }

        const name = nameInput ? nameInput.value.trim() : "";
        const email = emailInput ? emailInput.value.trim() : "";

        if (!name || !email) {
          if (statusMsg) {
            statusMsg.style.color = "#b00020";
            statusMsg.textContent = msgMissingContact;
          }
          return;
        }

        confirmBtn.disabled = true;
        saveOwningContact(currentSelect, currentStatus, name, email, {
          rowInfo: currentRowInfo,
          previousValue,
          fromOverlay: true,
          dateString: new Date().toISOString().split("T")[0],
        })
          .catch(() => {})
          .finally(() => {
            confirmBtn.disabled = false;
          });
      });
    }

    if (cancelBtn) {
      cancelBtn.addEventListener("click", () => {
        if (currentSelect) {
          currentSelect.value = previousValue || currentSelect.dataset.currentValue || "";
          toggleReadingStatusLock(currentSelect);
          filterOwningOptions(currentSelect, currentSelect.value || previousValue || "");
        }
        closeOverlay();
      });
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
    setupLibraryOwningOverlay();
    toggleReadingStatusLockForAll();
    applyOwningOptionFilters();
    setupSessionsAjax();
    setupSessionRecorderModal();
    setupLibraryFilterDashboard();
  });
})();
