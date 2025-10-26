# Politeia Reading Plugin — Database Activation & Schema Management

This document describes how the Politeia Reading plugin provisions and maintains its custom database tables. It is intended for developers who need to adjust the schema—for example, to support new cover storage strategies, structured notes, or additional metadata—while keeping both new installs and upgrades consistent.

## Activation Flow Overview

### Activation entry point

WordPress invokes the plugin activation hook, which calls `Politeia_Reading_Activator::activate()` from [`modules/reading/includes/class-activator.php`](../includes/class-activator.php).

The `activate()` method coordinates three main tasks:

1. Creating or updating all required database tables by calling `create_or_update_tables()`.
2. Running post-creation migrations that normalize data and backfill columns by calling `run_migrations()`.
3. Recording the database version (`politeia_reading_db_version`) and a rewrite flush flag in the WordPress options table.

If the optional `Politeia_Post_Reading_Schema` class is available, the activator also delegates to its `migrate()` method during activation so both modules share the same lifecycle.

### Version management for upgrades

The activator exposes a `maybe_upgrade()` method that should be hooked into `plugins_loaded`. It compares the stored version in `politeia_reading_db_version` with the current plugin constant (e.g., `POLITEIA_READING_VERSION`). When a mismatch is detected, it replays the schema creation and migration routines and then updates the stored version so existing installations converge on the latest schema.

## Table Creation Logic

### Preparing for `dbDelta()`

`create_or_update_tables()` performs the following steps:

1. Loads WordPress upgrade helpers with `require_once ABSPATH . 'wp-admin/includes/upgrade.php';`.
2. Retrieves the site-specific charset and collation using `$wpdb->get_charset_collate()`.
3. Builds six `CREATE TABLE` statements for:
   - `{$wpdb->prefix}politeia_books`
   - `{$wpdb->prefix}politeia_user_books`
   - `{$wpdb->prefix}politeia_reading_sessions`
   - `{$wpdb->prefix}politeia_loans`
   - `{$wpdb->prefix}politeia_authors`
   - `{$wpdb->prefix}politeia_book_authors`

Each table definition includes primary keys, foreign-key-friendly indexes, unique constraints (such as `title_author_hash` and `uniq_user_book`), and timestamp metadata (`created_at`, `updated_at`). Cover-related columns—`cover_attachment_id`, `cover_url`, and `cover_source`—are also part of the canonical and user-specific tables.

### Idempotent schema creation

Every SQL definition is passed to `dbDelta()`. WordPress compares the declared schema with the existing database and creates or alters tables as needed without dropping data. This makes plugin activation and repeated upgrades idempotent: running the routine multiple times will not duplicate tables or remove user data.

## Post-Creation Migrations

### Incremental migrations

After table creation, `run_migrations()` performs incremental, idempotent adjustments for legacy installs. Key steps include:

- Ensuring legacy columns (e.g., `rating`) exist via dedicated helper methods.
- Normalizing enum values such as `owning_status`.
- Guaranteeing the presence of cover-related columns (`cover_url`, `cover_source`) on both canonical and user book tables.
- Maintaining hash and uniqueness constraints for canonical books.
- Reasserting the unique `(user_id, book_id)` relationship on user libraries.

These operations rely on helper methods such as `maybe_add_column()`, `maybe_add_index()`, and `maybe_add_unique()` that check for existing schema elements before applying changes, keeping migrations safe to re-run.

### Data normalization and constraint enforcement

Specialized migrations compute deterministic `title_author_hash` values, deduplicate canonical book records, and realign user-book relations with the canonical catalog. They also enforce unique keys to prevent duplicate relationships while retaining existing user data.

## Guidance for Future Schema Changes

1. **Update the core `CREATE TABLE` statements.** Add or adjust columns directly in `create_or_update_tables()` so fresh installations immediately receive the new structure.
2. **Bump and persist the database version.** Increment the plugin's DB version constant (for example, `POLITEIA_READING_VERSION`) and rely on `activate()`/`maybe_upgrade()` to update the stored option. This ensures migrations run on both new and existing sites.
3. **Add conditional migration helpers.** For schema adjustments that must retrofit existing environments (changing column types, backfilling values, adding indexes), extend `run_migrations()` with the provided helper methods to keep operations idempotent.
4. **Maintain idempotence and safety.** Prefer `dbDelta()` and the conditional helpers over direct `ALTER TABLE` statements to avoid unintended data loss or duplicate schema elements.

By centralizing schema definitions in `create_or_update_tables()`, gating changes through version comparisons, and relying on idempotent migration helpers, the Politeia Reading plugin maintains a robust, self-healing schema lifecycle that can accommodate future enhancements such as richer cover storage, structured user notes, or extended author relationships.

## Schema Version Reference

- **Constant:** `POLITEIA_READING_DB_VERSION`
- **Option name:** `politeia_reading_db_version`
- **Hook order:** `register_activation_hook()` → `activate()` → `maybe_upgrade()` (on `plugins_loaded`)
- **Schema source:** `create_or_update_tables()` in `class-activator.php`

- **v1.2** — Replaced `cover_attachment_id_user` (`BIGINT`) with the text-based `cover_reference` column on `wp_politeia_user_books` to support Google Books URLs and uploaded cover references.

