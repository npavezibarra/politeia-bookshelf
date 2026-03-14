<?php
if (!defined('ABSPATH')) {
	exit;
}

get_header();

if (!is_user_logged_in()) {
	echo '<div class="wrap"><p>' . esc_html__('You must be logged in.', 'politeia-reading') . '</p></div>';
	get_footer();
	exit;
}

$current_user_id = get_current_user_id();
global $wpdb;

$plans_table = $wpdb->prefix . 'politeia_plans';
$plan_finish_book_table = $wpdb->prefix . 'politeia_plan_finish_book';
$plan_habit_table = $wpdb->prefix . 'politeia_plan_habit';
$plan_participants_table = $wpdb->prefix . 'politeia_plan_participants';
$plan_subjects_table = $wpdb->prefix . 'politeia_plan_subjects';
$planned_sessions_table = $wpdb->prefix . 'politeia_planned_sessions';
$planned_session_events_table = $wpdb->prefix . 'politeia_planned_session_events';
$books_table = $wpdb->prefix . 'politeia_books';
$user_books_table = $wpdb->prefix . 'politeia_user_books';
$reading_sessions_table = $wpdb->prefix . 'politeia_reading_sessions';

$required_tables = array(
	$plans_table,
	$plan_finish_book_table,
	$plan_habit_table,
	$plan_participants_table,
	$plan_subjects_table,
	$planned_sessions_table,
	$planned_session_events_table,
);

$missing_tables = array();
foreach ($required_tables as $required_table) {
	$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $required_table));
	if ($table_exists !== $required_table) {
		$missing_tables[] = $required_table;
	}
}

$normalized_plans = array();
$stats = array(
	'total' => 0,
	'active' => 0,
	'completed' => 0,
	'failed' => 0,
);

if (empty($missing_tables)) {
	$finish_has_user_book_id = (bool) $wpdb->get_var("SHOW COLUMNS FROM {$plan_finish_book_table} LIKE 'user_book_id'");
	$finish_has_book_id = (bool) $wpdb->get_var("SHOW COLUMNS FROM {$plan_finish_book_table} LIKE 'book_id'");
	$finish_has_start_page = (bool) $wpdb->get_var("SHOW COLUMNS FROM {$plan_finish_book_table} LIKE 'start_page'");
	$finish_has_starting_page = (bool) $wpdb->get_var("SHOW COLUMNS FROM {$plan_finish_book_table} LIKE 'starting_page'");
	$participants_has_revoked_at = (bool) $wpdb->get_var("SHOW COLUMNS FROM {$plan_participants_table} LIKE 'revoked_at'");

	$finish_user_book_id_expr = $finish_has_user_book_id ? 'pfb.user_book_id' : 'ub_by_book.id';
	$finish_book_id_expr = $finish_has_user_book_id ? 'ub.book_id' : ($finish_has_book_id ? 'pfb.book_id' : 'NULL');
	$finish_book_pages_expr = $finish_has_user_book_id ? 'ub.pages' : 'ub_by_book.pages';
	$finish_start_page_expr = $finish_has_start_page ? 'pfb.start_page' : ($finish_has_starting_page ? 'pfb.starting_page' : '1');
	$participants_active_clause = $participants_has_revoked_at ? 'AND pp_observer.revoked_at IS NULL' : '';

	$join_user_books_from_user_book_id = $finish_has_user_book_id
		? "LEFT JOIN {$user_books_table} ub ON ub.id = pfb.user_book_id"
		: "LEFT JOIN {$user_books_table} ub ON 1 = 0";
	$join_user_books_from_book_id = $finish_has_book_id
		? "LEFT JOIN {$user_books_table} ub_by_book ON ub_by_book.user_id = p.user_id AND ub_by_book.book_id = pfb.book_id AND ub_by_book.deleted_at IS NULL"
		: "LEFT JOIN {$user_books_table} ub_by_book ON 1 = 0";

	$plans = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT
				p.id,
				p.user_id,
				p.name,
				p.plan_type,
				p.status AS plan_status_raw,
				p.created_at,
				CASE
					WHEN p.user_id = %d THEN 'owner'
					ELSE 'observer'
				END AS relationship_type,
				ph.plan_id AS habit_plan_id,
				ph.duration_days,
				pfb.plan_id AS finish_book_plan_id,
				{$finish_user_book_id_expr} AS user_book_id,
				{$finish_start_page_expr} AS start_page,
				{$finish_book_id_expr} AS book_id,
				{$finish_book_pages_expr} AS user_book_pages,
				b.title AS finish_book_title,
				sb.title AS subject_title,
				ps_stats.due_date,
				ps_stats.sessions_total,
				ps_stats.sessions_accomplished,
				ps_stats.sessions_partial,
				ps_stats.sessions_missed,
				pse_stats.event_count,
				pse_stats.last_event_at,
				rs.max_end_page
			FROM {$plans_table} p
			LEFT JOIN {$plan_participants_table} pp_observer
				ON pp_observer.plan_id = p.id
				AND pp_observer.user_id = %d
				AND pp_observer.role = 'observer'
				{$participants_active_clause}
			LEFT JOIN {$plan_habit_table} ph
				ON ph.plan_id = p.id
			LEFT JOIN {$plan_finish_book_table} pfb
				ON pfb.plan_id = p.id
			{$join_user_books_from_user_book_id}
			{$join_user_books_from_book_id}
			LEFT JOIN {$books_table} b
				ON b.id = {$finish_book_id_expr}
			LEFT JOIN (
				SELECT plan_id, MIN(subject_id) AS subject_id
				FROM {$plan_subjects_table}
				GROUP BY plan_id
			) psub
				ON psub.plan_id = p.id
			LEFT JOIN {$books_table} sb
				ON sb.id = psub.subject_id
			LEFT JOIN (
				SELECT
					plan_id,
					MAX(planned_start_datetime) AS due_date,
					COUNT(*) AS sessions_total,
					SUM(CASE WHEN status = 'accomplished' THEN 1 ELSE 0 END) AS sessions_accomplished,
					SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) AS sessions_partial,
					SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) AS sessions_missed
				FROM {$planned_sessions_table}
				GROUP BY plan_id
			) ps_stats
				ON ps_stats.plan_id = p.id
			LEFT JOIN (
				SELECT
					plan_id,
					COUNT(*) AS event_count,
					MAX(created_at) AS last_event_at
				FROM {$planned_session_events_table}
				GROUP BY plan_id
			) pse_stats
				ON pse_stats.plan_id = p.id
			LEFT JOIN (
				SELECT
					user_id,
					user_book_id,
					MAX(end_page) AS max_end_page
				FROM {$reading_sessions_table}
				WHERE deleted_at IS NULL
				GROUP BY user_id, user_book_id
			) rs
				ON rs.user_id = p.user_id
				AND rs.user_book_id = {$finish_user_book_id_expr}
			WHERE (p.user_id = %d OR pp_observer.user_id = %d)
			ORDER BY p.created_at DESC, p.id DESC",
			$current_user_id,
			$current_user_id,
			$current_user_id,
			$current_user_id
		)
	);

	$today = current_time('Y-m-d');
	$site_timezone = wp_timezone();

	foreach ($plans as $plan_row) {
		$plan_id = (int) $plan_row->id;
		$plan_title = isset($plan_row->name) && '' !== trim((string) $plan_row->name)
			? (string) $plan_row->name
			: sprintf(__('Plan #%d', 'politeia-reading'), $plan_id);

		$raw_type = strtolower((string) $plan_row->plan_type);
		$is_habit = !empty($plan_row->habit_plan_id) || in_array($raw_type, array('habit', 'form_habit'), true);
		$is_finish_book = !empty($plan_row->finish_book_plan_id) || in_array($raw_type, array('finish_book', 'complete_books', 'complete_book'), true);

		$category = $is_habit ? 'Habit' : 'Finish Book';
		$type = $is_habit ? 'habit' : 'finish_book';

		$due_date = '';
		$due_date_display = '—';
		if (!empty($plan_row->due_date)) {
			$due_dt = date_create_immutable((string) $plan_row->due_date, $site_timezone);
			if ($due_dt) {
				$due_date = $due_dt->format('Y-m-d');
				$due_date_display = wp_date('M d, Y', $due_dt->getTimestamp(), $site_timezone);
			}
		}

		$sessions_total = (int) $plan_row->sessions_total;
		$sessions_accomplished = (int) $plan_row->sessions_accomplished;
		$sessions_partial = (int) $plan_row->sessions_partial;
		$sessions_missed = (int) $plan_row->sessions_missed;

		$progress_percent = 0;
		if ($is_habit) {
			if ($sessions_total > 0) {
				$progress_percent = (int) round(($sessions_accomplished / max(1, $sessions_total)) * 100);
			} elseif ((int) $plan_row->duration_days > 0 && !empty($plan_row->created_at)) {
				$created = date_create_immutable((string) $plan_row->created_at, $site_timezone);
				if ($created) {
					$elapsed_days = max(0, (int) floor((current_time('timestamp') - $created->getTimestamp()) / DAY_IN_SECONDS));
					$progress_percent = (int) round(($elapsed_days / max(1, (int) $plan_row->duration_days)) * 100);
				}
			}
		} elseif ($is_finish_book && !empty($plan_row->user_book_pages)) {
			$start_page = max(1, (int) $plan_row->start_page);
			$book_pages = max(0, (int) $plan_row->user_book_pages);
			$target_pages = max(1, ($book_pages - $start_page + 1));
			$max_end_page = max(0, (int) $plan_row->max_end_page);
			$read_pages = max(0, min($target_pages, ($max_end_page - $start_page + 1)));
			$progress_percent = (int) round(($read_pages / $target_pages) * 100);
		} elseif ($sessions_total > 0) {
			$weighted_done = $sessions_accomplished + (0.5 * $sessions_partial);
			$progress_percent = (int) round(($weighted_done / max(1, $sessions_total)) * 100);
		}

		$progress_percent = max(0, min(100, $progress_percent));

		$raw_status = strtolower(trim((string) $plan_row->plan_status_raw));
		$is_explicit_failed = in_array($raw_status, array('failed', 'desisted', 'abandoned', 'cancelled'), true);
		$is_completed = $progress_percent >= 100;
		$is_failed_due_to_misses = $is_habit && $sessions_missed >= 2;
		$is_failed_due_to_deadline = !$is_completed && !empty($due_date) && $due_date < $today;

		if ($is_explicit_failed || $is_failed_due_to_misses || $is_failed_due_to_deadline) {
			$status = 'failed';
			$status_label = 'Failed';
		} elseif ($is_completed) {
			$status = 'completed';
			$status_label = 'Completed';
		} else {
			$status = 'active';
			$status_label = 'Active';
		}

		$subtitle = '';
		$relationship_type = isset($plan_row->relationship_type) ? (string) $plan_row->relationship_type : 'owner';
		if ($is_habit) {
			$subtitle = 'Habit plan · Daily reading consistency goal';
		} elseif (!empty($plan_row->finish_book_title)) {
			$subtitle = sprintf('Book-specific plan · %s', (string) $plan_row->finish_book_title);
		} elseif (!empty($plan_row->subject_title)) {
			$subtitle = sprintf('Book-specific plan · %s', (string) $plan_row->subject_title);
		} else {
			$subtitle = 'Book-specific plan · Reading progress target';
		}
		$normalized_plans[] = array(
			'id' => $plan_id,
			'title' => $plan_title,
			'type' => $type,
			'due_date' => $due_date,
			'due_date_display' => $due_date_display,
			'progress_percent' => $progress_percent,
			'status' => $status,
			'status_label' => $status_label,
			'subtitle' => $subtitle,
			'category' => $category,
			'icon' => $is_habit ? 'HB' : 'FB',
			'relationship_type' => $relationship_type,
			'can_edit' => ('owner' === $relationship_type),
			'url' => home_url('/my-plan/' . $plan_id),
		);

		$stats['total']++;
		if (isset($stats[$status])) {
			$stats[$status]++;
		}
	}
}
?>

<style>
:root {
  --black:#000000;
  --deep-gray:#333333;
  --light-gray:#A8A8A8;
  --subtle-gray:#F5F5F5;
  --offwhite:#FEFEFF;

  --gold-grad: linear-gradient(135deg,#8A6B1E,#C79F32,#E9D18A);
  --silver-grad: linear-gradient(135deg,#7E7E7E,#BFC0C0,#E5E5E5);
  --copper-grad: linear-gradient(135deg,#8A4B2A,#C97A45,#E6B18B);

  --border:#E6E6E6;
  --radius:6px;
}

*{box-sizing:border-box}

body{
  margin:0;
  font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
  background:var(--offwhite);
  color:var(--deep-gray);
}

.page{
  max-width:1180px;
  margin:0 auto;
  padding:32px 20px 60px;
}

.topbar{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:16px;
  margin-bottom:28px;
}

.title-block h1{
  margin:0;
  font-size:2rem;
  letter-spacing:-0.03em;
  color:var(--black);
}

.title-block p{
  margin:8px 0 0;
  color:var(--light-gray);
  max-width:700px;
  font-size:.95rem;
}

.primary-btn{
  border:0;
  background:var(--gold-grad);
  color:#000;
  border-radius:var(--radius);
  padding:12px 18px;
  font-size:.9rem;
  font-weight:700;
  cursor:pointer;
}

.overview-grid{
  display:grid;
  grid-template-columns:repeat(4,1fr);
  gap:14px;
  margin-bottom:22px;
}

.stat-card{
  background:white;
  border:1px solid var(--border);
  border-radius:var(--radius);
  padding:16px;
}

.stat-label{
  font-size:.78rem;
  color:var(--light-gray);
  margin-bottom:8px;
}

.stat-value{
  font-size:1.6rem;
  font-weight:700;
  margin-bottom:6px;
}

.stat-note{
  font-size:.85rem;
  color:var(--light-gray);
}

.panel{
  background:white;
  border:1px solid var(--border);
  border-radius:var(--radius);
  overflow:hidden;
}

.panel-header{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:16px;
  padding:16px 20px;
  border-bottom:1px solid var(--border);
  background:var(--subtle-gray);
}

.panel-header h2{
  margin:0;
  font-size:1rem;
  color:var(--black);
}

.filters{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
}

.filter,.search{
  border:1px solid var(--border);
  background:white;
  border-radius:var(--radius);
  padding:8px 10px;
  font-size:.9rem;
}

.search{min-width:220px}

.plans-list{display:grid}

.plan-row{
  display:grid;
  grid-template-columns:2.2fr 1.2fr 1fr 1fr 1fr 40px;
  gap:16px;
  align-items:center;
  padding:16px 20px;
  border-bottom:1px solid var(--border);
  text-decoration:none;
  color:inherit;
  background:white;
}

.plan-row:hover{
  background:var(--subtle-gray);
}

.plan-row:last-child{border-bottom:0}

.plan-main{
  display:flex;
  gap:12px;
}

.plan-icon{
  width:40px;
  height:40px;
  display:grid;
  place-items:center;
  border-radius:var(--radius);
  background:var(--gold-grad);
  color:#000;
  font-weight:700;
  font-size:.9rem;
}

.plan-title{
  margin:0 0 4px;
  font-size:.95rem;
  font-weight:700;
}

.plan-subtitle{
  margin:0;
  color:var(--light-gray);
  font-size:.85rem;
}

.meta-label{
  display:block;
  font-size:.75rem;
  color:var(--light-gray);
  margin-bottom:4px;
}

.meta-value{
  font-size:.9rem;
  font-weight:600;
}

.badge{
  padding:6px 10px;
  border-radius:var(--radius);
  font-size:.8rem;
  font-weight:700;
}

.badge.active{background:#000;color:#C79F32}
.badge.completed{background:var(--gold-grad);color:#000}
.badge.failed{background:var(--copper-grad);color:#000}

.progress-row{
  display:flex;
  justify-content:space-between;
  font-size:.8rem;
  color:var(--light-gray);
  margin-bottom:6px;
}

.progress-bar{
  width:100%;
  height:6px;
  background:#EEE;
  border-radius:var(--radius);
  overflow:hidden;
}

.progress-fill{height:100%;background:var(--gold-grad)}
.progress-fill.completed{background:var(--gold-grad)}
.progress-fill.failed{background:var(--copper-grad)}

.arrow{
  font-size:1.2rem;
  color:var(--light-gray);
}

.legend{
  display:flex;
  gap:12px;
  padding:14px 20px;
  border-top:1px solid var(--border);
  background:var(--subtle-gray);
}

.legend-item{
  display:flex;
  align-items:center;
  gap:6px;
  font-size:.8rem;
  color:var(--light-gray);
}

.legend-dot{
  width:10px;
  height:10px;
  border-radius:50%;
}

.mobile-note{
  margin-top:16px;
  color:var(--light-gray);
  font-size:.85rem;
}

@media(max-width:1024px){
  .overview-grid{grid-template-columns:repeat(2,1fr)}
}

@media(max-width:760px){
  .page{padding:20px 14px 40px}

  .topbar,.panel-header{
    flex-direction:column;
    align-items:stretch;
  }

  .overview-grid{grid-template-columns:1fr}

  .filters{flex-direction:column}

  .search{width:100%;min-width:0}

  .plan-row{
    grid-template-columns:1fr;
    gap:12px;
  }

  .arrow{display:none}

  .plan-main{
    padding-bottom:6px;
    border-bottom:1px solid var(--border);
  }

  .meta-block{
    display:grid;
    grid-template-columns:110px 1fr;
    gap:6px;
  }
}
</style>

<main class="page">
	<section class="topbar">
		<div class="title-block">
			<h1><?php esc_html_e('My Plans', 'politeia-reading'); ?></h1>
			<p><?php esc_html_e('A full-page list view for all user plans. This mockup shows book-specific plans and general reading goals in a single unified index.', 'politeia-reading'); ?>
			</p>
		</div>
		<a class="primary-btn" href="<?php echo esc_url(home_url('/my-plans/create')); ?>">+ <?php esc_html_e('Create New Plan', 'politeia-reading'); ?></a>
	</section>

	<section class="overview-grid">
		<article class="stat-card">
			<div class="stat-label"><?php esc_html_e('Total Plans', 'politeia-reading'); ?></div>
			<div class="stat-value"><?php echo esc_html((string) $stats['total']); ?></div>
			<div class="stat-note"><?php esc_html_e('Across books and general reading goals', 'politeia-reading'); ?></div>
		</article>
		<article class="stat-card">
			<div class="stat-label"><?php esc_html_e('Active', 'politeia-reading'); ?></div>
			<div class="stat-value"><?php echo esc_html((string) $stats['active']); ?></div>
			<div class="stat-note"><?php esc_html_e('Currently in progress', 'politeia-reading'); ?></div>
		</article>
		<article class="stat-card">
			<div class="stat-label"><?php esc_html_e('Completed', 'politeia-reading'); ?></div>
			<div class="stat-value"><?php echo esc_html((string) $stats['completed']); ?></div>
			<div class="stat-note"><?php esc_html_e('Successfully finished', 'politeia-reading'); ?></div>
		</article>
		<article class="stat-card">
			<div class="stat-label"><?php esc_html_e('Failed', 'politeia-reading'); ?></div>
			<div class="stat-value"><?php echo esc_html((string) $stats['failed']); ?></div>
			<div class="stat-note"><?php esc_html_e('Needs clear frontend treatment', 'politeia-reading'); ?></div>
		</article>
	</section>

	<section class="panel">
		<header class="panel-header">
			<h2><?php esc_html_e('Plans List', 'politeia-reading'); ?></h2>
			<div class="filters">
				<input class="search" type="text" value="<?php echo esc_attr__('Search by title, book, or goal type', 'politeia-reading'); ?>" readonly />
				<select class="filter" disabled>
					<option><?php esc_html_e('All Statuses', 'politeia-reading'); ?></option>
					<option><?php esc_html_e('Active', 'politeia-reading'); ?></option>
					<option><?php esc_html_e('Completed', 'politeia-reading'); ?></option>
					<option><?php esc_html_e('Failed', 'politeia-reading'); ?></option>
				</select>
				<select class="filter" disabled>
					<option><?php esc_html_e('All Types', 'politeia-reading'); ?></option>
					<option><?php esc_html_e('Finish Books', 'politeia-reading'); ?></option>
					<option><?php esc_html_e('Habits', 'politeia-reading'); ?></option>
				</select>
			</div>
		</header>

		<div class="plans-list">
			<?php if (!empty($missing_tables)): ?>
				<div class="plan-row">
					<div class="plan-main">
						<div class="plan-icon">!</div>
						<div class="plan-title-wrap">
							<h3 class="plan-title"><?php esc_html_e('Planning tables are not available', 'politeia-reading'); ?></h3>
							<p class="plan-subtitle"><?php echo esc_html(implode(', ', $missing_tables)); ?></p>
						</div>
					</div>
				</div>
			<?php elseif (empty($normalized_plans)): ?>
				<div class="plan-row">
					<div class="plan-main">
						<div class="plan-icon">∅</div>
						<div class="plan-title-wrap">
							<h3 class="plan-title"><?php esc_html_e('No plans found', 'politeia-reading'); ?></h3>
							<p class="plan-subtitle"><?php esc_html_e('Create your first plan to start tracking progress.', 'politeia-reading'); ?></p>
						</div>
					</div>
				</div>
			<?php else: ?>
				<?php foreach ($normalized_plans as $plan): ?>
					<?php
					$progress_class = 'progress-fill';
					if ('completed' === $plan['status']) {
						$progress_class .= ' completed';
					} elseif ('failed' === $plan['status']) {
						$progress_class .= ' failed';
					}
					?>
					<a href="<?php echo esc_url($plan['url']); ?>" class="plan-row">
						<div class="plan-main">
							<div class="plan-icon"><?php echo esc_html($plan['icon']); ?></div>
							<div class="plan-title-wrap">
								<h3 class="plan-title"><?php echo esc_html($plan['title']); ?></h3>
								<p class="plan-subtitle"><?php echo esc_html($plan['subtitle']); ?></p>
							</div>
						</div>
						<div class="meta-block">
							<span class="meta-label"><?php esc_html_e('Due Date', 'politeia-reading'); ?></span>
							<span class="meta-value"><?php echo esc_html($plan['due_date_display']); ?></span>
						</div>
						<div class="meta-block">
							<span class="meta-label"><?php esc_html_e('Status', 'politeia-reading'); ?></span>
							<span class="badge <?php echo esc_attr($plan['status']); ?>"><?php echo esc_html($plan['status_label']); ?></span>
						</div>
						<div class="progress-wrap">
							<div class="progress-row">
								<span><?php esc_html_e('Progress', 'politeia-reading'); ?></span>
								<strong><?php echo esc_html((string) $plan['progress_percent']); ?>%</strong>
							</div>
							<div class="progress-bar">
								<div class="<?php echo esc_attr($progress_class); ?>" style="width:<?php echo esc_attr((string) $plan['progress_percent']); ?>%"></div>
							</div>
						</div>
						<div class="meta-block">
							<span class="meta-label"><?php esc_html_e('Category', 'politeia-reading'); ?></span>
							<span class="meta-value"><?php echo esc_html($plan['category']); ?></span>
						</div>
						<div class="arrow">›</div>
					</a>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<footer class="legend">
			<div class="legend-item"><span class="legend-dot" style="background:#2f6fed"></span> <?php esc_html_e('Active plan', 'politeia-reading'); ?></div>
			<div class="legend-item"><span class="legend-dot" style="background:#1f9d61"></span> <?php esc_html_e('Completed plan', 'politeia-reading'); ?></div>
			<div class="legend-item"><span class="legend-dot" style="background:#c53030"></span> <?php esc_html_e('Failed plan', 'politeia-reading'); ?></div>
		</footer>
	</section>

	<p class="mobile-note">
		<?php esc_html_e('Mobile behavior: each plan row collapses into a stacked card layout so the page remains readable on smartphone screens.', 'politeia-reading'); ?>
	</p>
</main>

<?php
get_footer();
