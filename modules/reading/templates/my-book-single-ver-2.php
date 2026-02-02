<?php
// Template ver-2
if (!defined('ABSPATH')) {
	exit;
}
get_header();

if (!is_user_logged_in()) {
	echo '<div class="wrap"><p>' . esc_html__('You must be logged in.', 'politeia-reading') . '</p></div>';
	get_footer();
	exit;
}

global $wpdb;
$user_id = get_current_user_id();
$slug = get_query_var('prs_book_slug');

$tbl_b = $wpdb->prefix . 'politeia_books';
$tbl_ub = $wpdb->prefix . 'politeia_user_books';
$tbl_loans = $wpdb->prefix . 'politeia_loans';
$tbl_sessions = $wpdb->prefix . 'politeia_reading_sessions';
$tbl_session_notes = $wpdb->prefix . 'politeia_read_ses_notes';
$tbl_authors = $wpdb->prefix . 'politeia_authors';
$tbl_book_authors = $wpdb->prefix . 'politeia_book_authors';

$book_id = prs_get_book_id_by_primary_slug($slug);
if (!$book_id) {
	$book_id = prs_get_book_id_by_slug($slug);
}
$book = null;
if ($book_id) {
	$book = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT b.*,
					(
						SELECT GROUP_CONCAT(a.display_name ORDER BY ba.sort_order ASC SEPARATOR ', ')
						FROM {$tbl_book_authors} ba
						LEFT JOIN {$tbl_authors} a ON a.id = ba.author_id
						WHERE ba.book_id = b.id
					) AS authors
			 FROM {$tbl_b} b
			 WHERE b.id=%d
			 LIMIT 1",
			$book_id
		)
	);
}
if (!$book) {
	status_header(404);
	echo '<div class="wrap"><h1>' . esc_html__('Not found', 'politeia-reading') . '</h1></div>';
	get_footer();
	exit;
}

$primary_slug = prs_get_primary_slug_for_book((int) $book->id);
if ($primary_slug && ($slug !== $primary_slug)) {
	wp_safe_redirect(home_url('/my-books/my-book-' . $primary_slug . '/'), 301);
	exit;
}

$ub = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT * FROM {$tbl_ub} WHERE user_id=%d AND book_id=%d AND deleted_at IS NULL LIMIT 1",
		$user_id,
		$book->id
	)
);
if (!$ub) {
	status_header(403);
	echo '<div class="wrap"><h1>' . esc_html__('No access', 'politeia-reading') . '</h1><p>' . esc_html__('This book is not in your library.', 'politeia-reading') . '</p></div>';
	get_footer();
	exit;
}

/** Contact info (define before localize) */
$has_contact = (!empty($ub->counterparty_name)) || (!empty($ub->counterparty_email));
$contact_name = $ub->counterparty_name ? (string) $ub->counterparty_name : '';
$contact_email = $ub->counterparty_email ? (string) $ub->counterparty_email : '';

$label_borrowing = __('Borrowing to:', 'politeia-reading');
$label_borrowed = __('Borrowed from:', 'politeia-reading');
$label_sold = __('Sold to:', 'politeia-reading');
$label_lost = __('Last borrowed to:', 'politeia-reading');
$label_sold_on = __('Sold on:', 'politeia-reading');
$label_lost_date = __('Lost:', 'politeia-reading');
$label_unknown = __('Unknown', 'politeia-reading');

$owning_message = '';
if ($contact_name) {
	switch ((string) $ub->owning_status) {
		case 'borrowing':
			$owning_message = sprintf('%s %s', $label_borrowing, $contact_name);
			break;
		case 'borrowed':
			$owning_message = sprintf('%s %s', $label_borrowed, $contact_name);
			break;
		case 'sold':
			$owning_message = sprintf('%s %s', $label_sold, $contact_name);
			break;
		case 'lost':
			$owning_message = sprintf('%s %s', $label_lost, $contact_name);
			break;
	}
}

/** Active loan (local date) */
$active_start_gmt = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT start_date FROM {$tbl_loans}
   WHERE user_id=%d AND book_id=%d AND end_date IS NULL AND deleted_at IS NULL
   ORDER BY id DESC LIMIT 1",
		$user_id,
		$book->id
	)
);
$active_start_local = $active_start_gmt ? get_date_from_gmt($active_start_gmt, 'Y-m-d') : '';

$sessions = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT s.*, n.note 
		 FROM {$tbl_sessions} s 
		 LEFT JOIN {$tbl_session_notes} n ON s.id = n.rs_id AND n.user_id = s.user_id 
		 WHERE s.user_id = %d 
		   AND s.user_book_id = %d 
		   AND s.deleted_at IS NULL 
		 ORDER BY s.start_time DESC",
		$user_id,
		$ub->id
	)
);

$current_type = (isset($ub->type_book) && in_array($ub->type_book, array('p', 'd'), true)) ? $ub->type_book : '';

$user_cover_raw = '';
if (isset($ub->cover_reference) && '' !== $ub->cover_reference && null !== $ub->cover_reference) {
	$user_cover_raw = $ub->cover_reference;
} elseif (isset($ub->cover_attachment_id_user)) {
	$user_cover_raw = $ub->cover_attachment_id_user;
}
$parsed_user_cover = method_exists('PRS_Cover_Upload_Feature', 'parse_cover_value') ? PRS_Cover_Upload_Feature::parse_cover_value($user_cover_raw) : array(
	'attachment_id' => is_numeric($user_cover_raw) ? (int) $user_cover_raw : 0,
	'url' => '',
	'source' => '',
);
$user_cover_id = isset($parsed_user_cover['attachment_id']) ? (int) $parsed_user_cover['attachment_id'] : 0;
$canon_cover_id = isset($book->cover_attachment_id) ? (int) $book->cover_attachment_id : 0;
$user_cover_url = isset($parsed_user_cover['url']) ? trim((string) $parsed_user_cover['url']) : '';
$user_cover_url = $user_cover_url ? esc_url_raw($user_cover_url) : '';
$user_cover_source = isset($parsed_user_cover['source']) ? trim((string) $parsed_user_cover['source']) : '';
$attachment_source = $user_cover_id ? get_post_meta($user_cover_id, '_prs_cover_source', true) : '';
if ($attachment_source) {
	$user_cover_source = (string) $attachment_source;
}
$user_cover_source = $user_cover_source ? esc_url_raw($user_cover_source) : '';
$book_cover_url = isset($book->cover_url) ? trim((string) $book->cover_url) : '';
$book_cover_source = $book_cover_url ? trim(isset($book->cover_source) ? (string) $book->cover_source : '') : '';
$book_cover_source = $book_cover_source ? esc_url_raw($book_cover_source) : '';
$cover_url = '';
$cover_source = '';
$final_cover_id = 0;
$force_http_covers = function_exists('politeia_bookshelf_force_http_covers') ? politeia_bookshelf_force_http_covers() : false;
$cover_scheme = $force_http_covers ? 'http' : (is_ssl() ? 'https' : 'http');

if ($user_cover_url) {
	$cover_url = $user_cover_url;
	$cover_source = $user_cover_source;
} else {
	$final_cover_id = $user_cover_id ?: $canon_cover_id;
	if (0 === $final_cover_id && $book_cover_url) {
		$cover_url = $book_cover_url;
		$cover_source = $book_cover_source;
	}
}

$has_image = ($final_cover_id > 0) || '' !== $cover_url;

$placeholder_title = __('Untitled Book', 'politeia-reading');
$placeholder_author = __('Unknown Author', 'politeia-reading');
$placeholder_label = __('Default book cover', 'politeia-reading');
$search_cover_label = __('Search Cover', 'politeia-reading');
$remove_cover_label = __('Remove book cover', 'politeia-reading');
$remove_cover_confirm = __('Are you sure you want to remove this book cover?', 'politeia-reading');
$book_authors = isset($book->authors) ? trim((string) $book->authors) : '';
$book_isbn = isset($book->isbn) ? trim((string) $book->isbn) : '';

$total_pages = (isset($ub->pages) && $ub->pages) ? (int) $ub->pages : 0;
$progress_percent = 0;
$density_sessions = array();
if ($total_pages > 0 && !empty($sessions)) {
	$pages_read_total = 0;
	foreach ($sessions as $session) {
		if (!isset($session->start_page, $session->end_page)) {
			continue;
		}
		$start = (int) $session->start_page;
		$end = (int) $session->end_page;
		if ($end < $start) {
			continue;
		}
		$end = min($total_pages, $end);
		$start = min($total_pages, $start);
		$pages_read_total += max(0, $end - $start);

		$density_sessions[] = array(
			'start_page' => (int) $session->start_page,
			'end_page' => (int) $session->end_page,
		);
	}

	if ($pages_read_total > 0) {
		$progress_percent = (int) round(($pages_read_total / $total_pages) * 100);
	}
}
$progress_percent = max(0, min(100, $progress_percent));

/** Enqueue assets */
wp_enqueue_style('politeia-reading');
wp_enqueue_style(
	'politeia-reading-layout',
	POLITEIA_READING_URL . 'assets/css/politeia-reading.css',
	array('politeia-reading'),
	POLITEIA_READING_VERSION
);
wp_enqueue_style(
	'politeia-material-symbols',
	'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200',
	array(),
	null
);
wp_enqueue_script('politeia-my-book');

/** Data for main JS */
$owning_nonce = wp_create_nonce('save_owning_contact');
$meta_update_nonce = wp_create_nonce('prs_update_user_book_meta');
$cover_actions_nonce = wp_create_nonce('politeia_bookshelf_cover_actions');
$reading_nonce = wp_create_nonce('prs_reading_nonce');
wp_localize_script(
	'politeia-my-book',
	'PRS_BOOK',
	array(
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce' => $meta_update_nonce,
		'reading_nonce' => $reading_nonce,
		'owning_nonce' => $owning_nonce,
		'user_book_id' => (int) $ub->id,
		'book_id' => (int) $book->id,
		'owning_status' => (string) $ub->owning_status,
		'has_contact' => $has_contact ? 1 : 0,
		'rating' => isset($ub->rating) && $ub->rating !== null ? (int) $ub->rating : 0,
		'type_book' => (string) $current_type,
		'title' => (string) $book->title,
		'authors' => $book_authors,
		'cover_url' => $cover_url,
		'force_http_covers' => $force_http_covers ? 1 : 0,
		'cover_nonce' => $cover_actions_nonce,
		'user_id' => (int) $user_id,
		'language' => isset($book->language) ? (string) $book->language : '',
		'cover_source' => $cover_source,
		'purchase_channel_labels' => array(
			'online' => __('Online', 'politeia-reading'),
			'store' => __('Store', 'politeia-reading'),
		),
		'strings' => array(
			'note_required' => __('Please write a note before saving.', 'politeia-reading'),
			'note_missing_details' => __('Missing session details. Please try again.', 'politeia-reading'),
			'note_unavailable' => __('Unable to save the note right now. Please refresh the page and try again.', 'politeia-reading'),
			'note_missing_nonce' => __('Unable to save the note because the session security token is missing. Please refresh the page and try again.', 'politeia-reading'),
			'note_saved' => __('✅ Note saved successfully!', 'politeia-reading'),
			'note_save_failed_prefix' => __('⚠️ Failed to save note: %s', 'politeia-reading'),
			'note_ajax_failed' => __('❌ AJAX request failed — check console.', 'politeia-reading'),
			'note_missing_session_id' => __('Unable to load this session note because the session identifier is missing.', 'politeia-reading'),
			'unknown_error' => __('Unknown error', 'politeia-reading'),
			'press_enter_to_save' => __('Press Enter to save', 'politeia-reading'),
			'pages_error' => __('Error saving pages.', 'politeia-reading'),
			'pages_too_small' => __('Please enter a number greater than zero.', 'politeia-reading'),
			'pages_saved' => __('Saved!', 'politeia-reading'),
			'isbn_error' => __('Error saving ISBN.', 'politeia-reading'),
			'isbn_invalid' => __('Invalid ISBN.', 'politeia-reading'),
			'saved_short' => __('Saved.', 'politeia-reading'),
			'status_saving' => __('Saving...', 'politeia-reading'),
			'missing_contact' => __('Please enter both name and email.', 'politeia-reading'),
			'borrower_buying_title' => __('Borrowed person is buying this book:', 'politeia-reading'),
			'borrower_buying_confirm' => __('Confirm that the borrower is purchasing or compensating for the book.', 'politeia-reading'),
			'error_saving_contact' => __('Error saving contact.', 'politeia-reading'),
			'error_saving_date' => __('Error saving date.', 'politeia-reading'),
			'error_saving_channel' => __('Error saving channel.', 'politeia-reading'),
			'error_saving_rating' => __('Error saving rating.', 'politeia-reading'),
			'error_saving_format' => __('Error saving format.', 'politeia-reading'),
			'saved_successfully' => __('Saved successfully.', 'politeia-reading'),
			'disabled_lost' => __('Disabled while this book is lost.', 'politeia-reading'),
			'disabled_borrowed' => __('Disabled while this book is being borrowed.', 'politeia-reading'),
			'filter_all' => __('All', 'politeia-reading'),
			'selected_count' => __('%d selected', 'politeia-reading'),
			'channel_online' => __('Online', 'politeia-reading'),
			'channel_store' => __('Store', 'politeia-reading'),
			'remove_book_confirm' => __('Are you sure you want to remove this book from your library?', 'politeia-reading'),
			'remove_book_removing' => __('Removing...', 'politeia-reading'),
			'remove_book_error' => __('Error removing book.', 'politeia-reading'),
			'images_from_google' => __('Images from Google Books', 'politeia-reading'),
			'no_covers_found' => __('No covers found.', 'politeia-reading'),
			'cover_save_failed' => __('Unable to save cover.', 'politeia-reading'),
			'error_owning_status' => __('Error updating owning status.', 'politeia-reading'),
			'label_borrowing' => __('Borrowing to:', 'politeia-reading'),
			'label_borrowed' => __('Borrowed from:', 'politeia-reading'),
			'label_sold' => __('Sold to:', 'politeia-reading'),
			'label_lost' => __('Last borrowed to:', 'politeia-reading'),
			'label_sold_on' => __('Sold on:', 'politeia-reading'),
			'label_lost_date' => __('Lost:', 'politeia-reading'),
			'label_location' => __('Location', 'politeia-reading'),
			'label_in_shelf' => __('In Shelf', 'politeia-reading'),
			'label_not_in_shelf' => __('Not In Shelf', 'politeia-reading'),
			'label_unknown' => __('Unknown', 'politeia-reading'),
		),
	)
);
wp_add_inline_script(
	'politeia-my-book',
	sprintf(
		'window.PRS_BOOK_ID=%1$d;window.PRS_USER_BOOK_ID=%2$d;window.PRS_NONCE=%3$s;',
		(int) $book->id,
		(int) $ub->id,
		wp_json_encode($owning_nonce)
	),
	'before'
);
?>
<style>
	* {
		box-sizing: border-box;
	}

	body {
		margin: 0;
		font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
		background: #f6f7f9;
		color: #1f2937;
	}

	.prs-page-wrap {
		padding: 0 16px 32px;
	}

	.page {
		max-width: 1200px;
		margin: 20px auto;
		display: grid;
		grid-template-columns: 260px 1fr;
		gap: 24px;
	}

	.sidebar {
		background: #fff;
		border-radius: 12px;
		box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
	}

	.sidebar {
		background: transparent;
		border-radius: 0;
		box-shadow: none;
		padding: 0;
		display: flex;
		flex-direction: column;
		gap: 20px;
	}


	.prs-sidebar-block {
		background: #fff;
		border-radius: 12px;
		box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
		padding: 16px;
	}

	.prs-book-identity-slot {
		display: none;
	}

	#prs-cover-progress {
		display: flex;
		flex-direction: column;
		gap: 20px;
	}

	.content {
		background: transparent;
		border-radius: 0;
		box-shadow: none;
		display: flex;
		flex-direction: column;
		gap: 20px;
	}

	.prs-content-card {
		background: #fff;
		border-radius: 12px;
		box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
		padding: 24px;
	}

	.cover-frame {
		position: relative;
		width: 100%;
		height: 360px;
		background: #e5e7eb;
		border-radius: 8px;
		display: flex;
		align-items: center;
		justify-content: center;
		overflow: hidden;
	}

	.cover-frame figure {
		margin: 0;
		width: 100%;
		height: 100%;
	}

	.cover-frame img {
		width: 100%;
		height: 100%;
		object-fit: cover;
		display: block;
	}

	.prs-cover-placeholder {
		height: 100%;
		width: 100%;
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;
		gap: 6px;
		color: #6b7280;
		font-weight: 600;
		padding: 16px;
		text-align: center;
	}

	.prs-cover-author {
		font-size: 12px;
		color: #000000;
		line-height: 1.2;
	}

	.prs-owning-status-note {
		max-width: 180px;
		line-height: 1.4;
	}

	.prs-cover-overlay {
		position: absolute;
		inset: 0;
		display: flex;
		align-items: center;
		justify-content: center;
		background: rgba(0, 0, 0, 0.4);
		opacity: 0;
		pointer-events: none;
		transition: opacity 0.2s ease;
	}

	.prs-cover-overlay .prs-cover-actions {
		opacity: 1;
		transform: none;
	}

	.prs-cover-overlay .prs-cover-actions .prs-cover-btn {
		margin-top: 0;
	}

	.cover-frame:hover .prs-cover-overlay,
	.cover-frame:focus-within .prs-cover-overlay {
		opacity: 1;
		pointer-events: auto;
	}

	.cover-frame[data-cover-state="image"] .prs-cover-remove {
		display: inline-block;
	}

	.progress-bar {
		height: 8px;
		background: #e5e7eb;
		border-radius: 999px;
		overflow: hidden;
		margin-bottom: 6px;
	}

	.progress-bar canvas {
		display: block;
		width: 100%;
		height: 100%;
	}

	.prs-progress-text {
		font-size: 12px;
		color: #6b7280;
	}

	.prs-other-readers {
		display: grid;
		grid-template-columns: repeat(4, 1fr);
		gap: 6px;
	}

	.prs-other-reader-avatar {
		width: 100%;
		padding-top: 100%;
		background: #e5e7eb;
		border-radius: 50%;
		transform: scale(0.85);
	}

	.prs-details {
		list-style: none;
		padding: 0;
		margin: 0;
		font-size: 13px;
		color: #374151;
		display: grid;
		gap: 6px;
	}

	.prs-details li strong {
		font-weight: 600;
	}

	#book-details-section h4 {
		font-size: 18px;
		color: #000;
	}

	.prs-detail-divider {
		list-style: none;
		padding: 0;
		margin: 0;
	}

	.prs-detail-divider hr {
		border: none;
		border-top: 1px solid #e5e7eb;
		height: 1px;
		margin: 0;
	}

	.header {
		display: flex;
		gap: 20px;
		align-items: flex-start;
	}

	#book-identity {
		width: 60%;
	}

	#owning-status-summary {
		width: 40%;
		background: #f3f3f3;
		border-radius: 8px;
		padding: 12px;
		color: #374151;
		font-size: 14px;
	}

	#book-title-row h1 {
		margin: 0;
		font-size: 28px;
		line-height: 1;
	}

	#book-author {
		margin-bottom: 5px;
	}

	.header p {
		margin: 4px 0 0;
		color: #6b7280;
	}

	.prs-stars {
		display: inline-flex;
		gap: 4px;
		align-items: center;
		margin-top: 6px;
	}

	.prs-star {
		border: none;
		background: none;
		font-size: 18px;
		color: #d1d5db;
		cursor: pointer;
		padding: 0;
		line-height: 1;
	}

	.prs-star.is-active {
		color: #f59e0b;
	}

	.prs-reading-status-row {
		display: flex;
		align-items: center;
		gap: 12px;
		margin-top: 10px;
		font-size: 14px;
		color: #374151;
	}

	.prs-reading-status-row label {
		font-weight: 600;
	}

	#fld-reading-status label {
		color: var(--bb-headings-color);
		margin-bottom: .25rem;
		font-size: 15px;
		line-height: 1;
	}

	.prs-reading-status-row select {
		min-height: 32px;
	}

	.tabs {
		display: flex;
		border-bottom: 1px solid #e5e7eb;
		overflow-x: auto;
	}

	.prs-sessions-mobile {
		display: none;
	}

	.prs-sessions-mobile__card {
		background: #fff;
		border: 1px solid #d1d5db;
		border-radius: 6px;
		padding: 16px;
		text-align: center;
	}

	.prs-sessions-mobile__card+.prs-sessions-mobile__card {
		margin-top: 16px;
	}

	.prs-sessions-mobile__title {
		margin: 0 0 8px;
		font-size: 18px;
		font-weight: 600;
	}

	h4.prs-sessions-mobile__title {
		margin-bottom: 0;
	}

	.prs-sessions-mobile__date {
		font-size: 10px;
		color: #6b7280;
		margin-bottom: 8px;
	}

	.prs-sessions-mobile__times {
		display: flex;
		justify-content: center;
		gap: 40px;
		padding: 8px 0;
		border-top: 1px solid #e5e7eb;
		border-bottom: 1px solid #e5e7eb;
		font-weight: 600;
	}

	.prs-sessions-mobile__pages {
		padding: 8px 0;
		border-bottom: 1px solid #e5e7eb;
		color: #111827;
	}

	.prs-sessions-mobile__pages div {
		margin: 4px 0;
	}

	.prs-sessions-mobile__duration {
		padding-top: 8px;
		font-weight: 600;
	}

	.tab {
		flex: 1 1 0;
		padding: 14px 16px;
		cursor: pointer;
		font-weight: 600;
		font-size: 13px;
		color: #6b7280;
		border-bottom: 2px solid transparent;
		background: transparent;
		border: none;
		border-radius: 0;
		transition: color 0.2s ease, background 0.2s ease, border-color 0.2s ease;
	}

	.tab.active {
		color: #f7f0da;
		border-color: #C79F32;
		background: #000000;
	}

	.tab:hover {
		color: #000000;
		border-color: #C79F32;
		background: #f7f0da;
	}

	.prs-tab-content {
		display: none;
	}

	.prs-tab-content.is-active {
		display: block;
	}

	.prs-play-icon {
		font-size: 28px;
		vertical-align: middle;
		line-height: 1;
		margin-right: 4px;
		font-variation-settings: "FILL" 1, "wght" 600, "opsz" 24;
	}

	.prs-btn:not(.prs-btn--ghost) {
		background-color: #000000;
		color: #fff;
		border-radius: 6px;
		font-size: 16px;
		max-width: 250px;
		margin: auto;
	}

	.prs-sr-table #prs-sr-row-timer,
	.prs-sr-table #prs-sr-row-actions {
		border: none;
	}

	.prs-sr-table #prs-sr-row-timer td,
	.prs-sr-table #prs-sr-row-actions td {
		border: none !important;
		background: transparent;
		box-shadow: none;
	}

	.prs-book-stats-grid {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 16px;
	}

	.prs-book-stats-card {
		background: #f9fafb;
		border: 1px solid #e5e7eb;
		border-radius: 10px;
		padding: 16px;
		font-size: 14px;
		color: #374151;
		min-height: 100px;
	}

	table {
		width: 100%;
		border-collapse: collapse;
		font-size: 14px;
	}

	thead th {
		text-align: left;
		padding: 10px 8px;
		border-bottom: 1px solid #e5e7eb;
		color: #6b7280;
		font-weight: 500;
	}

	tbody td {
		padding: 10px 8px;
		border-bottom: 1px solid #f1f5f9;
	}

	.prs-pagination ul.page-numbers {
		display: flex;
		gap: 6px;
		list-style: none;
		justify-content: flex-end;
		padding: 0;
		margin: 16px 0 0;
	}

	.prs-pagination .page-numbers {
		padding: 6px 10px;
		background: #f1f5f9;
		border-radius: 6px;
		text-decoration: none;
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

	#book-title-row {
		display: flex;
		align-items: center;
		gap: 12px;
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
		z-index: 2;
	}

	.prs-session-modal__close:hover,
	.prs-session-modal__close:focus,
	.prs-session-modal__close:focus-visible,
	.prs-session-modal__close:active {
		background: none;
		box-shadow: none;
		color: #ffffff;
	}

	.prs-search-cover-overlay {
		position: fixed;
		inset: 0;
		background-color: rgba(0, 0, 0, 0.6);
		display: flex;
		justify-content: center;
		align-items: center;
		z-index: 1000;
	}

	.prs-search-cover-overlay.is-hidden {
		display: none;
	}

	.prs-search-cover-modal {
		background: #fff;
		padding: 30px;
		border-radius: 8px;
		width: 80%;
		max-width: 800px;
		text-align: center;
	}

	.prs-search-cover-title {
		font-size: 20px;
		font-weight: 600;
		margin-bottom: 20px;
	}

	.prs-search-cover-options {
		display: flex;
		flex-wrap: wrap;
		gap: 20px;
		justify-content: center;
		margin-bottom: 12px;
	}

	.prs-cover-option {
		flex: 1 1 160px;
		max-width: 220px;
		border: 1px solid #ccc;
		border-radius: 8px;
		padding: 12px;
		cursor: pointer;
		user-select: none;
		display: flex;
		justify-content: center;
		align-items: center;
		background-color: #fff;
	}

	.prs-cover-option.selected {
		border-color: #000;
		background-color: #f0f0f0;
	}

	.prs-cover-image {
		max-height: 200px;
		width: auto;
		max-width: 100%;
		object-fit: contain;
	}

	.prs-search-cover-attribution {
		font-size: 12px;
		color: #555;
		text-align: center;
		margin: 12px 0 0;
	}

	.prs-search-cover-actions {
		display: flex;
		justify-content: center;
		gap: 16px;
		margin-top: 20px;
	}

	.prs-btn {
		padding: 10px 14px;
		background: #111;
		color: #fff;
		border: none;
		font-size: 12px;
		cursor: pointer;
		box-shadow: none;
		outline: none;
		border-radius: 6px;
	}

	.prs-cancel-cover-button {
		background-color: #000;
		color: #fff;
	}

	.prs-cancel-cover-button:hover {
		background-color: #222;
	}

	#purchase-date-form,
	#purchase-channel-form {
		display: block;
	}

	#purchase-date-form input[type="date"],
	#purchase-channel-form select,
	#purchase-channel-form input[type="text"] {
		width: 100%;
		padding: 8px 10px;
		font-size: 13px;
		line-height: 1.2;
		border-radius: 6px;
		margin-bottom: 8px;
	}

	#purchase-date-form .prs-btn,
	#purchase-channel-form .prs-btn {
		width: 100%;
		margin-top: 8px;
		padding: 8px 12px;
		font-size: 12px;
		background: #000;
		color: #fff;
	}

	#purchase-date-form .prs-btn:hover,
	#purchase-date-form .prs-btn:focus-visible,
	#purchase-channel-form .prs-btn:hover,
	#purchase-channel-form .prs-btn:focus-visible {
		background: #C79F32;
		color: #fff;
	}

	@media (max-width: 980px) {
		.page {
			grid-template-columns: 1fr;
			width: 100%;
		}

		.sidebar,
		.prs-sidebar-block {
			width: 100%;
			min-width: 0;
		}

		.content,
		.prs-content-card {
			width: 100%;
			max-width: 100%;
			min-width: 0;
		}

		#book-cover-section {
			display: flex;
			align-items: flex-start;
			gap: 16px;
		}

		.prs-cover-wrap {
			flex: 0 0 140px;
		}

		#prs-cover-frame {
			width: 140px;
			height: auto;
			aspect-ratio: 2 / 3;
		}

		#prs-cover-frame .prs-cover-img {
			object-fit: contain;
		}

		.prs-book-identity-slot {
			display: block;
			flex: 1 1 auto;
			min-width: 0;
		}

		#book-identity,
		#owning-status-summary {
			width: 100%;
		}

		.header {
			flex-direction: column;
		}

		.prs-book-stats-grid {
			grid-template-columns: 1fr;
		}
	}

	@media (max-width: 690px) {
		.prs-sessions-table thead {
			display: none;
		}

		.prs-sessions-table {
			display: none;
		}

		.prs-sessions-mobile {
			display: block;
		}
	}

	.prs-layout-placeholder {
		display: none;
	}

	@media (min-width: 980px) {
		#prs-cover-frame {
			width: 100% !important;
			height: auto !important;
		}

		#prs-cover-frame:not(.has-image)[data-cover-state="empty"]>#prs-book-cover-figure,
		#prs-cover-frame:not(.has-image):not([data-cover-state])>#prs-book-cover-figure {
			height: 100%;
		}
	}

	@media screen and (max-width: 950px) {

		#prs-cover-frame,
		.prs-book-cover {
			height: auto !important;
		}
	}
</style>

<div class="prs-page-wrap">
	<div class="page">
		<aside class="sidebar">
			<section id="prs-cover-progress" class="prs-sidebar-block">
				<section id="book-cover-section">
					<div class="prs-cover-wrap">
						<div id="prs-cover-frame" class="cover-frame <?php echo $has_image ? 'has-image' : ''; ?>"
							data-cover-state="<?php echo $has_image ? 'image' : 'empty'; ?>"
							data-placeholder-title="<?php echo esc_attr($placeholder_title); ?>"
							data-placeholder-author="<?php echo esc_attr($placeholder_author); ?>"
							data-placeholder-label="<?php echo esc_attr($placeholder_label); ?>"
							data-search-label="<?php echo esc_attr($search_cover_label); ?>"
							data-remove-label="<?php echo esc_attr($remove_cover_label); ?>"
							data-remove-confirm="<?php echo esc_attr($remove_cover_confirm); ?>">
							<figure class="prs-book-cover" id="prs-book-cover-figure">
								<?php if ($has_image): ?>
									<?php
									if ($final_cover_id) {
										$cover_alt = trim((string) get_post_meta($final_cover_id, '_wp_attachment_image_alt', true));
										if (!$cover_alt && !empty($book->title)) {
											$cover_alt = $book->title;
										}
										if (!$cover_alt) {
											$cover_alt = __('Book cover', 'politeia-reading');
										}

										$cover_img_src = wp_get_attachment_image_src($final_cover_id, 'large');
										$cover_img_url = $cover_img_src ? set_url_scheme($cover_img_src[0], $cover_scheme) : '';
										if (!$cover_img_url) {
											$fallback_src = wp_get_attachment_url($final_cover_id);
											$cover_img_url = $fallback_src ? set_url_scheme($fallback_src, $cover_scheme) : '';
										}
										if ($force_http_covers && $cover_img_url) {
											$cover_img_url = preg_replace('#^https:#', 'http:', $cover_img_url);
										}

										if ($cover_img_url) {
											printf(
												'<img src="%1$s" class="prs-cover-img" id="prs-cover-img" alt="%2$s" />',
												esc_url($cover_img_url),
												esc_attr($cover_alt)
											);
										}
									} elseif ($cover_url) {
										$fallback_alt = !empty($book->title) ? $book->title : __('Book cover', 'politeia-reading');
										$cover_url = set_url_scheme($cover_url, $cover_scheme);
										if ($force_http_covers && $cover_url) {
											$cover_url = preg_replace('#^https:#', 'http:', $cover_url);
										}
										printf(
											'<img src="%1$s" class="prs-cover-img" id="prs-cover-img" alt="%2$s" />',
											esc_url($cover_url),
											esc_attr($fallback_alt)
										);
									}
									?>
								<?php else: ?>
									<div id="prs-cover-placeholder" class="prs-cover-placeholder" role="img"
										aria-label="<?php echo esc_attr($placeholder_label); ?>">
										<h3 id="prs-book-title-placeholder" class="prs-cover-title">
											<?php echo esc_html($placeholder_title); ?></h3>
										<span id="prs-book-author-placeholder"
											class="prs-cover-author"><?php echo esc_html($placeholder_author); ?></span>
										<?php echo do_shortcode('[prs_cover_button]'); ?>
									</div>
								<?php endif; ?>
							</figure>
							<?php if ($has_image): ?>
								<div class="prs-cover-overlay">
									<?php echo do_shortcode('[prs_cover_button show_search="true"]'); ?>
								</div>
							<?php endif; ?>
						</div>
						<figcaption id="prs-cover-attribution-wrap"
							class="prs-book-cover__caption <?php echo $cover_source ? '' : 'is-hidden'; ?>"
							aria-hidden="<?php echo $cover_source ? 'false' : 'true'; ?>">
							<a id="prs-cover-attribution"
								class="prs-book-cover__link <?php echo $cover_source ? '' : 'is-hidden'; ?>" <?php echo $cover_source ? 'href="' . esc_url($cover_source) . '"' : ''; ?> target="_blank"
								rel="noopener noreferrer">
								<?php esc_html_e('View on Google Books', 'politeia-reading'); ?>
							</a>
						</figcaption>
					</div>
					<div id="prs-book-identity-slot" class="prs-book-identity-slot"></div>
				</section>

				<section id="progress-section">
					<div class="progress">
						<div class="progress-bar">
							<?php if ($total_pages > 0 && !empty($density_sessions)): ?>
								<canvas id="prs-reading-density-canvas"
									data-total-pages="<?php echo esc_attr($total_pages); ?>"
									data-sessions='<?php echo esc_attr(wp_json_encode($density_sessions)); ?>'></canvas>
							<?php endif; ?>
						</div>
						<small
							class="prs-progress-text"><?php echo esc_html(sprintf(__('%d%% completed', 'politeia-reading'), (int) $progress_percent)); ?></small>
					</div>
				</section>

			</section>

			<section id="book-details-section" class="prs-sidebar-block">
				<h4 style="margin: 0 0 8px; font-size: 18px; color: #000;">
					<?php esc_html_e('Book Details', 'politeia-reading'); ?></h4>
				<ul class="prs-details">
					<li class="prs-detail-divider" aria-hidden="true">
						<hr />
					</li>
					<li id="fld-pages" class="prs-field">
						<strong><?php esc_html_e('Pages:', 'politeia-reading'); ?></strong>
						<span id="pages-view"><?php echo $ub->pages ? (int) $ub->pages : '—'; ?></span>
						<a href="#" id="pages-edit"
							class="prs-inline-actions"><?php esc_html_e('edit', 'politeia-reading'); ?></a>
						<input type="number" id="pages-input" class="prs-inline-input" min="1"
							value="<?php echo $ub->pages ? (int) $ub->pages : ''; ?>"
							style="display:none;width:80px;" />
						<div id="pages-hint" class="prs-help" style="display:none;margin-top:4px;">
							<?php esc_html_e('Press Enter to save', 'politeia-reading'); ?>
						</div>
					</li>
					<li id="fld-isbn" class="prs-field">
						<strong><?php esc_html_e('ISBN:', 'politeia-reading'); ?></strong>
						<span id="isbn-view"><?php echo $book_isbn ? esc_html($book_isbn) : '—'; ?></span>
						<a href="#" id="isbn-edit" class="prs-inline-actions"
							aria-label="<?php echo esc_attr__('Edit ISBN', 'politeia-reading'); ?>"><?php esc_html_e('edit', 'politeia-reading'); ?></a>
						<input type="text" id="isbn-input" class="prs-inline-input" inputmode="text"
							value="<?php echo $book_isbn ? esc_attr($book_isbn) : ''; ?>"
							style="display:none;width:140px;" />
						<div id="isbn-hint" class="prs-help" style="display:none;margin-top:4px;">
							<?php esc_html_e('Press Enter to save', 'politeia-reading'); ?>
						</div>
					</li>
					<li>
						<strong><?php esc_html_e('Published Date:', 'politeia-reading'); ?></strong>
						<?php echo $book->year ? esc_html((string) $book->year) : '—'; ?>
					</li>
					<li>
						<label for="prs-type-book"
							style="font-weight: 600; margin-right: 4px; font-size: 13px;"><?php esc_html_e('Format:', 'politeia-reading'); ?></label>
						<select id="prs-type-book" class="prs-type-book__select">
							<option value="" <?php selected($current_type, ''); ?>>
								<?php esc_html_e('Not specified', 'politeia-reading'); ?></option>
							<option value="d" <?php selected($current_type, 'd'); ?>>
								<?php esc_html_e('Digital', 'politeia-reading'); ?></option>
							<option value="p" <?php selected($current_type, 'p'); ?>>
								<?php esc_html_e('Printed', 'politeia-reading'); ?></option>
						</select>
						<span id="type-book-status" class="prs-help" aria-live="polite"></span>
					</li>
					<li class="prs-detail-divider" aria-hidden="true">
						<hr />
					</li>
					<li id="fld-purchase-date" class="prs-field">
						<strong><?php esc_html_e('Purchase Date:', 'politeia-reading'); ?></strong><br />
						<span
							id="purchase-date-view"><?php echo $ub->purchase_date ? esc_html($ub->purchase_date) : '—'; ?></span>
						<a href="#" id="purchase-date-edit"
							class="prs-inline-actions"><?php esc_html_e('edit', 'politeia-reading'); ?></a>
						<span id="purchase-date-form" style="display:none;" class="prs-inline-actions">
							<input type="date" id="purchase-date-input"
								value="<?php echo $ub->purchase_date ? esc_attr($ub->purchase_date) : ''; ?>" />
							<button type="button" id="purchase-date-save"
								class="prs-btn"><?php esc_html_e('Save', 'politeia-reading'); ?></button>
							<button type="button" id="purchase-date-cancel"
								class="prs-btn"><?php esc_html_e('Cancel', 'politeia-reading'); ?></button>
							<span id="purchase-date-status" class="prs-help"></span>
						</span>
					</li>
					<li id="fld-purchase-channel" class="prs-field">
						<strong><?php esc_html_e('Purchase Channel:', 'politeia-reading'); ?></strong><br />
						<span id="purchase-channel-view">
							<?php
							$label = '—';
							if ($ub->purchase_channel) {
								$label = ucfirst($ub->purchase_channel);
								if ($ub->purchase_place) {
									$label .= ' — ' . $ub->purchase_place;
								}
							}
							echo esc_html($label);
							?>
						</span>
						<a href="#" id="purchase-channel-edit"
							class="prs-inline-actions"><?php esc_html_e('edit', 'politeia-reading'); ?></a>
						<span id="purchase-channel-form" style="display:none;" class="prs-inline-actions">
							<select id="purchase-channel-select">
								<option value=""><?php esc_html_e('Select…', 'politeia-reading'); ?></option>
								<option value="online" <?php selected($ub->purchase_channel, 'online'); ?>>
									<?php esc_html_e('Online', 'politeia-reading'); ?></option>
								<option value="store" <?php selected($ub->purchase_channel, 'store'); ?>>
									<?php esc_html_e('Store', 'politeia-reading'); ?></option>
							</select>
							<input type="text" id="purchase-place-input"
								placeholder="<?php esc_attr_e('Which?', 'politeia-reading'); ?>"
								value="<?php echo $ub->purchase_place ? esc_attr($ub->purchase_place) : ''; ?>"
								style="display: <?php echo $ub->purchase_channel ? 'inline-block' : 'none'; ?>;" />
							<button type="button" id="purchase-channel-save"
								class="prs-btn"><?php esc_html_e('Save', 'politeia-reading'); ?></button>
							<button type="button" id="purchase-channel-cancel"
								class="prs-btn"><?php esc_html_e('Cancel', 'politeia-reading'); ?></button>
							<span id="purchase-channel-status" class="prs-help"></span>
						</span>
					</li>
				</ul>
			</section>

		</aside>

		<section class="content">
			<?php
			$reading_disabled = in_array($ub->owning_status, array('borrowing', 'borrowed'), true);
			$reading_disabled_text = __('Disabled while this book is being borrowed.', 'politeia-reading');
			$reading_disabled_title = $reading_disabled ? ' title="' . esc_attr($reading_disabled_text) . '"' : '';
			$reading_disabled_attr = $reading_disabled ? ' disabled="disabled"' : '';
			$reading_disabled_class = $reading_disabled ? ' is-disabled' : '';
			?>
			<div id="prs-book-header" class="prs-content-card">
				<div class="header">
					<div id="book-identity">
						<div id="book-title-row">
							<h1><?php echo esc_html($book->title); ?></h1>
							<span role="button" tabindex="0" id="prs-session-recorder-open"
								class="prs-session-recorder-trigger material-symbols-outlined"
								aria-label="<?php esc_attr_e('Open session recorder', 'politeia-reading'); ?>"
								aria-controls="prs-session-modal" aria-expanded="false">play_circle</span>
						</div>
						<p id="book-author">
							<?php echo $book_authors ? esc_html($book_authors) : esc_html($placeholder_author); ?>
						</p>
						<div id="fld-user-rating" class="prs-field">
							<div class="prs-stars" id="prs-user-rating" role="radiogroup"
								aria-label="<?php esc_attr_e('Your rating', 'politeia-reading'); ?>">
								<?php
								$current_rating = isset($ub->rating) && null !== $ub->rating ? (int) $ub->rating : 0;
								for ($i = 1; $i <= 5; $i++):
									?>
									<button type="button"
										class="prs-star<?php echo ($i <= $current_rating) ? ' is-active' : ''; ?>"
										data-value="<?php echo $i; ?>" role="radio"
										aria-checked="<?php echo ($i === $current_rating) ? 'true' : 'false'; ?>">
										★
									</button>
								<?php endfor; ?>
								<span id="rating-status" class="prs-help" aria-live="polite"></span>
							</div>
						</div>
						<div id="fld-reading-status" class="prs-field">
							<div class="prs-reading-status-row">
								<label
									for="reading-status-select"><?php esc_html_e('Reading Status', 'politeia-reading'); ?></label>
								<select id="reading-status-select"
									class="reading-status-select<?php echo esc_attr($reading_disabled_class); ?>"
									data-disabled-text="<?php echo esc_attr(__('Disabled while this book is being borrowed.', 'politeia-reading')); ?>"
									aria-disabled="<?php echo $reading_disabled ? 'true' : 'false'; ?>" <?php echo $reading_disabled_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $reading_disabled_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
									<option value="not_started" <?php selected($ub->reading_status, 'not_started'); ?>><?php esc_html_e('Not Started', 'politeia-reading'); ?></option>
									<option value="started" <?php selected($ub->reading_status, 'started'); ?>>
										<?php esc_html_e('Started', 'politeia-reading'); ?></option>
									<option value="finished" <?php selected($ub->reading_status, 'finished'); ?>>
										<?php esc_html_e('Finished', 'politeia-reading'); ?></option>
								</select>
								<span id="reading-status-status" class="prs-help" aria-live="polite"></span>
							</div>
						</div>
					</div>
					<div id="owning-status-summary">
						<?php
						$is_digital = ('d' === $current_type);
						$show_return_btn = in_array($ub->owning_status, array('borrowed', 'borrowing'), true);
						?>
						<div class="prs-field prs-status-field" id="fld-owning-status"
							data-contact-name="<?php echo esc_attr($contact_name); ?>"
							data-contact-email="<?php echo esc_attr($contact_email); ?>"
							data-label-borrowing="<?php echo esc_attr($label_borrowing); ?>"
							data-label-borrowed="<?php echo esc_attr($label_borrowed); ?>"
							data-label-sold="<?php echo esc_attr($label_sold); ?>"
							data-label-lost="<?php echo esc_attr($label_lost); ?>"
							data-label-sold-on="<?php echo esc_attr($label_sold_on); ?>"
							data-label-lost-date="<?php echo esc_attr($label_lost_date); ?>"
							data-label-unknown="<?php echo esc_attr($label_unknown); ?>"
							data-active-start="<?php echo esc_attr($active_start_local); ?>">
							<label class="label"
								for="owning-status-select"><?php esc_html_e('Owning Status', 'politeia-reading'); ?></label>
							<select id="owning-status-select" <?php disabled($is_digital); ?>
								aria-disabled="<?php echo $is_digital ? 'true' : 'false'; ?>">
								<option value="" <?php selected(empty($ub->owning_status)); ?>>
									<?php esc_html_e('— Select —', 'politeia-reading'); ?></option>
								<option value="borrowed" <?php selected($ub->owning_status, 'borrowed'); ?>>
									<?php esc_html_e('Borrowed', 'politeia-reading'); ?></option>
								<option value="borrowing" <?php selected($ub->owning_status, 'borrowing'); ?>>
									<?php esc_html_e('Lent Out', 'politeia-reading'); ?></option>
								<option value="bought" <?php selected($ub->owning_status, 'bought'); ?>>
									<?php esc_html_e('Bought', 'politeia-reading'); ?></option>
								<option value="sold" <?php selected($ub->owning_status, 'sold'); ?>>
									<?php esc_html_e('Sold', 'politeia-reading'); ?></option>
								<option value="lost" <?php selected($ub->owning_status, 'lost'); ?>>
									<?php esc_html_e('Lost', 'politeia-reading'); ?></option>
							</select>

							<button type="button" id="owning-return-shelf" class="prs-btn owning-return-shelf"
								data-book-id="<?php echo (int) $book->id; ?>"
								data-user-book-id="<?php echo (int) $ub->id; ?>"
								style="<?php echo $show_return_btn ? '' : 'display:none;'; ?>" <?php disabled($is_digital); ?>>
								<?php esc_html_e('Mark as returned', 'politeia-reading'); ?>
							</button>

							<span id="owning-status-status" class="prs-help owning-status-info"
								data-book-id="<?php echo (int) $book->id; ?>"><?php echo $owning_message ? esc_html($owning_message) : ''; ?></span>
							<p id="owning-status-note" class="prs-help prs-owning-status-note"
								style="<?php echo $is_digital ? '' : 'display:none;'; ?>">
								<?php esc_html_e('Owning status is available only for printed copies.', 'politeia-reading'); ?>
							</p>
							<p class="prs-location" id="derived-location">
								<strong><?php esc_html_e('Location', 'politeia-reading'); ?>:</strong>
								<span
									id="derived-location-text"><?php echo empty($ub->owning_status) ? esc_html__('In Shelf', 'politeia-reading') : esc_html__('Not In Shelf', 'politeia-reading'); ?></span>
							</p>
						</div>
					</div>
				</div>
			</div>

			<div id="prs-book-content" class="prs-content-card">
				<div class="tabs" role="tablist"
					aria-label="<?php esc_attr_e('Book sections', 'politeia-reading'); ?>">
					<button class="tab active" type="button" data-tab="reading-sessions" role="tab"
						aria-selected="true"><?php esc_html_e('Reading Sessions', 'politeia-reading'); ?></button>
					<button class="tab" type="button" data-tab="book-stats" role="tab"
						aria-selected="false"><?php esc_html_e('Book Stats', 'politeia-reading'); ?></button>
					<button class="tab" type="button" data-tab="notes-feed" role="tab"
						aria-selected="false"><?php esc_html_e('Notes Feed', 'politeia-reading'); ?></button>
				</div>

				<div class="prs-tab-content is-active" data-tab="reading-sessions">
					<?php
					$reading_template = trailingslashit(__DIR__) . 'my-book-single-ver-2/reading-sessions.php';
					if (file_exists($reading_template)) {
						include $reading_template;
					}
					?>
				</div>

				<div class="prs-tab-content" data-tab="book-stats">
					<?php
					$stats_template = trailingslashit(__DIR__) . 'my-book-single-ver-2/book-stats.php';
					if (file_exists($stats_template)) {
						include $stats_template;
					}
					?>
				</div>

				<div class="prs-tab-content" data-tab="notes-feed">
					<?php
					$notes_template = trailingslashit(__DIR__) . 'my-book-single-ver-2/notes-feed.php';
					if (file_exists($notes_template)) {
						include $notes_template;
					}
					?>
				</div>
			</div>
		</section>
	</div>
</div>

<script>
	document.addEventListener("DOMContentLoaded", function () {
		var tabs = document.querySelectorAll(".tabs .tab[data-tab]");
		var panels = document.querySelectorAll(".prs-tab-content[data-tab]");
		if (!tabs.length || !panels.length) return;

		function activateTab(target) {
			tabs.forEach(function (tab) {
				var isActive = tab.getAttribute("data-tab") === target;
				tab.classList.toggle("active", isActive);
				tab.setAttribute("aria-selected", isActive ? "true" : "false");
			});
			panels.forEach(function (panel) {
				panel.classList.toggle("is-active", panel.getAttribute("data-tab") === target);
			});
		}

		tabs.forEach(function (tab) {
			tab.addEventListener("click", function () {
				activateTab(tab.getAttribute("data-tab"));
			});
		});

		var params = new URLSearchParams(window.location.search || '');
		if (params.get('prs_start_session') === '1') {
			document.dispatchEvent(new CustomEvent('prs-session-modal:open', { detail: { focusClose: true } }));
		}

		var identity = document.getElementById("book-identity");
		var header = document.querySelector("#prs-book-header .header");
		var slot = document.getElementById("prs-book-identity-slot");
		if (identity && header && slot) {
			var mediaQuery = window.matchMedia("(max-width: 980px)");
			var syncIdentityPlacement = function () {
				if (mediaQuery.matches) {
					if (!slot.contains(identity)) {
						slot.appendChild(identity);
					}
				} else if (!header.contains(identity)) {
					header.insertBefore(identity, header.firstElementChild);
				}
			};

			syncIdentityPlacement();
			if (mediaQuery.addEventListener) {
				mediaQuery.addEventListener("change", syncIdentityPlacement);
			} else if (mediaQuery.addListener) {
				mediaQuery.addListener(syncIdentityPlacement);
			}
		}

		var content = document.querySelector(".content");
		var sidebar = document.querySelector(".sidebar");
		var bookContent = document.getElementById("prs-book-content");
		var bookHeader = document.getElementById("prs-book-header");
		var bookDetails = document.getElementById("book-details-section");
		if (content && sidebar && bookContent && bookHeader && bookDetails) {
			var contentMarker = document.getElementById("prs-book-content-placeholder");
			if (!contentMarker) {
				contentMarker = document.createElement("div");
				contentMarker.id = "prs-book-content-placeholder";
				contentMarker.className = "prs-layout-placeholder";
				bookContent.parentNode.insertBefore(contentMarker, bookContent);
			}

			var detailsMarker = document.getElementById("prs-book-details-placeholder");
			if (!detailsMarker) {
				detailsMarker = document.createElement("div");
				detailsMarker.id = "prs-book-details-placeholder";
				detailsMarker.className = "prs-layout-placeholder";
				bookDetails.parentNode.insertBefore(detailsMarker, bookDetails);
			}

			var layoutQuery = window.matchMedia("(max-width: 980px)");
			var syncMobileLayout = function () {
				if (layoutQuery.matches) {
					if (bookHeader.parentNode) {
						bookHeader.parentNode.insertBefore(bookContent, bookHeader);
						bookHeader.insertAdjacentElement("afterend", bookDetails);
					}
				} else {
					if (contentMarker.parentNode) {
						contentMarker.parentNode.insertBefore(bookContent, contentMarker.nextSibling);
					}
					if (detailsMarker.parentNode) {
						detailsMarker.parentNode.insertBefore(bookDetails, detailsMarker.nextSibling);
					}
				}
			};

			syncMobileLayout();
			if (layoutQuery.addEventListener) {
				layoutQuery.addEventListener("change", syncMobileLayout);
			} else if (layoutQuery.addListener) {
				layoutQuery.addListener(syncMobileLayout);
			}
		}
	});
</script>

<div id="prs-session-modal" class="prs-session-modal" role="dialog" aria-modal="true" aria-hidden="true"
	aria-label="<?php esc_attr_e('Session recorder', 'politeia-reading'); ?>">
	<div class="prs-session-modal__content">
		<button type="button" id="prs-session-recorder-close" class="prs-session-modal__close"
			aria-label="<?php esc_attr_e('Close session recorder', 'politeia-reading'); ?>">
			×
		</button>
		<?php echo do_shortcode('[politeia_start_reading book_id="' . (int) $book->id . '"]'); ?>
	</div>
</div>

<div id="prs-search-cover-overlay" class="prs-search-cover-overlay is-hidden" aria-hidden="true">
	<div class="prs-search-cover-modal">
		<h2 class="prs-search-cover-title"><?php esc_html_e('Search Book Cover', 'politeia-reading'); ?></h2>

		<div class="prs-search-cover-options"></div>

		<div class="prs-search-cover-actions">
			<button id="prs-cancel-cover" class="prs-btn prs-cancel-cover-button"
				type="button"><?php esc_html_e('CANCEL', 'politeia-reading'); ?></button>
			<button id="prs-set-cover" class="prs-btn prs-set-cover-button" type="button"
				disabled="disabled"><?php esc_html_e('SET COVER', 'politeia-reading'); ?></button>
		</div>
	</div>
</div>

<?php prs_render_owning_overlay(array('heading' => $label_borrowing)); ?>

<div id="return-overlay" class="prs-overlay" style="display:none;">
	<div class="prs-overlay-backdrop"></div>
	<div class="prs-overlay-content">
		<h2><?php esc_html_e('Are you sure you want to mark as returned?', 'politeia-reading'); ?></h2>
		<div class="prs-overlay-actions">
			<button type="button" id="return-overlay-yes"
				class="prs-btn"><?php esc_html_e('Yes', 'politeia-reading'); ?></button>
			<button type="button" id="return-overlay-no"
				class="prs-btn prs-btn-secondary"><?php esc_html_e('No', 'politeia-reading'); ?></button>
		</div>
	</div>
</div>
<?php
get_footer();
