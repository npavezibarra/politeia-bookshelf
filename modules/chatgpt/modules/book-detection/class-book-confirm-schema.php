<?php
/**
 * Class: Politeia_Book_Confirm_Schema
 * Purpose:
 *   - Create/upgrade the confirmation queue table (wp_politeia_book_confirm).
 *   - Provide helpers to mark items as "In Shelf" (book already in user's library),
 *     matching by Title + Author (no ISBN) with fuzzy fallback.
 *   - Store and expose suggested covers for queue rows (external source).
 * Language: English, translatable via 'politeia-chatgpt'.
 *
 * Notes:
 *   - This module only manages the confirmation queue used by Politeia ChatGPT.
 *   - Canonical books tables (wp_politeia_books / wp_politeia_user_books) are owned by Politeia Reading.
 */

if ( ! defined('ABSPATH') ) exit;

class Politeia_Book_Confirm_Schema {

    /** @var string */
    protected static $td = 'politeia-chatgpt';

    /** Full table names */
    public static function table_name()                { global $wpdb; return $wpdb->prefix . 'politeia_book_confirm'; }
    public static function books_table_name()          { global $wpdb; return $wpdb->prefix . 'politeia_books'; }
    public static function user_books_table_name()     { global $wpdb; return $wpdb->prefix . 'politeia_user_books'; }

    /** Check if confirmation table exists. */
    public static function exists() {
        global $wpdb; $t = self::table_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $found = $wpdb->get_var( $wpdb->prepare('SHOW TABLES LIKE %s', $t) );
        return ($found === $t);
    }

    /**
     * Ensure confirmation table is created/updated (idempotent).
     * - cover columns
     */
    public static function ensure() {
        global $wpdb;

        $table           = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            input_type VARCHAR(20) NOT NULL,
            source_note VARCHAR(190) DEFAULT '',
            title VARCHAR(255) NOT NULL,
            author VARCHAR(255) NOT NULL,
            normalized_title VARCHAR(255) DEFAULT NULL,
            normalized_author VARCHAR(255) DEFAULT NULL,
            external_isbn VARCHAR(32) DEFAULT NULL,
            external_source VARCHAR(50) DEFAULT NULL,
            external_score FLOAT DEFAULT NULL,
            match_method VARCHAR(30) DEFAULT NULL,
            matched_book_id BIGINT UNSIGNED DEFAULT NULL,

            -- Suggested cover (from external lookup)
            external_cover_url VARCHAR(500) DEFAULT NULL,
            external_cover_source VARCHAR(100) DEFAULT NULL,

            status ENUM('pending','confirmed','discarded') NOT NULL DEFAULT 'pending',
            raw_response LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_status (user_id, status),
            KEY idx_matched (matched_book_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        self::maybe_add_cover_columns();
    }

    /** Idempotent migration for cover columns */
    public static function maybe_add_cover_columns() {
        global $wpdb; $t = self::table_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $cols = $wpdb->get_col("SHOW COLUMNS FROM {$t}");
        if ( ! in_array('external_cover_url', $cols, true) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query("ALTER TABLE {$t} ADD COLUMN external_cover_url VARCHAR(500) DEFAULT NULL AFTER matched_book_id");
        }
        if ( ! in_array('external_cover_source', $cols, true) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query("ALTER TABLE {$t} ADD COLUMN external_cover_source VARCHAR(100) DEFAULT NULL AFTER external_cover_url");
        }
    }

    /* ------------------------ Normalization ------------------------ */

    protected static function table_exists($name) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $found = $wpdb->get_var( $wpdb->prepare('SHOW TABLES LIKE %s', $name) );
        return ($found === $name);
    }

    /** Normalize strings (strip tags, remove accents, lower, remove stopwords, sort tokens). */
    protected static function normalize_key($s) {
        $s = wp_strip_all_tags( (string) $s );
        $s = remove_accents($s);
        $s = strtolower($s);

        $stop = [' el ',' la ',' los ',' las ',' un ',' una ',' unos ',' unas ',' de ',' del ',' y ',' e ',' a ',' en ',' the ',' of ',' and ',' to ',' for '];
        $s = ' ' . preg_replace('/\s+/', ' ', $s) . ' ';
        foreach ($stop as $st) { $s = str_replace($st, ' ', $s); }

        $s = preg_replace('/[^a-z0-9\s]/', ' ', $s);
        $tokens = array_filter( explode(' ', trim(preg_replace('/\s+/', ' ', $s))) );
        sort($tokens, SORT_STRING);
        return implode( ' ', $tokens );
    }

    /** Build normalized composite key from Title + Author (plain string) */
    protected static function norm_title_author($title, $author) {
        return self::normalize_key( trim((string)$title . ' ' . (string)$author) );
    }

    /** Public helper: normalized pair key (plain, for fuzzy) */
    public static function normalize_pair_key($title, $author) {
        return self::norm_title_author($title, $author);
    }

    /** Relative Levenshtein distance (0=identical, 1=completely different) */
    protected static function rel_levenshtein_internal($a, $b) {
        $max = max(1, max(strlen($a), strlen($b)));
        return levenshtein($a, $b) / $max;
    }
    /** Public wrapper (for callers that need it) */
    public static function rel_levenshtein($a, $b) {
        return self::rel_levenshtein_internal($a, $b);
    }

    /** Fill normalized fields in-memory; optionally persist back */
    public static function backfill_normalized_fields(array &$rows, $persist = false) {
        global $wpdb; $t = self::table_name();
        foreach ($rows as &$r) {
            $title  = $r['title']  ?? '';
            $author = $r['author'] ?? '';
            if (empty($r['normalized_title']))  $r['normalized_title']  = self::normalize_key($title);
            if (empty($r['normalized_author'])) $r['normalized_author'] = self::normalize_key($author);

            if ($persist && !empty($r['id'])) {
                $wpdb->update(
                    $t,
                    [
                        'normalized_title'  => $r['normalized_title'],
                        'normalized_author' => $r['normalized_author'],
                    ],
                    [ 'id' => (int)$r['id'] ],
                    [ '%s','%s' ],
                    [ '%d' ]
                );
            }
        }
        unset($r);
    }

    /* -------------------------- In-Shelf marking ------------------------- */

    /**
     * Mark rows with:
     *  - already_in_shelf (0/1)
     *  - shelf_slug (string|null)
     *  - matched_book_id (int|null)
     *  - matched_book_year (int|null)   <-- agregado para poder mostrar/ephemeral
     */
    public static function batch_mark_in_shelf(array &$rows, $user_id, $threshold = 0.25) {
        global $wpdb;
        $books_tbl = self::books_table_name();
        $ub_tbl    = self::user_books_table_name();

        if ( ! self::table_exists($books_tbl) || ! self::table_exists($ub_tbl) ) {
            foreach ($rows as &$r) { $r['already_in_shelf']=0; $r['shelf_slug']=null; $r['matched_book_id']=null; $r['matched_book_year']=null; }
            unset($r); return $rows;
        }

        // User library with normalized key (+year)
        $authors_tbl = $wpdb->prefix . 'politeia_authors';
        $pivot_tbl   = $wpdb->prefix . 'politeia_book_authors';
        $slugs_tbl   = $wpdb->prefix . 'politeia_book_slugs';
        $slugs_table_exists = (bool) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s LIMIT 1',
                $slugs_tbl
            )
        );
        $slug_select = $slugs_table_exists ? 'COALESCE(s.slug, b.slug) AS slug' : 'b.slug AS slug';
        $slug_join   = $slugs_table_exists ? "LEFT JOIN {$slugs_tbl} s ON s.book_id = b.id AND s.is_primary = 1" : '';

        $sql = $wpdb->prepare("
            SELECT b.id, {$slug_select}, b.title, b.year,
                   (
                       SELECT GROUP_CONCAT(a.display_name ORDER BY ba.sort_order ASC SEPARATOR ', ')
                       FROM {$pivot_tbl} ba
                       LEFT JOIN {$authors_tbl} a ON a.id = ba.author_id
                       WHERE ba.book_id = b.id
                   ) AS authors
            FROM {$books_tbl} b
            INNER JOIN {$ub_tbl} ub
                ON ub.book_id = b.id AND ub.user_id = %d
            {$slug_join}
        ", (int)$user_id);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $user_books = $wpdb->get_results($sql, ARRAY_A);

        $lib_fuzzy = [];
        foreach ($user_books as $b) {
            $lib_fuzzy[] = [
                'id'   => (int)$b['id'],
                'slug' => (string)$b['slug'],
                'year' => ( isset($b['year']) && $b['year'] !== null && $b['year'] !== '' ) ? (int)$b['year'] : null,
                'key'  => self::norm_title_author($b['title'] ?? '', $b['authors'] ?? ''),
            ];
        }

        self::backfill_normalized_fields($rows, false);

        foreach ($rows as &$r) {
            $key = self::norm_title_author($r['normalized_title'] ?? '', $r['normalized_author'] ?? '');

            $best=null; $bestScore=1.0;
            foreach ($lib_fuzzy as $b) {
                $rel = self::rel_levenshtein_internal($key, $b['key']);
                if ($rel < $bestScore) { $bestScore=$rel; $best=$b; }
            }
            if ($best && $bestScore <= $threshold) {
                $r['already_in_shelf']=1; $r['shelf_slug']=$best['slug']; $r['matched_book_id']=$best['id']; $r['matched_book_year']=$best['year'];
            } else {
                $r['already_in_shelf']=0; $r['shelf_slug']=null; $r['matched_book_id']=null; $r['matched_book_year']=null;
            }
        }
        unset($r);
        return $rows;
    }

    /* ------------- Fetch rows (pending/others) + optional covers -------- */

    /**
     * Fetch confirmation rows for user (filtered by status) and mark In-Shelf.
     * Optionally try to fill missing covers via external provider hook and persist.
     *
     * @param int        $user_id
     * @param array      $statuses   (default ['pending'])
     * @param int        $limit
     * @param int        $offset
     * @param float|null $threshold
     * @param bool       $fill_covers
     * @return array
     */
    public static function get_confirm_rows_for_user($user_id, array $statuses = ['pending'], $limit = 100, $offset = 0, $threshold = null, $fill_covers = false) {
        global $wpdb; $t = self::table_name();

        $valid = array_intersect($statuses, ['pending','confirmed','discarded']);
        if (empty($valid)) $valid = ['pending'];
        $ph = implode(',', array_fill(0, count($valid), '%s'));

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $sql = $wpdb->prepare("
            SELECT id, user_id, input_type, source_note, title, author,
                   normalized_title, normalized_author,
                   external_isbn, external_source, external_score,
                   match_method, matched_book_id,
                   external_cover_url, external_cover_source,
                   status, raw_response, created_at, updated_at
            FROM {$t}
            WHERE user_id=%d AND status IN ($ph)
            ORDER BY created_at DESC, id DESC
            LIMIT %d OFFSET %d
        ", array_merge([ (int)$user_id ], $valid, [ (int)$limit, (int)$offset ]));

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($sql, ARRAY_A);

        self::backfill_normalized_fields($rows, false);
        self::batch_mark_in_shelf($rows, $user_id, $threshold === null ? 0.25 : (float)$threshold);

        if ($fill_covers) self::maybe_fill_covers_for_rows($rows, true);

        return $rows;
    }

    /* ----------------------------- Covers hook --------------------------- */

    /**
     * Resolve missing covers using filter:
     *   $cover = apply_filters('politeia_chatgpt_cover_for_title_author', null, $title, $author, $row);
     * Return null or ['url'=>'https://...','source'=>'openlibrary|google|...']
     */
    public static function maybe_fill_covers_for_rows(array &$rows, $persist = true) {
        if (empty($rows)) return;
        global $wpdb; $t = self::table_name();

        foreach ($rows as &$r) {
            if (!empty($r['external_cover_url'])) continue;
            $title  = $r['title']  ?? '';
            $author = $r['author'] ?? '';
            if ($title === '' || $author === '') continue;

            $provided = apply_filters('politeia_chatgpt_cover_for_title_author', null, $title, $author, $r);
            if (is_array($provided) && !empty($provided['url'])) {
                $url    = esc_url_raw($provided['url']);
                $source = isset($provided['source']) ? sanitize_text_field((string)$provided['source']) : '';
                $r['external_cover_url']    = $url;
                $r['external_cover_source'] = $source;

                if ($persist && !empty($r['id'])) {
                    $wpdb->update(
                        $t,
                        ['external_cover_url'=>$url, 'external_cover_source'=>$source],
                        ['id'=>(int)$r['id']],
                        ['%s','%s'],
                        ['%d']
                    );
                }
            }
        }
        unset($r);
    }

    /* ----------------------------- Purge helper -------------------------- */

    /**
     * Purge: elimina de la cola (status 'pending') los items que ya están
     * en la biblioteca del usuario (match normalizado + fuzzy). IMPORTANTE:
     * - Antes de borrar, empuja estos ítems a un transient efímero para que se
     *   muestren una vez en el frontend como "In Shelf".
     */
    public static function purge_owned_pending_for_user( $user_id ) {
        global $wpdb;
        $confirm_tbl = self::table_name();

        // 1) Traer PENDING del usuario
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, user_id, title, author, normalized_title, normalized_author
                   FROM {$confirm_tbl}
                  WHERE user_id = %d AND status = 'pending'
                  ORDER BY id DESC
                  LIMIT 500",
                (int) $user_id
            ),
            ARRAY_A
        );
        if (empty($rows)) return;

        // 2) Completar campos normalizados en memoria
        self::backfill_normalized_fields($rows, false);

        // 3) Marcar cuáles ya están en la estantería (match normalizado + fuzzy)
        self::batch_mark_in_shelf($rows, $user_id, 0.25);

        // 4) Construir efímeros para los que ya están en shelf
        $ephemerals = [];
        $ids_to_delete = [];
        foreach ($rows as $r) {
            if ( !empty($r['already_in_shelf']) ) {
                $ids_to_delete[] = (int)$r['id'];
                $ephemerals[] = [
                    'title'  => (string)($r['title'] ?? ''),
                    'author' => (string)($r['author'] ?? ''),
                    'year'   => isset($r['matched_book_year']) && $r['matched_book_year'] !== '' ? (int)$r['matched_book_year'] : null,
                ];
            }
        }

        // 5) Si hay efímeros, empujarlos al transient ANTES de borrar
        if ( !empty($ephemerals) ) {
            $key   = 'pol_confirm_ephemeral_' . (int)$user_id;
            $prev  = get_transient($key);
            $prev  = is_array($prev) ? $prev : [];
            $store = array_merge($prev, $ephemerals);
            set_transient($key, $store, 10 * MINUTE_IN_SECONDS);
        }

        // 6) Borrar de la cola
        if (!empty($ids_to_delete)) {
            $placeholders = implode(',', array_fill(0, count($ids_to_delete), '%d'));
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $sql = $wpdb->prepare(
                "DELETE FROM {$confirm_tbl} WHERE id IN ($placeholders)",
                $ids_to_delete
            );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query($sql);
        }
    }
}
