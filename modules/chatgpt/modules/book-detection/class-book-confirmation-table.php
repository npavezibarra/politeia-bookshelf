<?php

namespace Politeia\ChatGPT\BookDetection;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Lightweight wrapper kept for backward compatibility with legacy includes
 * that expected the confirmation table helper to expose the same API as the
 * old database handler. The functionality now lives in {@see BookDbHandler};
 * this class simply extends it while providing the previous class name via
 * {@see class_alias()}.
 */
class BookConfirmationTable extends BookDbHandler {
}

if ( ! class_exists( 'Politeia_Book_Confirmation_Table', false ) ) {
    class_alias( BookConfirmationTable::class, 'Politeia_Book_Confirmation_Table' );
}
