<?php
if (!defined('ABSPATH')) {
	exit;
}

$creator_css_rel_path = '/politeia-learning/modules/course-creator/assets/css/creator-dashboard.css';
$creator_css_abs_path = WP_PLUGIN_DIR . $creator_css_rel_path;
if (file_exists($creator_css_abs_path)) {
	wp_enqueue_style(
		'politeia-learning-creator-dashboard',
		WP_PLUGIN_URL . $creator_css_rel_path,
		array(),
		filemtime($creator_css_abs_path)
	);
}
wp_enqueue_style('dashicons');

get_header();

if (!is_user_logged_in()) {
	echo '<div class="wrap"><p>' . esc_html__('You must be logged in.', 'politeia-reading') . '</p></div>';
	get_footer();
	return;
}

$plan_id = intval(get_query_var('plan_id'));
$allowed_sections = array('calendar', 'list', 'notes', 'settings');
$current_section = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : 'calendar';
if (!in_array($current_section, $allowed_sections, true)) {
	$current_section = 'calendar';
}

$section_titles = array(
	'calendar' => 'CALENDAR',
	'list' => 'LIST',
	'notes' => 'NOTES',
	'settings' => 'SETTINGS',
);

$current_section_title = $section_titles[$current_section];
$current_title = $current_section_title;
$base_url = home_url('/my-plan/' . max(0, $plan_id) . '/');

$plan_cover_url = '';
$plan_cover_title = '';
$plan_cover_author = '';
$plan_user_book_pages = 0;
$plan_display_title = '';
$plan_type = '';
$plan_goal_kind = 'complete_books';
$is_habit_plan = false;

$resolve_cover_url = static function ($raw_cover, $url_fallback = '') {
	$resolved_url = '';
	$raw_cover = trim((string) $raw_cover);
	$url_fallback = trim((string) $url_fallback);

	if ($url_fallback) {
		$resolved_url = esc_url_raw($url_fallback);
	}

	if ($raw_cover !== '') {
		$attachment_id = 0;
		$parsed_url = '';

		if (class_exists('PRS_Cover_Upload_Feature') && method_exists('PRS_Cover_Upload_Feature', 'parse_cover_value')) {
			$parsed_cover = PRS_Cover_Upload_Feature::parse_cover_value($raw_cover);
			$attachment_id = isset($parsed_cover['attachment_id']) ? (int) $parsed_cover['attachment_id'] : 0;
			$parsed_url = isset($parsed_cover['url']) ? esc_url_raw((string) $parsed_cover['url']) : '';
		}

		if (!$attachment_id && !$parsed_url) {
			$json = json_decode($raw_cover, true);
			if (is_array($json)) {
				if (!empty($json['attachment_id'])) {
					$attachment_id = (int) $json['attachment_id'];
				} elseif (!empty($json['id'])) {
					$attachment_id = (int) $json['id'];
				}
				if (!empty($json['url'])) {
					$parsed_url = esc_url_raw((string) $json['url']);
				}
			}
		}

		if (!$attachment_id && !$parsed_url) {
			if (is_numeric($raw_cover) && (int) $raw_cover > 0) {
				$attachment_id = (int) $raw_cover;
			} elseif (0 === strpos($raw_cover, 'attachment:')) {
				$maybe_id = (int) trim(substr($raw_cover, strlen('attachment:')));
				if ($maybe_id > 0) {
					$attachment_id = $maybe_id;
				}
			} elseif (0 === strpos($raw_cover, 'url:')) {
				$parsed_url = esc_url_raw(substr($raw_cover, 4));
			} elseif (filter_var($raw_cover, FILTER_VALIDATE_URL)) {
				$parsed_url = esc_url_raw($raw_cover);
			}
		}

		if ($parsed_url) {
			$resolved_url = $parsed_url;
		} elseif ($attachment_id > 0) {
			$attachment_url = wp_get_attachment_image_url($attachment_id, 'medium');
			if (!$attachment_url) {
				$attachment_url = wp_get_attachment_image_url($attachment_id, 'full');
			}
			if ($attachment_url) {
				$resolved_url = $attachment_url;
			}
		}
	}

	return $resolved_url;
};

if ($plan_id > 0) {
	global $wpdb;
	$plans_table = $wpdb->prefix . 'politeia_plans';
	$finish_book_table = $wpdb->prefix . 'politeia_plan_finish_book';
	$plan_subjects_table = $wpdb->prefix . 'politeia_plan_subjects';
	$user_books_table = $wpdb->prefix . 'politeia_user_books';
	$books_table = $wpdb->prefix . 'politeia_books';
	$book_authors_table = $wpdb->prefix . 'politeia_book_authors';
	$authors_table = $wpdb->prefix . 'politeia_authors';

	$plan_row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, user_id, plan_type, name FROM {$plans_table} WHERE id = %d LIMIT 1",
			$plan_id
		)
	);

	$plan_type = $plan_row ? strtolower((string) $plan_row->plan_type) : '';
	$is_habit_plan = in_array($plan_type, array('habit', 'form_habit'), true);
	$plan_goal_kind = $is_habit_plan ? 'habit' : 'complete_books';
	$plan_display_title = $plan_row && !empty($plan_row->name) ? (string) $plan_row->name : '';

	if (!$is_habit_plan) {
		$plan_user_id = $plan_row ? (int) $plan_row->user_id : 0;
		$finish_has_user_book_id = (bool) $wpdb->get_var("SHOW COLUMNS FROM {$finish_book_table} LIKE 'user_book_id'");
		$finish_has_book_id = (bool) $wpdb->get_var("SHOW COLUMNS FROM {$finish_book_table} LIKE 'book_id'");

		$user_book_id = 0;
		$book_id = 0;

		if ($finish_has_user_book_id) {
			$user_book_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT user_book_id FROM {$finish_book_table} WHERE plan_id = %d LIMIT 1",
					$plan_id
				)
			);
		}

		if ($finish_has_book_id) {
			$book_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT book_id FROM {$finish_book_table} WHERE plan_id = %d LIMIT 1",
					$plan_id
				)
			);
		}

		if ($user_book_id <= 0 && $book_id > 0 && $plan_user_id > 0) {
			$user_book_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id
					FROM {$user_books_table}
					WHERE user_id = %d
						AND book_id = %d
						AND deleted_at IS NULL
					ORDER BY id DESC
					LIMIT 1",
					$plan_user_id,
					$book_id
				)
			);
		}

		if ($book_id <= 0) {
			$book_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT subject_id
					FROM {$plan_subjects_table}
					WHERE plan_id = %d
					ORDER BY subject_id ASC
					LIMIT 1",
					$plan_id
				)
			);
		}

		if ($user_book_id <= 0 && $book_id > 0 && $plan_user_id > 0) {
			$user_book_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id
					FROM {$user_books_table}
					WHERE user_id = %d
						AND book_id = %d
						AND deleted_at IS NULL
					ORDER BY id DESC
					LIMIT 1",
					$plan_user_id,
					$book_id
				)
			);
		}

		$user_book_row = null;
		if ($user_book_id > 0) {
			$user_book_row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, book_id, pages, cover_reference, cover_url
					FROM {$user_books_table}
					WHERE id = %d
					LIMIT 1",
					$user_book_id
				)
			);
			if ($user_book_row && empty($book_id)) {
				$book_id = (int) $user_book_row->book_id;
			}
			if ($user_book_row && !empty($user_book_row->pages)) {
				$plan_user_book_pages = (int) $user_book_row->pages;
			}
		}

		$book_row = null;
		if ($book_id > 0) {
			$book_row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						b.title,
						b.cover_attachment_id,
						b.cover_url,
						(
							SELECT GROUP_CONCAT(a.display_name ORDER BY ba.sort_order ASC SEPARATOR ', ')
							FROM {$book_authors_table} ba
							LEFT JOIN {$authors_table} a ON a.id = ba.author_id
							WHERE ba.book_id = b.id
						) AS authors
					FROM {$books_table} b
					WHERE b.id = %d
					LIMIT 1",
					$book_id
				)
			);
		}

		$plan_cover_title = $book_row && isset($book_row->title) ? (string) $book_row->title : $plan_display_title;
		$user_cover_url = $user_book_row && !empty($user_book_row->cover_url) ? (string) $user_book_row->cover_url : '';
		$cover_reference = $user_book_row && isset($user_book_row->cover_reference) ? (string) $user_book_row->cover_reference : '';
		$book_cover_attachment_id = $book_row && !empty($book_row->cover_attachment_id) ? (int) $book_row->cover_attachment_id : 0;
		$book_cover_url = $book_row && !empty($book_row->cover_url) ? (string) $book_row->cover_url : '';

		$plan_cover_url = $resolve_cover_url($cover_reference, $user_cover_url);
		if (!$plan_cover_url && $book_cover_attachment_id > 0) {
			$plan_cover_url = wp_get_attachment_image_url($book_cover_attachment_id, 'medium');
			if (!$plan_cover_url) {
				$plan_cover_url = wp_get_attachment_image_url($book_cover_attachment_id, 'full');
			}
		}
		if (!$plan_cover_url && $book_cover_url) {
			$plan_cover_url = esc_url_raw($book_cover_url);
		}

		$plan_cover_author = $book_row && !empty($book_row->authors) ? (string) $book_row->authors : '';

		if ((!$plan_cover_url || !$plan_cover_author) && $plan_user_id > 0 && $plan_display_title !== '') {
			$fallback_row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						ub.cover_reference,
						ub.cover_url AS user_cover_url,
						ub.pages AS user_book_pages,
						b.cover_attachment_id,
						b.cover_url AS book_cover_url,
						b.title AS book_title,
						(
							SELECT GROUP_CONCAT(a.display_name ORDER BY ba.sort_order ASC SEPARATOR ', ')
							FROM {$book_authors_table} ba
							LEFT JOIN {$authors_table} a ON a.id = ba.author_id
							WHERE ba.book_id = b.id
						) AS authors
					FROM {$user_books_table} ub
					INNER JOIN {$books_table} b ON b.id = ub.book_id
					WHERE ub.user_id = %d
						AND ub.deleted_at IS NULL
						AND (
							b.title = %s
							OR LOWER(TRIM(b.title)) = LOWER(TRIM(%s))
						)
					ORDER BY ub.id DESC
					LIMIT 1",
					$plan_user_id,
					$plan_display_title,
					$plan_display_title
				)
			);

			if ($fallback_row) {
				if (!$plan_cover_title && !empty($fallback_row->book_title)) {
					$plan_cover_title = (string) $fallback_row->book_title;
				}
				if (!$plan_cover_author && !empty($fallback_row->authors)) {
					$plan_cover_author = (string) $fallback_row->authors;
				}
				if ($plan_user_book_pages <= 0 && !empty($fallback_row->user_book_pages)) {
					$plan_user_book_pages = (int) $fallback_row->user_book_pages;
				}
				if (!$plan_cover_url) {
					$plan_cover_url = $resolve_cover_url(
						isset($fallback_row->cover_reference) ? (string) $fallback_row->cover_reference : '',
						!empty($fallback_row->user_cover_url) ? (string) $fallback_row->user_cover_url : ''
					);
					if (!$plan_cover_url && !empty($fallback_row->cover_attachment_id)) {
						$fb_attachment_id = (int) $fallback_row->cover_attachment_id;
						$plan_cover_url = wp_get_attachment_image_url($fb_attachment_id, 'medium');
						if (!$plan_cover_url) {
							$plan_cover_url = wp_get_attachment_image_url($fb_attachment_id, 'full');
						}
					}
					if (!$plan_cover_url && !empty($fallback_row->book_cover_url)) {
						$plan_cover_url = esc_url_raw((string) $fallback_row->book_cover_url);
					}
				}
			}
		}
	}
}

$topbar_plan_name = trim((string) ($plan_cover_title !== '' ? $plan_cover_title : $plan_display_title));
if ($topbar_plan_name !== '') {
	$topbar_plan_name = preg_replace('/\s+plan$/iu', '', $topbar_plan_name);
	$current_title = sprintf(
		'%s | %s %s',
		$current_section_title,
		$topbar_plan_name,
		esc_html__('PLAN', 'politeia-reading')
	);
}

$current_month = wp_date('Y-m', current_time('timestamp'), wp_timezone());
$current_month_label = wp_date('F Y', current_time('timestamp'), wp_timezone());
$today_key = current_time('Y-m-d');
?>

<div id="single-plan-template" class="single-plan-template pcg-creator-dashboard-wrapper">
	<style>
		.container.site-header-container.flex.default-header {
			max-width: 100% !important;
		}

		.container.single-plan-shell {
			max-width: 100% !important;
			width: 100% !important;
			margin: 0 !important;
			padding: 0 !important;
		}

		.bb-grid.site-content-grid.single-plan-shell-grid {
			max-width: 100% !important;
			width: 100% !important;
			margin: 0 !important;
			padding: 0 !important;
			grid-template-columns: 1fr !important;
			gap: 0 !important;
		}

		div#single-plan-container {
			margin: 0 !important;
			max-width: 100% !important;
			width: 100% !important;
			padding: 0 !important;
		}

		div#single-plan-template {
			padding: 0 !important;
			margin: 0 !important;
			max-width: 100% !important;
			width: 100% !important;
		}

		#single-plan-template,
		#single-plan-template *:not(.dashicons):not(.dashicons-before):not(.material-symbols-outlined) {
			font-family: 'Poppins', sans-serif !important;
		}

		div#content {
			padding: 77px 0 !important;
		}

		.single-plan-cover-wrap {
			padding: 16px;
			border-bottom: 1px solid #f0f0f0;
		}

		@media (max-width: 1230px) {
			.single-plan-cover-wrap {
				display: none !important;
			}
		}

		.single-plan-cover-image {
			display: block;
			width: 197px;
			height: auto;
			max-width: 100%;
			aspect-ratio: auto;
			object-fit: contain;
			border-radius: 6px;
		}

		.single-plan-cover-title {
			margin: 12px 0 0;
			font-size: 16px;
			line-height: 1.3;
			font-weight: 700;
			color: #111;
			word-break: break-word;
		}

		.single-plan-cover-author {
			margin: 4px 0 0;
			font-size: 13px;
			line-height: 1.3;
			font-weight: 500;
			color: #666;
			word-break: break-word;
		}

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

		.prs-plan-card .prs-calendar-card {
			margin-top: 0;
			border-radius: var(--prs-radius);
		}

		.single-plan-dashboard.pcg-sales-dashboard {
			max-width: 1000px;
		}

		.prs-plan-card .prs-calendar-header {
			display: flex;
			justify-content: space-between;
			align-items: flex-start;
			margin-bottom: 0;
		}

		.prs-plan-card .prs-calendar-title {
			font-size: 18px;
			text-transform: uppercase;
			font-weight: 700;
			color: var(--prs-black);
			margin-bottom: 0;
		}

		.prs-plan-card .prs-calendar-title-row {
			display: flex;
			align-items: center;
			gap: 12px;
		}

		.prs-plan-card .prs-calendar-nav {
			display: flex;
			gap: 8px;
			align-items: center;
		}

		.prs-plan-card .prs-calendar-nav-btn {
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

		.prs-plan-card .prs-calendar-nav-btn.is-disabled {
			opacity: 0.4;
			cursor: default;
			pointer-events: none;
		}

		.prs-plan-card .prs-calendar-meta {
			font-size: 11px;
			font-weight: 700;
			letter-spacing: 0.15em;
			text-transform: uppercase;
			color: var(--prs-gold);
			margin-bottom: 14px;
		}

		.single-plan-upcoming-pages {
			font-size: 18px;
			font-weight: 700;
			letter-spacing: 0.12em;
			text-transform: uppercase;
			color: #8f8f8f;
			white-space: nowrap;
			text-align: right;
		}

		.single-plan-upcoming-sub {
			display: block;
			margin-top: 4px;
			font-size: 11px;
			font-weight: 700;
			letter-spacing: 0.15em;
			text-transform: uppercase;
			color: #8f8f8f;
			line-height: 1.2;
		}

		.single-plan-settings-card {
			max-width: 320px;
			padding: 12px 14px;
			background: #fff;
			border: 1px solid #e6e6e6;
			border-radius: 6px;
		}

		.single-plan-settings-row {
			margin: 0 0 8px;
			font-size: 12px;
			line-height: 1.35;
			color: #222;
		}

		.single-plan-settings-row:last-child {
			margin-bottom: 0;
		}

		.single-plan-settings-key {
			font-size: 10px;
			letter-spacing: 0.08em;
			text-transform: uppercase;
			color: #8a8a8a;
			margin-right: 6px;
		}

		.prs-plan-card .prs-view.is-hidden {
			display: none;
			opacity: 0;
		}

		.prs-plan-card .prs-weekdays {
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

		.prs-plan-card .prs-weekdays div.is-today-weekday {
			background: linear-gradient(135deg, #8a6b1e, #c79f32, #e9d18a);
			-webkit-background-clip: text;
			background-clip: text;
			-webkit-text-fill-color: transparent;
			opacity: 1;
		}

		.prs-plan-card .prs-calendar-grid {
			display: grid;
			grid-template-columns: repeat(7, minmax(0, 1fr));
			gap: 6px;
		}

		.prs-plan-card .prs-day-cell {
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

		.prs-plan-card .prs-day-cell.is-today {
			border: 2px solid;
			border-image: linear-gradient(135deg, #8a6b1e, #c79f32, #e9d18a) 1;
		}

		.prs-plan-card .prs-day-number {
			position: absolute;
			top: 4px;
			left: 6px;
			font-size: 8px;
			font-weight: 700;
			opacity: 0.3;
		}

		.prs-plan-card .prs-day-empty {
			background: #e1e1e1;
			opacity: 0.2;
		}

		.prs-plan-card .prs-day-selected {
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

		.prs-plan-card .prs-day-selected.is-missed {
			background: #cfcfcf;
			color: #666666;
			box-shadow: none;
			cursor: default;
		}

		.prs-plan-card .prs-day-selected.is-accomplished {
			background: #000000;
			color: var(--prs-gold);
			box-shadow: none;
			cursor: default;
		}

		.prs-plan-card .prs-day-remove {
			position: absolute;
			top: 0;
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

		.prs-plan-card .prs-day-selected:hover .prs-day-remove,
		.prs-plan-card .prs-day-selected.is-remove-visible .prs-day-remove {
			opacity: 1;
			pointer-events: auto;
		}

		.prs-plan-card .prs-day-add {
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
			padding: 0;
			outline: none;
		}

		.prs-plan-card .prs-day-selected:active {
			cursor: grabbing;
		}

		.prs-plan-card .prs-day-cell.is-drag-over {
			background: #fffaf0;
			border: 2px dashed var(--prs-orange);
		}

		.prs-plan-card .prs-list-item {
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

		.prs-plan-card .prs-list-item:hover {
			border-color: var(--prs-gold);
		}

		.prs-plan-card .prs-list-badge {
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

		.prs-plan-card .prs-list-badge.is-missed {
			background: #cfcfcf;
			color: #666666;
		}

		.prs-plan-card .prs-list-badge.is-accomplished {
			background: #000000;
			color: var(--prs-gold);
		}

		.prs-plan-card .prs-list-title {
			font-size: 11px;
			font-weight: 700;
			text-transform: uppercase;
			color: var(--prs-black);
			margin-left: 12px;
		}

		.prs-plan-card .prs-list-date {
			font-size: 10px;
			font-weight: 700;
			color: #8f8f8f;
			text-transform: uppercase;
		}

		.prs-plan-card .opacity-0 {
			opacity: 0;
		}

	</style>
	<div id="single-plan-container" class="single-plan-container pcg-creator-container">
		<aside id="single-plan-sidebar" class="single-plan-sidebar pcg-creator-sidebar">
			<?php if ($plan_cover_url || $plan_cover_title) : ?>
				<div class="single-plan-cover-wrap">
					<?php if ($plan_cover_url) : ?>
						<img
							class="single-plan-cover-image"
							src="<?php echo esc_url($plan_cover_url); ?>"
							alt="<?php echo esc_attr($plan_cover_title ? $plan_cover_title : __('Plan book cover', 'politeia-reading')); ?>"
						/>
					<?php endif; ?>
					<?php if ($plan_cover_title) : ?>
						<h3 class="single-plan-cover-title"><?php echo esc_html($plan_cover_title); ?></h3>
					<?php endif; ?>
					<?php if ($plan_cover_author) : ?>
						<p class="single-plan-cover-author"><?php echo esc_html($plan_cover_author); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<nav class="single-plan-nav pcg-creator-nav">
				<ul class="single-plan-nav-list">
					<li class="single-plan-nav-item <?php echo 'calendar' === $current_section ? 'active' : ''; ?>">
						<a href="<?php echo esc_url(add_query_arg('section', 'calendar', $base_url)); ?>">
							<span class="dashicons dashicons-calendar-alt"></span>
							Calendar
						</a>
					</li>
					<li class="single-plan-nav-item <?php echo 'list' === $current_section ? 'active' : ''; ?>">
						<a href="<?php echo esc_url(add_query_arg('section', 'list', $base_url)); ?>">
							<span class="dashicons dashicons-list-view"></span>
							List
						</a>
					</li>
					<li class="single-plan-nav-item <?php echo 'notes' === $current_section ? 'active' : ''; ?>">
						<a href="<?php echo esc_url(add_query_arg('section', 'notes', $base_url)); ?>">
							<span class="dashicons dashicons-edit-page"></span>
							Notes
						</a>
					</li>
					<li class="single-plan-nav-item <?php echo 'settings' === $current_section ? 'active' : ''; ?>">
						<a href="<?php echo esc_url(add_query_arg('section', 'settings', $base_url)); ?>">
							<span class="dashicons dashicons-admin-generic"></span>
							Settings
						</a>
					</li>
				</ul>
			</nav>
		</aside>

		<main id="single-plan-content" class="single-plan-content pcg-creator-content">
			<div class="single-plan-section-container pcg-section-container">
				<div class="single-plan-sales-section pcg-sales-section">
					<div class="single-plan-topbar pcg-form-nav pcg-sales-nav">
						<div class="single-plan-topbar-inner pcg-sales-nav-inner">
							<div class="single-plan-topbar-left pcg-nav-left">
								<span class="single-plan-section-label pcg-current-course-label"><?php echo esc_html($current_title); ?></span>
							</div>
						</div>
					</div>

					<div class="single-plan-creator-section pcg-creator-section">
						<?php if (in_array($current_section, array('calendar', 'list'), true)) : ?>
							<div class="single-plan-dashboard pcg-sales-dashboard">
								<div
									class="prs-plan-card <?php echo 'calendar' === $current_section ? 'single-plan-calendar-card' : 'single-plan-list-card'; ?>"
									data-section-mode="<?php echo esc_attr($current_section); ?>"
									data-plan-id="<?php echo esc_attr((string) $plan_id); ?>"
									data-goal-kind="<?php echo esc_attr($plan_goal_kind); ?>"
									data-today-key="<?php echo esc_attr($today_key); ?>"
									data-initial-month="<?php echo esc_attr($current_month); ?>"
									data-session-label="<?php echo esc_attr__('Scheduled Session', 'politeia-reading'); ?>"
									data-day-format="<?php echo esc_attr__('Day %1$s of %2$s', 'politeia-reading'); ?>"
									data-month-label="<?php echo esc_attr($current_month_label); ?>"
									data-remove-label="<?php echo esc_attr__('Remove session', 'politeia-reading'); ?>"
									data-total-pages="0"
									data-pages-read="0"
									data-sessions-label="<?php echo esc_attr__('sessions', 'politeia-reading'); ?>"
									data-pages-label="<?php echo esc_attr__('pages', 'politeia-reading'); ?>"
									data-per-session-label="<?php echo esc_attr__('per session', 'politeia-reading'); ?>"
									data-missed-label="<?php echo esc_attr__('Missed', 'politeia-reading'); ?>"
									data-completed-label="<?php echo esc_attr__('Completed', 'politeia-reading'); ?>"
									data-session-dates="[]"
									data-session-items="[]"
									data-actual-sessions="[]"
									data-habit-duration="0"
									data-start-date=""
								>
									<div class="prs-calendar-card-container prs-collapsible no-chart is-open" data-role="collapsible">
										<div class="prs-calendar-card-left prs-calendar-card">
											<div class="prs-calendar-header">
												<div>
													<div class="prs-calendar-title-row">
														<h3 class="prs-calendar-title" data-role="calendar-title"><?php echo esc_html($current_month_label); ?></h3>
														<div class="prs-calendar-nav">
															<a href="#" class="prs-calendar-nav-btn" role="button" data-role="month-prev" aria-label="<?php esc_attr_e('Previous Month', 'politeia-reading'); ?>">
																<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6" /></svg>
															</a>
															<a href="#" class="prs-calendar-nav-btn" role="button" data-role="month-next" aria-label="<?php esc_attr_e('Next Month', 'politeia-reading'); ?>">
																<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6" /></svg>
															</a>
														</div>
													</div>
													<div class="prs-calendar-meta" data-role="calendar-meta"></div>
												</div>
												<div class="single-plan-upcoming-pages" data-role="upcoming-pages"></div>
											</div>

											<?php if ('calendar' === $current_section) : ?>
												<div class="prs-view" data-role="calendar-view">
													<div class="prs-weekdays">
														<div><?php esc_html_e('Mon', 'politeia-reading'); ?></div><div><?php esc_html_e('Tue', 'politeia-reading'); ?></div><div><?php esc_html_e('Wed', 'politeia-reading'); ?></div><div><?php esc_html_e('Thu', 'politeia-reading'); ?></div><div><?php esc_html_e('Fri', 'politeia-reading'); ?></div><div><?php esc_html_e('Sat', 'politeia-reading'); ?></div><div><?php esc_html_e('Sun', 'politeia-reading'); ?></div>
													</div>
													<div class="prs-calendar-grid" data-role="calendar-grid"></div>
												</div>
											<?php else : ?>
												<div class="prs-view" data-role="list-view">
													<div data-role="list"></div>
												</div>
											<?php endif; ?>
										</div>
									</div>
								</div>
							</div>
						<?php elseif ('settings' === $current_section) : ?>
							<div class="single-plan-dashboard pcg-sales-dashboard">
								<div class="single-plan-settings-card">
									<p class="single-plan-settings-row">
										<span class="single-plan-settings-key">Title</span>
										<span><?php echo esc_html($plan_cover_title ? $plan_cover_title : ''); ?></span>
									</p>
									<p class="single-plan-settings-row">
										<span class="single-plan-settings-key">Author</span>
										<span><?php echo esc_html($plan_cover_author ? $plan_cover_author : ''); ?></span>
									</p>
									<p class="single-plan-settings-row">
										<span class="single-plan-settings-key">Total Pages</span>
										<span><?php echo esc_html($plan_user_book_pages > 0 ? (string) $plan_user_book_pages : ''); ?></span>
									</p>
								</div>
							</div>
						<?php else : ?>
							<div class="single-plan-dashboard pcg-sales-dashboard"></div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</main>
	</div>
</div>

<script>
	(function () {
		var root = document.getElementById('single-plan-template');
		if (!root) return;
		var grid = root.closest('.bb-grid.site-content-grid');
		if (grid) {
			grid.classList.add('single-plan-shell-grid');
		}
		var container = root.closest('.container');
		if (container) {
			container.classList.add('single-plan-shell');
		}
	})();

	(function () {
		const card = document.querySelector('.prs-plan-card.single-plan-calendar-card, .prs-plan-card.single-plan-list-card');
		if (!card) {
			return;
		}

		const sectionMode = card.dataset.sectionMode || 'calendar';
		const grid = card.querySelector('[data-role="calendar-grid"]');
		const listContainer = card.querySelector('[data-role="list"]');
		const metaLabel = card.querySelector('[data-role="calendar-meta"]');
		const titleLabel = card.querySelector('[data-role="calendar-title"]');
		const upcomingPagesLabel = card.querySelector('[data-role="upcoming-pages"]');
		const btnPrevMonth = card.querySelector('[data-role="month-prev"]');
		const btnNextMonth = card.querySelector('[data-role="month-next"]');

		let totalPages = parseInt(card.dataset.totalPages, 10) || 0;
		let pagesRead = parseInt(card.dataset.pagesRead, 10) || 0;
		let goalKind = card.dataset.goalKind || '';
		const todayKey = card.dataset.todayKey || '';
		let sessionDates = [];
		let sessionItems = [];
		let actualSessions = [];
		let currentMonthKey = card.dataset.initialMonth || '';
		let minMonthKey = '';
		let maxMonthKey = '';

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

		const strings = {
			sessionLabel: card.dataset.sessionLabel || '',
			dayFormat: card.dataset.dayFormat || '',
			monthLabel: card.dataset.monthLabel || '',
			removeLabel: card.dataset.removeLabel || '',
			sessionsLabel: card.dataset.sessionsLabel || 'sessions',
			pagesLabel: card.dataset.pagesLabel || 'pages',
			perSessionLabel: card.dataset.perSessionLabel || 'per session',
			missedLabel: card.dataset.missedLabel || 'Missed',
			completedLabel: card.dataset.completedLabel || 'Completed',
		};

		const getStatusByDate = (dateStr) => {
			const item = sessionItems.find((entry) => entry.date === dateStr);
			return item && item.status ? item.status : 'planned';
		};

		const getMonthSessions = (monthKeyValue) => {
			return sessionDates.filter((dateStr) => dateStr.startsWith(monthKeyValue)).sort();
		};

		const getPlannedRangeByDate = (dateStr) => {
			const item = sessionItems.find((entry) => entry.date === dateStr);
			if (!item) return null;
			const start = typeof item.planned_start_page === 'number' ? item.planned_start_page : null;
			const end = typeof item.planned_end_page === 'number' ? item.planned_end_page : null;
			if (!start || !end || end < start) return null;
			return { start, end };
		};

		const getExpectedPagesForSession = (item) => {
			if (!item) return 0;
			if (goalKind === 'habit' && typeof item.expectedPages === 'number' && item.expectedPages > 0) {
				return item.expectedPages;
			}
			const range = getPlannedRangeByDate(item.date);
			if (range) {
				return Math.max(0, range.end - range.start + 1);
			}
			return 0;
		};

		const refreshMonthBounds = () => {
			if (!sessionDates.length) {
				if (!currentMonthKey) {
					currentMonthKey = monthKey(new Date());
				}
				minMonthKey = currentMonthKey;
				maxMonthKey = currentMonthKey;
				return;
			}

			const monthKeys = sessionDates.map((d) => d.slice(0, 7)).sort();
			minMonthKey = monthKeys[0];
			maxMonthKey = monthKeys[monthKeys.length - 1];
			if (!currentMonthKey) {
				currentMonthKey = minMonthKey;
			}
			if (compareMonthKey(currentMonthKey, minMonthKey) < 0) {
				currentMonthKey = minMonthKey;
			}
			if (compareMonthKey(currentMonthKey, maxMonthKey) > 0) {
				currentMonthKey = maxMonthKey;
			}
		};

		const updateMeta = () => {
			if (!metaLabel) return;
			const monthSessions = getMonthSessions(currentMonthKey);
			const sessionCount = monthSessions.length;
			metaLabel.textContent = `${sessionCount} ${strings.sessionsLabel}`;
		};

		const updateUpcomingPages = () => {
			if (!upcomingPagesLabel) return;
			const nextPlanned = sessionItems
				.filter((entry) => entry && entry.status === 'planned' && entry.date && entry.date >= (todayKey || ''))
				.sort((a, b) => String(a.date).localeCompare(String(b.date)))[0] || null;
			const expectedPages = getExpectedPagesForSession(nextPlanned);
			const valueLabel = expectedPages > 0 ? `${expectedPages} ${strings.pagesLabel}` : `0 ${strings.pagesLabel}`;
			upcomingPagesLabel.innerHTML = `${valueLabel}<span class="single-plan-upcoming-sub">${strings.perSessionLabel}</span>`;
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

		const fetchPlanDetails = () => {
			const planId = card.dataset.planId;
			if (!planId) return Promise.resolve();

			const timestamp = new Date().getTime();
			return fetch(`/wp-json/politeia/v1/reading-plan/${planId}?t=${timestamp}`, {
				headers: {
					'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
				}
			})
				.then((res) => res.json())
				.then((data) => {
					if (!data || !data.success) return;
					totalPages = parseInt(data.total_pages, 10) || 0;
					pagesRead = parseInt(data.pages_read, 10) || 0;
					sessionDates = Array.isArray(data.session_dates) ? data.session_dates : [];
					sessionItems = Array.isArray(data.session_items) ? data.session_items : [];
					actualSessions = Array.isArray(data.actual_sessions) ? data.actual_sessions : [];
					if (data.goal_kind) {
						goalKind = String(data.goal_kind);
					}
					refreshMonthBounds();
					updateUpcomingPages();
				})
				.catch(() => {});
		};

		const renderCalendar = () => {
			if (!grid) return;

			refreshMonthBounds();
			const viewDate = parseMonthKey(currentMonthKey);
			const daysCount = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0).getDate();
			const startOffset = (new Date(viewDate.getFullYear(), viewDate.getMonth(), 1).getDay() + 6) % 7;
			grid.innerHTML = '';
			const monthSessions = getMonthSessions(currentMonthKey);
			const sortedDays = monthSessions.map((dateStr) => parseInt(dateStr.split('-')[2], 10));

			const nonMissedDates = sessionDates.filter((dateStr) => getStatusByDate(dateStr) !== 'missed').sort();
			const orderByDate = new Map();
			nonMissedDates.forEach((dateStr, index) => {
				orderByDate.set(dateStr, index + 1);
			});

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
					const isMissed = status === 'missed';
					const isAccomplished = status === 'accomplished';
					const isLocked = status !== 'planned';
					const order = goalKind === 'complete_books'
						? (orderByDate.get(targetDate) || sortedDays.indexOf(day) + 1)
						: (sortedDays.indexOf(day) + 1);

					const mark = document.createElement('div');
					mark.className = `prs-day-selected${isMissed ? ' is-missed' : ''}${isAccomplished ? ' is-accomplished' : ''}`;
					mark.textContent = isMissed ? '' : String(order);
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
								.then((res) => res.json())
								.then((data) => {
									if (data && data.success) {
										fetchPlanDetails().then(() => renderCalendar());
									}
								});
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
									.then((res) => res.json())
									.then((data) => {
										if (data && (data.success || data.session)) {
											fetchPlanDetails().then(() => renderCalendar());
											addBtn.remove();
										}
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
						fetch(`/wp-json/politeia/v1/reading-plan/${planId}/session/${originDate}`, {
							method: 'PUT',
							headers: {
								'Content-Type': 'application/json',
								'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
							},
							body: JSON.stringify({ new_date: targetDate })
						})
							.then((res) => res.json())
							.then((data) => {
								if (data && data.success) {
									fetchPlanDetails().then(() => renderCalendar());
								}
							});
					}
				});

				grid.appendChild(cell);
			}

			if (todayKey && todayKey.startsWith(currentMonthKey)) {
				const todayDay = parseInt(todayKey.split('-')[2], 10);
				const todayDate = new Date(viewDate.getFullYear(), viewDate.getMonth(), todayDay);
				const todayWeekday = (todayDate.getDay() + 6) % 7;
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
			if (!listContainer) return;

			refreshMonthBounds();
			updateMeta();
			updateTitle();
			updateNavState();

			listContainer.innerHTML = '';
			const monthSessions = getMonthSessions(currentMonthKey);
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
					if (!sessionsByDate.has(entry.date)) sessionsByDate.set(entry.date, []);
					sessionsByDate.get(entry.date).push(entry);
				});

			const remainingPages = Math.max(0, totalPages - pagesRead);
			const remainingSessionsCount = sessionDates.filter((dateStr) => {
				const status = getStatusByDate(dateStr);
				return status === 'planned' && dateStr >= (todayKey || '');
			}).length;
			const fallbackPagesPerSession = remainingSessionsCount > 0 ? Math.ceil(remainingPages / remainingSessionsCount) : 0;

			const entries = [];
			monthSessions.forEach((dateKey) => {
				const status = getStatusByDate(dateKey);
				const actualList = sessionsByDate.get(dateKey) || [];
				if (status === 'accomplished' && actualList.length) {
					actualList.forEach((entry) => {
						entries.push({ type: 'accomplished', dateKey: entry.date, range: { start: entry.start, end: entry.end } });
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
			entries.forEach((entry) => {
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
					let plannedPages = fallbackPagesPerSession;
					if (goalKind === 'complete_books') {
						const range = getPlannedRangeByDate(dateKey);
						if (range) {
							plannedPages = Math.max(0, (range.end - range.start + 1));
						}
					}
					title.textContent = plannedPages > 0 ? `${plannedPages} ${strings.pagesLabel}` : strings.sessionLabel;
				} else if (status === 'accomplished') {
					const range = entry.range;
					if (range && range.start && range.end) {
						title.textContent = `${range.start}-${range.end} · ${strings.completedLabel}`;
					} else {
						title.textContent = `${strings.sessionLabel} · ${strings.completedLabel}`;
					}
				} else {
					title.textContent = `${strings.missedLabel}`;
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

		const renderCurrentSection = () => {
			updateUpcomingPages();
			if (sectionMode === 'list') {
				renderList();
			} else {
				renderCalendar();
			}
		};

		if (btnPrevMonth) {
			btnPrevMonth.addEventListener('click', (event) => {
				event.preventDefault();
				if (compareMonthKey(currentMonthKey, minMonthKey) <= 0) return;
				const date = parseMonthKey(currentMonthKey);
				date.setMonth(date.getMonth() - 1);
				currentMonthKey = monthKey(date);
				renderCurrentSection();
			});
		}

		if (btnNextMonth) {
			btnNextMonth.addEventListener('click', (event) => {
				event.preventDefault();
				if (compareMonthKey(currentMonthKey, maxMonthKey) >= 0) return;
				const date = parseMonthKey(currentMonthKey);
				date.setMonth(date.getMonth() + 1);
				currentMonthKey = monthKey(date);
				renderCurrentSection();
			});
		}

		fetchPlanDetails().finally(() => {
			renderCurrentSection();
		});
	})();
</script>

<?php
get_footer();
