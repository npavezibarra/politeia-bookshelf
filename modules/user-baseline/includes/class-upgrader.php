<?php
namespace Politeia\UserBaseline;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Upgrader {
	/**
	 * Ensure the User Baseline schema matches the expected version.
	 */
	public static function maybe_upgrade(): void {
		$target_version = defined( 'POLITEIA_USER_BASELINE_DB_VERSION' ) ? POLITEIA_USER_BASELINE_DB_VERSION : null;
		$stored_version = get_option( 'politeia_user_baseline_db_version' );

		if ( $target_version && $stored_version !== $target_version ) {
			Installer::install();
		}
	}
}
