<?php

namespace Politeia\ChatGPT\BookDetection {

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle AJAX requests that fetch publication years for candidate books.
 *
 * Expects a JSON payload with a list of {title, author} objects and responds
 * with an array of best-effort publication year guesses.
 */
function lookup_book_years_ajax() {
    try {
        check_ajax_referer( 'politeia-chatgpt-nonce', 'nonce' );

        if ( ! class_exists( BookExternalApi::class ) ) {
            if ( function_exists( 'politeia_chatgpt_safe_require' ) ) {
                politeia_chatgpt_safe_require( 'modules/book-detection/class-book-external-api.php' );
            }
        }

        if ( ! class_exists( BookExternalApi::class ) ) {
            throw new \Exception( 'BookExternalApi not loaded' );
        }

        $items_json = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : '[]';
        $items      = json_decode( $items_json, true );

        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $items ) ) {
            throw new \Exception( 'Invalid items payload' );
        }

        $api   = new BookExternalApi();
        $years = [];

        foreach ( $items as $item ) {
            $title  = isset( $item['title'] ) ? (string) $item['title'] : '';
            $author = isset( $item['author'] ) ? (string) $item['author'] : '';

            if ( '' === $title || '' === $author ) {
                $years[] = null;
                continue;
            }

            $query_title  = simplify_title( $title );
            $query_author = $author;

            $cache_key = 'pol_year_' . hash( 'sha1', wp_json_encode( [ $query_title, $query_author ] ) );
            $cached    = get_transient( $cache_key );

            if ( false !== $cached ) {
                $years[] = $cached ? (int) $cached : null;
                continue;
            }

            $best = $api->search_best_match(
                $query_title,
                $query_author,
                [ 'limit_per_provider' => 4 ]
            );

            $year = null;

            if ( is_array( $best ) ) {
                $candidates = [
                    $best['year'] ?? null,
                    $best['first_publish_year'] ?? null,
                    $best['firstPublishYear'] ?? null,
                    $best['publish_year'][0] ?? null,
                    $best['publishedDate'] ?? null,
                    $best['date'] ?? null,
                ];

                foreach ( $candidates as $candidate ) {
                    if ( null === $candidate || '' === $candidate ) {
                        continue;
                    }

                    if ( preg_match( '/\d{4}/', (string) $candidate, $match ) ) {
                        $year = (int) $match[0];
                        break;
                    }
                }
            }

            set_transient( $cache_key, $year ?: false, DAY_IN_SECONDS );
            $years[] = $year ?: null;
        }

        wp_send_json_success( [ 'years' => $years ] );
    } catch ( \Throwable $e ) {
        error_log( '[politeia_lookup_book_years] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
        wp_send_json_error( WP_DEBUG ? $e->getMessage() : 'internal_error' );
    }
}

add_action( 'wp_ajax_politeia_lookup_book_years', __NAMESPACE__ . '\\lookup_book_years_ajax' );
add_action( 'wp_ajax_nopriv_politeia_lookup_book_years', __NAMESPACE__ . '\\lookup_book_years_ajax' );

/**
 * Remove subtitles and normalize whitespace to improve API search results.
 */
function simplify_title( $title ) {
    $title = wp_strip_all_tags( (string) $title );
    $parts = preg_split( '/[:\-–—]/u', $title, 2 );
    $title = is_array( $parts ) ? $parts[0] : $title;
    $title = trim( $title );

    return $title;
}

} // namespace Politeia\ChatGPT\BookDetection

namespace {
    if ( ! function_exists( 'politeia_lookup_book_years_ajax' ) ) {
        function politeia_lookup_book_years_ajax() {
            return \Politeia\ChatGPT\BookDetection\lookup_book_years_ajax();
        }
    }

    if ( ! function_exists( 'politeia_year__simplify_title' ) ) {
        function politeia_year__simplify_title( $title ) {
            return \Politeia\ChatGPT\BookDetection\simplify_title( $title );
        }
    }
}
