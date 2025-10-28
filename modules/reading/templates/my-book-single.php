<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();

if ( ! is_user_logged_in() ) {
	echo '<div class="wrap"><p>You must be logged in.</p></div>';
	get_footer();
	exit;
}

global $wpdb;
$user_id = get_current_user_id();
$slug    = get_query_var( 'prs_book_slug' );

$tbl_b     = $wpdb->prefix . 'politeia_books';
$tbl_ub    = $wpdb->prefix . 'politeia_user_books';
$tbl_loans = $wpdb->prefix . 'politeia_loans';

$book = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl_b} WHERE slug=%s LIMIT 1", $slug ) );
if ( ! $book ) {
	status_header( 404 );
	echo '<div class="wrap"><h1>Not found</h1></div>';
	get_footer();
	exit; }

$ub = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl_ub} WHERE user_id=%d AND book_id=%d LIMIT 1", $user_id, $book->id ) );
if ( ! $ub ) {
	status_header( 403 );
	echo '<div class="wrap"><h1>No access</h1><p>This book is not in your library.</p></div>';
	get_footer();
	exit; }

/** Contacto ya guardado (definir antes de localize) */
$has_contact = ( ! empty( $ub->counterparty_name ) ) || ( ! empty( $ub->counterparty_email ) );
$contact_name  = $ub->counterparty_name ? (string) $ub->counterparty_name : '';
$contact_email = $ub->counterparty_email ? (string) $ub->counterparty_email : '';

$label_borrowing = __( 'Borrowing to:', 'politeia-reading' );
$label_borrowed  = __( 'Borrowed from:', 'politeia-reading' );
$label_sold      = __( 'Sold to:', 'politeia-reading' );
$label_lost      = __( 'Last borrowed to:', 'politeia-reading' );
$label_sold_on   = __( 'Sold on:', 'politeia-reading' );
$label_lost_date = __( 'Lost:', 'politeia-reading' );
$label_unknown   = __( 'Unknown', 'politeia-reading' );

$owning_message = '';
if ( $contact_name ) {
        switch ( (string) $ub->owning_status ) {
                case 'borrowing':
                        $owning_message = sprintf( '%s %s', $label_borrowing, $contact_name );
                        break;
                case 'borrowed':
                        $owning_message = sprintf( '%s %s', $label_borrowed, $contact_name );
                        break;
                case 'sold':
                        $owning_message = sprintf( '%s %s', $label_sold, $contact_name );
                        break;
                case 'lost':
                        $owning_message = sprintf( '%s %s', $label_lost, $contact_name );
                        break;
        }
}

/** Préstamo activo (fecha local) */
$active_start_gmt   = $wpdb->get_var(
        $wpdb->prepare(
                "SELECT start_date FROM {$tbl_loans}
   WHERE user_id=%d AND book_id=%d AND end_date IS NULL
   ORDER BY id DESC LIMIT 1",
                $user_id,
                $book->id
        )
);
$active_start_local = $active_start_gmt ? get_date_from_gmt( $active_start_gmt, 'Y-m-d' ) : '';

$current_type = ( isset( $ub->type_book ) && in_array( $ub->type_book, array( 'p', 'd' ), true ) ) ? $ub->type_book : '';

$user_cover_raw     = '';
if ( isset( $ub->cover_reference ) && '' !== $ub->cover_reference && null !== $ub->cover_reference ) {
        $user_cover_raw = $ub->cover_reference;
} elseif ( isset( $ub->cover_attachment_id_user ) ) {
        $user_cover_raw = $ub->cover_attachment_id_user;
}
$parsed_user_cover  = method_exists( 'PRS_Cover_Upload_Feature', 'parse_cover_value' ) ? PRS_Cover_Upload_Feature::parse_cover_value( $user_cover_raw ) : array(
        'attachment_id' => is_numeric( $user_cover_raw ) ? (int) $user_cover_raw : 0,
        'url'           => '',
        'source'        => '',
);
$user_cover_id      = isset( $parsed_user_cover['attachment_id'] ) ? (int) $parsed_user_cover['attachment_id'] : 0;
$canon_cover_id     = isset( $book->cover_attachment_id ) ? (int) $book->cover_attachment_id : 0;
$user_cover_url     = isset( $parsed_user_cover['url'] ) ? trim( (string) $parsed_user_cover['url'] ) : '';
$user_cover_url     = $user_cover_url ? esc_url_raw( $user_cover_url ) : '';
$user_cover_source  = isset( $parsed_user_cover['source'] ) ? trim( (string) $parsed_user_cover['source'] ) : '';
$attachment_source  = $user_cover_id ? get_post_meta( $user_cover_id, '_prs_cover_source', true ) : '';
if ( $attachment_source ) {
        $user_cover_source = (string) $attachment_source;
}
$user_cover_source = $user_cover_source ? esc_url_raw( $user_cover_source ) : '';
$book_cover_url     = isset( $book->cover_url ) ? trim( (string) $book->cover_url ) : '';
$book_cover_source  = $book_cover_url ? trim( isset( $book->cover_source ) ? (string) $book->cover_source : '' ) : '';
$book_cover_source  = $book_cover_source ? esc_url_raw( $book_cover_source ) : '';
$cover_url          = '';
$cover_source       = '';
$final_cover_id     = 0;

if ( $user_cover_url ) {
        $cover_url     = $user_cover_url;
        $cover_source  = $user_cover_source;
} else {
        $final_cover_id = $user_cover_id ?: $canon_cover_id;
        if ( 0 === $final_cover_id && $book_cover_url ) {
                $cover_url    = $book_cover_url;
                $cover_source = $book_cover_source;
        }
}

$has_image = ( $final_cover_id > 0 ) || '' !== $cover_url;

/** Encolar assets */
wp_enqueue_style( 'politeia-reading' );
wp_enqueue_script( 'politeia-my-book' ); // asegúrate de registrar este JS en tu plugin/tema

/** Datos al JS principal */
$owning_nonce        = wp_create_nonce( 'save_owning_contact' );
$meta_update_nonce   = wp_create_nonce( 'prs_update_user_book_meta' );
wp_localize_script(
        'politeia-my-book',
        'PRS_BOOK',
        array(
                'ajax_url'      => admin_url( 'admin-ajax.php' ),
                'nonce'         => $meta_update_nonce,
                'owning_nonce'  => $owning_nonce,
                'user_book_id'  => (int) $ub->id,
                'book_id'       => (int) $book->id,
                'owning_status' => (string) $ub->owning_status,
                'has_contact'   => $has_contact ? 1 : 0,
                'rating'        => isset( $ub->rating ) && $ub->rating !== null ? (int) $ub->rating : 0,
                'type_book'     => (string) $current_type,
                'title'         => (string) $book->title,
                'author'        => (string) $book->author,
                'cover_url'     => $cover_url,
                'language'      => isset( $book->language ) ? (string) $book->language : '',
                'cover_source'  => $cover_source,
        )
);
wp_add_inline_script(
        'politeia-my-book',
        sprintf(
                'window.PRS_BOOK_ID=%1$d;window.PRS_USER_BOOK_ID=%2$d;window.PRS_NONCE=%3$s;',
                (int) $book->id,
                (int) $ub->id,
                wp_json_encode( $owning_nonce )
        ),
        'before'
);
?>
<style>
        /* Maqueta general */
        .prs-back-link-wrap{
        margin-top:20px;
        font-size:14px;
        }
        .prs-back-link{
        color:#868686;
        outline:0;
        text-decoration:none;
        border:1px solid #b3b3b3;
        padding:1px 6px;
        border-radius:4px;
        background:#ffffff;
        display:inline-block;
        }
        .prs-single-grid{
        display:grid;
        grid-template-columns: 1fr;
        gap:24px;
        margin: 16px 0 32px;
        }
        .prs-box{ background:#f9f9f9; padding:16px; min-height:120px; }
        #prs-book-info{ min-height:140px; }
        #prs-book-stats{ grid-column:3; grid-row:1; min-height:auto; background:#ffffff;
        padding: 16px; border: 1px solid #dddddd; align-self:start; }
        #prs-reading-sessions{ grid-column:1 / 4; grid-row:3; min-height:320px; }

	/* Frame portada */
        .prs-cover-frame{
        position:relative; overflow:hidden;
        background:#eee; border-radius:12px; align-self:flex-start;
        }
        .prs-cover-img{ width:100%; height:auto; object-fit:contain; display:block; border-radius:inherit; }
        .prs-book-cover{ margin:0; }
        .prs-cover-placeholder{
        display:flex;
        flex-direction:column;
        justify-content:center;
        align-items:center;
        gap:10px;
        text-align:center;
        width:100%;
        height:400px;
        padding:24px;
        border-radius:8px;
        background:linear-gradient(180deg, #b98a55 0%, #3a1d0b 100%);
        color:#111;
        font-family:'Inter', sans-serif;
        position:relative;
        overflow:hidden;
        transition:all 0.3s ease-in-out;
        }
        .prs-cover-title{
        margin:0;
        max-width:90%;
        font-weight:800;
        font-size:clamp(20px, 2.6vw, 28px);
        text-shadow:0 1px 0 rgba(255,255,255,0.25);
        }
        .prs-cover-author{
        margin:4px 0 0;
        opacity:0.9;
        font-size:clamp(14px, 2vw, 18px);
        }
        .prs-cover-actions{
        display:flex;
        flex-direction:column;
        gap:10px;
        margin-top:18px;
        opacity:0;
        transform:translateY(10px);
        transition:opacity 0.25s ease, transform 0.25s ease;
        }
        .prs-cover-placeholder:hover .prs-cover-actions,
        .prs-cover-placeholder:focus-within .prs-cover-actions{
        opacity:1;
        transform:translateY(0);
        }
        .prs-cover-btn{
        background:#111;
        color:#fff;
        border:none;
        border-radius:14px;
        padding:10px 20px;
        font-weight:600;
        cursor:pointer;
        transition:background 0.2s ease-in-out;
        }
        .prs-cover-btn:hover,
        .prs-cover-btn:focus-visible{
        background:#2a2a2a;
        }
        .prs-cover-overlay{
        position:absolute;
        inset:0;
        display:flex;
        justify-content:center;
        align-items:center;
        background:rgba(0,0,0,0);
        pointer-events:none;
        opacity:0;
        transition:opacity 0.25s ease, background 0.25s ease;
        }
        .prs-cover-overlay .prs-cover-actions{
        margin-top:0;
        opacity:0;
        transform:translateY(10px);
        pointer-events:auto;
        }
        .prs-cover-frame.has-image:hover .prs-cover-overlay,
        .prs-cover-frame.has-image:focus-within .prs-cover-overlay{
        opacity:1;
        background:rgba(0,0,0,0.25);
        pointer-events:auto;
        }
        .prs-cover-frame.has-image:hover .prs-cover-overlay .prs-cover-actions,
        .prs-cover-frame.has-image:focus-within .prs-cover-overlay .prs-cover-actions{
        opacity:1;
        transform:translateY(0);
        }

	/* Tipos y tablas */
        .prs-box h2{ margin:0 0 8px; }
        .prs-book-title{ display:flex; align-items:center; gap:12px; margin:0; }
        .prs-book-title__text{ flex:1 1 auto; }
        .prs-session-recorder-trigger{ display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; background:#000; color:#fff; border:none; border-radius:6px; cursor:pointer; padding:0; font-size:18px; line-height:1; }
        .prs-session-recorder-trigger:hover,
        .prs-session-recorder-trigger:focus{ background:#222; }
        .prs-session-recorder-trigger:focus{ outline:2px solid #fff; outline-offset:2px; }
        .prs-session-modal{ display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center; padding:24px; }
        .prs-session-modal.is-active{ display:flex; }
        .prs-session-modal__content{ position:relative; max-width:440px; width:100%; max-height:90vh; overflow-y:auto; background:#ffffff; padding:24px; border:1px solid #dddddd; border-radius:12px; }
        .prs-session-modal__close{ position:absolute; top:12px; right:12px; border:none; background:none; color:#000000; cursor:pointer; font-size:20px; line-height:1; padding:4px; outline:none; box-shadow:none; }
        .prs-session-modal__close:hover,
        .prs-session-modal__close:focus,
        .prs-session-modal__close:focus-visible{ background:none; box-shadow:none; color:#000000; outline:none; }
        .prs-meta{ color:#555; margin-top:0px; }
	.prs-table{ width:100%; border-collapse:collapse; background:#fff; }
	.prs-table th, .prs-table td{ padding:8px 10px; border-bottom:1px solid #eee; text-align:left; }

        .prs-field{ margin-top:0px; }
	.prs-field .label{ font-weight:600; display:block; margin-bottom:4px; }
        .prs-inline-actions{ margin-left:0; }
	.prs-inline-actions a{ margin-left:8px; }
	.prs-help{ color:#666; font-size:12px; }
	#derived-location{ margin-top:6px; font-size:0.9em; color:#333; }
	#derived-location strong{ font-weight:600; }

	/* Contact form (3 filas) */
	.prs-contact-form{
	display:flex;
	flex-direction:column;
	gap:10px;
	max-width:600px;
	margin:0 !important;
	}
	.prs-contact-label{ align-self:center; font-weight:600; }
	.prs-contact-input{ width:100%; }
	.prs-contact-actions{ display:flex; align-items:center; gap:10px; }

	/* Paginación (parcial AJAX) */
	.prs-pagination ul.page-numbers{ display:flex; gap:6px; list-style:none; justify-content: center; }
	.prs-pagination .page-numbers{ padding:6px 10px; background:#fff; border:1px solid #ddd; border-radius:6px; text-decoration:none; width: fit-content; margin: auto; padding: 10px !important }
	.prs-pagination .current{ font-weight:700; }
        @media (max-width: 900px){
        .prs-single-grid{ grid-template-columns: 1fr; grid-template-rows:auto; }
        #prs-book-info, #prs-book-stats, #prs-reading-sessions{ grid-column:1; }
        .prs-contact-form{ flex-direction:column; margin-left:0; }
        }
</style>

<div class="wrap">
        <p class="prs-back-link-wrap"><a class="prs-back-link" href="<?php echo esc_url( home_url( '/my-books' ) ); ?>">&larr; Back to My Books</a></p>

	<div class="prs-single-grid">

        <div id="prs-book-info" class="prs-book-info-grid">

                <!-- Columna izquierda: portada -->
                <section id="prs-book-cover" class="prs-book-info__cover" aria-label="<?php esc_attr_e( 'Book cover', 'politeia-reading' ); ?>">
                        <div id="prs-cover-frame" class="prs-cover-frame <?php echo $has_image ? 'has-image' : ''; ?>">
                <figure class="prs-book-cover" id="prs-book-cover-figure">
                <?php if ( $has_image ) : ?>
                        <?php
                        if ( $final_cover_id ) {
                                $cover_image_src = wp_get_attachment_image_src( $final_cover_id, 'large' );
                                if ( $cover_image_src ) {
                                        $cover_alt = trim( (string) get_post_meta( $final_cover_id, '_wp_attachment_image_alt', true ) );
                                        if ( ! $cover_alt && ! empty( $book->title ) ) {
                                                $cover_alt = $book->title;
                                        }
                                        if ( ! $cover_alt ) {
                                                $cover_alt = __( 'Book cover', 'politeia-reading' );
                                        }

                                        printf(
                                                '<img src="%1$s" class="prs-cover-img" id="prs-cover-img" alt="%2$s" />',
                                                esc_url( $cover_image_src[0] ),
                                                esc_attr( $cover_alt )
                                        );
                                }
                        } elseif ( $cover_url ) {
                                $fallback_alt = ! empty( $book->title ) ? $book->title : __( 'Book cover', 'politeia-reading' );
                                printf(
                                        '<img src="%1$s" class="prs-cover-img" id="prs-cover-img" alt="%2$s" />',
                                        esc_url( $cover_url ),
                                        esc_attr( $fallback_alt )
                                );
                        }
                        ?>
                <?php else : ?>
                        <div
                                id="prs-cover-placeholder"
                                class="prs-cover-placeholder"
                                role="img"
                                aria-label="<?php esc_attr_e( 'Default book cover', 'politeia-reading' ); ?>">
                                <h2 id="prs-book-title-placeholder" class="prs-cover-title"><?php esc_html_e( 'Untitled Book', 'politeia-reading' ); ?></h2>
                                <h3 id="prs-book-author-placeholder" class="prs-cover-author"><?php esc_html_e( 'Unknown Author', 'politeia-reading' ); ?></h3>
                                <?php echo do_shortcode( '[prs_cover_button]' ); ?>
                        </div>
                <?php endif; ?>
                        <figcaption
                                id="prs-cover-attribution-wrap"
                                class="prs-book-cover__caption <?php echo $cover_source ? '' : 'is-hidden'; ?>"
                                aria-hidden="<?php echo $cover_source ? 'false' : 'true'; ?>">
                                <a
                                        id="prs-cover-attribution"
                                        class="prs-book-cover__link <?php echo $cover_source ? '' : 'is-hidden'; ?>"
                                        <?php echo $cover_source ? 'href="' . esc_url( $cover_source ) . '"' : ''; ?>
                                        target="_blank"
                                        rel="noopener noreferrer">
                                        <?php esc_html_e( 'View on Google Books', 'politeia-reading' ); ?>
                                </a>
                        </figcaption>
                </figure>
                <?php if ( $has_image ) : ?>
                <div class="prs-cover-overlay">
                        <?php echo do_shortcode( '[prs_cover_button]' ); ?>
                </div>
                <?php endif; ?>
                </div>
                </section>

                <!-- Arriba centro: título/info y metacampos -->
                <section id="prs-book-info__main" class="prs-book-info__main" aria-label="<?php esc_attr_e( 'Book information', 'politeia-reading' ); ?>">
                <h2 class="prs-book-title">
                        <span class="prs-book-title__text"><?php echo esc_html( $book->title ); ?></span>
                        <button type="button" id="prs-session-recorder-open" class="prs-session-recorder-trigger" aria-label="<?php esc_attr_e( 'Open session recorder', 'politeia-reading' ); ?>" aria-controls="prs-session-modal" aria-expanded="false">
                                <span aria-hidden="true">▶</span>
                                <span class="screen-reader-text"><?php esc_html_e( 'Open session recorder', 'politeia-reading' ); ?></span>
                        </button>
                </h2>
                <div class="prs-meta">
                        <strong class="prs-book-author"><?php echo esc_html( $book->author ); ?></strong>
                        <?php echo $book->year ? ' · ' . (int) $book->year : ''; ?>
                </div>

                <div class="prs-field prs-inline-field" id="fld-pages">
                        <span class="label"><?php esc_html_e( 'Pages:', 'politeia-reading' ); ?></span>
                        <span id="pages-view" class="prs-inline-value"><?php echo $ub->pages ? (int) $ub->pages : '—'; ?></span>
                        <a href="#" id="pages-edit" class="prs-inline-actions"><?php esc_html_e( 'edit', 'politeia-reading' ); ?></a>
                        <input type="number" id="pages-input" class="prs-inline-input" min="1" value="<?php echo $ub->pages ? (int) $ub->pages : ''; ?>" style="display:none;width:80px;" />
                        <div id="pages-hint" class="prs-help" style="display:none;margin-top:4px;">
                                <?php esc_html_e( 'Press Enter to save', 'politeia-reading' ); ?>
                        </div>
                </div>

                <?php
                $current_rating = isset( $ub->rating ) && null !== $ub->rating ? (int) $ub->rating : 0;
                $is_digital     = ( 'd' === $current_type );
                ?>
                <div class="prs-field prs-field--rating-type" id="fld-user-rating">
                        <div class="prs-rating-block">
                                <div id="prs-user-rating" class="prs-stars" role="radiogroup" aria-label="<?php esc_attr_e( 'Your rating', 'politeia-reading' ); ?>">
                                        <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                                                <button type="button"
                                                        class="prs-star<?php echo ( $i <= $current_rating ) ? ' is-active' : ''; ?>"
                                                        data-value="<?php echo $i; ?>"
                                                        role="radio"
                                                        aria-checked="<?php echo ( $i === $current_rating ) ? 'true' : 'false'; ?>">
                                                        ★
                                                </button>
                                        <?php endfor; ?>
                                </div>
                                <span id="rating-status" class="prs-help" aria-live="polite"></span>
                        </div>
                        <div class="prs-type-book">
                                <label for="prs-type-book" class="prs-type-book__label"><?php esc_html_e( 'Format', 'politeia-reading' ); ?></label>
                                <select id="prs-type-book" class="prs-type-book__select">
                                        <option value="" <?php selected( $current_type, '' ); ?>><?php esc_html_e( 'Not specified', 'politeia-reading' ); ?></option>
                                        <option value="d" <?php selected( $current_type, 'd' ); ?>><?php esc_html_e( 'Digital', 'politeia-reading' ); ?></option>
                                        <option value="p" <?php selected( $current_type, 'p' ); ?>><?php esc_html_e( 'Printed', 'politeia-reading' ); ?></option>
                                </select>
                                <span id="type-book-status" class="prs-help" aria-live="polite"></span>
                        </div>
                </div>

               <!-- Purchase Date & Channel -->
               <div class="prs-purchase-row">
                        <div class="prs-field prs-purchase-field" id="fld-purchase-date">
                                <label class="label"><?php esc_html_e( 'Purchase Date', 'politeia-reading' ); ?></label>
                                <span id="purchase-date-view"><?php echo $ub->purchase_date ? esc_html( $ub->purchase_date ) : '—'; ?></span>
                                <a href="#" id="purchase-date-edit" class="prs-inline-actions"><?php esc_html_e( 'edit', 'politeia-reading' ); ?></a>
                                <span id="purchase-date-form" style="display:none;" class="prs-inline-actions">
                                        <input type="date" id="purchase-date-input" value="<?php echo $ub->purchase_date ? esc_attr( $ub->purchase_date ) : ''; ?>" />
                                        <button type="button" id="purchase-date-save" class="prs-btn">Save</button>
                                        <button type="button" id="purchase-date-cancel" class="prs-btn">Cancel</button>
                                        <span id="purchase-date-status" class="prs-help"></span>
                                </span>
                        </div>

                        <!-- Purchase Channel + Which? -->
                        <div class="prs-field prs-purchase-field" id="fld-purchase-channel">
                                <label class="label"><?php esc_html_e( 'Purchase Channel', 'politeia-reading' ); ?></label>
                                <span id="purchase-channel-view">
                                        <?php
                                        $label = '—';
                                        if ( $ub->purchase_channel ) {
                                                $label = ucfirst( $ub->purchase_channel );
                                                if ( $ub->purchase_place ) {
                                                        $label .= ' — ' . $ub->purchase_place;
                                                }
                                        }
                                        echo esc_html( $label );
                                        ?>
                                </span>
                                <a href="#" id="purchase-channel-edit" class="prs-inline-actions"><?php esc_html_e( 'edit', 'politeia-reading' ); ?></a>
                                <span id="purchase-channel-form" style="display:none;" class="prs-inline-actions">
                                        <div>
                                        <select id="purchase-channel-select">
                                                <option value=""><?php esc_html_e( 'Select…', 'politeia-reading' ); ?></option>
                                                <option value="online" <?php selected( $ub->purchase_channel, 'online' ); ?>><?php esc_html_e( 'Online', 'politeia-reading' ); ?></option>
                                                <option value="store"  <?php selected( $ub->purchase_channel, 'store' ); ?>><?php esc_html_e( 'Store', 'politeia-reading' ); ?></option>
                                        </select>
                                        <input type="text" id="purchase-place-input" placeholder="<?php esc_attr_e( 'Which?', 'politeia-reading' ); ?>"
                                                        value="<?php echo $ub->purchase_place ? esc_attr( $ub->purchase_place ) : ''; ?>"
                                                        style="display: <?php echo $ub->purchase_channel ? 'inline-block' : 'none'; ?>;" />
                                        </div>
                                        <button type="button" id="purchase-channel-save" class="prs-btn">Save</button>
                                        <button type="button" id="purchase-channel-cancel" class="prs-btn">Cancel</button>
                                        <span id="purchase-channel-status" class="prs-help"></span>
                                </span>
                        </div>
                </div>
                </section>

                <!-- Status Row -->
                <section id="prs-book-info__sidebar" class="prs-book-info__sidebar" aria-label="<?php esc_attr_e( 'Reading and owning status', 'politeia-reading' ); ?>">
                <div id="prs-status-row" class="prs-status-row">
                        <?php
                                // "In Shelf" es derivado solo cuando owning_status es NULL/''.
                                $is_in_shelf = empty( $ub->owning_status );
                        ?>
                        <?php
                                $reading_disabled        = in_array( $ub->owning_status, array( 'borrowing', 'borrowed' ), true );
                                $reading_disabled_text    = __( 'Disabled while this book is being borrowed.', 'politeia-reading' );
                                $reading_disabled_title   = $reading_disabled ? ' title="' . esc_attr( $reading_disabled_text ) . '"' : '';
                                $reading_disabled_attr    = $reading_disabled ? ' disabled="disabled"' : '';
                                $reading_disabled_class   = $reading_disabled ? ' is-disabled' : '';
                        ?>
                        <div class="prs-field prs-status-field" id="fld-reading-status">
                                <label class="label" for="reading-status-select"><?php esc_html_e( 'Reading Status', 'politeia-reading' ); ?></label>
                                <select
                                        id="reading-status-select"
                                        class="reading-status-select<?php echo esc_attr( $reading_disabled_class ); ?>"
                                        data-disabled-text="<?php echo esc_attr( $reading_disabled_text ); ?>"
                                        aria-disabled="<?php echo $reading_disabled ? 'true' : 'false'; ?>"<?php echo $reading_disabled_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $reading_disabled_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                >
                                        <option value="not_started" <?php selected( $ub->reading_status, 'not_started' ); ?>><?php esc_html_e( 'Not Started', 'politeia-reading' ); ?></option>
                                        <option value="started"     <?php selected( $ub->reading_status, 'started' ); ?>><?php esc_html_e( 'Started', 'politeia-reading' ); ?></option>
                                        <option value="finished"    <?php selected( $ub->reading_status, 'finished' ); ?>><?php esc_html_e( 'Finished', 'politeia-reading' ); ?></option>
                                </select>
                                <span id="reading-status-status" class="prs-help"></span>
                                <p class="prs-location" id="derived-location">
                                        <strong><?php esc_html_e( 'Location', 'politeia-reading' ); ?>:</strong>
                                        <span id="derived-location-text"><?php echo $is_in_shelf ? esc_html__( 'In Shelf', 'politeia-reading' ) : esc_html__( 'Not In Shelf', 'politeia-reading' ); ?></span>
                                </p>
                        </div>

                        <!-- Owning Status (editable) + Contact (condicional) -->
                        <div class="prs-field prs-status-field" id="fld-owning-status" data-contact-name="<?php echo esc_attr( $contact_name ); ?>" data-contact-email="<?php echo esc_attr( $contact_email ); ?>" data-label-borrowing="<?php echo esc_attr( $label_borrowing ); ?>" data-label-borrowed="<?php echo esc_attr( $label_borrowed ); ?>" data-label-sold="<?php echo esc_attr( $label_sold ); ?>" data-label-lost="<?php echo esc_attr( $label_lost ); ?>" data-label-sold-on="<?php echo esc_attr( $label_sold_on ); ?>" data-label-lost-date="<?php echo esc_attr( $label_lost_date ); ?>" data-label-unknown="<?php echo esc_attr( $label_unknown ); ?>" data-active-start="<?php echo esc_attr( $active_start_local ); ?>">
                                <label class="label" for="owning-status-select"><?php esc_html_e( 'Owning Status', 'politeia-reading' ); ?></label>
                                <select id="owning-status-select" <?php disabled( $is_digital ); ?> aria-disabled="<?php echo $is_digital ? 'true' : 'false'; ?>">
                                        <option value="" <?php selected( empty( $ub->owning_status ) ); ?>><?php esc_html_e( '— Select —', 'politeia-reading' ); ?></option>
                                        <option value="borrowed"  <?php selected( $ub->owning_status, 'borrowed' ); ?>><?php esc_html_e( 'Borrowed', 'politeia-reading' ); ?></option>
                                        <option value="borrowing" <?php selected( $ub->owning_status, 'borrowing' ); ?>><?php esc_html_e( 'Borrowing', 'politeia-reading' ); ?></option>
                                        <option value="bought"    <?php selected( $ub->owning_status, 'bought' ); ?>><?php esc_html_e( 'Bought', 'politeia-reading' ); ?></option>
                                        <option value="sold"      <?php selected( $ub->owning_status, 'sold' ); ?>><?php esc_html_e( 'Sold', 'politeia-reading' ); ?></option>
                                        <option value="lost"      <?php selected( $ub->owning_status, 'lost' ); ?>><?php esc_html_e( 'Lost', 'politeia-reading' ); ?></option>
                                </select>

                                <?php $show_return_btn = in_array( $ub->owning_status, array( 'borrowed', 'borrowing' ), true ); ?>
                                <button
                                        type="button"
                                        id="owning-return-shelf"
                                        class="prs-btn owning-return-shelf"
                                        data-book-id="<?php echo (int) $book->id; ?>"
                                        data-user-book-id="<?php echo (int) $ub->id; ?>"
                                        style="<?php echo $show_return_btn ? '' : 'display:none;'; ?>"
                                        <?php disabled( $is_digital ); ?>
                                >
                                        <?php esc_html_e( 'Mark as returned', 'politeia-reading' ); ?>
                                </button>

                                <span id="owning-status-status" class="prs-help owning-status-info" data-book-id="<?php echo (int) $book->id; ?>"><?php echo $owning_message ? esc_html( $owning_message ) : ''; ?></span>
                                <p id="owning-status-note" class="prs-help prs-owning-status-note" style="<?php echo $is_digital ? '' : 'display:none;'; ?>">
                                        <?php esc_html_e( 'Owning status is available only for printed copies.', 'politeia-reading' ); ?>
                                </p>

                        </div>
                </div>
                </section>
        </div>

        </div>
</div>

<?php prs_render_owning_overlay( array( 'heading' => $label_borrowing ) ); ?>

<div id="return-overlay" class="prs-overlay" style="display:none;">
        <div class="prs-overlay-backdrop"></div>
        <div class="prs-overlay-content">
                <h2><?php esc_html_e( 'Are you sure you want to mark as returned?', 'politeia-reading' ); ?></h2>
                <div class="prs-overlay-actions">
                        <button type="button" id="return-overlay-yes" class="prs-btn"><?php esc_html_e( 'Yes', 'politeia-reading' ); ?></button>
                        <button type="button" id="return-overlay-no" class="prs-btn prs-btn-secondary"><?php esc_html_e( 'No', 'politeia-reading' ); ?></button>
                </div>
        </div>
</div>
<?php
get_footer();
