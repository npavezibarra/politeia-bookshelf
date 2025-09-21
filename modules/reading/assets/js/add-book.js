(function () {
        var modal = document.getElementById('prs-add-book-modal');
        var modalContent = modal ? modal.querySelector('.prs-add-book__modal-content') : null;
        var form = document.getElementById('prs-add-book-form');
        var formHeading = document.getElementById('prs-add-book-form-title');
        var successContainer = document.getElementById('prs-add-book-success');
        var successHeading = successContainer ? successContainer.querySelector('.prs-add-book__success-heading') : null;
        var closeButton = modal ? modal.querySelector('.prs-add-book__close') : null;
        var successActive = false;

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

        var resetToForm = function () {
                if (!successActive) {
                        return;
                }

                successActive = false;

                if (successContainer) {
                        successContainer.hidden = true;
                }

                if (modalContent) {
                        modalContent.classList.remove('prs-add-book__modal-content--success');
                }

                if (form) {
                        form.hidden = false;
                }

                if (formHeading) {
                        formHeading.hidden = false;
                        updateAriaLabelledBy(formHeading.id);
                }
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

                if (form) {
                        form.hidden = true;
                }

                if (formHeading) {
                        formHeading.hidden = true;
                }

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

        activateSuccess();

        var titleInput = document.getElementById('prs_title');
        if (!titleInput) {
                return;
        }

        var authorInput = document.getElementById('prs_author');
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
                if (authorInput) {
                        authorInput.value = item.author;
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
                        button.dataset.year = items[i].year;
                        button.textContent = items[i].title + ' - ' + items[i].author + ' - ' + items[i].year;
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
