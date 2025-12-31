<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

if ( ! function_exists( 'prs_migrate_remove_legacy_author_columns' ) ) {
        function prs_migrate_remove_legacy_author_columns(): void {
                global $wpdb;

                $table = $wpdb->prefix . 'politeia_books';

                $author_col = $wpdb->get_var(
                        $wpdb->prepare(
                                "SHOW COLUMNS FROM {$table} LIKE %s",
                                'author'
                        )
                );
                if ( $author_col ) {
                        $wpdb->query( "ALTER TABLE {$table} DROP COLUMN author" );
                }

                $norm_author_col = $wpdb->get_var(
                        $wpdb->prepare(
                                "SHOW COLUMNS FROM {$table} LIKE %s",
                                'normalized_author'
                        )
                );
                if ( $norm_author_col ) {
                        $wpdb->query( "ALTER TABLE {$table} DROP COLUMN normalized_author" );
                }

                if ( defined( 'POLITEIA_READING_DB_VERSION' ) ) {
                        update_option( 'politeia_reading_db_version', POLITEIA_READING_DB_VERSION );
                }
        }
}

prs_migrate_remove_legacy_author_columns();
