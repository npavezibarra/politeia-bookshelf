<?php
/**
 * Legacy compatibility loader for the book confirmation admin table.
 *
 * Older builds shipped the Politeia_Book_DB_Handler implementation inside this
 * file which meant that loading both class-book-db-handler.php and this file
 * triggered a fatal "Cannot redeclare class" error. The production site loads
 * both files during bootstrap, so we now only ensure the handler class exists
 * and expose a lightweight alias for any code that still expects the old
 * confirmation-table class name.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// If the handler is already available we can bail immediately.
if ( class_exists( 'Politeia_Book_DB_Handler', false ) ) {
    if ( ! class_exists( 'Politeia_Book_Confirmation_Table', false ) ) {
        class_alias( 'Politeia_Book_DB_Handler', 'Politeia_Book_Confirmation_Table' );
    }
    return;
}

// Otherwise load the canonical handler implementation and set up the alias.
require_once __DIR__ . '/class-book-db-handler.php';

if ( ! class_exists( 'Politeia_Book_Confirmation_Table', false ) ) {
    class_alias( 'Politeia_Book_DB_Handler', 'Politeia_Book_Confirmation_Table' );
}
