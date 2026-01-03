<?php
// Template ver-2
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

$tbl_b            = $wpdb->prefix . 'politeia_books';
$tbl_ub           = $wpdb->prefix . 'politeia_user_books';
$tbl_authors      = $wpdb->prefix . 'politeia_authors';
$tbl_book_authors = $wpdb->prefix . 'politeia_book_authors';

$book_id = prs_get_book_id_by_primary_slug( $slug );
if ( ! $book_id ) {
	$book_id = prs_get_book_id_by_slug( $slug );
}

$book = null;
if ( $book_id ) {
	$book = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT b.*,
					(
						SELECT GROUP_CONCAT(a.display_name ORDER BY ba.sort_order ASC SEPARATOR ', ')
						FROM {$tbl_book_authors} ba
						LEFT JOIN {$tbl_authors} a ON a.id = ba.author_id
						WHERE ba.book_id = b.id
					) AS authors
			 FROM {$tbl_b} b
			 WHERE b.id=%d
			 LIMIT 1",
			$book_id
		)
	);
}

if ( ! $book ) {
	status_header( 404 );
	echo '<div class="wrap"><h1>Not found</h1></div>';
	get_footer();
	exit;
}

$primary_slug = prs_get_primary_slug_for_book( (int) $book->id );
if ( $primary_slug && ( $slug !== $primary_slug ) ) {
	wp_safe_redirect( home_url( '/my-books/my-book-' . $primary_slug . '/' ), 301 );
	exit;
}

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
	exit;
}

$user_cover_raw = '';
if ( isset( $ub->cover_reference ) && '' !== $ub->cover_reference && null !== $ub->cover_reference ) {
	$user_cover_raw = $ub->cover_reference;
} elseif ( isset( $ub->cover_attachment_id_user ) ) {
	$user_cover_raw = $ub->cover_attachment_id_user;
}

$parsed_user_cover = method_exists( 'PRS_Cover_Upload_Feature', 'parse_cover_value' )
	? PRS_Cover_Upload_Feature::parse_cover_value( $user_cover_raw )
	: array(
		'attachment_id' => is_numeric( $user_cover_raw ) ? (int) $user_cover_raw : 0,
		'url'           => '',
		'source'        => '',
	);
$user_cover_id    = isset( $parsed_user_cover['attachment_id'] ) ? (int) $parsed_user_cover['attachment_id'] : 0;
$canon_cover_id   = isset( $book->cover_attachment_id ) ? (int) $book->cover_attachment_id : 0;
$user_cover_url   = isset( $parsed_user_cover['url'] ) ? trim( (string) $parsed_user_cover['url'] ) : '';
$user_cover_url   = $user_cover_url ? esc_url_raw( $user_cover_url ) : '';
$book_cover_url   = isset( $book->cover_url ) ? trim( (string) $book->cover_url ) : '';
$cover_url        = '';
$final_cover_id   = 0;
$force_http_covers = function_exists( 'politeia_bookshelf_force_http_covers' ) ? politeia_bookshelf_force_http_covers() : false;
$cover_scheme      = $force_http_covers ? 'http' : ( is_ssl() ? 'https' : 'http' );

if ( $user_cover_url ) {
	$cover_url = $user_cover_url;
} else {
	$final_cover_id = $user_cover_id ?: $canon_cover_id;
	if ( 0 === $final_cover_id && $book_cover_url ) {
		$cover_url = $book_cover_url;
	}
}

$book_title   = ! empty( $book->title ) ? (string) $book->title : __( 'Untitled Book', 'politeia-reading' );
$book_authors = isset( $book->authors ) ? trim( (string) $book->authors ) : '';
if ( '' === $book_authors ) {
	$book_authors = __( 'Unknown Author', 'politeia-reading' );
}

wp_enqueue_style( 'politeia-reading' );
wp_enqueue_style(
	'politeia-reading-layout',
	POLITEIA_READING_URL . 'assets/css/politeia-reading.css',
	array( 'politeia-reading' ),
	POLITEIA_READING_VERSION
);
?>
<div class="wrap">
	<div class="prs-single-ver-2">
		<figure class="prs-book-cover">
			<?php
			$cover_img_url = '';
			if ( $final_cover_id ) {
				$cover_img_src = wp_get_attachment_image_src( $final_cover_id, 'large' );
				$cover_img_url = $cover_img_src ? set_url_scheme( $cover_img_src[0], $cover_scheme ) : '';
				if ( ! $cover_img_url ) {
					$fallback_src  = wp_get_attachment_url( $final_cover_id );
					$cover_img_url = $fallback_src ? set_url_scheme( $fallback_src, $cover_scheme ) : '';
				}
			} elseif ( $cover_url ) {
				$cover_img_url = set_url_scheme( $cover_url, $cover_scheme );
			}

			if ( $force_http_covers && $cover_img_url ) {
				$cover_img_url = preg_replace( '#^https:#', 'http:', $cover_img_url );
			}

			if ( $cover_img_url ) :
				?>
				<img src="<?php echo esc_url( $cover_img_url ); ?>" alt="<?php echo esc_attr( $book_title ); ?>" class="prs-cover-img" />
			<?php else : ?>
				<div class="prs-cover-placeholder">
					<span><?php esc_html_e( 'No cover available', 'politeia-reading' ); ?></span>
				</div>
			<?php endif; ?>
		</figure>
		<h1 class="prs-book-title"><?php echo esc_html( $book_title ); ?></h1>
		<p class="prs-book-author"><?php echo esc_html( $book_authors ); ?></p>
	</div>
</div>
<?php
get_footer();
