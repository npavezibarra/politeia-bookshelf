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
                wp_enqueue_script( 'politeia-add-book' );
                wp_localize_script(
                        'politeia-add-book',
                        'PRS_ADD_BOOK_AUTOCOMPLETE',
                        array(
                                'ajax_url' => admin_url( 'admin-ajax.php' ),
                                'nonce'    => wp_create_nonce( 'prs_canonical_title_search' ),
                        )
                );

                $success                = ! empty( $_GET['prs_added'] ) && '1' === $_GET['prs_added'];
                $success_title          = '';
                $success_author         = '';
                $success_year           = null;
                $success_pages          = null;
                $success_cover_url      = '';
                $multiple_mode_content  = '';
                $multiple_shortcode_tag = 'politeia_chatgpt_input';

                if ( shortcode_exists( $multiple_shortcode_tag ) ) {
                        $multiple_mode_content = do_shortcode( '[' . $multiple_shortcode_tag . ']' );
                }

		if ( $success ) {
			if ( isset( $_GET['prs_added_title'] ) ) {
				$success_title = sanitize_text_field( wp_unslash( $_GET['prs_added_title'] ) );
			}
			if ( isset( $_GET['prs_added_author'] ) ) {
				$success_author = sanitize_text_field( wp_unslash( $_GET['prs_added_author'] ) );
			}
			if ( isset( $_GET['prs_added_year'] ) && '' !== $_GET['prs_added_year'] ) {
				$year = absint( $_GET['prs_added_year'] );
				if ( $year >= 1400 && $year <= ( (int) date( 'Y' ) + 1 ) ) {
					$success_year = $year;
				}
			}
			if ( isset( $_GET['prs_added_pages'] ) && '' !== $_GET['prs_added_pages'] ) {
				$pages = absint( $_GET['prs_added_pages'] );
				if ( $pages > 0 ) {
					$success_pages = $pages;
				}
			}
			if ( isset( $_GET['prs_added_cover'] ) && '' !== $_GET['prs_added_cover'] ) {
				$cover_id = absint( $_GET['prs_added_cover'] );
				if ( $cover_id ) {
					$cover_url = wp_get_attachment_image_url( $cover_id, 'medium' );
					if ( $cover_url ) {
						$success_cover_url = $cover_url;
					}
				}
			}
		}

		static $modal_registered = false;

		if ( ! $modal_registered ) {
			$modal_registered = true;

			ob_start();
			?>
        <div class="prs-add-book prs-add-book--modal">
        <div id="prs-add-book-modal"
        			class="prs-add-book__modal"
        			data-success="<?php echo esc_attr( $success ? '1' : '0' ); ?>"
        			style="<?php echo esc_attr( $success ? 'display:flex;' : 'display:none;' ); ?>"
        			role="dialog"
        			aria-modal="true"
        			aria-labelledby="<?php echo esc_attr( $success ? 'prs-add-book-success-title' : 'prs-add-book-form-title' ); ?>"
        			onclick="this.style.display='none'">
        			<div class="prs-add-book__modal-content<?php echo $success ? ' prs-add-book__modal-content--success' : ''; ?>" onclick="event.stopPropagation();">
        				<button type="button"
        					class="prs-add-book__close"
        					aria-label="<?php echo esc_attr__( 'Close dialog', 'politeia-reading' ); ?>"
        					onclick="document.getElementById('prs-add-book-modal').style.display='none'">
        					&times;
        				</button>
                                        <div id="prs-add-book-success" class="prs-add-book__success"<?php echo $success ? '' : ' hidden'; ?>>
                                                <?php if ( $success_cover_url ) : ?>
        						<?php
        						$success_cover_alt = $success_title
        							? sprintf(
        								/* translators: %s: book title. */
        								__( 'Cover image for %s', 'politeia-reading' ),
        								$success_title
        							)
        							: __( 'Uploaded book cover image', 'politeia-reading' );
        						?>
        						<div class="prs-add-book__success-cover">
        							<img src="<?php echo esc_url( $success_cover_url ); ?>"
        								alt="<?php echo esc_attr( $success_cover_alt ); ?>"
        								loading="lazy"
        								decoding="async" />
        						</div>
        					<?php endif; ?>
        					<h2 id="prs-add-book-success-title" class="prs-add-book__success-heading">
        						<?php echo esc_html__( 'Book Added Successfully', 'politeia-reading' ); ?>
        					</h2>
        					<ul class="prs-add-book__success-details">
        						<?php if ( $success_title ) : ?>
        							<li class="prs-add-book__success-item">
        								<span class="prs-add-book__success-label"><?php esc_html_e( 'Title', 'politeia-reading' ); ?></span>
        								<span class="prs-add-book__success-value"><?php echo esc_html( $success_title ); ?></span>
        							</li>
        						<?php endif; ?>
        						<?php if ( $success_author ) : ?>
        							<li class="prs-add-book__success-item">
        								<span class="prs-add-book__success-label"><?php esc_html_e( 'Author', 'politeia-reading' ); ?></span>
        								<span class="prs-add-book__success-value"><?php echo esc_html( $success_author ); ?></span>
        							</li>
        						<?php endif; ?>
        						<?php if ( null !== $success_year ) : ?>
        							<li class="prs-add-book__success-item">
        								<span class="prs-add-book__success-label"><?php esc_html_e( 'Year', 'politeia-reading' ); ?></span>
        								<span class="prs-add-book__success-value"><?php echo esc_html( $success_year ); ?></span>
        							</li>
        						<?php endif; ?>
        						<?php if ( null !== $success_pages ) : ?>
        							<li class="prs-add-book__success-item">
        								<span class="prs-add-book__success-label"><?php esc_html_e( 'Pages', 'politeia-reading' ); ?></span>
        								<span class="prs-add-book__success-value"><?php echo esc_html( $success_pages ); ?></span>
        							</li>
        						<?php endif; ?>
        					</ul>
        				</div>
                                        <div id="prs-add-book-mode-switch" class="prs-add-book__mode-switch"<?php echo $success ? ' hidden' : ''; ?>>
                                                <button type="button"
                                                        class="prs-add-book__mode-button is-active"
                                                        data-mode="single"
                                                        aria-pressed="true">
                                                        <?php esc_html_e( 'Single', 'politeia-reading' ); ?>
                                                </button>
                                                <span class="prs-add-book__mode-separator" aria-hidden="true">|</span>
                                                <button type="button"
                                                        class="prs-add-book__mode-button"
                                                        data-mode="multiple"
                                                        aria-pressed="false">
                                                        <?php esc_html_e( 'Multiple', 'politeia-reading' ); ?>
                                                </button>
                                        </div>
                                        <form id="prs-add-book-form"
                                                class="prs-form"
                                                method="post"
                                                enctype="multipart/form-data"
                                                action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"<?php echo $success ? ' hidden' : ''; ?>>
                                                <h2 id="prs-add-book-form-title" class="prs-add-book__heading"<?php echo $success ? ' hidden' : ''; ?>>
                                                        <?php echo esc_html__( 'Add to Library', 'politeia-reading' ); ?>
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
        									<div class="prs-add-book__field prs-add-book__field--title">
        										<input type="text" id="prs_title" name="prs_title" autocomplete="off" required />
        										<div
        											id="prs_title_suggestions"
        											class="prs-add-book__suggestions"
        											role="listbox"
        											aria-label="<?php esc_attr_e( 'Book suggestions', 'politeia-reading' ); ?>"
        											aria-hidden="true"
        										></div>
        									</div>
        								</td>
        							</tr>
                                                                <tr>
                                                                        <th scope="row">
                                                                                <label for="prs_author_input">
                                                                                        <?php esc_html_e( 'Author', 'politeia-reading' ); ?>
                                                                                        <span class="prs-form__required" aria-hidden="true">*</span>
                                                                                </label>
                                                                        </th>
                                                                        <td>
                                                                        <?php $remove_author_label = esc_attr__( 'Remove author', 'politeia-reading' ); ?>
                                                                        <div
                                                                                        id="prs_author_fields"
                                                                                        class="prs-add-book__authors"
                                                                                        data-remove-label="<?php echo $remove_author_label; ?>">
                                                                                        <div
                                                                                                        id="prs_author_list"
                                                                                                        class="prs-add-book__author-list"
                                                                                                        role="list"
                                                                                                        aria-live="polite"
                                                                                                        aria-relevant="additions removals"
                                                                                        ></div>
                                                                                        <button
                                                                                                        type="button"
                                                                                                        id="prs_author_add"
                                                                                                        class="prs-add-book__author-add"
                                                                                                        aria-label="<?php echo esc_attr__( 'Add author', 'politeia-reading' ); ?>"
                                                                                                        hidden
                                                                                        ><?php echo esc_html__( 'Edit', 'politeia-reading' ); ?></button>
                                                                                        <div class="prs-add-book__author-input-wrapper">
                                                                                                <input
                                                                                                                type="text"
                                                                                                                id="prs_author_input"
                                                                                                                name="prs_author[]"
                                                                                                                class="prs-add-book__author-input"
                                                                                                                autocomplete="off"
                                                                                                                required
                                                                                                                aria-describedby="prs_author_hint"
                                                                                                />
                                                                                        </div>
                                                                                        <div id="prs_author_hidden" class="prs-add-book__author-hidden" aria-hidden="true"></div>
                                                                                        <p id="prs_author_hint" class="prs-add-book__author-hint">
                                                                                                <?php echo esc_html__( 'Separate multiple authors with commas.', 'politeia-reading' ); ?>
                                                                                        </p>
                                                                                </div>
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
        									<label for="prs_pages"><?php esc_html_e( 'Pages', 'politeia-reading' ); ?></label>
        								</th>
        								<td>
        									<input type="number"
        										id="prs_pages"
        										name="prs_pages"
        										min="1"
        										step="1"
        										inputmode="numeric"
        										pattern="[0-9]*" />
        								</td>
        							</tr>
        							<tr>
        								<th scope="row">
        									<label class="prs-form__label" for="prs_cover">
        										<span class="prs-form__label-text"><?php esc_html_e( 'Cover', 'politeia-reading' ); ?>:</span>
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
        										<?php
        										$prs_cover_placeholder = plugins_url(
        											'modules/reading/assets/img/icon-book-cover.png',
        											dirname( __DIR__, 3 ) . '/politeia-bookshelf.php'
        										);
        										?>
        										<div id="prs_cover_preview" class="prs-form__file-preview" hidden>
        											<img src="<?php echo esc_url( $prs_cover_placeholder ); ?>"
        												decoding="async"
        												alt="<?php echo esc_attr( 'Selected book cover preview' ); ?>"
        												data-placeholder-src="<?php echo esc_attr( $prs_cover_placeholder ); ?>" />
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
                                                        <div id="prs-add-book-multiple" class="prs-add-book__multiple" hidden>
                                                                <h2 id="prs-add-book-multiple-title" class="prs-add-book__heading">
                                                                        <?php echo esc_html__( 'Add Multiple Books', 'politeia-reading' ); ?>
                                                                </h2>
                                                                <?php if ( $multiple_mode_content ) : ?>
                                                                        <?php echo $multiple_mode_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                                <?php else : ?>
                                                                        <p class="prs-add-book__mode-unavailable">
                                                                                <?php echo esc_html__( 'The multiple entry mode is currently unavailable.', 'politeia-reading' ); ?>
                                                                        </p>
                                                                <?php endif; ?>
                                                        </div>
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
        								var previewPlaceholder = previewImage ? previewImage.getAttribute('data-placeholder-src') : '';
        								var defaultLabel = trigger ? trigger.getAttribute('data-default-label') : '';
        								var changeLabel = trigger ? trigger.getAttribute('data-change-label') : '';
        								var form = fileInput.form;
        
        									var resetPreview = function () {
        										if (previewWrapper) {
        											previewWrapper.hidden = true;
        										}
        										if (previewImage) {
        											if (previewPlaceholder) {
        												previewImage.src = previewPlaceholder;
        											} else {
        												previewImage.removeAttribute('src');
        											}
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
        												previewWrapper.hidden = false;
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
        		
        </div>
                        <?php
			$modal_markup = ob_get_clean();

			add_action(
				'wp_footer',
				function () use ( $modal_markup ) {
					echo $modal_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			);
		}

		ob_start();

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
        </div>
                <?php
		return ob_get_clean();
	}
);

// Handle submit (front-end safe handler)
add_action( 'admin_post_prs_add_book_submit', 'prs_add_book_submit_handler' );
add_action( 'admin_post_nopriv_prs_add_book_submit', 'prs_add_book_submit_handler' );
add_action( 'wp_ajax_prs_canonical_title_search', 'prs_canonical_title_search_handler' );
add_action( 'wp_ajax_nopriv_prs_canonical_title_search', 'prs_canonical_title_search_handler' );

function prs_canonical_title_search_handler() {
        if ( ! is_user_logged_in() ) {
                wp_send_json_error( array( 'message' => 'Login required.' ), 403 );
        }

        $nonce = isset( $_POST['nonce'] ) ? wp_unslash( $_POST['nonce'] ) : '';
        if ( ! wp_verify_nonce( $nonce, 'prs_canonical_title_search' ) ) {
                wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
        }

        $query = isset( $_POST['query'] ) ? wp_unslash( $_POST['query'] ) : '';
        $query = prs_normalize_title( $query );

        if ( '' === $query ) {
                wp_send_json(
                        array(
                                'source' => 'canonical',
                                'items'  => array(),
                        )
                );
        }

        global $wpdb;
        $books_table = $wpdb->prefix . 'politeia_books';
        $book_authors_table = $wpdb->prefix . 'politeia_book_authors';
        $authors_table = $wpdb->prefix . 'politeia_authors';
        $like = $wpdb->esc_like( $query ) . '%';
        $rows = $wpdb->get_results(
                $wpdb->prepare(
                        "SELECT id, title, year, slug FROM {$books_table} WHERE normalized_title LIKE %s ORDER BY year DESC LIMIT 10",
                        $like
                ),
                ARRAY_A
        );

        $author_map = array();
        if ( $rows ) {
                $book_ids = array();
                foreach ( $rows as $row ) {
                        if ( ! empty( $row['id'] ) ) {
                                $book_ids[] = (int) $row['id'];
                        }
                }

                if ( $book_ids ) {
                        $placeholders = implode( ',', array_fill( 0, count( $book_ids ), '%d' ) );
                        $author_rows  = $wpdb->get_results(
                                $wpdb->prepare(
                                        "SELECT ba.book_id, a.name FROM {$book_authors_table} ba INNER JOIN {$authors_table} a ON a.id = ba.author_id WHERE ba.book_id IN ({$placeholders}) ORDER BY a.name ASC",
                                        $book_ids
                                ),
                                ARRAY_A
                        );

                        if ( $author_rows ) {
                                foreach ( $author_rows as $author_row ) {
                                        $book_id = isset( $author_row['book_id'] ) ? (int) $author_row['book_id'] : 0;
                                        $name = isset( $author_row['name'] ) ? (string) $author_row['name'] : '';
                                        if ( $book_id && '' !== $name ) {
                                                if ( ! isset( $author_map[ $book_id ] ) ) {
                                                        $author_map[ $book_id ] = array();
                                                }
                                                $author_map[ $book_id ][] = $name;
                                        }
                                }
                        }
                }
        }

        $items = array();
        if ( $rows ) {
                foreach ( $rows as $row ) {
                        $book_id = isset( $row['id'] ) ? (int) $row['id'] : 0;
                        $year = isset( $row['year'] ) ? (int) $row['year'] : 0;
                        $items[] = array(
                                'id'      => $book_id,
                                'title'   => isset( $row['title'] ) ? (string) $row['title'] : '',
                                'year'    => $year > 0 ? $year : '',
                                'slug'    => isset( $row['slug'] ) ? (string) $row['slug'] : '',
                                'authors' => isset( $author_map[ $book_id ] ) ? array_values( $author_map[ $book_id ] ) : array(),
                        );
                }
        }

        wp_send_json(
                array(
                        'source' => 'canonical',
                        'items'  => $items,
                )
        );
}

function prs_add_book_submit_handler() {
	if ( ! is_user_logged_in() ) {
		wp_die( 'Login required.' );
	}
	if ( ! isset( $_POST['prs_nonce'] ) || ! wp_verify_nonce( $_POST['prs_nonce'], 'prs_add_book' ) ) {
		wp_die( 'Invalid nonce.' );
	}

	$user_id = get_current_user_id();

        // Sanitización
        $title   = isset( $_POST['prs_title'] ) ? sanitize_text_field( wp_unslash( $_POST['prs_title'] ) ) : '';
        $authors = array();

        if ( isset( $_POST['prs_author'] ) ) {
                $raw_authors = wp_unslash( $_POST['prs_author'] );

                $collect_authors = static function( $value ) use ( &$authors ) {
                        if ( null === $value || '' === $value ) {
                                return;
                        }

                        $candidates = explode( ',', (string) $value );

                        foreach ( $candidates as $candidate ) {
                                $clean_author = sanitize_text_field( $candidate );
                                if ( '' === $clean_author ) {
                                        continue;
                                }

                                $clean_author = preg_replace( '/\s+/', ' ', $clean_author );
                                $clean_author = trim( (string) $clean_author );

                                if ( '' !== $clean_author ) {
                                        $authors[] = $clean_author;
                                }
                        }
                };

                if ( is_array( $raw_authors ) ) {
                        foreach ( $raw_authors as $raw_author ) {
                                $collect_authors( $raw_author );
                        }
                } else {
                        $collect_authors( $raw_authors );
                }
        }

        $primary_author = '';

        if ( ! empty( $authors ) ) {
                $normalized = array();
                $unique     = array();

                foreach ( $authors as $raw_author ) {
                        $key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $raw_author, 'UTF-8' ) : strtolower( $raw_author );
                        if ( isset( $normalized[ $key ] ) ) {
                                continue;
                        }
                        $normalized[ $key ] = true;
                        $unique[]           = $raw_author;
                }

                if ( ! empty( $unique ) ) {
                        $primary_author = array_shift( $unique );
                        $authors        = array_values( $unique );
                }
        }

        $year = null;
        if ( isset( $_POST['prs_year'] ) && $_POST['prs_year'] !== '' ) {
                $y   = absint( $_POST['prs_year'] );
                $min = 1400;
                $max = (int) date( 'Y' ) + 1;
                if ( $y >= $min && $y <= $max ) {
			$year = $y;
		}
	}

	$pages = null;
	if ( isset( $_POST['prs_pages'] ) && $_POST['prs_pages'] !== '' ) {
		$p = absint( $_POST['prs_pages'] );
		if ( $p > 0 ) {
			$pages = $p;
		}
	}

        if ( '' === $title || '' === $primary_author ) {
                wp_safe_redirect( add_query_arg( 'prs_error', 1, wp_get_referer() ?: home_url() ) );
                exit;
        }

        // Upload opcional de portada
        $attachment_id = prs_handle_cover_upload( 'prs_cover' );

        // Normalizar y revisar si el libro canónico ya existe antes de crear candidato.
        $book_id = 0;
        $slug = prs_generate_book_slug( $title, $year );
        if ( $slug ) {
                $book_id = (int) prs_get_book_id_by_slug( $slug );
        }

        if ( $book_id ) {
                $user_book_id = prs_ensure_user_book( $user_id, (int) $book_id );
                if ( $user_book_id && null !== $pages ) {
                        $wpdb->update(
                                $wpdb->prefix . 'politeia_user_books',
                                array( 'pages' => $pages ),
                                array( 'id' => (int) $user_book_id ),
                                array( '%d' ),
                                array( '%d' )
                        );
                }
        } else {
        // Crear candidato y confirmar de inmediato (flujo por etapas).
        $candidate_input = array(
                'title'  => $title,
                'author' => $primary_author,
                'year'   => $year,
                'image'  => $attachment_id ? (int) $attachment_id : null,
        );
        $candidate_args = array(
                'user_id'      => $user_id,
                'input_type'   => 'single_add',
                'source_note'  => 'single-add',
                'enqueue'      => true,
                'raw_response' => array(
                        'cover_attachment_id' => $attachment_id ? (int) $attachment_id : null,
                        'pages'               => $pages,
                        'authors'             => $authors,
                ),
        );

        $candidate_result = prs_create_book_candidate( $candidate_input, $candidate_args );

        $confirm_items = array();
        if ( ! empty( $candidate_result['pending'] ) ) {
                $pending_item = $candidate_result['pending'][0];
                $confirm_items[] = array(
                        'id'     => isset( $pending_item['id'] ) ? (int) $pending_item['id'] : 0,
                        'title'  => $pending_item['title'] ?? $title,
                        'author' => $pending_item['author'] ?? $primary_author,
                        'year'   => $year,
                );
        } elseif ( ! empty( $candidate_result['in_shelf'] ) ) {
                $confirm_items = array();
        } else {
                wp_safe_redirect( add_query_arg( 'prs_error', 1, wp_get_referer() ?: home_url() ) );
                exit;
        }

        if ( ! empty( $confirm_items ) ) {
                if ( function_exists( 'politeia_chatgpt_safe_require' ) ) {
                        politeia_chatgpt_safe_require( 'modules/buttons/class-buttons-confirm-controller.php' );
                }

                if ( class_exists( 'Politeia_Buttons_Confirm_Controller' ) && method_exists( 'Politeia_Buttons_Confirm_Controller', 'confirm_items_direct' ) ) {
                        $confirm_result = Politeia_Buttons_Confirm_Controller::confirm_items_direct( $confirm_items );
                        if ( empty( $confirm_result['confirmed'] ) ) {
                                wp_safe_redirect( add_query_arg( 'prs_error', 1, wp_get_referer() ?: home_url() ) );
                                exit;
                        }
                } else {
                        wp_safe_redirect( add_query_arg( 'prs_error', 1, wp_get_referer() ?: home_url() ) );
                        exit;
                }
        }

                if ( null !== $pages ) {
                        $page_slug = $slug ?: prs_generate_book_slug( $title, $year );
                        if ( $page_slug ) {
                                $book_id = (int) prs_get_book_id_by_slug( $page_slug );
                        }

                        if ( $book_id ) {
                                $user_book_id = prs_ensure_user_book( $user_id, (int) $book_id );
                                if ( $user_book_id ) {
                                        $wpdb->update(
                                                $wpdb->prefix . 'politeia_user_books',
                                                array( 'pages' => $pages ),
                                                array( 'id' => (int) $user_book_id ),
                                                array( '%d' ),
                                                array( '%d' )
                                        );
                                }
                        }
                }
        }

        // Redirect back with success flag
        $redirect_url    = wp_get_referer() ?: home_url();
        $display_authors = array_merge( array( $primary_author ), $authors );
        $query_args      = array(
                'prs_added'        => 1,
                'prs_added_title'  => $title,
                'prs_added_author' => implode( ', ', $display_authors ),
        );

	if ( null !== $year ) {
		$query_args['prs_added_year'] = $year;
	}

	if ( null !== $pages ) {
		$query_args['prs_added_pages'] = $pages;
	}

	if ( $attachment_id ) {
		$query_args['prs_added_cover'] = (int) $attachment_id;
	}

	$url = add_query_arg( $query_args, $redirect_url );
	wp_safe_redirect( $url );
	exit;
}
