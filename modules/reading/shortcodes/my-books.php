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

		$user_id  = get_current_user_id();
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

		// Traer fila sólo de la página actual
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"
       SELECT ub.id AS user_book_id,
              ub.reading_status,
              ub.owning_status,
              ub.pages,
               b.id   AS book_id,
               b.title,
               b.author,
               b.year,
               b.cover_attachment_id,
               b.slug
        FROM $ub ub
        JOIN $b b ON b.id = ub.book_id
        WHERE ub.user_id = %d
        ORDER BY b.title ASC
        LIMIT %d OFFSET %d
    ",
				$user_id,
				$per_page,
				$offset
			)
		);

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

		ob_start(); ?>
        <div class="prs-library">
               <table id="prs-library" class="prs-table">
               <thead>
                        <tr>
                        <th scope="colgroup" colspan="2"><?php esc_html_e( 'My Library', 'politeia-reading' ); ?></th>
                        </tr>
               </thead>
                <tbody>
                        <?php foreach ( (array) $rows as $r ) :
                                $slug     = $r->slug ?: sanitize_title( $r->title . '-' . $r->author . ( $r->year ? '-' . $r->year : '' ) );
                                $url      = home_url( '/my-books/my-book-' . $slug );
                                $year     = $r->year ? (int) $r->year : null;
                                $pages    = $r->pages ? (int) $r->pages : null;
                                $progress = 0;

                                if ( 'finished' === $r->reading_status ) {
                                        $progress = 100;
                                } elseif ( 'started' === $r->reading_status ) {
                                        $progress = 50;
                                }

                                $reading_id = 'reading-status-' . (int) $r->user_book_id;
                                $owning_id  = 'owning-status-' . (int) $r->user_book_id;

                                $year_text  = $year ? sprintf( __( 'Published: %s', 'politeia-reading' ), $year ) : __( 'Published: —', 'politeia-reading' );
                                $pages_text = $pages ? sprintf( __( 'Pages: %s', 'politeia-reading' ), $pages ) : __( 'Pages: —', 'politeia-reading' );

                                /* translators: %s: percentage of reading progress. */
                                $progress_label = sprintf( __( '%s%% complete', 'politeia-reading' ), $progress );
                                ?>
                        <tr data-user-book-id="<?php echo (int) $r->user_book_id; ?>">
                                <td class="prs-library__info">
                                <div class="prs-library__cover">
                                <?php
                                if ( $r->cover_attachment_id ) {
                                        echo wp_get_attachment_image(
                                                (int) $r->cover_attachment_id,
                                                'thumbnail',
                                                false,
                                                array(
                                                        'class' => 'prs-library__cover-image',
                                                        'alt'   => sanitize_text_field( $r->title ),
                                                )
                                        );
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
                                                <span class="prs-library__meta-item prs-library__pages"><?php echo esc_html( $pages_text ); ?></span>
                                        </div>
                                </div>
                                </td>
                                <td class="prs-library__actions">
                                <div class="prs-library__controls">
                                        <div class="prs-library__field">
                                                <label for="<?php echo esc_attr( $reading_id ); ?>"><?php esc_html_e( 'Reading Status', 'politeia-reading' ); ?></label>
                                                <select id="<?php echo esc_attr( $reading_id ); ?>" class="prs-reading-status">
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
                                                <select id="<?php echo esc_attr( $owning_id ); ?>" class="prs-owning-status">
                                                <?php
                                                $owning = array(
                                                        'in_shelf'  => __( 'In Shelf', 'politeia-reading' ),
                                                        'lost'      => __( 'Lost', 'politeia-reading' ),
                                                        'borrowed'  => __( 'Borrowed', 'politeia-reading' ),
                                                        'borrowing' => __( 'Borrowing', 'politeia-reading' ),
                                                        'sold'      => __( 'Sold', 'politeia-reading' ),
                                                );
                                                foreach ( $owning as $val => $label ) {
                                                        printf(
                                                                '<option value="%s"%s>%s</option>',
                                                                esc_attr( $val ),
                                                                selected( $r->owning_status, $val, false ),
                                                                esc_html( $label )
                                                        );
                                                }
                                                ?>
                                                </select>
                                        </div>
                                </div>
                                <div class="prs-library__extras">
                                        <div class="prs-library__progress">
                                                <div
                                                        class="prs-library__progress-track"
                                                        role="progressbar"
                                                        aria-valuenow="<?php echo esc_attr( $progress ); ?>"
                                                        aria-valuemin="0"
                                                        aria-valuemax="100"
                                                        aria-valuetext="<?php echo esc_attr( $progress_label ); ?>"
                                                >
                                                        <div class="prs-library__progress-fill" style="width: <?php echo (int) $progress; ?>%;"></div>
                                                </div>
                                                <span class="prs-library__progress-value"><?php echo (int) $progress; ?>%</span>
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

		<?php wp_nonce_field( 'prs_update_user_book', 'prs_update_user_book_nonce' ); ?>
	</div>
		<?php
		return ob_get_clean();
	}
);
