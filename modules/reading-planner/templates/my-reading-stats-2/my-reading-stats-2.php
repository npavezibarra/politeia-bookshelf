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
$avg_session_minutes = 0;
$total_pages_month   = 0;
$total_reading_hours_month = 0;
$total_reading_seconds_month = 0;
$avg_pages_per_hour = 0;
$heatmap_cells = array();
$stats_sections = array(
	'performance' => true,
	'consistency' => true,
	'library'     => true,
);
if ( $is_owner ) {
	if ( function_exists( 'politeia_bookshelf_get_my_stats_sections' ) ) {
		$stats_sections = politeia_bookshelf_get_my_stats_sections();
	}
	global $wpdb;
	$user_id        = (int) $current_user->ID;
	$sessions_table = $wpdb->prefix . 'politeia_reading_sessions';
	$timezone       = wp_timezone();
	$now_local      = new DateTimeImmutable( 'now', $timezone );
	$month_start_local = $now_local->modify( 'first day of this month' )->setTime( 0, 0, 0 );
	$month_end_local   = $now_local->modify( 'last day of this month' )->setTime( 23, 59, 59 );
	$month_start    = $month_start_local->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	$month_end      = $month_end_local->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	$avg_seconds    = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT AVG(TIMESTAMPDIFF(SECOND, start_time, end_time))
			 FROM {$sessions_table}
			 WHERE user_id = %d
			   AND end_time IS NOT NULL
			   AND deleted_at IS NULL
			   AND start_time >= %s
			   AND start_time <= %s",
			$user_id,
			$month_start,
			$month_end
		)
	);
	if ( $avg_seconds ) {
		$avg_session_minutes = (int) round( (float) $avg_seconds / 60 );
	}
	$total_seconds = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT SUM(GREATEST(TIMESTAMPDIFF(SECOND, start_time, end_time), 0))
			 FROM {$sessions_table}
			 WHERE user_id = %d
			   AND end_time IS NOT NULL
			   AND deleted_at IS NULL
			   AND start_time >= %s
			   AND start_time <= %s",
			$user_id,
			$month_start,
			$month_end
		)
	);
	if ( $total_seconds ) {
		$total_reading_seconds_month = (float) $total_seconds;
		$total_reading_hours_month = (int) round( $total_reading_seconds_month / 3600 );
	}
	$total_pages = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT SUM(GREATEST(end_page - start_page, 0))
			 FROM {$sessions_table}
			 WHERE user_id = %d
			   AND end_time IS NOT NULL
			   AND deleted_at IS NULL
			   AND start_time >= %s
			   AND start_time <= %s",
			$user_id,
			$month_start,
			$month_end
		)
	);
	if ( null !== $total_pages ) {
		$total_pages_month = (int) $total_pages;
	}
	if ( $total_reading_seconds_month > 0 ) {
		$avg_pages_per_hour = (int) round( $total_pages_month / ( $total_reading_seconds_month / 3600 ) );
	}

	$heatmap_year = (int) $now_local->format( 'Y' );
	$heatmap_start = new DateTimeImmutable( $heatmap_year . '-01-01 00:00:00', $timezone );
	$heatmap_end = new DateTimeImmutable( $heatmap_year . '-12-31 23:59:59', $timezone );
	$heatmap_start_gmt = $heatmap_start->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	$heatmap_end_gmt   = $heatmap_end->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );

	$heatmap_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT start_time, start_page, end_page
			 FROM {$sessions_table}
			 WHERE user_id = %d
			   AND deleted_at IS NULL
			   AND start_time >= %s
			   AND start_time <= %s",
			$user_id,
			$heatmap_start_gmt,
			$heatmap_end_gmt
		),
		ARRAY_A
	);

	$pages_by_date = array();
	if ( $heatmap_rows ) {
		foreach ( $heatmap_rows as $row ) {
			if ( empty( $row['start_time'] ) ) {
				continue;
			}
			$start_dt = date_create_immutable( $row['start_time'], new DateTimeZone( 'UTC' ) );
			if ( ! $start_dt ) {
				continue;
			}
			$local_dt = $start_dt->setTimezone( $timezone );
			$date_key = $local_dt->format( 'Y-m-d' );
			$start_page = isset( $row['start_page'] ) ? (int) $row['start_page'] : 0;
			$end_page   = isset( $row['end_page'] ) ? (int) $row['end_page'] : 0;
			$delta      = $end_page - $start_page;
			if ( $delta < 0 ) {
				$delta = 0;
			}
			if ( ! isset( $pages_by_date[ $date_key ] ) ) {
				$pages_by_date[ $date_key ] = 0;
			}
			$pages_by_date[ $date_key ] += $delta;
		}
	}

	$max_pages = $pages_by_date ? max( $pages_by_date ) : 0;
	$day_count = (int) $heatmap_start->diff( $heatmap_end )->days + 1;
	$week_start = 1; // Monday.
	$first_day = (int) $heatmap_start->format( 'N' );
	$leading = ( $first_day - $week_start + 7 ) % 7;
	for ( $i = 0; $i < $leading; $i++ ) {
		$heatmap_cells[] = array(
			'date'  => null,
			'pages' => 0,
			'class' => 'heatmap-cell is-empty',
		);
	}

	$cell_date = $heatmap_start;
	for ( $i = 0; $i < $day_count; $i++ ) {
		$date_key = $cell_date->format( 'Y-m-d' );
		$pages    = isset( $pages_by_date[ $date_key ] ) ? (int) $pages_by_date[ $date_key ] : 0;
		$class    = 'heatmap-cell';
		if ( $pages > 0 && $max_pages > 0 ) {
			$ratio = $pages / $max_pages;
			if ( $ratio <= 0.25 ) {
				$class .= ' level-1';
			} elseif ( $ratio <= 0.5 ) {
				$class .= ' level-2';
			} elseif ( $ratio <= 0.75 ) {
				$class .= ' level-3';
			} else {
				$class .= ' level-4';
			}
		}
		$heatmap_cells[] = array(
			'date'  => $cell_date,
			'pages' => $pages,
			'class' => $class,
		);
		$cell_date = $cell_date->modify( '+1 day' );
	}

	$last_day = (int) $heatmap_end->format( 'N' );
	$last_index = ( $last_day - $week_start + 7 ) % 7;
	$trailing = 6 - $last_index;
	for ( $i = 0; $i < $trailing; $i++ ) {
		$heatmap_cells[] = array(
			'date'  => null,
			'pages' => 0,
			'class' => 'heatmap-cell is-empty',
		);
	}
}
?>

<div class="wrap">
	<?php if ( $is_owner ) : ?>
		<?php echo do_shortcode( '[politeia_reading_plan]' ); ?>
		<style>
			#politeia-open-reading-plan {
				display: none;
			}
			.prs-reading-stats-dashboard {
				background-color: #f5f5f5;
				display: flex;
				align-items: start;
				justify-content: center;
				min-height: 70vh;
				margin: 0;
				font-family: system-ui, -apple-system, sans-serif;
				margin-top: 24px;
				width: 100%;
			}
			.prs-reading-stats-shell {
				width: 100%;
				max-width: none;
				--gold-grad: linear-gradient(135deg, #8a6b1e, #c79f32, #e9d18a);
				--silver-grad: linear-gradient(135deg, #949494, #d1d1d1, #f2f2f2);
				--copper-grad: linear-gradient(135deg, #783f27, #b87333, #e5aa70);
				--pure-black: #000000;
				--deep-gray: #333333;
				--light-gray: #a8a8a8;
				--subtle-gray: #f5f5f5;
				--off-white: #fefeff;
				background-color: var(--subtle-gray);
				color: var(--pure-black);
				font-family: 'Inter', system-ui, -apple-system, sans-serif;
				padding: 16px;
			}
			@media (min-width: 768px) {
				.prs-reading-stats-shell {
					padding: 0px 0px 122px;
				}
			}
			.prs-reading-stats-shell .material-symbols-outlined {
				font-variation-settings:
					'FILL' 0,
					'wght' 400,
					'GRAD' 0,
					'opsz' 24;
			}
			.prs-reading-stats-shell .gradient-gold {
				background: var(--gold-grad);
			}
			.prs-reading-stats-shell .gradient-silver {
				background: var(--silver-grad);
			}
			.prs-reading-stats-shell .gradient-copper {
				background: var(--copper-grad);
			}
			.prs-reading-stats-shell .card-politeia {
				background-color: var(--off-white);
				border: 1px solid #e2e8f0;
				transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
				border-radius: 9px;
			}
			.prs-reading-stats-shell .card-politeia:hover {
				transform: translateY(-2px);
				box-shadow: 0 12px 24px -10px rgba(0, 0, 0, 0.1);
				border-color: var(--light-gray);
			}
			.prs-reading-stats-shell .section-header {
				display: flex;
				align-items: center;
				gap: 12px;
				padding-bottom: 8px;
			}
			.prs-stats-section-title {
				font-size: 0.95rem;
				font-weight: 800;
				letter-spacing: 0.18em;
				text-transform: uppercase;
				margin: 0;
			}
			.prs-stats-section-icon {
				font-size: 1.25rem;
			}
			.prs-reading-stats-shell .stat-label {
				font-size: 0.65rem;
				font-weight: 800;
				text-transform: uppercase;
				letter-spacing: 0.15em;
				color: var(--deep-gray);
				line-height: 1.3;
			}
			.prs-stats-section {
				margin-bottom: 64px;
			}
			.prs-stats-section:last-child {
				margin-bottom: 0;
			}
			.prs-stats-grid {
				display: grid;
				gap: 24px;
			}
			.prs-stats-grid--4 {
				grid-template-columns: 1fr;
			}
			.prs-stats-grid--3 {
				grid-template-columns: 1fr;
			}
			.prs-stats-grid--library {
				grid-template-columns: 1fr;
			}
			@media (min-width: 640px) {
				.prs-stats-grid--4 {
					grid-template-columns: repeat(2, minmax(0, 1fr));
				}
			}
			@media (min-width: 1024px) {
				.prs-stats-grid--4 {
					grid-template-columns: repeat(4, minmax(0, 1fr));
				}
				.prs-stats-grid--3 {
					grid-template-columns: repeat(3, minmax(0, 1fr));
				}
				.prs-stats-grid--library {
					grid-template-columns: repeat(3, minmax(0, 1fr));
				}
			}
			.prs-stats-card {
				position: relative;
				overflow: hidden;
				padding: 32px;
				display: flex;
				flex-direction: column;
				align-items: center;
				text-align: center;
				gap: 8px;
			}
			.prs-stats-card--left {
				align-items: stretch;
				text-align: left;
			}
			.prs-stats-card--accent {
				border-left: 8px solid #b87333;
				padding: 24px;
				align-items: stretch;
				text-align: left;
			}
			.prs-stats-card--dark {
				background-color: #000000;
				color: #ffffff;
				border-color: #000000;
				padding: 24px;
				text-align: left;
			}
			.prs-stats-watermark {
				position: absolute;
				top: -14px;
				right: -14px;
				font-size: 3.5rem;
				opacity: 0.06;
				pointer-events: none;
			}
			.prs-stats-badge {
				width: 56px;
				height: 56px;
				border-radius: 50%;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				color: #ffffff;
				box-shadow: 0 10px 18px -10px rgba(0, 0, 0, 0.3);
				margin-bottom: 8px;
			}
			.prs-stats-badge .material-symbols-outlined {
				font-size: 1.6rem;
			}
			.prs-stats-number {
				font-size: 2.2rem;
				font-weight: 900;
			}
			.prs-stats-heatmap-header {
				display: flex;
				align-items: center;
				justify-content: space-between;
				gap: 16px;
				margin-bottom: 20px;
				flex-wrap: wrap;
			}
			.prs-stats-legend {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				font-size: 0.5rem;
				font-weight: 700;
				text-transform: uppercase;
				letter-spacing: 0.1em;
				color: #9ca3af;
			}
			.prs-stats-legend-dot {
				width: 8px;
				height: 8px;
				border-radius: 2px;
				background-color: #ebedf0;
			}
			.prs-reading-stats-shell .heatmap-grid {
				display: grid;
				grid-template-columns: repeat(53, 1fr);
				grid-template-rows: repeat(7, 1fr);
				grid-auto-flow: column;
				gap: 3px;
			}
			.prs-reading-stats-shell .heatmap-cell {
				aspect-ratio: 1 / 1;
				border-radius: 9px;
				background-color: #ebedf0;
			}
			.prs-reading-stats-shell .heatmap-cell.is-empty {
				background-color: transparent;
			}
			.prs-reading-stats-shell .level-1 {
				background-color: #e9d18a;
				opacity: 0.3;
			}
			.prs-reading-stats-shell .level-2 {
				background-color: #c79f32;
				opacity: 0.6;
			}
			.prs-reading-stats-shell .level-3 {
				background-color: #c79f32;
			}
			.prs-reading-stats-shell .level-4 {
				background-color: #8a6b1e;
			}
			.prs-stats-months {
				display: flex;
				justify-content: space-between;
				margin-top: 16px;
				font-size: 0.55rem;
				font-weight: 700;
				text-transform: uppercase;
				letter-spacing: 0.14em;
				color: #9ca3af;
			}
			.prs-stats-stack {
				display: flex;
				flex-direction: column;
				gap: 24px;
			}
			.prs-stats-accent-row {
				display: flex;
				align-items: center;
				gap: 16px;
			}
			.prs-stats-accent-icon {
				width: 48px;
				height: 48px;
				border-radius: 9px;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				color: #ffffff;
				flex-shrink: 0;
			}
			.prs-stats-accent-value {
				font-size: 1.8rem;
				font-weight: 900;
			}
			.prs-stats-format-group {
				display: flex;
				gap: 12px;
				align-items: flex-end;
				margin-top: 12px;
			}
			.prs-stats-format-bar {
				flex: 1;
				background: #f3f4f6;
				border-radius: 9px;
				height: 96px;
				position: relative;
				overflow: hidden;
			}
			.prs-stats-format-fill {
				position: absolute;
				bottom: 0;
				width: 100%;
			}
			.prs-stats-format-label {
				position: absolute;
				top: 8px;
				left: 8px;
				font-size: 0.55rem;
				font-weight: 800;
				text-transform: uppercase;
				letter-spacing: 0.1em;
				color: #111827;
			}
			.prs-stats-format-label--muted {
				color: #6b7280;
			}
			.prs-stats-prediction-title {
				font-size: 0.6rem;
				font-weight: 700;
				text-transform: uppercase;
				letter-spacing: 0.1em;
				color: #e9d18a;
				margin-bottom: 8px;
			}
			.prs-stats-prediction-text {
				font-size: 0.85rem;
				line-height: 1.4;
				margin: 0;
			}
			.prs-stats-highlight {
				color: #e9d18a;
				font-weight: 900;
			}
			.prs-stats-radar-card {
				min-height: 350px;
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
				gap: 24px;
				padding: 24px;
			}
			.prs-stats-radar-container {
				position: relative;
				width: 100%;
				max-width: 320px;
				aspect-ratio: 1 / 1;
				display: flex;
				align-items: center;
				justify-content: center;
			}
			.prs-stats-radar-label {
				position: absolute;
				font-size: 0.55rem;
				font-weight: 800;
				letter-spacing: 0.18em;
				background: #ffffff;
				padding: 4px 8px;
				text-transform: uppercase;
			}
			.prs-stats-radar-label--top {
				top: -6px;
			}
			.prs-stats-radar-label--bottom {
				bottom: -6px;
			}
			.prs-stats-radar-label--left {
				left: -6px;
			}
			.prs-stats-radar-label--right {
				right: -6px;
			}
			.prs-stats-pill {
				display: inline-flex;
				align-items: center;
				gap: 8px;
				padding: 6px 12px;
				background: #000000;
				color: #ffffff;
				border-radius: 9px;
				font-size: 0.55rem;
				font-weight: 700;
				letter-spacing: 0.12em;
				text-transform: uppercase;
			}
			.prs-stats-pill-dot {
				width: 8px;
				height: 8px;
				border-radius: 50%;
			}
			@media (min-width: 1024px) {
				.prs-stats-span-2 {
					grid-column: span 2;
				}
			}
		</style>
		<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />

		<div class="prs-reading-stats-dashboard">
			<div class="prs-reading-stats-shell">
				<?php /* Stats layout intentionally removed for my-reading-stats-2. */ ?>
			</div>
		</div>
	<?php else : ?>
		<p><?php esc_html_e( 'Access denied.', 'politeia-reading' ); ?></p>
	<?php endif; ?>
</div>

<?php
get_footer();
