<?php
namespace Politeia\Reading;

if (!defined('ABSPATH')) {
        exit;
}

class Upgrader
{
        /**
         * Ensure the plugin schema matches the expected version.
         */
        public static function maybe_upgrade(): void
        {
                $target_version = defined('POLITEIA_READING_DB_VERSION') ? POLITEIA_READING_DB_VERSION : null;
                $stored_version = get_option('politeia_reading_db_version');

                if (empty($stored_version)) {
                        $stored_version = '0.0.0';
                }

                if ($target_version && $stored_version !== $target_version) {
                        if (version_compare($stored_version, '1.14.1', '<')) {
                                require_once POLITEIA_READING_PATH . 'includes/migrations/upgrade-1.3.0-to-1.4.0.php';
                        }

                        Installer::install();
                        update_option('politeia_reading_db_version', $target_version);
                }

                if (class_exists(Migrations::class)) {
                        Migrations::run();
                }
        }
}
