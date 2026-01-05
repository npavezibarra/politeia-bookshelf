<?php
namespace Politeia\ReadingPlanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register reading plan launch shortcode.
 */
function register_shortcodes(): void {
	add_shortcode( 'politeia_reading_plan', __NAMESPACE__ . '\\render_reading_plan_shortcode' );
}
add_action( 'init', __NAMESPACE__ . '\\register_shortcodes' );

/**
 * Render the reading plan launch button and modal root.
 *
 * @return string
 */
function render_reading_plan_shortcode(): string {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	$script_handle = 'politeia-reading-plan-app';
	$style_handle  = 'politeia-reading-plan-app';
	$js_path       = POLITEIA_READING_PLAN_PATH . 'assets/js/reading-plan-entry.js';
	$css_path      = POLITEIA_READING_PLAN_PATH . 'assets/css/reading-plan-app.css';
	$js_url        = POLITEIA_READING_PLAN_URL . 'assets/js/reading-plan-entry.js';
	$css_url       = POLITEIA_READING_PLAN_URL . 'assets/css/reading-plan-app.css';
	$js_version    = file_exists( $js_path ) ? filemtime( $js_path ) : null;
	$css_version   = file_exists( $css_path ) ? filemtime( $css_path ) : null;

	wp_enqueue_script( 'underscore' );

	wp_register_script( $script_handle, $js_url, array( 'wp-element' ), $js_version, true );
	wp_register_style( $style_handle, $css_url, array(), $css_version );

	wp_enqueue_script( $script_handle );
	wp_enqueue_style( $style_handle );

	wp_localize_script(
		$script_handle,
		'PoliteiaReadingPlan',
		array(
			'restUrl' => rest_url( 'politeia/v1/reading-plan' ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'userId'  => get_current_user_id(),
		)
	);

	return '<button type="button" id="politeia-open-reading-plan">Start Reading Plan</button><div id="politeia-reading-plan-root" style="display:none;"></div>';
}
