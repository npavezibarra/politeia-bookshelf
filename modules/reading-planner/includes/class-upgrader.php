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
		self::maybe_drop_actual_columns();

		$target_version = defined( 'POLITEIA_READING_PLAN_DB_VERSION' ) ? POLITEIA_READING_PLAN_DB_VERSION : null;
		$stored_version = get_option( 'politeia_reading_plan_db_version' );

		if ( $target_version && $stored_version !== $target_version ) {
			if ( $stored_version && version_compare( $stored_version, '1.2.0', '<' ) ) {
				self::drop_actual_columns();
			}
			Installer::install();
			update_option( 'politeia_reading_plan_db_version', $target_version );
		}
	}

	private static function maybe_drop_actual_columns(): void {
		$flag = get_option( 'politeia_reading_plan_drop_actual_columns' );
		if ( '1' === (string) $flag ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'politeia_planned_sessions';
		$columns = array(
			'actual_start_datetime',
			'actual_end_datetime',
			'actual_start_page',
			'actual_end_page',
		);

		foreach ( $columns as $column ) {
			if ( self::column_exists( $table, $column ) ) {
				$wpdb->query( "ALTER TABLE {$table} DROP COLUMN {$column}" );
			}
		}

		update_option( 'politeia_reading_plan_drop_actual_columns', '1' );
	}

	private static function column_exists( string $table, string $column ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s LIMIT 1',
				$table,
				$column
			)
		);
	}
}
