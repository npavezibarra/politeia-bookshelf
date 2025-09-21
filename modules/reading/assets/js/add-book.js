(function () {
        var titleInput = document.getElementById('prs_title');
        if (!titleInput) {
                return;
        }

        var authorInput = document.getElementById('prs_author');
        var yearInput = document.getElementById('prs_year');
        var suggestionList = document.getElementById('prs_title_suggestions');

        if (!suggestionList) {
                suggestionList = document.createElement('datalist');
                suggestionList.id = 'prs_title_suggestions';
                titleInput.setAttribute('list', suggestionList.id);
                if (titleInput.parentNode) {
                        titleInput.parentNode.appendChild(suggestionList);
                }
        }

        var supportsAbortController = typeof window.AbortController === 'function';
        var abortController = null;
        var debounceTimer = null;
        var lastFetchedQuery = '';

        var clearSuggestions = function () {
                if (supportsAbortController && abortController) {
                        abortController.abort();
                        abortController = null;
                }
                lastFetchedQuery = '';
                if (suggestionList) {
                        suggestionList.textContent = '';
                }
        };

        var populateSuggestions = function (items) {
                if (!suggestionList) {
                        return;
                }
                suggestionList.textContent = '';
                for (var i = 0; i < items.length; i++) {
                        var item = items[i];
                        var option = document.createElement('option');
                        option.value = item.title;
                        var label = item.title;
                        if (item.year) {
                                option.dataset.year = item.year;
                                label += ' (' + item.year + ')';
                        }
                        if (item.author) {
                                option.dataset.author = item.author;
                                label += ' — ' + item.author;
                        }
                        option.label = label;
                        option.textContent = option.label;
                        suggestionList.appendChild(option);
                }
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

                if (supportsAbortController && abortController) {
                        abortController.abort();
                }

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
                                        clearSuggestions();
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

                                        var key = title.toLowerCase() + '|' + author.toLowerCase();
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
                                        clearSuggestions();
                                        return;
                                }

                                if (titleInput.value && titleInput.value.trim().toLowerCase() !== requestedQuery.toLowerCase()) {
                                        return;
                                }

                                populateSuggestions(items);
                        })
                        .catch(function (error) {
                                if (error && error.name === 'AbortError') {
                                        return;
                                }
                                clearSuggestions();
                        })
                        .then(function () {
                                if (abortController === currentController) {
                                        abortController = null;
                                }
                        });
        };

        var findOptionByValue = function (value) {
                if (!suggestionList) {
                        return null;
                }

                if (window.CSS && window.CSS.escape) {
                        return suggestionList.querySelector('option[value="' + window.CSS.escape(value) + '"]');
                }

                var options = suggestionList.querySelectorAll('option');
                for (var i = 0; i < options.length; i++) {
                        if (options[i].value === value) {
                                return options[i];
                        }
                }
                return null;
        };

        var applySuggestionDetails = function (option) {
                if (!option) {
                        return;
                }

                if (authorInput && option.dataset.author) {
                        authorInput.value = option.dataset.author;
                }

                if (yearInput && option.dataset.year) {
                        yearInput.value = option.dataset.year;
                }
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
                }, 300);

                var option = findOptionByValue(event.target.value);
                if (option) {
                        applySuggestionDetails(option);
                }
        });

        titleInput.addEventListener('change', function (event) {
                var value = event.target.value;
                if (!value) {
                        return;
                }

                var option = findOptionByValue(value);
                applySuggestionDetails(option);
        });
})();
