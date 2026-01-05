<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create a user baseline snapshot and related metrics.
 *
 * @param int    $user_id WordPress user ID.
 * @param array  $metrics Associative array of metric => value pairs.
 * @param string $context Baseline context (plan_acceptance, initial_evaluation, etc.).
 * @return int Baseline ID on success, 0 on failure.
 */
function create_user_baseline( int $user_id, array $metrics, string $context ): int {
	global $wpdb;

	if ( $user_id <= 0 || empty( $metrics ) || '' === trim( $context ) ) {
		return 0;
	}

	$user = get_user_by( 'id', $user_id );
	if ( ! $user ) {
		return 0;
	}

	$context = sanitize_text_field( $context );
	if ( '' === $context ) {
		return 0;
	}

	$baseline_table = $wpdb->prefix . 'politeia_user_baselines';
	$metrics_table  = $wpdb->prefix . 'politeia_user_baseline_metrics';
	$now            = current_time( 'mysql' );

	$transaction_started = false;
	if ( false !== $wpdb->query( 'START TRANSACTION' ) ) {
		$transaction_started = true;
	}

	$inserted = $wpdb->insert(
		$baseline_table,
		array(
			'user_id'    => $user_id,
			'context'    => $context,
			'created_at' => $now,
		),
		array( '%d', '%s', '%s' )
	);

	if ( false === $inserted ) {
		if ( $transaction_started ) {
			$wpdb->query( 'ROLLBACK' );
		}
		return 0;
	}

	$baseline_id = (int) $wpdb->insert_id;
	if ( $baseline_id <= 0 ) {
		if ( $transaction_started ) {
			$wpdb->query( 'ROLLBACK' );
		}
		return 0;
	}

	$inserted_metrics = 0;
	foreach ( $metrics as $metric => $value ) {
		$metric_key = sanitize_text_field( (string) $metric );
		$value_text = sanitize_text_field( (string) $value );

		if ( '' === $metric_key ) {
			continue;
		}

		$metric_inserted = $wpdb->insert(
			$metrics_table,
			array(
				'baseline_id' => $baseline_id,
				'metric'      => $metric_key,
				'value'       => $value_text,
			),
			array( '%d', '%s', '%s' )
		);

		if ( false === $metric_inserted ) {
			if ( $transaction_started ) {
				$wpdb->query( 'ROLLBACK' );
			}
			return 0;
		}

		$inserted_metrics++;
	}

	if ( 0 === $inserted_metrics ) {
		if ( $transaction_started ) {
			$wpdb->query( 'ROLLBACK' );
		}
		return 0;
	}

	if ( $transaction_started ) {
		$wpdb->query( 'COMMIT' );
	}

	return $baseline_id;
}

/**
 * Fetch the latest baseline and its metrics for a user.
 *
 * @param int $user_id WordPress user ID.
 * @return array<string,mixed>
 */
function get_latest_user_baseline( int $user_id ): array {
	global $wpdb;

	if ( $user_id <= 0 ) {
		return array();
	}

	$baseline_table = $wpdb->prefix . 'politeia_user_baselines';
	$metrics_table  = $wpdb->prefix . 'politeia_user_baseline_metrics';

	$baseline = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, user_id, context, created_at
			 FROM {$baseline_table}
			 WHERE user_id = %d
			 ORDER BY created_at DESC, id DESC
			 LIMIT 1",
			$user_id
		),
		ARRAY_A
	);

	if ( ! $baseline || empty( $baseline['id'] ) ) {
		return array();
	}

	$metric_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT metric, value
			 FROM {$metrics_table}
			 WHERE baseline_id = %d",
			(int) $baseline['id']
		),
		ARRAY_A
	);

	$metrics = array();
	if ( $metric_rows ) {
		foreach ( $metric_rows as $row ) {
			if ( empty( $row['metric'] ) ) {
				continue;
			}
			$metrics[ (string) $row['metric'] ] = isset( $row['value'] ) ? (string) $row['value'] : '';
		}
	}

	return array(
		'baseline_id' => (int) $baseline['id'],
		'created_at'  => (string) $baseline['created_at'],
		'context'     => (string) $baseline['context'],
		'metrics'     => $metrics,
	);
}

/**
 * Build baseline-aware progress comparisons for a user.
 *
 * @param int $user_id WordPress user ID.
 * @return array<string,array<string,mixed>>
 */
function get_user_progress_against_baseline( int $user_id ): array {
	$baseline = get_latest_user_baseline( $user_id );
	if ( empty( $baseline ) || empty( $baseline['metrics'] ) ) {
		return array();
	}

	$current = prs_user_baseline_current_metrics( $user_id, (string) $baseline['created_at'] );
	if ( empty( $current ) ) {
		return array();
	}

	$result = array();
	foreach ( $baseline['metrics'] as $metric => $baseline_value ) {
		if ( ! array_key_exists( $metric, $current ) ) {
			continue;
		}
		$current_value = $current[ $metric ];
		$delta = prs_user_baseline_format_delta( (string) $baseline_value, $current_value );
		$result[ $metric ] = array(
			'baseline' => (string) $baseline_value,
			'current'  => $current_value,
			'delta'    => $delta,
		);
	}

	return $result;
}

/**
 * Compute current metrics for baseline comparison.
 *
 * @param int    $user_id WordPress user ID.
 * @param string $baseline_created_at Baseline timestamp.
 * @return array<string,int>
 */
function prs_user_baseline_current_metrics( int $user_id, string $baseline_created_at ): array {
	global $wpdb;

	if ( $user_id <= 0 ) {
		return array();
	}

	$sessions_table = $wpdb->prefix . 'politeia_reading_sessions';
	$user_books_table = $wpdb->prefix . 'politeia_user_books';

	$now_ts  = current_time( 'timestamp' );
	$window_start = gmdate( 'Y-m-d H:i:s', $now_ts - ( 28 * DAY_IN_SECONDS ) );

	$sessions = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT start_time, start_page, end_page
			 FROM {$sessions_table}
			 WHERE user_id = %d
			   AND end_time IS NOT NULL
			   AND deleted_at IS NULL
			   AND start_time >= %s",
			$user_id,
			$window_start
		),
		ARRAY_A
	);

	$unique_days = array();
	$unique_weeks = array();
	$page_total = 0;
	$page_count = 0;

	if ( $sessions ) {
		foreach ( $sessions as $session ) {
			if ( empty( $session['start_time'] ) ) {
				continue;
			}
			$start_ts = strtotime( $session['start_time'] );
			if ( ! $start_ts ) {
				continue;
			}
			$unique_days[ gmdate( 'Y-m-d', $start_ts ) ] = true;
			$unique_weeks[ gmdate( 'o-W', $start_ts ) ] = true;

			$start_page = isset( $session['start_page'] ) ? (int) $session['start_page'] : 0;
			$end_page   = isset( $session['end_page'] ) ? (int) $session['end_page'] : 0;
			if ( $end_page >= $start_page && $end_page > 0 && $start_page >= 0 ) {
				$page_total += ( $end_page - $start_page );
				$page_count++;
			}
		}
	}

	$days_per_week = $unique_days ? (int) round( count( $unique_days ) / 4 ) : 0;
	$weeks_active_month = $unique_weeks ? count( $unique_weeks ) : 0;
	$pages_per_session = $page_count > 0 ? (int) round( $page_total / $page_count ) : 0;

	$baseline_start = $baseline_created_at ? $baseline_created_at : gmdate( 'Y-m-d H:i:s', $now_ts - YEAR_IN_SECONDS );
	$books_per_year = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*)
			 FROM {$user_books_table}
			 WHERE user_id = %d
			   AND reading_status = %s
			   AND deleted_at IS NULL
			   AND updated_at >= %s",
			$user_id,
			'finished',
			$baseline_start
		)
	);

	return array(
		'books_per_year'     => $books_per_year,
		'days_per_week'      => $days_per_week,
		'pages_per_session'  => $pages_per_session,
		'weeks_active_month' => $weeks_active_month,
	);
}

/**
 * Format delta against a baseline range or scalar.
 *
 * @param string $baseline_value Baseline range or scalar.
 * @param int    $current_value Current metric value.
 * @return string
 */
function prs_user_baseline_format_delta( string $baseline_value, int $current_value ): string {
	$baseline_value = trim( $baseline_value );
	if ( '' === $baseline_value ) {
		return '';
	}

	if ( preg_match( '/^(\d+)\s*-\s*(\d+)$/', $baseline_value, $matches ) ) {
		$min = (int) $matches[1];
		$max = (int) $matches[2];
		$delta_min = $current_value - $max;
		$delta_max = $current_value - $min;
		$prefix_min = $delta_min > 0 ? '+' : '';
		$prefix_max = $delta_max > 0 ? '+' : '';
		if ( $delta_min === $delta_max ) {
			return $prefix_min . $delta_min;
		}
		return $prefix_min . $delta_min . ' to ' . $prefix_max . $delta_max;
	}

	$baseline_int = is_numeric( $baseline_value ) ? (int) $baseline_value : 0;
	if ( 0 === $baseline_int && '0' !== $baseline_value ) {
		return '';
	}

	$delta = $current_value - $baseline_int;
	$prefix = $delta > 0 ? '+' : '';
	return $prefix . $delta;
}
