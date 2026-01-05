<?php
namespace Politeia\ReadingPlanner;

use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rest {
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
	}

	public static function register(): void {
		register_rest_route(
			'politeia/v1',
			'/reading-plan',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'accept_plan' ),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);
	}

	public static function accept_plan( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_REST_Response( array( 'error' => 'not_logged_in' ), 401 );
		}

		$nonce = $request->get_header( 'X-WP-Nonce' ) ?: ( $request['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid_nonce' ), 403 );
		}

		$user_id = get_current_user_id();
		$payload = $request->get_json_params();
		$goals   = isset( $payload['goals'] ) && is_array( $payload['goals'] ) ? $payload['goals'] : array();
		$baseline_metrics = isset( $payload['baselines'] ) && is_array( $payload['baselines'] ) ? $payload['baselines'] : array();
		$planned_sessions = isset( $payload['planned_sessions'] ) && is_array( $payload['planned_sessions'] ) ? $payload['planned_sessions'] : array();

		if ( empty( $goals ) || empty( $baseline_metrics ) ) {
			return new WP_REST_Response( array( 'error' => 'missing_required_fields' ), 400 );
		}

		$plan_name = isset( $payload['name'] ) ? sanitize_text_field( (string) $payload['name'] ) : 'Reading Plan';
		$plan_type = isset( $payload['plan_type'] ) ? sanitize_text_field( (string) $payload['plan_type'] ) : 'custom';
		$status    = isset( $payload['status'] ) ? sanitize_text_field( (string) $payload['status'] ) : 'accepted';

		global $wpdb;
		$plans_table    = $wpdb->prefix . 'politeia_plans';
		$goals_table    = $wpdb->prefix . 'politeia_plan_goals';
		$sessions_table = $wpdb->prefix . 'politeia_planned_sessions';
		$now            = current_time( 'mysql' );

		$transaction_started = false;
		if ( false !== $wpdb->query( 'START TRANSACTION' ) ) {
			$transaction_started = true;
		}

		$inserted = $wpdb->insert(
			$plans_table,
			array(
				'user_id'    => $user_id,
				'name'       => $plan_name,
				'plan_type'  => $plan_type,
				'status'     => $status,
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			if ( $transaction_started ) {
				$wpdb->query( 'ROLLBACK' );
			}
			error_log( '[ReadingPlanner] Plan insert failed: ' . $wpdb->last_error );
			return new WP_REST_Response( array( 'error' => 'plan_insert_failed' ), 500 );
		}

		$plan_id = (int) $wpdb->insert_id;
		if ( $plan_id <= 0 ) {
			if ( $transaction_started ) {
				$wpdb->query( 'ROLLBACK' );
			}
			error_log( '[ReadingPlanner] Plan insert failed: missing insert_id.' );
			return new WP_REST_Response( array( 'error' => 'plan_insert_failed' ), 500 );
		}

		foreach ( $goals as $goal ) {
			if ( ! is_array( $goal ) ) {
				continue;
			}

			$goal_kind = isset( $goal['goal_kind'] ) ? sanitize_text_field( (string) $goal['goal_kind'] ) : '';
			$metric    = isset( $goal['metric'] ) ? sanitize_text_field( (string) $goal['metric'] ) : '';
			$target    = isset( $goal['target_value'] ) ? (int) $goal['target_value'] : 0;
			$period    = isset( $goal['period'] ) ? sanitize_text_field( (string) $goal['period'] ) : '';
			$book_id   = isset( $goal['book_id'] ) ? (int) $goal['book_id'] : null;
			$subject_id = isset( $goal['subject_id'] ) ? (int) $goal['subject_id'] : null;

			if ( '' === $goal_kind || '' === $metric || $target <= 0 || '' === $period ) {
				if ( $transaction_started ) {
					$wpdb->query( 'ROLLBACK' );
				}
				error_log( '[ReadingPlanner] Invalid goal payload.' );
				return new WP_REST_Response( array( 'error' => 'invalid_goal' ), 400 );
			}

			$goal_inserted = $wpdb->insert(
				$goals_table,
				array(
					'plan_id'     => $plan_id,
					'goal_kind'   => $goal_kind,
					'metric'      => $metric,
					'target_value' => $target,
					'period'      => $period,
					'book_id'     => $book_id,
					'subject_id'  => $subject_id,
				),
				array( '%d', '%s', '%s', '%d', '%s', '%d', '%d' )
			);

			if ( false === $goal_inserted ) {
				if ( $transaction_started ) {
					$wpdb->query( 'ROLLBACK' );
				}
				error_log( '[ReadingPlanner] Goal insert failed: ' . $wpdb->last_error );
				return new WP_REST_Response( array( 'error' => 'goal_insert_failed' ), 500 );
			}
		}

		if ( $planned_sessions ) {
			foreach ( $planned_sessions as $session ) {
				if ( ! is_array( $session ) ) {
					continue;
				}

				$planned_start = isset( $session['planned_start_datetime'] ) ? sanitize_text_field( (string) $session['planned_start_datetime'] ) : '';
				$planned_end   = isset( $session['planned_end_datetime'] ) ? sanitize_text_field( (string) $session['planned_end_datetime'] ) : '';

				if ( '' === $planned_start || '' === $planned_end ) {
					if ( $transaction_started ) {
						$wpdb->query( 'ROLLBACK' );
					}
					error_log( '[ReadingPlanner] Invalid planned session payload.' );
					return new WP_REST_Response( array( 'error' => 'invalid_session' ), 400 );
				}

				$session_inserted = $wpdb->insert(
					$sessions_table,
					array(
						'plan_id'                => $plan_id,
						'planned_start_datetime' => $planned_start,
						'planned_end_datetime'   => $planned_end,
						'planned_start_page'     => isset( $session['planned_start_page'] ) ? (int) $session['planned_start_page'] : null,
						'planned_end_page'       => isset( $session['planned_end_page'] ) ? (int) $session['planned_end_page'] : null,
						'actual_start_datetime'  => isset( $session['actual_start_datetime'] ) ? sanitize_text_field( (string) $session['actual_start_datetime'] ) : null,
						'actual_end_datetime'    => isset( $session['actual_end_datetime'] ) ? sanitize_text_field( (string) $session['actual_end_datetime'] ) : null,
						'actual_start_page'      => isset( $session['actual_start_page'] ) ? (int) $session['actual_start_page'] : null,
						'actual_end_page'        => isset( $session['actual_end_page'] ) ? (int) $session['actual_end_page'] : null,
						'status'                 => 'planned',
						'previous_session_id'    => isset( $session['previous_session_id'] ) ? (int) $session['previous_session_id'] : null,
						'comment'                => isset( $session['comment'] ) ? wp_kses_post( (string) $session['comment'] ) : null,
					),
					array( '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%d', '%s' )
				);

				if ( false === $session_inserted ) {
					if ( $transaction_started ) {
						$wpdb->query( 'ROLLBACK' );
					}
					error_log( '[ReadingPlanner] Planned session insert failed: ' . $wpdb->last_error );
					return new WP_REST_Response( array( 'error' => 'planned_session_insert_failed' ), 500 );
				}
			}
		}

		if ( $transaction_started ) {
			$wpdb->query( 'COMMIT' );
		}

		$baseline_id = create_user_baseline( $user_id, $baseline_metrics, 'plan_acceptance' );
		if ( 0 === $baseline_id ) {
			error_log( '[ReadingPlanner] Baseline creation failed for user ' . $user_id );
			return new WP_REST_Response( array( 'error' => 'baseline_creation_failed' ), 500 );
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}
}
Rest::init();
