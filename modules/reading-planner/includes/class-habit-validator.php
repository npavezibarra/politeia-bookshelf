<?php
namespace Politeia\ReadingPlanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Habit_Validator {
	private const CRON_HOOK = 'politeia_reading_plan_habit_validate';

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'run' ) );
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK );
		}
	}

	public static function run(): void {
		global $wpdb;
		$plans_table    = $wpdb->prefix . 'politeia_plans';
		$goals_table    = $wpdb->prefix . 'politeia_plan_goals';
		$sessions_table = $wpdb->prefix . 'politeia_planned_sessions';
		$reading_table  = $wpdb->prefix . 'politeia_reading_sessions';

		$rows = $wpdb->get_results(
			"SELECT ps.id, ps.plan_id, ps.planned_start_datetime, ps.planned_end_datetime,
				ps.expected_duration_minutes, ps.expected_number_of_pages, p.user_id
			FROM {$sessions_table} ps
			INNER JOIN {$plans_table} p ON p.id = ps.plan_id
			INNER JOIN {$goals_table} g ON g.plan_id = p.id
			WHERE g.goal_kind = 'habit'
				AND ps.status = 'planned'",
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return;
		}

		$timezone = wp_timezone();
		$now_gmt  = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) );

		foreach ( $rows as $row ) {
			$expected_minutes = isset( $row['expected_duration_minutes'] ) ? (int) $row['expected_duration_minutes'] : 0;
			$expected_pages   = isset( $row['expected_number_of_pages'] ) ? (int) $row['expected_number_of_pages'] : 0;
			if ( $expected_minutes <= 0 || $expected_pages <= 0 ) {
				continue;
			}

			$start_dt = ! empty( $row['planned_start_datetime'] )
				? date_create_immutable( $row['planned_start_datetime'], $timezone )
				: null;
			$end_dt = ! empty( $row['planned_end_datetime'] )
				? date_create_immutable( $row['planned_end_datetime'], $timezone )
				: null;

			if ( ! $start_dt || ! $end_dt ) {
				continue;
			}

			$start_gmt = $start_dt->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
			$end_gmt   = $end_dt->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );

			if ( $end_gmt > $now_gmt ) {
				continue;
			}

			$totals = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						SUM(TIMESTAMPDIFF(SECOND, start_time, end_time)) AS duration_seconds,
						SUM(CASE WHEN end_page >= start_page THEN end_page - start_page + 1 ELSE 0 END) AS pages_read
					FROM {$reading_table}
					WHERE user_id = %d
						AND deleted_at IS NULL
						AND end_time IS NOT NULL
						AND start_time >= %s
						AND start_time <= %s",
					(int) $row['user_id'],
					$start_gmt,
					$end_gmt
				),
				ARRAY_A
			);

			$duration_sec = isset( $totals['duration_seconds'] ) ? (int) $totals['duration_seconds'] : 0;
			$pages_read   = isset( $totals['pages_read'] ) ? (int) $totals['pages_read'] : 0;

			if ( $duration_sec >= ( $expected_minutes * 60 ) && $pages_read >= $expected_pages ) {
				$wpdb->update(
					$sessions_table,
					array( 'status' => 'accomplished' ),
					array( 'id' => (int) $row['id'] ),
					array( '%s' ),
					array( '%d' )
				);
			}
		}
	}
}

Habit_Validator::init();
