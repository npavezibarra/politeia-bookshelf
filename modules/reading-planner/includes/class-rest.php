<?php
namespace Politeia\ReadingPlanner;

use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
	exit;
}

class Rest
{
	public static function init(): void
	{
		add_action('rest_api_init', array(__CLASS__, 'register'));
		add_action('wp_ajax_prs_reading_plan_add_book', array(__CLASS__, 'ajax_create_book'));
		add_action('wp_ajax_prs_reading_plan_check_active', array(__CLASS__, 'ajax_check_active_plan'));
	}

	public static function register(): void
	{
		register_rest_route(
			'politeia/v1',
			'/reading-plan',
			array(
				'methods' => 'POST',
				'callback' => array(__CLASS__, 'accept_plan'),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);
		register_rest_route(
			'politeia/v1',
			'/reading-plan/session-recorder',
			array(
				'methods' => 'GET',
				'callback' => array(__CLASS__, 'session_recorder'),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);
		register_rest_route(
			'politeia/v1',
			'/reading-plan/book',
			array(
				'methods' => 'POST',
				'callback' => array(__CLASS__, 'create_book'),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);
		// --- Session Persistence API (Phase 3) ---
		register_rest_route(
			'politeia/v1',
			'/reading-plan/(?P<plan_id>\d+)/session',
			array(
				'methods' => 'POST',
				'callback' => array(__CLASS__, 'add_session'),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);
		register_rest_route(
			'politeia/v1',
			'/reading-plan/(?P<plan_id>\d+)/session/(?P<date>[\d-]+)',
			array(
				'methods' => 'DELETE',
				'callback' => array(__CLASS__, 'remove_session'),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);
		register_rest_route(
			'politeia/v1',
			'/reading-plan/(?P<plan_id>\d+)/session/(?P<date>[\d-]+)',
			array(
				'methods' => 'PUT',
				'callback' => array(__CLASS__, 'move_session'),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);
		register_rest_route(
			'politeia/v1',
			'/reading-plan/(?P<plan_id>\d+)',
			array(
				'methods' => 'GET',
				'callback' => array(__CLASS__, 'get_plan'),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);
	}

	public static function session_recorder(WP_REST_Request $request)
	{
		if (!is_user_logged_in()) {
			return new WP_REST_Response(array('error' => 'not_logged_in'), 401);
		}

		$nonce = $request->get_header('X-WP-Nonce') ?: ($request['nonce'] ?? '');
		if (!wp_verify_nonce($nonce, 'wp_rest')) {
			return new WP_REST_Response(array('error' => 'invalid_nonce'), 403);
		}

		$book_id = (int) $request->get_param('book_id');
		$plan_id = (int) $request->get_param('plan_id');
		if ($book_id <= 0) {
			return new WP_REST_Response(array('error' => 'invalid_book'), 400);
		}

		global $wpdb;
		$user_id = get_current_user_id();
		$tbl_rs = $wpdb->prefix . 'politeia_reading_sessions';
		$tbl_ub = $wpdb->prefix . 'politeia_user_books';
		$tbl_plans = $wpdb->prefix . 'politeia_plans';
		$tbl_plan_sessions = $wpdb->prefix . 'politeia_planned_sessions';
		$default_start_page = 0;

		$row_ub = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, owning_status, pages FROM {$tbl_ub} WHERE user_id=%d AND book_id=%d AND deleted_at IS NULL LIMIT 1",
				$user_id,
				$book_id
			)
		);
		$owning_status = $row_ub && $row_ub->owning_status ? (string) $row_ub->owning_status : 'in_shelf';
		$total_pages = $row_ub && $row_ub->pages ? (int) $row_ub->pages : 0;
		$user_book_id = $row_ub ? (int) $row_ub->id : 0;
		$can_start = !in_array($owning_status, array('borrowed', 'lost', 'sold'), true);

		$last_end_page = 0;
		if ($user_book_id > 0) {
			$last_end_page = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT end_page FROM {$tbl_rs}
					 WHERE user_id = %d AND user_book_id = %d AND end_time IS NOT NULL AND deleted_at IS NULL
					 ORDER BY end_time DESC LIMIT 1",
					$user_id,
					$user_book_id
				)
			);
		}

		if ($plan_id > 0) {
			$plan_row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT user_id, plan_type FROM {$tbl_plans} WHERE id = %d LIMIT 1",
					$plan_id
				)
			);

			if ($plan_row && (int) $plan_row->user_id === (int) $user_id) {
				if ('complete_books' === $plan_row->plan_type) {
					$plan_finish_book_table = $wpdb->prefix . 'politeia_plan_finish_book';
					$default_start_page = (int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT start_page FROM {$plan_finish_book_table} WHERE plan_id = %d LIMIT 1",
							$plan_id
						)
					);
				}
				// Habits don't have a fixed book start page (Any Book)
			}
		}

		$prs_sr = array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('prs_reading_nonce'),
			'user_id' => (int) $user_id,
			'book_id' => (int) $book_id,
			'last_end_page' => is_null($last_end_page) ? '' : (int) $last_end_page,
			'default_start_page' => $default_start_page,
			'owning_status' => (string) $owning_status,
			'total_pages' => (int) $total_pages,
			'can_start' => $can_start ? 1 : 0,
			'actions' => array(
				'start' => 'prs_start_reading',
				'save' => 'prs_save_reading',
			),
			'strings' => array(
				'tooltip_pages_required' => __('Set total Pages for this book before starting a session.', 'politeia-reading'),
				'tooltip_not_owned' => __('You cannot start a session: the book is not in your possession (Borrowed, Lost or Sold).', 'politeia-reading'),
				'alert_pages_required' => __('You must set total Pages to start a session.', 'politeia-reading'),
				'alert_end_page_required' => __('Please enter an ending page before saving.', 'politeia-reading'),
				'alert_session_expired' => __('Session expired. Please refresh the page and try again.', 'politeia-reading'),
				'alert_start_network' => __('Network error while starting the session.', 'politeia-reading'),
				'alert_save_failed' => __('Could not save the session.', 'politeia-reading'),
				'alert_save_network' => __('Network error while saving the session.', 'politeia-reading'),
				'pages_single' => __('1 page', 'politeia-reading'),
				'pages_multiple' => __('%d pages', 'politeia-reading'),
				'minutes_under_one' => __('less than a minute', 'politeia-reading'),
				'minutes_single' => __('1 minute', 'politeia-reading'),
				'minutes_multiple' => __('%d minutes', 'politeia-reading'),
			),
		);

		$shortcode = '[politeia_start_reading book_id="' . $book_id . '"';
		if ($plan_id > 0) {
			$shortcode .= ' plan_id="' . $plan_id . '"';
		}
		$shortcode .= ']';

		$html = do_shortcode($shortcode);

		return new WP_REST_Response(
			array(
				'success' => true,
				'data' => array(
					'html' => $html,
					'prs_sr' => $prs_sr,
				),
			),
			200
		);
	}

	public static function accept_plan(WP_REST_Request $request)
	{
		if (!is_user_logged_in()) {
			return new WP_REST_Response(array('error' => 'not_logged_in'), 401);
		}

		$nonce = $request->get_header('X-WP-Nonce') ?: ($request['nonce'] ?? '');
		if (!wp_verify_nonce($nonce, 'wp_rest')) {
			return new WP_REST_Response(array('error' => 'invalid_nonce'), 403);
		}

		$user_id = get_current_user_id();
		$payload = $request->get_json_params();
		$goals = isset($payload['goals']) && is_array($payload['goals']) ? $payload['goals'] : array();
		$baseline_metrics = isset($payload['baselines']) && is_array($payload['baselines']) ? $payload['baselines'] : array();
		$planned_sessions = isset($payload['planned_sessions']) && is_array($payload['planned_sessions']) ? $payload['planned_sessions'] : array();

		if (empty($goals) || empty($baseline_metrics)) {
			return new WP_REST_Response(array('error' => 'missing_required_fields'), 400);
		}

		$allowed_goal_kinds = array('complete_books', 'habit');
		$allowed_metrics = array('pages_total', 'pages_per_session', 'daily_threshold');
		$allowed_periods = array('plan', 'session', 'day');
		$max_sessions = 400;
		if ($planned_sessions && count($planned_sessions) > $max_sessions) {
			return new WP_REST_Response(array('error' => 'too_many_sessions'), 400);
		}

		$plan_name = isset($payload['name']) ? sanitize_text_field((string) $payload['name']) : 'Reading Plan';
		$plan_type = isset($payload['plan_type']) ? sanitize_text_field((string) $payload['plan_type']) : 'custom';
		$status = 'accepted';

		// Extract strategy parameters
		$pages_per_session = isset($payload['pages_per_session']) ? (int) $payload['pages_per_session'] : null;
		$sessions_per_week = isset($payload['sessions_per_week']) ? (int) $payload['sessions_per_week'] : null;

		// Check if this plan has a complete_books goal (need to peek at goals)
		$has_complete_books = false;
		if (!empty($goals) && is_array($goals)) {
			foreach ($goals as $goal) {
				if (is_array($goal) && isset($goal['goal_kind']) && 'complete_books' === $goal['goal_kind']) {
					$has_complete_books = true;
					break;
				}
			}
		}

		// Validate strategy parameters for complete_books plans
		// (Legacy helper fields pages_per_session/sessions_per_week removed in Phase 2)

		global $wpdb;
		$plans_table = $wpdb->prefix . 'politeia_plans';
		$goals_table = $wpdb->prefix . 'politeia_plan_goals';
		$sessions_table = $wpdb->prefix . 'politeia_planned_sessions';
		$now = current_time('mysql');

		$transaction_started = false;
		if (false !== $wpdb->query('START TRANSACTION')) {
			$transaction_started = true;
		}

		$inserted = $wpdb->insert(
			$plans_table,
			array(
				'user_id' => $user_id,
				'name' => $plan_name,
				'plan_type' => $plan_type,
				'status' => $status,
				// 'pages_per_session' => $pages_per_session, // Removed
				// 'sessions_per_week' => $sessions_per_week, // Removed
				'created_at' => $now,
				'updated_at' => $now,
			),
			array('%d', '%s', '%s', '%s', '%s', '%s')
		);

		if (false === $inserted) {
			if ($transaction_started) {
				$wpdb->query('ROLLBACK');
			}
			error_log('[ReadingPlanner] Plan insert failed: ' . $wpdb->last_error);
			return new WP_REST_Response(array('error' => 'plan_insert_failed'), 500);
		}

		$plan_id = (int) $wpdb->insert_id;
		if ($plan_id <= 0) {
			if ($transaction_started) {
				$wpdb->query('ROLLBACK');
			}
			error_log('[ReadingPlanner] Plan insert failed: missing insert_id.');
			return new WP_REST_Response(array('error' => 'plan_insert_failed'), 500);
		}

		// --- GOALS TABLE REMOVAL (Refactor) ---
		// We no longer write to wp_politeia_plan_goals.
		// Instead, we trust the subsequent blocks to write to specialized tables.

		// Validate that we have at least one valid goal struct in payload before proceeding
		$valid_goal_found = false;
		foreach ($goals as $goal) {
			if (is_array($goal) && !empty($goal['goal_kind'])) {
				$valid_goal_found = true;
				break;
			}
		}

		if (!$valid_goal_found) {
			if ($transaction_started) {
				$wpdb->query('ROLLBACK');
			}
			return new WP_REST_Response(array('error' => 'invalid_goal'), 400);
		}

		// --- HABIT REFACTOR: Insert Plan Habit Configuration ---
		$is_habit_plan = false;
		foreach ($goals as $g) {
			if (isset($g['goal_kind']) && 'habit' === $g['goal_kind']) {
				$is_habit_plan = true;
				break;
			}
		}

		if ($is_habit_plan) {
			$plan_habit_table = $wpdb->prefix . 'politeia_plan_habit';
			// Extract from payload baselines
			$habit_start = isset($baseline_metrics['habit_start_pages']) ? (int) $baseline_metrics['habit_start_pages'] : 5;
			$habit_end = isset($baseline_metrics['habit_end_pages']) ? (int) $baseline_metrics['habit_end_pages'] : 30;
			// Duration: try payload, then options, then default
			$habit_duration = isset($baseline_metrics['habit_days_duration']) ? (int) $baseline_metrics['habit_days_duration'] : (int) get_option('default_habit_duration_days', 66);
			if ($habit_duration <= 0) {
				$habit_duration = 66;
			}

			$habit_inserted = $wpdb->insert(
				$plan_habit_table,
				array(
					'plan_id' => $plan_id,
					'start_page_amount' => $habit_start,
					'finish_page_amount' => $habit_end,
					'duration_days' => $habit_duration,
				),
				array('%d', '%d', '%d', '%d')
			);

			if (false === $habit_inserted) {
				if ($transaction_started) {
					$wpdb->query('ROLLBACK');
				}
				error_log('[ReadingPlanner] Plan habit insert failed: ' . $wpdb->last_error);
				return new WP_REST_Response(array('error' => 'plan_habit_insert_failed'), 500);
			}
		}


		// --- FINISH BOOK REFACTOR: Insert Plan Finish Book Configuration ---
		$finish_book_goal = null;
		foreach ($goals as $g) {
			if (isset($g['goal_kind']) && 'complete_books' === $g['goal_kind']) {
				$finish_book_goal = $g;
				break;
			}
		}

		if ($finish_book_goal) {
			$plan_finish_book_table = $wpdb->prefix . 'politeia_plan_finish_book';
			$fb_user_book_id = isset($finish_book_goal['user_book_id']) ? (int) $finish_book_goal['user_book_id'] : 0;
			$fb_book_id = isset($finish_book_goal['book_id']) ? (int) $finish_book_goal['book_id'] : 0;
			$fb_start_page = isset($finish_book_goal['starting_page']) ? (int) $finish_book_goal['starting_page'] : 1;
			if ($fb_start_page < 1)
				$fb_start_page = 1;

			$user_book_id = 0;
			$resolution_error = null;

			if ($fb_user_book_id > 0) {
				// Verify ownership
				$exists = $wpdb->get_var($wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}politeia_user_books WHERE id=%d AND user_id=%d LIMIT 1",
					$fb_user_book_id,
					$user_id
				));
				if ($exists) {
					$user_book_id = (int) $exists;
				} else {
					$resolution_error = 'invalid_user_book_id';
				}
			} elseif ($fb_book_id > 0) {
				// Resolve User Book ID from Canonical Book ID
				$user_book_id = prs_ensure_user_book($user_id, $fb_book_id);
				if (!$user_book_id) {
					$resolution_error = 'user_book_resolution_failed';
				}
			}

			if ($resolution_error) {
				if ($transaction_started) {
					$wpdb->query('ROLLBACK');
				}
				error_log('[ReadingPlanner] Plan finish book error: ' . $resolution_error);
				return new WP_REST_Response(array('error' => $resolution_error), 400);
			}

			if ($user_book_id > 0) {
				$fb_inserted = $wpdb->insert(
					$plan_finish_book_table,
					array(
						'plan_id' => $plan_id,
						'user_book_id' => $user_book_id,
						'start_page' => $fb_start_page,
					),
					array('%d', '%d', '%d')
				);

				if (false === $fb_inserted) {
					if ($transaction_started) {
						$wpdb->query('ROLLBACK');
					}
					error_log('[ReadingPlanner] Plan finish book insert failed: ' . $wpdb->last_error);
					return new WP_REST_Response(array('error' => 'plan_finish_book_insert_failed'), 500);
				}
			}
		}

		if ($planned_sessions) {
			foreach ($planned_sessions as $session) {
				if (!is_array($session)) {
					continue;
				}

				$planned_start = isset($session['planned_start_datetime']) ? sanitize_text_field((string) $session['planned_start_datetime']) : '';
				$planned_end = isset($session['planned_end_datetime']) ? sanitize_text_field((string) $session['planned_end_datetime']) : '';

				if ('' === $planned_start || '' === $planned_end) {
					if ($transaction_started) {
						$wpdb->query('ROLLBACK');
					}
					error_log('[ReadingPlanner] Invalid planned session payload.');
					return new WP_REST_Response(array('error' => 'invalid_session'), 400);
				}

				$start_dt = date_create_immutable($planned_start, wp_timezone());
				if (!$start_dt) {
					if ($transaction_started) {
						$wpdb->query('ROLLBACK');
					}
					error_log('[ReadingPlanner] Invalid planned session date.');
					return new WP_REST_Response(array('error' => 'invalid_session'), 400);
				}

				$planned_start = $start_dt->setTime(0, 0, 0)->format('Y-m-d H:i:s');
				$planned_end = $start_dt->setTime(23, 59, 59)->format('Y-m-d H:i:s');

				$session_inserted = $wpdb->insert(
					$sessions_table,
					array(
						'plan_id' => $plan_id,
						'planned_start_datetime' => $planned_start,
						'planned_end_datetime' => $planned_end,
						'status' => 'planned',
						'previous_session_id' => isset($session['previous_session_id']) ? (int) $session['previous_session_id'] : null,
						'comment' => isset($session['comment']) ? wp_kses_post((string) $session['comment']) : null,
					),
					array('%d', '%s', '%s', '%s', '%d', '%s')
				);

				if (false === $session_inserted) {
					if ($transaction_started) {
						$wpdb->query('ROLLBACK');
					}
					error_log('[ReadingPlanner] Planned session insert failed: ' . $wpdb->last_error);
					return new WP_REST_Response(array('error' => 'planned_session_insert_failed'), 500);
				}
			}
		}

		if ($transaction_started) {
			$wpdb->query('COMMIT');
		}

		$baseline_id = create_user_baseline($user_id, $baseline_metrics, 'plan_acceptance');
		if (0 === $baseline_id) {
			error_log('[ReadingPlanner] Baseline creation failed for user ' . $user_id);
			return new WP_REST_Response(array('error' => 'baseline_creation_failed'), 500);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'plan_id' => $plan_id,
			),
			200
		);
	}

	public static function create_book(WP_REST_Request $request)
	{
		if (!is_user_logged_in()) {
			return new WP_REST_Response(array('error' => 'not_logged_in'), 401);
		}

		$can_create = apply_filters('prs_reading_plan_can_create_book', current_user_can('edit_posts'), get_current_user_id());
		if (!$can_create) {
			return new WP_REST_Response(array('error' => 'forbidden'), 403);
		}

		$nonce = $request->get_header('X-WP-Nonce') ?: ($request['nonce'] ?? '');
		if (!wp_verify_nonce($nonce, 'wp_rest')) {
			return new WP_REST_Response(array('error' => 'invalid_nonce'), 403);
		}

		$payload = $request->get_json_params();
		$result = self::process_create_book($payload, get_current_user_id());
		if (is_wp_error($result)) {
			$code = $result->get_error_code();
			if ('missing_required_fields' === $code) {
				$status = 400;
			} elseif ('active_plan' === $code) {
				$status = 409;
			} else {
				$status = 500;
			}
			return new WP_REST_Response(array('error' => $code), $status);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data' => $result,
			),
			200
		);
	}

	public static function ajax_create_book()
	{
		if (!is_user_logged_in()) {
			wp_send_json_error(array('error' => 'not_logged_in'), 401);
		}

		$can_create = apply_filters('prs_reading_plan_can_create_book', current_user_can('edit_posts'), get_current_user_id());
		if (!$can_create) {
			wp_send_json_error(array('error' => 'forbidden'), 403);
		}

		check_ajax_referer('prs_reading_plan_add_book', 'nonce');

		$payload = array(
			'title' => isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '',
			'author' => isset($_POST['author']) ? wp_unslash($_POST['author']) : '',
			'pages' => isset($_POST['pages']) ? absint($_POST['pages']) : 0,
			'cover_url' => isset($_POST['cover_url']) ? esc_url_raw(wp_unslash($_POST['cover_url'])) : '',
		);

		$result = self::process_create_book($payload, get_current_user_id());
		if (is_wp_error($result)) {
			$code = $result->get_error_code();
			if ('missing_required_fields' === $code) {
				$status = 400;
			} elseif ('active_plan' === $code) {
				$status = 409;
			} else {
				$status = 500;
			}
			wp_send_json_error(array('error' => $code), $status);
		}

		wp_send_json_success(
			array(
				'data' => $result,
			)
		);
	}

	public static function ajax_check_active_plan()
	{
		if (!is_user_logged_in()) {
			wp_send_json_error(array('error' => 'not_logged_in'), 401);
		}

		check_ajax_referer('prs_reading_plan_check_active', 'nonce');

		$title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
		$raw_author = isset($_POST['author']) ? wp_unslash($_POST['author']) : '';

		$author_parts = self::split_authors($raw_author);
		$primary_author = $author_parts['primary'];
		$other_authors = $author_parts['others'];

		if ('' === $title || '' === $primary_author) {
			wp_send_json_success(array('active' => false));
		}

		$all_authors = array_merge(array($primary_author), $other_authors);
		$book_id = 0;
		$slug = prs_generate_book_slug($title, null);
		if ($slug) {
			$book_id = (int) prs_get_book_id_by_slug($slug);
		}
		if (!$book_id) {
			$book_id = (int) prs_get_book_id_by_identity($title, $all_authors, null);
		}
		if (!$book_id) {
			$book_id = self::find_book_id_by_title_author($title, $all_authors);
		}
		if (!$book_id) {
			wp_send_json_success(array('active' => false));
		}

		global $wpdb;
		$user_id = get_current_user_id();
		$plans_table = $wpdb->prefix . 'politeia_plans';
		$goals_table = $wpdb->prefix . 'politeia_plan_goals';
		$active_plan_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.id
				 FROM {$plans_table} p
				 INNER JOIN {$wpdb->prefix}politeia_plan_finish_book pfb ON pfb.plan_id = p.id
				 INNER JOIN {$wpdb->prefix}politeia_user_books ub ON ub.id = pfb.user_book_id
				 WHERE p.user_id = %d
				   AND p.status = %s
				   AND ub.book_id = %d
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

	protected static function split_authors($raw_author)
	{
		$authors = array();
		if ('' !== $raw_author && null !== $raw_author) {
			$collect_authors = static function ($value) use (&$authors) {
				if (null === $value || '' === $value) {
					return;
				}

				$candidates = explode(',', (string) $value);
				foreach ($candidates as $candidate) {
					$clean_author = sanitize_text_field($candidate);
					if ('' === $clean_author) {
						continue;
					}
					$clean_author = preg_replace('/\s+/', ' ', $clean_author);
					$clean_author = trim((string) $clean_author);
					if ('' !== $clean_author) {
						$authors[] = $clean_author;
					}
				}
			};

			if (is_array($raw_author)) {
				foreach ($raw_author as $raw_value) {
					$collect_authors($raw_value);
				}
			} else {
				$collect_authors($raw_author);
			}
		}

		$primary_author = '';
		$other_authors = array();
		if (!empty($authors)) {
			$normalized = array();
			$unique = array();

			foreach ($authors as $raw_value) {
				$key = function_exists('mb_strtolower') ? mb_strtolower($raw_value, 'UTF-8') : strtolower($raw_value);
				if (isset($normalized[$key])) {
					continue;
				}
				$normalized[$key] = true;
				$unique[] = $raw_value;
			}

			if (!empty($unique)) {
				$primary_author = array_shift($unique);
				$other_authors = array_values($unique);
			}
		}

		return array(
			'primary' => $primary_author,
			'others' => $other_authors,
		);
	}

	protected static function find_book_id_by_title_author($title, array $authors)
	{
		$normalized_title = prs_normalize_title($title);
		if ('' === $normalized_title) {
			return 0;
		}

		$author_hashes = prs_get_author_hashes_from_names($authors);
		if (empty($author_hashes)) {
			return 0;
		}

		global $wpdb;
		$books_table = $wpdb->prefix . 'politeia_books';
		$pivot_table = $wpdb->prefix . 'politeia_book_authors';
		$authors_table = $wpdb->prefix . 'politeia_authors';

		$placeholders = implode(', ', array_fill(0, count($author_hashes), '%s'));
		$params = array_merge(array($normalized_title), $author_hashes);

		$sql = "
			SELECT b.id
			FROM {$books_table} b
			INNER JOIN {$pivot_table} ba ON ba.book_id = b.id
			INNER JOIN {$authors_table} a ON a.id = ba.author_id
			WHERE b.normalized_title = %s
			  AND a.name_hash IN ({$placeholders})
			LIMIT 1
		";

		$book_id = $wpdb->get_var($wpdb->prepare($sql, $params));
		return $book_id ? (int) $book_id : 0;
	}

	protected static function process_create_book($payload, $user_id)
	{
		$title = isset($payload['title']) ? sanitize_text_field((string) $payload['title']) : '';
		$raw_author = isset($payload['author']) ? $payload['author'] : '';
		$pages = isset($payload['pages']) ? absint($payload['pages']) : 0;
		$cover_url = isset($payload['cover_url']) ? esc_url_raw((string) $payload['cover_url']) : '';

		$author_parts = self::split_authors($raw_author);
		$primary_author = $author_parts['primary'];
		$authors = $author_parts['others'];

		if ('' === $title || '' === $primary_author || $pages <= 0) {
			return new \WP_Error('missing_required_fields', 'Missing required fields.');
		}

		global $wpdb;
		$user_id = (int) $user_id;
		$all_authors = array_merge(array($primary_author), $authors);

		$book_id = 0;
		$slug = prs_generate_book_slug($title, null);
		if ($slug) {
			$book_id = (int) prs_get_book_id_by_slug($slug);
		}
		if (!$book_id) {
			$book_id = (int) prs_get_book_id_by_identity($title, $all_authors, null);
		}
		if (!$book_id) {
			$book_id = self::find_book_id_by_title_author($title, $all_authors);
		}

		if ($book_id) {
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
			if ($active_plan_id) {
				return new \WP_Error('active_plan', 'Active plan already exists for this book.');
			}

			$user_book_id = prs_ensure_user_book($user_id, (int) $book_id);
			if (!$user_book_id) {
				return new \WP_Error('user_book_failed', 'Could not attach book to user.');
			}
			$wpdb->update(
				$wpdb->prefix . 'politeia_user_books',
				array('pages' => $pages),
				array('id' => (int) $user_book_id),
				array('%d'),
				array('%d')
			);
			if ($cover_url) {
				$wpdb->update(
					$wpdb->prefix . 'politeia_user_books',
					array('cover_reference' => $cover_url),
					array('id' => (int) $user_book_id),
					array('%s'),
					array('%d')
				);
			}

			return array(
				'book_id' => (int) $book_id,
				'user_book_id' => (int) $user_book_id,
				'title' => $title,
				'author' => $primary_author,
				'pages' => (int) $pages,
				'cover_url' => $cover_url,
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
		if (is_wp_error($created)) {
			return new \WP_Error($created->get_error_code(), $created->get_error_message());
		}

		$book_id = (int) $created;
		if (!$book_id) {
			return new \WP_Error('book_not_found', 'Book not found after confirmation.');
		}

		$user_book_id = prs_ensure_user_book($user_id, (int) $book_id);
		if (!$user_book_id) {
			return new \WP_Error('user_book_failed', 'Could not attach book to user.');
		}
		$wpdb->update(
			$wpdb->prefix . 'politeia_user_books',
			array('pages' => $pages),
			array('id' => (int) $user_book_id),
			array('%d'),
			array('%d')
		);
		if ($cover_url) {
			$wpdb->update(
				$wpdb->prefix . 'politeia_user_books',
				array('cover_reference' => $cover_url),
				array('id' => (int) $user_book_id),
				array('%s'),
				array('%d')
			);
		}

		return array(
			'book_id' => (int) $book_id,
			'user_book_id' => (int) $user_book_id,
			'title' => $title,
			'author' => $primary_author,
			'pages' => (int) $pages,
			'cover_url' => $cover_url,
		);
	}

	public static function get_plan(WP_REST_Request $request)
	{
		$plan_id = (int) $request['plan_id'];
		global $wpdb;
		$user_id = get_current_user_id();
		$plans_table = $wpdb->prefix . 'politeia_plans';
		$goals_table = $wpdb->prefix . 'politeia_plan_goals';
		$sessions_table = $wpdb->prefix . 'politeia_planned_sessions';
		$reading_sessions_table = $wpdb->prefix . 'politeia_reading_sessions';
		$timezone = wp_timezone();

		if (!$plan_id) {
			return new WP_REST_Response(array('error' => 'invalid_plan_id'), 404);
		}

		$plan = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$plans_table} WHERE id = %d AND user_id = %d", $plan_id, $user_id), ARRAY_A);
		if (!$plan) {
			return new WP_REST_Response(array('error' => 'not_found'), 404);
		}


		// --- FREEZE THE PAST: Lazy Settlement ---
		// Before any derivation, ensure expired sessions are settled.
		$current_plan_type = isset($plan['plan_type']) ? $plan['plan_type'] : '';
		if ('habit' === $current_plan_type && class_exists('\\Politeia\\ReadingPlanner\\HabitSettlementEngine')) {
			\Politeia\ReadingPlanner\HabitSettlementEngine::settle((int) $plan_id, (int) $user_id);
		} elseif (class_exists('\\Politeia\\ReadingPlanner\\PlanSettlementEngine')) {
			\Politeia\ReadingPlanner\PlanSettlementEngine::settle((int) $plan_id, (int) $user_id);
		}

		// --- INVARIANT ENFORCEMENT & GOAL RESOLUTION ---
		$p_type = isset($plan['plan_type']) ? (string) $plan['plan_type'] : '';
		$goal_kind = '';
		$goal_book_id = 0;
		$user_book_id = 0;
		$goal_target = 0;
		$goal_starting_page = 1;

		// 1. Try New Tables First
		if ('habit' === $p_type) {
			$ph_table = $wpdb->prefix . 'politeia_plan_habit';
			$habit_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$ph_table} WHERE plan_id = %d LIMIT 1", $plan_id));
			if ($habit_row) {
				$goal_kind = 'habit';
				// Habits don't strictly have a "book_id" for the goal itself, usually 'Any Book' or a specific one?
				// Old logic allowed habit to be tied to a book?
				// If so, it would be in plan_goals. But phase 2/3 habits are generic.
			} else {
				error_log(sprintf('[ReadingPlanner] INVARIANT BROKEN: Plan ID %d (habit) missing plan_habit row.', $plan_id));
			}
		} elseif ('finish_book' === $p_type) {
			$pfb_table = $wpdb->prefix . 'politeia_plan_finish_book';
			$ub_table = $wpdb->prefix . 'politeia_user_books';
			// Join user_books to get canonical book_id and pages
			$fb_row = $wpdb->get_row($wpdb->prepare(
				"SELECT pfb.user_book_id, pfb.start_page, ub.book_id, ub.pages
				 FROM {$pfb_table} pfb
				 INNER JOIN {$ub_table} ub ON ub.id = pfb.user_book_id
				 WHERE pfb.plan_id = %d LIMIT 1",
				$plan_id
			));

			if ($fb_row) {
				$goal_kind = 'complete_books';
				$user_book_id = (int) $fb_row->user_book_id;
				$goal_book_id = (int) $fb_row->book_id;
				$goal_starting_page = (int) $fb_row->start_page;
				if ($goal_starting_page < 1)
					$goal_starting_page = 1;
				$total_book_pages = (int) $fb_row->pages;
				$goal_target = max(0, $total_book_pages - $goal_starting_page); // Approximate target derived
			} else {
				error_log(sprintf('[ReadingPlanner] INVARIANT BROKEN: Plan ID %d (finish_book) missing plan_finish_book row.', $plan_id));
			}
		}

		// 2. Fallback to Legacy Goals Table
		if ('' === $goal_kind) {
			$goal = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$goals_table}
					 WHERE plan_id = %d
					 ORDER BY (book_id IS NULL), id ASC
					 LIMIT 1",
					$plan_id
				),
				ARRAY_A
			);
			if ($goal) {
				$goal_kind = !empty($goal['goal_kind']) ? (string) $goal['goal_kind'] : '';
				$goal_book_id = !empty($goal['book_id']) ? (int) $goal['book_id'] : 0;
				$goal_target = !empty($goal['target_value']) ? (int) $goal['target_value'] : 0;
				$goal_starting_page = !empty($goal['starting_page']) ? (int) $goal['starting_page'] : 1;
				if ($goal_starting_page < 1)
					$goal_starting_page = 1;
				// If legacy plan has goal_book_id but no user_book_id, resolve it
				if ($goal_book_id > 0 && 0 === $user_book_id) {
					$user_book_id = prs_ensure_user_book($user_id, $goal_book_id);
				}
			}
		}

		$total_pages = $goal_target;
		$today_key = current_time('Y-m-d');

		// Fetch updated sessions
		$sessions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT planned_start_datetime, status
				 FROM {$sessions_table}
				 WHERE plan_id = %d
				 ORDER BY planned_start_datetime ASC",
				$plan_id
			),
			ARRAY_A
		);

		// Calculate Actual Sessions
		$actual_sessions_payload = array();
		// Use USER_BOOK_ID to find reading sessions
		if ($user_book_id > 0) {
			$actual_sessions = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT start_time, start_page, end_page
					 FROM {$reading_sessions_table}
					 WHERE user_id = %d AND user_book_id = %d AND deleted_at IS NULL",
					$user_id,
					$user_book_id
				),
				ARRAY_A
			);
			if ($actual_sessions) {
				foreach ($actual_sessions as $actual_session) {
					$actual_session = (array) $actual_session;
					if (empty($actual_session['start_time']))
						continue;
					$start_page = isset($actual_session['start_page']) ? (int) $actual_session['start_page'] : 0;
					$end_page = isset($actual_session['end_page']) ? (int) $actual_session['end_page'] : 0;
					if ($start_page <= 0 || $end_page <= 0 || $end_page < $start_page)
						continue;
					$date_key = get_date_from_gmt($actual_session['start_time'], 'Y-m-d');
					if ('' === $date_key)
						continue;
					$actual_sessions_payload[] = array(
						'date' => $date_key,
						'start' => $start_page,
						'end' => $end_page,
						'start_time' => (string) $actual_session['start_time'],
					);
				}
			}
		} elseif ($goal_book_id > 0) {
			// Fallback: Query by book_id if user_book_id somehow missing (removed column scenario makes this query invalid if column gone!)
			// But since we updated schema, ONLY user_book_id column exists.
			// So we CANNOT query by book_id anymore.
			// But we resolved user_book_id above. So this block is dead code unless user_book_id resolution failed.
			// If resolution failed, we can't find sessions.
		}


		// Calculate Derivations
		$highest_page_read = 0;
		if ($user_book_id > 0) {
			$highest_page_read = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT MAX(end_page) FROM {$reading_sessions_table}
					 WHERE user_id = %d AND user_book_id = %d AND deleted_at IS NULL AND end_page IS NOT NULL",
					$user_id,
					$user_book_id
				)
			);
		}
		if (!class_exists('\\Politeia\\ReadingPlanner\\PlanSessionDeriver')) {
			// Fallback or error, though it should be loaded
			return new WP_REST_Response(array('error' => 'deriver_missing'), 500);
		}

		$pages_read_in_plan = \Politeia\ReadingPlanner\PlanSessionDeriver::calculate_pages_read($highest_page_read, $goal_starting_page);
		$progress = \Politeia\ReadingPlanner\PlanSessionDeriver::calculate_progress($pages_read_in_plan, $total_pages);

		$future_session_dates = array();
		$session_dates = array();
		$session_items_map = array();

		if ($sessions) {
			foreach ($sessions as $session) {
				$session = (array) $session;
				if (empty($session['planned_start_datetime']))
					continue;
				$session_dt = date_create_immutable($session['planned_start_datetime'], $timezone);
				$session_ts = $session_dt ? $session_dt->getTimestamp() : null;
				$date_key = $session_ts ? wp_date('Y-m-d', $session_ts, $timezone) : '';
				if ('' === $date_key)
					continue;

				$session_dates[] = $date_key;
				$s_status = !empty($session['status']) ? (string) $session['status'] : 'planned';

				if ($s_status === 'planned' && $date_key >= $today_key) {
					$future_session_dates[] = $date_key;
				}

				$session_items_map[$date_key] = array(
					'date' => $date_key,
					'status' => $s_status,
					'planned_start_page' => 0,
					'planned_end_page' => 0,
				);
			}
		}

		$session_dates = array_values(array_unique($session_dates));

		$derived_projections = \Politeia\ReadingPlanner\PlanSessionDeriver::derive_sessions(
			$total_pages,
			$goal_starting_page,
			$pages_read_in_plan,
			$future_session_dates,
			$today_key
		);

		foreach ($derived_projections as $projection) {
			$d = $projection['date'];
			if (isset($session_items_map[$d])) {
				$session_items_map[$d]['planned_start_page'] = $projection['start_page'];
				$session_items_map[$d]['planned_end_page'] = $projection['end_page'];
				$session_items_map[$d]['order'] = $projection['order'];
			}
		}
		$session_items = array_values($session_items_map);

		return new WP_REST_Response(array(
			'success' => true,
			'plan_id' => $plan_id,
			'total_pages' => $total_pages,
			'pages_read' => $pages_read_in_plan,
			'progress' => $progress,
			'session_dates' => $session_dates,
			'session_items' => $session_items,
			'actual_sessions' => $actual_sessions_payload,
			'today_key' => $today_key,
		), 200);
	}

	/**
	 * Add a planned session (Intent-only).
	 */
	public static function add_session(WP_REST_Request $request)
	{
		$plan_id = (int) $request['plan_id'];
		$payload = $request->get_json_params();
		$date = isset($payload['session_date']) ? sanitize_text_field($payload['session_date']) : '';

		if (!$plan_id || !$date) {
			return new WP_REST_Response(array('error' => 'missing_params'), 400);
		}

		global $wpdb;
		$plans_table = $wpdb->prefix . 'politeia_plans';
		$sessions_table = $wpdb->prefix . 'politeia_planned_sessions';

		// Check ownership and status
		$plan = $wpdb->get_row($wpdb->prepare("SELECT user_id, status FROM {$plans_table} WHERE id = %d", $plan_id));
		if (!$plan || (int) $plan->user_id !== get_current_user_id() || 'accepted' !== $plan->status) {
			return new WP_REST_Response(array('error' => 'forbidden'), 403);
		}

		// Insert checks (optional: prevent duplicate date? No, allow multiple)
		$start_dt = date_create_immutable($date, wp_timezone());
		if (!$start_dt) {
			return new WP_REST_Response(array('error' => 'invalid_date'), 400);
		}
		$start_sql = $start_dt->setTime(0, 0, 0)->format('Y-m-d H:i:s');
		$end_sql = $start_dt->setTime(23, 59, 59)->format('Y-m-d H:i:s');

		$wpdb->insert(
			$sessions_table,
			array(
				'plan_id' => $plan_id,
				'planned_start_datetime' => $start_sql,
				'planned_end_datetime' => $end_sql,
				'status' => 'planned',
				// Legacy fields are nullable in schema 1.5.0
			),
			array('%d', '%s', '%s', '%s')
		);

		if (class_exists('\\Politeia\\ReadingPlanner\\PlanSessionDeriver')) {
			\Politeia\ReadingPlanner\PlanSessionDeriver::invalidate_plan_cache($plan_id);
		}

		return new WP_REST_Response(array('success' => true), 200);
	}

	/**
	 * Remove a planned session (Intent-only).
	 */
	public static function remove_session(WP_REST_Request $request)
	{
		$plan_id = (int) $request['plan_id'];
		$date = sanitize_text_field($request['date']);

		if (!$plan_id || !$date) {
			return new WP_REST_Response(array('error' => 'missing_params'), 400);
		}

		global $wpdb;
		$plans_table = $wpdb->prefix . 'politeia_plans';
		$sessions_table = $wpdb->prefix . 'politeia_planned_sessions';

		// Check ownership
		$plan = $wpdb->get_row($wpdb->prepare("SELECT user_id, plan_type FROM {$plans_table} WHERE id = %d", $plan_id));
		if (!$plan || (int) $plan->user_id !== get_current_user_id()) {
			return new WP_REST_Response(array('error' => 'forbidden'), 403);
		}

		// GUARD: Habit plans are immutable (cannot delete sessions)
		if ('habit' === $plan->plan_type) {
			return new WP_REST_Response(
				array('error' => 'forbidden', 'message' => 'Habit sessions cannot be deleted.'),
				403
			);
		}


		// Cannot remove 'accomplished' or 'missed' (Immutability)
		// We only delete 'planned' status.
		// FREEZE THE PAST: We only delete FUTURE sessions. Past 'planned' must settle to missed/accomplished.
		$now = current_time('Y-m-d H:i:s');
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$sessions_table}
				 WHERE plan_id = %d
				 AND DATE(planned_start_datetime) = %s
				 AND status = 'planned'
				 AND planned_start_datetime >= %s
				 LIMIT 1",
				$plan_id,
				$date,
				$now
			)
		);


		if (class_exists('\\Politeia\\ReadingPlanner\\PlanSessionDeriver')) {
			\Politeia\ReadingPlanner\PlanSessionDeriver::invalidate_plan_cache($plan_id);
		}

		return new WP_REST_Response(array('success' => true, 'deleted' => $deleted), 200);
	}

	/**
	 * Move a planned session (Intent-only).
	 */
	public static function move_session(WP_REST_Request $request)
	{
		$plan_id = (int) $request['plan_id'];
		$origin_date = sanitize_text_field($request['date']);
		$payload = $request->get_json_params();
		$new_date = isset($payload['new_date']) ? sanitize_text_field($payload['new_date']) : '';

		if (!$plan_id || !$origin_date || !$new_date) {
			return new WP_REST_Response(array('error' => 'missing_params'), 400);
		}

		global $wpdb;
		$plans_table = $wpdb->prefix . 'politeia_plans';
		$sessions_table = $wpdb->prefix . 'politeia_planned_sessions';

		// Check ownership
		$plan = $wpdb->get_row($wpdb->prepare("SELECT user_id, plan_type FROM {$plans_table} WHERE id = %d", $plan_id));
		if (!$plan || (int) $plan->user_id !== get_current_user_id()) {
			return new WP_REST_Response(array('error' => 'forbidden'), 403);
		}

		// GUARD: Habit plans are immutable (cannot move sessions)
		if ('habit' === $plan->plan_type) {
			return new WP_REST_Response(
				array('error' => 'forbidden', 'message' => 'Habit sessions cannot be moved.'),
				403
			);
		}

		// Prepare new date
		$target_dt = date_create_immutable($new_date, wp_timezone());
		if (!$target_dt) {
			return new WP_REST_Response(array('error' => 'invalid_target_date'), 400);
		}
		$target_start_sql = $target_dt->setTime(0, 0, 0)->format('Y-m-d H:i:s');
		$target_end_sql = $target_dt->setTime(23, 59, 59)->format('Y-m-d H:i:s');

		// Update only 'planned' sessions (Immutability)
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$sessions_table}
				 SET planned_start_datetime = %s,
				     planned_end_datetime = %s
				 WHERE plan_id = %d
				 AND DATE(planned_start_datetime) = %s
				 AND status = 'planned'
				 LIMIT 1",
				$target_start_sql,
				$target_end_sql,
				$plan_id,
				$origin_date
			)
		);

		if (class_exists('\\Politeia\\ReadingPlanner\\PlanSessionDeriver')) {
			\Politeia\ReadingPlanner\PlanSessionDeriver::invalidate_plan_cache($plan_id);
		}

		return new WP_REST_Response(array('success' => true, 'updated' => $updated), 200);
	}
}
Rest::init();
