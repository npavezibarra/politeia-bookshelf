<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

if ( ! function_exists( 'prs_migrate_remove_title_author_hash' ) ) {
        function prs_migrate_remove_title_author_hash(): void {
                global $wpdb;

                $books_table = $wpdb->prefix . 'politeia_books';
                $has_column  = (bool) $wpdb->get_var(
                        $wpdb->prepare(
                                'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s
                                 LIMIT 1',
                                $books_table,
                                'title_author_hash'
                        )
                );

                if ( ! $has_column ) {
                        if ( defined( 'POLITEIA_READING_DB_VERSION' ) ) {
                                update_option( 'politeia_reading_db_version', POLITEIA_READING_DB_VERSION );
                        }
                        return;
                }

                $indexes = $wpdb->get_col(
                        $wpdb->prepare(
                                'SELECT DISTINCT INDEX_NAME
                                 FROM INFORMATION_SCHEMA.STATISTICS
                                 WHERE TABLE_SCHEMA = DATABASE()
                                   AND TABLE_NAME = %s
                                   AND COLUMN_NAME = %s',
                                $books_table,
                                'title_author_hash'
                        )
                );

                if ( $indexes ) {
                        foreach ( $indexes as $index_name ) {
                                if ( 'PRIMARY' === $index_name ) {
                                        continue;
                                }
                                $wpdb->query( "ALTER TABLE {$books_table} DROP INDEX `{$index_name}`" );
                        }
                }

                $wpdb->query( "ALTER TABLE {$books_table} DROP COLUMN title_author_hash" );

                if ( defined( 'POLITEIA_READING_DB_VERSION' ) ) {
                        update_option( 'politeia_reading_db_version', POLITEIA_READING_DB_VERSION );
                }
        }
}

prs_migrate_remove_title_author_hash();
