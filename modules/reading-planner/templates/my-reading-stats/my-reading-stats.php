<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$requested_user = (string) get_query_var( 'prs_my_reading_stats_user' );
$current_user   = wp_get_current_user();
$is_owner       = $requested_user
	&& $current_user
	&& $current_user->exists()
	&& $current_user->user_login === $requested_user;
?>

<div class="wrap">
	<?php if ( $is_owner ) : ?>
		<h1><?php esc_html_e( 'My Reading Stats', 'politeia-reading' ); ?></h1>
	<?php else : ?>
		<p><?php esc_html_e( 'Access denied.', 'politeia-reading' ); ?></p>
	<?php endif; ?>
</div>

<?php
get_footer();
