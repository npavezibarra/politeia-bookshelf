# Politeia Reading Plugin — Database Activation & Schema Management

This document describes how the Politeia Reading plugin provisions and maintains its custom database tables. It is intended for developers who need to adjust the schema—for example, to support new cover storage strategies, structured notes, or additional metadata—while keeping both new installs and upgrades consistent.

## Activation Flow Overview

### Activation entry point

WordPress invokes the plugin activation hook, which calls `Politeia\Reading\Activator::activate()` from [`modules/reading/includes/class-activator.php`](../includes/class-activator.php).

The `activate()` method coordinates three main tasks:

1. Creating or updating all required database tables by delegating to `Politeia\Reading\Installer::install()`.
2. Triggering the optional `Politeia_Post_Reading_Schema::migrate()` routine if that module is present.
3. Recording the database version (`politeia_reading_db_version`) and flushing rewrite rules immediately so custom routes are available.

### Version management for upgrades

Schema upgrades are orchestrated by `Politeia\Reading\Upgrader::maybe_upgrade()`, which is hooked into `plugins_loaded`. It compares the stored version in `politeia_reading_db_version` with the `POLITEIA_READING_DB_VERSION` constant. When a mismatch is detected, it replays the installer and updates the stored option so existing installations converge on the latest schema.

Regardless of the version check outcome, the upgrader also loads migration drop-ins from `includes/migrations` so incremental adjustments can run safely exactly once per site.

## Table Creation Logic

### Preparing for `dbDelta()`

`Installer::get_schema_sql()` returns every `CREATE TABLE` statement required by the plugin. `Installer::install()` performs the following steps:

1. Loads WordPress upgrade helpers with `require_once ABSPATH . 'wp-admin/includes/upgrade.php';`.
2. Retrieves the site-specific charset and collation using `$wpdb->get_charset_collate()`.
3. Builds seven `CREATE TABLE` statements for:
   - `{$wpdb->prefix}politeia_books`
   - `{$wpdb->prefix}politeia_user_books`
   - `{$wpdb->prefix}politeia_reading_sessions`
   - `{$wpdb->prefix}politeia_read_ses_notes`
   - `{$wpdb->prefix}politeia_loans`
   - `{$wpdb->prefix}politeia_authors`
   - `{$wpdb->prefix}politeia_book_authors`

Each table definition includes primary keys, foreign-key-friendly indexes, unique constraints (such as `uniq_user_book`), and timestamp metadata (`created_at`, `updated_at`). Cover-related columns—`cover_attachment_id`, `cover_url`, and `cover_source`—are also part of the canonical and user-specific tables.

### Idempotent schema creation

Every SQL definition is passed to `dbDelta()`. WordPress compares the declared schema with the existing database and creates or alters tables as needed without dropping data. This makes plugin activation and repeated upgrades idempotent: running the routine multiple times will not duplicate tables or remove user data.

## Post-Creation Migrations

### Incremental migrations

After table creation, drop-in migration files in `includes/migrations` perform incremental, idempotent adjustments for legacy installs. The default `migration-legacy-schema.php` keeps historical behavior by:

- Ensuring legacy columns (e.g., `rating`) exist via dedicated helper methods.
- Normalizing enum values such as `owning_status`.
- Guaranteeing the presence of cover-related columns (`cover_url`, `cover_source`) on both canonical and user book tables.
- Maintaining hash and uniqueness constraints for canonical books.
- Reasserting the unique `(user_id, book_id)` relationship on user libraries.

The helpers (`maybe_add_column()`, `maybe_add_index()`, etc.) embedded in each migration keep operations safe to re-run, while the loader ensures each file executes only once per environment.

### Data normalization and constraint enforcement

Specialized migrations normalize canonical book records and realign user-book relations with the canonical catalog. They also enforce unique keys to prevent duplicate relationships while retaining existing user data.

## Guidance for Future Schema Changes

1. **Update the core `CREATE TABLE` statements.** Add or adjust columns directly in `Installer::get_schema_sql()` so fresh installations immediately receive the new structure.
2. **Bump and persist the database version.** Increment the plugin's DB version constant (for example, `POLITEIA_READING_DB_VERSION`) and rely on `Activator::activate()`/`Upgrader::maybe_upgrade()` to update the stored option. This ensures migrations run on both new and existing sites.
3. **Add conditional migration helpers.** For schema adjustments that must retrofit existing environments (changing column types, backfilling values, adding indexes), drop a new PHP file into `includes/migrations/`. The loader will execute it once per site, allowing you to scope helper utilities to that migration.
4. **Maintain idempotence and safety.** Prefer `dbDelta()` and the conditional helpers over direct `ALTER TABLE` statements to avoid unintended data loss or duplicate schema elements.

By centralizing schema definitions in `Installer::get_schema_sql()`, gating changes through the upgrader, and relying on idempotent migration helpers within drop-in files, the Politeia Reading plugin maintains a robust, self-healing schema lifecycle that can accommodate future enhancements such as richer cover storage, structured user notes, or extended author relationships.

## Schema Version Reference

- **Constant:** `POLITEIA_READING_DB_VERSION`
- **Option name:** `politeia_reading_db_version`
- **Hook order:** `register_activation_hook()` → `Activator::activate()` → `Upgrader::maybe_upgrade()` (on `plugins_loaded`)
- **Schema source:** `Installer::get_schema_sql()` in `class-installer.php`

- **v1.2** — Replaced `cover_attachment_id_user` (`BIGINT`) with the text-based `cover_reference` column on `wp_politeia_user_books` to support Google Books URLs and uploaded cover references.
