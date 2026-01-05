<?php
namespace Politeia\Reading\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

AddNoteEmotions::run();

class AddNoteEmotions {
	public static function run(): void {
		global $wpdb;

		$notes_table = $wpdb->prefix . 'politeia_read_ses_notes';
		$exists      = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$notes_table} LIKE %s",
				'emotions'
			)
		);

		if ( ! $exists ) {
			$wpdb->query( "ALTER TABLE {$notes_table} ADD COLUMN emotions JSON NULL AFTER note" );
		}

		if ( defined( 'POLITEIA_READING_DB_VERSION' ) ) {
			update_option( 'politeia_reading_db_version', POLITEIA_READING_DB_VERSION );
		}
	}
}
