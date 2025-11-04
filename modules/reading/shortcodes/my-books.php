<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode(
	'politeia_my_books',
	function () {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'You must be logged in to view your library.', 'politeia-reading' ) . '</p>';
		}

                wp_enqueue_style( 'politeia-reading' );
                wp_enqueue_script( 'politeia-my-book' );

                $owning_labels = prs_get_owning_labels();

                $owning_messages = array(
                        'missing' => __( 'Please enter both name and email.', 'politeia-reading' ),
                        'saving'  => __( 'Saving...', 'politeia-reading' ),
                        'error'   => __( 'Error saving contact.', 'politeia-reading' ),
                        'alert'   => __( 'Error saving contact.', 'politeia-reading' ),
                );

                $owning_nonce = wp_create_nonce( 'save_owning_contact' );

                wp_localize_script(
                        'politeia-my-book',
                        'PRS_LIBRARY',
                        array(
                                'ajax_url' => admin_url( 'admin-ajax.php' ),
                                'messages' => array(
                                        'invalid'   => __( 'Please enter a valid number of pages.', 'politeia-reading' ),
                                        'too_small' => __( 'Please enter a number greater than zero.', 'politeia-reading' ),
                                        'error'     => __( 'There was an error saving the number of pages.', 'politeia-reading' ),
                                ),
                                'owning'   => array(
                                        'nonce'    => $owning_nonce,
                                        'labels'   => $owning_labels,
                                        'messages' => $owning_messages,
                                ),
                        )
                );

                $label_borrowing    = $owning_labels['borrowing'];
                $label_borrowed     = $owning_labels['borrowed'];
                $label_sold         = $owning_labels['sold'];
                $label_lost         = $owning_labels['lost'];
                $label_location     = $owning_labels['location'];
                $label_in_shelf     = $owning_labels['in_shelf'];
                $label_not_in_shelf = $owning_labels['not_in_shelf'];
                $label_unknown      = $owning_labels['unknown'];

                $user_id  = get_current_user_id();
                if ( ! $user_id ) {
                        wp_get_current_user();
                        $user_id = get_current_user_id();
                }
                error_log( '[PRS_MY_BOOKS] Current user: ' . $user_id );
                $per_page = (int) apply_filters( 'politeia_my_books_per_page', 15 );
		if ( $per_page < 1 ) {
			$per_page = 15;
		}

		// Usamos un parámetro propio para no interferir con 'paged'
		$paged  = isset( $_GET['prs_page'] ) ? max( 1, absint( $_GET['prs_page'] ) ) : 1;
		$offset = ( $paged - 1 ) * $per_page;

		global $wpdb;
                $ub = $wpdb->prefix . 'politeia_user_books';
                $b  = $wpdb->prefix . 'politeia_books';

		// Total para paginación
               $total = (int) $wpdb->get_var(
                       $wpdb->prepare(
                               "
        SELECT COUNT(*)
        FROM $ub ub
        JOIN $b  b ON b.id = ub.book_id
        WHERE ub.user_id = %d
          AND ub.deleted_at IS NULL
          AND (ub.owning_status IS NULL OR ub.owning_status != 'deleted')
    ",
                               $user_id
                       )
               );

		if ( $total === 0 ) {
			return '<p>' . esc_html__( 'Your library is empty. Add a book first.', 'politeia-reading' ) . '</p>';
		}

		// Página segura (por si cambió el total)
		$max_pages = max( 1, (int) ceil( $total / $per_page ) );
		if ( $paged > $max_pages ) {
			$paged  = $max_pages;
			$offset = ( $paged - 1 ) * $per_page;
		}

                // Traer filas sólo de la página actual
                $books = prs_get_user_books_for_library(
                        $user_id,
                        array(
                                'per_page' => $per_page,
                                'offset'   => $offset,
                        )
                );

                error_log( '[PRS_MY_BOOKS] Found ' . count( $books ) . ' books for user ' . $user_id );

		// Helper de enlaces de paginación
		$base_url = remove_query_arg( 'prs_page' );
               $paginate = paginate_links(
                       array(
                               'base'      => add_query_arg( 'prs_page', '%#%', $base_url ),
                               'format'    => '',
                               'current'   => $paged,
                               'total'     => $max_pages,
                               'mid_size'  => 2,
                               'end_size'  => 1,
                               'prev_text' => '«',
                               'next_text' => '»',
                               'type'      => 'plain',
                       )
               );

		$add_book_shortcode = '';
		if ( shortcode_exists( 'politeia_add_book' ) ) {
			$add_book_shortcode = do_shortcode( '[politeia_add_book]' );
		}

		ob_start(); ?>
        <div class="prs-library">
               <div class="prs-library__header">
                       <div class="prs-library__header-inner">
                               <span class="prs-library__header-title"><?php esc_html_e( 'My Library', 'politeia-reading' ); ?></span>

                               <div class="prs-library__header-center">
                                       <input
                                               type="text"
                                               id="my-library-search"
                                               class="prs-library__search"
                                               placeholder="<?php esc_attr_e( 'Search by Title or Author…', 'politeia-reading' ); ?>"
                                               onkeyup="filterLibrary()"
                                       />
                                       <span id="prs-book-count" class="prs-book-count">15 books</span>
                               </div>

                               <div class="prs-library__header-actions">
                                       <button
                                               type="button"
                                               class="prs-library__filter-btn button button-secondary"
                                               aria-haspopup="dialog"
                                               aria-controls="prs-filter-dashboard"
                                               aria-expanded="false"
                                       >
                                               <?php esc_html_e( 'Filter', 'politeia-reading' ); ?>
                                       </button>
                                       <?php if ( $add_book_shortcode ) : ?>
                                               <div class="prs-library__header-add-book">
                                                       <?php echo $add_book_shortcode; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                               </div>
                                       <?php endif; ?>
                               </div>
                       </div>
               </div>
               <?php if ( ! empty( $paginate ) ) : ?>
               <div class="prs-pagination prs-pagination--top" aria-label="<?php esc_attr_e( 'Library pagination', 'politeia-reading' ); ?>">
                       <?php echo $paginate; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
               </div>
               <?php endif; ?>

		<table id="prs-library" class="prs-table">
                <tbody>
                        <?php
                        foreach ( (array) $books as $r ) {
                                echo prs_render_book_row(
                                        $r,
                                        array(
                                                'user_id'       => $user_id,
                                                'owning_labels' => $owning_labels,
                                        )
                                ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }
                        ?>
                </tbody>
                </table>

                <?php prs_render_owning_overlay(); ?>

                <?php wp_nonce_field( 'prs_update_user_book', 'prs_update_user_book_nonce' ); ?>
        </div>
        <div id="prs-filter-overlay" class="prs-filter-overlay" hidden></div>
        <div
                id="prs-filter-dashboard"
                class="prs-filter-dashboard prs-filter-modal"
                role="dialog"
                aria-modal="true"
                aria-hidden="true"
                aria-labelledby="prs-filter-title"
                hidden
        >
                <div class="prs-filter-dashboard__panel" role="document">
                        <h2 id="prs-filter-title" class="prs-filter-dashboard__title"><?php esc_html_e( 'Filter Library', 'politeia-reading' ); ?></h2>
                        <form id="prs-filter-form" class="prs-filter-dashboard__form">
                                <div class="prs-filter-dashboard__group">
                                        <label for="prs-filter-owning-status" class="prs-filter-dashboard__label"><?php esc_html_e( 'Owning Status', 'politeia-reading' ); ?></label>
                                        <select id="prs-filter-owning-status" name="owning_status" class="prs-filter-dashboard__select">
                                                <option value=""><?php esc_html_e( 'All owning statuses', 'politeia-reading' ); ?></option>
                                                <option value="in_shelf"><?php esc_html_e( 'In Shelf', 'politeia-reading' ); ?></option>
                                                <option value="lost"><?php esc_html_e( 'Lost', 'politeia-reading' ); ?></option>
                                                <option value="lent_out"><?php esc_html_e( 'Lent Out', 'politeia-reading' ); ?></option>
                                                <option value="sold"><?php esc_html_e( 'Sold', 'politeia-reading' ); ?></option>
                                        </select>
                                </div>
                                <div class="prs-filter-dashboard__group">
                                        <label for="prs-filter-reading-status" class="prs-filter-dashboard__label"><?php esc_html_e( 'Reading Status', 'politeia-reading' ); ?></label>
                                        <select id="prs-filter-reading-status" class="prs-filter-dashboard__select">
                                                <option value=""><?php esc_html_e( 'All reading statuses', 'politeia-reading' ); ?></option>
                                                <option value="not_started"><?php esc_html_e( 'Not Started', 'politeia-reading' ); ?></option>
                                                <option value="started"><?php esc_html_e( 'Started', 'politeia-reading' ); ?></option>
                                                <option value="finished"><?php esc_html_e( 'Finished', 'politeia-reading' ); ?></option>
                                        </select>
                                </div>
                                <div class="prs-filter-dashboard__group">
                                        <label for="prs-filter-progress-min" class="prs-filter-dashboard__label"><?php esc_html_e( 'Minimum Progress', 'politeia-reading' ); ?></label>
                                        <div class="prs-filter-range">
                                                <input id="prs-filter-progress-min" class="prs-filter-range__input" type="range" min="0" max="100" step="1" value="0" />
                                                <span class="prs-filter-range__value" data-display-for="prs-filter-progress-min">0%</span>
                                        </div>
                                </div>
                                <div class="prs-filter-dashboard__group">
                                        <label for="prs-filter-progress-max" class="prs-filter-dashboard__label"><?php esc_html_e( 'Maximum Progress', 'politeia-reading' ); ?></label>
                                        <div class="prs-filter-range">
                                                <input id="prs-filter-progress-max" class="prs-filter-range__input" type="range" min="0" max="100" step="1" value="100" />
                                                <span class="prs-filter-range__value" data-display-for="prs-filter-progress-max">100%</span>
                                        </div>
                                </div>
                                <div class="prs-filter-dashboard__group">
                                        <label for="prs-filter-order" class="prs-filter-dashboard__label"><?php esc_html_e( 'Order By', 'politeia-reading' ); ?></label>
                                        <select id="prs-filter-order" class="prs-filter-dashboard__select">
                                                <option value="title_asc"><?php esc_html_e( 'Title (A → Z)', 'politeia-reading' ); ?></option>
                                                <option value="title_desc"><?php esc_html_e( 'Title (Z → A)', 'politeia-reading' ); ?></option>
                                                <option value="author_asc"><?php esc_html_e( 'Author (A → Z)', 'politeia-reading' ); ?></option>
                                                <option value="author_desc"><?php esc_html_e( 'Author (Z → A)', 'politeia-reading' ); ?></option>
                                                <option value="progress_asc"><?php esc_html_e( 'Progress (Low → High)', 'politeia-reading' ); ?></option>
                                                <option value="progress_desc"><?php esc_html_e( 'Progress (High → Low)', 'politeia-reading' ); ?></option>
                                        </select>
                                </div>
                                <div class="prs-filter-dashboard__actions">
                                        <button type="submit" id="prs-filter-apply" class="button button-primary"><?php esc_html_e( 'Apply', 'politeia-reading' ); ?></button>
                                        <button type="button" id="prs-filter-reset" class="button button-secondary"><?php esc_html_e( 'Reset Filters', 'politeia-reading' ); ?></button>
                                        <button type="button" id="prs-filter-close" class="button prs-filter-dashboard__close"><?php esc_html_e( 'Close', 'politeia-reading' ); ?></button>
                                </div>
                        </form>
                </div>
        </div>
        <script>
        (function() {
                function getAjaxUrl() {
                        if (typeof ajaxurl !== 'undefined') {
                                return ajaxurl;
                        }

                        if (window.PRS_LIBRARY && window.PRS_LIBRARY.ajax_url) {
                                return window.PRS_LIBRARY.ajax_url;
                        }

                        return '';
                }

                function updateBookCount() {
                        var table = document.querySelector('#prs-library tbody');
                        if (!table) {
                                return;
                        }

                        var rows = table.querySelectorAll('tr');
                        var count = 0;

                        rows.forEach(function(row) {
                                if (row.style.display === 'none' || row.hidden) {
                                        return;
                                }

                                count++;
                        });

                        var counter = document.getElementById('prs-book-count');

                        if (counter) {
                                counter.textContent = count + ' ' + (count === 1 ? 'book' : 'books');
                        }
                }

                async function loadLibraryPage(page) {
                        if (typeof page === 'undefined') {
                                page = 1;
                        }

                        var endpoint = getAjaxUrl();
                        if (!endpoint) {
                                return;
                        }

                        try {
                                var response = await fetch(endpoint + '?action=prs_get_books_page&page=' + encodeURIComponent(page));
                                var data = await response.text();
                                var tbody = document.querySelector('#prs-library tbody');

                                if (tbody) {
                                        tbody.innerHTML = data;
                                }

                                updateBookCount();
                        } catch (err) {
                                console.error('Error loading library page:', err);
                        }
                }

                async function filterLibrary() {
                        var input = document.getElementById('my-library-search');
                        var query = input && input.value ? input.value.trim().toLowerCase() : '';
                        var counter = document.getElementById('prs-book-count');

                        if (query === '') {
                                await loadLibraryPage(1);
                                updateBookCount();
                                return;
                        }

                        var endpoint = getAjaxUrl();
                        if (!endpoint) {
                                console.warn('Ajax URL not available for library search.');
                                return;
                        }

                        try {
                                var response = await fetch(endpoint + '?action=prs_get_all_books');
                                var data = await response.text();
                                var tbody = document.querySelector('#prs-library tbody');

                                if (tbody) {
                                        tbody.innerHTML = data;

                                        var rows = tbody.querySelectorAll('tr');
                                        rows.forEach(function(row) {
                                                var text = row.textContent ? row.textContent.toLowerCase() : '';
                                                row.style.display = text.includes(query) ? '' : 'none';
                                        });
                                }

                                updateBookCount();
                        } catch (err) {
                                console.error('Error fetching all books:', err);
                                if (counter) {
                                        counter.textContent = 'Error loading results';
                                }
                        }
                }

                window.updateBookCount = updateBookCount;
                window.filterLibrary = filterLibrary;
                window.loadLibraryPage = loadLibraryPage;

                var tableBody = document.querySelector('#prs-library tbody');
                if (tableBody && 'MutationObserver' in window) {
                        var observer = new MutationObserver(updateBookCount);
                        observer.observe(tableBody, { childList: true, subtree: true });
                }

                function onReady() {
                        updateBookCount();

                        var applyButtons = document.querySelectorAll('.prs-filter-apply, #prs-filter-apply');
                        var resetButtons = document.querySelectorAll('.prs-filter-reset, #prs-filter-reset');

                        applyButtons.forEach(function(button) {
                                button.addEventListener('click', updateBookCount);
                        });

                        resetButtons.forEach(function(button) {
                                button.addEventListener('click', updateBookCount);
                        });
                }

                if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', onReady);
                } else {
                        onReady();
                }
        })();
        </script>
                <?php
                return ob_get_clean();
        }
);
