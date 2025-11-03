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
	echo '<p>You must be logged in to view your library.</p>';
}
echo '</div>';

get_footer();
