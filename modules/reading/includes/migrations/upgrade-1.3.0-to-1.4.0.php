<?php
/**
 * Migration: 1.3.0 to 1.4.0
 *
 * GOAL: Replace canonical book_id references with user_book_id in schema.
 * - Renames columns via ALTER TABLE.
 * - Updates data by mapping canonical Book IDs to User Book IDs.
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$finish_table = $wpdb->prefix . 'politeia_plan_finish_book';
$sessions_table = $wpdb->prefix . 'politeia_reading_sessions';
$plans_table = $wpdb->prefix . 'politeia_plans';
$user_books = $wpdb->prefix . 'politeia_user_books';

// Helper to check if column exists
function politeia_column_exists($table, $column)
{
    global $wpdb;
    return (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s",
        $table,
        $column
    ));
}

// 1. Rename columns (Data remains as canonical IDs temporarily)

// Only rename if 'book_id' exists and 'user_book_id' does not
if (politeia_column_exists($finish_table, 'book_id') && !politeia_column_exists($finish_table, 'user_book_id')) {
    $wpdb->query("
		ALTER TABLE $finish_table
		CHANGE book_id user_book_id BIGINT UNSIGNED NOT NULL
	");
}

if (politeia_column_exists($sessions_table, 'book_id') && !politeia_column_exists($sessions_table, 'user_book_id')) {
    $wpdb->query("
		ALTER TABLE $sessions_table
		CHANGE book_id user_book_id BIGINT UNSIGNED NOT NULL
	");
}

// 2. Migrate historical data from canonical IDs -> user book IDs

// We run updates always, as they are safe (if joins match, they update). 
// But to be cleaner, we can check if migration is needed? 
// Actually, re-running update is fine as long as logic is correct.
// However, once migrated, pfb.user_book_id will be a UserBookID. 
// ub.book_id is CanonicalID. 
// If we run logic again: ub.book_id = pfb.user_book_id (Canonical = UserBookID?) mismatch.
// So joins won't match, 0 rows updated. Safe.

// Plan Finish Book data migration
$wpdb->query("
  UPDATE $finish_table pfb
  JOIN $plans_table p ON p.id = pfb.plan_id
  JOIN $user_books ub
    ON ub.user_id = p.user_id
   AND ub.book_id = pfb.user_book_id
  SET pfb.user_book_id = ub.id
");

// Reading Sessions data migration
$wpdb->query("
  UPDATE $sessions_table rs
  JOIN $user_books ub
    ON ub.user_id = rs.user_id
   AND ub.book_id = rs.user_book_id
  SET rs.user_book_id = ub.id
");
