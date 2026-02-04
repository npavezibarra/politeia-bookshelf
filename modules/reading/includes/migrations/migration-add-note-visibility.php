<?php
namespace Politeia\Reading\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

AddNoteVisibility::run();

class AddNoteVisibility
{
    public static function run(): void
    {
        global $wpdb;

        $notes_table = $wpdb->prefix . 'politeia_read_ses_notes';
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$notes_table} LIKE %s",
                'visibility'
            )
        );

        if (!$exists) {
            $wpdb->query("
                ALTER TABLE {$notes_table}
                ADD COLUMN visibility ENUM('private','public')
                NOT NULL DEFAULT 'private'
                AFTER note
            ");

            $wpdb->query("
                ALTER TABLE {$notes_table}
                ADD KEY visibility (visibility)
            ");
        }

        if (defined('POLITEIA_READING_DB_VERSION')) {
            update_option('politeia_reading_db_version', POLITEIA_READING_DB_VERSION);
        }
    }
}
