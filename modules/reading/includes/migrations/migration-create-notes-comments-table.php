<?php
namespace Politeia\Reading\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

CreateNotesCommentsTable::run();

class CreateNotesCommentsTable
{
    public static function run(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'politeia_notes_comments';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			note_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			content TEXT NOT NULL,
			parent_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL,
			status ENUM('published','hidden','deleted') NOT NULL DEFAULT 'published',
			PRIMARY KEY (id),
			KEY note_id (note_id),
			KEY user_id (user_id),
			KEY parent_id (parent_id),
			KEY created_at (created_at)
		) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
