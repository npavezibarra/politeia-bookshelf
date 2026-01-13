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
			'strings' => array(
				'month_names'        => array(
					__( 'January', 'politeia-reading' ),
					__( 'February', 'politeia-reading' ),
					__( 'March', 'politeia-reading' ),
					__( 'April', 'politeia-reading' ),
					__( 'May', 'politeia-reading' ),
					__( 'June', 'politeia-reading' ),
					__( 'July', 'politeia-reading' ),
					__( 'August', 'politeia-reading' ),
					__( 'September', 'politeia-reading' ),
					__( 'October', 'politeia-reading' ),
					__( 'November', 'politeia-reading' ),
					__( 'December', 'politeia-reading' ),
				),
				'open_button'        => __( 'Start Reading Plan', 'politeia-reading' ),
				'close'              => __( 'Close', 'politeia-reading' ),
				'plan_type_realistic' => __( 'Realistic Reading Plan', 'politeia-reading' ),
				'plan_title_default' => __( 'Plan Title', 'politeia-reading' ),
				'monthly_plan'       => __( 'Monthly Plan', 'politeia-reading' ),
				'load_label'         => __( 'Load: %s PAGES / SESSION', 'politeia-reading' ),
				'estimated_label'    => __( 'Estimated: %s WEEKS', 'politeia-reading' ),
				'sessions_label'     => __( 'Sessions', 'politeia-reading' ),
				'drag_to_adjust'     => __( 'drag to another date to adjust', 'politeia-reading' ),
				'accept_plan'        => __( 'Accept Plan', 'politeia-reading' ),
				'adjust_plan'        => __( 'Adjust Plan Details', 'politeia-reading' ),
				'previous_month'     => __( 'Previous Month', 'politeia-reading' ),
				'next_month'         => __( 'Next Month', 'politeia-reading' ),
				'day_mon'            => __( 'Mon', 'politeia-reading' ),
				'day_tue'            => __( 'Tue', 'politeia-reading' ),
				'day_wed'            => __( 'Wed', 'politeia-reading' ),
				'day_thu'            => __( 'Thu', 'politeia-reading' ),
				'day_fri'            => __( 'Fri', 'politeia-reading' ),
				'day_sat'            => __( 'Sat', 'politeia-reading' ),
				'day_sun'            => __( 'Sun', 'politeia-reading' ),
				'list_page_label'    => __( '%1$s / %2$s', 'politeia-reading' ),
				'goal_prompt'        => __( 'What goal do you want to achieve?', 'politeia-reading' ),
				'goal_subtitle'      => __( 'Select your primary goal', 'politeia-reading' ),
				'goal_complete_title' => __( 'Finish a book', 'politeia-reading' ),
				'goal_complete_desc' => __( 'Finish specific books within a set time frame.', 'politeia-reading' ),
				'goal_habit_title'   => __( 'Build a habit', 'politeia-reading' ),
				'goal_habit_desc'    => __( 'Increase the frequency and consistency of your reading.', 'politeia-reading' ),
				'baseline_label'     => __( 'Baseline', 'politeia-reading' ),
				'baseline_books_year' => __( 'How many books did you finish in the last year?', 'politeia-reading' ),
				'baseline_book_pages' => __( 'How many pages did the book have?', 'politeia-reading' ),
				'book_number'        => __( 'Book #%d', 'politeia-reading' ),
				'baseline_frequency' => __( 'Baseline Frequency', 'politeia-reading' ),
				'baseline_sessions_month' => __( 'How many reading sessions did you have in the last month?', 'politeia-reading' ),
				'example_sessions'   => __( 'e.g. 8', 'politeia-reading' ),
				'confirm_continue'   => __( 'Confirm and Continue', 'politeia-reading' ),
				'minimum_session'    => __( 'Minimum Session', 'politeia-reading' ),
				'average_session_time' => __( 'On average, how much time did you spend per session?', 'politeia-reading' ),
				'minutes_label'      => __( 'minutes', 'politeia-reading' ),
				'or_other'           => __( 'or other:', 'politeia-reading' ),
				'minutes_placeholder' => __( '20', 'politeia-reading' ),
				'minutes_short'      => __( 'min', 'politeia-reading' ),
				'confirm'            => __( 'Confirm', 'politeia-reading' ),
				'daily_ambition'     => __( 'Daily Ambition', 'politeia-reading' ),
				'habit_intensity_prompt' => __( 'How intense do you want this habit to be?', 'politeia-reading' ),
				'intensity_light'    => __( 'LIGHT', 'politeia-reading' ),
				'intensity_light_reason' => __( 'It\'s the "magic number" for making progress without it feeling like a burden.', 'politeia-reading' ),
				'intensity_balanced' => __( 'BALANCED', 'politeia-reading' ),
				'intensity_balanced_reason' => __( 'It lets you finish a full chapter, creating a real sense of achievement.', 'politeia-reading' ),
				'intensity_intense'  => __( 'INTENSE', 'politeia-reading' ),
				'intensity_intense_reason' => __( 'Ideal for those who want reading to be a central part of their identity.', 'politeia-reading' ),
				'minutes_per_day'    => __( '%s MIN / DAY', 'politeia-reading' ),
				'book_prompt'        => __( 'Which book do you want to read now?', 'politeia-reading' ),
				'your_book'          => __( 'Your Book', 'politeia-reading' ),
				'remove_book'        => __( 'Remove book', 'politeia-reading' ),
				'next'               => __( 'Next', 'politeia-reading' ),
				'book_title'         => __( 'Book title', 'politeia-reading' ),
				'author'             => __( 'Author', 'politeia-reading' ),
				'pages'              => __( 'Pages', 'politeia-reading' ),
				'add_book'           => __( 'Add book', 'politeia-reading' ),
				'unknown_author'     => __( 'Unknown author', 'politeia-reading' ),
				'by_label'           => __( 'by', 'politeia-reading' ),
				'pages_label'        => __( 'pages', 'politeia-reading' ),
				'intensity_balanced_label' => __( 'Balanced', 'politeia-reading' ),
				'intensity_challenging_label' => __( 'Challenging', 'politeia-reading' ),
				'intensity_intense_label' => __( 'Intense', 'politeia-reading' ),
				'intensity_prompt'   => __( 'What intensity level do you want?', 'politeia-reading' ),
				'sessions_per_week'  => __( '%d sessions<br>per week', 'politeia-reading' ),
				'habit_plan_title'   => __( 'HABIT FORMATION PROPOSAL', 'politeia-reading' ),
				'habit_plan_of'      => __( 'HABIT OF %s MIN / DAY', 'politeia-reading' ),
				'habit_cycle_label'  => __( 'CONSOLIDATION CYCLE (42 DAYS)', 'politeia-reading' ),
				'estimated_load'     => __( 'Estimated Load: %s PAGES / SESSION', 'politeia-reading' ),
				'cycle_duration_weeks' => __( 'Cycle duration: %s WEEKS', 'politeia-reading' ),
				'realistic_plan'     => __( 'REALISTIC READING PLAN', 'politeia-reading' ),
				'suggested_load'     => __( 'Suggested Load: %s PAGES / SESSION', 'politeia-reading' ),
				'estimated_duration' => __( 'Estimated duration: %s weeks', 'politeia-reading' ),
				'remove_session'     => __( 'Remove session', 'politeia-reading' ),
				'add_session'        => __( 'Add session', 'politeia-reading' ),
				'no_sessions'        => __( 'No sessions', 'politeia-reading' ),
				'reading_session'    => __( 'Reading Session', 'politeia-reading' ),
			),
		)
	);

	ob_start();
	?>
	<button type="button" id="politeia-open-reading-plan"><?php esc_html_e( 'Start Reading Plan', 'politeia-reading' ); ?></button>
	<div id="politeia-reading-plan-overlay" hidden>
		<div class="politeia-reading-plan-shell" role="dialog" aria-modal="true" aria-labelledby="politeia-reading-plan-title">
			<button type="button" class="politeia-modal-close" aria-label="<?php echo esc_attr__( 'Close', 'politeia-reading' ); ?>">×</button>
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
					<h2 id="propuesta-tipo-label" class="text-[10px] font-medium text-black tracking-[0.25em] uppercase mb-1 opacity-80"><?php esc_html_e( 'Realistic Reading Plan', 'politeia-reading' ); ?></h2>
					<h3 id="propuesta-plan-titulo" class="text-sm font-medium text-black uppercase mb-3 tracking-wide"><?php esc_html_e( 'Plan Title', 'politeia-reading' ); ?></h3>
					<div class="w-full h-[1px] bg-[#A8A8A8] opacity-30"></div>
				</div>
				<header class="flex justify-between items-start mb-4">
					<div>
						<div class="flex items-center space-x-3">
							<h1 id="propuesta-mes-label" class="text-2xl font-medium text-black tracking-tight uppercase leading-tight">---</h1>
							<div id="calendar-nav-controls" class="flex space-x-1">
								<button id="calendar-prev-month" class="nav-btn-circ" title="<?php echo esc_attr__( 'Previous Month', 'politeia-reading' ); ?>">
									<i data-lucide="chevron-left" class="w-4 h-4"></i>
								</button>
								<button id="calendar-next-month" class="nav-btn-circ" title="<?php echo esc_attr__( 'Next Month', 'politeia-reading' ); ?>">
									<i data-lucide="chevron-right" class="w-4 h-4"></i>
								</button>
							</div>
						</div>
						<p id="propuesta-sub-label" class="text-[10px] text-deep-gray font-medium uppercase tracking-widest mt-0.5"><?php esc_html_e( 'Monthly Plan', 'politeia-reading' ); ?></p>
						<div id="propuesta-meta-info" class="mt-4 space-y-1">
							<div id="propuesta-carga" class="text-[11px] font-medium text-[#C79F32] uppercase tracking-wide"><?php esc_html_e( 'Load: -- PAGES / SESSION', 'politeia-reading' ); ?></div>
							<div id="propuesta-duracion" class="text-[10px] font-medium text-black/60 uppercase tracking-wider"><?php esc_html_e( 'Estimated: -- WEEKS', 'politeia-reading' ); ?></div>
						</div>
					</div>
					<div class="flex flex-col items-end">
						<div class="flex flex-col items-end">
							<div class="flex items-center space-x-2">
								<span class="w-2.5 h-2.5 rounded-full bg-[#C79F32]"></span>
								<span class="text-[10px] font-medium text-black uppercase tracking-wider"><?php esc_html_e( 'Sessions', 'politeia-reading' ); ?></span>
							</div>
							<p class="text-[8px] text-deep-gray font-medium opacity-60 mt-0.5 tracking-tight"><?php esc_html_e( 'drag to another date to adjust', 'politeia-reading' ); ?></p>
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
						<div class="text-center text-[10px] font-medium text-black py-2"><?php esc_html_e( 'Mon', 'politeia-reading' ); ?></div>
						<div class="text-center text-[10px] font-medium text-black py-2"><?php esc_html_e( 'Tue', 'politeia-reading' ); ?></div>
						<div class="text-center text-[10px] font-medium text-black py-2"><?php esc_html_e( 'Wed', 'politeia-reading' ); ?></div>
						<div class="text-center text-[10px] font-medium text-black py-2"><?php esc_html_e( 'Thu', 'politeia-reading' ); ?></div>
						<div class="text-center text-[10px] font-medium text-black py-2"><?php esc_html_e( 'Fri', 'politeia-reading' ); ?></div>
						<div class="text-center text-[10px] font-medium text-black py-2"><?php esc_html_e( 'Sat', 'politeia-reading' ); ?></div>
						<div class="text-center text-[10px] font-medium text-black py-2"><?php esc_html_e( 'Sun', 'politeia-reading' ); ?></div>
					</div>
						<div id="calendar-grid" class="grid grid-cols-7 gap-1.5 pt-2"></div>
					</div>
					<div id="list-view-wrapper" class="view-transition view-hidden">
						<div id="list-view" class="space-y-2 py-2"></div>
					</div>
				</div>
				<div class="mt-8 flex flex-col items-center space-y-4">
					<button id="accept-button" class="btn-primary w-full py-4 rounded-custom font-medium uppercase tracking-widest text-sm shadow-lg">
						<?php esc_html_e( 'Accept Plan', 'politeia-reading' ); ?>
					</button>
					<button id="adjust-btn" class="text-[10px] font-medium uppercase text-[#A8A8A8] hover:text-black transition-colors tracking-widest">
						<?php esc_html_e( 'Adjust Plan Details', 'politeia-reading' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Register reading plan launch shortcode.
 */
function register_shortcodes(): void {
	add_shortcode( 'politeia_reading_plan', __NAMESPACE__ . '\\render_reading_plan_shortcode' );
}
add_action( 'init', __NAMESPACE__ . '\\register_shortcodes' );
