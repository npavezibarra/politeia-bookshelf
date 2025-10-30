<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class PRS_Ajax_User_Books {
        public static function init() {
                add_action( 'wp_ajax_politeia_remove_user_book', array( __CLASS__, 'handle_remove_user_book' ) );
        }

        public static function handle_remove_user_book() {
                if ( ! is_user_logged_in() ) {
                        wp_send_json_error( 'Invalid request.' );
                }

                global $wpdb;

                $user_id      = get_current_user_id();
                $user_book_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
                $nonce        = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

                if ( ! $user_book_id || ! wp_verify_nonce( $nonce, 'remove_user_book_' . $user_book_id ) ) {
                        wp_send_json_error( 'Invalid request.' );
                }

                $table_user_books = $wpdb->prefix . 'politeia_user_books';

                $user_book = $wpdb->get_row(
                        $wpdb->prepare(
                                "SELECT id, user_id, book_id FROM {$table_user_books} WHERE id = %d AND deleted_at IS NULL",
                                $user_book_id
                        )
                );

                if ( ! $user_book ) {
                        wp_send_json_error( 'Invalid request.' );
                }

                if ( (int) $user_book->user_id !== (int) $user_id ) {
                        wp_send_json_error( 'You are not allowed to remove this book.' );
                }

                $now = current_time( 'mysql' );

                $updated_book = $wpdb->update(
                        $table_user_books,
                        array(
                                'deleted_at' => $now,
                                'updated_at' => $now,
                        ),
                        array( 'id' => $user_book_id )
                );

                if ( false === $updated_book ) {
                        wp_send_json_error( 'Error removing book.' );
                }

                $table_sessions = $wpdb->prefix . 'politeia_reading_sessions';
                $table_loans    = $wpdb->prefix . 'politeia_loans';

                $wpdb->update(
                        $table_sessions,
                        array( 'deleted_at' => $now ),
                        array(
                                'user_id' => (int) $user_id,
                                'book_id' => (int) $user_book->book_id,
                        )
                );

                $wpdb->update(
                        $table_loans,
                        array(
                                'deleted_at' => $now,
                                'updated_at' => $now,
                        ),
                        array(
                                'user_id' => (int) $user_id,
                                'book_id' => (int) $user_book->book_id,
                        )
                );

                wp_send_json_success(
                        array(
                                'message'    => 'Book removed from your library.',
                                'deleted_at' => $now,
                        )
                );
        }
}

PRS_Ajax_User_Books::init();
