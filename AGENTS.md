AGENTS.md

Scope: /Users/nicolaspavez/Local Sites/nupoliteia/app/public

Master Goal

Canonical truth (wp_politeia_books, authors, pivots) must only be written after an explicit confirmation step.
Single-book and multi-book flows must obey the same authority rules, even if UX differs.

Phase 0 — Ground Rules (tell Codex this first)

Non-negotiables:

Do NOT change table schemas unless explicitly instructed.
Do NOT delete legacy columns yet.
Do NOT refactor UI unless instructed.
Refactor flow first, cleanup later.
All new frontend text must be translatable and added to the appropriate .po/.mo files.

## Plan Types (Normalized)

As of version 1.5+, `wp_politeia_plans.plan_type` MUST be one of:
- `habit`: A habit-forming plan (config in `wp_politeia_plan_habit`).
- `complete_books`: A book-completion plan (config in `wp_politeia_plan_finish_book`).

**Legacy Note**: The values `ccl`, `finish_book`, and `custom` are deprecated and should not be used.

## Database Connection (Local Environment)

To run raw SQL queries or database commands from the terminal in this "Local" environment (macOS), the standard `wp db` or `mysql` commands may fail due to path configuration.

**Successful Connection Method:**

1.  **Find the MySQL Binary**:
    Located at: `/Users/nicolaspavez/Library/Application Support/Local/lightning-services/mysql-8.0.35+4/bin/darwin-arm64/bin/mysql`

2.  **Find the Socket File**:
    Located at: `/Users/nicolaspavez/Library/Application Support/Local/run/3CaoRaeYl/mysql/mysqld.sock`
    *(Note: The hash `3CaoRaeYl` might change if the site is re-imported or re-created, check `~/Library/Application Support/Local/run/` if it fails).*

3.  **Command Template**:
    ```bash
    "/Users/nicolaspavez/Library/Application Support/Local/lightning-services/mysql-8.0.35+4/bin/darwin-arm64/bin/mysql" \
      -u root \
      -proot \
      --socket="/Users/nicolaspavez/Library/Application Support/Local/run/3CaoRaeYl/mysql/mysqld.sock" \
      local \
      -e "YOUR SQL QUERY HERE;"
    ```

**Example (Drop Table):**
```bash
"/Users/nicolaspavez/Library/Application Support/Local/lightning-services/mysql-8.0.35+4/bin/darwin-arm64/bin/mysql" -u root -proot --socket="/Users/nicolaspavez/Library/Application Support/Local/run/3CaoRaeYl/mysql/mysqld.sock" local -e "DROP TABLE IF EXISTS wp_politeia_plan_goals;"
```
