<?php
namespace Politeia\ReadingPlanner;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validates and settles Habit sessions.
 * 
 * Enforces v1.0 Rules:
 * - Freezes past sessions as 'accomplished' or 'missed'.
 * - Cross-Book Accomplishment: Any reading counts.
 * - Strict Failure: Plan fails immediately after 2 missed sessions.
 * - Negative Settlement: Days with insufficient reading become 'missed'.
 */
class HabitSettlementEngine
{
    /**
     * Settle past sessions for a habit plan.
     *
     * @param int $plan_id The Plan ID.
     * @param int $user_id The User ID.
     * @return void
     */
    public static function settle(int $plan_id, int $user_id): void
    {
        global $wpdb;
        $plans_tbl = $wpdb->prefix . 'politeia_plans';
        $habit_tbl = $wpdb->prefix . 'politeia_plan_habit';
        $sessions_tbl = $wpdb->prefix . 'politeia_planned_sessions';
        $reading_tbl = $wpdb->prefix . 'politeia_reading_sessions';

        // 1. Fetch Plan & Habit Config
        $plan = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT p.user_id, p.status, 
                        h.start_page_amount, h.finish_page_amount, h.duration_days
                 FROM {$plans_tbl} p
                 INNER JOIN {$habit_tbl} h ON h.plan_id = p.id
                 WHERE p.id = %d",
                $plan_id
            )
        );

        if (!$plan || (int) $plan->user_id !== $user_id) {
            return;
        }

        // Only settle if active or accepted
        if (!in_array($plan->status, array('active', 'accepted'), true)) {
            return;
        }

        // 2. Determine Timezone & Today
        $user_tz_str = get_user_meta($user_id, 'timezone', true);
        if (!$user_tz_str) {
            $timezone = wp_timezone();
        } else {
            try {
                $timezone = new \DateTimeZone($user_tz_str);
            } catch (\Exception $e) {
                $timezone = wp_timezone();
            }
        }
        $now_user = new \DateTimeImmutable('now', $timezone);
        $today_str = $now_user->format('Y-m-d');

        // 3. Fetch Stale Sessions (Planned < Today)
        $stale_sessions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, planned_start_datetime 
                 FROM {$sessions_tbl}
                 WHERE plan_id = %d 
                   AND status = 'planned'
                   AND DATE(planned_start_datetime) < %s
                 ORDER BY planned_start_datetime ASC",
                $plan_id,
                $today_str
            )
        );

        if (empty($stale_sessions)) {
            // Even if no stale sessions, we must check failure rule (in case it wasn't triggered before)
            self::check_failure_condition($plan_id);
            return;
        }

        // 4. Determine Plan Start Date (for Curve Calculation)
        // We use the very first session of the plan to anchor the curve
        $first_session_date = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT DATE(planned_start_datetime) 
                 FROM {$sessions_tbl} 
                 WHERE plan_id = %d 
                 ORDER BY planned_start_datetime ASC 
                 LIMIT 1",
                $plan_id
            )
        );

        if (!$first_session_date) {
            return;
        }

        $start_ts = strtotime($first_session_date);
        $duration = (int) $plan->duration_days;
        $start_pages = (int) $plan->start_page_amount;
        $end_pages = (int) $plan->finish_page_amount;

        // 5. Process Each Stale Session
        foreach ($stale_sessions as $session) {
            $date_str = substr($session->planned_start_datetime, 0, 10);
            $date_ts = strtotime($date_str);

            // A. Calculate Target (Linear Interpolation)
            // day_offset is 0-indexed relative to start
            $day_offset = floor(($date_ts - $start_ts) / DAY_IN_SECONDS);
            if ($day_offset < 0)
                $day_offset = 0; // Should not happen if sorted

            // Formula: round(start + (end - start) * (i / (duration - 1)))
            $progress = 0;
            if ($duration > 1) {
                $progress = min(1.0, $day_offset / ($duration - 1));
            }
            $target_pages = (int) round($start_pages + (($end_pages - $start_pages) * $progress));
            // Cap at end_pages if beyond duration (though usually plan ends at duration)
            if ($day_offset >= $duration) {
                $target_pages = $end_pages;
            }

            // B. Calculate Actual (Any Book)
            $day_start = date_create($date_str . ' 00:00:00', $timezone);
            $day_end = date_create($date_str . ' 23:59:59', $timezone);

            $pages_read_today = 0;
            if ($day_start && $day_end) {
                $gmt_start = $day_start->setTimezone(new \DateTimeZone('GMT'))->format('Y-m-d H:i:s');
                $gmt_end = $day_end->setTimezone(new \DateTimeZone('GMT'))->format('Y-m-d H:i:s');

                $logs = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT start_page, end_page 
                         FROM {$reading_tbl}
                         WHERE user_id = %d
                           AND deleted_at IS NULL
                           AND start_time BETWEEN %s AND %s",
                        $user_id,
                        $gmt_start,
                        $gmt_end
                    )
                );

                foreach ($logs as $log) {
                    if ($log->end_page >= $log->start_page) {
                        $pages_read_today += ($log->end_page - $log->start_page + 1);
                    }
                }
            }

            // C. Settle Status
            $new_status = ($pages_read_today >= $target_pages) ? 'accomplished' : 'missed';

            $wpdb->update(
                $sessions_tbl,
                array('status' => $new_status),
                array('id' => $session->id),
                array('%s'),
                array('%d')
            );

            // Invalidate cache since we changed status
            if (class_exists('\\Politeia\\ReadingPlanner\\PlanSessionDeriver')) {
                \Politeia\ReadingPlanner\PlanSessionDeriver::invalidate_plan_cache($plan_id);
            }
        }

        // 6. Enforce Strict Failure (2+ Missed)
        self::check_failure_condition($plan_id);
    }

    /**
     * Check if plan has failed due to excessive missed sessions.
     */
    private static function check_failure_condition(int $plan_id): void
    {
        global $wpdb;
        $sessions_tbl = $wpdb->prefix . 'politeia_planned_sessions';
        $plans_tbl = $wpdb->prefix . 'politeia_plans';

        $missed_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$sessions_tbl} WHERE plan_id = %d AND status = 'missed'",
                $plan_id
            )
        );

        if ($missed_count >= 2) {
            $wpdb->update(
                $plans_tbl,
                array('status' => 'failed'),
                array('id' => $plan_id),
                array('%s'),
                array('%d')
            );

            // Invalidate cache since we changed status
            if (class_exists('\\Politeia\\ReadingPlanner\\PlanSessionDeriver')) {
                \Politeia\ReadingPlanner\PlanSessionDeriver::invalidate_plan_cache($plan_id);
            }
        }
    }
}
