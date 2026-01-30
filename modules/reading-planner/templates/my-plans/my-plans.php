<?php
if (!defined('ABSPATH')) {
	exit;
}

// AJAX handler for desisting a reading plan
add_action('wp_ajax_desist_reading_plan', 'prs_handle_desist_plan');
function prs_handle_desist_plan()
{
	// Verify nonce
	if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'desist_plan_nonce')) {
		wp_send_json_error('Invalid nonce');
		return;
	}

	// Get plan ID
	$plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;
	if (!$plan_id) {
		wp_send_json_error('Invalid plan ID');
		return;
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'politeia_plans';

	// Get the plan to verify ownership
	$plan = $wpdb->get_row($wpdb->prepare(
		"SELECT user_id, status FROM {$table_name} WHERE id = %d",
		$plan_id
	));

	if (!$plan) {
		wp_send_json_error('Plan not found');
		return;
	}

	// Verify user owns the plan
	$current_user_id = get_current_user_id();
	if ($plan->user_id != $current_user_id) {
		wp_send_json_error('You do not have permission to modify this plan');
		return;
	}

	// Update plan status to 'desisted'
	$updated = $wpdb->update(
		$table_name,
		array('status' => 'desisted'),
		array('id' => $plan_id),
		array('%s'),
		array('%d')
	);

	if ($updated === false) {
		wp_send_json_error('Failed to update plan status');
		return;
	}

	wp_send_json_success('Plan desisted successfully');
}

get_header();
$enqueue_my_book_assets = function () {
	if (function_exists('wp_enqueue_style') && function_exists('wp_enqueue_script')) {
		wp_enqueue_style('politeia-my-book');
		wp_enqueue_script('politeia-my-book');
	}
};
$enqueue_my_book_assets();
$requested_user = (string) get_query_var('prs_my_plans_user');
$current_user = wp_get_current_user();
$is_owner = $requested_user
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
		align-items: start;
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
		padding: 16px 32px 16px;
		display: flex;
		gap: 16px;
	}

	#plan_book_info {
		flex: 0 0 75%;
		width: 75%;
	}

	#politeia_plan_result {
		flex: 0 0 25%;
		width: 25%;
		display: flex;
		flex-direction: column;
		justify-content: flex-start;
		align-items: flex-end;
	}

	#politeia_plan_result img {
		width: 70px;
		height: auto;
	}

	#politeia_plan_result img.plan-in-progress,
	#politeia_plan_result img.plan-desisted {
		filter: grayscale(100%);
	}

	/* Today's calendar cell - golden gradient border */
	.prs-day-cell.is-today {
		border: 2px solid;
		border-image: linear-gradient(135deg, #8A6B1E, #C79F32, #E9D18A) 1;
	}

	/* Weekday label above today - golden gradient text */
	.prs-weekdays div.is-today-weekday {
		background: linear-gradient(135deg, #8A6B1E, #C79F32, #E9D18A);
		-webkit-background-clip: text;
		background-clip: text;
		-webkit-text-fill-color: transparent;
		opacity: 1;
	}

	/* Plan menu button and dropdown */
	.plan-menu-container {
		position: relative;
		margin-top: 8px;
		text-align: center;
	}

	.plan-menu-button {
		background: none;
		border: none;
		padding: 4px 8px;
		cursor: pointer;
		font-size: 20px;
		color: #999;
		line-height: 1;
	}

	.plan-menu-button:hover,
	.plan-menu-button:active,
	.plan-menu-button:focus {
		background: none;
		color: #999;
		outline: none;
	}

	.plan-menu-dots {
		display: inline-block;
		letter-spacing: 2px;
	}

	.plan-menu-dropdown {
		position: absolute;
		top: 100%;
		right: 0;
		background: white;
		border: 1px solid #ddd;
		border-radius: 4px;
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
		min-width: 120px;
		z-index: 1000;
		margin-top: 4px;
	}

	.plan-menu-item {
		display: block;
		width: 100%;
		padding: 10px 16px;
		background: none;
		border: none;
		text-align: left;
		cursor: pointer;
		font-size: 14px;
		color: #333;
		transition: background-color 0.2s;
	}

	.plan-menu-item:hover {
		background-color: #f5f5f5;
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

	.prs-plan-title a {
		color: inherit;
		text-decoration: none;
	}

	.prs-plan-title a:hover,
	.prs-plan-title a:focus {
		color: inherit;
		text-decoration: none;
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
		color: #C79F32;
		font-size: 35px;
		line-height: 1;
		font-variation-settings: "FILL" 1, "wght" 600, "opsz" 24;
	}

	.prs-session-recorder-trigger:hover,
	.prs-session-recorder-trigger:focus {
		color: #E9D18A;
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
		filter: drop-shadow(10px 10px 50px rgba(0, 0, 0, 0.5));
		max-width: 600px;
		width: 100%;
		max-height: 90vh;
		overflow-y: auto;
		background: transparent;
		padding: 0;
		border: none;
		border-radius: 0;
	}

	.prs-session-modal__close {
		position: absolute;
		top: 16px;
		right: 16px;
		border: none;
		background: none;
		color: #ffffff;
		cursor: pointer;
		font-size: 22px;
		line-height: 1;
		padding: 4px;
		outline: none;
		box-shadow: none;
		z-index: 2;
	}

	.prs-session-modal__close:hover,
	.prs-session-modal__close:focus,
	.prs-session-modal__close:focus-visible {
		background: none;
		box-shadow: none;
		color: #ffffff;
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

	#politeia-open-reading-plan {
		display: none;
	}

	.prs-upcoming-wrap {
		width: 100%;
		align-self: start;
	}

	.prs-upcoming-card {
		background: #000000;
		border-radius: 6px;
		height: auto;
		width: 100%;
		max-width: none;
		padding: 24px;
		color: #ffffff;
		display: flex;
		flex-direction: column;
		box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
	}

	.prs-upcoming-title {
		font-family: 'Poppins', sans-serif;
		font-size: 30px;
		font-weight: 700;
		margin: 0;
		color: #ffffff;
		text-align: left;
		border-bottom: 1px solid #333333;
		padding-bottom: 12px;
		letter-spacing: -0.02em;
	}

	.prs-upcoming-list {
		margin-top: 12px;
	}


	.prs-upcoming-item {
		padding: 12px 0;
		border-bottom: 1px solid #1f1f1f;
	}

	.prs-upcoming-item:last-child {
		border-bottom: 0;
	}

	.prs-upcoming-row {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
	}

	.prs-upcoming-info {
		display: flex;
		flex-direction: column;
		gap: 4px;
		min-width: 0;
	}

	.prs-upcoming-book {
		font-size: 1rem;
		font-weight: 500;
		margin: 0;
		color: #d1d5db;
		text-decoration: none;
	}

	.prs-upcoming-book:hover,
	.prs-upcoming-book:focus {
		color: #ffffff;
		text-decoration: none;
	}

	.prs-upcoming-meta {
		font-size: 0.85rem;
		color: #9ca3af;
	}

	.prs-upcoming-date {
		font-size: 0.85rem;
		color: #9ca3af;
		white-space: nowrap;
	}

	.prs-upcoming-date--today {
		color: #C79F32;
		font-weight: 700;
		letter-spacing: 0.06em;
		text-transform: uppercase;
	}

	.prs-upcoming-empty {
		font-size: 0.9rem;
		color: #9ca3af;
		margin-top: 16px;
	}

	.prs-upcoming-item h4 {
		font-size: 1rem;
		font-weight: 400;
		margin: 0;
		color: #d1d5db;
	}

	.prs-upcoming-today {
		color: #10b981;
		font-weight: 600;
	}
</style>

<div class="wrap prs-plans-wrap">
	<?php echo do_shortcode('[politeia_reading_plan]'); ?>
	<?php if ($is_owner): ?>
		<?php
		global $wpdb;
		$cards = array();
		$user_id = (int) $current_user->ID;
		$timezone = wp_timezone();
		$plans_table = $wpdb->prefix . 'politeia_plans';
		$goals_table = $wpdb->prefix . 'politeia_plan_goals';
		$sessions_table = $wpdb->prefix . 'politeia_planned_sessions';
		$reading_sessions_table = $wpdb->prefix . 'politeia_reading_sessions';
		$books_table = $wpdb->prefix . 'politeia_books';
		$authors_table = $wpdb->prefix . 'politeia_authors';
		$book_authors_table = $wpdb->prefix . 'politeia_book_authors';

		$plans = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$plans_table} WHERE user_id = %d AND status = %s ORDER BY created_at DESC, id DESC",
				$user_id,
				'accepted'
			),
			ARRAY_A
		);

		if ($plans) {
			foreach ($plans as $plan) {
				$plan_id = (int) $plan['id'];
				$cache_key = 'prs_plan_view_' . $plan_id;
				$cached_card = get_transient($cache_key);
				if (false !== $cached_card) {
					$cards[] = $cached_card;
					continue;
				}
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
				$goal_kind = $goal && !empty($goal['goal_kind']) ? (string) $goal['goal_kind'] : '';
				$goal_book_id = $goal && !empty($goal['book_id']) ? (int) $goal['book_id'] : 0;
				$goal_target = $goal && !empty($goal['target_value']) ? (int) $goal['target_value'] : 0;
				$goal_starting_page = $goal && !empty($goal['starting_page']) ? (int) $goal['starting_page'] : 1;

				// Ensure starting_page is at least 1
				if ($goal_starting_page < 1) {
					$goal_starting_page = 1;
				}

				if (!$goal_book_id && !empty($plan['name'])) {
					$normalized = function_exists('prs_normalize_title')
						? prs_normalize_title((string) $plan['name'])
						: '';
					$book_row = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT id FROM {$books_table} WHERE title = %s OR normalized_title = %s LIMIT 1",
							(string) $plan['name'],
							(string) $normalized
						),
						ARRAY_A
					);
					if ($book_row && !empty($book_row['id'])) {
						$goal_book_id = (int) $book_row['id'];
					}
					if (!$goal_book_id) {
						$params = array(
							$user_id,
							(string) $plan['name'],
							(string) $normalized,
						);
						$sql = "SELECT b.id FROM {$wpdb->prefix}politeia_user_books ub
							INNER JOIN {$books_table} b ON b.id = ub.book_id
							WHERE ub.user_id = %d AND ub.deleted_at IS NULL
							AND (b.title = %s OR b.normalized_title = %s";
						if ('' !== $normalized) {
							$params[] = '%' . $wpdb->esc_like($normalized) . '%';
							$sql .= ' OR b.normalized_title LIKE %s';
						}
						$sql .= ') ORDER BY ub.id DESC LIMIT 1';
						$book_row = $wpdb->get_row(
							$wpdb->prepare($sql, $params),
							ARRAY_A
						);
						if ($book_row && !empty($book_row['id'])) {
							$goal_book_id = (int) $book_row['id'];
						}
					}
				}
				$total_pages = $goal_target;
				$today_key = current_time('Y-m-d');

				if ('complete_books' === $goal_kind) {
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
						"SELECT planned_start_datetime, status
						FROM {$sessions_table}
						WHERE plan_id = %d
						ORDER BY planned_start_datetime ASC",
						$plan_id
					),
					ARRAY_A
				);

				$start_ts = null;
				$end_ts = null;
				if ($sessions) {
					$first_session = reset($sessions);
					$last_session = end($sessions);
					$start_dt = $first_session && !empty($first_session['planned_start_datetime'])
						? date_create_immutable($first_session['planned_start_datetime'], $timezone)
						: null;
					$end_dt = $last_session && !empty($last_session['planned_start_datetime'])
						? date_create_immutable($last_session['planned_start_datetime'], $timezone)
						: null;
					$start_ts = $start_dt ? $start_dt->getTimestamp() : null;
					$end_ts = $end_dt ? $end_dt->getTimestamp() : $start_ts;
				}
				if (!$start_ts && !empty($plan['created_at'])) {
					$start_ts = strtotime($plan['created_at']);
					$end_ts = $start_ts;
				}
				if (!$start_ts) {
					$start_ts = time();
					$end_ts = $start_ts;
				}

				$month_ts = $start_ts;
				$month_label = date_i18n('F', $month_ts);
				$month_year = date_i18n('F Y', $month_ts);
				$month_range_label = $month_year;
				if ($start_ts && $end_ts) {
					$start_month = date_i18n('F', $start_ts);
					$start_year = date_i18n('Y', $start_ts);
					$end_month = date_i18n('F', $end_ts);
					$end_year = date_i18n('Y', $end_ts);
					if ($start_year === $end_year) {
						if ($start_month === $end_month) {
							$month_range_label = sprintf('%1$s %2$s', $start_month, $start_year);
						} else {
							$month_range_label = sprintf('%1$s - %2$s %3$s', $start_month, $end_month, $start_year);
						}
					} else {
						$month_range_label = sprintf('%1$s %2$s - %3$s %4$s', $start_month, $start_year, $end_month, $end_year);
					}
				}
				$days_count = (int) date('t', $month_ts);
				$month_start_ts = strtotime(wp_date('Y-m-01', $month_ts, $timezone));
				$start_offset = (int) wp_date('w', $month_start_ts, $timezone);

				$actual_sessions_payload = array();
				$actual_session_dates = array();
				if ($goal_book_id) {
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
					if ($actual_sessions) {
						foreach ($actual_sessions as $actual_session) {
							if (empty($actual_session['start_time'])) {
								continue;
							}
							$start_page = isset($actual_session['start_page']) ? (int) $actual_session['start_page'] : 0;
							$end_page = isset($actual_session['end_page']) ? (int) $actual_session['end_page'] : 0;
							if ($start_page <= 0 || $end_page <= 0 || $end_page < $start_page) {
								continue;
							}
							$date_key = get_date_from_gmt($actual_session['start_time'], 'Y-m-d');
							if ('' === $date_key) {
								continue;
							}
							$actual_session_dates[] = $date_key;
							$actual_sessions_payload[] = array(
								'date' => $date_key,
								'start' => $start_page,
								'end' => $end_page,
								'start_time' => (string) $actual_session['start_time'],
							);
						}
					}
				}

				if ($actual_session_dates) {
					$actual_session_dates = array_values(array_unique($actual_session_dates));
					$placeholders = implode(',', array_fill(0, count($actual_session_dates), '%s'));
					$wpdb->query(
						$wpdb->prepare(
							"UPDATE {$sessions_table}
						SET status = 'accomplished'
						WHERE plan_id = %d
						AND status != 'accomplished'
						AND DATE(planned_start_datetime) IN ({$placeholders})",
							array_merge(array($plan_id), $actual_session_dates)
						)
					);
				}

				// --- BACKEND DERIVATION IMPLEMENTATION ---
	
				// 1. Calculate actual reading progress
				$highest_page_read = 0;
				if ($goal_book_id) {
					$highest_page_read = (int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT MAX(end_page) FROM {$reading_sessions_table}
							 WHERE user_id = %d AND book_id = %d AND deleted_at IS NULL AND end_page IS NOT NULL",
							$user_id,
							$goal_book_id
						)
					);
				}
				$pages_read_in_plan = \Politeia\ReadingPlanner\PlanSessionDeriver::calculate_pages_read($highest_page_read, $goal_starting_page);
				$progress = \Politeia\ReadingPlanner\PlanSessionDeriver::calculate_progress($pages_read_in_plan, $total_pages);

				// 2. Prepare dates for derivation
				$future_session_dates = array();
				$session_dates = array();
				$selected = array();
				$session_items_map = array();

				if ($sessions) {
					$month_key_current = wp_date('Y-m', $month_ts, $timezone);
					foreach ($sessions as $session) {
						if (empty($session['planned_start_datetime'])) {
							continue;
						}
						$session_dt = date_create_immutable($session['planned_start_datetime'], $timezone);
						$session_ts = $session_dt ? $session_dt->getTimestamp() : null;
						$date_key = $session_ts ? wp_date('Y-m-d', $session_ts, $timezone) : '';

						if ('' === $date_key) {
							continue;
						}

						$session_dates[] = $date_key;
						$s_status = !empty($session['status']) ? (string) $session['status'] : 'planned';

						// Populate future dates for derivation (only 'planned' sessions in the future)
						if ($s_status === 'planned' && $date_key >= $today_key) {
							$future_session_dates[] = $date_key;
						}

						// Store non-planned sessions directly (accomplished/missed)
						// Planned sessions will be overwritten by derivation below
						$session_items_map[$date_key] = array(
							'date' => $date_key,
							'status' => $s_status,
							'planned_start_page' => 0,
							'planned_end_page' => 0,
							'actual_start_page' => null,
							'actual_end_page' => null,
						);

						// Calendar dots logic
						if ($session_ts && wp_date('Y-m', $session_ts, $timezone) === $month_key_current) {
							$selected[] = (int) wp_date('j', $session_ts, $timezone);
						}
					}
				}

				$session_dates = array_values(array_unique($session_dates));
				$selected = array_values(array_unique($selected));
				sort($selected);

				// 3. Derive projections for future sessions
				$derived_projections = \Politeia\ReadingPlanner\PlanSessionDeriver::derive_sessions(
					$total_pages,
					$goal_starting_page,
					$pages_read_in_plan,
					$future_session_dates,
					$today_key
				);

				// 4. Merge derived projections into session items
				foreach ($derived_projections as $projection) {
					$d = $projection['date'];
					if (isset($session_items_map[$d])) {
						$session_items_map[$d]['planned_start_page'] = $projection['start_page'];
						$session_items_map[$d]['planned_end_page'] = $projection['end_page'];
						$session_items_map[$d]['order'] = $projection['order'];
						// Status remains 'planned' as set in derivation
					}
				}
				$session_items = array_values($session_items_map);

				// --- END BACKEND DERIVATION ---
	
				$badge = 'ccl' === (string) $plan['plan_type']
					? __('Plan: More Pages', 'politeia-reading')
					: __('Reading Plan', 'politeia-reading');

				if ('complete_books' === $goal_kind) {
					$badge = sprintf(
						/* translators: 1: goal label, 2: page count, 3: pages label. */
						__('Goal: %1$s %2$s %3$s', 'politeia-reading'),
						__('Finish Book', 'politeia-reading'),
						(int) $total_pages,
						__('pages', 'politeia-reading')
					);
				}
				$subtitle = '';
				if ($goal_book_id) {
					$subtitle = (string) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT GROUP_CONCAT(a.display_name ORDER BY ba.sort_order ASC SEPARATOR ', ')
						FROM {$book_authors_table} ba
						LEFT JOIN {$authors_table} a ON a.id = ba.author_id
						WHERE ba.book_id = %d",
							$goal_book_id
						)
					);
					if ('' === $subtitle) {
						$subtitle = __('Unknown author', 'politeia-reading');
					}
				}

				$card_data = array(
					'plan_id' => $plan_id,
					'book_id' => $goal_book_id,
					'badge' => $badge,
					'title' => $plan['name'],
					'subtitle' => $subtitle,
					'month_label' => $month_label,
					'month_year' => $month_year,
					'month_range' => $month_range_label,
					'initial_month' => date('Y-m', $month_ts),
					'days_count' => $days_count,
					'start_offset' => $start_offset,
					'selected' => $selected,
					'session_dates' => $session_dates,
					'total_pages' => $total_pages,
					'progress' => $progress,
					'pages_read' => $pages_read_in_plan,
					'goal_kind' => $goal_kind,
					'session_items' => $session_items,
					'actual_sessions' => $actual_sessions_payload,
					'today_key' => $today_key,
					'plan_status' => $plan['status'],
				);
				set_transient($cache_key, $card_data, DAY_IN_SECONDS);
				$cards[] = $card_data;
			}
		}

		$baseline_metrics = array();
		if (function_exists('get_latest_user_baseline')) {
			$baseline = get_latest_user_baseline($user_id);
			if (!empty($baseline['metrics'])) {
				$baseline_metrics = (array) $baseline['metrics'];
			}
		}

		$today_key = current_time('Y-m-d');
		$upcoming_sessions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.plan_id, s.planned_start_datetime,
				        p.name AS plan_name, g.goal_kind, g.book_id
				 FROM {$sessions_table} s
				 INNER JOIN {$plans_table} p ON p.id = s.plan_id
				 LEFT JOIN {$goals_table} g ON g.plan_id = p.id
				 WHERE p.user_id = %d
				   AND p.status = %s
				   AND s.status = 'planned'
				   AND DATE(s.planned_start_datetime) >= %s
				 ORDER BY s.planned_start_datetime ASC
				 LIMIT 5",
				$user_id,
				'accepted',
				$today_key
			),
			ARRAY_A
		);

		$book_titles = array();
		$book_slugs = array();
		if ($upcoming_sessions) {
			$book_ids = array();
			foreach ($upcoming_sessions as $session_row) {
				if (!empty($session_row['book_id'])) {
					$book_ids[] = (int) $session_row['book_id'];
				}
			}
			$book_ids = array_values(array_unique($book_ids));
			if ($book_ids) {
				$placeholders = implode(',', array_fill(0, count($book_ids), '%d'));
				$book_rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT id, title FROM {$books_table} WHERE id IN ({$placeholders})",
						$book_ids
					),
					ARRAY_A
				);
				foreach ($book_rows as $book_row) {
					$book_titles[(int) $book_row['id']] = (string) $book_row['title'];
				}
				if (function_exists('prs_get_primary_slug_for_book')) {
					foreach ($book_ids as $book_id) {
						$slug = prs_get_primary_slug_for_book($book_id);
						if ($slug) {
							$book_slugs[$book_id] = $slug;
						}
					}
				}
			}
		}
		?>
		<?php if ($cards): ?>
			<?php $cards_count = count($cards); ?>
			<div class="prs-plan-grid">
				<?php foreach ($cards as $card): ?>
					<div class="prs-plan-card" data-plan-id="<?php echo esc_attr((string) $card['plan_id']); ?>"
						data-days-count="<?php echo esc_attr((string) $card['days_count']); ?>"
						data-start-offset="<?php echo esc_attr((string) $card['start_offset']); ?>"
						data-selected-days="<?php echo esc_attr(wp_json_encode($card['selected'])); ?>"
						data-session-label="<?php echo esc_attr__('Scheduled Session', 'politeia-reading'); ?>"
						data-day-format="<?php echo esc_attr__('Day %1$s of %2$s', 'politeia-reading'); ?>"
						data-month-label="<?php echo esc_attr($card['month_label']); ?>"
						data-remove-label="<?php echo esc_attr__('Remove session', 'politeia-reading'); ?>"
						data-total-pages="<?php echo esc_attr((string) $card['total_pages']); ?>"
						data-pages-read="<?php echo esc_attr(isset($card['pages_read']) ? (string) $card['pages_read'] : '0'); ?>"
						data-sessions-label="<?php echo esc_attr__('sessions', 'politeia-reading'); ?>"
						data-pages-label="<?php echo esc_attr__('pages', 'politeia-reading'); ?>"
						data-missed-label="<?php echo esc_attr__('Missed', 'politeia-reading'); ?>"
						data-completed-label="<?php echo esc_attr__('Completed', 'politeia-reading'); ?>"
						data-session-dates="<?php echo esc_attr(wp_json_encode($card['session_dates'])); ?>"
						data-session-items="<?php echo esc_attr(wp_json_encode($card['session_items'])); ?>"
						data-actual-sessions="<?php echo esc_attr(wp_json_encode($card['actual_sessions'])); ?>"
						data-initial-month="<?php echo esc_attr($card['initial_month']); ?>"
						data-goal-kind="<?php echo esc_attr($card['goal_kind']); ?>"
						data-today-key="<?php echo esc_attr($card['today_key']); ?>"
						data-confirm-text="<?php echo esc_attr__('Accept Proposal', 'politeia-reading'); ?>"
						data-confirmed-text="<?php echo esc_attr__('Plan saved!', 'politeia-reading'); ?>">
						<div class="prs-plan-header">
							<div id="plan_book_info">
								<span class="prs-plan-badge"><?php echo esc_html($card['badge']); ?></span>
								<h2 class="prs-plan-title">
									<span class="prs-plan-title-row">
										<?php if (!empty($card['book_id']) && function_exists('prs_get_primary_slug_for_book')): ?>
											<?php
											$book_slug = prs_get_primary_slug_for_book((int) $card['book_id']);
											$book_url = $book_slug ? home_url('/my-books/my-book-' . $book_slug . '/') : '';
											?>
											<?php if ($book_url): ?>
												<a href="<?php echo esc_url($book_url); ?>"><?php echo esc_html($card['title']); ?></a>
											<?php else: ?>
												<span><?php echo esc_html($card['title']); ?></span>
											<?php endif; ?>
										<?php else: ?>
											<span><?php echo esc_html($card['title']); ?></span>
										<?php endif; ?>
										<?php if (!empty($card['book_id'])): ?>
											<span role="button" tabindex="0"
												class="prs-session-recorder-trigger material-symbols-outlined" data-role="session-open"
												aria-label="<?php esc_attr_e('Open session recorder', 'politeia-reading'); ?>"
												aria-controls="prs-session-modal-<?php echo esc_attr((string) $card['plan_id']); ?>"
												aria-expanded="false">play_circle</span>
										<?php endif; ?>
									</span>
									<br>
									<span class="prs-plan-subtitle"><?php echo esc_html($card['subtitle']); ?></span>
								</h2>
							</div>
							<div id="politeia_plan_result">
								<img src="<?php echo esc_url(plugins_url('politeia-bookshelf/modules/reading/assets/svg/PoliteiaGoldMedal.svg')); ?>"
									alt="<?php esc_attr_e('Plan Result', 'politeia-reading'); ?>" class="<?php
									   $status_class = '';
									   if ($card['plan_status'] === 'accepted') {
										   $status_class = 'plan-in-progress';
									   } elseif ($card['plan_status'] === 'desisted') {
										   $status_class = 'plan-desisted';
									   }
									   echo $status_class;
									   ?>" />
								<div class="plan-menu-container">
									<button type="button" class="plan-menu-button"
										aria-label="<?php esc_attr_e('Plan options', 'politeia-reading'); ?>"
										data-plan-id="<?php echo esc_attr($card['plan_id']); ?>">
										<span class="plan-menu-dots">⋯</span>
									</button>
									<div class="plan-menu-dropdown" style="display: none;">
										<button type="button" class="plan-menu-item plan-desist-btn"
											data-plan-id="<?php echo esc_attr($card['plan_id']); ?>">
											<?php esc_html_e('Desist', 'politeia-reading'); ?>
										</button>
									</div>
								</div>
							</div>
						</div>

						<button type="button" class="prs-plan-toggle" data-role="toggle">
							<div class="prs-plan-toggle-icon" aria-hidden="true">
								<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="none" viewBox="0 0 24 24"
									stroke="currentColor">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
										d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
								</svg>
							</div>
							<div>
								<span
									class="prs-plan-toggle-label"><?php esc_html_e('See Session Calendar', 'politeia-reading'); ?></span><br>
								<span class="prs-plan-toggle-date"><?php echo esc_html($card['month_range']); ?></span>
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
											<h3 class="prs-calendar-title" data-role="calendar-title">
												<?php echo esc_html($card['month_year']); ?>
											</h3>
											<div class="prs-calendar-nav">
												<a href="#" class="prs-calendar-nav-btn" role="button" data-role="month-prev"
													aria-label="<?php esc_attr_e('Previous Month', 'politeia-reading'); ?>">
													<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none"
														viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"
														stroke-linecap="round" stroke-linejoin="round">
														<path d="M15 6l-6 6 6 6" />
													</svg>
												</a>
												<a href="#" class="prs-calendar-nav-btn" role="button" data-role="month-next"
													aria-label="<?php esc_attr_e('Next Month', 'politeia-reading'); ?>">
													<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none"
														viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"
														stroke-linecap="round" stroke-linejoin="round">
														<path d="M9 6l6 6-6 6" />
													</svg>
												</a>
											</div>
										</div>
										<div class="prs-calendar-meta" data-role="calendar-meta"></div>
									</div>
									<div>
										<div class="prs-toggle-group" role="tablist">
											<a href="#" class="prs-toggle-button is-active" role="button" data-role="view-cal"
												aria-label="<?php esc_attr_e('Calendar', 'politeia-reading'); ?>">
												<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none"
													viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
													stroke-linecap="round" stroke-linejoin="round">
													<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
													<line x1="16" y1="2" x2="16" y2="6"></line>
													<line x1="8" y1="2" x2="8" y2="6"></line>
													<line x1="3" y1="10" x2="21" y2="10"></line>
												</svg>
											</a>
											<a href="#" class="prs-toggle-button" role="button" data-role="view-list"
												aria-label="<?php esc_attr_e('List', 'politeia-reading'); ?>">
												<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none"
													viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
													stroke-linecap="round" stroke-linejoin="round">
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
										<div><?php esc_html_e('Mon', 'politeia-reading'); ?></div>
										<div><?php esc_html_e('Tue', 'politeia-reading'); ?></div>
										<div><?php esc_html_e('Wed', 'politeia-reading'); ?></div>
										<div><?php esc_html_e('Thu', 'politeia-reading'); ?></div>
										<div><?php esc_html_e('Fri', 'politeia-reading'); ?></div>
										<div><?php esc_html_e('Sat', 'politeia-reading'); ?></div>
										<div><?php esc_html_e('Sun', 'politeia-reading'); ?></div>
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
								<span
									class="prs-progress-label"><?php esc_html_e('Reading Completed', 'politeia-reading'); ?></span>
								<span class="prs-progress-value"><?php echo esc_html($card['progress']); ?>%</span>
							</div>
							<div class="prs-progress-bar" role="progressbar"
								aria-valuenow="<?php echo esc_attr((string) $card['progress']); ?>" aria-valuemin="0"
								aria-valuemax="100">
								<div class="prs-progress-bar-fill"
									style="width: <?php echo esc_attr((string) $card['progress']); ?>%;"></div>
							</div>
						</div>
						<?php if (!empty($card['book_id'])): ?>
							<div id="prs-session-modal-<?php echo esc_attr((string) $card['plan_id']); ?>" class="prs-session-modal"
								role="dialog" aria-modal="true" aria-hidden="true"
								aria-label="<?php esc_attr_e('Session recorder', 'politeia-reading'); ?>" data-role="session-modal">
								<div class="prs-session-modal__content">
									<button type="button" class="prs-session-modal__close"
										aria-label="<?php esc_attr_e('Close session recorder', 'politeia-reading'); ?>"
										data-role="session-close">
										×
									</button>
									<?php echo do_shortcode('[politeia_start_reading book_id="' . (int) $card['book_id'] . '" plan_id="' . (int) $card['plan_id'] . '"]'); ?>
								</div>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
				<?php if (1 === $cards_count): ?>
					<div class="prs-upcoming-wrap">
						<div class="prs-upcoming-card">
							<h2 class="prs-upcoming-title"><?php esc_html_e('Upcoming Reading Sessions', 'politeia-reading'); ?>
							</h2>
							<div class="prs-upcoming-list">
								<?php if ($upcoming_sessions): ?>
									<?php foreach ($upcoming_sessions as $session_row): ?>
										<?php
										$session_title = $session_row['plan_name'] ?? '';
										$book_id = isset($session_row['book_id']) ? (int) $session_row['book_id'] : 0;
										$book_url = '';
										if ($book_id && isset($book_titles[$book_id])) {
											$session_title = $book_titles[$book_id];
											if (isset($book_slugs[$book_id]) && $book_slugs[$book_id]) {
												$book_url = home_url('/my-books/my-book-' . $book_slugs[$book_id] . '/');
											}
										}
										$session_title = $session_title ? $session_title : __('Reading Session', 'politeia-reading');

										$goal_kind = isset($session_row['goal_kind']) ? (string) $session_row['goal_kind'] : '';
										$meta_label = '';
										if (in_array($goal_kind, array('form_habit', 'habit'), true)) {
											$minutes = isset($baseline_metrics['minutes_per_session']) ? (int) $baseline_metrics['minutes_per_session'] : 0;
											if ($minutes <= 0) {
												$minutes = 30;
											}
											$meta_label = sprintf(__('%d min', 'politeia-reading'), $minutes);
										} else {
											$start_page = isset($session_row['planned_start_page']) ? (int) $session_row['planned_start_page'] : 0;
											$end_page = isset($session_row['planned_end_page']) ? (int) $session_row['planned_end_page'] : 0;
											$page_count = $end_page >= $start_page && $start_page > 0 ? ($end_page - $start_page + 1) : 0;
											$meta_label = $page_count > 0
												? sprintf(_n('%d página', '%d páginas', $page_count, 'politeia-reading'), $page_count)
												: __('Páginas', 'politeia-reading');
										}

										$date_label = '';
										$is_today = false;
										$is_tomorrow = false;
										if (!empty($session_row['planned_start_datetime'])) {
											$session_dt = date_create_immutable($session_row['planned_start_datetime'], $timezone);
											$session_ts = $session_dt ? $session_dt->getTimestamp() : null;
											if ($session_ts) {
												$session_key = wp_date('Y-m-d', $session_ts, $timezone);
												$today_dt = new DateTimeImmutable($today_key . ' 00:00:00', $timezone);
												$tomorrow_key = $today_dt->modify('+1 day')->format('Y-m-d');
												$is_today = $session_key === $today_key;
												$is_tomorrow = $session_key === $tomorrow_key;
												$date_label = wp_date('j F', $session_ts, $timezone);
											}
										}
										?>
										<div class="prs-upcoming-item">
											<div class="prs-upcoming-row">
												<div class="prs-upcoming-info">
													<?php if ($book_url): ?>
														<a class="prs-upcoming-book" href="<?php echo esc_url($book_url); ?>">
															<?php echo esc_html($session_title); ?>
														</a>
													<?php else: ?>
														<div class="prs-upcoming-book"><?php echo esc_html($session_title); ?></div>
													<?php endif; ?>
													<?php if ($meta_label): ?>
														<div class="prs-upcoming-meta"><?php echo esc_html($meta_label); ?></div>
													<?php endif; ?>
												</div>
												<?php if ($date_label): ?>
													<div class="prs-upcoming-date <?php echo $is_today ? 'prs-upcoming-date--today' : ''; ?>">
														<?php
														if ($is_today) {
															echo esc_html(__('Today', 'politeia-reading'));
														} elseif ($is_tomorrow) {
															echo esc_html(__('Tomorrow', 'politeia-reading'));
														} else {
															echo esc_html($date_label);
														}
														?>
													</div>
												<?php endif; ?>
											</div>
										</div>
									<?php endforeach; ?>
								<?php else: ?>
									<div class="prs-upcoming-empty">
										<?php esc_html_e('No upcoming sessions yet.', 'politeia-reading'); ?>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endif; ?>
			</div>
		<?php else: ?>
			<p><?php esc_html_e('No plans yet.', 'politeia-reading'); ?></p>
		<?php endif; ?>
	<?php else: ?>
		<p><?php esc_html_e('Access denied.', 'politeia-reading'); ?></p>
	<?php endif; ?>
</div>

<script>
	(function () {
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
			const planMenuButton = card.querySelector('.plan-menu-button');
			const planMenuDropdown = card.querySelector('.plan-menu-dropdown');
			const planDesistBtn = card.querySelector('.plan-desist-btn');

			let totalPages = parseInt(card.dataset.totalPages, 10) || 0;
			let pagesRead = parseInt(card.dataset.pagesRead, 10) || 0;
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

			const fetchPlanDetails = () => {
				const planId = card.dataset.planId;
				if (!planId) return;

				const timestamp = new Date().getTime();
				fetch(`/wp-json/politeia/v1/reading-plan/${planId}?t=${timestamp}`, {
					headers: {
						'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
					}
				})
					.then(res => res.json())
					.then(data => {
						if (data.success) {
							console.log('Fetched Plan Details:', data);
							totalPages = data.total_pages;
							pagesRead = data.pages_read;
							// Update derived session data
							sessionDates = data.session_dates || [];
							sessionItems = data.session_items || [];
							actualSessions = data.actual_sessions || [];

							// Update card datasets for consistency
							card.dataset.totalPages = totalPages;
							card.dataset.pagesRead = pagesRead;
							// We don't necessarily need to update the big JSON strings in dataset if we use vars

							// Update progress bar
							const progressVal = data.progress || 0;
							const progressBar = card.querySelector('.prs-progress-bar-fill');
							const progressText = card.querySelector('.prs-progress-value');
							if (progressBar) progressBar.style.width = `${progressVal}%`;
							if (progressText) progressText.textContent = `${progressVal}%`;
							const progressBarContainer = card.querySelector('.prs-progress-bar');
							if (progressBarContainer) progressBarContainer.setAttribute('aria-valuenow', progressVal);

							// Re-render views
							renderCalendar();
							renderList();
						}
					})
					.catch(err => console.error('Error fetching plan details:', err));
			};

			// buildDerivedPlan removed - using backend authoritative data

			const updateMeta = () => {
				if (!metaLabel) return;
				const monthSessions = getMonthSessions(currentMonthKey);
				const sessionCount = monthSessions.length;

				let pagesPerSession = 0;
				if (isCompleteBooks) {
					const remainingPages = Math.max(0, totalPages - pagesRead);
					const remainingSessionsCount = sessionDates.filter(d => {
						const s = getStatusByDate(d);
						return s === 'planned' && d >= (todayKey || '');
					}).length;
					pagesPerSession = remainingSessionsCount > 0 ? Math.ceil(remainingPages / remainingSessionsCount) : 0;
				} else {
					if (sessionCount > 0 && totalPages > 0) pagesPerSession = Math.ceil(totalPages / sessionCount);
				}

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
				const startOffset = (new Date(viewDate.getFullYear(), viewDate.getMonth(), 1).getDay() + 6) % 7;
				grid.innerHTML = '';
				const monthSessions = getMonthSessions(currentMonthKey);

				const nonMissedDates = sessionDates
					.filter((dateStr) => getStatusByDate(dateStr) !== 'missed')
					.sort();
				const orderByDate = new Map();
				nonMissedDates.forEach((dateStr, index) => {
					orderByDate.set(dateStr, index + 1);
				});

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

					// Check if this day is today
					const currentDate = `${currentMonthKey}-${pad2(day)}`;
					const isToday = todayKey && currentDate === todayKey;
					if (isToday) {
						cell.classList.add('is-today');
					}

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
							? (orderByDate.get(targetDate) || sortedDays.indexOf(day) + 1)
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
								const planId = card.dataset.planId;
								if (!planId) return;

								if (!confirm(strings.removeLabel || 'Remove session?')) return;

								fetch(`/wp-json/politeia/v1/reading-plan/${planId}/session/${targetDate}`, {
									method: 'DELETE',
									headers: {
										'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
									}
								})
									.then(res => res.json())

									.then(data => {
										if (data.success) {
											fetchPlanDetails();
										} else {
											alert(data.message || 'Error removing session');
										}
									})
									.catch(err => console.error(err));
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

									// Add session via API
									const planId = card.dataset.planId;
									if (!planId) return;

									addBtn.disabled = true;
									addBtn.style.cursor = 'wait';

									fetch(`/wp-json/politeia/v1/reading-plan/${planId}/session`, {
										method: 'POST',
										headers: {
											'Content-Type': 'application/json',
											'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
										},
										body: JSON.stringify({ session_date: newDate })
									})
										.then(res => res.json())
										.then(data => {
											if (data.success || data.session) {
												fetchPlanDetails();
												addBtn.disabled = false;
												addBtn.style.cursor = '';
												// Remove the add button immediately so it doesn't get stuck
												addBtn.remove();
											} else {
												alert(data.message || 'Error adding session');
												addBtn.disabled = false;
												addBtn.style.cursor = '';
											}
										})
										.catch(err => {
											console.error(err);
											addBtn.disabled = false;
											addBtn.style.cursor = '';
										});
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
						const planId = card.dataset.planId;

						if (target && !sortedDays.includes(target) && planId) {
							const originDate = `${currentMonthKey}-${pad2(origin)}`;
							const targetDate = `${currentMonthKey}-${pad2(target)}`;

							// Optimistic UI could happen here, but reloading is safer for now
							fetch(`/wp-json/politeia/v1/reading-plan/${planId}/session/${originDate}`, {
								method: 'PUT',
								headers: {
									'Content-Type': 'application/json',
									'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
								},
								body: JSON.stringify({ new_date: targetDate })
							})
								.then(res => res.json())
								.then(data => {
									if (data.success) {
										fetchPlanDetails();
									} else {
										alert(data.message || 'Error moving session');
									}
								})
								.catch(err => console.error(err));
						}
					});

					grid.appendChild(cell);
				}

				// Mark the weekday label above today's date
				if (todayKey && todayKey.startsWith(currentMonthKey)) {
					const todayDay = parseInt(todayKey.split('-')[2], 10);
					const todayDate = new Date(viewDate.getFullYear(), viewDate.getMonth(), todayDay);
					const todayWeekday = (todayDate.getDay() + 6) % 7; // Convert Sunday=0 to Monday=0
					const weekdayLabels = card.querySelectorAll('.prs-weekdays div');
					weekdayLabels.forEach((label, index) => {
						label.classList.remove('is-today-weekday');
						if (index === todayWeekday) {
							label.classList.add('is-today-weekday');
						}
					});
				}
			};

			const renderList = () => {
				listContainer.innerHTML = '';
				const monthSessions = getMonthSessions(currentMonthKey);
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
						if (isCompleteBooks) {
							const item = sessionItems.find(i => i.date === dateKey);
							if (item && item.planned_start_page > 0 && item.planned_end_page > 0) {
								expectedPages = item.planned_end_page - item.planned_start_page + 1;
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
					const recorder = sessionModal.querySelector('.prs-sr');
					if (recorder && typeof window.prsStartReadingInit === 'function') {
						let data = null;
						if (recorder.dataset.prsSr) {
							try {
								data = JSON.parse(recorder.dataset.prsSr);
							} catch (error) {
								data = null;
							}
						}
						if (data) {
							window.PRS_SR = data;
						}
						window.prsStartReadingInit({ root: sessionModal, data: data || window.PRS_SR });
					}
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
						event.preventDefault();
					}
				});

				// Plan menu toggle
				if (planMenuButton && planMenuDropdown) {
					planMenuButton.addEventListener('click', (event) => {
						event.stopPropagation();
						const isVisible = planMenuDropdown.style.display === 'block';
						// Close all other dropdowns first
						document.querySelectorAll('.plan-menu-dropdown').forEach(dropdown => {
							dropdown.style.display = 'none';
						});
						planMenuDropdown.style.display = isVisible ? 'none' : 'block';
					});

					// Close dropdown when clicking outside
					document.addEventListener('click', (event) => {
						if (!card.contains(event.target)) {
							planMenuDropdown.style.display = 'none';
						}
					});
				}

				// Desist plan handler
				if (planDesistBtn) {
					planDesistBtn.addEventListener('click', function () {
						const planId = this.dataset.planId;
						if (!planId) return;

						if (!confirm('¿Estás seguro de que deseas desistir de este plan? Esta acción no se puede deshacer.')) {
							return;
						}

						// Close the dropdown
						if (planMenuDropdown) {
							planMenuDropdown.style.display = 'none';
						}

						// Send AJAX request
						fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
							method: 'POST',
							headers: {
								'Content-Type': 'application/x-www-form-urlencoded',
							},
							body: new URLSearchParams({
								action: 'desist_reading_plan',
								plan_id: planId,
								nonce: '<?php echo wp_create_nonce('desist_plan_nonce'); ?>'
							})
						})
							.then(response => response.json())
							.then(data => {
								if (data.success) {
									alert('Plan desistido exitosamente.');
									location.reload();
								} else {
									alert('Error: ' + (data.data || 'No se pudo desistir del plan.'));
								}
							})
							.catch(error => {
								console.error('Error:', error);
								alert('Ocurrió un error al desistir del plan.');
							});
					});
				}

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
