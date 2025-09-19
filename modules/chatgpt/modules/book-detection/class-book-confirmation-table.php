<?php

namespace Politeia\ChatGPT\BookDetection;

/**
 * Class: BookConfirmationTable
 * Purpose: Backward compatible wrapper extending the book DB handler to expose
 *          helper methods for UI components that render confirmation queues.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BookConfirmationTable extends BookDbHandler {
}

if ( ! class_exists( 'Politeia_Book_Confirmation_Table', false ) ) {
    class_alias( BookConfirmationTable::class, 'Politeia_Book_Confirmation_Table' );
}
