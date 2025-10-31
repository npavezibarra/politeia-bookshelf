/* global PRS_BOOK, PRS_SESS, jQuery */

window.PRS_isSaving = false;

/**
 * Utilidades
 */
(function () {
  "use strict";

  let overlayConfirmed = false;
  let currentReturnButton = null;
  let currentBoughtContext = null;

  function triggerReturnAction() {
    if (!currentReturnButton || typeof currentReturnButton.__prsReturnHandler !== "function") {
      currentReturnButton = null;
      overlayConfirmed = false;
      return Promise.resolve();
    }

    const handler = currentReturnButton.__prsReturnHandler;
    currentReturnButton = null;

    try {
      const result = handler();
      if (result && typeof result.finally === "function") {
        return result.finally(() => {
          overlayConfirmed = false;
        });
      }
      overlayConfirmed = false;
      return result;
    } catch (err) {
      overlayConfirmed = false;
      throw err;
    }
  }

  function openReturnOverlay(bookId, btnEl) {
    currentReturnButton = btnEl || null;
    const overlay = document.getElementById("return-overlay");

    if (!overlay) {
      if (currentReturnButton && typeof currentReturnButton.__prsReturnHandler === "function") {
        overlayConfirmed = true;
        const result = currentReturnButton.__prsReturnHandler();
        if (result && typeof result.finally === "function") {
          result.finally(() => {
            overlayConfirmed = false;
          });
        } else {
          overlayConfirmed = false;
        }
        if (result && typeof result.catch === "function") {
          result.catch(() => {});
        }
      }
      currentReturnButton = null;
      return;
    }

    overlay.style.display = "flex";

    const yesReturn = document.getElementById("return-overlay-yes");
    const noReturn = document.getElementById("return-overlay-no");

    if (!yesReturn || !noReturn) {
      overlayConfirmed = true;
      const action = triggerReturnAction();
      if (action && typeof action.catch === "function") {
        action.catch(() => {});
      }
      overlay.style.display = "none";
      return;
    }

    yesReturn.replaceWith(yesReturn.cloneNode(true));
    noReturn.replaceWith(noReturn.cloneNode(true));

    const yes = document.getElementById("return-overlay-yes");
    const no = document.getElementById("return-overlay-no");

    yes.addEventListener("click", () => {
      overlay.style.display = "none";
      overlayConfirmed = true;
      const action = triggerReturnAction();
      if (action && typeof action.catch === "function") {
        action.catch(() => {});
      }
    });

    no.addEventListener("click", () => {
      overlay.style.display = "none";
      currentReturnButton = null;
      overlayConfirmed = false;
    });
  }

  function openBoughtOverlay(context) {
    const overlay = document.getElementById("bought-overlay");
    if (!overlay) {
      if (context && typeof context.onConfirm === "function") {
        context.onConfirm();
      }
      return;
    }

    const confirmBtn = document.getElementById("bought-overlay-confirm");
    const cancelBtn = document.getElementById("bought-overlay-cancel");

    if (!confirmBtn || !cancelBtn) {
      overlay.style.display = "none";
      if (context && typeof context.onConfirm === "function") {
        context.onConfirm();
      }
      return;
    }

    overlay.style.display = "flex";
    currentBoughtContext = context || null;

    confirmBtn.replaceWith(confirmBtn.cloneNode(true));
    cancelBtn.replaceWith(cancelBtn.cloneNode(true));

    const confirm = document.getElementById("bought-overlay-confirm");
    const cancel = document.getElementById("bought-overlay-cancel");

    confirm.addEventListener("click", () => {
      overlay.style.display = "none";
      if (currentBoughtContext && typeof currentBoughtContext.onConfirm === "function") {
        currentBoughtContext.onConfirm();
      }
      currentBoughtContext = null;
    });

    cancel.addEventListener("click", () => {
      overlay.style.display = "none";
      if (currentBoughtContext && typeof currentBoughtContext.onCancel === "function") {
        currentBoughtContext.onCancel();
      }
      currentBoughtContext = null;
    });
  }

  document.addEventListener("click", e => {
    const target = e.target;
    if (target && target.classList && target.classList.contains("owning-return-shelf")) {
      openReturnOverlay(target.dataset.bookId || "", target);
    }
  });

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
    const isBorrowRelated = owningValue === "borrowing" || owningValue === "borrowed";
    const isSold = owningValue === "sold";
    const isLost = owningValue === "lost";
    const shouldDisable = isBorrowRelated || isSold || isLost;

    const lostText = readingSelect.getAttribute("data-disabled-text-lost")
      || "Disabled while this book is lost.";
    const defaultDisabledText = readingSelect.getAttribute("data-disabled-text")
      || "Disabled while this book is being borrowed.";
    const disabledText = isLost ? lostText : defaultDisabledText;

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
    sold: ["sold", "bought"],
    bought: ["", "in_shelf"],
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

  function formatOwningDate(raw) {
    if (!raw && raw !== 0) {
      return "";
    }
    const value = String(raw).trim();
    if (!value) {
      return "";
    }
    if (value.includes("T")) {
      return value.split("T")[0];
    }
    if (value.includes(" ")) {
      return value.split(" ")[0];
    }
    return value;
  }

  function formatOwningAmount(raw) {
    if (raw === null || typeof raw === "undefined") {
      return "";
    }
    const value = String(raw).trim();
    if (!value) {
      return "";
    }
    const digits = value.replace(/[^0-9.,-]/g, "");
    if (!digits) {
      return "";
    }
    const normalized = digits.replace(/\./g, "").replace(/,/g, ".");
    const amount = Number(normalized);
    if (!Number.isFinite(amount) || Number.isNaN(amount)) {
      return "";
    }
    return amount.toLocaleString("es-CL");
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
    const returnBtn = qs(".owning-return-shelf", wrap);
    const derivedText = qs("#derived-location-text", wrap);
    const note = qs("#owning-status-note", wrap);
    const overlay = qs("#owning-overlay");
    const overlayTitle = qs("#owning-overlay-title");
    const nameInput = qs("#owning-overlay-name");
    const emailInput = qs("#owning-overlay-email");
    const amountInput = qs("#owning-overlay-amount");
    const confirmBtn = qs("#owning-overlay-confirm");
    const cancelBtn = qs("#owning-overlay-cancel");
    const overlayStatus = qs("#owning-overlay-status");
    if (returnBtn) {
      if (!returnBtn.dataset.bookId && bookId) {
        returnBtn.dataset.bookId = String(bookId);
      }
      if (!returnBtn.dataset.userBookId && userBookId) {
        returnBtn.dataset.userBookId = String(userBookId);
      }
    }

    const ajaxUrl = (typeof window.ajaxurl === "string" && window.ajaxurl)
      || (window.PRS_BOOK && PRS_BOOK.ajax_url)
      || "";

    const savedNameAttr = wrap.getAttribute("data-contact-name") || "";
    const labelBorrowing = wrap.getAttribute("data-label-borrowing") || "Borrowing to:";
    const labelBorrowed = wrap.getAttribute("data-label-borrowed") || "Borrowed from:";
    const labelSold = wrap.getAttribute("data-label-sold") || "Sold to:";
    const labelLost = wrap.getAttribute("data-label-lost") || "Last borrowed to:";
    const labelSoldOn = wrap.getAttribute("data-label-sold-on") || "Sold on:";
    const labelLostDate = wrap.getAttribute("data-label-lost-date") || "Lost:";
    const labelUnknown = wrap.getAttribute("data-label-unknown") || "Unknown";
    const contactStatuses = ["borrowed", "borrowing", "sold"];
    const savedSaleAmountAttr = wrap.getAttribute("data-sale-amount") || "";

    let savedOwningStatus = select ? (select.value || "").trim() : "";
    let pendingStatus = "";
    let lastContactName = savedNameAttr;
    let loanDate = wrap.getAttribute("data-active-start") || "";
    let lastSaleAmount = savedSaleAmountAttr;

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
      const amountRaw = typeof options.amount === "undefined" || options.amount === null
        ? ""
        : String(options.amount);
      const formattedAmount = statusValue === "sold" ? formatOwningAmount(amountRaw) : "";
      const textParts = [label];
      if (normalizedName) {
        textParts.push(normalizedName);
      }
      if (formattedAmount) {
        textParts.push(`$${formattedAmount}`);
      }
      if (date) {
        textParts.push(date);
      }

      if (allowRich) {
        const safeLabel = escapeHtml(label);
        const safeName = escapeHtml(normalizedName);
        const safeDate = escapeHtml(date);
        const safeAmount = formattedAmount ? escapeHtml(formattedAmount) : "";
        let html = `<strong>${safeLabel}</strong>`;
        if (statusValue === "sold") {
          if (safeName) {
            html += `<br>${safeName}`;
            if (safeAmount) {
              html += ` for $${safeAmount}`;
            }
          } else if (safeAmount) {
            html += `<br>$${safeAmount}`;
          }
        } else if (safeName) {
          html += `<br>${safeName}`;
        }
        if (safeDate) {
          html += `<br><small>${safeDate}</small>`;
        }
        return {
          html,
          text: textParts.join(" ").trim(),
        };
      }

      return {
        text: textParts.join(" ").trim(),
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

    function updateOwningStatusInfo(statusValue, changeDate, contactName, saleAmountRaw) {
      const normalized = normalizeOwningState(statusValue);
      if (normalized !== "lost" && normalized !== "sold") {
        return;
      }

      const infoEl = document.querySelector(`.owning-status-info[data-book-id="${bookId}"]`) || status;
      if (!infoEl) {
        return;
      }

      const formattedDate = formatOwningDate(changeDate);
      const safeDate = formattedDate ? escapeHtml(formattedDate) : "";

      if (normalized === "lost") {
        if (safeDate) {
          const lostLabel = escapeHtml(labelLostDate);
          infoEl.innerHTML = `<strong>${lostLabel}</strong><br><small>${safeDate}</small>`;
        } else {
          const lostLabel = escapeHtml(labelLostDate);
          infoEl.innerHTML = `<strong>${lostLabel}</strong>`;
        }
        return;
      }

      const soldLabel = escapeHtml(labelSold);
      const safeName = escapeHtml((contactName || "").trim());
      const safeDisplayName = safeName || escapeHtml(labelUnknown);
      const formattedAmount = formatOwningAmount(saleAmountRaw);
      const safeAmount = formattedAmount ? escapeHtml(formattedAmount) : "";
      let html = `<strong>${soldLabel}</strong>`;
      if (safeDisplayName) {
        html += `<br>${safeDisplayName}`;
        if (safeAmount) {
          html += ` for $${safeAmount}`;
        }
      } else if (safeAmount) {
        html += `<br>$${safeAmount}`;
      }
      if (safeDate) {
        html += `<br><small>${safeDate}</small>`;
      }
      infoEl.innerHTML = html;
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
      if (amountInput) {
        if (statusValue === "sold") {
          amountInput.value = lastSaleAmount || "";
          amountInput.style.display = "";
        } else {
          amountInput.value = "";
          amountInput.style.display = "none";
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
      if (amountInput) {
        amountInput.value = "";
        amountInput.style.display = "none";
      }
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
            lastSaleAmount = "";
            wrap.setAttribute("data-sale-amount", "");
            if (select) {
              delete select.dataset.saleAmount;
            }
            applyStatusDescription("", "", { amount: "" });
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

      if (window.PRS_isSaving) {
        return Promise.resolve(null);
      }
      window.PRS_isSaving = true;

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

      let amountValue = "";
      if (options && Object.prototype.hasOwnProperty.call(options, "amount")) {
        amountValue = options.amount == null ? "" : String(options.amount);
      } else if (amountInput && amountInput.style.display !== "none") {
        amountValue = amountInput.value.trim();
      }

      const body = new URLSearchParams({
        action: "save_owning_contact",
        book_id: String(bookId),
        user_book_id: String(userBookId),
        owning_status: statusValue,
        contact_name: trimmedName,
        contact_email: trimmedEmail,
        transaction_type: transactionType,
        amount: amountValue,
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
          const savedStatus = typeof payload.owning_status === "string" ? payload.owning_status : normalizedStatus;
          const nextName = typeof payload.counterparty_name === "string" ? payload.counterparty_name : trimmedName;
          const nextEmail = typeof payload.counterparty_email === "string" ? payload.counterparty_email : trimmedEmail;
          const normalizedSaved = normalizeOwningState(savedStatus);
          const responseDate = formatOwningDate(payload.date);
          const todayFormatted = formatOwningDate(new Date().toISOString());
          const shouldShowChangeDate = normalizedSaved === "lost" || normalizedSaved === "sold";
          const changeDate = shouldShowChangeDate ? (responseDate || todayFormatted) : "";
          const payloadAmount = typeof payload.amount !== "undefined" && payload.amount !== null
            ? String(payload.amount)
            : amountValue;
          const nextSaleAmount = normalizedSaved === "sold" ? payloadAmount : "";

          lastContactName = nextName || "";
          savedOwningStatus = savedStatus;
          lastSaleAmount = normalizedSaved === "sold" ? nextSaleAmount : "";
          wrap.setAttribute("data-sale-amount", lastSaleAmount);
          if (select) {
            if (lastSaleAmount) {
              select.dataset.saleAmount = lastSaleAmount;
            } else {
              delete select.dataset.saleAmount;
            }
          }

          wrap.setAttribute("data-contact-name", lastContactName);
          wrap.setAttribute("data-contact-email", nextEmail || "");

          if (contactStatuses.indexOf(savedStatus) !== -1) {
            const nextLoanDate = normalizedSaved === "sold" && changeDate
              ? changeDate
              : (responseDate || todayFormatted);
            setLoanDate(nextLoanDate);
          } else {
            setLoanDate("");
          }

          updateDerived(savedStatus);
          applyStatusDescription(savedStatus, lastContactName, {
            rich: contactStatuses.indexOf(savedStatus) !== -1 && (normalizedSaved === "sold" || !!loanDate),
            date: loanDate,
            amount: lastSaleAmount,
          });

          if (normalizedSaved === "sold" || (normalizedSaved === "lost" && changeDate)) {
            updateOwningStatusInfo(savedStatus, changeDate, nextName, lastSaleAmount);
          }

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
            select.value = savedStatus;
          }
          toggleReadingStatusLock(select);
          filterOwningOptions(select, savedStatus);
          if (returnBtn) {
            const shouldShowReturn = normalizedSaved === "borrowing" || normalizedSaved === "borrowed";
            returnBtn.style.display = shouldShowReturn ? "" : "none";
            returnBtn.disabled = false;
          }

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
          if (returnBtn) {
            const shouldShowReturn = savedOwningStatus === "borrowing" || savedOwningStatus === "borrowed";
            returnBtn.style.display = shouldShowReturn ? "" : "none";
            returnBtn.disabled = false;
          }
          throw err;
        })
        .finally(() => {
          window.PRS_isSaving = false;
        });
    }

    function markAsReturned(triggerBtn) {
      if (!ajaxUrl || !bookId || !userBookId) {
        console.warn("Missing owning overlay configuration.");
        return Promise.reject(new Error("configuration"));
      }

      const activeBtn = triggerBtn || returnBtn;
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
      if (activeBtn) {
        activeBtn.disabled = true;
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
          lastSaleAmount = "";
          wrap.setAttribute("data-contact-name", "");
          wrap.setAttribute("data-contact-email", "");
          wrap.setAttribute("data-sale-amount", "");
          setLoanDate("");
          updateDerived("");

          if (select) {
            select.value = "";
            delete select.dataset.saleAmount;
          }
          toggleReadingStatusLock(select);
          filterOwningOptions(select, "");

          const readingSelect = document.querySelector(`.reading-status-select[data-book-id="${bookId}"]`)
            || document.getElementById("reading-status-select");
          if (readingSelect) {
            readingSelect.disabled = false;
            readingSelect.classList.remove("is-disabled");
            readingSelect.setAttribute("aria-disabled", "false");
            readingSelect.title = "";
          }

          if (activeBtn) {
            activeBtn.style.display = "none";
            activeBtn.disabled = false;
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
          if (activeBtn) {
            activeBtn.disabled = false;
          }
          filterOwningOptions(select, savedOwningStatus);
          throw err;
        })
        .finally(() => {
          overlayConfirmed = false;
        });
    }

    if (select) {
      updateDerived(select.value || "");
      applyTypeLock();
      applyStatusDescription(savedOwningStatus, lastContactName, {
        rich: contactStatuses.indexOf(savedOwningStatus) !== -1
          && (normalizeOwningState(savedOwningStatus) === "sold" || !!loanDate),
        date: loanDate,
        amount: lastSaleAmount,
      });
      toggleReadingStatusLock(select);
      filterOwningOptions(select, savedOwningStatus);
      select.addEventListener("change", () => {
        if (overlayConfirmed) {
          overlayConfirmed = false;
          return;
        }
        if (window.PRS_isSaving) {
          return;
        }
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
          const readingSelect = document.querySelector(`.reading-status-select[data-book-id="${bookId}"]`)
            || document.getElementById("reading-status-select");
          if (readingSelect) {
            readingSelect.disabled = true;
            readingSelect.classList.add("is-disabled");
            readingSelect.setAttribute("aria-disabled", "true");
            readingSelect.title = "Disabled while this book is lost.";
          }

          const fallbackName = lastContactName || labelUnknown;
          saveOwningContact("lost", fallbackName, "", { fromOverlay: false, previousValue: savedOwningStatus })
            .catch(() => {})
            .finally(() => {
              filterOwningOptions(select, savedOwningStatus);
            });
          return;
        }

        if (val === "bought") {
          const revertSelection = () => {
            select.value = savedOwningStatus;
            toggleReadingStatusLock(select);
            filterOwningOptions(select, savedOwningStatus);
          };

          revertSelection();

          openBoughtOverlay({
            onConfirm: () => {
              saveOwningContact("bought", "", "", {
                previousValue: savedOwningStatus,
                fromOverlay: false,
                amount: "",
              }).catch(() => {
                revertSelection();
              });
            },
            onCancel: revertSelection,
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

    if (returnBtn) {
      returnBtn.__prsReturnHandler = () => markAsReturned(returnBtn);
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

        const saleAmount = amountInput && amountInput.style.display !== "none"
          ? amountInput.value.trim()
          : "";
        saveOwningContact(pendingStatus, name, email, {
          previousValue: savedOwningStatus,
          amount: saleAmount,
        })
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
        rich: contactStatuses.indexOf(savedOwningStatus) !== -1
          && (normalizeOwningState(savedOwningStatus) === "sold" || !!loanDate),
        date: loanDate,
        amount: lastSaleAmount,
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
    const amountInput = qs("#owning-overlay-amount");
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
    const labelSoldOn = owningLabels.sold_on || "Sold on:";
    const labelLostDate = owningLabels.lost_date || "Lost:";
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
      if (amountInput) {
        amountInput.value = "";
        amountInput.style.display = "none";
      }
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

      if (amountInput) {
        if (status === "sold") {
          amountInput.value = select ? (select.dataset.saleAmount || "") : "";
          amountInput.style.display = "";
        } else {
          amountInput.value = "";
          amountInput.style.display = "none";
        }
      }

      overlay.style.display = "flex";
      setTimeout(() => {
        if (nameInput) {
          nameInput.focus();
        }
      }, 0);
    }

    function updateInfoElement(el, status, name, date, amount = "") {
      if (!el) return;
      const normalizedStatus = (status || "").trim();
      const formattedDate = formatOwningDate(date);
      const safeDate = formattedDate ? escapeHtml(formattedDate) : "";
      const safeName = escapeHtml(name || "");
      const safeDisplayName = safeName || escapeHtml(labelUnknown);
      const formattedAmount = normalizedStatus === "sold" ? formatOwningAmount(amount) : "";
      const safeAmount = formattedAmount ? escapeHtml(formattedAmount) : "";

      if (normalizedStatus === "lost") {
        if (safeDate) {
          const lostLabel = escapeHtml(labelLostDate);
          el.innerHTML = `<strong>${lostLabel}</strong><br><small>${safeDate}</small>`;
        } else {
          const locationLine = `<strong>${escapeHtml(labelLocation)}</strong>: ${escapeHtml(labelNotInShelf)}`;
          el.innerHTML = locationLine;
        }
        return;
      }

      if (normalizedStatus === "sold") {
        const soldLabel = escapeHtml(labelSold);
        let html = `<strong>${soldLabel}</strong>`;
        if (safeDisplayName) {
          html += `<br>${safeDisplayName}`;
          if (safeAmount) {
            html += ` for $${safeAmount}`;
          }
        } else if (safeAmount) {
          html += `<br>$${safeAmount}`;
        }
        if (safeDate) {
          html += `<br><small>${safeDate}</small>`;
        }
        el.innerHTML = html;
        return;
      }

      if (requiresContact(normalizedStatus)) {
        const label = escapeHtml(getLabelFor(normalizedStatus));
        const displayName = safeDisplayName;
        let html = label ? `<strong>${label}</strong>` : "";
        if (displayName) {
          html += (html ? "<br>" : "") + displayName;
        }
        if (safeAmount && normalizedStatus === "sold") {
          html += `<br>$${safeAmount}`;
        }
        if (safeDate) {
          html += `<br><small>${safeDate}</small>`;
        }
        el.innerHTML = html;
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
      if (typeof meta.saleAmount !== "undefined") {
        if (meta.saleAmount) {
          select.dataset.saleAmount = meta.saleAmount;
        } else {
          delete select.dataset.saleAmount;
        }
      } else if (storedStatus !== "sold") {
        delete select.dataset.saleAmount;
      }

      toggleReadingStatusLock(select);
      filterOwningOptions(select, storedStatus || "");

      const row = select.closest("tr");
      const returnBtn = row ? row.querySelector(".owning-return-shelf") : null;
      if (returnBtn) {
        const normalizedStatus = normalizeStatus(storedStatus || "");
        const shouldShow = normalizedStatus === "borrowing" || normalizedStatus === "borrowed";
        returnBtn.style.display = shouldShow ? "" : "none";
        if (!returnBtn.dataset.bookId && select.dataset.bookId) {
          returnBtn.dataset.bookId = select.dataset.bookId;
        }
        if (!returnBtn.dataset.userBookId && select.dataset.userBookId) {
          returnBtn.dataset.userBookId = select.dataset.userBookId;
        }
        returnBtn.disabled = select.disabled;
      }
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

      if (window.PRS_isSaving) {
        return Promise.resolve(null);
      }
      window.PRS_isSaving = true;

      const fromOverlay = !!options.fromOverlay;
      if (fromOverlay && statusMsg) {
        statusMsg.style.color = "";
        statusMsg.textContent = msgSaving;
      }

      let amountValue = "";
      if (options && Object.prototype.hasOwnProperty.call(options, "amount")) {
        amountValue = options.amount == null ? "" : String(options.amount);
      } else if (amountInput && amountInput.style.display !== "none") {
        amountValue = amountInput.value.trim();
      }

      const body = new URLSearchParams({
        action: "save_owning_contact",
        book_id: String(bookId),
        user_book_id: String(userBookId),
        owning_status: normalizedStatus,
        contact_name: trimmedName,
        contact_email: trimmedEmail,
        transaction_type: transactionType,
        amount: amountValue,
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
          const normalizedSaved = normalizeStatus(savedStatus);
          const responseDate = formatOwningDate(payload.date);
          const todayFormatted = formatOwningDate(new Date().toISOString());
          const shouldShowChangeDate = normalizedSaved === "lost" || normalizedSaved === "sold";
          const changeDate = shouldShowChangeDate ? (responseDate || todayFormatted) : "";
          const payloadAmount = typeof payload.amount !== "undefined" && payload.amount !== null
            ? String(payload.amount)
            : amountValue;
          const nextSaleAmount = normalizedSaved === "sold" ? payloadAmount : "";
          let activeDate = "";
          if (requiresContact(savedStatus)) {
            if (normalizedSaved === "sold") {
              activeDate = changeDate || options.dateString || select.dataset.activeStart || todayFormatted;
            } else {
              activeDate = options.dateString || select.dataset.activeStart || (responseDate || todayFormatted);
            }
          }
          const infoDate = changeDate || activeDate;

          finalizeSelect(select, savedStatus, {
            contactName: nextName,
            contactEmail: nextEmail,
            activeStart: requiresContact(savedStatus) ? activeDate : "",
            saleAmount: normalizedSaved === "sold" ? nextSaleAmount : "",
          });

          if (rowEl) {
            rowEl.setAttribute("data-owning-status", savedStatus ? savedStatus : "in_shelf");
          }

          if (rowInfo) {
            if (normalizedSaved === "sold" && nextSaleAmount) {
              rowInfo.dataset.saleAmount = nextSaleAmount;
            } else {
              delete rowInfo.dataset.saleAmount;
            }
          }

          updateInfoElement(rowInfo, savedStatus, nextName, infoDate, nextSaleAmount);

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
          updateInfoElement(
            rowInfo,
            select.dataset.storedStatus || "",
            select.dataset.contactName || "",
            select.dataset.activeStart || "",
            select.dataset.saleAmount || (rowInfo && rowInfo.dataset ? rowInfo.dataset.saleAmount : "") || ""
          );
          toggleReadingStatusLock(select);
          filterOwningOptions(select, previous);
          const returnBtn = rowEl ? rowEl.querySelector(".owning-return-shelf") : null;
          if (returnBtn) {
            const stored = normalizeStatus(select.dataset.storedStatus || "");
            const shouldShow = stored === "borrowing" || stored === "borrowed";
            returnBtn.style.display = shouldShow ? "" : "none";
            returnBtn.disabled = select.disabled;
          }
          return Promise.reject(err);
        })
        .finally(() => {
          window.PRS_isSaving = false;
        });
    }

    function markAsReturnedRow(select, returnBtn, rowInfo) {
      const bookId = parseInt((returnBtn && returnBtn.dataset.bookId) || select.dataset.bookId || "", 10) || 0;
      const userBookId = parseInt((returnBtn && returnBtn.dataset.userBookId) || select.dataset.userBookId || "", 10) || 0;

      if (!ajaxUrl || !nonce || !bookId || !userBookId) {
        console.warn("Missing owning overlay configuration.");
        overlayConfirmed = false;
        return Promise.reject(new Error("configuration"));
      }

      if (returnBtn) {
        returnBtn.disabled = true;
      }

      const body = new URLSearchParams({
        action: "mark_as_returned",
        book_id: String(bookId),
        user_book_id: String(userBookId),
        nonce,
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

          select.dataset.currentValue = "in_shelf";
          select.dataset.storedStatus = "";
          select.dataset.contactName = "";
          select.dataset.contactEmail = "";
          select.dataset.activeStart = "";
          delete select.dataset.saleAmount;
          select.value = "in_shelf";

          const row = select.closest("tr");
          if (row) {
            row.setAttribute("data-owning-status", "in_shelf");
          }

          if (rowInfo && rowInfo.dataset) {
            delete rowInfo.dataset.saleAmount;
          }

          updateInfoElement(rowInfo, "", "", "", "");
          toggleReadingStatusLock(select);
          filterOwningOptions(select, "");

          const readingSelect = document.querySelector(`.reading-status-select[data-book-id="${select.dataset.bookId || ""}"]`)
            || document.getElementById("reading-status-select");
          if (readingSelect) {
            readingSelect.disabled = false;
            readingSelect.classList.remove("is-disabled");
            readingSelect.setAttribute("aria-disabled", "false");
            readingSelect.title = "";
          }

          if (returnBtn) {
            returnBtn.style.display = "none";
            returnBtn.disabled = false;
          }

          overlayConfirmed = false;
          return res;
        })
        .catch(err => {
          if (returnBtn) {
            returnBtn.disabled = false;
          }
          const message = (err && err.data && err.data.message) ? err.data.message : msgAlert;
          window.alert(message);
          overlayConfirmed = false;
          throw err;
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

      const row = select.closest("tr");
      const rowInfo = row ? row.querySelector(".owning-status-info") : null;
      const returnBtn = row ? row.querySelector(".owning-return-shelf") : null;
      if (returnBtn) {
        if (!returnBtn.dataset.bookId && select.dataset.bookId) {
          returnBtn.dataset.bookId = select.dataset.bookId;
        }
        if (!returnBtn.dataset.userBookId && select.dataset.userBookId) {
          returnBtn.dataset.userBookId = select.dataset.userBookId;
        }
        const initialState = normalizeStatus(select.dataset.currentValue || select.value || "");
        const shouldShowReturn = initialState === "borrowing" || initialState === "borrowed";
        returnBtn.style.display = shouldShowReturn ? "" : "none";
        returnBtn.disabled = select.disabled;
        returnBtn.__prsReturnHandler = () => markAsReturnedRow(select, returnBtn, rowInfo);
      }
      toggleReadingStatusLock(select);
      filterOwningOptions(select, select.dataset.currentValue || select.value || "");

      select.addEventListener("change", () => {
        if (overlayConfirmed) {
          overlayConfirmed = false;
          return;
        }
        if (window.PRS_isSaving) {
          return;
        }
        if (select.disabled) {
          select.value = select.dataset.currentValue || select.value || "";
          toggleReadingStatusLock(select);
          filterOwningOptions(select, select.dataset.currentValue || "");
          return;
        }

        const rawValue = normalizeStatus(select.value);
        const previous = select.dataset.currentValue || normalizeStatus(select.value);

        if (rawValue === "bought") {
          const revertSelection = () => {
            select.value = previous;
            select.dataset.currentValue = previous;
            toggleReadingStatusLock(select);
            filterOwningOptions(select, previous);
          };

          revertSelection();

          openBoughtOverlay({
            onConfirm: () => {
              saveOwningContact(select, "bought", "", "", {
                rowInfo,
                previousValue: previous,
                fromOverlay: false,
                amount: "",
              }).catch(() => {
                revertSelection();
              });
            },
            onCancel: revertSelection,
          });
          return;
        }

        toggleReadingStatusLock(select);
        filterOwningOptions(select, rawValue);

        if (requiresContact(rawValue)) {
          openOverlay(select, rawValue, rowInfo);
          return;
        }

        const readingSelect = document.querySelector(`.reading-status-select[data-book-id="${select.dataset.bookId || ""}"]`)
          || document.getElementById("reading-status-select");

        if (rawValue === "lost") {
          if (readingSelect) {
            readingSelect.disabled = true;
            readingSelect.classList.add("is-disabled");
            readingSelect.setAttribute("aria-disabled", "true");
            readingSelect.title = "Disabled while this book is lost.";
          }

          saveOwningContact(select, rawValue, select.dataset.contactName || "", select.dataset.contactEmail || "", {
            rowInfo,
            previousValue: previous,
            fromOverlay: false,
          }).catch(() => {});
          return;
        }

        if (rawValue === "in_shelf") {
          if (readingSelect) {
            readingSelect.disabled = false;
            readingSelect.classList.remove("is-disabled");
            readingSelect.setAttribute("aria-disabled", "false");
            readingSelect.title = "";
          }

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
        const amountValue = amountInput && amountInput.style.display !== "none"
          ? amountInput.value.trim()
          : "";

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
          dateString: formatOwningDate(new Date().toISOString()),
          amount: amountValue,
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

  function setupReadingDensityBar() {
    const canvas = document.getElementById("prs-reading-density-canvas");
    if (!canvas) return;

    const rawTotal = canvas.getAttribute("data-total-pages") || "";
    const totalPages = parseInt(rawTotal, 10);
    if (!Number.isFinite(totalPages) || totalPages <= 0) return;

    const rawSessions = canvas.getAttribute("data-sessions") || "";
    let sessions = [];

    try {
      sessions = JSON.parse(rawSessions);
    } catch (err) {
      sessions = [];
    }

    if (!Array.isArray(sessions) || sessions.length === 0) {
      return;
    }

    const ctx = canvas.getContext("2d");
    if (!ctx) return;

    const container = canvas.parentElement || canvas;

    function renderDensity() {
      const width = container.offsetWidth || canvas.clientWidth || 0;
      const height = container.offsetHeight || canvas.clientHeight || 0;

      if (width === 0 || height === 0) {
        return;
      }

      canvas.width = width;
      canvas.height = height;

      const pageDensity = new Array(totalPages).fill(0);

      sessions.forEach(session => {
        const startValue = parseInt(session.start_page, 10);
        const endValue = parseInt(session.end_page, 10);
        const start = Math.min(totalPages, Math.max(1, Number.isFinite(startValue) ? startValue : 0));
        const end = Math.min(totalPages, Math.max(start, Number.isFinite(endValue) ? endValue : 0));

        for (let i = start - 1; i < end; i++) {
          pageDensity[i] += 1;
        }
      });

      ctx.clearRect(0, 0, width, height);

      const pageWidth = width / totalPages;

      for (let i = 0; i < totalPages; i++) {
        const density = pageDensity[i];
        if (density === 0) {
          continue;
        }

        const clamped = Math.min(density, 5);
        const lightness = 70 - (clamped - 1) * 10;
        ctx.fillStyle = `hsl(210, 90%, ${lightness}%)`;
        ctx.fillRect(i * pageWidth, 0, pageWidth + 0.5, height);
      }
    }

    renderDensity();
    window.addEventListener("resize", renderDensity);
  }


  jQuery(document).on("click", ".prs-remove-book", function () {
    const btn = jQuery(this);
    const id = parseInt(String(btn.data("id") || 0), 10);
    const nonce = String(btn.data("nonce") || "");
    const originalText = btn.text();

    if (!id || !nonce) {
      return;
    }

    if (!window.confirm("Are you sure you want to remove this book from your library?")) {
      return;
    }

    btn.prop("disabled", true).text("Removing...");

    const ajaxUrl = (typeof PRS_LIBRARY !== "undefined" && PRS_LIBRARY && PRS_LIBRARY.ajax_url)
      ? PRS_LIBRARY.ajax_url
      : (typeof ajaxurl !== "undefined" ? ajaxurl : "");

    if (!ajaxUrl) {
      window.alert("Error removing book.");
      btn.prop("disabled", false).text(originalText);
      return;
    }

    jQuery.post(ajaxUrl, {
      action: "politeia_remove_user_book",
      id,
      nonce,
    })
      .done(response => {
        if (response && response.success) {
          const row = btn.closest("tr");
          if (row && row.length) {
            row.fadeOut(300, function () {
              jQuery(this).remove();
            });
          }
        } else {
          window.alert((response && response.data) || "Error removing book.");
          btn.prop("disabled", false).text(originalText);
        }
      })
      .fail(() => {
        window.alert("Error removing book.");
        btn.prop("disabled", false).text(originalText);
      });
  });




  function setupSearchCoverOverlay() {
    const searchBtn = document.getElementById("prs-cover-search");
    const overlay = document.getElementById("prs-search-cover-overlay");
    const cancelBtn = document.getElementById("prs-cancel-cover");
    const setCoverBtn = document.getElementById("prs-set-cover");
    const optionsContainer = overlay ? overlay.querySelector(".prs-search-cover-options") : null;
    let attributionEl = null;

    if (!overlay || !optionsContainer) {
      return;
    }

    function ensureAttributionElement() {
      if (attributionEl && attributionEl.isConnected) {
        return attributionEl;
      }
      attributionEl = document.createElement("p");
      attributionEl.className = "prs-search-cover-attribution";
      attributionEl.textContent = "Images from Google Books";
      const parent = optionsContainer.parentNode;
      if (parent) {
        parent.insertBefore(attributionEl, optionsContainer.nextSibling);
      }
      return attributionEl;
    }

    function toggleAttribution(isVisible) {
      const node = ensureAttributionElement();
      if (!node) {
        return;
      }
      node.style.display = isVisible ? "" : "none";
    }

    toggleAttribution(false);

    const ajaxUrl = (window.PRS_BOOK && PRS_BOOK.ajax_url)
      || (typeof window.ajaxurl === "string" ? window.ajaxurl : "");
    const nonce = (window.PRS_BOOK && PRS_BOOK.cover_nonce) || "";
    const bookId = (window.PRS_BOOK && PRS_BOOK.book_id) ? parseInt(String(PRS_BOOK.book_id), 10) : 0;
    const userBookId = (window.PRS_BOOK && PRS_BOOK.user_book_id) ? parseInt(String(PRS_BOOK.user_book_id), 10) : 0;
    const userId = (window.PRS_BOOK && PRS_BOOK.user_id) ? parseInt(String(PRS_BOOK.user_id), 10) : 0;

    let currentSelection = null;
    let isSearching = false;
    let isSaving = false;

    function setOverlayVisibility(isVisible) {
      if (isVisible) {
        overlay.classList.remove("is-hidden");
        overlay.setAttribute("aria-hidden", "false");
      } else {
        overlay.classList.add("is-hidden");
        overlay.setAttribute("aria-hidden", "true");
      }
    }

    function resetSelection() {
      currentSelection = null;
      if (setCoverBtn) {
        setCoverBtn.disabled = true;
      }
      optionsContainer.querySelectorAll(".prs-cover-option").forEach(opt => {
        opt.classList.remove("selected");
      });
    }

    function renderMessage(message, className) {
      optionsContainer.innerHTML = "";
      toggleAttribution(false);
      const wrapper = document.createElement("p");
      wrapper.textContent = message;
      wrapper.className = className || "prs-search-cover-message";
      optionsContainer.appendChild(wrapper);
    }

    function renderLoading() {
      renderMessage("Searching covers…", "prs-search-cover-loading");
    }

    function normalizeImageUrl(url) {
      if (!url) return null;

      let normalized = String(url);

      // Fix escaped slashes from JSON (e.g. "http:\/\/" → "http://")
      normalized = normalized.replace(/\\\//g, "/");

      // Force HTTPS to avoid mixed content blocking
      if (normalized.startsWith("http://")) {
        normalized = normalized.replace("http://", "https://");
      }

      return normalized;
    }

    function sanitizeCoverImage(img) {
      if (!img) {
        return;
      }

      img.removeAttribute("width");
      img.removeAttribute("height");
      img.removeAttribute("srcset");
      img.removeAttribute("sizes");

      if (img.style && typeof img.style.removeProperty === "function") {
        img.style.removeProperty("width");
        img.style.removeProperty("height");
        img.style.removeProperty("max-width");
        img.style.removeProperty("max-height");
      } else if (img.style) {
        img.style.width = "";
        img.style.height = "";
        img.style.maxWidth = "";
        img.style.maxHeight = "";
      }

      const classesToRemove = [
        "size-thumbnail",
        "size-medium",
        "size-large",
        "size-full",
        "wp-post-image",
        "attachment-thumbnail",
        "attachment-medium",
        "attachment-large",
        "attachment-full",
      ];

      classesToRemove.forEach(cls => {
        if (img.classList && img.classList.contains(cls)) {
          img.classList.remove(cls);
        }
      });
    }

    function prepareExistingCoverFrame() {
      const frame = document.getElementById("prs-cover-frame");
      if (!frame) {
        return;
      }

      const img = frame.querySelector("img.prs-cover-img");
      if (!img) {
        return;
      }

      sanitizeCoverImage(img);

      const currentSrc = img.getAttribute("src") || "";
      const normalized = normalizeImageUrl(currentSrc);
      if (normalized && normalized !== currentSrc) {
        img.src = normalized;
      }

      if (!frame.classList.contains("has-image")) {
        frame.classList.add("has-image");
      }

      if (!frame.getAttribute("data-cover-state")) {
        frame.setAttribute("data-cover-state", "image");
      }

      window.PRS_BOOK = window.PRS_BOOK || {};
      window.PRS_BOOK.cover_url = normalized || currentSrc;
    }

    function buildRequestBody(params) {
      return Object.keys(params)
        .map(key => `${encodeURIComponent(key)}=${encodeURIComponent(params[key])}`)
        .join("&");
    }

    function selectOption(option) {
      if (!option || !optionsContainer.contains(option)) {
        return;
      }
      optionsContainer.querySelectorAll(".prs-cover-option").forEach(opt => {
        if (opt !== option) {
          opt.classList.remove("selected");
        }
      });
      option.classList.add("selected");
      currentSelection = option;
      if (setCoverBtn) {
        setCoverBtn.disabled = false;
      }
    }

    function renderResults(items) {
      optionsContainer.innerHTML = "";
      toggleAttribution(false);

      if (!Array.isArray(items) || items.length === 0) {
        renderMessage("No covers found.", "prs-search-cover-empty");
        return;
      }

      let appended = 0;
      let displayed = 0;
      const limit = 5;

      for (let i = 0; i < items.length && displayed < limit; i += 1) {
        const entry = items[i];
        if (!entry || typeof entry !== "object") {
          continue;
        }

        const volume = entry.volumeInfo || {};
        const imageLinks = volume.imageLinks || null;
        const imageUrl = normalizeImageUrl(
          (imageLinks && imageLinks.thumbnail)
            || (imageLinks && imageLinks.smallThumbnail),
        );

        const option = document.createElement("div");
        option.className = "prs-cover-option";
        option.setAttribute("role", "button");
        option.setAttribute("tabindex", "0");

        const title = volume.title ? String(volume.title).trim() : "";
        const author = Array.isArray(volume.authors) && volume.authors.length
          ? String(volume.authors[0]).trim()
          : "";

        if (title) {
          option.dataset.coverTitle = title;
        }
        if (author) {
          option.dataset.coverAuthor = author;
        }

        if (imageUrl) {
          option.dataset.coverUrl = imageUrl;

          const img = document.createElement("img");
          img.src = imageUrl;
          img.alt = title || "";
          img.className = "prs-cover-image";
          img.loading = "lazy";
          option.appendChild(img);

          appended += 1;
        } else {
          const placeholder = document.createElement("div");
          placeholder.className = "prs-cover-option__placeholder";
          placeholder.textContent = "image not available";
          option.appendChild(placeholder);
        }

        optionsContainer.appendChild(option);
        displayed += 1;
      }

      if (displayed === 0) {
        renderMessage("No covers found.", "prs-search-cover-empty");
        return;
      }

      toggleAttribution(appended > 0);
    }

    function applyCoverUpdate(url, option) {
      const frame = document.getElementById("prs-cover-frame");
      const figure = document.getElementById("prs-book-cover-figure");
      if (!frame || !figure) {
        return;
      }

      let img = figure.querySelector("#prs-cover-img");
      const placeholder = document.getElementById("prs-cover-placeholder");

      if (!img) {
        img = document.createElement("img");
        img.id = "prs-cover-img";
        img.className = "prs-cover-img";
        figure.insertBefore(img, figure.firstChild || null);
      }

      sanitizeCoverImage(img);

      const normalizedUrl = normalizeImageUrl(url);
      if (normalizedUrl) {
        img.src = normalizedUrl;
      } else {
        img.src = url;
      }

      const selectedTitle = option && option.dataset.coverTitle ? option.dataset.coverTitle : "";
      const fallbackTitle = (window.PRS_BOOK && typeof PRS_BOOK.title === "string") ? PRS_BOOK.title : "";
      const altTitle = selectedTitle || fallbackTitle;
      img.alt = altTitle ? `Cover for ${altTitle}` : "Book cover";

      if (placeholder) {
        const actions = placeholder.querySelector(".prs-cover-actions");
        if (actions) {
          let overlayActions = frame.querySelector(".prs-cover-overlay");
          if (!overlayActions) {
            overlayActions = document.createElement("div");
            overlayActions.className = "prs-cover-overlay";
            frame.appendChild(overlayActions);
          }
          overlayActions.innerHTML = "";
          overlayActions.appendChild(actions);
        }
        placeholder.remove();
      }

      frame.classList.add("has-image");
      frame.setAttribute("data-cover-state", "image");

      const attributionWrap = document.getElementById("prs-cover-attribution-wrap");
      const attributionLink = document.getElementById("prs-cover-attribution");
      if (attributionWrap && attributionLink) {
        attributionLink.classList.add("is-hidden");
        attributionWrap.classList.add("is-hidden");
        attributionWrap.setAttribute("aria-hidden", "true");
        attributionLink.removeAttribute("href");
      }

      window.PRS_BOOK = window.PRS_BOOK || {};
      window.PRS_BOOK.cover_url = normalizedUrl || url;
    }

    function handleSearchClick(event) {
      if (event) {
        event.preventDefault();
      }
      if (!ajaxUrl || !nonce || !bookId) {
        renderMessage("Cover search is not available.", "prs-search-cover-error");
        setOverlayVisibility(true);
        return;
      }

      if (isSearching) {
        return;
      }

      isSearching = true;
      setOverlayVisibility(true);
      resetSelection();
      renderLoading();

      const payload = {
        action: "politeia_bookshelf_search_cover",
        nonce,
        book_id: String(bookId),
      };

      if (userBookId) {
        payload.user_book_id = String(userBookId);
      }
      if (userId) {
        payload.user_id = String(userId);
      }

      fetch(ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        },
        body: buildRequestBody(payload),
      })
        .then(response => response.json())
        .then(data => {
          if (!data || data.success !== true) {
            const message = data && data.data && data.data.message
              ? String(data.data.message)
              : "Unable to search for covers.";
            renderMessage(message, "prs-search-cover-error");
            return;
          }

          if (typeof window !== "undefined" && window.console) {
            console.log("[PRS] Google Books response:", data);
            if (data && data.data && data.data.items) {
              console.log("[PRS] Found items:", data.data.items.length);
            }
          }

          const items = data.data && Array.isArray(data.data.items)
            ? data.data.items
            : Array.isArray(data.items)
              ? data.items
              : [];
          renderResults(items);
        })
        .catch(() => {
          renderMessage("Unable to search for covers.", "prs-search-cover-error");
        })
        .finally(() => {
          isSearching = false;
        });
    }

    function handleSaveClick(event) {
      if (event) {
        event.preventDefault();
      }
      if (!currentSelection || !ajaxUrl || !nonce) {
        return;
      }
      if (isSaving) {
        return;
      }

      const coverUrl = currentSelection.dataset.coverUrl || "";
      if (!coverUrl) {
        return;
      }

      isSaving = true;
      if (setCoverBtn) {
        setCoverBtn.disabled = true;
      }

      const payload = {
        action: "politeia_bookshelf_save_cover",
        nonce,
        cover_url: coverUrl,
      };

      if (bookId) {
        payload.book_id = String(bookId);
      }
      if (userBookId) {
        payload.user_book_id = String(userBookId);
      }
      if (userId) {
        payload.user_id = String(userId);
      }

      fetch(ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        },
        body: buildRequestBody(payload),
      })
        .then(response => response.json())
        .then(data => {
          if (!data || data.success !== true) {
            const message = data && data.data && data.data.message
              ? String(data.data.message)
              : "Unable to save cover.";
            window.alert(message);
            if (setCoverBtn) {
              setCoverBtn.disabled = false;
            }
            return;
          }

          const savedUrl = data.data && data.data.cover_url ? String(data.data.cover_url) : coverUrl;
          applyCoverUpdate(savedUrl, currentSelection);
          resetSelection();
          setOverlayVisibility(false);
        })
        .catch(() => {
          window.alert("Unable to save cover.");
          if (setCoverBtn) {
            setCoverBtn.disabled = false;
          }
        })
        .finally(() => {
          isSaving = false;
        });
    }

    prepareExistingCoverFrame();

    if (searchBtn) {
      searchBtn.addEventListener("click", handleSearchClick);
    }

    if (cancelBtn) {
      cancelBtn.addEventListener("click", event => {
        if (event) {
          event.preventDefault();
        }
        setOverlayVisibility(false);
        resetSelection();
      });
    }

    if (setCoverBtn) {
      setCoverBtn.addEventListener("click", handleSaveClick);
      setCoverBtn.disabled = true;
    }

    if (optionsContainer) {
      optionsContainer.addEventListener("click", event => {
        const option = event.target.closest(".prs-cover-option");
        if (!option) {
          return;
        }
        event.preventDefault();
        selectOption(option);
      });

      optionsContainer.addEventListener("keydown", event => {
        if (event.key !== "Enter" && event.key !== " ") {
          return;
        }
        const option = event.target.closest(".prs-cover-option");
        if (!option) {
          return;
        }
        event.preventDefault();
        selectOption(option);
      });
    }

    if (overlay) {
      overlay.addEventListener("click", event => {
        if (event.target === overlay) {
          setOverlayVisibility(false);
          resetSelection();
        }
      });
    }
  }

  // ---------- Boot ----------
  document.addEventListener("DOMContentLoaded", function () {
    setupReadingDensityBar();
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
    setupSearchCoverOverlay();
  });
})();
