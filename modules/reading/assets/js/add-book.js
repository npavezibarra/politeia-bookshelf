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
        var settings = window.prsAddBookSettings || {};
        var authorAjaxUrl = settings.ajaxUrl || '';
        var authorNonce = settings.authorNonce || '';
        var i18n = settings.i18n || {};

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
                        var keys = ['prs_added', 'prs_added_title', 'prs_added_author', 'prs_added_authors', 'prs_added_year', 'prs_added_pages', 'prs_added_cover'];

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

        var titleInput = document.getElementById('prs_title');
        if (!titleInput) {
                return;
        }

        var yearInput = document.getElementById('prs_year');

        var authorsField = document.getElementById('prs_authors_field');
        var authorsInput = document.getElementById('prs_author_input');
        var authorsSuggestionContainer = document.getElementById('prs_author_suggestions');
        var selectedAuthorsContainer = document.getElementById('prs_selected_authors');
        var hiddenAuthorsContainer = document.getElementById('prs_author_hidden_inputs');
        var authorsError = document.getElementById('prs_authors_error');
        var authorsLive = document.getElementById('prs_authors_live');
        var selectedAuthors = [];

        var authorSupportsAbort = typeof window.AbortController === 'function';
        var authorAbortController = null;
        var authorDebounceTimer = null;
        var lastAuthorQuery = '';

        var normalizeAuthorForCompare = function (value) {
                if (!value) {
                        return '';
                }
                return String(value).toLowerCase().replace(/\s+/g, ' ').trim();
        };

        var formatRemoveLabel = function (name) {
                if (typeof i18n.removeAuthor === 'string') {
                        if (i18n.removeAuthor.indexOf('%s') !== -1) {
                                return i18n.removeAuthor.replace('%s', name);
                        }
                        return i18n.removeAuthor;
                }
                return 'Remove ' + name;
        };

        var announceAuthorsChange = function (message) {
                if (!authorsLive) {
                        return;
                }
                authorsLive.textContent = '';
                if (message) {
                        authorsLive.textContent = message;
                }
        };

        var hideAuthorsError = function () {
                if (authorsError) {
                        authorsError.hidden = true;
                }
                if (authorsField) {
                        authorsField.classList.remove('has-error');
                }
        };

        var showAuthorsError = function () {
                if (authorsError) {
                        authorsError.hidden = false;
                }
                if (authorsField) {
                        authorsField.classList.add('has-error');
                }
        };

        var updateHiddenAuthorInputs = function () {
                if (!hiddenAuthorsContainer) {
                        return;
                }
                hiddenAuthorsContainer.innerHTML = '';
                for (var i = 0; i < selectedAuthors.length; i++) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'prs_authors[]';
                        input.value = selectedAuthors[i].name;
                        hiddenAuthorsContainer.appendChild(input);
                }
        };

        var hasAuthor = function (name) {
                var normalized = normalizeAuthorForCompare(name);
                if (!normalized) {
                        return false;
                }
                for (var i = 0; i < selectedAuthors.length; i++) {
                        if (normalizeAuthorForCompare(selectedAuthors[i].name) === normalized) {
                                return true;
                        }
                }
                return false;
        };

        var removeAuthor = function (index) {
                if (index < 0 || index >= selectedAuthors.length) {
                        return;
                }
                selectedAuthors.splice(index, 1);
                renderSelectedAuthors();
                updateHiddenAuthorInputs();
                if (!selectedAuthors.length) {
                        showAuthorsError();
                }
                announceAuthorsChange(i18n.removed || 'Author removed.');
                if (authorsInput) {
                        authorsInput.focus();
                }
        };

        var renderSelectedAuthors = function () {
                if (!selectedAuthorsContainer) {
                        return;
                }
                selectedAuthorsContainer.innerHTML = '';

                if (!selectedAuthors.length) {
                        selectedAuthorsContainer.classList.add('is-empty');
                        return;
                }

                selectedAuthorsContainer.classList.remove('is-empty');

                for (var i = 0; i < selectedAuthors.length; i++) {
                        var item = document.createElement('span');
                        item.className = 'prs-add-book__author-chip';
                        item.setAttribute('role', 'listitem');

                        var label = document.createElement('span');
                        label.className = 'prs-add-book__author-chip-label';
                        label.textContent = selectedAuthors[i].name;
                        item.appendChild(label);

                        var removeButton = document.createElement('button');
                        removeButton.type = 'button';
                        removeButton.className = 'prs-add-book__author-chip-remove';
                        removeButton.textContent = '×';
                        removeButton.setAttribute('aria-label', formatRemoveLabel(selectedAuthors[i].name));
                        removeButton.addEventListener('click', (function (removeIndex) {
                                return function (event) {
                                        event.preventDefault();
                                        removeAuthor(removeIndex);
                                };
                        })(i));
                        item.appendChild(removeButton);

                        selectedAuthorsContainer.appendChild(item);
                }
        };

        renderSelectedAuthors();

        var hideAuthorSuggestions = function () {
                if (!authorsSuggestionContainer) {
                        return;
                }
                authorsSuggestionContainer.innerHTML = '';
                authorsSuggestionContainer.classList.remove('is-visible');
                authorsSuggestionContainer.setAttribute('aria-hidden', 'true');
                if (authorsInput) {
                        authorsInput.setAttribute('aria-expanded', 'false');
                }
        };

        var focusAuthorSuggestionAtIndex = function (index) {
                if (!authorsSuggestionContainer) {
                        return;
                }
                var buttons = authorsSuggestionContainer.querySelectorAll('.prs-add-book__suggestion');
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

        var handleAuthorSuggestionKeydown = function (event) {
                var target = event.currentTarget;
                var index = parseInt(target.getAttribute('data-index'), 10);
                if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        focusAuthorSuggestionAtIndex(index + 1);
                } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        if (index <= 0) {
                                if (authorsInput) {
                                        authorsInput.focus();
                                }
                        } else {
                                focusAuthorSuggestionAtIndex(index - 1);
                        }
                } else if (event.key === 'Escape') {
                        event.preventDefault();
                        hideAuthorSuggestions();
                        if (authorsInput) {
                                authorsInput.focus();
                        }
                }
        };

        var formatCreateLabel = function (value) {
                if (typeof i18n.createLabel === 'string') {
                        if (i18n.createLabel.indexOf('%s') !== -1) {
                                return i18n.createLabel.replace('%s', value);
                        }
                        return i18n.createLabel;
                }
                return 'Add "' + value + '"';
        };

        var addAuthor = function (name, options) {
                options = options || {};
                if (!name) {
                        return;
                }
                var cleaned = String(name).replace(/\s+/g, ' ').trim();
                if (!cleaned) {
                        return;
                }
                if (hasAuthor(cleaned)) {
                        announceAuthorsChange(i18n.duplicate || 'This author has already been added.');
                        hideAuthorSuggestions();
                        if (authorsInput) {
                                authorsInput.value = '';
                                authorsInput.focus();
                        }
                        return;
                }

                selectedAuthors.push({ name: cleaned });
                renderSelectedAuthors();
                updateHiddenAuthorInputs();
                hideAuthorsError();
                hideAuthorSuggestions();

                if (authorsInput) {
                        authorsInput.value = '';
                        authorsInput.focus();
                }

                if (!options.silent) {
                        var message = options.announceMessage || i18n.added || 'Author added.';
                        announceAuthorsChange(message);
                }
        };

        var showAuthorSuggestions = function (items, query) {
                if (!authorsSuggestionContainer || !authorsInput) {
                        return;
                }

                authorsSuggestionContainer.innerHTML = '';
                var visibleOptions = 0;
                var hasMessage = false;
                var normalizedQuery = normalizeAuthorForCompare(query);

                if (Array.isArray(items)) {
                        for (var i = 0; i < items.length; i++) {
                                var suggestion = items[i];
                                if (!suggestion || !suggestion.name) {
                                        continue;
                                }
                                var label = String(suggestion.name).trim();
                                if (!label || hasAuthor(label)) {
                                        continue;
                                }

                                var button = document.createElement('button');
                                button.type = 'button';
                                button.className = 'prs-add-book__suggestion';
                                button.setAttribute('data-index', visibleOptions);
                                button.setAttribute('role', 'option');
                                button.dataset.value = label;
                                button.textContent = label;
                                button.addEventListener('keydown', handleAuthorSuggestionKeydown);
                                button.addEventListener('click', function (event) {
                                        event.preventDefault();
                                        var value = event.currentTarget ? event.currentTarget.dataset.value : '';
                                        addAuthor(value);
                                });
                                authorsSuggestionContainer.appendChild(button);
                                visibleOptions++;
                        }
                }

                var shouldOfferCreate = normalizedQuery && !hasAuthor(query);

                if (!visibleOptions) {
                        var message = document.createElement('div');
                        message.className = 'prs-add-book__suggestion-message';
                        message.setAttribute('role', 'presentation');
                        if (!query || query.length < 2) {
                                message.textContent = i18n.typeMore || 'Type at least two characters to search.';
                        } else {
                                message.textContent = i18n.noMatches || 'No authors found';
                        }
                        authorsSuggestionContainer.appendChild(message);
                        hasMessage = true;
                }

                if (shouldOfferCreate && query) {
                        var createButton = document.createElement('button');
                        createButton.type = 'button';
                        createButton.className = 'prs-add-book__suggestion prs-add-book__suggestion--create';
                        createButton.setAttribute('data-index', visibleOptions);
                        createButton.setAttribute('role', 'option');
                        createButton.dataset.value = query;
                        createButton.textContent = formatCreateLabel(query);
                        createButton.addEventListener('keydown', handleAuthorSuggestionKeydown);
                        createButton.addEventListener('click', function (event) {
                                event.preventDefault();
                                if (authorsInput) {
                                        addAuthor(authorsInput.value);
                                }
                        });
                        authorsSuggestionContainer.appendChild(createButton);
                        visibleOptions++;
                }

                if (!visibleOptions && !hasMessage) {
                        authorsSuggestionContainer.classList.remove('is-visible');
                        authorsSuggestionContainer.setAttribute('aria-hidden', 'true');
                        authorsInput.setAttribute('aria-expanded', 'false');
                        return;
                }

                authorsSuggestionContainer.classList.add('is-visible');
                authorsSuggestionContainer.setAttribute('aria-hidden', 'false');
                authorsInput.setAttribute('aria-expanded', 'true');
        };

        var cancelAuthorRequest = function () {
                if (authorDebounceTimer) {
                        window.clearTimeout(authorDebounceTimer);
                        authorDebounceTimer = null;
                }
                if (authorSupportsAbort && authorAbortController) {
                        authorAbortController.abort();
                        authorAbortController = null;
                }
        };

        var fetchAuthorSuggestions = function (query) {
                cancelAuthorRequest();

                if (!query) {
                        hideAuthorSuggestions();
                        return;
                }

                if (!authorAjaxUrl) {
                        showAuthorSuggestions([], query);
                        return;
                }

                authorAbortController = authorSupportsAbort ? new AbortController() : null;
                lastAuthorQuery = query;

                var params = new window.URLSearchParams();
                params.append('action', 'prs_author_suggestions');
                if (authorNonce) {
                        params.append('nonce', authorNonce);
                }
                params.append('q', query);

                var fetchOptions = { credentials: 'same-origin' };
                var currentController = authorAbortController;
                if (currentController) {
                        fetchOptions.signal = currentController.signal;
                }

                fetch(authorAjaxUrl + '?' + params.toString(), fetchOptions)
                        .then(function (response) {
                                if (!response.ok) {
                                        throw new Error('Request failed');
                                }
                                return response.json();
                        })
                        .then(function (data) {
                                if (query !== lastAuthorQuery) {
                                        return;
                                }
                                var items = [];
                                if (data && typeof data === 'object') {
                                        if (Array.isArray(data.data)) {
                                                items = data.data;
                                        } else if (Array.isArray(data)) {
                                                items = data;
                                        }
                                }
                                showAuthorSuggestions(items, query);
                        })
                        .catch(function (error) {
                                if (error && error.name === 'AbortError') {
                                        return;
                                }
                                showAuthorSuggestions([], query);
                        })
                        .then(function () {
                                if (authorAbortController === currentController) {
                                        authorAbortController = null;
                                }
                        });
        };

        var scheduleAuthorFetch = function (query) {
                if (authorDebounceTimer) {
                        window.clearTimeout(authorDebounceTimer);
                }
                authorDebounceTimer = window.setTimeout(function () {
                        fetchAuthorSuggestions(query);
                }, 220);
        };

        var setAuthorsFromSuggestion = function (authorName) {
                if (!authorName) {
                        return;
                }
                if (hasAuthor(authorName)) {
                        return;
                }
                addAuthor(authorName);
        };

        if (authorsInput) {
                authorsInput.setAttribute('role', 'combobox');
                authorsInput.setAttribute('aria-autocomplete', 'list');
                authorsInput.setAttribute('autocomplete', 'off');
                if (authorsSuggestionContainer && !authorsInput.getAttribute('aria-controls')) {
                        authorsInput.setAttribute('aria-controls', authorsSuggestionContainer.id);
                }
                authorsInput.setAttribute('aria-expanded', 'false');

                authorsInput.addEventListener('focus', hideAuthorsError);

                authorsInput.addEventListener('keydown', function (event) {
                        if (event.key === 'Enter') {
                                event.preventDefault();
                                var active = document.activeElement;
                                if (authorsSuggestionContainer && authorsSuggestionContainer.contains(active) && active.classList.contains('prs-add-book__suggestion')) {
                                        active.click();
                                } else {
                                        addAuthor(authorsInput.value);
                                }
                        } else if (event.key === ',') {
                                event.preventDefault();
                                addAuthor(authorsInput.value);
                        } else if (event.key === 'Tab' && authorsInput.value.trim() !== '') {
                                addAuthor(authorsInput.value);
                        } else if (event.key === 'Backspace' && authorsInput.value === '' && selectedAuthors.length) {
                                event.preventDefault();
                                removeAuthor(selectedAuthors.length - 1);
                        } else if (event.key === 'ArrowDown') {
                                if (authorsSuggestionContainer && authorsSuggestionContainer.classList.contains('is-visible')) {
                                        event.preventDefault();
                                        focusAuthorSuggestionAtIndex(0);
                                }
                        } else if (event.key === 'Escape') {
                                hideAuthorSuggestions();
                        }
                });

                authorsInput.addEventListener('input', function (event) {
                        var query = event.target.value.trim();
                        if (!query) {
                                cancelAuthorRequest();
                                hideAuthorSuggestions();
                                return;
                        }
                        if (query.length < 2) {
                                cancelAuthorRequest();
                                showAuthorSuggestions([], query);
                                return;
                        }
                        scheduleAuthorFetch(query);
                });

                authorsInput.addEventListener('blur', function () {
                        window.setTimeout(function () {
                                if (!authorsSuggestionContainer) {
                                        return;
                                }
                                var active = document.activeElement;
                                if (active === authorsInput) {
                                        return;
                                }
                                if (authorsSuggestionContainer.contains(active)) {
                                        return;
                                }
                                hideAuthorSuggestions();
                        }, 120);
                });
        }

        if (form) {
                form.addEventListener('submit', function (event) {
                        if (!selectedAuthors.length) {
                                event.preventDefault();
                                showAuthorsError();
                                if (authorsInput) {
                                        authorsInput.focus();
                                }
                        } else {
                                hideAuthorsError();
                        }
                });
        }

        var suggestionContainer = document.getElementById('prs_title_suggestions');

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

        var titleSupportsAbort = typeof window.AbortController === 'function';
        var titleAbortController = null;
        var titleDebounceTimer = null;
        var lastTitleQuery = '';

        var resetTitleSuggestions = function () {
                if (!suggestionContainer) {
                        return;
                }
                suggestionContainer.innerHTML = '';
                suggestionContainer.classList.remove('is-visible');
                suggestionContainer.setAttribute('aria-hidden', 'true');
                titleInput.setAttribute('aria-expanded', 'false');
        };

        var cancelTitleRequest = function () {
                if (titleSupportsAbort && titleAbortController) {
                        titleAbortController.abort();
                        titleAbortController = null;
                }
        };

        var clearTitleSuggestions = function () {
                cancelTitleRequest();
                lastTitleQuery = '';
                resetTitleSuggestions();
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

        var focusTitleSuggestionAtIndex = function (index) {
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

        var selectTitleSuggestion = function (item) {
                if (!item) {
                        return;
                }

                titleInput.value = item.title;
                if (yearInput) {
                        yearInput.value = item.year;
                }
                setAuthorsFromSuggestion(item.author);
                resetTitleSuggestions();
                titleInput.focus();
        };

        var handleTitleSuggestionKeydown = function (event) {
                var target = event.currentTarget;
                var index = parseInt(target.getAttribute('data-index'), 10);
                if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        focusTitleSuggestionAtIndex(index + 1);
                } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        if (index <= 0) {
                                titleInput.focus();
                        } else {
                                focusTitleSuggestionAtIndex(index - 1);
                        }
                } else if (event.key === 'Escape') {
                        event.preventDefault();
                        resetTitleSuggestions();
                        titleInput.focus();
                }
        };

        var showTitleSuggestions = function (items) {
                if (!suggestionContainer) {
                        return;
                }

                suggestionContainer.innerHTML = '';

                if (!items || !items.length) {
                        resetTitleSuggestions();
                        return;
                }

                for (var i = 0; i < items.length; i++) {
                        var button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'prs-add-book__suggestion';
                        button.setAttribute('data-index', i);
                        button.setAttribute('role', 'option');
                        button.dataset.title = items[i].title;
                        button.dataset.author = items[i].author;
                        button.dataset.year = items[i].year;
                        button.textContent = items[i].title + ' - ' + items[i].author + ' - ' + items[i].year;
                        button.addEventListener('keydown', handleTitleSuggestionKeydown);
                        button.addEventListener('click', (function (suggestion) {
                                return function (clickEvent) {
                                        clickEvent.preventDefault();
                                        selectTitleSuggestion(suggestion);
                                };
                        })(items[i]));
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

        var fetchTitleSuggestions = function (query) {
                if (!query) {
                        clearTitleSuggestions();
                        return;
                }

                cancelTitleRequest();
                resetTitleSuggestions();

                titleAbortController = titleSupportsAbort ? new AbortController() : null;
                lastTitleQuery = query;
                var requestedQuery = query;

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
                var currentController = titleAbortController;
                if (currentController) {
                        fetchOptions.signal = currentController.signal;
                }

                fetch(url, fetchOptions)
                        .then(function (response) {
                                if (!response.ok) {
                                        throw new Error('Request failed');
                                }
                                return response.json();
                        })
                        .then(function (data) {
                                if (!data || !data.items || !data.items.length) {
                                        resetTitleSuggestions();
                                        return;
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

                                        var author = '';
                                        if (volumeInfo.authors && volumeInfo.authors.length) {
                                                author = String(volumeInfo.authors[0]).trim();
                                        }

                                        var year = '';
                                        if (volumeInfo.publishedDate) {
                                                year = parseYear(volumeInfo.publishedDate);
                                        }

                                        if (!author || !year) {
                                                continue;
                                        }

                                        var key = normalizeForComparison(title) + '|' + normalizeForComparison(author);
                                        if (seen[key]) {
                                                continue;
                                        }
                                        seen[key] = true;

                                        items.push({
                                                title: title,
                                                author: author,
                                                year: year
                                        });

                                        if (items.length >= 6) {
                                                break;
                                        }
                                }

                                if (!items.length) {
                                        resetTitleSuggestions();
                                        return;
                                }

                                if (titleInput.value && titleInput.value.trim().toLowerCase() !== requestedQuery.toLowerCase()) {
                                        return;
                                }

                                showTitleSuggestions(items);
                        })
                        .catch(function (error) {
                                if (error && error.name === 'AbortError') {
                                        return;
                                }
                                resetTitleSuggestions();
                        })
                        .then(function () {
                                if (titleAbortController === currentController) {
                                        titleAbortController = null;
                                }
                        });
        };

        titleInput.addEventListener('input', function (event) {
                var query = event.target.value.trim();

                if (titleDebounceTimer) {
                        window.clearTimeout(titleDebounceTimer);
                }

                if (query.length < 3) {
                        clearTitleSuggestions();
                        return;
                }

                titleDebounceTimer = window.setTimeout(function () {
                        if (query === lastTitleQuery) {
                                return;
                        }
                        fetchTitleSuggestions(query);
                }, 250);
        });

        titleInput.addEventListener('keydown', function (event) {
                if (!suggestionContainer || !suggestionContainer.classList.contains('is-visible')) {
                        if (event.key === 'Escape') {
                                resetTitleSuggestions();
                        }
                        return;
                }

                if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        focusTitleSuggestionAtIndex(0);
                } else if (event.key === 'Escape') {
                        event.preventDefault();
                        resetTitleSuggestions();
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
                        resetTitleSuggestions();
                }, 100);
        });

        document.addEventListener('click', function (event) {
                if (authorsSuggestionContainer && authorsSuggestionContainer.classList.contains('is-visible')) {
                        if (event.target !== authorsInput && (!authorsSuggestionContainer.contains(event.target))) {
                                hideAuthorSuggestions();
                        }
                }

                if (!suggestionContainer) {
                        return;
                }
                if (event.target === titleInput) {
                        return;
                }
                if (suggestionContainer.contains(event.target)) {
                        return;
                }
                resetTitleSuggestions();
        });

})();
