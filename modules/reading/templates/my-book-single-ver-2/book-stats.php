<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<style>
	@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

	:root {
		--pure-black: #000000;
		--deep-gray: #333333;
		--metallic-gold: #C79F32;
		--accent-orange: #FF8C00;
		--light-gray: #A8A8A8;
		--subtle-gray: #F5F5F5;
		--off-white: #FEFEFF;
	}

	.prs-book-stats {
		font-family: 'Inter', sans-serif;
		background-color: var(--off-white);
		color: var(--deep-gray);
		padding: 24px;
	}

	@media (min-width: 768px) {
		.prs-book-stats {
			padding: 20px 0px;
		}
	}

	.prs-book-stats__container {
		max-width: 72rem;
		margin: 0 auto;
	}

	.prs-book-stats__grid {
		display: grid;
		grid-template-columns: 1fr;
		gap: 24px;
	}

	@media (min-width: 768px) {
		.prs-book-stats__grid {
			grid-template-columns: repeat(2, minmax(0, 1fr));
		}
	}

	.chart-container {
		display: flex;
		align-items: flex-end;
		gap: 8px;
		height: 150px;
	}

	.prs-book-stats__chart--month {
		gap: 2px;
	}

	.bar {
		background: var(--metallic-gold);
		border-radius: 2px 2px 0 0;
		transition: all 0.3s ease;
		position: relative;
		flex: 1 1 0;
	}

	.bar:hover {
		background: var(--accent-orange);
		transform: scaleY(1.05);
	}

	/* Tooltip-like effect on hover */
	.bar:hover::after {
		content: attr(data-value);
		position: absolute;
		top: -25px;
		left: 50%;
		transform: translateX(-50%);
		background: transparent;
		color: var(--pure-black);
		font-size: 10px;
		padding: 0;
		border-radius: 0;
		white-space: nowrap;
	}

	.card {
		background: #ffffff;
		border: 1px solid #e4e4e4;
		border-radius: 12px;
		padding: 24px;
		transition: border-color 0.2s ease;
	}

	.card:hover {
		border-color: var(--metallic-gold);
	}

	.prs-book-stats__kpi {
		display: flex;
		flex-direction: column;
		justify-content: center;
	}

	.prs-book-stats__kpi-row {
		display: flex;
		align-items: center;
		gap: 16px;
	}

	.prs-book-stats__icon {
		padding: 12px;
		background: #000;
		border-radius: 8px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
	}

	.prs-book-stats__label {
		font-size: 12px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.12em;
		opacity: 0.7;
		margin-bottom: 0;
	}

	.prs-book-stats__metric {
		font-size: 1.875rem;
		line-height: 1.2;
		margin-bottom: 0;
	}

	.prs-book-stats__section-title {
		font-size: 1.125rem;
		margin: 0 0 6px;
	}

	.prs-book-stats__section-subtitle {
		font-size: 10px;
		margin: 0;
		opacity: 0.7;
	}

	.prs-book-stats__labels {
		display: flex;
		gap: 8px;
		margin-top: 16px;
		font-size: 10px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.08em;
	}

	.prs-book-stats__labels span {
		flex: 1 1 0;
		text-align: center;
	}

	.prs-book-stats__month-scale {
		display: flex;
		justify-content: space-between;
		margin-top: 16px;
		padding: 0 4px;
		font-size: 10px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.08em;
	}

	.headline {
		color: var(--pure-black);
		font-weight: 700;
	}

	.subtitle {
		color: var(--deep-gray);
		font-weight: 500;
	}
</style>

<?php
$avg_session_minutes = null;
$avg_pages_per_hour = null;
$session_duration_total = 0;
$session_duration_count = 0;
$session_pages_total = 0;
$session_pages_count = 0;
$session_rate_duration_total = 0;
$session_rate_pages_total = 0;
$weekly_pages = array_fill( 0, 7, 0 );
$weekly_date_range = '';
$monthly_pages = array();
$monthly_date_range = '';
$month_last_day = 0;

if ( ! empty( $sessions ) ) {
	foreach ( $sessions as $session ) {
		if ( empty( $session->start_time ) || empty( $session->end_time ) ) {
			continue;
		}
		$start_timestamp = strtotime( $session->start_time );
		$end_timestamp   = strtotime( $session->end_time );
		if ( ! $start_timestamp || ! $end_timestamp ) {
			continue;
		}
		$duration = $end_timestamp - $start_timestamp;
		if ( $duration < 0 ) {
			$duration = 0;
		}
		$session_duration_total += $duration;
		$session_duration_count++;
		$start_page = isset( $session->start_page ) ? (int) $session->start_page : null;
		$end_page   = isset( $session->end_page ) ? (int) $session->end_page : null;
		if ( null !== $start_page && null !== $end_page ) {
			$page_delta = $end_page - $start_page;
			if ( $page_delta < 0 ) {
				$page_delta = 0;
			}
			$session_pages_total += $page_delta;
			$session_pages_count++;
			if ( $duration > 0 ) {
				$session_rate_pages_total += $page_delta;
				$session_rate_duration_total += $duration;
			}
		}
	}
}

if ( $session_duration_count > 0 ) {
	$avg_session_minutes = (int) round( ( $session_duration_total / $session_duration_count ) / 60 );
}
if ( $session_rate_duration_total > 0 ) {
	$avg_pages_per_hour = (int) round( ( $session_rate_pages_total / $session_rate_duration_total ) * 3600 );
}

$week_start_ts = strtotime( 'monday this week', current_time( 'timestamp' ) );
$week_end_ts   = $week_start_ts + ( 6 * DAY_IN_SECONDS );
$weekly_date_range = date_i18n( 'M j', $week_start_ts ) . ' - ' . date_i18n( 'M j, Y', $week_end_ts );

if ( ! empty( $sessions ) && $week_start_ts ) {
	foreach ( $sessions as $session ) {
		if ( empty( $session->start_time ) ) {
			continue;
		}
		$start_timestamp = strtotime( $session->start_time );
		if ( ! $start_timestamp ) {
			continue;
		}
		$session_day_ts = strtotime( date( 'Y-m-d', $start_timestamp ) );
		if ( $session_day_ts < $week_start_ts || $session_day_ts > $week_end_ts ) {
			continue;
		}
		$day_index = (int) floor( ( $session_day_ts - $week_start_ts ) / DAY_IN_SECONDS );
		if ( $day_index < 0 || $day_index > 6 ) {
			continue;
		}
		$start_page = isset( $session->start_page ) ? (int) $session->start_page : null;
		$end_page   = isset( $session->end_page ) ? (int) $session->end_page : null;
		if ( null === $start_page || null === $end_page ) {
			continue;
		}
		$page_delta = $end_page - $start_page;
		if ( $page_delta < 0 ) {
			$page_delta = 0;
		}
		$weekly_pages[ $day_index ] += $page_delta;
	}
}

$weekly_max = max( $weekly_pages );

$month_start_ts = strtotime( date_i18n( 'Y-m-01', current_time( 'timestamp' ) ) );
$month_last_day = (int) date_i18n( 't', $month_start_ts );
$month_end_ts   = strtotime( date_i18n( 'Y-m-t', $month_start_ts ) );
$monthly_date_range = date_i18n( 'M 1', $month_start_ts ) . ' - ' . date_i18n( 'M j, Y', $month_end_ts );
$monthly_pages = array_fill( 0, $month_last_day, 0 );

if ( ! empty( $sessions ) && $month_start_ts ) {
	foreach ( $sessions as $session ) {
		if ( empty( $session->start_time ) ) {
			continue;
		}
		$start_timestamp = strtotime( $session->start_time );
		if ( ! $start_timestamp ) {
			continue;
		}
		$session_day_ts = strtotime( date( 'Y-m-d', $start_timestamp ) );
		if ( $session_day_ts < $month_start_ts || $session_day_ts > $month_end_ts ) {
			continue;
		}
		$day_index = (int) date( 'j', $session_day_ts ) - 1;
		if ( $day_index < 0 || $day_index >= $month_last_day ) {
			continue;
		}
		$start_page = isset( $session->start_page ) ? (int) $session->start_page : null;
		$end_page   = isset( $session->end_page ) ? (int) $session->end_page : null;
		if ( null === $start_page || null === $end_page ) {
			continue;
		}
		$page_delta = $end_page - $start_page;
		if ( $page_delta < 0 ) {
			$page_delta = 0;
		}
		$monthly_pages[ $day_index ] += $page_delta;
	}
}

$monthly_max = max( $monthly_pages );
?>

<section class="prs-book-stats">
	<div class="prs-book-stats__container">
		<!-- 2x2 Grid -->
		<div class="prs-book-stats__grid">

			<!-- Div 1: Session Time -->
			<div id="session-time" class="card prs-book-stats__kpi">
				<div class="prs-book-stats__kpi-row">
					<div class="prs-book-stats__icon" style="color: var(--metallic-gold);">
						<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
							<path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
						</svg>
					</div>
					<div>
						<p class="prs-book-stats__label subtitle">Average Session Time</p>
						<h2 class="prs-book-stats__metric headline">
							<?php echo null !== $avg_session_minutes ? esc_html( $avg_session_minutes . 'min' ) : '—'; ?>
						</h2>
					</div>
				</div>
			</div>

			<!-- Div 2: Pages per Hour -->
			<div id="pages-per-hour" class="card prs-book-stats__kpi">
				<div class="prs-book-stats__kpi-row">
					<div class="prs-book-stats__icon" style="color: var(--accent-orange);">
						<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
							<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
						</svg>
					</div>
					<div>
						<p class="prs-book-stats__label subtitle">Average Page per Hour</p>
						<h2 class="prs-book-stats__metric headline">
							<?php echo null !== $avg_pages_per_hour ? esc_html( (string) $avg_pages_per_hour ) : '—'; ?>
						</h2>
					</div>
				</div>
			</div>

			<!-- Div 3: Weekly Chart -->
			<div id="weekly-chart" class="card">
				<div class="prs-book-stats__section">
					<h3 class="prs-book-stats__section-title headline">Pages This Week</h3>
					<p class="prs-book-stats__section-subtitle subtitle" id="weekly-date-range"><?php echo esc_html( $weekly_date_range ); ?></p>
				</div>
				<div class="chart-container" id="week-bars">
					<?php
					foreach ( $weekly_pages as $value ) :
						$height_percent = $weekly_max > 0 ? ( $value / $weekly_max ) * 90 : 0;
						?>
						<div class="bar" data-value="<?php echo esc_attr( $value ); ?>" style="height: <?php echo esc_attr( number_format( $height_percent, 2, '.', '' ) ); ?>%;"></div>
					<?php endforeach; ?>
				</div>
				<div class="prs-book-stats__labels subtitle">
					<span>Mon</span>
					<span>Tue</span>
					<span>Wed</span>
					<span>Thu</span>
					<span>Fri</span>
					<span>Sat</span>
					<span>Sun</span>
				</div>
			</div>

			<!-- Div 4: Monthly Chart -->
			<div id="monthly-chart" class="card">
				<div class="prs-book-stats__section">
					<h3 class="prs-book-stats__section-title headline" id="monthly-title">Pages This Month</h3>
					<p class="prs-book-stats__section-subtitle subtitle" id="monthly-date-range"><?php echo esc_html( $monthly_date_range ); ?></p>
				</div>
				<div class="chart-container prs-book-stats__chart--month" id="month-bars">
					<?php
					foreach ( $monthly_pages as $value ) :
						$height_percent = $monthly_max > 0 ? ( $value / $monthly_max ) * 90 : 0;
						?>
						<div class="bar" data-value="<?php echo esc_attr( $value ); ?>" style="height: <?php echo esc_attr( number_format( $height_percent, 2, '.', '' ) ); ?>%;"></div>
					<?php endforeach; ?>
				</div>
				<div class="prs-book-stats__month-scale subtitle">
					<span>1</span>
					<span>15</span>
					<span id="month-last-day"><?php echo esc_html( (string) $month_last_day ); ?></span>
				</div>
			</div>

		</div>
	</div>
</section>

<script>
	// Setup dates for dynamic headers
	const now = new Date();
	const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

	/**
	 * -------------------------------------------------------------------------
	 * DATA INTEGRATION POINT: MONTHLY CHART
	 * Data is rendered server-side based on this month's sessions.
	 * -------------------------------------------------------------------------
	 */
	document.getElementById('monthly-title').textContent = `Pages ${monthNames[now.getMonth()]}`;
</script>
