<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section id="prs-reading-sessions" class="prs-book-sessions prs-reading-sessions">
	<?php if ( $sessions ) : ?>
		<?php $current_user_id = get_current_user_id(); ?>
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
						$note_button     = sprintf(
							'<button type="button" class="prs-sr-read-note-btn" data-session-id="%1$d" data-book-id="%2$d" data-user-id="%3$d" data-note="%4$s" data-start-page="%6$s" data-end-page="%7$s" data-chapter="%8$s" data-book-title="%9$s" data-note-label-read="%10$s" data-note-label-add="%11$s">%5$s</button>',
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
							esc_attr( $note_label_add )
						);
					}
					?>
					<tr>
						<td><?php echo esc_html( $i + 1 ); ?></td>
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
	<?php else : ?>
		<p class="prs-no-sessions"><?php esc_html_e( 'No sessions recorded for this book yet.', 'politeia-reading' ); ?></p>
	<?php endif; ?>
</section>
