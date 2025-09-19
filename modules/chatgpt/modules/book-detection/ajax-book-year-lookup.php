<?php

namespace Politeia\ChatGPT\BookDetection {

use Exception;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BookYearLookupAjax {
    public static function register(): void {
        \add_action( 'wp_ajax_politeia_lookup_book_years', [ self::class, 'handle' ] );
        \add_action( 'wp_ajax_nopriv_politeia_lookup_book_years', [ self::class, 'handle' ] );
    }

    public static function handle(): void {
        try {
            \check_ajax_referer( 'politeia-chatgpt-nonce', 'nonce' );

            if ( ! class_exists( BookExternalApi::class ) ) {
                if ( function_exists( 'politeia_chatgpt_safe_require' ) ) {
                    politeia_chatgpt_safe_require( 'modules/book-detection/class-book-external-api.php' );
                }
            }

            if ( ! class_exists( BookExternalApi::class ) ) {
                throw new Exception( 'BookExternalApi not loaded' );
            }

            $items_json = isset( $_POST['items'] ) ? \wp_unslash( $_POST['items'] ) : '[]';
            $items      = json_decode( $items_json, true );
            if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $items ) ) {
                throw new Exception( 'Invalid items payload' );
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

                $query_title  = self::simplifyTitle( $title );
                $query_author = $author;

                $cache_key = 'pol_year_' . hash( 'sha1', wp_json_encode( [ $query_title, $query_author ] ) );
                $cached    = \get_transient( $cache_key );
                if ( false !== $cached ) {
                    $years[] = $cached ? (int) $cached : null;
                    continue;
                }

                $best = $api->search_best_match( $query_title, $query_author, [ 'limit_per_provider' => 4 ] );

                $year = null;
                if ( is_array( $best ) ) {
                    $year = self::extractYearFromCandidate( $best );
                }

                \set_transient( $cache_key, $year ? $year : false, DAY_IN_SECONDS );
                $years[] = $year ? $year : null;
            }

            \wp_send_json_success(
                [
                    'years' => $years,
                ]
            );
        } catch ( Throwable $throwable ) {
            \error_log( '[politeia_lookup_book_years] ' . $throwable->getMessage() . ' @ ' . $throwable->getFile() . ':' . $throwable->getLine() );
            \wp_send_json_error( defined( 'WP_DEBUG' ) && WP_DEBUG ? $throwable->getMessage() : 'internal_error' );
        }
    }

    private static function simplifyTitle( string $title ): string {
        $title = \wp_strip_all_tags( $title );
        $parts = preg_split( '/[:\-–—]/u', $title, 2 );
        $title = $parts ? $parts[0] : $title;

        return trim( (string) $title );
    }

    private static function extractYearFromCandidate( array $candidate ): ?int {
        $candidates = [
            $candidate['year'] ?? null,
            $candidate['first_publish_year'] ?? null,
            $candidate['firstPublishYear'] ?? null,
            $candidate['publish_year'][0] ?? null,
            $candidate['publishedDate'] ?? null,
            $candidate['date'] ?? null,
        ];

        foreach ( $candidates as $value ) {
            if ( null === $value || '' === $value ) {
                continue;
            }

            if ( preg_match( '/\d{4}/', (string) $value, $matches ) ) {
                return (int) $matches[0];
            }
        }

        return null;
    }
}

BookYearLookupAjax::register();
}

namespace {
    if ( ! function_exists( 'politeia_lookup_book_years_ajax' ) ) {
        function politeia_lookup_book_years_ajax() {
            \Politeia\ChatGPT\BookDetection\BookYearLookupAjax::handle();
        }
    }
}
