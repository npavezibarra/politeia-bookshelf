(function () {
        var modal = document.getElementById('prs-add-book-modal');
        var modalContent = modal ? modal.querySelector('.prs-add-book__modal-content') : null;
        var form = document.getElementById('prs-add-book-form');
        var submitButton = form ? form.querySelector('.prs-add-book__submit') : null;
        var formHeading = document.getElementById('prs-add-book-form-title');
        var successContainer = document.getElementById('prs-add-book-success');
        var successHeading = successContainer ? successContainer.querySelector('.prs-add-book__success-heading') : null;
        var successAction = successContainer ? successContainer.querySelector('.prs-add-book__success-action') : null;
        var closeButton = modal ? modal.querySelector('.prs-add-book__close') : null;
        var modeSwitch = document.getElementById('prs-add-book-mode-switch');
        var modeButtons = modeSwitch ? modeSwitch.querySelectorAll('.prs-add-book__mode-button') : null;
        var multipleContainer = document.getElementById('prs-add-book-multiple');
        var multipleHeading = multipleContainer ? multipleContainer.querySelector('.prs-add-book__heading') : null;
        var successActive = false;
        var currentMode = 'single';

        var updateAriaLabelledBy = function (id) {
                if (!modal) {
                        return;
                }
                if (id) {
                        modal.setAttribute('aria-labelledby', id);
                }
        };

        var clearSuccessParams = function () {
                if (typeof window === 'undefined' || !window.history || typeof window.URL !== 'function') {
                        return;
                }

                try {
                        var currentUrl = new window.URL(window.location.href);
                        var params = currentUrl.searchParams;
                        var removed = false;
                        var keys = ['prs_added', 'prs_added_title', 'prs_added_author', 'prs_added_year', 'prs_added_pages', 'prs_added_cover', 'prs_added_slug'];

                        for (var i = 0; i < keys.length; i++) {
                                if (params.has(keys[i])) {
                                        params.delete(keys[i]);
                                        removed = true;
                                }
                        }

                        if (removed) {
                                var newSearch = params.toString();
                                var newUrl = currentUrl.pathname + (newSearch ? '?' + newSearch : '') + currentUrl.hash;
                                window.history.replaceState({}, '', newUrl);
                        }
                } catch (error) {
                        // Fallback silently if URL API is unavailable.
                }
        };

        var updateModeButtons = function () {
                if (!modeButtons || !modeButtons.length) {
                        return;
                }

                for (var i = 0; i < modeButtons.length; i++) {
                        var button = modeButtons[i];
                        var buttonMode = button.getAttribute('data-mode') || 'single';
                        if (buttonMode === currentMode) {
                                button.classList.add('is-active');
                                button.setAttribute('aria-pressed', 'true');
                        } else {
                                button.classList.remove('is-active');
                                button.setAttribute('aria-pressed', 'false');
                        }
                }
        };

        var updateModeVisibility = function () {
                var pendingSuccess = successActive || (modal && modal.getAttribute('data-success') === '1');

                if (modalContent) {
                        if (pendingSuccess) {
                                modalContent.classList.remove('prs-add-book__modal-content--multiple');
                        } else {
                                modalContent.classList.toggle('prs-add-book__modal-content--multiple', currentMode === 'multiple');
                        }
                }

                if (modeSwitch) {
                        modeSwitch.hidden = pendingSuccess;
                }

                if (pendingSuccess) {
                        if (form) {
                                form.hidden = true;
                        }
                        if (formHeading) {
                                formHeading.hidden = true;
                        }
                        if (multipleContainer) {
                                multipleContainer.hidden = true;
                        }
                        if (multipleHeading) {
                                multipleHeading.hidden = true;
                        }
                        return;
                }

                if (currentMode === 'multiple' && multipleContainer) {
                        if (form) {
                                form.hidden = true;
                        }
                        if (formHeading) {
                                formHeading.hidden = true;
                        }
                        multipleContainer.hidden = false;
                        if (multipleHeading) {
                                multipleHeading.hidden = false;
                                if (multipleHeading.id) {
                                        updateAriaLabelledBy(multipleHeading.id);
                                }
                        }
                } else {
                        currentMode = 'single';
                        if (form) {
                                form.hidden = false;
                        }
                        if (formHeading) {
                                formHeading.hidden = false;
                                if (formHeading.id) {
                                        updateAriaLabelledBy(formHeading.id);
                                }
                        }
                        if (multipleContainer) {
                                multipleContainer.hidden = true;
                        }
                        if (multipleHeading) {
                                multipleHeading.hidden = true;
                        }
                }
        };

        var setMode = function (mode) {
                if (mode === 'multiple' && !multipleContainer) {
                        mode = 'single';
                } else if (mode !== 'multiple') {
                        mode = 'single';
                }

                if (mode === currentMode) {
                        updateModeButtons();
                        updateModeVisibility();
                        return;
                }

                currentMode = mode;
                updateModeButtons();
                updateModeVisibility();
        };

        if (modeButtons && modeButtons.length) {
                for (var j = 0; j < modeButtons.length; j++) {
                        modeButtons[j].addEventListener('click', function (event) {
                                if (event && typeof event.preventDefault === 'function') {
                                        event.preventDefault();
                                }
                                var buttonMode = event && event.currentTarget ? event.currentTarget.getAttribute('data-mode') : null;
                                setMode(buttonMode);
                        });
                }
        }

        updateModeButtons();
        updateModeVisibility();

        var resetToForm = function (force) {
                if (!successActive && !force) {
                        return;
                }

                successActive = false;

                if (successContainer) {
                        successContainer.hidden = true;
                }

                if (modalContent) {
                        modalContent.classList.remove('prs-add-book__modal-content--success');
                }

                setMode('single');
        };

        var activateSuccess = function () {
                if (!modal || !successContainer) {
                        return;
                }

                if (modal.getAttribute('data-success') !== '1') {
                        return;
                }

                successActive = true;
                successContainer.hidden = false;

                if (modalContent) {
                        modalContent.classList.add('prs-add-book__modal-content--success');
                }

                setMode('single');

                if (successHeading && successHeading.id) {
                        updateAriaLabelledBy(successHeading.id);
                }

                modal.style.display = 'flex';
                clearSuccessParams();
                modal.setAttribute('data-success', '0');
        };

        if (closeButton) {
                closeButton.addEventListener('click', resetToForm);
        }

        if (modal) {
                modal.addEventListener('click', function (event) {
                        if (event.target === modal) {
                                resetToForm();
                        }
                });
        }

        if (form && submitButton) {
                form.addEventListener('submit', function () {
                        submitButton.classList.add('is-loading');
                        submitButton.setAttribute('aria-busy', 'true');
                        submitButton.disabled = true;
                });
        }

        var openButtons = document.querySelectorAll('[aria-controls="prs-add-book-modal"]');
        if (openButtons && openButtons.length) {
                for (var i = 0; i < openButtons.length; i++) {
                        openButtons[i].addEventListener('click', function () {
                                resetToForm(true);
                        });
                }
        }

        activateSuccess();

        if (successAction) {
                var revealAction = function () {
                        successAction.classList.add('is-ready');
                };

                if (document.fonts && typeof document.fonts.load === 'function') {
                        Promise.race([
                                document.fonts.load('24px "Material Symbols Outlined"'),
                                new Promise(function (resolve) { setTimeout(resolve, 1200); })
                        ]).then(revealAction).catch(revealAction);
                } else {
                        setTimeout(revealAction, 300);
                }
        }

        var authorContainer = document.getElementById('prs_author_fields');
        var authorInputField = document.getElementById('prs_author_input');
        var authorList = document.getElementById('prs_author_list');
        var authorAddButton = document.getElementById('prs_author_add');
        var authorInputWrapper = authorContainer ? authorContainer.querySelector('.prs-add-book__author-input-wrapper') : null;
        var authorHiddenContainer = document.getElementById('prs_author_hidden');
        var authorHint = document.getElementById('prs_author_hint');
        var titleInput = document.getElementById('prs_title');
        var yearInput = document.getElementById('prs_year');
        var yearDisplay = document.getElementById('prs_year_display');
        var yearEditButton = document.getElementById('prs_year_edit');
        var isbnInput = document.getElementById('prs_isbn');
        var isbnDisplay = document.getElementById('prs_isbn_display');
        var isbnEditButton = document.getElementById('prs_isbn_edit');
        var pagesInput = document.getElementById('prs_pages');
        var pagesDisplay = document.getElementById('prs_pages_display');
        var pagesEditButton = document.getElementById('prs_pages_edit');
        var removeAuthorLabel = authorContainer ? authorContainer.getAttribute('data-remove-label') : '';
        var authorValues = [];
        var authorLookup = Object.create(null);
        var authorEditMode = false;
        var yearEditMode = false;
        var isbnEditMode = false;
        var pagesEditMode = false;

        var getFieldValue = function (input) {
                if (!input) {
                        return '';
                }
                return String(input.value || '').trim();
        };

        var updateEditFieldState = function (input, editButton, editMode, display) {
                if (!input) {
                        return;
                }
                var hasValue = getFieldValue(input) !== '';
                var hideInput = hasValue && !editMode;
                input.hidden = hideInput;
                if (display) {
                        display.textContent = getFieldValue(input);
                        display.hidden = !hasValue || editMode;
                }
                if (editButton) {
                        editButton.hidden = !(hasValue && !editMode);
                }
        };

        var normalizeAuthorValue = function (value) {
                if (value === null || typeof value === 'undefined') {
                        return '';
                }
                var str = String(value);
                str = str.replace(/\s+/g, ' ');
                return str.trim();
        };

        var getAuthorKey = function (value) {
                var normalized = normalizeAuthorValue(value);
                return normalized ? normalized.toLowerCase() : '';
        };

        var syncAuthorRequirement = function () {
                if (!authorInputField) {
                        return;
                }
                if (authorValues.length) {
                        authorInputField.required = false;
                        authorInputField.setAttribute('aria-required', 'false');
                } else {
                        authorInputField.required = true;
                        authorInputField.setAttribute('aria-required', 'true');
                }
        };

        var refreshAuthors = function () {
                if (authorList) {
                        authorList.innerHTML = '';
                        authorList.hidden = authorValues.length === 0;
                }

                if (authorHiddenContainer) {
                        authorHiddenContainer.innerHTML = '';
                }

                for (var i = 0; i < authorValues.length; i++) {
                        var value = authorValues[i];

                        if (authorList) {
                                var chip = document.createElement('span');
                                chip.className = 'prs-add-book__author-chip';
                                chip.setAttribute('role', 'listitem');

                                var label = document.createElement('span');
                                label.className = 'prs-add-book__author-chip-label';
                                label.textContent = value;
                                chip.appendChild(label);

                                var removeButton = document.createElement('button');
                                removeButton.type = 'button';
                                removeButton.className = 'prs-add-book__author-chip-remove';
                                removeButton.textContent = '×';
                                if (removeAuthorLabel) {
                                        removeButton.setAttribute('aria-label', removeAuthorLabel + ' ' + value);
                                } else {
                                        removeButton.setAttribute('aria-label', 'Remove ' + value);
                                }
                                removeButton.dataset.index = String(i);
                                removeButton.addEventListener('click', function (event) {
                                        if (event && typeof event.preventDefault === 'function') {
                                                event.preventDefault();
                                        }
                                        var target = event.currentTarget;
                                        var index = typeof target.dataset.index !== 'undefined' ? parseInt(target.dataset.index, 10) : -1;
                                        if (!isNaN(index)) {
                                                removeAuthorAt(index);
                                        }
                                });
                                chip.appendChild(removeButton);

                                authorList.appendChild(chip);
                        }

                        if (authorHiddenContainer) {
                                var hiddenInput = document.createElement('input');
                                hiddenInput.type = 'hidden';
                                hiddenInput.name = 'prs_author[]';
                                hiddenInput.value = value;
                                authorHiddenContainer.appendChild(hiddenInput);
                        }
                }

                if (authorAddButton) {
                        if (authorValues.length && !authorEditMode) {
                                authorAddButton.hidden = false;
                                if (authorList) {
                                        authorList.appendChild(authorAddButton);
                                }
                        } else {
                                authorAddButton.hidden = true;
                                if (authorAddButton.parentNode) {
                                        authorAddButton.parentNode.removeChild(authorAddButton);
                                }
                        }
                }

                var hideAuthorInput = authorValues.length > 0 && !authorEditMode;
                if (authorInputWrapper) {
                        authorInputWrapper.hidden = hideAuthorInput;
                }
                if (authorInputField) {
                        authorInputField.hidden = hideAuthorInput;
                }
                if (authorHint) {
                        authorHint.hidden = hideAuthorInput;
                }

                syncAuthorRequirement();
        };

        var addAuthorValue = function (value) {
                var normalized = normalizeAuthorValue(value);
                if (!normalized) {
                        return false;
                }
                var key = getAuthorKey(normalized);
                if (!key || authorLookup[key]) {
                        return false;
                }
                authorLookup[key] = true;
                authorValues.push(normalized);
                return true;
        };

        var removeAuthorAt = function (index) {
                if (index < 0 || index >= authorValues.length) {
                        return;
                }

                var value = authorValues[index];
                authorValues.splice(index, 1);

                var key = getAuthorKey(value);
                if (key && authorLookup[key]) {
                        delete authorLookup[key];
                }

                refreshAuthors();

                if (authorInputField) {
                        authorInputField.focus();
                }
        };

        var processAuthorInputValue = function (value, commitRemainder) {
                if (!authorInputField) {
                        return;
                }

                var str = typeof value === 'string' ? value : '';
                if (!str) {
                        if (commitRemainder) {
                                authorInputField.value = '';
                        }
                        refreshAuthors();
                        return;
                }

                var segments = str.split(',');
                if (!segments.length) {
                        refreshAuthors();
                        return;
                }

                var remainder = segments.pop();

                for (var i = 0; i < segments.length; i++) {
                        addAuthorValue(segments[i]);
                }

                if (commitRemainder) {
                        addAuthorValue(remainder);
                        remainder = '';
                        if (authorValues.length) {
                                authorEditMode = false;
                        }
                }

                authorInputField.value = remainder ? remainder.replace(/^\s+/, '') : '';

                refreshAuthors();
        };

        var getPrimaryAuthorInput = function () {
                return authorInputField || document.getElementById('prs_author');
        };

        var setAuthors = function (authors) {
                if (!authorContainer || !authorInputField) {
                        var legacyInput = document.getElementById('prs_author');
                        if (legacyInput) {
                                if (Array.isArray(authors) && authors.length) {
                                        legacyInput.value = String(authors[0]);
                                } else if (typeof authors === 'string') {
                                        legacyInput.value = authors;
                                } else {
                                        legacyInput.value = '';
                                }
                        }
                        return;
                }

                authorValues = [];
                authorLookup = Object.create(null);

                if (Array.isArray(authors)) {
                        for (var i = 0; i < authors.length; i++) {
                                addAuthorValue(authors[i]);
                        }
                } else if (typeof authors === 'string') {
                        addAuthorValue(authors);
                }

                if (authorInputField) {
                        authorInputField.value = '';
                }

                authorEditMode = false;
                refreshAuthors();
        };

        if (authorAddButton && authorInputField) {
                authorAddButton.addEventListener('click', function (event) {
                        if (event && typeof event.preventDefault === 'function') {
                                event.preventDefault();
                        }
                        authorEditMode = true;
                        if (authorInputWrapper) {
                                authorInputWrapper.hidden = false;
                        }
                        authorInputField.hidden = false;
                        if (authorHint) {
                                authorHint.hidden = false;
                        }
                        if (authorValues.length) {
                                authorInputField.value = authorValues.join(', ');
                        }
                        authorInputField.focus();
                });
        }

        if (authorInputField) {
                authorInputField.addEventListener('input', function (event) {
                        processAuthorInputValue(event.target.value, false);
                });

                authorInputField.addEventListener('keydown', function (event) {
                        if (event.key === 'Enter') {
                                if (event && typeof event.preventDefault === 'function') {
                                        event.preventDefault();
                                }
                                processAuthorInputValue(authorInputField.value, true);
                        } else if (event.key === 'Backspace' && !authorInputField.value && authorValues.length) {
                                if (event && typeof event.preventDefault === 'function') {
                                        event.preventDefault();
                                }
                                removeAuthorAt(authorValues.length - 1);
                        }
                });

                authorInputField.addEventListener('blur', function () {
                        processAuthorInputValue(authorInputField.value, true);
                });
        }

        if (form && authorInputField) {
                form.addEventListener('submit', function () {
                        processAuthorInputValue(authorInputField.value, true);
                });
        }

        if (authorContainer && authorInputField) {
                refreshAuthors();
        }

        if (yearInput) {
                updateEditFieldState(yearInput, yearEditButton, yearEditMode, yearDisplay);
                yearInput.addEventListener('input', function () {
                        if (!getFieldValue(yearInput)) {
                                yearEditMode = true;
                                updateEditFieldState(yearInput, yearEditButton, yearEditMode, yearDisplay);
                        }
                });
                yearInput.addEventListener('blur', function () {
                        if (getFieldValue(yearInput)) {
                                yearEditMode = false;
                                updateEditFieldState(yearInput, yearEditButton, yearEditMode, yearDisplay);
                        }
                });
        }

        if (yearEditButton && yearInput) {
                yearEditButton.addEventListener('click', function (event) {
                        if (event && typeof event.preventDefault === 'function') {
                                event.preventDefault();
                        }
                        yearEditMode = true;
                        updateEditFieldState(yearInput, yearEditButton, yearEditMode, yearDisplay);
                        yearInput.focus();
                        if (typeof yearInput.select === 'function') {
                                yearInput.select();
                        }
                });
        }

        if (isbnInput) {
                updateEditFieldState(isbnInput, isbnEditButton, isbnEditMode, isbnDisplay);
                isbnInput.addEventListener('input', function () {
                        if (!getFieldValue(isbnInput)) {
                                isbnEditMode = true;
                                updateEditFieldState(isbnInput, isbnEditButton, isbnEditMode, isbnDisplay);
                        }
                });
                isbnInput.addEventListener('blur', function () {
                        if (getFieldValue(isbnInput)) {
                                isbnEditMode = false;
                                updateEditFieldState(isbnInput, isbnEditButton, isbnEditMode, isbnDisplay);
                        }
                });
        }

        if (isbnEditButton && isbnInput) {
                isbnEditButton.addEventListener('click', function (event) {
                        if (event && typeof event.preventDefault === 'function') {
                                event.preventDefault();
                        }
                        isbnEditMode = true;
                        updateEditFieldState(isbnInput, isbnEditButton, isbnEditMode, isbnDisplay);
                        isbnInput.focus();
                        if (typeof isbnInput.select === 'function') {
                                isbnInput.select();
                        }
                });
        }

        if (pagesInput) {
                updateEditFieldState(pagesInput, pagesEditButton, pagesEditMode, pagesDisplay);
                pagesInput.addEventListener('input', function () {
                        if (!getFieldValue(pagesInput)) {
                                pagesEditMode = true;
                                updateEditFieldState(pagesInput, pagesEditButton, pagesEditMode, pagesDisplay);
                        }
                });
                pagesInput.addEventListener('blur', function () {
                        if (getFieldValue(pagesInput)) {
                                pagesEditMode = false;
                                updateEditFieldState(pagesInput, pagesEditButton, pagesEditMode, pagesDisplay);
                        }
                });
        }

        if (pagesEditButton && pagesInput) {
                pagesEditButton.addEventListener('click', function (event) {
                        if (event && typeof event.preventDefault === 'function') {
                                event.preventDefault();
                        }
                        pagesEditMode = true;
                        updateEditFieldState(pagesInput, pagesEditButton, pagesEditMode, pagesDisplay);
                        pagesInput.focus();
                        if (typeof pagesInput.select === 'function') {
                                pagesInput.select();
                        }
                });
        }

        if (!titleInput) {
                return;
        }

        var authorInput = getPrimaryAuthorInput();
        var suggestionContainer = document.getElementById('prs_title_suggestions');
        var isbnSuggestionContainer = document.getElementById('prs_isbn_suggestions');
        var autocompleteConfig = typeof window !== 'undefined' ? window.PRS_ADD_BOOK_AUTOCOMPLETE : null;
        var canonicalEndpoint = autocompleteConfig && autocompleteConfig.ajax_url ? autocompleteConfig.ajax_url : '';
        var canonicalNonce = autocompleteConfig && autocompleteConfig.nonce ? autocompleteConfig.nonce : '';
        var coverPreviewWrapper = document.getElementById('prs_cover_preview');
        var coverPreviewImage = coverPreviewWrapper ? coverPreviewWrapper.querySelector('img') : null;
        var coverPlaceholder = coverPreviewImage ? coverPreviewImage.getAttribute('data-placeholder-src') : '';
        var coverUrlInput = document.getElementById('prs_cover_url');
        var coverFileInput = document.getElementById('prs_cover');
        var duplicateNotice = document.getElementById('prs_add_book_duplicate');
        var addBookForm = document.getElementById('prs-add-book-form');
        var submitButton = addBookForm ? addBookForm.querySelector('button[type="submit"]') : null;

        if (!suggestionContainer) {
                suggestionContainer = document.createElement('div');
                suggestionContainer.id = 'prs_title_suggestions';
                suggestionContainer.className = 'prs-add-book__suggestions';
                if (titleInput.parentNode) {
                        titleInput.parentNode.appendChild(suggestionContainer);
                }
        }

        titleInput.setAttribute('role', 'combobox');
        titleInput.setAttribute('aria-autocomplete', 'list');
        titleInput.setAttribute('autocomplete', 'off');
        if (suggestionContainer && !titleInput.getAttribute('aria-controls')) {
                titleInput.setAttribute('aria-controls', suggestionContainer.id);
        }
        titleInput.setAttribute('aria-expanded', 'false');

        var supportsAbortController = typeof window.AbortController === 'function';
        var abortController = null;
        var debounceTimer = null;
        var lastFetchedQuery = '';
        var isbnAbortController = null;
        var isbnDebounceTimer = null;
        var lastFetchedIsbn = '';
        var lastSuggestionItems = [];
        var lastSelectionToken = 0;
        var duplicateAbortController = null;
        var duplicateIdentityAbortController = null;

        var resetSuggestions = function () {
                if (!suggestionContainer) {
                        return;
                }
                suggestionContainer.innerHTML = '';
                suggestionContainer.classList.remove('is-visible');
                suggestionContainer.setAttribute('aria-hidden', 'true');
                titleInput.setAttribute('aria-expanded', 'false');
        };

        var cancelPendingRequest = function () {
                if (supportsAbortController && abortController) {
                        abortController.abort();
                        abortController = null;
                }
        };

        var clearSuggestions = function () {
                cancelPendingRequest();
                lastFetchedQuery = '';
                resetSuggestions();
        };

        var normalizeForComparison = function (value) {
                if (!value) {
                        return '';
                }
                var str = String(value).toLowerCase();
                if (typeof String.prototype.normalize === 'function') {
                        str = String.prototype.normalize.call(str, 'NFD');
                }
                str = str.replace(/[\u0300-\u036f]/g, '');
                str = str.replace(/[^a-z0-9]+/g, '');
                return str;
        };

        var normalizeIsbnInput = function (value) {
                if (!value) {
                        return '';
                }
                return String(value).replace(/[^0-9Xx]/g, '').toUpperCase();
        };

        var setDuplicateState = function (isDuplicate, message) {
                if (duplicateNotice) {
                        var defaultMessage = duplicateNotice.getAttribute('data-default-message') || '';
                        duplicateNotice.textContent = message || defaultMessage;
                        duplicateNotice.hidden = !isDuplicate;
                }
                if (submitButton) {
                        submitButton.disabled = !!isDuplicate;
                }
        };

        var clearDuplicateState = function () {
                setDuplicateState(false, '');
        };

        if (duplicateNotice && !duplicateNotice.hidden) {
                setDuplicateState(true, duplicateNotice.textContent || '');
        }


        var resetIsbnSuggestions = function () {
                if (!isbnSuggestionContainer || !isbnInput) {
                        return;
                }
                isbnSuggestionContainer.innerHTML = '';
                isbnSuggestionContainer.classList.remove('is-visible');
                isbnSuggestionContainer.setAttribute('aria-hidden', 'true');
                isbnInput.setAttribute('aria-expanded', 'false');
        };

        var cancelIsbnRequest = function () {
                if (supportsAbortController && isbnAbortController) {
                        isbnAbortController.abort();
                        isbnAbortController = null;
                }
        };

        var clearIsbnSuggestions = function () {
                cancelIsbnRequest();
                lastFetchedIsbn = '';
                resetIsbnSuggestions();
        };

        var focusIsbnSuggestionAtIndex = function (index) {
                if (!isbnSuggestionContainer) {
                        return;
                }
                var buttons = isbnSuggestionContainer.querySelectorAll('.prs-add-book__suggestion');
                if (!buttons.length) {
                        return;
                }
                if (index < 0) {
                        index = 0;
                }
                if (index >= buttons.length) {
                        index = buttons.length - 1;
                }
                buttons[index].focus();
        };

        var handleIsbnSuggestionKeydown = function (event) {
                var target = event.currentTarget;
                var index = parseInt(target.getAttribute('data-index'), 10);
                if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        focusIsbnSuggestionAtIndex(index + 1);
                } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        if (index <= 0) {
                                isbnInput.focus();
                        } else {
                                focusIsbnSuggestionAtIndex(index - 1);
                        }
                } else if (event.key === 'Escape') {
                        event.preventDefault();
                        resetIsbnSuggestions();
                        isbnInput.focus();
                } else if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        handleIsbnSuggestionSelection(target);
                }
        };

        var showIsbnSuggestions = function (items) {
                if (!isbnSuggestionContainer || !isbnInput) {
                        return;
                }

                isbnSuggestionContainer.innerHTML = '';

                if (!items || !items.length) {
                        resetIsbnSuggestions();
                        return;
                }

                for (var i = 0; i < items.length; i++) {
                        var item = items[i];
                        var button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'prs-add-book__suggestion';
                        button.setAttribute('data-index', i);
                        button.setAttribute('role', 'option');
                        button.dataset.title = item.title || '';
                        button.dataset.author = item.author || '';
                        button.dataset.year = item.year || '';
                        if (item.authors && item.authors.length) {
                                try {
                                        button.dataset.authors = JSON.stringify(item.authors);
                                } catch (error) {
                                        button.dataset.authors = '';
                                }
                        }

                        var titleLine = document.createElement('span');
                        titleLine.className = 'prs-add-book__suggestion-title';
                        titleLine.textContent = item.title || '';
                        button.appendChild(titleLine);

                        var authorsLabel = item.authors && item.authors.length ? item.authors.join(', ') : (item.author || '');
                        if (authorsLabel) {
                                var authorLine = document.createElement('span');
                                authorLine.className = 'prs-add-book__suggestion-author';
                                authorLine.textContent = authorsLabel;
                                button.appendChild(authorLine);
                        }

                        if (item.year) {
                                var yearLine = document.createElement('span');
                                yearLine.className = 'prs-add-book__suggestion-year';
                                yearLine.textContent = item.year;
                                button.appendChild(yearLine);
                        }

                        button.addEventListener('keydown', handleIsbnSuggestionKeydown);
                        button.addEventListener('mousedown', function (event) {
                                if (event && typeof event.preventDefault === 'function') {
                                        event.preventDefault();
                                }
                                handleIsbnSuggestionSelection(event.currentTarget);
                        });
                        button.addEventListener('click', function (event) {
                                if (event && typeof event.preventDefault === 'function') {
                                        event.preventDefault();
                                }
                                handleIsbnSuggestionSelection(event.currentTarget);
                        });
                        try {
                                button.dataset.payload = JSON.stringify(item || {});
                        } catch (error) {
                                button.dataset.payload = '{}';
                        }
                        isbnSuggestionContainer.appendChild(button);
                }

                isbnSuggestionContainer.classList.add('is-visible');
                isbnSuggestionContainer.setAttribute('aria-hidden', 'false');
                isbnInput.setAttribute('aria-expanded', 'true');
        };

        var handleIsbnSuggestionSelection = function (target) {
                if (!target) {
                        return;
                }

                var suggestion = {};
                if (target.dataset.payload) {
                        try {
                                suggestion = JSON.parse(target.dataset.payload);
                        } catch (error) {
                                suggestion = {};
                        }
                }

                var resolvedAuthors = [];
                if (suggestion.authors && suggestion.authors.length) {
                        resolvedAuthors = suggestion.authors;
                } else if (target.dataset.authors) {
                        try {
                                var parsedAuthors = JSON.parse(target.dataset.authors);
                                if (Array.isArray(parsedAuthors)) {
                                        resolvedAuthors = parsedAuthors;
                                }
                        } catch (error) {
                                resolvedAuthors = [];
                        }
                } else if (target.dataset.author) {
                        resolvedAuthors = [target.dataset.author];
                }

                var resolved = {
                        title: suggestion.title || target.dataset.title || '',
                        author: suggestion.author || target.dataset.author || '',
                        authors: resolvedAuthors,
                        year: suggestion.year || target.dataset.year || '',
                        isbn: suggestion.isbn || (isbnInput ? isbnInput.value : ''),
                        pages: suggestion.pages || '',
                        cover: suggestion.cover || '',
                        selfLink: suggestion.selfLink || ''
                };

                if (isbnInput && resolved.isbn) {
                        isbnInput.value = resolved.isbn;
                        isbnEditMode = false;
                        updateEditFieldState(isbnInput, isbnEditButton, isbnEditMode, isbnDisplay);
                }

                applySuggestionValues(resolved);
                checkUserBookIdentity({
                        title: resolved.title || '',
                        authors: resolved.authors || [],
                        year: resolved.year || '',
                        isbn: resolved.isbn || ''
                });

                if (!getFieldValue(isbnInput) || !getFieldValue(pagesInput)) {
                        fetchGoogleDetailsForSelection(resolved).then(function (details) {
                                if (!details) {
                                        return;
                                }
                                if (isbnInput && details.isbn && !getFieldValue(isbnInput)) {
                                        isbnInput.value = details.isbn;
                                }
                                if (pagesInput && details.pages && !getFieldValue(pagesInput)) {
                                        pagesInput.value = details.pages;
                                }
                                if (details.cover) {
                                        setCoverPreview(details.cover);
                                }
                                updateEditFieldState(isbnInput, isbnEditButton, isbnEditMode, isbnDisplay);
                                updateEditFieldState(pagesInput, pagesEditButton, pagesEditMode, pagesDisplay);
                        });
                }

                resetIsbnSuggestions();
        };

        var setCoverPreview = function (url) {
                if (!coverPreviewWrapper || !coverPreviewImage) {
                        return;
                }
                if (coverUrlInput) {
                        coverUrlInput.value = url || '';
                }
                if (!url) {
                        coverPreviewWrapper.hidden = true;
                        if (coverPlaceholder) {
                                coverPreviewImage.src = coverPlaceholder;
                        } else {
                                coverPreviewImage.removeAttribute('src');
                        }
                        return;
                }
                coverPreviewImage.src = url;
                coverPreviewWrapper.hidden = false;
        };

        var checkUserBookStatus = function (item) {
                if (!item || item.source !== 'canonical' || !item.id) {
                        clearDuplicateState();
                        return;
                }
                if (!canonicalEndpoint || !canonicalNonce) {
                        return;
                }

                if (duplicateAbortController && typeof duplicateAbortController.abort === 'function') {
                        duplicateAbortController.abort();
                }
                duplicateAbortController = supportsAbortController ? new AbortController() : null;

                var params = new window.URLSearchParams();
                params.append('action', 'prs_check_user_book');
                params.append('nonce', canonicalNonce);
                params.append('book_id', item.id);

                var fetchOptions = {
                        method: 'POST',
                        headers: {
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: params.toString()
                };
                if (duplicateAbortController) {
                        fetchOptions.signal = duplicateAbortController.signal;
                }

                fetch(canonicalEndpoint, fetchOptions)
                        .then(function (response) {
                                if (!response.ok) {
                                        throw new Error('Request failed');
                                }
                                return response.json();
                        })
                        .then(function (data) {
                                var payload = data && data.data ? data.data : data;
                                if (!payload) {
                                        clearDuplicateState();
                                        return;
                                }
                                if (payload.exists && payload.allowed === false) {
                                        setDuplicateState(true, payload.message || '');
                                        return;
                                }
                                clearDuplicateState();
                        })
                        .catch(function (error) {
                                if (error && error.name === 'AbortError') {
                                        return;
                                }
                                clearDuplicateState();
                        });
        };

        var checkUserBookIdentity = function (details) {
                if (!details || !details.title || !details.authors || !details.authors.length) {
                        clearDuplicateState();
                        return;
                }
                if (!canonicalEndpoint || !canonicalNonce) {
                        return;
                }

                if (duplicateIdentityAbortController && typeof duplicateIdentityAbortController.abort === 'function') {
                        duplicateIdentityAbortController.abort();
                }
                duplicateIdentityAbortController = supportsAbortController ? new AbortController() : null;

                var params = new window.URLSearchParams();
                params.append('action', 'prs_check_user_book_identity');
                params.append('nonce', canonicalNonce);
                params.append('title', details.title || '');
                params.append('year', details.year || '');
                params.append('isbn', details.isbn || '');
                for (var i = 0; i < details.authors.length; i++) {
                        params.append('authors[]', details.authors[i]);
                }

                var fetchOptions = {
                        method: 'POST',
                        headers: {
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: params.toString()
                };
                if (duplicateIdentityAbortController) {
                        fetchOptions.signal = duplicateIdentityAbortController.signal;
                }

                fetch(canonicalEndpoint, fetchOptions)
                        .then(function (response) {
                                if (!response.ok) {
                                        throw new Error('Request failed');
                                }
                                return response.json();
                        })
                        .then(function (data) {
                                var payload = data && data.data ? data.data : data;
                                if (!payload) {
                                        clearDuplicateState();
                                        return;
                                }
                                if (payload.exists && payload.allowed === false) {
                                        setDuplicateState(true, payload.message || '');
                                        return;
                                }
                                clearDuplicateState();
                        })
                        .catch(function (error) {
                                if (error && error.name === 'AbortError') {
                                        return;
                                }
                                clearDuplicateState();
                        });
        };

        var findSupplementalDetails = function (item) {
                if (!item || !lastSuggestionItems.length) {
                        return null;
                }

                var titleKey = normalizeForComparison(item.title);
                var authorKey = '';
                if (item.authors && item.authors.length) {
                        authorKey = normalizeForComparison(item.authors[0]);
                } else if (item.author) {
                        authorKey = normalizeForComparison(item.author);
                }

                if (!titleKey) {
                        return null;
                }

                for (var i = 0; i < lastSuggestionItems.length; i++) {
                        var candidate = lastSuggestionItems[i];
                        if (!candidate || candidate.source === 'canonical') {
                                continue;
                        }
                        if (titleKey !== normalizeForComparison(candidate.title)) {
                                continue;
                        }
                        if (authorKey) {
                                var candidateAuthor = '';
                                if (candidate.authors && candidate.authors.length) {
                                        candidateAuthor = normalizeForComparison(candidate.authors[0]);
                                } else if (candidate.author) {
                                        candidateAuthor = normalizeForComparison(candidate.author);
                                }
                                if (candidateAuthor && authorKey !== candidateAuthor) {
                                        continue;
                                }
                        }
                        if (candidate.isbn || candidate.pages) {
                                return candidate;
                        }
                }

                return null;
        };

        var isStaleQuery = function (requestedQuery) {
                if (!titleInput || !titleInput.value) {
                        return true;
                }
                return titleInput.value.trim().toLowerCase() !== requestedQuery.toLowerCase();
        };

        var focusSuggestionAtIndex = function (index) {
                if (!suggestionContainer) {
                        return;
                }
                var buttons = suggestionContainer.querySelectorAll('.prs-add-book__suggestion');
                if (!buttons.length) {
                        return;
                }
                if (index < 0) {
                        index = 0;
                }
                if (index >= buttons.length) {
                        index = buttons.length - 1;
                }
                buttons[index].focus();
        };

        var applySuggestionValues = function (item) {
                if (!item) {
                        return;
                }

                if (titleInput && item.title) {
                        titleInput.value = item.title;
                }
                if (item.authors && item.authors.length) {
                        setAuthors(item.authors);
                        authorInput = getPrimaryAuthorInput();
                } else if (item.author) {
                        setAuthors([item.author]);
                        authorInput = getPrimaryAuthorInput();
                }
                if (yearInput) {
                        yearInput.value = item.year || '';
                }

                var supplemental = null;
                if ((!item.isbn && !item.pages) && lastSuggestionItems.length) {
                        supplemental = findSupplementalDetails(item);
                }
                if (isbnInput) {
                        isbnInput.value = item.isbn || (supplemental && supplemental.isbn ? supplemental.isbn : '') || '';
                }
                if (pagesInput) {
                        pagesInput.value = item.pages || (supplemental && supplemental.pages ? supplemental.pages : '') || '';
                }

                yearEditMode = false;
                isbnEditMode = false;
                pagesEditMode = false;
                updateEditFieldState(yearInput, yearEditButton, yearEditMode, yearDisplay);
                updateEditFieldState(isbnInput, isbnEditButton, isbnEditMode, isbnDisplay);
                updateEditFieldState(pagesInput, pagesEditButton, pagesEditMode, pagesDisplay);

                if (item.cover) {
                        setCoverPreview(item.cover);
                }
        };

        var selectSuggestion = function (item) {
                if (!item) {
                        return;
                }

                lastSelectionToken += 1;
                var selectionToken = lastSelectionToken;

                applySuggestionValues(item);
                checkUserBookStatus(item);
                if (item.source !== 'canonical') {
                        var itemAuthors = [];
                        if (item.authors && item.authors.length) {
                                itemAuthors = item.authors;
                        } else if (item.author) {
                                itemAuthors = [item.author];
                        }
                        checkUserBookIdentity({
                                title: item.title || '',
                                authors: itemAuthors,
                                year: item.year || '',
                                isbn: item.isbn || ''
                        });
                }

                if (!getFieldValue(isbnInput) || !getFieldValue(pagesInput)) {
                        fetchGoogleDetailsForSelection(item).then(function (details) {
                                if (!details || selectionToken !== lastSelectionToken) {
                                        return;
                                }
                                if (isbnInput && details.isbn && !getFieldValue(isbnInput)) {
                                        isbnInput.value = details.isbn;
                                }
                                if (pagesInput && details.pages && !getFieldValue(pagesInput)) {
                                        pagesInput.value = details.pages;
                                }
                                if (details.cover) {
                                        setCoverPreview(details.cover);
                                }
                                updateEditFieldState(isbnInput, isbnEditButton, isbnEditMode, isbnDisplay);
                                updateEditFieldState(pagesInput, pagesEditButton, pagesEditMode, pagesDisplay);
                        });
                }
                resetSuggestions();
                if (titleInput) {
                        titleInput.focus();
                }
        };

        if (coverFileInput && coverUrlInput) {
                coverFileInput.addEventListener('change', function () {
                        if (coverFileInput.files && coverFileInput.files.length) {
                                coverUrlInput.value = '';
                        }
                });
        }

        var handleSuggestionKeydown = function (event) {
                var target = event.currentTarget;
                var index = parseInt(target.getAttribute('data-index'), 10);
                if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        focusSuggestionAtIndex(index + 1);
                } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        if (index <= 0) {
                                titleInput.focus();
                        } else {
                                focusSuggestionAtIndex(index - 1);
                        }
                } else if (event.key === 'Escape') {
                        event.preventDefault();
                        resetSuggestions();
                        titleInput.focus();
                }
        };

        var showSuggestions = function (items) {
                if (!suggestionContainer) {
                        return;
                }

                suggestionContainer.innerHTML = '';

                if (!items || !items.length) {
                        resetSuggestions();
                        return;
                }

                for (var i = 0; i < items.length; i++) {
                        var item = items[i];
                        var button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'prs-add-book__suggestion';
                        button.setAttribute('data-index', i);
                        button.setAttribute('role', 'option');
                        button.dataset.title = item.title || '';
                        button.dataset.author = item.author || '';
                        if (item.authors && item.authors.length) {
                                try {
                                        button.dataset.authors = JSON.stringify(item.authors);
                                } catch (error) {
                                        button.dataset.authors = '';
                                }
                        }
                        button.dataset.year = item.year || '';
                        if (item.source) {
                                button.classList.add('prs-add-book__suggestion--' + item.source);
                        }

                        var titleLine = document.createElement('span');
                        titleLine.className = 'prs-add-book__suggestion-title';
                        titleLine.textContent = item.title || '';
                        button.appendChild(titleLine);

                        var authorsLabel = item.authors && item.authors.length ? item.authors.join(', ') : (item.author || '');
                        if (authorsLabel) {
                                var authorLine = document.createElement('span');
                                authorLine.className = 'prs-add-book__suggestion-author';
                                authorLine.textContent = authorsLabel;
                                button.appendChild(authorLine);
                        }

                        if (item.year) {
                                var yearLine = document.createElement('span');
                                yearLine.className = 'prs-add-book__suggestion-year';
                                yearLine.textContent = item.year;
                                button.appendChild(yearLine);
                        }
                        button.addEventListener('keydown', handleSuggestionKeydown);
                        button.addEventListener('click', (function (suggestion) {
                                return function (clickEvent) {
                                        clickEvent.preventDefault();
                                        selectSuggestion(suggestion);
                                };
                        })(item));
                        suggestionContainer.appendChild(button);
                }

                suggestionContainer.classList.add('is-visible');
                suggestionContainer.setAttribute('aria-hidden', 'false');
                titleInput.setAttribute('aria-expanded', 'true');
        };

        var parseYear = function (publishedDate) {
                if (!publishedDate) {
                        return '';
                }

                var match = String(publishedDate).match(/\d{4}/);
                return match ? match[0] : '';
        };

        var fetchCanonicalSuggestions = function (query, controller) {
                if (!canonicalEndpoint || !canonicalNonce) {
                        return Promise.resolve([]);
                }

                var params = new window.URLSearchParams();
                params.append('action', 'prs_canonical_title_search');
                params.append('nonce', canonicalNonce);
                params.append('query', query);

                var fetchOptions = {
                        method: 'POST',
                        headers: {
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: params.toString()
                };
                if (controller) {
                        fetchOptions.signal = controller.signal;
                }

                return fetch(canonicalEndpoint, fetchOptions)
                        .then(function (response) {
                                if (!response.ok) {
                                        throw new Error('Request failed');
                                }
                                return response.json();
                        })
                        .then(function (data) {
                                var payload = data && data.data ? data.data : data;
                                if (!payload || !payload.items || !payload.items.length) {
                                        return [];
                                }

                                var items = [];
                                for (var i = 0; i < payload.items.length; i++) {
                                        var item = payload.items[i];
                                        if (!item || !item.title) {
                                                continue;
                                        }
                                        var authors = Array.isArray(item.authors) ? item.authors : [];
                                        items.push({
                                                id: item.id,
                                                title: item.title,
                                                year: item.year || '',
                                                slug: item.slug || '',
                                                author: authors.length ? authors[0] : '',
                                                authors: authors,
                                                source: 'canonical'
                                        });
                                }
                                return items;
                        })
                        .catch(function (error) {
                                if (error && error.name === 'AbortError') {
                                        throw error;
                                }
                                return [];
                        });
        };

        var fetchGoogleSuggestions = function (query, controller) {
                var baseUrl = 'https://www.googleapis.com/books/v1/volumes';
                var params = [
                        'q=' + encodeURIComponent('intitle:' + query),
                        'maxResults=6',
                        'printType=books',
                        'orderBy=relevance',
                        'fields=items(id,selfLink,volumeInfo/title,volumeInfo/authors,volumeInfo/publishedDate,volumeInfo/industryIdentifiers,volumeInfo/pageCount,volumeInfo/imageLinks)'
                ];
                var url = baseUrl + '?' + params.join('&');
                var fetchOptions = {};
                if (controller) {
                        fetchOptions.signal = controller.signal;
                }

                return fetch(url, fetchOptions)
                        .then(function (response) {
                                if (!response.ok) {
                                        throw new Error('Request failed');
                                }
                                return response.json();
                        })
                        .then(function (data) {
                                if (!data || !data.items || !data.items.length) {
                                        return [];
                                }

                                var docs = data.items;
                                var items = [];
                                var seen = Object.create(null);

                                for (var i = 0; i < docs.length; i++) {
                                        var doc = docs[i];
                                        if (!doc || !doc.volumeInfo) {
                                                continue;
                                        }

                                        var volumeInfo = doc.volumeInfo;
                                        var title = volumeInfo.title ? String(volumeInfo.title).trim() : '';
                                        if (!title) {
                                                continue;
                                        }

                                        var authors = [];
                                        if (volumeInfo.authors && volumeInfo.authors.length) {
                                                for (var authorIndex = 0; authorIndex < volumeInfo.authors.length; authorIndex++) {
                                                        var candidate = String(volumeInfo.authors[authorIndex]).trim();
                                                        if (!candidate) {
                                                                continue;
                                                        }
                                                        if (authors.indexOf(candidate) === -1) {
                                                                authors.push(candidate);
                                                        }
                                                }
                                        }

                                        var author = authors.length ? authors[0] : '';

                                        var year = '';
                                        if (volumeInfo.publishedDate) {
                                                year = parseYear(volumeInfo.publishedDate);
                                        }

                                        var isbn = '';
                                        if (volumeInfo.industryIdentifiers && volumeInfo.industryIdentifiers.length) {
                                                var identifiers = volumeInfo.industryIdentifiers;
                                                for (var idIndex = 0; idIndex < identifiers.length; idIndex++) {
                                                        var identifier = identifiers[idIndex] || {};
                                                        if (identifier.type === 'ISBN_13' && identifier.identifier) {
                                                                isbn = String(identifier.identifier);
                                                                break;
                                                        }
                                                }
                                                if (!isbn) {
                                                        for (var fallbackIndex = 0; fallbackIndex < identifiers.length; fallbackIndex++) {
                                                                var fallback = identifiers[fallbackIndex] || {};
                                                                if (fallback.type === 'ISBN_10' && fallback.identifier) {
                                                                        isbn = String(fallback.identifier);
                                                                        break;
                                                                }
                                                        }
                                                }
                                                if (!isbn) {
                                                        var fallbackIdentifier = identifiers[0];
                                                        if (fallbackIdentifier && fallbackIdentifier.identifier) {
                                                                isbn = String(fallbackIdentifier.identifier);
                                                        }
                                                }
                                        }

                                        var pages = '';
                                        if (typeof volumeInfo.pageCount !== 'undefined' && volumeInfo.pageCount !== null) {
                                                var pageValue = parseInt(volumeInfo.pageCount, 10);
                                                if (!isNaN(pageValue) && pageValue > 0) {
                                                        pages = pageValue;
                                                }
                                        }
                                        var cover = '';
                                        if (volumeInfo.imageLinks) {
                                                cover = volumeInfo.imageLinks.thumbnail || volumeInfo.imageLinks.smallThumbnail || '';
                                        }

                                        if (!author || !year) {
                                                continue;
                                        }

                                        var key = normalizeForComparison(title) + '|' + normalizeForComparison(authors.join('|'));
                                        if (seen[key]) {
                                                continue;
                                        }
                                        seen[key] = true;

                                        items.push({
                                                id: doc.id || '',
                                                selfLink: doc.selfLink || '',
                                                title: title,
                                                author: author,
                                                authors: authors,
                                                year: year,
                                                isbn: isbn,
                                                pages: pages,
                                                cover: cover,
                                                source: 'googlebooks'
                                        });

                                        if (items.length >= 6) {
                                                break;
                                        }
                                }

                                return items;
                        })
                        .catch(function (error) {
                                if (error && error.name === 'AbortError') {
                                        throw error;
                                }
                                return [];
                        });
        };

        var fetchOpenLibrarySuggestions = function (query, controller) {
                var baseUrl = 'https://openlibrary.org/search.json';
                var params = [
                        'title=' + encodeURIComponent(query),
                        'limit=6'
                ];
                var url = baseUrl + '?' + params.join('&');
                var fetchOptions = {};
                if (controller) {
                        fetchOptions.signal = controller.signal;
                }

                return fetch(url, fetchOptions)
                        .then(function (response) {
                                if (!response.ok) {
                                        throw new Error('Request failed');
                                }
                                return response.json();
                        })
                        .then(function (data) {
                                if (!data || !data.docs || !data.docs.length) {
                                        return [];
                                }

                                var docs = data.docs;
                                var items = [];
                                var seen = Object.create(null);

                                for (var i = 0; i < docs.length; i++) {
                                        var doc = docs[i];
                                        if (!doc) {
                                                continue;
                                        }

                                        var title = doc.title ? String(doc.title).trim() : '';
                                        if (!title) {
                                                continue;
                                        }

                                        var authors = [];
                                        if (doc.author_name && doc.author_name.length) {
                                                for (var authorIndex = 0; authorIndex < doc.author_name.length; authorIndex++) {
                                                        var candidate = String(doc.author_name[authorIndex]).trim();
                                                        if (!candidate) {
                                                                continue;
                                                        }
                                                        if (authors.indexOf(candidate) === -1) {
                                                                authors.push(candidate);
                                                        }
                                                }
                                        }

                                        var author = authors.length ? authors[0] : '';

                                        var year = '';
                                        if (doc.first_publish_year) {
                                                year = String(doc.first_publish_year);
                                        } else if (doc.publish_year && doc.publish_year.length) {
                                                year = String(doc.publish_year[0]);
                                        }

                                        var isbn = '';
                                        if (doc.isbn && doc.isbn.length) {
                                                for (var isbnIndex = 0; isbnIndex < doc.isbn.length; isbnIndex++) {
                                                        var candidateIsbn = String(doc.isbn[isbnIndex]).trim();
                                                        if (!candidateIsbn) {
                                                                continue;
                                                        }
                                                        if (/^\d{13}$/.test(candidateIsbn)) {
                                                                isbn = candidateIsbn;
                                                                break;
                                                        }
                                                }
                                                if (!isbn) {
                                                        isbn = String(doc.isbn[0]).trim();
                                                }
                                        }

                                        var pages = '';
                                        if (doc.number_of_pages_median) {
                                                var medianPages = parseInt(doc.number_of_pages_median, 10);
                                                if (!isNaN(medianPages) && medianPages > 0) {
                                                        pages = medianPages;
                                                }
                                        }
                                        var cover = '';
                                        if (doc.cover_i) {
                                                cover = 'https://covers.openlibrary.org/b/id/' + doc.cover_i + '-M.jpg';
                                        }

                                        if (!author || !year) {
                                                continue;
                                        }

                                        var key = normalizeForComparison(title) + '|' + normalizeForComparison(authors.join('|'));
                                        if (seen[key]) {
                                                continue;
                                        }
                                        seen[key] = true;

                                        items.push({
                                                title: title,
                                                author: author,
                                                authors: authors,
                                                year: year,
                                                isbn: isbn,
                                                pages: pages,
                                                cover: cover,
                                                source: 'openlibrary'
                                        });

                                        if (items.length >= 6) {
                                                break;
                                        }
                                }

                                return items;
                        })
                        .catch(function (error) {
                                if (error && error.name === 'AbortError') {
                                        throw error;
                                }
                                return [];
                        });
        };

        var fetchGoogleIsbnSuggestion = function (isbn, controller) {
                var baseParams = [
                        'q=' + encodeURIComponent('isbn:' + isbn),
                        'maxResults=1',
                        'printType=books',
                        'orderBy=relevance'
                ];
                var fieldParam = 'fields=items(id,selfLink,volumeInfo/title,volumeInfo/authors,volumeInfo/publishedDate,volumeInfo/industryIdentifiers,volumeInfo/pageCount,volumeInfo/imageLinks)';
                var url = 'https://www.googleapis.com/books/v1/volumes?' + baseParams.concat([fieldParam]).join('&');
                var urlNoFields = 'https://www.googleapis.com/books/v1/volumes?' + baseParams.join('&');
                var fetchOptions = {};
                if (controller) {
                        fetchOptions.signal = controller.signal;
                }

                var parseGoogleItems = function (data) {
                        if (!data || !data.items || !data.items.length) {
                                return [];
                        }

                        var doc = data.items[0];
                        if (!doc || !doc.volumeInfo) {
                                return [];
                        }

                        var volumeInfo = doc.volumeInfo;
                        var title = volumeInfo.title ? String(volumeInfo.title).trim() : '';
                        if (!title) {
                                return [];
                        }

                        var authors = [];
                        if (volumeInfo.authors && volumeInfo.authors.length) {
                                for (var authorIndex = 0; authorIndex < volumeInfo.authors.length; authorIndex++) {
                                        var candidate = String(volumeInfo.authors[authorIndex]).trim();
                                        if (!candidate) {
                                                continue;
                                        }
                                        if (authors.indexOf(candidate) === -1) {
                                                authors.push(candidate);
                                        }
                                }
                        }

                        var author = authors.length ? authors[0] : '';
                        var year = '';
                        if (volumeInfo.publishedDate) {
                                year = parseYear(volumeInfo.publishedDate);
                        }

                        var isbnValue = '';
                        if (volumeInfo.industryIdentifiers && volumeInfo.industryIdentifiers.length) {
                                var identifiers = volumeInfo.industryIdentifiers;
                                for (var idIndex = 0; idIndex < identifiers.length; idIndex++) {
                                        var identifier = identifiers[idIndex] || {};
                                        if (identifier.type === 'ISBN_13' && identifier.identifier) {
                                                isbnValue = String(identifier.identifier);
                                                break;
                                        }
                                }
                                if (!isbnValue) {
                                        for (var fallbackIndex = 0; fallbackIndex < identifiers.length; fallbackIndex++) {
                                                var fallback = identifiers[fallbackIndex] || {};
                                                if (fallback.type === 'ISBN_10' && fallback.identifier) {
                                                        isbnValue = String(fallback.identifier);
                                                        break;
                                                }
                                        }
                                }
                                if (!isbnValue) {
                                        var fallbackIdentifier = identifiers[0];
                                        if (fallbackIdentifier && fallbackIdentifier.identifier) {
                                                isbnValue = String(fallbackIdentifier.identifier);
                                        }
                                }
                        }

                        var pages = '';
                        if (typeof volumeInfo.pageCount !== 'undefined' && volumeInfo.pageCount !== null) {
                                var pageValue = parseInt(volumeInfo.pageCount, 10);
                                if (!isNaN(pageValue) && pageValue > 0) {
                                        pages = pageValue;
                                }
                        }

                        var cover = '';
                        if (volumeInfo.imageLinks) {
                                cover = volumeInfo.imageLinks.thumbnail || volumeInfo.imageLinks.smallThumbnail || '';
                        }

                        return [{
                                id: doc.id || '',
                                selfLink: doc.selfLink || '',
                                title: title,
                                author: author,
                                authors: authors,
                                year: year,
                                isbn: isbnValue || isbn,
                                pages: pages,
                                cover: cover,
                                source: 'googlebooks'
                        }];
                };

                var fetchJson = function (targetUrl, allowFallback) {
                        return fetch(targetUrl, fetchOptions)
                                .then(function (response) {
                                        if (!response.ok) {
                                                var error = new Error('Request failed');
                                                error.allowFallback = allowFallback;
                                                throw error;
                                        }
                                        return response.json();
                                })
                                .then(parseGoogleItems);
                };

                return fetchJson(url, true)
                        .catch(function (error) {
                                if (error && error.allowFallback) {
                                        return fetchJson(urlNoFields, false);
                                }
                                if (error && error.name === 'AbortError') {
                                        throw error;
                                }
                                return [];
                        })
                        .catch(function (error) {
                                if (error && error.name === 'AbortError') {
                                        throw error;
                                }
                                return [];
                        });
        };

        var fetchOpenLibraryIsbnSuggestion = function (isbn, controller) {
                var params = [
                        'isbn=' + encodeURIComponent(isbn),
                        'limit=1'
                ];
                var url = 'https://openlibrary.org/search.json?' + params.join('&');
                var fetchOptions = {};
                if (controller) {
                        fetchOptions.signal = controller.signal;
                }

                return fetch(url, fetchOptions)
                        .then(function (response) {
                                if (!response.ok) {
                                        throw new Error('Request failed');
                                }
                                return response.json();
                        })
                        .then(function (data) {
                                if (!data || !data.docs || !data.docs.length) {
                                        return [];
                                }

                                var doc = data.docs[0];
                                if (!doc) {
                                        return [];
                                }

                                var title = doc.title ? String(doc.title).trim() : '';
                                if (!title) {
                                        return [];
                                }

                                var authors = [];
                                if (doc.author_name && doc.author_name.length) {
                                        for (var authorIndex = 0; authorIndex < doc.author_name.length; authorIndex++) {
                                                var candidate = String(doc.author_name[authorIndex]).trim();
                                                if (!candidate) {
                                                        continue;
                                                }
                                                if (authors.indexOf(candidate) === -1) {
                                                        authors.push(candidate);
                                                }
                                        }
                                }

                                var author = authors.length ? authors[0] : '';
                                var year = '';
                                if (doc.first_publish_year) {
                                        year = String(doc.first_publish_year);
                                } else if (doc.publish_year && doc.publish_year.length) {
                                        year = String(doc.publish_year[0]);
                                }

                                var pages = '';
                                if (doc.number_of_pages_median) {
                                        var medianPages = parseInt(doc.number_of_pages_median, 10);
                                        if (!isNaN(medianPages) && medianPages > 0) {
                                                pages = medianPages;
                                        }
                                }

                                var cover = '';
                                if (doc.cover_i) {
                                        cover = 'https://covers.openlibrary.org/b/id/' + doc.cover_i + '-M.jpg';
                                }

                                return [{
                                        title: title,
                                        author: author,
                                        authors: authors,
                                        year: year,
                                        isbn: isbn,
                                        pages: pages,
                                        cover: cover,
                                        source: 'openlibrary'
                                }];
                        })
                        .catch(function (error) {
                                if (error && error.name === 'AbortError') {
                                        throw error;
                                }
                                return [];
                        });
        };

        var fetchIsbnSuggestions = function (isbn) {
                if (!isbn) {
                        clearIsbnSuggestions();
                        return;
                }

                cancelIsbnRequest();
                resetIsbnSuggestions();

                isbnAbortController = supportsAbortController ? new AbortController() : null;
                lastFetchedIsbn = isbn;
                var requestedIsbn = isbn;
                var currentController = isbnAbortController;

                fetchGoogleIsbnSuggestion(isbn, currentController)
                        .then(function (googleItems) {
                                if (!isbnInput || normalizeIsbnInput(isbnInput.value) !== requestedIsbn) {
                                        return null;
                                }
                                if (googleItems && googleItems.length) {
                                        return googleItems;
                                }
                                return fetchOpenLibraryIsbnSuggestion(isbn, currentController);
                        })
                        .then(function (items) {
                                if (!items) {
                                        return;
                                }
                                if (!isbnInput || normalizeIsbnInput(isbnInput.value) !== requestedIsbn) {
                                        return;
                                }
                                if (!items.length) {
                                        resetIsbnSuggestions();
                                        return;
                                }
                                showIsbnSuggestions(items);
                        })
                        .catch(function (error) {
                                if (error && error.name === 'AbortError') {
                                        return;
                                }
                                resetIsbnSuggestions();
                        })
                        .then(function () {
                                if (isbnAbortController === currentController) {
                                        isbnAbortController = null;
                                }
                        });
        };

        var extractGoogleDetails = function (volumeInfo) {
                if (!volumeInfo) {
                        return null;
                }

                var isbn = '';
                if (volumeInfo.industryIdentifiers && volumeInfo.industryIdentifiers.length) {
                        var identifiers = volumeInfo.industryIdentifiers;
                        for (var idIndex = 0; idIndex < identifiers.length; idIndex++) {
                                var identifier = identifiers[idIndex] || {};
                                if (identifier.type === 'ISBN_13' && identifier.identifier) {
                                        isbn = String(identifier.identifier);
                                        break;
                                }
                        }
                        if (!isbn) {
                                for (var fallbackIndex = 0; fallbackIndex < identifiers.length; fallbackIndex++) {
                                        var fallback = identifiers[fallbackIndex] || {};
                                        if (fallback.type === 'ISBN_10' && fallback.identifier) {
                                                isbn = String(fallback.identifier);
                                                break;
                                        }
                                }
                        }
                        if (!isbn) {
                                var fallbackIdentifier = identifiers[0];
                                if (fallbackIdentifier && fallbackIdentifier.identifier) {
                                        isbn = String(fallbackIdentifier.identifier);
                                }
                        }
                }

                var pages = '';
                if (typeof volumeInfo.pageCount !== 'undefined' && volumeInfo.pageCount !== null) {
                        var pageValue = parseInt(volumeInfo.pageCount, 10);
                        if (!isNaN(pageValue) && pageValue > 0) {
                                pages = pageValue;
                        }
                }
                var cover = '';
                if (volumeInfo.imageLinks) {
                        cover = volumeInfo.imageLinks.thumbnail || volumeInfo.imageLinks.smallThumbnail || '';
                }

                if (!isbn && !pages && !cover) {
                        return null;
                }

                return {
                        isbn: isbn,
                        pages: pages,
                        cover: cover
                };
        };

        var fetchGoogleVolumeDetails = function (selfLink) {
                if (!selfLink) {
                        return Promise.resolve(null);
                }

                return fetch(selfLink)
                        .then(function (response) {
                                if (!response.ok) {
                                        throw new Error('Request failed');
                                }
                                return response.json();
                        })
                        .then(function (data) {
                                if (!data || !data.volumeInfo) {
                                        return null;
                                }
                                return extractGoogleDetails(data.volumeInfo);
                        })
                        .catch(function () {
                                return null;
                        });
        };

        var fetchGoogleDetailsByQuery = function (title, author) {
                if (!title) {
                        return Promise.resolve(null);
                }

                var parts = [];
                parts.push('intitle:' + title);
                if (author) {
                        parts.push('inauthor:' + author);
                }
                var query = parts.join(' ');
                var params = [
                        'q=' + encodeURIComponent(query),
                        'maxResults=1',
                        'printType=books',
                        'orderBy=relevance',
                        'fields=items(selfLink,volumeInfo/industryIdentifiers,volumeInfo/pageCount,volumeInfo/imageLinks)'
                ];
                var url = 'https://www.googleapis.com/books/v1/volumes?' + params.join('&');

                return fetch(url)
                        .then(function (response) {
                                if (!response.ok) {
                                        throw new Error('Request failed');
                                }
                                return response.json();
                        })
                        .then(function (data) {
                                if (!data || !data.items || !data.items.length) {
                                        return null;
                                }

                                var item = data.items[0];
                                if (!item || !item.volumeInfo) {
                                        return null;
                                }

                                var details = extractGoogleDetails(item.volumeInfo);
                                if (details) {
                                        return details;
                                }

                                if (item.selfLink) {
                                        return fetchGoogleVolumeDetails(item.selfLink);
                                }

                                return null;
                        })
                        .catch(function () {
                                return null;
                        });
        };

        var fetchGoogleDetailsForSelection = function (item) {
                if (!item) {
                        return Promise.resolve(null);
                }

                if (item.selfLink) {
                        return fetchGoogleVolumeDetails(item.selfLink);
                }

                var author = '';
                if (item.authors && item.authors.length) {
                        author = item.authors[0];
                } else if (item.author) {
                        author = item.author;
                }

                return fetchGoogleDetailsByQuery(item.title, author);
        };

        var fetchSuggestions = function (query) {
                if (!query) {
                        clearSuggestions();
                        return;
                }

                cancelPendingRequest();
                resetSuggestions();

                abortController = supportsAbortController ? new AbortController() : null;
                lastFetchedQuery = query;
                var requestedQuery = query;
                var currentController = abortController;

                fetchCanonicalSuggestions(query, currentController)
                        .then(function (canonicalItems) {
                                if (isStaleQuery(requestedQuery)) {
                                        return null;
                                }
                                return Promise.all([
                                        Promise.resolve(canonicalItems || []),
                                        fetchGoogleSuggestions(query, currentController),
                                        fetchOpenLibrarySuggestions(query, currentController)
                                ]);
                        })
                        .then(function (results) {
                                if (!results) {
                                        return;
                                }

                                if (isStaleQuery(requestedQuery)) {
                                        return;
                                }

                                var canonicalItems = results[0] || [];
                                var googleItems = results[1] || [];
                                var openLibraryItems = results[2] || [];
                                var items = [];

                                if (canonicalItems.length) {
                                        var canonicalItem = canonicalItems[0];
                                        canonicalItem.source = 'canonical';
                                        items.push(canonicalItem);
                                }

                                if (googleItems.length) {
                                        var googleItem = googleItems[0];
                                        googleItem.source = 'googlebooks';
                                        items.push(googleItem);
                                }

                                if (openLibraryItems.length) {
                                        var openItem = openLibraryItems[0];
                                        openItem.source = 'openlibrary';
                                        items.push(openItem);
                                }

                                if (!items.length) {
                                        resetSuggestions();
                                        return;
                                }

                                lastSuggestionItems = canonicalItems.concat(googleItems, openLibraryItems);
                                showSuggestions(items);
                        })
                        .catch(function (error) {
                                if (error && error.name === 'AbortError') {
                                        return;
                                }
                                resetSuggestions();
                        })
                        .then(function () {
                                if (abortController === currentController) {
                                        abortController = null;
                                }
                        });
        };

        titleInput.addEventListener('input', function (event) {
                var query = event.target.value.trim();
                clearDuplicateState();

                if (debounceTimer) {
                        window.clearTimeout(debounceTimer);
                }

                if (query.length < 3) {
                        clearSuggestions();
                        return;
                }

                debounceTimer = window.setTimeout(function () {
                        if (query === lastFetchedQuery) {
                                return;
                        }
                        fetchSuggestions(query);
                }, 250);
        });

        if (isbnInput) {
                isbnInput.setAttribute('role', 'combobox');
                isbnInput.setAttribute('aria-autocomplete', 'list');
                isbnInput.setAttribute('autocomplete', 'off');
                if (isbnSuggestionContainer && !isbnInput.getAttribute('aria-controls')) {
                        isbnInput.setAttribute('aria-controls', isbnSuggestionContainer.id);
                }
                isbnInput.setAttribute('aria-expanded', 'false');

                isbnInput.addEventListener('input', function (event) {
                        var raw = event.target.value;
                        var normalized = normalizeIsbnInput(raw);
                        clearDuplicateState();

                        if (isbnDebounceTimer) {
                                window.clearTimeout(isbnDebounceTimer);
                        }

                        if (normalized.length < 10) {
                                clearIsbnSuggestions();
                                return;
                        }

                        isbnDebounceTimer = window.setTimeout(function () {
                                if (normalized === lastFetchedIsbn) {
                                        return;
                                }
                                fetchIsbnSuggestions(normalized);
                        }, 250);
                });

                isbnInput.addEventListener('keydown', function (event) {
                        if (!isbnSuggestionContainer || !isbnSuggestionContainer.classList.contains('is-visible')) {
                                if (event.key === 'Escape') {
                                        resetIsbnSuggestions();
                                }
                                return;
                        }

                        if (event.key === 'ArrowDown') {
                                event.preventDefault();
                                focusIsbnSuggestionAtIndex(0);
                        } else if (event.key === 'Escape') {
                                event.preventDefault();
                                resetIsbnSuggestions();
                        }
                });

                isbnInput.addEventListener('blur', function () {
                        window.setTimeout(function () {
                                if (!isbnSuggestionContainer) {
                                        return;
                                }
                                var active = document.activeElement;
                                if (active === isbnInput) {
                                        return;
                                }
                                if (isbnSuggestionContainer.contains(active)) {
                                        return;
                                }
                                resetIsbnSuggestions();
                        }, 100);
                });
        }

        if (addBookForm) {
                addBookForm.addEventListener('submit', function (event) {
                        if (duplicateNotice && !duplicateNotice.hidden) {
                                event.preventDefault();
                                event.stopPropagation();
                        }
                });
        }

        document.addEventListener('click', function (event) {
                if (!isbnSuggestionContainer || !isbnInput) {
                        return;
                }
                if (event.target === isbnInput) {
                        return;
                }
                if (isbnSuggestionContainer.contains(event.target)) {
                        return;
                }
                resetIsbnSuggestions();
        });


        titleInput.addEventListener('keydown', function (event) {
                if (!suggestionContainer || !suggestionContainer.classList.contains('is-visible')) {
                        if (event.key === 'Escape') {
                                resetSuggestions();
                        }
                        return;
                }

                if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        focusSuggestionAtIndex(0);
                } else if (event.key === 'Escape') {
                        event.preventDefault();
                        resetSuggestions();
                }
        });

        titleInput.addEventListener('blur', function () {
                window.setTimeout(function () {
                        if (!suggestionContainer) {
                                return;
                        }
                        var active = document.activeElement;
                        if (active === titleInput) {
                                return;
                        }
                        if (suggestionContainer.contains(active)) {
                                return;
                        }
                        resetSuggestions();
                }, 100);
        });

        document.addEventListener('click', function (event) {
                if (!suggestionContainer) {
                        return;
                }
                if (event.target === titleInput) {
                        return;
                }
                if (suggestionContainer.contains(event.target)) {
                        return;
                }
                resetSuggestions();
        });
})();
