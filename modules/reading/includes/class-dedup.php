<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class Politeia_Reading_Book_Dedup {
        public static function register() {
                add_action( 'wp_ajax_politeia_dedup_action', array( __CLASS__, 'handle_ajax' ) );
        }

        public static function handle_ajax() {
                if ( ! current_user_can( 'manage_politeia_books' ) ) {
                        wp_send_json_error( array( 'message' => __( 'You are not allowed to perform this action.', 'politeia-reading' ) ), 403 );
                }

                check_ajax_referer( 'politeia_dedup_action', 'nonce' );

                $candidate_id = isset( $_POST['candidate_id'] ) ? absint( $_POST['candidate_id'] ) : 0;
                $dedup_action = isset( $_POST['dedup_action'] ) ? sanitize_key( wp_unslash( $_POST['dedup_action'] ) ) : '';

                if ( $candidate_id <= 0 ) {
                        wp_send_json_error( array( 'message' => __( 'Invalid candidate identifier.', 'politeia-reading' ) ) );
                }

                if ( ! in_array( $dedup_action, array( 'confirm', 'reject' ), true ) ) {
                        wp_send_json_error( array( 'message' => __( 'Invalid action.', 'politeia-reading' ) ) );
                }

                if ( 'confirm' === $dedup_action ) {
                        $result = self::confirm_candidate( $candidate_id );
                } else {
                        $result = self::reject_candidate( $candidate_id );
                }

                if ( is_wp_error( $result ) ) {
                        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
                }

                wp_send_json_success( array( 'message' => __( 'Candidate updated successfully.', 'politeia-reading' ) ) );
        }

        private static function confirm_candidate( $candidate_id ) {
                global $wpdb;

                $candidate_table = $wpdb->prefix . 'politeia_book_candidates';
                $book_table      = $wpdb->prefix . 'politeia_books';
                $user_books      = $wpdb->prefix . 'politeia_user_books';

                $candidate = $wpdb->get_row(
                        $wpdb->prepare( "SELECT * FROM {$candidate_table} WHERE id = %d", $candidate_id )
                );

                if ( ! $candidate ) {
                        return new WP_Error( 'politeia_dedup_not_found', __( 'Candidate not found.', 'politeia-reading' ) );
                }

                $book_id = (int) $candidate->book_id;
                if ( $book_id <= 0 ) {
                        return new WP_Error( 'politeia_dedup_missing_book', __( 'Candidate is missing a canonical book reference.', 'politeia-reading' ) );
                }

                $book = $wpdb->get_row(
                        $wpdb->prepare( "SELECT * FROM {$book_table} WHERE id = %d", $book_id )
                );

                if ( ! $book ) {
                        return new WP_Error( 'politeia_dedup_missing_canonical', __( 'Canonical book does not exist.', 'politeia-reading' ) );
                }

                $updates = array();
                $formats = array();

                if ( ! empty( $candidate->candidate_title ) ) {
                        $title            = sanitize_text_field( $candidate->candidate_title );
                        $updates['title'] = $title;
                        $formats[]        = '%s';

                        if ( function_exists( 'politeia__normalize_text' ) ) {
                                $normalized = politeia__normalize_text( $title );
                        } else {
                                $normalized = $title;
                        }

                        $normalized = '' !== $normalized ? $normalized : null;

                        if ( null !== $normalized ) {
                                $updates['normalized_title'] = $normalized;
                                $formats[]                   = '%s';
                        }
                }

                $author_payload = null;

                if ( ! empty( $candidate->candidate_authors ) ) {
                        $authors              = self::parse_authors( $candidate->candidate_authors );
                        $author_payload       = $authors;
                        $primary_author       = reset( $authors );
                        $primary_author       = false !== $primary_author ? $primary_author : $candidate->candidate_authors;
                        $primary_author_clean = sanitize_text_field( $primary_author );
                        $updates['author']    = $primary_author_clean;
                        $formats[]            = '%s';

                        if ( function_exists( 'politeia__normalize_text' ) ) {
                                $normalized = politeia__normalize_text( $primary_author_clean );
                        } else {
                                $normalized = $primary_author_clean;
                        }

                        $normalized = '' !== $normalized ? $normalized : null;

                        if ( null !== $normalized ) {
                                $updates['normalized_author'] = $normalized;
                                $formats[]                    = '%s';
                        }
                }

                if ( null === $author_payload ) {
                        $author_payload = self::parse_authors( $candidate->original_authors );
                }

                $hash_title  = isset( $updates['title'] ) ? $updates['title'] : $book->title;
                $hash_author = isset( $updates['author'] ) ? $updates['author'] : $book->author;

                if ( function_exists( 'politeia__title_author_hash' ) ) {
                        $hash = politeia__title_author_hash( $hash_title, $hash_author );
                } else {
                        $hash = hash( 'sha256', strtolower( trim( (string) $hash_title ) ) . '|' . strtolower( trim( (string) $hash_author ) ) );
                }

                if ( $hash ) {
                        $updates['title_author_hash'] = $hash;
                        $formats[]                    = '%s';
                }

                if ( ! empty( $updates ) ) {
                        $updates['updated_at'] = current_time( 'mysql' );
                        $formats[]             = '%s';

                        $wpdb->update( $book_table, $updates, array( 'id' => $book_id ), $formats, array( '%d' ) );

                        if ( $wpdb->last_error ) {
                                return new WP_Error( 'politeia_dedup_update_failed', $wpdb->last_error );
                        }
                }

                if ( function_exists( 'prs_sync_book_author_links' ) ) {
                        prs_sync_book_author_links( $book_id, $author_payload );
                }

                $candidate_book_id = isset( $candidate->candidate_book_id ) ? trim( (string) $candidate->candidate_book_id ) : '';

                if ( ctype_digit( $candidate_book_id ) ) {
                        $duplicate_id = (int) $candidate_book_id;
                        if ( $duplicate_id > 0 && $duplicate_id !== $book_id ) {
                                $wpdb->update(
                                        $user_books,
                                        array(
                                                'book_id'    => $book_id,
                                                'updated_at' => current_time( 'mysql' ),
                                        ),
                                        array( 'book_id' => $duplicate_id ),
                                        array( '%d', '%s' ),
                                        array( '%d' )
                                );
                        }
                }

                $wpdb->update(
                        $candidate_table,
                        array( 'status' => 'confirmed' ),
                        array( 'id' => $candidate_id ),
                        array( '%s' ),
                        array( '%d' )
                );

                if ( $wpdb->last_error ) {
                        return new WP_Error( 'politeia_dedup_status_failed', $wpdb->last_error );
                }

                return true;
        }

        private static function reject_candidate( $candidate_id ) {
                global $wpdb;

                $candidate_table = $wpdb->prefix . 'politeia_book_candidates';

                $wpdb->update(
                        $candidate_table,
                        array( 'status' => 'rejected' ),
                        array( 'id' => $candidate_id ),
                        array( '%s' ),
                        array( '%d' )
                );

                if ( $wpdb->last_error ) {
                        return new WP_Error( 'politeia_dedup_reject_failed', $wpdb->last_error );
                }

                return true;
        }

        private static function parse_authors( $value ) {
                if ( empty( $value ) ) {
                        return array();
                }

                if ( is_array( $value ) ) {
                        $authors = $value;
                } else {
                        $authors = preg_split( '/[;,\|]+/', (string) $value );
                }

                $authors = is_array( $authors ) ? $authors : array( $authors );
                $authors = array_map( 'trim', $authors );
                $authors = array_filter( $authors, static function ( $author ) {
                        return '' !== $author;
                } );

                return array_map(
                        static function ( $author ) {
                                return sanitize_text_field( $author );
                        },
                        $authors
                );
        }
}

Politeia_Reading_Book_Dedup::register();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
        /**
         * WP-CLI helpers for deduplication testing.
         */
        class Politeia_Reading_Dedup_Command extends WP_CLI_Command {
                /**
                 * Seed sample candidate rows for testing the dedup UI.
                 *
                 * ## EXAMPLES
                 *
                 *     wp politeia dedupe seed
                 */
                public function seed() {
                        global $wpdb;

                        $candidate_table = $wpdb->prefix . 'politeia_book_candidates';
                        $book_table      = $wpdb->prefix . 'politeia_books';

                        $book_id = (int) $wpdb->get_var( "SELECT id FROM {$book_table} ORDER BY id ASC LIMIT 1" );

                        if ( ! $book_id ) {
                                if ( ! function_exists( 'prs_find_or_create_book' ) ) {
                                        WP_CLI::error( 'No canonical books are available and helper function is missing.' );
                                }

                                $book_id = prs_find_or_create_book( 'Sample Book', 'Sample Author', 2020 );
                                if ( is_wp_error( $book_id ) ) {
                                        WP_CLI::error( $book_id->get_error_message() );
                                }
                        }

                        $rows = array(
                                array(
                                        'book_id'           => $book_id,
                                        'candidate_book_id' => 'external:seed-1',
                                        'original_title'    => 'Sample Book',
                                        'original_authors'  => 'Sample Author',
                                        'candidate_title'   => 'Sample Book Deluxe Edition',
                                        'candidate_authors' => 'Sample Author;Second Author',
                                        'title_score'       => 95,
                                        'author_score'      => 90,
                                        'year_score'        => 80,
                                        'total_score'       => 88,
                                ),
                                array(
                                        'book_id'           => $book_id,
                                        'candidate_book_id' => '123456',
                                        'original_title'    => 'Sample Book',
                                        'original_authors'  => 'Sample Author',
                                        'candidate_title'   => 'The Sample Book',
                                        'candidate_authors' => 'Sample Author',
                                        'title_score'       => 85,
                                        'author_score'      => 85,
                                        'year_score'        => 70,
                                        'total_score'       => 80,
                                ),
                        );

                        $inserted = 0;
                        foreach ( $rows as $row ) {
                                $data   = $row;
                                $format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s' );

                                $data['created_at'] = current_time( 'mysql' );

                                $result = $wpdb->replace( $candidate_table, $data, $format );
                                if ( false !== $result ) {
                                        $inserted++;
                                }
                        }

                        WP_CLI::success( sprintf( 'Inserted or updated %d candidate rows.', $inserted ) );
                }
        }

        WP_CLI::add_command( 'politeia dedupe', 'Politeia_Reading_Dedup_Command' );
}
