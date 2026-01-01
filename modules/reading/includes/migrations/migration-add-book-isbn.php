<?php
namespace Politeia\Reading\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

AddBookIsbn::run();

class AddBookIsbn {
        public static function run(): void {
                global $wpdb;

                $books_table = $wpdb->prefix . 'politeia_books';
                $exists = $wpdb->get_var(
                        $wpdb->prepare(
                                "SHOW COLUMNS FROM {$books_table} LIKE %s",
                                'isbn'
                        )
                );

                if ( ! $exists ) {
                        $wpdb->query( "ALTER TABLE {$books_table} ADD COLUMN isbn VARCHAR(32) NULL AFTER cover_url" );
                }

                if ( defined( 'POLITEIA_READING_DB_VERSION' ) ) {
                        update_option( 'politeia_reading_db_version', POLITEIA_READING_DB_VERSION );
                }
        }
}
