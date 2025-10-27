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

                $owning_labels = array(
                        'borrowing'    => __( 'Borrowing to:', 'politeia-reading' ),
                        'borrowed'     => __( 'Borrowed from:', 'politeia-reading' ),
                        'sold'         => __( 'Sold to:', 'politeia-reading' ),
                        'lost'         => __( 'Last borrowed to:', 'politeia-reading' ),
                        'location'     => __( 'Location', 'politeia-reading' ),
                        'in_shelf'     => __( 'In Shelf', 'politeia-reading' ),
                        'not_in_shelf' => __( 'Not In Shelf', 'politeia-reading' ),
                        'unknown'      => __( 'Unknown', 'politeia-reading' ),
                );

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
                $l  = $wpdb->prefix . 'politeia_loans';

		// Total para paginación
               $total = (int) $wpdb->get_var(
                       $wpdb->prepare(
                               "
        SELECT COUNT(*)
        FROM $ub ub
        JOIN $b  b ON b.id = ub.book_id
        WHERE ub.user_id = %d
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

                static $books_has_total_pages = null;
                if ( null === $books_has_total_pages ) {
                        $books_has_total_pages = (bool) $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$b} LIKE %s", 'total_pages' ) );
                }

                $book_pages_select = $books_has_total_pages ? 'b.total_pages' : 'NULL';

                // Traer fila sólo de la página actual
                $books = $wpdb->get_results(
                        $wpdb->prepare(
                                "
              SELECT ub.id AS user_book_id,
              ub.reading_status,
              ub.owning_status,
              ub.type_book,
              ub.pages,
              ub.counterparty_name,
              ub.counterparty_email,
              ub.cover_reference,
              (
                      SELECT start_date
                      FROM $l l
                      WHERE l.user_id = ub.user_id
                        AND l.book_id = ub.book_id
                        AND l.end_date IS NULL
                      ORDER BY l.id DESC
                      LIMIT 1
              ) AS active_loan_start,
              b.id   AS book_id,
              b.title,
             b.author,
             b.year,
             b.cover_attachment_id,
             b.slug,
              {$book_pages_select} AS book_total_pages
        FROM $ub ub
        JOIN $b b ON b.id = ub.book_id
        WHERE ub.user_id = %d
          AND (ub.owning_status IS NULL OR ub.owning_status != 'deleted')
        ORDER BY b.title ASC
        LIMIT %d OFFSET %d
    ",
                                $user_id,
                                $per_page,
                                $offset
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
				'prev_text' => '« ' . __( 'Previous', 'politeia-reading' ),
				'next_text' => __( 'Next', 'politeia-reading' ) . ' »',
				'type'      => 'array',
			)
		);

		$add_book_shortcode = '';
		if ( shortcode_exists( 'politeia_add_book' ) ) {
			$add_book_shortcode = do_shortcode( '[politeia_add_book]' );
		}

		ob_start(); ?>
        <div class="prs-library">
               <table id="prs-library" class="prs-table">
               <thead>
                        <tr>
                        <th scope="colgroup" colspan="2">
                                <div class="prs-library__header">
                                        <span class="prs-library__header-title"><?php esc_html_e( 'My Library', 'politeia-reading' ); ?></span>
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
                        </th>
                        </tr>
               </thead>
                <tbody>
                        <?php foreach ( (array) $books as $r ) :
                                $slug     = $r->slug ?: sanitize_title( $r->title . '-' . $r->author . ( $r->year ? '-' . $r->year : '' ) );
                                $url      = home_url( '/my-books/my-book-' . $slug );
                                $year     = $r->year ? (int) $r->year : null;
                                $pages             = $r->pages ? (int) $r->pages : null;
                                $book_total_pages  = isset( $r->book_total_pages ) ? (int) $r->book_total_pages : 0;
                                $effective_pages   = $book_total_pages > 0 ? $book_total_pages : ( $pages ?? 0 );
                               $progress          = 0;
                               $owning_status     = isset( $r->owning_status ) ? (string) $r->owning_status : '';
                               $row_owning_attr   = $owning_status ? $owning_status : 'in_shelf';
                               $reading_status    = isset( $r->reading_status ) ? (string) $r->reading_status : '';
                               $author_value      = isset( $r->author ) ? (string) $r->author : '';
                               $title_value       = isset( $r->title ) ? (string) $r->title : '';

                               if ( class_exists( 'Politeia_Reading_Sessions' ) && $effective_pages > 0 ) {
                                        $progress = Politeia_Reading_Sessions::calculate_progress_percent( $user_id, (int) $r->book_id, $effective_pages );
                                }

                                $reading_id  = 'reading-status-' . (int) $r->user_book_id;
                                $owning_id   = 'owning-status-' . (int) $r->user_book_id;
                                $progress_id = 'reading-progress-' . (int) $r->user_book_id;

                                $loan_contact_name  = isset( $r->counterparty_name ) ? trim( (string) $r->counterparty_name ) : '';
                                $loan_contact_email = isset( $r->counterparty_email ) ? trim( (string) $r->counterparty_email ) : '';
                                $is_digital         = ( isset( $r->type_book ) && 'd' === $r->type_book );
                                $active_start_local = '';

                                if ( ! empty( $r->active_loan_start ) ) {
                                        $converted = get_date_from_gmt( $r->active_loan_start, 'Y-m-d' );
                                        if ( $converted ) {
                                                $active_start_local = $converted;
                                        }
                                }

                                $year_text  = $year ? sprintf( __( 'Published: %s', 'politeia-reading' ), $year ) : __( 'Published: —', 'politeia-reading' );
                                $pages_value    = $pages ? (int) $pages : '';
                                $pages_display  = $pages ? (string) (int) $pages : '';
                                $pages_input_id = 'prs-pages-input-' . (int) $r->user_book_id;

                                /* translators: %s: percentage of reading progress. */
                                $progress_label = sprintf( __( '%s%% complete', 'politeia-reading' ), (int) $progress );

                                $current_select_value = $owning_status ? $owning_status : 'in_shelf';
                                $stored_status        = $owning_status ? $owning_status : '';

                                $owning_info_lines = array();

                                if ( in_array( $owning_status, array( 'borrowed', 'borrowing', 'sold' ), true ) ) {
                                        $label_map   = array(
                                                'borrowed'  => $label_borrowed,
                                                'borrowing' => $label_borrowing,
                                                'sold'      => $label_sold,
                                        );
                                        $info_label  = isset( $label_map[ $owning_status ] ) ? $label_map[ $owning_status ] : '';
                                        $display_name = $loan_contact_name ? $loan_contact_name : $label_unknown;

                                        if ( $info_label ) {
                                                $owning_info_lines[] = '<strong>' . esc_html( $info_label ) . '</strong>';
                                        }

                                        $owning_info_lines[] = esc_html( $display_name );

                                        if ( $active_start_local ) {
                                                $owning_info_lines[] = '<small>' . esc_html( $active_start_local ) . '</small>';
                                        }
                                } elseif ( 'lost' === $owning_status ) {
                                        $owning_info_lines[] = sprintf(
                                                '<strong>%s</strong>: %s',
                                                esc_html( $label_location ),
                                                esc_html( $label_not_in_shelf )
                                        );

                                        if ( $loan_contact_name ) {
                                                $owning_info_lines[] = sprintf(
                                                        '<strong>%s</strong> %s',
                                                        esc_html( $label_lost ),
                                                        esc_html( $loan_contact_name )
                                                );
                                        }
                                } else {
                                        $owning_info_lines[] = sprintf(
                                                '<strong>%s</strong>: %s',
                                                esc_html( $label_location ),
                                                esc_html( $label_in_shelf )
                                        );
                                }

                                $owning_info_html = implode( '<br>', $owning_info_lines );
                                $owning_info_display = $owning_info_html ? wp_kses(
                                        $owning_info_html,
                                        array(
                                                'strong' => array(),
                                                'br'     => array(),
                                                'small'  => array(),
                                        )
                                ) : '';

                                $reading_disabled       = in_array( $owning_status, array( 'borrowing', 'borrowed' ), true );
                                $reading_disabled_text   = __( 'Disabled while this book is being borrowed.', 'politeia-reading' );
                                $reading_disabled_class  = $reading_disabled ? ' is-disabled' : '';
                                $reading_disabled_title  = $reading_disabled ? ' title="' . esc_attr( $reading_disabled_text ) . '"' : '';
                                ?>
                                <tr
                                data-user-book-id="<?php echo (int) $r->user_book_id; ?>"
                                data-owning-status="<?php echo esc_attr( $row_owning_attr ); ?>"
                                data-reading-status="<?php echo esc_attr( $reading_status ); ?>"
                                data-progress="<?php echo esc_attr( (int) $progress ); ?>"
                                data-author="<?php echo esc_attr( $author_value ); ?>"
                                data-title="<?php echo esc_attr( $title_value ); ?>"
                        >
                                <td class="prs-library__info">
                                <div class="prs-library__cover">
                                <?php
                                $user_cover_raw    = '';
                                if ( isset( $r->cover_reference ) && '' !== $r->cover_reference && null !== $r->cover_reference ) {
                                        $user_cover_raw = $r->cover_reference;
                                } elseif ( isset( $r->cover_attachment_id_user ) ) {
                                        $user_cover_raw = $r->cover_attachment_id_user;
                                }
                                $parsed_user_cover = method_exists( 'PRS_Cover_Upload_Feature', 'parse_cover_value' ) ? PRS_Cover_Upload_Feature::parse_cover_value( $user_cover_raw ) : array(
                                        'attachment_id' => is_numeric( $user_cover_raw ) ? (int) $user_cover_raw : 0,
                                        'url'           => '',
                                        'source'        => '',
                                );
                                $user_cover_id     = isset( $parsed_user_cover['attachment_id'] ) ? (int) $parsed_user_cover['attachment_id'] : 0;
                                $user_cover_url    = isset( $parsed_user_cover['url'] ) ? trim( (string) $parsed_user_cover['url'] ) : '';
                                $user_cover_url    = $user_cover_url ? esc_url_raw( $user_cover_url ) : '';
                                $user_cover_source = isset( $parsed_user_cover['source'] ) ? trim( (string) $parsed_user_cover['source'] ) : '';
                                if ( $user_cover_id ) {
                                        $attachment_source = get_post_meta( $user_cover_id, '_prs_cover_source', true );
                                        if ( $attachment_source ) {
                                                $user_cover_source = esc_url_raw( (string) $attachment_source );
                                        }
                                }
                                $user_cover_source = $user_cover_source ? esc_url_raw( $user_cover_source ) : '';
                                $book_cover_id     = isset( $r->cover_attachment_id ) ? (int) $r->cover_attachment_id : 0;
                                $book_cover_url    = isset( $r->cover_url ) ? trim( (string) $r->cover_url ) : '';
                                $book_cover_source = $book_cover_url ? trim( isset( $r->cover_source ) ? (string) $r->cover_source : '' ) : '';
                                $book_cover_source = $book_cover_source ? esc_url_raw( $book_cover_source ) : '';

                                if ( $user_cover_url ) {
                                        echo '<img class="prs-library__cover-image" src="' . esc_url( $user_cover_url ) . '" alt="' . esc_attr( $r->title ) . '" />';
                                        if ( $user_cover_source ) {
                                                echo '<div class="prs-library__cover-attribution"><a href="' . esc_url( $user_cover_source ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View on Google Books', 'politeia-reading' ) . '</a></div>';
                                        }
                                } elseif ( $user_cover_id ) {
                                        echo wp_get_attachment_image(
                                                $user_cover_id,
                                                'thumbnail',
                                                false,
                                                array(
                                                        'class' => 'prs-library__cover-image',
                                                        'alt'   => sanitize_text_field( $r->title ),
                                                )
                                        );
                                        if ( $user_cover_source ) {
                                                echo '<div class="prs-library__cover-attribution"><a href="' . esc_url( $user_cover_source ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View on Google Books', 'politeia-reading' ) . '</a></div>';
                                        }
                                } elseif ( $book_cover_id ) {
                                        echo wp_get_attachment_image(
                                                $book_cover_id,
                                                'thumbnail',
                                                false,
                                                array(
                                                        'class' => 'prs-library__cover-image',
                                                        'alt'   => sanitize_text_field( $r->title ),
                                                )
                                        );
                                } elseif ( $book_cover_url ) {
                                        echo '<img class="prs-library__cover-image" src="' . esc_url( $book_cover_url ) . '" alt="' . esc_attr( $r->title ) . '" />';
                                        if ( $book_cover_source ) {
                                                echo '<div class="prs-library__cover-attribution"><a href="' . esc_url( $book_cover_source ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View on Google Books', 'politeia-reading' ) . '</a></div>';
                                        }
                                } else {
                                        echo '<div class="prs-library__cover-placeholder" aria-hidden="true"></div>';
                                }
                                ?>
                                </div>
                                <div class="prs-library__details">
                                        <a class="prs-library__title" href="<?php echo esc_url( $url ); ?>">
                                        <?php echo esc_html( $r->title ); ?>
                                        </a>
                                        <div class="prs-library__meta">
                                                <?php if ( ! empty( $r->author ) ) : ?>
                                                <span class="prs-library__meta-item prs-library__author"><?php echo esc_html( $r->author ); ?></span>
                                                <?php endif; ?>
                                                <span class="prs-library__meta-item prs-library__year"><?php echo esc_html( $year_text ); ?></span>
                                                <span class="prs-library__meta-item prs-library__pages" data-pages="<?php echo esc_attr( $pages_value ); ?>">
                                                        <span class="prs-library__pages-display">
                                                                <span class="prs-library__pages-label"><?php esc_html_e( 'Pages:', 'politeia-reading' ); ?></span>
                                                                <span class="prs-library__pages-value"><?php echo esc_html( $pages_display ); ?></span>
                                                                <button type="button" class="prs-library__pages-edit"><?php esc_html_e( 'Edit', 'politeia-reading' ); ?></button>
                                                        </span>
                                                        <input
                                                                type="number"
                                                                min="1"
                                                                step="1"
                                                                inputmode="numeric"
                                                                class="prs-library__pages-input"
                                                                id="<?php echo esc_attr( $pages_input_id ); ?>"
                                                                value="<?php echo esc_attr( $pages_value ); ?>"
                                                                aria-label="<?php esc_attr_e( 'Total pages', 'politeia-reading' ); ?>"
                                                        />
                                                        <span class="prs-library__pages-error" role="alert" aria-live="polite"></span>
                                                </span>
                                        </div>
                                </div>
                                </td>
                                <td class="prs-library__actions">
                                <div class="prs-library__controls">
                                        <div class="prs-library__field">
                                                <label for="<?php echo esc_attr( $reading_id ); ?>"><?php esc_html_e( 'Reading Status', 'politeia-reading' ); ?></label>
                                                <select
                                                        id="<?php echo esc_attr( $reading_id ); ?>"
                                                        class="prs-reading-status reading-status-select<?php echo esc_attr( $reading_disabled_class ); ?>"
                                                        data-disabled-text="<?php echo esc_attr( $reading_disabled_text ); ?>"
                                                        aria-disabled="<?php echo $reading_disabled ? 'true' : 'false'; ?>"<?php echo $reading_disabled ? ' disabled="disabled"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $reading_disabled_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                >
                                                <?php
                                                $reading = array(
                                                        'not_started' => __( 'Not Started', 'politeia-reading' ),
                                                        'started'     => __( 'Started', 'politeia-reading' ),
                                                        'finished'    => __( 'Finished', 'politeia-reading' ),
                                                );
                                                foreach ( $reading as $val => $label ) {
                                                        printf(
                                                                '<option value="%s"%s>%s</option>',
                                                                esc_attr( $val ),
                                                                selected( $r->reading_status, $val, false ),
                                                                esc_html( $label )
                                                        );
                                                }
                                                ?>
                                                </select>
                                        </div>
                                        <div class="prs-library__field">
                                                <label for="<?php echo esc_attr( $owning_id ); ?>"><?php esc_html_e( 'Owning Status', 'politeia-reading' ); ?></label>
                                                <select
                                                        id="<?php echo esc_attr( $owning_id ); ?>"
                                                        class="prs-owning-status owning-status-select<?php echo $is_digital ? ' is-disabled' : ''; ?>"
                                                        data-book-id="<?php echo (int) $r->book_id; ?>"
                                                        data-user-book-id="<?php echo (int) $r->user_book_id; ?>"
                                                        data-current-value="<?php echo esc_attr( $current_select_value ); ?>"
                                                        data-stored-status="<?php echo esc_attr( $stored_status ); ?>"
                                                        data-contact-name="<?php echo esc_attr( $loan_contact_name ); ?>"
                                                        data-contact-email="<?php echo esc_attr( $loan_contact_email ); ?>"
                                                        data-active-start="<?php echo esc_attr( $active_start_local ); ?>"
                                                        <?php echo $is_digital ? 'disabled="disabled" aria-disabled="true"' : ''; ?>
                                                >
                                                <option value=""><?php echo esc_html__( '— Select —', 'politeia-reading' ); ?></option>
                                                <?php
                                                $owning = array(
                                                        'in_shelf'  => __( 'In Shelf', 'politeia-reading' ),
                                                        'borrowed'  => __( 'Borrowed', 'politeia-reading' ),
                                                        'borrowing' => __( 'Borrowing', 'politeia-reading' ),
                                                        'sold'      => __( 'Sold', 'politeia-reading' ),
                                                        'lost'      => __( 'Lost', 'politeia-reading' ),
                                                );
                                                foreach ( $owning as $val => $label ) {
                                                        $selected_attr = selected( $owning_status, $val, false );
                                                        if ( 'in_shelf' === $val && '' === $owning_status ) {
                                                                $selected_attr = ' selected="selected"';
                                                        }
                                                        printf(
                                                                '<option value="%s"%s>%s</option>',
                                                                esc_attr( $val ),
                                                                $selected_attr,
                                                                esc_html( $label )
                                                        );
                                                }
                                                ?>
                                                </select>
                                                <span class="owning-status-info"><?php echo $owning_info_display; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                                <?php if ( $is_digital ) : ?>
                                                <div class="prs-owning-status-note"><?php esc_html_e( 'Owning status is available only for printed copies.', 'politeia-reading' ); ?></div>
                                                <?php endif; ?>
                                        </div>
                                </div>
                                <div class="prs-library__extras">
                                        <div class="prs-library__progress-field">
                                                <span id="<?php echo esc_attr( $progress_id ); ?>" class="prs-library__progress-label"><?php esc_html_e( 'Reading Progress', 'politeia-reading' ); ?></span>
                                                <div class="prs-library__progress">
                                                        <div
                                                                class="prs-library__progress-track"
                                                                role="progressbar"
                                                                aria-valuenow="<?php echo esc_attr( (int) $progress ); ?>"
                                                                aria-valuemin="0"
                                                                aria-valuemax="100"
                                                                aria-valuetext="<?php echo esc_attr( $progress_label ); ?>"
                                                                aria-labelledby="<?php echo esc_attr( $progress_id ); ?>"
                                                        >
                                                                <div class="prs-library__progress-fill" style="width: <?php echo (int) $progress; ?>%;"></div>
                                                        </div>
                                                        <span class="prs-library__progress-value"><?php echo (int) $progress; ?>%</span>
                                                </div>
                                        </div>
                                        <button type="button" class="prs-library__remove" aria-label="<?php esc_attr_e( 'Remove book', 'politeia-reading' ); ?>">
                                                <?php esc_html_e( 'Remove', 'politeia-reading' ); ?>
                                        </button>
                                </div>
                                </td>
                        </tr>
                        <?php endforeach; ?>
                </tbody>
                </table>

                <?php if ( ! empty( $paginate ) ) : ?>
                <nav class="prs-pagination" aria-label="<?php esc_attr_e( 'Library pagination', 'politeia-reading' ); ?>">
                        <ul class="page-numbers" style="display:flex;gap:6px;list-style:none;padding-left:0;">
                        <?php foreach ( $paginate as $link ) : ?>
                                <li><?php echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
                        <?php endforeach; ?>
                        </ul>
                </nav>
                <?php endif; ?>

                <?php prs_render_owning_overlay(); ?>

                <?php wp_nonce_field( 'prs_update_user_book', 'prs_update_user_book_nonce' ); ?>
        </div>
        <div id="prs-filter-overlay" class="prs-filter-overlay" hidden></div>
        <div
                id="prs-filter-dashboard"
                class="prs-filter-dashboard"
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
                                        <select id="prs-filter-owning-status" class="prs-filter-dashboard__select">
                                                <option value=""><?php esc_html_e( 'All owning statuses', 'politeia-reading' ); ?></option>
                                                <option value="in_shelf"><?php esc_html_e( 'In Shelf', 'politeia-reading' ); ?></option>
                                                <option value="lost"><?php esc_html_e( 'Lost', 'politeia-reading' ); ?></option>
                                                <option value="borrowed"><?php esc_html_e( 'Borrowed', 'politeia-reading' ); ?></option>
                                                <option value="borrowing"><?php esc_html_e( 'Borrowing', 'politeia-reading' ); ?></option>
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
                <?php
                return ob_get_clean();
        }
);
