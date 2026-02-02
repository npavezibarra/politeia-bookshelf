<?php
/**
 * Shortcode: [politeia_start_reading book_id="..."]
 * UI en formato de tabla + estados: IDLE -> RUNNING -> STOPPED
 */
if (!defined('ABSPATH')) {
	exit;
}

add_shortcode(
	'politeia_start_reading',
	function ($atts) {
		if (!is_user_logged_in()) {
			return '<p>' . esc_html__('You must be logged in.', 'politeia-reading') . '</p>';
		}

		$atts = shortcode_atts(
			array(
				'book_id' => 0,
				'plan_id' => 0,
			),
			$atts,
			'politeia_start_reading'
		);

		$book_id = absint($atts['book_id']);
		$plan_id = absint($atts['plan_id']);
		if (!$book_id) {
			return '';
		}

		global $wpdb;
		$user_id = get_current_user_id();
		$default_start_page = 0;

		$tbl_rs = $wpdb->prefix . 'politeia_reading_sessions';
		$tbl_ub = $wpdb->prefix . 'politeia_user_books';
		$tbl_books = $wpdb->prefix . 'politeia_books';
		$tbl_authors = $wpdb->prefix . 'politeia_authors';
		$tbl_book_authors = $wpdb->prefix . 'politeia_book_authors';
		$tbl_plans = $wpdb->prefix . 'politeia_plans';
		$tbl_plan_sessions = $wpdb->prefix . 'politeia_planned_sessions';

		$book_title = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT title FROM {$tbl_books} WHERE id = %d LIMIT 1",
				$book_id
			)
		);
		$book_title = $book_title ? (string) $book_title : '';
		$book_author = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT GROUP_CONCAT(a.display_name ORDER BY ba.sort_order ASC SEPARATOR ', ')
				FROM {$tbl_book_authors} ba
				LEFT JOIN {$tbl_authors} a ON a.id = ba.author_id
				WHERE ba.book_id = %d",
				$book_id
			)
		);
		$book_author = $book_author ? (string) $book_author : __('Unknown author', 'politeia-reading');

		// Owning status y pages actuales del usuario para este libro
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

		// Última página de la última sesión (si existe)
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

		// No se puede iniciar si está prestado a otro, perdido o vendido
		$can_start = !in_array($owning_status, array('borrowed', 'lost', 'sold'), true);

		if ($plan_id > 0) {
			$plan_owner = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT user_id FROM {$tbl_plans} WHERE id = %d LIMIT 1",
					$plan_id
				)
			);
			if ($plan_owner === (int) $user_id) {
				// LEGACY: This column "planned_start_page" is deprecated and may be removed in future versions.
				// We wrap this in a silent try/catch (or just suppress errors) because likely it returns null now.
				// For now, we just suppress the error if the column is missing.
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

		// Encolar JS/CSS del recorder
		wp_enqueue_script('politeia-start-reading');
		wp_enqueue_style('politeia-reading');

		$prs_sr = array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('prs_reading_nonce'),
			'user_id' => (int) $user_id,
			'book_id' => (int) $book_id,
			'last_end_page' => is_null($last_end_page) ? '' : (int) $last_end_page,
			'default_start_page' => $default_start_page,
			'owning_status' => (string) $owning_status,
			'total_pages' => (int) $total_pages, // ← NUEVO
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

		// Pasar datos al JS
		wp_localize_script(
			'politeia-start-reading',
			'PRS_SR',
			$prs_sr
		);

		$check_icon_url = defined('POLITEIA_READING_URL')
			? esc_url(POLITEIA_READING_URL . 'assets/svg/check_circle_24dp_1F1F1F_FILL0_wght400_GRAD0_opsz24.svg')
			: '';

		ob_start();
		?>
	<style>
		@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@200;300;400;500;600;700&display=swap');

		.prs-sr {
			width: 100%;
			background: #1a1a1a;
			color: #ffffff;
			border-radius: 24px;
			padding: 32px;
			font-family: 'Poppins', sans-serif;
		}

		.prs-sr * {
			box-sizing: border-box;
		}

		.prs-sr input[type=number]::-webkit-outer-spin-button,
		.prs-sr input[type=number]::-webkit-inner-spin-button {
			-webkit-appearance: none;
			margin: 0;
		}

		.prs-sr input[type=number] {
			-moz-appearance: textfield;
		}

		.prs-sr-header {
			text-align: center;
			margin-bottom: 22px;
		}

		.prs-sr-kicker {
			font-size: 12px;
			text-transform: uppercase;
			letter-spacing: 0.28em;
			opacity: 0.6;
			margin-bottom: 12px;
			font-weight: 600;
		}

		.prs-sr-meta {
			margin: 0 0 8px;
		}

		.prs-sr-meta-title {
			display: block;
			font-size: 22px;
			font-weight: 500;
			letter-spacing: 0.03em;
		}

		.prs-sr-meta-author {
			display: block;
			font-size: 11px;
			text-transform: uppercase;
			letter-spacing: 0.2em;
			opacity: 0.55;
			margin-top: 4px;
		}

		.prs-sr-last {
			text-align: center;
			font-size: 14px;
			color: rgba(255, 255, 255, 0.75);
			margin-bottom: 28px;
		}

		.prs-sr-form {
			display: flex;
			flex-direction: column;
			align-items: center;
			gap: 32px;
		}

		.prs-sr-field {
			width: 100%;
			display: flex;
			flex-direction: column;
			align-items: center;
			gap: 10px;
		}

		.prs-sr-input,
		.prs-sr-view {
			width: 100%;
			max-width: 460px;
			background: #1a1a1a;
			border: none;
			border-bottom: 2px solid rgba(255, 255, 255, 0.12);
			border-radius: 0;
			padding: 12px 0;
			font-size: 34px;
			text-align: center;
			color: #ffffff;
			transition: border-color 0.2s ease;
		}

		.prs-sr-input:focus {
			outline: none;
			background: #1a1a1a;
			border-bottom-color: #c79f32;
			box-shadow: none;
		}

		.prs-sr-input:focus-visible {
			outline: none;
			box-shadow: none;
		}

		.prs-sr-input::placeholder {
			color: rgba(255, 255, 255, 0.1);
		}

		.prs-sr-label {
			font-size: 11px;
			text-transform: uppercase;
			letter-spacing: 0.35em;
			color: #ffffff;
			opacity: 1;
			font-weight: 600;
			text-align: center;
		}

		.prs-sr-view {
			display: none;
		}

		.prs-sr-timer-row {
			display: flex;
			flex-direction: column;
			align-items: center;
			gap: 20px;
		}

		.prs-sr-clock {
			position: relative;
			width: 260px;
			height: 260px;
			border-radius: 50%;
			background: #f5f5dc;
			box-shadow: 0 0 60px rgba(0, 0, 0, 0.6);
			overflow: hidden;
		}

		.prs-sr-stardust {
			position: absolute;
			inset: 0;
			width: 100%;
			height: 100%;
			display: block;
			z-index: 0;
		}

		.prs-sr-clock-inner {
			position: absolute;
			inset: 8px;
			border-radius: 50%;
			border: 1px solid rgba(255, 255, 255, 0.08);
			background: #f5f5dc;
			z-index: 2;
		}

		.prs-sr-clock svg {
			position: absolute;
			inset: 8px;
			width: calc(100% - 16px);
			height: calc(100% - 16px);
			z-index: 2;
		}

		.prs-sr-clock-center {
			position: absolute;
			top: 50%;
			left: 50%;
			width: 8px;
			height: 8px;
			border-radius: 50%;
			background: rgba(0, 0, 0, 0.1);
			transform: translate(-50%, -50%);
			z-index: 2;
		}

		.prs-sr-timer {
			font-size: 40px;
			letter-spacing: 0.08em;
			font-weight: 600;
			color: rgba(255, 255, 255, 0.8);
		}

		.prs-sr-actions {
			display: flex;
			flex-direction: column;
			align-items: center;
			gap: 14px;
		}

		.prs-sr-btn {
			display: inline-flex;
			align-items: center;
			gap: 16px;
			background: transparent;
			border: none;
			color: inherit;
			cursor: pointer;
			text-transform: uppercase;
			letter-spacing: 0.25em;
			font-size: 18px;
			font-weight: 600;
			padding: 10px 14px;
			transition: transform 0.2s ease, opacity 0.2s ease;
		}

		.prs-sr-btn[disabled] {
			opacity: 0.4;
			cursor: not-allowed;
		}

		.prs-sr-btn:active {
			transform: scale(0.98);
		}

		.prs-sr-btn-icon {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			font-size: 24px;
			background: transparent;
		}

		.prs-sr-btn-icon svg {
			width: 28px;
			height: 28px;
			fill: #C79F32;
			display: block;
		}

		.prs-sr-btn-label {
			display: inline-block;
		}

		.prs-sr-btn--start .prs-sr-btn-label,
		.prs-sr-btn--save .prs-sr-btn-label {
			background: linear-gradient(135deg, #8A6B1E, #C79F32, #E9D18A);
			-webkit-background-clip: text;
			background-clip: text;
			color: transparent;
		}

		.prs-sr-btn--stop .prs-sr-btn-label {
			background: linear-gradient(135deg, #783F27, #B87333, #E5AA70);
			-webkit-background-clip: text;
			background-clip: text;
			color: transparent;
		}

		.prs-sr-btn--start .prs-sr-btn-icon {
			color: #C79F32;
		}

		.prs-sr-btn--stop .prs-sr-btn-icon {
			color: #B87333;
		}

		.prs-sr-btn--save .prs-sr-btn-icon {
			color: #C79F32;
		}

		.prs-sr-row-needs-pages {
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 10px;
			padding: 12px 14px;
			border-radius: 10px;
			background: linear-gradient(135deg, #783F27, #B87333, #E5AA70);
			font-size: 13px;
			color: #fff;
		}

		.prs-sr-row-needs-pages .prs-warning-icon {
			stroke: #fff;
		}

		.prs-sr-flash-block {
			display: none;
			width: 100%;
			background: #1a1a1a;
			color: #ffffff;
			border-radius: 20px;
			padding: 0 28px 28px;
			font-family: 'Poppins', sans-serif;
		}

		.prs-sr-flash-inner {
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			text-align: center;
			gap: 10px;
		}

		.prs-sr-flash-inner h2 {
			margin: 0;
			font-size: 22px;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.16em;
			color: #ffffff;
		}

		.prs-sr-flash-inner h3 {
			margin: 0;
			font-size: 18px;
			font-weight: 500;
			color: #ffffff;
		}

		.prs-sr-flash-sub {
			margin-top: 4px;
			font-size: 13px;
			color: rgba(255, 255, 255, 0.7);
		}

		.prs-sr-flash-icon {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 54px;
			height: 54px;
			margin-bottom: 6px;
		}

		.prs-sr-flash-icon--check {
			background: linear-gradient(135deg, #8A6B1E, #C79F32, #E9D18A);
			-webkit-mask: url('<?php echo $check_icon_url; ?>') no-repeat center / contain;
			mask: url('<?php echo $check_icon_url; ?>') no-repeat center / contain;
		}

		.prs-btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
			min-height: 42px;
			padding: 10px 18px;
			background: rgba(255, 255, 255, 0.08);
			color: #ffffff;
			border: 1px solid rgba(255, 255, 255, 0.1);
			cursor: pointer;
			box-shadow: none;
			outline: none;
			border-radius: 12px;
			font-weight: 600;
			letter-spacing: 0.05em;
			text-transform: uppercase;
			font-size: 12px;
		}

		.prs-btn-secondary {
			background: transparent;
		}

		.prs-btn[disabled] {
			opacity: .4;
			cursor: not-allowed;
		}

		.prs-btn:focus-visible {
			outline: 2px solid #ffffff;
			outline-offset: 2px;
		}

		.prs-add-note-btn {
			background: #1a1a1a !important;
			color: transparent !important;
			border: none !important;
			font-size: 18px;
			padding: 12px 26px;
			letter-spacing: 0.2em;
		}

		.prs-add-note-btn .prs-add-note-text {
			background: linear-gradient(135deg, #8A6B1E, #C79F32, #E9D18A);
			-webkit-background-clip: text;
			background-clip: text;
			color: transparent !important;
		}

		.prs-add-note-btn:hover,
		.prs-add-note-btn:focus {
			filter: brightness(1.05);
		}

		.prs-note-panel {
			width: 100%;
			margin-top: 18px;
			background: #1a1a1a;
			color: #ffffff;
			border: none;
		}

		#prs-note-panel {
			background: #1a1a1a !important;
			color: #ffffff !important;
			border: none !important;
		}

		.note-editor-panel {
			border: none;
			border-radius: 18px;
			padding: 18px;
			background: #1a1a1a;
		}

		#prs-note-panel .note-editor-panel,
		#prs-note-panel .note-toolbar,
		#prs-note-panel .note-textarea,
		#prs-note-panel .note-actions {
			background: #1a1a1a !important;
			color: #ffffff !important;
			border: none !important;
		}

		.prs-note-header {
			display: flex;
			justify-content: space-between;
			gap: 16px;
			margin-bottom: 12px;
			font-size: 11px;
			text-transform: uppercase;
			letter-spacing: 0.2em;
			color: #ffffff;
			text-align: center;
		}

		.prs-note-header .prs-session-id,
		.prs-note-header .prs-pages {
			flex: 1;
			text-align: center;
		}

		.note-toolbar {
			display: flex;
			justify-content: center;
			gap: 18px;
			border-bottom: none;
			padding-bottom: 10px;
			margin-bottom: 12px;
			background: #1a1a1a;
		}

		#prs-note-panel .tool-button {
			background: none;
			border: none;
			color: #ffffff;
			font-weight: 600;
			padding: 6px 10px;
			cursor: pointer;
			font-size: 16px;
			border-radius: 6px;
			font-family: inherit;
		}

		#prs-note-panel .tool-button:hover,
		#prs-note-panel .tool-button:focus,
		#prs-note-panel .tool-button:focus-visible {
			background: none;
			color: #ffffff;
			outline: none;
		}

		.note-textarea {
			min-height: 160px;
			color: #ffffff;
			background: #1a1a1a;
			border: none;
			border-radius: 16px;
			padding: 16px;
			font-size: 15px;
			text-align: left;
		}

		div#prs-note-editor {
			font-family: 'Poppins', sans-serif !important;
		}

		.note-textarea:focus {
			outline: none;
			box-shadow: none;
		}

		.note-textarea::placeholder {
			color: rgba(255, 255, 255, 0.35);
		}

		.note-actions {
			display: flex;
			justify-content: center;
			gap: 16px;
			margin-top: 18px;
			background: #1a1a1a;
		}

		#prs-note-panel .note-actions {
			background: #1a1a1a;
		}

		#prs-cancel-note-btn {
			background: #1a1a1a !important;
			color: #ffffff !important;
			border: none !important;
		}

		#prs-save-note-btn {
			background: #1a1a1a !important;
			border: none !important;
		}

		#prs-save-note-btn {
			background: #1a1a1a;
			color: transparent;
			border: none;
		}

		#prs-save-note-btn .prs-btn-text {
			background: linear-gradient(135deg, #8A6B1E, #C79F32, #E9D18A);
			-webkit-background-clip: text;
			background-clip: text;
			color: transparent;
			font-size: 18px;
		}

		#prs-cancel-note-btn {
			background: #1a1a1a;
			color: #ffffff;
			border: none;
		}

		.prs-sr-end-error {
			margin-top: 10px;
			font-size: 12px;
			letter-spacing: 0.08em;
			text-transform: uppercase;
			color: #E9D18A;
			text-align: center;
			display: none;
		}

		@media (max-width: 640px) {
			.prs-sr {
				padding: 24px;
			}

			.prs-sr-input,
			.prs-sr-view {
				font-size: 28px;
			}

			.prs-sr-clock {
				width: 210px;
				height: 210px;
			}

			.prs-sr-timer {
				font-size: 32px;
			}

			.prs-sr-btn {
				letter-spacing: 0.15em;
				font-size: 14px;
			}
		}
	</style>

	<div class="prs-sr" data-book-id="<?php echo (int) $book_id; ?>"
		data-prs-sr="<?php echo esc_attr(wp_json_encode($prs_sr)); ?>">
		<div class="prs-sr-header">
			<div class="prs-sr-kicker"><?php esc_html_e('Session recorder', 'politeia-reading'); ?></div>
			<?php if ($book_title || $book_author): ?>
				<div class="prs-sr-meta">
					<?php if ($book_title): ?>
						<span class="prs-sr-meta-title"><?php echo esc_html($book_title); ?></span>
					<?php endif; ?>
					<?php if ($book_author): ?>
						<span class="prs-sr-meta-author">
							<?php echo esc_html(sprintf(__('by %s', 'politeia-reading'), $book_author)); ?>
						</span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ($last_end_page): ?>
			<div class="prs-sr-last" data-role="sr-last">
				<?php esc_html_e('Last session page', 'politeia-reading'); ?>:
				<strong><?php echo (int) $last_end_page; ?></strong>
			</div>
		<?php endif; ?>

		<!-- Bloque HTML de éxito (centrado, amarillo, h2/h3) -->
		<div id="prs-sr-flash" class="prs-sr-flash-block" style="display:none;" role="status" aria-live="polite"
			data-session-id="" data-book-id="<?php echo esc_attr($book_id); ?>"
			data-user-id="<?php echo esc_attr($user_id); ?>">
			<div class="prs-sr-flash-inner">
				<div id="prs-sr-summary" style="font-family: 'Poppins', sans-serif;">
					<span class="prs-sr-flash-icon prs-sr-flash-icon--check" aria-hidden="true"></span>
					<h2><?php esc_html_e('Great job!', 'politeia-reading'); ?></h2>
					<h3>
						<?php
							printf(
								/* translators: 1: pages read, 2: time spent. */
								wp_kses_post(__('You read %1$s in %2$s.', 'politeia-reading')),
								'<span id="prs-sr-flash-pages">—</span>',
								'<span id="prs-sr-flash-time">—</span>'
							);
							?>
					</h3>
					<div class="prs-sr-flash-sub">
						<?php esc_html_e('See you soon to keep reading this book.', 'politeia-reading'); ?>
					</div>
					<button type="button" id="prs-add-note-btn" class="prs-btn prs-add-note-btn"
						aria-controls="prs-note-panel" aria-expanded="false">
						<span class="prs-add-note-text"><?php esc_html_e('Add Note', 'politeia-reading'); ?></span>
					</button>
				</div>

				<div id="prs-note-panel" class="prs-note-panel" style="display:none;">
					<div class="note-editor-panel" role="group"
						aria-label="<?php esc_attr_e('Session note editor', 'politeia-reading'); ?>">
						<div class="note-toolbar" role="toolbar"
							aria-label="<?php esc_attr_e('Formatting options', 'politeia-reading'); ?>">
							<button type="button" class="tool-button bold" data-command="bold"
								title="<?php esc_attr_e('Bold', 'politeia-reading'); ?>">B</button>
							<button type="button" class="tool-button italic" data-command="italic"
								title="<?php esc_attr_e('Italic', 'politeia-reading'); ?>">I</button>
							<button type="button" class="tool-button" data-command="bullet"
								title="<?php esc_attr_e('Bullet list', 'politeia-reading'); ?>">•</button>
						</div>
						<?php $note_placeholder = esc_attr__('Write your thoughts about this session…', 'politeia-reading'); ?>
						<div id="prs-note-editor" class="note-textarea editor-area" contenteditable="true" role="textbox"
							aria-multiline="true" spellcheck="true" data-placeholder="<?php echo $note_placeholder; ?>"
							placeholder="<?php echo $note_placeholder; ?>"></div>
						<div class="note-limit-warning" role="status" aria-live="polite"
							style="display:none; font-size:12px; color:#b91c1c; text-align:center;">
							<?php esc_html_e('You have reached the 3000 character limit.', 'politeia-reading'); ?>
						</div>
					</div>
					<div class="note-actions">
						<button type="button" id="prs-save-note-btn" class="prs-btn">
							<span class="prs-btn-text"><?php esc_html_e('Save Note', 'politeia-reading'); ?></span>
						</button>
						<button type="button" id="prs-cancel-note-btn" class="prs-btn prs-btn-secondary">
							<?php esc_html_e('Cancel', 'politeia-reading'); ?>
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Wrapper del formulario (se oculta mientras se muestra el flash) -->
		<div id="prs-sr-formwrap" class="prs-sr-form">
			<div id="prs-sr-row-start" class="prs-sr-field" data-role="sr-field">
				<input type="number" min="1" id="prs-sr-start-page" class="prs-sr-input" placeholder="1" />
				<span id="prs-sr-start-page-view" class="prs-sr-view"></span>
				<label for="prs-sr-start-page"
					class="prs-sr-label"><?php esc_html_e('Start page', 'politeia-reading'); ?>*</label>
			</div>

			<div id="prs-sr-row-chapter" class="prs-sr-field" data-role="sr-field">
				<input type="text" id="prs-sr-chapter" class="prs-sr-input"
					placeholder="<?php esc_attr_e('Chapter', 'politeia-reading'); ?>" />
				<span id="prs-sr-chapter-view" class="prs-sr-view"></span>
				<label for="prs-sr-chapter" class="prs-sr-label"><?php esc_html_e('Chapter', 'politeia-reading'); ?></label>
			</div>

			<div id="prs-sr-row-timer" class="prs-sr-timer-row">
				<div class="prs-sr-clock" aria-hidden="true" style="display:none;">
					<canvas class="prs-sr-stardust" aria-hidden="true"></canvas>
					<div class="prs-sr-clock-inner"></div>
					<svg viewBox="0 0 200 200">
						<defs>
							<linearGradient id="prs-sr-gold-<?php echo (int) $book_id; ?>" x1="0" y1="0" x2="200" y2="200"
								gradientUnits="userSpaceOnUse">
								<stop offset="0%" stop-color="#8A6B1E" />
								<stop offset="50%" stop-color="#C79F32" />
								<stop offset="100%" stop-color="#E9D18A" />
							</linearGradient>
						</defs>
						<path id="prs-sr-progress" d="" fill="url(#prs-sr-gold-<?php echo (int) $book_id; ?>)" />
						<circle cx="100" cy="5" r="1.2" fill="#8B7355" opacity="0.4" />
						<circle cx="195" cy="100" r="1.2" fill="#8B7355" opacity="0.4" />
						<circle cx="100" cy="195" r="1.2" fill="#8B7355" opacity="0.4" />
						<circle cx="5" cy="100" r="1.2" fill="#8B7355" opacity="0.4" />
					</svg>
					<div class="prs-sr-clock-center"></div>
				</div>
			</div>

			<div id="prs-sr-row-needs-pages" style="display:none;">
				<div class="prs-sr-row-needs-pages">
					<svg class="prs-warning-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
						stroke="#EAB308" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
						<line x1="12" y1="9" x2="12" y2="13"></line>
						<line x1="12" y1="17" x2="12.01" y2="17"></line>
					</svg>
					<span><?php esc_html_e('To start a session, set the total Pages for this book in the info panel.', 'politeia-reading'); ?></span>
				</div>
			</div>

			<div id="prs-sr-row-actions" class="prs-sr-actions">
				<a href="#" role="button" id="prs-sr-start" class="prs-sr-btn prs-sr-btn--start" aria-disabled="true">
					<span class="prs-sr-btn-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
							<polygon points="8,5 20,12 8,19"></polygon>
						</svg>
					</span>
					<span class="prs-sr-btn-label"><?php esc_html_e('Start Reading', 'politeia-reading'); ?></span>
				</a>
				<a href="#" role="button" id="prs-sr-stop" class="prs-sr-btn prs-sr-btn--stop" style="display:none;"
					aria-disabled="false">
					<span class="prs-sr-btn-icon">&#9632;</span>
					<span class="prs-sr-btn-label"><?php esc_html_e('Stop Reading', 'politeia-reading'); ?></span>
				</a>
			</div>

			<div id="prs-sr-row-end" class="prs-sr-field" style="display:none;">
				<input type="number" min="1" id="prs-sr-end-page" class="prs-sr-input" placeholder="000" />
				<label for="prs-sr-end-page"
					class="prs-sr-label"><?php esc_html_e('End Page', 'politeia-reading'); ?>*</label>
				<div id="prs-sr-end-error" class="prs-sr-end-error">
					<?php esc_html_e('Page number cannot be less than the starting page.', 'politeia-reading'); ?>
				</div>
			</div>

			<div id="prs-sr-row-save" class="prs-sr-actions" style="display:none;">
				<a href="#" role="button" id="prs-sr-save" class="prs-sr-btn prs-sr-btn--save" aria-disabled="true">
					<span class="prs-sr-btn-icon">&#10003;</span>
					<span class="prs-sr-btn-label"><?php esc_html_e('Save Session', 'politeia-reading'); ?></span>
				</a>
			</div>
		</div>
	</div>
	<?php
		return ob_get_clean();
	}
);
