(function () {
        var modal = document.getElementById('prs-add-book-modal');
        var modalContent = modal ? modal.querySelector('.prs-add-book__modal-content') : null;
        var form = document.getElementById('prs-add-book-form');
        var formHeading = document.getElementById('prs-add-book-form-title');
        var successContainer = document.getElementById('prs-add-book-success');
        var successHeading = successContainer ? successContainer.querySelector('.prs-add-book__success-heading') : null;
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
                        var keys = ['prs_added', 'prs_added_title', 'prs_added_author', 'prs_added_year', 'prs_added_pages', 'prs_added_cover'];

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

        var openButtons = document.querySelectorAll('[aria-controls="prs-add-book-modal"]');
        if (openButtons && openButtons.length) {
                for (var i = 0; i < openButtons.length; i++) {
                        openButtons[i].addEventListener('click', function () {
                                resetToForm(true);
                        });
                }
        }

        activateSuccess();

        var authorContainer = document.getElementById('prs_author_fields');
        var authorInputField = document.getElementById('prs_author_input');
        var authorList = document.getElementById('prs_author_list');
        var authorAddButton = document.getElementById('prs_author_add');
        var authorInputWrapper = authorContainer ? authorContainer.querySelector('.prs-add-book__author-input-wrapper') : null;
        var authorHiddenContainer = document.getElementById('prs_author_hidden');
        var authorHint = document.getElementById('prs_author_hint');
        var removeAuthorLabel = authorContainer ? authorContainer.getAttribute('data-remove-label') : '';
        var authorValues = [];
        var authorLookup = Object.create(null);
        var authorEditMode = false;

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

        var titleInput = document.getElementById('prs_title');
        if (!titleInput) {
                return;
        }

        var authorInput = getPrimaryAuthorInput();
        var yearInput = document.getElementById('prs_year');
        var suggestionContainer = document.getElementById('prs_title_suggestions');
        var autocompleteConfig = typeof window !== 'undefined' ? window.PRS_ADD_BOOK_AUTOCOMPLETE : null;
        var canonicalEndpoint = autocompleteConfig && autocompleteConfig.ajax_url ? autocompleteConfig.ajax_url : '';
        var canonicalNonce = autocompleteConfig && autocompleteConfig.nonce ? autocompleteConfig.nonce : '';

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

        var selectSuggestion = function (item) {
                if (!item) {
                        return;
                }

                titleInput.value = item.title;
                if (item.authors && item.authors.length) {
                        setAuthors(item.authors);
                        authorInput = getPrimaryAuthorInput();
                } else if (item.author) {
                        setAuthors([item.author]);
                        authorInput = getPrimaryAuthorInput();
                }
                if (yearInput) {
                        yearInput.value = item.year;
                }
                resetSuggestions();
                titleInput.focus();
        };

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
                        'fields=items(volumeInfo/title,volumeInfo/authors,volumeInfo/publishedDate)'
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
                                                year: year
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
