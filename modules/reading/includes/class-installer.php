<?php
namespace Politeia\Reading;

if (!defined('ABSPATH')) {
        exit;
}

class Installer
{
        /**
         * Return the schema definition for all plugin tables.
         *
         * @return array<string,string>
         */
        public static function get_schema_sql(): array
        {
                global $wpdb;

                $charset_collate = $wpdb->get_charset_collate();
                $books_table = $wpdb->prefix . 'politeia_books';
                $book_slugs_table = $wpdb->prefix . 'politeia_book_slugs';
                $user_books_table = $wpdb->prefix . 'politeia_user_books';
                $sessions_table = $wpdb->prefix . 'politeia_reading_sessions';
                $session_notes_table = $wpdb->prefix . 'politeia_read_ses_notes';
                $loans_table = $wpdb->prefix . 'politeia_loans';
                $authors_table = $wpdb->prefix . 'politeia_authors';
                $book_authors_table = $wpdb->prefix . 'politeia_book_authors';

                return array(
                        $books_table => sprintf(
                                'CREATE TABLE %s (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            year SMALLINT UNSIGNED NULL,
            cover_attachment_id BIGINT UNSIGNED NULL,
            cover_url VARCHAR(800) NULL,
            isbn VARCHAR(32) NULL,
            slug VARCHAR(255) NULL,
            normalized_title VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_title (title),
            UNIQUE KEY uniq_slug (slug)
        ) %s;',
                                $books_table,
                                $charset_collate
                        ),
                        $book_slugs_table => sprintf(
                                'CREATE TABLE %s (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            book_id BIGINT UNSIGNED NOT NULL,
            slug VARCHAR(255) NOT NULL,
            is_primary TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_slug (slug),
            KEY idx_book (book_id),
            KEY idx_primary (book_id, is_primary)
        ) %s;',
                                $book_slugs_table,
                                $charset_collate
                        ),
                        $user_books_table => sprintf(
                                'CREATE TABLE %s (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            book_id BIGINT UNSIGNED NOT NULL,
            rating TINYINT UNSIGNED NULL DEFAULT NULL,
            reading_status ENUM(\'not_started\',\'started\',\'finished\') NOT NULL DEFAULT \'not_started\',
            owning_status ENUM(\'in_shelf\',\'lost\',\'borrowed\',\'borrowing\',\'sold\') NOT NULL DEFAULT \'in_shelf\',
            type_book ENUM(\'p\',\'d\') NULL DEFAULT NULL,
            pages INT UNSIGNED NULL,
            purchase_date DATE NULL,
            purchase_channel ENUM(\'online\',\'store\') NULL,
            purchase_place VARCHAR(255) NULL,
            counterparty_name VARCHAR(255) NULL,
            counterparty_email VARCHAR(190) NULL,
            cover_reference TEXT NULL,
            cover_url VARCHAR(800) NULL,
            cover_source VARCHAR(800) NULL,
            language VARCHAR(50) NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_user_book (user_id, book_id),
            KEY idx_user (user_id),
            KEY idx_book (book_id)
        ) %s;',
                                $user_books_table,
                                $charset_collate
                        ),
                        $sessions_table => sprintf(
                                'CREATE TABLE %s (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            user_book_id BIGINT UNSIGNED NOT NULL,
            start_time DATETIME NOT NULL,
            end_time DATETIME NOT NULL,
            start_page INT UNSIGNED NOT NULL,
            end_page INT UNSIGNED NOT NULL,
            chapter_name VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_user_book_time (user_id, user_book_id, start_time),
            KEY user_book_id (user_book_id)
        ) %s;',
                                $sessions_table,
                                $charset_collate
                        ),
                        $session_notes_table => sprintf(
                                'CREATE TABLE %s (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            rs_id BIGINT UNSIGNED NOT NULL,
            book_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            note TEXT NOT NULL,
            emotions JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY rs_id (rs_id),
            KEY book_id (book_id),
            KEY user_id (user_id)
        ) %s;',
                                $session_notes_table,
                                $charset_collate
                        ),
                        $loans_table => sprintf(
                                'CREATE TABLE %s (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            book_id BIGINT UNSIGNED NOT NULL,
            counterparty_name VARCHAR(255) NULL,
            counterparty_email VARCHAR(190) NULL,
            start_date DATETIME NOT NULL,
            end_date DATETIME NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_user_book (user_id, book_id),
            KEY idx_active (user_id, book_id, end_date)
        ) %s;',
                                $loans_table,
                                $charset_collate
                        ),
                        $authors_table => sprintf(
                                'CREATE TABLE %s (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            display_name VARCHAR(255) NOT NULL,
            normalized_name VARCHAR(255) NULL,
            name_hash CHAR(64) NOT NULL,
            slug VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_name_hash (name_hash),
            UNIQUE KEY uniq_slug (slug)
        ) %s;',
                                $authors_table,
                                $charset_collate
                        ),
                        $book_authors_table => sprintf(
                                'CREATE TABLE %s (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            book_id BIGINT UNSIGNED NOT NULL,
            author_id BIGINT UNSIGNED NOT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_book_author (book_id, author_id),
            KEY idx_book (book_id),
            KEY idx_author (author_id)
        ) %s;',
                                $book_authors_table,
                                $charset_collate
                        ),
                );
        }

        /**
         * Install or update the plugin schema.
         */
        public static function install(): void
        {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';

                foreach (self::get_schema_sql() as $table => $sql) {
                        error_log(sprintf('Politeia Reading dbDelta executing for table: %s', $table));
                        dbDelta($sql);
                }
        }
}
