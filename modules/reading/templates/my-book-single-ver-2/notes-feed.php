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
		justify-content: space-between;
		align-items: center;
		gap: 12px;
		margin-top: 12px;
		font-size: 0.8rem;
		color: var(--prs-notes-muted);
	}

	.prs-note__rate-button {
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
					<article class="prs-note" data-note-id="<?php echo esc_attr( (string) $note->id ); ?>">
					<?php
					$session_start_page = isset( $note->start_page ) ? (int) $note->start_page : 0;
					$session_end_page   = isset( $note->end_page ) ? (int) $note->end_page : 0;
					$session_label      = $note->rs_id ? sprintf( 'Session #%d', (int) $note->rs_id ) : __( 'Session', 'politeia-reading' );
					$page_range         = ( $session_start_page || $session_end_page )
						? sprintf( '%s - %s', $session_start_page ?: '—', $session_end_page ?: '—' )
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
							echo $note_date ? esc_html( date_i18n( 'M j, Y', $note_date ) ) : esc_html__( 'date', 'politeia-reading' );
							?>
						</time>
					</header>
						<div class="prs-note__body">
							<div class="prs-note__text"><?php echo esc_html( (string) $note->note ); ?></div>
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
							<button
								class="prs-note__rate-button"
								type="button"
								data-note-id="<?php echo esc_attr( (string) $note->id ); ?>"
								data-emotions="<?php echo esc_attr( $note->emotions ? (string) $note->emotions : '' ); ?>"
							>
								<?php esc_html_e( 'Rate', 'politeia-reading' ); ?>
							</button>
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
					<span class="prs-notes-feed__reader-avatar"></span>
					<span class="prs-notes-feed__reader-avatar"></span>
					<span class="prs-notes-feed__reader-avatar"></span>
					<span class="prs-notes-feed__reader-avatar"></span>
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
		const modal = document.getElementById("prs-note-modal");
		const rowsWrap = document.getElementById("prs-note-rows");
		const resetBtn = document.getElementById("prs-note-reset");
		const saveBtn = document.getElementById("prs-note-save");
		const noteButtons = document.querySelectorAll(".prs-note__rate-button");
		if (!modal || !rowsWrap || !resetBtn || !saveBtn || !noteButtons.length) return;

		const EMOTIONS = [
			{ id: "joy", label: "Joy", emoji: "😄", color: "joy" },
			{ id: "sorrow", label: "Sorrow", emoji: "😢", color: "sorrow" },
			{ id: "fear", label: "Fear", emoji: "😱", color: "fear" },
			{ id: "fascination", label: "Fascination", emoji: "🤯", color: "fascination" },
			{ id: "anger", label: "Anger", emoji: "😡", color: "anger" },
			{ id: "serenity", label: "Serenity", emoji: "😌", color: "serenity" },
			{ id: "enlightenment", label: "Enlightenment", emoji: "✨", color: "enlightenment" },
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
				saveBtn.textContent = "Logged Impression";
			} else {
				saveBtn.classList.remove("is-success");
				saveBtn.textContent = "Save Emotional Rating";
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
	})();
</script>
