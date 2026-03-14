<?php
namespace Politeia\Reading\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

AddSessionInsertType::run();

class AddSessionInsertType
{
    public static function run(): void
    {
        global $wpdb;

        $sessions_table = $wpdb->prefix . 'politeia_reading_sessions';
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$sessions_table} LIKE %s",
                'insert_type'
            )
        );

        if (!$exists) {
            $wpdb->query("
                ALTER TABLE {$sessions_table}
                ADD COLUMN insert_type ENUM('manual','recorder','automatic_stop')
                NOT NULL DEFAULT 'recorder'
                AFTER end_page
            ");
        }

        // Backfill safety: ensure no NULL or unexpected values exist.
        $wpdb->query("
            UPDATE {$sessions_table}
            SET insert_type = 'recorder'
            WHERE insert_type IS NULL OR insert_type NOT IN ('manual','recorder','automatic_stop')
        ");

        if (defined('POLITEIA_READING_DB_VERSION')) {
            update_option('politeia_reading_db_version', POLITEIA_READING_DB_VERSION);
        }
    }
}
