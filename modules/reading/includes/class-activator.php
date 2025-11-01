<?php
namespace Politeia\Reading;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class Activator {
        /**
         * Handle plugin activation.
         */
        public static function activate(): void {
                Installer::install();

                if ( class_exists( '\\Politeia_Post_Reading_Schema' ) ) {
                        \Politeia_Post_Reading_Schema::migrate();
                }

                if ( defined( 'POLITEIA_READING_DB_VERSION' ) ) {
                        update_option( 'politeia_reading_db_version', POLITEIA_READING_DB_VERSION );
                }

                flush_rewrite_rules();
        }
}
