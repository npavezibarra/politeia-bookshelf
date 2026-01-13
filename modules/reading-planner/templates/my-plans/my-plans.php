<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$requested_user = (string) get_query_var( 'prs_my_plans_user' );
$current_user   = wp_get_current_user();
$is_owner       = $requested_user
	&& $current_user
	&& $current_user->exists()
	&& $current_user->user_login === $requested_user;
?>

<style>
	:root {
		--prs-black: #000000;
		--prs-deep-gray: #333333;
		--prs-gold: #c79f32;
		--prs-orange: #ff8c00;
		--prs-light-gray: #a8a8a8;
		--prs-subtle-gray: #f5f5f5;
		--prs-off-white: #fefeff;
		--prs-radius: 6px;
	}

	.prs-plans-wrap {
		background: #f0f2f5;
		padding: 24px;
	}

	.prs-plan-grid {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 24px;
	}

	@media (max-width: 960px) {
		.prs-plan-grid {
			grid-template-columns: 1fr;
		}
	}

	.prs-plan-card {
		background: #ffffff;
		border: 1px solid #f1f1f1;
		border-radius: var(--prs-radius);
		box-shadow: 0 24px 40px rgba(0, 0, 0, 0.12);
		overflow: hidden;
	}

	.prs-plan-header {
		padding: 32px 32px 16px;
	}

	.prs-plan-badge {
		display: inline-block;
		padding: 4px 16px;
		background: var(--prs-black);
		color: var(--prs-gold);
		font-size: 10px;
		letter-spacing: 0.2em;
		text-transform: uppercase;
		border-radius: var(--prs-radius);
		font-weight: 700;
	}

	.prs-plan-title {
		margin: 12px 0 0;
		font-size: 28px;
		font-weight: 700;
		color: var(--prs-black);
		line-height: 1.2;
	}

	.prs-plan-subtitle {
		display: block;
		color: #a0a0a0;
		font-size: 18px;
		font-style: italic;
		font-weight: 500;
	}

	.prs-plan-toggle {
		margin: 0 24px 24px;
		width: calc(100% - 48px);
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 20px;
		background: var(--prs-subtle-gray);
		border: 1px solid #e2e2e2;
		border-radius: var(--prs-radius);
		cursor: pointer;
		transition: background 0.2s ease;
	}

	.prs-plan-toggle:hover {
		background: #ededed;
	}

	.prs-plan-toggle-icon {
		width: 48px;
		height: 48px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		background: var(--prs-black);
		color: var(--prs-gold);
		border-radius: var(--prs-radius);
		box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
	}

	.prs-plan-toggle-label {
		text-transform: uppercase;
		letter-spacing: 0.2em;
		font-size: 10px;
		font-weight: 700;
		color: var(--prs-black);
	}

	.prs-plan-toggle-date {
		font-size: 11px;
		font-weight: 700;
		color: var(--prs-gold);
	}

	.prs-chevron {
		width: 24px;
		height: 24px;
		color: #9b9b9b;
		transition: transform 0.3s ease;
	}

	.prs-chevron.is-open {
		transform: rotate(180deg);
	}

	.prs-collapsible {
		max-height: 0;
		opacity: 0;
		overflow: hidden;
		transition: max-height 0.4s ease, opacity 0.3s ease;
		margin: 0 24px;
	}

	.prs-collapsible.is-open {
		max-height: 2000px;
		opacity: 1;
	}

	.prs-calendar-card {
		margin-top: 16px;
		padding: 24px;
		background: var(--prs-subtle-gray);
		border: 1px solid var(--prs-light-gray);
		border-radius: var(--prs-radius);
	}

	.prs-calendar-header {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		margin-bottom: 24px;
	}

	.prs-calendar-title {
		font-size: 18px;
		text-transform: uppercase;
		font-weight: 700;
		color: var(--prs-black);
	}

	.prs-calendar-subtitle {
		font-size: 9px;
		text-transform: uppercase;
		letter-spacing: 0.2em;
		color: #8f8f8f;
		font-weight: 700;
		margin-top: 4px;
	}

	.prs-toggle-group {
		display: flex;
		align-items: center;
		background: #e5e5e5;
		padding: 2px;
		border-radius: var(--prs-radius);
	}

	.prs-toggle-button {
		height: 24px;
		width: 28px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		border-radius: 4px;
		cursor: pointer;
		color: var(--prs-light-gray);
		transition: background 0.2s ease, color 0.2s ease;
	}

	.prs-toggle-button.is-active {
		background: var(--prs-black);
		color: var(--prs-gold);
	}

	.prs-view {
		transition: opacity 0.3s ease;
	}

	.prs-view.is-hidden {
		display: none;
		opacity: 0;
	}

	.prs-weekdays {
		display: grid;
		grid-template-columns: repeat(7, minmax(0, 1fr));
		gap: 4px;
		font-size: 9px;
		font-weight: 700;
		text-transform: uppercase;
		opacity: 0.5;
		margin-bottom: 8px;
		text-align: center;
	}

	.prs-calendar-grid {
		display: grid;
		grid-template-columns: repeat(7, minmax(0, 1fr));
		gap: 6px;
	}

	.prs-day-cell {
		position: relative;
		height: 48px;
		display: flex;
		align-items: center;
		justify-content: center;
		background: var(--prs-off-white);
		border: 1px solid rgba(168, 168, 168, 0.3);
		border-radius: var(--prs-radius);
		transition: all 0.2s ease;
	}

	.prs-day-number {
		position: absolute;
		top: 4px;
		left: 6px;
		font-size: 8px;
		font-weight: 700;
		opacity: 0.3;
	}

	.prs-day-empty {
		background: #e1e1e1;
		opacity: 0.2;
	}

	.prs-day-selected {
		width: 32px;
		height: 32px;
		display: flex;
		align-items: center;
		justify-content: center;
		background: var(--prs-gold);
		color: #ffffff;
		font-weight: 700;
		border-radius: var(--prs-radius);
		box-shadow: 0 4px 6px rgba(0, 0, 0, 0.12);
		cursor: grab;
		user-select: none;
	}

	.prs-day-selected:active {
		cursor: grabbing;
	}

	.prs-day-cell.is-drag-over {
		background: #fffaf0;
		border: 2px dashed var(--prs-orange);
	}

	.prs-list-item {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 12px 16px;
		background: #ffffff;
		border: 1px solid #e2e2e2;
		border-radius: var(--prs-radius);
		box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
		transition: border 0.2s ease;
	}

	.prs-list-item:hover {
		border-color: var(--prs-gold);
	}

	.prs-list-badge {
		width: 28px;
		height: 28px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		background: var(--prs-gold);
		color: #ffffff;
		font-size: 10px;
		font-weight: 700;
		border-radius: var(--prs-radius);
	}

	.prs-list-title {
		font-size: 11px;
		font-weight: 700;
		text-transform: uppercase;
		color: var(--prs-black);
		margin-left: 12px;
	}

	.prs-list-date {
		font-size: 10px;
		font-weight: 700;
		color: #8f8f8f;
		text-transform: uppercase;
	}

	.prs-confirm-button {
		margin-top: 20px;
		width: 100%;
		padding: 16px;
		background: var(--prs-black);
		color: var(--prs-gold);
		border: none;
		border-radius: var(--prs-radius);
		font-weight: 700;
		font-size: 11px;
		letter-spacing: 0.2em;
		text-transform: uppercase;
		cursor: pointer;
		transition: transform 0.15s ease, background 0.2s ease;
	}

	.prs-confirm-button:active {
		transform: scale(0.98);
	}

	.prs-progress {
		background: #f6f6f6;
		padding: 24px 32px 32px;
	}

	.prs-progress-row {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 8px;
	}

	.prs-progress-label {
		font-size: 10px;
		font-weight: 700;
		letter-spacing: 0.2em;
		text-transform: uppercase;
		color: #a0a0a0;
	}

	.prs-progress-value {
		font-size: 14px;
		font-weight: 700;
		color: var(--prs-black);
	}

	.prs-progress-bar {
		height: 6px;
		background: #e5e5e5;
		border-radius: var(--prs-radius);
		overflow: hidden;
	}

	.prs-progress-bar-fill {
		height: 100%;
		background: var(--prs-gold);
		border-radius: var(--prs-radius);
		box-shadow: 0 0 10px rgba(199, 159, 50, 0.5);
	}
</style>

<div class="wrap prs-plans-wrap">
	<?php if ( $is_owner ) : ?>
		<h1><?php esc_html_e( 'My Plans', 'politeia-reading' ); ?></h1>
		<?php
		$cards = array(
			array(
				'badge'        => __( 'Plan: More Pages', 'politeia-reading' ),
				'title'        => __( 'The Iliad by Homer', 'politeia-reading' ),
				'subtitle'     => __( 'in 1 month', 'politeia-reading' ),
				'month_label'  => __( 'October', 'politeia-reading' ),
				'month_year'   => __( 'October 2023', 'politeia-reading' ),
				'days_count'   => 31,
				'start_offset' => 1,
				'selected'     => array( 3, 10, 17, 24 ),
				'progress'     => 12,
			),
			array(
				'badge'        => __( 'Plan: More Pages', 'politeia-reading' ),
				'title'        => __( 'The Odyssey by Homer', 'politeia-reading' ),
				'subtitle'     => __( 'in 2 months', 'politeia-reading' ),
				'month_label'  => __( 'November', 'politeia-reading' ),
				'month_year'   => __( 'November 2023', 'politeia-reading' ),
				'days_count'   => 30,
				'start_offset' => 3,
				'selected'     => array( 5, 12, 19, 26 ),
				'progress'     => 28,
			),
		);
		?>
		<div class="prs-plan-grid">
			<?php foreach ( $cards as $card ) : ?>
				<div
					class="prs-plan-card"
					data-days-count="<?php echo esc_attr( (string) $card['days_count'] ); ?>"
					data-start-offset="<?php echo esc_attr( (string) $card['start_offset'] ); ?>"
					data-selected-days="<?php echo esc_attr( wp_json_encode( $card['selected'] ) ); ?>"
					data-session-label="<?php echo esc_attr__( 'Scheduled Session', 'politeia-reading' ); ?>"
					data-day-format="<?php echo esc_attr__( 'Day %1$s of %2$s', 'politeia-reading' ); ?>"
					data-month-label="<?php echo esc_attr( $card['month_label'] ); ?>"
					data-confirm-text="<?php echo esc_attr__( 'Accept Proposal', 'politeia-reading' ); ?>"
					data-confirmed-text="<?php echo esc_attr__( 'Plan saved!', 'politeia-reading' ); ?>"
				>
					<div class="prs-plan-header">
						<span class="prs-plan-badge"><?php echo esc_html( $card['badge'] ); ?></span>
						<h2 class="prs-plan-title">
							<?php echo esc_html( $card['title'] ); ?><br>
							<span class="prs-plan-subtitle"><?php echo esc_html( $card['subtitle'] ); ?></span>
						</h2>
					</div>

					<button type="button" class="prs-plan-toggle" data-role="toggle">
						<div class="prs-plan-toggle-icon" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
							</svg>
						</div>
						<div>
							<span class="prs-plan-toggle-label"><?php esc_html_e( 'View Calendar', 'politeia-reading' ); ?></span>
							<span class="prs-plan-toggle-date"><?php echo esc_html( $card['month_year'] ); ?></span>
						</div>
						<svg class="prs-chevron" data-role="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7" />
						</svg>
					</button>

					<div class="prs-collapsible" data-role="collapsible">
						<div class="prs-calendar-card">
							<div class="prs-calendar-header">
								<div>
									<h3 class="prs-calendar-title"><?php echo esc_html( $card['month_year'] ); ?></h3>
									<p class="prs-calendar-subtitle"><?php esc_html_e( 'Monthly Planning', 'politeia-reading' ); ?></p>
								</div>
								<div>
									<div class="prs-toggle-group" role="tablist">
										<button type="button" class="prs-toggle-button is-active" data-role="view-cal" aria-label="<?php esc_attr_e( 'Calendar', 'politeia-reading' ); ?>">
											<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
												<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
												<line x1="16" y1="2" x2="16" y2="6"></line>
												<line x1="8" y1="2" x2="8" y2="6"></line>
												<line x1="3" y1="10" x2="21" y2="10"></line>
											</svg>
										</button>
										<button type="button" class="prs-toggle-button" data-role="view-list" aria-label="<?php esc_attr_e( 'List', 'politeia-reading' ); ?>">
											<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
												<line x1="8" y1="6" x2="21" y2="6"></line>
												<line x1="8" y1="12" x2="21" y2="12"></line>
												<line x1="8" y1="18" x2="21" y2="18"></line>
												<line x1="3" y1="6" x2="3.01" y2="6"></line>
												<line x1="3" y1="12" x2="3.01" y2="12"></line>
												<line x1="3" y1="18" x2="3.01" y2="18"></line>
											</svg>
										</button>
									</div>
								</div>
							</div>

							<div class="prs-view" data-role="calendar-view">
								<div class="prs-weekdays">
									<div><?php esc_html_e( 'Sun', 'politeia-reading' ); ?></div>
									<div><?php esc_html_e( 'Mon', 'politeia-reading' ); ?></div>
									<div><?php esc_html_e( 'Tue', 'politeia-reading' ); ?></div>
									<div><?php esc_html_e( 'Wed', 'politeia-reading' ); ?></div>
									<div><?php esc_html_e( 'Thu', 'politeia-reading' ); ?></div>
									<div><?php esc_html_e( 'Fri', 'politeia-reading' ); ?></div>
									<div><?php esc_html_e( 'Sat', 'politeia-reading' ); ?></div>
								</div>
								<div class="prs-calendar-grid" data-role="calendar-grid"></div>
							</div>

							<div class="prs-view is-hidden" data-role="list-view">
								<div data-role="list"></div>
							</div>

							<button type="button" class="prs-confirm-button" data-role="confirm">
								<?php esc_html_e( 'Accept Proposal', 'politeia-reading' ); ?>
							</button>
						</div>
					</div>

					<div class="prs-progress">
						<div class="prs-progress-row">
							<span class="prs-progress-label"><?php esc_html_e( 'Reading Completed', 'politeia-reading' ); ?></span>
							<span class="prs-progress-value"><?php echo esc_html( $card['progress'] ); ?>%</span>
						</div>
						<div class="prs-progress-bar" role="progressbar" aria-valuenow="<?php echo esc_attr( (string) $card['progress'] ); ?>" aria-valuemin="0" aria-valuemax="100">
							<div class="prs-progress-bar-fill" style="width: <?php echo esc_attr( (string) $card['progress'] ); ?>%;"></div>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<p><?php esc_html_e( 'Access denied.', 'politeia-reading' ); ?></p>
	<?php endif; ?>
</div>

<script>
	(function() {
		const cards = document.querySelectorAll('.prs-plan-card');
		if (!cards.length) {
			return;
		}

		cards.forEach((card) => {
			const toggleBtn = card.querySelector('[data-role="toggle"]');
			const collapsible = card.querySelector('[data-role="collapsible"]');
			const chevron = card.querySelector('[data-role="chevron"]');
			const grid = card.querySelector('[data-role="calendar-grid"]');
			const listContainer = card.querySelector('[data-role="list"]');
			const btnViewCal = card.querySelector('[data-role="view-cal"]');
			const btnViewList = card.querySelector('[data-role="view-list"]');
			const viewCal = card.querySelector('[data-role="calendar-view"]');
			const viewList = card.querySelector('[data-role="list-view"]');
			const confirmBtn = card.querySelector('[data-role="confirm"]');

			const daysCount = parseInt(card.dataset.daysCount, 10) || 30;
			const startOffset = parseInt(card.dataset.startOffset, 10) || 0;
			let selectedDays = new Set([]);
			let currentView = 'calendar';

			try {
				selectedDays = new Set(JSON.parse(card.dataset.selectedDays || '[]'));
			} catch (error) {
				selectedDays = new Set([]);
			}

			const strings = {
				sessionLabel: card.dataset.sessionLabel || '',
				dayFormat: card.dataset.dayFormat || '',
				monthLabel: card.dataset.monthLabel || '',
				confirmText: card.dataset.confirmText || '',
				confirmedText: card.dataset.confirmedText || '',
			};

			const formatDayLabel = (day) => {
				return strings.dayFormat
					.replace('%1$s', String(day))
					.replace('%2$s', String(strings.monthLabel));
			};

			const renderCalendar = () => {
				grid.innerHTML = '';
				const sorted = Array.from(selectedDays).sort((a, b) => a - b);

				for (let i = 0; i < startOffset; i++) {
					const empty = document.createElement('div');
					empty.className = 'prs-day-cell prs-day-empty';
					grid.appendChild(empty);
				}

				for (let day = 1; day <= daysCount; day++) {
					const cell = document.createElement('div');
					cell.className = 'prs-day-cell';
					cell.dataset.day = String(day);

					const label = document.createElement('span');
					label.className = 'prs-day-number';
					label.textContent = String(day);
					cell.appendChild(label);

					if (selectedDays.has(day)) {
						const order = sorted.indexOf(day) + 1;
						const mark = document.createElement('div');
						mark.className = 'prs-day-selected';
						mark.setAttribute('draggable', 'true');
						mark.textContent = String(order);
						mark.dataset.day = String(day);

						mark.addEventListener('dragstart', (event) => {
							event.dataTransfer.setData('text/plain', String(day));
							setTimeout(() => mark.classList.add('opacity-0'), 0);
						});

						mark.addEventListener('dragend', () => {
							mark.classList.remove('opacity-0');
							renderCalendar();
						});

						cell.appendChild(mark);
					}

					cell.addEventListener('dragover', (event) => {
						event.preventDefault();
						if (!selectedDays.has(day)) {
							cell.classList.add('is-drag-over');
						}
					});

					cell.addEventListener('dragleave', () => {
						cell.classList.remove('is-drag-over');
					});

					cell.addEventListener('drop', (event) => {
						event.preventDefault();
						cell.classList.remove('is-drag-over');
						const origin = parseInt(event.dataTransfer.getData('text/plain'), 10);
						const target = day;

						if (target && !selectedDays.has(target)) {
							selectedDays.delete(origin);
							selectedDays.add(target);
							renderCalendar();
							if (currentView === 'list') {
								renderList();
							}
						}
					});

					grid.appendChild(cell);
				}
			};

			const renderList = () => {
				listContainer.innerHTML = '';
				const sorted = Array.from(selectedDays).sort((a, b) => a - b);

				sorted.forEach((day, index) => {
					const item = document.createElement('div');
					item.className = 'prs-list-item';

					const left = document.createElement('div');
					left.style.display = 'flex';
					left.style.alignItems = 'center';

					const badge = document.createElement('span');
					badge.className = 'prs-list-badge';
					badge.textContent = String(index + 1);
					left.appendChild(badge);

					const title = document.createElement('span');
					title.className = 'prs-list-title';
					title.textContent = strings.sessionLabel;
					left.appendChild(title);

					const date = document.createElement('span');
					date.className = 'prs-list-date';
					date.textContent = formatDayLabel(day);

					item.appendChild(left);
					item.appendChild(date);
					listContainer.appendChild(item);
				});
			};

			if (toggleBtn && collapsible && chevron) {
				toggleBtn.addEventListener('click', () => {
					collapsible.classList.toggle('is-open');
					chevron.classList.toggle('is-open');
				});
			}

			if (btnViewCal && btnViewList) {
				btnViewCal.addEventListener('click', () => {
					currentView = 'calendar';
					btnViewCal.classList.add('is-active');
					btnViewList.classList.remove('is-active');
					viewCal.classList.remove('is-hidden');
					viewList.classList.add('is-hidden');
					renderCalendar();
				});

				btnViewList.addEventListener('click', () => {
					currentView = 'list';
					btnViewList.classList.add('is-active');
					btnViewCal.classList.remove('is-active');
					viewList.classList.remove('is-hidden');
					viewCal.classList.add('is-hidden');
					renderList();
				});
			}

			if (confirmBtn) {
				const confirmText = strings.confirmText;
				const confirmedText = strings.confirmedText;

				confirmBtn.addEventListener('click', () => {
					confirmBtn.textContent = confirmedText;
					confirmBtn.style.background = '#16a34a';
					setTimeout(() => {
						confirmBtn.textContent = confirmText;
						confirmBtn.style.background = 'var(--prs-black)';
					}, 2000);
				});
			}

			renderCalendar();
		});
	})();
</script>

<?php
get_footer();
