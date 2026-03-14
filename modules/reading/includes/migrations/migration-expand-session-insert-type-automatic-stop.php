<?php
namespace Politeia\Reading\Migrations;

if (!defined('ABSPATH')) {
	exit;
}

ExpandSessionInsertTypeAutomaticStop::run();

class ExpandSessionInsertTypeAutomaticStop
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
			return;
		}

		// Expand enum to include automatic_stop for legacy installs.
		$wpdb->query(
			"ALTER TABLE {$sessions_table}
			 MODIFY insert_type ENUM('manual','recorder','automatic_stop')
			 NOT NULL DEFAULT 'recorder'"
		);

		// Backfill safety: ensure no NULL or unexpected values exist.
		$wpdb->query(
			"UPDATE {$sessions_table}
			 SET insert_type = 'recorder'
			 WHERE insert_type IS NULL OR insert_type NOT IN ('manual','recorder','automatic_stop')"
		);
	}
}

