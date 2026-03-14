<?php
/**
 * Reading Sessions AJAX (start/save) con auto-started y auto-finished
 */
if (!defined('ABSPATH')) {
	exit;
}

class Politeia_Reading_Sessions
{
	private const HARD_PROMPT_SECONDS = 4800; // 80 minutes
	private const AUTO_STOP_SECONDS = 6000; // 100 minutes (80 + 20)
	private const ACTIVE_SESSIONS_OPTION = 'politeia_reading_active_recorder_sessions';
	private const ACTIVE_SESSION_TRANSIENT_PREFIX = 'politeia_reading_active_recorder_session_';
	private const CRON_HOOK = 'politeia_reading_recorder_autostop';
	private const CRON_SCHEDULE = 'politeia_reading_15min';

	public static function init()
	{
		add_action('wp_ajax_prs_start_reading', array(__CLASS__, 'ajax_start'));
		add_action('wp_ajax_prs_save_reading', array(__CLASS__, 'ajax_save'));
		add_action('wp_ajax_prs_add_manual_session', array(__CLASS__, 'ajax_add_manual_session'));
		add_action('wp_ajax_prs_sr_heartbeat', array(__CLASS__, 'ajax_heartbeat'));
		add_action('wp_ajax_prs_sr_auto_stop', array(__CLASS__, 'ajax_auto_stop'));

		// Render parcial (tabla de sesiones + paginación) para AJAX en my-book-single.php
		add_action('wp_ajax_prs_render_sessions', array(__CLASS__, 'ajax_render_sessions'));

		add_filter('cron_schedules', array(__CLASS__, 'register_cron_schedule'));
		add_action('init', array(__CLASS__, 'schedule_autostop_cron'));
		add_action(self::CRON_HOOK, array(__CLASS__, 'cron_autostop'));
	}

	/*
	=========================
	 * AJAX: START
	 * ========================= */
	public static function ajax_start()
	{
		if (!is_user_logged_in()) {
			self::err('auth', 401);
		}
		if (!self::verify_nonce('prs_reading_nonce', array('nonce'))) {
			self::err('bad_nonce', 403);
		}

		$user_id = get_current_user_id();
		$book_id = isset($_POST['book_id']) ? absint($_POST['book_id']) : 0;
		if (!$book_id) {
			self::err('invalid_book', 400);
		}

		$ub_row = self::get_user_book_row($user_id, $book_id);
		if (!$ub_row) {
			self::err('forbidden', 403);
		}

		// Debe tener pages definidos
		$total_pages = (int) ($ub_row->pages ?? 0);
		if ($total_pages <= 0) {
			self::err('pages_required', 400);
		}

		// Bloqueo por estado de posesión
		$owning_status = (string) ($ub_row->owning_status ?? 'in_shelf');
		if (self::blocked_by_status($owning_status)) {
			self::err('not_in_possession', 403);
		}

		$start_page = isset($_POST['start_page']) ? absint($_POST['start_page']) : 0;
		if ($start_page < 1) {
			self::err('invalid_start_page', 400);
		}

		$chapter = isset($_POST['chapter_name']) ? sanitize_text_field(wp_unslash($_POST['chapter_name'])) : '';
		if (strlen($chapter) > 255) {
			$chapter = substr($chapter, 0, 255);
		}

		global $wpdb;
		$t = $wpdb->prefix . 'politeia_reading_sessions';

		self::cleanup_active_sessions();

		$existing = self::find_active_session_id($user_id, $book_id);
		if ($existing > 0) {
			$meta = self::get_active_session_meta($existing);
			$started_at = $meta && !empty($meta['started_at_gmt']) ? (string) $meta['started_at_gmt'] : '';
			if (!$started_at) {
				$row = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT start_time FROM {$t} WHERE id=%d AND user_id=%d AND deleted_at IS NULL LIMIT 1",
						$existing,
						$user_id
					)
				);
				if ($row && !empty($row->start_time)) {
					$started_at = (string) $row->start_time;
				}
			}
			if ($started_at) {
				// Refresh transient TTL for the active session.
				set_transient(
					self::ACTIVE_SESSION_TRANSIENT_PREFIX . $existing,
					array(
						'session_id' => (int) $existing,
						'user_id' => (int) $user_id,
						'book_id' => (int) $book_id,
						'user_book_id' => (int) $ub_row->id,
						'started_at_gmt' => (string) $started_at,
					),
					3 * HOUR_IN_SECONDS
				);

				self::ok(
					array(
						'session_id' => (int) $existing,
						'started_at' => $started_at,
						'reused'     => 1,
					)
				);
			}
		}

		$now_gmt = gmdate('Y-m-d H:i:s', current_time('timestamp', true)); // GMT
		// Guardamos placeholder (end_time=end_time=start_time) por restricción NOT NULL
		$ins = array(
			'user_id' => (int) $user_id,
			'user_book_id' => (int) $ub_row->id,
			'start_time' => $now_gmt,
			'end_time' => $now_gmt,
			'start_page' => max(1, min($start_page, $total_pages)),
			'end_page' => max(1, min($start_page, $total_pages)),
			'chapter_name' => $chapter ?: null,
		);

		$formats = array('%d', '%d', '%s', '%s', '%d', '%d', '%s');
		if (self::table_has_columns('politeia_reading_sessions', array('insert_type'))) {
			$ins['insert_type'] = 'recorder';
			$formats[] = '%s';
		}

		$ok = $wpdb->insert($t, $ins, $formats);
		if (!$ok) {
			self::err('db_insert_failed', 500);
		}

		$session_id = (int) $wpdb->insert_id;

		self::register_active_session(
			$session_id,
			(int) $user_id,
			(int) $book_id,
			(int) $ub_row->id,
			$now_gmt
		);

		self::ok(
			array(
				'session_id' => $session_id,
				'started_at' => $now_gmt,
				'reused'     => 0,
			)
		);
	}

	/*
	=========================
	 * AJAX: SAVE
	 * ========================= */
	public static function ajax_save()
	{
		if (!is_user_logged_in()) {
			self::err('auth', 401);
		}
		if (!self::verify_nonce('prs_reading_nonce', array('nonce'))) {
			self::err('bad_nonce', 403);
		}

		$data = self::save_session_common('recorder', true);
		self::ok($data);
	}

	/*
	=========================
	 * AJAX: ADD MANUAL
	 * ========================= */
	public static function ajax_add_manual_session()
	{
		if (!is_user_logged_in()) {
			self::err('auth', 401);
		}
		if (!self::verify_nonce('prs_reading_nonce', array('nonce'))) {
			self::err('bad_nonce', 403);
		}

		$data = self::save_session_common('manual', false);
		self::ok($data);
	}

	/**
	 * Shared implementation for saving a session.
	 *
	 * @param string $insert_type 'manual' or 'recorder'.
	 * @param bool   $allow_update_existing Allow updating an existing session by session_id.
	 * @return array<string,mixed>
	 */
	private static function save_session_common($insert_type, $allow_update_existing)
	{
		$user_id = get_current_user_id();
		$book_id = isset($_POST['book_id']) ? absint($_POST['book_id']) : 0;
		if (!$book_id) {
			self::err('invalid_book', 400);
		}

		$ub_row = self::get_user_book_row($user_id, $book_id);
		if (!$ub_row) {
			self::err('forbidden', 403);
		}

		$total_pages = (int) ($ub_row->pages ?? 0);
		if ($total_pages <= 0) {
			self::err('pages_required', 400);
		}

		$owning_status = (string) ($ub_row->owning_status ?? 'in_shelf');
		if (self::blocked_by_status($owning_status)) {
			self::err('not_in_possession', 403);
		}

		$start_page = isset($_POST['start_page']) ? absint($_POST['start_page']) : 0;
		$end_page = isset($_POST['end_page']) ? absint($_POST['end_page']) : 0;

		// clamp a [1..pages]
		$start_page = max(1, min($start_page, $total_pages));
		$end_page = max(1, min($end_page, $total_pages));

		if ($start_page < 1 || $end_page < 1 || $end_page < $start_page) {
			self::err('invalid_pages', 400);
		}

		$chapter = isset($_POST['chapter_name']) ? sanitize_text_field(wp_unslash($_POST['chapter_name'])) : '';
		if (strlen($chapter) > 255) {
			$chapter = substr($chapter, 0, 255);
		}

		$duration_sec = isset($_POST['duration_sec']) ? absint($_POST['duration_sec']) : 0;

		global $wpdb;
		$t = $wpdb->prefix . 'politeia_reading_sessions';

		$start_gmt = '';
		$end_gmt   = '';
		if ($insert_type === 'manual') {
			$raw_start = isset($_POST['start_datetime']) ? sanitize_text_field(wp_unslash($_POST['start_datetime'])) : '';
			$raw_end   = isset($_POST['end_datetime']) ? sanitize_text_field(wp_unslash($_POST['end_datetime'])) : '';
			$raw_start = trim((string) $raw_start);
			$raw_end   = trim((string) $raw_end);
			if ($raw_start === '' || $raw_end === '') {
				self::err('invalid_datetime', 400);
			}

			$tz = wp_timezone();
			$start_local = \DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $raw_start, $tz);
			if (!$start_local) {
				$start_local = \DateTimeImmutable::createFromFormat('Y-m-d\\TH:i:s', $raw_start, $tz);
			}
			$end_local = \DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $raw_end, $tz);
			if (!$end_local) {
				$end_local = \DateTimeImmutable::createFromFormat('Y-m-d\\TH:i:s', $raw_end, $tz);
			}
			if (!$start_local || !$end_local) {
				self::err('invalid_datetime', 400);
			}

			$start_utc = $start_local->setTimezone(new \DateTimeZone('UTC'));
			$end_utc   = $end_local->setTimezone(new \DateTimeZone('UTC'));

			if ($end_utc->getTimestamp() < $start_utc->getTimestamp()) {
				self::err('invalid_time_range', 400);
			}

			$start_gmt = $start_utc->format('Y-m-d H:i:s');
			$end_gmt   = $end_utc->format('Y-m-d H:i:s');
		} else {
			$end_gmt = gmdate('Y-m-d H:i:s', current_time('timestamp', true)); // end_time GMT
			$end_dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $end_gmt, new \DateTimeZone('UTC'));
			$end_ts = $end_dt ? $end_dt->getTimestamp() : time();
			$start_ts = $duration_sec > 0 ? max(0, $end_ts - $duration_sec) : $end_ts;
			$start_gmt = gmdate('Y-m-d H:i:s', $start_ts);
		}

		$session_id = 0;
		if ($allow_update_existing) {
			$session_id = isset($_POST['session_id']) ? absint($_POST['session_id']) : 0;
		}
		if ($allow_update_existing && $session_id > 0) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id,user_id,user_book_id,start_time,end_time,insert_type FROM {$t} WHERE id=%d AND deleted_at IS NULL LIMIT 1",
					$session_id
				)
			);
			if ($row && (int) $row->user_id === $user_id && (int) $row->user_book_id === (int) $ub_row->id) {
				$is_active = self::is_active_session($session_id);
				$is_automatic_stop = isset($row->insert_type) && (string) $row->insert_type === 'automatic_stop';
				$is_placeholder = !empty($row->start_time) && !empty($row->end_time) && (string) $row->start_time === (string) $row->end_time;

				// When updating an existing recorder session, treat DB start_time as authoritative.
				if ($insert_type === 'recorder' && !empty($row->start_time)) {
					$start_gmt = (string) $row->start_time;
				}

				$forced_type = null;
				if ($insert_type === 'recorder' && ($is_active || $is_placeholder) && !$is_automatic_stop && !empty($row->start_time)) {
					$forced = self::compute_forced_end_time((string) $row->start_time);
					if ($forced['forced']) {
						$end_gmt = $forced['end_time'];
						$forced_type = 'automatic_stop';
					}
				}

				$update_data = array(
					'start_time' => $start_gmt,
					'end_time' => $end_gmt,
					'start_page' => $start_page,
					'end_page' => $end_page,
					'chapter_name' => $chapter ?: null,
				);
				$update_formats = array('%s', '%s', '%d', '%d', '%s');
				if ($forced_type && self::table_has_columns('politeia_reading_sessions', array('insert_type'))) {
					$update_data['insert_type'] = $forced_type;
					$update_formats[] = '%s';
				}

				$wpdb->update(
					$t,
					$update_data,
					array('id' => $session_id),
					$update_formats,
					array('%d')
				);
				if ($wpdb->last_error) {
					self::err('db_update_failed', 500);
				}

				// Closing a recorder session ends its active lifecycle (safe even if already deregistered).
				if ($insert_type === 'recorder') {
					self::deregister_active_session($session_id);
				}
			} else {
				$session_id = 0; // caer a inserción
			}
		}
		if ($session_id === 0) {
			$insert = array(
				'user_id' => $user_id,
				'user_book_id' => (int) $ub_row->id,
				'start_time' => $start_gmt,
				'end_time' => $end_gmt,
				'start_page' => $start_page,
				'end_page' => $end_page,
				'chapter_name' => $chapter ?: null,
			);
			$formats = array('%d', '%d', '%s', '%s', '%d', '%d', '%s');
			if (self::table_has_columns('politeia_reading_sessions', array('insert_type'))) {
				$insert_type_value = $insert_type;
				if ($insert_type === 'recorder') {
					// Clamp derived durations to avoid indefinite sessions.
					$start_dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $start_gmt, new \DateTimeZone('UTC'));
					$end_dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $end_gmt, new \DateTimeZone('UTC'));
					if ($start_dt && $end_dt) {
						$elapsed = max(0, $end_dt->getTimestamp() - $start_dt->getTimestamp());
						if ($elapsed >= self::AUTO_STOP_SECONDS) {
							$insert_type_value = 'automatic_stop';
							$end_gmt = gmdate('Y-m-d H:i:s', $start_dt->getTimestamp() + self::AUTO_STOP_SECONDS);
							$insert['end_time'] = $end_gmt;
						}
					}
				}
				$insert['insert_type'] = $insert_type_value;
				$formats[] = '%s';
			}

			$ok = $wpdb->insert($t, $insert, $formats);
			if (!$ok) {
				self::err('db_insert_failed', 500);
			}
			$session_id = (int) $wpdb->insert_id;
		}

		// 1) Auto-pasar a STARTED si estaba NOT_STARTED
		if ((string) $ub_row->reading_status === 'not_started') {
			self::update_user_book_fields(
				(int) $ub_row->id,
				array(
					'reading_status' => 'started',
				)
			);
			// refresca $ub_row para decisiones siguientes
			$ub_row = self::get_user_book_row($user_id, $book_id);
		}

		// 2) Calcular cobertura y auto-finished
		$coverage = self::coverage_stats($user_id, $book_id, $total_pages);
		$has_full = $coverage['full'] ?? false;

		if ($has_full) {
			// si no es finished o es finished auto, lo ponemos finished auto
			$do_finish = false;
				$update = array('reading_status' => 'finished');
			if (self::table_has_columns('politeia_user_books', array('finish_mode', 'finished_at'))) {
				$update['finish_mode'] = 'auto';
				$update['finished_at'] = $end_gmt;
			}
			if ((string) $ub_row->reading_status !== 'finished') {
				$do_finish = true;
			} else {
				// está finished: solo lo tocamos si es auto o nulo
				if (property_exists($ub_row, 'finish_mode')) {
					$fm = (string) ($ub_row->finish_mode ?? '');
					if ($fm === '' || $fm === 'auto') {
						$do_finish = true;
					}
				} else {
					// si no existe la col, asumimos que podemos setear finished
					$do_finish = true;
				}
			}
			if ($do_finish) {
				self::update_user_book_fields((int) $ub_row->id, $update);
			}
		} else {
			// si estaba finished auto, revertir a started
			$was_finished_auto = false;
			if ((string) $ub_row->reading_status === 'finished') {
				if (property_exists($ub_row, 'finish_mode')) {
					$was_finished_auto = ((string) $ub_row->finish_mode === 'auto');
				} else {
					// sin columna, no sabríamos: no tocamos
					$was_finished_auto = false;
				}
			}
			if ($was_finished_auto) {
				$update = array('reading_status' => 'started');
				// limpiar finished_at/finish_mode si existen
				if (self::table_has_columns('politeia_user_books', array('finish_mode', 'finished_at'))) {
					$update['finish_mode'] = null;
					$update['finished_at'] = null;
				}
				self::update_user_book_fields((int) $ub_row->id, $update);
			}
		}

		self::mark_planned_session_accomplished($user_id, $book_id, $start_gmt);

		return array(
			'session_id' => (int) $session_id,
			'start_time' => $start_gmt,
			'end_time' => $end_gmt,
			'coverage' => $coverage, // { covered, total, full }
		);
	}

	/*
	=========================
	 * AJAX: heartbeat / auto-stop
	 * ========================= */

	public static function ajax_heartbeat()
	{
		if (!is_user_logged_in()) {
			self::err('auth', 401);
		}
		if (!self::verify_nonce('prs_reading_nonce', array('nonce'))) {
			self::err('bad_nonce', 403);
		}

		$user_id = get_current_user_id();
		$session_id = isset($_POST['session_id']) ? absint($_POST['session_id']) : 0;
		if ($session_id <= 0) {
			self::err('invalid_session', 400);
		}

		global $wpdb;
		$t = $wpdb->prefix . 'politeia_reading_sessions';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id,user_id,start_time,deleted_at FROM {$t} WHERE id=%d AND deleted_at IS NULL LIMIT 1",
				$session_id
			)
		);
		if (!$row || (int) $row->user_id !== (int) $user_id) {
			self::err('forbidden', 403);
		}
		if (empty($row->start_time)) {
			self::err('invalid_session', 400);
		}

		$start_dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $row->start_time, new \DateTimeZone('UTC'));
		if (!$start_dt) {
			self::err('invalid_session', 400);
		}

		$now_ts = (int) current_time('timestamp', true);
		$elapsed = max(0, $now_ts - $start_dt->getTimestamp());

		self::ok(
			array(
				'elapsed_sec' => (int) $elapsed,
				'should_prompt_80' => $elapsed >= self::HARD_PROMPT_SECONDS && $elapsed < self::AUTO_STOP_SECONDS ? 1 : 0,
				'must_stop_100' => $elapsed >= self::AUTO_STOP_SECONDS ? 1 : 0,
				'is_active' => self::is_active_session($session_id) ? 1 : 0,
			)
		);
	}

	public static function ajax_auto_stop()
	{
		if (!is_user_logged_in()) {
			self::err('auth', 401);
		}
		if (!self::verify_nonce('prs_reading_nonce', array('nonce'))) {
			self::err('bad_nonce', 403);
		}

		$user_id = get_current_user_id();
		$session_id = isset($_POST['session_id']) ? absint($_POST['session_id']) : 0;
		if ($session_id <= 0) {
			self::err('invalid_session', 400);
		}

		$result = self::auto_stop_session($session_id, (int) $user_id, 'ajax');
		if (isset($result['error'])) {
			self::err((string) $result['error'], isset($result['code']) ? (int) $result['code'] : 400);
		}

		self::ok($result);
	}

	/*
	=========================
	 * Active session registry + cron
	 * ========================= */

	public static function register_cron_schedule($schedules)
	{
		if (!isset($schedules[self::CRON_SCHEDULE])) {
			$schedules[self::CRON_SCHEDULE] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => 'Every 15 minutes (Politeia Reading)',
			);
		}
		return $schedules;
	}

	public static function schedule_autostop_cron()
	{
		if (!wp_next_scheduled(self::CRON_HOOK)) {
			wp_schedule_event(time() + 5 * MINUTE_IN_SECONDS, self::CRON_SCHEDULE, self::CRON_HOOK);
		}
	}

	public static function cron_autostop()
	{
		$sessions = get_option(self::ACTIVE_SESSIONS_OPTION, array());
		if (!is_array($sessions) || empty($sessions)) {
			return;
		}

		foreach ($sessions as $sid) {
			$sid = absint($sid);
			if ($sid <= 0) {
				continue;
			}

			// Cron runs without a logged-in user context; auto_stop_session will validate ownership via row data.
			self::auto_stop_session($sid, 0, 'cron');
		}
	}

	private static function get_active_sessions(): array
	{
		$sessions = get_option(self::ACTIVE_SESSIONS_OPTION, array());
		if (!is_array($sessions)) {
			return array();
		}
		return array_values(array_unique(array_map('absint', $sessions)));
	}

	private static function save_active_sessions(array $sessions): void
	{
		$sessions = array_values(array_unique(array_filter(array_map('absint', $sessions))));
		update_option(self::ACTIVE_SESSIONS_OPTION, $sessions, false);
	}

	private static function register_active_session(int $session_id, int $user_id, int $book_id, int $user_book_id, string $started_at_gmt): void
	{
		$list = self::get_active_sessions();
		$list[] = $session_id;
		self::save_active_sessions($list);

		$meta = array(
			'session_id' => $session_id,
			'user_id' => $user_id,
			'book_id' => $book_id,
			'user_book_id' => $user_book_id,
			'started_at_gmt' => $started_at_gmt,
		);
		set_transient(self::ACTIVE_SESSION_TRANSIENT_PREFIX . $session_id, $meta, 3 * HOUR_IN_SECONDS);
	}

	private static function deregister_active_session(int $session_id): void
	{
		$list = self::get_active_sessions();
		$list = array_values(array_filter($list, static fn($id) => (int) $id !== (int) $session_id));
		self::save_active_sessions($list);
		delete_transient(self::ACTIVE_SESSION_TRANSIENT_PREFIX . $session_id);
	}

	private static function get_active_session_meta(int $session_id)
	{
		$meta = get_transient(self::ACTIVE_SESSION_TRANSIENT_PREFIX . $session_id);
		return is_array($meta) ? $meta : null;
	}

	private static function is_active_session(int $session_id): bool
	{
		$list = self::get_active_sessions();
		return in_array((int) $session_id, array_map('intval', $list), true);
	}

	private static function cleanup_active_sessions(): void
	{
		$list = self::get_active_sessions();
		if (empty($list)) {
			return;
		}

		global $wpdb;
		$t = $wpdb->prefix . 'politeia_reading_sessions';

		$kept = array();
		foreach ($list as $sid) {
			$sid = (int) $sid;
			if ($sid <= 0) {
				continue;
			}
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id,deleted_at FROM {$t} WHERE id=%d LIMIT 1",
					$sid
				)
			);
			if (!$row || !empty($row->deleted_at)) {
				delete_transient(self::ACTIVE_SESSION_TRANSIENT_PREFIX . $sid);
				continue;
			}
			$kept[] = $sid;
		}
		self::save_active_sessions($kept);
	}

	private static function find_active_session_id(int $user_id, int $book_id): int
	{
		$list = self::get_active_sessions();
		if (empty($list)) {
			return 0;
		}

		foreach ($list as $sid) {
			$meta = self::get_active_session_meta((int) $sid);
			if (!$meta) {
				continue;
			}
			if ((int) ($meta['user_id'] ?? 0) === $user_id && (int) ($meta['book_id'] ?? 0) === $book_id) {
				return (int) $sid;
			}
		}
		return 0;
	}

	private static function compute_forced_end_time(string $start_gmt): array
	{
		$start_dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $start_gmt, new \DateTimeZone('UTC'));
		if (!$start_dt) {
			return array('forced' => false, 'end_time' => $start_gmt);
		}

		$now_ts = (int) current_time('timestamp', true);
		$start_ts = $start_dt->getTimestamp();
		$elapsed = max(0, $now_ts - $start_ts);
		if ($elapsed < self::AUTO_STOP_SECONDS) {
			return array('forced' => false, 'end_time' => gmdate('Y-m-d H:i:s', $now_ts));
		}

		return array(
			'forced' => true,
			'end_time' => gmdate('Y-m-d H:i:s', $start_ts + self::AUTO_STOP_SECONDS),
		);
	}

	/**
	 * Force-stop an active recorder session and persist it as automatic_stop.
	 *
	 * @param int    $session_id
	 * @param int    $request_user_id 0 for cron.
	 * @param string $trigger ajax|cron|save_clamp
	 * @return array<string,mixed>
	 */
	private static function auto_stop_session(int $session_id, int $request_user_id, string $trigger): array
	{
		global $wpdb;
		$t = $wpdb->prefix . 'politeia_reading_sessions';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id,user_id,start_time,insert_type,deleted_at FROM {$t} WHERE id=%d LIMIT 1",
				$session_id
			),
			ARRAY_A
		);

		if (!$row || !empty($row['deleted_at'])) {
			self::deregister_active_session($session_id);
			return array(
				'session_id' => $session_id,
				'stopped' => 0,
				'reason' => 'missing',
			);
		}

		$owner_id = (int) ($row['user_id'] ?? 0);
		if ($request_user_id > 0 && $owner_id !== $request_user_id) {
			return array('error' => 'forbidden', 'code' => 403);
		}

		// Only auto-stop if it's still considered active in our registry.
		if (!self::is_active_session($session_id)) {
			return array(
				'session_id' => $session_id,
				'stopped' => 0,
				'reason' => 'not_active',
			);
		}

		$start_time = isset($row['start_time']) ? (string) $row['start_time'] : '';
		if (!$start_time) {
			self::deregister_active_session($session_id);
			return array('error' => 'invalid_session', 'code' => 400);
		}

		$forced = self::compute_forced_end_time($start_time);
		$end_time = $forced['end_time'];

		if (!$forced['forced']) {
			return array(
				'session_id' => $session_id,
				'stopped' => 0,
				'reason' => 'below_limit',
			);
		}

		$insert_type = isset($row['insert_type']) ? (string) $row['insert_type'] : 'recorder';
		if ($insert_type !== 'recorder' && $insert_type !== 'automatic_stop') {
			self::deregister_active_session($session_id);
			return array(
				'session_id' => $session_id,
				'stopped' => 0,
				'reason' => 'not_recorder',
			);
		}

		$update = array(
			'end_time' => $end_time,
		);
		$formats = array('%s');
		if (self::table_has_columns('politeia_reading_sessions', array('insert_type'))) {
			$update['insert_type'] = 'automatic_stop';
			$formats[] = '%s';
		}

		$wpdb->update(
			$t,
			$update,
			array('id' => $session_id),
			$formats,
			array('%d')
		);

		self::deregister_active_session($session_id);

		error_log(sprintf('[PRS_SR] auto_stop session_id=%d user_id=%d trigger=%s', $session_id, $owner_id, $trigger));
		do_action(
			'politeia_reading_session_auto_stopped',
			array(
				'session_id' => $session_id,
				'user_id' => $owner_id,
				'trigger' => $trigger,
				'end_time' => $end_time,
			)
		);

		return array(
			'session_id' => $session_id,
			'stopped' => 1,
			'end_time' => $end_time,
			'insert_type' => 'automatic_stop',
		);
	}

	/**
	 * Mark matching planned sessions as accomplished for the reading session date.
	 *
	 * @param int    $user_id
	 * @param int    $book_id
	 * @param string $start_gmt
	 * @return void
	 */
	private static function mark_planned_session_accomplished($user_id, $book_id, $start_gmt)
	{
		if (empty($start_gmt)) {
			return;
		}

		$dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $start_gmt, new \DateTimeZone('UTC'));
		if (!$dt) {
			return;
		}

		$day_key = $dt->setTimezone(wp_timezone())->format('Y-m-d');

		global $wpdb;
		$plans_table = $wpdb->prefix . 'politeia_plans';
		$goals_table = $wpdb->prefix . 'politeia_plan_goals';
		$finish_book_table = $wpdb->prefix . 'politeia_plan_finish_book';
		$user_books_table = $wpdb->prefix . 'politeia_user_books';
		$sessions_table = $wpdb->prefix . 'politeia_planned_sessions';

		// Find plans via legacy goals OR new finish_book table (via user_books link)
		$plan_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.id
				FROM {$plans_table} p
				LEFT JOIN {$goals_table} g ON g.plan_id = p.id
				LEFT JOIN {$finish_book_table} pfb ON pfb.plan_id = p.id
				LEFT JOIN {$user_books_table} ub ON ub.id = pfb.user_book_id
				WHERE p.user_id = %d 
				  AND (g.book_id = %d OR ub.book_id = %d)",
				$user_id,
				$book_id,
				$book_id
			)
		);

		if (empty($plan_ids)) {
			return;
		}

		$placeholders = implode(',', array_fill(0, count($plan_ids), '%d'));
		$params = array_merge($plan_ids, array($day_key));

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$sessions_table}
				SET status = 'accomplished'
				WHERE plan_id IN ({$placeholders})
				AND DATE(planned_start_datetime) = %s
				AND status = 'planned'",
				...$params
			)
		);

		// Invalidate cache for affected plans
		if (class_exists('\Politeia\ReadingPlanner\PlanSessionDeriver')) {
			foreach ($plan_ids as $pid) {
				\Politeia\ReadingPlanner\PlanSessionDeriver::invalidate_plan_cache((int) $pid);
			}
		}
	}

	/*
	=========================
	 * Cobertura: unión de intervalos
	 * ========================= */
	public static function coverage_stats($user_id, $book_id, $total_pages)
	{
		$total_pages = (int) $total_pages;
		if ($total_pages <= 0) {
			return array(
				'covered' => 0,
				'total' => 0,
				'full' => false,
			);
		}
		$intervals = self::fetch_intervals($user_id, $book_id);

		// normalizar y clamp
		$norm = array();
		foreach ($intervals as $iv) {
			$a = max(1, (int) $iv['s']);
			$b = min($total_pages, (int) $iv['e']);
			if ($b < $a) {
				continue;
			}
			$norm[] = array($a, $b);
		}
		if (!$norm) {
			return array(
				'covered' => 0,
				'total' => $total_pages,
				'full' => false,
			);
		}

		// unir
		usort(
			$norm,
			function ($x, $y) {
				return $x[0] <=> $y[0];
			}
		);
		$merged = array();
		$cur = $norm[0];
		for ($i = 1; $i < count($norm); $i++) {
			$iv = $norm[$i];
			if ($iv[0] <= $cur[1] + 1) {
				// solapa o adyacente → unir
				$cur[1] = max($cur[1], $iv[1]);
			} else {
				$merged[] = $cur;
				$cur = $iv;
			}
		}
		$merged[] = $cur;

		// suma de longitudes (inclusivo)
		$covered = 0;
		foreach ($merged as $m) {
			$covered += ($m[1] - $m[0] + 1);
		}
		$covered = max(0, min($covered, $total_pages));

		return array(
			'covered' => $covered,
			'total' => $total_pages,
			'full' => ($covered >= $total_pages),
		);
	}

	private static function fetch_intervals($user_id, $book_id)
	{
		global $wpdb;
		$t = $wpdb->prefix . 'politeia_reading_sessions';
		$ub = $wpdb->prefix . 'politeia_user_books';

		// Resolve user_book_id (could pass in, but for safety lookup)
		$user_book_id = $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$ub} WHERE user_id=%d AND book_id=%d LIMIT 1",
			$user_id,
			$book_id
		));

		if (!$user_book_id)
			return array();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT start_page, end_page FROM {$t}
             WHERE user_id=%d AND user_book_id=%d AND end_time IS NOT NULL AND deleted_at IS NULL",
				$user_id,
				$user_book_id
			),
			ARRAY_A
		);
		$out = array();
		if ($rows) {
			foreach ($rows as $r) {
				$s = (int) $r['start_page'];
				$e = (int) $r['end_page'];
				if ($e < $s) {
					continue;
				}
				$out[] = array(
					's' => $s,
					'e' => $e,
				);
			}
		}
		return $out;
	}

	/**
	 * Calculates a rounded progress percentage (0-100) for the given user/book.
	 *
	 * @param int $user_id
	 * @param int $book_id
	 * @param int $total_pages
	 * @return int
	 */
	public static function calculate_progress_percent($user_id, $book_id, $total_pages)
	{
		$total_pages = (int) $total_pages;
		if ($total_pages <= 0) {
			return 0;
		}

		$coverage = self::coverage_stats($user_id, $book_id, $total_pages);

		$covered = isset($coverage['covered']) ? (int) $coverage['covered'] : 0;
		$total = isset($coverage['total']) ? (int) $coverage['total'] : $total_pages;

		if ($total <= 0) {
			return 0;
		}

		$percent = ($covered / $total) * 100;
		$percent = round($percent);

		if ($percent < 0) {
			return 0;
		}

		if ($percent > 100) {
			return 100;
		}

		return (int) $percent;
	}

	/*
	=========================
	 * Helpers de dominio
	 * ========================= */
	private static function get_user_book_row($user_id, $book_id)
	{
		global $wpdb;
		$t = $wpdb->prefix . 'politeia_user_books';
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$t} WHERE user_id=%d AND book_id=%d AND deleted_at IS NULL LIMIT 1",
				$user_id,
				$book_id
			)
		);
	}

	private static function blocked_by_status($status)
	{
		return in_array((string) $status, array('borrowed', 'lost', 'sold'), true);
	}

	private static function update_user_book_fields($user_book_id, $data)
	{
		global $wpdb;
		$t = $wpdb->prefix . 'politeia_user_books';

		// Si las columnas extra no existen, no las mandamos
		if (isset($data['finish_mode']) || isset($data['finished_at'])) {
			if (!self::table_has_columns('politeia_user_books', array('finish_mode', 'finished_at'))) {
				unset($data['finish_mode'], $data['finished_at']);
			}
		}
		$data['updated_at'] = current_time('mysql');

		$wpdb->update($t, $data, array('id' => (int) $user_book_id));
	}

	private static function table_has_columns($basename, $cols)
	{
		global $wpdb;
		$t = $wpdb->prefix . $basename;
		foreach ((array) $cols as $c) {
			$found = $wpdb->get_var(
				$wpdb->prepare(
					"SHOW COLUMNS FROM {$t} LIKE %s",
					$c
				)
			);
			if (!$found) {
				return false;
			}
		}
		return true;
	}

	/*
	=========================
	 * Paginated sessions
	 * ========================= */

	/**
	 * Devuelve sesiones paginadas para (user, book).
	 *
	 * @return array { rows:[], total:int, max_pages:int, paged:int, per_page:int }
	 */
	public static function get_sessions_page($user_id, $book_id, $per_page = 15, $paged = 1, $orderby = 'start_time', $order = 'desc', $only_finished = true)
	{
		global $wpdb;
		$t = $wpdb->prefix . 'politeia_reading_sessions';

		$user_id = (int) $user_id;
		$book_id = (int) $book_id;
		$per_page = max(1, (int) $per_page);
		$paged = max(1, (int) $paged);
		$offset = ($paged - 1) * $per_page;

		$ub = $wpdb->prefix . 'politeia_user_books';
		$user_book_id = $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$ub} WHERE user_id=%d AND book_id=%d LIMIT 1",
			$user_id,
			$book_id
		));

		if (!$user_book_id) {
			return array(
				'rows' => array(),
				'total' => 0,
				'max_pages' => 0,
				'paged' => $paged,
				'per_page' => $per_page,
			);
		}

		$where = 'WHERE user_id=%d AND user_book_id=%d AND deleted_at IS NULL';
		$args = array($user_id, $user_book_id);

		if ($only_finished) {
			$where .= ' AND end_time IS NOT NULL';
		}

		// Total para paginación
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$t} {$where}",
				...$args
			)
		);

		if ($total === 0) {
			return array(
				'rows' => array(),
				'total' => 0,
				'max_pages' => 0,
				'paged' => $paged,
				'per_page' => $per_page,
			);
		}

		$max_pages = (int) ceil($total / $per_page);
		if ($paged > $max_pages) {
			$paged = $max_pages;
			$offset = ($paged - 1) * $per_page;
		}

		// --- NEW: Dynamic and safe ORDER BY clause ---
		$order_clause = '';
		$order_dir = strtolower($order) === 'asc' ? 'ASC' : 'DESC'; // Sanitize direction

		switch ($orderby) {
			case 'duration':
				$order_clause = "ORDER BY (UNIX_TIMESTAMP(end_time) - UNIX_TIMESTAMP(start_time)) {$order_dir}, id DESC";
				break;
			case 'pages':
				$order_clause = "ORDER BY (end_page - start_page + 1) {$order_dir}, id DESC";
				break;
			case 'start_time':
			default:
				$order_clause = "ORDER BY start_time {$order_dir}, id DESC";
				break;
		}

		// Traer la página actual
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, start_time, end_time, start_page, end_page, chapter_name
             FROM {$t}
             {$where}
             {$order_clause}
             LIMIT %d OFFSET %d",
				...array_merge($args, array($per_page, $offset))
			)
		);

		return array(
			'rows' => $rows ?: array(),
			'total' => $total,
			'max_pages' => $max_pages,
			'paged' => $paged,
			'per_page' => $per_page,
		);
	}

	/*
	=========================
	 * AJAX: render parcial de sesiones
	 * ========================= */
	public static function ajax_render_sessions()
	{
		if (!is_user_logged_in()) {
			self::err('auth', 401);
		}
		if (!self::verify_nonce('prs_sessions_nonce', array('nonce'))) {
			self::err('bad_nonce', 403);
		}

		$user_id = get_current_user_id();
		$book_id = isset($_POST['book_id']) ? absint($_POST['book_id']) : 0;
		$paged = isset($_POST['paged']) ? max(1, absint($_POST['paged'])) : 1;
		$per_page = (int) apply_filters('politeia_reading_sessions_per_page', 15);

		// --- NEW: Read sorting parameters ---
		$orderby = isset($_POST['orderby']) ? sanitize_key($_POST['orderby']) : 'start_time';
		$order = isset($_POST['order']) ? sanitize_key($_POST['order']) : 'desc';

		if (!$book_id) {
			self::err('invalid_book', 400);
		}

		// Pass sorting parameters to the get_sessions_page function
		$data = self::get_sessions_page($user_id, $book_id, $per_page, $paged, $orderby, $order, true);

		// --- NEW: Helper function to generate header attributes ---
		$sort_attrs = function ($key) use ($orderby, $order) {
			$class = 'prs-sortable';
			if ($key === $orderby) {
				$class .= ' ' . ($order === 'asc' ? 'asc' : 'desc');
			}
			return 'class="' . esc_attr($class) . '" data-sort="' . esc_attr($key) . '"';
		};

		// --- UPDATED: HTML table with sortable headers ---
		$html = '<table class="prs-table"><thead><tr>';
		$html .= '<th ' . $sort_attrs('start_time') . '>' . esc_html__('Start', 'politeia-reading') . '</th>';
		$html .= '<th>' . esc_html__('End', 'politeia-reading') . '</th>';
		$html .= '<th ' . $sort_attrs('duration') . '>' . esc_html__('Duration', 'politeia-reading') . '</th>';
		$html .= '<th>' . esc_html__('Start Pg', 'politeia-reading') . '</th>';
		$html .= '<th>' . esc_html__('End Pg', 'politeia-reading') . '</th>';
		$html .= '<th ' . $sort_attrs('pages') . '>' . esc_html__('Pages', 'politeia-reading') . '</th>';
		$html .= '<th>' . esc_html__('Chapter', 'politeia-reading') . '</th>';
		$html .= '</tr></thead><tbody>';

		$total_seconds = 0;
		$total_pages_read = 0;

		foreach ((array) $data['rows'] as $s) {
			$start_local = $s->start_time ? get_date_from_gmt($s->start_time, 'Y-m-d H:i') : '—';
			$end_local = $s->end_time ? get_date_from_gmt($s->end_time, 'Y-m-d H:i') : '—';

			$sec = 0;
			if ($s->start_time && $s->end_time) {
				$sec = max(0, strtotime($s->end_time . ' +0 seconds') - strtotime($s->start_time . ' +0 seconds'));
			}
			$p_start = (int) $s->start_page;
			$p_end = (int) $s->end_page;
			$pages_read = ($p_end >= $p_start) ? ($p_end - $p_start + 1) : 0;

			$total_seconds += $sec;
			$total_pages_read += $pages_read;

			$html .= '<tr>';
			$html .= '<td>' . esc_html($start_local) . '</td>';
			$html .= '<td>' . esc_html($end_local) . '</td>';
			$html .= '<td>' . esc_html(self::hms($sec)) . '</td>';
			$html .= '<td>' . (int) $p_start . '</td>';
			$html .= '<td>' . (int) $p_end . '</td>';
			$html .= '<td>' . (int) $pages_read . '</td>';
			$html .= '<td>' . ($s->chapter_name ? esc_html($s->chapter_name) : '—') . '</td>';
			$html .= '</tr>';
		}

		if (empty($data['rows'])) {
			$html .= '<tr><td colspan="7">' . esc_html__('No sessions yet.', 'politeia-reading') . '</td></tr>';
		}

		$html .= '</tbody><tfoot><tr>';
		$html .= '<th colspan="2" style="text-align:right">' . esc_html__('Totals (this page):', 'politeia-reading') . '</th>';
		$html .= '<th>' . esc_html(self::hms($total_seconds)) . '</th>';
		$html .= '<th></th><th></th>';
		$html .= '<th>' . (int) $total_pages_read . '</th>';
		$html .= '<th></th>';
		$html .= '</tr></tfoot></table>';

		// Paginación con enlaces AJAX (data-page)
		if ((int) $data['max_pages'] > 1) {
			$html .= '<nav class="prs-pagination" aria-label="' . esc_attr__('Sessions pagination', 'politeia-reading') . '"><ul class="page-numbers">';
			for ($i = 1; $i <= (int) $data['max_pages']; $i++) {
				if ($i === (int) $data['paged']) {
					$html .= '<li><span class="page-numbers current">' . $i . '</span></li>';
				} else {
					$html .= '<li><a href="#" class="page-numbers prs-sess-link" data-page="' . $i . '">' . $i . '</a></li>';
				}
			}
			$html .= '</ul></nav>';
		}

		self::ok(
			array(
				'html' => $html,
				'paged' => (int) $data['paged'],
				'max_pages' => (int) $data['max_pages'],
			)
		);
	}

	/** Helper: HH:MM:SS */
	private static function hms($sec)
	{
		$sec = max(0, (int) $sec);
		$h = floor($sec / 3600);
		$m = floor(($sec % 3600) / 60);
		$s = $sec % 60;
		return sprintf('%02d:%02d:%02d', $h, $m, $s);
	}

	/*
	=========================
	 * Utils
	 * ========================= */
	private static function verify_nonce($action, $keys = array('_ajax_nonce', 'security', 'nonce'))
	{
		foreach ((array) $keys as $k) {
			if (isset($_REQUEST[$k])) {
				return (bool) wp_verify_nonce($_REQUEST[$k], $action);
			}
		}
		return false;
	}

	private static function err($message, $code = 400)
	{
		wp_send_json_error(array('message' => $message), $code);
	}

	private static function ok($data)
	{
		wp_send_json_success($data);
	}
}

Politeia_Reading_Sessions::init();
