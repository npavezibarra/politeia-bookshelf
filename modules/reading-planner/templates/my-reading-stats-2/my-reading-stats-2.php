<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$requested_user = (string) get_query_var( 'prs_my_reading_stats_2_user' );
if ( '' === $requested_user ) {
	$requested_user = (string) get_query_var( 'prs_my_reading_stats_user' );
}
$current_user   = wp_get_current_user();
$is_owner       = $requested_user
	&& $current_user
	&& $current_user->exists()
	&& $current_user->user_login === $requested_user;
$total_user_books = 0;
$reading_status_counts = array(
	'not_started' => 0,
	'started'     => 0,
	'finished'    => 0,
);
if ( $is_owner ) {
	global $wpdb;
	$user_id = (int) $current_user->ID;
	$user_books_table = $wpdb->prefix . 'politeia_user_books';
	$books_table = $wpdb->prefix . 'politeia_books';
	$total_user_books = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*)
			FROM {$user_books_table} ub
			JOIN {$books_table} b ON b.id = ub.book_id
			WHERE ub.user_id = %d
			  AND ub.deleted_at IS NULL
			  AND (ub.owning_status IS NULL OR ub.owning_status != 'deleted')",
			$user_id
		)
	);
	$status_row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT
				SUM(CASE WHEN ub.reading_status = 'not_started' THEN 1 ELSE 0 END) AS not_started,
				SUM(CASE WHEN ub.reading_status = 'started' THEN 1 ELSE 0 END) AS started,
				SUM(CASE WHEN ub.reading_status = 'finished' THEN 1 ELSE 0 END) AS finished
			FROM {$user_books_table} ub
			JOIN {$books_table} b ON b.id = ub.book_id
			WHERE ub.user_id = %d
			  AND ub.deleted_at IS NULL
			  AND (ub.owning_status IS NULL OR ub.owning_status != 'deleted')",
			$user_id
		),
		ARRAY_A
	);
	if ( $status_row ) {
		$reading_status_counts['not_started'] = (int) $status_row['not_started'];
		$reading_status_counts['started'] = (int) $status_row['started'];
		$reading_status_counts['finished'] = (int) $status_row['finished'];
	}
}
?>

<div class="wrap">
	<?php if ( $is_owner ) : ?>
		<style>
			:root {
				--gold: linear-gradient(135deg, #8A6B1E, #C79F32, #E9D18A);
				--silver: linear-gradient(135deg, #949494, #D1D1D1, #F2F2F2);
				--copper: linear-gradient(135deg, #783F27, #B87333, #E5AA70);
				--black: #000000;
				--deep-gray: #333333;
				--light-gray: #A8A8A8;
				--subtle-gray: #f5f5f5;
				--off-white: #FEFEFF;
				--border-radius: 9px;
			}

			* {
				margin: 0;
				padding: 0;
				box-sizing: border-box;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
				font-style: normal !important;
			}

			body {
				background-color: var(--subtle-gray);
				padding: 0;
				color: var(--black);
			}

			.wrap {
				margin: 0;
				max-width: none;
				width: 100%;
			}

			.container {
				max-width: 1280px;
				margin: 0 auto;
			}

			header {
				display: flex;
				justify-content: flex-end;
				margin-bottom: 3rem;
			}

			.nav-buttons {
				display: flex;
				gap: 0.5rem;
				padding: 0.25rem;
				background: white;
				border: 1px solid var(--light-gray);
				border-radius: 6px;
			}

			.btn {
				padding: 0.5rem 1.5rem;
				font-size: 0.75rem;
				font-weight: 900;
				text-transform: uppercase;
				border-radius: 4px;
				border: none;
				cursor: pointer;
				transition: opacity 0.2s;
			}

			.btn-black {
				background: var(--black);
				color: white;
			}

			.btn-ghost {
				background: transparent;
				color: var(--deep-gray);
			}

			main {
				display: flex;
				flex-direction: column;
				gap: 4rem;
				padding-bottom: 5rem;
			}

			.grid-4 {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
				gap: 1.5rem;
			}

			.grid-3-split {
				display: grid;
				grid-template-columns: 2fr 1fr;
				gap: 1.5rem;
				margin-top: 1.5rem;
			}

			@media (max-width: 1024px) {
				.grid-3-split { grid-template-columns: 1fr; }
			}

			.section-header {
				display: flex;
				align-items: center;
				gap: 0.75rem;
				margin-bottom: 1.5rem;
				margin-top: 2.5rem;
			}

			.section-header:first-child { margin-top: 0; }

			.icon-circle {
				width: 40px;
				height: 40px;
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				flex-shrink: 0;
				color: white;
				box-shadow: 0 1px 2px rgba(0,0,0,0.1);
			}

			.icon-circle svg { width: 20px; height: 20px; }

			.section-title {
				font-size: 1.25rem;
				font-weight: 900;
				text-transform: uppercase;
				letter-spacing: -0.02em;
			}

			.title-line {
				flex: 1;
				height: 1px;
				background: var(--light-gray);
			}

			.card {
				background: var(--off-white);
				border: 1px solid var(--light-gray);
				border-radius: var(--border-radius);
				padding: 1.25rem;
				box-shadow: 0 1px 3px rgba(0,0,0,0.05);
				transition: box-shadow 0.2s;
			}

			.card:hover { box-shadow: 0 4px 6px rgba(0,0,0,0.07); }

			.card-header {
				display: flex;
				justify-content: space-between;
				align-items: flex-start;
			}

			.card-label {
				font-size: 0.875rem;
				font-weight: 600;
				text-transform: uppercase;
				letter-spacing: 0.05em;
				color: var(--deep-gray);
			}

			.card-value {
				font-size: 1.5rem;
				font-weight: 700;
				margin-top: 0.25rem;
			}

			.card-subtext {
				font-size: 0.75rem;
				color: var(--light-gray);
				margin-top: 0.25rem;
			}

			.progress-container {
				margin-bottom: 1rem;
			}

			.progress-labels {
				display: flex;
				justify-content: space-between;
				margin-bottom: 0.35rem;
			}

			.progress-label {
				font-size: 0.75rem;
				font-weight: 700;
				text-transform: uppercase;
				color: var(--deep-gray);
			}

			.progress-count {
				font-size: 0.75rem;
				font-weight: 700;
				color: var(--light-gray);
			}

			.progress-track {
				width: 100%;
				height: 10px;
				background: #e2e2e2;
				border-radius: var(--border-radius);
				overflow: hidden;
			}

			.progress-fill {
				height: 100%;
				transition: width 0.7s ease-out;
			}

			.black-card {
				background: var(--black);
				color: white;
				padding: 2rem;
				border-radius: var(--border-radius);
				display: flex;
				flex-direction: column;
				justify-content: space-between;
			}

			.chart-container {
				display: flex;
				align-items: flex-end;
				justify-content: space-between;
				height: 80px;
				gap: 4px;
				margin-top: 1rem;
			}

			.chart-bar {
				flex: 1;
				border-radius: 4px 4px 0 0;
				background: var(--gold);
			}

			.baseline-card {
				background: var(--black);
				padding: 2.5rem;
				border-radius: var(--border-radius);
				color: white;
				position: relative;
				overflow: hidden;
			}

			.baseline-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
				gap: 3rem;
				margin-top: 3rem;
			}

			.baseline-stat {
				margin-bottom: 0.5rem;
			}

			.baseline-stat-header {
				display: flex;
				justify-content: space-between;
				font-size: 10px;
				font-weight: 700;
				color: #71717a;
				text-transform: uppercase;
				margin-bottom: 0.5rem;
			}

			.baseline-track {
				height: 6px;
				background: #27272a;
				border-radius: var(--border-radius);
			}

			.baseline-fill { height: 100%; border-radius: var(--border-radius); }

			.bg-gold { background: var(--gold); }
			.bg-silver { background: var(--silver); color: var(--black) !important; }
			.bg-copper { background: var(--copper); }
			.bg-black { background: var(--black); }

			footer {
				border-top: 1px solid #e5e7eb;
				padding-top: 3rem;
				padding-bottom: 5rem;
				display: flex;
				justify-content: space-between;
				align-items: center;
			}

			.footer-logo {
				display: flex;
				align-items: center;
				gap: 0.75rem;
			}

			.logo-text-top { font-weight: 900; font-size: 1.125rem; letter-spacing: -0.05em; display: block; }
			.logo-text-bot { font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--light-gray); letter-spacing: 0.3em; }

			.footer-links {
				display: flex;
				gap: 2rem;
				font-size: 10px;
				font-weight: 900;
				text-transform: uppercase;
				letter-spacing: 0.1em;
			}

			.footer-links a { text-decoration: none; color: var(--deep-gray); }
		</style>

		<script src="https://unpkg.com/lucide@latest"></script>

		<div class="container">
			<header>
				<div class="nav-buttons">
					<button class="btn btn-black">Librería</button>
					<button class="btn btn-ghost">Lecturas</button>
					<button class="btn btn-ghost">Social</button>
				</div>
			</header>

			<main>
			<section id="library-stats">
				<div class="section-header" id="library-stats-header">
					<div class="icon-circle bg-gold" id="library-stats-header-icon"><i data-lucide="book"></i></div>
					<h2 class="section-title" id="library-stats-title">Librería</h2>
				</div>

				<div class="grid-4" id="library-stats-grid-4">
					<div class="card" id="library-stats-card-total">
						<div class="card-header" id="library-stats-card-total-header">
							<div id="library-stats-card-total-body">
								<p class="card-label" id="library-stats-card-total-label">Total Libros</p>
								<h3 class="card-value" id="library-stats-card-total-value"><?php echo esc_html( number_format_i18n( $total_user_books ) ); ?></h3>
								<p class="card-subtext" id="library-stats-card-total-subtext">Biblioteca completa</p>
							</div>
							<div class="icon-circle bg-gold" id="library-stats-card-total-icon"><i data-lucide="book"></i></div>
						</div>
					</div>
					<div class="card" id="library-stats-card-read">
						<div class="card-header" id="library-stats-card-read-header">
							<div id="library-stats-card-read-body">
								<p class="card-label" id="library-stats-card-read-label">Leídos / No Leídos</p>
								<h3 class="card-value" id="library-stats-card-read-value">
									<?php
									echo esc_html(
										sprintf(
											'%1$s / %2$s / %3$s',
											number_format_i18n( $reading_status_counts['not_started'] ),
											number_format_i18n( $reading_status_counts['started'] ),
											number_format_i18n( $reading_status_counts['finished'] )
										)
									);
									?>
								</h3>
								<p class="card-subtext" id="library-stats-card-read-subtext">Ratio 1.5x</p>
							</div>
							<div class="icon-circle bg-silver" id="library-stats-card-read-icon"><i data-lucide="book-open"></i></div>
						</div>
					</div>
					<div class="card" id="library-stats-card-format">
						<div class="card-header" id="library-stats-card-format-header">
							<div id="library-stats-card-format-body">
								<p class="card-label" id="library-stats-card-format-label">Físicos vs Digital</p>
								<h3 class="card-value" id="library-stats-card-format-value">92 / 50</h3>
								<p class="card-subtext" id="library-stats-card-format-subtext">Híbrido</p>
							</div>
							<div class="icon-circle bg-copper" id="library-stats-card-format-icon"><i data-lucide="globe"></i></div>
						</div>
					</div>
					<div class="card" id="library-stats-card-rating">
						<div class="card-header" id="library-stats-card-rating-header">
							<div id="library-stats-card-rating-body">
								<p class="card-label" id="library-stats-card-rating-label">Rating Promedio</p>
								<h3 class="card-value" id="library-stats-card-rating-value">4.8</h3>
								<p class="card-subtext" id="library-stats-card-rating-subtext">Calidad alta</p>
							</div>
							<div class="icon-circle bg-gold" id="library-stats-card-rating-icon"><i data-lucide="star"></i></div>
						</div>
					</div>
				</div>

				<div class="grid-3-split" id="library-stats-grid-split">
					<div class="card" id="library-stats-distribution-card">
						<h4 class="card-label" id="library-stats-distribution-title" style="margin-bottom: 1.5rem;">Distribución de Inventario</h4>
						<div class="progress-container" id="library-stats-progress-language">
							<div class="progress-labels" id="library-stats-progress-language-labels"><span class="progress-label" id="library-stats-progress-language-label">Libros por Idioma</span><span class="progress-count" id="library-stats-progress-language-count">110/142</span></div>
							<div class="progress-track" id="library-stats-progress-language-track"><div class="progress-fill bg-gold" id="library-stats-progress-language-fill" style="width: 77%;"></div></div>
						</div>
						<div class="progress-container" id="library-stats-progress-loans">
							<div class="progress-labels" id="library-stats-progress-loans-labels"><span class="progress-label" id="library-stats-progress-loans-label">Libros Prestados</span><span class="progress-count" id="library-stats-progress-loans-count">12/142</span></div>
							<div class="progress-track" id="library-stats-progress-loans-track"><div class="progress-fill bg-silver" id="library-stats-progress-loans-fill" style="width: 8%;"></div></div>
						</div>
						<div class="progress-container" id="library-stats-progress-losses">
							<div class="progress-labels" id="library-stats-progress-losses-labels"><span class="progress-label" id="library-stats-progress-losses-label">Libros Perdidos / Vendidos</span><span class="progress-count" id="library-stats-progress-losses-count">4/142</span></div>
							<div class="progress-track" id="library-stats-progress-losses-track"><div class="progress-fill bg-copper" id="library-stats-progress-losses-fill" style="width: 3%;"></div></div>
						</div>
					</div>
					<div class="black-card shadow-xl" id="library-stats-growth-card">
						<div id="library-stats-growth-header">
							<p id="library-stats-growth-label" style="font-size: 10px; font-weight: 700; letter-spacing: 0.1em; color: #71717a; text-transform: uppercase;">Crecimiento Mensual</p>
							<h3 id="library-stats-growth-value" style="font-size: 1.875rem; font-weight: 900; margin-top: 0.5rem;">+12 <span id="library-stats-growth-unit" style="font-size: 0.875rem; font-weight: 400; color: #a1a1aa;">libros</span></h3>
						</div>
						<div class="chart-container" id="library-stats-growth-chart">
							<div class="chart-bar" id="library-stats-growth-bar-1" style="height: 40%;"></div>
							<div class="chart-bar" id="library-stats-growth-bar-2" style="height: 60%;"></div>
							<div class="chart-bar" id="library-stats-growth-bar-3" style="height: 35%;"></div>
							<div class="chart-bar" id="library-stats-growth-bar-4" style="height: 85%;"></div>
							<div class="chart-bar" id="library-stats-growth-bar-5" style="height: 55%;"></div>
							<div class="chart-bar" id="library-stats-growth-bar-6" style="height: 100%;"></div>
							<div class="chart-bar" id="library-stats-growth-bar-7" style="height: 80%;"></div>
						</div>
					</div>
				</div>
			</section>

				<section>
					<div class="section-header">
						<div class="icon-circle bg-silver"><i data-lucide="clock"></i></div>
						<h2 class="section-title">Lectura y Hábitos</h2>
					</div>
					
					<div class="grid-3-split">
						<div class="card" style="display: flex; flex-direction: column; justify-content: center;">
							<p class="card-label" style="color: #a1a1aa;">Actividad Principal</p>
							<h3 style="font-size: 1.25rem; font-weight: 900; margin-top: 0.5rem;">El problema de los tres cuerpos</h3>
							<div style="margin-top: 2rem;">
								<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 0.5rem;">
									<span style="font-size: 1.875rem; font-weight: 900;">78%</span>
									<span class="card-label" style="font-size: 10px;">Progreso</span>
								</div>
								<div class="progress-track" style="height: 6px;"><div class="progress-fill bg-silver" style="width: 78%;"></div></div>
								<p style="font-size: 0.75rem; color: var(--deep-gray); margin-top: 1rem;">Velocidad constante: 42 pág/h</p>
							</div>
						</div>
						<div style="display: grid; gap: 1.5rem;">
							<div class="card">
								<div class="card-header">
									<div>
										<p class="card-label">Tiempo Total</p>
										<h3 class="card-value">156h</h3>
										<p class="card-subtext">Este año</p>
									</div>
									<div class="icon-circle bg-silver"><i data-lucide="clock"></i></div>
								</div>
							</div>
							<div class="card">
								<div class="card-header">
									<div>
										<p class="card-label">Días Consecutivos</p>
										<h3 class="card-value">24</h3>
										<p class="card-subtext">Racha actual</p>
									</div>
									<div class="icon-circle bg-gold"><i data-lucide="zap"></i></div>
								</div>
							</div>
						</div>
					</div>
				</section>

				<section style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
					<div>
						<div class="section-header">
							<div class="icon-circle bg-copper"><i data-lucide="sticky-note"></i></div>
							<h2 class="section-title">Notas</h2>
						</div>
						<div class="card">
							<div style="display: flex; justify-content: space-between; margin-bottom: 2rem;">
								<div>
									<p style="font-size: 2.25rem; font-weight: 900;">65%</p>
									<p class="card-label" style="font-size: 10px; color: var(--light-gray);">Sesiones con Notas</p>
								</div>
								<div style="text-align: right;">
									<p style="font-size: 1.25rem; font-weight: 700;">40 / 60</p>
									<p class="card-label" style="font-size: 10px; color: var(--light-gray);">Públicas / Privadas</p>
								</div>
							</div>
							<h4 class="card-label" style="font-size: 10px; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
								<i data-lucide="heart" style="width: 12px; color: #b87333;"></i> Distribución Emocional
							</h4>
							<div class="progress-container">
								<div class="progress-labels"><span class="progress-label" style="font-size: 10px; width: 80px;">Inspiración</span><div class="progress-track" style="height: 4px;"><div class="progress-fill bg-copper" style="width: 45%;"></div></div></div>
							</div>
							<div class="progress-container">
								<div class="progress-labels"><span class="progress-label" style="font-size: 10px; width: 80px;">Melancolía</span><div class="progress-track" style="height: 4px;"><div class="progress-fill bg-copper" style="width: 20%;"></div></div></div>
							</div>
							<div class="progress-container">
								<div class="progress-labels"><span class="progress-label" style="font-size: 10px; width: 80px;">Tensión</span><div class="progress-track" style="height: 4px;"><div class="progress-fill bg-copper" style="width: 35%;"></div></div></div>
							</div>
						</div>
					</div>

					<div>
						<div class="section-header">
							<div class="icon-circle bg-black"><i data-lucide="users"></i></div>
							<h2 class="section-title">Social</h2>
						</div>
						<div class="card" style="display: flex; flex-direction: column; gap: 1.5rem;">
							<div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #f9fafb; border: 1px solid var(--light-gray); border-radius: var(--border-radius);">
								<div style="display: flex; align-items: center; gap: 1rem;">
									<div class="icon-circle bg-black" style="font-weight: 900;">S</div>
									<div>
										<p style="font-size: 0.875rem; font-weight: 900; text-transform: uppercase;">Comentarios</p>
										<p style="font-size: 0.75rem; color: #a1a1aa;">24 nuevas interacciones</p>
									</div>
								</div>
								<span style="font-size: 1.125rem; font-weight: 900;">+12%</span>
							</div>
							<div style="display: flex; justify-content: space-between; align-items: center;">
								<p class="card-label" style="font-size: 10px;">Usuarios Activos</p>
								<div style="display: flex; margin-right: 0.75rem;">
									<div style="width: 40px; height: 40px; border-radius: 50%; border: 4px solid white; background: #e5e7eb; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 900; margin-right: -12px;">U1</div>
									<div style="width: 40px; height: 40px; border-radius: 50%; border: 4px solid white; background: #d1d5db; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 900; margin-right: -12px;">U2</div>
									<div style="width: 40px; height: 40px; border-radius: 50%; border: 4px solid white; background: #9ca3af; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 900; margin-right: -12px;">U3</div>
									<div style="width: 40px; height: 40px; border-radius: 50%; border: 4px solid white; background: var(--black); color: white; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 900;">+8</div>
								</div>
							</div>
							<div style="padding: 1rem; border-left: 4px solid #C79F32; background: var(--off-white); border-radius: 0 var(--border-radius) var(--border-radius) 0;">
								<p class="card-label" style="font-size: 10px; color: #a1a1aa; margin-bottom: 0.25rem;">Thread más largo</p>
								<p style="font-size: 0.875rem; font-weight: 700; letter-spacing: -0.01em;">La deconstrucción del héroe en el siglo XXI</p>
							</div>
						</div>
					</div>
				</section>

				<section>
					<div class="section-header">
						<div class="icon-circle bg-copper"><i data-lucide="hand-helping"></i></div>
						<h2 class="section-title">Préstamos</h2>
					</div>
					<div class="grid-4">
						<div class="card">
							<div class="card-header">
								<div><p class="card-label">Activos</p><h3 class="card-value">12</h3><p class="card-subtext">Libros fuera</p></div>
								<div class="icon-circle bg-copper"><i data-lucide="hand-helping"></i></div>
							</div>
						</div>
						<div class="card">
							<div class="card-header">
								<div><p class="card-label">Promedio Días</p><h3 class="card-value">18d</h3><p class="card-subtext">Tiempo retorno</p></div>
								<div class="icon-circle bg-silver"><i data-lucide="clock"></i></div>
							</div>
						</div>
						<div class="card">
							<div class="card-header">
								<div><p class="card-label">No Devueltos</p><h3 class="card-value">02</h3><p class="card-subtext">Alerta crítica</p></div>
								<div class="icon-circle bg-gold"><i data-lucide="zap"></i></div>
							</div>
						</div>
						<div class="card">
							<div class="card-header">
								<div><p class="card-label">Personas Freq.</p><h3 class="card-value">Ana, Luis</h3><p class="card-subtext">Confianza</p></div>
								<div class="icon-circle bg-silver"><i data-lucide="users"></i></div>
							</div>
						</div>
					</div>
				</section>

				<section>
					<div class="section-header">
						<div class="icon-circle bg-black"><i data-lucide="trending-up"></i></div>
						<h2 class="section-title">Baselines</h2>
						<div class="title-line"></div>
					</div>
					<div class="baseline-card shadow-2xl">
						<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3rem;">
							<div>
								<h3 style="font-size: 1.875rem; font-weight: 900; text-transform: uppercase;">Evolución vs Baseline</h3>
								<p style="font-size: 0.875rem; font-weight: 700; text-transform: uppercase; color: #71717a; letter-spacing: 0.1em; margin-top: 0.25rem;">Comparativa de hábitos iniciales</p>
							</div>
							<div style="padding: 0.5rem 1.5rem; border: 1px solid #3f3f46; border-radius: var(--border-radius); font-size: 10px; font-weight: 700; text-transform: uppercase;">Estadística de Crecimiento</div>
						</div>

						<div class="baseline-grid">
							<div class="baseline-stat">
								<div class="baseline-stat-header"><span>Hábitos AVG</span><span style="color: white;">+45%</span></div>
								<div class="baseline-track"><div class="baseline-fill bg-gold" style="width: 85%;"></div></div>
							</div>
							<div class="baseline-stat">
								<div class="baseline-stat-header"><span>Velocidad</span><span style="color: white;">+20%</span></div>
								<div class="baseline-track"><div class="baseline-fill bg-silver" style="width: 60%;"></div></div>
							</div>
							<div class="baseline-stat">
								<div class="baseline-stat-header"><span>Notas Freq</span><span style="color: white;">+120%</span></div>
								<div class="baseline-track"><div class="baseline-fill bg-copper" style="width: 92%;"></div></div>
							</div>
						</div>

						<div style="margin-top: 4rem; display: flex; align-items: baseline; gap: 1rem;">
							<span style="font-size: 4.5rem; font-weight: 900; color: var(--off-white);">12.5K</span>
							<div>
								<p style="font-size: 1.25rem; font-weight: 700; text-transform: uppercase;">Páginas Totales</p>
								<p style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #71717a; letter-spacing: 0.1em;">Superado Baseline Anual</p>
							</div>
						</div>
						<div style="position: absolute; inset: 0; opacity: 0.05; pointer-events: none; background-image: radial-gradient(white 1px, transparent 1px); background-size: 40px 40px;"></div>
					</div>
				</section>
			</main>

			<footer>
				<div class="footer-logo">
					<div class="icon-circle bg-black"><i data-lucide="book" style="color: white;"></i></div>
					<div>
						<span class="logo-text-top">LECTURASTATS</span>
						<span class="logo-text-bot">SISTEMA DE GESTIÓN</span>
					</div>
				</div>
				<div class="footer-links">
					<a href="#">Exportar Reporte</a>
					<a href="#">Base de Datos</a>
					<a href="#">Legal</a>
				</div>
			</footer>
		</div>

		<script>
			window.onload = function() {
				lucide.createIcons();
			};
		</script>
	<?php else : ?>
		<p><?php esc_html_e( 'Access denied.', 'politeia-reading' ); ?></p>
	<?php endif; ?>
</div>

<?php
get_footer();
