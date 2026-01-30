<?php
namespace Politeia\ReadingPlanner;

if (!defined('ABSPATH')) {
	exit;
}

class Upgrader
{
	/**
	 * Ensure the Reading Plan schema matches the expected version.
	 */
	public static function maybe_upgrade(): void
	{

		self::drop_actual_columns();

		$target_version = defined('POLITEIA_READING_DB_VERSION') ? POLITEIA_READING_DB_VERSION : null;
		$stored_version = get_option('politeia_reading_db_version');

		// If stored version is empty (fresh install or corrupted), force it to 0.0.0 to trigger updates
		if (empty($stored_version)) {
			$stored_version = '0.0.0';
		}

		if ($target_version && version_compare($stored_version, $target_version, '<')) {
			if (version_compare($stored_version, '1.2.0', '<')) {
				self::drop_actual_columns();
			}
			if (version_compare($stored_version, '1.3.0', '<')) {
				self::add_starting_page_column();
			}
			if (version_compare($stored_version, '1.4.0', '<')) {
				self::add_strategy_parameters();
			}
			if (version_compare($stored_version, '1.13.0', '<')) {
				self::drop_legacy_page_columns();
			}
			Installer::install();
			update_option('politeia_reading_db_version', $target_version);
		}
	}

	private static function drop_actual_columns(): void
	{
		$flag = get_option('politeia_reading_plan_drop_actual_columns');
		if ('1' === (string) $flag) {
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

		foreach ($columns as $column) {
			if (self::column_exists($table, $column)) {
				$wpdb->query("ALTER TABLE {$table} DROP COLUMN {$column}");
			}
		}

		update_option('politeia_reading_plan_drop_actual_columns', '1');
	}

	/**
	 * Add starting_page column to plan_goals table for version 1.3.0+
	 */
	private static function add_starting_page_column(): void
	{
		global $wpdb;
		$table = $wpdb->prefix . 'politeia_plan_goals';

		if (!self::column_exists($table, 'starting_page')) {
			$wpdb->query(
				"ALTER TABLE {$table} ADD COLUMN starting_page INT UNSIGNED NULL DEFAULT 1 AFTER subject_id"
			);
		}
	}

	/**
	 * Add strategy parameter columns to plans table for version 1.4.0+
	 */
	private static function add_strategy_parameters(): void
	{
		global $wpdb;
		$table = $wpdb->prefix . 'politeia_plans';

		if (!self::column_exists($table, 'pages_per_session')) {
			$wpdb->query(
				"ALTER TABLE {$table} ADD COLUMN pages_per_session INT UNSIGNED NULL AFTER status"
			);
		}

		if (!self::column_exists($table, 'sessions_per_week')) {
			$wpdb->query(
				"ALTER TABLE {$table} ADD COLUMN sessions_per_week INT UNSIGNED NULL AFTER pages_per_session"
			);
		}
	}

	/**
	 * Drop legacy page columns from planned_sessions table for version 1.5.0+
	 */
	private static function drop_legacy_page_columns(): void
	{
		global $wpdb;
		$table = $wpdb->prefix . 'politeia_planned_sessions';
		$columns = array(
			'planned_start_page',
			'planned_end_page',
			'expected_number_of_pages',
			'expected_duration_minutes',
		);

		foreach ($columns as $column) {
			if (self::column_exists($table, $column)) {
				$wpdb->query("ALTER TABLE {$table} DROP COLUMN {$column}");
			}
		}
	}

	private static function column_exists(string $table, string $column): bool
	{
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
