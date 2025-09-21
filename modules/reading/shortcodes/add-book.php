<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Register shortcode
add_shortcode(
	'politeia_add_book',
	function () {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'You must be logged in to add books.', 'politeia-reading' ) . '</p>';
		}

		wp_enqueue_style( 'politeia-reading' );

		ob_start();

		// Avisos dentro del buffer del shortcode
		if ( ! empty( $_GET['prs_added'] ) && $_GET['prs_added'] === '1' ) {
			echo '<div class="prs-notice prs-notice--success">' .
			esc_html__( 'Book added to My Library.', 'politeia-reading' ) .
			'</div>';
		}
		if ( ! empty( $_GET['prs_error'] ) && $_GET['prs_error'] === '1' ) {
			echo '<div class="prs-notice prs-notice--error">' .
			esc_html__( 'There was a problem adding the book.', 'politeia-reading' ) .
			'</div>';
		}
		?>
	<div class="prs-add-book">
		<button
			type="button"
			class="prs-btn"
			aria-controls="prs-add-book-modal"
			onclick="document.getElementById('prs-add-book-modal').style.display='flex'">
			<?php echo esc_html__( 'Add Book', 'politeia-reading' ); ?>
		</button>

		<div id="prs-add-book-modal"
			class="prs-add-book__modal"
			style="display:none;"
			role="dialog"
			aria-modal="true"
			aria-labelledby="prs-add-book-form-title"
			onclick="this.style.display='none'">
			<div class="prs-add-book__modal-content" onclick="event.stopPropagation();">
				<button type="button"
					class="prs-add-book__close"
					aria-label="<?php echo esc_attr__( 'Close dialog', 'politeia-reading' ); ?>"
					onclick="document.getElementById('prs-add-book-modal').style.display='none'">
					&times;
				</button>
				<form id="prs-add-book-form"
					class="prs-form"
					method="post"
					enctype="multipart/form-data"
					action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<h2 id="prs-add-book-form-title" class="prs-add-book__heading">
						<?php echo esc_html__( 'Add Book', 'politeia-reading' ); ?>
					</h2>
					<?php wp_nonce_field( 'prs_add_book', 'prs_nonce' ); ?>
					<input type="hidden" name="action" value="prs_add_book_submit" />

					<table class="prs-form__table">
						<tbody>
							<tr>
								<th scope="row">
									<label for="prs_title">
										<?php esc_html_e( 'Title', 'politeia-reading' ); ?>
										<span class="prs-form__required" aria-hidden="true">*</span>
									</label>
								</th>
								<td>
									<input type="text" id="prs_title" name="prs_title" required />
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="prs_author">
										<?php esc_html_e( 'Author', 'politeia-reading' ); ?>
										<span class="prs-form__required" aria-hidden="true">*</span>
									</label>
								</th>
								<td>
									<input type="text" id="prs_author" name="prs_author" required />
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="prs_year"><?php esc_html_e( 'Year', 'politeia-reading' ); ?></label>
								</th>
								<td>
									<input type="number"
										id="prs_year"
										name="prs_year"
										min="1400"
										max="<?php echo esc_attr( (int) date( 'Y' ) + 1 ); ?>" />
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label class="prs-form__label" for="prs_cover">
										<span class="prs-form__label-text"><?php esc_html_e( 'Cover', 'politeia-reading' ); ?></span>
										<span class="prs-form__label-note"><?php esc_html_e( '(jpg/png/webp)', 'politeia-reading' ); ?></span>
									</label>
								</th>
								<td>
									<div class="prs-form__file-control">
										<input
											type="file"
											id="prs_cover"
											name="prs_cover"
											accept=".jpg,.jpeg,.png,.webp"
											class="prs-form__file-input"
										/>
										<button
											type="button"
											id="prs_cover_trigger"
											class="prs-form__file-trigger"
											data-default-label="<?php echo esc_attr__( 'Upload Book Cover', 'politeia-reading' ); ?>"
											data-change-label="<?php echo esc_attr__( 'Change Book Cover', 'politeia-reading' ); ?>"
											onclick="document.getElementById('prs_cover').click();"
										>
											<span class="prs-form__file-icon" aria-hidden="true"></span>
											<span class="prs-form__file-text"><?php esc_html_e( 'Upload Book Cover', 'politeia-reading' ); ?></span>
										</button>
										<div id="prs_cover_preview" class="prs-form__file-preview" hidden>
											<img src="" alt="<?php echo esc_attr__( 'Selected book cover preview', 'politeia-reading' ); ?>" />
										</div>
									</div>
								</td>
							</tr>
							<tr class="prs-form__actions">
								<td colspan="2">
									<button class="prs-btn" type="submit"><?php esc_html_e( 'Save to My Library', 'politeia-reading' ); ?></button>
								</td>
							</tr>
						</tbody>
						</table>
						</form>
						<script>
							( function () {
								var fileInput = document.getElementById('prs_cover');
								if (!fileInput) {
									return;
								}

								var trigger = document.getElementById('prs_cover_trigger');
								var triggerText = trigger ? trigger.querySelector('.prs-form__file-text') : null;
								var previewWrapper = document.getElementById('prs_cover_preview');
								var previewImage = previewWrapper ? previewWrapper.querySelector('img') : null;
								var defaultLabel = trigger ? trigger.getAttribute('data-default-label') : '';
								var changeLabel = trigger ? trigger.getAttribute('data-change-label') : '';
								var form = fileInput.form;

								var resetPreview = function () {
									if (previewWrapper) {
										previewWrapper.setAttribute('hidden', 'hidden');
									}
									if (previewImage) {
										previewImage.removeAttribute('src');
									}
									if (triggerText && defaultLabel) {
										triggerText.textContent = defaultLabel;
									}
								};

								if (form) {
									form.addEventListener('reset', function () {
										window.setTimeout(resetPreview);
									});
								}

								fileInput.addEventListener('change', function () {
									if (this.files && this.files[0]) {
										var reader = new FileReader();
										reader.onload = function (event) {
											if (previewWrapper && previewImage) {
												previewImage.src = event.target && event.target.result ? event.target.result : '';
												previewWrapper.removeAttribute('hidden');
											}
										};
										reader.readAsDataURL(this.files[0]);
										if (triggerText && changeLabel) {
											triggerText.textContent = changeLabel;
										}
									} else {
										resetPreview();
									}
								});

								resetPreview();
							}() );
						</script>
						</div>
				</div>
		</div>
		<?php
		return ob_get_clean();
	}
);

// Handle submit (front-end safe handler)
add_action( 'admin_post_prs_add_book_submit', 'prs_add_book_submit_handler' );
add_action( 'admin_post_nopriv_prs_add_book_submit', 'prs_add_book_submit_handler' );

function prs_add_book_submit_handler() {
	if ( ! is_user_logged_in() ) {
		wp_die( 'Login required.' );
	}
	if ( ! isset( $_POST['prs_nonce'] ) || ! wp_verify_nonce( $_POST['prs_nonce'], 'prs_add_book' ) ) {
		wp_die( 'Invalid nonce.' );
	}

	$user_id = get_current_user_id();

	// Sanitización
	$title  = isset( $_POST['prs_title'] ) ? sanitize_text_field( wp_unslash( $_POST['prs_title'] ) ) : '';
	$author = isset( $_POST['prs_author'] ) ? sanitize_text_field( wp_unslash( $_POST['prs_author'] ) ) : '';
	$year   = null;
	if ( isset( $_POST['prs_year'] ) && $_POST['prs_year'] !== '' ) {
		$y   = absint( $_POST['prs_year'] );
		$min = 1400;
		$max = (int) date( 'Y' ) + 1;
		if ( $y >= $min && $y <= $max ) {
			$year = $y;
		}
	}

	if ( $title === '' || $author === '' ) {
		wp_safe_redirect( add_query_arg( 'prs_error', 1, wp_get_referer() ?: home_url() ) );
		exit;
	}

	// Upload opcional de portada
	$attachment_id = prs_handle_cover_upload( 'prs_cover' );

	// Crear o encontrar libro canónico
	$book_id = prs_find_or_create_book( $title, $author, $year, $attachment_id );
	if ( is_wp_error( $book_id ) || ! $book_id ) {
		wp_safe_redirect( add_query_arg( 'prs_error', 1, wp_get_referer() ?: home_url() ) );
		exit;
	}

	// Vincular a la biblioteca del usuario (idempotente)
	prs_ensure_user_book( $user_id, (int) $book_id );

	// Redirect back with success flag
	$url = add_query_arg( 'prs_added', 1, wp_get_referer() ?: home_url() );
	wp_safe_redirect( $url );
	exit;
}
