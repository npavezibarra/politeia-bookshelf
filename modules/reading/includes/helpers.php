<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function prs_current_user_id_or_die() {
	if ( ! is_user_logged_in() ) {
		wp_die( __( 'You must be logged in.', 'politeia-reading' ) );
	}
	return get_current_user_id();
}

function prs_books_slugs_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'politeia_book_slugs';
}

function prs_normalize_title( $title ) {
        if ( function_exists( 'politeia__normalize_text' ) ) {
                return politeia__normalize_text( $title );
        }

        $normalized_title = (string) $title;
        $normalized_title = wp_strip_all_tags( $normalized_title );
        $normalized_title = html_entity_decode( $normalized_title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
        $normalized_title = trim( $normalized_title );
        $normalized_title = remove_accents( $normalized_title );
        if ( function_exists( 'mb_strtolower' ) ) {
                $normalized_title = mb_strtolower( $normalized_title, 'UTF-8' );
        } else {
                $normalized_title = strtolower( $normalized_title );
        }
        $normalized_title = preg_replace( '/[^a-z0-9\s\-\_\'\":]+/u', ' ', $normalized_title );
        $normalized_title = preg_replace( '/\s+/u', ' ', $normalized_title );
        return trim( $normalized_title );
}

function prs_books_slugs_table_exists() {
        static $exists = null;
        if ( null !== $exists ) {
                return $exists;
        }

        global $wpdb;
        $table = prs_books_slugs_table_name();
        $exists = (bool) $wpdb->get_var(
                $wpdb->prepare(
                        'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s LIMIT 1',
                        $table
                )
        );
        return $exists;
}

function prs_get_book_id_by_slug( $slug ) {
        global $wpdb;

        $slug = is_string( $slug ) ? trim( $slug ) : '';
        if ( '' === $slug ) {
                return 0;
        }

        if ( prs_books_slugs_table_exists() ) {
                $table = prs_books_slugs_table_name();
                $book_id = $wpdb->get_var(
                        $wpdb->prepare(
                                "SELECT book_id FROM {$table} WHERE slug = %s LIMIT 1",
                                $slug
                        )
                );
                if ( $book_id ) {
                        return (int) $book_id;
                }
        }

        $books_table = $wpdb->prefix . 'politeia_books';
        $book_id = $wpdb->get_var(
                $wpdb->prepare(
                        "SELECT id FROM {$books_table} WHERE slug = %s LIMIT 1",
                        $slug
                )
        );
        return $book_id ? (int) $book_id : 0;
}

function prs_get_book_id_by_primary_slug( $slug ) {
        global $wpdb;

        $slug = is_string( $slug ) ? trim( $slug ) : '';
        if ( '' === $slug ) {
                return 0;
        }

        if ( prs_books_slugs_table_exists() ) {
                $table = prs_books_slugs_table_name();
                $book_id = $wpdb->get_var(
                        $wpdb->prepare(
                                "SELECT book_id FROM {$table} WHERE slug = %s AND is_primary = 1 LIMIT 1",
                                $slug
                        )
                );
                return $book_id ? (int) $book_id : 0;
        }

        $books_table = $wpdb->prefix . 'politeia_books';
        $book_id = $wpdb->get_var(
                $wpdb->prepare(
                        "SELECT id FROM {$books_table} WHERE slug = %s LIMIT 1",
                        $slug
                )
        );
        return $book_id ? (int) $book_id : 0;
}

function prs_get_primary_slug_for_book( $book_id ) {
        global $wpdb;

        $book_id = (int) $book_id;
        if ( $book_id <= 0 ) {
                return '';
        }

        if ( prs_books_slugs_table_exists() ) {
                $table = prs_books_slugs_table_name();
                $slug = $wpdb->get_var(
                        $wpdb->prepare(
                                "SELECT slug FROM {$table} WHERE book_id = %d AND is_primary = 1 LIMIT 1",
                                $book_id
                        )
                );
                if ( is_string( $slug ) && '' !== trim( $slug ) ) {
                        return $slug;
                }
        }

        $books_table = $wpdb->prefix . 'politeia_books';
        $slug = $wpdb->get_var(
                $wpdb->prepare(
                        "SELECT slug FROM {$books_table} WHERE id = %d LIMIT 1",
                        $book_id
                )
        );
        return is_string( $slug ) ? $slug : '';
}

function prs_book_slug_exists( $slug, $exclude_book_id = 0 ) {
        global $wpdb;

        $slug = is_string( $slug ) ? trim( $slug ) : '';
        if ( '' === $slug ) {
                return false;
        }

        $exclude_book_id = (int) $exclude_book_id;

        if ( prs_books_slugs_table_exists() ) {
                $table = prs_books_slugs_table_name();
                $query = "SELECT book_id FROM {$table} WHERE slug = %s";
                $params = array( $slug );
                if ( $exclude_book_id > 0 ) {
                        $query .= ' AND book_id <> %d';
                        $params[] = $exclude_book_id;
                }
                $query .= ' LIMIT 1';
                $book_id = $wpdb->get_var( $wpdb->prepare( $query, $params ) );
                if ( $book_id ) {
                        return true;
                }
        }

        $books_table = $wpdb->prefix . 'politeia_books';
        $query = "SELECT id FROM {$books_table} WHERE slug = %s";
        $params = array( $slug );
        if ( $exclude_book_id > 0 ) {
                $query .= ' AND id <> %d';
                $params[] = $exclude_book_id;
        }
        $query .= ' LIMIT 1';
        $book_id = $wpdb->get_var( $wpdb->prepare( $query, $params ) );
        return ! empty( $book_id );
}

function prs_generate_book_slug( $title, $year = null, $exclude_book_id = 0 ) {
        $base = sanitize_title( (string) $title );
        if ( '' === $base ) {
                $fallback_id = (int) $exclude_book_id;
                return $fallback_id > 0 ? 'book-' . $fallback_id : '';
        }

        if ( ! prs_book_slug_exists( $base, $exclude_book_id ) ) {
                return $base;
        }

        if ( $year ) {
                $with_year = $base . '-' . (int) $year;
                if ( ! prs_book_slug_exists( $with_year, $exclude_book_id ) ) {
                        return $with_year;
                }
        }

        return '';
}

function prs_set_primary_book_slug( $book_id, $slug ) {
        global $wpdb;

        $book_id = (int) $book_id;
        $slug = is_string( $slug ) ? trim( $slug ) : '';
        if ( $book_id <= 0 || '' === $slug || ! prs_books_slugs_table_exists() ) {
                return;
        }

        $table = prs_books_slugs_table_name();

        $wpdb->update(
                $table,
                array( 'is_primary' => 0 ),
                array( 'book_id' => $book_id ),
                array( '%d' ),
                array( '%d' )
        );

        $existing_id = $wpdb->get_var(
                $wpdb->prepare(
                        "SELECT id FROM {$table} WHERE slug = %s LIMIT 1",
                        $slug
                )
        );

        if ( $existing_id ) {
                $wpdb->update(
                        $table,
                        array(
                                'book_id'    => $book_id,
                                'is_primary' => 1,
                                'updated_at' => current_time( 'mysql' ),
                        ),
                        array( 'id' => (int) $existing_id ),
                        array( '%d', '%d', '%s' ),
                        array( '%d' )
                );
                return;
        }

        $wpdb->insert(
                $table,
                array(
                        'book_id'    => $book_id,
                        'slug'       => $slug,
                        'is_primary' => 1,
                        'created_at' => current_time( 'mysql' ),
                        'updated_at' => current_time( 'mysql' ),
                ),
                array( '%d', '%s', '%d', '%s', '%s' )
        );
}

function prs_add_book_slug_alias( $book_id, $slug ) {
        global $wpdb;

        $book_id = (int) $book_id;
        $slug = is_string( $slug ) ? trim( $slug ) : '';
        if ( $book_id <= 0 || '' === $slug || ! prs_books_slugs_table_exists() ) {
                return;
        }

        $table = prs_books_slugs_table_name();
        $existing_id = $wpdb->get_var(
                $wpdb->prepare(
                        "SELECT id FROM {$table} WHERE slug = %s LIMIT 1",
                        $slug
                )
        );

        if ( $existing_id ) {
                return;
        }

        $wpdb->insert(
                $table,
                array(
                        'book_id'    => $book_id,
                        'slug'       => $slug,
                        'is_primary' => 0,
                        'created_at' => current_time( 'mysql' ),
                        'updated_at' => current_time( 'mysql' ),
                ),
                array( '%d', '%s', '%d', '%s', '%s' )
        );
}

function prs_find_or_create_book( $title, $author, $year = null, $attachment_id = null, $all_authors = null, $source = 'candidate' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'politeia_books';

        $title  = trim( wp_strip_all_tags( $title ) );
        $author = trim( wp_strip_all_tags( $author ) );

        if ( $title === '' || $author === '' ) {
                return new WP_Error( 'prs_invalid_book', 'Missing title/author' );
        }

        $normalized_title = prs_normalize_title( $title );
        $normalized_title  = $normalized_title !== '' ? $normalized_title : null;

        $slug = prs_generate_book_slug( $title, $year );

        $existing_id = null;
        if ( $slug ) {
                $existing_id = prs_get_book_id_by_slug( $slug );
        }
        $authors_payload = $all_authors;
        if ( $authors_payload instanceof \Traversable ) {
                $authors_payload = iterator_to_array( $authors_payload, false );
        }
        if ( empty( $authors_payload ) ) {
                $authors_payload = array( $author );
        } elseif ( is_array( $authors_payload ) ) {
                $authors_payload[] = $author;
        } else {
                $authors_payload = array( $authors_payload, $author );
        }

        if ( $existing_id ) {
                $book_id = (int) $existing_id;
                if ( $slug && prs_books_slugs_table_exists() ) {
                        $primary_slug = prs_get_primary_slug_for_book( $book_id );
                        if ( '' === $primary_slug ) {
                                prs_set_primary_book_slug( $book_id, $slug );
                        }
                }
                if ( 'confirmed' === $source ) {
                        prs_sync_book_author_links( $book_id, $authors_payload, $source );
                }
                return $book_id;
        }

        if ( 'confirmed' !== $source ) {
                return new WP_Error( 'prs_canonical_write_blocked', 'Canonical writes require confirmation.' );
        }

        $insert_data = array(
                'title'               => $title,
                'year'                => $year ? (int) $year : null,
                'cover_attachment_id' => $attachment_id ? (int) $attachment_id : null,
                'normalized_title'    => $normalized_title,
                'created_at'          => current_time( 'mysql' ),
                'updated_at'          => current_time( 'mysql' ),
        );
        if ( $slug ) {
                $insert_data['slug'] = $slug;
        }

        $inserted = $wpdb->insert(
                $table,
                $insert_data
        );

        if ( false === $inserted ) {
                return new WP_Error( 'prs_insert_failed', $wpdb->last_error ?: 'Could not insert book.' );
        }

        $book_id = (int) $wpdb->insert_id;
        prs_sync_book_author_links( $book_id, $authors_payload, $source );
        if ( $slug ) {
                prs_set_primary_book_slug( $book_id, $slug );
        }

        return $book_id;
}

/**
 * Create book candidates from raw input and optional external lookups.
 *
 * @param array|string $input Raw input: array with title/author/etc or a title string.
 * @param array        $args  Optional args: user_id, input_type, source_note, enqueue, author, raw_response, limit_per_provider.
 *
 * @return array { candidates, queued, skipped, pending, in_shelf, external_best }
 */
function prs_create_book_candidate( $input, $args = array() ) {
        $defaults = array(
                'user_id'            => 0,
                'input_type'         => 'text',
                'source_note'        => 'candidate',
                'enqueue'            => true,
                'author'             => '',
                'raw_response'       => null,
                'limit_per_provider' => 5,
        );
        $args = wp_parse_args( $args, $defaults );

        $user_id = (int) $args['user_id'];
        if ( $user_id <= 0 ) {
                $user_id = get_current_user_id();
        }

        $title = '';
        $author = '';
        $year = null;
        $image = null;
        $raw_candidates = array();

        if ( is_array( $input ) ) {
                if ( isset( $input['candidates'] ) && is_array( $input['candidates'] ) ) {
                        $raw_candidates = $input['candidates'];
                }
                $title  = isset( $input['title'] ) ? (string) $input['title'] : '';
                $author = isset( $input['author'] ) ? (string) $input['author'] : '';
                $year   = isset( $input['year'] ) ? (int) $input['year'] : null;
                $image  = isset( $input['image'] ) ? (string) $input['image'] : null;
        } elseif ( is_string( $input ) ) {
                $title  = $input;
                $author = isset( $args['author'] ) ? (string) $args['author'] : '';
        }

        $candidates = array();

        foreach ( $raw_candidates as $cand ) {
                if ( ! is_array( $cand ) ) {
                        continue;
                }
                $candidates[] = array(
                        'title'  => isset( $cand['title'] ) ? (string) $cand['title'] : '',
                        'author' => isset( $cand['author'] ) ? (string) $cand['author'] : '',
                        'year'   => isset( $cand['year'] ) ? (int) $cand['year'] : null,
                        'image'  => isset( $cand['image'] ) ? (string) $cand['image'] : null,
                        'source' => isset( $cand['source'] ) ? (string) $cand['source'] : 'input',
                );
        }

        if ( '' !== trim( $title ) && '' !== trim( $author ) ) {
                $candidates[] = array(
                        'title'  => $title,
                        'author' => $author,
                        'year'   => $year,
                        'image'  => $image,
                        'source' => 'input',
                );
        }

        $external_best = null;
        if ( '' !== trim( $title ) && '' !== trim( $author ) ) {
                if ( ! class_exists( 'Politeia_Book_External_API' ) && function_exists( 'politeia_chatgpt_safe_require' ) ) {
                        politeia_chatgpt_safe_require( 'modules/book-detection/class-book-external-api.php' );
                }

                if ( class_exists( 'Politeia_Book_External_API' ) ) {
                        $api = new Politeia_Book_External_API();
                        $external_best = $api->search_best_match(
                                $title,
                                $author,
                                array( 'limit_per_provider' => (int) $args['limit_per_provider'] )
                        );
                        if ( is_array( $external_best ) && ! empty( $external_best['title'] ) && ! empty( $external_best['author'] ) ) {
                                $candidates[] = array(
                                        'title'  => (string) $external_best['title'],
                                        'author' => (string) $external_best['author'],
                                        'year'   => isset( $external_best['year'] ) ? (int) $external_best['year'] : null,
                                        'image'  => null,
                                        'source' => isset( $external_best['source'] ) ? (string) $external_best['source'] : 'external',
                                );
                        }
                }
        }

        $deduped = array();
        $seen = array();
        foreach ( $candidates as $cand ) {
                $t = isset( $cand['title'] ) ? trim( (string) $cand['title'] ) : '';
                $a = isset( $cand['author'] ) ? trim( (string) $cand['author'] ) : '';
                if ( '' === $t || '' === $a ) {
                        continue;
                }
                $key_t = function_exists( 'politeia__normalize_text' ) ? politeia__normalize_text( $t ) : strtolower( $t );
                $key_a = function_exists( 'politeia__normalize_text' ) ? politeia__normalize_text( $a ) : strtolower( $a );
                $key = $key_t . '|' . $key_a;
                if ( isset( $seen[ $key ] ) ) {
                        continue;
                }
                $seen[ $key ] = true;
                $deduped[] = $cand;
        }

        $queue_result = array(
                'queued'   => 0,
                'skipped'  => 0,
                'pending'  => array(),
                'in_shelf' => array(),
        );

        if ( $args['enqueue'] && function_exists( 'politeia_chatgpt_queue_confirm_items' ) ) {
                $queue_result = politeia_chatgpt_queue_confirm_items(
                        $deduped,
                        array(
                                'user_id'      => $user_id,
                                'input_type'   => (string) $args['input_type'],
                                'source_note'  => (string) $args['source_note'],
                                'raw_response' => $args['raw_response'],
                        )
                );
        }

        return array_merge(
                array(
                        'candidates'    => $deduped,
                        'external_best' => $external_best,
                ),
                $queue_result
        );
}

/**
 * Promote a confirmed candidate row to canonical data and attach to a user.
 *
 * @param int      $candidate_id Confirm queue row ID.
 * @param int      $user_id      User to attach book to.
 * @param int|null $year_override Optional year to apply if present.
 *
 * @return array|\WP_Error { book_id, user_book_id, created }
 */
function prs_promote_candidate_to_canonical( $candidate_id, $user_id, $year_override = null ) {
        global $wpdb;

        $candidate_id = (int) $candidate_id;
        $user_id      = (int) $user_id;

        if ( $candidate_id <= 0 || $user_id <= 0 ) {
                return new WP_Error( 'prs_invalid_candidate', 'Invalid candidate or user.' );
        }

        $tbl_confirm = $wpdb->prefix . 'politeia_book_confirm';
        $row         = $wpdb->get_row(
                $wpdb->prepare(
                        "SELECT * FROM {$tbl_confirm} WHERE id=%d AND user_id=%d LIMIT 1",
                        $candidate_id,
                        $user_id
                ),
                ARRAY_A
        );

        if ( ! $row ) {
            return new WP_Error( 'prs_candidate_missing', 'Candidate not found.' );
        }

        $title  = isset( $row['title'] ) ? trim( (string) $row['title'] ) : '';
        $author = isset( $row['author'] ) ? trim( (string) $row['author'] ) : '';

        if ( '' === $title || '' === $author ) {
                return new WP_Error( 'prs_candidate_invalid', 'Candidate is missing title or author.' );
        }

        $raw_response = array();
        if ( ! empty( $row['raw_response'] ) ) {
                $decoded = json_decode( (string) $row['raw_response'], true );
                if ( is_array( $decoded ) ) {
                        $raw_response = $decoded;
                }
        }

        $raw_payload   = isset( $raw_response['raw_payload'] ) && is_array( $raw_response['raw_payload'] )
                ? $raw_response['raw_payload']
                : array();
        $original      = isset( $raw_response['original_input'] ) && is_array( $raw_response['original_input'] )
                ? $raw_response['original_input']
                : array();

        $year = null;
        if ( null !== $year_override ) {
                $year = (int) $year_override;
        } elseif ( isset( $original['year'] ) && $original['year'] !== '' ) {
                $year = (int) $original['year'];
        } elseif ( isset( $raw_payload['year'] ) && $raw_payload['year'] !== '' ) {
                $year = (int) $raw_payload['year'];
        }

        $attachment_id = isset( $raw_payload['cover_attachment_id'] ) ? (int) $raw_payload['cover_attachment_id'] : null;
        $all_authors   = isset( $raw_payload['authors'] ) ? $raw_payload['authors'] : null;

        $books_table = $wpdb->prefix . 'politeia_books';
        $slug = prs_generate_book_slug( $title, $year );
        $existing_id = 0;
        if ( $slug ) {
                $existing_id = (int) prs_get_book_id_by_slug( $slug );
        }

        $book_id = 0;
        if ( $existing_id ) {
                $book_id = (int) $existing_id;
                prs_sync_book_author_links( $book_id, $all_authors, 'confirmed' );
        } else {
                $book_id = prs_find_or_create_book( $title, $author, $year, $attachment_id, $all_authors, 'confirmed' );
                if ( is_wp_error( $book_id ) ) {
                        return $book_id;
                }
                $book_id = (int) $book_id;
        }

        if ( $book_id <= 0 ) {
                return new WP_Error( 'prs_promote_failed', 'Failed to resolve canonical book.' );
        }

        if ( $year ) {
                $wpdb->query(
                        $wpdb->prepare(
                                "UPDATE {$books_table} SET year=%d WHERE id=%d AND (year IS NULL OR year=0)",
                                $year,
                                $book_id
                        )
                );
        }

        $user_book_id = prs_ensure_user_book( $user_id, $book_id );
        if ( ! $user_book_id ) {
                return new WP_Error( 'prs_user_book_failed', 'Could not attach book to user.' );
        }

        return array(
                'book_id'      => $book_id,
                'user_book_id' => (int) $user_book_id,
                'created'      => ( $existing_id > 0 ) ? false : true,
        );
}

/**
 * Diagnostic: inventory canonical integrity gaps (read-only).
 *
 * @param int $limit Optional limit for number of books inspected (0 = no limit).
 * @return array{rows:array<int,array>,counts:array<string,int>}
 */
/**
 * Diagnostic: detect canonical identity collisions without title_author_hash (read-only).
 *
 * @return array{total_books:int,unique_identities:int,collisions:int,collision_details:array<int,array>}
 */
function prs_diagnose_canonical_identity_collisions() {
        global $wpdb;

        $books_table = $wpdb->prefix . 'politeia_books';
        $pivot_table = $wpdb->prefix . 'politeia_book_authors';

        $sql = "
                SELECT b.id, b.normalized_title, b.year,
                       GROUP_CONCAT(ba.author_id ORDER BY ba.sort_order ASC SEPARATOR ',') AS author_ids
                FROM {$books_table} b
                LEFT JOIN {$pivot_table} ba ON ba.book_id = b.id
                GROUP BY b.id
                ORDER BY b.id ASC
        ";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results( $sql, ARRAY_A );

        $identity_map = array();

        foreach ( (array) $rows as $row ) {
                $normalized_title = isset( $row['normalized_title'] ) ? trim( (string) $row['normalized_title'] ) : '';
                $author_ids = isset( $row['author_ids'] ) && $row['author_ids'] !== null ? (string) $row['author_ids'] : '';
                $year = isset( $row['year'] ) && $row['year'] !== null && $row['year'] !== '' ? (string) $row['year'] : '';

                $identity = $normalized_title . '|' . $author_ids;
                if ( '' !== $year ) {
                        $identity .= '|' . $year;
                }

                if ( ! isset( $identity_map[ $identity ] ) ) {
                        $identity_map[ $identity ] = array();
                }
                $identity_map[ $identity ][] = (int) $row['id'];
        }

        $collision_details = array();
        foreach ( $identity_map as $identity => $ids ) {
                if ( count( $ids ) > 1 ) {
                        $collision_details[] = array(
                                'identity' => $identity,
                                'book_ids' => array_values( $ids ),
                        );
                }
        }

        return array(
                'total_books'       => count( $rows ),
                'unique_identities' => count( $identity_map ),
                'collisions'        => count( $collision_details ),
                'collision_details' => $collision_details,
        );
}

function prs_ensure_user_book( $user_id, $book_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'politeia_user_books';

	// Unique (user_id, book_id)
        $row = $wpdb->get_row(
                $wpdb->prepare(
                        "SELECT id, deleted_at FROM {$table} WHERE user_id = %d AND book_id = %d LIMIT 1",
                        $user_id,
                        $book_id
                )
        );
        if ( $row ) {
                $id = (int) $row->id;
                if ( ! empty( $row->deleted_at ) ) {
                        $wpdb->update(
                                $table,
                                array(
                                        'deleted_at' => null,
                                        'updated_at' => current_time( 'mysql' ),
                                ),
                                array( 'id' => $id )
                        );
                }
                return $id;
        }

	$wpdb->insert(
		$table,
		array(
			'user_id'        => (int) $user_id,
			'book_id'        => (int) $book_id,
			'reading_status' => 'not_started',
			'owning_status'  => 'in_shelf',
			'created_at'     => current_time( 'mysql' ),
			'updated_at'     => current_time( 'mysql' ),
		)
	);
	return (int) $wpdb->insert_id;
}

function prs_handle_cover_upload( $field_name = 'prs_cover' ) {
        if ( empty( $_FILES[ $field_name ]['name'] ) ) {
                return null;
        }

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$file = wp_handle_upload( $_FILES[ $field_name ], array( 'test_form' => false ) );
	if ( isset( $file['error'] ) ) {
		return null;
	}

	$attachment  = array(
		'post_mime_type' => $file['type'],
		'post_title'     => sanitize_file_name( basename( $file['file'] ) ),
		'post_content'   => '',
		'post_status'    => 'inherit',
	);
	$attach_id   = wp_insert_attachment( $attachment, $file['file'] );
	$attach_data = wp_generate_attachment_metadata( $attach_id, $file['file'] );
        wp_update_attachment_metadata( $attach_id, $attach_data );
        return (int) $attach_id;
}

/**
 * Synchronize canonical author entries and the pivot table that links books to authors.
 *
 * @param int          $book_id Book identifier from wp_politeia_books.
 * @param array|string $authors Array of author names or a delimited string.
 *
 * @return int[] Ordered list of author IDs linked to the book.
 */
function prs_sync_book_author_links( $book_id, $authors, $source = 'candidate' ) {
        global $wpdb;

        $book_id = (int) $book_id;
        if ( $book_id <= 0 ) {
                return array();
        }

        if ( 'confirmed' !== $source ) {
                return array();
        }

        if ( null === $authors ) {
                $authors = array();
        } elseif ( is_string( $authors ) ) {
                // Split on common delimiters while preserving names that include commas via JSON/array input.
                $parts   = preg_split( '/[;,\|]+/', $authors );
                $authors = is_array( $parts ) ? $parts : array( $authors );
        } elseif ( $authors instanceof \Traversable ) {
                $authors = iterator_to_array( $authors, false );
        } elseif ( ! is_array( $authors ) ) {
                $authors = array( $authors );
        }

        $canonical = array();
        $position  = 0;

        foreach ( $authors as $raw_author ) {
                $name = trim( wp_strip_all_tags( (string) $raw_author ) );
                if ( '' === $name ) {
                        continue;
                }

                $normalized = function_exists( 'politeia__normalize_text' ) ? politeia__normalize_text( $name ) : strtolower( $name );
                $normalized = ( '' !== trim( (string) $normalized ) ) ? $normalized : null;

                $hash_source = $normalized ?: strtolower( $name );
                if ( '' === $hash_source ) {
                        continue;
                }

                $hash = hash( 'sha256', $hash_source );

                if ( isset( $canonical[ $hash ] ) ) {
                        continue; // Mantén el primer orden declarado.
                }

                $canonical[ $hash ] = array(
                        'name'       => $name,
                        'normalized' => $normalized,
                        'hash'       => $hash,
                        'position'   => $position,
                );
                $position++;
        }

        $book_author_table = $wpdb->prefix . 'politeia_book_authors';
        if ( empty( $canonical ) ) {
                // Sin autores => limpia vínculos existentes.
                $wpdb->delete( $book_author_table, array( 'book_id' => $book_id ) );
                return array();
        }

        uasort(
                $canonical,
                static function ( $a, $b ) {
                        return $a['position'] <=> $b['position'];
                }
        );

        $authors_table = $wpdb->prefix . 'politeia_authors';
        $hashes        = array_keys( $canonical );
        $existing      = array();

        if ( ! empty( $hashes ) ) {
                $placeholders = implode( ', ', array_fill( 0, count( $hashes ), '%s' ) );
                $sql          = "SELECT id, name_hash FROM {$authors_table} WHERE name_hash IN ({$placeholders})";
                $rows         = $wpdb->get_results( $wpdb->prepare( $sql, $hashes ) );

                foreach ( $rows as $row ) {
                        $existing[ $row->name_hash ] = (int) $row->id;
                }
        }

        $now = current_time( 'mysql', true );

        foreach ( $canonical as $hash => &$author ) {
                if ( isset( $existing[ $hash ] ) ) {
                        $author['id'] = $existing[ $hash ];
                        continue;
                }

                $slug_base = sanitize_title( $author['name'] );
                $slug      = prs_generate_unique_author_slug( $slug_base, $authors_table, $hash );

                $wpdb->insert(
                        $authors_table,
                        array(
                                'display_name'    => $author['name'],
                                'normalized_name' => $author['normalized'],
                                'name_hash'       => $author['hash'],
                                'slug'            => $slug,
                                'created_at'      => $now,
                                'updated_at'      => $now,
                        ),
                        array( '%s', '%s', '%s', '%s', '%s', '%s' )
                );

                if ( $wpdb->last_error ) {
                        continue;
                }

                $author['id'] = (int) $wpdb->insert_id;
        }
        unset( $author );

        $author_ids = array();
        foreach ( $canonical as $author ) {
                if ( empty( $author['id'] ) ) {
                        continue;
                }
                $author_ids[] = (int) $author['id'];
        }

        $existing_links = $wpdb->get_results(
                $wpdb->prepare( "SELECT id, author_id FROM {$book_author_table} WHERE book_id = %d", $book_id )
        );
        $existing_map   = array();

        foreach ( $existing_links as $link ) {
                $existing_map[ (int) $link->author_id ] = (int) $link->id;
        }

        $position = 0;
        foreach ( $canonical as $author ) {
                if ( empty( $author['id'] ) ) {
                        continue;
                }

                $author_id = (int) $author['id'];

                if ( isset( $existing_map[ $author_id ] ) ) {
                        $wpdb->update(
                                $book_author_table,
                                array(
                                        'sort_order' => $position,
                                        'updated_at' => $now,
                                ),
                                array( 'id' => $existing_map[ $author_id ] ),
                                array( '%d', '%s' ),
                                array( '%d' )
                        );

                        unset( $existing_map[ $author_id ] );
                } else {
                        $wpdb->insert(
                                $book_author_table,
                                array(
                                        'book_id'    => $book_id,
                                        'author_id'  => $author_id,
                                        'sort_order' => $position,
                                        'created_at' => $now,
                                        'updated_at' => $now,
                                ),
                                array( '%d', '%d', '%d', '%s', '%s' )
                        );
                }

                $position++;
        }

        if ( ! empty( $existing_map ) ) {
                $ids_to_delete = array_values( $existing_map );
                $placeholders  = implode( ', ', array_fill( 0, count( $ids_to_delete ), '%d' ) );
                $wpdb->query(
                        $wpdb->prepare(
                                "DELETE FROM {$book_author_table} WHERE id IN ({$placeholders})",
                                $ids_to_delete
                        )
                );
        }

        return $author_ids;
}

/**
 * Generate a unique slug for an author entry.
 */
function prs_generate_unique_author_slug( $base_slug, $table, $hash_source = '' ) {
        global $wpdb;

        $slug = $base_slug;
        if ( '' === $slug ) {
                $slug      = 'author-' . substr( $hash_source ?: hash( 'sha256', microtime( true ) ), 0, 8 );
                $base_slug = $slug;
        }

        $candidate = $slug;
        $suffix    = 2;

        while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s LIMIT 1", $candidate ) ) ) {
                $candidate = $base_slug . '-' . $suffix;

                if ( strlen( $candidate ) > 191 ) {
                        $candidate = substr( $base_slug, 0, max( 1, 191 - strlen( (string) $suffix ) - 1 ) ) . '-' . $suffix;
                }

                $suffix++;

                if ( $suffix > 20 ) {
                        $fallback  = $hash_source ?: hash( 'crc32', $base_slug . microtime( true ) );
                        $candidate = substr( $base_slug, 0, 180 ) . '-' . substr( $fallback, 0, 8 );
                        break;
                }
        }

        return $candidate;
}

function prs_maybe_alter_user_books() {
	global $wpdb;
	$t = $wpdb->prefix . 'politeia_user_books';

	$cols = $wpdb->get_col(
		$wpdb->prepare(
			'SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s',
			DB_NAME,
			$t
		)
	);
	$has  = array_map( 'strtolower', (array) $cols );

        $alters = array();

        $has_type_book = in_array( 'type_book', $has, true );
        if ( ! $has_type_book ) {
                $alters[]     = "ADD COLUMN type_book ENUM('p','d') NULL DEFAULT NULL AFTER owning_status";
                $has_type_book = true;
        }

        if ( ! in_array( 'pages', $has, true ) ) {
                $after_column = $has_type_book ? 'type_book' : 'owning_status';
                $alters[]     = sprintf( 'ADD COLUMN pages INT UNSIGNED NULL AFTER %s', $after_column );
        }
	if ( ! in_array( 'purchase_date', $has, true ) ) {
		$alters[] = 'ADD COLUMN purchase_date DATE NULL';
	}
	if ( ! in_array( 'purchase_channel', $has, true ) ) {
		$alters[] = "ADD COLUMN purchase_channel ENUM('online','store') NULL";
	}
	if ( ! in_array( 'purchase_place', $has, true ) ) {
		$alters[] = 'ADD COLUMN purchase_place VARCHAR(255) NULL';
	}
	if ( ! in_array( 'counterparty_name', $has, true ) ) {
		$alters[] = 'ADD COLUMN counterparty_name VARCHAR(255) NULL';
	}
	if ( ! in_array( 'counterparty_email', $has, true ) ) {
		$alters[] = 'ADD COLUMN counterparty_email VARCHAR(190) NULL';
	}

	if ( $alters ) {
		$wpdb->query( "ALTER TABLE {$t} " . implode( ', ', $alters ) );
	}
}
add_action( 'plugins_loaded', 'prs_maybe_alter_user_books' );

function prs_maybe_create_loans_table() {
	global $wpdb;
	$table = $wpdb->prefix . 'politeia_loans';

	$exists = $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s',
			DB_NAME,
			$table
		)
	);

        if ( ! $exists ) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                $charset_collate = $wpdb->get_charset_collate();
                $sql             = "CREATE TABLE {$table} (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id BIGINT UNSIGNED NOT NULL,
          book_id BIGINT UNSIGNED NOT NULL,
          counterparty_name  VARCHAR(255) NULL,
          counterparty_email VARCHAR(190) NULL,
          amount DECIMAL(10,2) NULL,
          start_date DATETIME NOT NULL,
          end_date   DATETIME NULL,
          notes TEXT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_user_book (user_id, book_id),
          KEY idx_active (user_id, book_id, end_date)
        ) {$charset_collate};";
                dbDelta( $sql );
        }

        $columns = $wpdb->get_col(
                $wpdb->prepare(
                        'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s',
                        DB_NAME,
                        $table
                )
        );

        if ( $columns && ! in_array( 'amount', array_map( 'strtolower', $columns ), true ) ) {
                $wpdb->query( "ALTER TABLE {$table} ADD COLUMN amount DECIMAL(10,2) NULL AFTER counterparty_email" );
        }
}
add_action( 'plugins_loaded', 'prs_maybe_create_loans_table' );

/**
 * Render the reusable owning overlay markup once per request.
 *
 * @param array $args {
 *     Optional. Arguments to customize the overlay.
 *
 *     @type string $heading Default heading text.
 * }
 *
 * Note: The browser validates the email format via the HTML `type="email"` attribute.
 * For deeper verification (such as ensuring the domain can receive mail), consider
 * adding a lightweight server-side MX or domain existence check instead of attempting
 * full mailbox validation.
 */
function prs_render_owning_overlay( $args = array() ) {
        static $rendered = false;

        if ( $rendered ) {
                return;
        }

        $defaults = array(
                'heading' => __( 'Borrowing to:', 'politeia-reading' ),
        );

        $args     = wp_parse_args( $args, $defaults );
        $heading  = is_string( $args['heading'] ) ? $args['heading'] : '';
        $rendered = true;

        ?>
        <div id="owning-overlay" class="prs-overlay" style="display:none;">
                <div class="prs-overlay-backdrop"></div>

                <div class="prs-overlay-content">
                        <h2 id="owning-overlay-title"><?php echo esc_html( $heading ); ?></h2>

                        <input type="text" id="owning-overlay-name" class="prs-contact-input" placeholder="<?php echo esc_attr__( 'Name', 'politeia-reading' ); ?>" required>
                        <input type="email" id="owning-overlay-email" class="prs-contact-input" placeholder="<?php echo esc_attr__( 'Email', 'politeia-reading' ); ?>" required>
                        <input type="number" id="owning-overlay-amount" class="prs-contact-input" placeholder="<?php echo esc_attr__( 'Amount (e.g. 12000)', 'politeia-reading' ); ?>" step="0.01" style="display:none;">

                        <div class="prs-overlay-actions">
                                <button type="button" id="owning-overlay-confirm" class="prs-btn"><?php esc_html_e( 'Confirm', 'politeia-reading' ); ?></button>
                                <button type="button" id="owning-overlay-cancel" class="prs-btn prs-btn-secondary"><?php esc_html_e( 'Cancel', 'politeia-reading' ); ?></button>
                        </div>

                        <span id="owning-overlay-status" class="prs-help"></span>
                </div>
        </div>
        <div id="bought-overlay" class="prs-overlay" style="display:none;">
                <div class="prs-overlay-backdrop"></div>
                <div class="prs-overlay-content" style="max-width:360px;">
                        <h2><?php esc_html_e( 'Confirm Re-acquisition', 'politeia-reading' ); ?></h2>
                        <p><?php esc_html_e( 'You are marking this book as Bought Again. It will return to your shelf and become editable.', 'politeia-reading' ); ?></p>
                        <div class="prs-overlay-actions">
                                <button type="button" id="bought-overlay-confirm" class="prs-btn"><?php esc_html_e( 'Confirm', 'politeia-reading' ); ?></button>
                                <button type="button" id="bought-overlay-cancel" class="prs-btn prs-btn-secondary"><?php esc_html_e( 'Cancel', 'politeia-reading' ); ?></button>
                        </div>
                </div>
        </div>
        <?php
}

function prs_get_owning_labels() {
        return array(
                'borrowing'    => __( 'Borrowing to:', 'politeia-reading' ),
                'borrowed'     => __( 'Borrowed from:', 'politeia-reading' ),
                'sold'         => __( 'Sold to:', 'politeia-reading' ),
                'lost'         => __( 'Last borrowed to:', 'politeia-reading' ),
                'sold_on'      => __( 'Sold on:', 'politeia-reading' ),
                'lost_date'    => __( 'Lost:', 'politeia-reading' ),
                'location'     => __( 'Location', 'politeia-reading' ),
                'in_shelf'     => __( 'In Shelf', 'politeia-reading' ),
                'not_in_shelf' => __( 'Not In Shelf', 'politeia-reading' ),
                'unknown'      => __( 'Unknown', 'politeia-reading' ),
        );
}

function prs_get_user_books_for_library( $user_id, $args = array() ) {
        global $wpdb;

        $defaults = array(
                'per_page' => 0,
                'offset'   => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) {
                return array();
        }

        $per_page = (int) $args['per_page'];
        $offset   = max( 0, (int) $args['offset'] );

        $ub = $wpdb->prefix . 'politeia_user_books';
        $b  = $wpdb->prefix . 'politeia_books';
        $l  = $wpdb->prefix . 'politeia_loans';
        $ba = $wpdb->prefix . 'politeia_book_authors';
        $a  = $wpdb->prefix . 'politeia_authors';

        static $books_has_total_pages = null;
        if ( null === $books_has_total_pages ) {
                $books_has_total_pages = (bool) $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$b} LIKE %s", 'total_pages' ) );
        }

        $book_pages_select = $books_has_total_pages ? 'b.total_pages' : 'NULL';

        $sql = "
        SELECT
                ub.id AS user_book_id,
                ub.reading_status,
                ub.owning_status,
                ub.type_book,
                ub.pages,
                ub.counterparty_name,
                ub.counterparty_email,
                ub.cover_reference,
                (
                        SELECT start_date
                        FROM {$l} l
                        WHERE l.user_id = ub.user_id
                          AND l.book_id = ub.book_id
                          AND l.end_date IS NULL
                        ORDER BY l.id DESC
                        LIMIT 1
                ) AS active_loan_start,
                b.id AS book_id,
                b.title,
                b.year,
                b.cover_attachment_id,
                b.slug,
                (
                        SELECT GROUP_CONCAT(a.display_name ORDER BY ba.sort_order ASC SEPARATOR ', ')
                        FROM {$ba} ba
                        LEFT JOIN {$a} a ON a.id = ba.author_id
                        WHERE ba.book_id = b.id
                ) AS authors,
                {$book_pages_select} AS book_total_pages
        FROM {$ub} ub
        JOIN {$b} b ON b.id = ub.book_id
        WHERE ub.user_id = %d
          AND ub.deleted_at IS NULL
          AND (ub.owning_status IS NULL OR ub.owning_status != 'deleted')
        ORDER BY b.title ASC";

        $params = array( $user_id );

        if ( $per_page > 0 ) {
                $sql     .= ' LIMIT %d OFFSET %d';
                $params[] = $per_page;
                $params[] = $offset;
        }

        $prepared = $wpdb->prepare( $sql, $params );

        return $wpdb->get_results( $prepared );
}

function prs_render_book_row( $book, $context = array() ) {
        if ( ! $book ) {
                return '';
        }

        $defaults = array(
                'user_id'       => get_current_user_id(),
                'owning_labels' => prs_get_owning_labels(),
        );

        $context = wp_parse_args( $context, $defaults );

        $user_id       = isset( $context['user_id'] ) ? (int) $context['user_id'] : get_current_user_id();
        $owning_labels = isset( $context['owning_labels'] ) && is_array( $context['owning_labels'] ) ? $context['owning_labels'] : prs_get_owning_labels();

        $labels = wp_parse_args(
                $owning_labels,
                array(
                        'borrowing'    => __( 'Borrowing to:', 'politeia-reading' ),
                        'borrowed'     => __( 'Borrowed from:', 'politeia-reading' ),
                        'sold'         => __( 'Sold to:', 'politeia-reading' ),
                        'lost'         => __( 'Last borrowed to:', 'politeia-reading' ),
                        'location'     => __( 'Location', 'politeia-reading' ),
                        'in_shelf'     => __( 'In Shelf', 'politeia-reading' ),
                        'not_in_shelf' => __( 'Not In Shelf', 'politeia-reading' ),
                        'unknown'      => __( 'Unknown', 'politeia-reading' ),
                )
        );

        $label_borrowing    = $labels['borrowing'];
        $label_borrowed     = $labels['borrowed'];
        $label_sold         = $labels['sold'];
        $label_lost         = $labels['lost'];
        $label_location     = $labels['location'];
        $label_in_shelf     = $labels['in_shelf'];
        $label_not_in_shelf = $labels['not_in_shelf'];
        $label_unknown      = $labels['unknown'];

        $authors_value = isset( $book->authors ) ? (string) $book->authors : '';
        $slug = $book->slug ? $book->slug : prs_generate_book_slug( $book->title, $book->year ?? null );
        $url  = home_url( '/my-books/my-book-' . $slug );

        $year            = $book->year ? (int) $book->year : null;
        $pages           = $book->pages ? (int) $book->pages : null;
        $book_total_page = isset( $book->book_total_pages ) ? (int) $book->book_total_pages : 0;
        $effective_pages = $book_total_page > 0 ? $book_total_page : ( $pages ?? 0 );
        $progress        = 0;

        $owning_status   = isset( $book->owning_status ) ? (string) $book->owning_status : '';
        $row_owning_attr = $owning_status ? $owning_status : 'in_shelf';
        $reading_status  = isset( $book->reading_status ) ? (string) $book->reading_status : '';
        $author_value    = $authors_value;
        $title_value     = isset( $book->title ) ? (string) $book->title : '';

        if ( class_exists( 'Politeia_Reading_Sessions' ) && $effective_pages > 0 ) {
                $progress = Politeia_Reading_Sessions::calculate_progress_percent( $user_id, (int) $book->book_id, $effective_pages );
        }

        $reading_id  = 'reading-status-' . (int) $book->user_book_id;
        $owning_id   = 'owning-status-' . (int) $book->user_book_id;
        $progress_id = 'reading-progress-' . (int) $book->user_book_id;

        $loan_contact_name  = isset( $book->counterparty_name ) ? trim( (string) $book->counterparty_name ) : '';
        $loan_contact_email = isset( $book->counterparty_email ) ? trim( (string) $book->counterparty_email ) : '';
        $is_digital         = ( isset( $book->type_book ) && 'd' === $book->type_book );

        $active_start_local = '';
        if ( ! empty( $book->active_loan_start ) ) {
                $converted = get_date_from_gmt( $book->active_loan_start, 'Y-m-d' );
                if ( $converted ) {
                        $active_start_local = $converted;
                }
        }

        $year_text      = $year ? sprintf( __( 'Published: %s', 'politeia-reading' ), $year ) : __( 'Published: —', 'politeia-reading' );
        $pages_value    = $pages ? (int) $pages : '';
        $pages_display  = $pages ? (string) (int) $pages : '';
        $pages_input_id = 'prs-pages-input-' . (int) $book->user_book_id;

        $progress_label = sprintf( __( '%s%% complete', 'politeia-reading' ), (int) $progress );

        $current_select_value = $owning_status ? $owning_status : 'in_shelf';
        $stored_status        = $owning_status ? $owning_status : '';

        $owning_info_lines = array();

        if ( in_array( $owning_status, array( 'borrowed', 'borrowing', 'sold' ), true ) ) {
                $label_map   = array(
                        'borrowed'  => $label_borrowed,
                        'borrowing' => $label_borrowing,
                        'sold'      => $label_sold,
                );
                $info_label  = isset( $label_map[ $owning_status ] ) ? $label_map[ $owning_status ] : '';
                $display_name = $loan_contact_name ? $loan_contact_name : $label_unknown;

                if ( $info_label ) {
                        $owning_info_lines[] = '<strong>' . esc_html( $info_label ) . '</strong>';
                }

                $owning_info_lines[] = esc_html( $display_name );

                if ( $active_start_local ) {
                        $owning_info_lines[] = '<small>' . esc_html( $active_start_local ) . '</small>';
                }
        } elseif ( 'lost' === $owning_status ) {
                $owning_info_lines[] = sprintf(
                        '<strong>%s</strong>: %s',
                        esc_html( $label_location ),
                        esc_html( $label_not_in_shelf )
                );

                if ( $loan_contact_name ) {
                        $owning_info_lines[] = sprintf(
                                '<strong>%s</strong> %s',
                                esc_html( $label_lost ),
                                esc_html( $loan_contact_name )
                        );
                }
        } else {
                $owning_info_lines[] = sprintf(
                        '<strong>%s</strong>: %s',
                        esc_html( $label_location ),
                        esc_html( $label_in_shelf )
                );
        }

        $owning_info_html = implode( '<br>', $owning_info_lines );
        $owning_info_display = $owning_info_html ? wp_kses(
                $owning_info_html,
                array(
                        'strong' => array(),
                        'br'     => array(),
                        'small'  => array(),
                )
        ) : '';

        $reading_disabled       = in_array( $owning_status, array( 'borrowing', 'borrowed' ), true );
        $reading_disabled_text  = __( 'Disabled while this book is being borrowed.', 'politeia-reading' );
        $reading_disabled_class = $reading_disabled ? ' is-disabled' : '';
        $reading_disabled_title = $reading_disabled ? ' title="' . esc_attr( $reading_disabled_text ) . '"' : '';

        $user_cover_raw = '';
        if ( isset( $book->cover_reference ) && '' !== $book->cover_reference && null !== $book->cover_reference ) {
                $user_cover_raw = $book->cover_reference;
        } elseif ( isset( $book->cover_attachment_id_user ) ) {
                $user_cover_raw = $book->cover_attachment_id_user;
        }

        $parsed_user_cover = method_exists( 'PRS_Cover_Upload_Feature', 'parse_cover_value' ) ? PRS_Cover_Upload_Feature::parse_cover_value( $user_cover_raw ) : array(
                'attachment_id' => is_numeric( $user_cover_raw ) ? (int) $user_cover_raw : 0,
                'url'           => '',
                'source'        => '',
        );

        $user_cover_id     = isset( $parsed_user_cover['attachment_id'] ) ? (int) $parsed_user_cover['attachment_id'] : 0;
        $user_cover_url    = isset( $parsed_user_cover['url'] ) ? trim( (string) $parsed_user_cover['url'] ) : '';
        $user_cover_url    = $user_cover_url ? esc_url_raw( $user_cover_url ) : '';
        $user_cover_source = isset( $parsed_user_cover['source'] ) ? trim( (string) $parsed_user_cover['source'] ) : '';

        if ( $user_cover_id ) {
                $attachment_source = get_post_meta( $user_cover_id, '_prs_cover_source', true );
                if ( $attachment_source ) {
                        $user_cover_source = $attachment_source;
                }
        }

        $book_cover_id    = isset( $book->cover_attachment_id ) ? (int) $book->cover_attachment_id : 0;
        $book_cover_url   = '';
        $book_cover_source = '';

        if ( $book_cover_id ) {
                $book_cover_url   = wp_get_attachment_image_url( $book_cover_id, 'medium' );
                $book_cover_source = get_post_meta( $book_cover_id, '_prs_cover_source', true );
        }

        $has_user_cover = $user_cover_url || $user_cover_id;

        ob_start();
        ?>
        <tr
                class="prs-library-row"
                data-user-book-id="<?php echo (int) $book->user_book_id; ?>"
                data-owning-status="<?php echo esc_attr( $row_owning_attr ); ?>"
                data-reading-status="<?php echo esc_attr( $reading_status ); ?>"
                data-progress="<?php echo esc_attr( (int) $progress ); ?>"
                data-author="<?php echo esc_attr( $author_value ); ?>"
                data-title="<?php echo esc_attr( $title_value ); ?>"
        >
                <td class="prs-library__info">
                <div class="prs-library__cover">
                <?php
                if ( $has_user_cover ) {
                        if ( $user_cover_url ) {
                                echo '<img class="prs-library__cover-image" src="' . esc_url( $user_cover_url ) . '" alt="' . esc_attr( $book->title ) . '" />';
                                if ( $user_cover_source ) {
                                        echo '<div class="prs-library__cover-attribution"><a href="' . esc_url( $user_cover_source ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View on Google Books', 'politeia-reading' ) . '</a></div>';
                                }
                        } else {
                                echo wp_get_attachment_image(
                                        $user_cover_id,
                                        'medium',
                                        false,
                                        array(
                                                'class' => 'prs-library__cover-image',
                                                'alt'   => esc_attr( $book->title ),
                                        )
                                );
                                if ( $user_cover_source ) {
                                        echo '<div class="prs-library__cover-attribution"><a href="' . esc_url( $user_cover_source ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View on Google Books', 'politeia-reading' ) . '</a></div>';
                                }
                        }
                } elseif ( $book_cover_id ) {
                        if ( $book_cover_url ) {
                                echo '<img class="prs-library__cover-image" src="' . esc_url( $book_cover_url ) . '" alt="' . esc_attr( $book->title ) . '" />';
                                if ( $book_cover_source ) {
                                        echo '<div class="prs-library__cover-attribution"><a href="' . esc_url( $book_cover_source ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View on Google Books', 'politeia-reading' ) . '</a></div>';
                                }
                        } else {
                                echo '<div class="prs-library__cover-placeholder" aria-hidden="true"></div>';
                        }
                } else {
                        echo '<div class="prs-library__cover-placeholder" aria-hidden="true"></div>';
                }
                ?>
                </div>
                <div class="prs-library__details">
                        <a class="prs-library__title" href="<?php echo esc_url( $url ); ?>">
                                <span class="prs-book-title__text"><?php echo esc_html( $book->title ); ?></span>
                        </a>
                        <div class="prs-library__meta">
                                <?php if ( '' !== $author_value ) : ?>
                                <span class="prs-library__meta-item prs-library__author"><span class="prs-book-author"><?php echo esc_html( $author_value ); ?></span></span>
                                <?php endif; ?>
                                <span class="prs-library__meta-item prs-library__year"><?php echo esc_html( $year_text ); ?></span>
                                <span class="prs-library__meta-item prs-library__pages" data-pages="<?php echo esc_attr( $pages_value ); ?>">
                                        <span class="prs-library__pages-display">
                                                <span class="prs-library__pages-label"><?php esc_html_e( 'Pages:', 'politeia-reading' ); ?></span>
                                                <span class="prs-library__pages-value"><?php echo esc_html( $pages_display ); ?></span>
                                                <button type="button" class="prs-library__pages-edit"><?php esc_html_e( 'Edit', 'politeia-reading' ); ?></button>
                                        </span>
                                        <input
                                                type="number"
                                                min="1"
                                                step="1"
                                                inputmode="numeric"
                                                class="prs-library__pages-input"
                                                id="<?php echo esc_attr( $pages_input_id ); ?>"
                                                value="<?php echo esc_attr( $pages_value ); ?>"
                                                aria-label="<?php esc_attr_e( 'Total pages', 'politeia-reading' ); ?>"
                                        />
                                        <span class="prs-library__pages-error" role="alert" aria-live="polite"></span>
                                </span>
                        </div>
                </div>
                </td>
                <td class="prs-library__actions">
                <div class="prs-library__controls">
                        <div class="prs-library__field">
                                <label for="<?php echo esc_attr( $reading_id ); ?>"><?php esc_html_e( 'Reading Status', 'politeia-reading' ); ?></label>
                                <select
                                        id="<?php echo esc_attr( $reading_id ); ?>"
                                        class="prs-reading-status reading-status-select<?php echo esc_attr( $reading_disabled_class ); ?>"
                                        data-disabled-text="<?php echo esc_attr( $reading_disabled_text ); ?>"
                                        aria-disabled="<?php echo $reading_disabled ? 'true' : 'false'; ?>"<?php echo $reading_disabled ? ' disabled="disabled"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $reading_disabled_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                >
                                <?php
                                $reading = array(
                                        'not_started' => __( 'Not Started', 'politeia-reading' ),
                                        'started'     => __( 'Started', 'politeia-reading' ),
                                        'finished'    => __( 'Finished', 'politeia-reading' ),
                                );
                                foreach ( $reading as $val => $label ) {
                                        printf(
                                                '<option value="%s"%s>%s</option>',
                                                esc_attr( $val ),
                                                selected( $book->reading_status, $val, false ),
                                                esc_html( $label )
                                        );
                                }
                                ?>
                                </select>
                        </div>
                        <div class="prs-library__field">
                                <label for="<?php echo esc_attr( $owning_id ); ?>"><?php esc_html_e( 'Owning Status', 'politeia-reading' ); ?></label>
                                <select
                                        id="<?php echo esc_attr( $owning_id ); ?>"
                                        class="prs-owning-status owning-status-select<?php echo $is_digital ? ' is-disabled' : ''; ?>"
                                        data-book-id="<?php echo (int) $book->book_id; ?>"
                                        data-user-book-id="<?php echo (int) $book->user_book_id; ?>"
                                        data-current-value="<?php echo esc_attr( $current_select_value ); ?>"
                                        data-stored-status="<?php echo esc_attr( $stored_status ); ?>"
                                        data-contact-name="<?php echo esc_attr( $loan_contact_name ); ?>"
                                        data-contact-email="<?php echo esc_attr( $loan_contact_email ); ?>"
                                        data-active-start="<?php echo esc_attr( $active_start_local ); ?>"
                                        <?php echo $is_digital ? 'disabled="disabled" aria-disabled="true"' : ''; ?>
                                >
                                <option value=""><?php echo esc_html__( '— Select —', 'politeia-reading' ); ?></option>
                                <?php
                                $owning = array(
                                        'in_shelf'  => __( 'In Shelf', 'politeia-reading' ),
                                        'borrowed'  => __( 'Borrowed', 'politeia-reading' ),
                                        'borrowing' => __( 'Lent Out', 'politeia-reading' ),
                                        'bought'    => __( 'Bought', 'politeia-reading' ),
                                        'sold'      => __( 'Sold', 'politeia-reading' ),
                                        'lost'      => __( 'Lost', 'politeia-reading' ),
                                );
                                foreach ( $owning as $val => $label ) {
                                        $selected_attr = selected( $owning_status, $val, false );
                                        if ( 'in_shelf' === $val && '' === $owning_status ) {
                                                $selected_attr = ' selected="selected"';
                                        }
                                        printf(
                                                '<option value="%s"%s>%s</option>',
                                                esc_attr( $val ),
                                                $selected_attr,
                                                esc_html( $label )
                                        );
                                }
                                ?>
                                </select>
                                <?php
                                $show_return_btn  = ! $is_digital && in_array( $owning_status, array( 'borrowed', 'borrowing' ), true );
                                $return_btn_style = $show_return_btn ? '' : 'display:none;';
                                ?>
                                <button
                                        type="button"
                                        class="prs-btn owning-return-shelf"
                                        data-book-id="<?php echo (int) $book->book_id; ?>"
                                        data-user-book-id="<?php echo (int) $book->user_book_id; ?>"
                                        style="<?php echo esc_attr( $return_btn_style ); ?>"
                                        <?php echo $is_digital ? 'disabled="disabled" aria-disabled="true"' : ''; ?>
                                >
                                        <?php esc_html_e( 'Mark as returned', 'politeia-reading' ); ?>
                                </button>
                                <span class="owning-status-info" data-book-id="<?php echo (int) $book->book_id; ?>"><?php echo $owning_info_display; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                <?php if ( $is_digital ) : ?>
                                <div class="prs-owning-status-note"><?php esc_html_e( 'Owning status is available only for printed copies.', 'politeia-reading' ); ?></div>
                                <?php endif; ?>
                        </div>
                </div>
                <div class="prs-library__extras">
                        <div class="prs-library__progress-field">
                                <span id="<?php echo esc_attr( $progress_id ); ?>" class="prs-library__progress-label"><?php esc_html_e( 'Reading Progress', 'politeia-reading' ); ?></span>
                                <div class="prs-library__progress">
                                        <div
                                                class="prs-library__progress-track"
                                                role="progressbar"
                                                aria-valuenow="<?php echo esc_attr( (int) $progress ); ?>"
                                                aria-valuemin="0"
                                                aria-valuemax="100"
                                                aria-valuetext="<?php echo esc_attr( $progress_label ); ?>"
                                                aria-labelledby="<?php echo esc_attr( $progress_id ); ?>"
                                        >
                                                <div class="prs-library__progress-fill" style="width: <?php echo (int) $progress; ?>%;"></div>
                                        </div>
                                        <span class="prs-library__progress-value"><?php echo (int) $progress; ?>%</span>
                                </div>
                        </div>
                        <button
                                type="button"
                                class="prs-library__remove prs-remove-book"
                                data-id="<?php echo esc_attr( $book->user_book_id ); ?>"
                                data-nonce="<?php echo esc_attr( wp_create_nonce( 'remove_user_book_' . (int) $book->user_book_id ) ); ?>"
                                aria-label="<?php esc_attr_e( 'Remove book', 'politeia-reading' ); ?>">
                                <?php esc_html_e( 'Remove', 'politeia-reading' ); ?>
                        </button>
                </div>
                </td>
        </tr>
        <?php

        return ob_get_clean();
}

// Devuelve el start_date (GMT) del loan activo o null
function prs_get_active_loan_start_date( $user_id, $book_id ) {
	global $wpdb;
	$t = $wpdb->prefix . 'politeia_loans';
	return $wpdb->get_var(
		$wpdb->prepare(
			"SELECT start_date FROM {$t}
         WHERE user_id=%d AND book_id=%d AND end_date IS NULL
         ORDER BY id DESC LIMIT 1",
			$user_id,
			$book_id
		)
	);
}
