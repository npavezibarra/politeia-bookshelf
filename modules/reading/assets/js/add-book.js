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
        var authorTemplate = document.getElementById('prs_author_template');
        var addAuthorButton = document.getElementById('prs_add_author');
        var removeAuthorLabel = authorContainer ? authorContainer.getAttribute('data-remove-label') : '';

        var getAuthorFields = function () {
                if (!authorContainer) {
                        return [];
                }
                var nodeList = authorContainer.querySelectorAll('[data-author-field]');
                return Array.prototype.slice.call(nodeList || []);
        };

        var updateRemoveButtons = function () {
                var fields = getAuthorFields();
                for (var i = 0; i < fields.length; i++) {
                        var removeButton = fields[i].querySelector('[data-remove-author]');
                        if (!removeButton) {
                                continue;
                        }
                        removeButton.hidden = fields.length <= 1;
                }
        };

        var bindAuthorField = function (field) {
                if (!field) {
                        return field;
                }
                var removeButton = field.querySelector('[data-remove-author]');
                if (removeButton && !removeButton.hasAttribute('data-author-bound')) {
                        removeButton.setAttribute('data-author-bound', '1');
                        removeButton.addEventListener('click', function (event) {
                                if (event && typeof event.preventDefault === 'function') {
                                        event.preventDefault();
                                }
                                if (!authorContainer) {
                                        return;
                                }
                                var fields = getAuthorFields();
                                if (fields.length <= 1) {
                                        var primary = fields.length ? fields[0].querySelector('input') : null;
                                        if (primary) {
                                                primary.focus();
                                        }
                                        return;
                                }
                                if (field.parentNode === authorContainer) {
                                        authorContainer.removeChild(field);
                                } else if (field.parentNode) {
                                        field.parentNode.removeChild(field);
                                }
                                updateRemoveButtons();
                        });
                }
                return field;
        };

        var createAuthorField = function (value) {
                var field = null;
                if (authorTemplate && 'content' in authorTemplate && authorTemplate.content.firstElementChild) {
                        field = authorTemplate.content.firstElementChild.cloneNode(true);
                } else if (authorContainer) {
                        field = document.createElement('div');
                        field.className = 'prs-add-book__author';
                        field.setAttribute('data-author-field', '');

                        var input = document.createElement('input');
                        input.type = 'text';
                        input.name = 'prs_author[]';
                        input.required = true;
                        input.autocomplete = 'off';
                        input.className = 'prs-add-book__author-input';
                        field.appendChild(input);

                        var removeButton = document.createElement('button');
                        removeButton.type = 'button';
                        removeButton.className = 'prs-add-book__remove-author';
                        removeButton.setAttribute('data-remove-author', '');
                        removeButton.textContent = removeAuthorLabel || 'Remove';
                        if (removeAuthorLabel) {
                                removeButton.setAttribute('aria-label', removeAuthorLabel);
                        }
                        field.appendChild(removeButton);
                }

                field = bindAuthorField(field);

                var inputField = field ? field.querySelector('input') : null;
                if (inputField) {
                        inputField.value = value || '';
                }

                return field;
        };

        var ensureInitialAuthorField = function () {
                if (!authorContainer) {
                        return;
                }
                var fields = getAuthorFields();
                if (!fields.length) {
                        var newField = createAuthorField('');
                        if (newField) {
                                authorContainer.appendChild(newField);
                        }
                        fields = getAuthorFields();
                }
                for (var i = 0; i < fields.length; i++) {
                        bindAuthorField(fields[i]);
                }
                updateRemoveButtons();
        };

        var getPrimaryAuthorInput = function () {
                var fields = getAuthorFields();
                if (!fields.length) {
                        return null;
                }
                return fields[0].querySelector('input');
        };

        var setAuthors = function (authors) {
                if (!authorContainer) {
                        var legacyInput = document.getElementById('prs_author');
                        if (legacyInput) {
                                legacyInput.value = authors && authors.length ? authors[0] : '';
                        }
                        return;
                }

                var values = [];

                if (Array.isArray(authors)) {
                        for (var i = 0; i < authors.length; i++) {
                                if (authors[i]) {
                                        values.push(String(authors[i]));
                                }
                        }
                } else if (typeof authors === 'string') {
                        values.push(authors);
                }

                if (!values.length) {
                        values.push('');
                }

                var existingFields = getAuthorFields();

                for (var j = existingFields.length; j < values.length; j++) {
                        var appended = createAuthorField('');
                        if (appended) {
                                authorContainer.appendChild(appended);
                        }
                }

                existingFields = getAuthorFields();

                for (var k = 0; k < existingFields.length; k++) {
                        var input = existingFields[k].querySelector('input');
                        if (!input) {
                                continue;
                        }
                        input.value = values[k] || '';
                }

                for (var m = existingFields.length - 1; m >= values.length; m--) {
                        if (existingFields.length <= 1) {
                                break;
                        }
                        var field = existingFields[m];
                        if (field && field.parentNode) {
                                field.parentNode.removeChild(field);
                        }
                        existingFields = getAuthorFields();
                }

                updateRemoveButtons();
        };

        ensureInitialAuthorField();

        if (addAuthorButton && authorContainer) {
                addAuthorButton.addEventListener('click', function (event) {
                        if (event && typeof event.preventDefault === 'function') {
                                event.preventDefault();
                        }
                        var newField = createAuthorField('');
                        if (newField) {
                                authorContainer.appendChild(newField);
                                updateRemoveButtons();
                                var input = newField.querySelector('input');
                                if (input) {
                                        input.focus();
                                }
                        }
                });
        }

        var titleInput = document.getElementById('prs_title');
        if (!titleInput) {
                return;
        }

        var authorInput = getPrimaryAuthorInput();
        var yearInput = document.getElementById('prs_year');
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
                        var button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'prs-add-book__suggestion';
                        button.setAttribute('data-index', i);
                        button.setAttribute('role', 'option');
                        button.dataset.title = items[i].title;
                        button.dataset.author = items[i].author;
                        if (items[i].authors && items[i].authors.length) {
                                try {
                                        button.dataset.authors = JSON.stringify(items[i].authors);
                                } catch (error) {
                                        button.dataset.authors = '';
                                }
                        }
                        button.dataset.year = items[i].year;
                        var authorsLabel = items[i].authors && items[i].authors.length ? items[i].authors.join(', ') : items[i].author;
                        button.textContent = items[i].title + ' - ' + authorsLabel + ' - ' + items[i].year;
                        button.addEventListener('keydown', handleSuggestionKeydown);
                        button.addEventListener('click', (function (suggestion) {
                                return function (clickEvent) {
                                        clickEvent.preventDefault();
                                        selectSuggestion(suggestion);
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
                var currentController = abortController;
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
                                        resetSuggestions();
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

                                if (!items.length) {
                                        resetSuggestions();
                                        return;
                                }

                                if (titleInput.value && titleInput.value.trim().toLowerCase() !== requestedQuery.toLowerCase()) {
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
