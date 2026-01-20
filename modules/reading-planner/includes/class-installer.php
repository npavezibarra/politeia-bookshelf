<?php
namespace Politeia\ReadingPlanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Installer {
	/**
	 * Return schema definitions for Reading Plan tables.
	 *
	 * @return array<string,string>
	 */
	public static function get_schema_sql(): array {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$plans_table     = $wpdb->prefix . 'politeia_plans';
		$plan_goals_table = $wpdb->prefix . 'politeia_plan_goals';
		$plan_subjects_table = $wpdb->prefix . 'politeia_plan_subjects';
		$plan_participants_table = $wpdb->prefix . 'politeia_plan_participants';
		$planned_sessions_table = $wpdb->prefix . 'politeia_planned_sessions';

		return array(
			$plans_table => sprintf(
				'CREATE TABLE %s (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(255) NOT NULL,
			plan_type VARCHAR(50) NOT NULL,
			status VARCHAR(50) NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_user (user_id),
			KEY idx_status (status)
		) ENGINE=InnoDB %s;',
				$plans_table,
				$charset_collate
			),
			$plan_goals_table => sprintf(
				'CREATE TABLE %s (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			plan_id BIGINT UNSIGNED NOT NULL,
			goal_kind VARCHAR(50) NOT NULL,
			metric VARCHAR(50) NOT NULL,
			target_value INT UNSIGNED NOT NULL,
			period VARCHAR(50) NOT NULL,
			book_id BIGINT UNSIGNED NULL,
			subject_id BIGINT UNSIGNED NULL,
			PRIMARY KEY  (id),
			KEY idx_plan (plan_id),
			KEY idx_book (book_id),
			KEY idx_subject (subject_id)
		) ENGINE=InnoDB %s;',
				$plan_goals_table,
				$charset_collate
			),
			$plan_subjects_table => sprintf(
				'CREATE TABLE %s (
			plan_id BIGINT UNSIGNED NOT NULL,
			subject_id BIGINT UNSIGNED NOT NULL,
			role VARCHAR(50) NOT NULL,
			PRIMARY KEY  (plan_id, subject_id),
			KEY idx_subject (subject_id),
			KEY idx_role (role)
		) ENGINE=InnoDB %s;',
				$plan_subjects_table,
				$charset_collate
			),
			$plan_participants_table => sprintf(
				'CREATE TABLE %s (
			plan_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			role VARCHAR(50) NOT NULL,
			notify_on VARCHAR(50) NOT NULL,
			PRIMARY KEY  (plan_id, user_id),
			KEY idx_user (user_id),
			KEY idx_role (role),
			KEY idx_notify (notify_on)
		) ENGINE=InnoDB %s;',
				$plan_participants_table,
				$charset_collate
			),
			$planned_sessions_table => sprintf(
				'CREATE TABLE %s (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			plan_id BIGINT UNSIGNED NOT NULL,
			planned_start_datetime DATETIME NOT NULL,
			planned_end_datetime DATETIME NOT NULL,
			planned_start_page INT UNSIGNED NULL,
			planned_end_page INT UNSIGNED NULL,
			expected_number_of_pages INT UNSIGNED NULL,
			expected_duration_minutes INT UNSIGNED NULL,
			status VARCHAR(50) NOT NULL,
			previous_session_id BIGINT UNSIGNED NULL,
			comment TEXT NULL,
			PRIMARY KEY  (id),
			KEY idx_plan (plan_id),
			KEY idx_status (status),
			KEY idx_previous (previous_session_id)
		) ENGINE=InnoDB %s;',
				$planned_sessions_table,
				$charset_collate
			),
		);
	}

	/**
	 * Install or update the Reading Plan schema.
	 */
	public static function install(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( self::get_schema_sql() as $table => $sql ) {
			dbDelta( $sql );
		}

		if ( defined( 'POLITEIA_READING_PLAN_DB_VERSION' ) ) {
			update_option( 'politeia_reading_plan_db_version', POLITEIA_READING_PLAN_DB_VERSION );
		}
	}
}
