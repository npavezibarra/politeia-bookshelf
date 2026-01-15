<?php
/**
 * Shortcode: [politeia_start_reading book_id="..."]
 * UI en formato de tabla + estados: IDLE -> RUNNING -> STOPPED
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode(
	'politeia_start_reading',
	function ( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'You must be logged in.', 'politeia-reading' ) . '</p>';
		}

		$atts = shortcode_atts(
			array(
				'book_id' => 0,
			),
			$atts,
			'politeia_start_reading'
		);

		$book_id = absint( $atts['book_id'] );
		if ( ! $book_id ) {
			return '';
		}

		global $wpdb;
		$user_id = get_current_user_id();

                $tbl_rs     = $wpdb->prefix . 'politeia_reading_sessions';
                $tbl_ub     = $wpdb->prefix . 'politeia_user_books';
                $tbl_books  = $wpdb->prefix . 'politeia_books';

                $book_title = $wpdb->get_var(
                        $wpdb->prepare(
                                "SELECT title FROM {$tbl_books} WHERE id = %d LIMIT 1",
                                $book_id
                        )
                );
                $book_title = $book_title ? (string) $book_title : '';

		// Última página de la última sesión (si existe)
		$last_end_page = $wpdb->get_var(
			$wpdb->prepare(
                                "SELECT end_page FROM {$tbl_rs}
     WHERE user_id = %d AND book_id = %d AND end_time IS NOT NULL AND deleted_at IS NULL
     ORDER BY end_time DESC LIMIT 1",
                                $user_id,
                                $book_id
                        )
                );

                // Owning status y pages actuales del usuario para este libro
                $row_ub        = $wpdb->get_row(
                        $wpdb->prepare(
                                "SELECT owning_status, pages FROM {$tbl_ub} WHERE user_id=%d AND book_id=%d AND deleted_at IS NULL LIMIT 1",
                                $user_id,
                                $book_id
                        )
                );
		$owning_status = $row_ub && $row_ub->owning_status ? (string) $row_ub->owning_status : 'in_shelf';
		$total_pages   = $row_ub && $row_ub->pages ? (int) $row_ub->pages : 0;

		// No se puede iniciar si está prestado a otro, perdido o vendido
		$can_start = ! in_array( $owning_status, array( 'borrowed', 'lost', 'sold' ), true );

		// Encolar JS/CSS del recorder
		wp_enqueue_script( 'politeia-start-reading' );
		wp_enqueue_style( 'politeia-reading' );

		// Pasar datos al JS
		wp_localize_script(
			'politeia-start-reading',
			'PRS_SR',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'prs_reading_nonce' ),
				'user_id'       => (int) $user_id,
				'book_id'       => (int) $book_id,
				'last_end_page' => is_null( $last_end_page ) ? '' : (int) $last_end_page,
				'owning_status' => (string) $owning_status,
				'total_pages'   => (int) $total_pages, // ← NUEVO
				'can_start'     => $can_start ? 1 : 0,
				'actions'       => array(
					'start' => 'prs_start_reading',
					'save'  => 'prs_save_reading',
				),
				'strings'       => array(
					'tooltip_pages_required' => __( 'Set total Pages for this book before starting a session.', 'politeia-reading' ),
					'tooltip_not_owned'      => __( 'You cannot start a session: the book is not in your possession (Borrowed, Lost or Sold).', 'politeia-reading' ),
					'alert_pages_required'   => __( 'You must set total Pages to start a session.', 'politeia-reading' ),
					'alert_start_network'    => __( 'Network error while starting the session.', 'politeia-reading' ),
					'alert_save_failed'      => __( 'Could not save the session.', 'politeia-reading' ),
					'alert_save_network'     => __( 'Network error while saving the session.', 'politeia-reading' ),
					'pages_single'           => __( '1 page', 'politeia-reading' ),
					'pages_multiple'         => __( '%d pages', 'politeia-reading' ),
					'minutes_under_one'      => __( 'less than a minute', 'politeia-reading' ),
					'minutes_single'         => __( '1 minute', 'politeia-reading' ),
					'minutes_multiple'       => __( '%d minutes', 'politeia-reading' ),
				),
			)
		);

		ob_start();
		?>
	<style>
	/* Estilos mínimos (la alineación derecha de botones la manejas con tu CSS externo) */
	.prs-sr { width:100%; }
	.prs-sr .prs-sr-head { margin:0 0 8px; }
	.prs-sr .prs-sr-last { color:#555; margin:4px 0 10px; }

	.prs-sr-table { width:100%; border-collapse: collapse; background:#fff; }
	.prs-sr-table th,
	.prs-sr-table td { padding:10px; border:1px solid #ddd; vertical-align:middle; }
	.prs-sr-table th { width:40%; background:#f6f6f6; text-align:left; }
	.prs-sr-input { width:100%; box-sizing:border-box; }
	.prs-sr-timer { font-size:28px; font-weight:600; padding:12px 0; text-align:center; }

	/* Bloque de éxito (HTML) */
	.prs-sr-flash-block {
		display:none;
		width:100%;
		background:#ffe680;     /* amarillo */
		color:#111;             /* texto negro */
		border:1px solid #ddd;
		border-radius:10px;
		padding:24px;
	}
	.prs-sr-flash-inner{
		min-height:140px;       /* se ajusta a la altura del form via JS */
		display:flex;
		flex-direction:column;
		align-items:center;
		justify-content:center;
		text-align:center;
		gap:6px;
	}
	.prs-sr-flash-inner h2{
		margin:0; font-size:22px; font-weight:800;
	}
	.prs-sr-flash-inner h3{
		margin:0; font-size:18px; font-weight:600;
	}
	.prs-sr-flash-sub{
		margin-top:6px; font-size:14px; opacity:.9;
	}

	.prs-btn {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 8px;
		min-height: 42px;
		padding: 10px 18px;
		background: #111111;
		color: #ffffff;
		border: none;
		cursor: pointer;
		box-shadow: none;
		outline: none;
		border-radius: 8px;
		font-weight: 600;
		letter-spacing: 0.01em;
	}
	.prs-btn[disabled] { opacity:.4; cursor:not-allowed; }
	.prs-btn:focus-visible { outline:2px solid #fff; outline-offset:2px; }
	#prs-sr-start {
		background: #000000;
		color: #ffffff;
	}
	#prs-sr-start .prs-play-icon {
		color: #ffffff;
		font-size: 22px;
		line-height: 1;
	}
	#prs-sr-start:hover,
	#prs-sr-start:focus {
		background: #c79f32;
		color: #000000;
	}
	#prs-sr-start:hover .prs-play-icon,
	#prs-sr-start:focus .prs-play-icon {
		color: #000000;
	}

	/* Nota cuando faltan páginas */
	.prs-sr-note {
		font-size:13px;
		color:#444;
	}
	</style>

	<div class="prs-sr" data-book-id="<?php echo (int) $book_id; ?>">
	<h2 class="prs-sr-head"><?php esc_html_e( 'Session recorder', 'politeia-reading' ); ?></h2>

		<?php if ( $last_end_page ) : ?>
		<div class="prs-sr-last">
			<?php esc_html_e( 'Last session page', 'politeia-reading' ); ?>:
		<strong><?php echo (int) $last_end_page; ?></strong>
		</div>
	<?php endif; ?>

	<!-- Bloque HTML de éxito (centrado, amarillo, h2/h3) -->
        <div
                id="prs-sr-flash"
                class="prs-sr-flash-block"
                role="status"
                aria-live="polite"
                data-session-id=""
                data-book-id="<?php echo esc_attr( $book_id ); ?>"
                data-user-id="<?php echo esc_attr( $user_id ); ?>"
        >
                <div class="prs-sr-flash-inner">
                <div id="prs-sr-summary">
                        <h2><?php esc_html_e( 'Great job!', 'politeia-reading' ); ?></h2>
                        <h3>
                                <?php
                                printf(
                                        /* translators: 1: pages read, 2: time spent. */
                                        wp_kses_post( __( 'You read %1$s in %2$s.', 'politeia-reading' ) ),
                                        '<span id="prs-sr-flash-pages">—</span>',
                                        '<span id="prs-sr-flash-time">—</span>'
                                );
                                ?>
                        </h3>
                        <div class="prs-sr-flash-sub"><?php esc_html_e( 'See you soon to keep reading this book.', 'politeia-reading' ); ?></div>
                        <button type="button" id="prs-add-note-btn" class="prs-btn prs-add-note-btn" aria-controls="prs-note-panel" aria-expanded="false">
                                <?php esc_html_e( 'Add Note', 'politeia-reading' ); ?>
                        </button>
                </div>

                <div id="prs-note-panel" class="prs-note-panel" style="display:none;">
                        <div class="note-editor-panel" role="group" aria-label="<?php esc_attr_e( 'Session note editor', 'politeia-reading' ); ?>">
                                <div
                                        class="prs-note-header"
                                        data-default-title="<?php echo esc_attr( $book_title ); ?>"
                                        data-book-title="<?php echo esc_attr( $book_title ); ?>"
                                        data-label-prefix="<?php echo esc_attr__( 'SESSION', 'politeia-reading' ); ?>"
                                        data-default-session-label="<?php echo esc_attr__( 'SESSION —', 'politeia-reading' ); ?>"
                                        data-default-page-range="<?php echo esc_attr__( '— · —', 'politeia-reading' ); ?>"
                                >
                                        <div class="prs-session-id"><?php esc_html_e( 'SESSION —', 'politeia-reading' ); ?></div>
                                        <div class="prs-pages"><?php esc_html_e( '— · —', 'politeia-reading' ); ?></div>
                                </div>
                                <div class="note-toolbar" role="toolbar" aria-label="<?php esc_attr_e( 'Formatting options', 'politeia-reading' ); ?>">
                                        <button type="button" class="tool-button" title="<?php esc_attr_e( 'Heading 1', 'politeia-reading' ); ?>">H1</button>
                                        <button type="button" class="tool-button" title="<?php esc_attr_e( 'Heading 2', 'politeia-reading' ); ?>">H2</button>
                                        <button type="button" class="tool-button bold" title="<?php esc_attr_e( 'Bold', 'politeia-reading' ); ?>">B</button>
                                        <button type="button" class="tool-button italic" title="<?php esc_attr_e( 'Italic', 'politeia-reading' ); ?>">I</button>
                                        <button type="button" class="tool-button" title="<?php esc_attr_e( 'Bullet list', 'politeia-reading' ); ?>">•</button>
                                </div>
                                <?php $note_placeholder = esc_attr__( 'Write your thoughts about this session…', 'politeia-reading' ); ?>
                                <div
                                        id="prs-note-editor"
                                        class="note-textarea editor-area"
                                        contenteditable="true"
                                        role="textbox"
                                        aria-multiline="true"
                                        spellcheck="true"
                                        data-placeholder="<?php echo $note_placeholder; ?>"
                                        placeholder="<?php echo $note_placeholder; ?>"
                                ></div>
                                <div class="note-limit-warning" role="status" aria-live="polite" style="display:none; font-size:12px; color:#b91c1c; text-align:center;">
                                        <?php esc_html_e( 'You have reached the 3000 character limit.', 'politeia-reading' ); ?>
                                </div>
                        </div>
                        <div class="note-actions">
                                <button type="button" id="prs-save-note-btn" class="prs-btn">
                                <?php esc_html_e( 'Save Note', 'politeia-reading' ); ?>
                                </button>
                                <button type="button" id="prs-cancel-note-btn" class="prs-btn prs-btn-secondary">
                                <?php esc_html_e( 'Cancel', 'politeia-reading' ); ?>
                                </button>
                        </div>
                </div>
                </div>
        </div>

	<!-- Wrapper del formulario (se oculta mientras se muestra el flash) -->
	<div id="prs-sr-formwrap">
		<table class="prs-sr-table" role="grid">
		<tbody>
			<!-- Start page -->
			<tr id="prs-sr-row-start">
			<th scope="row"><label for="prs-sr-start-page"><?php esc_html_e( 'Start page', 'politeia-reading' ); ?>*</label></th>
			<td>
				<input type="number" min="1" id="prs-sr-start-page" class="prs-sr-input" />
				<span id="prs-sr-start-page-view" class="prs-sr-view" style="display:none;"></span>
			</td>
			</tr>

			<!-- Capítulo -->
			<tr id="prs-sr-row-chapter">
			<th scope="row"><label for="prs-sr-chapter"><?php esc_html_e( 'Chapter', 'politeia-reading' ); ?></label></th>
			<td>
				<input type="text" id="prs-sr-chapter" class="prs-sr-input" />
				<span id="prs-sr-chapter-view" class="prs-sr-view" style="display:none;"></span>
			</td>
			</tr>

			<!-- Timer -->
			<tr id="prs-sr-row-timer" class="prs-sr-row--full">
			<td colspan="2">
				<div id="prs-sr-timer" class="prs-sr-timer">00:00:00</div>
			</td>
			</tr>

			<!-- AVISO cuando no hay pages -->
                        <tr id="prs-sr-row-needs-pages" class="prs-sr-row--full" style="display:none;">
                        <td colspan="2">
                                <div class="prs-sr-row-needs-pages">
                                        <svg class="prs-warning-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#EAB308" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                                                <line x1="12" y1="9" x2="12" y2="13"></line>
                                                <line x1="12" y1="17" x2="12.01" y2="17"></line>
                                        </svg>
                                        <span><?php esc_html_e( 'To start a session, set the total Pages for this book in the info panel.', 'politeia-reading' ); ?></span>
                                </div>
                        </td>
                        </tr>

			<!-- Start/Stop Buttons (tu layout exacto; alineación derecha en tu CSS externo) -->
			<tr id="prs-sr-row-actions" class="prs-sr-row--full">
			<td colspan="2">
				<button
				type="button"
				id="prs-sr-start"
				class="prs-btn"
				disabled
				>
				<span aria-hidden="true" class="material-symbols-outlined prs-play-icon">play_circle</span> <?php esc_html_e( 'Start Reading', 'politeia-reading' ); ?>
				</button>
				<button type="button" id="prs-sr-stop" class="prs-btn" style="display:none;">
				■ <?php esc_html_e( 'Stop Reading', 'politeia-reading' ); ?>
				</button>
			</td>
			</tr>

			<!-- End Page (aparece tras Stop) -->
			<tr id="prs-sr-row-end" style="display:none;">
			<th scope="row"><label for="prs-sr-end-page"><?php esc_html_e( 'End Page', 'politeia-reading' ); ?>*</label></th>
			<td>
				<input type="number" min="1" id="prs-sr-end-page" class="prs-sr-input" />
			</td>
			</tr>

			<!-- Guardar sesión (aparece tras Stop) -->
			<tr id="prs-sr-row-save" class="prs-sr-row--full" style="display:none;">
			<td colspan="2">
				<button type="button" id="prs-sr-save" class="prs-btn" disabled>
				<?php esc_html_e( 'Save Session', 'politeia-reading' ); ?>
				</button>
			</td>
			</tr>
		</tbody>
		</table>
	</div>
	</div>
		<?php
		return ob_get_clean();
	}
);
