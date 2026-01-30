<?php
/**
 * =============================================================================
 * READING PLANNER — CORE INVARIANTS
 * =============================================================================
 *
 * This document codifies the fundamental design invariants for the Reading
 * Planner session management system. These invariants MUST be preserved
 * across all implementations to ensure data integrity and predictable behavior.
 *
 * @package Politeia\ReadingPlanner
 * @since   1.0.0
 * =============================================================================
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * =============================================================================
 * INVARIANT #1: PAST SESSIONS ARE IMMUTABLE
 * =============================================================================
 *
 * Sessions with status 'accomplished' or 'missed' MUST NOT be modified.
 *
 * Rationale:
 * - Historical accuracy: Past reading activity represents actual user behavior
 * - Data integrity: Progress calculations depend on stable historical data
 * - Audit trail: Users expect their reading history to remain unchanged
 *
 * Enforcement:
 * - Backend handlers MUST reject updates to sessions where status != 'planned'
 * - Frontend UI MUST NOT render edit/delete controls for past sessions
 * - Status transitions are one-way: planned → accomplished OR planned → missed
 *
 * Allowed transitions:
 *   [planned] → [accomplished]  (when reading session recorded on that date)
 *   [planned] → [missed]        (when date passes without reading session)
 *   [accomplished] → (none)     (terminal state)
 *   [missed] → (none)           (terminal state)
 */
define('POLITEIA_INVARIANT_PAST_SESSIONS_IMMUTABLE', true);

/**
 * =============================================================================
 * INVARIANT #2: PAGE RANGES ARE DERIVED, NOT AUTHORITATIVE
 * =============================================================================
 *
 * The `planned_start_page` and `planned_end_page` columns in planned_sessions
 * are hints/initial values, NOT the source of truth for progress tracking.
 *
 * Rationale:
 * - Page ranges are recalculated dynamically based on:
 *   a) Total pages in the book (from plan goal target_value)
 *   b) Pages already read (from actual reading sessions)
 *   c) Number of remaining planned sessions
 * - Storing "authoritative" page ranges would require cascading updates
 *   whenever a session is added, removed, or the user reads more/fewer pages
 *
 * Enforcement:
 * - Progress calculations MUST use actual reading sessions, not planned ranges
 * - Display logic MUST recalculate expected pages at render time
 * - Page ranges in DB serve only as initial estimates from plan creation
 *
 * Calculation formula (see buildDerivedPlan in my-plans.php):
 *   remainingPages = totalPages - pagesActuallyRead
 *   remainingSessions = count of planned sessions with date >= today
 *   pagesPerSession = floor(remainingPages / remainingSessions)
 */
define('POLITEIA_INVARIANT_PAGE_RANGES_DERIVED', true);

/**
 * =============================================================================
 * INVARIANT #3: ONLY INTENTION AND HISTORY ARE PERSISTED
 * =============================================================================
 *
 * The database stores two types of data:
 * 1. INTENTION: What the user plans to do (planned session dates)
 * 2. HISTORY: What the user actually did (reading sessions, status changes)
 *
 * NEVER persist derived/calculated values that can be recomputed.
 *
 * Rationale:
 * - Derived values become stale and create synchronization problems
 * - Single source of truth prevents data inconsistencies
 * - Reduces write operations and database load
 *
 * What IS persisted:
 * - Session dates (intention to read on specific dates)
 * - Session status transitions (history of what happened)
 * - Actual reading sessions (history of reading activity)
 *
 * What IS NOT persisted:
 * - Calculated page ranges for future sessions
 * - Progress percentages
 * - Expected completion dates
 * - Session ordering/numbering
 */
define('POLITEIA_INVARIANT_PERSIST_INTENTION_AND_HISTORY', true);

/**
 * =============================================================================
 * INVARIANT #4: RECALCULATION AT READ TIME, NO MASS DB WRITES
 * =============================================================================
 *
 * When session arrangements change (add/remove/move), recalculate display
 * values at read time. NEVER cascade update page ranges across all sessions.
 *
 * Rationale:
 * - Adding one session should not trigger N database updates
 * - Deleting one session should not trigger N database updates
 * - Moving one session should not trigger N database updates
 * - Read-time calculation guarantees fresh, accurate values
 *
 * Enforcement:
 * - Session CRUD operations update ONLY the affected session record
 * - Display rendering always calls buildDerivedPlan() or equivalent
 * - No triggers or hooks that cascade page range updates
 *
 * Performance consideration:
 * - Recalculation is O(n) where n = sessions in plan (typically < 100)
 * - This is negligible compared to the cost of cascade DB writes
 * - If needed, calculated values can be cached in memory/transients
 */
define('POLITEIA_INVARIANT_RECALCULATE_AT_READ_TIME', true);

/**
 * =============================================================================
 * INVARIANT SUMMARY TABLE
 * =============================================================================
 *
 * | # | Invariant                           | Prevents                    |
 * |---|-------------------------------------|------------------------------|
 * | 1 | Past sessions are immutable         | History corruption           |
 * | 2 | Page ranges are derived             | Stale data, sync issues      |
 * | 3 | Persist only intention + history    | Data redundancy              |
 * | 4 | Recalculate at read time            | Cascade updates, complexity  |
 *
 * =============================================================================
 * IMPLEMENTATION CHECKLIST
 * =============================================================================
 *
 * Before implementing any session-related feature, verify:
 *
 * [ ] Does this modify a past (accomplished/missed) session? → REJECT
 * [ ] Does this persist a calculated page range? → RECONSIDER
 * [ ] Does this require updating multiple session records? → RECONSIDER
 * [ ] Does this store data that can be derived? → RECONSIDER
 *
 * =============================================================================
 */
