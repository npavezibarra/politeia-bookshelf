<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();

echo '<div class="wrap">';

if ( is_user_logged_in() ) {
	// Reutiliza exactamente lo que ves en el shortcode [politeia_my_books]
	echo do_shortcode( '[politeia_my_books]' );
} else {
	echo '<p>' . esc_html__( 'You must be logged in to view your library.', 'politeia-reading' ) . '</p>';
}
echo '</div>';

get_footer();
