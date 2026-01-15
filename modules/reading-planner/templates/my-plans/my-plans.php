<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$requested_user = (string) get_query_var( 'prs_my_plans_user' );
$current_user   = wp_get_current_user();
$is_owner       = $requested_user
	&& $current_user
	&& $current_user->exists()
	&& $current_user->user_login === $requested_user;
?>

<style>
	@import url('https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200');

	:root {
		--prs-black: #000000;
		--prs-deep-gray: #333333;
		--prs-gold: #c79f32;
		--prs-orange: #ff8c00;
		--prs-light-gray: #a8a8a8;
		--prs-subtle-gray: #f5f5f5;
		--prs-off-white: #fefeff;
		--prs-radius: 6px;
	}

	.prs-plans-wrap {
		background: none;
		padding: 24px;
	}

	.prs-plan-grid {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 24px;
	}

	@media (max-width: 960px) {
		.prs-plan-grid {
			grid-template-columns: 1fr;
		}
	}

	.prs-plan-card {
		background: #ffffff;
		border: 1px solid #f1f1f1;
		border-radius: var(--prs-radius);
		box-shadow: 0 24px 40px rgba(0, 0, 0, 0.12);
		overflow: hidden;
	}

	.prs-plan-header {
		padding: 32px 32px 16px;
	}

	.prs-plan-badge {
		display: inline-block;
		padding: 0px;
		background: none;
		color: var(--prs-gold);
		font-size: 10px;
		letter-spacing: 0.2em;
		text-transform: uppercase;
		border-radius: var(--prs-radius);
		font-weight: 700;
	}

	.prs-plan-title {
		margin: 12px 0 0;
		font-size: 28px;
		font-weight: 700;
		color: var(--prs-black);
		line-height: 1.2;
	}

	.prs-plan-title-row {
		display: flex;
		align-items: center;
		gap: 12px;
	}

	.prs-plan-subtitle {
		display: block;
		color: #a0a0a0;
		font-size: 16px;
		font-weight: 500;
		margin-top: -30px;
	}

	.material-symbols-outlined {
		font-family: 'Material Symbols Outlined';
		font-weight: normal;
		font-style: normal;
		line-height: 1;
		text-transform: none;
		display: inline-block;
		white-space: nowrap;
		word-wrap: normal;
		direction: ltr;
		font-feature-settings: 'liga';
		-webkit-font-feature-settings: 'liga';
		-webkit-font-smoothing: antialiased;
	}

	.prs-session-recorder-trigger {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		cursor: pointer;
		color: #111827;
		font-size: 35px;
		line-height: 1;
		font-variation-settings: "FILL" 1, "wght" 600, "opsz" 24;
	}

	.prs-session-modal {
		display: none;
		position: fixed;
		inset: 0;
		background: rgba(0, 0, 0, 0.6);
		z-index: 9999;
		align-items: center;
		justify-content: center;
		padding: 24px;
	}

	.prs-session-modal.is-active {
		display: flex;
	}

	.prs-session-modal__content {
		position: relative;
		max-width: 600px;
		width: 100%;
		max-height: 90vh;
		overflow-y: auto;
		background: #ffffff;
		padding: 24px;
		border: 1px solid #dddddd;
		border-radius: 12px;
	}

	.prs-session-modal__close {
		position: absolute;
		top: 12px;
		right: 12px;
		border: none;
		background: none;
		color: #000000;
		cursor: pointer;
		font-size: 20px;
		line-height: 1;
		padding: 4px;
		outline: none;
		box-shadow: none;
	}

	.prs-session-modal__close:hover,
	.prs-session-modal__close:focus,
	.prs-session-modal__close:focus-visible {
		background: none;
		box-shadow: none;
		color: #000000;
		outline: none;
	}

	.prs-plan-toggle {
		margin: 0 24px 15px;
		width: calc(100% - 48px);
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 20px;
		background: var(--prs-subtle-gray);
		border: 1px solid #e2e2e2;
		border-radius: var(--prs-radius);
		cursor: pointer;
		transition: background 0.2s ease;
		appearance: none;
		-webkit-appearance: none;
		outline: none;
	}

	.prs-plan-toggle:hover,
	.prs-plan-toggle:focus,
	.prs-plan-toggle:focus-visible,
	.prs-plan-toggle:active {
		background: var(--prs-subtle-gray);
		border-color: #e2e2e2;
		box-shadow: none;
		outline: none;
	}

	.prs-plan-toggle-icon {
		width: 48px;
		height: 48px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		background: var(--prs-black);
		color: var(--prs-gold);
		border-radius: var(--prs-radius);
		box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
	}

	.prs-plan-toggle-label {
		text-transform: uppercase;
		letter-spacing: 0.2em;
		font-size: 10px;
		font-weight: 700;
		color: var(--prs-black);
	}

	.prs-plan-toggle-date {
		font-size: 11px;
		font-weight: 700;
		color: var(--prs-gold);
	}

	.prs-chevron {
		width: 24px;
		height: 24px;
		color: #9b9b9b;
		transition: transform 0.3s ease;
	}

	.prs-chevron.is-open {
		transform: rotate(180deg);
	}

	.prs-collapsible {
		max-height: 0;
		opacity: 0;
		overflow: hidden;
		transition: max-height 0.4s ease, opacity 0.3s ease;
		margin: 0 24px;
	}

	.prs-collapsible.is-open {
		max-height: 2000px;
		opacity: 1;
	}

	.prs-calendar-card {
		margin-top: 0px;
		padding: 24px;
		background: var(--prs-subtle-gray);
		border: 1px solid var(--prs-light-gray);
		border-radius: var(--prs-radius);
	}

	.prs-calendar-header {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		margin-bottom: 0px;
	}

	.prs-calendar-title {
		font-size: 18px;
		text-transform: uppercase;
		font-weight: 700;
		color: var(--prs-black);
		margin-bottom: 0px;
	}

	.prs-calendar-title-row {
		display: flex;
		align-items: center;
		gap: 12px;
	}

	.prs-calendar-nav {
		display: flex;
		gap: 8px;
		align-items: center;
	}

	.prs-calendar-nav-btn {
		width: 20px;
		height: 20px;
		border-radius: 50%;
		border: 1px solid #d6d6d6;
		background: #ffffff;
		color: var(--prs-black);
		display: inline-flex;
		align-items: center;
		justify-content: center;
		cursor: pointer;
		text-decoration: none;
	}

	.prs-calendar-nav-btn.is-disabled {
		opacity: 0.4;
		cursor: default;
		pointer-events: none;
	}

	.prs-calendar-meta {
		font-size: 11px;
		font-weight: 700;
		letter-spacing: 0.15em;
		text-transform: uppercase;
		color: var(--prs-gold);
		margin-bottom: 14px;
	}

	.prs-calendar-subtitle {
		font-size: 9px;
		text-transform: uppercase;
		letter-spacing: 0.2em;
		color: #8f8f8f;
		font-weight: 700;
		margin-top: 4px;
	}

	.prs-toggle-group {
		display: flex;
		align-items: center;
		background: #e6e6e6;
		padding: 4px;
		border-radius: 12px;
	}

	.prs-toggle-button {
		height: 30px;
		width: 38px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		border-radius: 10px;
		cursor: pointer;
		color: #111111;
		transition: background 0.2s ease, color 0.2s ease;
		border: none;
		background: transparent;
		box-shadow: none;
		text-decoration: none;
	}

	.prs-toggle-button.is-active {
		background: var(--prs-black);
		color: var(--prs-gold);
	}

	.prs-view {
		transition: opacity 0.3s ease;
	}

	.prs-view.is-hidden {
		display: none;
		opacity: 0;
	}

	.prs-weekdays {
		display: grid;
		grid-template-columns: repeat(7, minmax(0, 1fr));
		gap: 4px;
		font-size: 9px;
		font-weight: 700;
		text-transform: uppercase;
		opacity: 0.5;
		margin-bottom: 8px;
		text-align: center;
	}

	.prs-calendar-grid {
		display: grid;
		grid-template-columns: repeat(7, minmax(0, 1fr));
		gap: 6px;
	}

	.prs-day-cell {
		position: relative;
		height: 48px;
		display: flex;
		align-items: center;
		justify-content: center;
		background: var(--prs-off-white);
		border: 1px solid rgba(168, 168, 168, 0.3);
		border-radius: var(--prs-radius);
		transition: all 0.2s ease;
	}

	.prs-day-number {
		position: absolute;
		top: 4px;
		left: 6px;
		font-size: 8px;
		font-weight: 700;
		opacity: 0.3;
	}

	.prs-day-empty {
		background: #e1e1e1;
		opacity: 0.2;
	}

	.prs-day-selected {
		width: 26px;
		height: 26px;
		position: relative;
		display: flex;
		align-items: center;
		justify-content: center;
		background: var(--prs-gold);
		color: #111111;
		font-weight: 700;
		border-radius: 50%;
		box-shadow: 0 4px 6px rgba(0, 0, 0, 0.12);
		cursor: grab;
		user-select: none;
	}

	.prs-day-selected.is-missed {
		background: #cfcfcf;
		color: #666666;
		box-shadow: none;
		cursor: default;
	}

	.prs-day-selected.is-accomplished {
		background: #000000;
		color: var(--prs-gold);
		box-shadow: none;
		cursor: default;
	}

	.prs-day-remove {
		position: absolute;
		top: 0px;
		left: 28px;
		width: 6px;
		height: 6px;
		border-radius: 50%;
		border: none;
		background: #ff4d4f;
		color: #ffffff;
		font-size: 10px;
		line-height: 1;
		display: flex;
		align-items: center;
		justify-content: center;
		padding: 6px;
		cursor: pointer;
		box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
		opacity: 0;
		pointer-events: none;
		transition: opacity 0.2s ease;
	}

	.prs-day-selected:hover .prs-day-remove {
		opacity: 1;
		pointer-events: auto;
	}

	.prs-day-selected.is-remove-visible .prs-day-remove {
		opacity: 1;
		pointer-events: auto;
	}

	.prs-day-add {
		position: absolute;
		width: 18px;
		height: 18px;
		border-radius: 50%;
		border: none;
		background: none;
		color: var(--prs-black);
		font-size: 14px;
		line-height: 1;
		display: flex;
		align-items: center;
		justify-content: center;
		cursor: pointer;
		box-shadow: none;
		padding: 0;
		outline: none;
	}

	.prs-day-add:hover,
	.prs-day-add:focus,
	.prs-day-add:active {
		background: none;
		box-shadow: none;
		outline: none;
	}

	.prs-day-selected:active {
		cursor: grabbing;
	}

	.prs-day-cell.is-drag-over {
		background: #fffaf0;
		border: 2px dashed var(--prs-orange);
	}

	.prs-list-item {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 12px 16px;
		background: #ffffff;
		border: 1px solid #e2e2e2;
		border-radius: var(--prs-radius);
		box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
		transition: border 0.2s ease;
		margin-bottom: 10px;
	}

	.prs-list-item:hover {
		border-color: var(--prs-gold);
	}

	.prs-list-badge {
		width: 28px;
		height: 28px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		background: var(--prs-gold);
		color: #ffffff;
		font-size: 10px;
		font-weight: 700;
		border-radius: var(--prs-radius);
	}

	.prs-list-badge.is-missed {
		background: #cfcfcf;
		color: #666666;
	}

	.prs-list-badge.is-accomplished {
		background: #000000;
		color: var(--prs-gold);
	}

	.prs-list-badge.is-planned {
		background: var(--prs-gold);
		color: #ffffff;
	}

	.prs-list-title {
		font-size: 11px;
		font-weight: 700;
		text-transform: uppercase;
		color: var(--prs-black);
		margin-left: 12px;
	}

	.prs-list-date {
		font-size: 10px;
		font-weight: 700;
		color: #8f8f8f;
		text-transform: uppercase;
	}

	.prs-confirm-button {
		margin-top: 20px;
		width: 100%;
		padding: 16px;
		background: var(--prs-black);
		color: var(--prs-gold);
		border: none;
		border-radius: var(--prs-radius);
		font-weight: 700;
		font-size: 11px;
		letter-spacing: 0.2em;
		text-transform: uppercase;
		cursor: pointer;
		transition: transform 0.15s ease, background 0.2s ease;
	}

	.prs-confirm-button:active {
		transform: scale(0.98);
	}

	.prs-progress {
		background: #ffffff;
		padding: 24px 32px 32px;
	}

	.prs-progress-row {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 8px;
	}

	.prs-progress-label {
		font-size: 10px;
		font-weight: 700;
		letter-spacing: 0.2em;
		text-transform: uppercase;
		color: #a0a0a0;
	}

	.prs-progress-value {
		font-size: 14px;
		font-weight: 700;
		color: var(--prs-black);
	}

	.prs-progress-bar {
		height: 6px;
		background: #e5e5e5;
		border-radius: var(--prs-radius);
		overflow: hidden;
	}

	.prs-progress-bar-fill {
		height: 100%;
		background: var(--prs-gold);
		border-radius: var(--prs-radius);
		box-shadow: 0 0 10px rgba(199, 159, 50, 0.5);
	}
</style>

<div class="wrap prs-plans-wrap">
	<?php if ( $is_owner ) : ?>
		<h1><?php esc_html_e( 'My Plans', 'politeia-reading' ); ?></h1>
		<?php
		global $wpdb;
		$cards = array();
		$user_id = (int) $current_user->ID;
		$plans_table = $wpdb->prefix . 'politeia_plans';
		$goals_table = $wpdb->prefix . 'politeia_plan_goals';
		$sessions_table = $wpdb->prefix . 'politeia_planned_sessions';
		$reading_sessions_table = $wpdb->prefix . 'politeia_reading_sessions';
			$authors_table = $wpdb->prefix . 'politeia_authors';
			$book_authors_table = $wpdb->prefix . 'politeia_book_authors';

		$plan = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$plans_table} WHERE user_id = %d ORDER BY created_at DESC, id DESC LIMIT 1",
				$user_id
			),
			ARRAY_A
		);

		if ( $plan ) {
			$plan_id = (int) $plan['id'];
			$goal = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$goals_table} WHERE plan_id = %d ORDER BY id ASC LIMIT 1",
					$plan_id
				),
				ARRAY_A
			);
			$goal_kind = $goal && ! empty( $goal['goal_kind'] ) ? (string) $goal['goal_kind'] : '';
			$goal_book_id = $goal && ! empty( $goal['book_id'] ) ? (int) $goal['book_id'] : 0;
			$goal_target = $goal && ! empty( $goal['target_value'] ) ? (int) $goal['target_value'] : 0;
			$total_pages = $goal_target;
			if ( $goal_book_id ) {
				$ub_pages = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT pages FROM {$wpdb->prefix}politeia_user_books WHERE user_id = %d AND book_id = %d AND deleted_at IS NULL LIMIT 1",
						$user_id,
						$goal_book_id
					)
				);
				if ( $ub_pages ) {
					$total_pages = (int) $ub_pages;
				}
			}
			$today_key = current_time( 'Y-m-d' );

			if ( 'complete_books' === $goal_kind ) {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$sessions_table}
						SET status = 'missed'
						WHERE plan_id = %d
						AND status = 'planned'
						AND DATE(planned_start_datetime) < %s",
						$plan_id,
						$today_key
					)
				);
			}

			$sessions = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT planned_start_datetime, planned_start_page, planned_end_page, status
					FROM {$sessions_table}
					WHERE plan_id = %d
					ORDER BY planned_start_datetime ASC",
					$plan_id
				),
				ARRAY_A
			);

			$start_ts = null;
			$end_ts = null;
			if ( $sessions ) {
				$first_session = reset( $sessions );
				$last_session = end( $sessions );
				$start_ts = $first_session && ! empty( $first_session['planned_start_datetime'] ) ? strtotime( $first_session['planned_start_datetime'] ) : null;
				$end_ts = $last_session && ! empty( $last_session['planned_start_datetime'] ) ? strtotime( $last_session['planned_start_datetime'] ) : $start_ts;
			}
			if ( ! $start_ts && ! empty( $plan['created_at'] ) ) {
				$start_ts = strtotime( $plan['created_at'] );
				$end_ts = $start_ts;
			}
			if ( ! $start_ts ) {
				$start_ts = time();
				$end_ts = $start_ts;
			}

			$month_ts = $start_ts;
			$month_label = date_i18n( 'F', $month_ts );
			$month_year = date_i18n( 'F Y', $month_ts );
			$month_range_label = $month_year;
			if ( $start_ts && $end_ts ) {
				$start_month = date_i18n( 'F', $start_ts );
				$start_year = date_i18n( 'Y', $start_ts );
				$end_month = date_i18n( 'F', $end_ts );
				$end_year = date_i18n( 'Y', $end_ts );
				if ( $start_year === $end_year ) {
					if ( $start_month === $end_month ) {
						$month_range_label = sprintf( '%1$s %2$s', $start_month, $start_year );
					} else {
						$month_range_label = sprintf( '%1$s - %2$s %3$s', $start_month, $end_month, $start_year );
					}
				} else {
					$month_range_label = sprintf( '%1$s %2$s - %3$s %4$s', $start_month, $start_year, $end_month, $end_year );
				}
			}
			$days_count = (int) date( 't', $month_ts );
			$month_start_ts = strtotime( date( 'Y-m-01', $month_ts ) );
			$start_offset = (int) date( 'w', $month_start_ts );

			$actual_ranges = array();
			$actual_sessions_payload = array();
			if ( $goal_book_id ) {
				$actual_sessions = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT start_time, start_page, end_page
						FROM {$reading_sessions_table}
						WHERE user_id = %d AND book_id = %d AND deleted_at IS NULL",
						$user_id,
						$goal_book_id
					),
					ARRAY_A
				);
				if ( $actual_sessions ) {
					foreach ( $actual_sessions as $actual_session ) {
						if ( empty( $actual_session['start_time'] ) ) {
							continue;
						}
						$start_page = isset( $actual_session['start_page'] ) ? (int) $actual_session['start_page'] : 0;
						$end_page = isset( $actual_session['end_page'] ) ? (int) $actual_session['end_page'] : 0;
						if ( $start_page <= 0 || $end_page <= 0 || $end_page < $start_page ) {
							continue;
						}
						$date_key = date( 'Y-m-d', strtotime( $actual_session['start_time'] ) );
						$actual_sessions_payload[] = array(
							'date' => $date_key,
							'start' => $start_page,
							'end' => $end_page,
							'start_time' => (string) $actual_session['start_time'],
						);
						if ( empty( $actual_ranges[ $date_key ] ) ) {
							$actual_ranges[ $date_key ] = array(
								'start' => $start_page,
								'end' => $end_page,
							);
							continue;
						}
						$actual_ranges[ $date_key ]['start'] = min( $actual_ranges[ $date_key ]['start'], $start_page );
						$actual_ranges[ $date_key ]['end'] = max( $actual_ranges[ $date_key ]['end'], $end_page );
					}
				}
			}

			$selected = array();
			$session_dates = array();
			$session_items = array();
			if ( $sessions ) {
				$month_key = date( 'Y-m', $month_ts );
				foreach ( $sessions as $session ) {
					if ( empty( $session['planned_start_datetime'] ) ) {
						continue;
					}
					$session_ts = strtotime( $session['planned_start_datetime'] );
					if ( $session_ts && date( 'Y-m', $session_ts ) === $month_key ) {
						$selected[] = (int) date( 'j', $session_ts );
					}
					$date_key = date( 'Y-m-d', strtotime( $session['planned_start_datetime'] ) );
					$session_dates[] = $date_key;
					$actual_range = isset( $actual_ranges[ $date_key ] ) ? $actual_ranges[ $date_key ] : array();
					$session_items[] = array(
						'date' => $date_key,
						'status' => ! empty( $session['status'] ) ? (string) $session['status'] : 'planned',
						'planned_start_page' => isset( $session['planned_start_page'] ) ? (int) $session['planned_start_page'] : null,
						'planned_end_page' => isset( $session['planned_end_page'] ) ? (int) $session['planned_end_page'] : null,
						'actual_start_page' => isset( $actual_range['start'] ) ? (int) $actual_range['start'] : null,
						'actual_end_page' => isset( $actual_range['end'] ) ? (int) $actual_range['end'] : null,
					);
				}
			}
			$selected = array_values( array_unique( $selected ) );
			sort( $selected );
			$session_dates = array_values( array_unique( $session_dates ) );

			$badge = 'ccl' === (string) $plan['plan_type']
				? __( 'Plan: More Pages', 'politeia-reading' )
				: __( 'Reading Plan', 'politeia-reading' );
			$progress = 0;

			if ( 'complete_books' === $goal_kind ) {
				$badge = sprintf(
					/* translators: 1: goal label, 2: page count, 3: pages label. */
					__( 'Goal: %1$s %2$s %3$s', 'politeia-reading' ),
					__( 'Finish Book', 'politeia-reading' ),
					(int) $total_pages,
					__( 'pages', 'politeia-reading' )
				);
			}
			$subtitle = '';
			if ( $goal_book_id ) {
				$subtitle = (string) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT GROUP_CONCAT(a.display_name ORDER BY ba.sort_order ASC SEPARATOR ', ')
						FROM {$book_authors_table} ba
						LEFT JOIN {$authors_table} a ON a.id = ba.author_id
						WHERE ba.book_id = %d",
						$goal_book_id
					)
				);
			}
			if ( $goal_book_id && $total_pages > 0 ) {
				$total_read = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT SUM(
							CASE
								WHEN end_page IS NULL OR start_page IS NULL THEN 0
								WHEN end_page >= start_page THEN end_page - start_page
								ELSE 0
							END
						)
						FROM {$reading_sessions_table}
						WHERE user_id = %d AND book_id = %d AND deleted_at IS NULL",
						$user_id,
						$goal_book_id
					)
				);
				$progress = min( 100, (int) floor( ( $total_read / $total_pages ) * 100 ) );
			}

			$cards[] = array(
				'plan_id'      => $plan_id,
				'book_id'      => $goal_book_id,
				'badge'        => $badge,
				'title'        => $plan['name'],
				'subtitle'     => $subtitle,
				'month_label'  => $month_label,
				'month_year'   => $month_year,
				'month_range'  => $month_range_label,
				'initial_month' => date( 'Y-m', $month_ts ),
				'days_count'   => $days_count,
				'start_offset' => $start_offset,
				'selected'     => $selected,
				'session_dates' => $session_dates,
				'total_pages'  => $total_pages,
				'progress'     => $progress,
				'goal_kind'    => $goal_kind,
				'session_items' => $session_items,
				'actual_sessions' => $actual_sessions_payload,
				'today_key'    => $today_key,
			);
		}
		?>
		<?php if ( $cards ) : ?>
			<div class="prs-plan-grid">
				<?php foreach ( $cards as $card ) : ?>
					<div
						class="prs-plan-card"
						data-days-count="<?php echo esc_attr( (string) $card['days_count'] ); ?>"
						data-start-offset="<?php echo esc_attr( (string) $card['start_offset'] ); ?>"
						data-selected-days="<?php echo esc_attr( wp_json_encode( $card['selected'] ) ); ?>"
						data-session-label="<?php echo esc_attr__( 'Scheduled Session', 'politeia-reading' ); ?>"
						data-day-format="<?php echo esc_attr__( 'Day %1$s of %2$s', 'politeia-reading' ); ?>"
						data-month-label="<?php echo esc_attr( $card['month_label'] ); ?>"
						data-remove-label="<?php echo esc_attr__( 'Remove session', 'politeia-reading' ); ?>"
						data-total-pages="<?php echo esc_attr( (string) $card['total_pages'] ); ?>"
						data-sessions-label="<?php echo esc_attr__( 'sessions', 'politeia-reading' ); ?>"
						data-pages-label="<?php echo esc_attr__( 'pages', 'politeia-reading' ); ?>"
						data-missed-label="<?php echo esc_attr__( 'Missed', 'politeia-reading' ); ?>"
						data-completed-label="<?php echo esc_attr__( 'Completed', 'politeia-reading' ); ?>"
						data-session-dates="<?php echo esc_attr( wp_json_encode( $card['session_dates'] ) ); ?>"
						data-session-items="<?php echo esc_attr( wp_json_encode( $card['session_items'] ) ); ?>"
						data-actual-sessions="<?php echo esc_attr( wp_json_encode( $card['actual_sessions'] ) ); ?>"
						data-initial-month="<?php echo esc_attr( $card['initial_month'] ); ?>"
						data-goal-kind="<?php echo esc_attr( $card['goal_kind'] ); ?>"
						data-today-key="<?php echo esc_attr( $card['today_key'] ); ?>"
						data-confirm-text="<?php echo esc_attr__( 'Accept Proposal', 'politeia-reading' ); ?>"
						data-confirmed-text="<?php echo esc_attr__( 'Plan saved!', 'politeia-reading' ); ?>"
					>
					<div class="prs-plan-header">
						<span class="prs-plan-badge"><?php echo esc_html( $card['badge'] ); ?></span>
							<h2 class="prs-plan-title">
								<span class="prs-plan-title-row">
									<span><?php echo esc_html( $card['title'] ); ?></span>
									<?php if ( ! empty( $card['book_id'] ) ) : ?>
										<span
											role="button"
											tabindex="0"
											class="prs-session-recorder-trigger material-symbols-outlined"
											data-role="session-open"
											aria-label="<?php esc_attr_e( 'Open session recorder', 'politeia-reading' ); ?>"
											aria-controls="prs-session-modal-<?php echo esc_attr( (string) $card['plan_id'] ); ?>"
											aria-expanded="false"
										>play_circle</span>
									<?php endif; ?>
								</span>
								<br>
								<span class="prs-plan-subtitle"><?php echo esc_html( $card['subtitle'] ); ?></span>
							</h2>
						</div>

					<button type="button" class="prs-plan-toggle" data-role="toggle">
						<div class="prs-plan-toggle-icon" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
							</svg>
						</div>
						<div>
							<span class="prs-plan-toggle-label"><?php esc_html_e( 'See Session Calendar', 'politeia-reading' ); ?></span><br>
							<span class="prs-plan-toggle-date"><?php echo esc_html( $card['month_range'] ); ?></span>
						</div>
						<svg class="prs-chevron" data-role="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7" />
						</svg>
					</button>

					<div class="prs-collapsible" data-role="collapsible">
						<div class="prs-calendar-card">
							<div class="prs-calendar-header">
								<div>
									<div class="prs-calendar-title-row">
										<h3 class="prs-calendar-title" data-role="calendar-title"><?php echo esc_html( $card['month_year'] ); ?></h3>
										<div class="prs-calendar-nav">
											<a href="#" class="prs-calendar-nav-btn" role="button" data-role="month-prev" aria-label="<?php esc_attr_e( 'Previous Month', 'politeia-reading' ); ?>">
												<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
													<path d="M15 6l-6 6 6 6" />
												</svg>
											</a>
											<a href="#" class="prs-calendar-nav-btn" role="button" data-role="month-next" aria-label="<?php esc_attr_e( 'Next Month', 'politeia-reading' ); ?>">
												<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
													<path d="M9 6l6 6-6 6" />
												</svg>
											</a>
										</div>
									</div>
									<div class="prs-calendar-meta" data-role="calendar-meta"></div>
								</div>
								<div>
									<div class="prs-toggle-group" role="tablist">
										<a href="#" class="prs-toggle-button is-active" role="button" data-role="view-cal" aria-label="<?php esc_attr_e( 'Calendar', 'politeia-reading' ); ?>">
											<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
												<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
												<line x1="16" y1="2" x2="16" y2="6"></line>
												<line x1="8" y1="2" x2="8" y2="6"></line>
												<line x1="3" y1="10" x2="21" y2="10"></line>
											</svg>
										</a>
										<a href="#" class="prs-toggle-button" role="button" data-role="view-list" aria-label="<?php esc_attr_e( 'List', 'politeia-reading' ); ?>">
											<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
												<line x1="8" y1="6" x2="21" y2="6"></line>
												<line x1="8" y1="12" x2="21" y2="12"></line>
												<line x1="8" y1="18" x2="21" y2="18"></line>
												<line x1="3" y1="6" x2="3.01" y2="6"></line>
												<line x1="3" y1="12" x2="3.01" y2="12"></line>
												<line x1="3" y1="18" x2="3.01" y2="18"></line>
											</svg>
										</a>
									</div>
								</div>
							</div>

							<div class="prs-view" data-role="calendar-view">
								<div class="prs-weekdays">
									<div><?php esc_html_e( 'Sun', 'politeia-reading' ); ?></div>
									<div><?php esc_html_e( 'Mon', 'politeia-reading' ); ?></div>
									<div><?php esc_html_e( 'Tue', 'politeia-reading' ); ?></div>
									<div><?php esc_html_e( 'Wed', 'politeia-reading' ); ?></div>
									<div><?php esc_html_e( 'Thu', 'politeia-reading' ); ?></div>
									<div><?php esc_html_e( 'Fri', 'politeia-reading' ); ?></div>
									<div><?php esc_html_e( 'Sat', 'politeia-reading' ); ?></div>
								</div>
								<div class="prs-calendar-grid" data-role="calendar-grid"></div>
							</div>

							<div class="prs-view is-hidden" data-role="list-view">
								<div data-role="list"></div>
							</div>

						</div>
					</div>

					<div class="prs-progress">
						<div class="prs-progress-row">
							<span class="prs-progress-label"><?php esc_html_e( 'Reading Completed', 'politeia-reading' ); ?></span>
							<span class="prs-progress-value"><?php echo esc_html( $card['progress'] ); ?>%</span>
						</div>
						<div class="prs-progress-bar" role="progressbar" aria-valuenow="<?php echo esc_attr( (string) $card['progress'] ); ?>" aria-valuemin="0" aria-valuemax="100">
							<div class="prs-progress-bar-fill" style="width: <?php echo esc_attr( (string) $card['progress'] ); ?>%;"></div>
						</div>
					</div>
					<?php if ( ! empty( $card['book_id'] ) ) : ?>
						<div
							id="prs-session-modal-<?php echo esc_attr( (string) $card['plan_id'] ); ?>"
							class="prs-session-modal"
							role="dialog"
							aria-modal="true"
							aria-hidden="true"
							aria-label="<?php esc_attr_e( 'Session recorder', 'politeia-reading' ); ?>"
							data-role="session-modal"
						>
							<div class="prs-session-modal__content">
								<button
									type="button"
									class="prs-session-modal__close"
									aria-label="<?php esc_attr_e( 'Close session recorder', 'politeia-reading' ); ?>"
									data-role="session-close"
								>
									×
								</button>
								<?php echo do_shortcode( '[politeia_start_reading book_id="' . (int) $card['book_id'] . '"]' ); ?>
							</div>
						</div>
					<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'No plans yet.', 'politeia-reading' ); ?></p>
		<?php endif; ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Access denied.', 'politeia-reading' ); ?></p>
	<?php endif; ?>
</div>

<script>
	(function() {
		const cards = document.querySelectorAll('.prs-plan-card');
		if (!cards.length) {
			return;
		}

		cards.forEach((card) => {
			const toggleBtn = card.querySelector('[data-role="toggle"]');
			const collapsible = card.querySelector('[data-role="collapsible"]');
			const chevron = card.querySelector('[data-role="chevron"]');
			const grid = card.querySelector('[data-role="calendar-grid"]');
			const listContainer = card.querySelector('[data-role="list"]');
			const btnViewCal = card.querySelector('[data-role="view-cal"]');
			const btnViewList = card.querySelector('[data-role="view-list"]');
			const viewCal = card.querySelector('[data-role="calendar-view"]');
			const viewList = card.querySelector('[data-role="list-view"]');
			const confirmBtn = card.querySelector('[data-role="confirm"]');
			const metaLabel = card.querySelector('[data-role="calendar-meta"]');
			const titleLabel = card.querySelector('[data-role="calendar-title"]');
			const btnPrevMonth = card.querySelector('[data-role="month-prev"]');
			const btnNextMonth = card.querySelector('[data-role="month-next"]');
			const sessionOpen = card.querySelector('[data-role="session-open"]');
			const sessionModal = card.querySelector('[data-role="session-modal"]');
			const sessionClose = card.querySelector('[data-role="session-close"]');

			const totalPages = parseInt(card.dataset.totalPages, 10) || 0;
			const goalKind = card.dataset.goalKind || '';
			const todayKey = card.dataset.todayKey || '';
			let sessionDates = [];
			let sessionItems = [];
			let actualSessions = [];
			let currentView = 'calendar';
			let currentMonthKey = card.dataset.initialMonth || '';
			let minMonthKey = '';
			let maxMonthKey = '';

			try {
				sessionItems = JSON.parse(card.dataset.sessionItems || '[]');
			} catch (error) {
				sessionItems = [];
			}

			try {
				actualSessions = JSON.parse(card.dataset.actualSessions || '[]');
			} catch (error) {
				actualSessions = [];
			}

			try {
				sessionDates = JSON.parse(card.dataset.sessionDates || '[]');
			} catch (error) {
				sessionDates = [];
			}
			if (!sessionDates.length && sessionItems.length) {
				sessionDates = sessionItems.map((item) => item.date).filter(Boolean);
			}
			sessionDates = Array.from(new Set(sessionDates));

			const pad2 = (value) => String(value).padStart(2, '0');
			const monthKey = (date) => `${date.getFullYear()}-${pad2(date.getMonth() + 1)}`;
			const parseMonthKey = (key) => {
				const parts = String(key).split('-');
				const year = parseInt(parts[0], 10);
				const month = parseInt(parts[1], 10) - 1;
				return new Date(year, month, 1);
			};
			const compareMonthKey = (a, b) => {
				if (a === b) return 0;
				return a < b ? -1 : 1;
			};
			const locale = document.documentElement.lang || 'es-ES';
			const formatMonthYear = (date) => new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(date);
			const formatMonthName = (date) => new Intl.DateTimeFormat(locale, { month: 'long' }).format(date);
			const isCompleteBooks = goalKind === 'complete_books';

			if (!currentMonthKey) {
				if (sessionDates.length) {
					currentMonthKey = sessionDates.slice().sort()[0].slice(0, 7);
				} else {
					currentMonthKey = monthKey(new Date());
				}
			}

			if (sessionDates.length) {
				const monthKeys = sessionDates.map((d) => d.slice(0, 7)).sort();
				minMonthKey = monthKeys[0];
				maxMonthKey = monthKeys[monthKeys.length - 1];
			} else {
				minMonthKey = currentMonthKey;
				maxMonthKey = currentMonthKey;
			}

			const strings = {
				sessionLabel: card.dataset.sessionLabel || '',
				dayFormat: card.dataset.dayFormat || '',
				monthLabel: card.dataset.monthLabel || '',
				removeLabel: card.dataset.removeLabel || '',
				sessionsLabel: card.dataset.sessionsLabel || 'sessions',
				pagesLabel: card.dataset.pagesLabel || 'pages',
				missedLabel: card.dataset.missedLabel || 'Missed',
				completedLabel: card.dataset.completedLabel || 'Completed',
				confirmText: card.dataset.confirmText || '',
				confirmedText: card.dataset.confirmedText || '',
			};

			const getStatusByDate = (dateStr) => {
				const item = sessionItems.find((entry) => entry.date === dateStr);
				return item?.status || 'planned';
			};

			const getPlannedRangeByDate = (dateStr) => {
				const item = sessionItems.find((entry) => entry.date === dateStr);
				if (!item) {
					return null;
				}
				const start = typeof item.planned_start_page === 'number' ? item.planned_start_page : null;
				const end = typeof item.planned_end_page === 'number' ? item.planned_end_page : null;
				if (!start || !end) {
					return null;
				}
				return { start, end };
			};

			const getMonthSessions = (monthKeyValue) => {
				return sessionDates
					.filter((dateStr) => dateStr.startsWith(monthKeyValue))
					.sort();
			};

			const plannedPagesForItem = (item) => {
				if (!item || typeof item.planned_start_page !== 'number' || typeof item.planned_end_page !== 'number') {
					return 0;
				}
				if (item.planned_end_page < item.planned_start_page) {
					return 0;
				}
				return item.planned_end_page - item.planned_start_page + 1;
			};

			const buildDerivedPlan = () => {
				const actualPages = actualSessions.reduce((sum, entry) => {
					const start = typeof entry.start === 'number' ? entry.start : 0;
					const end = typeof entry.end === 'number' ? entry.end : 0;
					if (!start || !end || end < start) {
						return sum;
					}
					return sum + (end - start + 1);
				}, 0);
				const allDates = sessionDates.slice().sort();
				const orderAll = new Map();
				allDates.forEach((dateStr, index) => {
					orderAll.set(dateStr, index + 1);
				});
				const nonMissedDates = sessionDates
					.filter((dateStr) => getStatusByDate(dateStr) !== 'missed')
					.sort();
				const orderByDate = new Map();
				nonMissedDates.forEach((dateStr, index) => {
					orderByDate.set(dateStr, index + 1);
				});

				if (!isCompleteBooks) {
					return {
						derivedByDate: new Map(),
						remainingItems: [],
						remainingPages: totalPages,
						remainingCount: sessionDates.length,
						orderAll,
						orderByDate,
						actualPages,
					};
				}

				const completedPages = Math.min(totalPages, actualPages);

				const remainingDates = sessionDates
					.filter((dateStr) => {
						const status = sessionItems.find((item) => item.date === dateStr)?.status || 'planned';
						if (status !== 'planned') {
							return false;
						}
						if (todayKey && dateStr < todayKey) {
							return false;
						}
						return true;
					})
					.sort();

				const remainingPages = Math.max(0, totalPages - completedPages);
				const remainingCount = remainingDates.length;
				const basePages = remainingCount > 0 ? Math.floor(remainingPages / remainingCount) : 0;
				const extraPages = remainingCount > 0 ? remainingPages % remainingCount : 0;
				const derivedByDate = new Map();

				let cursor = 1;
				remainingDates.forEach((dateStr, index) => {
					const pages = basePages + (index < extraPages ? 1 : 0);
					const startPage = pages > 0 ? cursor : 0;
					const endPage = pages > 0 ? cursor + pages - 1 : 0;
					if (pages > 0) {
						cursor = endPage + 1;
					}
					derivedByDate.set(dateStr, {
						start: startPage,
						end: endPage,
						order: index + 1,
					});
				});

				return {
					derivedByDate,
					remainingItems: remainingDates,
					remainingPages,
					remainingCount,
					orderAll,
					orderByDate,
					actualPages,
				};
			};

			const updateMeta = () => {
				if (!metaLabel) return;
				const derived = buildDerivedPlan();
				const monthSessions = getMonthSessions(currentMonthKey);
				const sessionCount = isCompleteBooks
					? monthSessions.filter((dateStr) => derived.remainingItems.includes(dateStr)).length
					: monthSessions.length;
				const pagesPerSession = isCompleteBooks
					? (derived.remainingCount > 0 ? Math.ceil(derived.remainingPages / derived.remainingCount) : 0)
					: (sessionCount > 0 && totalPages > 0 ? Math.ceil(totalPages / sessionCount) : 0);
				metaLabel.textContent = `${sessionCount} ${strings.sessionsLabel} | ${pagesPerSession} ${strings.pagesLabel}`;
			};

			const updateTitle = () => {
				if (!titleLabel) return;
				titleLabel.textContent = formatMonthYear(parseMonthKey(currentMonthKey));
			};

			const updateNavState = () => {
				if (btnPrevMonth) {
					btnPrevMonth.classList.toggle('is-disabled', compareMonthKey(currentMonthKey, minMonthKey) <= 0);
				}
				if (btnNextMonth) {
					btnNextMonth.classList.toggle('is-disabled', compareMonthKey(currentMonthKey, maxMonthKey) >= 0);
				}
			};

			const renderCalendar = () => {
				const viewDate = parseMonthKey(currentMonthKey);
				const daysCount = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0).getDate();
				const startOffset = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1).getDay();
				grid.innerHTML = '';
				const monthSessions = getMonthSessions(currentMonthKey);
				const derived = buildDerivedPlan();
				const sortedDays = monthSessions.map((dateStr) => parseInt(dateStr.split('-')[2], 10));
				updateMeta();
				updateTitle();
				updateNavState();

				for (let i = 0; i < startOffset; i++) {
					const empty = document.createElement('div');
					empty.className = 'prs-day-cell prs-day-empty';
					grid.appendChild(empty);
				}

				for (let day = 1; day <= daysCount; day++) {
					const cell = document.createElement('div');
					cell.className = 'prs-day-cell';
					cell.dataset.day = String(day);

					const label = document.createElement('span');
					label.className = 'prs-day-number';
					label.textContent = String(day);
					cell.appendChild(label);

					if (sortedDays.includes(day)) {
						const targetDate = `${currentMonthKey}-${pad2(day)}`;
						const status = getStatusByDate(targetDate);
						const isMissed = status === 'missed' && isCompleteBooks;
						const isAccomplished = status === 'accomplished';
						const isLocked = status !== 'planned';
						const order = isCompleteBooks
							? (derived.orderByDate.get(targetDate) || sortedDays.indexOf(day) + 1)
							: (sortedDays.indexOf(day) + 1);

						const mark = document.createElement('div');
						mark.className = `prs-day-selected${isMissed ? ' is-missed' : ''}${isAccomplished ? ' is-accomplished' : ''}`;
						if (isCompleteBooks) {
							mark.textContent = isMissed ? '' : String(order);
						} else {
							mark.textContent = String(order);
						}
						mark.dataset.day = String(day);
						let hideTimer = null;
						if (!isLocked) {
							mark.setAttribute('draggable', 'true');
							mark.addEventListener('mouseenter', () => {
								if (hideTimer) {
									clearTimeout(hideTimer);
									hideTimer = null;
								}
								mark.classList.add('is-remove-visible');
							});
							mark.addEventListener('mouseleave', () => {
								if (hideTimer) {
									clearTimeout(hideTimer);
								}
								hideTimer = setTimeout(() => {
									mark.classList.remove('is-remove-visible');
									hideTimer = null;
								}, 1000);
							});
							const removeBtn = document.createElement('button');
							removeBtn.type = 'button';
							removeBtn.className = 'prs-day-remove';
							removeBtn.setAttribute('aria-label', strings.removeLabel || 'Remove session');
							removeBtn.textContent = '×';
							removeBtn.addEventListener('click', (event) => {
								event.stopPropagation();
								sessionDates = sessionDates.filter((dateStr) => dateStr !== targetDate);
								renderCalendar();
								if (currentView === 'list') {
									renderList();
								}
							});
							mark.appendChild(removeBtn);

							mark.addEventListener('dragstart', (event) => {
								if (event.target && event.target.classList.contains('prs-day-remove')) {
									event.preventDefault();
									return;
								}
								event.dataTransfer.setData('text/plain', String(day));
								setTimeout(() => mark.classList.add('opacity-0'), 0);
							});

								mark.addEventListener('dragend', () => {
									mark.classList.remove('opacity-0');
									renderCalendar();
								});
						}

						cell.appendChild(mark);
					}

					if (!sortedDays.includes(day)) {
						let hoverTimer = null;
						cell.addEventListener('mouseenter', () => {
							hoverTimer = setTimeout(() => {
								if (cell.querySelector('.prs-day-add')) return;
								const addBtn = document.createElement('button');
								addBtn.type = 'button';
								addBtn.className = 'prs-day-add';
								addBtn.setAttribute('aria-label', strings.sessionLabel || 'Add session');
								addBtn.textContent = '+';
								addBtn.addEventListener('click', (event) => {
									event.stopPropagation();
									const newDate = `${currentMonthKey}-${pad2(day)}`;
									if (!sessionDates.includes(newDate)) {
										sessionDates.push(newDate);
									}
									renderCalendar();
									if (currentView === 'list') {
										renderList();
									}
								});
								cell.appendChild(addBtn);
							}, 300);
						});
						cell.addEventListener('mouseleave', () => {
							if (hoverTimer) {
								clearTimeout(hoverTimer);
								hoverTimer = null;
							}
							const addBtn = cell.querySelector('.prs-day-add');
							if (addBtn) addBtn.remove();
						});
					}

					cell.addEventListener('dragover', (event) => {
						event.preventDefault();
						if (!sortedDays.includes(day)) {
							cell.classList.add('is-drag-over');
						}
					});

					cell.addEventListener('dragleave', () => {
						cell.classList.remove('is-drag-over');
					});

					cell.addEventListener('drop', (event) => {
						event.preventDefault();
						cell.classList.remove('is-drag-over');
						const origin = parseInt(event.dataTransfer.getData('text/plain'), 10);
						const target = day;

						if (target && !sortedDays.includes(target)) {
							const originDate = `${currentMonthKey}-${pad2(origin)}`;
							const targetDate = `${currentMonthKey}-${pad2(target)}`;
							sessionDates = sessionDates.filter((dateStr) => dateStr !== originDate);
							if (!sessionDates.includes(targetDate)) {
								sessionDates.push(targetDate);
							}
							renderCalendar();
							if (currentView === 'list') {
								renderList();
							}
						}
					});

					grid.appendChild(cell);
				}
			};

			const renderList = () => {
				listContainer.innerHTML = '';
				const monthSessions = getMonthSessions(currentMonthKey);
				const derived = buildDerivedPlan();
				const listDates = monthSessions;
				const sorted = listDates.map((dateStr) => parseInt(dateStr.split('-')[2], 10));
				const sessionCount = sorted.length;
				const pagesPerSession = sessionCount > 0 && totalPages > 0
					? Math.ceil(totalPages / sessionCount)
					: 0;
				const sessionsByDate = new Map();
				actualSessions
					.slice()
					.sort((a, b) => {
						const at = String(a.start_time || '');
						const bt = String(b.start_time || '');
						if (at === bt) return 0;
						return at < bt ? -1 : 1;
					})
					.forEach((entry) => {
						if (!entry.date) return;
						if (!sessionsByDate.has(entry.date)) {
							sessionsByDate.set(entry.date, []);
						}
						sessionsByDate.get(entry.date).push(entry);
					});

				const entries = [];
				listDates.forEach((dateKey) => {
					const status = getStatusByDate(dateKey);
					const actualList = sessionsByDate.get(dateKey) || [];
					if (status === 'accomplished' && actualList.length) {
						actualList.forEach((entry) => {
							entries.push({
								type: 'accomplished',
								dateKey: entry.date,
								range: { start: entry.start, end: entry.end },
							});
						});
					} else if (status === 'accomplished') {
						entries.push({ type: 'accomplished', dateKey });
					} else if (status === 'missed') {
						entries.push({ type: 'missed', dateKey });
					} else {
						entries.push({ type: 'planned', dateKey });
					}
				});

				let listOrder = 0;
				entries.forEach((entry, index) => {
					const dateKey = entry.dateKey;
					const day = parseInt(dateKey.split('-')[2], 10);
					const status = entry.type === 'accomplished' ? 'accomplished' : (entry.type === 'missed' ? 'missed' : 'planned');
					const item = document.createElement('div');
					item.className = 'prs-list-item';

					const left = document.createElement('div');
					left.style.display = 'flex';
					left.style.alignItems = 'center';

					const badge = document.createElement('span');
					const badgeStatus = status === 'missed' ? 'is-missed' : (status === 'accomplished' ? 'is-accomplished' : 'is-planned');
					badge.className = `prs-list-badge ${badgeStatus}`;
					if (status === 'missed') {
						badge.textContent = '';
					} else {
						listOrder += 1;
						badge.textContent = String(listOrder);
					}
					left.appendChild(badge);

					const title = document.createElement('span');
					title.className = 'prs-list-title';
					if (status === 'planned') {
						let expectedPages = 0;
						if (isCompleteBooks && derived.derivedByDate.has(dateKey)) {
							const range = derived.derivedByDate.get(dateKey);
							if (range.start > 0 && range.end > 0) {
								expectedPages = range.end - range.start + 1;
							}
						} else if (pagesPerSession > 0) {
							expectedPages = pagesPerSession;
						}
						if (expectedPages > 0) {
							title.textContent = `${expectedPages} ${strings.pagesLabel}`;
						} else {
							title.textContent = strings.sessionLabel;
						}
					} else {
						const range = entry.range;
						if (status === 'accomplished' && range && range.start && range.end) {
							title.textContent = `${range.start}-${range.end}`;
						} else if (status === 'accomplished') {
							title.textContent = strings.sessionLabel;
						} else {
							title.textContent = `${strings.missedLabel} 🙁`;
						}
						if (status === 'accomplished') {
							title.textContent = `${title.textContent} · ${strings.completedLabel} 🙂`;
						}
					}
					left.appendChild(title);

					const date = document.createElement('span');
					date.className = 'prs-list-date';
					const entryMonthKey = dateKey.slice(0, 7);
					date.textContent = `${day} ${formatMonthName(parseMonthKey(entryMonthKey))}`;

					item.appendChild(left);
					item.appendChild(date);
					listContainer.appendChild(item);
				});
			};

			const setView = (view) => {
				currentView = view;
				btnViewCal.classList.toggle('is-active', view === 'calendar');
				btnViewList.classList.toggle('is-active', view === 'list');
				viewCal.classList.toggle('is-hidden', view !== 'calendar');
				viewList.classList.toggle('is-hidden', view !== 'list');
				if (view === 'calendar') {
					renderCalendar();
				} else {
					renderList();
				}
			};

			const setupSessionRecorderModal = () => {
				if (!sessionOpen || !sessionModal) return;

				const handleKeydown = (event) => {
					if (event.key === 'Escape') {
						event.preventDefault();
						closeModal();
					}
				};

				const openModal = (options = {}) => {
					const shouldFocusClose = options.focusClose !== false;
					if (!sessionModal.classList.contains('is-active')) {
						sessionModal.classList.add('is-active');
						document.addEventListener('keydown', handleKeydown);
					}
					sessionModal.setAttribute('aria-hidden', 'false');
					sessionOpen.setAttribute('aria-expanded', 'true');
					if (shouldFocusClose && sessionClose) {
						setTimeout(() => sessionClose.focus(), 0);
					}
				};

				const closeModal = () => {
					if (!sessionModal.classList.contains('is-active')) {
						return;
					}
					sessionModal.classList.remove('is-active');
					sessionOpen.setAttribute('aria-expanded', 'false');
					sessionModal.setAttribute('aria-hidden', 'true');
					document.removeEventListener('keydown', handleKeydown);
					setTimeout(() => sessionOpen.focus(), 0);
				};

				sessionOpen.addEventListener('click', (event) => {
					event.preventDefault();
					openModal();
				});

				sessionOpen.addEventListener('keydown', (event) => {
					if (event.key === 'Enter' || event.key === ' ') {
						event.preventDefault();
						openModal();
					}
				});

				if (sessionClose) {
					sessionClose.addEventListener('click', (event) => {
						event.preventDefault();
						closeModal();
					});
				}

				sessionModal.addEventListener('click', (event) => {
					if (event.target === sessionModal) {
						closeModal();
					}
				});

				document.addEventListener('prs-session-modal:open', (event) => {
					const detail = event?.detail || {};
					openModal({ focusClose: detail.focusClose !== false });
				});

				document.addEventListener('prs-session-modal:close', () => {
					closeModal();
				});
			};

			if (toggleBtn && collapsible && chevron) {
				toggleBtn.addEventListener('click', () => {
					collapsible.classList.toggle('is-open');
					chevron.classList.toggle('is-open');
				});
			}

			if (btnPrevMonth) {
				btnPrevMonth.addEventListener('click', (event) => {
					event.preventDefault();
					if (compareMonthKey(currentMonthKey, minMonthKey) <= 0) return;
					const date = parseMonthKey(currentMonthKey);
					date.setMonth(date.getMonth() - 1);
					currentMonthKey = monthKey(date);
					setView(currentView);
				});
			}

			if (btnNextMonth) {
				btnNextMonth.addEventListener('click', (event) => {
					event.preventDefault();
					if (compareMonthKey(currentMonthKey, maxMonthKey) >= 0) return;
					const date = parseMonthKey(currentMonthKey);
					date.setMonth(date.getMonth() + 1);
					currentMonthKey = monthKey(date);
					setView(currentView);
				});
			}

			if (btnViewCal && btnViewList) {
				btnViewCal.addEventListener('click', (event) => {
					event.preventDefault();
					setView('calendar');
				});

				btnViewList.addEventListener('click', (event) => {
					event.preventDefault();
					setView('list');
				});
			}

			if (confirmBtn) {
				const confirmText = strings.confirmText;
				const confirmedText = strings.confirmedText;

				confirmBtn.addEventListener('click', () => {
					confirmBtn.textContent = confirmedText;
					confirmBtn.style.background = '#16a34a';
					setTimeout(() => {
						confirmBtn.textContent = confirmText;
						confirmBtn.style.background = 'var(--prs-black)';
					}, 2000);
				});
			}

			setupSessionRecorderModal();
			renderCalendar();
		});
	})();
</script>

<?php
get_footer();
