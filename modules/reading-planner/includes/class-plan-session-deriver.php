<?php
namespace Politeia\ReadingPlanner;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pure derivation engine for session projections.
 *
 * This class contains ONLY pure functions:
 * - No database reads or writes
 * - No side effects
 * - Deterministic: same input → same output
 *
 * @since 1.5.0
 * @see INVARIANTS.php for design principles
 */
class PlanSessionDeriver
{
    /**
     * Derive session projections from plan state.
     *
     * PURE FUNCTION: No DB writes, no side effects.
     * Same input produces same output.
     *
     * @param int    $total_pages          Total pages in the plan target.
     * @param int    $starting_page        Page number where reading starts.
     * @param int    $pages_read           Pages already read within plan range.
     * @param array  $future_session_dates Array of date strings (Y-m-d) for future sessions.
     * @param string $today_key            Today's date string (Y-m-d) for reference.
     *
     * @return array Array of derived session projections, each containing:
     *               - date: string (Y-m-d)
     *               - order: int (1-indexed position)
     *               - start_page: int
     *               - end_page: int
     *               - pages: int (number of pages for this session)
     */
    public static function derive_sessions(
        int $total_pages,
        int $starting_page,
        int $pages_read,
        array $future_session_dates,
        string $today_key
    ): array {
        // Filter and sort future dates
        $valid_dates = array_filter($future_session_dates, function ($date) use ($today_key) {
            return is_string($date) && strlen($date) === 10 && $date >= $today_key;
        });
        $valid_dates = array_values($valid_dates);
        sort($valid_dates);

        $remaining_count = count($valid_dates);
        if ($remaining_count === 0) {
            return array();
        }

        // Calculate remaining pages
        $remaining_pages = max(0, $total_pages - $pages_read);

        if ($remaining_pages === 0) {
            // All pages read, return empty projections
            return array_map(function ($date, $index) {
                return array(
                    'date' => $date,
                    'order' => $index + 1,
                    'start_page' => 0,
                    'end_page' => 0,
                    'pages' => 0,
                );
            }, $valid_dates, array_keys($valid_dates));
        }

        // Distribute pages evenly
        $base_pages = (int) floor($remaining_pages / $remaining_count);
        $extra_pages = $remaining_pages % $remaining_count;

        $projections = array();
        $cursor = $starting_page + $pages_read;

        foreach ($valid_dates as $index => $date) {
            // First $extra_pages sessions get one extra page
            $session_pages = $base_pages + ($index < $extra_pages ? 1 : 0);

            $start_page = $session_pages > 0 ? $cursor : 0;
            $end_page = $session_pages > 0 ? $cursor + $session_pages - 1 : 0;

            if ($session_pages > 0) {
                $cursor = $end_page + 1;
            }

            $projections[] = array(
                'date' => $date,
                'order' => $index + 1,
                'start_page' => $start_page,
                'end_page' => $end_page,
                'pages' => $session_pages,
            );
        }

        return $projections;
    }

    /**
     * Calculate pages already read within plan range.
     *
     * PURE FUNCTION: No side effects.
     *
     * @param int $highest_page_read The highest page number read by the user.
     * @param int $starting_page     The page where the plan starts.
     *
     * @return int Pages read within the plan range (0 if not started).
     */
    public static function calculate_pages_read(int $highest_page_read, int $starting_page): int
    {
        if ($highest_page_read < $starting_page) {
            return 0;
        }
        return $highest_page_read - $starting_page + 1;
    }

    /**
     * Calculate progress percentage.
     *
     * PURE FUNCTION: No side effects.
     *
     * @param int $pages_read   Pages already read within plan.
     * @param int $target_pages Total pages to read in the plan.
     *
     * @return int Progress percentage (0-100).
     */
    public static function calculate_progress(int $pages_read, int $target_pages): int
    {
        if ($target_pages <= 0) {
            return 0;
        }
        return min(100, (int) floor(($pages_read / $target_pages) * 100));
    }

    /**
     * Merge accomplished and missed sessions with future sessions.
     *
     * PURE FUNCTION: Combines session states for display.
     *
     * @param array $accomplished_dates Array of date strings that are accomplished.
     * @param array $missed_dates       Array of date strings that are missed.
     * @param array $derived_sessions   Array from derive_sessions().
     *
     * @return array Combined session array with status flags.
     */
    public static function merge_session_states(
        array $accomplished_dates,
        array $missed_dates,
        array $derived_sessions
    ): array {
        $accomplished_set = array_flip($accomplished_dates);
        $missed_set = array_flip($missed_dates);

        return array_map(function ($session) use ($accomplished_set, $missed_set) {
            $date = $session['date'];
            if (isset($accomplished_set[$date])) {
                $session['status'] = 'accomplished';
            } elseif (isset($missed_set[$date])) {
                $session['status'] = 'missed';
            } else {
                $session['status'] = 'planned';
            }
            return $session;
        }, $derived_sessions);
    }

    /**
     * Derive session projections for a habit plan (Intensity Curve).
     *
     * PURE FUNCTION: No DB writes, no side effects.
     * Linearly interpolates daily intensity from start to end over duration.
     *
     * @param int    $start_pages   Starting daily intensity.
     * @param int    $end_pages     Target daily intensity (at end of duration).
     * @param int    $duration_days Total duration of the habit challenge.
     * @param string $start_date    Plan start date (Y-m-d).
     * @param array  $future_dates  Array of date strings (Y-m-d) for future sessions.
     *
     * @return array Array of derived session projections.
     */
    public static function derive_habit_sessions(
        int $start_pages,
        int $end_pages,
        int $duration_days,
        string $start_date,
        array $future_dates
    ): array {
        $projections = array();
        $start_ts = strtotime($start_date);

        if (!$start_ts || $duration_days <= 0) {
            return array();
        }

        sort($future_dates);

        foreach ($future_dates as $index => $date) {
            $date_ts = strtotime($date);
            if (!$date_ts || $date_ts < $start_ts) {
                continue;
            }

            // Calculate day offset (1-based)
            $day_offset = floor(($date_ts - $start_ts) / DAY_IN_SECONDS) + 1;

            // Calculate progress (0 to 1)
            $progress = min(1.0, max(0.0, ($day_offset - 1) / max(1, $duration_days - 1)));

            // Linear interpolation
            $intensity = (int) round($start_pages + (($end_pages - $start_pages) * $progress));

            // Clamp to end_pages if passed duration
            if ($day_offset > $duration_days) {
                $intensity = $end_pages;
            }

            $projections[] = array(
                'date' => $date,
                'order' => $index + 1, // Order relative to future list
                'start_page' => 0,     // Not applicable for generic habits
                'end_page' => 0,       // Not applicable for generic habits
                'pages' => $intensity,
            );
        }

        return $projections;
    }

    /**
     * Calculate habit progress based on time duration.
     *
     * PURE FUNCTION: No side effects.
     *
     * @param string $start_date    Plan start date (Y-m-d).
     * @param int    $duration_days Total duration in days.
     * @param string $today_key     Current date (Y-m-d).
     *
     * @return int Progress percentage (0-100).
     */
    public static function calculate_habit_progress(string $start_date, int $duration_days, string $today_key): int
    {
        if ($duration_days <= 0) {
            return 100;
        }

        $start_ts = strtotime($start_date);
        $today_ts = strtotime($today_key);

        if (!$start_ts || !$today_ts || $today_ts < $start_ts) {
            return 0;
        }

        $days_elapsed = floor(($today_ts - $start_ts) / DAY_IN_SECONDS);

        // Progress is days completed (elapsed) / duration
        return min(100, (int) floor(($days_elapsed / $duration_days) * 100));
    }
    /**
     * Invalidate derived plan cache.
     *
     * @param int $plan_id Plan ID.
     */
    public static function invalidate_plan_cache(int $plan_id): void
    {
        delete_transient('prs_plan_view_v3_' . $plan_id);
    }
}
