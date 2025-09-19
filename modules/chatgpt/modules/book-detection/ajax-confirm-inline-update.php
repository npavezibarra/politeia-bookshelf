<?php

namespace Politeia\ChatGPT\BookDetection {

use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BookConfirmInlineUpdateAjax {
    public static function register(): void {
        \add_action( 'wp_ajax_politeia_confirm_update_field', [ self::class, 'handle' ] );
        \add_action( 'wp_ajax_nopriv_politeia_confirm_update_field', [ self::class, 'handle' ] );
    }

    public static function handle(): void {
        try {
            \check_ajax_referer( 'politeia-chatgpt-nonce', 'nonce' );

            $id    = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
            $field = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
            $value = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';

            if ( ! $id || ! in_array( $field, [ 'title', 'author' ], true ) ) {
                \wp_send_json_error( 'invalid_request' );
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
                \wp_send_json_error( 'not_found' );
            }

            $value = trim( wp_strip_all_tags( (string) $value ) );
            if ( '' === $value ) {
                \wp_send_json_error( 'empty_value' );
            }

            $title  = ( 'title' === $field ) ? $value : $row['title'];
            $author = ( 'author' === $field ) ? $value : $row['author'];

            $normalized_title  = self::normalizeText( $title );
            $normalized_author = self::normalizeText( $author );
            $hash              = self::titleAuthorHash( $title, $author );

            $duplicate_id = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table}
                      WHERE user_id=%d AND status='pending' AND title_author_hash=%s AND id<>%d
                      LIMIT 1",
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

            \wp_send_json_success(
                [
                    'id'     => $id,
                    'title'  => $title,
                    'author' => $author,
                    'hash'   => $hash,
                ]
            );
        } catch ( Throwable $throwable ) {
            \error_log( '[politeia_confirm_update_field] ' . $throwable->getMessage() . ' @ ' . $throwable->getFile() . ':' . $throwable->getLine() );
            \wp_send_json_error( defined( 'WP_DEBUG' ) && WP_DEBUG ? $throwable->getMessage() : 'internal_error' );
        }
    }

    private static function normalizeText( string $value ): string {
        $value = wp_strip_all_tags( $value );
        $value = html_entity_decode( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
        $value = preg_replace( '/\s+/u', ' ', $value );

        return trim( $value );
    }

    private static function titleAuthorHash( string $title, string $author ): string {
        if ( class_exists( BookConfirmSchema::class ) ) {
            return BookConfirmSchema::compute_title_author_hash( $title, $author );
        }

        $normalized_title  = strtolower( remove_accents( trim( self::normalizeText( $title ) ) ) );
        $normalized_author = strtolower( remove_accents( trim( self::normalizeText( $author ) ) ) );

        $clean = ' ' . preg_replace( '/\s+/', ' ', $normalized_title . ' ' . $normalized_author ) . ' ';
        $clean = preg_replace( '/\b(el|la|los|las|un|una|unos|unas|de|del|y|e|a|en|the|of|and|to|for)\b/u', ' ', $clean );
        $clean = preg_replace( '/[^a-z0-9\s]/u', ' ', $clean );

        $tokens = array_values( array_filter( explode( ' ', preg_replace( '/\s+/', ' ', trim( $clean ) ) ) ) );
        sort( $tokens, SORT_STRING );

        return hash( 'sha256', implode( ' ', $tokens ) );
    }
}

BookConfirmInlineUpdateAjax::register();
}

namespace {
    if ( ! function_exists( 'politeia_confirm_update_field_ajax' ) ) {
        function politeia_confirm_update_field_ajax() {
            \Politeia\ChatGPT\BookDetection\BookConfirmInlineUpdateAjax::handle();
        }
    }
}
