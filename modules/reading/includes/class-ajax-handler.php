<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class Politeia_Reading_Ajax_Handler {

        public static function init() {
                add_action( 'wp_ajax_politeia_save_session_note', array( __CLASS__, 'save_session_note' ) );
                add_action( 'wp_ajax_politeia_get_session_note', array( __CLASS__, 'get_session_note' ) );
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

                $existing = $wpdb->get_row(
                        $wpdb->prepare(
                                "SELECT id FROM {$table_notes} WHERE rs_id = %d AND book_id = %d AND user_id = %d LIMIT 1",
                                $rs_id,
                                $book_id,
                                $user_id
                        )
                );

                $now = current_time( 'mysql' );

                if ( $existing ) {
                        $updated = $wpdb->update(
                                $table_notes,
                                array(
                                        'note'       => $note,
                                        'updated_at' => $now,
                                ),
                                array( 'id' => (int) $existing->id ),
                                array( '%s', '%s' ),
                                array( '%d' )
                        );

                        if ( false === $updated ) {
                                $error = $wpdb->last_error ? $wpdb->last_error : 'DB update failed.';
                                wp_send_json_error( $error, 500 );
                        }

                        wp_send_json_success(
                                array(
                                        'note_id' => (int) $existing->id,
                                        'updated' => true,
                                )
                        );
                }

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

                wp_send_json_success(
                        array(
                                'note_id' => (int) $wpdb->insert_id,
                                'updated' => false,
                        )
                );
        }

        public static function get_session_note() {
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

                if ( ! $rs_id || ! $book_id || ! $user_id ) {
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

                $note_row = $wpdb->get_row(
                        $wpdb->prepare(
                                "SELECT note, updated_at FROM {$table_notes} WHERE rs_id = %d AND book_id = %d AND user_id = %d ORDER BY updated_at DESC LIMIT 1",
                                $rs_id,
                                $book_id,
                                $user_id
                        )
                );

                $note = $note_row && isset( $note_row->note ) ? $note_row->note : '';
                $updated_at = $note_row && isset( $note_row->updated_at ) ? $note_row->updated_at : '';

                wp_send_json_success(
                        array(
                                'note'       => $note,
                                'updated_at' => $updated_at,
                                'has_note'   => (bool) $note_row,
                        )
                );
        }
}

Politeia_Reading_Ajax_Handler::init();
