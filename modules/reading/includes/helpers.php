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

function prs_find_or_create_book( $title, $authors, $year = null, $attachment_id = null ) {
        global $wpdb;
        $table              = $wpdb->prefix . 'politeia_books';
        $authors_table      = $wpdb->prefix . 'politeia_authors';
        $book_authors_table = $wpdb->prefix . 'politeia_book_authors';

        $title = trim( wp_strip_all_tags( (string) $title ) );

        if ( is_string( $authors ) ) {
                $authors = array( $authors );
        } elseif ( is_object( $authors ) && method_exists( $authors, '__toString' ) ) {
                $authors = array( (string) $authors );
        }

        if ( ! is_array( $authors ) ) {
                $authors = array();
        }

        $clean_authors = array();
        foreach ( $authors as $author ) {
                $author = trim( wp_strip_all_tags( (string) $author ) );
                if ( '' !== $author ) {
                        $clean_authors[] = $author;
                }
        }

        $clean_authors = array_values( array_unique( $clean_authors ) );

        if ( '' === $title || empty( $clean_authors ) ) {
                return new WP_Error( 'prs_invalid_book', 'Missing title/authors' );
        }

        $normalized_title_raw = function_exists( 'politeia__normalize_text' ) ? politeia__normalize_text( $title ) : $title;
        $normalized_title_raw = $normalized_title_raw !== '' ? $normalized_title_raw : null;

        $normalized_title_for_hash = $normalized_title_raw ?: prs_basic_normalize_string( $title );

        $normalized_authors_for_column = array();
        $normalized_authors_for_hash   = array();
        $author_payloads               = array();

        foreach ( $clean_authors as $author_name ) {
                $normalized_for_column = function_exists( 'politeia__normalize_text' ) ? politeia__normalize_text( $author_name ) : $author_name;
                if ( '' !== $normalized_for_column ) {
                        $normalized_authors_for_column[] = $normalized_for_column;
                }

                $normalized_for_hash = prs_normalize_author_name( $author_name );
                if ( '' === $normalized_for_hash ) {
                        $normalized_for_hash = prs_basic_normalize_string( $author_name );
                }

                if ( '' !== $normalized_for_hash ) {
                        $normalized_authors_for_hash[] = $normalized_for_hash;
                }

                $author_payloads[] = array(
                        'name'       => $author_name,
                        'normalized' => $normalized_for_hash,
                );
        }

        $normalized_authors_for_hash = array_values( array_unique( $normalized_authors_for_hash ) );

        if ( empty( $normalized_authors_for_hash ) ) {
                $normalized_authors_for_hash[] = '';
        }

        $sorted_for_hash = $normalized_authors_for_hash;
        sort( $sorted_for_hash, SORT_STRING );

        if ( function_exists( 'politeia__title_author_hash' ) ) {
                $hash = politeia__title_author_hash( $title, $clean_authors );
        } else {
                $hash = hash( 'sha256', $normalized_title_for_hash . '|' . implode( '|', $sorted_for_hash ) );
        }

        if ( empty( $hash ) ) {
                $hash = hash( 'sha256', $normalized_title_for_hash . '|' . implode( '|', $sorted_for_hash ) );
        }

        $authors_string = implode( ', ', $clean_authors );
        $slug_source    = $title . '-' . implode( '-', $clean_authors ) . ( $year ? '-' . $year : '' );
        $slug           = sanitize_title( $slug_source );

        $book_id     = null;
        $needs_update = false;

        // Prefer lookup by unique hash to avoid duplicate key errors when slug differs.
        $existing = $wpdb->get_row(
                $wpdb->prepare(
                        "SELECT id, title_author_hash FROM {$table} WHERE title_author_hash = %s LIMIT 1",
                        $hash
                )
        );

        if ( $existing ) {
                $book_id = (int) $existing->id;
        } elseif ( $slug ) {
                // Fallback to slug match for legacy rows that might not yet have hashes populated.
                $existing_by_slug = $wpdb->get_row(
                        $wpdb->prepare(
                                "SELECT id, title_author_hash FROM {$table} WHERE slug = %s LIMIT 1",
                                $slug
                        )
                );

                if ( $existing_by_slug ) {
                        $book_id     = (int) $existing_by_slug->id;
                        $needs_update = true;
                }
        }

        $normalized_author_for_column = ! empty( $normalized_authors_for_column ) ? implode( ', ', $normalized_authors_for_column ) : null;

        if ( $book_id ) {
                if ( $needs_update ) {
                        $wpdb->update(
                                $table,
                                array(
                                        'title_author_hash' => $hash,
                                        'normalized_title'  => $normalized_title_raw,
                                        'normalized_author' => $normalized_author_for_column,
                                        'author'             => $authors_string,
                                        'updated_at'         => current_time( 'mysql' ),
                                ),
                                array( 'id' => $book_id ),
                                array( '%s', '%s', '%s', '%s', '%s' ),
                                array( '%d' )
                        );
                }

                prs_sync_book_author_links( $book_id, $author_payloads, $authors_table, $book_authors_table );

                return $book_id;
        }

        $inserted = $wpdb->insert(
                $table,
                array(
                        'title'               => $title,
                        'author'              => $authors_string,
                        'year'                => $year ? (int) $year : null,
                        'cover_attachment_id' => $attachment_id ? (int) $attachment_id : null,
                        'slug'                => $slug,
                        'normalized_title'    => $normalized_title_raw,
                        'normalized_author'   => $normalized_author_for_column,
                        'title_author_hash'   => $hash,
                        'created_at'          => current_time( 'mysql' ),
                        'updated_at'          => current_time( 'mysql' ),
                )
        );

        if ( false === $inserted ) {
                return new WP_Error( 'prs_insert_failed', $wpdb->last_error ?: 'Could not insert book.' );
        }

        $book_id = (int) $wpdb->insert_id;

        prs_sync_book_author_links( $book_id, $author_payloads, $authors_table, $book_authors_table );

        return $book_id;
}

function prs_sync_book_author_links( $book_id, array $author_payloads, $authors_table, $book_authors_table ) {
        global $wpdb;

        if ( empty( $author_payloads ) || ! prs_table_exists( $authors_table ) || ! prs_table_exists( $book_authors_table ) ) {
                return;
        }

        $order = 1;
        foreach ( $author_payloads as $payload ) {
                if ( empty( $payload['name'] ) ) {
                        $order++;
                        continue;
                }

                $normalized_lookup = isset( $payload['normalized'] ) ? (string) $payload['normalized'] : '';
                $name              = (string) $payload['name'];

                $author_id = null;
                if ( '' !== $normalized_lookup ) {
                        $author_id = $wpdb->get_var(
                                $wpdb->prepare(
                                        "SELECT id FROM {$authors_table} WHERE normalized_name = %s LIMIT 1",
                                        $normalized_lookup
                                )
                        );
                }

                if ( ! $author_id ) {
                        $author_id = $wpdb->get_var(
                                $wpdb->prepare(
                                        "SELECT id FROM {$authors_table} WHERE name = %s LIMIT 1",
                                        $name
                                )
                        );
                }

                if ( ! $author_id ) {
                        $now      = current_time( 'mysql' );
                        $inserted = $wpdb->insert(
                                $authors_table,
                                array(
                                        'name'            => $name,
                                        'normalized_name' => '' !== $normalized_lookup ? $normalized_lookup : null,
                                        'created_at'      => $now,
                                        'updated_at'      => $now,
                                ),
                                array( '%s', '%s', '%s', '%s' )
                        );

                        if ( false === $inserted ) {
                                $order++;
                                continue;
                        }

                        $author_id = (int) $wpdb->insert_id;
                }

                $wpdb->replace(
                        $book_authors_table,
                        array(
                                'book_id'       => (int) $book_id,
                                'author_id'     => (int) $author_id,
                                'display_order' => $order,
                        ),
                        array( '%d', '%d', '%d' )
                );

                $order++;
        }
}

function prs_table_exists( $table ) {
        global $wpdb;

        if ( '' === $table ) {
                return false;
        }

        static $cache = array();

        if ( array_key_exists( $table, $cache ) ) {
                return $cache[ $table ];
        }

        $exists = (bool) $wpdb->get_var(
                $wpdb->prepare(
                        'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s LIMIT 1',
                        $table
                )
        );

        $cache[ $table ] = $exists;

        return $exists;
}

function prs_basic_normalize_string( $value ) {
        $value = wp_strip_all_tags( (string) $value );
        $value = html_entity_decode( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
        $value = preg_replace( '/\s+/u', ' ', $value );
        $value = trim( $value );

        if ( function_exists( 'remove_accents' ) ) {
                $value = remove_accents( $value );
        }

        $value = mb_strtolower( $value, 'UTF-8' );

        return $value;
}

function prs_normalize_author_name( $name ) {
        $name = wp_strip_all_tags( (string) $name );
        $name = trim( $name );

        if ( '' === $name ) {
                return '';
        }

        if ( function_exists( 'remove_accents' ) ) {
                $name = remove_accents( $name );
        }

        $name = mb_strtolower( $name, 'UTF-8' );
        $name = preg_replace( "/[^a-z0-9\s\-\']+/u", ' ', $name );
        $name = preg_replace( '/\s+/u', ' ', $name );

        return trim( $name );
}

function prs_ensure_user_book( $user_id, $book_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'politeia_user_books';

	// Unique (user_id, book_id)
	$id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM {$table} WHERE user_id = %d AND book_id = %d LIMIT 1",
			$user_id,
			$book_id
		)
	);
	if ( $id ) {
		return (int) $id;
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
}
add_action( 'plugins_loaded', 'prs_maybe_create_loans_table' );

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
