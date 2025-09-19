<?php

namespace Politeia\ChatGPT\BookDetection {

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Queue confirmation items for a user while avoiding duplicates and recording
 * ephemeral matches that already exist in the user's library.
 *
 * @param mixed      $arg1 Candidates array or user ID depending on signature.
 * @param mixed|null $arg2 Optional metadata or candidates array.
 * @param mixed|null $arg3 Optional input type when using the legacy signature.
 * @param string     $arg4 Optional source note when using the legacy signature.
 *
 * @return array
 */
function queue_confirm_items( $arg1, $arg2 = null, $arg3 = null, $arg4 = '' ) {
    global $wpdb;

    if ( ! class_exists( BookConfirmSchema::class ) ) {
        if ( function_exists( 'politeia_chatgpt_safe_require' ) ) {
            politeia_chatgpt_safe_require( 'modules/book-detection/class-book-confirm-schema.php' );
        }
    }

    if ( class_exists( BookConfirmSchema::class ) ) {
        BookConfirmSchema::ensure();
    }

    $user_id     = 0;
    $candidates  = [];
    $input_type  = 'text';
    $source_note = '';
    $raw_payload = null;

    if ( is_array( $arg1 ) && ( is_array( $arg2 ) || null === $arg2 ) ) {
        $candidates  = (array) $arg1;
        $meta        = is_array( $arg2 ) ? $arg2 : [];
        $user_id     = isset( $meta['user_id'] ) ? (int) $meta['user_id'] : get_current_user_id();
        $input_type  = isset( $meta['input_type'] ) ? sanitize_text_field( $meta['input_type'] ) : 'text';
        $source_note = isset( $meta['source_note'] ) ? sanitize_text_field( $meta['source_note'] ) : '';

        if ( array_key_exists( 'raw_response', $meta ) ) {
            $raw_payload = is_string( $meta['raw_response'] )
                ? $meta['raw_response']
                : wp_json_encode( $meta['raw_response'] );
        }
    } else {
        $user_id     = (int) $arg1;
        $candidates  = (array) $arg2;
        $input_type  = $arg3 ? sanitize_text_field( $arg3 ) : 'text';
        $source_note = $arg4 ? sanitize_text_field( $arg4 ) : '';
    }

    $user_id = $user_id ?: (int) get_current_user_id();

    $table_books   = $wpdb->prefix . 'politeia_books';
    $table_users   = $wpdb->prefix . 'politeia_user_books';
    $table_confirm = $wpdb->prefix . 'politeia_book_confirm';

    $queued   = 0;
    $skipped  = 0;
    $pending  = [];
    $in_shelf = [];

    $user_library_hash  = [];
    $user_library_fuzzy = [];

    if ( class_exists( BookConfirmSchema::class ) ) {
        $sql = $wpdb->prepare(
            "SELECT b.id, b.title, b.author, b.year, b.slug, b.title_author_hash
               FROM {$table_books} b
               JOIN {$table_users} ub ON ub.book_id=b.id AND ub.user_id=%d",
            $user_id
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results( $sql, ARRAY_A );

        foreach ( $rows as $row ) {
            $hash = ! empty( $row['title_author_hash'] ) ? strtolower( (string) $row['title_author_hash'] ) : '';

            if ( $hash ) {
                $user_library_hash[ $hash ] = [
                    'id'   => (int) $row['id'],
                    'year' => isset( $row['year'] ) && '' !== $row['year'] ? (int) $row['year'] : null,
                    'slug' => $row['slug'],
                ];
            }

            $user_library_fuzzy[] = [
                'id'   => (int) $row['id'],
                'year' => isset( $row['year'] ) && '' !== $row['year'] ? (int) $row['year'] : null,
                'slug' => (string) $row['slug'],
                'key'  => strtolower( BookConfirmSchema::compute_title_author_hash( $row['title'] ?? '', $row['author'] ?? '' ) ),
            ];
        }
    }

    $seen_hashes = [];

    foreach ( (array) $candidates as $candidate ) {
        $title  = isset( $candidate['title'] ) ? trim( (string) $candidate['title'] ) : '';
        $author = isset( $candidate['author'] ) ? trim( (string) $candidate['author'] ) : '';

        if ( '' === $title || '' === $author ) {
            ++$skipped;
            continue;
        }

        $normalized_title  = normalize_text( $title );
        $normalized_author = normalize_text( $author );
        $hash              = title_author_hash( $title, $author );
        $hash_lc           = strtolower( $hash );

        if ( isset( $seen_hashes[ $hash_lc ] ) ) {
            ++$skipped;
            continue;
        }

        $seen_hashes[ $hash_lc ] = true;

        $owned_year = null;

        if ( isset( $user_library_hash[ $hash_lc ] ) ) {
            $owned_year = $user_library_hash[ $hash_lc ]['year'];
        } else {
            $owned = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT b.id, b.year
                       FROM {$table_books} b
                       JOIN {$table_users} ub ON ub.book_id=b.id AND ub.user_id=%d
                      WHERE b.title_author_hash=%s
                      LIMIT 1",
                    $user_id,
                    $hash
                ),
                ARRAY_A
            );

            if ( $owned ) {
                $owned_year = ( '' !== $owned['year'] && null !== $owned['year'] ) ? (int) $owned['year'] : null;
            }
        }

        if ( null !== $owned_year || isset( $user_library_hash[ $hash_lc ] ) ) {
            $in_shelf[] = [
                'title'    => $title,
                'author'   => $author,
                'year'     => $owned_year,
                'in_shelf' => true,
            ];
            ++$skipped;
            continue;
        }

        $fuzzy_hit  = null;
        $fuzzy_best = 1.0;

        if ( ! empty( $user_library_fuzzy ) && class_exists( BookConfirmSchema::class ) ) {
            $probe = strtolower( BookConfirmSchema::compute_title_author_hash( $title, $author ) );

            foreach ( $user_library_fuzzy as $row ) {
                $norm_probe = $probe;

                if ( method_exists( BookConfirmSchema::class, 'norm_title_author' ) ) {
                    $norm_probe = BookConfirmSchema::compute_title_author_hash( $title, $author );
                }

                $rel = levenshtein( $norm_probe, $row['key'] ) / max( 1, max( strlen( $norm_probe ), strlen( $row['key'] ) ) );

                if ( $rel < $fuzzy_best ) {
                    $fuzzy_best = $rel;
                    $fuzzy_hit  = $row;
                }
            }
        }

        if ( $fuzzy_hit && $fuzzy_best <= 0.25 ) {
            $in_shelf[] = [
                'title'    => $title,
                'author'   => $author,
                'year'     => $fuzzy_hit['year'] ?? null,
                'in_shelf' => true,
            ];
            ++$skipped;
            continue;
        }

        $pending_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, title, author FROM {$table_confirm}
                  WHERE user_id=%d AND status='pending' AND title_author_hash=%s
                  LIMIT 1",
                $user_id,
                $hash
            ),
            ARRAY_A
        );

        if ( $pending_row ) {
            $pending[] = [
                'id'     => (int) $pending_row['id'],
                'title'  => (string) $pending_row['title'],
                'author' => (string) $pending_row['author'],
                'year'   => null,
            ];
            ++$skipped;
            continue;
        }

        $data = [
            'user_id'           => $user_id,
            'input_type'        => $input_type,
            'source_note'       => $source_note,
            'title'             => $title,
            'author'            => $author,
            'normalized_title'  => $normalized_title,
            'normalized_author' => $normalized_author,
            'title_author_hash' => $hash,
            'status'            => 'pending',
        ];

        $format = [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ];

        if ( isset( $candidate['isbn'] ) ) {
            $data['external_isbn'] = sanitize_text_field( (string) $candidate['isbn'] );
            $format[]              = '%s';
        }

        if ( isset( $candidate['source'] ) ) {
            $data['external_source'] = sanitize_text_field( (string) $candidate['source'] );
            $format[]                = '%s';
        }

        if ( isset( $candidate['score'] ) ) {
            $data['external_score'] = (float) $candidate['score'];
            $format[]               = '%f';
        }

        if ( isset( $candidate['method'] ) ) {
            $data['match_method'] = sanitize_text_field( (string) $candidate['method'] );
            $format[]             = '%s';
        }

        if ( isset( $candidate['matched_book_id'] ) ) {
            $data['matched_book_id'] = (int) $candidate['matched_book_id'];
            $format[]                = '%d';
        }

        if ( isset( $candidate['cover_url'] ) ) {
            $data['external_cover_url'] = esc_url_raw( (string) $candidate['cover_url'] );
            $format[]                   = '%s';
        }

        if ( isset( $candidate['cover_source'] ) ) {
            $data['external_cover_source'] = sanitize_text_field( (string) $candidate['cover_source'] );
            $format[]                      = '%s';
        }

        if ( null !== $raw_payload ) {
            $data['raw_response'] = $raw_payload;
            $format[]             = '%s';
        }

        $inserted = $wpdb->insert( $table_confirm, $data, $format );

        if ( ! $inserted ) {
            ++$skipped;
            continue;
        }

        $pending[] = [
            'id'     => (int) $wpdb->insert_id,
            'title'  => $title,
            'author' => $author,
            'year'   => null,
        ];
        ++$queued;
    }

    if ( ! empty( $in_shelf ) ) {
        $key   = 'pol_confirm_ephemeral_' . (int) $user_id;
        $prev  = get_transient( $key );
        $prev  = is_array( $prev ) ? $prev : [];
        $store = array_merge( $prev, $in_shelf );
        set_transient( $key, $store, 15 * MINUTE_IN_SECONDS );
    }

    $result = [
        'queued'   => $queued,
        'skipped'  => $skipped,
        'pending'  => $pending,
        'in_shelf' => $in_shelf,
    ];

    return apply_filters( 'politeia_chatgpt_after_queue_items', $result, $user_id, $candidates );
}

function normalize_text( $text ) {
    $text = (string) $text;
    $text = wp_strip_all_tags( $text );
    $text = html_entity_decode( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
    $text = preg_replace( '/\s+/u', ' ', $text );
    $text = trim( $text );

    return $text;
}

function title_author_hash( $title, $author ) {
    if ( class_exists( BookConfirmSchema::class ) ) {
        return BookConfirmSchema::compute_title_author_hash( $title, $author );
    }

    $title_normalized  = strtolower( remove_accents( trim( normalize_text( $title ) ) ) );
    $author_normalized = strtolower( remove_accents( trim( normalize_text( $author ) ) ) );

    $clean = ' ' . preg_replace( '/\s+/', ' ', $title_normalized . ' ' . $author_normalized ) . ' ';
    $clean = preg_replace( '/\b(el|la|los|las|un|una|unos|unas|de|del|y|e|a|en|the|of|and|to|for)\b/u', ' ', $clean );
    $clean = preg_replace( '/[^a-z0-9\s]/u', ' ', $clean );
    $tokens = array_values( array_filter( explode( ' ', preg_replace( '/\s+/', ' ', trim( $clean ) ) ) ) );
    sort( $tokens, SORT_STRING );

    return hash( 'sha256', implode( ' ', $tokens ) );
}

} // namespace Politeia\ChatGPT\BookDetection

namespace {
    if ( ! function_exists( 'politeia_chatgpt_queue_confirm_items' ) ) {
        function politeia_chatgpt_queue_confirm_items( $arg1, $arg2 = null, $arg3 = null, $arg4 = '' ) {
            return \Politeia\ChatGPT\BookDetection\queue_confirm_items( $arg1, $arg2, $arg3, $arg4 );
        }
    }

    if ( ! function_exists( 'politeia__normalize_text' ) ) {
        function politeia__normalize_text( $text ) {
            return \Politeia\ChatGPT\BookDetection\normalize_text( $text );
        }
    }

    if ( ! function_exists( 'politeia__title_author_hash' ) ) {
        function politeia__title_author_hash( $title, $author ) {
            return \Politeia\ChatGPT\BookDetection\title_author_hash( $title, $author );
        }
    }
}
