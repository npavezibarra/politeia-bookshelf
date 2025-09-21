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
                        var labelParts = [item.title];
                        if (item.author) {
                                option.dataset.author = item.author;
                                labelParts.push('— ' + item.author);
                        }
                        if (item.year) {
                                option.dataset.year = item.year;
                                labelParts.push('(' + item.year + ')');
                        }
                        option.label = labelParts.join(' ');
                        option.textContent = option.label;
                        suggestionList.appendChild(option);
                }
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

                var url = 'https://openlibrary.org/search.json?limit=6&title=' + encodeURIComponent(query);
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
                                if (!data || !data.docs || !data.docs.length) {
                                        clearSuggestions();
                                        return;
                                }

                                var docs = data.docs;
                                var items = [];
                                var seen = Object.create(null);

                                for (var i = 0; i < docs.length; i++) {
                                        var doc = docs[i];
                                        var title = (doc && doc.title) ? String(doc.title).trim() : '';
                                        if (!title) {
                                                continue;
                                        }
                                        var author = '';
                                        if (doc.author_name && doc.author_name.length) {
                                                author = String(doc.author_name[0]).trim();
                                        }
                                        var year = '';
                                        if (doc.first_publish_year) {
                                                year = String(doc.first_publish_year);
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
        });

        titleInput.addEventListener('change', function (event) {
                var value = event.target.value;
                if (!value) {
                        return;
                }

                var option = findOptionByValue(value);
                if (!option) {
                        return;
                }

                if (authorInput && option.dataset.author) {
                        authorInput.value = option.dataset.author;
                }

                if (yearInput && option.dataset.year) {
                        yearInput.value = option.dataset.year;
                }
        });
})();
