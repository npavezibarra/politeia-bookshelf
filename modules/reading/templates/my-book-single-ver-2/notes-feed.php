<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<style>
	:root {
		--prs-notes-bg: #f7f7f8;
		--prs-notes-panel: #ffffff;
		--prs-notes-border: #e2e2e5;
		--prs-notes-text: #1f2933;
		--prs-notes-muted: #6b7280;
		--prs-notes-accent: #111827;
	}

	.prs-notes-feed,
	.prs-notes-feed * {
		box-sizing: border-box;
	}

	.prs-notes-feed {
		margin: 0;
		font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
		background: none;
		color: var(--prs-notes-text);
	}

	.prs-notes-feed__app {
		max-width: 1200px;
		margin: 0 auto;
		padding: 24px 0px;
	}

	.prs-notes-feed__layout {
		display: grid;
		grid-template-columns: 2fr 1fr;
		gap: 24px;
		align-items: start;
	}

	.prs-notes-feed__notes {
		display: flex;
		flex-direction: column;
		gap: 16px;
	}

	.prs-note {
		position: relative;
		background: var(--prs-notes-panel);
		/* border: 1px solid var(--prs-notes-border); */
		border-radius: 8px;
		padding: 16px 0px;
	}

	.prs-note__header {
		display: flex;
		justify-content: space-between;
		margin-bottom: 12px;
	}

	.prs-note__date {
		font-size: 0.75rem;
		color: var(--prs-notes-muted);
	}

	.prs-note__body {
		font-size: 0.95rem;
		line-height: 1.5;
	}

	.prs-note__text {
		min-height: 120px;
		padding: 12px;
		border: 1px solid var(--prs-notes-border);
		border-radius: 6px;
		background: #fafafa;
	}

	.prs-note__footer {
		display: flex;
		justify-content: flex-end;
		margin-top: 12px;
		font-size: 0.8rem;
		color: var(--prs-notes-muted);
	}

	.prs-note__rate-button {
		position: absolute;
		bottom: 12px;
		left: 12px;
		font-size: 0.75rem;
		padding: 4px 8px;
		border-radius: 999px;
		border: 1px solid var(--prs-notes-border);
		background: #ffffff;
		color: var(--prs-notes-muted);
		cursor: pointer;
	}

	.prs-notes-feed__sidebar {
		background: var(--prs-notes-panel);
		/* border: 1px solid var(--prs-notes-border); */
		border-radius: 8px;
		padding: 16px;
		position: sticky;
		top: 24px;
	}
	.prs-notes-feed__sidebar h2 {
		font-size: 18px;
		margin-bottom: 10px;
	}

	.prs-notes-feed__readers {
		display: grid;
		grid-template-columns: repeat(4, 1fr);
		gap: 12px;
		margin-bottom: 24px;
	}

	.prs-notes-feed__reader-avatar {
		width: 48px;
		height: 48px;
		border-radius: 50%;
		background: #d1d5db;
		justify-self: center;
	}

	@media (max-width: 900px) {
		.prs-notes-feed__layout {
			grid-template-columns: 1fr;
		}

		.prs-notes-feed__sidebar {
			position: static;
		}
	}
</style>

<?php
global $wpdb, $book, $user_id, $tbl_session_notes, $tbl_sessions;

if ( empty( $tbl_session_notes ) ) {
	$tbl_session_notes = $wpdb->prefix . 'politeia_read_ses_notes';
}
if ( empty( $tbl_sessions ) ) {
	$tbl_sessions = $wpdb->prefix . 'politeia_reading_sessions';
}

$notes = array();
if ( ! empty( $book->id ) && ! empty( $user_id ) ) {
	$notes = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT n.*, s.start_time, s.end_time
			 FROM {$tbl_session_notes} n
			 LEFT JOIN {$tbl_sessions} s
			   ON s.id = n.rs_id
			  AND s.book_id = n.book_id
			  AND s.user_id = n.user_id
			 WHERE n.book_id = %d
			   AND n.user_id = %d
			 ORDER BY n.created_at DESC
			 LIMIT 50",
			(int) $book->id,
			(int) $user_id
		)
	);
}
?>

<section class="prs-notes-feed">
	<div class="prs-notes-feed__app">
		<main class="prs-notes-feed__layout">
			<section class="prs-notes-feed__notes">
				<?php if ( ! empty( $notes ) ) : ?>
					<?php foreach ( $notes as $note ) : ?>
						<article class="prs-note">
							<header class="prs-note__header">
								<time class="prs-note__date">
									<?php
									$note_date = ! empty( $note->created_at ) ? strtotime( $note->created_at ) : 0;
									echo $note_date ? esc_html( date_i18n( 'M j, Y', $note_date ) ) : esc_html__( 'date', 'politeia-reading' );
									?>
								</time>
							</header>
							<div class="prs-note__body">
								<div class="prs-note__text"><?php echo esc_html( (string) $note->note ); ?></div>
							</div>
							<button class="prs-note__rate-button" type="button"><?php esc_html_e( 'Rate', 'politeia-reading' ); ?></button>
							<footer class="prs-note__footer"></footer>
						</article>
					<?php endforeach; ?>
				<?php else : ?>
					<p class="prs-note__empty"><?php esc_html_e( 'You have not taken any notes on this book yet', 'politeia-reading' ); ?></p>
				<?php endif; ?>
			</section>

			<aside class="prs-notes-feed__sidebar">
				<h2><?php esc_html_e( 'Other Readers', 'politeia-reading' ); ?></h2>
				<div class="prs-notes-feed__readers">
					<span class="prs-notes-feed__reader-avatar"></span>
					<span class="prs-notes-feed__reader-avatar"></span>
					<span class="prs-notes-feed__reader-avatar"></span>
					<span class="prs-notes-feed__reader-avatar"></span>
				</div>
			</aside>
		</main>
	</div>
</section>
