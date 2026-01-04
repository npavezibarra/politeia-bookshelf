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
		background: var(--pure-black);
		color: white;
		font-size: 10px;
		padding: 2px 6px;
		border-radius: 4px;
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
						<h2 class="prs-book-stats__metric headline">30min</h2>
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
						<h2 class="prs-book-stats__metric headline">6</h2>
					</div>
				</div>
			</div>

			<!-- Div 3: Weekly Chart -->
			<div id="weekly-chart" class="card">
				<div class="prs-book-stats__section">
					<h3 class="prs-book-stats__section-title headline">Pages This Week</h3>
					<p class="prs-book-stats__section-subtitle subtitle" id="weekly-date-range">date of this week</p>
				</div>
				<div class="chart-container" id="week-bars">
					<!-- Weekly bars will be injected here -->
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
					<p class="prs-book-stats__section-subtitle subtitle" id="monthly-date-range">date of this month</p>
				</div>
				<div class="chart-container prs-book-stats__chart--month" id="month-bars">
					<!-- Dynamic number of bars injected here -->
				</div>
				<div class="prs-book-stats__month-scale subtitle">
					<span>1</span>
					<span>15</span>
					<span id="month-last-day">30</span>
				</div>
			</div>

		</div>
	</div>
</section>

<script>
	// Setup dates for dynamic headers
	const now = new Date();
	const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

	// Update Monthly Title
	document.getElementById('monthly-title').textContent = `Pages ${monthNames[now.getMonth()]}`;

	/**
	 * -------------------------------------------------------------------------
	 * DATA INTEGRATION POINT: WEEKLY CHART
	 * Replace the 'weeklyData' array with values from your database.
	 * -------------------------------------------------------------------------
	 */
	const weeklyData = [45, 78, 56, 90, 65, 30, 42]; // Dummy weekly data

	const weekContainer = document.getElementById('week-bars');
	weeklyData.forEach(val => {
		const bar = document.createElement('div');
		bar.className = 'bar';
		bar.style.height = `${val}%`;
		bar.setAttribute('data-value', val);
		weekContainer.appendChild(bar);
	});


	/**
	 * -------------------------------------------------------------------------
	 * DATA INTEGRATION POINT: MONTHLY CHART
	 * This section dynamically calculates days in the month and renders bars.
	 * Replace random generation with real data array matching daysInMonth.
	 * -------------------------------------------------------------------------
	 */
	const monthContainer = document.getElementById('month-bars');

	// Calculate days in the current month
	const year = now.getFullYear();
	const month = now.getMonth();
	const daysInMonth = new Date(year, month + 1, 0).getDate();

	// Update the '30' label to the actual last day
	document.getElementById('month-last-day').textContent = daysInMonth;

	for (let i = 0; i < daysInMonth; i++) {
		const val = Math.floor(Math.random() * 80) + 20; // Dummy data generation
		const bar = document.createElement('div');
		bar.className = 'bar';
		bar.style.height = `${val}%`;
		bar.setAttribute('data-value', val);
		monthContainer.appendChild(bar);
	}
</script>
