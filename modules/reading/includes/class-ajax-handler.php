<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class Politeia_Reading_Ajax_Handler {

        public static function init() {
                add_action( 'wp_ajax_politeia_save_session_note', array( __CLASS__, 'save_session_note' ) );
        }

        public static function save_session_note() {
                if ( ! is_user_logged_in() ) {
                        wp_send_json_error( 'Not allowed.', 401 );
                }

                $nonce_valid = check_ajax_referer( 'prs_reading_nonce', 'nonce', false );
                if ( ! $nonce_valid ) {
                        wp_send_json_error( 'Invalid nonce.', 403 );
                }

                global $wpdb;

                $table_notes    = $wpdb->prefix . 'politeia_read_ses_notes';
                $table_sessions = $wpdb->prefix . 'politeia_reading_sessions';

                $user_id = get_current_user_id();
                $rs_id   = isset( $_POST['rs_id'] ) ? absint( $_POST['rs_id'] ) : 0;
                $book_id = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
                $note    = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

                if ( ! $rs_id || ! $book_id || ! $user_id || '' === $note ) {
                        wp_send_json_error( 'Missing required fields.', 400 );
                }

                $session = $wpdb->get_row(
                        $wpdb->prepare(
                                "SELECT id FROM {$table_sessions} WHERE id = %d AND user_id = %d AND book_id = %d AND deleted_at IS NULL LIMIT 1",
                                $rs_id,
                                $user_id,
                                $book_id
                        )
                );

                if ( ! $session ) {
                        wp_send_json_error( 'Invalid session.', 404 );
                }

                $now = current_time( 'mysql' );

                $inserted = $wpdb->insert(
                        $table_notes,
                        array(
                                'rs_id'      => $rs_id,
                                'book_id'    => $book_id,
                                'user_id'    => $user_id,
                                'note'       => $note,
                                'created_at' => $now,
                                'updated_at' => $now,
                        ),
                        array( '%d', '%d', '%d', '%s', '%s', '%s' )
                );

                if ( false === $inserted ) {
                        $error = $wpdb->last_error ? $wpdb->last_error : 'DB insert failed.';
                        wp_send_json_error( $error, 500 );
                }

                wp_send_json_success();
        }
}

Politeia_Reading_Ajax_Handler::init();
