<?php
namespace Politeia\ReadingPlanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	$js_url        = POLITEIA_READING_PLAN_URL . 'assets/js/reading-plan.js';
	$css_url       = POLITEIA_READING_PLAN_URL . 'assets/css/reading-plan.css';
	$js_version    = file_exists( POLITEIA_READING_PLAN_PATH . 'assets/js/reading-plan.js' ) ? filemtime( POLITEIA_READING_PLAN_PATH . 'assets/js/reading-plan.js' ) : null;
	$css_version   = file_exists( POLITEIA_READING_PLAN_PATH . 'assets/css/reading-plan.css' ) ? filemtime( POLITEIA_READING_PLAN_PATH . 'assets/css/reading-plan.css' ) : null;

	wp_register_script( $script_handle, $js_url, array(), $js_version, true );
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

	return '
		<button type="button" id="politeia-open-reading-plan">Start Reading Plan</button>
		<div id="politeia-reading-plan-overlay" hidden>
			<div class="politeia-modal" role="dialog" aria-modal="true" aria-labelledby="politeia-reading-plan-title">
				<button type="button" class="politeia-modal-close" aria-label="Close">×</button>
				<div class="politeia-steps">
					<section data-step="1">
						<h2 id="politeia-reading-plan-title">Paso 1: Metas</h2>
						<div class="politeia-step-body" data-step-body="1"></div>
						<div class="politeia-nav">
							<button type="button" class="politeia-next">Siguiente</button>
						</div>
					</section>
					<section data-step="2" hidden>
						<h2>Paso 2: Punto de partida</h2>
						<div class="politeia-step-body" data-step-body="2"></div>
						<div class="politeia-nav">
							<button type="button" class="politeia-prev">Atrás</button>
							<button type="button" class="politeia-next">Siguiente</button>
						</div>
					</section>
					<section data-step="3" hidden>
						<h2>Paso 3: Biblioteca</h2>
						<div class="politeia-step-body" data-step-body="3"></div>
						<div class="politeia-nav">
							<button type="button" class="politeia-prev">Atrás</button>
							<button type="button" class="politeia-next">Siguiente</button>
						</div>
					</section>
					<section data-step="4" hidden>
						<h2>Paso 4: Disponibilidad</h2>
						<div class="politeia-step-body" data-step-body="4"></div>
						<div class="politeia-nav">
							<button type="button" class="politeia-prev">Atrás</button>
							<button type="button" class="politeia-next">Siguiente</button>
						</div>
					</section>
					<section data-step="5" hidden>
						<h2>Paso 5: Horizonte</h2>
						<div class="politeia-step-body" data-step-body="5"></div>
						<div class="politeia-nav">
							<button type="button" class="politeia-prev">Atrás</button>
							<button type="button" class="politeia-submit">Iniciar Plan</button>
						</div>
					</section>
				</div>
			</div>
		</div>
	';
}

/**
 * Register reading plan launch shortcode.
 */
function register_shortcodes(): void {
	add_shortcode( 'politeia_reading_plan', __NAMESPACE__ . '\\render_reading_plan_shortcode' );
}
add_action( 'init', __NAMESPACE__ . '\\register_shortcodes' );
