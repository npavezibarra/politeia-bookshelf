<?php
namespace Politeia\Reading\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

LegacySchema::run();

class LegacySchema {
        public static function run(): void {
                global $wpdb;

                self::maybe_add_rating_column();
                self::maybe_migrate_owning_status();

                $books      = $wpdb->prefix . 'politeia_books';
                $user_books = $wpdb->prefix . 'politeia_user_books';
                $sessions   = $wpdb->prefix . 'politeia_reading_sessions';
                $loans      = $wpdb->prefix . 'politeia_loans';

                self::maybe_add_column( $books, 'cover_url', 'VARCHAR(800) NULL' );
                self::maybe_add_column( $books, 'cover_source', 'VARCHAR(800) NULL' );
                self::maybe_add_column( $user_books, 'cover_reference', 'TEXT NULL AFTER rating' );
                self::maybe_add_column( $user_books, 'deleted_at', 'DATETIME NULL DEFAULT NULL AFTER updated_at' );
                self::maybe_add_column( $sessions, 'deleted_at', 'DATETIME NULL DEFAULT NULL' );
                self::maybe_add_column( $loans, 'deleted_at', 'DATETIME NULL DEFAULT NULL AFTER updated_at' );

                if ( self::column_exists( $user_books, 'cover_attachment_id_user' ) && self::column_exists( $user_books, 'cover_reference' ) ) {
                        $wpdb->query(
                                "UPDATE {$user_books} SET cover_reference = CAST(cover_attachment_id_user AS CHAR)"
                                . " WHERE cover_attachment_id_user IS NOT NULL AND (cover_reference IS NULL OR cover_reference = '')"
                        );
                }

                self::migrate_books_hash_and_unique();
                self::ensure_unique_user_book();
        }

        private static function migrate_books_hash_and_unique(): void {
                global $wpdb;

                $books = $wpdb->prefix . 'politeia_books';

                self::maybe_add_column( $books, 'normalized_title', 'VARCHAR(255) NULL' );
                self::maybe_add_column( $books, 'normalized_author', 'VARCHAR(255) NULL' );
                // LEGACY SAFETY NET -- do not depend on this long-term
                self::maybe_add_column( $books, 'title_author_hash', 'CHAR(64) NULL' ); // LEGACY SAFETY NET -- do not depend on this long-term

                // LEGACY SAFETY NET -- do not depend on this long-term
                $wpdb->query(
                        "
            UPDATE {$books}
            SET title_author_hash = LOWER(SHA2(CONCAT_WS('|', normalized_title, normalized_author), 256)) /* LEGACY SAFETY NET -- do not depend on this long-term */
            WHERE (title_author_hash IS NULL OR title_author_hash = '') /* LEGACY SAFETY NET -- do not depend on this long-term */
              AND normalized_title  IS NOT NULL AND normalized_title  <> ''
              AND normalized_author IS NOT NULL AND normalized_author <> ''
        "
                );

                // LEGACY SAFETY NET -- do not depend on this long-term
                $wpdb->query(
                        "
            UPDATE {$books}
            SET title_author_hash = LOWER(SHA2(CONCAT_WS('|', LOWER(TRIM(title)), LOWER(TRIM(author))), 256)) /* LEGACY SAFETY NET -- do not depend on this long-term */
            WHERE (title_author_hash IS NULL OR title_author_hash = '') /* LEGACY SAFETY NET -- do not depend on this long-term */
        "
                );

                self::dedupe_books_and_fix_links( $books, $wpdb->prefix . 'politeia_user_books' );

                if ( self::column_exists( $books, 'title_author_hash' ) ) { // LEGACY SAFETY NET -- do not depend on this long-term
                        $wpdb->query( "ALTER TABLE {$books} MODIFY title_author_hash CHAR(64) NOT NULL" ); // LEGACY SAFETY NET -- do not depend on this long-term
                }

                self::maybe_add_unique( $books, 'uniq_title_author_hash', array( 'title_author_hash' ) ); // LEGACY SAFETY NET -- do not depend on this long-term
        }

        private static function ensure_unique_user_book(): void {
                global $wpdb;

                $user_books = $wpdb->prefix . 'politeia_user_books';

                $wpdb->query(
                        "
            DELETE t1
            FROM {$user_books} t1
            JOIN {$user_books} t2
              ON t1.user_id = t2.user_id AND t1.book_id = t2.book_id AND t1.id > t2.id
        "
                );

                self::maybe_add_unique( $user_books, 'uniq_user_book', array( 'user_id', 'book_id' ) );
                self::maybe_add_index( $user_books, 'idx_user', array( 'user_id' ) );
                self::maybe_add_index( $user_books, 'idx_book', array( 'book_id' ) );
        }

        private static function dedupe_books_and_fix_links( string $books_table, string $user_books_table ): void {
                global $wpdb;

                $duplicates = (int) $wpdb->get_var( // LEGACY SAFETY NET -- do not depend on this long-term
                        "
            SELECT COUNT(*) FROM (
              SELECT title_author_hash, COUNT(*) c /* LEGACY SAFETY NET -- do not depend on this long-term */
              FROM {$books_table}
              WHERE title_author_hash IS NOT NULL AND title_author_hash <> '' /* LEGACY SAFETY NET -- do not depend on this long-term */
              GROUP BY title_author_hash HAVING c > 1 /* LEGACY SAFETY NET -- do not depend on this long-term */
            ) x
        "
                );

                if ( 0 === $duplicates ) {
                        return;
                }

                $wpdb->query( 'DROP TEMPORARY TABLE IF EXISTS _pol_keep' );
                $wpdb->query( // LEGACY SAFETY NET -- do not depend on this long-term
                        "
            CREATE TEMPORARY TABLE _pol_keep
            SELECT MIN(id) AS keep_id, title_author_hash /* LEGACY SAFETY NET -- do not depend on this long-term */
            FROM {$books_table}
            WHERE title_author_hash IS NOT NULL AND title_author_hash <> '' /* LEGACY SAFETY NET -- do not depend on this long-term */
            GROUP BY title_author_hash /* LEGACY SAFETY NET -- do not depend on this long-term */
        "
                );

                $wpdb->query( // LEGACY SAFETY NET -- do not depend on this long-term
                        "
            UPDATE {$user_books_table} ub
            JOIN {$books_table} b  ON ub.book_id = b.id
            JOIN _pol_keep k       ON b.title_author_hash = k.title_author_hash /* LEGACY SAFETY NET -- do not depend on this long-term */
            SET ub.book_id = k.keep_id
        "
                );

                $wpdb->query( // LEGACY SAFETY NET -- do not depend on this long-term
                        "
            DELETE b
            FROM {$books_table} b
            LEFT JOIN _pol_keep k ON b.id = k.keep_id
            WHERE k.keep_id IS NULL
              AND b.title_author_hash IS NOT NULL AND b.title_author_hash <> '' /* LEGACY SAFETY NET -- do not depend on this long-term */
        "
                );
        }

        private static function maybe_add_rating_column(): void {
                global $wpdb;

                $table  = $wpdb->prefix . 'politeia_user_books';
                $exists = $wpdb->get_var(
                        $wpdb->prepare(
                                "SHOW COLUMNS FROM {$table} LIKE %s",
                                'rating'
                        )
                );

                if ( ! $exists ) {
                        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN rating TINYINT UNSIGNED NULL DEFAULT NULL AFTER book_id" );
                }
        }

        private static function maybe_migrate_owning_status(): void {
                global $wpdb;

                $table = $wpdb->prefix . 'politeia_user_books';

                $row = $wpdb->get_row(
                        $wpdb->prepare(
                                "SHOW COLUMNS FROM {$table} WHERE Field=%s",
                                'owning_status'
                        )
                );

                if ( ! $row ) {
                        return;
                }

                $type         = isset( $row->Type ) ? strtolower( (string) $row->Type ) : '';
                $has_in_shelf = ( false !== strpos( $type, "'in_shelf'" ) );

                if ( $has_in_shelf ) {
                        $wpdb->query( "UPDATE {$table} SET owning_status = NULL WHERE owning_status = 'in_shelf'" );
                        $wpdb->query(
                                "ALTER TABLE {$table} MODIFY owning_status ENUM('borrowed','borrowing','sold','lost') NULL DEFAULT NULL"
                        );
                } elseif ( false === strpos( $type, 'default null' ) ) {
                        $wpdb->query(
                                "ALTER TABLE {$table} MODIFY owning_status ENUM('borrowed','borrowing','sold','lost') NULL DEFAULT NULL"
                        );
                }
        }

        private static function column_exists( string $table, string $column ): bool {
                global $wpdb;

                return (bool) $wpdb->get_var(
                        $wpdb->prepare(
                                'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s LIMIT 1',
                                $table,
                                $column
                        )
                );
        }

        private static function index_exists( string $table, string $index_name ): bool {
                global $wpdb;

                return (bool) $wpdb->get_var(
                        $wpdb->prepare(
                                'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s LIMIT 1',
                                $table,
                                $index_name
                        )
                );
        }

        private static function maybe_add_column( string $table, string $column, string $definition ): void {
                if ( ! self::column_exists( $table, $column ) ) {
                        global $wpdb;

                        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN {$column} {$definition}" );
                }
        }

        private static function maybe_add_unique( string $table, string $name, array $columns ): void {
                if ( self::index_exists( $table, $name ) ) {
                        return;
                }

                global $wpdb;

                $columns_sql = implode( ',', array_map( static fn( $col ) => "`{$col}`", $columns ) );
                $wpdb->query( "ALTER TABLE {$table} ADD UNIQUE KEY `{$name}` ({$columns_sql})" );
        }

        private static function maybe_add_index( string $table, string $name, array $columns ): void {
                if ( self::index_exists( $table, $name ) ) {
                        return;
                }

                global $wpdb;

                $columns_sql = implode( ',', array_map( static fn( $col ) => "`{$col}`", $columns ) );
                $wpdb->query( "ALTER TABLE {$table} ADD INDEX `{$name}` ({$columns_sql})" );
        }
}
