<?php
namespace Politeia\Reading;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class Migrations {
        private const OPTION_PREFIX = 'politeia_reading_migration_';

        /**
         * Execute each migration file in the migrations directory once.
         */
        public static function run(): void {
                $directory = trailingslashit( POLITEIA_READING_PATH . 'includes/migrations' );

                if ( ! is_dir( $directory ) ) {
                        return;
                }

                $files = glob( $directory . '*.php' );
                if ( ! $files ) {
                        return;
                }

                sort( $files );

                foreach ( $files as $file ) {
                        if ( 'class-migrations.php' === basename( $file ) ) {
                                continue;
                        }

                        $option_key = self::OPTION_PREFIX . md5( basename( $file ) );
                        if ( get_option( $option_key ) ) {
                                continue;
                        }

                        include $file;

                        update_option( $option_key, time() );
                }
        }
}
