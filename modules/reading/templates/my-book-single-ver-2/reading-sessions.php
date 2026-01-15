<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section id="prs-reading-sessions" class="prs-book-sessions prs-reading-sessions">
	<?php if ( $sessions ) : ?>
		<?php $current_user_id = get_current_user_id(); ?>
		<?php
		$session_index_map = array();
		if ( ! empty( $sessions ) ) {
			$ordered_sessions = $sessions;
			usort(
				$ordered_sessions,
				static function ( $a, $b ) {
					$a_time = ! empty( $a->start_time ) ? strtotime( (string) $a->start_time ) : 0;
					$b_time = ! empty( $b->start_time ) ? strtotime( (string) $b->start_time ) : 0;
					if ( $a_time === $b_time ) {
						return (int) $a->id <=> (int) $b->id;
					}
					return $a_time <=> $b_time;
				}
			);

			$index = 1;
			foreach ( $ordered_sessions as $ordered_session ) {
				$session_index_map[ (int) $ordered_session->id ] = $index;
				$index++;
			}
		}
		?>
		<table class="prs-table prs-sessions-table">
			<thead>
				<tr>
					<th>#</th>
					<th><?php esc_html_e( 'Start Time', 'politeia-reading' ); ?></th>
					<th><?php esc_html_e( 'End Time', 'politeia-reading' ); ?></th>
					<th><?php esc_html_e( 'Note', 'politeia-reading' ); ?></th>
					<th><?php esc_html_e( 'Initial Page', 'politeia-reading' ); ?></th>
					<th><?php esc_html_e( 'End Page', 'politeia-reading' ); ?></th>
					<th><?php esc_html_e( 'Total Pages', 'politeia-reading' ); ?></th>
					<th><?php esc_html_e( 'Duration', 'politeia-reading' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $sessions as $i => $s ) :
					$start_display = '—';
					if ( $s->start_time ) {
						$start_timestamp = strtotime( $s->start_time );
						if ( $start_timestamp ) {
							$start_display  = '<div class="prs-sr-date">';
							$start_display .= '<div class="prs-sr-time">' . esc_html( date_i18n( 'g:i a', $start_timestamp ) ) . '</div>';
							$start_display .= '<div class="prs-sr-date-line">' . esc_html( date_i18n( 'F j, Y', $start_timestamp ) ) . '</div>';
							$start_display .= '</div>';
						}
					}

					$end_display = '—';
					if ( $s->end_time ) {
						$end_timestamp = strtotime( $s->end_time );
						if ( $end_timestamp ) {
							$end_display  = '<div class="prs-sr-date">';
							$end_display .= '<div class="prs-sr-time">' . esc_html( date_i18n( 'g:i a', $end_timestamp ) ) . '</div>';
							$end_display .= '<div class="prs-sr-date-line">' . esc_html( date_i18n( 'F j, Y', $end_timestamp ) ) . '</div>';
							$end_display .= '</div>';
						}
					}
					$duration_str  = '—';
					if ( $s->start_time && $s->end_time ) {
						$duration = strtotime( $s->end_time ) - strtotime( $s->start_time );
						if ( $duration < 0 ) {
							$duration = 0;
						}
						$minutes = floor( $duration / 60 );
						$seconds = $duration % 60;
						/* translators: 1: minutes, 2: seconds. */
						$duration_str = sprintf( _x( '%1$d min %2$02d sec', 'reading session duration', 'politeia-reading' ), $minutes, $seconds );
					}
					$start_page  = isset( $s->start_page ) ? (int) $s->start_page : null;
					$end_page    = isset( $s->end_page ) ? (int) $s->end_page : null;
					$total_pages = null;
					if ( null !== $start_page && null !== $end_page ) {
						$total_pages = $end_page - $start_page;
					}

					$note_button = '—';
					$note_value  = isset( $s->note ) ? trim( (string) $s->note ) : '';
					if ( ! empty( $s->id ) && $current_user_id ) {
						$note_label_read = esc_html__( 'Read Note', 'politeia-reading' );
						$note_label_add  = esc_html__( 'Add Note', 'politeia-reading' );
						$note_label      = '' !== $note_value ? $note_label_read : $note_label_add;
						$start_attr      = ( null !== $start_page && $start_page >= 0 ) ? (string) $start_page : '';
						$end_attr        = ( null !== $end_page && $end_page >= 0 ) ? (string) $end_page : '';
						$chapter_attr    = isset( $s->chapter_name ) ? trim( (string) $s->chapter_name ) : '';
						$book_title_attr = isset( $book->title ) ? trim( (string) $book->title ) : '';
						$session_index   = isset( $session_index_map[ (int) $s->id ] ) ? (int) $session_index_map[ (int) $s->id ] : ( $i + 1 );
						$note_button     = sprintf(
							'<button type="button" class="prs-sr-read-note-btn" data-session-id="%1$d" data-session-number="%12$d" data-book-id="%2$d" data-user-id="%3$d" data-note="%4$s" data-start-page="%6$s" data-end-page="%7$s" data-chapter="%8$s" data-book-title="%9$s" data-note-label-read="%10$s" data-note-label-add="%11$s">%5$s</button>',
							(int) $s->id,
							(int) $s->book_id,
							(int) $current_user_id,
							esc_attr( $note_value ),
							$note_label,
							esc_attr( $start_attr ),
							esc_attr( $end_attr ),
							esc_attr( $chapter_attr ),
							esc_attr( $book_title_attr ),
							esc_attr( $note_label_read ),
							esc_attr( $note_label_add ),
							(int) $session_index
						);
					}
					?>
					<tr>
						<td><?php echo esc_html( $session_index ); ?></td>
						<td><?php echo wp_kses_post( $start_display ); ?></td>
						<td><?php echo wp_kses_post( $end_display ); ?></td>
						<td><?php echo wp_kses_post( $note_button ); ?></td>
						<td><?php echo esc_html( ( null !== $start_page && $start_page >= 0 ) ? $start_page : '—' ); ?></td>
						<td><?php echo esc_html( ( null !== $end_page && $end_page >= 0 ) ? $end_page : '—' ); ?></td>
						<td><?php echo esc_html( ( null !== $total_pages && $total_pages > 0 ) ? $total_pages : '—' ); ?></td>
						<td><?php echo esc_html( $duration_str ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<div class="prs-sessions-mobile">
			<?php foreach ( $sessions as $i => $s ) :
				$session_index = isset( $session_index_map[ (int) $s->id ] ) ? (int) $session_index_map[ (int) $s->id ] : ( $i + 1 );
				$start_time = $s->start_time ? strtotime( $s->start_time ) : 0;
				$end_time = $s->end_time ? strtotime( $s->end_time ) : 0;
				$start_time_label = $start_time ? date_i18n( 'g:i a', $start_time ) : '—';
				$end_time_label = $end_time ? date_i18n( 'g:i a', $end_time ) : '—';
				$date_label = $start_time ? date_i18n( 'F j, Y', $start_time ) : esc_html__( 'Date', 'politeia-reading' );
				$start_page  = isset( $s->start_page ) ? (int) $s->start_page : null;
				$end_page    = isset( $s->end_page ) ? (int) $s->end_page : null;
				$total_pages = null;
				if ( null !== $start_page && null !== $end_page ) {
					$total_pages = $end_page - $start_page;
				}
				$duration_str  = '—';
				if ( $s->start_time && $s->end_time ) {
					$duration = strtotime( $s->end_time ) - strtotime( $s->start_time );
					if ( $duration < 0 ) {
						$duration = 0;
					}
					$minutes = floor( $duration / 60 );
					$seconds = $duration % 60;
					$duration_str = sprintf( _x( '%1$d min %2$02d sec', 'reading session duration', 'politeia-reading' ), $minutes, $seconds );
				}
				?>
				<div class="prs-sessions-mobile__card">
					<h4 class="prs-sessions-mobile__title"><?php echo esc_html( sprintf( __( 'Session %d', 'politeia-reading' ), $session_index ) ); ?></h4>
					<div class="prs-sessions-mobile__date"><?php echo esc_html( $date_label ); ?></div>
					<div class="prs-sessions-mobile__times">
						<span><?php echo esc_html( $start_time_label ); ?></span>
						<span><?php echo esc_html( $end_time_label ); ?></span>
					</div>
					<div class="prs-sessions-mobile__pages">
						<div><?php esc_html_e( 'Initial Page:', 'politeia-reading' ); ?> <strong><?php echo esc_html( ( null !== $start_page && $start_page >= 0 ) ? $start_page : '—' ); ?></strong></div>
						<div><?php esc_html_e( 'End Page:', 'politeia-reading' ); ?> <strong><?php echo esc_html( ( null !== $end_page && $end_page >= 0 ) ? $end_page : '—' ); ?></strong></div>
						<div><?php esc_html_e( 'Total Pages:', 'politeia-reading' ); ?> <strong><?php echo esc_html( ( null !== $total_pages && $total_pages > 0 ) ? $total_pages : '—' ); ?></strong></div>
					</div>
					<div class="prs-sessions-mobile__duration"><?php echo esc_html( $duration_str ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<style>
			.prs-reading-empty {
				margin: 24px 0 12px;
				display: flex;
				justify-content: center;
			}
			.prs-reading-empty__wrap {
				width: 100%;
				max-width: 520px;
				text-align: center;
			}
			.prs-reading-empty__card {
				border: 2px dotted #cbd5e1;
				border-radius: 16px;
				background: #ffffff;
				padding: 32px 24px;
				display: flex;
				flex-direction: column;
				align-items: center;
				gap: 6px;
				transition: border-color 0.2s ease, background 0.2s ease;
			}
			.prs-reading-empty__card:hover {
				border-color: #94a3b8;
				background: rgba(248, 250, 252, 0.7);
			}
			.prs-reading-empty__icon {
				margin-bottom: 12px;
				background: #f1f5f9;
				padding: 16px;
				border-radius: 999px;
				color: #94a3b8;
			}
			.prs-reading-empty__title {
				font-size: 20px;
				font-weight: 600;
				color: #1f2937;
				margin: 0;
			}
			.prs-reading-empty__subtitle {
				font-size: 14px;
				color: #6b7280;
				margin: 0 0 18px;
			}
			.prs-reading-empty__footer {
				margin-top: 16px;
				font-size: 12px;
				color: #94a3b8;
			}
			.prs-reading-empty #politeia-open-reading-plan {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				gap: 8px;
				padding: 12px 22px;
				border-radius: 4px;
				border: 1px solid #000000;
				background: #000000;
				color: #ffffff;
				font-weight: 600;
				letter-spacing: 0.01em;
				text-transform: none;
				cursor: pointer;
				transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
			}
			.prs-reading-empty #politeia-open-reading-plan:hover,
			.prs-reading-empty #politeia-open-reading-plan:focus {
				background: #27272a;
				color: #ffffff;
			}
			.prs-reading-empty #politeia-open-reading-plan:active {
				transform: scale(0.98);
			}
			.prs-reading-empty #politeia-open-reading-plan svg {
				width: 20px;
				height: 20px;
			}
		</style>
		<div class="prs-reading-empty">
			<div class="prs-reading-empty__wrap">
				<div class="prs-reading-empty__card">
					<div class="prs-reading-empty__icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
							<path d="M8 7h6"/>
							<path d="M8 11h8"/>
						</svg>
					</div>
					<h2 class="prs-reading-empty__title"><?php esc_html_e( 'No tienes sesiones de lectura de este libro.', 'politeia-reading' ); ?></h2>
					<p class="prs-reading-empty__subtitle"><?php esc_html_e( '¿Te gustaría iniciar un plan para terminarlo?', 'politeia-reading' ); ?></p>
					<?php
					if ( isset( $book->id, $ub->id ) ) {
						$shortcode_bits = array(
							'politeia_reading_plan',
							'user_book_id="' . (int) $ub->id . '"',
							'book_id="' . (int) $book->id . '"',
						);

						if ( ! empty( $book->title ) ) {
							$shortcode_bits[] = 'book_title="' . esc_attr( (string) $book->title ) . '"';
						}

						if ( isset( $book_authors ) && '' !== $book_authors ) {
							$shortcode_bits[] = 'book_author="' . esc_attr( (string) $book_authors ) . '"';
						}

						if ( ! empty( $ub->pages ) ) {
							$shortcode_bits[] = 'book_pages="' . (int) $ub->pages . '"';
						}

						if ( isset( $cover_url ) && '' !== $cover_url ) {
							$shortcode_bits[] = 'book_cover="' . esc_url( (string) $cover_url ) . '"';
						}

						echo do_shortcode( '[' . implode( ' ', $shortcode_bits ) . ']' );
					}
					?>
				</div>
				<p class="prs-reading-empty__footer"><?php esc_html_e( 'Personaliza tu ritmo y alcanza tus metas literarias', 'politeia-reading' ); ?></p>
			</div>
		</div>
		<script>
			(function() {
				const btn = document.getElementById('politeia-open-reading-plan');
				if (!btn || btn.dataset.emptyState === '1') {
					return;
				}
				btn.dataset.emptyState = '1';
				btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>' + '<?php echo esc_js( __( 'Iniciar Plan de Lectura', 'politeia-reading' ) ); ?>';
			})();
		</script>
	<?php endif; ?>
</section>
