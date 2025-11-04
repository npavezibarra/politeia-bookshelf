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

$tbl_b             = $wpdb->prefix . 'politeia_books';
$tbl_ub            = $wpdb->prefix . 'politeia_user_books';
$tbl_loans         = $wpdb->prefix . 'politeia_loans';
$tbl_sessions      = $wpdb->prefix . 'politeia_reading_sessions';
$tbl_session_notes = $wpdb->prefix . 'politeia_read_ses_notes';

$book = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl_b} WHERE slug=%s LIMIT 1", $slug ) );
if ( ! $book ) {
	status_header( 404 );
	echo '<div class="wrap"><h1>Not found</h1></div>';
	get_footer();
	exit; }

$ub = $wpdb->get_row(
        $wpdb->prepare(
                "SELECT * FROM {$tbl_ub} WHERE user_id=%d AND book_id=%d AND deleted_at IS NULL LIMIT 1",
                $user_id,
                $book->id
        )
);
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
   WHERE user_id=%d AND book_id=%d AND end_date IS NULL AND deleted_at IS NULL
   ORDER BY id DESC LIMIT 1",
                $user_id,
                $book->id
        )
);
$active_start_local = $active_start_gmt ? get_date_from_gmt( $active_start_gmt, 'Y-m-d' ) : '';

$sessions = $wpdb->get_results(
        $wpdb->prepare(
                "SELECT s.*, n.note FROM {$tbl_sessions} s LEFT JOIN {$tbl_session_notes} n ON s.id = n.rs_id AND n.book_id = s.book_id AND n.user_id = s.user_id WHERE s.user_id = %d AND s.book_id = %d AND s.deleted_at IS NULL ORDER BY s.start_time DESC",
                $user_id,
                $book->id
        )
);

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

$placeholder_title       = __( 'Untitled Book', 'politeia-reading' );
$placeholder_author      = __( 'Unknown Author', 'politeia-reading' );
$placeholder_label       = __( 'Default book cover', 'politeia-reading' );
$search_cover_label      = __( 'Search Cover', 'politeia-reading' );
$remove_cover_label      = __( 'Remove book cover', 'politeia-reading' );
$remove_cover_confirm    = __( 'Are you sure you want to remove this book cover?', 'politeia-reading' );

/** Encolar assets */
wp_enqueue_style( 'politeia-reading' );
wp_enqueue_style(
        'politeia-reading-layout',
        POLITEIA_READING_URL . 'assets/css/politeia-reading.css',
        array( 'politeia-reading' ),
        POLITEIA_READING_VERSION
);
wp_enqueue_script( 'politeia-my-book' ); // asegúrate de registrar este JS en tu plugin/tema

/** Datos al JS principal */
$owning_nonce        = wp_create_nonce( 'save_owning_contact' );
$meta_update_nonce   = wp_create_nonce( 'prs_update_user_book_meta' );
$cover_actions_nonce = wp_create_nonce( 'politeia_bookshelf_cover_actions' );
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
                'cover_nonce'   => $cover_actions_nonce,
                'user_id'       => (int) $user_id,
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
        border:none;
        padding:0;
        border-radius:0;
        background:none;
        display:inline-block;
        }
        .prs-single-grid{
        display:grid;
        grid-template-columns: 1fr;
        gap:0;
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
        min-height:100%;
        height:100%;
        padding:24px;
        border-radius:0px;
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
        @media (max-width: 950px){
        .prs-cover-actions{
        display:none !important;
        }
        }
        .prs-cover-placeholder:hover .prs-cover-actions,
        .prs-cover-placeholder:focus-within .prs-cover-actions{
        opacity:1;
        transform:translateY(0);
        }
        .prs-cover-btn{
        background:#000000;
        color:#fff;
        border:none;
        border-radius:6px;
        padding:10px 20px;
        font-weight:600;
        font-size:12px;
        cursor:pointer;
        transition:background 0.2s ease-in-out;
        }
        .prs-cover-actions .prs-cover-search-button{
        display:inline-flex;
        justify-content:center;
        }
        .prs-cover-remove{
        display:none;
        font-size:12px;
        color:#fff;
        text-decoration:underline;
        align-self:center;
        margin-top:4px;
        }
        .prs-cover-frame[data-cover-state="image"] .prs-cover-remove{
        display:inline-block;
        }
        .prs-cover-remove:hover,
        .prs-cover-remove:focus-visible{
        color:#fff;
        text-decoration:none;
        }
        .prs-cover-remove.is-disabled{
        opacity:0.6;
        pointer-events:none;
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
        .prs-cover-frame[data-cover-state="image"] .prs-cover-overlay{
        display:flex;
        }
        .prs-cover-frame[data-cover-state="empty"] .prs-cover-overlay{
        display:none;
        }
        .prs-cover-frame.has-image:hover .prs-cover-overlay,
        .prs-cover-frame.has-image:focus-within .prs-cover-overlay,
        .prs-cover-frame[data-cover-state="image"]:hover .prs-cover-overlay,
        .prs-cover-frame[data-cover-state="image"]:focus-within .prs-cover-overlay{
        opacity:1;
        background:rgba(0,0,0,0.25);
        pointer-events:auto;
        }
        .prs-cover-frame.has-image:hover .prs-cover-overlay .prs-cover-actions,
        .prs-cover-frame.has-image:focus-within .prs-cover-overlay .prs-cover-actions,
        .prs-cover-frame[data-cover-state="image"]:hover .prs-cover-overlay .prs-cover-actions,
        .prs-cover-frame[data-cover-state="image"]:focus-within .prs-cover-overlay .prs-cover-actions{
        opacity:1;
        transform:translateY(0);
        }
        .prs-search-cover-overlay{
        position:fixed;
        inset:0;
        background-color:rgba(0,0,0,0.6);
        display:flex;
        justify-content:center;
        align-items:center;
        z-index:1000;
        }
        .prs-search-cover-overlay.is-hidden{
        display:none;
        }
        .prs-search-cover-modal{
        background:#fff;
        padding:30px;
        border-radius:8px;
        width:80%;
        max-width:800px;
        text-align:center;
        }
        .prs-search-cover-title{
        font-size:20px;
        font-weight:600;
        margin-bottom:20px;
        }
        .prs-search-cover-options{
        display:flex;
        flex-wrap:wrap;
        gap:20px;
        justify-content:center;
        margin-bottom:12px;
        }
        .prs-cover-option{
        flex:1 1 160px;
        max-width:220px;
        border:1px solid #ccc;
        border-radius:8px;
        padding:12px;
        cursor:pointer;
        user-select:none;
        display:flex;
        justify-content:center;
        align-items:center;
        background-color:#fff;
        }
        .prs-cover-option.selected{
        border-color:#000;
        background-color:#f0f0f0;
        }
        .prs-cover-image{
        max-height:200px;
        width:auto;
        max-width:100%;
        object-fit:contain;
        }
        .prs-search-cover-attribution{
        font-size:12px;
        color:#555;
        text-align:center;
        margin:12px 0 0;
        }
        .prs-search-cover-actions{
        display:flex;
        justify-content:center;
        gap:16px;
        margin-top:20px;
        }
        .prs-btn{
        padding:10px 14px;
        background:#111;
        color:#fff;
        border:none;
        font-size:12px;
        cursor:pointer;
        box-shadow:none;
        outline:none;
        border-radius:6px;
        }
        #prs-cover-save,
        #prs-set-cover{
        background-color:var(--bb-primary-button-background-regular);
        }
        #prs-cover-save:hover,
        #prs-set-cover:hover{
        background-color:var(--bb-primary-button-background-hover, #1E42DD);
        }
        .prs-search-cover-actions .prs-btn{
        flex:1;
        max-width:200px;
        padding:12px 0;
        font-weight:600;
        font-size:14px;
        transition:background-color 0.2s ease;
        }
        .prs-cancel-cover-button{
        background-color:#000;
        color:#fff;
        }
        .prs-cancel-cover-button:hover{
        background-color:#222;
        }
        .prs-set-cover-button{
        color:#fff;
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
        .prs-session-modal__content{ position:relative; max-width:600px; width:100%; max-height:90vh; overflow-y:auto; background:#ffffff; padding:24px; border:1px solid #dddddd; border-radius:12px; }
        .prs-session-modal__close{ position:absolute; top:12px; right:12px; border:none; background:none; color:#000000; cursor:pointer; font-size:20px; line-height:1; padding:4px; outline:none; box-shadow:none; }
        .prs-session-modal__close:hover,
        .prs-session-modal__close:focus,
        .prs-session-modal__close:focus-visible{ background:none; box-shadow:none; color:#000000; outline:none; }
        .prs-meta{ color:#555; margin-top:0px; }
	.prs-table{ width:100%; border-collapse:collapse; background:#fff; }
        .prs-table th, .prs-table td{ padding:8px 10px; border-bottom:1px solid #eee; text-align:left; }
        .prs-sessions-table th,
        .prs-sessions-table td {
        padding:6px 6px;
        font-size:13px;
        }
        .prs-sessions-table th:nth-child(4),
        .prs-sessions-table th:nth-child(5),
        .prs-sessions-table th:nth-child(6) {
        text-align:center;
        }
        .prs-sessions-table td:nth-child(4),
        .prs-sessions-table td:nth-child(5),
        .prs-sessions-table td:nth-child(6) {
        text-align:center;
        }

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

        <div id="prs-single-grid" class="prs-single-grid">

        <div id="prs-book-info" class="prs-book-info prs-book-info-grid">

                <!-- Columna izquierda: portada -->
                <section id="prs-book-cover" class="prs-book-info__cover prs-book-cover" aria-label="<?php esc_attr_e( 'Book cover', 'politeia-reading' ); ?>">
                        <div
                                id="prs-cover-frame"
                                class="prs-cover-frame <?php echo $has_image ? 'has-image' : ''; ?>"
                                data-cover-state="<?php echo $has_image ? 'image' : 'empty'; ?>"
                                data-placeholder-title="<?php echo esc_attr( $placeholder_title ); ?>"
                                data-placeholder-author="<?php echo esc_attr( $placeholder_author ); ?>"
                                data-placeholder-label="<?php echo esc_attr( $placeholder_label ); ?>"
                                data-search-label="<?php echo esc_attr( $search_cover_label ); ?>"
                                data-remove-label="<?php echo esc_attr( $remove_cover_label ); ?>"
                                data-remove-confirm="<?php echo esc_attr( $remove_cover_confirm ); ?>">
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
                                aria-label="<?php echo esc_attr( $placeholder_label ); ?>">
                                <h2 id="prs-book-title-placeholder" class="prs-cover-title"><?php echo esc_html( $placeholder_title ); ?></h2>
                                <h3 id="prs-book-author-placeholder" class="prs-cover-author"><?php echo esc_html( $placeholder_author ); ?></h3>
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
                        <?php echo do_shortcode( '[prs_cover_button show_search="true"]' ); ?>
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

                <?php
                $total_pages      = ( isset( $ub->pages ) && $ub->pages ) ? (int) $ub->pages : 0;
                $density_sessions = array();

                if ( $total_pages > 0 && ! empty( $sessions ) ) {
                        foreach ( $sessions as $session ) {
                                if ( isset( $session->start_page, $session->end_page ) ) {
                                        $density_sessions[] = array(
                                                'start_page' => (int) $session->start_page,
                                                'end_page'   => (int) $session->end_page,
                                        );
                                }
                        }
                }
                ?>

                <?php if ( $total_pages > 0 && ! empty( $density_sessions ) ) : ?>
                <div class="prs-reading-density-bar mt-4">
                        <canvas
                                id="prs-reading-density-canvas"
                                data-total-pages="<?php echo esc_attr( $total_pages ); ?>"
                                data-sessions='<?php echo esc_attr( wp_json_encode( $density_sessions ) ); ?>'
                        ></canvas>
                </div>
                <?php endif; ?>

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
                <section id="prs-book-info__sidebar" class="prs-book-info__sidebar prs-flip-container" aria-label="<?php esc_attr_e( 'Reading and owning status', 'politeia-reading' ); ?>">
                        <div class="prs-flipper">
                                <div class="prs-front relative" aria-hidden="false">
                                        <button type="button" id="prs-flip-btn-front" class="prs-flip-button" aria-label="<?php esc_attr_e( 'Show calendar view', 'politeia-reading' ); ?>">
                                                <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                                        </button>
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
                                        <option value="borrowing" <?php selected( $ub->owning_status, 'borrowing' ); ?>><?php esc_html_e( 'Lent Out', 'politeia-reading' ); ?></option>
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
                                </div>
                                <div class="prs-back relative" aria-hidden="true">
                                        <button type="button" id="prs-flip-btn-back" class="prs-flip-button" aria-label="<?php esc_attr_e( 'Show reading and owning status', 'politeia-reading' ); ?>">
                                                <span class="dashicons dashicons-update" aria-hidden="true"></span>
                                        </button>
                                        <h3 class="prs-section-title"><?php esc_html_e( 'Reading Calendar', 'politeia-reading' ); ?></h3>
                                        <p class="prs-help"><?php esc_html_e( 'Plan your reading schedule for the month.', 'politeia-reading' ); ?></p>
                                        <?php
                                        $timezone_string         = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : 'UTC';
                                        if ( empty( $timezone_string ) ) {
                                                $timezone_string = 'UTC';
                                        }
                                        $calendar_timezone       = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( $timezone_string );
                                        if ( ! $calendar_timezone instanceof DateTimeZone ) {
                                                $calendar_timezone = new DateTimeZone( 'UTC' );
                                        }
                                        $calendar_context        = new DateTimeImmutable( 'now', $calendar_timezone );
                                        $calendar_first_day      = $calendar_context->modify( 'first day of this month' );
                                        $calendar_timestamp      = $calendar_first_day->getTimestamp();
                                        $calendar_month_label    = wp_date( 'F', $calendar_timestamp );
                                        $calendar_year_label     = wp_date( 'Y', $calendar_timestamp );
                                        $calendar_month_number   = (int) wp_date( 'n', $calendar_timestamp );
                                        $calendar_year_number    = (int) $calendar_year_label;
                                        $calendar_days_in_month  = (int) wp_date( 't', $calendar_timestamp );
                                        $calendar_first_weekday  = (int) wp_date( 'w', $calendar_timestamp );
                                        $calendar_day_names      = array(
                                                esc_html__( 'Sun', 'politeia-reading' ),
                                                esc_html__( 'Mon', 'politeia-reading' ),
                                                esc_html__( 'Tue', 'politeia-reading' ),
                                                esc_html__( 'Wed', 'politeia-reading' ),
                                                esc_html__( 'Thu', 'politeia-reading' ),
                                                esc_html__( 'Fri', 'politeia-reading' ),
                                                esc_html__( 'Sat', 'politeia-reading' ),
                                        );
                                        ?>
                                        <div class="prs-calendar-grid" role="grid" aria-label="<?php echo esc_attr( sprintf( __( '%1$s %2$s calendar', 'politeia-reading' ), $calendar_month_label, $calendar_year_label ) ); ?>">
                                                <?php foreach ( $calendar_day_names as $day_name ) : ?>
                                                        <div class="prs-calendar-day prs-calendar-day--label" role="columnheader"><?php echo esc_html( $day_name ); ?></div>
                                                <?php endforeach; ?>
                                                <?php for ( $i = 0; $i < $calendar_first_weekday; $i++ ) : ?>
                                                        <div class="prs-calendar-day prs-calendar-day--empty" aria-hidden="true"></div>
                                                <?php endfor; ?>
                                                <?php for ( $day = 1; $day <= $calendar_days_in_month; $day++ ) : ?>
                                                        <?php
                                                        $day_timestamp = $calendar_first_day->setDate( $calendar_year_number, $calendar_month_number, $day )->getTimestamp();
                                                        $cell_label    = wp_date( 'F j, Y', $day_timestamp );
                                                        ?>
                                                        <div class="prs-calendar-day" role="gridcell" aria-label="<?php echo esc_attr( $cell_label ); ?>"><?php echo (int) $day; ?></div>
                                                <?php endfor; ?>
                                        </div>
                                        <button type="button" class="prs-plan-btn"><?php esc_html_e( 'Plan a reading session', 'politeia-reading' ); ?></button>
                                </div>
                        </div>
                </section>
        </div>

        <section id="prs-reading-sessions" class="prs-book-sessions prs-reading-sessions">
                <h2 class="prs-section-title"><?php esc_html_e( 'Reading Sessions', 'politeia-reading' ); ?></h2>
                <?php if ( $sessions ) : ?>
                        <?php $current_user_id = get_current_user_id(); ?>
                        <table class="prs-table prs-sessions-table">
                                <thead>
                                        <tr>
                                                <th>#</th>
                                                <th>Start Time</th>
                                                <th>End Time</th>
                                                <th><?php esc_html_e( 'Note', 'politeia-reading' ); ?></th>
                                                <th>End Page</th>
                                                <th>Total Pages</th>
                                                <th>Chapter</th>
                                                <th>Duration</th>
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
                                                $start_page     = isset( $s->start_page ) ? (int) $s->start_page : null;
                                                $end_page       = isset( $s->end_page ) ? (int) $s->end_page : null;
                                                $total_pages    = null;
                                                if ( null !== $start_page && null !== $end_page ) {
                                                        $total_pages = $end_page - $start_page;
                                                }
                                                $chapter_label = $s->chapter_name ? $s->chapter_name : '—';
                                                ?>
                                                <?php
                                                $note_button = '—';
                                                $note_value  = isset( $s->note ) ? trim( (string) $s->note ) : '';
                                                if ( '' !== $note_value && ! empty( $s->id ) && $current_user_id ) {
                                                        $note_label  = esc_html__( 'Read Note', 'politeia-reading' );
                                                        $start_attr  = ( null !== $start_page && $start_page >= 0 ) ? (string) $start_page : '';
                                                        $end_attr    = ( null !== $end_page && $end_page >= 0 ) ? (string) $end_page : '';
                                                        $chapter_attr = isset( $s->chapter_name ) ? trim( (string) $s->chapter_name ) : '';
                                                        $book_title_attr = isset( $book->title ) ? trim( (string) $book->title ) : '';
                                                        $note_button = sprintf(
                                                                '<button type="button" class="prs-sr-read-note-btn" data-session-id="%1$d" data-book-id="%2$d" data-user-id="%3$d" data-note="%4$s" data-start-page="%6$s" data-end-page="%7$s" data-chapter="%8$s" data-book-title="%9$s">%5$s</button>',
                                                                (int) $s->id,
                                                                (int) $s->book_id,
                                                                (int) $current_user_id,
                                                                esc_attr( $note_value ),
                                                                $note_label,
                                                                esc_attr( $start_attr ),
                                                                esc_attr( $end_attr ),
                                                                esc_attr( $chapter_attr ),
                                                                esc_attr( $book_title_attr )
                                                        );
                                                }
                                                ?>
                                                <tr>
                                                        <td><?php echo esc_html( $i + 1 ); ?></td>
                                                        <td><?php echo wp_kses_post( $start_display ); ?></td>
                                                        <td><?php echo wp_kses_post( $end_display ); ?></td>
                                                        <td><?php echo wp_kses_post( $note_button ); ?></td>
                                                        <td><?php echo esc_html( ( null !== $end_page && $end_page >= 0 ) ? $end_page : '—' ); ?></td>
                                                        <td><?php echo esc_html( ( null !== $total_pages && $total_pages > 0 ) ? $total_pages : '—' ); ?></td>
                                                        <td><?php echo esc_html( $chapter_label ); ?></td>
                                                        <td><?php echo esc_html( $duration_str ); ?></td>
                                                </tr>
                                        <?php endforeach; ?>
                                </tbody>
                        </table>
                <?php else : ?>
                        <p class="prs-no-sessions"><?php esc_html_e( 'No sessions recorded for this book yet.', 'politeia-reading' ); ?></p>
                <?php endif; ?>
        </section>

        </div>
</div>

<div
        id="prs-session-modal"
        class="prs-session-modal"
        role="dialog"
        aria-modal="true"
        aria-hidden="true"
        aria-label="<?php esc_attr_e( 'Session recorder', 'politeia-reading' ); ?>"
>
        <div class="prs-session-modal__content">
                <button
                        type="button"
                        id="prs-session-recorder-close"
                        class="prs-session-modal__close"
                        aria-label="<?php esc_attr_e( 'Close session recorder', 'politeia-reading' ); ?>"
                >
                        ×
                </button>
                <?php echo do_shortcode( '[politeia_start_reading book_id="' . (int) $book->id . '"]' ); ?>
        </div>
</div>

<div id="prs-search-cover-overlay" class="prs-search-cover-overlay is-hidden" aria-hidden="true">
        <div class="prs-search-cover-modal">
                <h2 class="prs-search-cover-title"><?php esc_html_e( 'Search Book Cover', 'politeia-reading' ); ?></h2>

                <div class="prs-search-cover-options"></div>

                <div class="prs-search-cover-actions">
                        <button id="prs-cancel-cover" class="prs-btn prs-cancel-cover-button" type="button"><?php esc_html_e( 'CANCEL', 'politeia-reading' ); ?></button>
                        <button id="prs-set-cover" class="prs-btn prs-set-cover-button" type="button" disabled="disabled"><?php esc_html_e( 'SET COVER', 'politeia-reading' ); ?></button>
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
