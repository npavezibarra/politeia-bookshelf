<?php
namespace Politeia\ReadingPlanner;
use Politeia\ReadingPlanner\Config;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Render the reading plan launch button and modal root.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function render_reading_plan_shortcode($atts = array()): string
{
	if (!is_user_logged_in()) {
		return '';
	}

	$atts = shortcode_atts(
		array(
			'user_book_id' => 0,
			'book_id' => 0,
			'book_title' => '',
			'book_author' => '',
			'book_pages' => 0,
			'book_cover' => '',
		),
		$atts,
		'politeia_reading_plan'
	);

	$user_book_id = absint($atts['user_book_id']);
	$book_id = absint($atts['book_id']);
	$book_title = sanitize_text_field((string) $atts['book_title']);
	$book_author = sanitize_text_field((string) $atts['book_author']);
	$book_pages = (int) $atts['book_pages'];
	$book_cover = esc_url_raw((string) $atts['book_cover']);
	$prefill_book = null;

	if ($user_book_id || $book_id || $book_title || $book_author || $book_pages || $book_cover) {
		$prefill_book = array(
			'userBookId' => $user_book_id,
			'bookId' => $book_id,
			'title' => $book_title,
			'author' => $book_author,
			'pages' => $book_pages,
			'cover' => $book_cover,
		);
	}

	$script_handle = 'politeia-reading-plan-app';
	$style_handle = 'politeia-reading-plan-app';
	$js_url = POLITEIA_READING_PLAN_URL . 'assets/js/reading-plan.js';
	$css_url = POLITEIA_READING_PLAN_URL . 'assets/css/reading-plan.css';
	$js_version = file_exists(POLITEIA_READING_PLAN_PATH . 'assets/js/reading-plan.js') ? filemtime(POLITEIA_READING_PLAN_PATH . 'assets/js/reading-plan.js') : null;
	$css_version = file_exists(POLITEIA_READING_PLAN_PATH . 'assets/css/reading-plan.css') ? filemtime(POLITEIA_READING_PLAN_PATH . 'assets/css/reading-plan.css') : null;

	wp_register_script($script_handle, $js_url, array(), $js_version, true);
	wp_register_style($style_handle, $css_url, array(), $css_version);

	wp_enqueue_script($script_handle);
	wp_enqueue_style($style_handle);
	wp_enqueue_script('politeia-start-reading');
	wp_enqueue_style('politeia-reading');

	$user = wp_get_current_user();
	$user_login = $user && isset($user->user_login) ? (string) $user->user_login : '';
	$my_plans_url = $user_login ? home_url('/members/' . rawurlencode($user_login) . '/my-plans/') : home_url('/my-plans/');
	$active_plans = array();

	if ($user_login) {
		global $wpdb;
		$plans_table = $wpdb->prefix . 'politeia_plans';
		$goals_table = $wpdb->prefix . 'politeia_plan_goals';
		$books_table = $wpdb->prefix . 'politeia_books';
		$authors_table = $wpdb->prefix . 'politeia_authors';
		$pivot_table = $wpdb->prefix . 'politeia_book_authors';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.id AS plan_id,
				        p.name AS plan_name,
				        b.title AS book_title,
				        GROUP_CONCAT(a.display_name ORDER BY ba.sort_order ASC SEPARATOR ', ') AS authors
				 FROM {$plans_table} p
				 LEFT JOIN {$goals_table} g ON g.plan_id = p.id
				 LEFT JOIN {$books_table} b ON b.id = g.book_id
				 LEFT JOIN {$pivot_table} ba ON ba.book_id = b.id
				 LEFT JOIN {$authors_table} a ON a.id = ba.author_id
				 WHERE p.user_id = %d
				   AND p.status = %s
				 GROUP BY p.id, b.id",
				get_current_user_id(),
				'accepted'
			),
			ARRAY_A
		);

		foreach ((array) $rows as $row) {
			$plan_id = isset($row['plan_id']) ? (int) $row['plan_id'] : 0;
			$title = isset($row['book_title']) && '' !== $row['book_title'] ? (string) $row['book_title'] : '';
			if ('' === $title && isset($row['plan_name'])) {
				$title = (string) $row['plan_name'];
			}
			$authors = isset($row['authors']) ? (string) $row['authors'] : '';
			if (!$plan_id || '' === $title) {
				continue;
			}
			$normalized_title = function_exists('prs_normalize_title') ? prs_normalize_title($title) : $title;
			$active_plans[] = array(
				'plan_id' => $plan_id,
				'title' => $title,
				'author' => $authors,
				'normalized_title' => $normalized_title,
			);
		}
	}

	wp_localize_script(
		$script_handle,
		'PoliteiaReadingPlan',
		array(
			'restUrl' => rest_url('politeia/v1/reading-plan'),
			'bookCreateUrl' => rest_url('politeia/v1/reading-plan/book'),
			'myPlansUrl' => $my_plans_url,
			'activePlans' => $active_plans,
			'bookCreateAjax' => array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('prs_reading_plan_add_book'),
			),
			'bookCheckAjax' => array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('prs_reading_plan_check_active'),
			),
			'sessionRecorderUrl' => rest_url('politeia/v1/reading-plan/session-recorder'),
			'nonce' => wp_create_nonce('wp_rest'),
			'userId' => get_current_user_id(),
			'coverUpload' => array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('prs_cover_nonce'),
			),
			'autocomplete' => array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('prs_canonical_title_search'),
			),
			'prefillBook' => $prefill_book,
			'pagesPerSessionOptions' => Config::get_pages_per_session_options(),
			'sessionsPerWeekOptions' => Config::get_sessions_per_week_options(),
			'defaultPagesPerSession' => Config::get_default_pages_per_session(),
			'defaultSessionsPerWeek' => Config::get_default_sessions_per_week(),
			'habitDaysDuration' => Config::get_habit_config('habit_days_duration', 48),
			'intensityConfig' => array(
				'light' => array(
					'start_pages' => Config::get_habit_config('habit_light_start_pages', 3),
					'end_pages' => Config::get_habit_config('habit_light_end_pages', 10),
				),
				'intense' => array(
					'start_pages' => Config::get_habit_config('habit_intense_start_pages', 15),
					'end_pages' => Config::get_habit_config('habit_intense_end_pages', 30),
				),
			),
			'strings' => array(
				'month_names' => array(
					__('Enero', 'politeia-reading'),
					__('Febrero', 'politeia-reading'),
					__('Marzo', 'politeia-reading'),
					__('Abril', 'politeia-reading'),
					__('Mayo', 'politeia-reading'),
					__('Junio', 'politeia-reading'),
					__('Julio', 'politeia-reading'),
					__('Agosto', 'politeia-reading'),
					__('Septiembre', 'politeia-reading'),
					__('Octubre', 'politeia-reading'),
					__('Noviembre', 'politeia-reading'),
					__('Diciembre', 'politeia-reading'),
				),
				'open_button' => __('Comenzar Plan de Lectura', 'politeia-reading'),
				'close' => __('Cerrar', 'politeia-reading'),
				'plan_type_realistic' => __('Plan de Lectura Realista', 'politeia-reading'),
				'plan_title_default' => __('Título del Plan', 'politeia-reading'),
				'monthly_plan' => __('Plan Mensual', 'politeia-reading'),
				'load_label' => __('Carga: %s PÁGINAS / SESIÓN', 'politeia-reading'),
				'estimated_label' => __('Estimado: %s SEMANAS', 'politeia-reading'),
				'sessions_label' => __('Sesiones', 'politeia-reading'),
				'drag_to_adjust' => __('arrastra para ajustar fecha', 'politeia-reading'),
				'accept_plan' => __('Aceptar Plan', 'politeia-reading'),
				'plan_created' => __('Plan creado exitosamente.', 'politeia-reading'),
				'plan_create_failed' => __('No se pudo crear el plan. Intenta de nuevo.', 'politeia-reading'),
				'adjust_plan' => __('Ajustar Detalles', 'politeia-reading'),
				'previous_month' => __('Mes Anterior', 'politeia-reading'),
				'next_month' => __('Siguiente Mes', 'politeia-reading'),
				'day_mon' => __('Lun', 'politeia-reading'),
				'day_tue' => __('Mar', 'politeia-reading'),
				'day_wed' => __('Mié', 'politeia-reading'),
				'day_thu' => __('Jue', 'politeia-reading'),
				'day_fri' => __('Vie', 'politeia-reading'),
				'day_sat' => __('Sáb', 'politeia-reading'),
				'day_sun' => __('Dom', 'politeia-reading'),
				'list_page_label' => __('%1$s / %2$s', 'politeia-reading'),
				'goal_prompt' => __('¿Qué objetivo quieres lograr?', 'politeia-reading'),
				'goal_subtitle' => __('Selecciona tu objetivo principal', 'politeia-reading'),
				'goal_complete_title' => __('Terminar un libro', 'politeia-reading'),
				'goal_complete_desc' => __('Terminar un libro específico en un tiempo definido.', 'politeia-reading'),
				'goal_habit_title' => __('Crear un hábito', 'politeia-reading'),
				'goal_habit_desc' => __('Aumentar la frecuencia y consistencia de tu lectura.', 'politeia-reading'),
				'baseline_label' => __('Línea Base', 'politeia-reading'),
				'baseline_books_year' => __('¿Cuántos libros terminaste el año pasado?', 'politeia-reading'),
				'baseline_book_pages' => __('¿Cuántas páginas tenía el libro?', 'politeia-reading'),
				'book_number' => __('Libro #%d', 'politeia-reading'),
				'baseline_frequency' => __('Frecuencia Base', 'politeia-reading'),
				'baseline_sessions_month' => __('¿Cuántas sesiones de lectura tuviste el mes pasado?', 'politeia-reading'),
				'example_sessions' => __('ej. 8', 'politeia-reading'),
				'confirm_continue' => __('Confirmar y Continuar', 'politeia-reading'),
				'minimum_session' => __('Sesión Mínima', 'politeia-reading'),
				'average_session_time' => __('En promedio, ¿cuánto tiempo dedicaste por sesión?', 'politeia-reading'),
				'minutes_label' => __('minutos', 'politeia-reading'),
				'or_other' => __('u otro:', 'politeia-reading'),
				'minutes_placeholder' => __('20', 'politeia-reading'),
				'minutes_short' => __('min', 'politeia-reading'),
				'confirm' => __('Confirmar', 'politeia-reading'),
				'daily_ambition' => __('Ambición Diaria', 'politeia-reading'),
				'habit_intensity_prompt' => __('¿Qué tan intenso quieres que sea el hábito?', 'politeia-reading'),
				'intensity_light' => __('LIGERO', 'politeia-reading'),
				'intensity_light_reason' => __('Es el "número mágico" para progresar sin sentir carga.', 'politeia-reading'),
				'intensity_balanced' => __('EQUILIBRADO', 'politeia-reading'),
				'intensity_balanced_reason' => __('Te permite terminar un capítulo completo, creando sentido de logro.', 'politeia-reading'),
				'intensity_intense' => __('INTENSO', 'politeia-reading'),
				'intensity_intense_reason' => __('Ideal para quienes quieren que la lectura sea central en su identidad.', 'politeia-reading'),
				'minutes_per_day' => __('%s MIN / DÍA', 'politeia-reading'),
				'book_prompt' => __('¿Qué libro quieres leer ahora?', 'politeia-reading'),
				'book_active_plan_notice' => __('Ya tienes un plan activo para este libro.', 'politeia-reading'),
				'book_active_plan_link' => __('Ir a mis planes', 'politeia-reading'),
				'your_book' => __('Tu Libro', 'politeia-reading'),
				'remove_book' => __('Quitar libro', 'politeia-reading'),
				'next' => __('Siguiente', 'politeia-reading'),
				'book_title' => __('Título del libro', 'politeia-reading'),
				'author' => __('Autor', 'politeia-reading'),
				'pages' => __('Páginas', 'politeia-reading'),
				'add_book' => __('Añadir libro', 'politeia-reading'),
				'cover_drop_label' => __('arrastra portada aquí', 'politeia-reading'),
				'cover_upload_cta' => __('subir portada', 'politeia-reading'),
				'cover_format_label' => __('JPG o PNG', 'politeia-reading'),
				'cover_change_label' => __('Cambiar Portada', 'politeia-reading'),
				'cover_remove_label' => __('Quitar portada', 'politeia-reading'),
				'cover_preview_alt' => __('Vista previa', 'politeia-reading'),
				'starting_page' => __('Página de Inicio', 'politeia-reading'),
				'start_page_question' => __('¿En qué página comienza el contenido?', 'politeia-reading'),
				'pages_per_session_prompt' => __('¿Cuántas páginas quieres leer por sesión?', 'politeia-reading'),
				'pages_per_session_15_desc' => __('Carga de lectura ligera', 'politeia-reading'),
				'pages_per_session_30_desc' => __('Carga de lectura moderada', 'politeia-reading'),
				'pages_per_session_60_desc' => __('Carga de lectura intensiva', 'politeia-reading'),
				'sessions_per_week_prompt' => __('¿Cuántos días a la semana quieres leer?', 'politeia-reading'),
				'sessions_per_week_3_desc' => __('3 días por semana', 'politeia-reading'),
				'sessions_per_week_5_desc' => __('5 días por semana', 'politeia-reading'),
				'sessions_per_week_7_desc' => __('Lectura diaria', 'politeia-reading'),
				'pages_label_short' => __('páginas', 'politeia-reading'),
				'days_label' => __('días', 'politeia-reading'),
				'derived_intensity_label' => __('Este plan corresponde a un nivel de lectura', 'politeia-reading'),
				'reading_level' => __('', 'politeia-reading'),
				'unknown_author' => __('Autor desconocido', 'politeia-reading'),
				'by_label' => __('por', 'politeia-reading'),
				'pages_label' => __('páginas', 'politeia-reading'),
				'intensity_balanced_label' => __('Intermedio', 'politeia-reading'),
				'intensity_challenging_label' => __('Ligero', 'politeia-reading'),
				'intensity_intense_label' => __('Intenso', 'politeia-reading'),
				'intensity_prompt' => __('Seleccionar intensidad', 'politeia-reading'),
				'sessions_per_week' => __('%d sesiones<br>por semana', 'politeia-reading'),
				'habit_session_meta' => __('%1$s min / %2$s páginas', 'politeia-reading'),
				'habit_plan_title' => __('PROPUESTA FORMACIÓN DE HÁBITO', 'politeia-reading'),
				'habit_plan_of' => __('HÁBITO DE %s MIN / DÍA', 'politeia-reading'),
				'habit_cycle_label' => __('CICLO DE CONSOLIDACIÓN (42 DÍAS)', 'politeia-reading'),
				'habit_step1_title' => __('48 días de lectura', 'politeia-reading'),
				'habit_step1_body' => __('Para construir y consolidar tu hábito de lectura, completarás 48 sesiones diarias.', 'politeia-reading'),
				'habit_step1_cta' => __('¡Entendido!', 'politeia-reading'),
				'habit_step2_title' => __('Crecimiento progresivo', 'politeia-reading'),
				'habit_step2_body' => __('El <span class="habit-highlight">tiempo de sesión</span> y el <span class="habit-highlight">número de páginas</span> aumentarán gradualmente para desafiarte.', 'politeia-reading'),
				'habit_step2_cta' => __('Siguiente', 'politeia-reading'),
				'habit_step3_title' => __('La constancia es todo', 'politeia-reading'),
				'habit_step3_body' => __('Perder una sesión es una advertencia. Perder <span class="habit-highlight">2 sesiones</span> termina el plan.', 'politeia-reading'),
				'habit_step3_cta' => __('¡Entendido!', 'politeia-reading'),
				'habit_step4_title' => __('Tu biblioteca, tus reglas', 'politeia-reading'),
				'habit_step4_body' => __('Cada vez que registres una sesión de un libro añadido a <span class="habit-highlight">Mi Biblioteca</span>, y cumpla los parámetros, la sesión se cuenta automáticamente.', 'politeia-reading'),
				'habit_step4_cta' => __('¡Entendido!', 'politeia-reading'),
				'habit_step5_title' => __('Seleccionar intensidad', 'politeia-reading'),
				'habit_fail_label' => __('Plan Fallido', 'politeia-reading'),
				'habit_intensity_light_desc' => __('Comenzamos en 15m y 3pg. Terminamos en 30m y 10pg mínimo.', 'politeia-reading'),
				'habit_intensity_intense_desc' => __('Comenzamos en 30m y 15pg. Terminamos en 60m y 30pg.', 'politeia-reading'),
				'habit_graph_step1_label' => __('15 min', 'politeia-reading'),
				'habit_graph_step1_sublabel' => __('5 páginas', 'politeia-reading'),
				'habit_graph_step2_label' => __('18 min', 'politeia-reading'),
				'habit_graph_step2_sublabel' => __('6 páginas', 'politeia-reading'),
				'habit_graph_step3_label' => __('25 min', 'politeia-reading'),
				'habit_graph_step3_sublabel' => __('10 páginas', 'politeia-reading'),
				'habit_step6_small' => __('¿Estás listo para dedicar los próximos 48 días a la lectura?', 'politeia-reading'),
				'habit_step6_title' => __('¿Qué día quieres comenzar?', 'politeia-reading'),
				'habit_step6_cta' => __('Continuar', 'politeia-reading'),
				'habit_day_today' => __('Hoy', 'politeia-reading'),
				'habit_day_sun' => __('Dom', 'politeia-reading'),
				'habit_day_mon' => __('Lun', 'politeia-reading'),
				'habit_day_tue' => __('Mar', 'politeia-reading'),
				'habit_day_wed' => __('Mié', 'politeia-reading'),
				'habit_day_thu' => __('Jue', 'politeia-reading'),
				'habit_day_fri' => __('Vie', 'politeia-reading'),
				'habit_day_sat' => __('Sáb', 'politeia-reading'),
				'habit_light_label' => __('Ligero', 'politeia-reading'),
				'habit_intense_label' => __('Intenso', 'politeia-reading'),
				'habit_minutes_range' => __('%1$s–%2$s min', 'politeia-reading'),
				'habit_pages_range' => __('%1$s–%2$s páginas', 'politeia-reading'),
				'habit_intensity_range' => __('%1$s–%2$s min / %3$s–%4$s páginas', 'politeia-reading'),
				'habit_48_title' => __('DESAFÍO DE HÁBITO 48 DÍAS', 'politeia-reading'),
				'habit_intensity_label' => __('Intensidad: %s', 'politeia-reading'),
				'habit_growth_label' => __('Los objetivos diarios crecen linealmente.', 'politeia-reading'),
				'habit_range_label' => __('Objetivos: %1$s–%2$s min / %3$s–%4$s páginas', 'politeia-reading'),
				'habit_duration_days' => __('Duración: %s días', 'politeia-reading'),
				'habit_plan_name' => __('Desafío de Hábito 48 Días', 'politeia-reading'),
				'habit_accepted_message' => __('¡Felicidades! Has comenzado tu desafío de hábito de 48 días.', 'politeia-reading'),
				'estimated_load' => __('Carga Estimada: %s PÁGINAS / SESIÓN', 'politeia-reading'),
				'cycle_duration_weeks' => __('Duración del ciclo: %s SEMANAS', 'politeia-reading'),
				'realistic_plan' => __('PLAN DE LECTURA REALISTA', 'politeia-reading'),
				'suggested_load' => __('Carga Sugerida: %s PÁGINAS / SESIÓN', 'politeia-reading'),
				'estimated_duration' => __('Duración estimada: %s semanas', 'politeia-reading'),
				'remove_session' => __('Quitar sesión', 'politeia-reading'),
				'add_session' => __('Añadir sesión', 'politeia-reading'),
				'no_sessions' => __('Sin sesiones', 'politeia-reading'),
				'reading_session' => __('Sesión de Lectura', 'politeia-reading'),
				'plan_accepted_message' => __('¡Felicidades! Has aceptado tu plan de lectura para "%s".', 'politeia-reading'),
				'next_session_message' => __('Tu próxima sesión de lectura es el %s.', 'politeia-reading'),
				'start_reading' => __('Comenzar Lectura', 'politeia-reading'),
				'session_recorder_unavailable' => __('El registro de sesión no está disponible para este libro.', 'politeia-reading'),
				'next_session_tbd' => __('Por programar', 'politeia-reading'),
				'habit_start_label' => __('INICIO', 'politeia-reading'),
				'habit_end_label' => __('FINAL', 'politeia-reading'),
				'total_pages_label' => __('TOTAL PÁGINAS', 'politeia-reading'),
				'duration_label' => __('DURACIÓN', 'politeia-reading'),
				'daily_page_target' => __('OBJETIVO DIARIO DE PÁGINAS', 'politeia-reading'),
				'days_count_label' => __('%s Días', 'politeia-reading'),
				'start_end_label' => __('INICIO/FINAL', 'politeia-reading'),
			),
		)
	);

	ob_start();
	?>
	<button type="button"
		id="politeia-open-reading-plan"><?php esc_html_e('Comenzar Plan de Lectura', 'politeia-reading'); ?></button>
	<div id="politeia-reading-plan-overlay" hidden>
		<div class="politeia-reading-plan-shell" role="dialog" aria-modal="true"
			aria-labelledby="politeia-reading-plan-title">
			<button type="button" class="politeia-modal-close"
				aria-label="<?php echo esc_attr__('Cerrar', 'politeia-reading'); ?>">×</button>
			<div id="form-container"
				class="bg-[#FEFEFF] w-full max-w-xl rounded-custom shadow-2xl overflow-hidden border border-[#A8A8A8] flex flex-col hidden">
				<div class="p-10 flex-1 flex flex-col">
					<div id="step-content" class="flex-1 min-h-[400px]"></div>
				</div>
				<div id="progress-dots" class="reading-plan-progress-dots" aria-hidden="true">
					<span class="progress-dot"></span>
					<span class="progress-dot"></span>
					<span class="progress-dot"></span>
				</div>
			</div>
			<div id="summary-container" class="calendar-card rounded-custom shadow-2xl p-6 max-w-xl w-full hidden">
				<div class="mb-6 text-center">
					<h2 id="propuesta-tipo-label"
						class="text-[10px] font-medium text-black tracking-[0.25em] uppercase mb-1 opacity-80">
						<?php esc_html_e('Plan de Lectura Realista', 'politeia-reading'); ?>
					</h2>
					<h3 id="propuesta-plan-titulo" class="text-sm font-medium text-black uppercase mb-3 tracking-wide">
						<?php esc_html_e('Título del Plan', 'politeia-reading'); ?>
					</h3>
					<div class="w-full h-[1px] bg-[#A8A8A8] opacity-30"></div>
				</div>
				<header class="flex justify-between items-start mb-4">
					<div>
						<div class="flex items-center space-x-3">
							<h1 id="propuesta-mes-label"
								class="text-2xl font-medium text-black tracking-tight uppercase leading-tight">---</h1>
							<div id="calendar-nav-controls" class="flex space-x-1">
								<button id="calendar-prev-month" class="nav-btn-circ"
									title="<?php echo esc_attr__('Mes Anterior', 'politeia-reading'); ?>">
									<i data-lucide="chevron-left" class="w-4 h-4"></i>
								</button>
								<button id="calendar-next-month" class="nav-btn-circ"
									title="<?php echo esc_attr__('Siguiente Mes', 'politeia-reading'); ?>">
									<i data-lucide="chevron-right" class="w-4 h-4"></i>
								</button>
							</div>
						</div>
						<p id="propuesta-sub-label"
							class="text-[10px] text-deep-gray font-medium uppercase tracking-widest mt-0.5">
							<?php esc_html_e('Plan Mensual', 'politeia-reading'); ?>
						</p>
						<div id="propuesta-meta-info" class="mt-4 space-y-1">
							<div id="propuesta-carga"
								class="text-[11px] font-medium text-[#C79F32] uppercase tracking-wide">
								<?php esc_html_e('Carga: -- PÁGINAS / SESIÓN', 'politeia-reading'); ?>
							</div>
							<div id="propuesta-duracion"
								class="text-[10px] font-medium text-black/60 uppercase tracking-wider">
								<?php esc_html_e('Estimado: -- SEMANAS', 'politeia-reading'); ?>
							</div>
						</div>
					</div>
					<div class="flex flex-col items-end">
						<div class="flex flex-col items-end">
							<div class="flex items-center space-x-2">
								<span class="w-2.5 h-2.5 rounded-full bg-[#C79F32]"></span>
								<span
									class="text-[10px] font-medium text-black uppercase tracking-wider"><?php esc_html_e('Sesiones', 'politeia-reading'); ?></span>
							</div>
							<p class="text-[8px] text-deep-gray font-medium opacity-60 mt-0.5 tracking-tight">
								<?php esc_html_e('arrastra para ajustar', 'politeia-reading'); ?>
							</p>
						</div>
						<div class="toggle-container mt-3">
							<div id="toggle-calendar" class="toggle-btn active"><i data-lucide="calendar"
									class="w-3.5 h-3.5"></i></div>
							<div id="toggle-list" class="toggle-btn"><i data-lucide="list" class="w-3.5 h-3.5"></i></div>
							<div id="toggle-chart" class="toggle-btn hidden"><i data-lucide="trending-up"
									class="w-3.5 h-3.5"></i></div>
						</div>
						<div id="list-pagination" class="mt-3 flex items-center space-x-2 hidden">
							<button id="list-prev-page" class="pagination-btn"><i data-lucide="chevron-left"
									class="w-3 h-3"></i></button>
							<span id="list-page-info" class="text-[9px] font-black tracking-tighter uppercase opacity-60">1
								/ 1</span>
							<button id="list-next-page" class="pagination-btn"><i data-lucide="chevron-right"
									class="w-3 h-3"></i></button>
						</div>
					</div>
				</header>
				<div id="main-view-container"
					class="mt-4 relative overflow-hidden transition-[height] duration-500 ease-in-out">
					<div id="calendar-view-wrapper" class="view-transition view-visible">
						<div class="grid grid-cols-7 mb-2 border-b border-black/5">
							<div class="text-center text-[10px] font-medium text-black py-2">
								<?php esc_html_e('Lun', 'politeia-reading'); ?>
							</div>
							<div class="text-center text-[10px] font-medium text-black py-2">
								<?php esc_html_e('Mar', 'politeia-reading'); ?>
							</div>
							<div class="text-center text-[10px] font-medium text-black py-2">
								<?php esc_html_e('Mié', 'politeia-reading'); ?>
							</div>
							<div class="text-center text-[10px] font-medium text-black py-2">
								<?php esc_html_e('Jue', 'politeia-reading'); ?>
							</div>
							<div class="text-center text-[10px] font-medium text-black py-2">
								<?php esc_html_e('Vie', 'politeia-reading'); ?>
							</div>
							<div class="text-center text-[10px] font-medium text-black py-2">
								<?php esc_html_e('Sáb', 'politeia-reading'); ?>
							</div>
							<div class="text-center text-[10px] font-medium text-black py-2">
								<?php esc_html_e('Dom', 'politeia-reading'); ?>
							</div>
						</div>
						<div id="calendar-grid" class="grid grid-cols-7 gap-1.5 pt-2"></div>
					</div>
					<div id="list-view-wrapper" class="view-transition view-hidden">
						<div id="list-view" class="space-y-2 py-2"></div>
					</div>
					<div id="chart-view-wrapper" class="view-transition view-hidden relative">
						<div id="chart-container" class="bg-[#1a1a1a] rounded-lg p-4 h-[300px] relative"></div>
					</div>
				</div>
				<div class="mt-8 flex flex-col items-center space-y-4">
					<div id="reading-plan-success"
						class="hidden text-[11px] font-medium uppercase tracking-widest text-[#2F7D32]">
						<?php esc_html_e('Plan creado exitosamente.', 'politeia-reading'); ?>
					</div>
					<div id="reading-plan-success-panel" class="reading-plan-success hidden">
						<h3 id="reading-plan-success-title" class="reading-plan-success__title"></h3>
						<p id="reading-plan-success-next" class="reading-plan-success__next"></p>
						<button id="reading-plan-start-session" class="reading-plan-success__btn" type="button">
							<?php esc_html_e('Comenzar Lectura', 'politeia-reading'); ?>
						</button>
						<p id="reading-plan-success-note" class="reading-plan-success__note hidden"></p>
					</div>
					<button id="accept-button"
						class="btn-primary w-full py-4 rounded-custom font-medium uppercase tracking-widest text-sm">
						<?php esc_html_e('Aceptar Plan', 'politeia-reading'); ?>
					</button>
					<div id="reading-plan-error"
						class="hidden text-[11px] font-medium uppercase tracking-widest text-[#B42318]">
						<?php esc_html_e('No se pudo crear el plan. Intenta de nuevo.', 'politeia-reading'); ?>
					</div>
					<button id="adjust-btn"
						class="text-[10px] font-medium uppercase text-[#A8A8A8] hover:text-black transition-colors tracking-widest">
						<?php esc_html_e('Ajustar Detalles', 'politeia-reading'); ?>
					</button>
				</div>
			</div>
			<div id="reading-plan-session-modal" class="reading-plan-session-modal" aria-hidden="true">
				<div class="reading-plan-session-modal__content">
					<button type="button" class="reading-plan-session-modal__close"
						aria-label="<?php echo esc_attr__('Cerrar', 'politeia-reading'); ?>">×</button>
					<div id="reading-plan-session-content"></div>
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
function register_shortcodes(): void
{
	add_shortcode('politeia_reading_plan', __NAMESPACE__ . '\\render_reading_plan_shortcode');
}
add_action('init', __NAMESPACE__ . '\\register_shortcodes');
