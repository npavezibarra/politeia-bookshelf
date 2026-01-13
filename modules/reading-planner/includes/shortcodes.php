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
			'autocomplete' => array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'prs_canonical_title_search' ),
			),
		)
	);

	return '
		<button type="button" id="politeia-open-reading-plan">Start Reading Plan</button>
		<div id="politeia-reading-plan-overlay" hidden>
			<div class="politeia-reading-plan-shell" role="dialog" aria-modal="true" aria-labelledby="politeia-reading-plan-title">
				<button type="button" class="politeia-modal-close" aria-label="Close">×</button>
				<div id="form-container" class="bg-[#FEFEFF] w-full max-w-xl rounded-custom shadow-2xl overflow-hidden border border-[#A8A8A8] flex flex-col hidden">
					<div class="bg-[#F5F5F5] h-1.5 flex" id="progress-bar">
						<div class="flex-1 transition-all duration-700"></div>
						<div class="flex-1 transition-all duration-700"></div>
						<div class="flex-1 transition-all duration-700"></div>
					</div>
					<div class="p-10 flex-1 flex flex-col">
						<div id="step-content" class="flex-1 min-h-[400px]"></div>
					</div>
				</div>
				<div id="summary-container" class="calendar-card rounded-custom shadow-2xl p-6 max-w-xl w-full hidden">
					<div class="mb-6 text-center">
						<h2 id="propuesta-tipo-label" class="text-[10px] font-medium text-black tracking-[0.25em] uppercase mb-1 opacity-80">PROPUESTA DE LECTURA REALISTA</h2>
						<h3 id="propuesta-plan-titulo" class="text-sm font-medium text-black uppercase mb-3 tracking-wide">Título del Plan</h3>
						<div class="w-full h-[1px] bg-[#A8A8A8] opacity-30"></div>
					</div>
					<header class="flex justify-between items-start mb-4">
						<div>
							<div class="flex items-center space-x-3">
								<h1 id="propuesta-mes-label" class="text-2xl font-medium text-black tracking-tight uppercase leading-tight">---</h1>
								<div id="calendar-nav-controls" class="flex space-x-1">
									<button id="calendar-prev-month" class="nav-btn-circ" title="Mes Anterior">
										<i data-lucide="chevron-left" class="w-4 h-4"></i>
									</button>
									<button id="calendar-next-month" class="nav-btn-circ" title="Mes Siguiente">
										<i data-lucide="chevron-right" class="w-4 h-4"></i>
									</button>
								</div>
							</div>
							<p id="propuesta-sub-label" class="text-[10px] text-deep-gray font-medium uppercase tracking-widest mt-0.5">Planificación Mensual</p>
							<div id="propuesta-meta-info" class="mt-4 space-y-1">
								<div id="propuesta-carga" class="text-[11px] font-medium text-[#C79F32] uppercase tracking-wide">Carga: -- PÁGINAS / SESIÓN</div>
								<div id="propuesta-duracion" class="text-[10px] font-medium text-black/60 uppercase tracking-wider">Estimado: -- SEMANAS</div>
							</div>
						</div>
						<div class="flex flex-col items-end">
							<div class="flex flex-col items-end">
								<div class="flex items-center space-x-2">
									<span class="w-2.5 h-2.5 rounded-full bg-[#C79F32]"></span>
									<span class="text-[10px] font-medium text-black uppercase tracking-wider">SESIONES</span>
								</div>
								<p class="text-[8px] text-deep-gray font-medium opacity-60 mt-0.5 tracking-tight">arrastra a otra fecha para ajustar</p>
							</div>
							<div class="toggle-container mt-3">
								<div id="toggle-calendar" class="toggle-btn active"><i data-lucide="calendar" class="w-3.5 h-3.5"></i></div>
								<div id="toggle-list" class="toggle-btn"><i data-lucide="list" class="w-3.5 h-3.5"></i></div>
							</div>
							<div id="list-pagination" class="mt-3 flex items-center space-x-2 hidden">
								<button id="list-prev-page" class="pagination-btn"><i data-lucide="chevron-left" class="w-3 h-3"></i></button>
								<span id="list-page-info" class="text-[9px] font-black tracking-tighter uppercase opacity-60">1 / 1</span>
								<button id="list-next-page" class="pagination-btn"><i data-lucide="chevron-right" class="w-3 h-3"></i></button>
							</div>
						</div>
					</header>
					<div id="main-view-container" class="mt-4 relative overflow-hidden transition-[height] duration-500 ease-in-out">
						<div id="calendar-view-wrapper" class="view-transition view-visible">
						<div class="grid grid-cols-7 mb-2 border-b border-black/5">
							<div class="text-center text-[10px] font-medium text-black py-2">LUN</div>
							<div class="text-center text-[10px] font-medium text-black py-2">MAR</div>
							<div class="text-center text-[10px] font-medium text-black py-2">MIÉ</div>
							<div class="text-center text-[10px] font-medium text-black py-2">JUE</div>
							<div class="text-center text-[10px] font-medium text-black py-2">VIE</div>
							<div class="text-center text-[10px] font-medium text-black py-2">SÁB</div>
							<div class="text-center text-[10px] font-medium text-black py-2">DOM</div>
						</div>
							<div id="calendar-grid" class="grid grid-cols-7 gap-1.5 pt-2"></div>
						</div>
						<div id="list-view-wrapper" class="view-transition view-hidden">
							<div id="list-view" class="space-y-2 py-2"></div>
						</div>
					</div>
					<div class="mt-8 flex flex-col items-center space-y-4">
						<button id="accept-button" class="btn-primary w-full py-4 rounded-custom font-medium uppercase tracking-widest text-sm shadow-lg">
							Aceptar Propuesta
						</button>
						<button id="adjust-btn" class="text-[10px] font-medium uppercase text-[#A8A8A8] hover:text-black transition-colors tracking-widest">
							Ajustar Datos del Plan
						</button>
					</div>
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
