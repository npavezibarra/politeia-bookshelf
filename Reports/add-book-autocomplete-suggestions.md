# Add Book Autocomplete Suggestions Review

Problem summary
- External providers (Google Books/Open Library) return noisy, unrelated results when the user types a short, context-free prefix (e.g., "Insurr"), even when canonical results are accurate.

Why this happens
- The external APIs optimize for broad recall on large corpora, so a short prefix produces weak matches that are still "closest" by their ranking.
- Our current flow always queries external providers and takes the first result from each source, so low-confidence matches still surface.

Potential improvements (using existing data)
1. Canonical-guided external search
   - If canonical results exist, refine external queries using canonical title + author.
   - Example: use intitle:"Insurreccion" and inauthor:"Fernando Villegas" for Google; title=Insurreccion&author=Fernando Villegas for Open Library.

2. Delay external queries for ambiguous input
   - Only query external sources after a minimum length (e.g., 5+ chars) or when canonical results are empty.

3. Confidence scoring + filtering
   - Score external results by title similarity (prefix match, diacritics-insensitive, normalized), author similarity, and year proximity.
   - Drop external suggestions if top score falls below a threshold.

4. Context-aware ranking
   - Boost results that match user locale/language, existing library language, recently added authors, or recent book themes.

5. Prefer starts-with matches
   - Weight results higher when the normalized title starts with the query, not just contains it.

6. "More results" disclosure
   - If external results are weak, hide them by default and show a "More results" option instead.

Next steps
- Define a scoring rubric (title similarity, author similarity, language match, recency) and thresholds.
- Decide whether to gate external queries behind canonical results or longer input length.
- Implement and test ranking/filtering with real examples (e.g., "Insurr", "Ang", "Cicer").
