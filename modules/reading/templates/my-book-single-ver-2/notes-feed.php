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

	.prs-note__session {
		font-weight: 700;
		font-size: 12px;
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
		width: 100%;
		resize: vertical;
		overflow: hidden;
	}

	.prs-note__footer {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 12px;
		margin-top: 12px;
		font-size: 0.8rem;
		color: var(--prs-notes-muted);
	}

	.prs-note__actions {
		display: flex;
		gap: 8px;
		align-items: center;
	}

	.prs-note__rate-button,
	.prs-note__edit-button {
		font-size: 0.75rem;
		padding: 4px 8px;
		border-radius: 999px;
		border: 1px solid var(--prs-notes-border);
		background: #ffffff;
		color: var(--prs-notes-muted);
		cursor: pointer;
	}

	.prs-note__composition {
		flex: 1 1 auto;
		max-width: 240px;
		height: 6px;
		border-radius: 999px;
		overflow: hidden;
		display: flex;
		background: #f1f5f9;
	}

	.prs-note__composition.is-empty {
		opacity: 0.45;
	}

	.prs-note__composition-seg--joy { background: #facc15; }
	.prs-note__composition-seg--sorrow { background: #60a5fa; }
	.prs-note__composition-seg--fear { background: #a855f7; }
	.prs-note__composition-seg--fascination { background: #818cf8; }
	.prs-note__composition-seg--anger { background: #f87171; }
	.prs-note__composition-seg--serenity { background: #34d399; }
	.prs-note__composition-seg--enlightenment { background: #fbbf24; }

	.prs-note-modal {
		position: fixed;
		inset: 0;
		background: rgba(15, 23, 42, 0.35);
		display: none;
		align-items: center;
		justify-content: center;
		padding: 24px;
		z-index: 10000;
	}

	.prs-note-modal.is-active {
		display: flex;
	}

	.prs-note-modal__panel {
		width: 100%;
		max-width: 420px;
		background: #fff;
		border-radius: 28px;
		padding: 24px;
		box-shadow: 0 24px 60px rgba(15, 23, 42, 0.2);
	}

	.prs-note-modal__rows {
		display: grid;
		gap: 16px;
		margin-bottom: 20px;
	}

	.prs-note-modal__row-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 6px;
	}

	.prs-note-modal__label {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		font-size: 11px;
		font-weight: 700;
		letter-spacing: 0.08em;
		text-transform: uppercase;
	}

	.prs-note-modal__count {
		font-size: 10px;
		font-weight: 700;
		color: #cbd5f5;
	}

	.prs-note-modal__bar {
		display: flex;
		gap: 6px;
		height: 12px;
	}

	.prs-note-modal__segment {
		flex: 1 1 0;
		border-radius: 999px;
		border: none;
		cursor: pointer;
		background: #e2e8f0;
		transition: transform 0.2s ease, background 0.2s ease;
	}

	.prs-note-modal__segment:hover {
		background: #cbd5e1;
	}

	.prs-note-modal__segment.is-active {
		transform: scaleY(1.2);
	}

	.prs-note-modal__segment--joy.is-active { background: #facc15; }
	.prs-note-modal__segment--sorrow.is-active { background: #60a5fa; }
	.prs-note-modal__segment--fear.is-active { background: #a855f7; }
	.prs-note-modal__segment--fascination.is-active { background: #818cf8; }
	.prs-note-modal__segment--anger.is-active { background: #f87171; }
	.prs-note-modal__segment--serenity.is-active { background: #34d399; }
	.prs-note-modal__segment--enlightenment.is-active { background: #fbbf24; }

	.prs-note-modal__actions {
		display: flex;
		gap: 12px;
	}

	.prs-note-modal__reset {
		border: none;
		background: #f1f5f9;
		color: #64748b;
		border-radius: 16px;
		padding: 10px 12px;
		cursor: pointer;
		font-size: 12px;
	}

	.prs-note-modal__save {
		flex: 1 1 auto;
		border: none;
		border-radius: 16px;
		padding: 12px 16px;
		font-weight: 700;
		font-size: 12px;
		cursor: pointer;
		background: #0f172a;
		color: #fff;
	}

	.prs-note-modal__save.is-disabled {
		background: #e2e8f0;
		color: #94a3b8;
		cursor: not-allowed;
	}

	.prs-note-modal__save.is-success {
		background: #10b981;
		color: #fff;
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
		overflow: hidden;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		text-decoration: none;
	}

	.prs-notes-feed__reader-avatar img {
		width: 100%;
		height: 100%;
		object-fit: cover;
		display: block;
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
$tbl_ub = $wpdb->prefix . 'politeia_user_books';

$notes = array();
if ( ! empty( $book->id ) && ! empty( $user_id ) ) {
	$notes = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT n.*, s.start_time, s.end_time, s.start_page, s.end_page
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

$note_index_map = array();
if ( ! empty( $notes ) ) {
	$ordered_notes = $notes;
	usort(
		$ordered_notes,
		static function ( $a, $b ) {
			$a_time = ! empty( $a->created_at ) ? strtotime( (string) $a->created_at ) : 0;
			$b_time = ! empty( $b->created_at ) ? strtotime( (string) $b->created_at ) : 0;
			if ( $a_time === $b_time ) {
				return (int) $a->id <=> (int) $b->id;
			}
			return $a_time <=> $b_time;
		}
	);

	$index = 1;
	foreach ( $ordered_notes as $ordered_note ) {
		$note_index_map[ (int) $ordered_note->id ] = $index;
		$index++;
	}
}

$other_readers = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT DISTINCT user_id
		 FROM {$tbl_ub}
		 WHERE book_id = %d
		 AND deleted_at IS NULL
		 AND user_id <> %d
		 ORDER BY user_id ASC
		 LIMIT 8",
		(int) $book->id,
		(int) $user_id
	)
);
?>

<section class="prs-notes-feed">
	<div class="prs-notes-feed__app">
		<main class="prs-notes-feed__layout">
			<section class="prs-notes-feed__notes">
				<?php if ( ! empty( $notes ) ) : ?>
				<?php foreach ( $notes as $note ) : ?>
					<?php
					$emotion_keys = array( 'joy', 'sorrow', 'fear', 'fascination', 'anger', 'serenity', 'enlightenment' );
					$emotion_values = array();
					$total_emotion = 0;
					$decoded_emotions = $note->emotions ? json_decode( (string) $note->emotions, true ) : array();
					if ( ! is_array( $decoded_emotions ) ) {
						$decoded_emotions = array();
					}
					foreach ( $emotion_keys as $key ) {
						$value = isset( $decoded_emotions[ $key ] ) ? (int) $decoded_emotions[ $key ] : 0;
						if ( $value < 0 ) {
							$value = 0;
						} elseif ( $value > 5 ) {
							$value = 5;
						}
						$emotion_values[ $key ] = $value;
						$total_emotion += $value;
					}
					?>
					<article class="prs-note" data-note-id="<?php echo esc_attr( (string) $note->id ); ?>" data-rs-id="<?php echo esc_attr( (string) $note->rs_id ); ?>">
					<?php
					$session_start_page = isset( $note->start_page ) ? (int) $note->start_page : 0;
					$session_end_page   = isset( $note->end_page ) ? (int) $note->end_page : 0;
					$note_index = isset( $note_index_map[ (int) $note->id ] ) ? (int) $note_index_map[ (int) $note->id ] : 0;
					$session_label      = $note_index
						? sprintf( __( 'Session #%d', 'politeia-reading' ), $note_index )
						: __( 'Session', 'politeia-reading' );
					$page_range         = ( $session_start_page || $session_end_page )
						? sprintf( __( 'pages %1$s - %2$s', 'politeia-reading' ), $session_start_page ?: '—', $session_end_page ?: '—' )
						: '';
					?>
					<header class="prs-note__header">
						<div class="prs-note__session">
							<?php
							echo esc_html( $session_label . ( $page_range ? ', ' . $page_range : '' ) );
							?>
						</div>
						<time class="prs-note__date">
							<?php
							$note_date = ! empty( $note->created_at ) ? strtotime( $note->created_at ) : 0;
							echo $note_date ? esc_html( date_i18n( 'M j, Y', $note_date ) ) : esc_html__( 'Date', 'politeia-reading' );
							?>
						</time>
					</header>
						<div class="prs-note__body">
							<textarea class="prs-note__text" readonly="readonly"><?php echo esc_textarea( (string) $note->note ); ?></textarea>
						</div>
						<footer class="prs-note__footer">
							<div class="prs-note__composition<?php echo $total_emotion > 0 ? '' : ' is-empty'; ?>" aria-label="<?php esc_attr_e( 'Emotional composition', 'politeia-reading' ); ?>">
								<?php if ( $total_emotion > 0 ) : ?>
									<?php foreach ( $emotion_values as $key => $value ) : ?>
										<?php if ( $value > 0 ) : ?>
											<div class="prs-note__composition-seg--<?php echo esc_attr( $key ); ?>" style="width: <?php echo esc_attr( number_format( ( $value / $total_emotion ) * 100, 2, '.', '' ) ); ?>%;"></div>
										<?php endif; ?>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
							<div class="prs-note__actions">
								<button class="prs-note__edit-button" type="button">
									<?php esc_html_e( 'Edit', 'politeia-reading' ); ?>
								</button>
								<button
									class="prs-note__rate-button"
									type="button"
									data-note-id="<?php echo esc_attr( (string) $note->id ); ?>"
									data-emotions="<?php echo esc_attr( $note->emotions ? (string) $note->emotions : '' ); ?>"
								>
									<?php esc_html_e( 'Rate', 'politeia-reading' ); ?>
								</button>
							</div>
						</footer>
					</article>
				<?php endforeach; ?>
				<?php else : ?>
					<p class="prs-note__empty"><?php esc_html_e( 'You have not taken any notes on this book yet', 'politeia-reading' ); ?></p>
				<?php endif; ?>
			</section>

			<aside class="prs-notes-feed__sidebar">
				<h2><?php esc_html_e( 'Other Readers', 'politeia-reading' ); ?></h2>
				<div class="prs-notes-feed__readers">
					<?php foreach ( $other_readers as $reader ) : ?>
						<?php
						$avatar_url = get_avatar_url( (int) $reader->user_id, array( 'size' => 48 ) );
						$reader_user = get_userdata( (int) $reader->user_id );
						$profile_url = $reader_user ? home_url( '/members/' . $reader_user->user_login . '/' ) : '';
						?>
						<?php if ( $avatar_url && $profile_url ) : ?>
							<a class="prs-notes-feed__reader-avatar" href="<?php echo esc_url( $profile_url ); ?>">
								<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php esc_attr_e( 'Reader avatar', 'politeia-reading' ); ?>">
							</a>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</aside>
		</main>
	</div>
</section>

<div class="prs-note-modal" id="prs-note-modal" aria-hidden="true">
	<div class="prs-note-modal__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Rate emotions', 'politeia-reading' ); ?>">
		<div class="prs-note-modal__rows" id="prs-note-rows"></div>
		<div class="prs-note-modal__actions">
			<button class="prs-note-modal__reset" type="button" id="prs-note-reset"><?php esc_html_e( 'Reset', 'politeia-reading' ); ?></button>
			<button class="prs-note-modal__save is-disabled" type="button" id="prs-note-save" disabled="disabled"><?php esc_html_e( 'Save Emotional Rating', 'politeia-reading' ); ?></button>
		</div>
	</div>
</div>

<script>
	(function () {
		if (typeof window === "undefined") return;
		const I18N = <?php echo wp_json_encode( array(
			'emotion_joy'          => __( 'Joy', 'politeia-reading' ),
			'emotion_sorrow'       => __( 'Sorrow', 'politeia-reading' ),
			'emotion_fear'         => __( 'Fear', 'politeia-reading' ),
			'emotion_fascination'  => __( 'Fascination', 'politeia-reading' ),
			'emotion_anger'        => __( 'Anger', 'politeia-reading' ),
			'emotion_serenity'     => __( 'Serenity', 'politeia-reading' ),
			'emotion_enlightenment'=> __( 'Enlightenment', 'politeia-reading' ),
			'logged_impression'    => __( 'Logged Impression', 'politeia-reading' ),
			'save_rating'          => __( 'Save Emotional Rating', 'politeia-reading' ),
			'save_label'           => __( 'Save', 'politeia-reading' ),
			'edit_label'           => __( 'Edit', 'politeia-reading' ),
			'note_required'        => __( 'Please enter a note before saving.', 'politeia-reading' ),
			'note_unavailable'     => __( 'Unable to save note right now.', 'politeia-reading' ),
			'save_failed'          => __( 'Save failed.', 'politeia-reading' ),
		) ); ?>;
		const t = (key, fallback) => (I18N && I18N[key]) ? I18N[key] : fallback;
		const modal = document.getElementById("prs-note-modal");
		const rowsWrap = document.getElementById("prs-note-rows");
		const resetBtn = document.getElementById("prs-note-reset");
		const saveBtn = document.getElementById("prs-note-save");
		const noteButtons = document.querySelectorAll(".prs-note__rate-button");
		const editButtons = document.querySelectorAll(".prs-note__edit-button");
		if (!modal || !rowsWrap || !resetBtn || !saveBtn || !noteButtons.length) return;

		const EMOTIONS = [
			{ id: "joy", label: t("emotion_joy", "Joy"), emoji: "😄", color: "joy" },
			{ id: "sorrow", label: t("emotion_sorrow", "Sorrow"), emoji: "😢", color: "sorrow" },
			{ id: "fear", label: t("emotion_fear", "Fear"), emoji: "😱", color: "fear" },
			{ id: "fascination", label: t("emotion_fascination", "Fascination"), emoji: "🤯", color: "fascination" },
			{ id: "anger", label: t("emotion_anger", "Anger"), emoji: "😡", color: "anger" },
			{ id: "serenity", label: t("emotion_serenity", "Serenity"), emoji: "😌", color: "serenity" },
			{ id: "enlightenment", label: t("emotion_enlightenment", "Enlightenment"), emoji: "✨", color: "enlightenment" },
		];

		let activeNoteId = null;
		let ratings = {};
		let submitted = false;

		function resetRatings() {
			ratings = {
				joy: 0,
				sorrow: 0,
				fear: 0,
				fascination: 0,
				anger: 0,
				serenity: 0,
				enlightenment: 0,
			};
			submitted = false;
		}

		function totalIntensity() {
			return Object.values(ratings).reduce((a, b) => a + b, 0);
		}

		function buildRows() {
			rowsWrap.innerHTML = "";
			EMOTIONS.forEach(({ id, label, emoji, color }) => {
				const row = document.createElement("div");
				const header = document.createElement("div");
				header.className = "prs-note-modal__row-header";
				const labelEl = document.createElement("div");
				labelEl.className = "prs-note-modal__label";
				labelEl.innerHTML = `<span>${emoji}</span><span>${label}</span>`;
				const countEl = document.createElement("span");
				countEl.className = "prs-note-modal__count";
				countEl.dataset.emotion = id;
				header.appendChild(labelEl);
				header.appendChild(countEl);

				const bar = document.createElement("div");
				bar.className = "prs-note-modal__bar";
				for (let i = 1; i <= 5; i++) {
					const segment = document.createElement("button");
					segment.type = "button";
					segment.className = `prs-note-modal__segment prs-note-modal__segment--${color}`;
					segment.dataset.emotion = id;
					segment.dataset.value = String(i);
					segment.addEventListener("click", () => {
						const value = parseInt(segment.dataset.value, 10);
						ratings[id] = ratings[id] === value ? 0 : value;
						submitted = false;
						renderState();
					});
					bar.appendChild(segment);
				}

				row.appendChild(header);
				row.appendChild(bar);
				rowsWrap.appendChild(row);
			});
		}

		function renderState() {
			document.querySelectorAll(".prs-note-modal__segment").forEach((el) => {
				const emotion = el.dataset.emotion;
				const value = parseInt(el.dataset.value, 10);
				if (ratings[emotion] >= value) {
					el.classList.add("is-active");
				} else {
					el.classList.remove("is-active");
				}
			});
			document.querySelectorAll(".prs-note-modal__count").forEach((el) => {
				const emotion = el.dataset.emotion;
				const value = ratings[emotion] || 0;
				el.textContent = value > 0 ? `${value}/5` : "";
			});

			const total = totalIntensity();
			if (submitted) {
				saveBtn.classList.add("is-success");
				saveBtn.classList.remove("is-disabled");
				saveBtn.disabled = false;
				saveBtn.textContent = t("logged_impression", "Logged Impression");
			} else {
				saveBtn.classList.remove("is-success");
				saveBtn.textContent = t("save_rating", "Save Emotional Rating");
				if (total === 0) {
					saveBtn.classList.add("is-disabled");
					saveBtn.disabled = true;
				} else {
					saveBtn.classList.remove("is-disabled");
					saveBtn.disabled = false;
				}
			}
		}

		function renderComposition(noteId, values) {
			const note = document.querySelector(`.prs-note[data-note-id="${noteId}"]`);
			if (!note) return;
			const bar = note.querySelector(".prs-note__composition");
			if (!bar) return;
			const total = Object.values(values).reduce((a, b) => a + b, 0);
			bar.innerHTML = "";
			if (total === 0) {
				bar.classList.add("is-empty");
				return;
			}
			bar.classList.remove("is-empty");
			Object.keys(values).forEach((key) => {
				const value = values[key] || 0;
				if (value <= 0) return;
				const segment = document.createElement("div");
				segment.className = `prs-note__composition-seg--${key}`;
				segment.style.width = `${(value / total) * 100}%`;
				bar.appendChild(segment);
			});
		}

		function openModal(noteId, emotionsValue) {
			activeNoteId = noteId;
			resetRatings();
			if (emotionsValue) {
				try {
					const parsed = JSON.parse(emotionsValue);
					if (parsed && typeof parsed === "object") {
						Object.keys(ratings).forEach((key) => {
							const value = parseInt(parsed[key], 10);
							if (!Number.isNaN(value)) {
								ratings[key] = Math.max(0, Math.min(5, value));
							}
						});
					}
				} catch (e) {
					resetRatings();
				}
			}
			buildRows();
			renderState();
			modal.classList.add("is-active");
			modal.setAttribute("aria-hidden", "false");
		}

		function closeModal() {
			modal.classList.remove("is-active");
			modal.setAttribute("aria-hidden", "true");
		}

		noteButtons.forEach((button) => {
			button.addEventListener("click", () => {
				const noteId = button.dataset.noteId;
				if (!noteId) return;
				openModal(noteId, button.dataset.emotions || "");
			});
		});

		editButtons.forEach((button) => {
			button.addEventListener("click", () => {
				const note = button.closest(".prs-note");
				if (!note) return;
				const textarea = note.querySelector(".prs-note__text");
				if (!textarea) return;
				const isEditing = button.dataset.state === "editing";
				if (!isEditing) {
					button.dataset.state = "editing";
					button.textContent = t("save_label", "Save");
					textarea.removeAttribute("readonly");
					textarea.style.height = "auto";
					textarea.style.height = `${textarea.scrollHeight}px`;
					textarea.focus();
					textarea.setSelectionRange(textarea.value.length, textarea.value.length);
					if (!textarea.dataset.autosize) {
						textarea.dataset.autosize = "1";
						textarea.addEventListener("input", () => {
							textarea.style.height = "auto";
							textarea.style.height = `${textarea.scrollHeight}px`;
						});
					}
					return;
				}

				const noteText = textarea.value.trim();
				if (!noteText) {
					window.alert(t("note_required", "Please enter a note before saving."));
					textarea.focus();
					return;
				}

				const rsId = note.dataset.rsId;
				const bookId = window.PRS_BOOK && window.PRS_BOOK.book_id ? window.PRS_BOOK.book_id : null;
				const nonce = window.PRS_BOOK && window.PRS_BOOK.reading_nonce ? window.PRS_BOOK.reading_nonce : null;
				const ajaxUrl = window.PRS_BOOK && window.PRS_BOOK.ajax_url ? window.PRS_BOOK.ajax_url : null;
				if (!rsId || !bookId || !nonce || !ajaxUrl) {
					window.alert(t("note_unavailable", "Unable to save note right now."));
					return;
				}

				const payload = new FormData();
				payload.append("action", "politeia_save_session_note");
				payload.append("nonce", nonce);
				payload.append("rs_id", String(rsId));
				payload.append("book_id", String(bookId));
				payload.append("note", textarea.value);

				button.disabled = true;
				fetch(ajaxUrl, { method: "POST", body: payload, credentials: "same-origin" })
					.then((resp) => resp.json())
					.then((data) => {
						if (!data || !data.success) {
							throw new Error(data && data.data ? data.data : t("save_failed", "Save failed."));
						}
						textarea.setAttribute("readonly", "readonly");
						button.dataset.state = "";
						button.textContent = t("edit_label", "Edit");
					})
					.catch((err) => {
						window.alert(err && err.message ? err.message : t("save_failed", "Save failed."));
					})
					.finally(() => {
						button.disabled = false;
					});
			});
		});

		modal.addEventListener("click", (event) => {
			if (event.target === modal) {
				closeModal();
			}
		});

		resetBtn.addEventListener("click", () => {
			resetRatings();
			renderState();
		});

		saveBtn.addEventListener("click", async () => {
			if (saveBtn.disabled || !activeNoteId) return;
			const ajaxUrl = (typeof window.ajaxurl === "string" && window.ajaxurl)
				? window.ajaxurl
				: (window.PRS_BOOK && PRS_BOOK.ajax_url) || (window.PRS_SR && PRS_SR.ajax_url) || "";
			const nonce = (window.PRS_SR && PRS_SR.nonce) || "";
			if (!ajaxUrl || !nonce) return;

			const payload = new FormData();
			payload.append("action", "politeia_save_note_emotions");
			payload.append("nonce", nonce);
			payload.append("note_id", String(activeNoteId));
			payload.append("emotions", JSON.stringify(ratings));

			try {
				const response = await fetch(ajaxUrl, { method: "POST", body: payload, credentials: "same-origin" });
				const json = await response.json();
				if (json && json.success) {
					submitted = true;
					renderState();
					const btn = document.querySelector(`.prs-note__rate-button[data-note-id="${activeNoteId}"]`);
					if (btn) {
						btn.dataset.emotions = JSON.stringify(ratings);
					}
					renderComposition(activeNoteId, ratings);
					closeModal();
				}
			} catch (e) {
				// Keep modal open on failure.
			}
		});
		const noteTextareas = document.querySelectorAll(".prs-note__text");
		noteTextareas.forEach((textarea) => {
			textarea.style.height = "auto";
			textarea.style.height = `${textarea.scrollHeight}px`;
		});
	})();
</script>
