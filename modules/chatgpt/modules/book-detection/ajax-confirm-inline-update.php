<?php

namespace Politeia\ChatGPT\BookDetection {

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AJAX endpoint that updates a pending confirmation queue row inline.
 */
function confirm_update_field_ajax() {
    try {
        check_ajax_referer( 'politeia-chatgpt-nonce', 'nonce' );

        $id    = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        $field = isset( $_POST['field'] ) ? sanitize_key( $_POST['field'] ) : '';
        $value = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';

        if ( ! $id || ! in_array( $field, [ 'title', 'author' ], true ) ) {
            wp_send_json_error( 'invalid_request' );
        }

        global $wpdb;
        $table   = $wpdb->prefix . 'politeia_book_confirm';
        $user_id = get_current_user_id();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id=%d AND user_id=%d AND status='pending' LIMIT 1",
                $id,
                $user_id
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            wp_send_json_error( 'not_found' );
        }

        $value = trim( wp_strip_all_tags( (string) $value ) );

        if ( '' === $value ) {
            wp_send_json_error( 'empty_value' );
        }

        if ( ! function_exists( __NAMESPACE__ . '\\normalize_text' ) ) {
            function normalize_text( $text ) {
                $text = (string) $text;
                $text = wp_strip_all_tags( $text );
                $text = html_entity_decode( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
                $text = preg_replace( '/\s+/u', ' ', $text );
                $text = trim( $text );

                return $text;
            }
        }

        if ( ! function_exists( __NAMESPACE__ . '\\title_author_hash' ) ) {
            function title_author_hash( $title, $author ) {
                $title = strtolower( trim( normalize_text( $title ) ) );
                $author = strtolower( trim( normalize_text( $author ) ) );

                return hash( 'sha256', $title . '|' . $author );
            }
        }

        $title  = ( 'title' === $field ) ? $value : $row['title'];
        $author = ( 'author' === $field ) ? $value : $row['author'];

        $normalized_title  = normalize_text( $title );
        $normalized_author = normalize_text( $author );
        $hash              = title_author_hash( $title, $author );

        $duplicate_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE user_id=%d AND status='pending' AND title_author_hash=%s AND id<>%d LIMIT 1",
                $user_id,
                $hash,
                $id
            )
        );

        if ( $duplicate_id ) {
            $wpdb->delete( $table, [ 'id' => $duplicate_id, 'user_id' => $user_id ], [ '%d', '%d' ] );
        }

        $wpdb->update(
            $table,
            [
                'title'             => $title,
                'author'            => $author,
                'normalized_title'  => $normalized_title,
                'normalized_author' => $normalized_author,
                'title_author_hash' => $hash,
                'updated_at'        => current_time( 'mysql', 1 ),
            ],
            [
                'id'      => $id,
                'user_id' => $user_id,
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s' ],
            [ '%d', '%d' ]
        );

        wp_send_json_success(
            [
                'id'     => $id,
                'title'  => $title,
                'author' => $author,
                'hash'   => $hash,
            ]
        );
    } catch ( \Throwable $e ) {
        error_log( '[politeia_confirm_update_field] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
        wp_send_json_error( WP_DEBUG ? $e->getMessage() : 'internal_error' );
    }
}

add_action( 'wp_ajax_politeia_confirm_update_field', __NAMESPACE__ . '\\confirm_update_field_ajax' );
add_action( 'wp_ajax_nopriv_politeia_confirm_update_field', __NAMESPACE__ . '\\confirm_update_field_ajax' );

} // namespace Politeia\ChatGPT\BookDetection

namespace {
    if ( ! function_exists( 'politeia_confirm_update_field_ajax' ) ) {
        function politeia_confirm_update_field_ajax() {
            return \Politeia\ChatGPT\BookDetection\confirm_update_field_ajax();
        }
    }
}
