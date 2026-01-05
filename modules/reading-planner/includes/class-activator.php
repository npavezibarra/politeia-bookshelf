<?php
namespace Politeia\ReadingPlanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activator {
	public static function activate(): void {
		Installer::install();

		if ( defined( 'POLITEIA_READING_PLAN_DB_VERSION' ) ) {
			update_option( 'politeia_reading_plan_db_version', POLITEIA_READING_PLAN_DB_VERSION );
		}
	}
}
