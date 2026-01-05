<?php
namespace Politeia\ReadingPlanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Upgrader {
	/**
	 * Ensure the Reading Plan schema matches the expected version.
	 */
	public static function maybe_upgrade(): void {
		$target_version = defined( 'POLITEIA_READING_PLAN_DB_VERSION' ) ? POLITEIA_READING_PLAN_DB_VERSION : null;
		$stored_version = get_option( 'politeia_reading_plan_db_version' );

		if ( $target_version && $stored_version !== $target_version ) {
			Installer::install();
			update_option( 'politeia_reading_plan_db_version', $target_version );
		}
	}
}
