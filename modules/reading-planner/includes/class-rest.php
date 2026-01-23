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
		add_action( 'wp_ajax_prs_reading_plan_add_book', array( __CLASS__, 'ajax_create_book' ) );
		add_action( 'wp_ajax_prs_reading_plan_check_active', array( __CLASS__, 'ajax_check_active_plan' ) );
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
		register_rest_route(
			'politeia/v1',
			'/reading-plan/session-recorder',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'session_recorder' ),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);
		register_rest_route(
			'politeia/v1',
			'/reading-plan/book',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_book' ),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);
	}

	public static function session_recorder( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_REST_Response( array( 'error' => 'not_logged_in' ), 401 );
		}

		$nonce = $request->get_header( 'X-WP-Nonce' ) ?: ( $request['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid_nonce' ), 403 );
		}

		$book_id = (int) $request->get_param( 'book_id' );
		$plan_id = (int) $request->get_param( 'plan_id' );
		if ( $book_id <= 0 ) {
			return new WP_REST_Response( array( 'error' => 'invalid_book' ), 400 );
		}

		global $wpdb;
		$user_id = get_current_user_id();
		$tbl_rs           = $wpdb->prefix . 'politeia_reading_sessions';
		$tbl_ub           = $wpdb->prefix . 'politeia_user_books';
		$tbl_plans        = $wpdb->prefix . 'politeia_plans';
		$tbl_plan_sessions = $wpdb->prefix . 'politeia_planned_sessions';
		$default_start_page = 0;

		$last_end_page = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT end_page FROM {$tbl_rs}
				 WHERE user_id = %d AND book_id = %d AND end_time IS NOT NULL AND deleted_at IS NULL
				 ORDER BY end_time DESC LIMIT 1",
				$user_id,
				$book_id
			)
		);

		$row_ub = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT owning_status, pages FROM {$tbl_ub} WHERE user_id=%d AND book_id=%d AND deleted_at IS NULL LIMIT 1",
				$user_id,
				$book_id
			)
		);
		$owning_status = $row_ub && $row_ub->owning_status ? (string) $row_ub->owning_status : 'in_shelf';
		$total_pages   = $row_ub && $row_ub->pages ? (int) $row_ub->pages : 0;
		$can_start     = ! in_array( $owning_status, array( 'borrowed', 'lost', 'sold' ), true );

		if ( $plan_id > 0 ) {
			$plan_owner = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT user_id FROM {$tbl_plans} WHERE id = %d LIMIT 1",
					$plan_id
				)
			);
			if ( $plan_owner === (int) $user_id ) {
				$default_start_page = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT planned_start_page FROM {$tbl_plan_sessions}
						WHERE plan_id = %d AND planned_start_page IS NOT NULL
						ORDER BY planned_start_datetime ASC LIMIT 1",
						$plan_id
					)
				);
			}
		}

		$prs_sr = array(
			'ajax_url'      => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'prs_reading_nonce' ),
			'user_id'       => (int) $user_id,
			'book_id'       => (int) $book_id,
			'last_end_page' => is_null( $last_end_page ) ? '' : (int) $last_end_page,
			'default_start_page' => $default_start_page,
			'owning_status' => (string) $owning_status,
			'total_pages'   => (int) $total_pages,
			'can_start'     => $can_start ? 1 : 0,
			'actions'       => array(
				'start' => 'prs_start_reading',
				'save'  => 'prs_save_reading',
			),
			'strings'       => array(
				'tooltip_pages_required' => __( 'Set total Pages for this book before starting a session.', 'politeia-reading' ),
				'tooltip_not_owned'      => __( 'You cannot start a session: the book is not in your possession (Borrowed, Lost or Sold).', 'politeia-reading' ),
				'alert_pages_required'   => __( 'You must set total Pages to start a session.', 'politeia-reading' ),
				'alert_end_page_required' => __( 'Please enter an ending page before saving.', 'politeia-reading' ),
				'alert_session_expired'  => __( 'Session expired. Please refresh the page and try again.', 'politeia-reading' ),
				'alert_start_network'    => __( 'Network error while starting the session.', 'politeia-reading' ),
				'alert_save_failed'      => __( 'Could not save the session.', 'politeia-reading' ),
				'alert_save_network'     => __( 'Network error while saving the session.', 'politeia-reading' ),
				'pages_single'           => __( '1 page', 'politeia-reading' ),
				'pages_multiple'         => __( '%d pages', 'politeia-reading' ),
				'minutes_under_one'      => __( 'less than a minute', 'politeia-reading' ),
				'minutes_single'         => __( '1 minute', 'politeia-reading' ),
				'minutes_multiple'       => __( '%d minutes', 'politeia-reading' ),
			),
		);

		$shortcode = '[politeia_start_reading book_id="' . $book_id . '"';
		if ( $plan_id > 0 ) {
			$shortcode .= ' plan_id="' . $plan_id . '"';
		}
		$shortcode .= ']';

		$html = do_shortcode( $shortcode );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'html'   => $html,
					'prs_sr' => $prs_sr,
				),
			),
			200
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

				$start_dt = date_create_immutable( $planned_start, wp_timezone() );
				if ( $start_dt ) {
					$planned_start = $start_dt->setTime( 0, 0, 0 )->format( 'Y-m-d H:i:s' );
					$planned_end   = $start_dt->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' );
				}

				$session_inserted = $wpdb->insert(
					$sessions_table,
					array(
						'plan_id'                => $plan_id,
						'planned_start_datetime' => $planned_start,
						'planned_end_datetime'   => $planned_end,
						'planned_start_page'     => isset( $session['planned_start_page'] ) ? (int) $session['planned_start_page'] : null,
						'planned_end_page'       => isset( $session['planned_end_page'] ) ? (int) $session['planned_end_page'] : null,
						'expected_number_of_pages' => isset( $session['expected_number_of_pages'] ) ? (int) $session['expected_number_of_pages'] : null,
						'expected_duration_minutes' => isset( $session['expected_duration_minutes'] ) ? (int) $session['expected_duration_minutes'] : null,
						'status'                 => 'planned',
						'previous_session_id'    => isset( $session['previous_session_id'] ) ? (int) $session['previous_session_id'] : null,
						'comment'                => isset( $session['comment'] ) ? wp_kses_post( (string) $session['comment'] ) : null,
					),
					array( '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%d', '%s' )
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

		return new WP_REST_Response(
			array(
				'success' => true,
				'plan_id' => $plan_id,
			),
			200
		);
	}

	public static function create_book( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_REST_Response( array( 'error' => 'not_logged_in' ), 401 );
		}

		$nonce = $request->get_header( 'X-WP-Nonce' ) ?: ( $request['nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid_nonce' ), 403 );
		}

		$payload = $request->get_json_params();
		$result = self::process_create_book( $payload, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			if ( 'missing_required_fields' === $code ) {
				$status = 400;
			} elseif ( 'active_plan' === $code ) {
				$status = 409;
			} else {
				$status = 500;
			}
			return new WP_REST_Response( array( 'error' => $code ), $status );
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $result,
			),
			200
		);
	}

	public static function ajax_create_book() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'error' => 'not_logged_in' ), 401 );
		}

		check_ajax_referer( 'prs_reading_plan_add_book', 'nonce' );

		$payload = array(
			'title'     => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'author'    => isset( $_POST['author'] ) ? wp_unslash( $_POST['author'] ) : '',
			'pages'     => isset( $_POST['pages'] ) ? absint( $_POST['pages'] ) : 0,
			'cover_url' => isset( $_POST['cover_url'] ) ? esc_url_raw( wp_unslash( $_POST['cover_url'] ) ) : '',
		);

		$result = self::process_create_book( $payload, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			if ( 'missing_required_fields' === $code ) {
				$status = 400;
			} elseif ( 'active_plan' === $code ) {
				$status = 409;
			} else {
				$status = 500;
			}
			wp_send_json_error( array( 'error' => $code ), $status );
		}

		wp_send_json_success(
			array(
				'data' => $result,
			)
		);
	}

	public static function ajax_check_active_plan() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'error' => 'not_logged_in' ), 401 );
		}

		check_ajax_referer( 'prs_reading_plan_check_active', 'nonce' );

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$raw_author = isset( $_POST['author'] ) ? wp_unslash( $_POST['author'] ) : '';

		$author_parts = self::split_authors( $raw_author );
		$primary_author = $author_parts['primary'];
		$other_authors = $author_parts['others'];

		if ( '' === $title || '' === $primary_author ) {
			wp_send_json_success( array( 'active' => false ) );
		}

		$all_authors = array_merge( array( $primary_author ), $other_authors );
		$book_id = 0;
		$slug = prs_generate_book_slug( $title, null );
		if ( $slug ) {
			$book_id = (int) prs_get_book_id_by_slug( $slug );
		}
		if ( ! $book_id ) {
			$book_id = (int) prs_get_book_id_by_identity( $title, $all_authors, null );
		}
		if ( ! $book_id ) {
			$book_id = self::find_book_id_by_title_author( $title, $all_authors );
		}
		if ( ! $book_id ) {
			wp_send_json_success( array( 'active' => false ) );
		}

		global $wpdb;
		$user_id = get_current_user_id();
		$plans_table = $wpdb->prefix . 'politeia_plans';
		$goals_table = $wpdb->prefix . 'politeia_plan_goals';
		$active_plan_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.id
				 FROM {$plans_table} p
				 INNER JOIN {$goals_table} g ON g.plan_id = p.id
				 WHERE p.user_id = %d
				   AND p.status = %s
				   AND g.book_id = %d
				 LIMIT 1",
				$user_id,
				'accepted',
				$book_id
			)
		);

		wp_send_json_success(
			array(
				'active' => (bool) $active_plan_id,
			)
		);
	}

	protected static function split_authors( $raw_author ) {
		$authors = array();
		if ( '' !== $raw_author && null !== $raw_author ) {
			$collect_authors = static function ( $value ) use ( &$authors ) {
				if ( null === $value || '' === $value ) {
					return;
				}

				$candidates = explode( ',', (string) $value );
				foreach ( $candidates as $candidate ) {
					$clean_author = sanitize_text_field( $candidate );
					if ( '' === $clean_author ) {
						continue;
					}
					$clean_author = preg_replace( '/\s+/', ' ', $clean_author );
					$clean_author = trim( (string) $clean_author );
					if ( '' !== $clean_author ) {
						$authors[] = $clean_author;
					}
				}
			};

			if ( is_array( $raw_author ) ) {
				foreach ( $raw_author as $raw_value ) {
					$collect_authors( $raw_value );
				}
			} else {
				$collect_authors( $raw_author );
			}
		}

		$primary_author = '';
		$other_authors = array();
		if ( ! empty( $authors ) ) {
			$normalized = array();
			$unique     = array();

			foreach ( $authors as $raw_value ) {
				$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $raw_value, 'UTF-8' ) : strtolower( $raw_value );
				if ( isset( $normalized[ $key ] ) ) {
					continue;
				}
				$normalized[ $key ] = true;
				$unique[]           = $raw_value;
			}

			if ( ! empty( $unique ) ) {
				$primary_author = array_shift( $unique );
				$other_authors  = array_values( $unique );
			}
		}

		return array(
			'primary' => $primary_author,
			'others'  => $other_authors,
		);
	}

	protected static function find_book_id_by_title_author( $title, array $authors ) {
		$normalized_title = prs_normalize_title( $title );
		if ( '' === $normalized_title ) {
			return 0;
		}

		$author_hashes = prs_get_author_hashes_from_names( $authors );
		if ( empty( $author_hashes ) ) {
			return 0;
		}

		global $wpdb;
		$books_table   = $wpdb->prefix . 'politeia_books';
		$pivot_table   = $wpdb->prefix . 'politeia_book_authors';
		$authors_table = $wpdb->prefix . 'politeia_authors';

		$placeholders = implode( ', ', array_fill( 0, count( $author_hashes ), '%s' ) );
		$params = array_merge( array( $normalized_title ), $author_hashes );

		$sql = "
			SELECT b.id
			FROM {$books_table} b
			INNER JOIN {$pivot_table} ba ON ba.book_id = b.id
			INNER JOIN {$authors_table} a ON a.id = ba.author_id
			WHERE b.normalized_title = %s
			  AND a.name_hash IN ({$placeholders})
			LIMIT 1
		";

		$book_id = $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
		return $book_id ? (int) $book_id : 0;
	}

	protected static function process_create_book( $payload, $user_id ) {
		$title   = isset( $payload['title'] ) ? sanitize_text_field( (string) $payload['title'] ) : '';
		$raw_author = isset( $payload['author'] ) ? $payload['author'] : '';
		$pages = isset( $payload['pages'] ) ? absint( $payload['pages'] ) : 0;
		$cover_url = isset( $payload['cover_url'] ) ? esc_url_raw( (string) $payload['cover_url'] ) : '';

		$author_parts = self::split_authors( $raw_author );
		$primary_author = $author_parts['primary'];
		$authors = $author_parts['others'];

		if ( '' === $title || '' === $primary_author || $pages <= 0 ) {
			return new \WP_Error( 'missing_required_fields', 'Missing required fields.' );
		}

		global $wpdb;
		$user_id = (int) $user_id;
		$all_authors = array_merge( array( $primary_author ), $authors );

		$book_id = 0;
		$slug = prs_generate_book_slug( $title, null );
		if ( $slug ) {
			$book_id = (int) prs_get_book_id_by_slug( $slug );
		}
		if ( ! $book_id ) {
			$book_id = (int) prs_get_book_id_by_identity( $title, $all_authors, null );
		}
		if ( ! $book_id ) {
			$book_id = self::find_book_id_by_title_author( $title, $all_authors );
		}

		if ( $book_id ) {
			$plans_table = $wpdb->prefix . 'politeia_plans';
			$goals_table = $wpdb->prefix . 'politeia_plan_goals';
			$active_plan_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT p.id
					 FROM {$plans_table} p
					 INNER JOIN {$goals_table} g ON g.plan_id = p.id
					 WHERE p.user_id = %d
					   AND p.status = %s
					   AND g.book_id = %d
					 LIMIT 1",
					$user_id,
					'accepted',
					$book_id
				)
			);
			if ( $active_plan_id ) {
				return new \WP_Error( 'active_plan', 'Active plan already exists for this book.' );
			}

			$user_book_id = prs_ensure_user_book( $user_id, (int) $book_id );
			if ( ! $user_book_id ) {
				return new \WP_Error( 'user_book_failed', 'Could not attach book to user.' );
			}
			$wpdb->update(
				$wpdb->prefix . 'politeia_user_books',
				array( 'pages' => $pages ),
				array( 'id' => (int) $user_book_id ),
				array( '%d' ),
				array( '%d' )
			);
			if ( $cover_url ) {
				$wpdb->update(
					$wpdb->prefix . 'politeia_user_books',
					array( 'cover_reference' => $cover_url ),
					array( 'id' => (int) $user_book_id ),
					array( '%s' ),
					array( '%d' )
				);
			}

			return array(
				'book_id'      => (int) $book_id,
				'user_book_id' => (int) $user_book_id,
				'title'        => $title,
				'author'       => $primary_author,
				'pages'        => (int) $pages,
				'cover_url'    => $cover_url,
			);
		}

		$created = prs_find_or_create_book(
			$title,
			$primary_author,
			null,
			'',
			null,
			$all_authors,
			'confirmed'
		);
		if ( is_wp_error( $created ) ) {
			return new \WP_Error( $created->get_error_code(), $created->get_error_message() );
		}

		$book_id = (int) $created;
		if ( ! $book_id ) {
			return new \WP_Error( 'book_not_found', 'Book not found after confirmation.' );
		}

		$user_book_id = prs_ensure_user_book( $user_id, (int) $book_id );
		if ( ! $user_book_id ) {
			return new \WP_Error( 'user_book_failed', 'Could not attach book to user.' );
		}
		$wpdb->update(
			$wpdb->prefix . 'politeia_user_books',
			array( 'pages' => $pages ),
			array( 'id' => (int) $user_book_id ),
			array( '%d' ),
			array( '%d' )
		);
		if ( $cover_url ) {
			$wpdb->update(
				$wpdb->prefix . 'politeia_user_books',
				array( 'cover_reference' => $cover_url ),
				array( 'id' => (int) $user_book_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		return array(
			'book_id'      => (int) $book_id,
			'user_book_id' => (int) $user_book_id,
			'title'        => $title,
			'author'       => $primary_author,
			'pages'        => (int) $pages,
			'cover_url'    => $cover_url,
		);
	}
}
Rest::init();
