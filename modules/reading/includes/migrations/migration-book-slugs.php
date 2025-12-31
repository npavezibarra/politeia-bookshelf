<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

if ( ! function_exists( 'prs_migrate_book_slugs' ) ) {
        function prs_migrate_book_slugs(): void {
                global $wpdb;

                $books_table = $wpdb->prefix . 'politeia_books';
                $slugs_table = $wpdb->prefix . 'politeia_book_slugs';

                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                $charset_collate = $wpdb->get_charset_collate();

                $sql = "CREATE TABLE {$slugs_table} (
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
                ) {$charset_collate};";
                dbDelta( $sql );

                $existing = $wpdb->get_results( "SELECT id, book_id, slug, is_primary FROM {$slugs_table}", ARRAY_A );
                $used = array();
                $primary_by_book = array();
                foreach ( (array) $existing as $row ) {
                        $slug = isset( $row['slug'] ) ? (string) $row['slug'] : '';
                        $book_id = isset( $row['book_id'] ) ? (int) $row['book_id'] : 0;
                        if ( '' === $slug || $book_id <= 0 ) {
                                continue;
                        }
                        $used[ $slug ] = $book_id;
                        if ( ! empty( $row['is_primary'] ) ) {
                                $primary_by_book[ $book_id ] = $slug;
                        }
                }

                $books = $wpdb->get_results(
                        "SELECT id, title, year, slug FROM {$books_table} ORDER BY id ASC",
                        ARRAY_A
                );

                foreach ( (array) $books as $book ) {
                        $book_id = isset( $book['id'] ) ? (int) $book['id'] : 0;
                        if ( $book_id <= 0 ) {
                                continue;
                        }

                        $title = isset( $book['title'] ) ? (string) $book['title'] : '';
                        $year  = isset( $book['year'] ) && $book['year'] !== null && $book['year'] !== '' ? (int) $book['year'] : null;
                        $current_slug = isset( $book['slug'] ) ? (string) $book['slug'] : '';

                        $base_slug = sanitize_title( $title );
                        $new_slug = $base_slug;
                        if ( '' === $new_slug ) {
                                $new_slug = $current_slug;
                        }

                        if ( '' !== $new_slug && isset( $used[ $new_slug ] ) && (int) $used[ $new_slug ] !== $book_id ) {
                                if ( $year ) {
                                        $new_slug = $base_slug . '-' . $year;
                                }
                        }

                        if ( '' !== $new_slug && isset( $used[ $new_slug ] ) && (int) $used[ $new_slug ] !== $book_id ) {
                                $new_slug = $current_slug;
                        }

                        if ( $new_slug && $current_slug !== $new_slug ) {
                                $wpdb->update(
                                        $books_table,
                                        array( 'slug' => $new_slug ),
                                        array( 'id' => $book_id ),
                                        array( '%s' ),
                                        array( '%d' )
                                );
                        }

                        if ( $new_slug ) {
                                $primary = isset( $primary_by_book[ $book_id ] ) ? $primary_by_book[ $book_id ] : '';
                                if ( $primary !== $new_slug ) {
                                        $wpdb->update(
                                                $slugs_table,
                                                array( 'is_primary' => 0 ),
                                                array( 'book_id' => $book_id ),
                                                array( '%d' ),
                                                array( '%d' )
                                        );

                                        $existing_id = $wpdb->get_var(
                                                $wpdb->prepare(
                                                        "SELECT id FROM {$slugs_table} WHERE slug = %s LIMIT 1",
                                                        $new_slug
                                                )
                                        );
                                        if ( $existing_id ) {
                                                $wpdb->update(
                                                        $slugs_table,
                                                        array(
                                                                'book_id'    => $book_id,
                                                                'is_primary' => 1,
                                                                'updated_at' => current_time( 'mysql' ),
                                                        ),
                                                        array( 'id' => (int) $existing_id ),
                                                        array( '%d', '%d', '%s' ),
                                                        array( '%d' )
                                                );
                                        } else {
                                                $wpdb->insert(
                                                        $slugs_table,
                                                        array(
                                                                'book_id'    => $book_id,
                                                                'slug'       => $new_slug,
                                                                'is_primary' => 1,
                                                                'created_at' => current_time( 'mysql' ),
                                                                'updated_at' => current_time( 'mysql' ),
                                                        ),
                                                        array( '%d', '%s', '%d', '%s', '%s' )
                                                );
                                        }

                                        $primary_by_book[ $book_id ] = $new_slug;
                                        $used[ $new_slug ] = $book_id;
                                }
                        }

                        if ( $current_slug && $current_slug !== $new_slug ) {
                                if ( ! isset( $used[ $current_slug ] ) || (int) $used[ $current_slug ] === $book_id ) {
                                        $wpdb->insert(
                                                $slugs_table,
                                                array(
                                                        'book_id'    => $book_id,
                                                        'slug'       => $current_slug,
                                                        'is_primary' => 0,
                                                        'created_at' => current_time( 'mysql' ),
                                                        'updated_at' => current_time( 'mysql' ),
                                                ),
                                                array( '%d', '%s', '%d', '%s', '%s' )
                                        );
                                        $used[ $current_slug ] = $book_id;
                                }
                        }
                }

                if ( defined( 'POLITEIA_READING_DB_VERSION' ) ) {
                        update_option( 'politeia_reading_db_version', POLITEIA_READING_DB_VERSION );
                }
        }
}

prs_migrate_book_slugs();
