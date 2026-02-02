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

		$target_version = defined('POLITEIA_READING_PLAN_DB_VERSION') ? POLITEIA_READING_PLAN_DB_VERSION : null;
		$stored_version = get_option('politeia_reading_plan_db_version');

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
			if (version_compare($stored_version, '1.14.0', '<')) {
				self::drop_plan_helper_columns();
			}

			Installer::install(); // Ensure table exists before migration

			if (version_compare($stored_version, '1.15.0', '<')) {
				self::migrate_to_1_15_0();
			}

			// 1.16.0 adds plan_finish_book table (handled generally by Installer::install)
			// check not strictly needed unless specific migration logic exists


			if (version_compare($stored_version, '1.16.1', '<')) {
				self::migrate_to_1_16_1();
			}


			if (version_compare($stored_version, '1.16.2', '<')) {
				self::cleanup_1_16_2();
			}

			update_option('politeia_reading_plan_db_version', $target_version);
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
	 * Drop pages_per_session and sessions_per_week from plans table for version 1.14.0+
	 */
	private static function drop_plan_helper_columns(): void
	{
		global $wpdb;
		$table = $wpdb->prefix . 'politeia_plans';
		$columns = array(
			'pages_per_session',
			'sessions_per_week',
		);

		foreach ($columns as $column) {
			if (self::column_exists($table, $column)) {
				$wpdb->query("ALTER TABLE {$table} DROP COLUMN {$column}");
			}
		}
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
		// NO-OP: Columns are deprecated and removed in 1.14.0+
		// We keep method for history or re-add logic if older version, but since we drop them later, simplest is to do nothing if upgrading to latest.
		// However, adhering to linear upgrade path:
		// If we are upgrading from 1.0 to 1.14:
		// 1. < 1.4 creates colums.
		// 2. < 1.14 drops them.
		// So we keep this method logic intact.

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

	/**
	 * Migrate habit data to wp_politeia_plan_habit table for version 1.15.0+
	 */
	private static function migrate_to_1_15_0(): void
	{
		global $wpdb;

		$plans_table = $wpdb->prefix . 'politeia_plans';
		$metrics_table = $wpdb->prefix . 'politeia_user_baseline_metrics';
		$baselines_table = $wpdb->prefix . 'politeia_user_baselines';
		$plan_habit_table = $wpdb->prefix . 'politeia_plan_habit';

		// Get all habit plans
		$plans = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, user_id FROM $plans_table WHERE plan_type = %s",
				'habit'
			)
		);

		if (empty($plans)) {
			return;
		}

		$default_duration = (int) get_option('default_habit_duration_days', 66);

		foreach ($plans as $plan) {
			// Get metrics for the user
			// We assume the latest baseline is relevant, or we pick the one associated with the user.
			// Since baseline_id is not directly linked to plan, we look up by user_id.
			// There might be multiple baselines. We'll take the latest one.
			$baseline_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM $baselines_table WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
					$plan->user_id
				)
			);

			if (!$baseline_id) {
				continue;
			}

			$start_pages = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT value FROM $metrics_table WHERE baseline_id = %d AND metric = %s",
					$baseline_id,
					'habit_start_pages'
				)
			);

			$finish_pages = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT value FROM $metrics_table WHERE baseline_id = %d AND metric = %s",
					$baseline_id,
					'habit_end_pages'
				)
			);

			// Insert into new table, ignore if already exists (idempotency)
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO $plan_habit_table (plan_id, start_page_amount, finish_page_amount, duration_days) VALUES (%d, %d, %d, %d)",
					$plan->id,
					$start_pages,
					$finish_pages,
					$default_duration
				)
			);
		}
	}

	/**
	 * Migrate finish book data to wp_politeia_plan_finish_book for version 1.16.1+
	 */
	private static function migrate_to_1_16_1(): void
	{
		global $wpdb;
		$plans_table = $wpdb->prefix . 'politeia_plans';
		$goals_table = $wpdb->prefix . 'politeia_plan_goals';
		$finish_book_table = $wpdb->prefix . 'politeia_plan_finish_book';

		// Query plans that are finish_book (or legacy complete_books via goal_kind)
		// We join with goals to get book_id and starting_page
		$sql = "SELECT p.id as plan_id, g.book_id, g.starting_page
				FROM {$plans_table} p
				INNER JOIN {$goals_table} g ON p.id = g.plan_id
				WHERE g.goal_kind = 'complete_books' OR p.plan_type = 'finish_book'
				AND g.book_id IS NOT NULL";

		$plans_data = $wpdb->get_results($sql);

		if ($plans_data) {
			foreach ($plans_data as $row) {
				$plan_id = (int) $row->plan_id;
				$book_id = (int) $row->book_id;
				$start_page = isset($row->starting_page) ? (int) $row->starting_page : 1;

				if ($start_page < 1) {
					$start_page = 1;
				}

				// Idempotent insert
				$wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO {$finish_book_table} 
						 (plan_id, book_id, start_page) 
						 VALUES (%d, %d, %d)",
						$plan_id,
						$book_id,
						$start_page
					)
				);
			}
		}
	}

	/**
	 * Remove legacy habit metrics from baseline metrics for version 1.16.2+
	 */
	private static function cleanup_1_16_2(): void
	{
		global $wpdb;
		$metrics_table = $wpdb->prefix . 'politeia_user_baseline_metrics';

		// Metrics to delete
		$metrics_to_delete = array(
			'habit_start_pages',
			'habit_end_pages',
			'habit_start_minutes',
			'habit_end_minutes',
		);

		foreach ($metrics_to_delete as $metric) {
			$wpdb->delete(
				$metrics_table,
				array('metric' => $metric),
				array('%s')
			);
		}
	}
}
