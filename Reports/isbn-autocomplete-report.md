# ISBN Autocomplete Attempts — Status Report

## Goal
Allow users to type an ISBN and, after selecting the single suggested match, auto-fill Title, Authors, Year, Pages, and Cover in the Add Book form.

## Changes Implemented
1. Added an ISBN suggestions dropdown to the form (`prs_isbn_suggestions` in `modules/reading/shortcodes/add-book.php`).
2. Wired front-end ISBN search (Google Books first, Open Library fallback) with:
   - Normalized ISBN input and debounce.
   - One-item suggestions rendered with title/author/year metadata.
   - Selection handler intended to set ISBN then apply the full suggestion (`applySuggestionValues`) plus a Google detail fetch fallback.
3. Captured cover/imageLinks in Google/OpenLibrary lookups and hooked `setCoverPreview` to ISBN selection.
4. Added defensive dataset fallbacks in suggestion buttons and re-applied values on click to avoid missing fields.

## Observed Outcome
- ISBN suggestions appear and selecting them sets the ISBN field, but Title/Author/Year/Pages/Cover remain empty.

## Hypotheses on Why It’s Still Failing
1. **Suggestion payload missing fields at click time:** The constructed `suggestion` object may lack title/author/year (e.g., Open Library ISBN response missing data), and dataset fallbacks aren’t populated as expected, so `applySuggestionValues` receives empty strings.
2. **Normalization mismatch prevents fallback enrichment:** `findSupplementalDetails` matches on normalized title/author; if the ISBN suggestion’s title/author differ in accents/casing/spacing from the title suggestions array, it won’t find supplemental data for pages/cover.
3. **Detail fetch not triggered (selfLink absent):** Some ISBN results may not include `selfLink`, so the fallback fetch path using title/author query isn’t firing or returning data due to query normalization issues.
4. **Event ordering with hidden inputs:** ISBN selection updates the chip/input but focus handling or hidden input toggles may clear or skip applying values before the UI refreshes.

## Next Steps (suggested)
1. Log the exact suggestion object on ISBN click (title/author/year/isbn/pages/cover/selfLink) and confirm datasets are populated; adjust matching/normalization accordingly.
2. Force a title/author query fallback when `selfLink` is absent and no title/author is present in the suggestion, then reapply results.
3. Temporarily bypass `findSupplementalDetails` and directly apply whatever fields come back from the ISBN lookup to verify data path, then reintroduce enrichment.
4. Add a minimal on-screen debug banner (dev-only) showing the resolved payload to speed iteration.
