<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class Politeia_Reading_Book_Dedup {
        public static function register() {
                add_action( 'wp_ajax_politeia_dedup_action', array( __CLASS__, 'handle_ajax' ) );
        }

        /**
         * Scan the canonical books table for likely duplicates and record them as
         * pending candidates so the review UI can surface them.
         *
         * @return array{inserted:int,updated:int,removed:int,groups:int} Summary of the sync.
         */
        public static function sync_internal_duplicates() {
                global $wpdb;

                $books_table      = $wpdb->prefix . 'politeia_books';
                $candidates_table = $wpdb->prefix . 'politeia_book_candidates';

                $books = $wpdb->get_results(
                        "SELECT id, title, author, year, normalized_title, normalized_author FROM {$books_table}"
                );

                if ( empty( $books ) ) {
                        return array(
                                'inserted' => 0,
                                'updated'  => 0,
                                'removed'  => 0,
                                'groups'   => 0,
                        );
                }

		$groups_by_author = array();
		$title_groups     = array();

		foreach ( $books as $book ) {
			$title_source = $book->normalized_title ?: $book->title;
			$title_key    = self::normalize_for_match( $title_source );

			if ( '' === $title_key ) {
				continue;
			}

			if ( ! isset( $title_groups[ $title_key ] ) ) {
				$title_groups[ $title_key ] = array();
			}

			$title_groups[ $title_key ][] = $book;

			$author_source = $book->normalized_author ?: $book->author;
			$author_key    = self::normalize_for_match( $author_source );
			$group_key     = $title_key . '|' . $author_key;

			if ( ! isset( $groups_by_author[ $group_key ] ) ) {
				$groups_by_author[ $group_key ] = array();
			}

			$groups_by_author[ $group_key ][] = $book;
		}

		$inserted       = 0;
		$updated        = 0;
		$processed_keys = array();

		foreach ( $groups_by_author as $group ) {
			self::process_group_candidates( $group, $candidates_table, $processed_keys, $inserted, $updated );
		}

		foreach ( $title_groups as $group ) {
			self::process_group_candidates( $group, $candidates_table, $processed_keys, $inserted, $updated );
		}

                $removed = 0;

                $pending_rows = $wpdb->get_results( "SELECT id, book_id, candidate_book_id FROM {$candidates_table} WHERE status = 'pending'" );
                foreach ( $pending_rows as $row ) {
                        $key = $row->book_id . '|' . $row->candidate_book_id;

                        if ( ! isset( $processed_keys[ $key ] ) && ctype_digit( (string) $row->candidate_book_id ) ) {
                                $deleted = $wpdb->delete(
                                        $candidates_table,
                                        array( 'id' => (int) $row->id ),
                                        array( '%d' )
                                );

                                if ( false !== $deleted ) {
                                        $removed++;
                                }
                        }
                }

		return array(
			'inserted' => $inserted,
			'updated'  => $updated,
			'removed'  => $removed,
			'groups'   => count( $groups_by_author ) + count( $title_groups ),
		);
	}

	private static function process_group_candidates( $group, $candidates_table, array &$processed_keys, &$inserted, &$updated ) {
		if ( count( $group ) < 2 ) {
			return;
		}

		usort(
			$group,
			static function ( $a, $b ) {
				return (int) $a->id - (int) $b->id;
			}
		);

		$canonical = array_shift( $group );

		foreach ( $group as $candidate ) {
			self::queue_candidate_pair( $canonical, $candidate, $candidates_table, $processed_keys, $inserted, $updated );
		}
	}

	private static function queue_candidate_pair( $canonical, $candidate, $candidates_table, array &$processed_keys, &$inserted, &$updated ) {
		global $wpdb;

		$canonical_id = (int) $canonical->id;
		$candidate_id = (int) $candidate->id;

		if ( $canonical_id <= 0 || $candidate_id <= 0 || $canonical_id === $candidate_id ) {
			return;
		}

		$pair_key = $canonical_id . '|' . $candidate_id;

		if ( isset( $processed_keys[ $pair_key ] ) ) {
			return;
		}

		$processed_keys[ $pair_key ] = true;

		$candidate_book_id = (string) $candidate_id;
		$original_title    = sanitize_text_field( (string) $canonical->title );
		$original_author   = sanitize_text_field( (string) $canonical->author );

		$title_score  = self::similarity_score( $canonical->title, $candidate->title );
		$author_score = self::similarity_score( $canonical->author, $candidate->author );
		$year_score   = self::year_score( $canonical->year, $candidate->year );

		$score_components = array();

		foreach ( array( $title_score, $author_score, $year_score ) as $score ) {
			if ( null !== $score ) {
				$score_components[] = (int) $score;
			}
		}

		$total_score = null;

		if ( ! empty( $score_components ) ) {
			$total_score = (int) round( array_sum( $score_components ) / count( $score_components ) );
		}

		$data = array(
			'book_id'           => $canonical_id,
			'candidate_book_id' => $candidate_book_id,
			'original_title'    => $original_title,
			'original_authors'  => $original_author,
			'candidate_title'   => sanitize_text_field( (string) $candidate->title ),
			'candidate_authors' => sanitize_text_field( (string) $candidate->author ),
			'title_score'       => null === $title_score ? 0 : (int) $title_score,
			'author_score'      => null === $author_score ? 0 : (int) $author_score,
			'year_score'        => null === $year_score ? 0 : (int) $year_score,
			'total_score'       => null === $total_score ? 0 : (int) $total_score,
		);

		$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d' );

		$existing = $wpdb->get_row(
			$wpdb->prepare("SELECT id, status FROM {$candidates_table} WHERE book_id = %d AND candidate_book_id = %s",
				$canonical_id,
				$candidate_book_id
			)
		);

		if ( $existing ) {
			if ( 'pending' !== $existing->status ) {
				return;
			}

			$result = $wpdb->update(
				$candidates_table,
				$data,
				array( 'id' => (int) $existing->id ),
				$formats,
				array( '%d' )
			);

			if ( false !== $result ) {
				$updated++;
			}
		} else {
			$data['status']     = 'pending';
			$data['created_at'] = current_time( 'mysql' );

			$insert_formats = array_merge( $formats, array( '%s', '%s' ) );

			$result = $wpdb->insert( $candidates_table, $data, $insert_formats );

			if ( false !== $result ) {
				$inserted++;
			}
		}
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

        private static function normalize_for_match( $value ) {
                $value = (string) $value;

                if ( function_exists( 'politeia__normalize_text' ) ) {
                        $value = politeia__normalize_text( $value );
                }

                if ( function_exists( 'remove_accents' ) ) {
                        $value = remove_accents( $value );
                }

                $value = strtolower( trim( $value ) );
                $value = preg_replace( '/\s+/u', ' ', $value );

                return (string) $value;
        }

        private static function similarity_score( $first, $second ) {
                $first  = trim( (string) $first );
                $second = trim( (string) $second );

                if ( '' === $first || '' === $second ) {
                        return null;
                }

                if ( function_exists( 'politeia__normalize_text' ) ) {
                        $first  = politeia__normalize_text( $first );
                        $second = politeia__normalize_text( $second );
                }

                if ( function_exists( 'remove_accents' ) ) {
                        $first  = remove_accents( $first );
                        $second = remove_accents( $second );
                }

                $first  = strtolower( $first );
                $second = strtolower( $second );

                similar_text( $first, $second, $percent );

                return (int) round( $percent );
        }

        private static function year_score( $first, $second ) {
                $first  = (int) $first;
                $second = (int) $second;

                if ( $first <= 0 || $second <= 0 ) {
                        return null;
                }

                $diff = abs( $first - $second );

                if ( 0 === $diff ) {
                        return 100;
                }

                if ( 1 === $diff ) {
                        return 80;
                }

                if ( 2 === $diff ) {
                        return 60;
                }

                return max( 0, 40 - ( $diff - 3 ) * 20 );
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

                /**
                 * Scan wp_politeia_books for internal duplicates and generate pending candidates.
                 */
                public function scan() {
                        $result = Politeia_Reading_Book_Dedup::sync_internal_duplicates();

                        WP_CLI::success(
                                sprintf(
                                        'Duplicate sync complete. Inserted: %d, updated: %d, removed: %d.',
                                        (int) $result['inserted'],
                                        (int) $result['updated'],
                                        (int) $result['removed']
                                )
                        );
                }
        }

        WP_CLI::add_command( 'politeia dedupe', 'Politeia_Reading_Dedup_Command' );
}
