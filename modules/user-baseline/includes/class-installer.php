<?php
namespace Politeia\UserBaseline;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Installer {
	/**
	 * Return schema definitions for User Baseline tables.
	 *
	 * @return array<string,string>
	 */
	public static function get_schema_sql(): array {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$baselines_table = $wpdb->prefix . 'politeia_user_baselines';
		$baseline_metrics_table = $wpdb->prefix . 'politeia_user_baseline_metrics';

		return array(
			$baselines_table => sprintf(
				'CREATE TABLE %s (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			context VARCHAR(50) NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_user (user_id),
			KEY idx_context (context)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 %s;',
				$baselines_table,
				$charset_collate
			),
			$baseline_metrics_table => sprintf(
				'CREATE TABLE %s (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			baseline_id BIGINT UNSIGNED NOT NULL,
			metric VARCHAR(100) NOT NULL,
			value VARCHAR(100) NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_baseline (baseline_id),
			KEY idx_metric (metric)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 %s;',
				$baseline_metrics_table,
				$charset_collate
			),
		);
	}

	/**
	 * Install or update the User Baseline schema.
	 */
	public static function install(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = implode( "\n", self::get_schema_sql() );
		if ( $sql ) {
			dbDelta( $sql );
		}

		if ( defined( 'POLITEIA_USER_BASELINE_DB_VERSION' ) ) {
			update_option( 'politeia_user_baseline_db_version', POLITEIA_USER_BASELINE_DB_VERSION );
		}
	}
}
